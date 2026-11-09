<?php

namespace Pdir\ContaoLlmsBundle\Tests\Service;

use Contao\ArticleModel;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\NewsModel;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use Pdir\ContaoLlmsBundle\Service\LlmsGenerator;
use Pdir\ContaoLlmsBundle\Tests\Fixtures\LanguageFileTranslator;
use Pdir\ContaoLlmsBundle\Tests\Fixtures\ModelFactoryTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LlmsGeneratorTest extends TestCase
{
    use ModelFactoryTrait;

    private const START_PAGE = 'https://example.org/';

    // 2026-10-15 12:00 (Europe/Berlin)
    private const TSTAMP = 1792058400;

    public function testGeneratesTheLlmsTxt(): void
    {
        $rootPage = $this->createRootPage([
            'llmsDescription' => "Kurz.\r\n\r\nDetails:\r\n\r\n- A\r\n    - B",
            'llmsTxt' => 'Bitte freundlich sein.',
            'llmsFull' => true,
            'llmsSitemap' => true,
            'llmsEmail' => 'info@example.org',
            'llmsPhone' => '+49 1',
            'llmsGuidelines' => true,
        ]);

        $items = [
            $this->createPageItem(2, 'Startseite', 'https://example.org/', 'Start'),
            $this->createItem('article', 'Geschichte [1999]', 'https://example.org/a b(c)', 'Seit 1999.'),
            $this->createPageItem(3, 'Team', 'https://example.org/team', "Zeile 1\r\n\r\nZeile 2", 'Über uns'),
            $this->createPageItem(4, 'Archiv', 'https://example.org/archiv', '', 'optional'),
            // Generated before and empty (e.g. a redirect)
            $this->createPageItem(5, 'Weiterleitung', 'https://example.org/weiter', '', '', ['llmsFullDescriptionGenerated' => self::TSTAMP]),
        ];

        $legal = [$this->createItem('page', 'Impressum', 'https://example.org/impressum')];

        $expected = <<<'MD'
            # Testfirma

            > Kurz.

            Details:

            - A
                - B

            Bitte freundlich sein.

            ## Wichtige Seiten

            - [Startseite](https://example.org/): Start
            - [Geschichte \[1999\]](https://example.org/a%20b%28c%29): Seit 1999.

            ## Über uns

            - [Team](https://example.org/team): Zeile 1
              Zeile 2

            ## Rechtliches

            - [Impressum](https://example.org/impressum)

            ## Kontakt

            - E-Mail: info@example.org
            - Telefon: +49 1

            ## Nutzungshinweise

            - Bei Verwendung von Inhalten dieser Website bitte „Testfirma“ als Quelle nennen.
            - Nach Möglichkeit auf die Originalquelle verlinken.
            - Für eine kommerzielle Nutzung: info@example.org
            - Die Nutzung für Bildung und Forschung ist erwünscht.

            ## Quellenangabe

            Beim Zitieren bitte folgendes Format verwenden:
            „Laut Testfirma (https://example.org/): [zitierter Inhalt]“

            ## Optional

            - [Archiv](https://example.org/archiv)
            - [llms-full.txt](https://example.org/llms-full.txt): Vollständige Inhalte aller Seiten als Markdown
            - [Sitemap](https://example.org/sitemap.xml): Alle indexierbaren URLs als XML
            MD;

        $sections = [['root' => $rootPage, 'items' => $items, 'legal' => $legal]];

        $this->assertSame($expected, $this->createGenerator()->generateBasic($rootPage, $sections, self::START_PAGE));
    }

    public function testOmitsOptionalPartsOfTheLlmsTxt(): void
    {
        $rootPage = $this->createRootPage(['description' => 'Meta-Beschreibung']);
        $sections = [['root' => $rootPage, 'items' => [$this->createPageItem(2, 'Startseite', 'https://example.org/')], 'legal' => []]];

        $expected = <<<'MD'
            # Testfirma

            > Meta-Beschreibung

            ## Wichtige Seiten

            - [Startseite](https://example.org/)
            MD;

        $this->assertSame($expected, $this->createGenerator()->generateBasic($rootPage, $sections, self::START_PAGE));
    }

    public function testFallsBackToTheDescriptionOfTheFirstPage(): void
    {
        $rootPage = $this->createRootPage();
        $sections = [['root' => $rootPage, 'items' => [$this->createPageItem(2, 'Startseite', 'https://example.org/', 'Die Startseite')], 'legal' => []]];

        $this->assertStringStartsWith("# Testfirma\n\n> Die Startseite\n\n## Wichtige Seiten", $this->createGenerator()->generateBasic($rootPage, $sections, self::START_PAGE));
    }

    public function testDistinguishesTheSectionsOfMultipleLanguages(): void
    {
        $german = $this->createRootPage(['llmsTitle' => 'Testfirma']);
        $english = $this->createRootPage(['id' => 10, 'language' => 'en']);

        $sections = [
            ['root' => $german, 'items' => [$this->createPageItem(2, 'Startseite', 'https://example.org/')], 'legal' => [$this->createItem('page', 'Impressum', 'https://example.org/impressum')]],
            ['root' => $english, 'items' => [$this->createPageItem(11, 'Home', 'https://example.org/en/')], 'legal' => [$this->createItem('page', 'Imprint', 'https://example.org/en/imprint')]],
        ];

        $content = $this->createGenerator()->generateBasic($german, $sections, self::START_PAGE);

        $this->assertStringContainsString("## Wichtige Seiten (de)\n\n- [Startseite](https://example.org/)", $content);
        $this->assertStringContainsString("## Key Pages (en)\n\n- [Home](https://example.org/en/)", $content);
        $this->assertStringContainsString("## Rechtliches\n\n- [Impressum (de)](https://example.org/impressum)\n- [Imprint (en)](https://example.org/en/imprint)", $content);
    }

    public function testLinksTheLlmsFullTxtWithTheUrlPrefix(): void
    {
        $rootPage = $this->createRootPage(['llmsFull' => true, 'llmsSitemap' => true]);
        $sections = [['root' => $rootPage, 'items' => [$this->createPageItem(2, 'Startseite', 'https://example.org/de/')], 'legal' => []]];

        $content = $this->createGenerator()->generateBasic($rootPage, $sections, self::START_PAGE, 'de/');

        $this->assertStringContainsString('[llms-full.txt](https://example.org/de/llms-full.txt)', $content);

        // The sitemap is not language-specific
        $this->assertStringContainsString('[Sitemap](https://example.org/sitemap.xml)', $content);
    }

    public function testTranslatesTheFixedTexts(): void
    {
        $rootPage = $this->createRootPage(['language' => 'en', 'llmsEmail' => 'info@example.org', 'llmsGuidelines' => true]);
        $sections = [['root' => $rootPage, 'items' => [$this->createPageItem(2, 'Home', 'https://example.org/')], 'legal' => []]];

        $content = $this->createGenerator()->generateBasic($rootPage, $sections, self::START_PAGE);

        $this->assertStringContainsString('## Key Pages', $content);
        $this->assertStringContainsString("## Contact\n\n- E-mail: info@example.org", $content);
        $this->assertStringContainsString('- For commercial use, contact info@example.org.', $content);
        $this->assertStringContainsString('"According to Testfirma (https://example.org/), [quoted content]"', $content);
    }

    public function testGeneratesTheLlmsFullTxt(): void
    {
        $rootPage = $this->createRootPage([
            'llmsDescription' => "Kurz.\n\nDetails.",
            'llmsFullTxt' => 'Vollständige Inhalte.',
            'llmsEmail' => 'support@example.org',
        ]);

        $insertTagParser = $this->createStub(InsertTagParser::class);
        $insertTagParser
            ->method('replaceInline')
            ->willReturnCallback(static fn (string $html): string => str_replace('{{date::Y}}', '2026', $html))
        ;

        $items = [
            $this->createPageItem(2, 'Manuell', 'https://example.org/manuell', 'Von Hand', '', [
                'llmsFullDescriptionOverride' => true,
                'llmsFullDescription' => "Absatz 1\n \n\n\n   \nAbsatz 2",
            ]),
            [
                ...$this->createItem('article', 'Geschichte', 'https://example.org/index/articles/geschichte', 'Seit 1999.'),
                'model' => $this->createModel(ArticleModel::class, ['id' => 1, 'teaser' => '<p>Seit {{date::Y}} am Markt, siehe <a href="files/chronik.pdf">Chronik</a>.</p>']),
            ],
            // Pages without content of their own are skipped
            $this->createPageItem(3, 'Leer', 'https://example.org/leer', '', '', ['llmsFullDescriptionOverride' => true]),
            // Items of other types (e.g. added by the hook) only have a heading
            $this->createItem('custom', 'Angebote', 'https://example.org/angebote', 'Der Woche'),
        ];

        $legal = [$this->createItem('page', 'Impressum', 'https://example.org/impressum')];

        $expected = <<<'MD'
            # Testfirma

            > Kurz.

            Details.

            Vollständige Inhalte.

            Stand: Oktober 2026 · Sprache: Deutsch · Support: support@example.org

            ---

            # Manuell

            URL: https://example.org/manuell
            Zuletzt geändert: 2026-10-15T12:00:00+02:00

            > Von Hand

            Absatz 1

            Absatz 2

            ---

            # Geschichte

            URL: https://example.org/index/articles/geschichte
            Zuletzt geändert: 2026-10-15T12:00:00+02:00

            > Seit 1999.

            Seit 2026 am Markt, siehe [Chronik](https://example.org/files/chronik.pdf).

            ---

            # Angebote

            URL: https://example.org/angebote
            Zuletzt geändert: 2026-10-15T12:00:00+02:00

            > Der Woche

            ---

            # Rechtliches

            - [Impressum](https://example.org/impressum)

            # Kontakt

            - E-Mail: support@example.org
            MD;

        $sections = [['root' => $rootPage, 'items' => $items, 'legal' => $legal]];
        $generator = $this->createGenerator(insertTagParser: $insertTagParser);

        $this->assertSame($expected, $generator->generateFull($rootPage, $sections, self::START_PAGE));
    }

    public function testListsAllLanguagesInTheStatusLine(): void
    {
        $german = $this->createRootPage();
        $english = $this->createRootPage(['id' => 10, 'language' => 'en']);

        $sections = [
            ['root' => $german, 'items' => [], 'legal' => []],
            ['root' => $english, 'items' => [], 'legal' => []],
        ];

        $content = $this->createGenerator()->generateFull($german, $sections, self::START_PAGE);

        $this->assertMatchesRegularExpression('/^Stand: \S+ \d{4} · Sprache: Deutsch, Englisch$/m', $content);
        $this->assertStringNotContainsString('Support:', $content);
    }

    public function testGeneratesAndStoresThePageContent(): void
    {
        $html = '<html><body><nav>Menü</nav><div id="main"><h1>Willkommen</h1><p>Inhalt mit <a href="/kontakt" title="Kontakt">Link</a>.</p></div><footer>Footer</footer></body></html>';
        $requests = [];

        $client = new MockHttpClient(static function (string $method, string $url, array $options) use ($html, &$requests): MockResponse {
            $requests[] = $url;

            return new MockResponse($html);
        });

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('update')
            ->with(
                'tl_page',
                $this->callback(static fn (array $data): bool => "# Willkommen\n\nInhalt mit [Link](https://example.org/kontakt)." === $data['llmsFullDescription'] && $data['llmsFullDescriptionGenerated'] >= time() - 5),
                ['id' => 2],
            )
        ;

        $rootPage = $this->createRootPage();
        $sections = [['root' => $rootPage, 'items' => [$this->createPageItem(2, 'Startseite', 'https://example.org/')], 'legal' => []]];

        $content = $this->createGenerator($client, $connection)->generateFull($rootPage, $sections, self::START_PAGE);

        $this->assertSame(['https://example.org/'], $requests);
        $this->assertStringContainsString("\n\n## Willkommen\n\nInhalt mit [Link](https://example.org/kontakt).\n\n---", $content);
        $this->assertStringNotContainsString('Menü', $content);
        $this->assertStringNotContainsString('Footer', $content);
    }

    public function testLimitsThePageContentToTwoHeadingLevels(): void
    {
        $markdown = <<<'MD'
            # Startseite

            Intro.

            # Free Contao Themes für moderne Websites.

            ## Häufige Fragen

            ### Was sind Contao Themes?

            Eine Designvorlage.

            #### **Fett** und *kursiv*

            ```
            # Kein Heading
            ```

            ## Version 5.7 und mehr...
            MD;

        $expected = <<<'MD'
            Intro.

            ## Free Contao Themes für moderne Websites

            ## Häufige Fragen

            **Was sind Contao Themes?**

            Eine Designvorlage.

            **Fett und *kursiv***

            ```
            # Kein Heading
            ```

            ## Version 5.7 und mehr...
            MD;

        $rootPage = $this->createRootPage();
        $item = $this->createPageItem(2, 'Startseite', 'https://example.org/', '', '', ['llmsFullDescriptionOverride' => true, 'llmsFullDescription' => $markdown]);
        $sections = [['root' => $rootPage, 'items' => [$item], 'legal' => []]];

        $content = $this->createGenerator()->generateFull($rootPage, $sections, self::START_PAGE);

        $this->assertStringContainsString("\n\n".$expected."\n\n---", $content);
        $this->assertDoesNotMatchRegularExpression('/^#{3,} /m', str_replace('# Kein Heading', '', $content));
    }

    public function testAddsTheSelectedImages(): void
    {
        $rootPage = $this->createRootPage();
        $item = [
            ...$this->createPageItem(2, 'Startseite', 'https://example.org/', 'Die Startseite', '', ['llmsFullDescriptionOverride' => true, 'llmsFullDescription' => '## Willkommen']),
            'images' => [
                ['path' => 'files/themes/creuva laptop.jpg', 'alt' => 'Creuva [Laptop]'],
                ['path' => 'files/ohne-alt.png', 'alt' => ''],
                ['url' => 'https://cdn.example.org/bild.webp', 'alt' => 'Extern'],
            ],
        ];

        $content = $this->createGenerator()->generateFull($rootPage, [['root' => $rootPage, 'items' => [$item], 'legal' => []]], self::START_PAGE);

        $this->assertStringContainsString(
            "> Die Startseite\n\n![Creuva \\[Laptop\\]](https://example.org/files/themes/creuva%20laptop.jpg)\n\n![](https://example.org/files/ohne-alt.png)\n\n![Extern](https://cdn.example.org/bild.webp)\n\n## Willkommen",
            $content,
        );
    }

    public function testReusesTheStoredPageContent(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('update');

        $rootPage = $this->createRootPage();

        $item = $this->createPageItem(2, 'Startseite', 'https://example.org/', '', '', [
            'llmsFullDescription' => 'Gespeichert.',
            'llmsFullDescriptionGenerated' => time() - 60,
        ]);

        $sections = [['root' => $rootPage, 'items' => [$item], 'legal' => []]];
        $content = $this->createGenerator($this->createFailingClient(), $connection)->generateFull($rootPage, $sections, self::START_PAGE);

        $this->assertStringContainsString("\n\nGespeichert.\n\n---", $content);
    }

    public function testRegeneratesThePageContentIfThePageHasChanged(): void
    {
        $client = new MockHttpClient([new MockResponse('<html><body><main><p>Neu.</p></main></body></html>')]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('update');

        $rootPage = $this->createRootPage();

        $item = $this->createPageItem(2, 'Startseite', 'https://example.org/', '', '', [
            'llmsFullDescription' => 'Alt.',
            'llmsFullDescriptionGenerated' => time() - 60,
            'llmsLastModified' => time(),
        ]);

        $sections = [['root' => $rootPage, 'items' => [$item], 'legal' => []]];
        $content = $this->createGenerator($client, $connection)->generateFull($rootPage, $sections, self::START_PAGE);

        $this->assertStringContainsString("\n\nNeu.\n\n---", $content);
        $this->assertStringNotContainsString('Alt.', $content);
    }

    public function testStoresAnEmptyContentForRedirects(): void
    {
        $client = new MockHttpClient([new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'https://example.org/ziel']])]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('update')
            ->with('tl_page', $this->callback(static fn (array $data): bool => '' === $data['llmsFullDescription']), ['id' => 2])
        ;

        $rootPage = $this->createRootPage();
        $sections = [['root' => $rootPage, 'items' => [$this->createPageItem(2, 'Weiterleitung', 'https://example.org/weiter')], 'legal' => []]];

        $content = $this->createGenerator($client, $connection)->generateFull($rootPage, $sections, self::START_PAGE);

        $this->assertStringNotContainsString('## Weiterleitung', $content);
    }

    public function testRefreshesTheStoredContentFromTheConfiguredMaxAge(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('update');

        $rootPage = $this->createRootPage();

        $item = $this->createPageItem(2, 'Startseite', 'https://example.org/', '', '', [
            'llmsFullDescription' => 'Gespeichert.',
            'llmsFullDescriptionGenerated' => time() - 3600,
        ]);

        $generator = $this->createGenerator(new MockHttpClient([new MockResponse('<html><body><div id="main"><p>Neu.</p></div></body></html>')]), $connection);
        $generator->setMaxAge(1800);

        $content = $generator->generateFull($rootPage, [['root' => $rootPage, 'items' => [$item], 'legal' => []]], 'https://example.org/');

        $this->assertStringContainsString('Neu.', $content);
    }

    public function testCountsContentsThatCouldNotBeFetched(): void
    {
        $rootPage = $this->createRootPage();
        $item = $this->createPageItem(2, 'Startseite', 'https://example.org/');

        $generator = $this->createGenerator(new MockHttpClient([new MockResponse('Wartung', ['http_code' => 503])]));
        $generator->generateFull($rootPage, [['root' => $rootPage, 'items' => [$item], 'legal' => []]], 'https://example.org/');

        $this->assertSame(1, $generator->getFailures());
    }

    public function testKeepsTheStoredContentOnServerErrors(): void
    {
        $client = new MockHttpClient([new MockResponse('Wartung', ['http_code' => 503])]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('update');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('page ID 2'));

        $rootPage = $this->createRootPage();

        $item = $this->createPageItem(2, 'Startseite', 'https://example.org/', '', '', [
            'llmsFullDescription' => 'Gespeichert.',
            'llmsFullDescriptionGenerated' => time() - 2 * 86400,
        ]);

        $sections = [['root' => $rootPage, 'items' => [$item], 'legal' => []]];
        $content = $this->createGenerator($client, $connection, logger: $logger)->generateFull($rootPage, $sections, self::START_PAGE);

        $this->assertStringContainsString("\n\nGespeichert.\n\n---", $content);
    }

    public function testReusesTheStoredContentOfNews(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with($this->stringContains('tl_llms_content'), ['tl_news', 7])
            ->willReturn(['tstamp' => time() - 60, 'url' => 'https://example.org/news/a', 'content' => 'Gespeicherte Nachricht.'])
        ;

        $connection->expects($this->never())->method('transactional');

        $rootPage = $this->createRootPage();
        $sections = [['root' => $rootPage, 'items' => [$this->createNewsItem(7, 'https://example.org/news/a')], 'legal' => []]];

        $content = $this->createGenerator($this->createFailingClient(), $connection)->generateFull($rootPage, $sections, self::START_PAGE);

        $this->assertStringContainsString("# Nachricht\n\nURL: https://example.org/news/a", $content);
        $this->assertStringContainsString("\n\nGespeicherte Nachricht.\n\n---", $content);
    }

    public function testAddsTheDateAndAuthorOfNews(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['tstamp' => time() - 60, 'url' => 'https://example.org/news/a', 'content' => 'Text.']);

        $news = [...$this->createNewsItem(7, 'https://example.org/news/a'), 'date' => mktime(10, 0, 0, 10, 25, 2026), 'author' => 'Max Mustermann'];
        $withoutAuthor = [...$this->createNewsItem(8, 'https://example.org/news/a'), 'title' => 'Ohne Autor', 'date' => mktime(10, 0, 0, 10, 18, 2026), 'author' => ''];

        $german = $this->createRootPage();
        $content = $this->createGenerator($this->createFailingClient(), $connection)->generateFull($german, [['root' => $german, 'items' => [$news, $withoutAuthor], 'legal' => []]], self::START_PAGE);

        $this->assertStringContainsString("# Nachricht\n\nURL: https://example.org/news/a\nDatum: 25.10.2026 · Autor: Max Mustermann\nZuletzt geändert:", $content);
        $this->assertStringContainsString("# Ohne Autor\n\nURL: https://example.org/news/a\nDatum: 18.10.2026\nZuletzt geändert:", $content);

        $english = $this->createRootPage(['language' => 'en']);
        $content = $this->createGenerator($this->createFailingClient(), $connection)->generateFull($english, [['root' => $english, 'items' => [$news], 'legal' => []]], self::START_PAGE);

        $this->assertStringContainsString("\nDate: Oct 25, 2026 · Author: Max Mustermann\n", $content);
    }

    public function testRegeneratesTheContentOfNewsIfTheUrlHasChanged(): void
    {
        $client = new MockHttpClient([new MockResponse('<html><body><div id="main"><p>Neue Nachricht.</p></div></body></html>')]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn(['tstamp' => time() - 60, 'url' => 'https://example.org/news/alt', 'content' => 'Alt.'])
        ;

        $connection->expects($this->once())->method('transactional');

        $rootPage = $this->createRootPage();
        $sections = [['root' => $rootPage, 'items' => [$this->createNewsItem(7, 'https://example.org/news/neu')], 'legal' => []]];

        $content = $this->createGenerator($client, $connection)->generateFull($rootPage, $sections, self::START_PAGE);

        $this->assertStringContainsString("\n\nNeue Nachricht.\n\n---", $content);
    }

    /**
     * @dataProvider provideHtml
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideHtml')]
    public function testConvertsHtmlToMarkdown(string $html, string $expected): void
    {
        $client = new MockHttpClient([new MockResponse('<html><body><div id="main">'.$html.'</div></body></html>')]);

        $this->assertSame($expected, $this->createGenerator($client)->generateFromUrl('https://example.org/seite'));
    }

    public static function provideHtml(): iterable
    {
        yield 'headings keep their level' => [
            '<h1>Eins</h1><h2>Zwei</h2><h6>Sechs</h6>',
            "# Eins\n\n## Zwei\n\n###### Sechs",
        ];

        yield 'parts of headings are separated' => [
            '<h2><span class="first-headline">So funktioniert es</span><span class="main-headline">In vier Schritten</span></h2><h2><span>Fragen?</span><span>Antworten</span></h2><h3><span>Contao Themes</span> <span>aus Erfahrung</span></h3><h4>Zeile 1<br>Zeile 2</h4>',
            "## So funktioniert es: In vier Schritten\n\n## Fragen? Antworten\n\n### Contao Themes aus Erfahrung\n\n#### Zeile 1 Zeile 2",
        ];

        yield 'relative URLs become absolute' => [
            '<p><a href="kontakt">Kontakt</a> und <a href="/files/preise.pdf">Preise</a></p>',
            '[Kontakt](https://example.org/kontakt) und [Preise](https://example.org/files/preise.pdf)',
        ];

        yield 'images, empty links and titles are removed' => [
            '<p><img src="deko.svg" alt=""><img src="/bild.jpg" alt="Bild"><a href="/leer"> </a><a href="/x" title="Titel">Text</a></p>',
            '[Text](https://example.org/x)',
        ];

        yield 'figures with captions, pictures and linked images are removed' => [
            '<figure><a href="/gross.jpg"><picture><source srcset="/a.webp"><img src="/a.jpg" alt="A"></picture></a><figcaption>Bildunterschrift</figcaption></figure><p><a href="/theme"><img src="/theme.jpg" alt="Theme"></a>Text</p><figure><blockquote>Zitat</blockquote></figure>',
            "Text\n\n> Zitat",
        ];

        yield 'entities and non-breaking spaces' => [
            '<p>Free &amp; Premium&nbsp;Themes &lt;3</p><p>&nbsp;</p><div>   </div><p>Ende</p>',
            "Free & Premium Themes <3\n\nEnde",
        ];

        yield 'nested lists and code blocks keep their indentation' => [
            '<ul><li>A<ul><li>B</li></ul></li></ul><pre><code>  eingerückt'."\n\n\n".'  weiter</code></pre>',
            "- A\n    - B\n\n```\n  eingerückt\n\n\n  weiter\n```",
        ];

        yield 'tables with caption, header and footer' => [
            '<table><caption>Preise</caption><thead><tr><th>Plan</th><th>Preis</th></tr></thead><tfoot><tr><td>Gesamt</td><td>–</td></tr></tfoot><tbody><tr><td>Free</td><td>0 € | 99 €</td></tr><tr><td><a href="/pro">Pro</a></td><td>Zeile 1<br>'."\n".'Zeile 2</td></tr></tbody></table>',
            "Preise\n\n| Plan | Preis |\n|---|---|\n| Free | 0 € \\| 99 € |\n| [Pro](https://example.org/pro) | Zeile 1 Zeile 2 |\n| Gesamt | – |",
        ];

        yield 'tables without header' => [
            '<table><tbody><tr><td>Free</td><td>0 €</td></tr></tbody></table>',
            "| | |\n|---|---|\n| Free | 0 € |",
        ];

        yield 'tables without content' => [
            '<p>Vorher.</p><table><thead><tr></tr></thead><tbody></tbody></table><p>Nachher.</p>',
            "Vorher.\n\nNachher.",
        ];

        yield 'excluded content elements are removed' => [
            '<p>Vorher.</p><!-- llms::stop --><div class="c-logos"><p>Logos</p></div><!-- llms::continue --><p>Mitte.</p><!-- llms::stop --><div>Gruppe<!-- llms::stop --><p>Kind</p><!-- llms::continue --><p>Rest</p></div><!-- llms::continue --><p>Nachher.</p>',
            "Vorher.\n\nMitte.\n\nNachher.",
        ];

        yield 'a start marker without end is ignored' => [
            '<p>Vorher.</p><!-- llms::stop --><p>Nachher.</p>',
            "Vorher.\n\nNachher.",
        ];

        yield 'scripts, forms and navigation are removed' => [
            '<p>Text</p><script>alert(1)</script><form><input name="q"></form><nav>Menü</nav>',
            'Text',
        ];
    }

    public function testUsesTheBodyIfThereIsNoMainArea(): void
    {
        $client = new MockHttpClient([new MockResponse('<html><body><p>Nur Body.</p></body></html>')]);

        $this->assertSame('Nur Body.', $this->createGenerator($client)->generateFromUrl('https://example.org/'));
    }

    private function createGenerator(HttpClientInterface|null $client = null, Connection|null $connection = null, InsertTagParser|null $insertTagParser = null, LoggerInterface|null $logger = null): LlmsGenerator
    {
        if (null === $insertTagParser) {
            $insertTagParser = $this->createStub(InsertTagParser::class);
            $insertTagParser->method('replaceInline')->willReturnArgument(0);
        }

        return new LlmsGenerator(
            $client ?? $this->createFailingClient(),
            $connection ?? $this->createStub(Connection::class),
            $insertTagParser,
            new LanguageFileTranslator(),
            $logger,
        );
    }

    private function createFailingClient(): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->fail('Unexpected request to '.$url);
        });
    }

    private function createRootPage(array $data = []): PageModel
    {
        return $this->createModel(PageModel::class, [
            'id' => 1,
            'type' => 'root',
            'title' => 'Testfirma',
            'language' => 'de',
            ...$data,
        ]);
    }

    private function createItem(string $type, string $title, string $url, string $description = ''): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'tstamp' => self::TSTAMP,
            'lastModified' => 0,
        ];
    }

    private function createPageItem(int $id, string $title, string $url, string $description = '', string $section = '', array $data = []): array
    {
        return [
            ...$this->createItem('page', $title, $url, $description),
            'section' => $section,
            'model' => $this->createModel(PageModel::class, ['id' => $id, 'type' => 'regular', 'title' => $title, ...$data]),
        ];
    }

    private function createNewsItem(int $id, string $url): array
    {
        return [
            ...$this->createItem('news', 'Nachricht', $url),
            'model' => $this->createModel(NewsModel::class, ['id' => $id, 'tstamp' => time() - 3600]),
        ];
    }
}
