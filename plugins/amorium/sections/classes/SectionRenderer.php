<?php namespace Amorium\Sections\Classes;

use Log;
use Config;
use Cms\Classes\Controller;
use Twig\Error\Error as TwigError;

/**
 * SectionRenderer renders the HTML of a section record as a Twig template
 * with the field values entered for it on the page available as "data".
 */
class SectionRenderer
{
    public static function render($section, $data = null, $index = null): string
    {
        $html = (string) $section?->html;
        if (trim($html) === '') {
            return '';
        }

        $controller = Controller::getController();
        if (!$controller) {
            return $html;
        }

        $vars = [
            'section' => [
                'id' => $section->id,
                'title' => $section->title,
                'slug' => $section->slug,
                'index' => (int) $index,
            ],
            'data' => is_array($data) ? $data : [],
        ];

        try {
            return $controller->getTwig()
                ->createTemplate($html, 'amorium-section-' . $section->id)
                ->render($vars);
        }
        catch (TwigError $ex) {
            if (Config::get('app.debug')) {
                throw $ex;
            }

            Log::error($ex);
            return '';
        }
    }
}
