<?php

declare(strict_types=1);

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\DataContainer;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;

PaletteManipulator::create()
    ->addLegend('llms_legend', 'website_legend', PaletteManipulator::POSITION_AFTER)
    ->addField('llmsTitle', 'llms_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('llmsEmail', 'llms_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('llmsPhone', 'llms_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('llmsGuidelines', 'llms_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('llmsLegalPages', 'llms_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('llmsDescription', 'llms_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('llms', 'llms_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('llmsTxt', 'llms_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('llmsFull', 'llms_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('llmsFullTxt', 'llms_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('llmsSitemap', 'llms_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('llmsDescribedBy', 'llms_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('root', 'tl_page')
    ->applyToPalette('rootfallback', 'tl_page');

// The news and calendar bundles are optional
foreach (['llmsExcludeNews' => 'Contao\\NewsBundle\\ContaoNewsBundle', 'llmsExcludeEvents' => 'Contao\\CalendarBundle\\ContaoCalendarBundle'] as $field => $bundle) {
    if (class_exists($bundle)) {
        PaletteManipulator::create()
            ->addField($field, 'llmsLegalPages', PaletteManipulator::POSITION_BEFORE)
            ->applyToPalette('root', 'tl_page')
            ->applyToPalette('rootfallback', 'tl_page');
    }
}

PaletteManipulator::create()
    ->addLegend('llms_legend', 'meta_legend', PaletteManipulator::POSITION_AFTER)
    ->addField('llmsTitle', 'llms_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('llmsDescription', 'llms_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('llmsSection', 'llms_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('llmsHide', 'llms_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('llmsImages', 'llms_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('llmsFullDescriptionOverride', 'llms_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('llmsFullDescription', 'llms_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('llmsLastModified', 'llms_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('regular', 'tl_page');

$GLOBALS['TL_DCA']['tl_page']['fields'] += [
    'llms' => [
        'inputType' => 'checkbox',
        'eval' => ['tl_class' => 'w50 m12'],
        'sql' => ['type' => 'boolean', 'default' => false]
    ],

    'llmsFull' => [
        'inputType' => 'checkbox',
        'eval' => ['tl_class' => 'w50 m12 clr'],
        'sql' => ['type' => 'boolean', 'default' => false]
    ],

    // Link to the Contao sitemap in the section "Optional" of the llms.txt
    'llmsSitemap' => [
        'inputType' => 'checkbox',
        'eval' => ['tl_class' => 'w50 m12 clr'],
        'sql' => ['type' => 'boolean', 'default' => false]
    ],

    // Link the llms.txt from every page with rel="describedby" (HTML and HTTP header)
    'llmsDescribedBy' => [
        'inputType' => 'checkbox',
        'eval' => ['tl_class' => 'w50 m12'],
        'sql' => ['type' => 'boolean', 'default' => true]
    ],

    'llmsTxt' => [
        'inputType' => 'textarea',
        'eval' => ['doNotCopy' => true, 'decodeEntities' => true, 'tl_class' => 'clr'],
        'sql' => ['type' => 'text', 'length' => AbstractMySQLPlatform::LENGTH_LIMIT_TEXT, 'notnull' => false]
    ],

    'llmsFullTxt' => [
        'inputType' => 'textarea',
        'eval' => ['doNotCopy' => true, 'decodeEntities' => true, 'tl_class' => 'clr'],
        'sql' => ['type' => 'text', 'length' => AbstractMySQLPlatform::LENGTH_LIMIT_TEXT, 'notnull' => false]
    ],

    'llmsTitle' => [
        'inputType' => 'text',
        'eval' => ['maxlength' => 255, 'basicEntities' => true, 'tl_class' => 'w50'],
        'sql' => ['type' => 'string', 'length' => 255, 'default' => '']
    ],

    'llmsDescription' => [
        'inputType' => 'textarea',
        'eval' => ['style' => 'height:60px', 'decodeEntities' => true, 'tl_class' => 'clr'],
        'sql' => ['type' => 'text', 'length' => AbstractMySQLPlatform::LENGTH_LIMIT_TEXT, 'notnull' => false]
    ],

    'llmsEmail' => [
        'inputType' => 'text',
        'eval' => ['maxlength' => 255, 'rgxp' => 'email', 'decodeEntities' => true, 'tl_class' => 'w50'],
        'sql' => ['type' => 'string', 'length' => 255, 'default' => '']
    ],

    'llmsPhone' => [
        'inputType' => 'text',
        'eval' => ['maxlength' => 255, 'basicEntities' => true, 'tl_class' => 'w50'],
        'sql' => ['type' => 'string', 'length' => 255, 'default' => '']
    ],

    // Text field with suggestions of the sections already in use (see SectionOptionsCallback)
    'llmsSection' => [
        'inputType' => 'llmsSection',
        'eval' => ['maxlength' => 64, 'decodeEntities' => true, 'tl_class' => 'w50'],
        'sql' => ['type' => 'string', 'length' => 64, 'default' => '']
    ],

    // Images of the content are removed, only these are added to the llms-full.txt
    'llmsImages' => [
        'inputType' => 'fileTree',
        'eval' => ['multiple' => true, 'fieldType' => 'checkbox', 'filesOnly' => true, 'isSortable' => true, 'isGallery' => true, 'extensions' => '%contao.image.valid_extensions%', 'tl_class' => 'clr'],
        'sql' => ['type' => 'blob', 'notnull' => false]
    ],

    'llmsGuidelines' => [
        'inputType' => 'checkbox',
        'eval' => ['tl_class' => 'w50 m12'],
        'sql' => ['type' => 'boolean', 'default' => true]
    ],

    // Imprint, privacy policy, accessibility statement etc. in the section "Legal"
    'llmsLegalPages' => [
        'inputType' => 'pageTree',
        'eval' => ['multiple' => true, 'fieldType' => 'checkbox', 'isSortable' => true, 'tl_class' => 'clr'],
        'sql' => ['type' => 'blob', 'notnull' => false]
    ],

    'llmsExcludeNews' => [
        'inputType' => 'checkbox',
        'eval' => ['tl_class' => 'w50 m12'],
        'sql' => ['type' => 'boolean', 'default' => false]
    ],

    'llmsExcludeEvents' => [
        'inputType' => 'checkbox',
        'eval' => ['tl_class' => 'w50 m12'],
        'sql' => ['type' => 'boolean', 'default' => false]
    ],

    'llmsHide' => [
        'inputType' => 'checkbox',
        'eval' => ['tl_class' => 'w50 m12'],
        'sql' => ['type' => 'boolean', 'default' => false]
    ],

    'llmsFullDescriptionOverride' => [
        'inputType' => 'checkbox',
        'eval' => ['tl_class' => 'w50 m12', 'submitOnChange'=>true],
        'sql' => ['type' => 'boolean', 'default' => false]
    ],

    'llmsFullDescription' => [
        'inputType' => 'textarea',
        'eval' => ['style' => 'height:60px', 'decodeEntities' => true, 'tl_class' => 'clr', 'readonly' => true, 'doNotCopy' => true],
        'sql' => ['type' => 'text', 'length' => AbstractMySQLPlatform::LENGTH_LIMIT_TEXT, 'notnull' => false]
    ],

    // Timestamp of the last automatic generation of llmsFullDescription
    'llmsFullDescriptionGenerated' => [
        'eval' => ['doNotCopy' => true],
        'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0]
    ],

    'llmsLastModified' => [
        'default' => time(),
        'flag' => DataContainer::SORT_MONTH_DESC,
        'inputType' => 'text',
        'eval' => ['rgxp'=>'datim', 'doNotCopy'=>true, 'tl_class'=>'w50', 'readonly' => true],
        'sql' => ['type' => 'integer', 'default'=>0]
    ],
];
