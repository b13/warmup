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


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;


/**
 * Simulates a HTTP request for TYPO3 Frontend within the same request.
 *
 * @todo: clean this class up
 */
class FrontendRequestBuilder
{
    public function buildRequestForPage(UriInterface $uri, ?int $frontendUserId, $frontendUserGroups = []): ?ResponseInterface
    {
        $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);
        $response= $requestFactory->request("" . $uri, 'GET', []);
        return $response;
    }
}
