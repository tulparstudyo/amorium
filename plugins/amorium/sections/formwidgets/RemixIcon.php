<?php namespace Amorium\Sections\FormWidgets;

use Cache;
use Backend\Classes\FormWidgetBase;

/**
 * RemixIcon renders a searchable dropdown of the icon classes defined in
 * remixicon.css, each option is previewed with its icon.
 *
 *     icon:
 *         label: Icon
 *         type: remixicon
 */
class RemixIcon extends FormWidgetBase
{
    /**
     * @inheritDoc
     */
    protected $defaultAlias = 'remixicon';

    /**
     * @inheritDoc
     */
    public function render()
    {
        $field = clone $this->formField;
        $field->type = 'dropdown';
        $field->placeholder = $field->placeholder ?: 'Select an icon';
        $field->options(static::iconOptions());

        return $this->makePartial('~/modules/backend/widgets/form/partials/_field_dropdown.php', [
            'field' => $field,
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function loadAssets()
    {
        $this->addCss('/plugins/amorium/sections/formwidgets/remixicon/assets/fonts/remixicon.css');
    }

    /**
     * icons returns the icon classes defined in remixicon.css.
     */
    public static function icons(): array
    {
        $path = __DIR__ . '/remixicon/assets/fonts/remixicon.css';

        return Cache::rememberForever('amorium.sections.remixicons.' . @filemtime($path), function () use ($path) {
            preg_match_all('/^\.(ri-[a-z0-9-]+):+before\b/m', (string) @file_get_contents($path), $matches);

            return array_values(array_unique($matches[1]));
        });
    }

    protected static function iconOptions(): array
    {
        $options = [];
        foreach (static::icons() as $icon) {
            $options[$icon] = [substr($icon, 3), $icon];
        }

        return $options;
    }
}
