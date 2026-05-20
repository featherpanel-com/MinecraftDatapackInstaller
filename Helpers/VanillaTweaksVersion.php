<?php

/*
 * This file is part of FeatherPanel.
 *
 * Copyright (C) 2025 MythicalSystems Studios
 * Copyright (C) 2025 FeatherPanel Contributors
 * Copyright (C) 2025 Cassian Gherman (aka NaysKutzu)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See the LICENSE file or <https://www.gnu.org/licenses/>.
 */

namespace App\Addons\minecraftdatapackinstaller\Helpers;

use App\Cache\Cache;
use GuzzleHttp\Client;

class VanillaTweaksVersion
{
    /**
     * Vanilla Tweaks major.minor keys to verify against their CDN.
     * Includes 26.x (e.g. 26.1) and legacy 1.x releases.
     */
    private const SEED_VERSIONS = [
        '26.1',
        '1.21',
        '1.20',
        '1.19',
        '1.18',
        '1.17',
        '1.16',
        '1.15',
        '1.14',
        '1.13',
    ];

    /**
     * Normalize a Minecraft version to major.minor for Vanilla Tweaks.
     * e.g. 26.1.2 -> 26.1, 1.21.5 -> 1.21.
     */
    public static function normalize(?string $version): ?string
    {
        if ($version === null || trim($version) === '') {
            return null;
        }

        if (preg_match('/^(\d+\.\d+)/', trim($version), $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Pick the newest normalized version from a list of raw version strings.
     */
    public static function pickNewest(array $versions): ?string
    {
        $normalized = [];

        foreach ($versions as $version) {
            $value = self::normalize(is_string($version) ? $version : null);
            if ($value !== null) {
                $normalized[$value] = true;
            }
        }

        if ($normalized === []) {
            return null;
        }

        $list = array_keys($normalized);
        usort($list, fn (string $a, string $b) => version_compare($b, $a));

        return $list[0];
    }

    /**
     * @return string[] Newest-first list of Vanilla Tweaks-supported MC versions
     */
    public static function getAvailableVersions(Client $client): array
    {
        $cacheKey = 'vanillatweaks:mc_versions';
        $cached = Cache::get($cacheKey);

        if ($cached !== null && is_array($cached)) {
            return $cached;
        }

        $available = [];

        foreach (self::SEED_VERSIONS as $version) {
            try {
                $response = $client->get("/assets/resources/json/{$version}/dpcategories.json", [
                    'http_errors' => false,
                ]);

                if ($response->getStatusCode() === 200) {
                    $available[] = $version;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        usort($available, fn (string $a, string $b) => version_compare($b, $a));

        Cache::put($cacheKey, $available, 60);

        return $available;
    }

    public static function defaultVersion(Client $client): string
    {
        $available = self::getAvailableVersions($client);

        return $available[0] ?? '26.1';
    }

    /**
     * Resolve user-provided version to a supported Vanilla Tweaks key.
     */
    public static function resolve(?string $version, Client $client): ?string
    {
        $normalized = self::normalize($version);
        if ($normalized === null) {
            return null;
        }

        $available = self::getAvailableVersions($client);
        if (in_array($normalized, $available, true)) {
            return $normalized;
        }

        return null;
    }
}
