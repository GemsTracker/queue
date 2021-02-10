<?php


namespace Gems\Queue\Batch\Store;


class CacheBatchStore extends BatchStoreAbstract
{
    /**
     * @var \Zend_Cache_Core
     */
    public $cache;

    /**
     * @var string Name of the batch store
     */
    public $name;

    /**
     * @var CacheStore Store values;
     */
    protected $store;

    public function __construct($id, \Zend_Cache_Core $cache)
    {
        $this->cache = $cache;
        $this->name = $name = 'batchStore_' . $id;
        $this->initStore();

        if (!isset($this->store->processed)) {
            $this->reset();
        }
    }

    /**
     * @param \Exception $e
     * @return $this
     */
    public function addException(\Exception $e)
    {
        $message = $e->getMessage();

        $exceptions = $this->store->exceptions;

        $exceptions[] = $message;

        $this->store->exceptions = $exceptions;

        return $this;
    }

    /**
     * Add a message to the message stack
     * @param string $message
     */
    public function addMessage($message)
    {
        $messages = $this->store->messages;
        $messages[] = $message;
        $this->store->messages = $messages;
    }

    /**
     * Add a number to the Step count
     * @param $number
     */
    public function addStepcount($number)
    {
        $this->store->count = $this->store->count + $number;
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
        if (! isset($this->store->counters[$name])) {
            $this->store->counters[$name] = 0;
        }
        $this->store->counters[$name] += $add;

        return $this->store->counters[$name];
    }

    public function incrementProcessed()
    {
        $this->store->processed = $this->store->processed + 1;
    }

    /**
     * Number of tasks in batch
     *
     * @return int
     */
    public function getCount()
    {
        return $this->store->count;
    }

    public function getCounter($name)
    {
        if (isset($this->store->counters[$name])) {
            return $this->store->counters[$name];
        }

        return 0;
    }

    public function getExceptions()
    {
        return $this->store->exceptions;
    }

    /**
     * Get a message from the message stack with a specific id.
     *
     * @param $id
     * @param string $default A default message
     * @return string
     */
    public function getMessage($id, $default = null)
    {
        if (array_key_exists($id, $this->store->messages)) {
            return $this->store->messages[$id];
        } else {
            return $default;
        }
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
        $messages = $this->store->messages;

        if ($reset) {
            $this->reset();
            $messages = $this->store->messages;
        }

        return $messages;
    }

    public function getProcessed()
    {
        return $this->store->processed;
    }

    public function getVariable($name, $default = null)
    {
        if ($this->store->variables && isset($this->store->variables[$name])) {
            return $this->store->variables[$name];
        } else {
            return $default;
        }
    }

    public function getVariables()
    {
        if (isset($this->store->variables)) {
            return $this->store->variables;
        }
        return null;
    }

    public function hasVariable($name)
    {
        return isset($this->store->variables, $this->store->variables[$name]);
    }

    protected function initStore()
    {
        $this->store = new CacheStore($this->name, $this->cache);
    }

    public function isFinished()
    {
        return $this->store->finished;
    }

    /**
     * Reset and empty the session storage
     */
    public function reset()
    {
        $this->initStore();

        $this->store->count      = 0;
        $this->store->counters   = [];
        $this->store->exceptions = [];
        $this->store->finished   = false;
        $this->store->messages   = [];
        $this->store->processed  = 0;
    }

    /**
     * Reset a named counter
     *
     * @param string $name
     * @return $this
     */
    public function resetCounter($name)
    {
        unset($this->store->counters[$name]);
    }

    /**
     * Reset a message on the message stack with a specific id.
     *
     * @param $id
     */
    public function resetMessage($id)
    {
        $messages = $this->store->messages;
        unset($messages[$id]);
        $this->store->messages = $messages;
    }

    public function setFinished()
    {
        $this->store->finished = true;
    }

    /**
     * Add/set a message on the message stack with a specific id.
     *
     * @param $id
     * @param string $text A message to the user
     * @return \MUtil_Batch_BatchAbstract (continuation pattern)
     */
    public function setMessage($id, $text)
    {
        $this->store->messages[$id] = $text;
    }

    public function setVariable($name, $value)
    {
        if (! isset($this->store->variables)) {
            $this->store->variables = new \ArrayObject();
        }

        $variables = $this->store->variables;

        $variables[$name] = $value;



        $this->store->variables = $variables;
    }
}
