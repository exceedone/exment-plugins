<?php

namespace App\Plugins\ExmentGroupware;

/**
 * Adds "Office で開く" to the page of a Word, Excel or PowerPoint file of the
 * file server (core: Services\FileServer\Preview::registerOpener). On an
 * Exment without the file server, nothing is added.
 */
class OfficeOpener
{
    public const KEY = 'exment_groupware_office';

    // a string, not ::class: an Exment without the file server does not have the class
    public const PREVIEW_CLASS = 'Exceedone\Exment\Services\FileServer\Preview';

    public static function fileServerAvailable(): bool
    {
        return class_exists(static::PREVIEW_CLASS) && method_exists(static::PREVIEW_CLASS, 'registerOpener');
    }

    /**
     * Whether the login user may use the file server: Exment has it, and
     * (with Feature management) it is used and is for this user.
     */
    public static function fileServerUsed(): bool
    {
        return static::fileServerAvailable() && FeatureGate::allowed(FeatureGate::FILE_SERVER);
    }

    /**
     * @param \Exceedone\Exment\Model\Plugin $plugin
     */
    public static function register($plugin): void
    {
        if (!static::fileServerAvailable()) {
            return;
        }
        try {
            call_user_func([static::PREVIEW_CLASS, 'registerOpener'], static::KEY, function ($node) use ($plugin) {
                return static::opener($plugin, $node);
            });
        } catch (\Throwable $e) {
            \Log::warning('[ExmentGroupware] could not add the Office button: ' . $e->getMessage());
        }
    }

    /**
     * The button for a file, or null when it is not a file the editor opens.
     *
     * @param \Exceedone\Exment\Model\Plugin $plugin
     * @param mixed $node \Exceedone\Exment\Model\FileNode
     * @return array{label: string, url: string, icon: string, embed_url: string}|null
     */
    public static function opener($plugin, $node): ?array
    {
        if (!is_object($node) || !method_exists($node, 'isFile') || !$node->isFile()) {
            return null;
        }
        if (!IbisOffice::opens(strval($node->name))) {
            return null;
        }
        if (!FeatureGate::allowed(FeatureGate::OFFICE)) {
            return null;
        }
        $office = new IbisOffice($plugin);
        if (!$office->isAvailable()) {
            // ISS02827: a system administrator learns, on the file itself, that it could be
            // seen and edited here; the others see the page as before
            $user = \Exment::user();
            if ($office->customUrl() !== null || !isset($user) || !$user->hasPermission(\Exceedone\Exment\Enums\Permission::SYSTEM)) {
                return null;
            }
            return [
                'label' => 'ibisOffice を入れる',
                'url' => $plugin->getFullUrl(),
                'icon' => 'fa-download',
                'embed_url' => $plugin->getFullUrl('office-missing'),
            ];
        }
        return [
            'label' => 'Office で開く',
            'url' => $plugin->getFullUrl('office', intval($node->workspace_id), intval($node->id)),
            'icon' => 'fa-pencil-square-o',
            // shown read only in the preview of the file page
            'embed_url' => $plugin->getFullUrl('office', intval($node->workspace_id), intval($node->id)) . '?view=1',
        ];
    }
}
