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

namespace MikSoftware\DaemonBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use MikSoftware\DaemonBundle\DependencyInjection\Compiler\InitPass;

/**
 * Base entry-point for MIKSoftware/DaemonBundle
 *
 * @author    Michele Nucci
 * @copyright 2013-2014 Michele Nucci (http://m1k.info)
 * @license   BSD-3-Clause
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class MikSoftwareDaemonBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
                
        $container->addCompilerPass(new InitPass(), PassConfig::TYPE_OPTIMIZE);
    }    
}
