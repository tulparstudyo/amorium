<?php namespace AmoriumCms\Amorium\FormWidgets;

use Backend\Classes\FormWidgetBase;

/**
 * FontPicker lets the user pick a Google Font family with jsfontpicker
 * (https://github.com/wipeautcrafter/jsfontpicker). The family name is saved.
 *
 *     body_font:
 *         label: Body Font
 *         type: fontpicker
 *         allowEmpty: true
 *         placeholder: Same as body font
 */
class FontPicker extends FormWidgetBase
{
    /**
     * @var bool allowEmpty shows a clear button in the picker
     */
    public $allowEmpty = false;

    /**
     * @var string|null placeholder shown when no font is selected
     */
    public $placeholder;

    /**
     * @inheritDoc
     */
    protected $defaultAlias = 'fontpicker';

    /**
     * @inheritDoc
     */
    public function init()
    {
        $this->fillFromConfig([
            'allowEmpty',
            'placeholder',
        ]);
    }

    /**
     * @inheritDoc
     */
    public function render()
    {
        $this->vars['name'] = $this->getFieldName();
        $this->vars['value'] = (string) $this->getLoadValue();
        $this->vars['allowEmpty'] = $this->allowEmpty;
        $this->vars['placeholder'] = __($this->placeholder ?: 'Pick a font...');
        $this->vars['field'] = $this->formField;

        return $this->makePartial('fontpicker');
    }

    /**
     * @inheritDoc
     */
    public function getSaveValue($value)
    {
        $value = trim((string) $value);

        return preg_match('/^[A-Za-z0-9 ]+$/', $value) ? $value : null;
    }

    /**
     * @inheritDoc
     */
    protected function loadAssets()
    {
        $this->addCss('/plugins/amoriumcms/amorium/formwidgets/fontpicker/assets/fontpicker.min.css');
        $this->addCss('/plugins/amoriumcms/amorium/formwidgets/fontpicker/assets/fontpicker-field.css');
        $this->addJs('/plugins/amoriumcms/amorium/formwidgets/fontpicker/assets/fontpicker.iife.min.js');
        $this->addJs('/plugins/amoriumcms/amorium/formwidgets/fontpicker/assets/fontpicker-field.js');
    }
}
