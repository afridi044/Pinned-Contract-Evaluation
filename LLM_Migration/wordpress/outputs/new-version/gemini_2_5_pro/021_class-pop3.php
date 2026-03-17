<?php
/**
 * mail_fetch/setup.php
 *
 * Copyright (c) 1999-2011 CDI (cdi@thewebmasters.net) All Rights Reserved
 * Modified by Philippe Mingo 2001-2009 mingo@rotedic.com
 * An RFC 1939 compliant wrapper class for the POP3 protocol.
 *
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * POP3 class
 *
 * @copyright 1999-2011 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage mail_fetch
 */

declare(strict_types=1);

class POP3
{
    public string $ERROR = '';
    public int $TIMEOUT = 60;
    public int $COUNT = -1;
    public string $MAILSERVER = '';
    public bool $DEBUG = false;
    public bool $ALLOWAPOP = false;

    private const int BUFFER = 512;
    /** @var resource|null The socket file pointer */
    private mixed $FP = null;
    private string $BANNER = '';

    public function __construct(string $server = '', int $timeout = 0)
    {
        if ($server !== '') {
            // Do not allow programs to alter MAILSERVER
            // if it is already specified.
            if ($this->MAILSERVER === '') {
                $this->MAILSERVER = $server;
            }
        }
        if ($timeout > 0) {
            $this->TIMEOUT = $timeout;
            set_time_limit($timeout);
        }
    }

    public function connect(string $server, int $port = 110): bool
    {
        if ($port === 0) {
            $port = 110;
        }

        if ($this->MAILSERVER !== '') {
            $server = $this->MAILSERVER;
        }

        if ($server === '') {
            $this->ERROR = "POP3 connect: " . _("No server specified");
            $this->FP = null;
            return false;
        }

        $fp = @fsockopen($server, $port, $errno, $errstr, $this->TIMEOUT);

        if (!$fp) {
            $this->ERROR = "POP3 connect: " . _("Error ") . "[$errno] [$errstr]";
            $this->FP = null;
            return false;
        }

        stream_set_timeout($fp, $this->TIMEOUT);
        $reply = fgets($fp, self::BUFFER);
        if ($reply === false) {
            $this->ERROR = "POP3 connect: " . _("Failed to get initial response from server.");
            fclose($fp);
            $this->FP = null;
            return false;
        }

        $reply = $this->stripClf($reply);
        if ($this->DEBUG) {
            error_log("POP3 SEND [connect: $server] GOT [$reply]", 0);
        }

        if (!$this->isOk($reply)) {
            $this->ERROR = "POP3 connect: " . _("Error ") . "[$reply]";
            fclose($fp);
            $this->FP = null;
            return false;
        }

        $this->FP = $fp;
        $this->BANNER = $this->parseBanner($reply);
        return true;
    }

    public function user(string $user): bool
    {
        if ($user === '') {
            $this->ERROR = "POP3 user: " . _("no login ID submitted");
            return false;
        }

        if ($this->FP === null) {
            $this->ERROR = "POP3 user: " . _("connection not established");
            return false;
        }

        $reply = $this->send_cmd("USER $user");
        if ($reply === false || !$this->isOk($reply)) {
            $this->ERROR = "POP3 user: " . _("Error ") . "[$reply]";
            return false;
        }

        return true;
    }

    public function pass(string $pass): int|false
    {
        if ($pass === '') {
            $this->ERROR = "POP3 pass: " . _("No password submitted");
            return false;
        }

        if ($this->FP === null) {
            $this->ERROR = "POP3 pass: " . _("connection not established");
            return false;
        }

        $reply = $this->send_cmd("PASS $pass");
        if ($reply === false || !$this->isOk($reply)) {
            $this->ERROR = "POP3 pass: " . _("Authentication failed") . " [$reply]";
            $this->quit();
            return false;
        }

        $count = $this->last("count");
        if (is_int($count) && $count !== -1) {
            $this->COUNT = $count;
            return $count;
        }
        return false;
    }

    public function apop(string $login, string $pass): int|false
    {
        if ($this->FP === null) {
            $this->ERROR = "POP3 apop: " . _("No connection to server");
            return false;
        }

        if (!$this->ALLOWAPOP) {
            return $this->login($login, $pass);
        }

        if ($login === '') {
            $this->ERROR = "POP3 apop: " . _("No login ID submitted");
            return false;
        }

        if ($pass === '') {
            $this->ERROR = "POP3 apop: " . _("No password submitted");
            return false;
        }

        if ($this->BANNER === '') {
            $this->ERROR = "POP3 apop: " . _("No server banner") . ' - ' . _("abort");
            return $this->login($login, $pass);
        }

        $authString = $this->BANNER . $pass;
        $apopString = md5($authString);
        $cmd = "APOP $login $apopString";
        $reply = $this->send_cmd($cmd);

        if ($reply === false || !$this->isOk($reply)) {
            $this->ERROR = "POP3 apop: " . _("apop authentication failed") . ' - ' . _("abort");
            return $this->login($login, $pass);
        }

        $count = $this->last("count");
        if (is_int($count) && $count !== -1) {
            $this->COUNT = $count;
            return $count;
        }
        return false;
    }

    public function login(string $login, string $pass): int|false
    {
        if ($this->FP === null) {
            $this->ERROR = "POP3 login: " . _("No connection to server");
            return false;
        }

        if (!$this->user($login)) {
            return false;
        }

        return $this->pass($pass);
    }

    public function top(int $msgNum, int $numLines = 0): array|false
    {
        if ($this->FP === null) {
            $this->ERROR = "POP3 top: " . _("No connection to server");
            return false;
        }

        set_time_limit($this->TIMEOUT);

        $cmd = "TOP $msgNum $numLines";
        fwrite($this->FP, "$cmd\r\n");
        $reply = fgets($this->FP, self::BUFFER);

        if ($reply === false) {
            $this->ERROR = "POP3 top: " . _("Error receiving response");
            return false;
        }

        $reply = $this->stripClf($reply);
        if ($this->DEBUG) {
            error_log("POP3 SEND [$cmd] GOT [$reply]", 0);
        }

        if (!$this->isOk($reply)) {
            $this->ERROR = "POP3 top: " . _("Error ") . "[$reply]";
            return false;
        }

        $msgArray = [];
        while (($line = fgets($this->FP, self::BUFFER)) !== false) {
            if (preg_match('/^\.\r\n/', $line)) {
                break;
            }
            $msgArray[] = $line;
        }

        return $msgArray;
    }

    public function pop_list(int|string $msgNum = ''): array|string|false
    {
        if ($this->FP === null) {
            $this->ERROR = "POP3 pop_list: " . _("No connection to server");
            return false;
        }

        $total = $this->COUNT;
        if ($total === -1) {
            return false;
        }
        if ($total === 0) {
            return ["0", "0"];
        }

        set_time_limit($this->TIMEOUT);

        if ($msgNum !== '') {
            $cmd = "LIST $msgNum";
            fwrite($this->FP, "$cmd\r\n");
            $reply = fgets($this->FP, self::BUFFER);
            if ($reply === false) {
                $this->ERROR = "POP3 pop_list: " . _("Error receiving response");
                return false;
            }
            $reply = $this->stripClf($reply);
            if ($this->DEBUG) {
                error_log("POP3 SEND [$cmd] GOT [$reply]", 0);
            }
            if (!$this->isOk($reply)) {
                $this->ERROR = "POP3 pop_list: " . _("Error ") . "[$reply]";
                return false;
            }
            [, , $size] = preg_split('/\s+/', $reply);
            return $size;
        }

        $reply = $this->send_cmd("LIST");
        if ($reply === false || !$this->isOk($reply)) {
            $reply = $this->stripClf((string)$reply);
            $this->ERROR = "POP3 pop_list: " . _("Error ") . "[$reply]";
            return false;
        }

        $msgArray = [];
        $msgArray[0] = $total;
        for ($msgC = 1; $msgC <= $total; $msgC++) {
            $line = fgets($this->FP, self::BUFFER);
            if ($line === false || str_starts_with($line, '.')) {
                $this->ERROR = "POP3 pop_list: " . _("Premature end of list");
                return false;
            }
            $line = $this->stripClf($line);
            [$thisMsg, $msgSize] = preg_split('/\s+/', $line);
            if ((int)$thisMsg !== $msgC) {
                $msgArray[$msgC] = "deleted";
            } else {
                $msgArray[$msgC] = $msgSize;
            }
        }
        return $msgArray;
    }

    public function get(int $msgNum): array|false
    {
        if ($this->FP === null) {
            $this->ERROR = "POP3 get: " . _("No connection to server");
            return false;
        }

        set_time_limit($this->TIMEOUT);
        $reply = $this->send_cmd("RETR $msgNum");

        if ($reply === false || !$this->isOk($reply)) {
            $this->ERROR = "POP3 get: " . _("Error ") . "[$reply]";
            return false;
        }

        $msgArray = [];
        while (($line = fgets($this->FP, self::BUFFER)) !== false) {
            if (preg_match('/^\.\r\n/', $line)) {
                break;
            }
            if (str_starts_with($line, '..')) {
                $line = substr($line, 1);
            }
            $msgArray[] = $line;
        }
        return $msgArray;
    }

    public function last(string $type = "count"): int|array
    {
        if ($this->FP === null) {
            $this->ERROR = "POP3 last: " . _("No connection to server");
            return -1;
        }

        $reply = $this->send_cmd("STAT");
        if ($reply === false || !$this->isOk($reply)) {
            $this->ERROR = "POP3 last: " . _("Error ") . "[$reply]";
            return -1;
        }

        [, $count, $size] = preg_split('/\s+/', $reply);
        $count = (int)$count;
        $size = (int)$size;

        if ($type !== "count") {
            return [$count, $size];
        }
        return $count;
    }

    public function reset(): bool
    {
        if ($this->FP === null) {
            $this->ERROR = "POP3 reset: " . _("No connection to server");
            return false;
        }
        $reply = $this->send_cmd("RSET");
        if ($reply === false || !$this->isOk($reply)) {
            $this->ERROR = "POP3 reset: " . _("Error ") . "[$reply]";
            error_log("POP3 reset: ERROR [$reply]", 0);
        }
        $this->quit();
        return true;
    }

    public function send_cmd(string $cmd): string|false
    {
        if ($this->FP === null) {
            $this->ERROR = "POP3 send_cmd: " . _("No connection to server");
            return false;
        }

        if ($cmd === '') {
            $this->ERROR = "POP3 send_cmd: " . _("Empty command string");
            return "";
        }

        set_time_limit($this->TIMEOUT);
        fwrite($this->FP, "$cmd\r\n");
        $reply = fgets($this->FP, self::BUFFER);

        if ($reply === false) {
            return false;
        }

        $reply = $this->stripClf($reply);
        if ($this->DEBUG) {
            error_log("POP3 SEND [$cmd] GOT [$reply]", 0);
        }
        return $reply;
    }

    public function quit(): bool
    {
        if ($this->FP === null) {
            $this->ERROR = "POP3 quit: " . _("connection does not exist");
            return false;
        }

        $cmd = "QUIT";
        fwrite($this->FP, "$cmd\r\n");
        $reply = fgets($this->FP, self::BUFFER);
        if ($reply !== false) {
            $reply = $this->stripClf($reply);
            if ($this->DEBUG) {
                error_log("POP3 SEND [$cmd] GOT [$reply]", 0);
            }
        }
        fclose($this->FP);
        $this->FP = null;
        return true;
    }

    public function popstat(): array|false
    {
        $popArray = $this->last("array");

        if ($popArray === -1 || !is_array($popArray) || empty($popArray)) {
            return false;
        }
        return $popArray;
    }

    public function uidl(int|string $msgNum = ''): array|string|false
    {
        if ($this->FP === null) {
            $this->ERROR = "POP3 uidl: " . _("No connection to server");
            return false;
        }

        if ($msgNum !== '') {
            $reply = $this->send_cmd("UIDL $msgNum");
            if ($reply === false || !$this->isOk($reply)) {
                $this->ERROR = "POP3 uidl: " . _("Error ") . "[$reply]";
                return false;
            }
            [, , $myUidl] = preg_split('/\s+/', $reply);
            return $myUidl;
        }

        set_time_limit($this->TIMEOUT);
        $uidlArray = [];
        $total = $this->COUNT;
        $uidlArray[0] = $total;

        if ($total < 1) {
            return $uidlArray;
        }

        $cmd = "UIDL";
        fwrite($this->FP, "UIDL\r\n");
        $reply = fgets($this->FP, self::BUFFER);
        if ($reply === false) {
            $this->ERROR = "POP3 uidl: " . _("Error receiving response");
            return false;
        }
        $reply = $this->stripClf($reply);
        if ($this->DEBUG) {
            error_log("POP3 SEND [$cmd] GOT [$reply]", 0);
        }
        if (!$this->isOk($reply)) {
            $this->ERROR = "POP3 uidl: " . _("Error ") . "[$reply]";
            return false;
        }

        $count = 1;
        while (($line = fgets($this->FP, self::BUFFER)) !== false) {
            if (preg_match('/^\.\r\n/', $line)) {
                break;
            }
            [$msg, $msgUidl] = preg_split('/\s+/', $line);
            $msgUidl = $this->stripClf($msgUidl);
            if ($count == $msg) {
                $uidlArray[(int)$msg] = $msgUidl;
            } else {
                $uidlArray[$count] = 'deleted';
            }
            $count++;
        }
        return $uidlArray;
    }

    public function delete(int|string $msgNum): bool
    {
        if ($this->FP === null) {
            $this->ERROR = "POP3 delete: " . _("No connection to server");
            return false;
        }
        if ($msgNum === '') {
            $this->ERROR = "POP3 delete: " . _("No msg number submitted");
            return false;
        }
        $reply = $this->send_cmd("DELE $msgNum");
        if ($reply === false || !$this->isOk($reply)) {
            $this->ERROR = "POP3 delete: " . _("Command failed ") . "[$reply]";
            return false;
        }
        return true;
    }

    // *********************************************************
    // The following methods are internal to the class.
    // *********************************************************

    private function isOk(string $cmd): bool
    {
        return str_starts_with($cmd, '+OK');
    }

    private function stripClf(string $text): string
    {
        return str_replace(["\r", "\n"], '', $text);
    }

    private function parseBanner(string $server_text): string
    {
        if (preg_match('/<([^>]*)>/', $server_text, $matches)) {
            return '<' . $matches[1] . '>';
        }
        return '';
    }
}