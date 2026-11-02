<?php

namespace Pdir\ContaoLlmsBundle\Controller;

use Contao\ArticleModel;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\FilesModel;
use Contao\FrontendUser;
use Contao\NewsArchiveModel;
use Contao\NewsModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Pdir\ContaoLlmsBundle\Cache\LlmsCacheInvalidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

trait LlmsAdapterTrait
{
    /**
     * Returns the fallback root page of the current host and all published
     * root pages of the same domain that have the given flag enabled.
     *
     * With a URL prefix (e.g. /de/llms.txt), only the root page of the
     * current host with this URL prefix is returned.
     *
     * @return array{0: PageModel, 1: list<PageModel>}
     */
    private function getRootPages(Request $request, string $flag, string $urlPrefix = ''): array
    {
        $pageModelAdapter = $this->getContaoAdapter(PageModel::class);

        /** @var PageModel|null $rootPage */
        $rootPage = $pageModelAdapter->findPublishedFallbackByHostname($request->getHost(), ['fallbackToEmpty' => true]);

        if ('' !== $urlPrefix) {
            if (null === $rootPage) {
                throw new NotFoundHttpException();
            }

            foreach ($pageModelAdapter->findPublishedRootPages(['dns' => $rootPage->dns]) ?? [] as $page) {
                if ($urlPrefix === (string) $page->urlPrefix && $page->{$flag} && !$page->maintenanceMode) {
                    return [$page, [$page]];
                }
            }

            throw new NotFoundHttpException();
        }

        if (null === $rootPage || !$rootPage->{$flag}) {
            throw new NotFoundHttpException();
        }

        // The fallback root page comes first, the others in the order of the page tree
        $rootPages = $rootPage->maintenanceMode ? [] : [$rootPage];

        foreach ($pageModelAdapter->findPublishedRootPages(['dns' => $rootPage->dns, 'order' => 'sorting']) ?? [] as $page) {
            if ((int) $page->id !== (int) $rootPage->id && $page->{$flag} && !$page->maintenanceMode) {
                $rootPages[] = $page;
            }
        }

        return [$rootPage, $rootPages];
    }

    /**
     * Creates a response that is cached in the shared cache (like the Contao
     * sitemap) and invalidated whenever a page or its content changes. The
     * Last-Modified header is the time of the last change of the content.
     */
    private function createResponse(Request $request, string $file, string $content, int $sharedMaxAge): Response
    {
        $response = new Response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        $response->setSharedMaxAge($sharedMaxAge); // will be unset by the MakeResponsePrivateListener if a user is logged in

        // Protected pages are only listed for authorized members, so make sure
        // an authorized request does not retrieve the files from the HTTP cache
        $response->setVary('Cookie');

        // The content differs per domain and for members of protected pages
        $user = $this->getUser();
        $groups = $user instanceof FrontendUser ? array_map('intval', (array) $user->groups) : [];
        sort($groups);

        $variant = $request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo().($groups ? '#'.implode(',', $groups) : '');
        $response->setLastModified(new \DateTimeImmutable('@'.$this->lastModified->get($file, $variant, $content)));

        $this->tagResponse([LlmsCacheInvalidator::TAG]);

        // Answers conditional requests (If-Modified-Since) with "304 Not Modified"
        $response->isNotModified($request);

        return $response;
    }

    /**
     * Returns one section per root page, each with its list of items and the
     * legal pages selected in the root page.
     *
     * @param list<PageModel> $rootPages
     *
     * @return list<array{root: PageModel, items: list<array<string, mixed>>, legal: list<array<string, mixed>>}>
     */
    private function getSections(array $rootPages): array
    {
        $sections = [];

        foreach ($rootPages as $rootPage) {
            $pageSections = [];
            $items = $this->getPageAndArticleItems($rootPage, '', $pageSections);
            array_push($items, ...$this->getNewsAndEventItems($rootPage, $pageSections));

            $legalPageIds = array_map('intval', StringUtil::deserialize($rootPage->llmsLegalPages, true));

            // Legal pages only appear in their own section at the end
            if ($legalPageIds) {
                $items = array_values(array_filter(
                    $items,
                    static fn (array $item): bool => !\in_array((int) $item['model']->id, $legalPageIds, true) || 'page' !== $item['type'],
                ));
            }

            $sections[] = [
                'root' => $rootPage,
                'items' => $this->callLegacyHook($rootPage, $items),
                'legal' => $this->getLegalItems($legalPageIds),
            ];
        }

        return $sections;
    }

    /**
     * Returns the selected legal pages (imprint, privacy policy, accessibility
     * statement) in the selected order. Unlike other pages, they are listed
     * even if they are hidden or set to noindex, because they were chosen
     * explicitly.
     *
     * @param list<int> $pageIds
     */
    private function getLegalItems(array $pageIds): array
    {
        if (!$pageIds || null === ($pageModels = $this->getContaoAdapter(PageModel::class)->findMultipleByIds($pageIds))) {
            return [];
        }

        $result = [];

        foreach ($pageModels as $pageModel) {
            $pageModel->loadDetails();

            if (
                !$pageModel->published
                || ($pageModel->start && $pageModel->start > time())
                || ($pageModel->stop && $pageModel->stop <= time())
                || ($pageModel->protected && !$this->isGranted(ContaoCorePermissions::MEMBER_IN_GROUPS, $pageModel->groups))
                || !$this->pageRegistry->isRoutable($pageModel)
            ) {
                continue;
            }

            try {
                $result[] = [
                    'type' => 'page',
                    'model' => $pageModel,
                    'title' => $pageModel->llmsTitle ?: ($pageModel->pageTitle ?: $pageModel->title),
                    'description' => $pageModel->llmsDescription ?: (string) $pageModel->description,
                    'url' => $this->urlGenerator->generate($pageModel, [], UrlGeneratorInterface::ABSOLUTE_URL),
                ];
            } catch (ExceptionInterface) {
                // Skip pages without URL
            }
        }

        return $result;
    }

    /**
     * @param string             $section      The llms.txt section inherited from the parent page
     * @param array<int, string> $pageSections Collects the section of every accessible page (including
     *                                         hidden ones like news reader pages)
     */
    private function getPageAndArticleItems(PageModel $parentPageModel, string $section, array &$pageSections): array
    {
        $pageModelAdapter = $this->getContaoAdapter(PageModel::class);

        // Since the publication status of a page is not inherited by its child
        // pages, we have to use findByPid() instead of findPublishedByPid() and
        // filter out unpublished pages in the foreach loop (see #2217)
        $pageModels = $pageModelAdapter->findByPid($parentPageModel->id, ['order' => 'sorting']);

        if (null === $pageModels) {
            return [];
        }

        $articleModelAdapter = $this->getContaoAdapter(ArticleModel::class);

        $result = [];

        // Recursively walk through all subpages
        foreach ($pageModels as $pageModel) {
            // Load details in order to inherit permission settings (see #5556)
            $pageModel->loadDetails();

            if ($pageModel->protected && !$this->isGranted(ContaoCorePermissions::MEMBER_IN_GROUPS, $pageModel->groups)) {
                continue;
            }

            $pageSection = trim((string) $pageModel->llmsSection) ?: $section;
            $pageSections[(int) $pageModel->id] = $pageSection;
            $isPublished = $pageModel->published && (!$pageModel->start || $pageModel->start <= time()) && (!$pageModel->stop || $pageModel->stop > time());

            if (
                $isPublished
                && !$pageModel->requireItem
                && !$pageModel->llmsHide
                && !str_starts_with((string) $pageModel->robots, 'noindex')
                && $this->pageRegistry->supportsContentComposition($pageModel)
                && $this->pageRegistry->isRoutable($pageModel)
                && 'html' === $this->pageRegistry->getRoute($pageModel)->getDefault('_format')
            ) {
                try {
                    $pageUrl = $this->urlGenerator->generate($pageModel, [], UrlGeneratorInterface::ABSOLUTE_URL);

                    $result[] = [
                        'type' => 'page',
                        'model' => $pageModel,
                        'section' => $pageSection,
                        'title' => $pageModel->llmsTitle ?: ($pageModel->pageTitle ?: $pageModel->title),
                        'description' => $pageModel->llmsDescription ?: (string) $pageModel->description,
                        'url' => $pageUrl,
                        'tstamp' => (int) $pageModel->tstamp,
                        'lastModified' => (int) $pageModel->llmsLastModified,
                        'images' => $this->getImages($pageModel),
                    ];

                    // Get articles with teaser
                    if (null !== ($articleModels = $articleModelAdapter->findPublishedWithTeaserByPid($pageModel->id, ['ignoreFePreview' => true]))) {
                        foreach ($articleModels as $articleModel) {
                            $result[] = [
                                'type' => 'article',
                                'model' => $articleModel,
                                'section' => $pageSection,
                                'title' => $articleModel->title,
                                'description' => $this->toPlainText((string) $articleModel->teaser),
                                'url' => $this->urlGenerator->generate($articleModel, [], UrlGeneratorInterface::ABSOLUTE_URL),
                                'tstamp' => (int) $articleModel->tstamp,
                                'lastModified' => 0,
                            ];
                        }
                    }
                } catch (ExceptionInterface) {
                    // Skip URL for this page but generate child pages
                }
            }

            array_push($result, ...$this->getPageAndArticleItems($pageModel, $pageSection, $pageSections));
        }

        return $result;
    }

    /**
     * Returns the news of the news bundle and the events of the calendar
     * bundle unless they are excluded in the root page. Both bundles are
     * optional.
     *
     * @param array<int, string> $pageSections
     */
    private function getNewsAndEventItems(PageModel $rootPage, array $pageSections): array
    {
        $items = [];

        if (!$rootPage->llmsExcludeNews && class_exists(NewsArchiveModel::class)) {
            array_push($items, ...$this->getArchiveItems(
                NewsArchiveModel::class,
                NewsModel::class,
                $pageSections,
                fn (NewsModel $news): array => [
                    'type' => 'news',
                    'title' => $news->headline,
                    'description' => $this->toPlainText($news->description ?: (string) $news->teaser),
                    'tstamp' => (int) $news->date,
                    'lastModified' => (int) $news->tstamp,
                    'date' => (int) $news->date,
                    'author' => $this->getAuthorName((int) $news->author),
                ],
            ));
        }

        if (!$rootPage->llmsExcludeEvents && class_exists(CalendarModel::class)) {
            $startTimes = [];

            $events = $this->getArchiveItems(
                CalendarModel::class,
                CalendarEventsModel::class,
                $pageSections,
                function (CalendarEventsModel $event) use (&$startTimes): ?array {
                    // Only upcoming and running events
                    if (null === ($occurrence = $this->getNextOccurrence($event))) {
                        return null;
                    }

                    $startTimes[(int) $event->id] = $occurrence[0];

                    return [
                        'type' => 'event',
                        'title' => $event->title,
                        'description' => $this->getEventDescription($event, ...$occurrence),
                        'tstamp' => (int) $event->tstamp,
                        'lastModified' => 0,
                    ];
                },
            );

            // Chronologically by the (next) occurrence, across all calendars
            usort($events, static fn (array $a, array $b): int => $startTimes[(int) $a['model']->id] <=> $startTimes[(int) $b['model']->id]);

            array_push($items, ...$events);
        }

        return $items;
    }

    /**
     * Returns the items of all archives (news archives or calendars) whose
     * reader page belongs to the page tree, using the same rules as the
     * sitemaps of the Contao news and calendar bundles. The items inherit the
     * llms.txt section of their reader page.
     *
     * @param class-string       $archiveClass
     * @param class-string       $itemClass
     * @param array<int, string> $pageSections
     * @param \Closure          $toItem       Returns the type-specific fields of an item or null to skip it
     */
    private function getArchiveItems(string $archiveClass, string $itemClass, array $pageSections, \Closure $toItem): array
    {
        $archiveAdapter = $this->getContaoAdapter($archiveClass);
        $isMember = $this->isGranted('ROLE_MEMBER');
        $archives = $isMember ? $archiveAdapter->findAll() : $archiveAdapter->findByProtected('');

        if (null === $archives) {
            return [];
        }

        $pageAdapter = $this->getContaoAdapter(PageModel::class);
        $itemAdapter = $this->getContaoAdapter($itemClass);
        $result = [];

        foreach ($archives as $archive) {
            $jumpTo = (int) $archive->jumpTo;

            // Skip archives without a reader page in the page tree
            if (!isset($pageSections[$jumpTo])) {
                continue;
            }

            if ($isMember && $archive->protected && !$this->isGranted(ContaoCorePermissions::MEMBER_IN_GROUPS, $archive->groups)) {
                continue;
            }

            $readerPage = $pageAdapter->findWithDetails($jumpTo);

            if (
                null === $readerPage
                || !$readerPage->published
                || ($readerPage->start && $readerPage->start > time())
                || ($readerPage->stop && $readerPage->stop <= time())
                || 'noindex,nofollow' === $readerPage->robots
            ) {
                continue;
            }

            // Only items with their own content (no redirects to other pages or URLs)
            foreach ($itemAdapter->findPublishedDefaultByPid($archive->id) ?? [] as $model) {
                if (str_starts_with((string) $model->robots, 'noindex') || null === ($item = $toItem($model))) {
                    continue;
                }

                try {
                    $result[] = [
                        ...$item,
                        'model' => $model,
                        'section' => $pageSections[$jumpTo],
                        'url' => $this->urlGenerator->generate($model, [], UrlGeneratorInterface::ABSOLUTE_URL),
                    ];
                } catch (ExceptionInterface) {
                    // Skip items without URL
                }
            }
        }

        return $result;
    }

    /**
     * Returns the start and end time of the current or next occurrence of an
     * event or null if the event is over. Recurring events are expanded the
     * same way as in Contao\Events::getAllEvents().
     *
     * @return array{0: int, 1: int}|null
     */
    private function getNextOccurrence(CalendarEventsModel $event): ?array
    {
        $now = time();
        $startTime = (int) $event->startTime;
        $endTime = max((int) $event->endTime, $startTime);

        if ($event->recurring) {
            $repeat = StringUtil::deserialize($event->repeatEach);

            if (isset($repeat['unit'], $repeat['value']) && $repeat['value'] >= 1) {
                $modifier = '+ '.$repeat['value'].' '.$repeat['unit'];
                $recurrences = (int) $event->recurrences;

                for ($count = 1; $endTime < $now; ++$count) {
                    if (($recurrences > 0 && $count > $recurrences) || false === ($next = strtotime($modifier, $endTime))) {
                        return null;
                    }

                    $startTime = (int) strtotime($modifier, $startTime);
                    $endTime = $next;
                }
            }
        }

        return $endTime >= $now ? [$startTime, $endTime] : null;
    }

    /**
     * Returns the date, location and description of an event, e.g.
     * "2026-11-12 – 2026-11-13, Dresden. Two days of workshops".
     */
    private function getEventDescription(CalendarEventsModel $event, int $startTime, int $endTime): string
    {
        $date = date('Y-m-d', $startTime);

        if (date('Y-m-d', $endTime) !== $date) {
            $date .= ' – '.date('Y-m-d', $endTime);
        }

        $meta = implode(', ', array_filter([$date, trim((string) $event->location)]));
        $description = $this->toPlainText($event->description ?: (string) $event->teaser);

        return '' !== $description ? $meta.'. '.$description : $meta;
    }

    /**
     * Converts a teaser or description to a single line of plain text.
     */
    /**
     * Returns the images selected in the LLMs settings of a page with their
     * alternative text (or caption or title) in the language of the page.
     *
     * @return list<array{path: string, alt: string}>
     */
    private function getImages(PageModel $pageModel): array
    {
        $uuids = StringUtil::deserialize($pageModel->llmsImages, true);

        if (!$uuids || null === ($files = $this->getContaoAdapter(FilesModel::class)->findMultipleByUuids($uuids))) {
            return [];
        }

        $images = [];

        foreach ($files as $file) {
            if ('file' !== $file->type) {
                continue;
            }

            $meta = StringUtil::deserialize($file->meta, true);
            $meta = $meta[(string) $pageModel->language] ?? (reset($meta) ?: []);
            $alt = '';

            foreach (['alt', 'caption', 'title'] as $key) {
                if ('' !== ($alt = trim((string) ($meta[$key] ?? '')))) {
                    break;
                }
            }

            $images[] = ['path' => (string) $file->path, 'alt' => $alt];
        }

        return $images;
    }

    /**
     * Returns the name of the back end user who is the author of a news.
     */
    private function getAuthorName(int $userId): string
    {
        if ($userId < 1) {
            return '';
        }

        return trim((string) $this->getContaoAdapter(UserModel::class)->findById($userId)?->name);
    }

    private function toPlainText(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($this->insertTagParser->replaceInline($html))));
    }

    /**
     * HOOK: getLlmsPages($items, $rootPageId, true, $language) allows adding
     * or removing items (arrays with the keys type, title, description, url,
     * tstamp, lastModified and optionally section).
     */
    private function callLegacyHook(PageModel $rootPage, array $items): array
    {
        $systemAdapter = $this->getContaoAdapter(System::class);

        if (isset($GLOBALS['TL_HOOKS']['getLlmsPages']) && \is_array($GLOBALS['TL_HOOKS']['getLlmsPages'])) {
            foreach ($GLOBALS['TL_HOOKS']['getLlmsPages'] as $callback) {
                $items = $systemAdapter->importStatic($callback[0])->{$callback[1]}($items, $rootPage->id, true, $rootPage->language);
            }
        }

        return $items;
    }
}
