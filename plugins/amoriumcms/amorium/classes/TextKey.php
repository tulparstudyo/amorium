<?php namespace AmoriumCms\Amorium\Classes;

use Normalizer;

/**
 * TextKey normalizes texts so that differences which do not change the
 * translation, such as surrounding or repeated spaces, share one key.
 * Line breaks are kept, because some fields are split into lines.
 */
class TextKey
{
    /**
     * split returns the leading whitespace, the core text and the trailing whitespace.
     */
    public static function split(string $text): array
    {
        if (!preg_match('/^(\s*)(.*?)(\s*)$/su', $text, $matches)) {
            return ['', $text, ''];
        }

        return [$matches[1], $matches[2], $matches[3]];
    }

    /**
     * normalize returns the text in the form used for comparing and hashing.
     */
    public static function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r", '&nbsp;', "\u{00A0}"], ["\n", "\n", ' ', ' '], $text);

        if (class_exists(Normalizer::class)) {
            $text = Normalizer::normalize($text, Normalizer::FORM_C) ?: $text;
        }

        $text = preg_replace('/[^\S\n]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * hash returns the key of the normalized text.
     */
    public static function hash(string $text): string
    {
        return hash('xxh128', static::normalize($text));
    }
}
