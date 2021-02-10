<?php

namespace Gems\Queue\Batch\Stack;


class DatabaseStack extends \MUtil_Batch_Stack_StackAbstract
{
    const LOW_PRIORITY = 10;
    const MEDIUM_PRIORITY = 20;
    const HIGH_PRIORITY = 30;
    const DIRECT_PRIORITY = 100;

    /**
     * @var string batch ID
     */
    protected $batchId;

    /**
     * @var string Table name
     */
    public static $databaseTable = 'gems__batch_queue';

    /**
     * @var \MUtil_Model_TableModel
     */
    protected $model;

    public function __construct($id, $userId = null)
    {
        $this->batchId = $id;
        $this->model = $this->createModel($userId);
    }

    protected function createModel($userId = null)
    {
        $model = new \MUtil_Model_TableModel(static::$databaseTable);
        \Gems_Model::setChangeFieldsByPrefix($model, 'gbq', $userId);
        return $model;
    }

    protected function _addCommand(array $command, $id = null, $priority = self::MEDIUM_PRIORITY, $delayUntil = null)
    {
        $encodedCommand = json_encode($command);
        if ($id !== null && $this->hasItem($id)) {
            return;
        }

        if ($priority === null) {
            $priority = self::MEDIUM_PRIORITY;
        }

        if ($delayUntil instanceof \DateTimeInterface) {
            $delayUntil = new \MUtil_Date($delayUntil->format('Y-m-d H:i:s'));
        }

        $data = [
            'gbq_id_batch' => $this->batchId,
            'gbq_priority' => $priority,
            'gbq_command' => $encodedCommand,
            'gbq_item_name' => $id,
            'gbq_delay_until' => $delayUntil,
        ];

        \MUtil_Echo::track($data);
        $result = $this->model->save($data);
        \MUtil_Echo::track($result);

        return true;
    }

    /**
     * Add an execution step to the command stack.
     *
     * @param string $method Name of a method of the batch object
     * @param array  $params Array with scalars, as many parameters as needed allowed
     * @return boolean When true, increment the number of commands, otherwise the command existed
     */
    public function addStep($method, array $params, $taskId = null, $priority = self::MEDIUM_PRIORITY, $delayUntil = null)
    {
        $this->_checkParams($params);

        return $this->_addCommand([$method, $params], $taskId, $priority, $delayUntil);
    }

    /**
     * Check if there is an item in the current batch with a specific name/id
     *
     * @param $id
     * @return bool
     * @throws \Zend_Db_Select_Exception
     */
    protected function hasItem($id)
    {
        return (bool)$this->model->getItemCount([
            'gbq_id_batch' => $this->batchId,
            'gbq_item_name' => $id,
        ]);

        /*$select = $this->db->select();
        $select->from($this->databaseTable, ['queueSize' => new \Zend_Db_Expr('COUNT(*)')])
            ->where('gbq_id_batch = ?', $this->batchId)
            ->where('gbq_item_name = ?', $id);
        return (bool)$this->db->fetchOne($select);*/
    }

    /**
     * Return true when there still exist unexecuted commands
     *
     * @return boolean
     */
    public function hasNext()
    {
        $adapter = $this->model->getAdapter();
        return (bool)$this->model->getItemCount([
            'gbq_id_batch' => $this->batchId,
            [
                'gbq_delay_until IS NULL',
                new \Zend_Db_Expr('gbq_delay_until < ' . $adapter->quote((new \DateTimeImmutable())->format('Y-m-d H:i:s'))),
            ]
        ]);

        /*$select = $this->db->select();
        $select->from($this->databaseTable, ['queueSize' => new \Zend_Db_Expr('COUNT(*)')])
            ->where('gbq_id_batch = ?', $this->batchId);
        return (bool)$this->db->fetchOne($select);*/
    }

    /**
     * Return the next command
     *
     * @return array 0 => command, 1 => params
     */
    public function getNext()
    {
        $result = $this->getNextDatabaseItem();

        if (isset($result['gbq_command'])) {
            return json_decode($result['gbq_command'], true);
        }

        /*$select = $this->db->select();
        $select->from($this->databaseTable)
            ->where('gbq_id_batch = ?', $this->batchId)
            ->order('gbq_priority, gbq_id_item');
        return $this->db->fetchRow($select);*/
    }

    protected function getNextDatabaseItem()
    {
        $adapter = $this->model->getAdapter();
        return $this->model->loadFirst([
            'gbq_id_batch' => $this->batchId,
            [
                'gbq_delay_until IS NULL',
                new \Zend_Db_Expr('gbq_delay_until < ' . $adapter->quote((new \DateTimeImmutable())->format('Y-m-d H:i:s'))),
            ]
        ], [
            'gbq_priority', 'gbq_id_item'
        ]);
    }

    /**
     * Run the next command
     *
     * @return void
     */
    public function gotoNext()
    {
        $current = $this->getNextDatabaseItem();
        if ($current && isset($current['gbq_id_item'])) {
            $this->model->delete([
                'gbq_id_item' => $current['gbq_id_item']
            ]);
            /*$this->db->delete($this->databaseTable,
                [
                    'gbq_id_item = ?' => $current['gbq_id_item'],
                    'gbq_id_batch = ?' => $this->batchId,
                ]);*/
        }
    }

    /**
     * Reset the stack
     *
     * @return \MUtil_Batch_Stack_Stackinterface (continuation pattern)
     */
    public function reset()
    {
        $this->model->delete(['gbq_id_batch' => $this->batchId]);

        /*$this->db->delete($this->databaseTable, [
            'gbq_id_batch = ?' => $this->batchId,
        ]);*/

        return $this;


    }
}
