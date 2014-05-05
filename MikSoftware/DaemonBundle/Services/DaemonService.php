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

namespace MikSoftware\DaemonBundle\Services;

use MikSoftware\DaemonBundle\Daemon\DaemonException;
use MikSoftware\DaemonBundle\Daemon\Daemon;

/**
 * Base service for Daemon
 *
 * @author    Michele Nucci
 * @copyright 2013-2014 Michele Nucci (http://m1k.info)
 * @license   BSD-3-Clause
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class DaemonService
{   
    /**
     * Daemon configuration object
     * 
     * @var array
     */
    private $config = array();
    
    /**
     * Deamon PID
     * 
     * @var integer
     */
    private $pid;
    
    /**
     * Base time interval for daemon loop
     * 
     * @var integer
     */
    private $interval = 2;
    
    /**
     * @var DaemonBundle/Daemon/Daemon
     */
    protected $daemon;

    /**
     * Initialize the service
     */
    public function initialize($options)
    {
        if (empty($options)) {
            throw new DaemonException('Daemon instantiated without a config!');
        }
                      
        $this->setConfig($options);
        $this->setPid($this->getPid());
        $this->setDaemon(new Daemon($this->getConfig()));        
    }
    
    /**
     * @param MikSoftware\DaemonBundle\Daemon\Daemon
     */
    public function setDaemon(Daemon $daemon)
    {
        $this->daemon = $daemon;
    }
    
    /**
     * Return the main daemon instance
     * 
     * @return MikSoftware\DaemonBundle\Daemon\Daemon
     */
    public function getDaemon()
    {
        return $this->daemon;
    }
    
    /**
     * 
     * @param unknown $config
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }
    
    /**
     * Return a configuration parameter or the config object
     * 
     * @param string $key
     * @return string|multitype:
     */
    public function getConfig($key = '')
    {
        if ($key != '') {
            return trim($this->config[$key]);
        }
        
        return $this->config;
    }
    
    /**
     * Return the daemon PID
     */
    public function getPid()
    {
        if (!empty($this->pid)) {
            return $this->pid;
        }
        
        return $this->readFile($this->getConfig('appPidLocation'));
    }
    
    /**
     * Set the daemon PID
     * 
     * @param integer
     */
    public function setPid($pid)
    {
        $this->pid = $pid;
    }
    
    /**
     * Set the time interval for daemon loop
     * 
     * @param integer
     */
    public function setInterval($interval)
    {
        $this->interval = $interval;
    }
    
    /**
     * Return the time interval for daemon loop
     */
    public function getInterval()
    {
        return $this->interval;
    }
    
    
    /**
     * Start method
     */
    public function start()
    {
        $daemon = $this->daemon;
        $this->daemon->setSigHandler('SIGTERM',
            function() use ($daemon) {
                $daemon->warning("Received SIGTERM. ");
                $daemon->stop();
            }
        );
                
        $status = $this->daemon->start();                
        $this->daemon->info('{appName} System Daemon Started at %s', date("F j, Y, g:i a"));
        $this->setPid($this->getPid());
        return $status;
    }
    
    /**
     * Restart method
     */
    public function restart()
    {
        return $this->daemon->restart();
    }

    /**
     * Stop method
     */
    public function stop()
    {
        return $this->daemon->stop();
    }
    
    /**
     * Base iterate function
     * 
     * @param integer
     */
    public function iterate($sec)
    {
        return $this->daemon->iterate($sec);
    }
    
    /**
     * Return if daemon is running
     * 
     * @return boolean
     */
    public function isRunning()
    {
        return $this->daemon->isRunning();
    }
    
    /**
     * Return if darmon is dying
     */
    public function isDying()
    {
        return $this->daemon->isDying();
    }
    
    
    /**
     * Read the content of a given file
     * 
     * @param string
     * @param mixed
     */
    private function readFile($filename, $return = false)
    {
        if (!file_exists($filename)) {
            return $return;
        }
        
        $f = fopen($filename, 'r');
        if (!$f) {
            return false;
        }
        
        $data = fread($f, filesize($filename));
        fclose($f);

        return $data;
    }    
}
