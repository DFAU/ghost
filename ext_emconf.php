<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'DFAU Background Jobqueue',
    'description' => '',
    'category' => 'be',
    'version' => '0.0.0',
    'state' => 'beta',
    'clearCacheOnLoad' => true,
    'author' => 'Thomas Maroschik',
    'author_email' => 'tmaroschik@dfau.de',
    'author_company' => 'DFAU',
    'constraints' => [
        'depends' => [
            'php' => '7.2.0-0.0.0',
            'typo3' => '10.4.0-11.99.99'
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
