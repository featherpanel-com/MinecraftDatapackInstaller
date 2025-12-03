<?php

/*
 * This file is part of FeatherPanel.
 *
 * MIT License
 *
 * Copyright (c) 2025 MythicalSystems
 * Copyright (c) 2025 Cassian Gherman (NaysKutzu)
 * Copyright (c) 2018 - 2021 Dane Everitt <dane@daneeveritt.com> and Contributors
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace App\Addons\minecraftdatapackinstaller\Controllers;

use App\App;
use App\Chat\Node;
use App\Cache\Cache;
use App\Chat\Server;
use GuzzleHttp\Client;
use App\SubuserPermissions;
use App\Chat\ServerActivity;
use App\Helpers\ApiResponse;
use App\Services\Wings\Wings;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Controllers\User\Server\CheckSubuserPermissionsTrait;

class VanillaTweaksController
{
    use CheckSubuserPermissionsTrait;

    private Client $httpClient;

    public function __construct()
    {
        // Configure HTTP client with realistic browser headers to avoid Cloudflare bot detection
        $this->httpClient = new Client([
            'base_uri' => 'https://vanillatweaks.net',
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:143.0) Gecko/20100101 Firefox/143.0',
                'Accept' => '*/*',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Accept-Encoding' => 'gzip, deflate, br',
                'DNT' => '1',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
                'Sec-Fetch-Dest' => 'empty',
                'Sec-Fetch-Mode' => 'cors',
                'Sec-Fetch-Site' => 'same-origin',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'no-cache',
            ],
            'allow_redirects' => true,
            'http_errors' => true,
            'verify' => true,
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Detect Minecraft version from versions folder.
     */
    #[OA\Get(
        path: '/api/user/servers/{uuidShort}/addons/datapackinstaller/detect-version',
        summary: 'Detect Minecraft version',
        description: 'Detect Minecraft version from server versions folder',
        tags: ['Minecraft DataPack Installer'],
        parameters: [
            new OA\Parameter(
                name: 'uuidShort',
                in: 'path',
                description: 'Server short UUID',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Version detected successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function detectVersion(Request $request, string $serverUuid): Response
    {
        try {
            $user = $this->validateUser($request);
            $server = $this->validateServer($serverUuid);
            $node = $this->validateNode($server['node_id']);

            $permissionCheck = $this->checkPermission($request, $server, SubuserPermissions::FILE_READ);
            if ($permissionCheck !== null) {
                return $permissionCheck;
            }

            $wings = $this->createWingsConnection($node);

            // Check if versions folder exists
            try {
                $response = $wings->getServer()->listDirectory($server['uuid'], '/versions');
                if (!$response->isSuccessful()) {
                    return ApiResponse::success(['version' => null], 'Versions folder not found');
                }

                $responseData = $response->getData();
                $files = $responseData['contents'] ?? $responseData ?? [];

                // Look for version folders -> e.g. 1.21.5 -> 1.21
                $detectedVersion = null;
                foreach ($files as $item) {
                    if (isset($item['name']) && ($item['directory'] ?? false) === true) {
                        // Match version pattern -> e.g. 1.21.5 -> 1.21
                        if (preg_match('/^(\d+\.\d+)/', $item['name'], $matches)) {
                            $detectedVersion = $matches[1];
                            break;
                        }
                    }
                }

                return ApiResponse::success(['version' => $detectedVersion], 'Version detected');
            } catch (\Exception $e) {
                // Versions folder doesn't exist or can't be read
                return ApiResponse::success(['version' => null], 'Versions folder not accessible');
            }
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Error detecting version: ' . $e->getMessage());

            return ApiResponse::error('Failed to detect version: ' . $e->getMessage(), 'ERROR', 500);
        }
    }

    /**
     * Get list of available worlds on the server.
     */
    #[OA\Get(
        path: '/api/user/servers/{uuidShort}/addons/datapackinstaller/worlds',
        summary: 'List worlds',
        description: 'Get list of worlds with datapacks folder',
        tags: ['Minecraft DataPack Installer'],
        parameters: [
            new OA\Parameter(
                name: 'uuidShort',
                in: 'path',
                description: 'Server short UUID',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Worlds listed successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function getWorlds(Request $request, string $serverUuid): Response
    {
        try {
            $user = $this->validateUser($request);
            $server = $this->validateServer($serverUuid);
            $node = $this->validateNode($server['node_id']);

            $permissionCheck = $this->checkPermission($request, $server, SubuserPermissions::FILE_READ);
            if ($permissionCheck !== null) {
                return $permissionCheck;
            }

            $wings = $this->createWingsConnection($node);
            $response = $wings->getServer()->listDirectory($server['uuid'], '/');

            if (!$response->isSuccessful()) {
                return ApiResponse::error('Failed to list directory', 'LIST_ERROR', 500);
            }

            $responseData = $response->getData();
            $files = $responseData['contents'] ?? $responseData ?? [];

            $worlds = [];
            foreach ($files as $item) {
                // Check if item is a directory
                if (!isset($item['name']) || ($item['directory'] ?? false) !== true) {
                    continue;
                }

                // Check if directory is a valid Minecraft world (has level.dat)
                try {
                    $worldResponse = $wings->getServer()->listDirectory($server['uuid'], '/' . $item['name']);
                    if (!$worldResponse->isSuccessful()) {
                        continue;
                    }

                    $worldData = $worldResponse->getData();
                    $worldFiles = $worldData['contents'] ?? $worldData ?? [];

                    // Look for level.dat to confirm it's a valid Minecraft world
                    $isValidWorld = false;
                    foreach ($worldFiles as $worldItem) {
                        // Check for level.dat file
                        if (isset($worldItem['name']) && $worldItem['name'] === 'level.dat'
                            && ($worldItem['directory'] ?? false) !== true) {
                            $isValidWorld = true;
                            break;
                        }
                    }

                    if ($isValidWorld) {
                        $worlds[] = [
                            'name' => $item['name'],
                            'path' => '/' . $item['name'],
                        ];
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            // Sort worlds, put "world" first
            usort($worlds, function ($a, $b) {
                if ($a['name'] === 'world') {
                    return -1;
                }
                if ($b['name'] === 'world') {
                    return 1;
                }

                return strcmp($a['name'], $b['name']);
            });

            return ApiResponse::success(['worlds' => $worlds], 'Worlds listed');
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Error listing worlds: ' . $e->getMessage());

            return ApiResponse::error('Failed to fetch worlds: ' . $e->getMessage(), 'ERROR', 500);
        }
    }

    /**
     * Get available packs from Vanilla Tweaks.
     */
    #[OA\Get(
        path: '/api/user/servers/{uuidShort}/addons/datapackinstaller/packs',
        summary: 'Get packs',
        description: 'Get available datapacks from Vanilla Tweaks',
        tags: ['Minecraft DataPack Installer'],
        parameters: [
            new OA\Parameter(
                name: 'uuidShort',
                in: 'path',
                description: 'Server short UUID',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'mcVersion',
                in: 'query',
                description: 'Minecraft version',
                required: false,
                schema: new OA\Schema(type: 'string', default: '1.21')
            ),
            new OA\Parameter(
                name: 'type',
                in: 'query',
                description: 'Pack type (datapacks, resourcepacks, craftingtweaks)',
                required: false,
                schema: new OA\Schema(type: 'string', default: 'datapacks')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Packs fetched successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function getPacks(Request $request, string $serverUuid): Response
    {
        try {
            $user = $this->validateUser($request);
            $server = $this->validateServer($serverUuid);
            $node = $this->validateNode($server['node_id']);

            $permissionCheck = $this->checkPermission($request, $server, SubuserPermissions::FILE_READ);
            if ($permissionCheck !== null) {
                return $permissionCheck;
            }

            $mcVersion = $request->query->get('mcVersion', '1.21');
            $type = $request->query->get('type', 'datapacks');

            $prefixMap = [
                'datapacks' => 'dp',
                'resourcepacks' => 'rp',
                'craftingtweaks' => 'ct',
            ];

            $prefix = $prefixMap[$type] ?? 'dp';
            $userAgent = $request->headers->get('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:143.0) Gecko/20100101 Firefox/143.0');

            // Check cache first (cache for 60 minutes)
            $cacheKey = 'vanillatweaks:packs:' . $mcVersion . ':' . $type;
            $cachedData = Cache::get($cacheKey);

            if ($cachedData !== null) {
                return ApiResponse::success($cachedData, 'Packs fetched (cached)');
            }

            try {
                $response = $this->httpClient->get('/assets/resources/json/' . $mcVersion . '/' . $prefix . 'categories.json', [
                    'headers' => [
                        'User-Agent' => $userAgent,
                        'Referer' => 'https://vanillatweaks.net/picker/' . $type . '/',
                        'Accept' => 'application/json, text/javascript, */*; q=0.01',
                        'X-Requested-With' => 'XMLHttpRequest',
                    ],
                ]);
                $data = json_decode($response->getBody()->getContents(), true);

                // Cache the data for 60 minutes
                if ($data !== null) {
                    Cache::put($cacheKey, $data, 60);
                }

                return ApiResponse::success($data, 'Packs fetched');
            } catch (\Exception $e) {
                return ApiResponse::error('Failed to fetch packs: ' . $e->getMessage(), 'FETCH_ERROR', 500);
            }
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Error fetching packs: ' . $e->getMessage());

            return ApiResponse::error('Failed to fetch packs: ' . $e->getMessage(), 'ERROR', 500);
        }
    }

    /**
     * Proxy pack images from VanillaTweaks to avoid CORS issues.
     */
    #[OA\Get(
        path: '/api/user/servers/{uuidShort}/addons/datapackinstaller/image',
        summary: 'Get pack image',
        description: 'Get pack image from Vanilla Tweaks (proxied to avoid CORS)',
        tags: ['Minecraft DataPack Installer'],
        parameters: [
            new OA\Parameter(
                name: 'uuidShort',
                in: 'path',
                description: 'Server short UUID',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'mcVersion',
                in: 'query',
                description: 'Minecraft version',
                required: false,
                schema: new OA\Schema(type: 'string', default: '1.21')
            ),
            new OA\Parameter(
                name: 'type',
                in: 'query',
                description: 'Pack type',
                required: false,
                schema: new OA\Schema(type: 'string', default: 'datapacks')
            ),
            new OA\Parameter(
                name: 'pack',
                in: 'query',
                description: 'Pack name',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Image fetched successfully'),
            new OA\Response(response: 404, description: 'Image not found'),
        ]
    )]
    public function getPackImage(Request $request, string $serverUuid): Response
    {
        try {
            $user = $this->validateUser($request);
            $server = $this->validateServer($serverUuid);
            $node = $this->validateNode($server['node_id']);

            $permissionCheck = $this->checkPermission($request, $server, SubuserPermissions::FILE_READ);
            if ($permissionCheck !== null) {
                return $permissionCheck;
            }

            $mcVersion = $request->query->get('mcVersion', '1.21');
            $packType = $request->query->get('type', 'datapacks');
            $packName = $request->query->get('pack');

            if (!$packName) {
                return ApiResponse::error('Pack name is required', 'MISSING_PACK_NAME', 400);
            }

            try {
                $prefix = $packType === 'datapacks' ? 'datapacks' : ($packType === 'resourcepacks' ? 'resourcepacks' : 'craftingtweaks');
                $imageUrl = 'https://vanillatweaks.net/assets/resources/icons/' . $prefix . '/' . $mcVersion . '/' . rawurlencode($packName) . '.png';

                // Check cache first (cache images for 24 hours)
                $cacheKey = 'vanillatweaks:image:' . md5($imageUrl);
                $cachedImage = Cache::get($cacheKey);

                if ($cachedImage !== null) {
                    // Generate ETag based on pack name and version for caching
                    $etag = md5($imageUrl);
                    $clientEtag = $request->headers->get('If-None-Match');

                    // Check if client has cached version (304 Not Modified)
                    if ($clientEtag === '"' . $etag . '"') {
                        return new Response('', 304, [
                            'ETag' => '"' . $etag . '"',
                            'Cache-Control' => 'public, max-age=86400',
                        ]);
                    }

                    return new Response($cachedImage, 200, [
                        'Content-Type' => 'image/png',
                        'ETag' => '"' . $etag . '"',
                        'Cache-Control' => 'public, max-age=86400',
                    ]);
                }

                // Generate ETag based on pack name and version for caching
                $etag = md5($imageUrl);
                $clientEtag = $request->headers->get('If-None-Match');

                // Check if client has cached version (304 Not Modified)
                if ($clientEtag === '"' . $etag . '"') {
                    return new Response('', 304, [
                        'ETag' => '"' . $etag . '"',
                        'Cache-Control' => 'public, max-age=86400',
                    ]);
                }

                $response = $this->httpClient->get($imageUrl, [
                    'headers' => [
                        'Referer' => 'https://vanillatweaks.net/picker/' . $prefix . '/',
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    ],
                    'http_errors' => false, // Don't throw on 404
                ]);

                // Check if request was successful
                if ($response->getStatusCode() === 404) {
                    // Return a transparent 1x1 PNG for missing images
                    $transparentPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

                    return new Response($transparentPng, 200, [
                        'Content-Type' => 'image/png',
                        'Cache-Control' => 'public, max-age=3600',
                    ]);
                }

                $imageContent = $response->getBody()->getContents();

                // Cache the image for 24 hours (1440 minutes)
                Cache::put($cacheKey, $imageContent, 1440);

                return new Response($imageContent, 200, [
                    'Content-Type' => 'image/png',
                    'ETag' => '"' . $etag . '"',
                    'Cache-Control' => 'public, max-age=86400',
                ]);
            } catch (\Exception $e) {
                // Return a transparent 1x1 PNG for errors
                $transparentPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

                return new Response($transparentPng, 200, [
                    'Content-Type' => 'image/png',
                    'Cache-Control' => 'public, max-age=3600',
                ]);
            }
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Error fetching pack image: ' . $e->getMessage());

            return ApiResponse::error('Failed to fetch image', 'ERROR', 404);
        }
    }

    /**
     * Install selected packs to a world.
     */
    #[OA\Post(
        path: '/api/user/servers/{uuidShort}/addons/datapackinstaller/install',
        summary: 'Install packs',
        description: 'Install selected datapacks to a world',
        tags: ['Minecraft DataPack Installer'],
        parameters: [
            new OA\Parameter(
                name: 'uuidShort',
                in: 'path',
                description: 'Server short UUID',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'mcVersion', type: 'string', description: 'Minecraft version'),
                        new OA\Property(property: 'pack_type', type: 'string', description: 'Pack type'),
                        new OA\Property(property: 'packs', type: 'object', description: 'Selected packs'),
                        new OA\Property(property: 'world', type: 'string', description: 'World name', default: 'world'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Packs installed successfully'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function installPacks(Request $request, string $serverUuid): Response
    {
        try {
            $user = $this->validateUser($request);
            $server = $this->validateServer($serverUuid);
            $node = $this->validateNode($server['node_id']);

            $permissionCheck = $this->checkPermission($request, $server, SubuserPermissions::FILE_CREATE);
            if ($permissionCheck !== null) {
                return $permissionCheck;
            }

            $data = json_decode($request->getContent(), true);
            $mcVersion = $data['mcVersion'] ?? null;
            $packType = $data['pack_type'] ?? 'datapacks';
            $packs = $data['packs'] ?? [];
            $worldName = $data['world'] ?? 'world';

            if (!$mcVersion || empty($packs)) {
                return ApiResponse::error('Missing required parameters', 'INVALID_REQUEST', 400);
            }

            $userAgent = $request->headers->get('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:143.0) Gecko/20100101 Firefox/143.0');

            try {
                $typeMap = [
                    'datapacks' => 'datapacks',
                    'resourcepacks' => 'resourcepacks',
                    'craftingtweaks' => 'craftingtweaks',
                ];

                $fullType = $typeMap[$packType] ?? 'datapacks';

                // Convert packs array to the format Vanilla Tweaks expects
                $packsFormatted = [];
                foreach ($packs as $category => $packList) {
                    // Convert category name to slug format (lowercase, spaces to hyphens)
                    $categorySlug = strtolower(str_replace(['/', ' '], '-', $category));
                    $packsFormatted[$categorySlug] = $packList;
                }

                // Prepare form data exactly as browser would send it
                $formData = 'version=' . urlencode($mcVersion) . '&packs=' . urlencode(json_encode($packsFormatted));

                // Make POST request with realistic browser headers
                $response = $this->httpClient->post('/assets/server/zip' . $fullType . '.php', [
                    'headers' => [
                        'User-Agent' => $userAgent,
                        'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With' => 'XMLHttpRequest',
                        'Accept' => '*/*',
                        'Origin' => 'https://vanillatweaks.net',
                        'Referer' => 'https://vanillatweaks.net/picker/' . $fullType . '/',
                        'Sec-Fetch-Dest' => 'empty',
                        'Sec-Fetch-Mode' => 'cors',
                        'Sec-Fetch-Site' => 'same-origin',
                        'Content-Length' => (string) strlen($formData),
                    ],
                    'body' => $formData,
                ]);

                $result = json_decode($response->getBody()->getContents(), true);

                if (($result['status'] ?? '') !== 'success') {
                    return ApiResponse::error(
                        $result['message'] ?? 'Failed to generate pack',
                        'GENERATE_ERROR',
                        500
                    );
                }

                $downloadUrl = 'https://vanillatweaks.net' . $result['link'];

                // Add delay to mimic human behavior
                usleep(mt_rand(500000, 1500000));

                // Download the zip file
                $zipResponse = $this->httpClient->get($result['link'], [
                    'headers' => [
                        'User-Agent' => $userAgent,
                        'Referer' => 'https://vanillatweaks.net/picker/' . $fullType . '/',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    ],
                ]);
                $zipContent = $zipResponse->getBody()->getContents();

                // Create temporary file
                $tempZipPath = sys_get_temp_dir() . '/vanillatweaks_' . uniqid() . '.zip';
                file_put_contents($tempZipPath, $zipContent);

                $targetDirectory = '/' . trim($worldName, '/') . '/datapacks';
                $successMessages = [
                    'datapacks' => 'Datapacks installed successfully',
                    'resourcepacks' => 'Resource packs installed successfully',
                    'craftingtweaks' => 'Crafting tweaks installed successfully',
                ];
                $successMessage = $successMessages[$packType] ?? 'Packs installed successfully';

                $wings = $this->createWingsConnection($node);

                // Ensure datapacks directory exists
                try {
                    $wings->getServer()->createDirectory($server['uuid'], 'datapacks', '/' . trim($worldName, '/'));
                } catch (\Exception $e) {
                    // Directory might already exist, ignore
                }

                if ($packType === 'craftingtweaks') {
                    $downloadPath = parse_url($result['link'], PHP_URL_PATH);
                    $zipFileName = pathinfo($downloadPath ?? '', PATHINFO_BASENAME);
                    if (!$zipFileName) {
                        $versionSlug = preg_replace('/[^0-9A-Za-z\-_]+/', '', (string) $mcVersion);
                        $zipFileName = 'crafting-tweaks-' . ($versionSlug ?: 'pack') . '.zip';
                    }

                    $zipContents = file_get_contents($tempZipPath);
                    if ($zipContents === false) {
                        unlink($tempZipPath);

                        return ApiResponse::error('Failed to read crafting tweaks archive', 'READ_ERROR', 500);
                    }

                    $this->uploadFileToServer($wings, $server, $targetDirectory . '/' . $zipFileName, $zipContents);

                    unlink($tempZipPath);

                    $this->logActivity($server, $node, 'datapacks_installed', [
                        'world' => $worldName,
                        'pack_type' => $packType,
                        'packs_count' => count($packs),
                    ], $user);

                    return ApiResponse::success([], $successMessage);
                }

                // Extract zip
                $zip = new \ZipArchive();
                if ($zip->open($tempZipPath) !== true) {
                    unlink($tempZipPath);

                    return ApiResponse::error('Failed to open zip file', 'ZIP_ERROR', 500);
                }

                $tempExtractPath = sys_get_temp_dir() . '/vanillatweaks_extract_' . uniqid();
                mkdir($tempExtractPath, 0755, true);
                $zip->extractTo($tempExtractPath);
                $zip->close();

                // Upload each extracted file to the server
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($tempExtractPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $file) {
                    if ($file->isDir()) {
                        continue;
                    }

                    $filePath = $file->getRealPath();
                    $relativePath = str_replace($tempExtractPath . '/', '', $filePath);
                    $relativePath = str_replace('\\', '/', $relativePath);

                    // Upload file to server
                    $this->uploadFileToServer($wings, $server, $targetDirectory . '/' . $relativePath, file_get_contents($filePath));
                }

                // Cleanup
                unlink($tempZipPath);
                $this->deleteDirectory($tempExtractPath);

                $this->logActivity($server, $node, 'datapacks_installed', [
                    'world' => $worldName,
                    'pack_type' => $packType,
                    'packs_count' => count($packs),
                ], $user);

                return ApiResponse::success([], $successMessage);
            } catch (\Exception $e) {
                App::getInstance(true)->getLogger()->error('Error installing packs: ' . $e->getMessage());

                return ApiResponse::error('Failed to install packs: ' . $e->getMessage(), 'INSTALL_ERROR', 500);
            }
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Error installing packs: ' . $e->getMessage());

            return ApiResponse::error('Failed to install packs: ' . $e->getMessage(), 'ERROR', 500);
        }
    }

    /**
     * Upload a file to the server using Wings.
     */
    private function uploadFileToServer(Wings $wings, array $server, string $path, string $content): void
    {
        $pathInfo = pathinfo($path);
        $directory = $pathInfo['dirname'] ?? '/';
        $filename = $pathInfo['basename'] ?? 'file';

        // Ensure directory exists - create nested directories one by one
        $dirParts = explode('/', trim($directory, '/'));
        $currentPath = '';
        foreach ($dirParts as $part) {
            if (empty($part)) {
                continue;
            }
            $parentPath = $currentPath ?: '/';
            $currentPath = ($currentPath ? $currentPath . '/' : '') . $part;

            // Try to create directory, ignore if it already exists
            try {
                $response = $wings->getServer()->createDirectory($server['uuid'], $part, $parentPath);
                // Ignore errors - directory might already exist
            } catch (\Exception $e) {
                // Directory might already exist, ignore
            }
        }

        // Write file
        $fullPath = rtrim($directory, '/') . '/' . $filename;
        $writeResponse = $wings->getServer()->writeFile($server['uuid'], $fullPath, $content);

        if (!$writeResponse->isSuccessful()) {
            throw new \Exception('Failed to write file: ' . $writeResponse->getError());
        }
    }

    /**
     * Recursively delete a directory.
     */
    private function deleteDirectory(string $dir): void
    {
        if (!file_exists($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Helper method to validate user authentication.
     */
    private function validateUser(Request $request): array
    {
        $user = $request->attributes->get('user');
        if (!$user) {
            throw new \Exception('User not authenticated', 401);
        }

        return $user;
    }

    /**
     * Helper method to get and validate server.
     */
    private function validateServer(string $serverUuid): array
    {
        $server = Server::getServerByUuidShort($serverUuid);
        if (!$server) {
            throw new \Exception('Server not found', 404);
        }

        return $server;
    }

    /**
     * Helper method to get and validate node.
     */
    private function validateNode(int $nodeId): array
    {
        $node = Node::getNodeById($nodeId);
        if (!$node) {
            throw new \Exception('Node not found', 404);
        }

        return $node;
    }

    /**
     * Helper method to log server activity.
     */
    private function logActivity(array $server, array $node, string $event, array $metadata, array $user): void
    {
        ServerActivity::createActivity([
            'server_id' => $server['id'],
            'node_id' => $server['node_id'],
            'user_id' => $user['id'],
            'ip' => $user['last_ip'] ?? null,
            'event' => $event,
            'metadata' => json_encode($metadata),
        ]);
    }

    /**
     * Create Wings connection.
     */
    private function createWingsConnection(array $node): Wings
    {
        $scheme = $node['scheme'];
        $host = $node['fqdn'];
        $port = $node['daemonListen'];
        $token = $node['daemon_token'];
        $timeout = 30;

        return new Wings($host, $port, $scheme, $token, $timeout);
    }
}
