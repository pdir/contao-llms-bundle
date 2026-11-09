<?php

declare(strict_types=1);

// Added to all palettes in LlmsExcludeListener, so palettes of other
// bundles are covered as well
$GLOBALS['TL_DCA']['tl_content']['fields']['llmsExclude'] = [
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 m12'],
    'sql' => ['type' => 'boolean', 'default' => false],
];
