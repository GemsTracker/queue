<?php


namespace Gems\Queue\Batch\Store;


class SessionBatchStore extends BatchStoreAbstract
{
    /**
     * @var string Name of the batch store
     */
    public $name;

    /**
     * @var \Zend_Session_Namespace
     */
    protected $session;

    public function __construct($id)
    {
        $this->name = $name = 'batchStore_' . $id;
        $this->session = new \Zend_Session_Namespace($name);
        if (! isset($this->session->processed)) {
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
        $this->session->exceptions[] = $message;

        return $this;
    }

    /**
     * Add a message to the message stack
     * @param string $message
     */
    public function addMessage($message)
    {
        $this->session->messages[] = $message;
    }

    /**
     * Add a number to the Step count
     * @param $number
     */
    public function addStepcount($number)
    {
        $this->session->count = $this->session->count + $number;
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
        if (! isset($this->session->counters[$name])) {
            $this->session->counters[$name] = 0;
        }
        $this->session->counters[$name] += $add;

        return $this->session->counters[$name];
    }

    public function incrementProcessed()
    {
        $this->session->processed = $this->session->processed + 1;
    }

    /**
     * Number of tasks in batch
     *
     * @return int
     */
    public function getCount()
    {
        return $this->session->count;
    }

    public function getCounter($name)
    {
        if (isset($this->session->counters[$name])) {
            return $this->session->counters[$name];
        }

        return 0;
    }

    public function getExceptions()
    {
        return $this->session->exceptions;
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
        if (array_key_exists($id, $this->session->messages)) {
            return $this->session->messages[$id];
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
        $messages = $this->session->messages;

        if ($reset) {
            $this->reset();
        }

        return $messages;
    }

    public function getProcessed()
    {
        return $this->session->processed;
    }

    public function getVariable($name, $default = null)
    {
        if (isset($this->session->variables, $this->session->variables[$name])) {
            return $this->session->variables[$name];
        } else {
            return $default;
        }
    }

    public function getVariables()
    {
        if (isset($this->session->variables)) {
            return $this->session->variables;
        }
        return null;
    }

    public function hasVariable($name)
    {
        return isset($this->session->variables, $this->session->variables[$name]);
    }

    public function isFinished()
    {
        return $this->session->finished;
    }

    /**
     * Reset and empty the session storage
     */
    public function reset()
    {
        $this->session->unsetAll();

        $this->session->count      = 0;
        $this->session->counters   = [];
        $this->session->exceptions = [];
        $this->session->finished   = false;
        $this->session->messages   = [];
        $this->session->processed  = 0;
    }

    /**
     * Reset a named counter
     *
     * @param string $name
     * @return $this
     */
    public function resetCounter($name)
    {
        unset($this->session->counters[$name]);
    }

    /**
     * Reset a message on the message stack with a specific id.
     *
     * @param $id
     */
    public function resetMessage($id)
    {
        unset($this->session->messages[$id]);
    }

    public function setFinished()
    {
        $this->session->finished = true;
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
        $this->session->messages[$id] = $text;
    }

    public function setVariable($name, $value)
    {
        if (! isset($this->session->variables)) {
            $this->session->variables = new \ArrayObject();
        }

        $this->session->variables[$name] = $value;
    }

}
