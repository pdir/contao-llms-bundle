<?php

namespace Pdir\ContaoLlmsBundle\Controller;

use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Routing\Page\PageRegistry;
use Pdir\ContaoLlmsBundle\Cache\LlmsLastModified;
use Pdir\ContaoLlmsBundle\Service\LlmsGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route('/llms-full.txt', name: 'pdir_contao_llms_full_txt', defaults: ['_scope' => 'frontend'])]
#[Route('/{urlPrefix}/llms-full.txt', name: 'pdir_contao_llms_full_txt_prefixed', requirements: ['urlPrefix' => '.+'], defaults: ['_scope' => 'frontend'])]
class LlmsFullTxtController extends AbstractController
{
    use LlmsAdapterTrait;

    public function __construct(
        private readonly LlmsGenerator $generator,
        private readonly PageRegistry $pageRegistry,
        private readonly ContentUrlGenerator $urlGenerator,
        private readonly InsertTagParser $insertTagParser,
        private readonly LlmsLastModified $lastModified,
    ) {
    }

    public function __invoke(Request $request, string $urlPrefix = ''): Response
    {
        $this->initializeContaoFramework();

        [$rootPage, $rootPages] = $this->getRootPages($request, 'llmsFull', $urlPrefix);

        $sections = $this->getSections($rootPages);
        $startPage = $request->getSchemeAndHttpHost() . $request->getBasePath() . '/';

        $content = $this->generator->generateFull($rootPage, $sections, $startPage);

        // @todo dispatch an event so that other extensions can modify the content

        // Shorter lifetime, because the rendered pages can also change through
        // modules or extensions without the cache tag being invalidated
        return $this->createResponse($request, 'llms-full.txt', $content, 3600);
    }
}
