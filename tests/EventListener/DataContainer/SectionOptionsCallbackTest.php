<?php

namespace Pdir\ContaoLlmsBundle\Tests\EventListener\DataContainer;

use Doctrine\DBAL\Connection;
use Pdir\ContaoLlmsBundle\EventListener\DataContainer\SectionOptionsCallback;
use PHPUnit\Framework\TestCase;

class SectionOptionsCallbackTest extends TestCase
{
    public function testReturnsTheSectionsInUseSortedAndWithoutDuplicates(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['Themes', 'ratgeber', ' Themes ', 'Optional', 'Downloads 10', 'Downloads 2']);

        $this->assertSame(['Downloads 2', 'Downloads 10', 'Optional', 'ratgeber', 'Themes'], (new SectionOptionsCallback($connection))());
    }

    public function testAlwaysSuggestsTheOptionalSection(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);

        $this->assertSame(['Optional'], (new SectionOptionsCallback($connection))());
    }
}
