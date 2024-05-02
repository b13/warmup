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
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Http\MiddlewareDispatcher;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\Exception\RequiredArgumentMissingException;
use TYPO3\CMS\Frontend\Http\RequestHandler;

/**
 * Simulates an HTTP request for TYPO3 Frontend within the same request.
 *
 * @todo: clean this class up
 */
class FrontendRequestBuilder
{
    private $originalUser;

    private array $backedUpEnvironment = [];

    private function prepare(): void
    {
        $this->originalUser = $GLOBALS['BE_USER'];
        $this->backupEnvironment();
        $this->initializeEnvironmentForNonCliCall(Environment::getContext());

        $GLOBALS['BE_USER'] = null;
        unset($GLOBALS['TSFE']);
    }

    private function restore(): void
    {
        $GLOBALS['BE_USER'] = $this->originalUser;
        unset($GLOBALS['TSFE']);
        $this->restoreEnvironment();
    }

    public function buildRequestForPage(UriInterface $uri, ?int $frontendUserId, $frontendUserGroups = []): ?ResponseInterface
    {
        $this->prepare();
        $request = new ServerRequest($uri, 'GET', null, [], [
            'HTTP_HOST' => $uri->getHost(),
            'REQUEST_URI' => $uri->getPath(),
        ]);

        $request = $request->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);
        $request = $request->withAttribute('b13/warmup', [
            'simulateFrontendUserId' => $frontendUserId,
            'simulateFrontendUserGroupIds' => $frontendUserGroups,
        ]);

        $response = null;
        try {
            $response = $this->executeFrontendRequest($request);
        } catch (ImmediateResponseException $e) {
            var_dump(get_class($e));
            $response = $e->getResponse();
        } catch (RequiredArgumentMissingException $e) {
            // @todo: log
        } catch (PageNotFoundException $e) {
            // @todo: log
        } catch (\Throwable $e) {
            // @todo: log
            var_dump(get_class($e));
        }
        $response->getBody()->rewind();
        var_dump($response->getBody()->getContents());
        $this->restore();

        return $response;
    }

    public function executeFrontendRequest(ServerRequestInterface $request): ResponseInterface
    {
        $dispatcher = $this->buildDispatcher();
        return $dispatcher->handle($request);
    }

    private function buildDispatcher(): MiddlewareDispatcher
    {
        $requestHandler = GeneralUtility::makeInstance(RequestHandler::class);
        $middlewares = GeneralUtility::getContainer()->get('frontend.middlewares');
        return new MiddlewareDispatcher($requestHandler, $middlewares);
    }

    private function initializeEnvironmentForNonCliCall(ApplicationContext $applicationContext): void
    {
        Environment::initialize(
            $applicationContext,
            false,
            Environment::isComposerMode(),
            Environment::getProjectPath(),
            Environment::getPublicPath(),
            Environment::getVarPath(),
            Environment::getConfigPath(),
            Environment::getCurrentScript(),
            Environment::isWindows() ? 'WINDOWS' : 'UNIX'
        );
    }

    /**
     * Helper method used in setUp() if $this->backupEnvironment is true
     * to back up current state of the Environment::class
     */
    private function backupEnvironment(): void
    {
        $this->backedUpEnvironment['context'] = Environment::getContext();
        $this->backedUpEnvironment['isCli'] = Environment::isCli();
        $this->backedUpEnvironment['composerMode'] = Environment::isComposerMode();
        $this->backedUpEnvironment['projectPath'] = Environment::getProjectPath();
        $this->backedUpEnvironment['publicPath'] = Environment::getPublicPath();
        $this->backedUpEnvironment['varPath'] = Environment::getVarPath();
        $this->backedUpEnvironment['configPath'] = Environment::getConfigPath();
        $this->backedUpEnvironment['currentScript'] = Environment::getCurrentScript();
        $this->backedUpEnvironment['isOsWindows'] = Environment::isWindows();
    }

    /**
     * Helper method used in tearDown() if $this->backupEnvironment is true
     * to reset state of Environment::class
     */
    private function restoreEnvironment(): void
    {
        Environment::initialize(
            $this->backedUpEnvironment['context'],
            $this->backedUpEnvironment['isCli'],
            $this->backedUpEnvironment['composerMode'],
            $this->backedUpEnvironment['projectPath'],
            $this->backedUpEnvironment['publicPath'],
            $this->backedUpEnvironment['varPath'],
            $this->backedUpEnvironment['configPath'],
            $this->backedUpEnvironment['currentScript'],
            $this->backedUpEnvironment['isOsWindows'] ? 'WINDOWS' : 'UNIX'
        );
    }
}
