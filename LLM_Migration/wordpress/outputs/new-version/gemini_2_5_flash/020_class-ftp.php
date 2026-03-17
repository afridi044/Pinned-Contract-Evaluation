<?php
/**
 * PemFTP - A Ftp implementation in pure PHP
 *
 * @package PemFTP
 * @since 2.5
 *
 * @version 1.0
 * @copyright Alexey Dotsenko
 * @author Alexey Dotsenko
 * @link http://www.phpclasses.org/browse/package/1743.html Site
 * @license LGPL http://www.opensource.org/licenses/lgpl-license.html
 */

/**
 * Defines the newline characters, if not defined already.
 *
 * This can be redefined.
 *
 * @since 2.5
 * @var string
 */
if(!defined('CRLF')) define('CRLF',"\r\n");

/**
 * Sets whatever to autodetect ASCII mode.
 *
 * This can be redefined.
 *
 * @since 2.5
 * @var int
 */
if(!defined("FTP_AUTOASCII")) define("FTP_AUTOASCII", -1);

/**
 *
 * This can be redefined.
 * @since 2.5
 * @var int
 */
if(!defined("FTP_BINARY")) define("FTP_BINARY", 1);

/**
 *
 * This can be redefined.
 * @since 2.5
 * @var int
 */
if(!defined("FTP_ASCII")) define("FTP_ASCII", 0);

/**
 * Whether to force FTP.
 *
 * This can be redefined.
 *
 * @since 2.5
 * @var bool
 */
if(!defined('FTP_FORCE')) define('FTP_FORCE', true);

/**
 * @since 2.5
 * @var string
 */
define('FTP_OS_Unix','u');

/**
 * @since 2.5
 * @var string
 */
define('FTP_OS_Windows','w');

/**
 * @since 2.5
 * @var string
 */
define('FTP_OS_Mac','m');

/**
 * PemFTP base class
 *
 */
class ftp_base {
	/* Public variables */
	public ?bool $LocalEcho = null;
	public ?bool $Verbose = null;
	public string $OS_local;
	public string $OS_remote;

	/* Private variables */
	private ?int $_lastaction = null;
	private array $_errors = [];
	private int $_type;
	private int $_umask;
	private int $_timeout;
	private bool $_passive;
	private ?string $_host = null;
	private ?string $_fullhost = null;
	private ?int $_port = null;
	private ?string $_datahost = null;
	private ?int $_dataport = null;
	private mixed $_ftp_control_sock = null;
	private mixed $_ftp_data_sock = null;
	private mixed $_ftp_temp_sock = null;
	private int $_ftp_buff_size;
	private string $_login;
	private string $_password;
	private bool $_connected;
	private bool $_ready;
	private int $_code;
	private string $_message;
	private bool $_can_restore;
	private bool $_port_available;
	private ?int $_curtype = null;
	private array $_features;

	private array $_error_array;
	public array $AuthorizedTransferMode;
	public array $OS_FullName;
	private array $_eol_code;
	public array $AutoAsciiExt;
	public array $features;

	/* Constructor */
	// Old-style constructor `ftp_base` removed, as it just called `__construct`.
	public function __construct(bool $port_mode = false, bool $verb = false, bool $le = false) {
		$this->LocalEcho = $le;
		$this->Verbose = $verb;
		$this->_lastaction = null;
		$this->_error_array = [];
		$this->_eol_code = [FTP_OS_Unix => "\n", FTP_OS_Mac => "\r", FTP_OS_Windows => "\r\n"];
		$this->AuthorizedTransferMode = [FTP_AUTOASCII, FTP_ASCII, FTP_BINARY];
		$this->OS_FullName = [FTP_OS_Unix => 'UNIX', FTP_OS_Windows => 'WINDOWS', FTP_OS_Mac => 'MACOS'];
		$this->AutoAsciiExt = ["ASP","BAT","C","CPP","CSS","CSV","JS","H","HTM","HTML","SHTML","INI","LOG","PHP3","PHTML","PL","PERL","SH","SQL","TXT"];
		$this->_port_available = ($port_mode === true);
		$this->SendMSG("Staring FTP client class".($this->_port_available ? "" : " without PORT mode support"));
		$this->_connected = false;
		$this->_ready = false;
		$this->_can_restore = false;
		$this->_code = 0;
		$this->_message = "";
		$this->_ftp_buff_size = 4096;
		$this->_curtype = null;
		$this->SetUmask(0o022);
		$this->SetType(FTP_AUTOASCII);
		$this->SetTimeout(30);
		$this->Passive(!$this->_port_available);
		$this->_login = "anonymous";
		$this->_password = "anon@ftp.com";
		$this->_features = [];
	    $this->OS_local = FTP_OS_Unix;
		$this->OS_remote = FTP_OS_Unix;
		$this->features = [];
		if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') $this->OS_local = FTP_OS_Windows;
		elseif(strtoupper(substr(PHP_OS, 0, 3)) === 'MAC') $this->OS_local = FTP_OS_Mac;
	}

// <!-- --------------------------------------------------------------------------------------- -->
// <!--       Public functions                                                                  -->
// <!-- --------------------------------------------------------------------------------------- -->

	public function parselisting(string $line): array|string {
		$is_windows = ($this->OS_remote === FTP_OS_Windows);
		if ($is_windows && preg_match("/(\d{2})-(\d{2})-(\d{2}) +(\d{2}):(\d{2})(AM|PM) +(\d+|<DIR>) +(.+)/", $line, $lucifer)) {
			$b = [];
			if ($lucifer[3] < 70) { $lucifer[3] += 2000; } else { $lucifer[3] += 1900; } // 4digit year fix
			$b['isdir'] = ($lucifer[7] === "<DIR>");
			if ( $b['isdir'] )
				$b['type'] = 'd';
			else
				$b['type'] = 'f';
			$b['size'] = $lucifer[7];
			$b['month'] = $lucifer[1];
			$b['day'] = $lucifer[2];
			$b['year'] = $lucifer[3];
			$b['hour'] = $lucifer[4];
			$b['minute'] = $lucifer[5];
			$b['time'] = @mktime($lucifer[4]+(strcasecmp($lucifer[6],"PM") === 0 ? 12 : 0),$lucifer[5],0,$lucifer[1],$lucifer[2],$lucifer[3]);
			$b['am/pm'] = $lucifer[6];
			$b['name'] = $lucifer[8];
		} else if (!$is_windows && ($lucifer = preg_split("/[ ]/", $line, 9, PREG_SPLIT_NO_EMPTY))) {
			//echo $line."\n";
			$lcount = count($lucifer);
			if ($lcount < 8) return '';
			$b = [];
			$b['isdir'] = $lucifer[0][0] === "d";
			$b['islink'] = $lucifer[0][0] === "l";
			if ( $b['isdir'] )
				$b['type'] = 'd';
			elseif ( $b['islink'] )
				$b['type'] = 'l';
			else
				$b['type'] = 'f';
			$b['perms'] = $lucifer[0];
			$b['number'] = $lucifer[1];
			$b['owner'] = $lucifer[2];
			$b['group'] = $lucifer[3];
			$b['size'] = $lucifer[4];
			if ($lcount === 8) {
				sscanf($lucifer[5],"%d-%d-%d",$b['year'],$b['month'],$b['day']);
				sscanf($lucifer[6],"%d:%d",$b['hour'],$b['minute']);
				$b['time'] = @mktime($b['hour'],$b['minute'],0,$b['month'],$b['day'],$b['year']);
				$b['name'] = $lucifer[7];
			} else {
				$b['month'] = $lucifer[5];
				$b['day'] = $lucifer[6];
				if (preg_match("/(\d{2}):(\d{2})/",$lucifer[7],$l2)) {
					$b['year'] = (int)date("Y");
					$b['hour'] = $l2[1];
					$b['minute'] = $l2[2];
				} else {
					$b['year'] = $lucifer[7];
					$b['hour'] = 0;
					$b['minute'] = 0;
				}
				$b['time'] = strtotime(sprintf("%d %s %d %02d:%02d",$b['day'],$b['month'],$b['year'],$b['hour'],$b['minute']));
				$b['name'] = $lucifer[8];
			}
		}

		return $b;
	}

	public function SendMSG(string $message = "", bool $crlf = true): bool {
		if ($this->Verbose) {
			echo $message.($crlf ? CRLF : "");
			flush();
		}
		return true;
	}

	public function SetType(int $mode = FTP_AUTOASCII): bool {
		if(!in_array($mode, $this->AuthorizedTransferMode, true)) {
			$this->SendMSG("Wrong type");
			return false;
		}
		$this->_type = $mode;
		$this->SendMSG("Transfer type: ".($this->_type === FTP_BINARY ? "binary" : ($this->_type === FTP_ASCII ? "ASCII" : "auto ASCII") ) );
		return true;
	}

	private function _settype(int $mode = FTP_ASCII): bool {
		if($this->_ready) {
			if($mode === FTP_BINARY) {
				if($this->_curtype !== FTP_BINARY) {
					if(!$this->_exec("TYPE I", "SetType")) return false;
					$this->_curtype = FTP_BINARY;
				}
			} elseif($this->_curtype !== FTP_ASCII) {
				if(!$this->_exec("TYPE A", "SetType")) return false;
				$this->_curtype = FTP_ASCII;
			}
		} else return false;
		return true;
	}

	public function Passive(?bool $pasv = null): bool {
		if(is_null($pasv)) $this->_passive = !$this->_passive;
		else $this->_passive = $pasv;
		if(!$this->_port_available && !$this->_passive) {
			$this->SendMSG("Only passive connections available!");
			$this->_passive = true;
			return false;
		}
		$this->SendMSG("Passive mode ".($this->_passive ? "on" : "off"));
		return true;
	}

	public function SetServer(string $host, int $port = 21, bool $reconnect = true): bool {
		if(!is_int($port)) {
	        $this->Verbose = true;
    	    $this->SendMSG("Incorrect port syntax");
			return false;
		} else {
			$ip = @gethostbyname($host);
	        $dns = @gethostbyaddr($host);
	        if(!$ip) $ip = $host;
	        if(!$dns) $dns = $host;
	        // Validate the IPAddress PHP4 returns -1 for invalid, PHP5 false
	        // -1 === "255.255.255.255" which is the broadcast address which is also going to be invalid
	        $ipaslong = ip2long($ip);
			if ( ($ipaslong === false) || ($ipaslong === -1) ) {
				$this->SendMSG("Wrong host name/address \"".$host."\"");
				return false;
			}
	        $this->_host = $ip;
	        $this->_fullhost = $dns;
	        $this->_port = $port;
	        $this->_dataport = $port-1;
		}
		$this->SendMSG("Host \"".$this->_fullhost."(".$this->_host."):".$this->_port."\"");
		if($reconnect){
			if($this->_connected) {
				$this->SendMSG("Reconnecting");
				if(!$this->quit(FTP_FORCE)) return false;
				if(!$this->connect()) return false;
			}
		}
		return true;
	}

	public function SetUmask(int $umask = 0o022): bool {
		$this->_umask = $umask;
		umask($this->_umask);
		$this->SendMSG("UMASK 0".decoct($this->_umask));
		return true;
	}

	public function SetTimeout(int $timeout = 30): bool {
		$this->_timeout = $timeout;
		$this->SendMSG("Timeout ".$this->_timeout);
		if($this->_connected)
			if(!$this->_settimeout($this->_ftp_control_sock)) return false;
		return true;
	}

	public function connect(?string $server = null): bool {
		if(!empty($server)) {
			if(!$this->SetServer($server)) return false;
		}
		if($this->_ready) return true;
	    $this->SendMSG('Local OS : '.$this->OS_FullName[$this->OS_local]);
		if(!($this->_ftp_control_sock = $this->_connect($this->_host, $this->_port))) {
			$this->SendMSG("Error : Cannot connect to remote host \"".$this->_fullhost." :".$this->_port."\"");
			return false;
		}
		$this->SendMSG("Connected to remote host \"".$this->_fullhost.":".$this->_port."\". Waiting for greeting.");
		do {
			if(!$this->_readmsg()) return false;
			if(!$this->_checkCode()) return false;
			$this->_lastaction = time();
		} while($this->_code < 200);
		$this->_ready = true;
		$syst = $this->systype();
		if(!$syst) $this->SendMSG("Can't detect remote OS");
		else {
			if(preg_match("/win|dos|novell/i", $syst[0])) $this->OS_remote = FTP_OS_Windows;
			elseif(preg_match("/os/i", $syst[0])) $this->OS_remote = FTP_OS_Mac;
			elseif(preg_match("/(li|u)nix/i", $syst[0])) $this->OS_remote = FTP_OS_Unix;
			else $this->OS_remote = FTP_OS_Mac;
			$this->SendMSG("Remote OS: ".$this->OS_FullName[$this->OS_remote]);
		}
		if(!$this->features()) $this->SendMSG("Can't get features list. All supported - disabled");
		else $this->SendMSG("Supported features: ".implode(", ", array_keys($this->_features)));
		return true;
	}

	public function quit(bool $force = false): bool {
		if($this->_ready) {
			if(!$this->_exec("QUIT") && !$force) return false;
			if(!$this->_checkCode() && !$force) return false;
			$this->_ready = false;
			$this->SendMSG("Session finished");
		}
		$this->_quit();
		return true;
	}

	public function login(?string $user = null, ?string $pass = null): bool {
		$this->_login = $user ?? "anonymous";
		$this->_password = $pass ?? "anon@anon.com";
		if(!$this->_exec("USER ".$this->_login, "login")) return false;
		if(!$this->_checkCode()) return false;
		if($this->_code !== 230) {
			if(!$this->_exec((($this->_code === 331) ? "PASS " : "ACCT ").$this->_password, "login")) return false;
			if(!$this->_checkCode()) return false;
		}
		$this->SendMSG("Authentication succeeded");
		if(empty($this->_features)) {
			if(!$this->features()) $this->SendMSG("Can't get features list. All supported - disabled");
			else $this->SendMSG("Supported features: ".implode(", ", array_keys($this->_features)));
		}
		return true;
	}

	public function pwd(): string|false {
		if(!$this->_exec("PWD", "pwd")) return false;
		if(!$this->_checkCode()) return false;
		return preg_replace("/^\d{3} \"(.+)\".+/", "\\1", $this->_message);
	}

	public function cdup(): bool {
		if(!$this->_exec("CDUP", "cdup")) return false;
		if(!$this->_checkCode()) return false;
		return true;
	}

	public function chdir(string $pathname): bool {
		if(!$this->_exec("CWD ".$pathname, "chdir")) return false;
		if(!$this->_checkCode()) return false;
		return true;
	}

	public function rmdir(string $pathname): bool {
		if(!$this->_exec("RMD ".$pathname, "rmdir")) return false;
		if(!$this->_checkCode()) return false;
		return true;
	}

	public function mkdir(string $pathname): bool {
		if(!$this->_exec("MKD ".$pathname, "mkdir")) return false;
		if(!$this->_checkCode()) return false;
		return true;
	}

	public function rename(string $from, string $to): bool {
		if(!$this->_exec("RNFR ".$from, "rename")) return false;
		if(!$this->_checkCode()) return false;
		if($this->_code === 350) {
			if(!$this->_exec("RNTO ".$to, "rename")) return false;
			if(!$this->_checkCode()) return false;
		} else return false;
		return true;
	}

	public function filesize(string $pathname): string|false {
		if(!isset($this->_features["SIZE"])) {
			$this->SendMSG("Error: filesize not supported by server");
			return false;
		}
		if(!$this->_exec("SIZE ".$pathname, "filesize")) return false;
		if(!$this->_checkCode()) return false;
		return preg_replace("/^\d{3} (\d+)".CRLF."/", "\\1", $this->_message);
	}

	public function abort(): bool {
		if(!$this->_exec("ABOR", "abort")) return false;
		if(!$this->_checkCode()) {
			if($this->_code !== 426) return false;
			if(!$this->_readmsg("abort")) return false;
			if(!$this->_checkCode()) return false;
		}
		return true;
	}

	public function mdtm(string $pathname): int|false {
		if(!isset($this->_features["MDTM"])) {
			$this->SendMSG("Error: mdtm not supported by server");
			return false;
		}
		if(!$this->_exec("MDTM ".$pathname, "mdtm")) return false;
		if(!$this->_checkCode()) return false;
		$mdtm = preg_replace("/^\d{3} (\d+)".CRLF."/", "\\1", $this->_message);
		$date = sscanf($mdtm, "%4d%2d%2d%2d%2d%2d");
		$timestamp = mktime($date[3], $date[4], $date[5], $date[1], $date[2], $date[0]);
		return $timestamp;
	}

	public function systype(): array|false {
		if(!$this->_exec("SYST", "systype")) return false;
		if(!$this->_checkCode()) return false;
		$DATA = explode(" ", $this->_message);
		return [$DATA[1], $DATA[3]];
	}

	public function delete(string $pathname): bool {
		if(!$this->_exec("DELE ".$pathname, "delete")) return false;
		if(!$this->_checkCode()) return false;
		return true;
	}

	public function site(string $command, string $fnction = "site"): bool {
		if(!$this->_exec("SITE ".$command, $fnction)) return false;
		if(!$this->_checkCode()) return false;
		return true;
	}

	public function chmod(string $pathname, int $mode): bool {
		if(!$this->site( sprintf('CHMOD %o %s', $mode, $pathname), "chmod")) return false;
		return true;
	}

	public function restore(int $from): bool {
		if(!isset($this->_features["REST"])) {
			$this->SendMSG("Error: restore not supported by server");
			return false;
		}
		if($this->_curtype !== FTP_BINARY) {
			$this->SendMSG("Error: restore can't restore in ASCII mode");
			return false;
		}
		if(!$this->_exec("REST ".$from, "resore")) return false;
		if(!$this->_checkCode()) return false;
		return true;
	}

	public function features(): bool {
		if(!$this->_exec("FEAT", "features")) return false;
		if(!$this->_checkCode()) return false;
		$f = preg_split("/[".CRLF."]+/", preg_replace("/\d{3}[ -].*?".CRLF."/", "", $this->_message), -1, PREG_SPLIT_NO_EMPTY);
		$this->_features = [];
		foreach($f as $k=>$v) {
			$v = explode(" ", trim($v));
			$this->_features[array_shift($v)] = $v;
		}
		return true;
	}public function rawlist(string $pathname = "", string $arg = ""): array|false {
		return $this->_list(($arg ? " " . $arg : "") . ($pathname ? " " . $pathname : ""), "LIST", "rawlist");
	}

	public function nlist(string $pathname = ""): array|false {
		// Note: $arg is not defined in this function's scope in the original code.
		// Preserving original code as per instructions, which may lead to an Undefined variable notice.
		return $this->_list(($arg ? " " . $arg : "") . ($pathname ? " " . $pathname : ""), "NLST", "nlist");
	}

	public function is_exists(string $pathname): bool {
		return $this->file_exists($pathname);
	}

	public function file_exists(string $pathname): bool {
		$exists = true;
		if (!$this->_exec("RNFR " . $pathname, "rename")) {
            $exists = false;
        } else {
			if (!$this->_checkCode()) {
                $exists = false;
            }
			$this->abort();
		}
		if ($exists) {
            $this->SendMSG("Remote file " . $pathname . " exists");
        } else {
            $this->SendMSG("Remote file " . $pathname . " does not exist");
        }
		return $exists;
	}

	public function fget($fp, string $remotefile, int $rest = 0): bool { // $fp is expected to be a resource
		if ($this->_can_restore && $rest !== 0) {
            fseek($fp, $rest);
        }
		$pi = pathinfo($remotefile);
		if ($this->_type === FTP_ASCII || ($this->_type === FTP_AUTOASCII && in_array(strtoupper($pi["extension"]), $this->AutoAsciiExt))) {
            $mode = FTP_ASCII;
        } else {
            $mode = FTP_BINARY;
        }
		if (!$this->_data_prepare($mode)) {
			return false;
		}
		if ($this->_can_restore && $rest !== 0) {
            $this->restore($rest);
        }
		if (!$this->_exec("RETR " . $remotefile, "get")) {
			$this->_data_close();
			return false;
		}
		if (!$this->_checkCode()) {
			$this->_data_close();
			return false;
		}
		$out = $this->_data_read($mode, $fp);
		$this->_data_close();
		if (!$this->_readmsg()) {
            return false;
        }
		if (!$this->_checkCode()) {
            return false;
        }
		return $out;
	}

	public function get(string $remotefile, ?string $localfile = null, int $rest = 0): bool {
		if ($localfile === null) {
            $localfile = $remotefile;
        }
		if (@file_exists($localfile)) {
            $this->SendMSG("Warning : local file will be overwritten");
        }
		$fp = @fopen($localfile, "w");
		if (!$fp) {
			$this->PushError("get","can't open local file", "Cannot create \"" . $localfile . "\"");
			return false;
		}
		if ($this->_can_restore && $rest !== 0) {
            fseek($fp, $rest);
        }
		$pi = pathinfo($remotefile);
		if ($this->_type === FTP_ASCII || ($this->_type === FTP_AUTOASCII && in_array(strtoupper($pi["extension"]), $this->AutoAsciiExt))) {
            $mode = FTP_ASCII;
        } else {
            $mode = FTP_BINARY;
        }
		if (!$this->_data_prepare($mode)) {
			fclose($fp);
			return false;
		}
		if ($this->_can_restore && $rest !== 0) {
            $this->restore($rest);
        }
		if (!$this->_exec("RETR " . $remotefile, "get")) {
			$this->_data_close();
			fclose($fp);
			return false;
		}
		if (!$this->_checkCode()) {
			$this->_data_close();
			fclose($fp);
			return false;
		}
		$out = $this->_data_read($mode, $fp);
		fclose($fp);
		$this->_data_close();
		if (!$this->_readmsg()) {
            return false;
        }
		if (!$this->_checkCode()) {
            return false;
        }
		return $out;
	}

	public function fput(string $remotefile, $fp): bool { // $fp is expected to be a resource
		// Note: $rest is not defined in this function's scope in the original code.
		// Preserving original code as per instructions, which may lead to an Undefined variable notice.
		if ($this->_can_restore && $rest !== 0) {
            fseek($fp, $rest);
        }
		$pi = pathinfo($remotefile);
		if ($this->_type === FTP_ASCII || ($this->_type === FTP_AUTOASCII && in_array(strtoupper($pi["extension"]), $this->AutoAsciiExt))) {
            $mode = FTP_ASCII;
        } else {
            $mode = FTP_BINARY;
        }
		if (!$this->_data_prepare($mode)) {
			return false;
		}
		if ($this->_can_restore && $rest !== 0) {
            $this->restore($rest);
        }
		if (!$this->_exec("STOR " . $remotefile, "put")) {
			$this->_data_close();
			return false;
		}
		if (!$this->_checkCode()) {
			$this->_data_close();
			return false;
		}
		$ret = $this->_data_write($mode, $fp);
		$this->_data_close();
		if (!$this->_readmsg()) {
            return false;
        }
		if (!$this->_checkCode()) {
            return false;
        }
		return $ret;
	}

	public function put(string $localfile, ?string $remotefile = null, int $rest = 0): bool {
		if ($remotefile === null) {
            $remotefile = $localfile;
        }
		if (!file_exists($localfile)) {
			$this->PushError("put","can't open local file", "No such file or directory \"" . $localfile . "\"");
			return false;
		}
		$fp = @fopen($localfile, "r");

		if (!$fp) {
			$this->PushError("put","can't open local file", "Cannot read file \"" . $localfile . "\"");
			return false;
		}
		if ($this->_can_restore && $rest !== 0) {
            fseek($fp, $rest);
        }
		$pi = pathinfo($localfile);
		if ($this->_type === FTP_ASCII || ($this->_type === FTP_AUTOASCII && in_array(strtoupper($pi["extension"]), $this->AutoAsciiExt))) {
            $mode = FTP_ASCII;
        } else {
            $mode = FTP_BINARY;
        }
		if (!$this->_data_prepare($mode)) {
			fclose($fp);
			return false;
		}
		if ($this->_can_restore && $rest !== 0) {
            $this->restore($rest);
        }
		if (!$this->_exec("STOR " . $remotefile, "put")) {
			$this->_data_close();
			fclose($fp);
			return false;
		}
		if (!$this->_checkCode()) {
			$this->_data_close();
			fclose($fp);
			return false;
		}
		$ret = $this->_data_write($mode, $fp);
		fclose($fp);
		$this->_data_close();
		if (!$this->_readmsg()) {
            return false;
        }
		if (!$this->_checkCode()) {
            return false;
        }
		return $ret;
	}

	public function mput(string $local = ".", ?string $remote = null, bool $continious = false): bool {
		$local = realpath($local);
		if (!@file_exists($local)) {
			$this->PushError("mput","can't open local folder", "Cannot stat folder \"" . $local . "\"");
			return false;
		}
		if (!is_dir($local)) {
            return $this->put($local, $remote);
        }
		if (empty($remote)) {
            $remote = ".";
        } elseif (!$this->file_exists($remote) && !$this->mkdir($remote)) {
            return false;
        }
		if ($handle = opendir($local)) {
			$list = [];
			while (false !== ($file = readdir($handle))) {
				if ($file !== "." && $file !== "..") {
                    $list[] = $file;
                }
			}
			closedir($handle);
		} else {
			$this->PushError("mput","can't open local folder", "Cannot read folder \"" . $local . "\"");
			return false;
		}
		if (empty($list)) {
            return true;
        }
		$ret = true;
		foreach ($list as $el) {
			if (is_dir($local . "/" . $el)) {
                $t = $this->mput($local . "/" . $el, $remote . "/" . $el);
            } else {
                $t = $this->put($local . "/" . $el, $remote . "/" . $el);
            }
			if (!$t) {
				$ret = false;
				if (!$continious) {
                    break;
                }
			}
		}
		return $ret;

	}

	public function mget(string $remote, string $local = ".", bool $continious = false): bool {
		$list = $this->rawlist($remote, "-lA");
		if ($list === false) {
			$this->PushError("mget","can't read remote folder list", "Can't read remote folder \"" . $remote . "\" contents");
			return false;
		}
		if (empty($list)) {
            return true;
        }
		if (!@file_exists($local)) {
			if (!@mkdir($local)) {
				$this->PushError("mget","can't create local folder", "Cannot create folder \"" . $local . "\"");
				return false;
			}
		}
		foreach ($list as $k => $v) {
			$list[$k] = $this->parselisting($v);
			if ($list[$k]["name"] === "." || $list[$k]["name"] === "..") {
                unset($list[$k]);
            }
		}
		$ret = true;
		foreach ($list as $el) {
			if ($el["type"] === "d") {
				if (!$this->mget($remote . "/" . $el["name"], $local . "/" . $el["name"], $continious)) {
					$this->PushError("mget", "can't copy folder", "Can't copy remote folder \"" . $remote . "/" . $el["name"] . "\" to local \"" . $local . "/" . $el["name"] . "\"");
					$ret = false;
					if (!$continious) {
                        break;
                    }
				}
			} else {
				if (!$this->get($remote . "/" . $el["name"], $local . "/" . $el["name"])) {
					$this->PushError("mget", "can't copy file", "Can't copy remote file \"" . $remote . "/" . $el["name"] . "\" to local \"" . $local . "/" . $el["name"] . "\"");
					$ret = false;
					if (!$continious) {
                        break;
                    }
				}
			}
			@chmod($local . "/" . $el["name"], $el["perms"]);
			$t = strtotime($el["date"]);
			if ($t !== -1 && $t !== false) {
                @touch($local . "/" . $el["name"], $t);
            }
		}
		return $ret;
	}

	public function mdel(string $remote, bool $continious = false): bool {
		$list = $this->rawlist($remote, "-la");
		if ($list === false) {
			$this->PushError("mdel","can't read remote folder list", "Can't read remote folder \"" . $remote . "\" contents");
			return false;
		}

		foreach ($list as $k => $v) {
			$list[$k] = $this->parselisting($v);
			if ($list[$k]["name"] === "." || $list[$k]["name"] === "..") {
                unset($list[$k]);
            }
		}
		$ret = true;

		foreach ($list as $el) {
			if (empty($el)) {
				continue;
            }

			if ($el["type"] === "d") {
				if (!$this->mdel($remote . "/" . $el["name"], $continious)) {
					$ret = false;
					if (!$continious) {
                        break;
                    }
				}
			} else {
				if (!$this->delete($remote . "/" . $el["name"])) {
					$this->PushError("mdel", "can't delete file", "Can't delete remote file \"" . $remote . "/" . $el["name"] . "\"");
					$ret = false;
					if (!$continious) {
                        break;
                    }
				}
			}
		}

		// Note: $el is out of scope here if the loop didn't run or if it's the last element.
		// Preserving original code as per instructions, which may lead to an Undefined variable notice.
		if (!$this->rmdir($remote)) {
			$this->PushError("mdel", "can't delete folder", "Can't delete remote folder \"" . $remote . "/" . $el["name"] . "\"");
			$ret = false;
		}
		return $ret;
	}

	public function mmkdir(string $dir, int $mode = 0777): bool {
		if (empty($dir)) {
            return false;
        }
		if ($this->is_exists($dir) || $dir === "/") {
            return true;
        }
		if (!$this->mmkdir(dirname($dir), $mode)) {
            return false;
        }
		$r = $this->mkdir($dir, $mode);
		$this->chmod($dir,$mode);
		return $r;
	}

	public function glob(string $pattern, ?array $handle = null): array|false {
		$path = null;
        $output = null;
		if (PHP_OS === 'WIN32') {
            $slash = '\\';
        } else {
            $slash = '/';
        }
		$lastpos = strrpos($pattern, $slash);
		if (!($lastpos === false)) {
			// Preserving original (potentially buggy) substr logic as per instructions.
			$path = substr($pattern, 0, -$lastpos - 1);
			$pattern = substr($pattern, $lastpos);
		} else {
            $path = getcwd();
        }
		if (is_array($handle) && !empty($handle)) {
			foreach ($handle as $dir) { // Replaced each() with foreach
				if ($this->glob_pattern_match($pattern, $dir)) {
                    $output[] = $dir;
                }
			}
		} else {
			$handle = @opendir($path);
			if ($handle === false) {
                return false;
            }
			while (false !== ($dir = readdir($handle))) {
				if ($this->glob_pattern_match($pattern, $dir)) {
                    $output[] = $dir;
                }
			}
			closedir($handle);
		}
		if (is_array($output)) {
            return $output;
        }
		return false;
	}

	public function glob_pattern_match(string $pattern, string $string): bool {
		$out = null;
		$chunks = explode(';', $pattern);
		foreach ($chunks as $pattern) {
			$escape = ['$', '^', '.', '{', '}', '(', ')', '[', ']', '|'];
			while (strpos($pattern, '**') !== false) {
				$pattern = str_replace('**', '*', $pattern);
            }
			foreach ($escape as $probe) {
				$pattern = str_replace($probe, "\\" . $probe, $pattern);
            }
			$pattern = str_replace('?*', '*',
				str_replace('*?', '*',
					str_replace('*', ".*",
						str_replace('?','.{1,1}',$pattern))));
			$out[] = $pattern;
		}
		if (count($out) === 1) {
            return ($this->glob_regexp("^" . $out[0] . "$", $string));
        } else {
			foreach ($out as $tester) {
				// Note: my_regexp is not defined in this segment. Preserving original call.
				if ($this->my_regexp("^" . $tester . "$", $string)) {
                    return true;
                }
			}
		}
		return false;
	}

	public function glob_regexp(string $pattern, string $probe): bool {
		$sensitive = (PHP_OS !== 'WIN32');
		// ereg/eregi are removed in PHP 7.0, replaced with preg_match.
		// Patterns for ereg/eregi do not use delimiters, but preg_match requires them.
		// The pattern passed to this function already includes ^ and $ anchors.
		return ($sensitive
			? preg_match('/' . $pattern . '/', $probe)
			: preg_match('/' . $pattern . '/i', $probe)
		);
	}

	public function dirlist(string $remote): array|false {
		$list = $this->rawlist($remote, "-la");
		if ($list === false) {
			$this->PushError("dirlist","can't read remote folder list", "Can't read remote folder \"" . $remote . "\" contents");
			return false;
		}

		$dirlist = [];
		foreach ($list as $k => $v) {
			$entry = $this->parselisting($v);
			if (empty($entry)) {
				continue;
            }

			if ($entry["name"] === "." || $entry["name"] === "..") {
				continue;
            }

			$dirlist[$entry['name']] = $entry;
		}

		return $dirlist;
	}
// <!-- --------------------------------------------------------------------------------------- -->
// <!--       Private functions                                                                 -->
// <!-- --------------------------------------------------------------------------------------- -->
	private function _checkCode(): bool {
		return ($this->_code < 400 && $this->_code > 0);
	}

	private function _list(string $arg = "", string $cmd = "LIST", string $fnction = "_list"): array|false {
		if (!$this->_data_prepare()) {
            return false;
        }
		if (!$this->_exec($cmd . $arg, $fnction)) {
			$this->_data_close();
			return false;
		}
		if (!$this->_checkCode()) {
			$this->_data_close();
			return false;
		}
		$out = "";
		if ($this->_code < 200) {
			$out = $this->_data_read();
			$this->_data_close();
			if (!$this->_readmsg()) {
                return false;
            }
			if (!$this->_checkCode()) {
                return false;
            }
			if ($out === false) {
                return false;
            }
			$out = preg_split("/[" . CRLF . "]+/", $out, -1, PREG_SPLIT_NO_EMPTY);
//			$this->SendMSG(implode($this->_eol_code[$this->OS_local], $out));
		}
		return $out;
	}

// <!-- --------------------------------------------------------------------------------------- -->
// <!-- Partie : gestion des erreurs                                                            -->
// <!-- --------------------------------------------------------------------------------------- -->
// Gnre une erreur pour traitement externe  la classe
	public function PushError(string $fctname, string $msg, string|false $desc = false): int {
		$error = [];
		$error['time'] = time();
		$error['fctname'] = $fctname;
		$error['msg'] = $msg;
		$error['desc'] = $desc;
		if ($desc) {
            $tmp = ' (' . $desc . ')';
        } else {
            $tmp = '';
        }
		$this->SendMSG($fctname . ': ' . $msg . $tmp);
		return array_push($this->_error_array, $error);
	}

// Rcupre une erreur externe
	public function PopError(): array|false {
		if (count($this->_error_array)) {
            return array_pop($this->_error_array);
        } else {
            return false;
        }
	}
}

$mod_sockets = extension_loaded( 'sockets' );
if ( ! $mod_sockets && function_exists( 'dl' ) && is_callable( 'dl' ) ) {
	$prefix = ( PHP_SHLIB_SUFFIX === 'dll' ) ? 'php_' : '';
	@dl( $prefix . 'sockets.' . PHP_SHLIB_SUFFIX );
	$mod_sockets = extension_loaded( 'sockets' );
}

require_once "class-ftp-" . ( $mod_sockets ? "sockets" : "pure" ) . ".php";
?>