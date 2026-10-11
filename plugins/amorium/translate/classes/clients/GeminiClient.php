<?php namespace Amorium\Translate\Classes\Clients;

use Config;
use ApplicationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use Amorium\Translate\Classes\TranslatorClient;

/**
 * GeminiClient sends text to the Google Gemini generateContent API.
 */
class GeminiClient implements TranslatorClient
{
    public function __construct(protected string $model, protected array $options)
    {
    }

    public function translate(string $text, string $from, string $to): string
    {
        return trim($this->generate(
            "You are a professional translator. Translate the user's text from {$from} to {$to}. Preserve HTML tags, Markdown, placeholders such as :name and line breaks. Return only the translated text, without explanations or quotes.",
            $text
        ));
    }

    /**
     * translateBatch sends the texts as one JSON object and reads the translated object back.
     */
    public function translateBatch(array $texts, string $from, string $to): array
    {
        $content = $this->generate(
            "You are a professional translator. The user sends a JSON object. Translate every value from {$from} to {$to}. Keep the keys unchanged and return a JSON object with exactly the same keys. Preserve HTML tags, Markdown, placeholders such as :name and line breaks inside the values. Return only the JSON object.",
            json_encode((object) $texts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ['responseMimeType' => 'application/json']
        );

        $translated = json_decode($content, true);

        if (!is_array($translated)) {
            throw new ApplicationException('The translation API returned invalid JSON.');
        }

        $result = [];
        foreach ($texts as $key => $text) {
            $value = $translated[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $result[$key] = trim($value);
            }
        }

        return $result;
    }

    public function generate(string $instruction, string $text, array $config = []): string
    {
        $url = rtrim((string) ($this->options['api_url'] ?? ''), '/')."/models/{$this->model}:generateContent";

        $response = Http::withHeaders(['x-goog-api-key' => (string) Config::get('amorium.translate::api_key')])
            ->acceptJson()
            ->withOptions(['verify' => (bool) Config::get('amorium.translate::verify_ssl', true)])
            ->timeout((int) Config::get('amorium.translate::timeout', 120))
            ->retry(3, 2000, fn ($ex) => $ex instanceof RequestException && $ex->response->status() === 429)
            ->post($url, [
                'systemInstruction' => [
                    'parts' => [['text' => $instruction]],
                ],
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $text]]],
                ],
                'generationConfig' => ['temperature' => 0.2] + $config,
            ])
            ->throw();

        $content = $response->json('candidates.0.content.parts.0.text');

        if (!is_string($content) || trim($content) === '') {
            throw new ApplicationException('The translation API returned an empty response.');
        }

        return $content;
    }
}
