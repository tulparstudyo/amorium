<?php namespace AmoriumCms\Amorium\Classes;

use Config;
use Throwable;

/**
 * BatchTranslator translates the texts of one record with as few requests as
 * possible. Texts already in the translation memory are not sent again, the
 * rest are split into chunks by length, each chunk is one request and the
 * results are merged into a map of source => translation. Only the core of a
 * text is translated, its surrounding whitespace is added back afterwards.
 */
class BatchTranslator
{
    /**
     * @var callable|null onError receives the exceptions of failed requests.
     */
    protected $onError;

    public function __construct(
        protected TranslatorClient $client,
        protected string $from,
        protected string $to,
        ?callable $onError = null
    ) {
        $this->onError = $onError;
    }

    /**
     * translate returns the translations keyed by source text. Texts that
     * could not be translated are missing from the result.
     */
    public function translate(array $texts): array
    {
        $texts = array_values(array_unique(array_filter($texts, fn ($text) => is_string($text) && trim($text) !== '')));

        $parts = [];
        $cores = [];
        foreach ($texts as $text) {
            [$lead, $core, $trail] = TextKey::split($text);
            $key = TextKey::hash($core);
            $parts[$text] = [$lead, $key, $trail];
            $cores[$key] ??= $core;
        }

        $translations = TranslationMemory::find($cores, $this->from, $this->to);
        $missing = array_values(array_diff_key($cores, $translations));

        foreach ($this->requestTranslations($missing) as $core => $translation) {
            $translations[TextKey::hash($core)] = $translation;
            TranslationMemory::store($core, $translation, $this->from, $this->to);
        }

        $map = [];
        foreach ($parts as $text => [$lead, $key, $trail]) {
            if (isset($translations[$key])) {
                $map[$text] = $lead.$translations[$key].$trail;
            }
        }

        return $map;
    }

    /**
     * requestTranslations sends the texts to the API and returns the
     * translations keyed by text.
     */
    protected function requestTranslations(array $texts): array
    {
        $map = [];

        foreach ($this->chunks($texts) as $chunk) {
            try {
                $result = $this->client->translateBatch($chunk, $this->from, $this->to);
            }
            catch (Throwable $ex) {
                $this->fail($ex);
                continue;
            }

            foreach ($chunk as $key => $text) {
                if (isset($result[$key])) {
                    $map[$text] = $result[$key];
                    continue;
                }

                try {
                    $map[$text] = $this->client->translate($text, $this->from, $this->to);
                }
                catch (Throwable $ex) {
                    $this->fail($ex);
                }
            }
        }

        return $map;
    }

    /**
     * chunks splits the texts into groups below the configured character limit.
     * A text longer than the limit is sent alone.
     */
    protected function chunks(array $texts): array
    {
        $limit = max(1000, (int) Config::get('amoriumcms.amorium::translate.batch_chars', 20000));
        $chunks = [];
        $chunk = [];
        $length = 0;

        foreach ($texts as $text) {
            $size = mb_strlen($text);

            if ($chunk && $length + $size > $limit) {
                $chunks[] = $chunk;
                $chunk = [];
                $length = 0;
            }

            $chunk[(string) (count($chunk) + 1)] = $text;
            $length += $size;
        }

        if ($chunk) {
            $chunks[] = $chunk;
        }

        return $chunks;
    }

    protected function fail(Throwable $ex): void
    {
        if ($this->onError) {
            ($this->onError)($ex);
        }
    }
}
