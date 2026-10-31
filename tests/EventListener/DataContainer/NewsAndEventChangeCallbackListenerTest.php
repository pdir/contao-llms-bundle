<?php

namespace Pdir\ContaoLlmsBundle\Tests\EventListener\DataContainer;

use Pdir\ContaoLlmsBundle\Cache\LlmsCacheInvalidator;
use Pdir\ContaoLlmsBundle\EventListener\DataContainer\NewsAndEventChangeCallbackListener;
use Pdir\ContaoLlmsBundle\EventListener\DataContainer\PageChangeCallbackListener;
use Pdir\ContaoLlmsBundle\Tests\Fixtures\DataContainerFactoryTrait;
use PHPUnit\Framework\TestCase;

class NewsAndEventChangeCallbackListenerTest extends TestCase
{
    use DataContainerFactoryTrait;

    public function testDiscardsTheContentOfChangedNewsAndEvents(): void
    {
        $cacheInvalidator = $this->createMock(LlmsCacheInvalidator::class);
        $cacheInvalidator
            ->expects($this->exactly(2))
            ->method('discard')
            ->willReturnCallback(fn (string $table, int $id) => $this->assertContains([$table, $id], [['tl_news', 7], ['tl_calendar_events', 8]]))
        ;

        $listener = new NewsAndEventChangeCallbackListener($cacheInvalidator);
        $listener->onItemChange($this->createDataContainer(7, 'tl_news'));
        $listener->onItemChange($this->createDataContainer(8, 'tl_calendar_events'));
        $listener->onItemChange($this->createDataContainer(null, 'tl_news'));
    }

    public function testInvalidatesTheCacheOnCopyAndArchiveChanges(): void
    {
        $cacheInvalidator = $this->createMock(LlmsCacheInvalidator::class);
        $cacheInvalidator->expects($this->exactly(2))->method('invalidate');

        $listener = new NewsAndEventChangeCallbackListener($cacheInvalidator);
        $listener->onItemCopy(9, $this->createDataContainer(7, 'tl_news'));
        $listener->onArchiveChange($this->createDataContainer(1, 'tl_calendar'));
    }

    public function testInvalidatesTheCacheOnPageChanges(): void
    {
        $cacheInvalidator = $this->createMock(LlmsCacheInvalidator::class);
        $cacheInvalidator->expects($this->exactly(2))->method('invalidate');

        $listener = new PageChangeCallbackListener($cacheInvalidator);
        $listener->onPageChange($this->createDataContainer(2, 'tl_page'));
        $listener->onPageCopy(3, $this->createDataContainer(2, 'tl_page'));
    }
}
