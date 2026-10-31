<?php

namespace Pdir\ContaoLlmsBundle\Widget;

use Contao\StringUtil;
use Contao\TextField;

/**
 * Text field that suggests the existing LLMs sections, so that editors do not
 * create duplicate sections by typos (e.g. "Theme" next to "Themes"). Any other
 * value can still be entered.
 */
class SectionField extends TextField
{
    public function generate(): string
    {
        $options = array_filter(array_map(
            static fn (mixed $option): string => (string) (\is_array($option) ? ($option['value'] ?? '') : $option),
            (array) $this->options,
        ));

        if ([] === $options) {
            return parent::generate();
        }

        $listId = 'list_'.$this->strId;
        $this->arrAttributes['list'] = $listId;
        $this->arrAttributes['autocomplete'] = 'off';

        $datalist = '';

        foreach ($options as $option) {
            $datalist .= \sprintf('<option value="%s"></option>', StringUtil::specialcharsAttribute($option));
        }

        return parent::generate().\sprintf('<datalist id="%s">%s</datalist>', $listId, $datalist);
    }
}
