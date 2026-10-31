<?php

namespace Pdir\ContaoLlmsBundle\Tests\Command;

use Doctrine\DBAL\Connection;
use Pdir\ContaoLlmsBundle\Command\LlmsGenerateCommand;
use Pdir\ContaoLlmsBundle\Service\LlmsGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RequestContext;

class LlmsGenerateCommandTest extends TestCase
{
    public function testRequestsTheFilesOfEveryDomain(): void
    {
        $urls = [];

        $tester = $this->createTester(
            [['dns' => 'example.org', 'useSSL' => 1], ['dns' => 'example.com', 'useSSL' => 0]],
            static function (Request $request) use (&$urls): Response {
                $urls[] = $request->getUri();

                return new Response();
            },
        );

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertSame(['https://example.org/llms-full.txt', 'https://example.org/llms.txt', 'http://example.com/llms-full.txt', 'http://example.com/llms.txt'], $urls);
    }

    public function testUsesTheDefaultUriForRootPagesWithoutDomain(): void
    {
        $urls = [];

        $tester = $this->createTester(
            [['dns' => '', 'useSSL' => 1], ['dns' => 'example.org', 'useSSL' => 1]],
            static function (Request $request) use (&$urls): Response {
                $urls[] = $request->getUri();

                return new Response();
            },
            new RequestContext('', 'GET', 'example.org', 'https'),
        );

        // Both root pages share a domain, so it is only requested once
        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertSame(['https://example.org/llms-full.txt', 'https://example.org/llms.txt'], $urls);
    }

    public function testFailsForRootPagesWithoutAnyDomain(): void
    {
        $tester = $this->createTester(
            [['dns' => '', 'useSSL' => 1]],
            function (): Response {
                $this->fail('No file should be requested.');
            },
        );

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('DEFAULT_URI', $tester->getDisplay());
    }

    public function testIgnoresDisabledFiles(): void
    {
        $tester = $this->createTester(
            [['dns' => 'example.org', 'useSSL' => 1]],
            static fn (Request $request): Response => new Response('', str_ends_with($request->getPathInfo(), 'llms.txt') ? 200 : 404),
        );

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('Generated 1 LLMs files', $tester->getDisplay());
    }

    public function testFailsOnServerErrors(): void
    {
        $tester = $this->createTester(
            [['dns' => 'example.org', 'useSSL' => 1]],
            static fn (): Response => new Response('', 500),
        );

        $this->assertSame(Command::FAILURE, $tester->execute([]));
    }

    public function testFailsIfEntriesCouldNotBeFetched(): void
    {
        $tester = $this->createTester(
            [['dns' => 'example.org', 'useSSL' => 1]],
            static fn (): Response => new Response(),
            null,
            2,
        );

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('2 entries', $tester->getDisplay());
    }

    private function createTester(array $rootPages, \Closure $handle, RequestContext|null $requestContext = null, int $failures = 0): CommandTester
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturnCallback($handle);

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($rootPages);

        $generator = $this->createMock(LlmsGenerator::class);
        $generator->expects($this->once())->method('setMaxAge')->with($this->lessThan(86400));
        $generator->method('getFailures')->willReturn($failures);

        return new CommandTester(new LlmsGenerateCommand($kernel, $generator, $connection, $requestContext ?? new RequestContext()));
    }
}
