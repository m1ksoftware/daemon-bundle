DaemonBundle
============
[![PayPayl donate button](http://img.shields.io/paypal/donate.png?color=green)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=NFQGD52UP2RLA "Donate once-off to this project using Paypal")

DeamonBundle allows to easily convert your Symfony2 console scripts into system daemons.

DaemonBundle is a based in the PEAR library System_Daemon which was created by Kevin Vanzonneveld and it is inspaired by [uecode/daemon-bundle](https://github.com/uecode/daemon-bundle).

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

 3. Configure the bundle by adding parameters to the  `config.yml` file:

    ```yaml
    # app/config/config.yml
    
    mik_software_daemon:
        daemons:
            <command name>:
                appName: <name>
                appDir: %kernel.root_dir%
                appDescription: <description>
                logDir: %kernel.logs_dir%
                appPidDir: %kernel.cache_dir%/daemons/
                appRunAsGID: 1
                appRunAsUID: 1
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

The methods `start`, `stop` and `restart` will be automatically added to your command. It is possible to add new methods to your command as follow:
  
    ```php
    <?php
        ....
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
            
            ...
            
            protected function status()
            {
                // TODO: implement the new method
            }
        }
    ```
            
