<?php

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ghost']['connections'][\DFAU\Ghost\CmsConfigurationFactory::DEFAULT_CONNECTION_NAME]['queueFactory'] = [
    'className' => \Bernard\QueueFactory\PersistentFactory::class,
    'arguments' => [
        'driver' => function () {
            /** @var \TYPO3\CMS\Core\Database\ConnectionPool $connectionPool */
            $connectionPool = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class);
            return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\Bernard\Driver\Doctrine\Driver::class, $connectionPool->getConnectionForTable('bernard_messages'));
        },
        'serializer' => function () {
            return new \Bernard\Serializer();
        },
    ],
];

if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ghost']['connections'][\DFAU\Ghost\CmsConfigurationFactory::DEFAULT_CONNECTION_NAME]['receivers'])) {
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ghost']['connections'][\DFAU\Ghost\CmsConfigurationFactory::DEFAULT_CONNECTION_NAME]['receivers'] = [];
}

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ghost']['connections'][\DFAU\Ghost\CmsConfigurationFactory::DEFAULT_CONNECTION_NAME]['subscribers'] = [
    \DFAU\Ghost\CmsConfigurationFactory::MIDDLEWARE_DIRECTION_PRODUCER => [],
    \DFAU\Ghost\CmsConfigurationFactory::MIDDLEWARE_DIRECTION_CONSUMER => [
        \Bernard\EventListener\ErrorLogSubscriber::class => [],
        \Bernard\EventListener\FailureSubscriber::class => [
            'depends' => \Bernard\EventListener\ErrorLogSubscriber::class,
            'arguments' => [
                'producer' => function ($connectionName, \Bernard\QueueFactory $queues) {
                    return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
                        \Bernard\Producer::class,
                        $queues,
                        \DFAU\Ghost\CmsConfigurationFactory::getEventDispatcherForDirectionAndConnectionName(
                            $queues,
                            \DFAU\Ghost\CmsConfigurationFactory::MIDDLEWARE_DIRECTION_PRODUCER,
                            $connectionName
                        )
                    );
                },
            ],
        ],
    ],
];
