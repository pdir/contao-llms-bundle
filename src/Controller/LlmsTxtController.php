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
#[Route('/llms.txt', name: 'pdir_contao_llms_txt', defaults: ['_scope' => 'frontend'])]
class LlmsTxtController extends AbstractController
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

    public function __invoke(Request $request): Response
    {
        $this->initializeContaoFramework();

        [$rootPage, $rootPages] = $this->getRootPages($request, 'llms');

        $sections = $this->getSections($rootPages);
        $startPage = $request->getSchemeAndHttpHost() . $request->getBasePath() . '/';

        $content = $this->generator->generateBasic($rootPage, $sections, $startPage);

        // @todo dispatch an event so that other extensions can modify the content

        // Changes are covered by the cache tag, the lifetime limits other changes
        return $this->createResponse($request, 'llms.txt', $content, 86400);
    }
}
