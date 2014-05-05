DaemonBundle
============
[![PayPayl donate button](http://img.shields.io/paypal/donate.png?color=green)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=NFQGD52UP2RLA "Donate once-off to this project using Paypal")
[![Latest Stable Version](https://poser.pugx.org/miksoftware/daemon-bundle/v/stable.png)](https://packagist.org/packages/miksoftware/daemon-bundle)
[![Latest Unstable Version](https://poser.pugx.org/miksoftware/daemon-bundle/v/unstable.png)](https://packagist.org/packages/miksoftware/daemon-bundle)
[![License](https://poser.pugx.org/miksoftware/daemon-bundle/license.png)](https://packagist.org/packages/miksoftware/daemon-bundle)

DeamonBundle allows to easily convert your Symfony2 console scripts into system daemons. To use DaemonBundle `pcntl` is required to be configured in your PHP binary.

DaemonBundle is a based in the PEAR library [System_Daemon](http://pear.php.net/package/System_Daemon/redirected) which was created by Kevin Vanzonneveld and it is highly inspaired by [uecode/daemon-bundle](https://github.com/uecode/daemon-bundle).

System_Daemon Package
---------------------

System_Daemon is a PHP class that allows developers to create their own daemon applications on Linux systems. The class is focussed entirely on creating and spawning standalone daemons. More information can be found at:

  * [Create Daemons in PHP](http://kvz.io/blog/2009/01/09/create-daemons-in-php/)
  * [System_Daemon PEAR Package](http://pear.php.net/package/System_Daemon/redirected)

Installation
------------

 1. Add this bundle to the `composer.json` file of your project.

    ```bash
    php composer.phar require miksoftware/daemon-bundle dev-master
    ```

 2. Add the bundle to your application kernel.

    ```php
    // app/AppKernel.php
    public function registerBundles()
    {
        return array(
            // ...
            new MikSoftware\DaemonBundle\MikSoftwareDaemonBundle()
            // ...
        );
    }
    ```

 3. Configure the bundle by adding parameters to the  `config.yml` or `parameters.yml` file:

    ```yaml
    # app/config/config.yml
    
    mik_software_daemon:
        daemons:
            <command name>: ~
    ```

Configuration Parameters
------------------------

To change any configuration setting, you could add required parameters to your project config. Following, an example of a minimal required configuration.

```yaml
mik_software_daemon:
    daemons:
        <daemon_id_name>: ~
```

Other available parameters (with default values):

```yaml
mik_software_daemon:
    daemons:
        <daemon_id_name>: ~
            appName: systemDaemon
            appDir: %kernel.root_dir%
            appDescription: System Daemon
            logDir: %kernel.logs_dir%
            authorName: ~
            authorEmail: ~
            appPidDir: %kernel.cache_dir%/daemons/
            sysMaxExecutionTime: 0
            sysMaxInputTime: 0
            sysMemoryLimit: 1024M
            appUser: www-data
            appGroup: www-data
            appRunAsGID: 1000
            appRunAsUID: 1000
            logVerbosity: info
```

Usage
-----

 1. Create a Symfony2 command that extends from `DaemonizedCommand`:
 
    ```php
    <?php
    
    namespace <Application>\<Bundle>\Command;
    
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;
    use Symfony\Component\Console\Input\InputArgument;
    use MikSoftware\DaemonBundle\Commnad\DaemonizedCommand;
    
    class <Name>Command extends DaemonizedCommand
    {
        protected function configureDaemonCommand()
        {
            $this
                ->setName('<name>')
                ->setDescription('<description>')
                ->setHelp('Usage <info>php app/console <name> start|stop|restart</info>');
        }
    
        /**
         * Starts asynchronous release:deploy commands for queue items.
         */
        protected function daemonLogic()
        {
            // Log something
            $this->getContainer()->get('logger')->info('Daemon is running!');
            // And then sleep for 5 seconds
            $this->daemon->iterate(1);
        }
    }
    ```

 2. Run your daemon

    ```bash
    mik@mbp:~$ php app/console <name> start
    mik@mbp:~$ php app/console <name> stop
    mik@mbp:~$ php app/console <name> restart
    ```

The methods `start`, `stop` and `restart` will be automatically added to your Symfony2 command. It is possible to add new methods to your command as follow:
  
```php
<?php
    // ...
    class <Name>Command extends DaemonizedCommand
    {
        protected function configureDaemonCommand()
        {
            $this->addMethods(array('status'));        
            $this
                ->setName('<name>')
                ->setDescription('<description>')
                ->setHelp('Usage <info>php app/console <name> start|stop|restart</info>');
        }
            
        // ...
            
        protected function status()
        {
            // TODO: implement the new method
        }
    }
```

License
-------

DaemonBundle is completly open and it is released under the terms of BSD 3-Clause.

Copyright (c) 2013-2014, Michele Nucci
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
3. Neither the name of the M1KSoftware and Michele Nucci nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY MICHELE NUCCI ''AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL MICHELE NUCCI BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
