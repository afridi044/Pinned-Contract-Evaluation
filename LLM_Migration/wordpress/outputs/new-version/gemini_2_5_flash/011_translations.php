<?php
/**
 * Class for a set of entries for translation and their associated headers
 *
 * @version $Id: translations.php 718 2012-10-31 00:32:02Z nbachiyski $
 * @package pomo
 * @subpackage translations
 */

require_once __DIR__ . '/entry.php';

if (!class_exists('Translations')) {
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
            if (false === $key) {
                return false;
            }
            $this->entries[$key] = $entry; // Objects are passed by value (which is a reference), no need for '&'
            return true;
        }

        /**
         * Add entry to the PO structure or merge if key exists
         *
         * @param Translation_Entry|array $entry
         * @return bool true on success, false if the entry doesn't have a key
         */
        public function add_entry_or_merge(Translation_Entry|array $entry): bool
        {
            if (is_array($entry)) {
                $entry = new Translation_Entry($entry);
            }
            $key = $entry->key();
            if (false === $key) {
                return false;
            }
            if (isset($this->entries[$key])) {
                $this->entries[$key]->merge_with($entry);
            } else {
                $this->entries[$key] = $entry; // Objects are passed by value (which is a reference), no need for '&'
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

        /**
         * Sets multiple headers
         *
         * @param array<string, string> $headers
         */
        public function set_headers(array $headers): void
        {
            foreach ($headers as $header => $value) {
                $this->set_header($header, $value);
            }
        }

        /**
         * Gets a header value
         *
         * @param string $header
         * @return string|false
         */
        public function get_header(string $header): string|false
        {
            return $this->headers[$header] ?? false;
        }

        /**
         * Translates an entry
         *
         * @param Translation_Entry $entry
         * @return Translation_Entry|false
         */
        public function translate_entry(Translation_Entry $entry): Translation_Entry|false
        {
            $key = $entry->key();
            return $this->entries[$key] ?? false;
        }

        /**
         * Translates a singular string
         *
         * @param string $singular
         * @param string|null $context
         * @return string
         */
        public function translate(string $singular, ?string $context = null): string
        {
            $entry = new Translation_Entry(['singular' => $singular, 'context' => $context]);
            $translated = $this->translate_entry($entry);
            return ($translated && !empty($translated->translations)) ? $translated->translations[0] : $singular;
        }

        /**
         * Given the number of items, returns the 0-based index of the plural form to use
         *
         * Here, in the base Translations class, the common logic for English is implemented:
         * 	0 if there is one element, 1 otherwise
         *
         * This function should be overrided by the sub-classes. For example MO/PO can derive the logic
         * from their headers.
         *
         * @param int $count number of items
         * @return int
         */
        public function select_plural_form(int $count): int
        {
            return 1 === $count ? 0 : 1;
        }

        /**
         * Returns the total number of plural forms
         *
         * @return int
         */
        public function get_plural_forms_count(): int
        {
            return 2;
        }

        /**
         * Translates a plural string
         *
         * @param string $singular
         * @param string $plural
         * @param int $count
         * @param string|null $context
         * @return string
         */
        public function translate_plural(string $singular, string $plural, int $count, ?string $context = null): string
        {
            $entry = new Translation_Entry(['singular' => $singular, 'plural' => $plural, 'context' => $context]);
            $translated = $this->translate_entry($entry);
            $index = $this->select_plural_form($count);
            $total_plural_forms = $this->get_plural_forms_count();
            if (
                $translated && 0 <= $index && $index < $total_plural_forms &&
                is_array($translated->translations) &&
                isset($translated->translations[$index])
            ) {
                return $translated->translations[$index];
            } else {
                return 1 === $count ? $singular : $plural;
            }
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
                $this->entries[$entry->key()] = $entry;
            }
        }

        /**
         * Merge originals from $other in the current object.
         *
         * @param Translations $other Another Translation object, whose translations will be merged in this one
         * @return void
         **/
        public function merge_originals_with(Translations $other): void
        {
            foreach ($other->entries as $entry) {
                if (!isset($this->entries[$entry->key()])) {
                    $this->entries[$entry->key()] = $entry;
                } else {
                    $this->entries[$entry->key()]->merge_with($entry);
                }
            }
        }
    }
}

if (!class_exists('Gettext_Translations')) {
    class Gettext_Translations extends Translations
    {
        protected ?callable $_gettext_select_plural_form = null;
        protected ?int $_nplurals = null;

        /**
         * The gettext implementation of select_plural_form.
         *
         * It lives in this class, because there are more than one descendand, which will use it and
         * they can't share it effectively.
         *
         * @param int $count
         * @return int
         */
        public function gettext_select_plural_form(int $count): int
        {
            if (null === $this->_gettext_select_plural_form) {
                [$nplurals, $expression] = $this->nplurals_and_expression_from_header($this->get_header('Plural-Forms'));
                $this->_nplurals = $nplurals;
                $this->_gettext_select_plural_form = $this->make_plural_form_function($nplurals, $expression);
            }
            // Direct call to closure is possible
            return ($this->_gettext_select_plural_form)($count);
        }

        /**
         * Parses plural forms header
         *
         * @param string|false $header
         * @return array{0: int, 1: string}
         */
        public function nplurals_and_expression_from_header(string|false $header): array
        {
            if (is_string($header) && preg_match('/^\s*nplurals\s*=\s*(\d+)\s*;\s+plural\s*=\s*(.+)$/', $header, $matches)) {
                $nplurals = (int)$matches[1];
                $expression = trim($this->parenthesize_plural_exression($matches[2]));
                return [$nplurals, $expression]; // Array destructuring for list()
            } else {
                return [2, 'n != 1'];
            }
        }

        /**
         * Makes a function, which will return the right translation index, according to the
         * plural forms header
         *
         * @param int $nplurals
         * @param string $expression
         * @return callable(int): int
         */
        public function make_plural_form_function(int $nplurals, string $expression): callable
        {
            $expression = str_replace('n', '$n', $expression);

            // WARNING: Using eval() is generally discouraged due to security risks if $expression comes from untrusted sources.
            // This is a direct functional replacement for create_function (removed in PHP 8.2) which also executed arbitrary code.
            // For a robust solution, consider parsing the plural expression into an AST or using a dedicated plural rules library.
            return function (int $n) use ($nplurals, $expression): int {
                // $expression already has 'n' replaced with '$n', e.g., "$n != 1"
                // eval() executes in the current scope, so $n (the closure parameter) is accessible.
                $index = eval("return (int)({$expression});");
                return ($index < $nplurals) ? $index : $nplurals - 1;
            };
        }

        /**
         * Adds parentheses to the inner parts of ternary operators in
         * plural expressions, because PHP evaluates ternary oerators from left to right
         *
         * @param string $expression the expression without parentheses
         * @return string the expression with parentheses added
         */
        public function parenthesize_plural_exression(string $expression): string
        {
            $expression .= ';';
            $res = '';
            $depth = 0;
            for ($i = 0; $i < strlen($expression); ++$i) {
                $char = $expression[$i];
                switch ($char) {
                    case '?':
                        $res .= ' ? (';
                        $depth++;
                        break;
                    case ':':
                        $res .= ') : (';
                        break;
                    case ';':
                        $res .= str_repeat(')', $depth) . ';';
                        $depth = 0;
                        break;
                    default:
                        $res .= $char;
                }
            }
            return rtrim($res, ';');
        }

        /**
         * Makes headers array from a translation string
         *
         * @param string $translation
         * @return array<string, string>
         */
        public function make_headers(string $translation): array
        {
            $headers = [];
            // sometimes \ns are used instead of real new lines
            $translation = str_replace('\n', "\n", $translation);
            $lines = explode("\n", $translation);
            foreach ($lines as $line) {
                $parts = explode(':', $line, 2);
                if (!isset($parts[1])) {
                    continue;
                }
                $headers[trim($parts[0])] = trim($parts[1]);
            }
            return $headers;
        }

        /**
         * Sets a header and updates plural form function if 'Plural-Forms' header is set
         *
         * @param string $header
         * @param string $value
         * @return void
         */
        public function set_header(string $header, string $value): void
        {
            parent::set_header($header, $value);
            if ('Plural-Forms' === $header) {
                [$nplurals, $expression] = $this->nplurals_and_expression_from_header($this->get_header('Plural-Forms'));
                $this->_nplurals = $nplurals;
                $this->_gettext_select_plural_form = $this->make_plural_form_function($nplurals, $expression);
            }
        }
    }
}

if (!class_exists('NOOP_Translations')) {
    /**
     * Provides the same interface as Translations, but doesn't do anything
     */
    class NOOP_Translations
    {
        public array $entries = [];
        public array $headers = [];

        /**
         * @param Translation_Entry|array $entry
         * @return bool
         */
        public function add_entry(Translation_Entry|array $entry): bool
        {
            return true;
        }

        /**
         * @param string $header
         * @param string $value
         * @return void
         */
        public function set_header(string $header, string $value): void
        {
        }

        /**
         * @param array<string, string> $headers
         * @return void
         */
        public function set_headers(array $headers): void
        {
        }

        /**
         * @param string $header
         * @return false
         */
        public function get_header(string $header): false
        {
            return false;
        }

        /**
         * @param Translation_Entry $entry
         * @return false
         */
        public function translate_entry(Translation_Entry $entry): false
        {
            return false;
        }

        /**
         * @param string $singular
         * @param string|null $context
         * @return string
         */
        public function translate(string $singular, ?string $context = null): string
        {
            return $singular;
        }

        /**
         * @param int $count
         * @return int
         */
        public function select_plural_form(int $count): int
        {
            return 1 === $count ? 0 : 1;
        }

        /**
         * @return int
         */
        public function get_plural_forms_count(): int
        {
            return 2;
        }

        /**
         * @param string $singular
         * @param string $plural
         * @param int $count
         * @param string|null $context
         * @return string
         */
        public function translate_plural(string $singular, string $plural, int $count, ?string $context = null): string
        {
            return 1 === $count ? $singular : $plural;
        }

        /**
         * @param Translations $other
         * @return void
         */
        public function merge_with(Translations $other): void
        {
        }
    }
}