<?php
/**
 * Classes, which help reading streams of data from files.
 * Based on the classes from Danilo Segan <danilo@kvota.net>
 *
 * @version $Id: streams.php 718 2012-10-31 00:32:02Z nbachiyski $
 * @package pomo
 * @subpackage streams
 */

if (!class_exists('POMO_Reader')) :
    class POMO_Reader
    {
        public $endian = 'little';
        protected $_post = '';
        protected $_pos = 0;
        protected $is_overloaded;

        public function __construct()
        {
            $this->is_overloaded = ((ini_get("mbstring.func_overload") & 2) != 0) && function_exists('mb_substr');
        }

        /**
         * Sets the endianness of the file.
         *
         * @param string $endian 'big' or 'little'
         */
        public function setEndian(string $endian): void
        {
            $this->endian = $endian;
        }

        /**
         * Reads a 32bit Integer from the Stream
         *
         * @return mixed The integer, corresponding to the next 32 bits from
         * 	the stream of false if there are not enough bytes or on error
         */
        public function readint32(): mixed
        {
            $bytes = $this->read(4);
            if (4 != $this->strlen($bytes)) {
                return false;
            }
            $endianLetter = ('big' == $this->endian) ? 'N' : 'V';
            $int = unpack($endianLetter, $bytes);
            return array_shift($int);
        }

        /**
         * Reads an array of 32-bit Integers from the Stream
         *
         * @param int $count How many elements should be read
         * @return mixed Array of integers or false if there isn't
         * 	enough data or on error
         */
        public function readint32array(int $count): mixed
        {
            $bytes = $this->read(4 * $count);
            if (4 * $count != $this->strlen($bytes)) {
                return false;
            }
            $endianLetter = ('big' == $this->endian) ? 'N' : 'V';
            return unpack($endianLetter . $count, $bytes);
        }

        public function substr(string $string, int $start, int $length): string
        {
            if ($this->is_overloaded) {
                return mb_substr($string, $start, $length, 'ascii');
            } else {
                return substr($string, $start, $length);
            }
        }

        public function strlen(string $string): int
        {
            if ($this->is_overloaded) {
                return mb_strlen($string, 'ascii');
            } else {
                return strlen($string);
            }
        }

        public function str_split(string $string, int $chunk_size): array
        {
            if (!function_exists('str_split')) {
                $length = $this->strlen($string);
                $out = [];
                for ($i = 0; $i < $length; $i += $chunk_size) {
                    $out[] = $this->substr($string, $i, $chunk_size);
                }
                return $out;
            } else {
                return str_split($string, $chunk_size);
            }
        }

        public function pos(): int
        {
            return $this->_pos;
        }

        public function is_resource(): bool
        {
            return true;
        }

        public function close(): bool
        {
            return true;
        }

        abstract public function read(int $bytes): string;
        abstract public function seekto(int $pos): bool;
    }
endif;

if (!class_exists('POMO_FileReader')) :
    class POMO_FileReader extends POMO_Reader
    {
        protected $_f;

        public function __construct(string $filename)
        {
            parent::__construct();
            $this->_f = fopen($filename, 'rb');
        }

        public function read(int $bytes): string
        {
            return fread($this->_f, $bytes);
        }

        public function seekto(int $pos): bool
        {
            if (-1 == fseek($this->_f, $pos, SEEK_SET)) {
                return false;
            }
            $this->_pos = $pos;
            return true;
        }

        public function is_resource(): bool
        {
            return is_resource($this->_f);
        }

        public function feof(): bool
        {
            return feof($this->_f);
        }

        public function close(): bool
        {
            return fclose($this->_f);
        }

        public function read_all(): string
        {
            $all = '';
            while (!$this->feof()) {
                $all .= $this->read(4096);
            }
            return $all;
        }
    }
endif;

if (!class_exists('POMO_StringReader')) :
    /**
     * Provides file-like methods for manipulating a string instead
     * of a physical file.
     */
    class POMO_StringReader extends POMO_Reader
    {
        protected $_str = '';

        public function __construct(string $str = '')
        {
            parent::__construct();
            $this->_str = $str;
            $this->_pos = 0;
        }

        public function read(int $bytes): string
        {
            $data = $this->substr($this->_str, $this->_pos, $bytes);
            $this->_pos += $bytes;
            if ($this->strlen($this->_str) < $this->_pos) {
                $this->_pos = $this->strlen($this->_str);
            }
            return $data;
        }

        public function seekto(int $pos): bool
        {
            $this->_pos = $pos;
            if ($this->strlen($this->_str) < $this->_pos) {
                $this->_pos = $this->strlen($this->_str);
            }
            return $this->_pos;
        }

        public function length(): int
        {
            return $this->strlen($this->_str);
        }

        public function read_all(): string
        {
            return $this->substr($this->_str, $this->_pos, $this->strlen($this->_str));
        }
    }
endif;

if (!class_exists('POMO_CachedFileReader')) :
    /**
     * Reads the contents of the file in the beginning.
     */
    class POMO_CachedFileReader extends POMO_StringReader
    {
        public function __construct(string $filename)
        {
            parent::__construct();
            $this->_str = file_get_contents($filename);
            if (false === $this->_str) {
                return false;
            }
            $this->_pos = 0;
        }
    }
endif;

if (!class_exists('POMO_CachedIntFileReader')) :
    /**
     * Reads the contents of the file in the beginning.
     */
    class POMO_CachedIntFileReader extends POMO_CachedFileReader
    {
        public function __construct(string $filename)
        {
            parent::__construct($filename);
        }
    }
endif;