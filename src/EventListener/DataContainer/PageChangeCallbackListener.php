<?php

namespace Pdir\ContaoLlmsBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Pdir\ContaoLlmsBundle\Cache\LlmsCacheInvalidator;

/**
 * Invalidates the cached LLMs files whenever a page changes.
 */
class PageChangeCallbackListener
{
    public function __construct(private readonly LlmsCacheInvalidator $cacheInvalidator)
    {
    }

    #[AsCallback(table: 'tl_page', target: 'config.onsubmit')]
    #[AsCallback(table: 'tl_page', target: 'config.ondelete')]
    #[AsCallback(table: 'tl_page', target: 'config.oncut')]
    public function onPageChange(DataContainer $dc): void
    {
        $this->cacheInvalidator->invalidate();
    }

    #[AsCallback(table: 'tl_page', target: 'config.oncopy')]
    public function onPageCopy(int|string $insertId, DataContainer $dc): void
    {
        $this->cacheInvalidator->invalidate();
    }
}
