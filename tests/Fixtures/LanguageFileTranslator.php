<?php

namespace Pdir\ContaoLlmsBundle\Tests\Fixtures;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Translates "llms.*" keys with the language files of the bundle, formatted
 * like the Contao translator does (vsprintf). Falls back to English.
 */
class LanguageFileTranslator implements TranslatorInterface
{
    private array $messages = [];

    public function trans(string $id, array $parameters = [], string|null $domain = null, string|null $locale = null): string
    {
        $language = substr((string) $locale, 0, 2);
        $messages = $this->load(is_file($this->getFile($language)) ? $language : 'en');
        $message = $messages[substr($id, \strlen('llms.'))] ?? $id;

        return $parameters ? vsprintf($message, $parameters) : $message;
    }

    public function getLocale(): string
    {
        return 'en';
    }

    private function load(string $language): array
    {
        if (!isset($this->messages[$language])) {
            $GLOBALS['TL_LANG'] = [];
            include $this->getFile($language);
            $this->messages[$language] = $GLOBALS['TL_LANG']['llms'];
            unset($GLOBALS['TL_LANG']);
        }

        return $this->messages[$language];
    }

    private function getFile(string $language): string
    {
        return __DIR__.'/../../contao/languages/'.$language.'/llms.php';
    }
}
