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

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Context\VisibilityAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

class RootlineWarmupService
{
    public function __construct(protected ConnectionPool $connectionPool, protected LoggerInterface $logger) {}

    public function warmUp(SymfonyStyle $io): void
    {
        // fetch all pages which are not deleted and in live workspace
        $queryBuilder = $this->connectionPool
            ->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class))
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $statement = $queryBuilder->select('*')->from('pages')->executeQuery();
        while ($pageRecord = $statement->fetchAssociative()) {
            try {
                $this->buildRootLineForPage($pageRecord);
            } catch (\RuntimeException $e) {
                $io->error('Rootline Cache for Page ID ' . $pageRecord['uid'] . ' could not be warmed up');
            }
        }
    }

    protected function buildRootLineForPage(array $pageRecord): void
    {
        $context = clone GeneralUtility::makeInstance(Context::class);
        $context->setAspect('visibility', new VisibilityAspect(false, false, false, false));
        $pageUid = $pageRecord['uid'];
        if ($pageRecord['sys_language_uid'] > 0) {
            $context->setAspect('language', new LanguageAspect($pageRecord['sys_language_uid']));
            $pageUid = $pageRecord['l10n_parent'];
        }
        $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $pageUid, '', $context);
        $this->logger->debug('buildRootLine', ['pageUid' => $pageRecord['uid'], 'cacheIdentifier' => $rootlineUtility->getCacheIdentifier($pageUid)]);
        $rootlineUtility->get();
    }
}
