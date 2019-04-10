<?php
defined('TYPO3_MODE') or die();

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ghost']['connections'][\DFAU\Ghost\CmsConfigurationFactory::DEFAULT_CONNECTION_NAME] = [
    'queueFactory' => [
        'className' => \Bernard\QueueFactory\PersistentFactory::class,
        'arguments' => [
            'driver' => function () {
                if (class_exists(\TYPO3\CMS\Core\Database\ConnectionPool::class)) {
                    /** @var \TYPO3\CMS\Core\Database\ConnectionPool $connectionPool */
                    $connectionPool = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class);
                    return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\Bernard\Driver\DoctrineDriver::class, $connectionPool->getConnectionForTable('bernard_messages'));
                } else {
                    return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\DFAU\Ghost\Driver\Typo3DbDriver::class);
                }
            },
            'serializer' => function () {
                return new \Bernard\Serializer();
            }
        ]
    ],
    'receivers' => [],
    'subscribers' => [
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
                    }
                ],
            ],
        ],
    ]
];

if (class_exists('redis')) {
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ghost']['connections']['redis'] = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ghost']['connections'][\DFAU\Ghost\CmsConfigurationFactory::DEFAULT_CONNECTION_NAME];
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ghost']['connections']['redis']['queueFactory']['arguments']['driver'] = function () {
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->setOption(Redis::OPT_PREFIX, 'ghost:');
        return new \Bernard\Driver\PhpRedisDriver($redis);
    };
}

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][\DFAU\Ghost\Command\ConsumeCommand::class] = \DFAU\Ghost\Command\ConsumeCommand::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\DFAU\Ghost\Command\ConsumeCommand::class] = [

];\DFAU\Ghost\Command\ConsumeCommand::class;