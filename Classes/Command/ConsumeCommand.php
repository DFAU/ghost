<?php

namespace DFAU\Ghost\Command;

use TYPO3\CMS\Core\Database\ConnectionPool;
use Bernard\Consumer;
use Bernard\Queue\RoundRobinQueue;
use DFAU\Ghost\CmsConfigurationFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ConsumeCommand extends Command
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this->setDescription('Consume list of Bernard Messages');
        $this->addArgument(
            'queueNames',
            InputArgument::REQUIRED,
            'name of queue to progress'
        );
        $this->addArgument(
            'maxRuntime',
            InputArgument::OPTIONAL,
            'maximum Runtime for this task',
            '600'
        );
        $this->addArgument(
            'connectionName',
            InputArgument::OPTIONAL,
            'Name of DB Connection',
            '_default'
        );
        $this->addArgument(
            'workerPoolSize',
            InputArgument::OPTIONAL,
            'Number of Workers in Workerpool',
            '1'
        );
    }

    /**
     * @inheritdoc
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $arguments = $input->getArguments();
        $workerPoolSize = $arguments['workerPoolSize'];
        $maxRuntime = $arguments['maxRuntime'];
        $connectionName = $arguments['connectionName'];
        $queueNames = $arguments['queueNames'];

        /*, $maxRuntime = PHP_INT_MAX, $connectionName = CmsConfigurationFactory::DEFAULT_CONNECTION_NAME);*/
        $queueNames = GeneralUtility::trimExplode(',', $queueNames, true);

        if ($workerPoolSize > 1) {
            //force disconnect before worker fork
            $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
            $connectionPool->getConnectionForTable('bernard_messages')->close();
        }

        $queueWorker = function ($i) use ($queueNames, $connectionName, $maxRuntime, $output) {
            $GLOBALS['worker-id'] = $i;
            $queueFactory = CmsConfigurationFactory::getQueueFactoryForConnectionName($connectionName);

            /** @var Consumer $consumer */
            $consumer = GeneralUtility::makeInstance(
                Consumer::class,
                CmsConfigurationFactory::getRecieversForConnectionName($connectionName),
                CmsConfigurationFactory::getEventDispatcherForDirectionAndConnectionName($queueFactory, CmsConfigurationFactory::MIDDLEWARE_DIRECTION_CONSUMER, $connectionName)
            );

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
                $output->writeln('Error: could not create queue for worker:' . $i);
            }
        };

        $queueWorker(1);
        return Command::SUCCESS;
    }
}
