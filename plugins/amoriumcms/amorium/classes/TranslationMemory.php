<?php namespace AmoriumCms\Amorium\Classes;

use Db;
use Config;

/**
 * TranslationMemory stores every translation received from the API, so the
 * same text is never sent again for the same language pair and model. It is
 * a table instead of the cache, because clearing the cache must not lose paid
 * work. Changing the model starts a new memory, the old rows are kept.
 */
class TranslationMemory
{
    public const TABLE = 'amorium_translate_memory';

    /**
     * find returns the stored translations of the texts keyed by TextKey::hash.
     */
    public static function find(array $texts, string $from, string $to): array
    {
        $normalized = [];
        foreach ($texts as $text) {
            $normalized[TextKey::hash($text)] = TextKey::normalize($text);
        }

        if (!$normalized) {
            return [];
        }

        $found = [];

        foreach (array_chunk(array_keys($normalized), 500) as $keys) {
            $rows = Db::table(static::TABLE)
                ->whereIn('source_key', $keys)
                ->where('from_language', $from)
                ->where('to_language', $to)
                ->where('model', static::model())
                ->get(['source_key', 'source', 'translation']);

            foreach ($rows as $row) {
                if ($row->source === $normalized[$row->source_key]) {
                    $found[$row->source_key] = $row->translation;
                }
            }
        }

        return $found;
    }

    /**
     * store saves the translation of a text.
     */
    public static function store(string $text, string $translation, string $from, string $to): void
    {
        $translation = trim($translation);
        if ($translation === '') {
            return;
        }

        Db::table(static::TABLE)->updateOrInsert(
            [
                'source_key' => TextKey::hash($text),
                'from_language' => $from,
                'to_language' => $to,
                'model' => static::model(),
            ],
            [
                'source' => TextKey::normalize($text),
                'translation' => $translation,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /**
     * model returns the configured translation model.
     */
    protected static function model(): string
    {
        return (string) Config::get('amoriumcms.amorium::translate.model');
    }
}
