<?php
declare(strict_types=1);
/**
 * Class for a set of entries for translation and their associated headers
 *
 * @version $Id: translations.php 718 2012-10-31 00:32:02Z nbachiyski $
 * @package pomo
 * @subpackage translations
 */

require_once __DIR__ . '/entry.php';

if (!class_exists(Translations::class)) :
class Translations
{
    public array $entries = [];
    public array $headers = [];

    /**
     * Add entry to the PO structure
     *
     * @param Translation_Entry|array $entry
     * @return bool true on success, false if the entry doesn't have a key
     */
    public function add_entry(Translation_Entry|array $entry): bool
    {
        if (is_array($entry)) {
            $entry = new Translation_Entry($entry);
        }

        $key = $entry->key();
        if ($key === false) {
            return false;
        }

        $this->entries[$key] = $entry;

        return true;
    }

    public function add_entry_or_merge(Translation_Entry|array $entry): bool
    {
        if (is_array($entry)) {
            $entry = new Translation_Entry($entry);
        }

        $key = $entry->key();
        if ($key === false) {
            return false;
        }

        if (isset($this->entries[$key])) {
            $this->entries[$key]->merge_with($entry);
        } else {
            $this->entries[$key] = $entry;
        }

        return true;
    }

    /**
     * Sets $header PO header to $value
     *
     * If the header already exists, it will be overwritten
     *
     * TODO: this should be out of this class, it is gettext specific
     *
     * @param string $header header name, without trailing :
     * @param string $value header value, without trailing \n
     */
    public function set_header(string $header, string $value): void
    {
        $this->headers[$header] = $value;
    }

    public function set_headers(array $headers): void
    {
        foreach ($headers as $header => $value) {
            $this->set_header((string) $header, (string) $value);
        }
    }

    public function get_header(string $header): string|false
    {
        return $this->headers[$header] ?? false;
    }

    public function translate_entry(Translation_Entry $entry): Translation_Entry|false
    {
        $key = $entry->key();
        if ($key === false) {
            return false;
        }

        return $this->entries[$key] ?? false;
    }

    public function translate(string $singular, ?string $context = null): string
    {
        $entry       = new Translation_Entry(['singular' => $singular, 'context' => $context]);
        $translated  = $this->translate_entry($entry);

        return ($translated && !empty($translated->translations))
            ? (string) $translated->translations[0]
            : $singular;
    }

    /**
     * Given the number of items, returns the 0-based index of the plural form to use
     *
     * Here, in the base Translations class, the common logic for English is implemented:
     *  0 if there is one element, 1 otherwise
     *
     * This function should be overrided by the sub-classes. For example MO/PO can derive the logic
     * from their headers.
     *
     * @param int $count number of items
     */
    public function select_plural_form(int $count): int
    {
        return $count === 1 ? 0 : 1;
    }

    public function get_plural_forms_count(): int
    {
        return 2;
    }

    public function translate_plural(string $singular, string $plural, int $count, ?string $context = null): string
    {
        $entry               = new Translation_Entry(['singular' => $singular, 'plural' => $plural, 'context' => $context]);
        $translated          = $this->translate_entry($entry);
        $index               = $this->select_plural_form($count);
        $total_plural_forms  = $this->get_plural_forms_count();

        if (
            $translated &&
            $index >= 0 &&
            $index < $total_plural_forms &&
            is_array($translated->translations) &&
            isset($translated->translations[$index])
        ) {
            return (string) $translated->translations[$index];
        }

        return $count === 1 ? $singular : $plural;
    }

    /**
     * Merge $other in the current object.
     *
     * @param Translations $other Another Translation object, whose translations will be merged in this one
     * @return void
     **/
    public function merge_with(Translations $other): void
    {
        foreach ($other->entries as $entry) {
            if ($entry instanceof Translation_Entry) {
                $this->entries[$entry->key()] = $entry;
            }
        }
    }

    public function merge_originals_with(Translations $other): void
    {
        foreach ($other->entries as $entry) {
            if (!($entry instanceof Translation_Entry)) {
                continue;
            }

            $key = $entry->key();

            if ($key === false) {
                continue;
            }

            if (!isset($this->entries[$key])) {
                $this->entries[$key] = $entry;
            } else {
                $this->entries[$key]->merge_with($entry);
            }
        }
    }
}

class Gettext_Translations extends Translations
{
    protected ?callable $_gettext_select_plural_form = null;
    protected int $_nplurals = 0;

    /**
     * The gettext implementation of select_plural_form.
     *
     * It lives in this class, because there are more than one descendant, which will use it and
     * they can't share it effectively.
     *
     */
    public function gettext_select_plural_form(int $count): int
    {
        if ($this->_gettext_select_plural_form === null) {
            [$nplurals, $expression] = $this->nplurals_and_expression_from_header($this->get_header('Plural-Forms'));
            $this->_nplurals                 = $nplurals;
            $this->_gettext_select_plural_form = $this->make_plural_form_function($nplurals, $expression);
        }

        $callable = $this->_gettext_select_plural_form;

        return $callable($count);
    }

    public function nplurals_and_expression_from_header(string|false $header): array
    {
        if (is_string($header) && preg_match('/^\s*nplurals\s*=\s*(\d+)\s*;\s+plural\s*=\s*(.+)$/', $header, $matches)) {
            $nplurals   = (int) $matches[1];
            $expression = trim($this->parenthesize_plural_exression($matches[2]));

            return [$nplurals, $expression];
        }

        return [2, 'n != 1'];
    }

    /**
     * Makes a function, which will return the right translation index, according to the
     * plural forms header
     */
    public function make_plural_form_function(int $nplurals, string $expression): callable
    {
        $expression   = str_replace('n', '$n', $expression);
        $functionBody = sprintf(
            <<<'PHP'
return static function (int $n) : int {
    $index = (int)(%s);
    return ($index < %d) ? $index : %d - 1;
};
PHP,
            $expression,
            $nplurals,
            $nplurals
        );

        /** @var callable $callable */
        $callable = eval($functionBody);

        return $callable;
    }

    /**
     * Adds parentheses to the inner parts of ternary operators in
     * plural expressions, because PHP evaluates ternary operators from left to right
     *
     * @param string $expression the expression without parentheses
     * @return string the expression with parentheses added
     */
    public function parenthesize_plural_exression(string $expression): string
    {
        $expression .= ';';
        $result = '';
        $depth  = 0;

        $length = strlen($expression);
        for ($i = 0; $i < $length; ++$i) {
            $char = $expression[$i];
            switch ($char) {
                case '?':
                    $result .= ' ? (';
                    $depth++;
                    break;
                case ':':
                    $result .= ') : (';
                    break;
                case ';':
                    $result .= str_repeat(')', $depth) . ';';
                    $depth = 0;
                    break;
                default:
                    $result .= $char;
            }
        }

        return rtrim($result, ';');
    }

    public function make_headers(string $translation): array
    {
        $headers     = [];
        $translation = str_replace('\n', "\n", $translation);
        $lines       = explode("\n", $translation);

        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            if (!isset($parts[1])) {
                continue;
            }
            $headers[trim($parts[0])] = trim($parts[1]);
        }

        return $headers;
    }

    public function set_header(string $header, string $value): void
    {
        parent::set_header($header, $value);
        if ($header === 'Plural-Forms') {
            [$nplurals, $expression]          = $this->nplurals_and_expression_from_header($this->get_header('Plural-Forms'));
            $this->_nplurals                  = $nplurals;
            $this->_gettext_select_plural_form = $this->make_plural_form_function($nplurals, $expression);
        }
    }
}
endif;

if (!class_exists(NOOP_Translations::class)) :
/**
 * Provides the same interface as Translations, but doesn't do anything
 */
class NOOP_Translations
{
    public array $entries = [];
    public array $headers = [];

    public function add_entry(Translation_Entry|array $entry): bool
    {
        return true;
    }

    public function set_header(string $header, string $value): void
    {
    }

    public function set_headers(array $headers): void
    {
    }

    public function get_header(string $header): false
    {
        return false;
    }

    public function translate_entry(Translation_Entry $entry): false
    {
        return false;
    }

    public function translate(string $singular, ?string $context = null): string
    {
        return $singular;
    }

    public function select_plural_form(int $count): int
    {
        return $count === 1 ? 0 : 1;
    }

    public function get_plural_forms_count(): int
    {
        return 2;
    }

    public function translate_plural(string $singular, string $plural, int $count, ?string $context = null): string
    {
        return $count === 1 ? $singular : $plural;
    }

    public function merge_with(Translations $other): void
    {
    }
}
endif;
?>