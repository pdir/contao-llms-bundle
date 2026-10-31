<?php

namespace Pdir\ContaoLlmsBundle\EventListener\DataContainer;

use Contao\ContentModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\PageModel;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsCallback(table: 'tl_page', target: 'config.onload')]
class MakeLlmsFullDescriptionOverrideEditableCallback
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function __invoke(DataContainer|null $dc = null): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $dc || !$dc->id || null === $request || 'edit' !== $request->query->get('act')) {
            return;
        }

        $page = PageModel::findById($dc->id);

        if (null === $page || 'regular' !== $page->type || !$page->llmsFullDescriptionOverride) {
            return;
        }

        $GLOBALS['TL_DCA']['tl_page']['fields']['llmsFullDescription']['eval']['readonly'] = false;
    }
}
