<?php
/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
/////////////////////////////////////////////////////////////////
//                                                             //
// Please see readme.txt for more information                  //
//                                                            ///
/////////////////////////////////////////////////////////////////

// define a constant rather than looking up every time it is needed
if (!defined('GETID3_OS_ISWINDOWS')) {
	define('GETID3_OS_ISWINDOWS', str_starts_with(strtoupper(PHP_OS), 'WIN'));
}
// Get base path of getID3() - ONCE
if (!defined('GETID3_INCLUDEPATH')) {
	define('GETID3_INCLUDEPATH', __DIR__.DIRECTORY_SEPARATOR);
}

// attempt to define temp dir as something flexible but reliable
$temp_dir = ini_get('upload_tmp_dir') ?: '';
if ($temp_dir !== '' && (!is_dir($temp_dir) || !is_readable($temp_dir))) {
	$temp_dir = '';
}
if ($temp_dir === '' && function_exists('sys_get_temp_dir')) {
	// PHP v5.2.1+
	// sys_get_temp_dir() may give inaccessible temp dir, e.g. with open_basedir on virtual hosts
	$temp_dir = sys_get_temp_dir();
}
$temp_dir = $temp_dir !== '' ? realpath($temp_dir) : '';
if ($temp_dir === false) {
	$temp_dir = '';
}
$open_basedir = ini_get('open_basedir');
if (!empty($open_basedir)) {
	// e.g. "/var/www/vhosts/getid3.org/httpdocs/:/tmp/"
	$temp_dir     = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $temp_dir);
	$open_basedir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $open_basedir);
	if ($temp_dir !== '' && !str_ends_with($temp_dir, DIRECTORY_SEPARATOR)) {
		$temp_dir .= DIRECTORY_SEPARATOR;
	}
	$found_valid_tempdir = false;
	$open_basedirs = explode(PATH_SEPARATOR, $open_basedir);
	foreach ($open_basedirs as $basedir) {
		if ($basedir !== '' && !str_ends_with($basedir, DIRECTORY_SEPARATOR)) {
			$basedir .= DIRECTORY_SEPARATOR;
		}
		if (preg_match('#^'.preg_quote($basedir, '#').'#', $temp_dir)) {
			$found_valid_tempdir = true;
			break;
		}
	}
	if (!$found_valid_tempdir) {
		$temp_dir = '';
	}
	unset($open_basedirs, $found_valid_tempdir, $basedir);
}
if (!$temp_dir) {
	$temp_dir = '*'; // invalid directory name should force tempnam() to use system default temp dir
}
// $temp_dir = '/something/else/';  // feel free to override temp dir here if it works better for your system
define('GETID3_TEMP_DIR', $temp_dir);
unset($open_basedir, $temp_dir);

// End: Defines


class getID3
{
	// public: Settings
	public string $encoding        = 'UTF-8';        // CASE SENSITIVE! - i.e. (must be supported by iconv()). Examples:  ISO-8859-1  UTF-8  UTF-16  UTF-16BE
	public string $encoding_id3v1  = 'ISO-8859-1';   // Should always be 'ISO-8859-1', but some tags may be written in other encodings such as 'EUC-CN' or 'CP1252'

	// public: Optional tag checks - disable for speed.
	public bool $option_tag_id3v1         = true;  // Read and process ID3v1 tags
	public bool $option_tag_id3v2         = true;  // Read and process ID3v2 tags
	public bool $option_tag_lyrics3       = true;  // Read and process Lyrics3 tags
	public bool $option_tag_apetag        = true;  // Read and process APE tags
	public bool $option_tags_process      = true;  // Copy tags to root key 'tags' and encode to $this->encoding
	public bool $option_tags_html         = true;  // Copy tags to root key 'tags_html' properly translated from various encodings to HTML entities

	// public: Optional tag/comment calucations
	public bool $option_extra_info        = true;  // Calculate additional info such as bitrate, channelmode etc

	// public: Optional handling of embedded attachments (e.g. images)
	public bool $option_save_attachments  = true; // defaults to true (ATTACHMENTS_INLINE) for backward compatibility

	// public: Optional calculations
	public bool $option_md5_data          = false; // Get MD5 sum of data part - slow
	public bool $option_md5_data_source   = false; // Use MD5 of source file if availble - only FLAC and OptimFROG
	public bool $option_sha1_data         = false; // Get SHA1 sum of data part - slow
	public ?bool $option_max_2gb_check    = null;  // Check whether file is larger than 2GB and thus not supported by 32-bit PHP (null: auto-detect based on PHP_INT_MAX)

	// public: Read buffer size in bytes
	public int $option_fread_buffer_size = 32768;

	// Public variables
	public ?string $filename = null;                 // Filename of file being analysed.
	public $fp = null;                               // Filepointer to file being analysed.
	public array $info = [];                         // Result array.
	public string $tempdir = GETID3_TEMP_DIR;

	// Protected variables
	protected string $startup_error   = '';
	protected string $startup_warning = '';
	protected int|string|false $memory_limit    = 0;

	const VERSION           = '1.9.7-20130705';
	const FREAD_BUFFER_SIZE = 32768;

	const ATTACHMENTS_NONE   = false;
	const ATTACHMENTS_INLINE = true;

	// public: constructor
	public function __construct() {

		// Check for PHP version
		$required_php_version = '5.0.5';
		if (version_compare(PHP_VERSION, $required_php_version, '<')) {
			$this->startup_error .= 'getID3() requires PHP v'.$required_php_version.' or higher - you are running v'.PHP_VERSION;
			return;
		}

		// Check memory
		$this->memory_limit = ini_get('memory_limit');
		if (is_string($this->memory_limit) && preg_match('#([0-9]+)M#i', $this->memory_limit, $matches)) {
			// could be stored as "16M" rather than 16777216 for example
			$this->memory_limit = (int) $matches[1] * 1048576;
		} elseif (is_string($this->memory_limit) && preg_match('#([0-9]+)G#i', $this->memory_limit, $matches)) { // The 'G' modifier is available since PHP 5.1.0
			// could be stored as "2G" rather than 2147483648 for example
			$this->memory_limit = (int) $matches[1] * 1073741824;
		}
		if ($this->memory_limit <= 0) {
			// memory limits probably disabled
		} elseif ($this->memory_limit <= 4194304) {
			$this->startup_error .= 'PHP has less than 4MB available memory and will very likely run out. Increase memory_limit in php.ini';
		} elseif ($this->memory_limit <= 12582912) {
			$this->startup_warning .= 'PHP has less than 12MB available memory and might run out if all modules are loaded. Increase memory_limit in php.ini';
		}

		// Check safe_mode off
		if (preg_match('#(1|ON)#i', (string) ini_get('safe_mode'))) {
			$this->warning('WARNING: Safe mode is on, shorten support disabled, md5data/sha1data for ogg vorbis disabled, ogg vorbos/flac tag writing disabled.');
		}

		if ((int) ini_get('mbstring.func_overload') > 0) {
			$this->warning('WARNING: php.ini contains "mbstring.func_overload = '.ini_get('mbstring.func_overload').'", this may break things.');
		}

		// Check for magic_quotes_runtime
		if (function_exists('get_magic_quotes_runtime') && get_magic_quotes_runtime()) {
			$this->startup_error('magic_quotes_runtime must be disabled before running getID3(). Surround getid3 block by set_magic_quotes_runtime(0) and set_magic_quotes_runtime(1).');
			return;
		}

		// Check for magic_quotes_gpc
		if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
			$this->startup_error('magic_quotes_gpc must be disabled before running getID3(). Surround getid3 block by set_magic_quotes_gpc(0) and set_magic_quotes_gpc(1).');
			return;
		}

		// Load support library
		if (!include_once(GETID3_INCLUDEPATH.'getid3.lib.php')) {
			$this->startup_error .= 'getid3.lib.php is missing or corrupt';
		}

		if ($this->option_max_2gb_check === null) {
			$this->option_max_2gb_check = (PHP_INT_MAX <= 2147483647);
		}


		// Needed for Windows only:
		// Define locations of helper applications for Shorten, VorbisComment, MetaFLAC
		//   as well as other helper functions such as head, tail, md5sum, etc
		// This path cannot contain spaces, but the below code will attempt to get the
		//   8.3-equivalent path automatically
		// IMPORTANT: This path must include the trailing slash
		if (GETID3_OS_ISWINDOWS && !defined('GETID3_HELPERAPPSDIR')) {

			$helperappsdir = GETID3_INCLUDEPATH.'..'.DIRECTORY_SEPARATOR.'helperapps'; // must not have any space in this path

			if (!is_dir($helperappsdir)) {
				$this->startup_warning .= '"'.$helperappsdir.'" cannot be defined as GETID3_HELPERAPPSDIR because it does not exist';
			} elseif (strpos((string) realpath($helperappsdir), ' ') !== false) {
				$DirPieces = explode(DIRECTORY_SEPARATOR, (string) realpath($helperappsdir));
				$path_so_far = [];
				foreach ($DirPieces as $key => $value) {
					if (strpos($value, ' ') !== false) {
						if (!empty($path_so_far)) {
							$commandline = 'dir /x '.escapeshellarg(implode(DIRECTORY_SEPARATOR, $path_so_far));
							$dir_listing = `$commandline`;
							$lines = preg_split('/\r\n|\n|\r/', $dir_listing);
							foreach ($lines as $line) {
								$line = trim($line);
								if ($line === '') {
									continue;
								}
								if (preg_match('#^([0-9/]{10}) +([0-9:]{4,5}( [AP]M)?) +(<DIR>|[0-9,]+) +([^ ]{0,11}) +(.+)$#', $line, $matches)) {
									[, $date, $time, $ampm, $filesize, $shortname, $filename] = $matches;
									if ((strtoupper($filesize) === '<DIR>') && (strtolower($filename) === strtolower($value))) {
										$value = $shortname;
									}
								}
							}
						} else {
							$this->startup_warning .= 'GETID3_HELPERAPPSDIR must not have any spaces in it - use 8dot3 naming convention if neccesary. You can run "dir /x" from the commandline to see the correct 8.3-style names.';
						}
					}
					$path_so_far[] = $value;
				}
				$helperappsdir = implode(DIRECTORY_SEPARATOR, $path_so_far);
			}
			define('GETID3_HELPERAPPSDIR', $helperappsdir.DIRECTORY_SEPARATOR);
		}
	}

	public function version(): string {
		return self::VERSION;
	}

	public function fread_buffer_size(): int {
		return $this->option_fread_buffer_size;
	}


	// public: setOption
	public function setOption(array $optArray): bool {
		if (empty($optArray)) {
			return false;
		}
		foreach ($optArray as $opt => $val) {
			$opt = (string) $opt;
			if (!property_exists($this, $opt)) {
				continue;
			}
			$this->$opt = $val;
		}
		return true;
	}


	public function openfile(string $filename): bool {
		try {
			if (!empty($this->startup_error)) {
				throw new getid3_exception($this->startup_error);
			}
			if (!empty($this->startup_warning)) {
				$this->warning($this->startup_warning);
			}

			// init result array and set parameters
			$this->filename = $filename;
			$this->info = [];
			$this->info['GETID3_VERSION']   = $this->version();
			$this->info['php_memory_limit'] = $this->memory_limit;

			// remote files not supported
			if (preg_match('/^(ht|f)tp:\/\//', $filename)) {
				throw new getid3_exception('Remote files are not supported - please copy the file locally first');
			}

			$filename = str_replace('/', DIRECTORY_SEPARATOR, $filename);
			$filename = preg_replace('#(.+)'.preg_quote(DIRECTORY_SEPARATOR).' {2,}#Ux', '\1'.DIRECTORY_SEPARATOR, $filename);

			// open local file
			if (is_readable($filename) && is_file($filename) && ($this->fp = fopen($filename, 'rb'))) {
				// great
			} else {
				throw new getid3_exception('Could not open "'.$filename.'" (does not exist, or is not a file)');
			}

			$this->info['filesize'] = filesize($filename);
			// set redundant parameters - might be needed in some include file
			$this->info['filename']     = basename($filename);
			$this->info['filepath']     = str_replace('\\', '/', (string) realpath(dirname($filename)));
			$this->info['filenamepath'] = $this->info['filepath'].'/'.$this->info['filename'];


			// option_max_2gb_check
			if ($this->option_max_2gb_check) {
				// PHP (32-bit all, and 64-bit Windows) doesn't support integers larger than 2^31 (~2GB)
				// filesize() simply returns (filesize % (pow(2, 32)), no matter the actual filesize
				// ftell() returns 0 if seeking to the end is beyond the range of unsigned integer
				$fseek = fseek($this->fp, 0, SEEK_END);
				if (($fseek < 0) || (($this->info['filesize'] != 0) && (ftell($this->fp) == 0)) ||
					($this->info['filesize'] < 0) ||
					(ftell($this->fp) < 0)) {
						$real_filesize = getid3_lib::getFileSizeSyscall($this->info['filenamepath']);

						if ($real_filesize === false) {
							unset($this->info['filesize']);
							fclose($this->fp);
							throw new getid3_exception('Unable to determine actual filesize. File is most likely larger than '.round(PHP_INT_MAX / 1073741824).'GB and is not supported by PHP.');
						} elseif (getid3_lib::intValueSupported($real_filesize)) {
							unset($this->info['filesize']);
							fclose($this->fp);
							throw new getid3_exception('PHP seems to think the file is larger than '.round(PHP_INT_MAX / 1073741824).'GB, but filesystem reports it as '.number_format($real_filesize, 3).'GB, please report to info@getid3.org');
						}
						$this->info['filesize'] = $real_filesize;
						$this->warning('File is larger than '.round(PHP_INT_MAX / 1073741824).'GB (filesystem reports it as '.number_format($real_filesize, 3).'GB) and is not properly supported by PHP.');
				}
			}

			// set more parameters
			$this->info['avdataoffset']        = 0;
			$this->info['avdataend']           = $this->info['filesize'];
			$this->info['fileformat']          = '';                // filled in later
			$this->info['audio']['dataformat'] = '';                // filled in later, unset if not used
			$this->info['video']['dataformat'] = '';                // filled in later, unset if not used
			$this->info['tags']                = [];                // filled in later, unset if not used
			$this->info['error']               = [];                // filled in later, unset if not used
			$this->info['warning']             = [];                // filled in later, unset if not used
			$this->info['comments']            = [];                // filled in later, unset if not used
			$this->info['encoding']            = $this->encoding;   // required by id3v2 and iso modules - can be unset at the end if desired

			return true;

		} catch (Exception $e) {
			$this->error($e->getMessage());
		}
		return false;
	}

	// public: analyze file
	public function analyze(string $filename): array {
		try {
			if (!$this->openfile($filename)) {
				return $this->info;
			}

			// Handle tags
			foreach (['id3v2'=>'id3v2', 'id3v1'=>'id3v1', 'apetag'=>'ape', 'lyrics3'=>'lyrics3'] as $tag_name => $tag_key) {
				$option_tag = 'option_tag_'.$tag_name;
				if ($this->$option_tag) {
					$this->include_module('tag.'.$tag_name);
					try {
						$tag_class = 'getid3_'.$tag_name;
						$tag = new $tag_class($this);
						$tag->Analyze();
					}
					catch (getid3_exception $e) {
						throw $e;
					}
				}
			}
			if (isset($this->info['id3v2']['tag_offset_start'])) {
				$this->info['avdataoffset'] = max($this->info['avdataoffset'], $this->info['id3v2']['tag_offset_end']);
			}
			foreach (['id3v1'=>'id3v1', 'apetag'=>'ape', 'lyrics3'=>'lyrics3'] as $tag_name => $tag_key) {
				if (isset($this->info[$tag_key]['tag_offset_start'])) {
					$this->info['avdataend'] = min($this->info['avdataend'], $this->info[$tag_key]['tag_offset_start']);
				}
			}

			// ID3v2 detection (NOT parsing), even if ($this->option_tag_id3v2 == false) done to make fileformat easier
			if (!$this->option_tag_id3v2) {
				fseek($this->fp, 0, SEEK_SET);
				$header = fread($this->fp, 10);
				if ((substr($header, 0, 3) == 'ID3') && (strlen($header) == 10)) {
					$this->info['id3v2']['header']        = true;
					$this->info['id3v2']['majorversion']  = ord($header[3]);
					$this->info['id3v2']['minorversion']  = ord($header[4]);
					$this->info['avdataoffset']          += getid3_lib::BigEndian2Int(substr($header, 6, 4), 1) + 10; // length of ID3v2 tag in 10-byte header doesn't include 10-byte header length
				}
			}

			// read 32 kb file data
			fseek($this->fp, $this->info['avdataoffset'], SEEK_SET);
			$formattest = fread($this->fp, 32774) ?: '';

			// determine format
			$determined_format = $this->GetFileFormat($formattest, $filename);

			// unable to determine file format
			if (!$determined_format) {
				fclose($this->fp);
				return $this->error('unable to determine file format');
			}

			// check for illegal ID3 tags
			if (isset($determined_format['fail_id3']) && (in_array('id3v1', $this->info['tags'], true) || in_array('id3v2', $this->info['tags'], true))) {
				if ($determined_format['fail_id3'] === 'ERROR') {
					fclose($this->fp);
					return $this->error('ID3 tags not allowed on this file type.');
				} elseif ($determined_format['fail_id3'] === 'WARNING') {
					$this->warning('ID3 tags not allowed on this file type.');
				}
			}

			// check for illegal APE tags
			if (isset($determined_format['fail_ape']) && in_array('ape', $this->info['tags'], true)) {
				if ($determined_format['fail_ape'] === 'ERROR') {
					fclose($this->fp);
					return $this->error('APE tags not allowed on this file type.');
				} elseif ($determined_format['fail_ape'] === 'WARNING') {
					$this->warning('APE tags not allowed on this file type.');
				}
			}

			// set mime type
			$this->info['mime_type'] = $determined_format['mime_type'];

			// supported format signature pattern detected, but module deleted
			if (!file_exists(GETID3_INCLUDEPATH.$determined_format['include'])) {
				fclose($this->fp);
				return $this->error('Format not supported, module "'.$determined_format['include'].'" was removed.');
			}

			// module requires iconv support
			// Check encoding/iconv support
			if (!empty($determined_format['iconv_req']) && !function_exists('iconv') && !in_array($this->encoding, ['ISO-8859-1', 'UTF-8', 'UTF-16LE', 'UTF-16BE', 'UTF-16'], true)) {
				$errormessage = 'iconv() support is required for this module ('.$determined_format['include'].') for encodings other than ISO-8859-1, UTF-8, UTF-16LE, UTF16-BE, UTF-16. ';
				if (GETID3_OS_ISWINDOWS) {
					$errormessage .= 'PHP does not have iconv() support. Please enable php_iconv.dll in php.ini, and copy iconv.dll from c:/php/dlls to c:/windows/system32';
				} else {
					$errormessage .= 'PHP is not compiled with iconv() support. Please recompile with the --with-iconv switch';
				}
				return $this->error($errormessage);
			}

			// include module
			include_once(GETID3_INCLUDEPATH.$determined_format['include']);

			// instantiate module class
			$class_name = 'getid3_'.$determined_format['module'];
			if (!class_exists($class_name)) {
				return $this->error('Format not supported, module "'.$determined_format['include'].'" is corrupt.');
			}
			$class = new $class_name($this);
			$class->Analyze();
			unset($class);

			// close file
			fclose($this->fp);

			// process all tags - copy to 'tags' and convert charsets
			if ($this->option_tags_process) {
				$this->HandleAllTags();
			}

			// perform more calculations
			if ($this->option_extra_info) {
				$this->ChannelsBitratePlaytimeCalculations();
				$this->CalculateCompressionRatioVideo();
				$this->CalculateCompressionRatioAudio();
				$this->CalculateReplayGain();
				$this->ProcessAudioStreams();
			}

			// get the MD5 sum of the audio/video portion of the file - without ID3/APE/Lyrics3/etc header/footer tags
			if ($this->option_md5_data) {
				// do not calc md5_data if md5_data_source is present - set by flac only - future MPC/SV8 too
				if (!$this->option_md5_data_source || empty($this->info['md5_data_source'])) {
					$this->getHashdata('md5');
				}
			}

			// get the SHA1 sum of the audio/video portion of the file - without ID3/APE/Lyrics3/etc header/footer tags
			if ($this->option_sha1_data) {
				$this->getHashdata('sha1');
			}

			// remove undesired keys
			$this->CleanUp();

		} catch (Exception $e) {
			$this->error('Caught exception: '.$e->getMessage());
		}

		// return info array
		return $this->info;
	}


	// private: error handling
	public function error(string $message): array {
		$this->CleanUp();
		$this->info['error'] ??= [];
		$this->info['error'][] = $message;
		return $this->info;
	}


	// private: warning handling
	public function warning(string $message): bool {
		$this->info['warning'] ??= [];
		$this->info['warning'][] = $message;
		return true;
	}


	// private: CleanUp
	private function CleanUp(): bool {

		// remove possible empty keys
		$AVpossibleEmptyKeys = ['dataformat', 'bits_per_sample', 'encoder_options', 'streams', 'bitrate'];
		foreach ($AVpossibleEmptyKeys as $key) {
			if (empty($this->info['audio'][$key]) && isset($this->info['audio'][$key])) {
				unset($this->info['audio'][$key]);
			}
			if (empty($this->info['video'][$key]) && isset($this->info['video'][$key])) {
				unset($this->info['video'][$key]);
			}
		}

		// remove empty root keys
		if (!empty($this->info)) {
			foreach ($this->info as $key => $value) {
				if ($value === 0 || $value === '0') {
					continue;
				}
				if (empty($value)) {
					unset($this->info[$key]);
				}
			}
		}

		// remove meaningless entries from unknown-format files
		if (empty($this->info['fileformat'])) {
			if (isset($this->info['avdataoffset'])) {
				unset($this->info['avdataoffset']);
			}
			if (isset($this->info['avdataend'])) {
				unset($this->info['avdataend']);
			}
		}

		// remove possible duplicated identical entries
		if (!empty($this->info['error'])) {
			$this->info['error'] = array_values(array_unique($this->info['error']));
		}
		if (!empty($this->info['warning'])) {
			$this->info['warning'] = array_values(array_unique($this->info['warning']));
		}

		// remove "global variable" type keys
		unset($this->info['php_memory_limit']);

		return true;
	}


	// return array containing information about all supported formats
	public function GetFileFormatArray(): array {
		static $format_info = [];
		if (empty($format_info)) {
			$format_info = [

				// Audio formats

				// AC-3   - audio      - Dolby AC-3 / Dolby Digital
				'ac3'  => [
							'pattern'   => '^\x0B\x77',
							'group'     => 'audio',
							'module'    => 'ac3',
							'mime_type' => 'audio/ac3',
						],

				// AAC  - audio       - Advanced Audio Coding (AAC) - ADIF format
				'adif' => [
							'pattern'   => '^ADIF',
							'group'     => 'audio',
							'module'    => 'aac',
							'mime_type' => 'application/octet-stream',
							'fail_ape'  => 'WARNING',
						],

/*
				// AA   - audio       - Audible Audiobook
				'aa'   => array(
							'pattern'   => '^.{4}\x57\x90\x75\x36',
							'group'     => 'audio',
							'module'    => 'aa',
							'mime_type' => 'audio/audible',
						),
*/
				// AAC  - audio       - Advanced Audio Coding (AAC) - ADTS format (very similar to MP3)
				'adts' => [
							'pattern'   => '^\xFF[\xF0-\xF1\xF8-\xF9]',
							'group'     => 'audio',
							'module'    => 'aac',
							'mime_type' => 'application/octet-stream',
							'fail_ape'  => 'WARNING',
						],


				// AU   - audio       - NeXT/Sun AUdio (AU)
				'au'   => [
							'pattern'   => '^\.snd',
							'group'     => 'audio',
							'module'    => 'au',
							'mime_type' => 'audio/basic',
						],

				// AVR  - audio       - Audio Visual Research
				'avr'  => [
							'pattern'   => '^2BIT',
							'group'     => 'audio',
							'module'    => 'avr',
							'mime_type' => 'application/octet-stream',
						],

				// BONK - audio       - Bonk v0.9+
				'bonk' => [
							'pattern'   => '^\x00(BONK|INFO|META| ID3)',
							'group'     => 'audio',
							'module'    => 'bonk',
							'mime_type' => 'audio/xmms-bonk',
						],

				// DSS  - audio       - Digital Speech Standard
				'dss'  => [
							'pattern'   => '^[\x02-\x03]ds[s2]',
							'group'     => 'audio',
							'module'    => 'dss',
							'mime_type' => 'application/octet-stream',
						],

				// DTS  - audio       - Dolby Theatre System
				'dts'  => [
							'pattern'   => '^\x7F\xFE\x80\x01',
							'group'     => 'audio',
							'module'    => 'dts',
							'mime_type' => 'audio/dts',
						],

				// FLAC - audio       - Free Lossless Audio Codec
				'flac' => [
							'pattern'   => '^fLaC',
							'group'     => 'audio',
							'module'    => 'flac',
							'mime_type' => 'audio/x-flac',
						],

				// LA   - audio       - Lossless Audio (LA)
				'la'   => [
							'pattern'   => '^LA0[2-4]',
							'group'     => 'audio',
							'module'    => 'la',
							'mime_type' => 'application/octet-stream',
						],

				// LPAC - audio       - Lossless Predictive Audio Compression (LPAC)
				'lpac' => [
							'pattern'   => '^LPAC',
							'group'     => 'audio',
							'module'    => 'lpac',
							'mime_type' => 'application/octet-stream',
						],

				// MIDI - audio       - MIDI (Musical Instrument Digital Interface)
				'midi' => [
							'pattern'   => '^MThd',
							'group'     => 'audio',
							'module'    => 'midi',
							'mime_type' => 'audio/midi',
						],

				// MAC  - audio       - Monkey's Audio Compressor
				'mac'  => [
							'pattern'   => '^MAC ',
							'group'     => 'audio',
							'module'    => 'monkey',
							'mime_type' => 'application/octet-stream',
						],

// has been known to produce false matches in random files (e.g. JPEGs), leave out until more precise matching available
//				// MOD  - audio       - MODule (assorted sub-formats)
//				'mod'  => array(
//							'pattern'   => '^.{1080}(M\\.K\\.|M!K!|FLT4|FLT8|[5-9]CHN|[1-3][0-9]CH)',
//							'group'     => 'audio',
//							'module'    => 'mod',
//							'option'    => 'mod',
//							'mime_type' => 'audio/mod',
//						),

				// MOD  - audio       - MODule (Impulse Tracker)
				'it'   => [
							'pattern'   => '^IMPM',
							'group'     => 'audio',
							'module'    => 'mod',
							//'option'    => 'it',
							'mime_type' => 'audio/it',
						],

				// MOD  - audio       - MODule (eXtended Module, various sub-formats)
				'xm'   => [
							'pattern'   => '^Extended Module',
							'group'     => 'audio',
							'module'    => 'mod',
							//'option'    => 'xm',
							'mime_type' => 'audio/xm',
						],

				// MOD  - audio       - MODule (ScreamTracker)
				's3m'  => [
							'pattern'   => '^.{44}SCRM',
							'group'     => 'audio',
							'module'    => 'mod',
							//'option'    => 's3m',
							'mime_type' => 'audio/s3m',
						],

				// MPC  - audio       - Musepack / MPEGplus
				'mpc'  => [
							'pattern'   => '^(MPCK|MP\+|[\x00\x01\x10\x11\x40\x41\x50\x51\x80\x81\x90\x91\xC0\xC1\xD0\xD1][\x20-37][\x00\x20\x40\x60\x80\xA0\xC0\xE0])',
							'group'     => 'audio',
							'module'    => 'mpc',
							'mime_type' => 'audio/x-musepack',
						],

				// MP3  - audio       - MPEG-audio Layer 3 (very similar to AAC-ADTS)
				'mp3'  => [
							'pattern'   => '^\xFF[\xE2-\xE7\xF2-\xF7\xFA-\xFF][\x00-\x0B\x10-\x1B\x20-\x2B\x30-\x3B\x40-\x4B\x50-\x5B\x60-\x6B\x70-\x7B\x80-\x8B\x90-\x9B\xA0-\xAB\xB0-\xBB\xC0-\xCB\xD0-\xDB\xE0-\xEB\xF0-\xFB]',
							'group'     => 'audio',
							'module'    => 'mp3',
							'mime_type' => 'audio/mpeg',
						],

				// OFR  - audio       - OptimFROG
				'ofr'  => [
							'pattern'   => '^(\*RIFF|OFR)',
							'group'     => 'audio',
							'module'    => 'optimfrog',
							'mime_type' => 'application/octet-stream',
						],

				// RKAU - audio       - RKive AUdio compressor
				'rkau' => [
							'pattern'   => '^RKA',
							'group'     => 'audio',
							'module'    => 'rkau',
							'mime_type' => 'application/octet-stream',
						],

				// SHN  - audio       - Shorten
				'shn'  => [
							'pattern'   => '^ajkg',
							'group'     => 'audio',
							'module'    => 'shorten',
							'mime_type' => 'audio/xmms-shn',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						],

				// TTA  - audio       - TTA Lossless Audio Compressor (http://tta.corecodec.org)
				'tta'  => [
							'pattern'   => '^TTA',  // could also be '^TTA(\x01|\x02|\x03|2|1)'
							'group'     => 'audio',
							'module'    => 'tta',
							'mime_type' => 'application/octet-stream',
						],

				// VOC  - audio       - Creative Voice (VOC)
				'voc'  => [
							'pattern'   => '^Creative Voice File',
							'group'     => 'audio',
							'module'    => 'voc',
							'mime_type' => 'audio/voc',
						],

				// VQF  - audio       - transform-domain weighted interleave Vector Quantization Format (VQF)
				'vqf'  => [
							'pattern'   => '^TWIN',
							'group'     => 'audio',
							'module'    => 'vqf',
							'mime_type' => 'application/octet-stream',
						],

				// WV  - audio        - WavPack (v4.0+)
				'wv'   => [
							'pattern'   => '^wvpk',
							'group'     => 'audio',
							'module'    => 'wavpack',
							'mime_type' => 'application/octet-stream',
						],


				// Audio-Video formats

				// ASF  - audio/video - Advanced Streaming Format, Windows Media Video, Windows Media Audio
				'asf'  => [
							'pattern'   => '^\x30\x26\xB2\x75\x8E\x66\xCF\x11\xA6\xD9\x00\xAA\x00\x62\xCE\x6C',
							'group'     => 'audio-video',
							'module'    => 'asf',
							'mime_type' => 'video/x-ms-asf',
							'iconv_req' => false,
						],

				// BINK - audio/video - Bink / Smacker
				'bink' => [
							'pattern'   => '^(BIK|SMK)',
							'group'     => 'audio-video',
							'module'    => 'bink',
							'mime_type' => 'application/octet-stream',
						],

				// FLV  - audio/video - FLash Video
				'flv' => [
							'pattern'   => '^FLV\x01',
							'group'     => 'audio-video',
							'module'    => 'flv',
							'mime_type' => 'video/x-flv',
						],

				// MKAV - audio/video - Mastroka
				'matroska' => [
							'pattern'   => '^\x1A\x45\xDF\xA3',
							'group'     => 'audio-video',
							'module'    => 'matroska',
							'mime_type' => 'video/x-matroska', // may also be audio/x-matroska
						],

				// MPEG - audio/video - MPEG (Moving Pictures Experts Group)
				'mpeg' => [
							'pattern'   => '^\x00\x00\x01(\xBA|\xB3)',
							'group'     => 'audio-video',
							'module'    => 'mpeg',
							'mime_type' => 'video/mpeg',
						],

				// NSV  - audio/video - Nullsoft Streaming Video (NSV)
				'nsv'  => [
							'pattern'   => '^NSV[sf]',
							'group'     => 'audio-video',
							'module'    => 'nsv',
							'mime_type' => 'application/octet-stream',
						],

				// Ogg  - audio/video - Ogg (Ogg-Vorbis, Ogg-FLAC, Speex, Ogg-Theora(*), Ogg-Tarkin(*))
				'ogg'  => [
							'pattern'   => '^OggS',
							'group'     => 'audio',
							'module'    => 'ogg',
							'mime_type' => 'application/ogg',
							'fail_id3'  => 'WARNING',
							'fail_ape'  => 'WARNING',
						],

				// QT   - audio/video - Quicktime
				'quicktime' => [
							'pattern'   => '^.{4}(cmov|free|ftyp|mdat|moov|pnot|skip|wide)',
							'group'     => 'audio-video',
							'module'    => 'quicktime',
							'mime_type' => 'video/quicktime',
						],

				// RIFF - audio/video - Resource Interchange File Format (RIFF) / WAV / AVI / CD-audio / SDSS = renamed variant used by SmartSound QuickTracks (www.smartsound.com) / FORM = Audio Interchange File Format (AIFF)
				'riff' => [
							'pattern'   => '^(RIFF|SDSS|FORM)',
							'group'     => 'audio-video',
							'module'    => 'riff',
							'mime_type' => 'audio/x-wave',
							'fail_ape'  => 'WARNING',
						],

				// Real - audio/video - RealAudio, RealVideo
				'real' => [
							'pattern'   => '^(\\.RMF|\\.ra)',
							'group'     => 'audio-video',
							'module'    => 'real',
							'mime_type' => 'audio/x-realaudio',
						],

				// SWF - audio/video - ShockWave Flash
				'swf' => [
							'pattern'   => '^(F|C)WS',
							'group'     => 'audio-video',
							'module'    => 'swf',
							'mime_type' => 'application/x-shockwave-flash',
						],

				// TS - audio/video - MPEG-2 Transport Stream
				'ts' => [
							'pattern'   => '^(\x47.{187}){10,}', // packets are 188 bytes long and start with 0x47 "G".  Check for at least 10 packets matching this pattern
							'group'     => 'audio-video',
							'module'    => 'ts',
							'mime_type' => 'video/MP2T',
						],


				// Still-Image formats

				// BMP  - still image - Bitmap (Windows, OS/2; uncompressed, RLE8, RLE4)
				'bmp'  => [
							'pattern'   => '^BM',
							'group'     => 'graphic',
							'module'    => 'bmp',
							'mime_type' => 'image/bmp',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						],

				// GIF  - still image - Graphics Interchange Format
				'gif'  => [
							'pattern'   => '^GIF',
							'group'     => 'graphic',
							'module'    => 'gif',
							'mime_type' => 'image/gif',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						],

				// JPEG - still image - Joint Photographic Experts Group (JPEG)
				'jpg'  => [
							'pattern'   => '^\xFF\xD8\xFF',
							'group'     => 'graphic',
							'module'    => 'jpg',
							'mime_type' => 'image/jpeg',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						],

				// PCD  - still image - Kodak Photo CD
				'pcd'  => [
							'pattern'   => '^.{2048}PCD_IPI\x00',
							'group'     => 'graphic',
							'module'    => 'pcd',
							'mime_type' => 'image/x-photo-cd',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						],


				// PNG  - still image - Portable Network Graphics (PNG)
				'png'  => [
							'pattern'   => '^\x89\x50\x4E\x47\x0D\x0A\x1A\x0A',
							'group'     => 'graphic',
							'module'    => 'png',
							'mime_type' => 'image/png',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						],


				// SVG  - still image - Scalable Vector Graphics (SVG)
				'svg'  => [
							'pattern'   => '(<!DOCTYPE svg PUBLIC |xmlns="http:\/\/www\.w3\.org\/2000\/svg")',
							'group'     => 'graphic',
							'module'    => 'svg',
							'mime_type' => 'image/svg+xml',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						],


				// TIFF - still image - Tagged Information File Format (TIFF)
				'tiff' => [
							'pattern'   => '^(II\x2A\x00|MM\x00\x2A)',
							'group'     => 'graphic',
							'module'    => 'tiff',
							'mime_type' => 'image/tiff',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						],


				// EFAX - still image - eFax (TIFF derivative)
				'efax'  => [
							'pattern'   => '^\xDC\xFE',
							'group'     => 'graphic',
							'module'    => 'efax',
							'mime_type' => 'image/efax',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						],


				// Data formats

				// ISO  - data        - International Standards Organization (ISO) CD-ROM Image
				'iso'  => [
							'pattern'   => '^.{32769}CD001',
							'group'     => 'misc',
							'module'    => 'iso',
							'mime_type' => 'application/octet-stream',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
							'iconv_req' => false,
						],

				// RAR  - data        - RAR compressed data
				'rar'  => [
							'pattern'   => '^Rar\!',
							'group'     => 'archive',
							'module'    => 'rar',
							'mime_type' => 'application/octet-stream',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						],

				// SZIP - audio/data  - SZIP compressed data
				'szip' => [
							'pattern'   => '^SZ\x0A\x04',
							'group'     => 'archive',
							'module'    => 'szip',
							'mime_type' => 'application/octet-stream',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						],

				// TAR  - data        - TAR compressed data
				'tar'  => [
							'pattern'   => '^.{100}[0-9\x20]{7}\x00[0-9\x20]{7}\x00[0-9\x20]{7}\x00[0-9\x20\x00]{12}[0-9\x20\x00]{12}',
							'group'     => 'archive',
							'module'    => 'tar',
							'mime_type' => 'application/x-tar',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						],

				// GZIP  - data        - GZIP compressed data
				'gz'  => [
							'pattern'   => '^\x1F\x8B\x08',
							'group'     => 'archive',
							'module'    => 'gzip',
							'mime_type' => 'application/x-gzip',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						],

				// ZIP  - data         - ZIP compressed data
				'zip'  => [
							'pattern'   => '^PK\x03\x04',
							'group'     => 'archive',
							'module'    => 'zip',
							'mime_type' => 'application/zip',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						],


				// Misc other formats

				// PAR2 - data        - Parity Volume Set Specification 2.0
				'par2' => [
							'pattern'   => '^PAR2\x00PKT',
							'group'     => 'misc',
							'module'    => 'par2',
							'mime_type' => 'application/octet-stream',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						],

				// PDF  - data        - Portable Document Format
				'pdf'  => [
							'pattern'   => '^\x25PDF',
							'group'     => 'misc',
							'module'    => 'pdf',
							'mime_type' => 'application/pdf',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						],

				// MSOFFICE  - data   - ZIP compressed data
				'msoffice' => [
							'pattern'   => '^\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1', // D0CF11E == DOCFILE == Microsoft Office Document
							'group'     => 'misc',
							'module'    => 'msoffice',
							'mime_type' => 'application/octet-stream',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						],

				 // CUE  - data       - CUEsheet (index to single-file disc images)
				 'cue' => [
							'pattern'   => '', // empty pattern means cannot be automatically detected, will fall through all other formats and match based on filename and very basic file contents
							'group'     => 'misc',
							'module'    => 'cue',
							'mime_type' => 'application/octet-stream',
						   ],

			];
		}

		return $format_info;
	}
}public function GetFileFormat(&$filedata, $filename='') {
		$formatInfoArray = $this->GetFileFormatArray();

		// Identify file format - loop through $formatInfoArray and detect with regex
		foreach ($formatInfoArray as $format_name => $info) {
			if (!empty($info['pattern']) && preg_match('#'.$info['pattern'].'#s', $filedata)) {
				$info['include'] = 'module.'.$info['group'].'.'.$info['module'].'.php';
				return $info;
			}
		}

		if (preg_match('#\.mp[123a]$#i', $filename)) {
			$info = $formatInfoArray['mp3'] ?? null;
			if ($info) {
				$info['include'] = 'module.'.$info['group'].'.'.$info['module'].'.php';
				return $info;
			}
		} elseif (preg_match('/\.cue$/i', $filename) && preg_match('#FILE "[^"]+" (BINARY|MOTOROLA|AIFF|WAVE|MP3)#', $filedata)) {
			$info = $formatInfoArray['cue'] ?? null;
			if ($info) {
				$info['include'] = 'module.'.$info['group'].'.'.$info['module'].'.php';
				return $info;
			}
		}

		return false;
	}


	// converts array to $encoding charset from $this->encoding
	public function CharConvert(&$array, $encoding) {

		if ($encoding === $this->encoding) {
			return;
		}

		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$this->CharConvert($array[$key], $encoding);
			} elseif (is_string($value)) {
				$array[$key] = trim(getid3_lib::iconv_fallback($encoding, $this->encoding, $value));
			}
		}
	}


	public function HandleAllTags() {

		static $tags;
		if (empty($tags)) {
			$tags = [
				'asf'       => ['asf'          , 'UTF-16LE'],
				'midi'      => ['midi'         , 'ISO-8859-1'],
				'nsv'       => ['nsv'          , 'ISO-8859-1'],
				'ogg'       => ['vorbiscomment', 'UTF-8'],
				'png'       => ['png'          , 'UTF-8'],
				'tiff'      => ['tiff'         , 'ISO-8859-1'],
				'quicktime' => ['quicktime'    , 'UTF-8'],
				'real'      => ['real'         , 'ISO-8859-1'],
				'vqf'       => ['vqf'          , 'ISO-8859-1'],
				'zip'       => ['zip'          , 'ISO-8859-1'],
				'riff'      => ['riff'         , 'ISO-8859-1'],
				'lyrics3'   => ['lyrics3'      , 'ISO-8859-1'],
				'id3v1'     => ['id3v1'        , $this->encoding_id3v1],
				'id3v2'     => ['id3v2'        , 'UTF-8'],
				'ape'       => ['ape'          , 'UTF-8'],
				'cue'       => ['cue'          , 'ISO-8859-1'],
				'matroska'  => ['matroska'     , 'UTF-8'],
				'flac'      => ['vorbiscomment', 'UTF-8'],
				'divxtag'   => ['divx'         , 'ISO-8859-1'],
			];
		}

		foreach ($tags as $comment_name => $tagname_encoding_array) {
			[$tag_name, $encoding] = $tagname_encoding_array;
			$normalizedTagName = trim($tag_name);

			if (isset($this->info[$comment_name]) && !isset($this->info[$comment_name]['encoding'])) {
				$this->info[$comment_name]['encoding'] = $encoding;
			}

			if (!empty($this->info[$comment_name]['comments'])) {
				foreach ($this->info[$comment_name]['comments'] as $tag_key => $valuearray) {
					$trimmedTagKey = trim($tag_key);
					foreach ($valuearray as $key => $value) {
						if (is_string($value)) {
							$value = trim($value, " \r\n\t");
						}
						if ($value !== '' && $value !== null) {
							$this->info['tags'][$normalizedTagName][$trimmedTagKey][] = $value;
						}
					}
					if ($tag_key === 'picture') {
						unset($this->info[$comment_name]['comments'][$tag_key]);
					}
				}

				if (empty($this->info['tags'][$normalizedTagName])) {
					continue;
				}

				if ($this->option_tags_html) {
					foreach ($this->info['tags'][$normalizedTagName] as $tag_key => $valuearray) {
						foreach ($valuearray as $key => $value) {
							if (is_string($value)) {
								$this->info['tags_html'][$normalizedTagName][$tag_key][$key] = str_replace('&#0;', '', trim(getid3_lib::MultiByteCharString2HTML($value, $encoding)));
							} else {
								$this->info['tags_html'][$normalizedTagName][$tag_key][$key] = $value;
							}
						}
					}
				}

				$this->CharConvert($this->info['tags'][$normalizedTagName], $encoding);
			}

		}

		if (!empty($this->info['tags'])) {
			$unsetKeys = ['tags', 'tags_html'];
			foreach ($this->info['tags'] as $tagtype => $tagarray) {
				foreach ($tagarray as $tagname => $tagdata) {
					if ($tagname === 'picture') {
						foreach ($tagdata as $key => $pictureData) {
							$this->info['comments']['picture'][] = $pictureData;
							if (isset($pictureData['data'], $pictureData['image_mime'])) {
								if (isset($this->info['tags'][$tagtype][$tagname][$key])) {
									unset($this->info['tags'][$tagtype][$tagname][$key]);
								}
								if (isset($this->info['tags_html'][$tagtype][$tagname][$key])) {
									unset($this->info['tags_html'][$tagtype][$tagname][$key]);
								}
							}
						}
					}
				}
				foreach ($unsetKeys as $unsetKey) {
					if (isset($this->info[$unsetKey][$tagtype]['picture']) && empty($this->info[$unsetKey][$tagtype]['picture'])) {
						unset($this->info[$unsetKey][$tagtype]['picture']);
					}
					if (isset($this->info[$unsetKey][$tagtype]) && empty($this->info[$unsetKey][$tagtype])) {
						unset($this->info[$unsetKey][$tagtype]);
					}
					if (isset($this->info[$unsetKey]) && empty($this->info[$unsetKey])) {
						unset($this->info[$unsetKey]);
					}
				}
				if (isset($this->info[$tagtype]['comments']['picture'])) {
					unset($this->info[$tagtype]['comments']['picture']);
				}
				if (isset($this->info[$tagtype]['comments']) && empty($this->info[$tagtype]['comments'])) {
					unset($this->info[$tagtype]['comments']);
				}
				if (isset($this->info[$tagtype]) && empty($this->info[$tagtype])) {
					unset($this->info[$tagtype]);
				}
			}
		}
		return true;
	}


	public function getHashdata($algorithm) {
		switch ($algorithm) {
			case 'md5':
			case 'sha1':
				break;

			default:
				return $this->error('bad algorithm "'.$algorithm.'" in getHashdata()');
		}

		if (($this->info['fileformat'] ?? null) === 'ogg' && ($this->info['audio']['dataformat'] ?? null) === 'vorbis') {

			if (preg_match('#(1|ON)#i', (string) ini_get('safe_mode'))) {

				$this->warning('Failed making system call to vorbiscomment.exe - '.$algorithm.'_data is incorrect - error returned: PHP running in Safe Mode (backtick operator not available)');
				$this->info[$algorithm.'_data'] = false;

			} else {

				$old_abort = ignore_user_abort(true);

				$empty = tempnam(GETID3_TEMP_DIR, 'getID3');
				$temp  = tempnam(GETID3_TEMP_DIR, 'getID3');
				if (($empty === false) || ($temp === false)) {
					$this->info['warning'][]        = 'Failed creating temporary files for vorbiscomment - '.$algorithm.'_data will be incorrect.';
					$this->info[$algorithm.'_data'] = false;
					if ($empty && file_exists($empty)) {
						unlink($empty);
					}
					if ($temp && file_exists($temp)) {
						unlink($temp);
					}
					ignore_user_abort($old_abort);
					return true;
				}
				touch($empty);

				$file = $this->info['filenamepath'];

				if (GETID3_OS_ISWINDOWS) {

					if (file_exists(GETID3_HELPERAPPSDIR.'vorbiscomment.exe')) {

						$commandline = '"'.GETID3_HELPERAPPSDIR.'vorbiscomment.exe" -w -c "'.$empty.'" "'.$file.'" "'.$temp.'"';
						$vorbisCommentError = shell_exec($commandline) ?? '';

					} else {

						$vorbisCommentError = 'vorbiscomment.exe not found in '.GETID3_HELPERAPPSDIR;

					}

				} else {

					$commandline = 'vorbiscomment -w -c '.escapeshellarg($empty).' '.escapeshellarg($file).' '.escapeshellarg($temp).' 2>&1';
					$vorbisCommentError = shell_exec($commandline) ?? '';

				}

				if (!empty($vorbisCommentError)) {

					$this->info['warning'][]        = 'Failed making system call to vorbiscomment(.exe) - '.$algorithm.'_data will be incorrect. If vorbiscomment is unavailable, please download from http://www.vorbis.com/download.psp and put in the getID3() directory. Error returned: '.$vorbisCommentError;
					$this->info[$algorithm.'_data'] = false;

				} else {

					$this->info[$algorithm.'_data'] = match ($algorithm) {
						'md5'  => md5_file($temp),
						'sha1' => sha1_file($temp),
					};
				}

				if (file_exists($empty)) {
					unlink($empty);
				}
				if (file_exists($temp)) {
					unlink($temp);
				}

				ignore_user_abort($old_abort);

			}

		} else {

			if (!empty($this->info['avdataoffset']) || (isset($this->info['avdataend'], $this->info['filesize']) && ($this->info['avdataend'] < $this->info['filesize']))) {

				$this->info[$algorithm.'_data'] = getid3_lib::hash_data($this->info['filenamepath'], $this->info['avdataoffset'], $this->info['avdataend'], $algorithm);

			} else {

				$this->info[$algorithm.'_data'] = match ($algorithm) {
					'md5'  => md5_file($this->info['filenamepath']),
					'sha1' => sha1_file($this->info['filenamepath']),
				};
			}

		}
		return true;
	}


	public function ChannelsBitratePlaytimeCalculations() {

		if (!empty($this->info['audio']['channelmode']) || !isset($this->info['audio']['channels'])) {
			// ignore
		} elseif ($this->info['audio']['channels'] === 1) {
			$this->info['audio']['channelmode'] = 'mono';
		} elseif ($this->info['audio']['channels'] === 2) {
			$this->info['audio']['channelmode'] = 'stereo';
		}

		$combinedBitrate  = ($this->info['audio']['bitrate'] ?? 0);
		$combinedBitrate += ($this->info['video']['bitrate'] ?? 0);
		if (($combinedBitrate > 0) && empty($this->info['bitrate'])) {
			$this->info['bitrate'] = $combinedBitrate;
		}

		if (!empty($this->info['video']['dataformat']) && empty($this->info['video']['bitrate'])) {
			if (($this->info['audio']['bitrate'] ?? 0) > 0 && ($this->info['audio']['bitrate'] ?? null) == ($this->info['bitrate'] ?? null)) {
				if (($this->info['playtime_seconds'] ?? 0) > 0) {
					if (isset($this->info['avdataend'], $this->info['avdataoffset'])) {
						$this->info['bitrate'] = round((($this->info['avdataend'] - $this->info['avdataoffset']) * 8) / $this->info['playtime_seconds']);
						$this->info['video']['bitrate'] = $this->info['bitrate'] - $this->info['audio']['bitrate'];
					}
				}
			}
		}

		if ((!isset($this->info['playtime_seconds']) || ($this->info['playtime_seconds'] <= 0)) && !empty($this->info['bitrate']) && isset($this->info['avdataend'], $this->info['avdataoffset'])) {
			$this->info['playtime_seconds'] = (($this->info['avdataend'] - $this->info['avdataoffset']) * 8) / $this->info['bitrate'];
		}

		if (!isset($this->info['bitrate']) && !empty($this->info['playtime_seconds']) && isset($this->info['avdataend'], $this->info['avdataoffset'])) {
			$this->info['bitrate'] = (($this->info['avdataend'] - $this->info['avdataoffset']) * 8) / $this->info['playtime_seconds'];
		}
		if (isset($this->info['bitrate']) && empty($this->info['audio']['bitrate']) && empty($this->info['video']['bitrate'])) {
			if (isset($this->info['audio']['dataformat']) && empty($this->info['video']['resolution_x'])) {
				$this->info['audio']['bitrate'] = $this->info['bitrate'];
			} elseif (isset($this->info['video']['resolution_x']) && empty($this->info['audio']['dataformat'])) {
				$this->info['video']['bitrate'] = $this->info['bitrate'];
			}
		}

		if (!empty($this->info['playtime_seconds']) && empty($this->info['playtime_string'])) {
			$this->info['playtime_string'] = getid3_lib::PlaytimeString($this->info['playtime_seconds']);
		}
	}


	public function CalculateCompressionRatioVideo() {
		if (empty($this->info['video'])) {
			return false;
		}
		if (empty($this->info['video']['resolution_x']) || empty($this->info['video']['resolution_y'])) {
			return false;
		}
		if (empty($this->info['video']['bits_per_sample'])) {
			return false;
		}

		switch ($this->info['video']['dataformat']) {
			case 'bmp':
			case 'gif':
			case 'jpeg':
			case 'jpg':
			case 'png':
			case 'tiff':
				$frameRate = 1;
				$playtimeSeconds = 1;
				$bitrateCompressed = $this->info['filesize'] * 8;
				break;

			default:
				if (!empty($this->info['video']['frame_rate'])) {
					$frameRate = $this->info['video']['frame_rate'];
				} else {
					return false;
				}
				if (!empty($this->info['playtime_seconds'])) {
					$playtimeSeconds = $this->info['playtime_seconds'];
				} else {
					return false;
				}
				if (!empty($this->info['video']['bitrate'])) {
					$bitrateCompressed = $this->info['video']['bitrate'];
				} else {
					return false;
				}
				break;
		}
		$bitrateUncompressed = $this->info['video']['resolution_x'] * $this->info['video']['resolution_y'] * $this->info['video']['bits_per_sample'] * $frameRate;

		$this->info['video']['compression_ratio'] = $bitrateCompressed / $bitrateUncompressed;
		return true;
	}


	public function CalculateCompressionRatioAudio() {
		if (empty($this->info['audio']['bitrate']) || empty($this->info['audio']['channels']) || empty($this->info['audio']['sample_rate']) || !is_numeric($this->info['audio']['sample_rate'])) {
			return false;
		}
		$bitsPerSample = $this->info['audio']['bits_per_sample'] ?? 16;
		if (!$bitsPerSample) {
			$bitsPerSample = 16;
		}
		$this->info['audio']['compression_ratio'] = $this->info['audio']['bitrate'] / ($this->info['audio']['channels'] * $this->info['audio']['sample_rate'] * $bitsPerSample);

		if (!empty($this->info['audio']['streams'])) {
			foreach ($this->info['audio']['streams'] as $streamnumber => $streamdata) {
				if (!empty($streamdata['bitrate']) && !empty($streamdata['channels']) && !empty($streamdata['sample_rate'])) {
					$streamBitsPerSample = $streamdata['bits_per_sample'] ?? 16;
					if (!$streamBitsPerSample) {
						$streamBitsPerSample = 16;
					}
					$this->info['audio']['streams'][$streamnumber]['compression_ratio'] = $streamdata['bitrate'] / ($streamdata['channels'] * $streamdata['sample_rate'] * $streamBitsPerSample);
				}
			}
		}
		return true;
	}


	public function CalculateReplayGain() {
		if (isset($this->info['replay_gain'])) {
			if (!isset($this->info['replay_gain']['reference_volume'])) {
				$this->info['replay_gain']['reference_volume'] = 89.0;
			}
			if (isset($this->info['replay_gain']['track']['adjustment'])) {
				$this->info['replay_gain']['track']['volume'] = $this->info['replay_gain']['reference_volume'] - $this->info['replay_gain']['track']['adjustment'];
			}
			if (isset($this->info['replay_gain']['album']['adjustment'])) {
				$this->info['replay_gain']['album']['volume'] = $this->info['replay_gain']['reference_volume'] - $this->info['replay_gain']['album']['adjustment'];
			}

			if (isset($this->info['replay_gain']['track']['peak'])) {
				$this->info['replay_gain']['track']['max_noclip_gain'] = -getid3_lib::RGADamplitude2dB($this->info['replay_gain']['track']['peak']);
			}
			if (isset($this->info['replay_gain']['album']['peak'])) {
				$this->info['replay_gain']['album']['max_noclip_gain'] = -getid3_lib::RGADamplitude2dB($this->info['replay_gain']['album']['peak']);
			}
		}
		return true;
	}

	public function ProcessAudioStreams() {
		if (!empty($this->info['audio']['bitrate']) || !empty($this->info['audio']['channels']) || !empty($this->info['audio']['sample_rate'])) {
			if (empty($this->info['audio']['streams'])) {
				foreach ($this->info['audio'] as $key => $value) {
					if ($key !== 'streams') {
						$this->info['audio']['streams'][0][$key] = $value;
					}
				}
			}
		}
		return true;
	}

	public function getid3_tempnam() {
		return tempnam($this->tempdir, 'gI3');
	}

	public function include_module($name) {
		if (!file_exists(GETID3_INCLUDEPATH.'module.'.$name.'.php')) {
			throw new getid3_exception('Required module.'.$name.'.php is missing.');
		}
		include_once(GETID3_INCLUDEPATH.'module.'.$name.'.php');
		return true;
	}

}


abstract class getid3_handler
{
	protected getID3 $getid3;

	protected bool $data_string_flag     = false;
	protected string $data_string        = '';
	protected int $data_string_position  = 0;
	protected int $data_string_length    = 0;

	private ?string $dependency_to = null;


	public function __construct(getID3 $getid3, ?string $call_module=null) {
		$this->getid3 = $getid3;

		if ($call_module) {
			$this->dependency_to = str_replace('getid3_', '', $call_module);
		}
	}


	abstract public function Analyze();


	public function AnalyzeString(string $string): void {
	    $this->setStringMode($string);

		$saved_avdataoffset = $this->getid3->info['avdataoffset'] ?? 0;
		$saved_avdataend    = $this->getid3->info['avdataend'] ?? 0;
		$saved_filesize     = $this->getid3->info['filesize'] ?? null;

		$this->getid3->info['avdataoffset'] = 0;
		$this->getid3->info['filesize']     = $this->data_string_length;
		$this->getid3->info['avdataend']    = $this->data_string_length;

		$this->Analyze();

		$this->getid3->info['avdataoffset'] = $saved_avdataoffset;
		$this->getid3->info['avdataend']    = $saved_avdataend;
		$this->getid3->info['filesize']     = $saved_filesize;

		$this->data_string_flag = false;
	}

	public function setStringMode(string $string): void {
		$this->data_string_flag     = true;
		$this->data_string          = $string;
		$this->data_string_position = 0;
		$this->data_string_length   = strlen($string);
	}

	protected function ftell(): int|false {
		if ($this->data_string_flag) {
			return $this->data_string_position;
		}
		return ftell($this->getid3->fp);
	}

	protected function fread(int $bytes): string|false {
		if ($this->data_string_flag) {
			$this->data_string_position += $bytes;
			return substr($this->data_string, $this->data_string_position - $bytes, $bytes);
		}
	    $position = $this->ftell();
		if ($position === false) {
			return false;
		}
	    $pos = $position + $bytes;
	    if (!getid3_lib::intValueSupported($pos)) {
			throw new getid3_exception('cannot fread('.$bytes.' from '.$position.') because beyond PHP filesystem limit', 10);
	    }
		return fread($this->getid3->fp, $bytes);
	}

	protected function fseek(int $bytes, int $whence=SEEK_SET): int {
		if ($this->data_string_flag) {
			switch ($whence) {
				case SEEK_SET:
					$this->data_string_position = $bytes;
					break;

				case SEEK_CUR:
					$this->data_string_position += $bytes;
					break;

				case SEEK_END:
					$this->data_string_position = $this->data_string_length + $bytes;
					break;
			}
			return 0;
	    } else {
	    	$pos = $bytes;
	    	if ($whence === SEEK_CUR) {
	    		$currentPosition = $this->ftell();
	    		if ($currentPosition === false) {
	    			return -1;
	    		}
				$pos = $currentPosition + $bytes;
	    	} elseif ($whence === SEEK_END) {
				$pos = ($this->getid3->info['filesize'] ?? 0) + $bytes;
	    	}
	    	if (!getid3_lib::intValueSupported($pos)) {
				throw new getid3_exception('cannot fseek('.$pos.') because beyond PHP filesystem limit', 10);
			}
		}
		return fseek($this->getid3->fp, $bytes, $whence);
	}

	protected function feof(): bool {
		if ($this->data_string_flag) {
			return $this->data_string_position >= $this->data_string_length;
		}
		return feof($this->getid3->fp);
	}

	final protected function isDependencyFor(string $module): bool {
		return $this->dependency_to === $module;
	}

	protected function error(string $text)
	{
		$this->getid3->info['error'][] = $text;

		return false;
	}

	protected function warning(string $text)
	{
		return $this->getid3->warning($text);
	}

	protected function notice(string $text): void
	{
		// does nothing for now
	}

	public function saveAttachment(string $name, int $offset, int $length, ?string $image_mime=null) {
		try {

			if ($this->getid3->option_save_attachments === getID3::ATTACHMENTS_NONE) {

				$attachment = null;

			} elseif ($this->getid3->option_save_attachments === getID3::ATTACHMENTS_INLINE) {

				$this->fseek($offset);
				$attachment = $this->fread($length);
				if ($attachment === false || strlen($attachment) != $length) {
					throw new Exception('failed to read attachment data');
				}

			} else {

				$dir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->getid3->option_save_attachments), DIRECTORY_SEPARATOR);
				if (!is_dir($dir) || !is_writable($dir)) {
					throw new Exception('supplied path ('.$dir.') does not exist, or is not writable');
				}
				$dest = $dir.DIRECTORY_SEPARATOR.$name.($image_mime ? '.'.getid3_lib::ImageExtFromMime($image_mime) : '');

				$fp_dest = fopen($dest, 'wb');
				if ($fp_dest === false) {
					throw new Exception('failed to create file '.$dest);
				}

				$this->fseek($offset);
				$bufferSize = $this->data_string_flag ? $length : $this->getid3->fread_buffer_size();
				$bytesLeft = $length;
				while ($bytesLeft > 0) {
					$buffer = $this->fread(min($bufferSize, $bytesLeft));
					if ($buffer === false) {
						throw new Exception('not enough data to read');
					}
					$bytesWritten = fwrite($fp_dest, $buffer);
					if ($bytesWritten === false || $bytesWritten === 0) {
						throw new Exception('failed to write to destination file, may be not enough disk space');
					}
					$bytesLeft -= $bytesWritten;
				}

				fclose($fp_dest);
				$attachment = $dest;

			}

		} catch (Exception $exception) {

			if (isset($fp_dest) && is_resource($fp_dest)) {
				fclose($fp_dest);
			}
			if (isset($dest) && file_exists($dest)) {
				unlink($dest);
			}

			$attachment = null;
			$this->warning('Failed to extract attachment '.$name.': '.$exception->getMessage());

		}

		$this->fseek($offset + $length);

		return $attachment;
	}

}


class getid3_exception extends Exception
{
}