<?php namespace AmoriumCms\Amorium\Classes\Clients;

use Config;
use ApplicationException;
use Illuminate\Support\Facades\Http;
use AmoriumCms\Amorium\Classes\TranslatorClient;

/**
 * GemmaClient sends text to the OpenAI compatible chat completions API.
 */
class GemmaClient implements TranslatorClient
{
    public function __construct(protected string $model, protected array $options)
    {
    }

    public function translate(string $text, string $from, string $to): string
    {
        return $this->chat([
            ['role' => 'user', 'content' => "Translate from {$from} to {$to}: {$text}"],
        ]);
    }

    public function generate(string $instruction, string $text): string
    {
        return $this->chat([
            ['role' => 'system', 'content' => $instruction],
            ['role' => 'user', 'content' => $text],
        ]);
    }

    protected function chat(array $messages): string
    {
        $response = Http::withToken((string) Config::get('amoriumcms.amorium::translate.api_key'))
            ->acceptJson()
            ->withOptions(['verify' => (bool) Config::get('amoriumcms.amorium::translate.verify_ssl', true)])
            ->timeout((int) Config::get('amoriumcms.amorium::translate.timeout', 120))
            ->post((string) ($this->options['api_url'] ?? ''), [
                'model' => $this->model,
                'stream' => false,
                'messages' => $messages,
            ])
            ->throw();

        $content = $response->json('choices.0.message.content');

        if (!is_string($content) || trim($content) === '') {
            throw new ApplicationException('The translation API returned an empty response.');
        }

        return trim($content);
    }

    /**
     * translateBatch sends the texts one by one, the small model cannot keep a JSON structure.
     */
    public function translateBatch(array $texts, string $from, string $to): array
    {
        return array_map(fn ($text) => $this->translate((string) $text, $from, $to), $texts);
    }
}
