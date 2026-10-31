<?php

namespace Pdir\ContaoLlmsBundle\Cache;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use FOS\HttpCache\CacheInvalidator;

/**
 * Invalidates the cached llms.txt and llms-full.txt responses.
 *
 * Contao only invalidates the tags of the changed record and its parent, which
 * does not cover all changes that affect the LLMs files (e.g. a new subpage of
 * an excluded page). Therefore the responses are tagged with a dedicated tag.
 */
class LlmsCacheInvalidator
{
    public const TAG = 'pdir.llms';

    public function __construct(
        private readonly Connection $connection,
        private readonly CacheInvalidator|null $cacheInvalidator = null,
    ) {
    }

    public function invalidate(): void
    {
        $this->cacheInvalidator?->invalidateTags([self::TAG]);
    }

    /**
     * Discards the stored content of a record (see tl_llms_content), so it is
     * generated again, and invalidates the cached responses.
     */
    public function discard(string $source, int $sourceId): void
    {
        $this->connection->delete('tl_llms_content', ['source' => $source, 'sourceId' => $sourceId]);
        $this->invalidate();
    }

    /**
     * Discards all generated content (pages, news, events), so it is generated
     * again on the next request, and invalidates the cached responses. Manually
     * overridden page descriptions are kept.
     *
     * Used by the console command and the purge job of the system maintenance.
     *
     * @return array{pages: int, records: int}
     */
    public function purge(): array
    {
        $result = $this->connection->transactional(static fn (Connection $connection): array => [
            'pages' => (int) $connection->executeStatement(
                "UPDATE tl_page SET llmsFullDescription = '', llmsFullDescriptionGenerated = 0 WHERE llmsFullDescriptionOverride = 0 AND (llmsFullDescriptionGenerated > 0 OR llmsFullDescription != '')",
            ),
            // The change times of the files are kept (see LlmsLastModified)
            'records' => (int) $connection->executeStatement(
                'DELETE FROM tl_llms_content WHERE source NOT IN (?)',
                [LlmsLastModified::SOURCES],
                [ArrayParameterType::STRING],
            ),
        ]);

        $this->invalidate();

        return $result;
    }
}
