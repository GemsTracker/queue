<?php


namespace Gems\Queue\Batch\Store;


class CacheStore
{
    /**
     * @var \Zend_Cache_Core
     */
    public $cache;

    public $name;

    /**
     * @var \ArrayObject
     */
    public $store;

    public function __construct($id, \Zend_Cache_Core $cache)
    {
        $this->cache = $cache;
        $this->name = $name = $id;

        $this->reload();
    }

    public function __get($name)
    {
        $this->reload();
        if (isset($this->store->$name)) {
            return $this->store->$name;
        }
        return null;
    }

    public function __isset($name)
    {
        $this->reload();
        if (isset($this->store->$name)) {
            return true;
        }
        return false;
    }

    public function __set($name, $value)
    {
        $this->store->$name = $value;

        $this->save();
    }

    protected function reload()
    {
        $this->store = $this->cache->load($this->name);

        if ($this->store === false) {
            $this->store = new \ArrayObject;
        }
    }

    protected function save()
    {
        try {
            $this->cache->save($this->store, $this->name, ['batchStore']);
        } catch(\Zend_Cache_Exception $e) {
            echo $e->getMessage();
            throw new \MUtil_Batch_BatchException('Could not save Batch Store to cache');
        }
    }
}
