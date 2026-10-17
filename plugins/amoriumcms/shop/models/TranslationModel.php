<?php namespace AmoriumCms\Shop\Models;

use Model;

/**
 * TranslationModel is one locale row of a translated record.
 */
abstract class TranslationModel extends Model
{
    /**
     * @var bool timestamps
     */
    public $timestamps = false;

    /**
     * @var array guarded
     */
    protected $guarded = ['id'];

    /**
     * getForeignKeyName is the column that points to the translated record
     */
    abstract public function getForeignKeyName(): string;
}
