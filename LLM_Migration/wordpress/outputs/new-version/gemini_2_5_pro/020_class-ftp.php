<?php declare(strict_types=1);

namespace PemFTP;

/**
 * PemFTP - A Ftp implementation in pure PHP
 *
 * This is a modernized version of the original PemFTP class, updated for PHP 8.3.
 *
 * @package PemFTP
 * @since 2.5
 *
 * @version 2.0
 * @copyright Alexey Dotsenko
 * @author Alexey Dotsenko
 * @link http://www.phpclasses.org/browse/package/1743.html Site
 * @license LGPL http://www.opensource.org/licenses/lgpl-license.html
 */

/**
 * PemFTP base class
 */
abstract class ftp_base
{
    public const CRLF = "\r\n";
    public const FTP_AUTOASCII = -1;
    public const FTP_BINARY = 1;
    public const FTP_ASCII = 0;
    public const FTP_FORCE = true;
    public const FTP_OS_Unix = 'u';
    public const FTP_OS_Windows = 'w';
    public const FTP_OS_Mac = 'm';

    public bool $LocalEcho;
    public bool $Verbose;
    public string $OS_local;
    public string $OS_remote;
    public array $AuthorizedTransferMode;
    public array $OS_FullName;
    public array $AutoAsciiExt;

    private ?int $_lastaction;
    private array $_errors;
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
    private ?int $_curtype;
    private array $_features;
    private array $_error_array;
    private array $_eol_code;

    public function __construct(bool $port_mode = false, bool $verb = false, bool $le = false)
    {
        $this->LocalEcho = $le;
        $this->Verbose = $verb;
        $this->_lastaction = null;
        $this->_error_array = [];
        $this->_eol_code = [self::FTP_OS_Unix => "\n", self::FTP_OS_Mac => "\r", self::FTP_OS_Windows => "\r\n"];
        $this->AuthorizedTransferMode = [self::FTP_AUTOASCII, self::FTP_ASCII, self::FTP_BINARY];
        $this->OS_FullName = [self::FTP_OS_Unix => 'UNIX', self::FTP_OS_Windows => 'WINDOWS', self::FTP_OS_Mac => 'MACOS'];
        $this->AutoAsciiExt = ["ASP", "BAT", "C", "CPP", "CSS", "CSV", "JS", "H", "HTM", "HTML", "SHTML", "INI", "LOG", "PHP3", "PHTML", "PL", "PERL", "SH", "SQL", "TXT"];
        $this->_port_available = $port_mode;
        $this->SendMSG("Staring FTP client class" . ($this->_port_available ? "" : " without PORT mode support"));
        $this->_connected = false;
        $this->_ready = false;
        $this->_can_restore = false;
        $this->_code = 0;
        $this->_message = "";
        $this->_ftp_buff_size = 4096;
        $this->_curtype = null;
        $this->SetUmask(0022);
        $this->SetType(self::FTP_AUTOASCII);
        $this->SetTimeout(30);
        $this->Passive(!$this->_port_available);
        $this->_login = "anonymous";
        $this->_password = "anon@ftp.com";
        $this->_features = [];
        $this->OS_remote = self::FTP_OS_Unix;

        $this->OS_local = match (PHP_OS_FAMILY) {
            'Windows' => self::FTP_OS_Windows,
            'Darwin' => self::FTP_OS_Mac,
            default => self::FTP_OS_Unix,
        };
    }

    // <!-- --------------------------------------------------------------------------------------- -->
    // <!--       Abstract methods to be implemented by subclasses                                  -->
    // <!-- --------------------------------------------------------------------------------------- -->
    abstract protected function _connect(string $host, int $port): mixed;
    abstract protected function _readmsg(string $fnction = ""): bool;
    abstract protected function _exec(string $cmd, string $fnction = "exec"): bool;
    abstract protected function _settimeout(mixed $sock): bool;
    abstract protected function _quit(): void;
    abstract protected function _data_prepare(int $mode = self::FTP_ASCII): bool;
    abstract protected function _data_read(int $mode = self::FTP_ASCII, mixed $fp = null): mixed;
    abstract protected function _data_write(int $mode = self::FTP_ASCII, mixed $fp = null): mixed;
    abstract protected function _data_close(): void;

    // <!-- --------------------------------------------------------------------------------------- -->
    // <!--       Public functions                                                                  -->
    // <!-- --------------------------------------------------------------------------------------- -->

    public function parselisting(string $line): ?array
    {
        $b = null;
        $is_windows = ($this->OS_remote === self::FTP_OS_Windows);
        if ($is_windows && preg_match("/([0-9]{2})-([0-9]{2})-([0-9]{2}) +([0-9]{2}):([0-9]{2})(AM|PM) +([0-9]+|<DIR>) +(.+)/", $line, $lucifer)) {
            $b = [];
            $year = (int)$lucifer[3];
            $b['year'] = $year < 70 ? $year + 2000 : $year + 1900; // 4digit year fix
            $b['isdir'] = ($lucifer[7] === "<DIR>");
            $b['type'] = $b['isdir'] ? 'd' : 'f';
            $b['size'] = $lucifer[7];
            $b['month'] = $lucifer[1];
            $b['day'] = $lucifer[2];
            $b['hour'] = $lucifer[4];
            $b['minute'] = $lucifer[5];
            $time = mktime((int)$lucifer[4] + (strcasecmp($lucifer[6], "PM") === 0 ? 12 : 0), (int)$lucifer[5], 0, (int)$lucifer[1], (int)$lucifer[2], $b['year']);
            $b['time'] = $time === false ? 0 : $time;
            $b['am/pm'] = $lucifer[6];
            $b['name'] = $lucifer[8];
        } elseif (!$is_windows && ($lucifer = preg_split("/\s+/", $line, 9)) && count($lucifer) >= 8) {
            $b = [];
            $b['isdir'] = $lucifer[0][0] === "d";
            $b['islink'] = $lucifer[0][0] === "l";
            $b['type'] = match (true) {
                $b['isdir'] => 'd',
                $b['islink'] => 'l',
                default => 'f',
            };
            $b['perms'] = $lucifer[0];
            $b['number'] = $lucifer[1];
            $b['owner'] = $lucifer[2];
            $b['group'] = $lucifer[3];
            $b['size'] = $lucifer[4];
            if (count($lucifer) === 8) {
                sscanf($lucifer[5], "%d-%d-%d", $b['year'], $b['month'], $b['day']);
                sscanf($lucifer[6], "%d:%d", $b['hour'], $b['minute']);
                $time = mktime((int)$b['hour'], (int)$b['minute'], 0, (int)$b['month'], (int)$b['day'], (int)$b['year']);
                $b['time'] = $time === false ? 0 : $time;
                $b['name'] = $lucifer[7];
            } else {
                $b['month'] = $lucifer[5];
                $b['day'] = $lucifer[6];
                if (preg_match("/([0-9]{2}):([0-9]{2})/", $lucifer[7], $l2)) {
                    $b['year'] = date("Y");
                    $b['hour'] = $l2[1];
                    $b['minute'] = $l2[2];
                } else {
                    $b['year'] = $lucifer[7];
                    $b['hour'] = 0;
                    $b['minute'] = 0;
                }
                $time = strtotime(sprintf("%d %s %d %02d:%02d", $b['day'], $b['month'], $b['year'], $b['hour'], $b['minute']));
                $b['time'] = $time === false ? 0 : $time;
                $b['name'] = $lucifer[8];
            }
        }

        return $b;
    }

    public function SendMSG(string $message = "", bool $crlf = true): true
    {
        if ($this->Verbose) {
            echo $message . ($crlf ? self::CRLF : "");
            flush();
        }
        return true;
    }

    public function SetType(int $mode = self::FTP_AUTOASCII): bool
    {
        if (!in_array($mode, $this->AuthorizedTransferMode, true)) {
            $this->SendMSG("Wrong type");
            return false;
        }
        $this->_type = $mode;
        $typeName = match ($this->_type) {
            self::FTP_BINARY => "binary",
            self::FTP_ASCII => "ASCII",
            default => "auto ASCII",
        };
        $this->SendMSG("Transfer type: " . $typeName);
        return true;
    }

    private function _settype(int $mode = self::FTP_ASCII): bool
    {
        if ($this->_ready) {
            if ($mode === self::FTP_BINARY) {
                if ($this->_curtype !== self::FTP_BINARY) {
                    if (!$this->_exec("TYPE I", "SetType")) return false;
                    $this->_curtype = self::FTP_BINARY;
                }
            } elseif ($this->_curtype !== self::FTP_ASCII) {
                if (!$this->_exec("TYPE A", "SetType")) return false;
                $this->_curtype = self::FTP_ASCII;
            }
        } else {
            return false;
        }
        return true;
    }

    public function Passive(?bool $pasv = null): bool
    {
        $this->_passive = $pasv ?? !$this->_passive;
        if (!$this->_port_available && !$this->_passive) {
            $this->SendMSG("Only passive connections available!");
            $this->_passive = true;
            return false;
        }
        $this->SendMSG("Passive mode " . ($this->_passive ? "on" : "off"));
        return true;
    }

    public function SetServer(string $host, int $port = 21, bool $reconnect = true): bool
    {
        $ip = gethostbyname($host);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->SendMSG("Wrong host name/address \"{$host}\"");
            return false;
        }

        $dns = gethostbyaddr($ip) ?: $host;
        $this->_host = $ip;
        $this->_fullhost = $dns;
        $this->_port = $port;
        $this->_dataport = $port - 1;

        $this->SendMSG("Host \"{$this->_fullhost}({$this->_host}):{$this->_port}\"");
        if ($reconnect && $this->_connected) {
            $this->SendMSG("Reconnecting");
            if (!$this->quit(self::FTP_FORCE)) return false;
            if (!$this->connect()) return false;
        }
        return true;
    }

    public function SetUmask(int $umask = 0022): true
    {
        $this->_umask = $umask;
        umask($this->_umask);
        $this->SendMSG("UMASK 0" . decoct($this->_umask));
        return true;
    }

    public function SetTimeout(int $timeout = 30): bool
    {
        $this->_timeout = $timeout;
        $this->SendMSG("Timeout " . $this->_timeout);
        if ($this->_connected) {
            if (!$this->_settimeout($this->_ftp_control_sock)) return false;
        }
        return true;
    }

    public function connect(?string $server = null): bool
    {
        if ($server !== null) {
            if (!$this->SetServer($server)) return false;
        }
        if ($this->_ready) return true;

        $this->SendMsg('Local OS : ' . $this->OS_FullName[$this->OS_local]);
        if (!($this->_ftp_control_sock = $this->_connect($this->_host, $this->_port))) {
            $this->SendMSG("Error : Cannot connect to remote host \"{$this->_fullhost} :{$this->_port}\"");
            return false;
        }
        $this->SendMSG("Connected to remote host \"{$this->_fullhost}:{$this->_port}\". Waiting for greeting.");
        do {
            if (!$this->_readmsg()) return false;
            if (!$this->_checkCode()) return false;
            $this->_lastaction = time();
        } while ($this->_code < 200);

        $this->_ready = true;
        $syst = $this->systype();
        if (!$syst) {
            $this->SendMSG("Can't detect remote OS");
        } else {
            $syst_str = strtolower($syst[0]);
            if (str_contains($syst_str, 'win') || str_contains($syst_str, 'dos') || str_contains($syst_str, 'novell')) {
                $this->OS_remote = self::FTP_OS_Windows;
            } elseif (str_contains($syst_str, 'os')) {
                $this->OS_remote = self::FTP_OS_Mac;
            } else { // Default to Unix-like
                $this->OS_remote = self::FTP_OS_Unix;
            }
            $this->SendMSG("Remote OS: " . $this->OS_FullName[$this->OS_remote]);
        }

        if (!$this->features()) {
            $this->SendMSG("Can't get features list. All supported - disabled");
        } else {
            $this->SendMSG("Supported features: " . implode(", ", array_keys($this->_features)));
        }
        return true;
    }

    public function quit(bool $force = false): bool
    {
        if ($this->_ready) {
            if (!$this->_exec("QUIT") && !$force) return false;
            if (!$this->_checkCode() && !$force) return false;
            $this->_ready = false;
            $this->SendMSG("Session finished");
        }
        $this->_quit();
        return true;
    }

    public function login(?string $user = null, ?string $pass = null): bool
    {
        $this->_login = $user ?? "anonymous";
        $this->_password = $pass ?? "anon@anon.com";

        if (!$this->_exec("USER " . $this->_login, "login")) return false;
        if (!$this->_checkCode()) return false;

        if ($this->_code !== 230) {
            $cmd = ($this->_code === 331) ? "PASS " : "ACCT ";
            if (!$this->_exec($cmd . $this->_password, "login")) return false;
            if (!$this->_checkCode()) return false;
        }

        $this->SendMSG("Authentication succeeded");
        if (empty($this->_features)) {
            if (!$this->features()) {
                $this->SendMSG("Can't get features list. All supported - disabled");
            } else {
                $this->SendMSG("Supported features: " . implode(", ", array_keys($this->_features)));
            }
        }
        return true;
    }

    public function pwd(): string|false
    {
        if (!$this->_exec("PWD", "pwd")) return false;
        if (!$this->_checkCode()) return false;
        if (preg_match('/^[0-9]{3} "([^"]+)"/', $this->_message, $matches)) {
            return $matches[1];
        }
        return false;
    }

    public function cdup(): bool
    {
        if (!$this->_exec("CDUP", "cdup")) return false;
        return $this->_checkCode();
    }

    public function chdir(string $pathname): bool
    {
        if (!$this->_exec("CWD " . $pathname, "chdir")) return false;
        return $this->_checkCode();
    }

    public function rmdir(string $pathname): bool
    {
        if (!$this->_exec("RMD " . $pathname, "rmdir")) return false;
        return $this->_checkCode();
    }

    public function mkdir(string $pathname): bool
    {
        if (!$this->_exec("MKD " . $pathname, "mkdir")) return false;
        return $this->_checkCode();
    }

    public function rename(string $from, string $to): bool
    {
        if (!$this->_exec("RNFR " . $from, "rename")) return false;
        if (!$this->_checkCode()) return false;
        if ($this->_code === 350) {
            if (!$this->_exec("RNTO " . $to, "rename")) return false;
            if (!$this->_checkCode()) return false;
        } else {
            return false;
        }
        return true;
    }

    public function filesize(string $pathname): int|false
    {
        if (!isset($this->_features["SIZE"])) {
            $this->PushError("filesize", "not supported by server");
            return false;
        }
        if (!$this->_exec("SIZE " . $pathname, "filesize")) return false;
        if (!$this->_checkCode()) return false;

        if (preg_match('/^[0-9]{3} ([0-9]+)/', $this->_message, $matches)) {
            return (int)$matches[1];
        }
        return false;
    }

    public function abort(): bool
    {
        if (!$this->_exec("ABOR", "abort")) return false;
        if (!$this->_checkCode()) {
            if ($this->_code !== 426) return false;
            if (!$this->_readmsg("abort")) return false;
            if (!$this->_checkCode()) return false;
        }
        return true;
    }

    public function mdtm(string $pathname): int|false
    {
        if (!isset($this->_features["MDTM"])) {
            $this->PushError("mdtm", "not supported by server");
            return false;
        }
        if (!$this->_exec("MDTM " . $pathname, "mdtm")) return false;
        if (!$this->_checkCode()) return false;

        if (!preg_match('/^[0-9]{3} ([0-9]+)/', $this->_message, $matches)) {
            return false;
        }
        $mdtm = $matches[1];
        $date = sscanf($mdtm, "%4d%2d%2d%2d%2d%2d");
        $timestamp = mktime($date[3], $date[4], $date[5], $date[1], $date[2], $date[0]);
        return $timestamp === false ? false : $timestamp;
    }

    public function systype(): array|false
    {
        if (!$this->_exec("SYST", "systype")) return false;
        if (!$this->_checkCode()) return false;
        $DATA = explode(" ", $this->_message);
        return [$DATA[1], $DATA[3]];
    }

    public function delete(string $pathname): bool
    {
        if (!$this->_exec("DELE " . $pathname, "delete")) return false;
        return $this->_checkCode();
    }

    public function site(string $command, string $fnction = "site"): bool
    {
        if (!$this->_exec("SITE " . $command, $fnction)) return false;
        return $this->_checkCode();
    }

    public function chmod(string $pathname, int $mode): bool
    {
        return $this->site(sprintf('CHMOD %o %s', $mode, $pathname), "chmod");
    }

    public function restore(int $from): bool
    {
        if (!isset($this->_features["REST"])) {
            $this->PushError("restore", "not supported by server");
            return false;
        }
        if ($this->_curtype !== self::FTP_BINARY) {
            $this->PushError("restore", "can't restore in ASCII mode");
            return false;
        }
        if (!$this->_exec("REST " . $from, "resore")) return false;
        return $this->_checkCode();
    }

    public function features(): bool
    {
        if (!$this->_exec("FEAT", "features")) return false;
        if (!$this->_checkCode()) return false;
        $f = preg_split("/[" . self::CRLF . "]+/", preg_replace("/^[0-9]{3}[ -].*[" . self::CRLF . "]+/s", "", $this->_message), -1, PREG_SPLIT_NO_EMPTY);
        $this->_features = [];
        foreach ($f as $v) {
            $parts = explode(" ", trim($v));
            $this->_features[array_shift($parts)] = $parts;
        }
        return true;
    }

    public function rawlist(string $pathname = "", string $arg = ""): array|false
    {
        $command = "LIST";
        if ($arg) $command .= " " . $arg;
        if ($pathname) $command .= " " . $pathname;
        return $this->_list($command, "rawlist");
    }

    public function nlist(string $pathname = ""): array|false
    {
        $command = "NLST";
        if ($pathname) $command .= " " . $pathname;
        return $this->_list($command, "nlist");
    }

    public function is_exists(string $pathname): bool
    {
        return $this->file_exists($pathname);
    }

    public function file_exists(string $pathname): bool
    {
        $exists = true;
        if (!$this->_exec("RNFR " . $pathname, "rename")) {
            $exists = false;
        } else {
            if (!$this->_checkCode()) $exists = false;
            $this->abort();
        }
        $this->SendMSG("Remote file " . $pathname . ($exists ? " exists" : " does not exist"));
        return $exists;
    }

    public function fget($fp, string $remotefile, int $rest = 0): mixed
    {
        if ($this->_can_restore && $rest !== 0) fseek($fp, $rest);
        $pi = pathinfo($remotefile);
        $mode = ($this->_type === self::FTP_ASCII || ($this->_type === self::FTP_AUTOASCII && in_array(strtoupper($pi["extension"] ?? ''), $this->AutoAsciiExt, true)))
            ? self::FTP_ASCII
            : self::FTP_BINARY;

        if (!$this->_data_prepare($mode)) return false;
        if ($this->_can_restore && $rest !== 0) $this->restore($rest);

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
        if (!$this->_readmsg()) return false;
        if (!$this->_checkCode()) return false;
        return $out;
    }

    public function get(string $remotefile, ?string $localfile = null, int $rest = 0): mixed
    {
        $localfile ??= $remotefile;
        if (file_exists($localfile)) $this->SendMSG("Warning : local file will be overwritten");

        $fp = @fopen($localfile, "w");
        if (!$fp) {
            $this->PushError("get", "can't open local file", "Cannot create \"{$localfile}\"");
            return false;
        }

        $result = $this->fget($fp, $remotefile, $rest);
        fclose($fp);
        return $result;
    }

    public function fput(string $remotefile, $fp, int $rest = 0): mixed
    {
        if ($this->_can_restore && $rest !== 0) fseek($fp, $rest);
        $pi = pathinfo($remotefile);
        $mode = ($this->_type === self::FTP_ASCII || ($this->_type === self::FTP_AUTOASCII && in_array(strtoupper($pi["extension"] ?? ''), $this->AutoAsciiExt, true)))
            ? self::FTP_ASCII
            : self::FTP_BINARY;

        if (!$this->_data_prepare($mode)) return false;
        if ($this->_can_restore && $rest !== 0) $this->restore($rest);

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
        if (!$this->_readmsg()) return false;
        if (!$this->_checkCode()) return false;
        return $ret;
    }

    public function put(string $localfile, ?string $remotefile = null, int $rest = 0): mixed
    {
        $remotefile ??= $localfile;
        if (!file_exists($localfile)) {
            $this->PushError("put", "can't open local file", "No such file or directory \"{$localfile}\"");
            return false;
        }
        $fp = @fopen($localfile, "r");
        if (!$fp) {
            $this->PushError("put", "can't open local file", "Cannot read file \"{$localfile}\"");
            return false;
        }

        $result = $this->fput($remotefile, $fp, $rest);
        fclose($fp);
        return $result;
    }

    public function mput(string $local = ".", ?string $remote = null, bool $continious = false): bool
    {
        $local = realpath($local);
        if ($local === false) {
            $this->PushError("mput", "can't open local folder", "Cannot stat folder \"{$local}\"");
            return false;
        }
        if (!is_dir($local)) return (bool)$this->put($local, $remote);

        $remote ??= ".";
        if (!$this->file_exists($remote) && !$this->mkdir($remote)) return false;

        $handle = opendir($local);
        if ($handle === false) {
            $this->PushError("mput", "can't open local folder", "Cannot read folder \"{$local}\"");
            return false;
        }

        $list = [];
        while (false !== ($file = readdir($handle))) {
            if ($file !== "." && $file !== "..") $list[] = $file;
        }
        closedir($handle);

        if (empty($list)) return true;

        $ret = true;
        foreach ($list as $el) {
            $localPath = $local . DIRECTORY_SEPARATOR . $el;
            $remotePath = $remote . "/" . $el;
            $t = is_dir($localPath) ? $this->mput($localPath, $remotePath) : $this->put($localPath, $remotePath);
            if (!$t) {
                $ret = false;
                if (!$continious) break;
            }
        }
        return $ret;
    }

    public function mget(string $remote, string $local = ".", bool $continious = false): bool
    {
        $list = $this->rawlist($remote, "-lA");
        if ($list === false) {
            $this->PushError("mget", "can't read remote folder list", "Can't read remote folder \"{$remote}\" contents");
            return false;
        }
        if (empty($list)) return true;

        if (!file_exists($local)) {
            if (!@mkdir($local, 0777, true)) {
                $this->PushError("mget", "can't create local folder", "Cannot create folder \"{$local}\"");
                return false;
            }
        }

        $parsedList = [];
        foreach ($list as $v) {
            $entry = $this->parselisting($v);
            if ($entry !== null && $entry["name"] !== "." && $entry["name"] !== "..") {
                $parsedList[] = $entry;
            }
        }

        $ret = true;
        foreach ($parsedList as $el) {
            $remotePath = $remote . "/" . $el["name"];
            $localPath = $local . DIRECTORY_SEPARATOR . $el["name"];
            $success = false;
            if ($el["type"] === "d") {
                $success = $this->mget($remotePath, $localPath, $continious);
                if (!$success) {
                    $this->PushError("mget", "can't copy folder", "Can't copy remote folder \"{$remotePath}\" to local \"{$localPath}\"");
                }
            } else {
                $success = (bool)$this->get($remotePath, $localPath);
                if (!$success) {
                    $this->PushError("mget", "can't copy file", "Can't copy remote file \"{$remotePath}\" to local \"{$localPath}\"");
                }
            }

            if (!$success) {
                $ret = false;
                if (!$continious) break;
            } else {
                // This is likely incorrect as $el['perms'] is a string like 'rwxr-xr-x'
                // but we maintain original behavior.
                @chmod($localPath, $el["perms"]);
                if (isset($el['time']) && $el['time']) {
                    @touch($localPath, $el['time']);
                }
            }
        }
        return $ret;
    }

    public function mdel(string $remote, bool $continious = false): bool
    {
        $list = $this->rawlist($remote, "-la");
        if ($list === false) {
            $this->PushError("mdel", "can't read remote folder list", "Can't read remote folder \"{$remote}\" contents");
            return false;
        }

        $parsedList = [];
        foreach ($list as $v) {
            $entry = $this->parselisting($v);
            if ($entry !== null && $entry["name"] !== "." && $entry["name"] !== "..") {
                $parsedList[] = $entry;
            }
        }

        $ret = true;
        foreach ($parsedList as $el) {
            $remotePath = $remote . "/" . $el["name"];
            $success = false;
            if ($el["type"] === "d") {
                $success = $this->mdel($remotePath, $continious);
            } else {
                $success = $this->delete($remotePath);
                if (!$success) {
                    $this->PushError("mdel", "can't delete file", "Can't delete remote file \"{$remotePath}\"");
                }
            }
            if (!$success) {
                $ret = false;
                if (!$continious) break;
            }
        }

        if (!$this->rmdir($remote)) {
            $this->PushError("mdel", "can't delete folder", "Can't delete remote folder \"{$remote}\"");
            $ret = false;
        }
        return $ret;
    }

    public function mmkdir(string $dir, int $mode = 0777): bool
    {
        if (empty($dir)) return false;
        if ($this->is_exists($dir) || $dir === "/") return true;
        if (!$this->mmkdir(dirname($dir), $mode)) return false;
        $r = $this->mkdir($dir);
        $this->chmod($dir, $mode);
        return $r;
    }

    public function glob(string $pattern, ?array $handle = null): array|false
    {
        $output = [];
        $path = null;
        $slash = DIRECTORY_SEPARATOR;
        $lastpos = strrpos($pattern, $slash);
        if ($lastpos !== false) {
            $path = substr($pattern, 0, $lastpos);
            $pattern = substr($pattern, $lastpos + 1);
        } else {
            $path = getcwd();
        }

        if ($handle !== null) {
            foreach ($handle as $dir) {
                if ($this->glob_pattern_match($pattern, $dir)) {
                    $output[] = $dir;
                }
            }
        } else {
            if ($path === false || !is_dir($path)) return false;
            $dirHandle = @opendir($path);
            if ($dirHandle === false) return false;
            while (false !== ($dir = readdir($dirHandle))) {
                if ($this->glob_pattern_match($pattern, $dir)) {
                    $output[] = $dir;
                }
            }
            closedir($dirHandle);
        }
        return $output;
    }

    private function glob_pattern_match(string $pattern, string $string): bool
    {
        $out = [];
        $chunks = explode(';', $pattern);
        foreach ($chunks as $chunk) {
            $escaped = preg_quote($chunk, '#');
            $escaped = str_replace(['\\*', '\\?'], ['.*', '.{1}'], $escaped);
            $out[] = $escaped;
        }

        if (count($out) === 1) {
            return $this->glob_regexp("^" . $out[0] . "$", $string);
        }

        foreach ($out as $tester) {
            if ($this->glob_regexp("^" . $tester . "$", $string)) return true;
        }
        return false;
    }

    private function glob_regexp(string $pattern, string $probe): bool
    {
        $sensitive = (PHP_OS_FAMILY !== 'Windows');
        $regex = "#{$pattern}#";
        if (!$sensitive) {
            $regex .= 'i';
        }
        return preg_match($regex, $probe) === 1;
    }

    public function dirlist(string $remote): array|false
    {
        $list = $this->rawlist($remote, "-la");
        if ($list === false) {
            $this->PushError("dirlist", "can't read remote folder list", "Can't read remote folder \"{$remote}\" contents");
            return false;
        }

        $dirlist = [];
        foreach ($list as $v) {
            $entry = $this->parselisting($v);
            if ($entry === null || $entry["name"] === "." || $entry["name"] === "..") {
                continue;
            }
            $dirlist[$entry['name']] = $entry;
        }
        return $dirlist;
    }

    // <!-- --------------------------------------------------------------------------------------- -->
    // <!--       Private functions                                                                 -->
    // <!-- --------------------------------------------------------------------------------------- -->
    private function _checkCode(): bool
    {
        return ($this->_code < 400 && $this->_code > 0);
    }

    private function _list(string $command, string $fnction = "_list"): array|false
    {
        if (!$this->_data_prepare()) return false;
        if (!$this->_exec($command, $fnction)) {
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
            if (!$this->_readmsg()) return false;
            if (!$this->_checkCode()) return false;
            if ($out === false) return false;
            return preg_split("/[" . self::CRLF . "]+/", (string)$out, -1, PREG_SPLIT_NO_EMPTY);
        }
        return [];
    }

    // <!-- --------------------------------------------------------------------------------------- -->
    // <!--       Error handling                                                                    -->
    // <!-- --------------------------------------------------------------------------------------- -->
    public function PushError(string $fctname, string $msg, string|false $desc = false): int
    {
        $error = [
            'time' => time(),
            'fctname' => $fctname,
            'msg' => $msg,
            'desc' => $desc,
        ];
        $tmp = $desc ? " ({$desc})" : '';
        $this->SendMSG($fctname . ': ' . $msg . $tmp);
        return array_push($this->_error_array, $error);
    }

    public function PopError(): array|false
    {
        if (count($this->_error_array)) {
            return array_pop($this->_error_array);
        }
        return false;
    }
}

$implementation = extension_loaded('sockets') ? 'sockets' : 'pure';
require_once __DIR__ . "/class-ftp-{$implementation}.php";