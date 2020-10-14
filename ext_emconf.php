<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'DFAU Background Jobqueue',
    'description' => '',
    'category' => 'be',
    'shy' => 0,
    'version' => '0.0.0',
    'dependencies' => '',
    'conflicts' => '',
    'priority' => 'bottom',
    'loadOrder' => '',
    'module' => '',
    'state' => 'beta',
    'uploadfolder' => 0,
    'createDirs' => 'typo3temp/GhostQueues',
    'modify_tables' => '',
    'clearcacheonload' => 1,
    'lockType' => '',
    'author' => 'Thomas Maroschik',
    'author_email' => 'tmaroschik@dfau.de',
    'author_company' => 'DFAU',
    'CGLcompliance' => '',
    'CGLcompliance_note' => '',
    'constraints' => [
        'depends' => [
            'php' => '7.2.0-0.0.0',
            'typo3' => '10.4.0-10.99.99'
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
    'suggests' => [
    ],
];
