<?php

namespace Pdir\ContaoLlmsBundle\Tests\EventListener;

use Contao\ContentModel;
use Contao\FrontendTemplate;
use Pdir\ContaoLlmsBundle\EventListener\LlmsExcludeListener;
use Pdir\ContaoLlmsBundle\Tests\Fixtures\ModelFactoryTrait;
use PHPUnit\Framework\TestCase;

class LlmsExcludeListenerTest extends TestCase
{
    use ModelFactoryTrait;

    protected function tearDown(): void
    {
        unset($GLOBALS['TL_DCA']);

        parent::tearDown();
    }

    public function testWrapsExcludedElements(): void
    {
        $listener = new LlmsExcludeListener();

        $this->assertSame('<!-- llms::stop --><div>Logos</div><!-- llms::continue -->', $listener->markExcludedElement($this->createContentModel(true), '<div>Logos</div>'));
    }

    public function testKeepsOtherElements(): void
    {
        $listener = new LlmsExcludeListener();

        $this->assertSame('<div>Text</div>', $listener->markExcludedElement($this->createContentModel(false), '<div>Text</div>'));
        $this->assertSame('', $listener->markExcludedElement($this->createContentModel(true), ''));
    }

    public function testWrapsExcludedArticles(): void
    {
        $listener = new LlmsExcludeListener();

        $this->assertSame('<!-- llms::stop --><div class="mod_article">A</div><!-- llms::continue -->', $listener->markExcludedArticle('<div class="mod_article">A</div>', 'mod_article', $this->createTemplate(['type' => 'article', 'llmsExclude' => true])));
        $this->assertSame('<!-- llms::stop -->B<!-- llms::continue -->', $listener->markExcludedArticle('B', 'mod_article_wide', $this->createTemplate(['type' => 'article', 'llmsExclude' => true])));
    }

    public function testKeepsOtherTemplates(): void
    {
        $listener = new LlmsExcludeListener();

        $this->assertSame('A', $listener->markExcludedArticle('A', 'mod_article', $this->createTemplate(['type' => 'article', 'llmsExclude' => false])));
        $this->assertSame('B', $listener->markExcludedArticle('B', 'mod_navigation', $this->createTemplate(['type' => 'navigation', 'llmsExclude' => true])));
        $this->assertSame('C', $listener->markExcludedArticle('C', 'mod_article', null));
    }

    public function testAddsTheFieldToAllPalettes(): void
    {
        $GLOBALS['TL_DCA']['tl_content'] = [
            'palettes' => [
                '__selector__' => ['type'],
                'text' => '{type_legend},type,headline;{text_legend},text;{expert_legend:hide},cssID;{invisible_legend:hide},invisible',
                'creuva_logos' => '{type_legend},type;{logos_legend},multiSRC',
            ],
            'fields' => ['llmsExclude' => []],
        ];

        $GLOBALS['TL_DCA']['tl_article'] = [
            'palettes' => [
                '__selector__' => ['protected'],
                'default' => '{title_legend},title,alias;{expert_legend:hide},cssID;{publish_legend},published',
            ],
            'fields' => ['llmsExclude' => []],
        ];

        $listener = new LlmsExcludeListener();
        $listener->addToPalettes('tl_content');
        $listener->addToPalettes('tl_article');

        $palettes = $GLOBALS['TL_DCA']['tl_content']['palettes'];

        $this->assertSame(['type'], $palettes['__selector__']);
        $this->assertStringContainsString('{llms_legend:hide},llmsExclude;{expert_legend:hide}', $palettes['text']);
        $this->assertStringContainsString('{llms_legend:hide},llmsExclude', $palettes['creuva_logos']);
        $this->assertStringContainsString('{llms_legend:hide},llmsExclude;{expert_legend:hide}', $GLOBALS['TL_DCA']['tl_article']['palettes']['default']);
    }

    private function createContentModel(bool $exclude): ContentModel
    {
        return $this->createModel(ContentModel::class, ['id' => 1, 'llmsExclude' => $exclude]);
    }

    private function createTemplate(array $data): FrontendTemplate
    {
        $template = (new \ReflectionClass(FrontendTemplate::class))->newInstanceWithoutConstructor();
        $template->setData($data);

        return $template;
    }
}
