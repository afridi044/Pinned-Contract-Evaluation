<?php
declare(strict_types=1);

if (!defined('SERVICES_JSON_SLICE')) {
    define('SERVICES_JSON_SLICE', 1);
}

if (!defined('SERVICES_JSON_IN_STR')) {
    define('SERVICES_JSON_IN_STR', 2);
}

if (!defined('SERVICES_JSON_IN_ARR')) {
    define('SERVICES_JSON_IN_ARR', 3);
}

if (!defined('SERVICES_JSON_IN_OBJ')) {
    define('SERVICES_JSON_IN_OBJ', 4);
}

if (!defined('SERVICES_JSON_IN_CMT')) {
    define('SERVICES_JSON_IN_CMT', 5);
}

if (!defined('SERVICES_JSON_LOOSE_TYPE')) {
    define('SERVICES_JSON_LOOSE_TYPE', 16);
}

if (!defined('SERVICES_JSON_SUPPRESS_ERRORS')) {
    define('SERVICES_JSON_SUPPRESS_ERRORS', 32);
}

if (!defined('SERVICES_JSON_USE_TO_JSON')) {
    define('SERVICES_JSON_USE_TO_JSON', 64);
}

if (!class_exists('Services_JSON', false)) {
    /**
     * Converts to and from JSON format.
     */
    class Services_JSON
    {
        private int $use;
        private bool $_mb_strlen = false;
        private bool $_mb_substr = false;
        private bool $_mb_convert_encoding = false;

        /**
         * Constructs a new JSON instance.
         */
        public function __construct(int $use = 0)
        {
            $this->use = $use;
            $this->_mb_strlen = function_exists('mb_strlen');
            $this->_mb_convert_encoding = function_exists('mb_convert_encoding');
            $this->_mb_substr = function_exists('mb_substr');
        }

        /**
         * Convert a string from one UTF-16 char to one UTF-8 char.
         */
        private function utf162utf8(string $utf16): string
        {
            if ($utf16 === '') {
                return '';
            }

            if ($this->_mb_convert_encoding) {
                return mb_convert_encoding($utf16, 'UTF-8', 'UTF-16');
            }

            if (strlen($utf16) < 2) {
                return '';
            }

            $bytes = (ord($utf16[0]) << 8) | ord($utf16[1]);

            if ((0x7F & $bytes) === $bytes) {
                return chr(0x7F & $bytes);
            }

            if ((0x07FF & $bytes) === $bytes) {
                return chr(0xC0 | (($bytes >> 6) & 0x1F))
                    . chr(0x80 | ($bytes & 0x3F));
            }

            if ((0xFFFF & $bytes) === $bytes) {
                return chr(0xE0 | (($bytes >> 12) & 0x0F))
                    . chr(0x80 | (($bytes >> 6) & 0x3F))
                    . chr(0x80 | ($bytes & 0x3F));
            }

            return '';
        }

        /**
         * Convert a string from one UTF-8 char to one UTF-16 char.
         */
        private function utf82utf16(string $utf8): string
        {
            if ($utf8 === '') {
                return '';
            }

            if ($this->_mb_convert_encoding) {
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
         * Encodes an arbitrary variable into JSON format (and sends JSON header).
         */
        public function encode(mixed $var)
        {
            header('Content-Type: application/json');
            return $this->encodeUnsafe($var);
        }

        /**
         * Encodes an arbitrary variable into JSON format without sending JSON header.
         */
        public function encodeUnsafe(mixed $var)
        {
            $lc = setlocale(LC_NUMERIC, 0);
            setlocale(LC_NUMERIC, 'C');
            $ret = $this->_encode($var);
            if ($lc !== false) {
                setlocale(LC_NUMERIC, $lc);
            }
            return $ret;
        }

        /**
         * Internal encoding implementation.
         */
        private function _encode(mixed $var)
        {
            switch (gettype($var)) {
                case 'boolean':
                    return $var ? 'true' : 'false';

                case 'NULL':
                    return 'null';

                case 'integer':
                    return (string) ((int) $var);

                case 'double':
                case 'float':
                    return (string) ((float) $var);

                case 'string':
                    $ascii = '';
                    $strlen_var = $this->strlen8($var);

                    for ($c = 0; $c < $strlen_var; ++$c) {
                        $ord_var_c = ord($var[$c]);

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
                                $ascii .= '\\' . $var[$c];
                                break;

                            case $ord_var_c >= 0x20 && $ord_var_c <= 0x7F:
                                $ascii .= $var[$c];
                                break;

                            case ($ord_var_c & 0xE0) === 0xC0:
                                if ($c + 1 >= $strlen_var) {
                                    $c += 1;
                                    $ascii .= '?';
                                    break;
                                }

                                $char = pack('C*', $ord_var_c, ord($var[$c + 1]));
                                $c += 1;
                                $utf16 = $this->utf82utf16($char);
                                $ascii .= sprintf('\u%04s', bin2hex($utf16));
                                break;

                            case ($ord_var_c & 0xF0) === 0xE0:
                                if ($c + 2 >= $strlen_var) {
                                    $c += 2;
                                    $ascii .= '?';
                                    break;
                                }

                                $char = pack('C*', $ord_var_c, ord($var[$c + 1]), ord($var[$c + 2]));
                                $c += 2;
                                $utf16 = $this->utf82utf16($char);
                                $ascii .= sprintf('\u%04s', bin2hex($utf16));
                                break;

                            case ($ord_var_c & 0xF8) === 0xF0:
                                if ($c + 3 >= $strlen_var) {
                                    $c += 3;
                                    $ascii .= '?';
                                    break;
                                }

                                $char = pack(
                                    'C*',
                                    $ord_var_c,
                                    ord($var[$c + 1]),
                                    ord($var[$c + 2]),
                                    ord($var[$c + 3])
                                );
                                $c += 3;
                                $utf16 = $this->utf82utf16($char);
                                $ascii .= sprintf('\u%04s', bin2hex($utf16));
                                break;

                            case ($ord_var_c & 0xFC) === 0xF8:
                                if ($c + 4 >= $strlen_var) {
                                    $c += 4;
                                    $ascii .= '?';
                                    break;
                                }

                                $char = pack(
                                    'C*',
                                    $ord_var_c,
                                    ord($var[$c + 1]),
                                    ord($var[$c + 2]),
                                    ord($var[$c + 3]),
                                    ord($var[$c + 4])
                                );
                                $c += 4;
                                $utf16 = $this->utf82utf16($char);
                                $ascii .= sprintf('\u%04s', bin2hex($utf16));
                                break;

                            case ($ord_var_c & 0xFE) === 0xFC:
                                if ($c + 5 >= $strlen_var) {
                                    $c += 5;
                                    $ascii .= '?';
                                    break;
                                }

                                $char = pack(
                                    'C*',
                                    $ord_var_c,
                                    ord($var[$c + 1]),
                                    ord($var[$c + 2]),
                                    ord($var[$c + 3]),
                                    ord($var[$c + 4]),
                                    ord($var[$c + 5])
                                );
                                $c += 5;
                                $utf16 = $this->utf82utf16($char);
                                $ascii .= sprintf('\u%04s', bin2hex($utf16));
                                break;
                        }
                    }

                    return '"' . $ascii . '"';

                case 'array':
                    if ($var !== [] && array_keys($var) !== range(0, count($var) - 1)) {
                        $properties = array_map([$this, 'name_value'], array_keys($var), array_values($var));

                        foreach ($properties as $property) {
                            if (self::isError($property)) {
                                return $property;
                            }
                        }

                        return '{' . implode(',', $properties) . '}';
                    }

                    $elements = array_map([$this, '_encode'], $var);

                    foreach ($elements as $element) {
                        if (self::isError($element)) {
                            return $element;
                        }
                    }

                    return '[' . implode(',', $elements) . ']';

                case 'object':
                    if (($this->use & SERVICES_JSON_USE_TO_JSON) && method_exists($var, 'toJSON')) {
                        $recode = $var->toJSON();

                        if (is_object($recode) && method_exists($recode, 'toJSON')) {
                            return ($this->use & SERVICES_JSON_SUPPRESS_ERRORS)
                                ? 'null'
                                : new Services_JSON_Error(get_class($var) . ' toJSON returned an object with a toJSON method.');
                        }

                        return $this->_encode($recode);
                    }

                    $vars = get_object_vars($var);

                    $properties = array_map([$this, 'name_value'], array_keys($vars), array_values($vars));

                    foreach ($properties as $property) {
                        if (self::isError($property)) {
                            return $property;
                        }
                    }

                    return '{' . implode(',', $properties) . '}';

                default:
                    return ($this->use & SERVICES_JSON_SUPPRESS_ERRORS)
                        ? 'null'
                        : new Services_JSON_Error(gettype($var) . ' can not be encoded as JSON string');
            }
        }

        /**
         * Generates JSON-formatted name-value pairs.
         */
        private function name_value(string $name, mixed $value)
        {
            $encoded_value = $this->_encode($value);

            if (self::isError($encoded_value)) {
                return $encoded_value;
            }

            return $this->_encode((string) $name) . ':' . $encoded_value;
        }

        /**
         * Reduce a string by removing comments and whitespace.
         */
        private function reduce_string(string $str): string
        {
            $str = preg_replace(
                [
                    '#^\s*//(.+)$#m',
                    '#^\s*/\*(.+)\*/#Us',
                    '#/\*(.+)\*/\s*$#Us',
                ],
                '',
                $str
            );

            return trim($str);
        }

        /**
         * Decodes a JSON string into appropriate variable.
         */
        public function decode(string $str)
        {
            $str = $this->reduce_string($str);

            switch (strtolower($str)) {
                case 'true':
                    return true;

                case 'false':
                    return false;

                case 'null':
                    return null;

                default:
                    if (is_numeric($str)) {
                        return ((float) $str == (int) $str)
                            ? (int) $str
                            : (float) $str;
                    }

                    $m = [];
                    if (preg_match('/^("|\').*(\1)$/s', $str, $m) && $m[1] === $m[2]) {
                        $delim = $this->substr8($str, 0, 1);
                        $chrs = $this->substr8($str, 1, -1);
                        $utf8 = '';
                        $strlen_chrs = $this->strlen8($chrs);

                        for ($c = 0; $c < $strlen_chrs; ++$c) {
                            $substr_chrs_c_2 = $this->substr8($chrs, $c, 2);
                            $ord_chrs_c = ord($chrs[$c]);

                            switch (true) {
                                case $substr_chrs_c_2 === '\b':
                                    $utf8 .= chr(0x08);
                                    ++$c;
                                    break;

                                case $substr_chrs_c_2 === '\t':
                                    $utf8 .= chr(0x09);
                                    ++$c;
                                    break;

                                case $substr_chrs_c_2 === '\n':
                                    $utf8 .= chr(0x0A);
                                    ++$c;
                                    break;

                                case $substr_chrs_c_2 === '\f':
                                    $utf8 .= chr(0x0C);
                                    ++$c;
                                    break;

                                case $substr_chrs_c_2 === '\r':
                                    $utf8 .= chr(0x0D);
                                    ++$c;
                                    break;

                                case $substr_chrs_c_2 === '\\"':
                                case $substr_chrs_c_2 === '\\\'':
                                case $substr_chrs_c_2 === '\\\\':
                                case $substr_chrs_c_2 === '\\/':
                                    if (($delim === '"' && $substr_chrs_c_2 !== '\\\'')
                                        || ($delim === "'" && $substr_chrs_c_2 !== '\\"')
                                    ) {
                                        $utf8 .= $chrs[++$c];
                                    }
                                    break;

                                case preg_match('/\\\u[0-9A-F]{4}/i', $this->substr8($chrs, $c, 6)) === 1:
                                    $utf16 = chr(hexdec($this->substr8($chrs, $c + 2, 2)))
                                        . chr(hexdec($this->substr8($chrs, $c + 4, 2)));
                                    $utf8 .= $this->utf162utf8($utf16);
                                    $c += 5;
                                    break;

                                case $ord_chrs_c >= 0x20 && $ord_chrs_c <= 0x7F:
                                    $utf8 .= $chrs[$c];
                                    break;

                                case ($ord_chrs_c & 0xE0) === 0xC0:
                                    $utf8 .= $this->substr8($chrs, $c, 2);
                                    ++$c;
                                    break;

                                case ($ord_chrs_c & 0xF0) === 0xE0:
                                    $utf8 .= $this->substr8($chrs, $c, 3);
                                    $c += 2;
                                    break;

                                case ($ord_chrs_c & 0xF8) === 0xF0:
                                    $utf8 .= $this->substr8($chrs, $c, 4);
                                    $c += 3;
                                    break;

                                case ($ord_chrs_c & 0xFC) === 0xF8:
                                    $utf8 .= $this->substr8($chrs, $c, 5);
                                    $c += 4;
                                    break;

                                case ($ord_chrs_c & 0xFE) === 0xFC:
                                    $utf8 .= $this->substr8($chrs, $c, 6);
                                    $c += 5;
                                    break;
                            }
                        }

                        return $utf8;
                    }

                    if (preg_match('/^\[.*\]$/s', $str) || preg_match('/^\{.*\}$/s', $str)) {
                        if ($str[0] === '[') {
                            $stk = [SERVICES_JSON_IN_ARR];
                            $arr = [];
                        } else {
                            $stk = [SERVICES_JSON_IN_OBJ];
                            $obj = ($this->use & SERVICES_JSON_LOOSE_TYPE) ? [] : new stdClass();
                        }

                        $stk[] = [
                            'what' => SERVICES_JSON_SLICE,
                            'where' => 0,
                            'delim' => false,
                        ];

                        $chrs = $this->substr8($str, 1, -1);
                        $chrs = $this->reduce_string($chrs);

                        if ($chrs === '') {
                            if (reset($stk) === SERVICES_JSON_IN_ARR) {
                                return $arr;
                            }

                            if (reset($stk) === SERVICES_JSON_IN_OBJ) {
                                return $obj;
                            }
                        }

                        $strlen_chrs = $this->strlen8($chrs);

                        for ($c = 0; $c <= $strlen_chrs; ++$c) {
                            $top = end($stk);
                            $substr_chrs_c_2 = $this->substr8($chrs, $c, 2);

                            if ($c === $strlen_chrs
                                || ($chrs[$c] === ',' && $top['what'] === SERVICES_JSON_SLICE)
                            ) {
                                $slice = $this->substr8($chrs, $top['where'], $c - $top['where']);
                                $stk[] = ['what' => SERVICES_JSON_SLICE, 'where' => $c + 1, 'delim' => false];

                                if (reset($stk) === SERVICES_JSON_IN_ARR) {
                                    $arr[] = $this->decode($slice);
                                } elseif (reset($stk) === SERVICES_JSON_IN_OBJ) {
                                    $parts = [];

                                    if (preg_match('/^\s*(["\'].*[^\\\\]["\'])\s*:/Uis', $slice, $parts)) {
                                        $key = $this->decode($parts[1]);
                                        $val = $this->decode(trim((string) substr($slice, strlen($parts[0])), ", \t\n\r\0\x0B"));
                                        if ($this->use & SERVICES_JSON_LOOSE_TYPE) {
                                            $obj[$key] = $val;
                                        } else {
                                            $obj->{$key} = $val;
                                        }
                                    } elseif (preg_match('/^\s*(\w+)\s*:/Uis', $slice, $parts)) {
                                        $key = $parts[1];
                                        $val = $this->decode(trim((string) substr($slice, strlen($parts[0])), ", \t\n\r\0\x0B"));

                                        if ($this->use & SERVICES_JSON_LOOSE_TYPE) {
                                            $obj[$key] = $val;
                                        } else {
                                            $obj->{$key} = $val;
                                        }
                                    }
                                }
                            } elseif (($chrs[$c] === '"' || $chrs[$c] === "'") && $top['what'] !== SERVICES_JSON_IN_STR) {
                                $stk[] = ['what' => SERVICES_JSON_IN_STR, 'where' => $c, 'delim' => $chrs[$c]];
                            } elseif (
                                $chrs[$c] === $top['delim']
                                && $top['what'] === SERVICES_JSON_IN_STR
                                && ($this->strlen8($this->substr8($chrs, 0, $c)) - $this->strlen8(rtrim($this->substr8($chrs, 0, $c), '\\'))) % 2 !== 1
                            ) {
                                array_pop($stk);
                            } elseif (
                                $chrs[$c] === '['
                                && in_array($top['what'], [SERVICES_JSON_SLICE, SERVICES_JSON_IN_ARR, SERVICES_JSON_IN_OBJ], true)
                            ) {
                                $stk[] = ['what' => SERVICES_JSON_IN_ARR, 'where' => $c, 'delim' => false];
                            } elseif ($chrs[$c] === ']' && $top['what'] === SERVICES_JSON_IN_ARR) {
                                array_pop($stk);
                            } elseif (
                                $chrs[$c] === '{'
                                && in_array($top['what'], [SERVICES_JSON_SLICE, SERVICES_JSON_IN_ARR, SERVICES_JSON_IN_OBJ], true)
                            ) {
                                $stk[] = ['what' => SERVICES_JSON_IN_OBJ, 'where' => $c, 'delim' => false];
                            } elseif ($chrs[$c] === '}' && $top['what'] === SERVICES_JSON_IN_OBJ) {
                                array_pop($stk);
                            } elseif (
                                $substr_chrs_c_2 === '/*'
                                && in_array($top['what'], [SERVICES_JSON_SLICE, SERVICES_JSON_IN_ARR, SERVICES_JSON_IN_OBJ], true)
                            ) {
                                $stk[] = ['what' => SERVICES_JSON_IN_CMT, 'where' => $c, 'delim' => false];
                                ++$c;
                            } elseif ($substr_chrs_c_2 === '*/' && $top['what'] === SERVICES_JSON_IN_CMT) {
                                array_pop($stk);
                                ++$c;

                                for ($i = $top['where']; $i <= $c; ++$i) {
                                    $chrs = substr_replace($chrs, ' ', $i, 1);
                                }
                            }
                        }

                        if (reset($stk) === SERVICES_JSON_IN_ARR) {
                            return $arr;
                        }

                        if (reset($stk) === SERVICES_JSON_IN_OBJ) {
                            return $obj;
                        }
                    }

                    return null;
            }
        }

        /**
         * Determines if supplied data is an error.
         */
        public static function isError(mixed $data, mixed $code = null): bool
        {
            if (class_exists('PEAR')) {
                /** @noinspection PhpUndefinedClassInspection */
                return PEAR::isError($data, $code);
            }

            if (is_object($data)) {
                $class = get_class($data);
                if ($class === 'Services_JSON_Error' || is_subclass_of($data, 'Services_JSON_Error')) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Calculates length of string in bytes.
         */
        public function strlen8(string $str): int
        {
            if ($this->_mb_strlen) {
                return mb_strlen($str, '8bit');
            }
            return strlen($str);
        }

        /**
         * Returns part of a string, interpreting start and length as number of bytes.
         */
        public function substr8(string $string, int $start, ?int $length = null): string
        {
            if ($length === null) {
                $length = $this->strlen8($string) - $start;
            }

            if ($this->_mb_substr) {
                return mb_substr($string, $start, $length, '8bit');
            }

            return substr($string, $start, $length);
        }
    }
}

if (!class_exists('Services_JSON_Error', false)) {
    if (class_exists('PEAR_Error')) {
        class Services_JSON_Error extends PEAR_Error
        {
            public function __construct(
                string $message = 'unknown error',
                mixed $code = null,
                mixed $mode = null,
                mixed $options = null,
                mixed $userinfo = null
            ) {
                parent::__construct($message, $code, $mode, $options, $userinfo);
            }
        }
    } else {
        class Services_JSON_Error
        {
            public function __construct(
                string $message = 'unknown error',
                mixed $code = null,
                mixed $mode = null,
                mixed $options = null,
                mixed $userinfo = null
            ) {
            }
        }
    }
}
?>