<?php

namespace App\Core\Applications;

class AppRegistry
{
    public static function all(): array
    {
        $modules = config('rudimah.modules', []);
        $manifests = [];

        foreach ($modules as $mod) {
            $manifests[] = new AppManifest(
                key: $mod['key'],
                slug: $mod['slug'],
                name: $mod['name'],
                description: $mod['description'],
                enabled: $mod['enabled'] ?? false,
            );
        }

        return $manifests;
    }

    public static function find(string $key): ?AppManifest
    {
        foreach (self::all() as $manifest) {
            if ($manifest->key === $key || $manifest->slug === $key) {
                return $manifest;
            }
        }

        return null;
    }
}
