<?php


namespace Gems\Queue;

use Gems\Event\Application\GetDatabasePaths;
use Gems\Event\Application\MenuAdd;
use Gems\Event\Application\SetFrontControllerDirectory;
use Gems\Event\Application\ZendTranslateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ModuleSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            GetDatabasePaths::NAME => [
                ['getDatabasePaths'],
            ],
            MenuAdd::NAME => [
                ['addToMenu']
            ],
            SetFrontControllerDirectory::NAME => [
                ['setFrontControllerDirectory'],
            ],
        ];
    }

    public function addToMenu(MenuAdd $event)
    {
        $menu = $event->getMenu();
        $translateAdapter = $event->getTranslatorAdapter();

        $menu->addPage(null, 'pr.queue.next', 'queue', 'next');

        $setup = $this->findController('database')->getParent();

        $queue = $setup->addPage($translateAdapter->_('Task queue'), 'pr.queue', 'queue', 'index');
        $show = $queue->addShowAction();
        //$run = $show->addPage($this->_('Run item'), 'pr.queue.run', 'queue', 'run-queue-item');
        //$run->setModelParameters(1);
    }

    public function getDatabasePaths(GetDatabasePaths $event)
    {
        $path = ModuleSettings::getVendorPath() . DIRECTORY_SEPARATOR . 'configs' . DIRECTORY_SEPARATOR . 'db';
        $event->addPath(ModuleSettings::$moduleName, $path);
    }

    public function setFrontControllerDirectory(SetFrontControllerDirectory $event)
    {
        $applicationPath = ModuleSettings::getVendorPath() . DIRECTORY_SEPARATOR . 'controllers';
        $event->setControllerDirIfControllerExists($applicationPath);
    }
}
