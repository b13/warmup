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
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

class PageWarmupService
{
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
        $excludeDocTypes = [
            PageRepository::DOKTYPE_LINK,
            PageRepository::DOKTYPE_SHORTCUT,
            PageRepository::DOKTYPE_BE_USER_SECTION,
            PageRepository::DOKTYPE_MOUNTPOINT,
            PageRepository::DOKTYPE_SPACER,
            PageRepository::DOKTYPE_SYSFOLDER,
            PageRepository::DOKTYPE_RECYCLER,
        ];
        $queryBuilder = $this->connectionPool
            ->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class))
            ->add(GeneralUtility::makeInstance(HiddenRestriction::class))
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $statement = $queryBuilder->select('*')->from('pages')->where(
            $queryBuilder->expr()->notIn('doktype', $queryBuilder->createNamedParameter($excludeDocTypes, ArrayParameterType::INTEGER))
        )->executeQuery();

        $io->writeln('Starting to request pages at ' . date('d.m.Y H:i:s'));
        $requestedPages = 0;

        while ($pageRecord = $statement->fetchAssociative()) {
            try {
                $languageUid = (int)$pageRecord['sys_language_uid'];
                $pageUid = $pageRecord['uid'];
                if ($languageUid > 0) {
                    $pageUid = $pageRecord['l10n_parent'];
                }
                $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pageUid);
                $siteLanguage = $site->getLanguageById($languageUid);
                $url = $site->getRouter()->generateUri($pageUid, ['_language' => $siteLanguage]);
                $this->executeRequestForPageRecord($url, $pageRecord);
                $requestedPages++;
            } catch (SiteNotFoundException $e) {
                $io->error('Cache for Page ID ' . $pageRecord['uid'] . ' could not be warmed up');
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
        $userGroups = $pageRecord['fe_group'];
        $rootLine = GeneralUtility::makeInstance(RootlineUtility::class, $pageRecord['uid'])->get();
        foreach ($rootLine as $pageInRootLine) {
            if ($pageInRootLine['extendToSubpages']) {
                $userGroups .= ',' . $pageInRootLine['fe_group'];
            }
        }
        $userGroups = GeneralUtility::intExplode(',', $userGroups, true);
        $userGroups = array_filter($userGroups);
        $userGroups = array_unique($userGroups);
        return $userGroups;
    }
}
