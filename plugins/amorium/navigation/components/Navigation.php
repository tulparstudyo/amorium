<?php namespace Amorium\Navigation\Components;

use Request;
use Cms\Classes\ComponentBase;
use Cms\Classes\PageManager;
use Tailor\Models\EntryRecord;

/**
 * Navigation renders the links nested below a top level navigation record
 */
class Navigation extends ComponentBase
{
    /**
     * @var array|null itemsCache
     */
    protected $itemsCache;

    public function componentDetails()
    {
        return [
            'name' => 'Navigation',
            'description' => 'Displays the nested links of a menu, found by the slug of its top level record.'
        ];
    }

    public function defineProperties()
    {
        return [
            'slug' => [
                'title' => 'Menu Slug',
                'description' => 'Slug of the top level navigation record.',
                'type' => 'string',
                'default' => 'main-menu'
            ]
        ];
    }

    /**
     * items returns the nested links of the menu for the active site
     */
    public function items(): array
    {
        if ($this->itemsCache !== null) {
            return $this->itemsCache;
        }

        $menu = EntryRecord::inSection('Amorium\Navigation')
            ->applyVisibleFrontend()
            ->getNested()
            ->firstWhere('slug', $this->property('slug'));

        return $this->itemsCache = $menu ? $this->makeItems($menu->children) : [];
    }

    protected function makeItems($records): array
    {
        $currentUrl = rtrim(Request::url(), '/');
        $items = [];

        foreach ($records as $record) {
            $url = $this->makeUrl($record);
            $children = $record->children ? $this->makeItems($record->children) : [];

            $items[] = (object) [
                'title' => $record->title,
                'url' => $url,
                'type' => $record->link_type,
                'isExternal' => $record->link_type === 'external',
                'isActive' => $url !== '' && rtrim($url, '/') === $currentUrl,
                'isChildActive' => collect($children)->contains(fn($child) => $child->isActive || $child->isChildActive),
                'items' => $children
            ];
        }

        return $items;
    }

    protected function makeUrl($record): string
    {
        return match ($record->link_type) {
            'page' => $record->link_page ? PageManager::url($record->link_page) : '',
            'external' => (string) $record->link_url,
            'anchor' => $this->makeAnchorUrl($record),
            default => ''
        };
    }

    /**
     * makeAnchorUrl returns the anchor on the selected page, or on the home page
     * when no page is selected, so the link works from every page.
     */
    protected function makeAnchorUrl($record): string
    {
        $anchor = ltrim((string) $record->link_anchor, '#');
        if ($anchor === '') {
            return '';
        }

        $pageUrl = $record->link_page
            ? PageManager::url($record->link_page)
            : $this->controller->pageUrl('index');

        return $pageUrl . '#' . $anchor;
    }
}
