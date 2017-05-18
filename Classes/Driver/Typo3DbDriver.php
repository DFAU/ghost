<?php


namespace DFAU\Ghost\Driver;


use TYPO3\CMS\Core\Database\DatabaseConnection;

class Typo3DbDriver implements \Bernard\Driver
{

    /**
     * @var DatabaseConnection
     */
    protected $connection;

    /**
     * {@inheritDoc}
     */
    public function __construct()
    {
        $this->connection = $GLOBALS['TYPO3_DB'];
        $this->connection->connectDB();
    }

    /**
     * {@inheritDoc}
     */
    public function listQueues()
    {
        return $this->connection->exec_SELECTgetRows('name', 'bernard_queues', '');
    }

    /**
     * {@inheritDoc}
     */
    public function createQueue($queueName)
    {
        $this->connection->exec_INSERTquery('bernard_queues', array('name' => $queueName));
    }

    /**
     * {@inheritDoc}
     */
    public function countMessages($queueName)
    {
        return $this->connection->exec_SELECTcountRows('id', 'bernard_messages', 'queue = ' . $this->connection->fullQuoteStr($queueName, 'bernard_messages') . ' AND visible = 1');
    }

    /**
     * {@inheritDoc}
     */
    public function pushMessage($queueName, $message)
    {
        $data = array(
            'queue'   => $queueName,
            'message' => $message,
            'sentAt'  => date("Y-m-d H:i:s"),
        );

        $this->createQueue($queueName);
        $this->connection->exec_INSERTquery('bernard_messages', $data);
    }

    /**
     * {@inheritDoc}
     */
    public function popMessage($queueName, $interval = 5)
    {
        $runtime = microtime(true) + $interval;

        while (microtime(true) < $runtime) {
//            $this->connection->sql_query('SET autocommit = 0;');
            $this->connection->sql_query('START TRANSACTION');

            try {
                $message = $this->doPopMessage($queueName);

                $this->connection->sql_query('COMMIT');

            } catch (\Exception $e) {
                $this->connection->sql_query('ROLLBACK');
            }
//            $this->connection->sql_query('SET autocommit = 1;');

            if (isset($message)) {
                return $message;
            }

            //sleep for 10 ms
            usleep(10000);
        }
    }

    protected function doPopMessage($queueName)
    {
        $result = $this->connection->exec_SELECTgetRows(
            'message, id',
            'bernard_messages',
            'queue = ' . $this->connection->fullQuoteStr($queueName, 'bernard_messages') . ' AND visible = 1',
            '',
            'sentAt, id',
            '1 FOR UPDATE'
        );

        if (isset($result[0])) {
            $this->connection->exec_UPDATEquery('bernard_messages', 'id = ' . $result[0]['id'], array('visible' => 0));
            return array_values($result[0]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function acknowledgeMessage($queueName, $receipt)
    {
        $this->connection->exec_DELETEquery('bernard_messages', 'id = ' . $receipt .' AND queue LIKE "' . $queueName . '"');
    }

    /**
     * {@inheritDoc}
     */
    public function peekQueue($queueName, $index = 0, $limit = 20)
    {
        return $this->connection->exec_SELECTgetRows(
            'message',
            'bernard_messages',
            'queue = ' . $this->connection->fullQuoteStr($queueName, 'bernard_messages'),
            '',
            '' ,
            $index . ',' . $limit
        );
    }

    /**
     * {@inheritDoc}
     */
    public function removeQueue($queueName)
    {
        $this->connection->exec_DELETEquery('bernard_messages', 'queue LIKE ' . $this->connection->fullQuoteStr($queueName, 'bernard_messages'));
        $this->connection->exec_DELETEquery('bernard_queues', 'name LIKE ' . $this->connection->fullQuoteStr($queueName, 'bernard_messages'));
    }

    /**
     * {@inheritDoc}
     */
    public function info()
    {
        return [];
    }
}
