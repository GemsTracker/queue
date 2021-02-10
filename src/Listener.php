<?php

namespace Gems\Queue;


use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessUtils;
use Symfony\Component\Process\PhpExecutableFinder;

class Listener
{
    /**
     * Location of the file that needs to be present to run the process
     *
     * @var string
     */
    protected $activateFileLocation;

    /**
     * @var \Zend_Log
     */
    protected $log;

    /**
     * The amount of MB that should be the memory limit. If the current process exceeds the memory limit, the script will end.
     *
     * @var int
     */
    protected $memoryLimit = 128;

    protected $processCommand;

    protected $processTimeout = 3600;

    protected $processIdleTimeout = 60;

    /**
     * The amount of seconds to wait before polling the queue.
     *
     * @var int
     */
    protected $sleep = 20;

    protected $sleepAfterError = 60;


    public function __construct(\Zend_Log $log, $options=null)
    {
        $this->log = $log;
        $this->setActivateFileLocation();
        $this->processCommand = $this->buildCommand();
    }

    protected function buildCommand()
    {
        return [
            $this->phpBinary(),
            $this->consoleCommand(),
            $this->getQueueController(),
            $this->getQueueAction(),
        ];
    }

    protected function consoleCommand()
    {
        $rootDir = $this->getRootDir();
        return $rootDir . DIRECTORY_SEPARATOR . 'scripts/index.php';
    }

    public function getActivateFileLocation()
    {
        return $this->activateFileLocation;
    }

    public function getRootDir()
    {
        $rootDir = GEMS_ROOT_DIR;
        return $rootDir;
    }

    public function listen()
    {
        $process = $this->makeProcess();
        $this->log->notice('Starting listener');
        while (true) {
            $this->runProcess($process);
        }
    }

    protected function makeProcess()
    {
        $process = new Process(
            $this->processCommand
        );
        $process->setTimeout($this->processTimeout);
        $process->setIdleTimeout($this->processIdleTimeout);
        return $process;
    }

    /**
     * Determine if the memory limit has been exceeded.
     *
     * @param  int  $memoryLimit
     * @return bool
     */
    public function memoryExceeded()
    {
        return (memory_get_usage(true) / 1024 / 1024) >= $this->memoryLimit;
    }

    protected function runProcess(Process $process)
    {
        if ($this->isActive()) {
            try {
                $process->run();
                echo $process->getOutput();
            } catch(\Exception $e) {
                $this->log->err(sprintf('The process \'%s\' failed!: ', $this->processCommand, $e->getMessage()));
            }
            if (!$process->isSuccessful()) {
                $this->log->err(sprintf('The process \'%s\' failed!', $this->processCommand));
                sleep($this->sleepAfterError);
            }
            if ($process->isSuccessful()) {
                //unlink($this->getActivateFileLocation());
            }
        } else {
            //echo "falling asleep.. Zzzzzzzzzzz\n";
            sleep($this->sleep);
        }

        if ($this->memoryExceeded()) {
            $this->stop();
        }
    }

    /**
     * should the process run?
     *
     * @return boolean
     */
    public function isActive()
    {
        if (file_exists($this->getActivateFileLocation())) {
            return true;
        }
        return false;
    }

    /**
     * Get the PHP binary.
     *
     * @return string
     */
    protected function phpBinary()
    {
        return (new PhpExecutableFinder)->find(false);
    }

    /**
     * Sets the activate file location if it hasn't been set yet
     *
     * @param boolean $forceUpdate
     * @return void
     */
    protected function setActivateFileLocation($forceUpdate=false)
    {
        if ($forceUpdate === true || !$this->activateFileLocation) {

            $rootDir = $this->getRootDir();
            $filename = 'queue.test';
            $this->activateFileLocation = $rootDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . $filename;
        }
    }

    /**
     * Stop listening and bail out of the script.
     *
     * @return void
     */
    public function stop()
    {
        $this->log->notice('Exiting listener');
        die;
    }

    protected function getQueueAction()
    {
        return 'next';
    }

    protected function getQueueController()
    {
        return 'queue';
    }
}
