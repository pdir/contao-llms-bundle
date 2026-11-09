<?php

namespace Pdir\ContaoLlmsBundle\EventListener;

use Contao\ContentElement;
use Contao\ContentModel;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\FrontendTemplate;
use Contao\Model;
use Pdir\ContaoLlmsBundle\Service\LlmsGenerator;

/**
 * Articles and content elements can be excluded from the LLMs files. Their
 * output is wrapped in <!-- llms::stop --> and <!-- llms::continue -->
 * comments, and the LlmsGenerator removes everything between them.
 *
 * @internal
 */
class LlmsExcludeListener
{
    private const TABLES = ['tl_article', 'tl_content'];

    /**
     * Adds the field to every palette. The hook runs after all DCA files are
     * loaded, so palettes of other bundles are covered as well.
     */
    #[AsHook('loadDataContainer', priority: -256)]
    public function addToPalettes(string $table): void
    {
        if (!\in_array($table, self::TABLES, true) || !isset($GLOBALS['TL_DCA'][$table]['fields']['llmsExclude'])) {
            return;
        }

        $manipulator = PaletteManipulator::create()
            ->addLegend('llms_legend', 'expert_legend', PaletteManipulator::POSITION_BEFORE, true)
            ->addField('llmsExclude', 'llms_legend', PaletteManipulator::POSITION_APPEND);

        foreach ($GLOBALS['TL_DCA'][$table]['palettes'] ?? [] as $name => $palette) {
            if ('__selector__' === $name || !\is_string($palette) || str_contains($palette, 'llmsExclude')) {
                continue;
            }

            $manipulator->applyToPalette($name, $table);
        }
    }

    #[AsHook('getContentElement')]
    public function markExcludedElement(Model $model, string $buffer, ContentElement|null $element = null): string
    {
        if (!$model instanceof ContentModel || !$model->llmsExclude) {
            return $buffer;
        }

        return $this->wrap($buffer);
    }

    /**
     * Articles have no hook with their output, but the article template
     * (mod_article or a custom mod_article_*) contains the article data.
     */
    #[AsHook('parseFrontendTemplate')]
    public function markExcludedArticle(string $buffer, string $templateName, FrontendTemplate|null $template = null): string
    {
        if (!str_starts_with($templateName, 'mod_article') || 'article' !== $template?->type || !$template->llmsExclude) {
            return $buffer;
        }

        return $this->wrap($buffer);
    }

    private function wrap(string $buffer): string
    {
        if ('' === trim($buffer)) {
            return $buffer;
        }

        return LlmsGenerator::EXCLUDE_START.$buffer.LlmsGenerator::EXCLUDE_END;
    }
}
