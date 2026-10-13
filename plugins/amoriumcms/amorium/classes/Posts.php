<?php namespace AmoriumCms\Amorium\Classes;

use Site;
use Event;
use Cms\Classes\Controller;
use Tailor\Models\EntryRecord;
use Illuminate\Support\Collection;

/**
 * Posts lets sections select, list and show posts. A post is referenced by its
 * site root id, which is shared by the rows of every site, so a selection made
 * on one site shows the same posts on the other sites.
 *
 * A page that contains the blog detail section shows the post whose slug or id
 * follows the page address, for example /news/my-first-post.
 */
class Posts
{
    public const HANDLE = 'Amorium\Post';

    public const PAGE_HANDLE = 'Amorium\Page';

    public const LIST_SECTION = 'blog-list';

    public const DETAIL_SECTION = 'blog-detail';

    /**
     * @var \Tailor\Models\EntryRecord|null current post shown by the blog detail section
     */
    protected static $current;

    /**
     * @var array sectionPages cache of the first page holding a section, by site and section slug
     */
    protected static $sectionPages = [];

    /**
     * options returns the posts of the active site for a taglist or dropdown field.
     */
    public static function options(): array
    {
        return EntryRecord::inSection(static::HANDLE)
            ->orderBy('published_at', 'desc')
            ->get()
            ->filter(fn ($post) => trim((string) $post->title) !== '')
            ->mapWithKeys(fn ($post) => [(string) ($post->site_root_id ?: $post->id) => $post->title])
            ->all();
    }

    /**
     * selected returns the visible posts of the active site in the given order.
     */
    public static function selected($keys)
    {
        $keys = array_values(array_filter(array_map('intval', (array) $keys)));
        if (!$keys) {
            return collect();
        }

        $posts = static::query()
            ->where(function ($query) use ($keys) {
                $query->whereIn('site_root_id', $keys)
                    ->orWhere(fn ($query) => $query->whereNull('site_root_id')->whereIn('id', $keys));
            })
            ->get()
            ->keyBy(fn ($post) => $post->site_root_id ?: $post->id);

        return collect($keys)->map(fn ($key) => $posts->get($key))->filter()->values();
    }

    /**
     * paginate returns the visible posts of the active site, newest first, for the
     * page number in the "page" query parameter.
     */
    public static function paginate($perPage = null)
    {
        $perPage = max(1, (int) $perPage ?: 9);

        $paginator = static::query()
            ->orderBy('published_at', 'desc')
            ->paginate($perPage);

        if ($paginator->currentPage() > 1 && $paginator->isEmpty()) {
            abort(404);
        }

        return $paginator;
    }

    /**
     * current returns the post shown by the blog detail section.
     */
    public static function current()
    {
        return static::$current;
    }

    /**
     * recent returns the newest posts other than the given one.
     */
    public static function recent($post = null, $limit = 4)
    {
        return static::otherPosts($post)->limit((int) $limit)->get();
    }

    /**
     * related returns the newest posts sharing a category with the given one.
     */
    public static function related($post, $limit = 3)
    {
        $categoryIds = $post ? $post->categories->modelKeys() : [];
        if (!$categoryIds) {
            return collect();
        }

        return static::otherPosts($post)
            ->whereHas('categories', fn ($query) => $query->whereIn($query->getModel()->getQualifiedKeyName(), $categoryIds))
            ->limit((int) $limit)
            ->get();
    }

    /**
     * url returns the address of a post on the page holding the blog detail section,
     * or an empty string when no page holds it.
     */
    public static function url($post): string
    {
        $controller = Controller::getController();
        $page = static::sectionPage(static::DETAIL_SECTION);
        if (!$post || !$controller || !$page) {
            return '';
        }

        return (string) $controller->pageUrl('page', ['fullslug' => $page->fullslug . '/' . $post->slug]);
    }

    /**
     * listUrl returns the address of the page holding the blog list section,
     * or an empty string when no page holds it.
     */
    public static function listUrl(): string
    {
        $controller = Controller::getController();
        $page = static::sectionPage(static::LIST_SECTION);
        if (!$controller || !$page) {
            return '';
        }

        if ($page->fullslug === 'home-page') {
            return (string) $controller->pageUrl('index');
        }

        return (string) $controller->pageUrl('page', ['fullslug' => $page->fullslug]);
    }

    /**
     * resolveDetail finds the page and the post of an address made of the full slug
     * of a page holding the blog detail section and the slug or id of a post.
     * Returns the page when both are found and remembers the post as the current one.
     */
    public static function resolveDetail($fullslug)
    {
        $fullslug = trim((string) $fullslug, '/');
        $position = strrpos($fullslug, '/');
        if ($position === false) {
            return null;
        }

        $page = EntryRecord::inSection(static::PAGE_HANDLE)
            ->applyVisibleFrontend()
            ->where('fullslug', substr($fullslug, 0, $position))
            ->first();

        if (!$page || !static::hasSection($page, static::DETAIL_SECTION)) {
            return null;
        }

        $post = static::find(substr($fullslug, $position + 1));
        if (!$post) {
            return null;
        }

        static::$current = $post;
        static::translateSitePicker($page, $post);

        return $page;
    }

    /**
     * find returns the visible post of the active site with the given slug, or with
     * the given id or site root id.
     */
    protected static function find(string $key)
    {
        if ($key === '') {
            return null;
        }

        if ($post = static::query()->where('slug', $key)->first()) {
            return $post;
        }

        if (!ctype_digit($key)) {
            return null;
        }

        return static::query()
            ->where(fn ($query) => $query->where('id', $key)->orWhere('site_root_id', $key))
            ->first();
    }

    /**
     * sectionPage returns the first visible page of the active site holding the given section.
     */
    protected static function sectionPage(string $slug)
    {
        $key = (Site::getSiteIdFromContext() ?: 0) . ':' . $slug;

        if (!array_key_exists($key, static::$sectionPages)) {
            static::$sectionPages[$key] = EntryRecord::inSection(static::PAGE_HANDLE)
                ->applyVisibleFrontend()
                ->get()
                ->first(fn ($page) => static::hasSection($page, $slug));
        }

        return static::$sectionPages[$key];
    }

    /**
     * hasSection checks if an enabled section with the given slug is placed on the page.
     */
    protected static function hasSection($page, string $slug): bool
    {
        foreach ($page->sections ?: [] as $item) {
            $section = $item->section;
            if ($section instanceof Collection) {
                $section = $section->first();
            }

            if (!$item->is_disabled && $section && $section->slug === $slug) {
                return true;
            }
        }

        return false;
    }

    /**
     * translateSitePicker points the language links to the same post on the other sites.
     */
    protected static function translateSitePicker($page, $post)
    {
        if (!$page->isMultisiteEnabled() || !$post->isMultisiteEnabled()) {
            return;
        }

        Event::listen('cms.sitePicker.overrideParams', function ($cmsPage, $params, $currentSite, $proposedSite) use ($page, $post) {
            if ($cmsPage->baseFileName !== 'page') {
                return;
            }

            $otherPage = $page->findForSite($proposedSite->id);
            $otherPost = $post->findForSite($proposedSite->id);
            if (!$otherPage || !$otherPost) {
                return;
            }

            return array_merge($params, ['fullslug' => $otherPage->fullslug . '/' . $otherPost->slug]);
        });
    }

    /**
     * otherPosts returns a query of the visible posts other than the given one, newest first.
     */
    protected static function otherPosts($post)
    {
        $query = static::query()->orderBy('published_at', 'desc');

        if ($post) {
            $query->where($post->getQualifiedKeyName(), '<>', $post->getKey());
        }

        return $query;
    }

    /**
     * query returns a query of the visible posts of the active site.
     */
    protected static function query()
    {
        return EntryRecord::inSection(static::HANDLE)->applyVisibleFrontend();
    }
}
