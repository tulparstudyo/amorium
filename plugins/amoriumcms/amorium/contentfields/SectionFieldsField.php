<?php namespace AmoriumCms\Amorium\ContentFields;

use Tailor\ContentFields\FallbackField;

/**
 * SectionFieldsField stores the values of the fields defined on a section record
 */
class SectionFieldsField extends FallbackField
{
    /**
     * extendModelObject will extend the record model.
     */
    public function extendModelObject($model)
    {
        $model->addJsonable($this->fieldName);
    }

    /**
     * extendDatabaseTable adds any required columns to the database.
     */
    public function extendDatabaseTable($table)
    {
        $table->mediumText($this->fieldName)->nullable();
    }
}
