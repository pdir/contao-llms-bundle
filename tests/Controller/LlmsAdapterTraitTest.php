<?php

namespace Pdir\ContaoLlmsBundle\Tests\Controller;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\PageModel;
use Pdir\ContaoLlmsBundle\Controller\LlmsAdapterTrait;
use Pdir\ContaoLlmsBundle\Tests\Fixtures\ModelFactoryTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LlmsAdapterTraitTest extends TestCase
{
    use ModelFactoryTrait;

    private const DAY = 86400;

    public function testSkipsPastEvents(): void
    {
        $event = $this->createEvent(time() - 10 * self::DAY, time() - 10 * self::DAY + 3600);

        $this->assertNull($this->createAdapter()->getNextOccurrence($event));
    }

    public function testIncludesRunningAndUpcomingEvents(): void
    {
        $running = $this->createEvent($start = time() - self::DAY, $end = time() + self::DAY);
        $this->assertSame([$start, $end], $this->createAdapter()->getNextOccurrence($running));

        $upcoming = $this->createEvent($start = time() + 14 * self::DAY, $end = time() + 15 * self::DAY);
        $this->assertSame([$start, $end], $this->createAdapter()->getNextOccurrence($upcoming));
    }

    public function testReturnsTheNextOccurrenceOfRecurringEvents(): void
    {
        $start = time() - 30 * self::DAY;
        $event = $this->createEvent($start, $start + 3600, ['unit' => 'weeks', 'value' => 1]);

        $occurrence = $this->createAdapter()->getNextOccurrence($event);

        // The first occurrence after now, five weeks after the start
        $this->assertNotNull($occurrence);
        $this->assertSame(strtotime('+5 weeks', $start), $occurrence[0]);
        $this->assertSame(3600, $occurrence[1] - $occurrence[0]);
        $this->assertGreaterThanOrEqual(time(), $occurrence[1]);
    }

    public function testSkipsFinishedSeries(): void
    {
        $start = time() - 30 * self::DAY;

        // Two recurrences: 23 and 16 days ago
        $event = $this->createEvent($start, $start + 3600, ['unit' => 'weeks', 'value' => 1], 2);
        $this->assertNull($this->createAdapter()->getNextOccurrence($event));

        // Five recurrences: the last one is in five weeks
        $event = $this->createEvent($start, $start + 3600, ['unit' => 'weeks', 'value' => 1], 5);
        $this->assertSame(strtotime('+5 weeks', $start), $this->createAdapter()->getNextOccurrence($event)[0]);
    }

    public function testIgnoresInvalidRecurrences(): void
    {
        $start = time() - 30 * self::DAY;
        $event = $this->createEvent($start, $start + 3600, ['unit' => 'weeks', 'value' => 0]);

        $this->assertNull($this->createAdapter()->getNextOccurrence($event));
    }

    public function testFormatsTheEventDescription(): void
    {
        $adapter = $this->createAdapter();

        $event = $this->createEvent(0, 0, location: 'Dresden', teaser: '<p>Zwei Tage <strong>Theme-Entwicklung</strong> {{date::Y}}.</p>');
        $this->assertSame('2026-11-12 – 2026-11-13, Dresden. Zwei Tage Theme-Entwicklung 2026.', $adapter->getEventDescription($event, mktime(9, 0, 0, 11, 12, 2026), mktime(17, 0, 0, 11, 13, 2026)));

        // The meta description takes precedence over the teaser
        $event = $this->createEvent(0, 0, description: 'Kurzes Webinar.', teaser: '<p>Teaser</p>');
        $this->assertSame('2026-11-12. Kurzes Webinar.', $adapter->getEventDescription($event, mktime(9, 0, 0, 11, 12, 2026), mktime(10, 0, 0, 11, 12, 2026)));

        $event = $this->createEvent(0, 0);
        $this->assertSame('2026-11-12', $adapter->getEventDescription($event, mktime(9, 0, 0, 11, 12, 2026), mktime(10, 0, 0, 11, 12, 2026)));
    }

    public function testConvertsHtmlToPlainText(): void
    {
        $this->assertSame('Seit 2026 am Markt.', $this->createAdapter()->toPlainText("<p>Seit {{date::Y}}\n   am <em>Markt</em>.</p>"));
    }

    public function testReturnsAllRootPagesOfTheDomainWithoutUrlPrefix(): void
    {
        [$german, $english, $french] = $this->createRootPages();

        $this->assertSame([$german, [$german, $english]], $this->createRootPageAdapter($german, [$german, $english, $french])->getRootPages(Request::create('https://example.org/llms.txt'), 'llms'));
    }

    public function testReturnsTheRootPageOfTheUrlPrefix(): void
    {
        [$german, $english, $french] = $this->createRootPages();
        $adapter = $this->createRootPageAdapter($german, [$german, $english, $french]);

        $this->assertSame([$german, [$german]], $adapter->getRootPages(Request::create('https://example.org/de/llms.txt'), 'llms', 'de'));
        $this->assertSame([$english, [$english]], $adapter->getRootPages(Request::create('https://example.org/en/llms.txt'), 'llms', 'en'));
    }

    public function testThrowsNotFoundForUnknownOrDisabledUrlPrefixes(): void
    {
        [$german, $english, $french] = $this->createRootPages();
        $adapter = $this->createRootPageAdapter($german, [$german, $english, $french]);

        foreach (['fr', 'it'] as $urlPrefix) {
            try {
                $adapter->getRootPages(Request::create('https://example.org/'.$urlPrefix.'/llms.txt'), 'llms', $urlPrefix);
                $this->fail('Expected a NotFoundHttpException for '.$urlPrefix);
            } catch (NotFoundHttpException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * @return list<PageModel>
     */
    private function createRootPages(): array
    {
        return [
            $this->createModel(PageModel::class, ['id' => 1, 'dns' => 'example.org', 'urlPrefix' => 'de', 'llms' => true, 'maintenanceMode' => false]),
            $this->createModel(PageModel::class, ['id' => 2, 'dns' => 'example.org', 'urlPrefix' => 'en', 'llms' => true, 'maintenanceMode' => false]),
            $this->createModel(PageModel::class, ['id' => 3, 'dns' => 'example.org', 'urlPrefix' => 'fr', 'llms' => false, 'maintenanceMode' => false]),
        ];
    }

    /**
     * @param list<PageModel> $rootPages
     */
    private function createRootPageAdapter(PageModel $fallback, array $rootPages): object
    {
        // Stands in for the Contao adapter of the PageModel class
        $pageAdapter = new class($fallback, $rootPages) {
            public function __construct(private readonly PageModel $fallback, private readonly array $rootPages)
            {
            }

            public function findPublishedFallbackByHostname(): PageModel
            {
                return $this->fallback;
            }

            public function findPublishedRootPages(): array
            {
                return $this->rootPages;
            }
        };

        return new class($pageAdapter) {
            use LlmsAdapterTrait;

            public function __construct(private readonly object $pageAdapter)
            {
            }

            public function __call(string $method, array $arguments): mixed
            {
                return $this->{$method}(...$arguments);
            }

            private function getContaoAdapter(string $class): object
            {
                return $this->pageAdapter;
            }
        };
    }

    private function createEvent(int $startTime, int $endTime, array|null $repeatEach = null, int $recurrences = 0, string $location = '', string $description = '', string $teaser = ''): CalendarEventsModel
    {
        return $this->createModel(CalendarEventsModel::class, [
            'id' => 1,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'recurring' => null !== $repeatEach,
            'repeatEach' => null !== $repeatEach ? serialize($repeatEach) : '',
            'recurrences' => $recurrences,
            'location' => $location,
            'description' => $description,
            'teaser' => $teaser,
        ]);
    }

    private function createAdapter(): object
    {
        $insertTagParser = $this->createStub(InsertTagParser::class);
        $insertTagParser->method('replaceInline')->willReturnCallback(static fn (string $html): string => str_replace('{{date::Y}}', '2026', $html));

        return new class($insertTagParser) {
            use LlmsAdapterTrait;

            public function __construct(private readonly InsertTagParser $insertTagParser)
            {
            }

            /**
             * Makes the private methods of the trait accessible.
             */
            public function __call(string $method, array $arguments): mixed
            {
                return $this->{$method}(...$arguments);
            }
        };
    }
}
