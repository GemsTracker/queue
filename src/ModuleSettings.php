<?php


namespace Gems\Queue;

use Gems\Modules\ModuleSettingsAbstract;

class ModuleSettings extends ModuleSettingsAbstract
{
    public static $moduleName = 'Gems\\Queue';

    public static $eventSubscriber = ModuleSubscriber::class;
}
