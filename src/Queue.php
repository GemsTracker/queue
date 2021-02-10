<?php

namespace Gems\Queue;

use Gems\Queue\Batch\Stack\DatabaseStack;

class Queue
{
    const DEFAULT_QUEUE_ID = 'queue';

    public static $currentUserId;

    protected static function getQueueStack($batchId = null, $userId = null)
    {
        if ($batchId === null) {
            $batchId = self::DEFAULT_QUEUE_ID;
        }

        if ($userId === null && static::$currentUserId === null) {
            throw new \RuntimeException('No user ID set for queue');
        }

        $stack = new DatabaseStack($batchId, static::$currentUserId);

        return $stack;
    }

    public static function add($task, $parameters = [], $userId = null, $taskId = null, $priority = null, $delayUntil = null)
    {
        static::addTo(null, $task,$parameters, $userId, $taskId, $priority, $delayUntil);
    }

    public static function addTo($batchId, $task, $parameters = [], $userId = null, $taskId = null, $priority = null, $delayUntil = null)
    {
        $stack = static::getQueueStack($batchId, $userId);

        if ($stack instanceof DatabaseStack) {
            $stack->addStep('runTask', [$task, $parameters], $taskId, $priority, $delayUntil);
        } else {
            $stack->addStep('runTask', [$task, $parameters]);
        }
    }

    public static function setCurrentUserId($userId)
    {
        static::$currentUserId = $userId;
    }
}
