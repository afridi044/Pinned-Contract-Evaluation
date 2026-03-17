<?php
declare(strict_types=1);

/**
 * PemFTP - A Ftp implementation in pure PHP
 *
 * @package PemFTP
 * @since 2.5
 *
 * @version 1.0
 */

/**
 * Defines the newline characters, if not defined already.
 *
 * This can be redefined.
 *
 * @since 2.5
 * @var string
 */
if (!defined('CRLF')) {
    define('CRLF', "\r\n");
}

/**
 * Sets whatever to autodetect ASCII mode.
 *
 * This can be redefined.
 *
 * @since 2.5
 * @var int
 */
if (!defined('FTP_AUTOASCII')) {
    define('FTP_AUTOASCII', -1);
}

/**
 *
 * This can be redefined.
 * @since 2.5
 * @var int
 */
if (!defined('FTP_BINARY')) {
    define('FTP_BINARY', 1);
}

/**
 *
 * This can be redefined.
 * @since 2.5
 * @var int
 */
if (!defined('FTP_ASCII')) {
    define('FTP_ASCII', 0);
}

/**
 * Whether to force FTP.
 *
 * This can be redefined.
 *
 * @since 2.5
 * @var bool
 */
if (!defined('FTP_FORCE')) {
    define('FTP_FORCE', true);
}

/**
 * @since 2.5
 * @var string
 */
define('FTP_OS_Unix', 'u');

/**
 * @since 2.5
 * @var string
 */
define('FTP_OS_Windows', 'w');

/**
 * @since 2.5
 * @var string
 */
define('FTP_OS_Mac', 'm');

/**
 * PemFTP base class
 */
class ftp_base
{
    public bool $LocalEcho = false;
    public bool $Verbose = false;
    public string $OS_local = FTP_OS_Unix;
    public string $OS_remote = FTP_OS_Unix;
    protected ?int $_lastaction = null;
    protected array $_errors = [];
    protected int $_type = FTP_AUTOASCII;
    protected int $_umask = 0o022;
    protected int $_timeout = 30;
    protected bool $_passive = true;
    protected string $_host = '';
    protected string $_fullhost = '';
    protected ?int $_port = null;
    protected ?string $_datahost = null;
    protected ?int $_dataport = null;
    protected mixed $_ftp_control_sock = null;
    protected mixed $_ftp_data_sock = null;
    protected mixed $_ftp_temp_sock = null;
    protected int $_ftp_buff_size = 4096;
    protected string $_login = 'anonymous';
    protected string $_password = 'anon@ftp.com';
    protected bool $_connected = false;
    protected bool $_ready = false;
    protected int $_code = 0;
    protected string $_message = '';
    protected bool $_can_restore = false;
    protected bool $_port_available = false;
    protected ?int $_curtype = null;
    protected array $_features = [];
    protected array $_error_array = [];
    public array $AuthorizedTransferMode = [];
    public array $OS_FullName = [];
    protected array $_eol_code = [];
    public array $AutoAsciiExt = [];
    public array $features = [];

    public function __construct(bool $port_mode = false, bool $verb = false, bool $le = false)
    {
        $this->LocalEcho = $le;
        $this->Verbose = $verb;
        $this->_lastaction = null;
        $this->_error_array = [];
        $this->_eol_code = [
            FTP_OS_Unix => "\n",
            FTP_OS_Mac => "\r",
            FTP_OS_Windows => "\r\n",
        ];
        $this->AuthorizedTransferMode = [FTP_AUTOASCII, FTP_ASCII, FTP_BINARY];
        $this->OS_FullName = [
            FTP_OS_Unix => 'UNIX',
            FTP_OS_Windows => 'WINDOWS',
            FTP_OS_Mac => 'MACOS',
        ];
        $this->AutoAsciiExt = [
            'ASP', 'BAT', 'C', 'CPP', 'CSS', 'CSV', 'JS', 'H', 'HTM', 'HTML',
            'SHTML', 'INI', 'LOG', 'PHP3', 'PHTML', 'PL', 'PERL', 'SH', 'SQL', 'TXT',
        ];
        $this->_port_available = $port_mode;
        $this->SendMSG('Starting FTP client class' . ($this->_port_available ? '' : ' without PORT mode support'));
        $this->_connected = false;
        $this->_ready = false;
        $this->_can_restore = false;
        $this->_code = 0;
        $this->_message = '';
        $this->_ftp_buff_size = 4096;
        $this->_curtype = null;
        $this->SetUmask(0o022);
        $this->SetType(FTP_AUTOASCII);
        $this->SetTimeout(30);
        $this->Passive(!$this->_port_available);
        $this->_login = 'anonymous';
        $this->_password = 'anon@ftp.com';
        $this->_features = [];
        $this->OS_local = FTP_OS_Unix;
        $this->OS_remote = FTP_OS_Unix;
        $this->features = [];
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            $this->OS_local = FTP_OS_Windows;
        } elseif (strncasecmp(PHP_OS, 'MAC', 3) === 0) {
            $this->OS_local = FTP_OS_Mac;
        }
    }

    public function ftp_base(bool $port_mode = false): void
    {
        $this->__construct($port_mode);
    }

    public function parselisting(string $line): array
    {
        $entry = [];
        $isWindows = ($this->OS_remote === FTP_OS_Windows);

        if ($isWindows && preg_match(
            '/([0-9]{2})-([0-9]{2})-([0-9]{2})\s+([0-9]{2}):([0-9]{2})(AM|PM)\s+([0-9]+|<DIR>)\s+(.+)/',
            $line,
            $matches
        )) {
            $year = (int) $matches[3];
            $year += ($year < 70) ? 2000 : 1900;
            $entry['isdir'] = ($matches[7] === '<DIR>');
            $entry['type'] = $entry['isdir'] ? 'd' : 'f';
            $entry['size'] = $entry['isdir'] ? 0 : (int) $matches[7];
            $entry['month'] = (int) $matches[1];
            $entry['day'] = (int) $matches[2];
            $entry['year'] = $year;
            $hour = (int) $matches[4];
            $minute = (int) $matches[5];
            if (strcasecmp($matches[6], 'PM') === 0 && $hour < 12) {
                $hour += 12;
            }
            if (strcasecmp($matches[6], 'AM') === 0 && $hour === 12) {
                $hour = 0;
            }
            $entry['hour'] = $hour;
            $entry['minute'] = $minute;
            $entry['time'] = mktime($hour, $minute, 0, $entry['month'], $entry['day'], $year);
            $entry['am/pm'] = $matches[6];
            $entry['name'] = $matches[8];
        } elseif (!$isWindows) {
            $parts = preg_split('/\s+/', $line, 9, PREG_SPLIT_NO_EMPTY);
            if (is_array($parts) && count($parts) >= 8) {
                $entry['isdir'] = isset($parts[0][0]) && $parts[0][0] === 'd';
                $entry['islink'] = isset($parts[0][0]) && $parts[0][0] === 'l';
                if ($entry['isdir']) {
                    $entry['type'] = 'd';
                } elseif ($entry['islink']) {
                    $entry['type'] = 'l';
                } else {
                    $entry['type'] = 'f';
                }
                $entry['perms'] = $parts[0] ?? '';
                $entry['number'] = $parts[1] ?? '';
                $entry['owner'] = $parts[2] ?? '';
                $entry['group'] = $parts[3] ?? '';
                $entry['size'] = isset($parts[4]) ? (int) $parts[4] : 0;
                if (count($parts) === 8) {
                    sscanf($parts[5], "%d-%d-%d", $year, $month, $day);
                    sscanf($parts[6], "%d:%d", $hour, $minute);
                    $entry['year'] = (int) $year;
                    $entry['month'] = (int) $month;
                    $entry['day'] = (int) $day;
                    $entry['hour'] = (int) $hour;
                    $entry['minute'] = (int) $minute;
                    $entry['time'] = mktime($hour, $minute, 0, $month, $day, $year);
                    $entry['name'] = $parts[7] ?? '';
                } else {
                    $entry['month'] = $parts[5] ?? '';
                    $entry['day'] = isset($parts[6]) ? (int) $parts[6] : 0;
                    if (isset($parts[7]) && preg_match('/([0-9]{2}):([0-9]{2})/', $parts[7], $timeParts)) {
                        $entry['year'] = (int) date('Y');
                        $entry['hour'] = (int) $timeParts[1];
                        $entry['minute'] = (int) $timeParts[2];
                    } else {
                        $entry['year'] = isset($parts[7]) ? (int) $parts[7] : (int) date('Y');
                        $entry['hour'] = 0;
                        $entry['minute'] = 0;
                    }
                    $entry['time'] = strtotime(sprintf(
                        '%d %s %d %02d:%02d',
                        $entry['day'],
                        $entry['month'],
                        $entry['year'],
                        $entry['hour'] ?? 0,
                        $entry['minute'] ?? 0
                    )) ?: 0;
                    $entry['name'] = $parts[8] ?? '';
                }
            }
        }

        return $entry;
    }

    public function SendMSG(string $message = '', bool $crlf = true): bool
    {
        if ($this->Verbose) {
            echo $message . ($crlf ? CRLF : '');
            flush();
        }
        return true;
    }

    public function SetType(int $mode = FTP_AUTOASCII): bool
    {
        if (!in_array($mode, $this->AuthorizedTransferMode, true)) {
            $this->SendMSG('Wrong type');
            return false;
        }
        $this->_type = $mode;
        $this->SendMSG('Transfer type: ' . ($this->_type === FTP_BINARY ? 'binary' : ($this->_type === FTP_ASCII ? 'ASCII' : 'auto ASCII')));
        return true;
    }

    protected function _settype(int $mode = FTP_ASCII): bool
    {
        if ($this->_ready) {
            if ($mode === FTP_BINARY) {
                if ($this->_curtype !== FTP_BINARY) {
                    if (!$this->_exec('TYPE I', 'SetType')) {
                        return false;
                    }
                    $this->_curtype = FTP_BINARY;
                }
            } elseif ($this->_curtype !== FTP_ASCII) {
                if (!$this->_exec('TYPE A', 'SetType')) {
                    return false;
                }
                $this->_curtype = FTP_ASCII;
            }
        } else {
            return false;
        }
        return true;
    }

    public function Passive(?bool $pasv = null): bool
    {
        if ($pasv === null) {
            $this->_passive = !$this->_passive;
        } else {
            $this->_passive = $pasv;
        }
        if (!$this->_port_available && !$this->_passive) {
            $this->SendMSG('Only passive connections available!');
            $this->_passive = true;
            return false;
        }
        $this->SendMSG('Passive mode ' . ($this->_passive ? 'on' : 'off'));
        return true;
    }

    public function SetServer(string $host, int $port = 21, bool $reconnect = true): bool
    {
        if ($port <= 0 || $port > 65535) {
            $this->Verbose = true;
            $this->SendMSG('Incorrect port syntax');
            return false;
        }

        $ip = @gethostbyname($host);
        $dns = @gethostbyaddr($host);
        if (!$ip) {
            $ip = $host;
        }
        if (!$dns) {
            $dns = $host;
        }
        $ipaslong = ip2long($ip);
        if ($ipaslong === false || $ipaslong === -1) {
            $this->SendMSG('Wrong host name/address "' . $host . '"');
            return false;
        }
        $this->_host = $ip;
        $this->_fullhost = $dns;
        $this->_port = $port;
        $this->_dataport = $port - 1;

        $this->SendMSG('Host "' . $this->_fullhost . '(' . $this->_host . '):' . $this->_port . '"');
        if ($reconnect && $this->_connected) {
            $this->SendMSG('Reconnecting');
            if (!$this->quit(FTP_FORCE)) {
                return false;
            }
            if (!$this->connect()) {
                return false;
            }
        }
        return true;
    }

    public function SetUmask(int $umask = 0o022): bool
    {
        $this->_umask = $umask;
        umask($this->_umask);
        $this->SendMSG('UMASK 0' . decoct($this->_umask));
        return true;
    }

    public function SetTimeout(int $timeout = 30): bool
    {
        $this->_timeout = $timeout;
        $this->SendMSG('Timeout ' . $this->_timeout);
        if ($this->_connected) {
            if (!$this->_settimeout($this->_ftp_control_sock)) {
                return false;
            }
        }
        return true;
    }

    public function connect(?string $server = null): bool
    {
        if (!empty($server)) {
            if (!$this->SetServer($server)) {
                return false;
            }
        }
        if ($this->_ready) {
            return true;
        }
        $this->SendMSG('Local OS : ' . ($this->OS_FullName[$this->OS_local] ?? 'UNKNOWN'));
        if (!($this->_ftp_control_sock = $this->_connect($this->_host, (int) $this->_port))) {
            $this->SendMSG('Error : Cannot connect to remote host "' . $this->_fullhost . ' :' . $this->_port . '"');
            return false;
        }
        $this->SendMSG('Connected to remote host "' . $this->_fullhost . ':' . $this->_port . '". Waiting for greeting.');
        do {
            if (!$this->_readmsg()) {
                return false;
            }
            if (!$this->_checkCode()) {
                return false;
            }
            $this->_lastaction = time();
        } while ($this->_code < 200);
        $this->_ready = true;
        $syst = $this->systype();
        if ($syst === false) {
            $this->SendMSG("Can't detect remote OS");
        } else {
            $systemString = strtolower($syst[0] ?? '');
            if (preg_match('/win|dos|novell/', $systemString)) {
                $this->OS_remote = FTP_OS_Windows;
            } elseif (preg_match('/os/', $systemString)) {
                $this->OS_remote = FTP_OS_Mac;
            } elseif (preg_match('/(li|u)nix/', $systemString)) {
                $this->OS_remote = FTP_OS_Unix;
            } else {
                $this->OS_remote = FTP_OS_Mac;
            }
            $this->SendMSG('Remote OS: ' . ($this->OS_FullName[$this->OS_remote] ?? 'UNKNOWN'));
        }
        if (!$this->features()) {
            $this->SendMSG("Can't get features list. All supported - disabled");
        } else {
            $this->SendMSG('Supported features: ' . implode(', ', array_keys($this->_features)));
        }
        return true;
    }

    public function quit(bool $force = false): bool
    {
        if ($this->_ready) {
            if (!$this->_exec('QUIT') && !$force) {
                return false;
            }
            if (!$this->_checkCode() && !$force) {
                return false;
            }
            $this->_ready = false;
            $this->SendMSG('Session finished');
        }
        $this->_quit();
        return true;
    }

    public function login(?string $user = null, ?string $pass = null): bool
    {
        $this->_login = $user ?? 'anonymous';
        $this->_password = $pass ?? 'anon@anon.com';
        if (!$this->_exec('USER ' . $this->_login, 'login')) {
            return false;
        }
        if (!$this->_checkCode()) {
            return false;
        }
        if ($this->_code !== 230) {
            $command = ($this->_code === 331 ? 'PASS ' : 'ACCT ') . $this->_password;
            if (!$this->_exec($command, 'login')) {
                return false;
            }
            if (!$this->_checkCode()) {
                return false;
            }
        }
        $this->SendMSG('Authentication succeeded');
        if (empty($this->_features)) {
            if (!$this->features()) {
                $this->SendMSG("Can't get features list. All supported - disabled");
            } else {
                $this->SendMSG('Supported features: ' . implode(', ', array_keys($this->_features)));
            }
        }
        return true;
    }

    public function pwd(): string
    {
        if (!$this->_exec('PWD', 'pwd')) {
            return '';
        }
        if (!$this->_checkCode()) {
            return '';
        }
        if (preg_match('/^[0-9]{3}\s+"(.+)"/', $this->_message, $matches)) {
            return $matches[1];
        }
        return '';
    }

    public function cdup(): bool
    {
        if (!$this->_exec('CDUP', 'cdup')) {
            return false;
        }
        if (!$this->_checkCode()) {
            return false;
        }
        return true;
    }

    public function chdir(string $pathname): bool
    {
        if (!$this->_exec('CWD ' . $pathname, 'chdir')) {
            return false;
        }
        if (!$this->_checkCode()) {
            return false;
        }
        return true;
    }

    public function rmdir(string $pathname): bool
    {
        if (!$this->_exec('RMD ' . $pathname, 'rmdir')) {
            return false;
        }
        if (!$this->_checkCode()) {
            return false;
        }
        return true;
    }

    public function mkdir(string $pathname): bool
    {
        if (!$this->_exec('MKD ' . $pathname, 'mkdir')) {
            return false;
        }
        if (!$this->_checkCode()) {
            return false;
        }
        return true;
    }

    public function rename(string $from, string $to): bool
    {
        if (!$this->_exec('RNFR ' . $from, 'rename')) {
            return false;
        }
        if (!$this->_checkCode()) {
            return false;
        }
        if ($this->_code === 350) {
            if (!$this->_exec('RNTO ' . $to, 'rename')) {
                return false;
            }
            if (!$this->_checkCode()) {
                return false;
            }
        } else {
            return false;
        }
        return true;
    }

    public function filesize(string $pathname): int|false
    {
        if (!isset($this->_features['SIZE'])) {
            $this->PushError('filesize', 'not supported by server');
            return false;
        }
        if (!$this->_exec('SIZE ' . $pathname, 'filesize')) {
            return false;
        }
        if (!$this->_checkCode()) {
            return false;
        }
        if (preg_match('/^[0-9]{3}\s+([0-9]+)/', $this->_message, $matches)) {
            return (int) $matches[1];
        }
        return false;
    }

    public function abort(): bool
    {
        if (!$this->_exec('ABOR', 'abort')) {
            return false;
        }
        if (!$this->_checkCode()) {
            if ($this->_code !== 426) {
                return false;
            }
            if (!$this->_readmsg('abort')) {
                return false;
            }
            if (!$this->_checkCode()) {
                return false;
            }
        }
        return true;
    }

    public function mdtm(string $pathname): int|false
    {
        if (!isset($this->_features['MDTM'])) {
            $this->PushError('mdtm', 'not supported by server');
            return false;
        }
        if (!$this->_exec('MDTM ' . $pathname, 'mdtm')) {
            return false;
        }
        if (!$this->_checkCode()) {
            return false;
        }
        if (!preg_match('/^[0-9]{3}\s+([0-9]{14})/', $this->_message, $matches)) {
            return false;
        }
        $mdtm = $matches[1];
        $date = DateTime::createFromFormat('YmdHis', $mdtm, new DateTimeZone('UTC'));
        if ($date === false) {
            return false;
        }
        return $date->getTimestamp();
    }

    public function systype(): array|false
    {
        if (!$this->_exec('SYST', 'systype')) {
            return false;
        }
        if (!$this->_checkCode()) {
            return false;
        }
        $data = preg_split('/\s+/', trim($this->_message));
        $system = $data[1] ?? '';
        $details = $data[3] ?? '';
        return [$system, $details];
    }

    public function delete(string $pathname): bool
    {
        if (!$this->_exec('DELE ' . $pathname, 'delete')) {
            return false;
        }
        if (!$this->_checkCode()) {
            return false;
        }
        return true;
    }

    public function site(string $command, string $fnction = 'site'): bool
    {
        if (!$this->_exec('SITE ' . $command, $fnction)) {
            return false;
        }
        if (!$this->_checkCode()) {
            return false;
        }
        return true;
    }

    public function chmod(string $pathname, int $mode): bool
    {
        return $this->site(sprintf('CHMOD %o %s', $mode, $pathname), 'chmod');
    }

    public function restore(int $from): bool
    {
        if (!isset($this->_features['REST'])) {
            $this->PushError('restore', 'not supported by server');
            return false;
        }
        if ($this->_curtype !== FTP_BINARY) {
            $this->PushError('restore', "can't restore in ASCII mode");
            return false;
        }
        if (!$this->_exec('REST ' . $from, 'restore')) {
            return false;
        }
        if (!$this->_checkCode()) {
            return false;
        }
        return true;
    }

    public function features(): bool
    {
        if (!$this->_exec('FEAT', 'features')) {
            return false;
        }
        if (!$this->_checkCode()) {
            return false;
        }
        $message = preg_replace('/^[0-9]{3}[ -].*(\r\n|\r|\n)/m', '', $this->_message) ?? '';
        $rows = preg_split('/\r\n|\r|\n/', trim($message), -1, PREG_SPLIT_NO_EMPTY);
        $this->_features = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $parts = preg_split('/\s+/', trim($row));
                if (!empty($parts)) {
                    $key = array_shift($parts);
                    if ($key !== null) {
                        $this->_features[$key] = $parts;
                    }
                }
            }
        }
        return true;
    }

    public function rawlist(string $pathname = '', string $arg = ''): array|false
    {
        $command = ($arg !== '' ? ' ' . $arg : '') . ($pathname !== '' ? ' ' . $pathname : '');
        return $this->_list($command, 'LIST', 'rawlist');
    }

    public function nlist(string $pathname = '', string $arg = ''): array|false
    {
        $command = ($arg !== '' ? ' ' . $arg : '') . ($pathname !== '' ? ' ' . $pathname : '');
        return $this->_list($command, 'NLST', 'nlist');
    }

    public function is_exists(string $pathname): bool
    {
        return $this->file_exists($pathname);
    }

    public function file_exists(string $pathname): bool
    {
        $exists = true;
        if (!$this->_exec('RNFR ' . $pathname, 'rename')) {
            $exists = false;
        } else {
            if (!$this->_checkCode()) {
                $exists = false;
            }
            $this->abort();
        }
        $this->SendMSG('Remote file ' . $pathname . ($exists ? ' exists' : ' does not exist'));
        return $exists;
    }

    public function fget($fp, string $remotefile, int $rest = 0): bool|string
    {
        if ($this->_can_restore && $rest !== 0) {
            fseek($fp, $rest);
        }
        $extension = pathinfo($remotefile, PATHINFO_EXTENSION);
        $mode = ($this->_type === FTP_ASCII || ($this->_type === FTP_AUTOASCII && $extension !== '' && in_array(strtoupper($extension), $this->AutoAsciiExt, true)))
            ? FTP_ASCII
            : FTP_BINARY;
        if (!$this->_data_prepare($mode)) {
            return false;
        }
        if ($this->_can_restore && $rest !== 0 && !$this->restore($rest)) {
            $this->_data_close();
            return false;
        }
        if (!$this->_exec('RETR ' . $remotefile, 'get')) {
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

    public function get(string $remotefile, ?string $localfile = null, int $rest = 0): bool|string
    {
        $localfile ??= $remotefile;
        if (@file_exists($localfile)) {
            $this->SendMSG('Warning : local file will be overwritten');
        }
        $fp = @fopen($localfile, 'w');
        if (!$fp) {
            $this->PushError('get', "can't open local file", 'Cannot create "' . $localfile . '"');
            return false;
        }
        if ($this->_can_restore && $rest !== 0) {
            fseek($fp, $rest);
        }
        $extension = pathinfo($remotefile, PATHINFO_EXTENSION);
        $mode = ($this->_type === FTP_ASCII || ($this->_type === FTP_AUTOASCII && $extension !== '' && in_array(strtoupper($extension), $this->AutoAsciiExt, true)))
            ? FTP_ASCII
            : FTP_BINARY;
        if (!$this->_data_prepare($mode)) {
            fclose($fp);
            return false;
        }
        if ($this->_can_restore && $rest !== 0 && !$this->restore($rest)) {
            $this->_data_close();
            fclose($fp);
            return false;
        }
        if (!$this->_exec('RETR ' . $remotefile, 'get')) {
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

    public function fput(string $remotefile, $fp, int $rest = 0): bool|string
    {
        if ($this->_can_restore && $rest !== 0) {
            fseek($fp, $rest);
        }
        $extension = pathinfo($remotefile, PATHINFO_EXTENSION);
        $mode = ($this->_type === FTP_ASCII || ($this->_type === FTP_AUTOASCII && $extension !== '' && in_array(strtoupper($extension), $this->AutoAsciiExt, true)))
            ? FTP_ASCII
            : FTP_BINARY;
        if (!$this->_data_prepare($mode)) {
            return false;
        }
        if ($this->_can_restore && $rest !== 0 && !$this->restore($rest)) {
            $this->_data_close();
            return false;
        }
        if (!$this->_exec('STOR ' . $remotefile, 'put')) {
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

    public function put(string $localfile, ?string $remotefile = null, int $rest = 0): bool|string
    {
        $remotefile ??= $localfile;
        if (!file_exists($localfile)) {
            $this->PushError('put', "can't open local file", 'No such file or directory "' . $localfile . '"');
            return false;
        }
        $fp = @fopen($localfile, 'r');
        if (!$fp) {
            $this->PushError('put', "can't open local file", 'Cannot read file "' . $localfile . '"');
            return false;
        }
        if ($this->_can_restore && $rest !== 0) {
            fseek($fp, $rest);
        }
        $extension = pathinfo($localfile, PATHINFO_EXTENSION);
        $mode = ($this->_type === FTP_ASCII || ($this->_type === FTP_AUTOASCII && $extension !== '' && in_array(strtoupper($extension), $this->AutoAsciiExt, true)))
            ? FTP_ASCII
            : FTP_BINARY;
        if (!$this->_data_prepare($mode)) {
            fclose($fp);
            return false;
        }
        if ($this->_can_restore && $rest !== 0 && !$this->restore($rest)) {
            $this->_data_close();
            fclose($fp);
            return false;
        }
        if (!$this->_exec('STOR ' . $remotefile, 'put')) {
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

    public function mput(string $local = '.', ?string $remote = null, bool $continious = false): bool
    {
        $resolvedLocal = realpath($local);
        if ($resolvedLocal === false) {
            $this->PushError('mput', "can't open local folder", 'Cannot stat folder "' . $local . '"');
            return false;
        }
        if (!is_dir($resolvedLocal)) {
            return (bool) $this->put($resolvedLocal, $remote);
        }
        $remote ??= '.';
        if (!$this->file_exists($remote) && !$this->mkdir($remote)) {
            return false;
        }
        $handle = opendir($resolvedLocal);
        if ($handle === false) {
            $this->PushError('mput', "can't open local folder", 'Cannot read folder "' . $resolvedLocal . '"');
            return false;
        }
        $list = [];
        while (($file = readdir($handle)) !== false) {
            if ($file !== '.' && $file !== '..') {
                $list[] = $file;
            }
        }
        closedir($handle);
        if (empty($list)) {
            return true;
        }
        $result = true;
        foreach ($list as $item) {
            $sourcePath = $resolvedLocal . DIRECTORY_SEPARATOR . $item;
            $targetPath = rtrim($remote, '/\\') . '/' . $item;
            $success = is_dir($sourcePath)
                ? $this->mput($sourcePath, $targetPath, $continious)
                : (bool) $this->put($sourcePath, $targetPath);
            if (!$success) {
                $result = false;
                if (!$continious) {
                    break;
                }
            }
        }
        return $result;
    }

    public function mget(string $remote, string $local = '.', bool $continious = false): bool
    {
        $list = $this->rawlist($remote, '-lA');
        if ($list === false) {
            $this->PushError('mget', "can't read remote folder list", 'Can't read remote folder "' . $remote . '" contents');
            return false;
        }
        if (empty($list)) {
            return true;
        }
        if (!@file_exists($local)) {
            if (!@mkdir($local)) {
                $this->PushError('mget', "can't create local folder", 'Cannot create folder "' . $local . '"');
                return false;
            }
        }
        $entries = [];
        foreach ($list as $item) {
            $parsed = $this->parselisting($item);
            if (!empty($parsed) && $parsed['name'] !== '.' && $parsed['name'] !== '..') {
                $entries[] = $parsed;
            }
        }
        $result = true;
        foreach ($entries as $entry) {
            $remotePath = rtrim($remote, '/\\') . '/' . $entry['name'];
            $localPath = rtrim($local, '/\\') . DIRECTORY_SEPARATOR . $entry['name'];
            if (($entry['type'] ?? '') === 'd') {
                if (!$this->mget($remotePath, $localPath, $continious)) {
                    $this->PushError('mget', "can't copy folder", 'Can't copy remote folder "' . $remotePath . '" to local "' . $localPath . '"');
                    $result = false;
                    if (!$continious) {
                        break;
                    }
                }
            } else {
                if (!$this->get($remotePath, $localPath)) {
                    $this->PushError('mget', "can't copy file", 'Can't copy remote file "' . $remotePath . '" to local "' . $localPath . '"');
                    $result = false;
                    if (!$continious) {
                        break;
                    }
                }
            }
            if (isset($entry['time'])) {
                @touch($localPath, (int) $entry['time']);
            }
        }
        return $result;
    }

    public function mdel(string $remote, bool $continious = false): bool
    {
        $list = $this->rawlist($remote, '-la');
        if ($list === false) {
            $this->PushError('mdel', "can't read remote folder list", 'Can't read remote folder "' . $remote . '" contents');
            return false;
        }

        $entries = [];
        foreach ($list as $item) {
            $parsed = $this->parselisting($item);
            if (!empty($parsed) && $parsed['name'] !== '.' && $parsed['name'] !== '..') {
                $entries[] = $parsed;
            }
        }
        $result = true;

        foreach ($entries as $entry) {
            $remotePath = rtrim($remote, '/\\') . '/' . $entry['name'];
            if (($entry['type'] ?? '') === 'd') {
                if (!$this->mdel($remotePath, $continious)) {
                    $result = false;
                    if (!$continious) {
                        break;
                    }
                }
            } else {
                if (!$this->delete($remotePath)) {
                    $this->PushError('mdel', "can't delete file", 'Can't delete remote file "' . $remotePath . '"');
                    $result = false;
                    if (!$continious) {
                        break;
                    }
                }
            }
        }

        if (!$this->rmdir($remote)) {
            $this->PushError('mdel', "can't delete folder", 'Can't delete remote folder "' . $remote . '"');
            $result = false;
        }
        return $result;
    }

    public function mmkdir(string $dir, int $mode = 0o777): bool
    {
        if ($dir === '' || $dir === '/') {
            return true;
        }
        if ($this->is_exists($dir)) {
            return true;
        }
        if (!$this->mmkdir(dirname($dir), $mode)) {
            return false;
        }
        $result = $this->mkdir($dir);
        if ($result) {
            $this->chmod($dir, $mode);
        }
        return $result;
    }

    public function glob(string $pattern, ?array $handle = null): array|false
    {
        $slash = DIRECTORY_SEPARATOR;
        $lastpos = strrpos($pattern, $slash);
        if ($lastpos !== false) {
            $path = substr($pattern, 0, $lastpos);
            $pattern = substr($pattern, $lastpos + 1);
        } else {
            $path = getcwd();
        }

        if ($path === false) {
            return false;
        }

        $output = [];
        if (is_array($handle) && $handle !== []) {
            foreach ($handle as $dir) {
                if ($this->glob_pattern_match($pattern, (string) $dir)) {
                    $output[] = $dir;
                }
            }
        } else {
            $dirHandle = @opendir($path);
            if ($dirHandle === false) {
                return false;
            }
            while (($dir = readdir($dirHandle)) !== false) {
                if ($this->glob_pattern_match($pattern, $dir)) {
                    $output[] = $dir;
                }
            }
            closedir($dirHandle);
        }

        return $output !== [] ? $output : false;
    }

    public function glob_pattern_match(string $pattern, string $string): bool
    {
        $chunks = explode(';', $pattern);
        $regexes = [];
        foreach ($chunks as $chunk) {
            while (str_contains($chunk, '**')) {
                $chunk = str_replace('**', '*', $chunk);
            }
            $escaped = preg_quote($chunk, '/');
            $escaped = str_replace(['\?*', '*\?'], ['\*', '\*'], $escaped);
            $escaped = str_replace(['\*', '\?'], ['.*', '.{1}'], $escaped);
            $regexes[] = $escaped;
        }

        if (count($regexes) === 1) {
            return $this->glob_regexp('^' . $regexes[0] . '$', $string);
        }

        foreach ($regexes as $regex) {
            if ($this->glob_regexp('^' . $regex . '$', $string)) {
                return true;
            }
        }

        return false;
    }

    public function glob_regexp(string $pattern, string $probe): bool
    {
        $caseSensitive = strncasecmp(PHP_OS, 'WIN', 3) !== 0;
        $modifiers = $caseSensitive ? '' : 'i';
        return (bool) preg_match('/' . $pattern . '/' . $modifiers, $probe);
    }

    public function dirlist(string $remote): array|false
    {
        $list = $this->rawlist($remote, '-la');
        if ($list === false) {
            $this->PushError('dirlist', "can't read remote folder list", 'Can't read remote folder "' . $remote . '" contents');
            return false;
        }

        $dirlist = [];
        foreach ($list as $item) {
            $entry = $this->parselisting($item);
            if (!empty($entry) && $entry['name'] !== '.' && $entry['name'] !== '..') {
                $dirlist[$entry['name']] = $entry;
            }
        }

        return $dirlist;
    }

    protected function _checkCode(): bool
    {
        return $this->_code < 400 && $this->_code > 0;
    }

    protected function _list(string $arg = '', string $cmd = 'LIST', string $fnction = '_list'): array|false
    {
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
        $out = '';
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
            $out = preg_split('/' . CRLF . '+/', trim((string) $out), -1, PREG_SPLIT_NO_EMPTY);
        }
        return $out;
    }

    public function PushError(string $fctname, string $msg, string|false $desc = false): int
    {
        $error = [
            'time' => time(),
            'fctname' => $fctname,
            'msg' => $msg,
            'desc' => $desc,
        ];
        $suffix = $desc ? ' (' . $desc . ')' : '';
        $this->SendMSG($fctname . ': ' . $msg . $suffix);
        $this->_error_array[] = $error;
        return count($this->_error_array);
    }

    public function PopError(): array|false
    {
        if (count($this->_error_array)) {
            return array_pop($this->_error_array);
        }
        return false;
    }
}

$mod_sockets = extension_loaded('sockets');
if (
    !$mod_sockets
    && function_exists('dl')
    && is_callable('dl')
) {
    $prefix = (PHP_SHLIB_SUFFIX === 'dll') ? 'php_' : '';
    @dl($prefix . 'sockets.' . PHP_SHLIB_SUFFIX);
    $mod_sockets = extension_loaded('sockets');
}

require_once __DIR__ . '/class-ftp-' . ($mod_sockets ? 'sockets' : 'pure') . '.php';
?>