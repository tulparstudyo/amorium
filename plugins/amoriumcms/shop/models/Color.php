<?php namespace AmoriumCms\Shop\Models;

use Str;
use Site;
use Model;

/**
 * Color is a fixed property of a product group, like the brand.
 * Groups that share a common code are the colors of one product.
 */
class Color extends Model
{
    use \AmoriumCms\Shop\Models\Concerns\HasTranslations;
    use \AmoriumCms\Shop\Models\Concerns\BumpsCatalogCache;

    public $table = 'amoriumcms_shop_colors';

    protected $fillable = ['code', 'hex', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public $hasMany = [
        'translations' => [ColorTranslation::class, 'key' => 'color_id', 'delete' => true],
        'groups' => [ProductGroup::class, 'key' => 'color_id'],
    ];

    /**
     * isLabel tells whether an imported attribute name is the product color
     */
    public static function isLabel(string $name): bool
    {
        return in_array(mb_strtolower(trim($name)), ['renk', 'color', 'colour', 'цвет', 'اللون'], true);
    }

    /**
     * ensure finds a color by its name or creates one
     */
    public static function ensure(string $name, ?string $hex = null, array $locales = []): self
    {
        $name = trim($name);
        $color = static::whereHas('translations', fn ($query) => $query->where('name', $name))->first();

        if (!$color) {
            $color = new static;
            $color->code = static::uniqueCode(Str::slug($name) ?: 'color');
            $color->hex = static::hex($hex);
            $color->is_active = true;
            $color->sort_order = ((int) static::max('sort_order')) + 1;
            $color->save();
        } elseif (!$color->hex && $hex = static::hex($hex)) {
            $color->hex = $hex;
            $color->save();
        }

        $locales = $locales ?: array_filter([Site::getPrimarySite()?->hard_locale ?: 'tr']);

        foreach ($locales as $locale) {
            ColorTranslation::firstOrCreate(
                ['color_id' => $color->id, 'locale' => $locale],
                ['name' => $name]
            );
        }

        return $color->load('translations');
    }

    /**
     * hex keeps a #rrggbb value
     */
    public static function hex(?string $hex): ?string
    {
        $hex = trim((string) $hex);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? strtolower($hex) : null;
    }

    /**
     * uniqueCode
     */
    protected static function uniqueCode(string $base): string
    {
        $base = substr($base, 0, 64) ?: 'color';
        $code = $base;
        $i = 2;

        while (static::where('code', $code)->exists()) {
            $suffix = '_' . $i++;
            $code = substr($base, 0, 64 - strlen($suffix)) . $suffix;
        }

        return $code;
    }
}
