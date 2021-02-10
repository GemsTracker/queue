<?php

class QueueController extends \Gems_Controller_ModelSnippetActionAbstract
{

    /**
     * @var \Gems_User_User
     */
    public $currentUser;

    /**
     * The snippets used for the index action, before those in autofilter
     *
     * @var mixed String or array of snippets name
     */
    protected $indexStartSnippets = ['Generic\\ContentTitleSnippet', 'Queue\\QueueAutosearchFormSnippet'];

    /**
     * The parameters used for the autofilter action.
     *
     * When the value is a function name of that object, then that functions is executed
     * with the array key as single parameter and the return value is set as the used value
     * - unless the key is an integer in which case the code is executed but the return value
     * is not stored.
     *
     * @var array Mixed key => value array for snippet initialization
     */
    protected $autofilterParameters = [
        'extraSort'   => [
            'gbq_id_batch' => SORT_ASC,
            'gbq_priority' => SORT_ASC,
        ],
    ];

    protected function createModel($detailed, $action)
    {
        $model = new \MUtil_Model_TableModel(\Gems\Queue\Batch\Stack\DatabaseStack::$databaseTable);

        $model->set('gbq_id_batch', [
            'label' => $this->_('Batch ID'),
        ]);

        $model->set('gbq_item_name', [
            'label' => $this->_('Item name'),
            'description' => $this->_('When a name is used and exists in a batch, it will not be added'),
        ]);

        $model->set('gbq_priority', [
            'label' => $this->_('Priority'),
        ]);

        if ($detailed) {
            $model->set('gbq_command', [
                'label' => $this->_('Command'),
            ]);

            $jsonType = new \MUtil\Model\Type\JsonData(10);
            $jsonType->apply($model, 'gbq_command', false);
        }

        return $model;
    }

    public function nextAction()
    {
        echo "Starting next!\n";
        $request = $this->getRequest();
        $batchId = $request->getParam('batch-id');

        if ($batchId === null) {
            $batchId = \Gems\Queue\Queue::DEFAULT_QUEUE_ID;
        }

        $stack = new \Gems\Queue\Batch\Stack\DatabaseStack($batchId, $this->currentUser->getUserId());
        if (!$stack->hasNext()) {
            //echo "I will sleep!";
            sleep(30);
        }

        // Only run one!
        $store = new \Gems\Queue\Batch\Store\ClassBatchStore();
        $store->incrementStepCount();

        $batch = new \Gems\Queue\Task\StoreTaskRunnerBatch($batchId, $stack, $store);
        $this->loader->applySource($batch);

        $batch->autoStart = true;
        $this->_helper->BatchRunner($batch, $this->_('Executing next queue item'), $this->accesslog);
    }

    protected function getTaskRunnerBatch($id)
    {
        $stack = new \Gems\Queue\Batch\Stack\DatabaseStack($id, $this->currentUser->getUserId());

        return new \Gems_Task_TaskRunnerBatch($id, $stack);
    }

    public function listenAction()
    {
        $request = $this->getRequest();
        $batchId = $request->getParam('batch-id');

        $writer = new Zend_Log_Writer_Stream(GEMS_ROOT_DIR . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'queue-listener.log');
        $listenLogger = new \Zend_Log($writer);

        $listener = new \Gems\Queue\Listener($listenLogger);
        $listener->listen();
    }
}
