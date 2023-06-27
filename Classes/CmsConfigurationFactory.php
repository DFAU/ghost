<?php

namespace DFAU\Ghost;

use Bernard\QueueFactory;
use Bernard\Router;
use Bernard\Router\ReceiverMapRouter;
use DFAU\Ghost\Exception\IncompleteConnectionConfigurationException;
use DFAU\Ghost\Exception\UndefinedConnectionException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Service\DependencyOrderingService;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CmsConfigurationFactory
{
    const DEFAULT_CONNECTION_NAME = '_default';

    const MIDDLEWARE_DIRECTION_PRODUCER = 'producer';
    const MIDDLEWARE_DIRECTION_CONSUMER = 'consumer';

    /**
     * @param string $connectionName
     * @param string|null $configurationPath
     * @throws IncompleteConnectionConfigurationException
     * @throws UndefinedConnectionException
     * @return array
     */
    protected static function getConnectionConfiguration(
        string $connectionName = self::DEFAULT_CONNECTION_NAME,
        string $configurationPath = null
    ): array {
        if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ghost']['connections'][$connectionName])) {
            throw new UndefinedConnectionException(
                'The connection name "' . $connectionName . '" is not defined in $GLOBALS[\'TYPO3_CONF_VARS\'][\'EXTCONF\'][\'ghost\'][\'connections\']',
                1494840181
            );
        }

        $connectionConfiguration = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ghost']['connections'][self::DEFAULT_CONNECTION_NAME];
        if ($connectionName !== self::DEFAULT_CONNECTION_NAME) {
            ArrayUtility::mergeRecursiveWithOverrule(
                $connectionConfiguration,
                $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ghost']['connections'][$connectionName]
            );
        }

        if ($configurationPath !== null) {
            try {
                return ArrayUtility::getValueByPath($connectionConfiguration, $configurationPath);
            } catch (\RuntimeException $exception) {
                throw new IncompleteConnectionConfigurationException(
                    'The connection configuration for connection name "' . $connectionName . '" is missing the configuration path "' . $configurationPath . '"',
                    1494844217
                );
            }
        }

        return $connectionConfiguration;
    }

    /**
     * @param string $connectionName
     * @throws UndefinedConnectionException
     * @return QueueFactory
     */
    public static function getQueueFactoryForConnectionName(
        string $connectionName = self::DEFAULT_CONNECTION_NAME
    ): QueueFactory {
        static $queueFactories = [];

        if (!isset($queueFactories[$connectionName])) {
            $queueFactoryConfiguration = self::getConnectionConfiguration($connectionName, 'queueFactory');
            $queueFactoryArguments = array_map('call_user_func', $queueFactoryConfiguration['arguments']);
            array_unshift($queueFactoryArguments, $queueFactoryConfiguration['className']);
            $queueFactories[$connectionName] = call_user_func_array(
                [GeneralUtility::class, 'makeInstance'],
                $queueFactoryArguments
            );
        }

        return $queueFactories[$connectionName];
    }

    /**
     * @param string $connectionName
     * @return Router
     */
    public static function getRecieversForConnectionName(string $connectionName = self::DEFAULT_CONNECTION_NAME): Router
    {
        $receiverConfiguration = self::getConnectionConfiguration($connectionName, 'receivers');

        // TODO v10Change - I'm not 100% sure this is the right router
        /** @var ReceiverMapRouter $router */
        $router = GeneralUtility::makeInstance(ReceiverMapRouter::class, $receiverConfiguration);
        return $router;
    }

    /**
     * @param string $direction
     * @param string $connectionName
     * @return EventDispatcher
     */
    public static function getEventDispatcherForDirectionAndConnectionName(
        QueueFactory $queues,
        string $direction,
        string $connectionName = self::DEFAULT_CONNECTION_NAME
    ): EventDispatcher {
        $subscribers = self::getConnectionConfiguration($connectionName, 'subscribers/' . $direction);
        /** @var DependencyOrderingService $orderingService */
        $orderingService = GeneralUtility::makeInstance(DependencyOrderingService::class);
        $subscriberChain = $orderingService->orderByDependencies($subscribers, 'before', 'depends');
        array_walk(
            $subscriberChain,
            function (&$eventListener, $eventListenerClassName) use ($queues, $connectionName) {
                $middlewareArguments = array_map(function ($argument) use ($queues, $connectionName) {
                    return call_user_func($argument, $connectionName, $queues);
                }, isset($eventListener['arguments']) ? $eventListener['arguments'] : []);
                array_unshift($middlewareArguments, $eventListenerClassName);
                $eventListener = call_user_func_array([GeneralUtility::class, 'makeInstance'], $middlewareArguments);
            }
        );

        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = GeneralUtility::makeInstance(EventDispatcher::class);
        array_walk($subscriberChain, function ($subscriber) use ($eventDispatcher) {
            $eventDispatcher->addSubscriber($subscriber);
        });

        return $eventDispatcher;
    }
}
