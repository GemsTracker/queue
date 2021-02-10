<?php

namespace Gems\Queue\Batch\Store;


class ClassBatchStore extends BatchStoreAbstract
{
    protected $count = 0;

    protected $counters = [];

    protected $exceptions = [];

    protected $finished = false;

    protected $messages = [];

    protected $processed = 0;

    protected $variables = [];

    public function addException(\Exception $e)
    {
        $this->exceptions[] = $e->getMessage();
    }

    public function addMessage($message)
    {
        $this->messages[] = $message;
    }

    public function addStepcount($number)
    {
        $this->count += $number;
    }

    public function addToCounter($name, $add = 1)
    {
        if (!array_key_exists($name, $this->counters)) {
            $this->counters[$name] = 0;
        }
        $this->counters[$name] += $add;
    }

    public function incrementProcessed()
    {
        $this->processed += 1;
    }

    public function getCount()
    {
        return $this->count;
    }

    public function getCounter($name)
    {
        if (array_key_exists($name, $this->counters)) {
            return $this->counters[$name];
        }
        return null;
    }

    public function getExceptions()
    {
        return $this->exceptions;
    }

    public function getMessage($id, $default = null)
    {
        if (array_key_exists($id, $this->messages)) {
            return $this->messages[$id];
        } else {
            return $default;
        }
    }

    public function getMessages($reset = false)
    {
        $messages = $this->messages;
        if ($reset) {
            $this->reset();
        }
        return $messages;
    }

    public function getProcessed()
    {
        return $this->processed;
    }

    public function getVariable($name, $default = null)
    {
        if (array_key_exists($name, $this->variables)) {
            return $this->variables[$name];
        } else {
            return $default;
        }
    }

    public function getVariables()
    {
        return $this->variables;
    }

    public function hasVariable($name)
    {
        return array_key_Exists($name, $this->variables);
    }

    public function isFinished()
    {
        return $this->finished;
    }

    public function reset()
    {
        $this->count = 0;
        $this->counters = [];
        $this->exceptions = [];
        $this->finished = false;
        $this->messages = [];
        $this->processed = 0;
        $this->variables = [];
    }

    public function resetCounter($name)
    {
        if (array_key_exists($name, $this->counters)) {
            return $this->counters[$name] = 0;
        }
    }

    public function resetMessage($id)
    {
        if (array_key_exists($id, $this->messages)) {
            unset($this->counters[$id]);
        }
    }

    public function setFinished()
    {
        $this->finished = true;
    }

    public function setMessage($id, $text)
    {
        $this->messages[$id] = $text;
    }

    public function setVariable($name, $value)
    {
        $this->variables[$name] = $value;
    }
}
