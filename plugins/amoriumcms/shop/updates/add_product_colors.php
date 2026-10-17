<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use AmoriumCms\Shop\Classes\VariantService;
use AmoriumCms\Shop\Models\Color;
use AmoriumCms\Shop\Models\ColorTranslation;
use AmoriumCms\Shop\Models\Product;
use AmoriumCms\Shop\Models\ProductGroup;
use AmoriumCms\Shop\Models\ProductGroupTranslation;
use AmoriumCms\Shop\Models\ProductImage;
use AmoriumCms\Shop\Models\ProductSpecValue;
use AmoriumCms\Shop\Models\Spec;
use AmoriumCms\Shop\Models\SpecOption;

/**
 * Color becomes a property of the product. The common code may be shared by its colors.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('amoriumcms_shop_colors', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 64)->unique();
            $table->string('hex', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('amoriumcms_shop_color_translations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('color_id')->unsigned();
            $table->string('locale', 16);
            $table->string('name');
            $table->unique(['color_id', 'locale'], 'shop_color_trans_unique');
        });

        Schema::table('amoriumcms_shop_product_groups', function (Blueprint $table) {
            $table->integer('color_id')->unsigned()->nullable()->after('brand_id')->index();
            $table->dropUnique(['code']);
            $table->index('code', 'shop_group_code');
        });

        $this->moveColorSpec();

        Schema::table('amoriumcms_shop_product_groups', function (Blueprint $table) {
            $table->unique(['code', 'color_id'], 'shop_group_code_color');
        });
    }

    public function down()
    {
        Schema::table('amoriumcms_shop_product_groups', function (Blueprint $table) {
            $table->dropUnique('shop_group_code_color');
            $table->dropIndex('shop_group_code');
            $table->unique('code');
            $table->dropColumn('color_id');
        });

        Schema::dropIfExists('amoriumcms_shop_color_translations');
        Schema::dropIfExists('amoriumcms_shop_colors');
    }

    /**
     * moveColorSpec copies the color spec onto the product and separates mixed colors
     */
    protected function moveColorSpec(): void
    {
        $spec = $this->colorSpec();

        if (!$spec) {
            return;
        }

        $optionIds = ProductSpecValue::where('spec_id', $spec->id)->whereNotNull('spec_option_id')->pluck('spec_option_id')->unique();
        $options = SpecOption::with('translations')->whereIn('id', $optionIds)->get()->keyBy('id');
        $colors = [];

        foreach ($options as $option) {
            $colors[$option->id] = $this->colorFromOption($option);
        }

        $groupIds = Product::whereHas('spec_values', fn ($query) => $query->where('spec_id', $spec->id))->pluck('group_id')->unique();
        $refresh = $groupIds->all();

        foreach (ProductGroup::with(['translations', 'categories'])->whereIn('id', $groupIds)->get() as $group) {
            $buckets = [];

            foreach (Product::with('spec_values')->where('group_id', $group->id)->orderBy('id')->get() as $product) {
                $optionId = $product->spec_values->firstWhere('spec_id', $spec->id)?->spec_option_id;
                $buckets[$optionId ?: 0][] = $product->id;
            }

            $colored = array_values(array_filter(array_keys($buckets), fn ($optionId) => $optionId && isset($colors[$optionId])));

            if (!$colored) {
                continue;
            }

            // The original page keeps the color of its first barcode
            $firstId = Product::where('group_id', $group->id)->min('id');
            $keeper = Product::with('spec_values')->find($firstId)?->spec_values->firstWhere('spec_id', $spec->id)?->spec_option_id;
            $keeper = in_array($keeper, $colored) ? $keeper : $colored[0];
            $colored = array_values(array_unique(array_merge([$keeper], $colored)));

            $group->color_id = $colors[$keeper]->id;
            $group->save();
            $this->releaseImages($group->id, $keeper);

            foreach (array_filter($colored, fn ($optionId) => $optionId !== $keeper) as $optionId) {
                $copy = $group->replicate();
                $copy->color_id = $colors[$optionId]->id;
                $copy->save();
                $this->copyTranslations($group, $copy, $options[$optionId]->code);
                $copy->categories()->sync($group->categories->pluck('id')->all());
                Product::whereIn('id', $buckets[$optionId])->update(['group_id' => $copy->id]);
                $refresh[] = $copy->id;
                ProductImage::where('group_id', $group->id)->where('spec_option_id', $optionId)->update([
                    'group_id' => $copy->id,
                    'spec_option_id' => null,
                ]);
            }
        }

        ProductSpecValue::where('spec_id', $spec->id)->delete();
        $spec->is_variant = false;
        $spec->is_filterable = false;
        $spec->save();

        foreach (array_unique($refresh) as $groupId) {
            $products = Product::with('spec_values.spec')->where('group_id', $groupId)->get();

            if ($products->isNotEmpty()) {
                VariantService::refreshGroup($products);
            }
        }
    }

    /**
     * colorSpec is the spec that was used as the product color
     */
    protected function colorSpec(): ?Spec
    {
        return Spec::with('translations')->get()->first(function ($spec) {
            if ($spec->code === 'color') {
                return true;
            }

            return $spec->translations->contains(fn ($row) => Color::isLabel((string) $row->name));
        });
    }

    /**
     * colorFromOption
     */
    protected function colorFromOption(SpecOption $option): Color
    {
        $label = $option->trans('label') ?: $option->code;
        $color = Color::whereHas('translations', fn ($query) => $query->where('name', $label))->first();

        if ($color) {
            if (!$color->hex && $option->color_hex) {
                $color->hex = Color::hex($option->color_hex);
                $color->save();
            }

            return $color;
        }

        $color = new Color;
        $color->code = $this->colorCode($option->code ?: $label);
        $color->hex = Color::hex($option->color_hex);
        $color->is_active = true;
        $color->sort_order = (int) $option->sort_order;
        $color->save();

        foreach ($option->translations as $row) {
            ColorTranslation::create([
                'color_id' => $color->id,
                'locale' => $row->locale,
                'name' => $row->label,
            ]);
        }

        if (!$color->translations()->exists()) {
            ColorTranslation::create([
                'color_id' => $color->id,
                'locale' => 'tr',
                'name' => $label,
            ]);
        }

        return $color;
    }

    /**
     * colorCode
     */
    protected function colorCode(string $base): string
    {
        $base = substr(\Str::slug($base) ?: 'color', 0, 64);
        $code = $base;
        $i = 2;

        while (Color::where('code', $code)->exists()) {
            $suffix = '_' . $i++;
            $code = substr($base, 0, 64 - strlen($suffix)) . $suffix;
        }

        return $code;
    }

    /**
     * copyTranslations gives the separated color its own slugs
     */
    protected function copyTranslations(ProductGroup $group, ProductGroup $copy, string $suffix): void
    {
        foreach ($group->translations as $row) {
            $slug = $this->uniqueSlug($row->locale, $row->slug . '-' . $suffix);

            ProductGroupTranslation::create([
                'group_id' => $copy->id,
                'locale' => $row->locale,
                'name' => $row->name,
                'slug' => $slug,
                'description' => $row->description,
                'meta_title' => $row->meta_title,
                'meta_description' => $row->meta_description,
            ]);
        }
    }

    /**
     * uniqueSlug
     */
    protected function uniqueSlug(string $locale, string $base): string
    {
        $base = substr(\Str::slug($base) ?: 'item', 0, 255);
        $slug = $base;
        $i = 2;

        while (ProductGroupTranslation::where('locale', $locale)->where('slug', $slug)->exists()) {
            $extra = '-' . $i++;
            $slug = substr($base, 0, 255 - strlen($extra)) . $extra;
        }

        return $slug;
    }

    /**
     * releaseImages shows the color images for every size of that product
     */
    protected function releaseImages(int $groupId, int $optionId): void
    {
        ProductImage::where('group_id', $groupId)->where('spec_option_id', $optionId)->update(['spec_option_id' => null]);
    }
};
