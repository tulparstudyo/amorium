<?php

use Amorium\Translate\Classes\Clients\GemmaClient;
use Amorium\Translate\Classes\Clients\GeminiClient;

return [
    'enabled' => env('TRANSLATE_ENABLED', false),
    'model' => env('TRANSLATE_MODEL', 'zongwei/gemma3-translator:1b'),
    'api_key' => env('TRANSLATE_API_KEY', ''),
    'verify_ssl' => env('TRANSLATE_VERIFY_SSL', true),
    'timeout' => env('TRANSLATE_TIMEOUT', 120),

    /*
     * The texts of one record are sent in a single request. Longer records
     * are split into requests of at most this many characters.
     */
    'batch_chars' => env('TRANSLATE_BATCH_CHARS', 20000),

    /*
     * The keys are model name prefixes, the first match is used.
     */
    'clients' => [
        'gemini' => [
            'class' => GeminiClient::class,
            'api_url' => env('TRANSLATE_GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        ],
        'zongwei/gemma3-translator' => [
            'class' => GemmaClient::class,
            'api_url' => env('TRANSLATE_GEMMA_API_URL', ''),
        ],
    ],
];
