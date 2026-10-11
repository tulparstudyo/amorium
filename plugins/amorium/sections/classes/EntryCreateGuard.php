<?php namespace Amorium\Sections\Classes;

use Backend;
use Flash;
use Request;
use Site;
use ApplicationException;
use Tailor\Classes\BlueprintIndexer;
use Tailor\Controllers\BulkActions;
use Tailor\Controllers\Entries;

/**
 * New entries of multisite Amorium blueprints can be created only while
 * the primary site is selected in the backend.
 */
class EntryCreateGuard
{
    public static function boot(): void
    {
        Entries::extend(function ($controller) {
            $controller->bindEvent('page.beforeDisplay', function () use ($controller) {
                return static::responseFor($controller);
            });
        });

        BulkActions::extend(function ($controller) {
            $controller->bindEvent('page.beforeDisplay', function () use ($controller) {
                return static::responseFor($controller);
            });
        });
    }

    /**
     * denies returns true when this blueprint is a multisite Amorium entry
     * and the backend edit site is not the primary site.
     */
    public static function denies($source): bool
    {
        return static::deniesHandle($source->handleSlug ?? null);
    }

    /**
     * deniesHandle
     */
    public static function deniesHandle(?string $handleSlug): bool
    {
        if (!$handleSlug || !str_starts_with($handleSlug, 'amorium-')) {
            return false;
        }

        if (!BlueprintIndexer::instance()->findSectionByHandle($handleSlug)?->useMultisite()) {
            return false;
        }

        $site = Site::getEditSite();

        return !$site || !$site->is_primary;
    }

    /**
     * responseFor blocks create, duplicate and import requests.
     */
    public static function responseFor($controller)
    {
        $target = static::tailorTarget();

        if (!$target || !static::deniesHandle($target['handle'])) {
            return null;
        }

        if (!static::requestCreatesRecord($controller, $target['controller'], $target['action'])) {
            return null;
        }

        if ($controller->getAjaxHandler()) {
            throw new ApplicationException(__(
                'New records can only be created while the primary site is active.'
            ));
        }

        Flash::error(__('New records can only be created while the primary site is active.'));

        return Backend::redirect('tailor/entries/'.$target['handle']);
    }

    /**
     * requestCreatesRecord
     */
    protected static function requestCreatesRecord($controller, string $controllerName, ?string $action): bool
    {
        if ($controller instanceof Entries) {
            $handler = $controller->getAjaxHandler();

            return $action === 'create'
                || $handler === 'onCreateBaseSections'
                || ($handler === 'onBulkAction' && post('action') === 'duplicate');
        }

        return $controller instanceof BulkActions && $controllerName === 'bulkactions' && $action === 'import';
    }

    /**
     * tailorTarget reads the entries or bulk-actions handle from the request path.
     * The match is not anchored to the start so a subdirectory install still resolves.
     */
    protected static function tailorTarget(): ?array
    {
        $path = trim(Request::path(), '/');

        if (!preg_match('#(?:^|/)tailor/(entries|bulkactions)/([^/]+)(?:/([^/]+))?#', $path, $matches)) {
            return null;
        }

        return [
            'controller' => $matches[1],
            'handle' => rawurldecode($matches[2]),
            'action' => isset($matches[3]) ? rawurldecode($matches[3]) : null,
        ];
    }
}
