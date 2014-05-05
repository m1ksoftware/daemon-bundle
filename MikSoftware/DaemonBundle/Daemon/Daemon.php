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

namespace MikSoftware\DaemonBundle\Daemon;

/**
 * Main Daemon Class.
 * 
 * Requirements:
 * PHP >= 5.3.x
 * PHP build with --enable-cli --with-pcntl 
 * *NIX systems
 *
 * @category  System
 * @author    Michele Nucci <mik.nucci -at- gmail.com>
 * @copyright 2013-2014 Michele Nucci (http://m1k.info)
 * @license   BSD-3-Clause
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Daemon
{
    /**
     * The process identifier
     *
     * @var integer
     */
    protected $processId = 0;

    /**
     * Flag for daemon dying
     *
     * @var boolean
     */
    protected $isDying = false;
    
    /**
     * Main options
     *
     * @var mixed object or boolean
     */
    protected $options = false;
    
    /**
     * Main constructor
     */
    public function __construct(array $configs)
    {
        // Quickly initialize some defaults like usePEAR
        // by adding the $premature flag
        $this->optionsInit(true);        
        $this->setOptions($configs);        
        
        if ($this->opt('logPhpErrors')) {
            set_error_handler(array('self', 'phpErrors'), E_ALL);
        }
        
        if (!defined( 'SIGHUP') ) {
            trigger_error( 'No support to pcntl available.', E_USER_ERROR );
        }
        
        if (php_sapi_name() !== 'cli') {
            trigger_error( 'It is possible to create daemon only from the command line (CLI-mode)', E_USER_ERROR );
        }
        
        if (!function_exists('posix_getpid')) {
            trigger_error( 'PHP is compiled without --enable-posix directive', E_USER_ERROR );
        }
        
        if (function_exists('gc_enable')) {
            gc_enable();
        }
        
        if (false === $this->optionsInit(false)) {
            if (is_object($this->options) && is_array($this->options->errors)) {
                foreach ($this->options->errors as $error) {
                    $this->notice($error);
                }
            }
            trigger_error( 'Required options are not set. Review log:', E_USER_ERROR );
        }

        if ($this->opt('appName') !== strtolower($this->opt('appName'))) {
            return $this->crit('Option: appName should be lowercase');
        }
        
        if (strlen($this->opt('appName')) > 16) {
            return $this->crit('Option: appName should be no longer than 16 characters');
        }        
    }

    /**
     * Spawn daemon process.
     *
     * @return boolean
     * @see iterate()
     * @see stop()   
     * @see optionsInit()
     * @see summon()
     */
    public function start()
    {
        // Conditionally add loglevel mappings that are not supported in
        // all PHP versions.
        // They will be in string representation and have to be
        // converted & unset
        foreach (Definitions::$logPhpMapping as $phpConstant => $props) {
            if (!is_numeric($phpConstant)) {
                if (defined($phpConstant)) {
                    Definitions::$logPhpMapping[constant($phpConstant)] = $props;
                }
                unset(Definitions::$logPhpMapping[$phpConstant]);
            }
        }
        
        // Same goes for POSIX signals. Not all Constants are available on
        // all platforms.
        foreach (Definitions::$sigHandlers as $signal => $handler) {
            if (is_string($signal) || !$signal) {
                if (defined($signal) && ($const = constant($signal))) {
                    Definitions::$sigHandlers[$const] = $handler;
                }
                unset(Definitions::$sigHandlers[$signal]);
            }
        }

        // Demonize!
        return $this->summon();
    }
    
    /**
     * Stop daemon process.
     *
     * @return void
     * @see start()
     */
    public function stop()
    {
        $pid = $this->isRunning();
        if ($pid && !$this->isDying) {
            $this->info('Stopping {appName} ['.$pid.']');
            $this->ddie(false, $pid);
        }
    }
    
    /**
     * Restart daemon process.
     *
     * @return void
     * @see ddie()
     */
    public function restart()
    {
        $pid = $this->getPidFromFile();
        $this->info('Restarting {appName} ['.$pid.']');
        $this->ddie(true, $pid);
    }
    
    /**
     * Protects your daemon by e.g. clearing statcache. Can optionally
     * be used as a replacement for sleep as well.
     *
     * @param integer $sleepSeconds Optionally put your daemon to rest for X s.
     *
     * @return void
     * @see start()
     * @see stop()
     */
    protected function iterate($sleepSeconds = 0)
    {        
        $this->optionObjSetup();
        if ($sleepSeconds >= 1) {
            sleep($sleepSeconds);
        } else if (is_numeric($sleepSeconds)) {
            usleep($sleepSeconds * 1000000);
        }

        clearstatcache();

        // Garbage Collection (PHP >= 5.3)
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        return true;
    }
    
    /**
     * Overrule or add signal handlers.
     *
     * @param string $signal  Signal constant (e.g. SIGHUP)
     * @param mixed  $handler Which handler to call on signal
     *
     * @return boolean
     * @see $sigHandlers
     */
    public function setSigHandler($signal, $handler)
    {
        if (!isset(Definitions::$sigHandlers[$signal])) {
            // The signal should be defined already
            $this->notice(
                'Can only overrule on of these signal handlers: %s',
                join(', ', array_keys(Definitions::$sigHandlers))
            );
            return false;
        }

        // Overwrite on existance
        Definitions::$sigHandlers[$signal] = $handler;
        return true;
    }

    /**
     * Sets any option found in $optionDefinitions
     * Public interface to talk with with protected option methods
     *
     * @param string $name  Name of the Option
     * @param mixed  $value Value of the Option
     *
     * @return boolean
     */
    public function setOption($name, $value)
    {
        if (!$this->optionObjSetup()) {
            return false;
        }

        return $this->options->setOption($name, $value);
    }

    /**
     * Sets an array of options found in $optionDefinitions
     * Public interface to talk with with protected option methods
     *
     * @param array $options Array with Options
     *
     * @return boolean
     */
    public function setOptions($options)
    {        
        if (!$this->optionObjSetup()) {            
            return false;
        }
                
        return $this->options->setOptions($options);                
    }

    /**
     * Shortcut for getOption & setOption
     *
     * @param string $name Option to set or get
     *
     * @return mixed
     */
    private function opt($name)
    {
        $args = func_get_args();
        if (count($args) > 1) {
            return $this->setOption($name, $args[1]);
        } else {
            return $this->getOption($name);
        }
    }


    /**
     * Gets any option found in $optionDefinitions
     * Public interface to talk with with protected option methods
     *
     * @param string $name Name of the Option
     *
     * @return mixed
     */
    public function getOption($name)
    {
        if (!$this->optionObjSetup()) {
            return false;
        }

        return $this->options->getOption($name);
    }

    /**
     * Gets an array of options found
     *
     * @return array
     */
    public function getOptions()
    {
        if (!$this->optionObjSetup()) {
            return false;
        }

        return $this->options->getOptions();
    }

    /**
     * Catches PHP Errors and forwards them to log function
     *
     * @param integer $errno   Level
     * @param string  $errstr  Error
     * @param string  $errfile File
     * @param integer $errline Line
     *
     * @return boolean
     */
    protected function phpErrors($errno, $errstr, $errfile, $errline)
    {
        // Ignore suppressed errors (prefixed by '@')
        if (error_reporting() == 0) {
            return;
        }

        // Map PHP error level to System_Daemon log level
        if (!isset(Definitions::$logPhpMapping[$errno][0])) {
            $this->warning('Unknown PHP errorno: %s', $errno);
            $phpLvl = Definitions::LOG_ERR;
        } else {
            list($logLvl, $phpLvl) = Definitions::$logPhpMapping[$errno];
        }

        // Log it
        // No shortcuts this time!
        $this->log(
            $logLvl, 
            '[PHP ' . $phpLvl . '] '.$errstr, 
            $errfile, 
            __CLASS__,
            __FUNCTION__, 
            $errline
        );

        return true;
    }

    /**
     * Abbreviate a string. e.g: Kevin van zonneveld -> Kevin van Z...
     *
     * @param string  $str    Data
     * @param integer $cutAt  Where to cut
     * @param string  $suffix Suffix with something?
     *
     * @return string
     */
    protected function abbr($str, $cutAt = 30, $suffix = '...')
    {
        if (strlen($str) <= 30) {
            return $str;
        }

        $canBe = $cutAt - strlen($suffix);

        return substr($str, 0, $canBe). $suffix;
    }

    /**
     * Tries to return the most significant information as a string
     * based on any given argument.
     *
     * @param mixed $arguments Any type of variable
     *
     * @return string
     */
    protected function semantify($arguments)
    {
        if (is_object($arguments)) {
            return get_class($arguments);
        }
        if (!is_array($arguments)) {
            if (!is_numeric($arguments) && !is_bool($arguments)) {
                $arguments = '\''.$arguments.'\'';
            }
            return $arguments;
        }
        $arr = array();
        foreach ($arguments as $key=>$val) {
            if (is_array($val)) {
                $val = json_encode($val);
            } elseif (!is_numeric($val) && !is_bool($val)) {
                $val = '\''.$val.'\'';
            }

            $val = $this->abbr($val);

            $arr[] = $key.': '.$val;
        }
        return join(', ', $arr);
    }

    /**
     * Logging shortcut
     *
     * @return boolean
     */
    public function emerg()
    {
        $arguments = func_get_args(); 
        array_unshift($arguments, __FUNCTION__);
        call_user_func_array(array('self', 'ilog'), $arguments);
        
        return false;        
    }

    /**
     * Logging shortcut
     *
     * @return boolean
     */
    public function alert()
    {
        $arguments = func_get_args(); 
        array_unshift($arguments, __FUNCTION__);
        call_user_func_array(array('self', 'ilog'), $arguments);
        
        return false;        
    }

    /**
     * Logging shortcut
     *
     * @return boolean
     */
    public function crit()
    {
        $arguments = func_get_args();
        array_unshift($arguments, __FUNCTION__);
        call_user_func_array(array('self', 'ilog'), $arguments);
        
        return false;        
    }

    /**
     * Logging shortcut
     *
     * @return boolean
     */
    public function err()
    {
        $arguments = func_get_args();
        array_unshift($arguments, __FUNCTION__);
        call_user_func_array(array('self', 'ilog'), $arguments);
        
        return false;        
    }

    /**
     * Logging shortcut
     *
     * @return boolean
     */
    public function warning()
    {
        $arguments = func_get_args(); 
        array_unshift($arguments, __FUNCTION__);
        call_user_func_array(array('self', 'ilog'), $arguments);
        
        return false;        
    }

    /**
     * Logging shortcut
     *
     * @return boolean
     */
    public function notice()
    {
        $arguments = func_get_args();
        array_unshift($arguments, __FUNCTION__);
        call_user_func_array(array('self', 'ilog'), $arguments);
        
        return true;        
    }

    /**
     * Logging shortcut
     *
     * @return boolean
     */
    public function info()
    {
        $arguments = func_get_args();
        array_unshift($arguments, __FUNCTION__);
        call_user_func_array(array('self', 'ilog'), $arguments);
        
        return true;        
    }

    /**
     * Logging shortcut
     *
     * @return boolean
     */
    public function debug()
    {
        $arguments = func_get_args();
        array_unshift($arguments, __FUNCTION__);
        call_user_func_array(array('self', 'ilog'), $arguments);
        
        return true;        
    }

    /**
     * Internal logging function. Bridge between shortcuts like:
     * err(), warning(), info() and the actual log() function
     *
     * @param mixed $level As string or constant
     * @param mixed $str   Message
     *
     * @return boolean
     */
    protected function ilog($level, $str)
    {
        $arguments = func_get_args();
        $level     = $arguments[0];
        $format    = $arguments[1];
                
        if (is_string($level)) {
            if (false === ($l = array_search($level, Definitions::$logLevels))) {
                $this->log(LOG_EMERG, 'No such loglevel: '. $level);
            } else {
                $level = $l;
            }
        }

        unset($arguments[0]);
        unset($arguments[1]);
        
        $str = $format;
        if (count($arguments)) {
            foreach ($arguments as $k => $v) {
                $arguments[$k] = $this->semantify($v);
            }
            $str = vsprintf($str, $arguments);
        }        
        
        $this->optionObjSetup();        
        $str = preg_replace_callback(
            '/\{([^\{\}]+)\}/is',
            array($this->options, 'replaceVars'),
            $str
        );

        $history  = 2;
        $dbg_bt   = @debug_backtrace();
        $class    = (string)@$dbg_bt[($history-1)]['class'];
        $function = (string)@$dbg_bt[($history-1)]['function'];
        $file     = (string)@$dbg_bt[$history]['file'];
        $line     = (string)@$dbg_bt[$history]['line'];
        
        return $this->log($level, $str, $file, $class, $function, $line);
    }

    /**
     * Almost every deamon requires a log file, this function can
     * facilitate that. Also handles class-generated errors, chooses
     * either PEAR handling or PEAR-independant handling, depending on:
     * $this->opt('usePEAR').
     * Also supports PEAR_Log if you referenc to a valid instance of it
     * in $this->opt('usePEARLogInstance').
     *
     * It logs a string according to error levels specified in array:
     * $this->logLevels (0 is fatal and handles daemon's death)
     *
     * @param integer $level    What function the log record is from
     * @param string  $str      The log record
     * @param string  $file     What code file the log record is from
     * @param string  $class    What class the log record is from
     * @param string  $function What function the log record is from
     * @param integer $line     What code line the log record is from
     *
     * @throws DaemonException
     * @return boolean
     * @see Definitions::logLevels
     * @see logLocation
     */
    protected function log($level, $str, $file = false, $class = false, $function = false, $line = false) 
    {        
        // If verbosity level is not matched, don't do anything
        if (null === $this->opt('logVerbosity') || false === $this->opt('logVerbosity')) {
            // Somebody is calling log before launching daemon..
            // fair enough, but we have to init some log options            
            $this->optionsInit(true);
        }
                
        if (!$this->opt('appName')) {
            // Not logging for anything without a name            
            return false;
        }
        
        if ($level > $this->opt('logVerbosity')) {            
            return true;
        }

        // Make the tail of log massage.
        $log_tail = '';
        if ($level < Definitions::LOG_NOTICE) {
            if ($this->opt('logFilePosition')) {
                if ($this->opt('logTrimAppDir')) {
                    $file = substr($file, strlen($this->opt('appDir')));
                }

                $log_tail .= ' [f:'.$file.']';
            }
            if ($this->opt('logLinePosition')) {
                $log_tail .= ' [l:'.$line.']';
            }
        }

        // Make use of a PEAR_Log() instance
        if ($this->opt('usePEARLogInstance') !== false) {
            $this->opt('usePEARLogInstance')->log($str . $log_tail, $level);
            return true;
        }
        
        if (false !== ($cb = $this->opt('useCustomLogHandler'))) {
            if (!is_callable($cb)) {
                throw new DaemonException('Your "useCustomLogHandler" is not callable');
            }
            call_user_func($cb, $str . $log_tail, $level);
            return true;
        }

        // Save resources if arguments are passed.
        // But by falling back to debug_backtrace() it still works
        // if someone forgets to pass them.
        if (function_exists('debug_backtrace') && (!$file || !$line)) {
            $dbg_bt   = @debug_backtrace();
            $class    = (isset($dbg_bt[1]['class'])?$dbg_bt[1]['class']:'');
            $function = (isset($dbg_bt[1]['function'])?$dbg_bt[1]['function']:'');
            $file     = $dbg_bt[0]['file'];
            $line     = $dbg_bt[0]['line'];
        }

        $str_date  = '[' . date('M d H:i:s') . ']';
        $str_level = str_pad(Definitions::$logLevels[$level] . '', 8, ' ', STR_PAD_LEFT);
        $log_line  = $str_date . ' ' . $str_level . ': ' . $str . $log_tail; // $str_ident

        $non_debug      = ($level < Definitions::LOG_DEBUG);
        $log_succeeded  = true;
        $log_echoed     = false;

        if (!$this->isInBackground() && $non_debug && !$log_echoed) {
            // It's okay to echo if you're running as a foreground process.
            echo $log_line . "\n";
            $log_echoed = true;
            // but still try to also log to file for future reference
        }

        if (!$this->opt('logLocation')) {
            throw new DaemonException('Either use PEAR Log or specify a logLocation');
        }

        // 'Touch' logfile
        if (!file_exists($this->opt('logLocation'))) {
            file_put_contents($this->opt('logLocation'), '');
        }

        // Not writable even after touch? Allowed to echo again!!
        if (!is_writable($this->opt('logLocation')) && $non_debug && !$log_echoed) {
            echo $log_line . "\n";
            $log_echoed    = true;
            $log_succeeded = false;
        }

        // Append to logfile
        $f = file_put_contents(
            $this->opt('logLocation'),
            $log_line . "\n",
            FILE_APPEND
        );
        if (!$f) {
            $log_succeeded = false;
        }

        // These are pretty serious errors
        if ($level < Definitions::LOG_ERR) {
            // An emergency logentry is reason for the deamon to
            // die immediately
            if ($level === Definitions::LOG_EMERG) {
                $this->ddie();
            }
        }

        return $log_succeeded;
    }

    /**
     * Default signal handler.
     * You can overrule various signals with the
     * setSigHandler() method
     *
     * @param integer $signo The posix signal received.
     *
     * @return void
     * @see setSigHandler()
     * @see Definitions::$sigHandlers
     */
    protected function defaultSigHandler($signo)
    {
        // Must be public or else will throw a
        // fatal error: Call to protected method
        $this->debug('Received signal: %s', $signo);

        switch ($signo) {
        	case SIGTERM:
        	    // Handle shutdown tasks
        	    if ($this->isInBackground()) {
        	        $this->ddie();
        	    } else {
        	        exit();
        	    }
        	    break;
        	case SIGHUP:
        	    // Handle restart tasks
        	    $this->debug('Received signal: restart');
        	    break;
        	case SIGCHLD:
        	    // A child process has died
        	    $this->debug('Received signal: child');
        	    while (pcntl_wait($status, WNOHANG OR WUNTRACED) > 0) {
        	        usleep(1000);
        	    }
        	    break;
        	default:
        	    // Handle all other signals
        	    break;
        }
    }

    /**
     * Check if the class is already running in the background
     *
     * @return boolean
     */
    protected function isInBackground()
    {        
        return $this->isRunning();
    }

    /**
     * Check if our daemon is being killed, you might
     * want to include this in your loop
     *
     * @return boolean
     */
    protected function isDying()
    {
        return $this->isDying;
    }

    /**
     * Check if a previous process with same pidfile was already running
     *
     * @return mixed
     */
    public function isRunning()
    {
        $appPidLocation = $this->opt('appPidLocation');

        if (!file_exists($appPidLocation)) {
            unset($appPidLocation);
            return false;
        }

        $pid = $this->getPidFromFile();
        if (!$pid) {
            return false;
        }

        // Ping app
        if (!posix_kill(intval($pid), 0)) {
            // Not responding so unlink pidfile
            @unlink($appPidLocation);
            return $this->warning(
                'Orphaned pidfile found and removed: {appPidLocation}. Previous process crashed?'
            );
        }
                
        return $pid;
    }

    /**
     * Put the running script in background
     *
     * @return void
     */
    protected function summon()
    {
        if ($this->opt('usePEARLogInstance')) {
            $logLoc = '(PEAR Log)';            
        } else if ($this->opt('useCustomLogHandler')) {
            $logLoc = '(Custom log handler)';
        } else {
            $logLoc = $this->opt('logLocation');
        }        
        
        $this->notice('Starting {appName} daemon, output in: %s', $logLoc);
        
        // Allowed?
        if ($this->isRunning()) {
            return $this->emerg('{appName} daemon is still running. Exiting');
        }
        
        // Reset Process Information
        $this->safeMode       = !!@ini_get('safe_mode');
        $this->processId      = 0;
        $this->processIsChild = false;
                
        // Fork process!
        if (!$this->fork()) {
            return $this->emerg('Unable to fork');
        }
                       
        // Additional PID succeeded check
        if (!is_numeric($this->processId) || $this->processId < 1) {
            return $this->emerg('No valid pid: %s', $this->processId);
        }        
        
        // Change umask
        @umask(0);
        
        // Write pidfile
        $p = $this->writePid($this->opt('appPidLocation'), $this->processId);
        if (false === $p) {            
            return $this->emerg('Unable to write pid file {appPidLocation}');
        }
        
        // Change identity. maybe
        $c = $this->changeIdentity(
            $this->opt('appRunAsGID'),
            $this->opt('appRunAsUID')
        );
        if (false === $c) {
            $this->crit('Unable to change identity');
            if ($this->opt('appDieOnIdentityCrisis')) {
                $this->emerg('Cannot continue after this');
            }
        }

        // Important for daemons
        // See http://www.php.net/manual/en/function.pcntl-signal.php
        declare(ticks = 1);

        // Setup signal handlers
        // Handlers for individual signals can be overrulled with
        // setSigHandler()
        foreach (Definitions::$sigHandlers as $signal => $handler) {
            if (!is_callable($handler) && $handler != SIG_IGN && $handler != SIG_DFL) {
                return $this->emerg(
                    'You want to assign signal %s to handler %s but ' .
                    'it\'s not callable',
                    $signal,
                    $handler
                );
            } else if (!pcntl_signal($signal, $handler)) {
                return $this->emerg(
                    'Unable to reroute signal handler: %s',
                    $signal
                );
            }
        }

        // Change dir
        @chdir($this->opt('appDir'));

        return true;
    }

    /**
     * Determine whether pidfilelocation is valid
     *
     * @param string  $pidFilePath Pid location
     * @param boolean $log         Allow this function to log directly on error
     *
     * @return boolean
     */
    protected function isValidPidLocation($pidFilePath, $log = true)
    {   
        if (empty($pidFilePath)) {
            return $this->err(
                '{appName} daemon encountered an empty appPidLocation'
            );
        }
                
        $pidDirPath = dirname($pidFilePath);
        $parts      = explode('/', $pidDirPath);                
        if (count($parts) <= 3 || end($parts) != $this->opt('appName')) {
            // like: /var/run/x.pid
            return $this->err(
                'Since version 0.6.3, the pidfile needs to be ' .
                'in it\'s own subdirectory like: %s/{appName}/{appName}.pid'
            );
        }
                
        return true;
    }

    /**
     * Creates pid dir and writes process id to pid file
     *
     * @param string  $pidFilePath PID File path
     * @param integer $pid         PID
     *
     * @return boolean
     */
    protected function writePid($pidFilePath = null, $pid = null)
    {
        if (empty($pid)) {
            return $this->err('{appName} daemon encountered an empty PID');
        }
        
        if (!$this->isValidPidLocation($pidFilePath, true)) {
            return false;
        }
        
        $pidDirPath = dirname($pidFilePath);

        if (!$this->mkdirr($pidDirPath, 0755)) {
            return $this->err('Unable to create directory: %s', $pidDirPath);
        }

        if (!file_put_contents($pidFilePath, $pid)) {
            return $this->err('Unable to write pidfile: %s', $pidFilePath);
        }

        if (!chmod($pidFilePath, 0644)) {
            return $this->err('Unable to chmod pidfile: %s', $pidFilePath);;
        }

        return true;
    }

    /**
     * Recursive alternative to mkdir
     *
     * @param string  $dirPath Directory to create
     * @param integer $mode    Umask
     *
     * @return boolean
     */
    protected function mkdirr($dirPath, $mode)
    {
        is_dir(dirname($dirPath)) || $this->mkdirr(dirname($dirPath), $mode);
        return is_dir($dirPath) || @mkdir($dirPath, $mode);
    }

    /**
     * Change identity of process & resources if needed.
     *
     * @param integer $gid Group identifier (number)
     * @param integer $uid User identifier (number)
     *
     * @return boolean
     */
    protected function changeIdentity($gid = 0, $uid = 0)
    {
        // What files need to be chowned?
        $chownFiles = array();
        if ($this->isValidPidLocation($this->opt('appPidLocation'), true)) {
            $chownFiles[] = dirname($this->opt('appPidLocation'));
        }
        $chownFiles[] = $this->opt('appPidLocation');
        if (!is_object($this->opt('usePEARLogInstance'))) {
            $chownFiles[] = $this->opt('logLocation');
        }
                
        // Chown pid- & log file
        // We have to change owner in case of identity change.
        // This way we can modify the files even after we're not root anymore
        foreach ($chownFiles as $filePath) {
            if (!file_exists($filePath)) {
                continue;
            }

            // Change File GID
            $doGid = (filegroup($filePath) != $gid ? $gid : false);
            if (false !== $doGid && !@chgrp($filePath, intval($gid))) {
                return $this->err(
                    'Unable to change group of file %s to %s',
                    $filePath,
                    $gid
                );
            }
            
            // Change File UID
            $doUid = (fileowner($filePath) != $uid ? $uid : false);
            if (false !== $doUid && !@chown($filePath, intval($uid))) {
                return $this->err(
                    'Unable to change user of file %s to %s',
                    $filePath,
                    $uid
                );
            }

            // Export correct homedir
            if (($info = posix_getpwuid($uid)) && is_dir($info['dir'])) {
                system('export HOME="' . $info['dir'] . '"');
            }
        }

        // Change Process GID
        $doGid = (posix_getgid() !== $gid ? $gid : false);
        if (false !== $doGid && !@posix_setgid($gid)) {
            return $this->err('Unable to change group of process to %s', $gid);
        }
        
        // Change Process UID
        $doUid = (posix_getuid() !== $uid ? $uid : false);
        if (false !== $doUid && !@posix_setuid($uid)) {
            return $this->err('Unable to change user of process to %s', $uid);
        }

        $group = posix_getgrgid($gid);
        $user  = posix_getpwuid($uid);

        return $this->info(
            'Changed identify to %s:%s',
            $group['name'],
            $user['name']
        );
    }

    /**
     * Fork process and kill parent process, the heart of the 'daemonization'
     *
     * @return boolean
     */
    protected function fork()
    {        
        $this->debug('forking {appName} daemon');        
        $pid = pcntl_fork();
        if ($pid === -1) {            
            // Error
            return $this->warning('Process could not be forked');
        } else if ($pid) {            
            // Parent
            $this->debug('Ending {appName} parent process');
            // Die without attracting attention
            exit();
        } else {            
            $this->isDying   = false;
            $this->processId = posix_getpid();
            return true;
        }
    }

    /**
     * Kill the daemon
     * Keep this function as independent from complex logic as possible
     *
     * @param boolean $restart Whether to restart after die
     *
     * @return void
     */
    protected function ddie($restart = false, $pid = false)    
    {   
        if ($this->isDying) {
            return null;
        }

        $this->isDying = true;
                        
        // Following caused a bug if pid couldn't be written because of
        // privileges
        // || !file_exists($this->opt('appPidLocation'))
        if (!$this->isInBackground()) {            
            $this->info('Process was not daemonized yet, just halting current process');
            die();
        }
                
        if (!$pid) {
            $pid = $this->getPidFromFile();
        }
                
        @unlink($this->opt('appPidLocation'));

        if ($restart) {
            // So instead we should:            
            $arg = str_replace('restart', 'start', join(' ', $GLOBALS['argv']));                         
            die(exec($arg . ' > /dev/null &'));
        } else {
            passthru('kill -9 ' . $pid);
            die();
        }
    }

    /**
     * Sets up Option Object instance
     *
     * @return boolean
     */
    protected function optionObjSetup()
    {        
        // Create Option Object if nescessary
        if (!$this->options) {            
            $this->options = new Options(Definitions::$optionDefinitions);            
        }

        // Still false? This was an error!
        if (!$this->options) {
            return $this->emerg('Unable to setup Options object. ');
        }

        return true;
    }

    /**
     * Checks if all the required options are set.
     * Initializes, sanitizes & defaults unset variables
     *
     * @param boolean $premature Whether to do a premature option init
     *
     * @return mixed integer or boolean
     */
    protected function optionsInit($premature = false)
    {
        if (!$this->optionObjSetup()) {
            return false;
        }

        return $this->options->init($premature);
    }
    
    /**
     * Return the PID from PIDfile.
     * 
     * @return string
     */
    private function getPidFromFile()
    {
       return $this->fileread($this->opt('appPidLocation'));
    }
    
    /**
     * Read a file. file_get_contents() leaks memory! (#18031 for more info)
     *
     * @param string $filepath
     *
     * @return string
     */
    private function fileread($filepath) {
        $f = @fopen($filepath, 'r');
        if (!$f) {
            return false;
        }
        $data = fread($f, filesize($filepath));
        fclose($f);
        return $data;
    }

    /**
     * Shortcut method used to call the log function
     * with the correct parameters
     * 
     * @param array
     */
    private function unshiftAndLog($arguments)
    {
    }
}
