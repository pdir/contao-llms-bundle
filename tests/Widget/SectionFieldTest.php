<?php

namespace Pdir\ContaoLlmsBundle\Tests\Widget;

use Pdir\ContaoLlmsBundle\Widget\SectionField;
use PHPUnit\Framework\TestCase;

class SectionFieldTest extends TestCase
{
    public function testSuggestsTheOptions(): void
    {
        $html = $this->createWidget([['value' => 'Themes', 'label' => 'Themes'], ['value' => 'A & B', 'label' => 'A & B']])->generate();

        $this->assertStringContainsString(' list="list_llmsSection"', $html);
        $this->assertStringContainsString(' autocomplete="off"', $html);
        $this->assertStringContainsString('<datalist id="list_llmsSection"><option value="Themes"></option><option value="A &amp; B"></option></datalist>', $html);
    }

    public function testRendersAPlainTextFieldWithoutOptions(): void
    {
        $html = $this->createWidget([])->generate();

        $this->assertStringContainsString('id="ctrl_llmsSection"', $html);
        $this->assertStringNotContainsString('list=', $html);
        $this->assertStringNotContainsString('<datalist', $html);
    }

    private function createWidget(array $options): SectionField
    {
        $widget = (new \ReflectionClass(SectionField::class))->newInstanceWithoutConstructor();
        $widget->id = 'llmsSection';
        $widget->name = 'llmsSection';
        $widget->value = 'Themes';
        $widget->options = $options;

        return $widget;
    }
}
