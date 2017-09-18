<?php

$EM_CONF[$_EXTKEY] = array(
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
	'constraints' => array(
		'depends' => array(
			'php' => '7.0.0-0.0.0',
			'typo3' => '7.6.0-8.99.99'
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'suggests' => array(
	),
);

