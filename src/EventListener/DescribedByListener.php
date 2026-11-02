<?php

namespace Pdir\ContaoLlmsBundle\EventListener;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Makes the llms.txt discoverable from every front end page with
 * rel="describedby", both as an HTML <link> element and as an HTTP Link
 * header. Pages of a root page with a URL prefix (e.g. "de") point to the
 * language-specific file (/de/llms.txt).
 *
 * The listener runs before the response is stored in the HTTP cache, so
 * cached pages contain the link as well.
 *
 * @internal
 */
#[AsEventListener]
class DescribedByListener
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if (
            !$event->isMainRequest()
            || 'frontend' !== $request->attributes->get('_scope')
            || !$response->isSuccessful()
            || !str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')
            || null === ($url = $this->getLlmsTxtUrl($request))
        ) {
            return;
        }

        $response->headers->set('Link', \sprintf('<%s>; rel="describedby"; type="text/plain"', $url), false);

        $content = $response->getContent();

        if (\is_string($content) && false !== ($pos = stripos($content, '</head>'))) {
            $link = \sprintf('<link rel="describedby" href="%s" type="text/plain">', htmlspecialchars($url, ENT_QUOTES));
            $response->setContent(substr_replace($content, $link."\n", $pos, 0));
        }
    }

    /**
     * Returns the URL of the llms.txt that contains the current page or null
     * if there is none or the root page does not want it to be linked.
     */
    private function getLlmsTxtUrl(Request $request): ?string
    {
        $pageModel = $request->attributes->get('pageModel');

        if (!$pageModel instanceof PageModel) {
            return null;
        }

        $pageModel->loadDetails();

        $pageAdapter = $this->framework->getAdapter(PageModel::class);
        $rootPage = $pageAdapter->findById($pageModel->rootId);

        if (null === $rootPage || !$rootPage->llms || !$rootPage->llmsDescribedBy || $rootPage->maintenanceMode) {
            return null;
        }

        $urlPrefix = (string) $rootPage->urlPrefix;

        try {
            if ('' !== $urlPrefix) {
                return $this->urlGenerator->generate('pdir_contao_llms_txt_prefixed', ['urlPrefix' => $urlPrefix], UrlGeneratorInterface::ABSOLUTE_URL);
            }

            // The /llms.txt is served by the fallback root page of the domain
            if (!$rootPage->fallback) {
                $fallback = $pageAdapter->findPublishedFallbackByHostname($request->getHost(), ['fallbackToEmpty' => true]);

                if (null === $fallback || !$fallback->llms || $fallback->maintenanceMode) {
                    return null;
                }
            }

            return $this->urlGenerator->generate('pdir_contao_llms_txt', [], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (ExceptionInterface) {
            return null;
        }
    }
}
