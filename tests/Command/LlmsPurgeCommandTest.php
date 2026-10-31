<?php

namespace Pdir\ContaoLlmsBundle\Tests\Command;

use Pdir\ContaoLlmsBundle\Cache\LlmsCacheInvalidator;
use Pdir\ContaoLlmsBundle\Command\LlmsPurgeCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class LlmsPurgeCommandTest extends TestCase
{
    public function testPurgesTheLlmsData(): void
    {
        $cacheInvalidator = $this->createMock(LlmsCacheInvalidator::class);
        $cacheInvalidator->expects($this->once())->method('purge')->willReturn(['pages' => 3, 'records' => 2]);

        $command = new LlmsPurgeCommand($cacheInvalidator);
        $tester = new CommandTester($command);

        $this->assertSame('pdir:llms:purge', $command->getName());
        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('3 pages, 2 news and events', preg_replace('/\s+/', ' ', $tester->getDisplay()));
    }
}
