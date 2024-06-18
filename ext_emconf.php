<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Cache Warmup',
    'description' => 'Allows to warmup caches via the command line interface',
    'category' => 'be',
    'author' => 'b13 GmbH',
    'author_email' => 'typo3@b13.com',
    'state' => 'stable',
    'version' => '1.4.4',
    'constraints' => [
        'depends' => [
            'typo3' => '12.0.0-12.4.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
