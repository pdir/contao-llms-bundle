<?php

namespace Pdir\ContaoLlmsBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\PageModel;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Explains the paragraph rule of the LLMs description in root pages: only the
 * first paragraph is output as blockquote, the rest as regular Markdown.
 */
#[AsCallback(table: 'tl_page', target: 'config.onload')]
class RootDescriptionLabelCallback
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function __invoke(DataContainer|null $dc = null): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $dc || !$dc->id || null === $request || 'edit' !== $request->query->get('act')) {
            return;
        }

        $page = PageModel::findById($dc->id);

        if (null === $page || 'root' !== $page->type || !isset($GLOBALS['TL_LANG']['tl_page']['llmsRootDescription'])) {
            return;
        }

        $GLOBALS['TL_DCA']['tl_page']['fields']['llmsDescription']['label'] = $GLOBALS['TL_LANG']['tl_page']['llmsRootDescription'];
    }
}
