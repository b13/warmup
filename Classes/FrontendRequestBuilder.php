<?php

declare(strict_types=1);

namespace B13\Warmup;

/*
 * This file is part of TYPO3 CMS-based extension "warmup" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\Http\Application;

/**
 * Simulates an HTTP request for TYPO3 Frontend within the same request.
 */
class FrontendRequestBuilder
{
    public function __construct(protected Application $application, protected LoggerInterface $logger) {}

    /**
     * @param int[] $frontendUserGroups
     */
    public function buildRequestForPage(UriInterface $uri, array $frontendUserGroups = []): void
    {
        $entryScript = Environment::getPublicPath() . '/index.php';
        $serverParams = [
            'SCRIPT_NAME' => '/index.php',
            'SCRIPT_FILENAME' => $entryScript,
            'HTTP_HOST' => $uri->getHost(),
            'SERVER_NAME' => $uri->getHost(),
            'HTTPS' => $uri->getScheme() === 'https' ? 'on' : 'off',
            'REMOTE_ADDR' => '127.0.0.1',
        ];
        $serverRequest = new ServerRequest($uri, 'GET', null, [], $serverParams);
        // NormalizedParams::createFromRequest() would use the CLI script (e.g. vendor/bin/typo3)
        // as entry point, which is outside the public folder and results in an empty site path.
        // Simulate the frontend entry point instead, so URLs are generated as in a web request.
        $normalizedParams = new NormalizedParams(
            $serverParams,
            $GLOBALS['TYPO3_CONF_VARS']['SYS'],
            $entryScript,
            Environment::getPublicPath()
        );
        $serverRequest = $serverRequest->withAttribute('normalizedParams', $normalizedParams);
        $serverRequest = $serverRequest->withAttribute('b13/warmup', [
            'simulateFrontendUserGroupIds' => $frontendUserGroups,
        ]);
        try {
            $this->application->handle($serverRequest);
        } catch (\Exception $e) {
            $this->logger->error('cannot fetch url {url}: {message}', [
                'url' => (string)$uri,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }
}
