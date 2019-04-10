<?php

namespace DFAU\Ghost\Command;

use Bernard\Consumer;
use Bernard\Queue\RoundRobinQueue;
use DFAU\Ghost\CmsConfigurationFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConsumeCommand extends Command
{

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this->setDescription('Consume list of Bernard Messages')
        ->addOption(
        'queueNames',
        '',
        InputOption::VALUE_REQUIRED,
        'name of queue to progress'
        )
        ->addOption(
            'maxRuntime',
            '',
            InputOption::VALUE_OPTIONAL,
            'maximum Runtime for this task'
        )
        ->addOption(
            'connectionName',
            '',
            InputOption::VALUE_OPTIONAL,
            'Name of DB Connection'
        )
        ->addOption(
            'workerPoolSize',
            '',
            InputOption::VALUE_OPTIONAL,
            'Number of Workers in Workerpool'
        );
    }

    /**
     *
     * @inheritdoc
     *
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $options = $input->getOptions();
        $workerPoolSize = ($options['workerPoolSize']) ? $options['workerPoolSize'] : 1;
        $maxRuntime = ($options['maxRuntime']) ? $options['maxRuntime'] : PHP_INT_MAX;
        $connectionName = ($options['connectionName']) ? $options['connectionName'] : CmsConfigurationFactory::DEFAULT_CONNECTION_NAME;
        $queueNames = $options['queueNames'];

        /*, $maxRuntime = PHP_INT_MAX, $connectionName = CmsConfigurationFactory::DEFAULT_CONNECTION_NAME);*/
        $queueNames = GeneralUtility::trimExplode(',', $queueNames, true);

        if ($workerPoolSize > 1) {
            //force disconnect before worker fork
            if (class_exists(ConnectionPool::class)) {
                $connectionPool = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class);
                $connectionPool->getConnectionForTable('bernard_messages')->close();
            } else {
                $connectionPool = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class);
                $connectionPool->getConnectionForTable('bernard_messages')->close();
            }
        }

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
            } else {
                $this->output->outputLine('Error: could not create queue for worker:' . $i);
            }
        };

        if ($workerPoolSize > 1 && !class_exists(\QXS\WorkerPool\WorkerPool::class)) {
            $this->output->outputLine('Warning: Worker Pool Size is bigger than 1 and qxsch/worker-pool is not installed. Falling back to single worker.');
        }

        if ($workerPoolSize > 1 && class_exists(\QXS\WorkerPool\WorkerPool::class)) {
            $wp = new \QXS\WorkerPool\WorkerPool();
            $wp->setWorkerPoolSize($workerPoolSize)
                ->disableSemaphore()
                ->create(new \QXS\WorkerPool\ClosureWorker($queueWorker));

            for ($i = 0; $i < $workerPoolSize; $i++) {
                $wp->run($i);
            }

            $wp->waitForAllWorkers();
        } else {
            $queueWorker(1);
        }
    }

}