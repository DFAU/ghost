<?php


namespace DFAU\Ghost\Command;

use Bernard\Consumer;
use Bernard\Queue\RoundRobinQueue;
use DFAU\Ghost\CmsConfigurationFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

class QueueCommandController extends CommandController
{

    /**
     * @param string $queueNames Seperated by comma
     * @param int $workerPoolSize
     * @param int $maxRuntime
     * @param string $connectionName
     */
    public function consumeCommand(string $queueNames, $workerPoolSize = 1, $maxRuntime = PHP_INT_MAX, $connectionName = CmsConfigurationFactory::DEFAULT_CONNECTION_NAME)
    {
        $queueNames = GeneralUtility::trimExplode(',', $queueNames, true);

        $queueWorker = function ($i) use ($queueNames, $connectionName, $maxRuntime) {
            $GLOBALS['worker-id'] = $i;
            $queueFactory = CmsConfigurationFactory::getQueueFactoryForConnectionName($connectionName);

            /** @var Consumer $consumer */
            $consumer = GeneralUtility::makeInstance(
                Consumer::class,
                CmsConfigurationFactory::getRecieversForConnectionName($connectionName),
                CmsConfigurationFactory::getEventDispatcherForDirectionAndConnectionName($queueFactory, CmsConfigurationFactory::MIDDLEWARE_DIRECTION_CONSUMER, $connectionName));

            $queue = null;
            if (count($queueNames) > 1) {
                $queues = array_map([$queueFactory, 'create'], $queueNames);
                $queue = new RoundRobinQueue($queues);
            } elseif (isset($queueNames[0])) {
                $queue = $queueFactory->create($queueNames[0]);
            }

            if ($queue) {
                $consumer->consume($queue, [ 'max-runtime' => $maxRuntime ]);
            }
        };

        if ($workerPoolSize > 1) {
            $wp = new \QXS\WorkerPool\WorkerPool();
            $wp->setWorkerPoolSize($workerPoolSize)->create(new \QXS\WorkerPool\ClosureWorker($queueWorker));

            for ($i = 0; $i < $workerPoolSize; $i++) {
                $wp->run($i);
            }

            $wp->waitForAllWorkers();
        } else {
            $queueWorker(1);
        }
    }

}