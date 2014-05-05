<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS 
 * ''AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT 
 * LIMITED TO, THE IMPLIED WARRANTIES OF  MERCHANTABILITY AND  FITNESS 
 * FOR  A  PARTICULAR  PURPOSE  ARE  DISCLAIMED. IN NO EVENT SHALL THE 
 * COPYRIGHT HOLDER BE LIABLE FOR  ANY  DIRECT, INDIRECT,  INCIDENTAL, 
 * SPECIAL, EXEMPLARY, OR  CONSEQUENTIAL  DAMAGES (INCLUDING, BUT  NOT 
 * LIMITED TO, PROCUREMENT  OF SUBSTITUTE  GOODS OR  SERVICES; LOSS OF
 * USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN  CONTRACT, STRICT LIABILITY, 
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING  IN ANY WAY OUT
 * OF THE USE OF THIS  SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 * SUCH DAMAGE.
 * 
 * This file is part of the MIKSoftware/DaemonBudle by Michele Nucci.
 * 
 * Part of of the MIKSoftware/DaemonBudle is based on the System_Daemon  
 * package by Kevin van Zonneveld <kevin@vanzonneveld.net> (2008).
 */
  
namespace MikSoftware\DaemonBundle\Commnad;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use MikSoftware\DaemonBundle\Daemon\DaemonException;
use MikSoftware\DaemonBundle\Daemon\DaemonEventException;

/**
 * Base demonized Symfony 2 command.
 * 
 * @author    Michele Nucci
 * @copyright 2013-2014 Michele Nucci (http://m1k.info)
 * @license   BSD-3-Clause
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
abstract class BaseCommand extends ContainerAwareCommand
{    
    // Constants for events
    const EVENT_START = 'EVENT_START';        
    const EVENT_STOP  = 'EVENT_STOP';
    
    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;
    
    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;    

    /**
     * @var array
     */
    protected $events = array();
    
    /**
     * @var array
     */    
    private $methods = array('start', 'stop', 'restart');
    
    /**
     * @var DaemonService $daemon
     */
    private $daemon;

    
	/* (non-PHPdoc)
	 * @see \Symfony\Component\Console\Command\Command::configure()
	 */
	final protected function configure()
	{
	    $this->configureDaemonCommand();  
	    $this->addArgument('method', InputArgument::REQUIRED, implode('|', $this->methods));
	}

	/**
	 * Add new methods to this Daemon. The declared methods
	 * must be implemented into the derived classes.
	 * Methods start|stop|restart will be automatically added.
	 * 
	 * @param mixed
	 */
	protected function addMethods($methodName)
	{
	    if (null != $methodName) {
	        if (is_string($methodName)) {
	            $this->checkAndAddMethod($methodName);
	        } else if (is_array($methodName)) {
	            foreach ($methodName as $method) {
	                if (is_string($method)) {
	                    $this->checkAndAddMethod($method);
	                }
	            }
	        }
	    }
	}
	
    /**
     * Returns the input interface.
     *
     * @return \Symfony\Component\Console\Input\InputInterface
     */
    public function getInput()
    {
        return $this->input;
    }
    
    /**
     * Sets the input interface.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @return \DaemonBundle\Command\DaemonCommand
     */
    public function setInput(InputInterface $input)
    {
        $this->input = $input;
    
        return $this;
    }
    
    /**
     * Returns the output interface.
     *
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }
    
    /**
     * Sets the output interface.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return \DaemonBundle\Command\DaemonCommand
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    
        return $this;
    }
    
    /**
     * Grabs the argument data and runs the argument on the daemon
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return void
     * @throws \Exception
     */
    final protected function execute(InputInterface $input, OutputInterface $output)
    {
        $method = $input->getArgument('method');
                
        if (!in_array($method, $this->methods)) {
            throw new DaemonException(sprintf('Method must be one of: %s', implode(', ', $this->methods)));
        } else if (!is_callable(array($this, $method))) {
            throw new DaemonException(sprintf('Method not supported.'));
        }
        
        $this->setInput($input);
        $this->setOutput($output);
    
        $this->createDaemon();

        call_user_func(array($this, $method));
    }
    
    /**
     * Creates and Initializes the daemon
     */
    final private function createDaemon()
    {
        $this->daemon = $this->getContainer()->get('mik_software.daemon_service');
        $daemonName   = strtolower(str_replace(':', '_', $this->getName()));
        if (!$this->getContainer()->hasParameter($daemonName . '.daemon.options')) {
            throw new DaemonException(sprintf("Couldnt find a daemon for %s", $daemonName . '.daemon.options'));
        }
        
        $this->daemon->initialize($this->getContainer()->getParameter($daemonName . '.daemon.options'));        
    }
    
    /**
     * Starts the Daemon
     *
     * @throws \DaemonBundle\Daemon\DaemonException
     */
    final private function start()
    {
        if ($this->daemon->isRunning()) {
            $this->output->writeln('Daemon is already running!');
            exit();
        }
    
        $this->daemon->start();
    
        $this->runEvents(self::EVENT_START);    
        $this->mainLoop();
        
        $this->runEvents(self::EVENT_STOP);
        $this->daemon->stop();
    }
    
    /**
     * Stops the Daemon
     *
     * @throws \DaemonBundle\Daemon\DaemonException
     */
    final protected function stop()
    {
        if (!$this->daemon->isRunning()) {
            $this->output->writeln('Daemon is not running.');
            die();
        }
        
        $this->runEvents(self::EVENT_STOP);        
        $this->daemon->stop();
    }
    
    /**
     * Restarts the daemon.
     *
     * @throws \DaemonBundle\Daemon\DaemonException
     */
    final private function restart()
    {
        if (!$this->daemon->isRunning()) {
            $this->output('Daemon is not running!');
            exit();
        }    
        
        $this->daemon->restart();
        
        $this->runEvents(self::EVENT_START);
        $this->loop();
        
        $this->runEvents(self::EVENT_STOP);
        $this->daemon->stop();                
    }
    
    protected function runEvents($type)
    {
        $this->log("Finding all {$type} events and running them. ");
        $events = $this->getEvents($type);
        foreach ($events as $name => $event) {
            $this->log("Running the `{$name}` {$type} event. ");
            if ($event instanceof \Closure) {
                $event($this);
            } else {
                call_user_func_array($event, array($this));
            }
        }
    }    
    
    protected function log($content = '', $level = 'info')
    {
        $this->getContainer()->get('logger')->$level($content);
    }
    
    /**
     * Adds an event to the command
     *
     * @param string            $type Type of the event, EVENT_START, EVENT_STOP
     * @param string            $name Name of the event
     * @param \Closure|callable $function
     *
     * @throws \DaemonBundle\Daemon\DaemonEventException
     */
    protected function addEvent($type, $name, $function)
    {
        if (is_callable($function) || $function instanceof \Closure) {
            if (is_string($name)) {
                $this->events[$type][$name] = $function;
            } else {
                throw new DaemonEventException("Name passed isn't a string. ");
            }
        } else {
            throw new DaemonEventException("Function passed is not a callable or a closure. ");
        }
    }
    
    /**
     * Removes the named event for a given type.
     *
     * @param string $type Type of the event, EVENT_START, EVENT_STOP
     * @param string $name Name of the event
     */
    protected function removeEvent($type, $name)
    {
        unset($this->events[$type][$name]);
    }
    
    /**
     * Gets an array of events for the given type
     *
     * @param string $type Type of events, EVENT_START, EVENT_CYCLE_START, EVENT_CYCLE_STOP, EVENT_STOP right now
     *
     * @return \Closure[]|callable[]
     */
    protected function getEvents($type)
    {
        return array_key_exists($type, $this->events) ? $this->events[$type] : array();
    }
    
    /**
     * Clear all events
     */
    protected function clrearEvents()
    {
        unset($this->events);
        $this->events = array();
    }
    
    /**
     * Check and add a new method to this daemon.
     * 
     * @param unknown $methodName
     */
    private function checkAndAddMethod($methodName)
    {
        if (!in_array($methodName, $this->methods)) {
            array_push($this->methods, strtolower($methodName));
        }
    }
    
    /**
     * Return the DaemonService
     * 
     * @return \MikSoftware\DaemonBundle\Commnad\DaemonService
     */
    protected function getDaemon()
    {
        return $this->daemon;
    }
        
    /**
     * Configure this command
     */
    abstract protected function configureDaemonCommand();
    
    /**
     * Main loop 
     */
    abstract protected function mainLoop();
}