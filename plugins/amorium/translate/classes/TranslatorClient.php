<?php namespace Amorium\Translate\Classes;

/**
 * TranslatorClient is implemented by every translation API client.
 */
interface TranslatorClient
{
    public function translate(string $text, string $from, string $to): string;

    /**
     * translateBatch translates the texts and returns them with the same keys.
     * Keys missing from the result are treated as failed translations.
     */
    public function translateBatch(array $texts, string $from, string $to): array;

    /**
     * generate sends the text with a system instruction and returns the raw answer.
     */
    public function generate(string $instruction, string $text): string;
}
