<?php

namespace Pdir\ContaoLlmsBundle\Tests\EventListener;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;
use Pdir\ContaoLlmsBundle\EventListener\DescribedByListener;
use Pdir\ContaoLlmsBundle\Tests\Fixtures\ModelFactoryTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DescribedByListenerTest extends TestCase
{
    use ModelFactoryTrait;

    private const HTML = "<!DOCTYPE html>\n<html><head><title>Test</title></head><body></body></html>";

    public function testAddsTheLinkElementAndHeader(): void
    {
        $response = $this->dispatch($this->createRootPage());

        $this->assertSame('<https://example.org/llms.txt>; rel="describedby"; type="text/plain"', $response->headers->get('Link'));
        $this->assertStringContainsString('<link rel="describedby" href="https://example.org/llms.txt" type="text/plain">'."\n</head>", $response->getContent());
    }

    public function testLinksTheLanguageSpecificFileOfTheUrlPrefix(): void
    {
        $response = $this->dispatch($this->createRootPage(['urlPrefix' => 'de']));

        $this->assertSame('<https://example.org/de/llms.txt>; rel="describedby"; type="text/plain"', $response->headers->get('Link'));
        $this->assertStringContainsString('href="https://example.org/de/llms.txt"', $response->getContent());
    }

    public function testKeepsExistingLinkHeaders(): void
    {
        $response = new Response(self::HTML, 200, ['Content-Type' => 'text/html; charset=UTF-8', 'Link' => '</style.css>; rel="preload"']);
        $response = $this->dispatch($this->createRootPage(), $response);

        $this->assertCount(2, $response->headers->all('link'));
    }

    public function testDoesNothingIfDisabled(): void
    {
        foreach ([['llms' => false], ['llmsDescribedBy' => false], ['maintenanceMode' => true]] as $data) {
            $response = $this->dispatch($this->createRootPage($data));

            $this->assertFalse($response->headers->has('Link'));
            $this->assertSame(self::HTML, $response->getContent());
        }
    }

    public function testRequiresTheFallbackRootPageForTheLlmsTxtWithoutUrlPrefix(): void
    {
        $rootPage = $this->createRootPage(['fallback' => false]);

        $response = $this->dispatch($rootPage, null, $this->createRootPage(['id' => 2, 'llms' => false]));
        $this->assertFalse($response->headers->has('Link'));

        $response = $this->dispatch($rootPage, null, $this->createRootPage(['id' => 2]));
        $this->assertTrue($response->headers->has('Link'));
    }

    public function testIgnoresOtherResponses(): void
    {
        $responses = [
            new Response('{}', 200, ['Content-Type' => 'application/json']),
            new Response(self::HTML, 500, ['Content-Type' => 'text/html']),
        ];

        foreach ($responses as $response) {
            $this->assertFalse($this->dispatch($this->createRootPage(), $response)->headers->has('Link'));
        }

        // Back end and sub-requests
        $this->assertFalse($this->dispatch($this->createRootPage(), null, null, 'backend')->headers->has('Link'));
        $this->assertFalse($this->dispatch($this->createRootPage(), null, null, 'frontend', HttpKernelInterface::SUB_REQUEST)->headers->has('Link'));
    }

    private function createRootPage(array $data = []): PageModel
    {
        return $this->createModel(PageModel::class, [
            'id' => 1,
            'type' => 'root',
            'fallback' => true,
            'urlPrefix' => '',
            'llms' => true,
            'llmsDescribedBy' => true,
            'maintenanceMode' => false,
            ...$data,
        ]);
    }

    private function dispatch(PageModel $rootPage, Response|null $response = null, PageModel|null $fallback = null, string $scope = 'frontend', int $requestType = HttpKernelInterface::MAIN_REQUEST): Response
    {
        // A page whose details are already loaded, so no database is needed
        $pageModel = $this->createStub(PageModel::class);
        $pageModel->method('loadDetails')->willReturnSelf();
        $pageModel->method('__get')->willReturnCallback(static fn (string $key): mixed => 'rootId' === $key ? 1 : null);

        $request = Request::create('https://example.org/de/seite');
        $request->attributes->set('_scope', $scope);
        $request->attributes->set('pageModel', $pageModel);

        $response ??= new Response(self::HTML, 200, ['Content-Type' => 'text/html; charset=UTF-8']);

        $pageAdapter = new class($rootPage, $fallback) extends Adapter {
            public function __construct(private readonly PageModel $rootPage, private readonly PageModel|null $fallback)
            {
            }

            public function __call(string $name, array $arguments): mixed
            {
                return match ($name) {
                    'findById' => $this->rootPage,
                    'findPublishedFallbackByHostname' => $this->fallback,
                };
            }
        };

        $framework = $this->createStub(ContaoFramework::class);
        $framework->method('getAdapter')->willReturn($pageAdapter);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $name, array $parameters): string => 'https://example.org/'.(isset($parameters['urlPrefix']) ? $parameters['urlPrefix'].'/' : '').'llms.txt',
        );

        $kernel = $this->createStub(HttpKernelInterface::class);
        (new DescribedByListener($framework, $urlGenerator))(new ResponseEvent($kernel, $request, $requestType, $response));

        return $response;
    }
}
