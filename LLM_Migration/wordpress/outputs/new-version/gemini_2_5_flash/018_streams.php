<?php
declare(strict_types=1);

/**
 * Classes, which help reading streams of data from files.
 * Based on the classes from Danilo Segan <danilo@kvota.net>
 */
class POMO_Reader
{
    public string $endian = 'little';
    protected int $_pos = 0; // Changed to protected as subclasses access it directly

    public function __construct()
    {
        // The 'mbstring.func_overload' INI setting is deprecated since PHP 7.2 and removed in PHP 8.0.
        // The conditional logic for mb_substr/mb_strlen is no longer necessary.
        // Standard substr/strlen will be used, which operate on bytes, consistent with 'ascii' intent.
        // $_pos is initialized in the property declaration.
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
     * Reads a 32-bit Integer from the Stream.
     *
     * @return int|false The integer, corresponding to the next 32 bits from
     * 	the stream, or false if there are not enough bytes or on error.
     */
    public function readint32(): int|false
    {
        $bytes = $this->read(4);
        if (4 !== $this->strlen($bytes)) {
            return false;
        }
        $endian_letter = ('big' === $this->endian) ? 'N' : 'V';
        $int = unpack($endian_letter, $bytes);
        if ($int === false) { // unpack can return false on error
            return false;
        }
        return array_shift($int);
    }

    /**
     * Reads an array of 32-bit Integers from the Stream.
     *
     * @param int $count How many elements should be read.
     * @return array<int>|false Array of integers or false if there isn't
     * 	enough data or on error.
     */
    public function readint32array(int $count): array|false
    {
        $bytes = $this->read(4 * $count);
        if (4 * $count !== $this->strlen($bytes)) {
            return false;
        }
        $endian_letter = ('big' === $this->endian) ? 'N' : 'V';
        $result = unpack($endian_letter . $count, $bytes);
        if ($result === false) { // unpack can return false on error
            return false;
        }
        // unpack with 'N' or 'V' format codes returns an array with 1-based integer keys.
        // Re-index to ensure 0-based integer keys for consistency.
        return array_values($result);
    }

    /**
     * Reads bytes from the stream. This method should be implemented by subclasses.
     *
     * @param int $bytes The number of bytes to read.
     * @return string|false The read bytes, or false on error/end of stream.
     */
    protected function read(int $bytes): string|false
    {
        // In the base class, there's no stream to read from.
        // Returning false indicates failure to read, consistent with subclasses' error handling.
        return false;
    }

    public function substr(string $string, int $start, int $length): string
    {
        // 'mbstring.func_overload' is removed, so directly use substr.
        return substr($string, $start, $length);
    }

    public function strlen(string $string): int
    {
        // 'mbstring.func_overload' is removed, so directly use strlen.
        return strlen($string);
    }

    /**
     * Splits a string into smaller chunks.
     *
     * @param string $string The input string.
     * @param int $chunk_size The maximum length of the chunks.
     * @return array<string> An array of strings.
     */
    public function str_split(string $string, int $chunk_size): array
    {
        // str_split is available since PHP 5.0, no need for function_exists check.
        return str_split($string, $chunk_size);
    }

    public function pos(): int
    {
        return $this->_pos;
    }

    public function close(): bool
    {
        // Base reader doesn't manage a resource to close.
        return true;
    }
}

class POMO_FileReader extends POMO_Reader
{
    /** @var resource|false The file handle resource, or false if fopen failed or file is closed. */
    private $f;

    /**
     * @param string $filename The path to the file.
     * @throws \RuntimeException If the file cannot be opened.
     */
    public function __construct(string $filename)
    {
        parent::__construct();
        $this->f = fopen($filename, 'rb');
        if ($this->f === false) {
            throw new \RuntimeException("Failed to open file: {$filename}");
        }
    }

    /**
     * @inheritDoc
     */
    protected function read(int $bytes): string|false
    {
        if ($this->f === false) {
            return false; // File not open or already closed
        }
        return fread($this->f, $bytes);
    }

    public function seekto(int $pos): bool
    {
        if ($this->f === false) {
            return false; // File not open or already closed
        }
        if (-1 === fseek($this->f, $pos, SEEK_SET)) {
            return false;
        }
        $this->_pos = $pos;
        return true;
    }

    public function is_resource(): bool
    {
        return is_resource($this->f);
    }

    public function feof(): bool
    {
        if ($this->f === false) {
            return true; // Consider end of file if not open
        }
        return feof($this->f);
    }

    public function close(): bool
    {
        if ($this->f === false) {
            return true; // Already closed or never opened successfully
        }
        $closed = fclose($this->f);
        $this->f = false; // Mark as closed
        return $closed;
    }

    public function read_all(): string
    {
        if ($this->f === false) {
            return ''; // File not open
        }
        $all = '';
        while (!$this->feof()) {
            $chunk = $this->read(4096);
            if ($chunk === false) { // Handle potential read error
                break;
            }
            $all .= $chunk;
        }
        return $all;
    }
}

/**
 * Provides file-like methods for manipulating a string instead
 * of a physical file.
 */
class POMO_StringReader extends POMO_Reader
{
    protected string $_str = ''; // Changed to protected as subclasses access it directly

    public function __construct(string $str = '')
    {
        parent::__construct();
        $this->_str = $str;
        // $_pos is already 0 from parent::__construct()
    }

    /**
     * @inheritDoc
     */
    protected function read(int $bytes): string
    {
        $data = $this->substr($this->_str, $this->_pos, $bytes);
        $this->_pos += $bytes;
        // Ensure _pos does not exceed string length
        if ($this->_pos > $this->strlen($this->_str)) {
            $this->_pos = $this->strlen($this->_str);
        }
        return $data;
    }

    public function seekto(int $pos): int
    {
        $this->_pos = $pos;
        // Ensure _pos does not exceed string length
        if ($this->_pos > $this->strlen($this->_str)) {
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
        // Read from current position to the end of the string.
        // The length to read is the total length minus the current position.
        return $this->substr($this->_str, $this->_pos, $this->strlen($this->_str) - $this->_pos);
    }
}

/**
 * Reads the contents of the file into memory at the beginning.
 */
class POMO_CachedFileReader extends POMO_StringReader
{
    /**
     * @param string $filename The path to the file.
     * @throws \RuntimeException If the file cannot be read.
     */
    public function __construct(string $filename)
    {
        parent::__construct(); // Calls POMO_StringReader's constructor
        $contents = file_get_contents($filename);
        if ($contents === false) {
            // Constructors cannot return false in modern PHP. Throw an exception instead.
            throw new \RuntimeException("Failed to read file: {$filename}");
        }
        $this->_str = $contents;
        // $_pos is already 0 from parent::__construct()
    }
}

/**
 * Reads the contents of the file into memory at the beginning.
 * This class is functionally identical to POMO_CachedFileReader.
 */
class POMO_CachedIntFileReader extends POMO_CachedFileReader
{
    /**
     * @param string $filename The path to the file.
     * @throws \RuntimeException If the file cannot be read.
     */
    public function __construct(string $filename)
    {
        parent::__construct($filename);
    }
}