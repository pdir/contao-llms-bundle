<?php

namespace Pdir\ContaoLlmsBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Pdir\ContaoLlmsBundle\Cache\LlmsCacheInvalidator;

/**
 * Discards the stored content of changed news and events and invalidates the
 * cached LLMs files. The callbacks are only called if the news or calendar
 * bundle is installed, as Contao never loads the tables otherwise.
 */
class NewsAndEventChangeCallbackListener
{
    public function __construct(private readonly LlmsCacheInvalidator $cacheInvalidator)
    {
    }

    #[AsCallback(table: 'tl_news', target: 'config.onsubmit')]
    #[AsCallback(table: 'tl_news', target: 'config.ondelete')]
    #[AsCallback(table: 'tl_news', target: 'config.oncut')]
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit')]
    #[AsCallback(table: 'tl_calendar_events', target: 'config.ondelete')]
    #[AsCallback(table: 'tl_calendar_events', target: 'config.oncut')]
    public function onItemChange(DataContainer $dc): void
    {
        if ($dc->id) {
            $this->cacheInvalidator->discard($dc->table, (int) $dc->id);
        }
    }

    #[AsCallback(table: 'tl_news', target: 'config.oncopy')]
    #[AsCallback(table: 'tl_calendar_events', target: 'config.oncopy')]
    public function onItemCopy(int|string $insertId, DataContainer $dc): void
    {
        $this->cacheInvalidator->invalidate();
    }

    #[AsCallback(table: 'tl_news_archive', target: 'config.onsubmit')]
    #[AsCallback(table: 'tl_news_archive', target: 'config.ondelete')]
    #[AsCallback(table: 'tl_calendar', target: 'config.onsubmit')]
    #[AsCallback(table: 'tl_calendar', target: 'config.ondelete')]
    public function onArchiveChange(DataContainer $dc): void
    {
        $this->cacheInvalidator->invalidate();
    }
}
