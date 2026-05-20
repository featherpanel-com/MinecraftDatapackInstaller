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

use App\App;
use App\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;
use App\Addons\minecraftdatapackinstaller\Controllers\VanillaTweaksController;

return function (RouteCollection $routes): void {
    App::getInstance(true)->registerServerRoute(
        $routes,
        'session-server-addons-datapackinstaller-detect-version',
        '/api/user/servers/{uuidShort}/addons/datapackinstaller/detect-version',
        function (Request $request, array $args) {
            $uuidShort = $args['uuidShort'] ?? null;
            if (!$uuidShort) {
                return ApiResponse::error('Missing or invalid UUID short', 'INVALID_UUID_SHORT', 400);
            }

            return (new VanillaTweaksController())->detectVersion($request, $uuidShort);
        },
        'uuidShort',
        ['GET']
    );

    App::getInstance(true)->registerServerRoute(
        $routes,
        'session-server-addons-datapackinstaller-versions',
        '/api/user/servers/{uuidShort}/addons/datapackinstaller/versions',
        function (Request $request, array $args) {
            $uuidShort = $args['uuidShort'] ?? null;
            if (!$uuidShort) {
                return ApiResponse::error('Missing or invalid UUID short', 'INVALID_UUID_SHORT', 400);
            }

            return (new VanillaTweaksController())->getMcVersions($request, $uuidShort);
        },
        'uuidShort',
        ['GET']
    );

    App::getInstance(true)->registerServerRoute(
        $routes,
        'session-server-addons-datapackinstaller-worlds',
        '/api/user/servers/{uuidShort}/addons/datapackinstaller/worlds',
        function (Request $request, array $args) {
            $uuidShort = $args['uuidShort'] ?? null;
            if (!$uuidShort) {
                return ApiResponse::error('Missing or invalid UUID short', 'INVALID_UUID_SHORT', 400);
            }

            return (new VanillaTweaksController())->getWorlds($request, $uuidShort);
        },
        'uuidShort',
        ['GET']
    );

    App::getInstance(true)->registerServerRoute(
        $routes,
        'session-server-addons-datapackinstaller-packs',
        '/api/user/servers/{uuidShort}/addons/datapackinstaller/packs',
        function (Request $request, array $args) {
            $uuidShort = $args['uuidShort'] ?? null;
            if (!$uuidShort) {
                return ApiResponse::error('Missing or invalid UUID short', 'INVALID_UUID_SHORT', 400);
            }

            return (new VanillaTweaksController())->getPacks($request, $uuidShort);
        },
        'uuidShort',
        ['GET']
    );

    App::getInstance(true)->registerServerRoute(
        $routes,
        'session-server-addons-datapackinstaller-image',
        '/api/user/servers/{uuidShort}/addons/datapackinstaller/image',
        function (Request $request, array $args) {
            $uuidShort = $args['uuidShort'] ?? null;
            if (!$uuidShort) {
                return ApiResponse::error('Missing or invalid UUID short', 'INVALID_UUID_SHORT', 400);
            }

            return (new VanillaTweaksController())->getPackImage($request, $uuidShort);
        },
        'uuidShort',
        ['GET']
    );

    App::getInstance(true)->registerServerRoute(
        $routes,
        'session-server-addons-datapackinstaller-install',
        '/api/user/servers/{uuidShort}/addons/datapackinstaller/install',
        function (Request $request, array $args) {
            $uuidShort = $args['uuidShort'] ?? null;
            if (!$uuidShort) {
                return ApiResponse::error('Missing or invalid UUID short', 'INVALID_UUID_SHORT', 400);
            }

            return (new VanillaTweaksController())->installPacks($request, $uuidShort);
        },
        'uuidShort',
        ['POST']
    );
};
