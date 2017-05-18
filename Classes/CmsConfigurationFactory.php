<?php


namespace DFAU\Ghost;


use Bernard\Middleware\MiddlewareBuilder;
use Bernard\QueueFactory;
use Bernard\Router;
use Bernard\Router\SimpleRouter;
use DFAU\Ghost\Exception\IncompleteConnectionConfigurationException;
use DFAU\Ghost\Exception\UndefinedConnectionException;
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
     * @return array
     * @throws IncompleteConnectionConfigurationException
     * @throws UndefinedConnectionException
     */
    protected static function getConnectionConfiguration(string $connectionName = self::DEFAULT_CONNECTION_NAME, string $configurationPath = null) : array {
        if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ghost']['connections'][$connectionName])) {
            throw new UndefinedConnectionException('The connection name "' .  $connectionName . '" is not defined in $GLOBALS[\'TYPO3_CONF_VARS\'][\'EXTCONF\'][\'ghost\'][\'connections\']', 1494840181);
        }

        $connectionConfiguration = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ghost']['connections'][self::DEFAULT_CONNECTION_NAME];
        if ($connectionName !== self::DEFAULT_CONNECTION_NAME) {
            ArrayUtility::mergeRecursiveWithOverrule($connectionConfiguration, $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ghost']['connections'][$connectionName]);
        }

        if ($configurationPath !== null) {
            try {
                return ArrayUtility::getValueByPath($connectionConfiguration, $configurationPath);
            } catch (\RuntimeException $exception) {
                throw new IncompleteConnectionConfigurationException('The connection configuration for connection name "' . $connectionName . '" is missing the configuration path "' . $configurationPath . '"', 1494844217);
            }
        }

        return $connectionConfiguration;
    }


    /**
     * @param string $connectionName
     * @return QueueFactory
     * @throws UndefinedConnectionException
     */
    public static function getQueueFactoryForConnectionName(string $connectionName = self::DEFAULT_CONNECTION_NAME) : QueueFactory
    {
        static $queueFactories = [];

        if (!isset($queueFactories[$connectionName])) {
            $queueFactoryConfiguration = self::getConnectionConfiguration($connectionName, 'queueFactory');
            $queueFactoryArguments = array_map('call_user_func', $queueFactoryConfiguration['arguments']);
            array_unshift($queueFactoryArguments, $queueFactoryConfiguration['className']);
            $queueFactories[$connectionName] = call_user_func_array([GeneralUtility::class, 'makeInstance'], $queueFactoryArguments);
        }

        return $queueFactories[$connectionName];
    }

    /**
     * @param string $connectionName
     * @return Router
     */
    public static function getRecieversForConnectionName(string $connectionName = self::DEFAULT_CONNECTION_NAME) : Router
    {
        $receiverConfiguration = self::getConnectionConfiguration($connectionName, 'receivers');

        /** @var SimpleRouter $router */
        $router = GeneralUtility::makeInstance(SimpleRouter::class, $receiverConfiguration);
        return $router;
    }

    /**
     * @param string $direction
     * @param string $connectionName
     * @return MiddlewareBuilder
     */
    public static function getMiddlewareForDirectionAndConnectionName(QueueFactory $queues, string $direction, string $connectionName = self::DEFAULT_CONNECTION_NAME) : MiddlewareBuilder
    {
        $chainConfiguration = self::getConnectionConfiguration($connectionName, 'middleware/'. $direction);
        /** @var DependencyOrderingService $orderingService */
        $orderingService = GeneralUtility::makeInstance(DependencyOrderingService::class);
        $middlewareChain = $orderingService->orderByDependencies($chainConfiguration, 'before', 'depends');
        array_walk($middlewareChain, function(&$middleware, $middlewareClassName) use ($queues) {
            $middlewareArguments = array_map(function($argument) use ($queues) { return call_user_func($argument, $queues); }, isset($middleware['arguments']) ? $middleware['arguments'] : []);
            array_unshift($middlewareArguments, $middlewareClassName);
            $middleware = call_user_func_array([GeneralUtility::class, 'makeInstance'], $middlewareArguments);
        });

        /** @var MiddlewareBuilder $middleware */
        $middleware = GeneralUtility::makeInstance(MiddlewareBuilder::class, $middlewareChain);
        return $middleware;
    }
}