<?php

namespace Pdir\ContaoLlmsBundle\Tests\Cache;

use Doctrine\DBAL\Connection;
use Pdir\ContaoLlmsBundle\Cache\LlmsLastModified;
use PHPUnit\Framework\TestCase;

class LlmsLastModifiedTest extends TestCase
{
    private const VARIANT = 'https://example.org/llms.txt';

    public function testKeepsTheTimeIfTheContentIsUnchanged(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->with($this->anything(), ['llms.txt', crc32(self::VARIANT)])
            ->willReturn(['tstamp' => 1700000000, 'url' => self::VARIANT, 'content' => hash('xxh128', 'Inhalt')])
        ;
        $connection->expects($this->never())->method('transactional');

        $this->assertSame(1700000000, (new LlmsLastModified($connection))->get('llms.txt', self::VARIANT, 'Inhalt'));
    }

    public function testStoresTheTimeOfAChange(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['tstamp' => 1700000000, 'url' => self::VARIANT, 'content' => hash('xxh128', 'Alt')]);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $callback) => $callback($connection));
        $connection->expects($this->once())->method('delete')->with('tl_llms_content', ['source' => 'llms.txt', 'sourceId' => crc32(self::VARIANT)]);
        $connection
            ->expects($this->once())
            ->method('insert')
            ->with('tl_llms_content', $this->callback(static fn (array $row): bool => $row['content'] === hash('xxh128', 'Neu') && $row['url'] === self::VARIANT && $row['tstamp'] >= time() - 5))
        ;

        $this->assertGreaterThanOrEqual(time() - 5, (new LlmsLastModified($connection))->get('llms.txt', self::VARIANT, 'Neu'));
    }

    public function testStoresTheTimeOfANewFile(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);
        $connection->expects($this->once())->method('transactional');

        $this->assertGreaterThanOrEqual(time() - 5, (new LlmsLastModified($connection))->get('llms-full.txt', self::VARIANT, 'Inhalt'));
    }
}
