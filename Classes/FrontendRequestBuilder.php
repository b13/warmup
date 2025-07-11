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
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\Http\Application;

/**
 * Simulates an HTTP request for TYPO3 Frontend within the same request.
 */
class FrontendRequestBuilder
{
    public function __construct(protected Application $application, protected LoggerInterface $logger) {}

    public function buildRequestForPage(UriInterface $uri, $frontendUserGroups = []): void
    {
        $serverParams = [
            'SCRIPT_NAME' => '/index.php',
            'HTTP_HOST'  => $uri->getHost(),
            'SERVER_NAME' => $uri->getHost(),
            'HTTPS' => $uri->getScheme() === 'https' ? 'on' : 'off',
            'REMOTE_ADDR' => '127.0.0.1',
        ];
        $headers = [];
        $serverRequest = new ServerRequest($uri, 'GET', null, $headers, $serverParams);
        $serverRequest = $serverRequest->withAttribute('normalizedParams', NormalizedParams::createFromRequest($serverRequest));
        $serverRequest = $serverRequest->withAttribute('b13/warmup', [
            'simulateFrontendUserGroupIds' => $frontendUserGroups,
        ]);
        try {
            $this->application->handle($serverRequest);
        } catch (\Exception $e) {
            $this->logger->error('cannot fetch url ' . (string)$uri);
        }
    }
}
