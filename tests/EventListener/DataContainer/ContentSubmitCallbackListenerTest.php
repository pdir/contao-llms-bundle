<?php

namespace Pdir\ContaoLlmsBundle\Tests\EventListener\DataContainer;

use Doctrine\DBAL\Connection;
use Pdir\ContaoLlmsBundle\Cache\LlmsCacheInvalidator;
use Pdir\ContaoLlmsBundle\EventListener\DataContainer\ContentSubmitCallbackListener;
use Pdir\ContaoLlmsBundle\Tests\Fixtures\DataContainerFactoryTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ContentSubmitCallbackListenerTest extends TestCase
{
    use DataContainerFactoryTrait;

    public function testUpdatesThePageOfChangedArticleContent(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['pid' => 4, 'ptable' => 'tl_article']);
        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with($this->stringContains('SET p.llmsLastModified = ?'), $this->callback(static fn (array $params): bool => 4 === $params[1] && $params[0] >= time() - 5))
        ;

        $cacheInvalidator = $this->createMock(LlmsCacheInvalidator::class);
        $cacheInvalidator->expects($this->once())->method('invalidate');
        $cacheInvalidator->expects($this->never())->method('discard');

        (new ContentSubmitCallbackListener($connection, $cacheInvalidator))->onContentChange($this->createDataContainer(10, 'tl_content'));
    }

    /**
     * @dataProvider provideNewsAndEventTables
     */
    #[DataProvider('provideNewsAndEventTables')]
    public function testDiscardsTheContentOfNewsAndEvents(string $table): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['pid' => 7, 'ptable' => $table]);
        $connection->expects($this->never())->method('executeStatement');

        $cacheInvalidator = $this->createMock(LlmsCacheInvalidator::class);
        $cacheInvalidator->expects($this->once())->method('discard')->with($table, 7);

        (new ContentSubmitCallbackListener($connection, $cacheInvalidator))->onContentChange($this->createDataContainer(10, 'tl_content'));
    }

    public static function provideNewsAndEventTables(): iterable
    {
        yield ['tl_news'];
        yield ['tl_calendar_events'];
    }

    public function testIgnoresContentOfOtherParents(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['pid' => 1, 'ptable' => 'tl_faq']);
        $connection->expects($this->never())->method('executeStatement');

        $cacheInvalidator = $this->createMock(LlmsCacheInvalidator::class);
        $cacheInvalidator->expects($this->never())->method('invalidate');
        $cacheInvalidator->expects($this->never())->method('discard');

        (new ContentSubmitCallbackListener($connection, $cacheInvalidator))->onContentChange($this->createDataContainer(10, 'tl_content'));
    }

    public function testUpdatesThePageOfAChangedArticle(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement')->with($this->anything(), $this->callback(static fn (array $params): bool => 4 === $params[1]));

        $cacheInvalidator = $this->createMock(LlmsCacheInvalidator::class);
        $cacheInvalidator->expects($this->once())->method('invalidate');

        (new ContentSubmitCallbackListener($connection, $cacheInvalidator))->onArticleChange($this->createDataContainer(4, 'tl_article'));
    }

    public function testDoesNothingWithoutId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAssociative');
        $connection->expects($this->never())->method('executeStatement');

        $listener = new ContentSubmitCallbackListener($connection, $this->createStub(LlmsCacheInvalidator::class));
        $listener->onContentChange($this->createDataContainer(null, 'tl_content'));
        $listener->onArticleChange($this->createDataContainer(null, 'tl_article'));
    }
}
