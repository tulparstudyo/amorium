<?php namespace Amorium\Form\Classes;

use App;
use Session;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Amorium\Form\Models\Submission;

/**
 * SubmitForm handles the POST request of a Form section.
 */
class SubmitForm
{
    protected const MAX_ATTEMPTS = 5;

    protected const DECAY_SECONDS = 60;

    public function __invoke(Request $request)
    {
        $payload = FormDefinition::decode($request->input('_form'));

        // October only loads plugin JSON translations in the backend
        app('translator')->addJsonPath(plugins_path('amorium/form/lang'));

        if ($payload && !empty($payload['locale'])) {
            App::setLocale($payload['locale']);
        }

        if (!hash_equals((string) Session::token(), (string) $request->input('_token'))) {
            return $this->respond($request, 419, __("The page has expired, please refresh it and try again."));
        }

        if (!$payload) {
            return $this->respond($request, 400, __("This form is no longer valid, please refresh the page and try again."));
        }

        // Bots fill the hidden "website" field, pretend the message was sent
        if (trim((string) $request->input('website')) !== '') {
            return $this->respond($request, 200);
        }

        $limiterKey = 'amorium-form:' . $request->ip();
        if (RateLimiter::tooManyAttempts($limiterKey, static::MAX_ATTEMPTS)) {
            return $this->respond($request, 429, __("Too many submissions, please try again in a minute."));
        }

        [$values, $errors] = $this->validate($payload['fields'], $request);

        if ($errors) {
            return $this->respond($request, 422, __("Please check the highlighted fields."), $errors);
        }

        RateLimiter::hit($limiterKey, static::DECAY_SECONDS);

        Submission::create([
            'site_id' => $payload['site'] ?? null,
            'section_id' => $payload['section'] ?? null,
            'form_name' => mb_substr((string) ($payload['name'] ?? ''), 0, 255),
            'data' => $values,
            'page_url' => mb_substr((string) ($payload['page'] ?? ''), 0, 2048),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
        ]);

        return $this->respond($request, 200);
    }

    /**
     * validate returns the stored values and the errors keyed by field name.
     */
    protected function validate(array $fields, Request $request): array
    {
        $values = [];
        $errors = [];

        foreach ($fields as $field) {
            $name = $field['name'] ?? '';
            $type = $field['type'] ?? 'text';
            $options = $field['options'] ?? [];
            $input = $request->input($name);
            $error = null;

            if ($type === 'checkbox' && $options) {
                $value = array_values(array_intersect($options, array_map('strval', (array) $input)));
                $isEmpty = !$value;
            }
            elseif ($type === 'checkbox') {
                $value = (bool) $input;
                $isEmpty = !$value;
            }
            else {
                $value = is_scalar($input) ? trim((string) $input) : '';
                $isEmpty = $value === '';
            }

            if ($isEmpty) {
                if (!empty($field['required'])) {
                    $error = __("This field is required.");
                }
            }
            elseif ($type === 'email' && Validator::make(['v' => $value], ['v' => 'email'])->fails()) {
                $error = __("Please enter a valid email address.");
            }
            elseif ($type === 'number' && !is_numeric($value)) {
                $error = __("Please enter a number.");
            }
            elseif (in_array($type, ['select', 'radio'], true) && !in_array($value, $options, true)) {
                $error = __("Please choose one of the options.");
            }
            elseif (is_string($value) && mb_strlen($value) > ($type === 'textarea' ? 5000 : 255)) {
                $error = __("This value is too long.");
            }

            if ($error) {
                $errors[$name] = $error;
            }

            $values[] = [
                'label' => $field['label'] ?? $name,
                'type' => $type,
                'value' => $value,
            ];
        }

        return [$values, $errors];
    }

    /**
     * respond returns JSON to the section script and redirects back otherwise.
     */
    protected function respond(Request $request, int $status, ?string $message = null, array $errors = [])
    {
        if ($request->expectsJson()) {
            return response()->json(array_filter([
                'message' => $message,
                'errors' => $errors ?: null,
            ]), $status);
        }

        return redirect()->back();
    }
}
