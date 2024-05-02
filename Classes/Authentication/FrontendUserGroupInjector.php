<?php

declare(strict_types=1);

namespace B13\Warmup\Authentication;

/*
 * This file is part of TYPO3 CMS-based extension "warmup" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\ModifyResolvedFrontendGroupsEvent;

/**
 * Magic logic to add user groups injected into $this->>info['alwaysActiveGroups']
 */
class FrontendUserGroupInjector implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function frontendUserGroupModifier(ModifyResolvedFrontendGroupsEvent $event): void
    {
        $simulationData = $event->getRequest()->getAttribute('b13/warmup');
        if (!is_array($simulationData)) {
            $this->logger->warning(self::class . ' was activated, but no user groups were set');
            return;
        }
        $userGroups = $this->fetchGroupsFromDatabase($simulationData['simulateFrontendUserGroupIds']);
        $event->setGroups($userGroups);
    }

    private function fetchGroupsFromDatabase(array $groupUids): array
    {
        $groupRecords = [];
        $this->logger->debug('Get usergroups with id: ' . implode(',', $groupUids));
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('fe_groups');

        $res = $queryBuilder->select('*')
            ->from('fe_groups')
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter($groupUids, Connection::PARAM_INT_ARRAY)
                )
            )
            ->executeQuery();

        while ($row = $res->fetchAssociative()) {
            $groupRecords[$row['uid']] = $row;
        }
        return $groupRecords;
    }
}
