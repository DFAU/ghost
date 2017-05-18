<?php


namespace DFAU\Ghost\Command;

use Bernard\Consumer;
use DFAU\Ghost\CmsConfigurationFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

class QueueCommandController extends CommandController
{

    /**
     * @param string $queueName
     * @param string $connectionName
     */
    public function consumeCommand(string $queueName, $connectionName = CmsConfigurationFactory::DEFAULT_CONNECTION_NAME)
    {
        $GLOBALS['TYPO3_DB']->setDatabaseName(TYPO3_db); //force disconnect before worker fork

        $workerPoolSize = 10;
        $wp = new \QXS\WorkerPool\WorkerPool();
        $wp->setWorkerPoolSize($workerPoolSize)
           ->create(new \QXS\WorkerPool\ClosureWorker(function ($i) use ($queueName, $connectionName) {
               $GLOBALS['worker-id'] = $i;
               $queues = CmsConfigurationFactory::getQueueFactoryForConnectionName($connectionName);

               /** @var Consumer $consumer */
               $consumer = GeneralUtility::makeInstance(
                   Consumer::class,
                   CmsConfigurationFactory::getRecieversForConnectionName($connectionName),
                   CmsConfigurationFactory::getMiddlewareForDirectionAndConnectionName($queues, CmsConfigurationFactory::MIDDLEWARE_DIRECTION_CONSUMER, $connectionName));

               $consumer->consume($queues->create($queueName));
           }));

        for ($i = 0; $i < $workerPoolSize; $i++) {
            $wp->run($i);
        }

        $wp->waitForAllWorkers();
    }

}