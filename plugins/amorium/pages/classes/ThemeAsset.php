<?php namespace Amorium\Pages\Classes;

use Cms\Classes\Theme;
use Cms\Classes\Controller;

/**
 * ThemeAsset adds the file modification time to theme asset URLs so browsers
 * load the new file after every build.
 */
class ThemeAsset
{
    /**
     * url is used in markup as 'assets/css/theme.css'|themeAsset
     */
    public static function url($path): string
    {
        $path = ltrim((string) $path, '/');
        $controller = Controller::getController() ?: new Controller;
        $url = $controller->themeUrl($path);

        $theme = Theme::getActiveTheme();
        $file = $theme ? $theme->getPath() . '/' . $path : null;

        return $file && is_file($file) ? $url . '?v=' . filemtime($file) : $url;
    }
}
