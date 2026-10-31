<?php

namespace Pdir\ContaoLlmsBundle\Cache;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * Determines the time of the last change of the llms.txt and llms-full.txt.
 *
 * The timestamps of pages, news and events do not cover every change (e.g. a
 * deleted page, an expired event or a changed module on a page). Therefore a
 * hash of every generated file is stored in tl_llms_content, and the time is
 * only updated if the content differs from the last one.
 */
class LlmsLastModified
{
    public const SOURCES = ['llms.txt', 'llms-full.txt'];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param string $file    The file name, e.g. "llms.txt"
     * @param string $variant Distinguishes the variants of a file, e.g. the URL and the member groups
     */
    public function get(string $file, string $variant, string $content): int
    {
        $sourceId = crc32($variant);
        $hash = hash('xxh128', $content);

        $row = $this->connection->fetchAssociative('SELECT tstamp, url, content FROM tl_llms_content WHERE source = ? AND sourceId = ?', [$file, $sourceId]);

        if (false !== $row && $row['url'] === $variant && $row['content'] === $hash) {
            return (int) $row['tstamp'];
        }

        $now = time();

        try {
            $this->connection->transactional(static function (Connection $connection) use ($file, $sourceId, $variant, $hash, $now): void {
                $connection->delete('tl_llms_content', ['source' => $file, 'sourceId' => $sourceId]);
                $connection->insert('tl_llms_content', ['tstamp' => $now, 'source' => $file, 'sourceId' => $sourceId, 'url' => $variant, 'content' => $hash]);
            });
        } catch (UniqueConstraintViolationException) {
            // Stored by a parallel request in the meantime
        }

        return $now;
    }
}
