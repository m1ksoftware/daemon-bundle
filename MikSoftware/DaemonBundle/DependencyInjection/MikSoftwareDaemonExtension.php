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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * Class that loads and manages the bundle configuration
 *
 * @author    Michele Nucci
 * @copyright 2013-2014 Michele Nucci (http://m1k.info)
 * @license   BSD-3-Clause
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class MikSoftwareDaemonExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
                
        $container->setParameter('mik_software_daemon.daemons', $config['daemons']);
    }
}
