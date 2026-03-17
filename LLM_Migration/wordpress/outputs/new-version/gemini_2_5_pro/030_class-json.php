<?php

declare(strict_types=1);

/**
 * This file contains a modernized version of the legacy Services_JSON class.
 * The original code has been migrated to PHP 8.3 standards, incorporating modern
 * syntax, type safety, and exception-based error handling while preserving the
 * original's functionality, including its custom JSON parser/encoder logic.
 *
 * For new projects, using PHP's native `json_encode()` and `json_decode()`
 * is strongly recommended for performance and security.
 */
namespace Modernized\Json;

/**
 * Custom exception for JSON service errors.
 */
class ServicesJsonException extends \Exception
{
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Converts to and from JSON format.
 *
 * JSON (JavaScript Object Notation) is a lightweight data-interchange
 * format. It is easy for humans to read and write. It is easy for machines
 * to parse and generate.
 *
 * This package provides a simple encoder and decoder for JSON notation.
 * All strings should be in ASCII or UTF-8 format!
 *
 * LICENSE: Redistribution and use in source and binary forms, with or
 * without modification, are permitted provided that the following
 * conditions are met: Redistributions of source code must retain the
 * above copyright notice, this list of conditions and the following
 * disclaimer. Redistributions in binary form must reproduce the above
 * copyright notice, this list of conditions and the following disclaimer
 * in the documentation and/or other materials provided with the
 * distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN
 * NO EVENT SHALL CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS
 * OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR
 * TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
 * USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 *
 * @category
 * @package     Services_JSON
 * @author      Michal Migurski <mike-json@teczno.com>
 * @author      Matt Knapp <mdknapp[at]gmail[dot]com>
 * @author      Brett Stimmerman <brettstimmerman[at]gmail[dot]com>
 * @copyright   2005 Michal Migurski
 * @license     http://www.opensource.org/licenses/bsd-license.php
 * @link        http://pear.php.net/pepr/pepr-proposal-show.php?id=198
 */
class Services_JSON
{
    /**
     * Behavior switch for Services_JSON::decode():
     * loose typing, "{...}" syntax creates associative arrays instead of objects.
     */
    public const LOOSE_TYPE = 16;

    /**
     * Behavior switch for Services_JSON::decode():
     * error suppression. Values which can't be encoded (e.g. resources)
     * appear as NULL instead of throwing exceptions.
     */
    public const SUPPRESS_ERRORS = 32;

    /**
     * Behavior switch for Services_JSON::decode():
     * call toJSON() when serializing objects.
     */
    public const USE_TO_JSON = 64;

    /** Marker constant for Services_JSON::decode(), used to flag stack state */
    private const SLICE = 1;

    /** Marker constant for Services_JSON::decode(), used to flag stack state */
    private const IN_STR = 2;

    /** Marker constant for Services_JSON::decode(), used to flag stack state */
    private const IN_ARR = 3;

    /** Marker constant for Services_JSON::decode(), used to flag stack state */
    private const IN_OBJ = 4;

    /** Marker constant for Services_JSON::decode(), used to flag stack state */
    private const IN_CMT = 5;

    private readonly bool $mb_strlen;
    private readonly bool $mb_convert_encoding;
    private readonly bool $mb_substr;

    /**
     * Constructs a new JSON instance.
     *
     * @param int $use Object behavior flags; combine with bitwise-OR.
     */
    public function __construct(private int $use = 0)
    {
        $this->mb_strlen = function_exists('mb_strlen');
        $this->mb_convert_encoding = function_exists('mb_convert_encoding');
        $this->mb_substr = function_exists('mb_substr');
    }

    /**
     * Encodes a variable into JSON format and sends the 'application/json' content-type header.
     *
     * @param mixed $var Any number, boolean, string, array, or object to be encoded.
     *                   Strings are expected to be in ASCII or UTF-8 format.
     * @return string JSON string representation of the input variable.
     * @throws ServicesJsonException If an error occurs during encoding.
     */
    public function encode(mixed $var): string
    {
        // Setting headers in a utility class is a side effect and generally bad practice,
        // but it's maintained here for functional equivalence.
        if (!headers_sent()) {
            header('Content-type: application/json');
        }
        return $this->encodeUnsafe($var);
    }

    /**
     * Encodes a variable into JSON format without sending headers.
     *
     * @param mixed $var Any number, boolean, string, array, or object to be encoded.
     * @return string JSON string representation of the input variable.
     * @throws ServicesJsonException If an error occurs during encoding.
     */
    public function encodeUnsafe(mixed $var): string
    {
        // The original code changed the numeric locale to 'C' to ensure that
        // floating-point numbers use a '.' as the decimal separator.
        $lc = setlocale(LC_NUMERIC, '0');
        setlocale(LC_NUMERIC, 'C');
        try {
            $result = $this->internalEncode($var);
        } finally {
            setlocale(LC_NUMERIC, $lc);
        }
        return $result;
    }

    /**
     * Decodes a JSON string into a PHP variable.
     *
     * @param string $str JSON-formatted string.
     * @return mixed Number, boolean, string, array, or object corresponding to the JSON input.
     * @throws ServicesJsonException If the input string is not valid JSON.
     */
    public function decode(string $str): mixed
    {
        $str = $this->reduce_string($str);

        return match (strtolower($str)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $this->parseValue($str),
        };
    }

    /**
     * The core encoding logic.
     *
     * @param mixed $var The variable to encode.
     * @return string The JSON-encoded string.
     * @throws ServicesJsonException
     */
    private function internalEncode(mixed $var): string
    {
        return match (gettype($var)) {
            'boolean' => $var ? 'true' : 'false',
            'NULL' => 'null',
            'integer' => (string)(int)$var,
            'double', 'float' => (string)(float)$var,
            'string' => $this->encodeString($var),
            'array' => $this->encodeArray($var),
            'object' => $this->encodeObject($var),
            default => ($this->use & self::SUPPRESS_ERRORS)
                ? 'null'
                : throw new ServicesJsonException(gettype($var) . " can not be encoded as JSON string"),
        };
    }

    /**
     * Encodes a string into a JSON string literal.
     *
     * @param string $string The string to encode.
     * @return string The JSON-encoded string literal.
     */
    private function encodeString(string $string): string
    {
        $ascii = '';
        $strlen_var = $this->strlen8($string);

        for ($c = 0; $c < $strlen_var; ++$c) {
            $ord_var_c = ord($string[$c]);

            switch (true) {
                case $ord_var_c === 0x08:
                    $ascii .= '\b';
                    break;
                case $ord_var_c === 0x09:
                    $ascii .= '\t';
                    break;
                case $ord_var_c === 0x0A:
                    $ascii .= '\n';
                    break;
                case $ord_var_c === 0x0C:
                    $ascii .= '\f';
                    break;
                case $ord_var_c === 0x0D:
                    $ascii .= '\r';
                    break;
                case $ord_var_c === 0x22:
                case $ord_var_c === 0x2F:
                case $ord_var_c === 0x5C:
                    $ascii .= '\\' . $string[$c];
                    break;
                case (($ord_var_c >= 0x20) && ($ord_var_c <= 0x7F)):
                    $ascii .= $string[$c];
                    break;
                case (($ord_var_c & 0xE0) === 0xC0):
                    $char = $string[$c] . ($string[$c + 1] ?? '');
                    $c += 1;
                    $utf16 = $this->utf82utf16($char);
                    $ascii .= sprintf('\u%04s', bin2hex($utf16));
                    break;
                case (($ord_var_c & 0xF0) === 0xE0):
                    $char = $string[$c] . ($string[$c + 1] ?? '') . ($string[$c + 2] ?? '');
                    $c += 2;
                    $utf16 = $this->utf82utf16($char);
                    $ascii .= sprintf('\u%04s', bin2hex($utf16));
                    break;
                case (($ord_var_c & 0xF8) === 0xF0):
                    $char = $string[$c] . ($string[$c + 1] ?? '') . ($string[$c + 2] ?? '') . ($string[$c + 3] ?? '');
                    $c += 3;
                    $utf16 = $this->utf82utf16($char);
                    $ascii .= sprintf('\u%04s', bin2hex($utf16));
                    break;
                case (($ord_var_c & 0xFC) === 0xF8):
                    $char = $string[$c] . ($string[$c + 1] ?? '') . ($string[$c + 2] ?? '') . ($string[$c + 3] ?? '') . ($string[$c + 4] ?? '');
                    $c += 4;
                    $utf16 = $this->utf82utf16($char);
                    $ascii .= sprintf('\u%04s', bin2hex($utf16));
                    break;
                case (($ord_var_c & 0xFE) === 0xFC):
                    $char = $string[$c] . ($string[$c + 1] ?? '') . ($string[$c + 2] ?? '') . ($string[$c + 3] ?? '') . ($string[$c + 4] ?? '') . ($string[$c + 5] ?? '');
                    $c += 5;
                    $utf16 = $this->utf82utf16($char);
                    $ascii .= sprintf('\u%04s', bin2hex($utf16));
                    break;
            }
        }
        return '"' . $ascii . '"';
    }

    /**
     * Encodes an array into a JSON array or object.
     *
     * @param array $arr The array to encode.
     * @return string The JSON-encoded array or object.
     */
    private function encodeArray(array $arr): string
    {
        if (count($arr) && array_keys($arr) !== range(0, count($arr) - 1)) {
            // Associative array, treat as object
            $properties = array_map(
                [$this, 'name_value'],
                array_keys($arr),
                array_values($arr)
            );
            return '{' . implode(',', $properties) . '}';
        }

        // Indexed array
        $elements = array_map([$this, 'internalEncode'], $arr);
        return '[' . implode(',', $elements) . ']';
    }

    /**
     * Encodes an object into a JSON object.
     *
     * @param object $obj The object to encode.
     * @return string The JSON-encoded object.
     * @throws ServicesJsonException
     */
    private function encodeObject(object $obj): string
    {
        if (($this->use & self::USE_TO_JSON) && method_exists($obj, 'toJSON')) {
            $recode = $obj->toJSON();
            if (is_object($recode) && method_exists($recode, 'toJSON')) {
                if ($this->use & self::SUPPRESS_ERRORS) {
                    return 'null';
                }
                throw new ServicesJsonException(get_class($obj) . " toJSON returned an object with a toJSON method.");
            }
            return $this->internalEncode($recode);
        }

        $vars = get_object_vars($obj);
        $properties = array_map(
            [$this, 'name_value'],
            array_keys($vars),
            array_values($vars)
        );

        return '{' . implode(',', $properties) . '}';
    }

    /**
     * Array-walking function for generating JSON-formatted name-value pairs.
     *
     * @param string $name Name of key to use.
     * @param mixed $value An array element to be encoded.
     * @return string JSON-formatted name-value pair, like '"name":value'.
     */
    private function name_value(string $name, mixed $value): string
    {
        return $this->internalEncode($name) . ':' . $this->internalEncode($value);
    }

    /**
     * Parses a JSON-formatted string.
     *
     * @param string $str The string to parse.
     * @return mixed The parsed value.
     * @throws ServicesJsonException
     */
    private function parseValue(string $str): mixed
    {
        if (is_numeric($str)) {
            return ((float)$str == (int)$str) ? (int)$str : (float)$str;
        }

        if (preg_match('/^("|\').*(\1)$/s', $str, $m) && $m[1] === $m[2]) {
            return $this->decodeString($str);
        }

        if (preg_match('/^\[.*\]$/s', $str) || preg_match('/^\{.*\}$/s', $str)) {
            return $this->parseCollection($str);
        }

        throw new ServicesJsonException("Unable to parse value: {$str}");
    }

    /**
     * Decodes a JSON string literal.
     *
     * @param string $str The JSON string literal (e.g., '"hello"').
     * @return string The decoded string.
     */
    private function decodeString(string $str): string
    {
        $delim = $this->substr8($str, 0, 1);
        $chrs = $this->substr8($str, 1, -1);
        $utf8 = '';
        $strlen_chrs = $this->strlen8($chrs);

        for ($c = 0; $c < $strlen_chrs; ++$c) {
            $substr_chrs_c_2 = $this->substr8($chrs, $c, 2);

            switch ($substr_chrs_c_2) {
                case '\b':
                    $utf8 .= chr(0x08);
                    $c++;
                    continue 2;
                case '\t':
                    $utf8 .= chr(0x09);
                    $c++;
                    continue 2;
                case '\n':
                    $utf8 .= chr(0x0A);
                    $c++;
                    continue 2;
                case '\f':
                    $utf8 .= chr(0x0C);
                    $c++;
                    continue 2;
                case '\r':
                    $utf8 .= chr(0x0D);
                    $c++;
                    continue 2;
            }

            if ($substr_chrs_c_2 === '\\"' || $substr_chrs_c_2 === "\\'" || $substr_chrs_c_2 === '\\\\' || $substr_chrs_c_2 === '\\/') {
                if (($delim === '"' && $substr_chrs_c_2 !== "\\'") || ($delim === "'" && $substr_chrs_c_2 !== '\\"')) {
                    $utf8 .= $chrs[++$c];
                    continue;
                }
            }

            if (preg_match('/\\\u[0-9A-F]{4}/i', $this->substr8($chrs, $c, 6))) {
                $utf16 = chr(hexdec($this->substr8($chrs, ($c + 2), 2)))
                    . chr(hexdec($this->substr8($chrs, ($c + 4), 2)));
                $utf8 .= $this->utf162utf8($utf16);
                $c += 5;
                continue;
            }

            $utf8 .= $chrs[$c];
        }

        return $utf8;
    }

    /**
     * Parses a JSON array or object.
     *
     * @param string $str The collection string (e.g., '[1,2]' or '{"a":1}').
     * @return array|object The parsed collection.
     */
    private function parseCollection(string $str): array|object
    {
        if ($str[0] === '[') {
            $stk = [self::IN_ARR];
            $arr = [];
        } else {
            $stk = [self::IN_OBJ];
            $obj = ($this->use & self::LOOSE_TYPE) ? [] : new \stdClass();
        }

        $stk[] = ['what' => self::SLICE, 'where' => 0, 'delim' => false];

        $chrs = $this->substr8($str, 1, -1);
        $chrs = $this->reduce_string($chrs);

        if ($chrs === '') {
            return $str[0] === '[' ? [] : $obj;
        }

        $strlen_chrs = $this->strlen8($chrs);

        for ($c = 0; $c <= $strlen_chrs; ++$c) {
            $top = end($stk);
            $char = $chrs[$c] ?? null;
            $substr_chrs_c_2 = $this->substr8($chrs, $c, 2);

            if ($c === $strlen_chrs || ($char === ',' && $top['what'] === self::SLICE)) {
                $slice = $this->substr8($chrs, $top['where'], ($c - $top['where']));
                $stk[] = ['what' => self::SLICE, 'where' => ($c + 1), 'delim' => false];

                if (reset($stk) === self::IN_ARR) {
                    $arr[] = $this->decode($slice);
                } elseif (reset($stk) === self::IN_OBJ) {
                    if (preg_match('/^\s*(["\'].*[^\\\]["\'])\s*:/Uis', $slice, $parts)) {
                        $key = $this->decode($parts[1]);
                        $val = $this->decode(trim(substr($slice, strlen($parts[0])), ", \t\n\r\0\x0B"));
                    } elseif (preg_match('/^\s*(\w+)\s*:/Uis', $slice, $parts)) {
                        $key = $parts[1];
                        $val = $this->decode(trim(substr($slice, strlen($parts[0])), ", \t\n\r\0\x0B"));
                    } else {
                        continue; // Or throw error for malformed pair
                    }

                    if ($this->use & self::LOOSE_TYPE) {
                        $obj[$key] = $val;
                    } else {
                        $obj->{$key} = $val;
                    }
                }
            } elseif ((($char === '"') || ($char === "'")) && ($top['what'] !== self::IN_STR)) {
                $stk[] = ['what' => self::IN_STR, 'where' => $c, 'delim' => $char];
            } elseif (($char === $top['delim']) && ($top['what'] === self::IN_STR) && (($this->strlen8(rtrim($this->substr8($chrs, 0, $c), '\\'))) % 2 === 0)) {
                array_pop($stk);
            } elseif (($char === '[') && in_array($top['what'], [self::SLICE, self::IN_ARR, self::IN_OBJ], true)) {
                $stk[] = ['what' => self::IN_ARR, 'where' => $c, 'delim' => false];
            } elseif (($char === ']') && ($top['what'] === self::IN_ARR)) {
                array_pop($stk);
            } elseif (($char === '{') && in_array($top['what'], [self::SLICE, self::IN_ARR, self::IN_OBJ], true)) {
                $stk[] = ['what' => self::IN_OBJ, 'where' => $c, 'delim' => false];
            } elseif (($char === '}') && ($top['what'] === self::IN_OBJ)) {
                array_pop($stk);
            } elseif (($substr_chrs_c_2 === '/*') && in_array($top['what'], [self::SLICE, self::IN_ARR, self::IN_OBJ], true)) {
                $stk[] = ['what' => self::IN_CMT, 'where' => $c, 'delim' => false];
                $c++;
            } elseif (($substr_chrs_c_2 === '*/') && ($top['what'] === self::IN_CMT)) {
                array_pop($stk);
                $c++;
                for ($i = $top['where']; $i <= $c; ++$i) {
                    $chrs = substr_replace($chrs, ' ', $i, 1);
                }
            }
        }

        if (reset($stk) === self::IN_ARR) {
            return $arr;
        }
        return $obj;
    }

    /**
     * Reduces a string by removing leading/trailing comments and whitespace.
     *
     * @param string $str String to strip.
     * @return string Stripped string.
     */
    private function reduce_string(string $str): string
    {
        $str = preg_replace([
            // eliminate single line comments in '// ...' form
            '#^\s*//(.+)$#m',
            // eliminate multi-line comments in '/* ... */' form, at start of string
            '#^\s*/\*(.+)\*/#Us',
            // eliminate multi-line comments in '/* ... */' form, at end of string
            '#/\*(.+)\*/\s*$#Us'
        ], '', $str);

        return trim($str);
    }

    /**
     * Converts a UTF-16 character to UTF-8.
     *
     * @param string $utf16 UTF-16 character.
     * @return string UTF-8 character.
     */
    private function utf162utf8(string $utf16): string
    {
        if ($this->mb_convert_encoding) {
            return mb_convert_encoding($utf16, 'UTF-8', 'UTF-16');
        }

        $bytes = (ord($utf16[0]) << 8) | ord($utf16[1]);

        switch (true) {
            case ((0x7F & $bytes) === $bytes):
                return chr(0x7F & $bytes);
            case ((0x07FF & $bytes) === $bytes):
                return chr(0xC0 | (($bytes >> 6) & 0x1F))
                    . chr(0x80 | ($bytes & 0x3F));
            case ((0xFFFF & $bytes) === $bytes):
                return chr(0xE0 | (($bytes >> 12) & 0x0F))
                    . chr(0x80 | (($bytes >> 6) & 0x3F))
                    . chr(0x80 | ($bytes & 0x3F));
        }
        return '';
    }

    /**
     * Converts a UTF-8 character to UTF-16.
     *
     * @param string $utf8 UTF-8 character.
     * @return string UTF-16 character.
     */
    private function utf82utf16(string $utf8): string
    {
        if ($this->mb_convert_encoding) {
            return mb_convert_encoding($utf8, 'UTF-16', 'UTF-8');
        }

        switch ($this->strlen8($utf8)) {
            case 1:
                return $utf8;
            case 2:
                return chr(0x07 & (ord($utf8[0]) >> 2))
                    . chr((0xC0 & (ord($utf8[0]) << 6)) | (0x3F & ord($utf8[1])));
            case 3:
                return chr((0xF0 & (ord($utf8[0]) << 4)) | (0x0F & (ord($utf8[1]) >> 2)))
                    . chr((0xC0 & (ord($utf8[1]) << 6)) | (0x7F & ord($utf8[2])));
        }
        return '';
    }

    /**
     * Calculates the length of a string in bytes.
     *
     * @param string $str The string.
     * @return int The length in bytes.
     */
    private function strlen8(string $str): int
    {
        if ($this->mb_strlen) {
            return mb_strlen($str, "8bit");
        }
        return strlen($str);
    }

    /**
     * Returns part of a string, interpreting start and length as number of bytes.
     *
     * @param string $string The input string.
     * @param int $start The starting position.
     * @param int|null $length The length.
     * @return string The extracted part of the string.
     */
    private function substr8(string $string, int $start, ?int $length = null): string
    {
        if ($length === null) {
            $length = $this->strlen8($string) - $start;
        }
        if ($this->mb_substr) {
            return mb_substr($string, $start, $length, "8bit");
        }
        return substr($string, $start, $length);
    }
}