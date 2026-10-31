<?php

namespace Pdir\ContaoLlmsBundle\Tests\Cache;

use Doctrine\DBAL\Connection;
use FOS\HttpCache\CacheInvalidator;
use Pdir\ContaoLlmsBundle\Cache\LlmsCacheInvalidator;
use PHPUnit\Framework\TestCase;

class LlmsCacheInvalidatorTest extends TestCase
{
    public function testInvalidatesTheCacheTag(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->once())->method('invalidateTags')->with(['pdir.llms']);

        (new LlmsCacheInvalidator($this->createStub(Connection::class), $cacheInvalidator))->invalidate();
    }

    public function testWorksWithoutHttpCache(): void
    {
        $this->expectNotToPerformAssertions();

        (new LlmsCacheInvalidator($this->createStub(Connection::class)))->invalidate();
    }

    public function testDiscardsTheStoredContentOfARecord(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('delete')->with('tl_llms_content', ['source' => 'tl_news', 'sourceId' => 7]);

        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->once())->method('invalidateTags')->with(['pdir.llms']);

        (new LlmsCacheInvalidator($connection, $cacheInvalidator))->discard('tl_news', 7);
    }

    public function testPurgesAllGeneratedContent(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $callback) => $callback($connection));

        $statements = [];

        $connection
            ->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params = []) use (&$statements): int {
                if ($params) {
                    self::assertSame([['llms.txt', 'llms-full.txt']], $params);
                }

                $statements[] = $sql;

                return str_starts_with($sql, 'UPDATE') ? 3 : 2;
            })
        ;

        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->once())->method('invalidateTags')->with(['pdir.llms']);

        $result = (new LlmsCacheInvalidator($connection, $cacheInvalidator))->purge();

        $this->assertSame(['pages' => 3, 'records' => 2], $result);

        // Manually overridden descriptions are kept
        $this->assertStringContainsString('UPDATE tl_page', $statements[0]);
        $this->assertStringContainsString('llmsFullDescriptionOverride = 0', $statements[0]);
        // The change times of the files are kept
        $this->assertSame('DELETE FROM tl_llms_content WHERE source NOT IN (?)', $statements[1]);
    }
}
