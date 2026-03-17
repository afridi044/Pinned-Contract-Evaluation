<?php
/**
 * Classes, which help reading streams of data from files.
 * Based on the classes from Danilo Segan <danilo@kvota.net>
 *
 * @version $Id: streams.php 718 2012-10-31 00:32:02Z nbachiyski $
 * @package pomo
 * @subpackage streams
 */

declare(strict_types=1);

if ( ! class_exists( 'POMO_Reader' ) ) :
	/**
	 * Abstract class for reading data from a stream.
	 */
	abstract class POMO_Reader {
		public string $endian = 'little';
		protected int $_pos = 0;

		public function __construct() {
			// The mbstring.func_overload check is removed as the directive was removed in PHP 8.0.
		}

		/**
		 * Sets the endianness of the file.
		 *
		 * @param string $endian 'big' or 'little'.
		 */
		public function setEndian( string $endian ): void {
			$this->endian = $endian;
		}

		/**
		 * Reads a specific number of bytes from the stream.
		 *
		 * @param int $bytes The number of bytes to read.
		 * @return string|false The read data or false on failure.
		 */
		abstract public function read( int $bytes ): string|false;

		/**
		 * Seeks to a specific position in the stream.
		 *
		 * @param int $pos The position to seek to.
		 * @return bool|int True on success, false on failure for file streams, or the new position for string streams.
		 */
		abstract public function seekto( int $pos ): bool|int;

		/**
		 * Reads a 32-bit Integer from the Stream.
		 *
		 * @return int|false The integer, corresponding to the next 32 bits from
		 *                   the stream, or false if there are not enough bytes or on error.
		 */
		public function readint32(): int|false {
			$bytes = $this->read( 4 );
			if ( false === $bytes || 4 !== strlen( $bytes ) ) {
				return false;
			}

			$endian_letter = ( 'big' === $this->endian ) ? 'N' : 'V';
			$int           = unpack( $endian_letter, $bytes );

			return is_array( $int ) ? array_shift( $int ) : false;
		}

		/**
		 * Reads an array of 32-bit Integers from the Stream.
		 *
		 * @param int $count How many elements should be read.
		 * @return array<int, int>|false Array of integers or false if there isn't
		 *                               enough data or on error.
		 */
		public function readint32array( int $count ): array|false {
			$bytes = $this->read( 4 * $count );
			if ( false === $bytes || ( 4 * $count ) !== strlen( $bytes ) ) {
				return false;
			}

			$endian_letter = ( 'big' === $this->endian ) ? 'N' : 'V';
			return unpack( $endian_letter . $count, $bytes );
		}

		/**
		 * @return int The current position in the stream.
		 */
		public function pos(): int {
			return $this->_pos;
		}

		/**
		 * @return bool Whether the stream is a resource.
		 */
		public function is_resource(): bool {
			return true;
		}

		/**
		 * @return bool True on success, false on failure.
		 */
		public function close(): bool {
			return true;
		}
	}
endif;

if ( ! class_exists( 'POMO_FileReader' ) ) :
	/**
	 * Reads data from a file.
	 */
	class POMO_FileReader extends POMO_Reader {
		/**
		 * The file handle.
		 *
		 * @var resource|false
		 */
		private mixed $_f;

		/**
		 * @param string $filename Path to the file.
		 * @throws \RuntimeException If the file cannot be opened.
		 */
		public function __construct( string $filename ) {
			parent::__construct();
			$this->_f = @fopen( $filename, 'rb' );
			if ( false === $this->_f ) {
				throw new \RuntimeException( "Cannot open file '$filename' for reading." );
			}
		}

		public function read( int $bytes ): string|false {
			return fread( $this->_f, $bytes );
		}

		public function seekto( int $pos ): bool {
			if ( -1 === fseek( $this->_f, $pos, SEEK_SET ) ) {
				return false;
			}
			$this->_pos = $pos;
			return true;
		}

		public function is_resource(): bool {
			return is_resource( $this->_f );
		}

		public function feof(): bool {
			return feof( $this->_f );
		}

		public function close(): bool {
			if ( is_resource( $this->_f ) ) {
				return fclose( $this->_f );
			}
			return false;
		}

		public function read_all(): string {
			$all = '';
			while ( ! $this->feof() ) {
				$chunk = $this->read( 4096 );
				if ( false === $chunk ) {
					break;
				}
				$all .= $chunk;
			}
			return $all;
		}
	}
endif;

if ( ! class_exists( 'POMO_StringReader' ) ) :
	/**
	 * Provides file-like methods for manipulating a string instead
	 * of a physical file.
	 */
	class POMO_StringReader extends POMO_Reader {
		protected string $_str;

		public function __construct( string $str = '' ) {
			parent::__construct();
			$this->_str = $str;
			$this->_pos = 0;
		}

		public function read( int $bytes ): string {
			$data       = substr( $this->_str, $this->_pos, $bytes );
			$this->_pos += $bytes;
			if ( strlen( $this->_str ) < $this->_pos ) {
				$this->_pos = strlen( $this->_str );
			}
			return $data;
		}

		public function seekto( int $pos ): int {
			$this->_pos = $pos;
			if ( strlen( $this->_str ) < $this->_pos ) {
				$this->_pos = strlen( $this->_str );
			}
			return $this->_pos;
		}

		public function length(): int {
			return strlen( $this->_str );
		}

		public function read_all(): string {
			return substr( $this->_str, $this->_pos );
		}
	}
endif;

if ( ! class_exists( 'POMO_CachedFileReader' ) ) :
	/**
	 * Reads the contents of a file into a string and uses POMO_StringReader
	 * to read from it.
	 */
	class POMO_CachedFileReader extends POMO_StringReader {
		/**
		 * @param string $filename Path to the file.
		 * @throws \RuntimeException If the file cannot be read.
		 */
		public function __construct( string $filename ) {
			$contents = file_get_contents( $filename );
			if ( false === $contents ) {
				throw new \RuntimeException( "Could not read file: $filename" );
			}
			parent::__construct( $contents );
		}
	}
endif;

if ( ! class_exists( 'POMO_CachedIntFileReader' ) ) :
	/**
	 * A class that is functionally identical to its parent.
	 * It is preserved for backward compatibility.
	 */
	class POMO_CachedIntFileReader extends POMO_CachedFileReader {
		// The original constructor was redundant and is now implicitly inherited.
	}
endif;