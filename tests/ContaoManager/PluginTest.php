<?php

namespace Pdir\ContaoLlmsBundle\Tests\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Pdir\ContaoLlmsBundle\ContaoManager\Plugin;
use Pdir\ContaoLlmsBundle\PdirContaoLlmsBundle;
use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase
{
    public function testLoadsTheBundleAfterTheCoreBundle(): void
    {
        $bundles = (new Plugin())->getBundles($this->createStub(ParserInterface::class));

        $this->assertCount(1, $bundles);
        $this->assertInstanceOf(BundleConfig::class, $bundles[0]);
        $this->assertSame(PdirContaoLlmsBundle::class, $bundles[0]->getName());
        $this->assertSame([ContaoCoreBundle::class], $bundles[0]->getLoadAfter());
    }
}
