<?php namespace AmoriumCms\Amorium\Classes;

use Tailor\Models\EntryRecord;

/**
 * Posts lets sections select posts. A post is referenced by its site root id,
 * which is shared by the rows of every site, so a selection made on one site
 * shows the same posts on the other sites.
 */
class Posts
{
    public const HANDLE = 'Amorium\Post';

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

        $posts = EntryRecord::inSection(static::HANDLE)
            ->applyVisibleFrontend()
            ->where(function ($query) use ($keys) {
                $query->whereIn('site_root_id', $keys)
                    ->orWhere(fn ($query) => $query->whereNull('site_root_id')->whereIn('id', $keys));
            })
            ->get()
            ->keyBy(fn ($post) => $post->site_root_id ?: $post->id);

        return collect($keys)->map(fn ($key) => $posts->get($key))->filter()->values();
    }
}
