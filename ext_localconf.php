<?php

declare(strict_types=1);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addService(
    'warmup',
    'auth',
    \B13\Warmup\Authentication\FrontendUserGroupInjector::class,
    [
        'title' => 'Add Frontend Groups based on CLI Request Builder',
        'description' => 'Adds frontend usergroups by verifying data from the Frontend Request Builder.',
        'subtype' => 'getGroupsFE',
        'available' => false,
        'priority' => 90,
        'quality' => 90,
        'className' => \B13\Warmup\Authentication\FrontendUserGroupInjector::class,
    ]
);
