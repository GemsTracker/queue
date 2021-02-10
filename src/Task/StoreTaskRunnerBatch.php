<?php


namespace Gems\Queue\Task;


use Gems\Queue\Batch\Store\BatchStoreInterface;
use Gems\Queue\Batch\Store\CacheBatchStore;
use Gems\Queue\Batch\Store\SessionBatchStore;

class StoreTaskRunnerBatch extends \Gems_Task_TaskRunnerBatch
{
    /**
     *
     * @var float The timer for _checkReport()
     */
    private $_checkReportStart = null;

    /**
     * An id unique for this session.
     *
     * @var string Unique id
     */
    private $_id;

    /**
     * Stack to keep existing id's.
     *
     * @var array
     */
    private static $_idStack = [];

    /**
     * Holds the last message set by the batch job
     *
     * @var string
     */
    private $_lastMessage = null;

    /**
     *
     * @var array of callables for logging addMessage messages
     */
    private $_loggers = array();

    /**
     *
     * @var string
     */
    private $_messageLogFile;

    /**
     *
     * @var boolean
     */
    private $_messageLogWhenAdding = false;

    /**
     *
     * @var boolean
     */
    private $_messageLogWhenSetting = false;

    /**
     * Progress template
     *
     * Available placeholders:
     * {total}      Total time
     * {elapsed}    Elapsed time
     * {remaining}  Remaining time
     * {percent}    Progress percent without the % sign
     * {msg}        Message reveiced
     *
     * @var string
     */
    private $_progressTemplate = "{percent}% {msg}";

    /**
     * @var \Zend_Cache_Core
     */
    public $cache;

    protected $id;

    /**
     * @var \Gems_Loader
     */
    protected $loader;

    /**
     * @var BatchStoreInterface
     */
    protected $store;

    public function __construct($id, \MUtil_Batch_Stack_Stackinterface $stack = null, BatchStoreInterface $store = null)
    {
        if (\MUtil_Console::isConsole()) {
            $this->method = self::CONS;
        }

        $id = preg_replace('/[^a-zA-Z0-9_]/', '', $id);

        if (isset(self::$_idStack[$id])) {
            throw new \MUtil_Batch_BatchException("Duplicate batch id created: '$id'");
        }
        self::$_idStack[$id] = $id;

        $this->id = $this->_id = $id;

        if (null === $stack) {
            $stack = new \MUtil_Batch_Stack_SessionStack($id);
        }
        $this->stack = $stack;

        if ($store !== null) {
            $this->store = $store;
        }
    }

    public function afterRegistry()
    {
        parent::afterRegistry();
        if (!$this->store instanceof BatchStoreInterface) {
            $this->_initSession($this->_id);
        }
    }

    /**
     * Check if the aplication should report back to the user
     *
     * @return boolean True when application should report to the user
     */
    private function _checkReport()
    {
        // @TODO Might be confusing if one of the first steps adds more steps, make this optional?
        if (1 === $this->store->getProcessed()) {
            return true;
        }

        if (null === $this->_checkReportStart) {
            $this->_checkReportStart = microtime(true) + ($this->minimalStepDurationMs / 1000);
            return false;
        }

        if (microtime(true) > $this->_checkReportStart) {
            $this->_checkReportStart = null;
            return true;
        }

        return false;
    }

    /**
     * Signal an loop item has to be run again.
     */
    protected function _extraRun()
    {
        $this->store->incrementStepCount();
        $this->store->incrementProcessed();
    }

    /**
     * Helper function to complete the progressbar.
     */
    protected function _finishBar()
    {
        $this->store->setFinished();

        $bar = $this->getProgressBar();
        $bar->finish();
    }

    private function _initSession($id)
    {
        $safeClass = preg_replace('/[^a-zA-Z0-9_]/', '', get_class($this));
        if ($this->isConsole()) {
            $this->store = new CacheBatchStore($safeClass . '_' . $id, $this->cache);
        } else {
            $this->store = new SessionBatchStore($safeClass . '_' . $id);
        }
    }

    /**
     * Add to exception store
     * @param \Exception $e
     * @return \MUtil_Batch_BatchAbstract (continuation pattern)
     */
    public function addException(\Exception $e)
    {
        $message = $e->getMessage();

        $this->addMessage($message);
        $this->store->addException($e);

        if ($this->log instanceof \Zend_Log) {
            $messages[] = $message;

            $previous = $e->getPrevious();
            while ($previous) {
                $messages[] = '  Previous exception: ' . $previous->getMessage();
                $previous = $previous->getPrevious();
            }
            $messages[] = $e->getTraceAsString();

            $this->log->log(implode("\n", $messages), \Zend_Log::ERR);
        }
        return $this;
    }

    /**
     *
     * @param callable $function Function to call with text message
     * @return $this
     */
    public function addLogFunction($function)
    {
        $this->_loggers[] = $function;

        return $this;
    }

    /**
     * Add a message to the message stack.
     *
     * @param string $text A message to the user
     * @return \MUtil_Batch_BatchAbstract (continuation pattern)
     */
    public function addMessage($text)
    {
        $this->store->addMessage($text);
        $this->_lastMessage = $text;

        if ($this->_messageLogWhenAdding && $this->_messageLogFile) {
            $this->logMessage($text);
        }

        foreach ($this->_loggers as $function) {
            call_user_func($function, $text);
        }

        return $this;
    }

    /**
     * Add an execution step to the command stack.
     *
     * @param string $method Name of a method of this object
     * @param mixed $param1 Optional scalar or array with scalars, as many parameters as needed allowed
     * @param mixed $param2 ...
     * @return \MUtil_Task_TaskBatch (continuation pattern)
     */
    protected function addStep($method, $param1 = null)
    {
        if (! method_exists($this, $method)) {
            throw new \MUtil_Batch_BatchException("Invalid batch method: '$method'.");
        }

        $params = array_slice(func_get_args(), 1);

        if ($this->stack->addStep($method, $params)) {
            $this->store->incrementStepCount();
        }

        return $this;
    }

    /**
     * Allow to add steps to the counter
     *
     * This should only be used by iterable tasks that execute in more then 1 step
     *
     * @param int $number
     */
    public function addStepCount($number)
    {
        if ($number > 0) {
            $this->store->addStepcount($number);
        }
    }

    /**
     * Increment a named counter
     *
     * @param string $name
     * @param integer $add
     * @return integer
     */
    public function addToCounter($name, $add = 1)
    {
        return $this->store->addToCounter($name, $add);
    }

    /**
     * The number of commands in this batch (both processed
     * and unprocessed).
     *
     * @return int
     */
    public function count()
    {
        return $this->store->getCount();
    }

    /**
     * Return the value of a named counter
     *
     * @param string $name
     * @return integer
     */
    public function getCounter($name)
    {
        return $this->store->getCount($name);
    }

    /**
     * Return the stored exceptions.
     *
     * @return array of \Exceptions
     */
    public function getExceptions()
    {
        return $this->store->getExceptions();
    }

    /**
     * Returns the prefix used for the function names for the PUSH method to avoid naming clashes.
     *
     * Set automatically to get_class($this) . '_' $this->_id . '_', use different name
     * in case of name clashes.
     *
     * @see setFunctionPrefix()
     *
     * @return string
     */
    protected function getFunctionPrefix()
    {
        if (! $this->_functionPrefix) {
            $prefix = str_replace('\\', '', get_class($this) . '_' . $this->_id . '_');
            $this->setFunctionPrefix($prefix);
        }

        return (string) $this->_functionPrefix;
    }

    /**
     * Return the batch id
     *
     * @return string
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * Returns the lat message set for feedback to the user.
     * @return string
     */
    public function getLastMessage()
    {
        return $this->_lastMessage;
    }

    /**
     * Get a message from the message stack with a specific id.
     *
     * @param scalar $id
     * @param string $default A default message
     * @return string
     */
    public function getMessage($id, $default = null)
    {
        return $this->store->getMessage($id, $default);
    }

    /**
     * String of messages from the batch
     *
     * Do not forget to reset() the batch if you're done with it after
     * displaying the report.
     *
     * @param boolean $reset When true the batch is reset afterwards
     * @return array
     */
    public function getMessages($reset = false)
    {
        return $this->store->getMessages($reset);
    }

    /**
     * Return a progress panel object, set up to be used by
     * this batch.
     *
     * @param \Zend_View_Abstract $view
     * @param mixed $arg_array \MUtil_Ra::args() arguments to populate progress bar with
     * @return \MUtil_Html_ProgressPanel
     */
    public function getPanel(\Zend_View_Abstract $view, $arg_array = null)
    {
        $args = func_get_args();

        \MUtil_JQuery::enableView($view);
        //$jquery = $view->jQuery();
        //$jquery->enable();

        if (isset($this->finishUrl)) {
            $urlFinish = $this->finishUrl;
        } else {
            $urlFinish = $view->url(array($this->progressParameterName => $this->progressParameterReportValue));
        }
        $urlRun    = $view->url(array($this->progressParameterName => $this->progressParameterRunValue));

        $panel = new \MUtil_Html_ProgressPanel($args);
        $panel->id = $this->_id;

        $mutilBatchDir = MUTIL_LIBRARY_DIR . DIRECTORY_SEPARATOR . 'MUtil' . DIRECTORY_SEPARATOR . 'Batch' . DIRECTORY_SEPARATOR;

        $js = new \MUtil_Html_Code_JavaScript($mutilBatchDir . '/Batch' . $this->method . '.js');
        $js->setInHeader(false);
        // Set the fields, in case they where not set earlier
        $js->setDefault('__AUTOSTART__', $this->autoStart ? 'true' : 'false');
        $js->setDefault('{PANEL_ID}', '#' . $this->_id);
        $js->setDefault('{FORM_ID}', $this->_formId);
        $js->setDefault('{TEMPLATE}', $this->_progressTemplate);
        $js->setDefault('{TEXT_ID}', $panel->getDefaultChildTag() . '.' . $panel->progressTextClass);
        $js->setDefault('{URL_FINISH}', addcslashes($urlFinish, "/"));
        $js->setDefault('{URL_START_RUN}', addcslashes($urlRun, "/"));
        $js->setDefault('FUNCTION_PREFIX_', $this->getFunctionPrefix());

        $panel->append($js);

        return $panel;
    }

    /**
     * The Zend ProgressBar handles the communication through
     * an adapter interface.
     *
     * @return \Zend_ProgressBar
     */
    public function getProgressBar()
    {
        if (! $this->progressBar instanceof \Zend_ProgressBar) {
            $this->setProgressBar(
                new \Zend_ProgressBar($this->getProgressBarAdapter(), 0, 100, $this->_id . '_pb')
            );
        }
        return $this->progressBar;
    }

    /**
     * Get the current progress percentage
     *
     * @return float
     */
    public function getProgressPercentage()
    {
        return round($this->store->getProcessed() / max($this->store->getCount(), 1) * 100, 2);
    }

    /**
     * Return a variable from the session store.
     *
     * @param string $name Name of the variable
     * @return mixed (continuation pattern)
     */
    public function getSessionVariable($name)
    {
        return $this->store->getVariable($name);
    }

    /**
     * Return the variables from the session store.
     *
     * @return \ArrayObject or null
     */
    protected function getSessionVariables()
    {
        return $this->store->getVariables();
    }

    /**
     * Return whether a session variable exists in the session store.
     *
     * @param string $name Name of the variable
     * @return boolean
     */
    public function hasSessionVariable($name)
    {
        return $this->store->hasSessionVariable($name);
    }

    /**
     * Return true after commands all have been ran.
     *
     * @return boolean
     */
    public function isFinished()
    {
        return $this->store->isFinished();
    }

    /**
     * Return true when at least one command has been loaded.
     *
     * @return boolean
     */
    public function isLoaded()
    {
        return $this->store->getCount() || $this->store->getProcessed();
    }

    /**
     * Reset a named counter
     *
     * @param string $name
     * @return $this
     */
    public function resetCounter($name)
    {
        $this->store->resetCounter($name);

        return $this;
    }

    /**
     * Reset a message on the message stack with a specific id.
     *
     * @param scalar $id
     * @return \MUtil_Batch_BatchAbstract (continuation pattern)
     */
    public function resetMessage($id)
    {
        $this->store->resetMessage($id);

        return $this;
    }

    /**
     * Run the whole batch at once, without communicating with a progress bar.
     *
     * @return int Number of steps taken
     */
    public function runAll()
    {
        // [Try to] remove the maxumum execution time for this session
        @ini_set("max_execution_time", 0);
        @set_time_limit(0);

        while ($this->step());

        return $this->store->getProcessed();
    }

    /**
     * Run the whole batch at once, while still communicating with a progress bar.
     *
     * @return boolean True when something ran
     */
    public function runContinuous()
    {
        // Is there something to run?
        if ($this->isFinished() || (! $this->isLoaded())) {
            return false;
        }

        // [Try to] remove the maxumum execution time for this session
        @ini_set("max_execution_time", 0);
        @set_time_limit(0);

        while ($this->step()) {
            if ($this->_checkReport()) {
                // Communicate progress
                $this->_updateBar();
            }
        }
        $this->_updateBar();
        $this->_finishBar();

        return true;
    }

    /**
     *
     * @param string $task Class name of task
     * @param array $params Parameters used in the call to execute
     * @return boolean true when the task has completed, otherwise task is rerun.
     * @throws \MUtil_Batch_BatchException
     */
    public function runTask($task, array $params = array())
    {
        // \MUtil_Echo::track($task);

        $taskObject = $this->loader->getTaskLoader()->getTask($task);
        if ($taskObject instanceof \MUtil_Task_TaskInterface) {
            $taskObject->setBatch($this);
        }

        if ($taskObject instanceof \MUtil_Task_TaskInterface) {
            call_user_func_array(array($taskObject, 'execute'), $params);

            return $taskObject->isFinished();

        } else {
            throw new \MUtil_Batch_BatchException(sprintf('ERROR: Task by name %s not found', $task));
        }
    }

    /**
     * Add/set a message on the message stack with a specific id.
     *
     * @param scalar $id
     * @param string $text A message to the user
     * @return \MUtil_Batch_BatchAbstract (continuation pattern)
     */
    public function setMessage($id, $text)
    {
        $this->store->setMessage($id, $text);
        $this->_lastMessage = $text;

        if ($this->_messageLogWhenSetting && $this->_messageLogFile) {
            $this->logMessage($text);
        }

        return $this;
    }

    /**
     *
     * @param string $filename Filename to log to
     * @param boolean $logSet Log setMessage calls
     * @param boolean $logAdd Log addMessage calls
     * @return $this
     */
    public function setMessageLogFile($filename, $logSet = true, $logAdd = true)
    {
        $this->_messageLogFile        = $filename;
        $this->_messageLogWhenSetting = $logSet && $filename;
        $this->_messageLogWhenAdding  = $logAdd && $filename;

        return $this;
    }

    /**
     * Set the progress template
     *
     * Available placeholders:
     * {total}      Total time
     * {elapsed}    Elapsed time
     * {remaining}  Remaining time
     * {percent}    Progress percent without the % sign
     * {msg}        Message reveiced
     *
     * @var string
     */
    public function setProgressTemplate($template)
    {
        $this->_progressTemplate = $template;
    }

    /**
     * Store a variable in the session store.
     *
     * @param string $name Name of the variable
     * @param mixed $variable Something that can be serialized
     * @return \MUtil_Batch_BatchAbstract (continuation pattern)
     */
    public function setSessionVariable($name, $variable)
    {
        $this->store->setVariable($name, $variable);
        return $this;
    }

    /**
     * Add/set an execution step to the command stack. Named to prevent double addition.
     *
     * @param string $method Name of a method of this object
     * @param mixed $id A unique id to prevent double adding of something to do
     * @param mixed $param1 Scalar or array with scalars, as many parameters as needed allowed
     * @return \MUtil_Batch_BatchAbstract (continuation pattern)
     */
    protected function setStep($method, $id, $param1 = null)
    {
        if (! method_exists($this, $method)) {
            throw new \MUtil_Batch_BatchException("Invalid batch method: '$method'.");
        }

        $params = array_slice(func_get_args(), 2);

        if ($this->stack->setStep($method, $id, $params)) {
            $this->store->incrementStepCount();
        }

        return $this;
    }

    /**
     * Progress a single step on the command stack
     *
     * @return boolean
     */
    protected function step()
    {
        if ($this->stack->hasNext()) {

            try {
                $command = $this->stack->getNext();
                if (!isset($command[0], $command[1])) {
                    throw new \MUtil_Batch_BatchException("Invalid batch command: '$command'.");
                }
                list($method, $params) = $command;

                if (!method_exists($this, $method)) {
                    throw new \MUtil_Batch_BatchException("Invalid batch method: '$method'.");
                }

                if (call_user_func_array(array($this, $method), $params)) {
                    $this->stack->gotoNext();
                }
                $this->store->incrementProcessed();

            } catch (\Exception $e) {
                $this->addMessage('ERROR!!!');
                $this->addMessage(
                    'While calling:' . $command[0] . '(' . implode(',', \MUtil_Ra::flatten($command[1])) . ')'
                );
                $this->addException($e);
                $this->stopBatch($e->getMessage());

                //\MUtil_Echo::track($e);
            }
            return true;
        } else {
            return false;
        }
    }

    public function stopBatch($message)
    {
        // Set to stopped
        $this->store->setFinished();

        // Cleanup stack
        $this->stack->reset();

        $this->addMessage($message);
    }



}
