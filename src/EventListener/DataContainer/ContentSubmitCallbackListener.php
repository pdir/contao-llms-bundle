<?php

namespace Pdir\ContaoLlmsBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Pdir\ContaoLlmsBundle\Cache\LlmsCacheInvalidator;

/**
 * Updates tl_page.llmsLastModified whenever content of a regular page changes
 * and invalidates the cached LLMs files.
 */
class ContentSubmitCallbackListener
{
    private Connection $db;

    public function __construct(Connection $db, private readonly LlmsCacheInvalidator $cacheInvalidator)
    {
        $this->db = $db;
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_content', target: 'config.onsubmit')]
    #[AsCallback(table: 'tl_content', target: 'config.ondelete')]
    #[AsCallback(table: 'tl_content', target: 'config.oncut')]
    public function onContentChange(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $content = $this->db->fetchAssociative('SELECT pid, ptable FROM tl_content WHERE id = ?', [$dc->id]);

        // Content elements of news and events (optional bundles)
        if (false !== $content && \in_array($content['ptable'], ['tl_news', 'tl_calendar_events'], true)) {
            $this->cacheInvalidator->discard($content['ptable'], (int) $content['pid']);

            return;
        }

        // An empty ptable means tl_article (legacy records)
        if (false === $content || !\in_array($content['ptable'], ['', 'tl_article'], true)) {
            return;
        }

        $this->updatePageFromArticle((int) $content['pid']);
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_article', target: 'config.onsubmit')]
    #[AsCallback(table: 'tl_article', target: 'config.ondelete')]
    #[AsCallback(table: 'tl_article', target: 'config.oncut')]
    public function onArticleChange(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $this->updatePageFromArticle((int) $dc->id);
    }

    /**
     * @throws Exception
     */
    private function updatePageFromArticle(int $articleId): void
    {
        $this->db->executeStatement(
            "UPDATE tl_page p INNER JOIN tl_article a ON a.pid = p.id SET p.llmsLastModified = ? WHERE a.id = ? AND p.type = 'regular'",
            [time(), $articleId],
        );

        $this->cacheInvalidator->invalidate();
    }
}
