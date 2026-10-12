<?php namespace AmoriumCms\Amorium\FormWidgets;

use Yaml;
use Exception;
use Tailor\Models\EntryRecord;
use Backend\FormWidgets\NestedForm;
use October\Rain\Html\Helper as HtmlHelper;

/**
 * SectionFields renders the form fields defined on the selected section record,
 * the values are stored as an array on the page section item.
 */
class SectionFields extends NestedForm
{
    /**
     * @inheritDoc
     */
    protected $defaultAlias = 'sectionfields';

    /**
     * @var string|null sectionFieldName is the sibling field that selects the section
     */
    public $sectionFieldName = 'section';

    /**
     * @var string|null definitionKey identifies the definition used by the nested form
     */
    protected $definitionKey;

    /**
     * @var string|null definitionError when the definition cannot be parsed
     */
    protected $definitionError;

    /**
     * @inheritDoc
     */
    public function init()
    {
        $this->fillFromConfig(['sectionFieldName']);

        $section = $this->resolveSection();
        $this->form = $this->makeFormConfig($section);
        $this->definitionKey = $this->makeDefinitionKey($section);

        parent::init();
    }

    /**
     * @inheritDoc
     */
    public function render()
    {
        $this->syncWithSelectedSection();
        $this->prepareVars();

        return $this->makePartial('sectionfields', [
            'definitionError' => $this->definitionError,
            'hasFields' => $this->hasFields(),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getSaveValue($value)
    {
        $this->syncWithSelectedSection();

        return $this->hasFields() ? $this->formWidget->getSaveData() : [];
    }

    /**
     * @inheritDoc
     */
    protected function loadAssets()
    {
        $this->addCss('/modules/backend/formwidgets/nestedform/assets/css/nestedform.css');
    }

    /**
     * syncWithSelectedSection rebuilds the nested form when the posted section
     * differs from the one the widget was created with.
     */
    protected function syncWithSelectedSection(): void
    {
        $section = $this->resolveSection(true);
        $key = $this->makeDefinitionKey($section);

        if ($key === $this->definitionKey) {
            return;
        }

        $this->definitionError = null;
        $this->form = $this->makeFormConfig($section);
        $this->definitionKey = $key;
        $this->makeNestedFormWidget();
    }

    /**
     * resolveSection returns the selected section record, preferring posted data
     */
    protected function resolveSection(bool $preferPost = false)
    {
        if ($preferPost) {
            $siblingName = preg_replace('/\[[^\[\]]+\]$/', '[' . $this->sectionFieldName . ']', $this->getFieldName());
            $missing = new \stdClass;
            $posted = array_get(post(), implode('.', HtmlHelper::nameToArray($siblingName)), $missing);

            if ($posted !== $missing) {
                return $posted ? $this->findSection($posted) : null;
            }
        }

        return $this->model->{$this->sectionFieldName} ?? null;
    }

    /**
     * findSection looks up a posted section by the key the record finder posts,
     * which is site_root_id for multisite sections, not the id of the active site.
     */
    protected function findSection($key)
    {
        $keyName = 'id';

        if ($this->model && $this->model->hasRelation($this->sectionFieldName)) {
            $relation = $this->model->{$this->sectionFieldName}();
            if (method_exists($relation, 'getOwnerKeyName')) {
                $keyName = $relation->getOwnerKeyName();
            }
        }

        return EntryRecord::inSection('Amorium\Section')->where($keyName, $key)->first();
    }

    /**
     * makeFormConfig converts the YAML definition of a section to a form configuration
     */
    protected function makeFormConfig($section): array
    {
        $yaml = trim((string) ($section->fields_definition ?? ''));
        if ($yaml === '') {
            return ['fields' => []];
        }

        try {
            $config = Yaml::parse($yaml);
        }
        catch (Exception $ex) {
            $this->definitionError = $ex->getMessage();
            return ['fields' => []];
        }

        if (!is_array($config)) {
            return ['fields' => []];
        }

        if (!isset($config['fields']) && !isset($config['tabs']) && !isset($config['secondaryTabs'])) {
            $config = ['fields' => $config];
        }

        return $config;
    }

    protected function makeDefinitionKey($section): string
    {
        return $section ? $section->id . ':' . md5((string) $section->fields_definition) : '';
    }

    protected function hasFields(): bool
    {
        return !empty($this->form['fields'])
            || !empty($this->form['tabs']['fields'])
            || !empty($this->form['secondaryTabs']['fields']);
    }
}
