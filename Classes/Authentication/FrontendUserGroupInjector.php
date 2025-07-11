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

use Doctrine\DBAL\ArrayParameterType;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Frontend\Authentication\ModifyResolvedFrontendGroupsEvent;

/**
 * Magic logic to add user groups injected into $this->>info['alwaysActiveGroups']
 */
class FrontendUserGroupInjector
{
    public function __construct(protected LoggerInterface $logger, protected ConnectionPool $connectionPool) {}

    public function frontendUserGroupModifier(ModifyResolvedFrontendGroupsEvent $event): void
    {
        $simulationData = $event->getRequest()->getAttribute('b13/warmup');
        if (!is_array($simulationData)) {
            $this->logger->info(self::class . ' was activated, but no user groups were set');
            return;
        }
        $userGroups = $this->fetchGroupsFromDatabase($simulationData['simulateFrontendUserGroupIds']);
        $event->setGroups($userGroups);
    }

    private function fetchGroupsFromDatabase(array $groupUids): array
    {
        $groupRecords = [];
        $this->logger->debug('Get usergroups with id: ' . implode(',', $groupUids));
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('fe_groups');

        $res = $queryBuilder->select('*')
            ->from('fe_groups')
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter($groupUids, ArrayParameterType::INTEGER)
                )
            )
            ->executeQuery();

        while ($row = $res->fetchAssociative()) {
            $groupRecords[$row['uid']] = $row;
        }
        return $groupRecords;
    }
}
