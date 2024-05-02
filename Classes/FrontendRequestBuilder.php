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


use B13\Warmup\Authentication\FrontendUserGroupInjector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Http\MiddlewareDispatcher;
use TYPO3\CMS\Core\Http\MiddlewareStackResolver;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Service\DependencyOrderingService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\Exception\RequiredArgumentMissingException;
use TYPO3\CMS\Extbase\Service\EnvironmentService;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;
use TYPO3\CMS\Frontend\Http\RequestHandler;

/**
 * Simulates an HTTP request for TYPO3 Frontend within the same request.
 *
 * @todo: clean this class up
 */
class FrontendRequestBuilder
{
    private $originalUser;

    private $backedUpEnvironment = [];

    private $originalEnvironmentService;

    private function prepare()
    {
        $this->originalUser = $GLOBALS['BE_USER'];
        $this->backupEnvironment();
        $this->initializeEnvironmentForNonCliCall(Environment::getContext());

        $this->originalEnvironmentService = GeneralUtility::makeInstance(EnvironmentService::class);

        // TYPO3 v10 ships with strict types
        $environmentService = new class extends EnvironmentService {
            public function isEnvironmentInFrontendMode(): bool
            {
                return true;
            }
            public function isEnvironmentInBackendMode(): bool
            {
                return false;
            }
            public function isEnvironmentInCliMode()
            {
                return false;
            }
        };

        GeneralUtility::setSingletonInstance(EnvironmentService::class, $environmentService);

        $GLOBALS['BE_USER'] = null;
        unset($GLOBALS['TSFE']);
    }

    private function restore()
    {
        $this->disableFrontendGroupAuthService();
        $GLOBALS['BE_USER'] = $this->originalUser;
        GeneralUtility::setSingletonInstance(EnvironmentService::class, $this->originalEnvironmentService);
        unset($GLOBALS['TSFE']);
        $this->restoreEnvironment();
    }


    public function buildRequestForPage(UriInterface $uri, ?int $frontendUserId, $frontendUserGroups = []): ?ResponseInterface
    {
        $this->prepare();
        $request = new ServerRequest($uri, 'GET', null, [], [
            'HTTP_HOST' => $uri->getHost(),
            'REQUEST_URI' => $uri->getPath()
        ]);

        if (!empty($frontendUserGroups)) {
            $this->activateFrontendGroupAuthService($frontendUserGroups, $frontendUserId);
        }

        $response = null;
        try {
            $response = $this->executeFrontendRequest($request);
        } catch (ImmediateResponseException $e) {
            $response = $e->getResponse();
        } catch (RequiredArgumentMissingException $e) {
            // @todo: log
        } catch (PageNotFoundException $e) {
            // @todo: log
        } catch (\Throwable $e) {
            // @todo: log
            // var_dump(get_class($e));
        }

        $this->restore();

        return $response;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function executeFrontendRequest(ServerRequestInterface $request): ResponseInterface
    {
        $dispatcher = $this->buildDispatcher();
        return $dispatcher->handle($request);
    }

    /**
     * @return MiddlewareDispatcher
     * @throws \TYPO3\CMS\Core\Cache\Exception\InvalidDataException
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
     * @throws \TYPO3\CMS\Core\Exception
     */
    private function buildDispatcher()
    {
        $requestHandler = GeneralUtility::makeInstance(RequestHandler::class);
        if ((new Typo3Version())->getMajorVersion() >= 10) {
            $packageManagerOrContainer = GeneralUtility::getContainer();
        } else {
            $packageManagerOrContainer = GeneralUtility::makeInstance(PackageManager::class);
        }
        $resolver = new MiddlewareStackResolver(
            $packageManagerOrContainer,
            GeneralUtility::makeInstance(DependencyOrderingService::class),
            GeneralUtility::makeInstance(CacheManager::class)->getCache('cache_core')
        );
        $middlewares = $resolver->resolve('frontend');
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

    private function activateFrontendGroupAuthService(array $userGroups, ?int $specificUserId)
    {
        $GLOBALS['T3_SERVICES']['auth'][FrontendUserGroupInjector::class]['available'] = true;
        $GLOBALS['T3_SERVICES']['auth'][FrontendUserGroupInjector::class]['alwaysActiveGroups'] = $userGroups;
        $GLOBALS['T3_SERVICES']['auth'][FrontendUserGroupInjector::class]['requestUser'] = $specificUserId ?? false;

    }

    private function disableFrontendGroupAuthService()
    {
        $GLOBALS['T3_SERVICES']['auth'][FrontendUserGroupInjector::class]['available'] = false;
        $GLOBALS['T3_SERVICES']['auth'][FrontendUserGroupInjector::class]['alwaysActiveGroups'] = [];
        $GLOBALS['T3_SERVICES']['auth'][FrontendUserGroupInjector::class]['requestUser'] = false;
    }
}
