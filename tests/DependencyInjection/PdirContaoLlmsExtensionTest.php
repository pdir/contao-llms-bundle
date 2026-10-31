<?php

namespace Pdir\ContaoLlmsBundle\Tests\DependencyInjection;

use Pdir\ContaoLlmsBundle\Cache\LlmsCacheInvalidator;
use Pdir\ContaoLlmsBundle\Command\LlmsPurgeCommand;
use Pdir\ContaoLlmsBundle\Controller\LlmsFullTxtController;
use Pdir\ContaoLlmsBundle\Controller\LlmsTxtController;
use Pdir\ContaoLlmsBundle\DependencyInjection\PdirContaoLlmsExtension;
use Pdir\ContaoLlmsBundle\EventListener\DataContainer\ContentSubmitCallbackListener;
use Pdir\ContaoLlmsBundle\EventListener\DataContainer\NewsAndEventChangeCallbackListener;
use Pdir\ContaoLlmsBundle\EventListener\DataContainer\PageChangeCallbackListener;
use Pdir\ContaoLlmsBundle\EventListener\DataContainer\RootDescriptionLabelCallback;
use Pdir\ContaoLlmsBundle\Service\LlmsGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

class PdirContaoLlmsExtensionTest extends TestCase
{
    public function testRegistersTheServices(): void
    {
        $container = new ContainerBuilder();
        (new PdirContaoLlmsExtension())->load([], $container);

        foreach ([
            LlmsGenerator::class,
            LlmsCacheInvalidator::class,
            LlmsPurgeCommand::class,
            LlmsTxtController::class,
            LlmsFullTxtController::class,
            ContentSubmitCallbackListener::class,
            NewsAndEventChangeCallbackListener::class,
            PageChangeCallbackListener::class,
            RootDescriptionLabelCallback::class,
        ] as $class) {
            $this->assertTrue($container->hasDefinition($class), $class);
        }

        // Public for the controllers and the purge job of the system maintenance
        $this->assertTrue($container->getDefinition(LlmsTxtController::class)->isPublic());
        $this->assertTrue($container->getDefinition(LlmsCacheInvalidator::class)->isPublic());

        // The HTTP cache is optional
        $reference = $container->getDefinition(LlmsCacheInvalidator::class)->getArgument('$cacheInvalidator');
        $this->assertInstanceOf(Reference::class, $reference);
        $this->assertSame('fos_http_cache.cache_manager', (string) $reference);
        $this->assertSame(ContainerInterface::IGNORE_ON_INVALID_REFERENCE, $reference->getInvalidBehavior());

        $this->assertSame(LlmsGenerator::class, (string) $container->getAlias('pdir.llms_generator'));
    }
}
