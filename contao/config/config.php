<?php

use Pdir\ContaoLlmsBundle\Cache\LlmsCacheInvalidator;
use Pdir\ContaoLlmsBundle\Widget\SectionField;

// System maintenance: discard the generated content of the LLMs files
$GLOBALS['TL_PURGE']['tables']['llms'] = [
    'callback' => [LlmsCacheInvalidator::class, 'purge'],
    'affected' => ['tl_llms_content'],
];

// Text field with suggestions of the LLMs sections already in use
$GLOBALS['BE_FFL']['llmsSection'] = SectionField::class;
