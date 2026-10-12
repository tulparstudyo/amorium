<?php namespace AmoriumCms\Amorium\Classes;

use Site;
use Event;
use Config;
use Locale;
use ApplicationException;
use Tailor\Models\EntryRecord;
use Tailor\Controllers\Entries;

/**
 * ContentReviewer adds the "Review with AI" button to the rich editor toolbar
 * of the supported blueprints. The button sends the editor content to the AI,
 * which fixes spelling and grammar, improves SEO and returns the new content.
 */
class ContentReviewer
{
    /**
     * @var array BLUEPRINTS lists the blueprint uuids with the button.
     */
    public const BLUEPRINTS = ['amorium_post'];

    public static function boot(): void
    {
        if (!Config::get('amoriumcms.amorium::translate.enabled')) {
            return;
        }

        Event::listen('backend.form.extendFields', function ($widget) {
            if (
                $widget->isNested ||
                !$widget->getController() instanceof Entries ||
                !$widget->model instanceof EntryRecord ||
                !in_array($widget->model->blueprint_uuid, static::BLUEPRINTS)
            ) {
                return;
            }

            $widget->getController()->addJs('/plugins/amoriumcms/amorium/assets/js/content-review.js', [
                'data-label' => __("Review with AI: fixes spelling and grammar, improves SEO"),
                'data-text-working' => __("The content is being reviewed by AI..."),
                'data-text-done' => __("The content was reviewed and updated by AI."),
                'data-text-empty' => __("There is no content to review."),
            ]);
        });

        Entries::extend(function ($controller) {
            $controller->addDynamicMethod('onReviewContent', function () use ($controller) {
                $source = (fn () => $this->activeSource)->call($controller);
                if (!$source || !in_array($source->uuid, static::BLUEPRINTS)) {
                    throw new ApplicationException(__("Access denied."));
                }

                return ['content' => static::review((string) post('content'), (string) post('title'))];
            });
        });
    }

    /**
     * review returns the corrected and SEO optimized HTML of the content.
     */
    public static function review(string $content, string $title = ''): string
    {
        if (trim(strip_tags($content, '<img><iframe><video>')) === '') {
            throw new ApplicationException(__("There is no content to review."));
        }

        @set_time_limit(0);

        $site = Site::getEditSite();
        $locale = (string) ($site->hard_locale ?? $site->locale ?? '');
        $language = Locale::getDisplayLanguage($locale, 'en') ?: $locale;

        $instruction = implode(' ', [
            "You are a professional {$language} editor and SEO copywriter.",
            "The user sends the HTML body of a blog post written in {$language}.",
            "Review and rewrite it, keeping the same language:",
            "1. Fix all spelling mistakes.",
            "2. Fix all grammar and punctuation mistakes.",
            "3. Make the content SEO friendly: use a logical heading hierarchy with h2 and h3 (never h1, the post title is the h1),",
            "keep paragraphs short and readable, use the main topic keywords naturally without keyword stuffing,",
            "use descriptive link texts and add a meaningful alt attribute to images without one.",
            "Keep the meaning, facts, tone, links, images, embeds, tables and the existing HTML structure.",
            "Do not invent new information and do not add a title, summary or comments about your changes.",
            "Return only the HTML, without Markdown code fences or explanations.",
        ]);

        $text = trim($title) !== ''
            ? "Post title: {$title}\n\nHTML:\n{$content}"
            : $content;

        $result = trim(TranslatorFactory::make()->generate($instruction, $text));
        $result = preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $result);

        if (trim(strip_tags($result)) === '') {
            throw new ApplicationException(__("The AI returned an empty response."));
        }

        return $result;
    }
}
