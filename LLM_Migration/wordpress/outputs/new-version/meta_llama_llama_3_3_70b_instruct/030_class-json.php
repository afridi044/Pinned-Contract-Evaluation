<?php

declare(strict_types=1);

class ServicesJson
{
    private const SERVICES_JSON_SLICE = 1;
    private const SERVICES_JSON_IN_STR = 2;
    private const SERVICES_JSON_IN_ARR = 3;
    private const SERVICES_JSON_IN_OBJ = 4;
    private const SERVICES_JSON_IN_CMT = 5;
    private const SERVICES_JSON_LOOSE_TYPE = 16;
    private const SERVICES_JSON_SUPPRESS_ERRORS = 32;
    private const SERVICES_JSON_USE_TO_JSON = 64;

    private int $use;
    private bool $_mb_strlen;
    private bool $_mb_convert_encoding;
    private bool $_mb_substr;

    public function __construct(int $use = 0)
    {
        $this->use = $use;
        $this->_mb_strlen = function_exists('mb_strlen');
        $this->_mb_convert_encoding = function_exists('mb_convert_encoding');
        $this->_mb_substr = function_exists('mb_substr');
    }

    private function utf162utf8(string $utf16): string
    {
        if ($this->_mb_convert_encoding) {
            return mb_convert_encoding($utf16, 'UTF-8', 'UTF-16');
        }

        $bytes = (ord($utf16[0]) << 8) | ord($utf16[1]);

        switch (true) {
            case ((0x7F & $bytes) == $bytes):
                return chr(0x7F & $bytes);

            case (0x07FF & $bytes) == $bytes:
                return chr(0xC0 | (($bytes >> 6) & 0x1F))
                     . chr(0x80 | ($bytes & 0x3F));

            case (0xFFFF & $bytes) == $bytes:
                return chr(0xE0 | (($bytes >> 12) & 0x0F))
                     . chr(0x80 | (($bytes >> 6) & 0x3F))
                     . chr(0x80 | ($bytes & 0x3F));
        }

        return '';
    }

    private function utf82utf16(string $utf8): string
    {
        if ($this->_mb_convert_encoding) {
            return mb_convert_encoding($utf8, 'UTF-16', 'UTF-8');
        }

        switch ($this->strlen8($utf8)) {
            case 1:
                return $utf8;

            case 2:
                return chr(0x07 & (ord($utf8[0]) >> 2))
                     . chr((0xC0 & (ord($utf8[0]) << 6))
                         | (0x3F & ord($utf8[1])));

            case 3:
                return chr((0xF0 & (ord($utf8[0]) << 4))
                         | (0x0F & (ord($utf8[1]) >> 2)))
                     . chr((0xC0 & (ord($utf8[1]) << 6))
                         | (0x7F & ord($utf8[2])));
        }

        return '';
    }

    public function encode($var): string
    {
        header('Content-type: application/json');
        return $this->encodeUnsafe($var);
    }

    public function encodeUnsafe($var): string
    {
        $lc = setlocale(LC_NUMERIC, 0);
        setlocale(LC_NUMERIC, 'C');
        $ret = $this->_encode($var);
        setlocale(LC_NUMERIC, $lc);
        return $ret;
    }

    private function _encode($var): string
    {
        switch (gettype($var)) {
            case 'boolean':
                return $var ? 'true' : 'false';

            case 'NULL':
                return 'null';

            case 'integer':
                return (string) $var;

            case 'double':
            case 'float':
                return (string) $var;

            case 'string':
                $ascii = '';
                $strlenVar = $this->strlen8($var);

                for ($c = 0; $c < $strlenVar; ++$c) {
                    $ordVarC = ord($var[$c]);

                    switch (true) {
                        case $ordVarC == 0x08:
                            $ascii .= '\b';
                            break;
                        case $ordVarC == 0x09:
                            $ascii .= '\t';
                            break;
                        case $ordVarC == 0x0A:
                            $ascii .= '\n';
                            break;
                        case $ordVarC == 0x0C:
                            $ascii .= '\f';
                            break;
                        case $ordVarC == 0x0D:
                            $ascii .= '\r';
                            break;

                        case $ordVarC == 0x22:
                        case $ordVarC == 0x2F:
                        case $ordVarC == 0x5C:
                            $ascii .= '\\' . $var[$c];
                            break;

                        case ($ordVarC >= 0x20) && ($ordVarC <= 0x7F):
                            $ascii .= $var[$c];
                            break;

                        case ($ordVarC & 0xE0) == 0xC0:
                            $char = pack('C*', $ordVarC, ord($var[$c + 1]));
                            $c += 1;
                            $utf16 = $this->utf82utf16($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;

                        case ($ordVarC & 0xF0) == 0xE0:
                            $char = pack('C*', $ordVarC, ord($var[$c + 1]), ord($var[$c + 2]));
                            $c += 2;
                            $utf16 = $this->utf82utf16($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;

                        case ($ordVarC & 0xF8) == 0xF0:
                            $char = pack('C*', $ordVarC, ord($var[$c + 1]), ord($var[$c + 2]), ord($var[$c + 3]));
                            $c += 3;
                            $utf16 = $this->utf82utf16($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;

                        case ($ordVarC & 0xFC) == 0xF8:
                            $char = pack('C*', $ordVarC, ord($var[$c + 1]), ord($var[$c + 2]), ord($var[$c + 3]), ord($var[$c + 4]));
                            $c += 4;
                            $utf16 = $this->utf82utf16($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;

                        case ($ordVarC & 0xFE) == 0xFC:
                            $char = pack('C*', $ordVarC, ord($var[$c + 1]), ord($var[$c + 2]), ord($var[$c + 3]), ord($var[$c + 4]), ord($var[$c + 5]));
                            $c += 5;
                            $utf16 = $this->utf82utf16($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;
                    }
                }

                return '"' . $ascii . '"';

            case 'array':
                if (is_array($var) && count($var) && (array_keys($var) !== range(0, sizeof($var) - 1))) {
                    $properties = array_map([$this, 'nameValue'], array_keys($var), array_values($var));

                    foreach ($properties as $property) {
                        if ($this->isError($property)) {
                            return $property;
                        }
                    }

                    return '{' . join(',', $properties) . '}';
                }

                $elements = array_map([$this, '_encode'], $var);

                foreach ($elements as $element) {
                    if ($this->isError($element)) {
                        return $element;
                    }
                }

                return '[' . join(',', $elements) . ']';

            case 'object':
                if (($this->use & self::SERVICES_JSON_USE_TO_JSON) && method_exists($var, 'toJSON')) {
                    $recode = $var->toJSON();

                    if (method_exists($recode, 'toJSON')) {
                        return ($this->use & self::SERVICES_JSON_SUPPRESS_ERRORS)
                            ? 'null'
                            : new ServicesJsonError(get_class($var) . ' toJSON returned an object with a toJSON method.');
                    }

                    return $this->_encode($recode);
                }

                $vars = get_object_vars($var);

                $properties = array_map([$this, 'nameValue'], array_keys($vars), array_values($vars));

                foreach ($properties as $property) {
                    if ($this->isError($property)) {
                        return $property;
                    }
                }

                return '{' . join(',', $properties) . '}';

            default:
                return ($this->use & self::SERVICES_JSON_SUPPRESS_ERRORS)
                    ? 'null'
                    : new ServicesJsonError(gettype($var) . ' can not be encoded as JSON string');
        }
    }

    private function nameValue(string $name, $value): string
    {
        $encodedValue = $this->_encode($value);

        if ($this->isError($encodedValue)) {
            return $encodedValue;
        }

        return $this->_encode((string) $name) . ':' . $encodedValue;
    }

    private function reduceString(string $str): string
    {
        $str = preg_replace([
            '#^\s*//(.+)$#m',
            '#^\s*/\*(.+)\*/#Us',
            '#/\*(.+)\*/\s*$#Us',
        ], '', $str);

        return trim($str);
    }

    public function decode(string $str)
    {
        $str = $this->reduceString($str);

        switch (strtolower($str)) {
            case 'true':
                return true;

            case 'false':
                return false;

            case 'null':
                return null;

            default:
                $m = [];

                if (is_numeric($str)) {
                    return ((float) $str == (int) $str)
                        ? (int) $str
                        : (float) $str;

                } elseif (preg_match('/^("|\').*(\1)$/s', $str, $m) && $m[1] == $m[2]) {
                    $delim = $this->substr8($str, 0, 1);
                    $chrs = $this->substr8($str, 1, -1);
                    $utf8 = '';
                    $strlenChrs = $this->strlen8($chrs);

                    for ($c = 0; $c < $strlenChrs; ++$c) {
                        $substrChrsC2 = $this->substr8($chrs, $c, 2);
                        $ordChrsC = ord($chrs[$c]);

                        switch (true) {
                            case $substrChrsC2 == '\b':
                                $utf8 .= chr(0x08);
                                ++$c;
                                break;
                            case $substrChrsC2 == '\t':
                                $utf8 .= chr(0x09);
                                ++$c;
                                break;
                            case $substrChrsC2 == '\n':
                                $utf8 .= chr(0x0A);
                                ++$c;
                                break;
                            case $substrChrsC2 == '\f':
                                $utf8 .= chr(0x0C);
                                ++$c;
                                break;
                            case $substrChrsC2 == '\r':
                                $utf8 .= chr(0x0D);
                                ++$c;
                                break;

                            case $substrChrsC2 == '\\"':
                            case $substrChrsC2 == '\\\'':
                            case $substrChrsC2 == '\\\\':
                            case $substrChrsC2 == '\\/':
                                if (($delim == '"' && $substrChrsC2 != '\\\'') ||
                                   ($delim == "'" && $substrChrsC2 != '\\"')) {
                                    $utf8 .= $chrs[++$c];
                                }
                                break;

                            case preg_match('/\\\u[0-9A-F]{4}/i', $this->substr8($chrs, $c, 6)):
                                $utf16 = chr(hexdec($this->substr8($chrs, ($c + 2), 2)))
                                       . chr(hexdec($this->substr8($chrs, ($c + 4), 2)));
                                $utf8 .= $this->utf162utf8($utf16);
                                $c += 5;
                                break;

                            case ($ordChrsC >= 0x20) && ($ordChrsC <= 0x7F):
                                $utf8 .= $chrs[$c];
                                break;

                            case ($ordChrsC & 0xE0) == 0xC0:
                                $utf8 .= $this->substr8($chrs, $c, 2);
                                ++$c;
                                break;

                            case ($ordChrsC & 0xF0) == 0xE0:
                                $utf8 .= $this->substr8($chrs, $c, 3);
                                $c += 2;
                                break;

                            case ($ordChrsC & 0xF8) == 0xF0:
                                $utf8 .= $this->substr8($chrs, $c, 4);
                                $c += 3;
                                break;

                            case ($ordChrsC & 0xFC) == 0xF8:
                                $utf8 .= $this->substr8($chrs, $c, 5);
                                $c += 4;
                                break;

                            case ($ordChrsC & 0xFE) == 0xFC:
                                $utf8 .= $this->substr8($chrs, $c, 6);
                                $c += 5;
                                break;
                        }
                    }

                    return $utf8;

                } elseif (preg_match('/^\[.*\]$/s', $str) || preg_match('/^\{.*\}$/s', $str)) {
                    if ($str[0] == '[') {
                        $stk = [self::SERVICES_JSON_IN_ARR];
                        $arr = [];
                    } else {
                        if ($this->use & self::SERVICES_JSON_LOOSE_TYPE) {
                            $stk = [self::SERVICES_JSON_IN_OBJ];
                            $obj = [];
                        } else {
                            $stk = [self::SERVICES_JSON_IN_OBJ];
                            $obj = new stdClass();
                        }
                    }

                    $stk[] = ['what' => self::SERVICES_JSON_SLICE, 'where' => 0, 'delim' => false];

                    $chrs = $this->substr8($str, 1, -1);
                    $chrs = $this->reduceString($chrs);

                    if ($chrs == '') {
                        if (reset($stk) == self::SERVICES_JSON_IN_ARR) {
                            return $arr;

                        } else {
                            return $obj;

                        }
                    }

                    $strlenChrs = $this->strlen8($chrs);

                    for ($c = 0; $c <= $strlenChrs; ++$c) {
                        $top = end($stk);
                        $substrChrsC2 = $this->substr8($chrs, $c, 2);

                        if (($c == $strlenChrs) || (($chrs[$c] == ',') && ($top['what'] == self::SERVICES_JSON_SLICE))) {
                            $slice = $this->substr8($chrs, $top['where'], ($c - $top['where']));
                            $stk[] = ['what' => self::SERVICES_JSON_SLICE, 'where' => ($c + 1), 'delim' => false];

                            if (reset($stk) == self::SERVICES_JSON_IN_ARR) {
                                $arr[] = $this->decode($slice);

                            } elseif (reset($stk) == self::SERVICES_JSON_IN_OBJ) {
                                $parts = [];
                                if (preg_match('/^\s*(["\'].*[^\\\]["\'])\s*:/Uis', $slice, $parts)) {
                                    $key = $this->decode($parts[1]);
                                    $val = $this->decode(trim(substr($slice, strlen($parts[0])), ", \t\n\r\0\x0B"));
                                    if ($this->use & self::SERVICES_JSON_LOOSE_TYPE) {
                                        $obj[$key] = $val;
                                    } else {
                                        $obj->$key = $val;
                                    }
                                } elseif (preg_match('/^\s*(\w+)\s*:/Uis', $slice, $parts)) {
                                    $key = $parts[1];
                                    $val = $this->decode(trim(substr($slice, strlen($parts[0])), ", \t\n\r\0\x0B"));

                                    if ($this->use & self::SERVICES_JSON_LOOSE_TYPE) {
                                        $obj[$key] = $val;
                                    } else {
                                        $obj->$key = $val;
                                    }
                                }
                            }

                        } elseif (($chrs[$c] == '"') || ($chrs[$c] == "'")) && ($top['what'] != self::SERVICES_JSON_IN_STR) {
                            $stk[] = ['what' => self::SERVICES_JSON_IN_STR, 'where' => $c, 'delim' => $chrs[$c]];
                        } elseif (($chrs[$c] == $top['delim']) &&
                                 ($top['what'] == self::SERVICES_JSON_IN_STR) &&
                                 (($this->strlen8($this->substr8($chrs, 0, $c)) - $this->strlen8(rtrim($this->substr8($chrs, 0, $c), '\\'))) % 2 != 1)) {
                            array_pop($stk);
                        } elseif (($chrs[$c] == '[') &&
                                 in_array($top['what'], [self::SERVICES_JSON_SLICE, self::SERVICES_JSON_IN_ARR, self::SERVICES_JSON_IN_OBJ])) {
                            $stk[] = ['what' => self::SERVICES_JSON_IN_ARR, 'where' => $c, 'delim' => false];
                        } elseif (($chrs[$c] == ']') && ($top['what'] == self::SERVICES_JSON_IN_ARR)) {
                            array_pop($stk);
                        } elseif (($chrs[$c] == '{') &&
                                 in_array($top['what'], [self::SERVICES_JSON_SLICE, self::SERVICES_JSON_IN_ARR, self::SERVICES_JSON_IN_OBJ])) {
                            $stk[] = ['what' => self::SERVICES_JSON_IN_OBJ, 'where' => $c, 'delim' => false];
                        } elseif (($chrs[$c] == '}') && ($top['what'] == self::SERVICES_JSON_IN_OBJ)) {
                            array_pop($stk);
                        } elseif (($substrChrsC2 == '/*') &&
                                 in_array($top['what'], [self::SERVICES_JSON_SLICE, self::SERVICES_JSON_IN_ARR, self::SERVICES_JSON_IN_OBJ])) {
                            $stk[] = ['what' => self::SERVICES_JSON_IN_CMT, 'where' => $c, 'delim' => false];
                            $c++;
                        } elseif (($substrChrsC2 == '*/') && ($top['what'] == self::SERVICES_JSON_IN_CMT)) {
                            array_pop($stk);
                            $c++;

                            for ($i = $top['where']; $i <= $c; ++$i) {
                                $chrs = substr_replace($chrs, ' ', $i, 1);
                            }
                        }
                    }

                    if (reset($stk) == self::SERVICES_JSON_IN_ARR) {
                        return $arr;

                    } elseif (reset($stk) == self::SERVICES_JSON_IN_OBJ) {
                        return $obj;
                    }
                }
        }
    }

    public function isError($data, $code = null): bool
    {
        if (class_exists('PEAR')) {
            return PEAR::isError($data, $code);
        } elseif (is_object($data) && (get_class($data) == 'ServicesJsonError' ||
                                 is_subclass_of($data, 'ServicesJsonError'))) {
            return true;
        }

        return false;
    }

    private function strlen8(string $str): int
    {
        if ($this->_mb_strlen) {
            return mb_strlen($str, "8bit");
        }
        return strlen($str);
    }

    private function substr8(string $string, int $start, int $length = false): string
    {
        if ($length === false) {
            $length = $this->strlen8($string) - $start;
        }
        if ($this->_mb_substr) {
            return mb_substr($string, $start, $length, "8bit");
        }
        return substr($string, $start, $length);
    }
}

class ServicesJsonError extends Exception
{
    public function __construct(string $message = 'unknown error', int $code = null, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}