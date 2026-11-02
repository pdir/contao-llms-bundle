<?php

$GLOBALS['TL_LANG']['tl_page']['llms_legend'] = 'LLMs settings';

$GLOBALS['TL_LANG']['tl_page']['llms'] = ['Enable llms.txt', 'Here you can enable the llms.txt route.'];
$GLOBALS['TL_LANG']['tl_page']['llmsFull'] = ['Enable llms-full.txt', 'Here you can enable the llms-full.txt route.'];
$GLOBALS['TL_LANG']['tl_page']['llmsTitle'] = ['LLMs title', 'Here you can define a custom llms.txt title.'];
$GLOBALS['TL_LANG']['tl_page']['llmsDescription'] = ['LLMs description', 'Here you can define a custom llms.txt description.'];
$GLOBALS['TL_LANG']['tl_page']['llmsRootDescription'] = ['LLMs description', 'Short description of the website below the heading of the LLMs files. The first paragraph is output as blockquote (>). Everything after the first empty line appears as regular Markdown, e.g. a list of key facts; indentation is preserved.'];
$GLOBALS['TL_LANG']['tl_page']['llmsTxt'] = ['Custom llms.txt instructions', 'Here you can add custom instructions for the llms.txt. These may be extended automatically by extensions.'];
$GLOBALS['TL_LANG']['tl_page']['llmsFullTxt'] = ['Custom llms-full.txt instructions', 'Here you can add custom instructions for the llms-full.txt. These may be extended automatically by extensions.'];
$GLOBALS['TL_LANG']['tl_page']['llmsEmail'] = ['LLMs e-mail address', 'Here you can enter the public e-mail address for the contact section.'];
$GLOBALS['TL_LANG']['tl_page']['llmsPhone'] = ['LLMs phone', 'Here you can enter the phone number for the contact section.'];
$GLOBALS['TL_LANG']['tl_page']['llmsSection'] = ['LLMs section', 'Section of the llms.txt in which the page appears, e.g. "Themes". Sections already in use are suggested. Inherited by subpages. Pages in the "Optional" section may be skipped by LLMs (e.g. secondary pages).'];
$GLOBALS['TL_LANG']['tl_page']['llmsImages'] = ['LLMs images', 'Images that are output in the llms-full.txt below the page description. Images of the page content are always removed. The alternative text of the file is used as image description, otherwise the caption or the title.'];
$GLOBALS['TL_LANG']['tl_page']['llmsGuidelines'] = ['Output usage guidelines', 'Outputs usage and attribution guidelines at the end of the LLMs files.'];
$GLOBALS['TL_LANG']['tl_page']['llmsSitemap'] = ['Link sitemap', 'Links the sitemap.xml in the "Optional" section of the llms.txt.'];
$GLOBALS['TL_LANG']['tl_page']['llmsDescribedBy'] = ['Link llms.txt on all pages', 'Points to the llms.txt from every page with rel="describedby", in the HTML head and in the HTTP Link header. This helps crawlers find the file, with URL prefixes the language-specific one (e.g. /de/llms.txt).'];
$GLOBALS['TL_LANG']['tl_page']['llmsExcludeNews'] = ['Exclude news', 'Does not include the news of the news bundle in the LLMs files.'];
$GLOBALS['TL_LANG']['tl_page']['llmsExcludeEvents'] = ['Exclude events', 'Does not include the events of the calendar bundle in the LLMs files.'];
$GLOBALS['TL_LANG']['tl_page']['llmsLegalPages'] = ['Legal pages', 'Pages such as imprint, privacy policy and accessibility statement. They are linked in the "Legal" section before the contact details and do not appear in other sections. Without a selection, the section is omitted.'];
$GLOBALS['TL_LANG']['tl_page']['llmsHide'] = ['Hide in LLMs files', 'This page will not be shown in the LLMs files.'];
$GLOBALS['TL_LANG']['tl_page']['llmsFullDescriptionOverride'] = ['Override LLMs page description manually', 'If enabled, you can define a custom page description for LLMs.'];
$GLOBALS['TL_LANG']['tl_page']['llmsFullDescription'] = ['LLMs page description', 'Automatically generated if manual override is not enabled. Contains LLMs instructions extracted from the rendered HTML.'];
$GLOBALS['TL_LANG']['tl_page']['llmsLastModified'] = ['LLMs – Last modified', 'Automatically updated when page content changes.'];
