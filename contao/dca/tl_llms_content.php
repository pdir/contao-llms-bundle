<?php

declare(strict_types=1);

use Contao\DC_Table;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;

/*
 * Stores the generated Markdown of records that have no fields of their own
 * for it (e.g. news) and the hash of the generated files with the time of
 * their last change (see LlmsLastModified). Not editable in the back end.
 */
$GLOBALS['TL_DCA']['tl_llms_content'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'closed' => true,
        'notEditable' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'source,sourceId' => 'unique',
            ],
        ],
    ],

    'fields' => [
        'id' => [
            'sql' => ['type' => 'integer', 'unsigned' => true, 'autoincrement' => true],
        ],
        // Time of generation
        'tstamp' => [
            'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
        ],
        // Table of the source record, e.g. tl_news, or the file name, e.g. llms.txt
        'source' => [
            'sql' => ['type' => 'string', 'length' => 64, 'default' => ''],
        ],
        'sourceId' => [
            'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
        ],
        'url' => [
            'sql' => ['type' => 'text', 'notnull' => false],
        ],
        'content' => [
            'sql' => ['type' => 'text', 'length' => AbstractMySQLPlatform::LENGTH_LIMIT_MEDIUMTEXT, 'notnull' => false],
        ],
    ],
];
