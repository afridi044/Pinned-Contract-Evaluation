<?php
declare(strict_types=1);

if (!class_exists('POMO_Reader')) {
    class POMO_Reader
    {
        protected string $endian = 'little';
        protected string $_post = '';
        protected bool $is_overloaded = false;
        protected int $_pos = 0;

        public function __construct()
        {
            $this->is_overloaded = (((int) ini_get('mbstring.func_overload') & 2) !== 0) && function_exists('mb_substr');
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
         * Reads a 32bit Integer from the Stream.
         *
         * @return int|false
         */
        public function readint32(): int|false
        {
            $bytes = $this->read(4);

            if (!is_string($bytes) || $this->strlen($bytes) !== 4) {
                return false;
            }

            $endianLetter = $this->endian === 'big' ? 'N' : 'V';
            /** @var array<int, int> $int */
            $int = unpack($endianLetter, $bytes);

            return $int !== false ? array_shift($int) : false;
        }

        /**
         * Reads an array of 32-bit Integers from the Stream.
         *
         * @param int $count How many elements should be read.
         * @return array<int, int>|false
         */
        public function readint32array(int $count): array|false
        {
            $bytes = $this->read(4 * $count);

            if (!is_string($bytes) || $this->strlen($bytes) !== 4 * $count) {
                return false;
            }

            $endianLetter = $this->endian === 'big' ? 'N' : 'V';
            $result = unpack($endianLetter . $count, $bytes);

            return $result !== false ? $result : false;
        }

        public function substr(string $string, int $start, int $length): string
        {
            return $this->is_overloaded
                ? mb_substr($string, $start, $length, 'ASCII')
                : substr($string, $start, $length);
        }

        public function strlen(string $string): int
        {
            return $this->is_overloaded
                ? mb_strlen($string, 'ASCII')
                : strlen($string);
        }

        /**
         * @return array<int, string>
         */
        public function str_split(string $string, int $chunk_size): array
        {
            if ($chunk_size <= 0) {
                return [$string];
            }

            return str_split($string, $chunk_size);
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

        /**
         * Placeholder for subclasses to implement.
         *
         * @param int $bytes
         * @return string|false
         */
        protected function read(int $bytes): string|false
        {
            return false;
        }
    }
}

if (!class_exists('POMO_FileReader')) {
    class POMO_FileReader extends POMO_Reader
    {
        /** @var resource|null */
        protected $_f = null;

        public function __construct(string $filename)
        {
            parent::__construct();
            $this->_f = @fopen($filename, 'rb');
        }

        public function read(int $bytes): string|false
        {
            if (!is_resource($this->_f)) {
                return false;
            }

            $data = fread($this->_f, $bytes);

            if ($data !== false) {
                $this->_pos += strlen($data);
            }

            return $data;
        }

        public function seekto(int $pos): bool
        {
            if (!is_resource($this->_f)) {
                return false;
            }

            if (fseek($this->_f, $pos, SEEK_SET) === -1) {
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
            return is_resource($this->_f) ? feof($this->_f) : true;
        }

        public function close(): bool
        {
            if (is_resource($this->_f)) {
                $result = fclose($this->_f);
                $this->_f = null;

                return $result;
            }

            return true;
        }

        public function read_all(): string
        {
            if (!is_resource($this->_f)) {
                return '';
            }

            $all = '';

            while (!$this->feof()) {
                $chunk = $this->read(4096);

                if ($chunk === false || $chunk === '') {
                    break;
                }

                $all .= $chunk;
            }

            return $all;
        }
    }
}

if (!class_exists('POMO_StringReader')) {
    /**
     * Provides file-like methods for manipulating a string instead
     * of a physical file.
     */
    class POMO_StringReader extends POMO_Reader
    {
        protected string $_str = '';

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

            $length = $this->strlen($this->_str);

            if ($this->_pos > $length) {
                $this->_pos = $length;
            }

            return $data;
        }

        public function seekto(int $pos): int
        {
            $length = $this->strlen($this->_str);
            $this->_pos = max(0, min($pos, $length));

            return $this->_pos;
        }

        public function length(): int
        {
            return $this->strlen($this->_str);
        }

        public function read_all(): string
        {
            $length = $this->strlen($this->_str);
            $remaining = max(0, $length - $this->_pos);

            return $this->substr($this->_str, $this->_pos, $remaining);
        }
    }
}

if (!class_exists('POMO_CachedFileReader')) {
    /**
     * Reads the contents of the file in the beginning.
     */
    class POMO_CachedFileReader extends POMO_StringReader
    {
        public function __construct(string $filename)
        {
            parent::__construct();
            $contents = @file_get_contents($filename);

            if ($contents === false) {
                $this->_str = '';
                $this->_pos = 0;

                return;
            }

            $this->_str = $contents;
            $this->_pos = 0;
        }
    }
}

if (!class_exists('POMO_CachedIntFileReader')) {
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
}
?>