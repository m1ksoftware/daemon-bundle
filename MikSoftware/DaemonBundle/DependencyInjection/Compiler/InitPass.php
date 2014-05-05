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

namespace MikSoftware\DaemonBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\Definition;
use MikSoftware\DaemonBundle\Daemon\Definitions;

/**
 * Custom InitPass
 * 
 * @author    Michele Nucci
 * @copyright 2013-2014 Michele Nucci (http://m1k.info)
 * @license   BSD-3-Clause
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class InitPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasParameter('mik_software_daemon.daemons')) {
            return;
        }
    
        $config = $container->getParameter('mik_software_daemon.daemons');
        $filesystem = new Filesystem();
    
        foreach ($config as $name => $cnf) {
            if (null == $cnf) {
                $cnf = array();
            }
    
            $pidDir = $cnf['appPidDir'];
            $filesystem->mkdir($pidDir, 0755);
            
            if (isset($cnf['appUser']) || isset($cnf['appGroup'])) {
                if (isset($cnf['appUser']) && (function_exists('posix_getpwnam'))) {
                    $user = posix_getpwnam($cnf['appUser']);
                    if ($user) {                        
                        $cnf['appRunAsUID'] = $user['uid'];
                    }
                }
    
                if (isset($cnf['appGroup']) && (function_exists('posix_getgrnam'))) {
                    $group = posix_getgrnam($cnf['appGroup']);
                    if ($group) {
                        $cnf['appRunAsGID'] = $group['gid'];
                    }
                }
                
                if (!isset($cnf['appRunAsGID'])) {
                    $user = posix_getpwuid($cnf['appRunAsUID']);
                    $cnf['appRunAsGID'] = $user['gid'];
                }
            }
            
            if (is_string($cnf['logVerbosity'])) {                
                $l = array_search($cnf['logVerbosity'], Definitions::$logLevels);                
                if ($l) {                    
                    $cnf['logVerbosity'] = $l;
                }
            }
            
            $cnf['logLocation']    = rtrim($cnf['logDir'], '/') . '/' . $cnf['appName'] . 'Daemon.log';
            $cnf['appPidLocation'] = rtrim($cnf['appPidDir'], '/') . '/' . $cnf['appName'] . '/' . $cnf['appName'] . '.pid';
                
            unset($cnf['logDir'], $cnf['appPidDir']);
            
            $container->setParameter($name . '.daemon.options', $cnf);
        }
    }    
}
