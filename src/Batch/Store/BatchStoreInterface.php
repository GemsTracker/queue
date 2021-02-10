<?php


namespace Gems\Queue\Batch\Store;


interface BatchStoreInterface
{
    /**
     * @param \Exception $e
     * @return $this
     */
    public function addException(\Exception $e);

    /**
     * Add a message to the message stack
     * @param string $message
     */
    public function addMessage($message);

    /**
     * Add a number to the Step count
     * @param $number
     */
    public function addStepcount($number);

    /**
     * Increment a named counter
     *
     * @param string $name
     * @param integer $add
     * @return integer
     */
    public function addToCounter($name, $add = 1);

    /**
     * Increment the number of processed items by one
     */
    public function incrementProcessed();

    /**
     * Increment the number of tasks by one
     */
    public function incrementStepCount();

    /**
     * Number of tasks in batch
     *
     * @return int
     */
    public function getCount();

    /**
     * Get a named counter value
     * @param string $name
     * @return int
     */
    public function getCounter($name);

    /**
     * Get all exceptions
     *
     * @return array|null
     */
    public function getExceptions();

    /**
     * Get a message from the message stack with a specific id.
     *
     * @param $id
     * @param string $default A default message
     * @return string
     */
    public function getMessage($id, $default = null);

    /**
     * String of messages from the batch
     *
     * Do not forget to reset() the batch if you're done with it after
     * displaying the report.
     *
     * @param boolean $reset When true the batch is reset afterwards
     * @return array
     */
    public function getMessages($reset = false);

    /**
     * Number of processed tasks
     *
     * @return int
     */
    public function getProcessed();

    /**
     * Get a variable from the Store
     *
     * @param $name string name of the variable
     * @param null $default default value if variable is not found
     * @return mixed
     */
    public function getVariable($name, $default = null);

    /**
     * Get all variables from the store
     *
     * @return array|null
     */
    public function getVariables();

    /**
     * Return whether a session variable exists in the session store.
     *
     * @param string $name Name of the variable
     * @return boolean
     */
    public function hasVariable($name);

    /**
     * Is the current batch finished?
     *
     * @return boolean
     */
    public function isFinished();

    /**
     * Reset and empty the session storage
     */
    public function reset();

    /**
     * Reset a named counter
     *
     * @param string $name
     * @return $this
     */
    public function resetCounter($name);

    /**
     * Reset a message on the message stack with a specific id.
     *
     * @param $id
     */
    public function resetMessage($id);

    /**
     * Set the queue as finished
     */
    public function setFinished();

    /**
     * Add/set a message on the message stack with a specific id.
     *
     * @param $id
     * @param string $text A message to the user
     * @return \MUtil_Batch_BatchAbstract (continuation pattern)
     */
    public function setMessage($id, $text);

    /**
     * Set a batch variable in the store
     *
     * @param $name string name of the variable
     * @param $value mixed
     */
    public function setVariable($name, $value);
}
