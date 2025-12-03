<?php

namespace App\Addons\minecraftdatapackinstaller;

use App\Plugins\AppPlugin;

class MinecraftDatapackInstaller implements AppPlugin
{
    /**
     * @inheritDoc
     */
    public static function processEvents(\App\Plugins\PluginEvents $event): void
    {
        // Process plugin events here
        // Example: $event->on('app.boot', function() { ... });
    }

    /**
     * @inheritDoc
     */
    public static function pluginInstall(): void
    {
        // Plugin installation logic
        // Create tables, directories, etc.
    }

    /**
     * @inheritDoc
     */
    public static function pluginUpdate(?string $oldVersion, ?string $newVersion): void
    {
        // Plugin update logic
        // Migrate data, update configurations, etc.
        // $oldVersion contains the previous version (e.g., '1.0.0')
        // $newVersion contains the new version being installed (e.g., '1.0.1')
    }

    /**
     * @inheritDoc
     */
    public static function pluginUninstall(): void
    {
        // Plugin uninstallation logic
        // Clean up tables, files, etc.
    }
}