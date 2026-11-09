<?php

declare(strict_types=1);

// Added to all palettes in LlmsExcludeListener
$GLOBALS['TL_DCA']['tl_article']['fields']['llmsExclude'] = [
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 m12'],
    'sql' => ['type' => 'boolean', 'default' => false],
];
