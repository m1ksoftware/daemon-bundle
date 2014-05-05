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

namespace MikSoftware\DaemonBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class used to manage the configuration tree builder.
 * 
 * @author    Michele Nucci
 * @copyright 2013-2014 Michele Nucci (http://m1k.info)
 * @license   BSD-3-Clause
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('miksoftware_daemons');
        
        $rootNode
            ->children()
                ->arrayNode('daemons')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('appName')->defaultValue('systemDaemon')->end()
                            ->scalarNode('appDir')->defaultValue('%kernel.root_dir%')->end()
                            ->scalarNode('appDescription')->defaultValue('System Daemon')->end()
                            ->scalarNode('logDir')->defaultValue('%kernel.logs_dir%')->end()
                            ->scalarNode('authorName')->defaultValue('')->end()
                            ->scalarNode('authorEmail')->defaultValue('')->end()
                            ->scalarNode('appPidDir')->defaultValue('%kernel.cache_dir%/daemons/')->end()
                            ->scalarNode('sysMaxExecutionTime')->defaultValue('0')->end()
                            ->scalarNode('sysMaxInputTime')->defaultValue('0')->end()
                            ->scalarNode('sysMemoryLimit')->defaultValue('1024M')->end()
                            ->scalarNode('appUser')->defaultValue('www-data')->end()
                            ->scalarNode('appGroup')->defaultValue('www-data')->end()
                            ->scalarNode('appRunAsGID')->defaultValue('1000')->end()
                            ->scalarNode('appRunAsUID')->defaultValue('1000')->end()
                            ->scalarNode('logVerbosity')->defaultValue('info')->end()
                        ->end()
                    ->end()
                ->end()
            ->scalarNode('debug')->defaultValue('false')->end()
        ->end();
        
        return $treeBuilder;
    }
}
