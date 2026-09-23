<?php

declare(strict_types=1);

namespace B13\Warmup\Service;

/*
 * This file is part of TYPO3 CMS-based extension "warmup" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use B13\Warmup\FrontendRequestBuilder;
use Doctrine\DBAL\ArrayParameterType;
use Psr\Http\Message\UriInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

class PageWarmupService implements WarmupServiceInterface
{
    /**
     * Page types that never render a frontend page on their own.
     */
    private const EXCLUDED_DOKTYPES = [
        PageRepository::DOKTYPE_LINK,
        PageRepository::DOKTYPE_SHORTCUT,
        PageRepository::DOKTYPE_BE_USER_SECTION,
        PageRepository::DOKTYPE_MOUNTPOINT,
        PageRepository::DOKTYPE_SPACER,
        PageRepository::DOKTYPE_SYSFOLDER,
        // "Recycler", removed as a constant in v13 and migrated to DOKTYPE_BE_USER_SECTION,
        // kept as a literal so v12 installations still skip these pages
        255,
    ];

    private SymfonyStyle $io;

    public function __construct(
        protected FrontendRequestBuilder $frontendRequestBuilder,
        protected SiteFinder $siteFinder,
        protected ConnectionPool $connectionPool
    ) {}

    public function warmUp(SymfonyStyle $io): void
    {
        $this->io = $io;

        // fetch all pages which are not deleted and in live workspace and not one of excluded types
        $queryBuilder = $this->connectionPool
            ->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(new WorkspaceRestriction())
            ->add(new HiddenRestriction())
            ->add(new DeletedRestriction());
        $statement = $queryBuilder->select('*')->from('pages')->where(
            $queryBuilder->expr()->notIn('doktype', $queryBuilder->createNamedParameter(self::EXCLUDED_DOKTYPES, ArrayParameterType::INTEGER))
        )->executeQuery();

        $io->writeln('Starting to request pages at ' . date('d.m.Y H:i:s'));
        $requestedPages = 0;

        while ($pageRecord = $statement->fetchAssociative()) {
            try {
                $languageUid = (int)$pageRecord['sys_language_uid'];
                $pageUid = (int)$pageRecord['uid'];
                if ($languageUid > 0) {
                    $pageUid = (int)$pageRecord['l10n_parent'];
                }
                $site = $this->siteFinder->getSiteByPageId($pageUid);
                $siteLanguage = $site->getLanguageById($languageUid);
                $url = $site->getRouter()->generateUri($pageUid, ['_language' => $siteLanguage]);
                $this->executeRequestForPageRecord($url, $pageRecord);
                $requestedPages++;
            } catch (\Exception $e) {
                $io->error('Cache for Page ID ' . $pageRecord['uid'] . ' could not be warmed up: ' . $e->getMessage());
            }
        }

        $io->writeln('Finished requesting ' . $requestedPages . ' pages at ' . date('d.m.Y H:i:s'));
    }

    protected function executeRequestForPageRecord(UriInterface $url, array $pageRecord): void
    {
        $userGroups = $this->resolveRequestedUserGroupsForPage($pageRecord);
        $this->io->writeln('Calling ' . (string)$url . ' (Page ID: ' . $pageRecord['uid'] . ', UserGroups: ' . implode(',', $userGroups) . ')');
        $this->frontendRequestBuilder->buildRequestForPage($url, $userGroups);
    }

    protected function resolveRequestedUserGroupsForPage(array $pageRecord): array
    {
        $userGroups = GeneralUtility::intExplode(',', (string)($pageRecord['fe_group'] ?? ''), true);
        $rootLine = GeneralUtility::makeInstance(RootlineUtility::class, (int)$pageRecord['uid'])->get();
        foreach ($rootLine as $pageInRootLine) {
            if ($pageInRootLine['extendToSubpages'] ?? false) {
                $userGroups = array_merge($userGroups, GeneralUtility::intExplode(',', (string)($pageInRootLine['fe_group'] ?? ''), true));
            }
        }
        $userGroups = array_filter($userGroups);
        return array_unique($userGroups);
    }
}
