<?php


namespace Gems\Queue\Batch\Store;


use MUtil\Registry\TargetTrait;

abstract class BatchStoreAbstract implements BatchStoreInterface
{
    public function getLastMessage()
    {
        $messages = $this->getMessages();
        $lastMessage = end($messages);
        reset($messages);
        return $lastMessage;
    }

    public function incrementStepCount()
    {
        $this->addStepcount(1);
    }
}
