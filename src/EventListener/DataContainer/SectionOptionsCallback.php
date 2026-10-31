<?php

namespace Pdir\ContaoLlmsBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Doctrine\DBAL\Connection;

/**
 * Returns the LLMs sections already in use as suggestions for the page field.
 */
#[AsCallback(table: 'tl_page', target: 'fields.llmsSection.options')]
class SectionOptionsCallback
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return list<string>
     */
    public function __invoke(): array
    {
        $sections = $this->connection->fetchFirstColumn("SELECT DISTINCT llmsSection FROM tl_page WHERE llmsSection != ''");
        $sections = array_map(static fn (mixed $section): string => trim((string) $section), $sections);
        $sections = array_unique(array_filter([...$sections, 'Optional']));

        natcasesort($sections);

        return array_values($sections);
    }
}
