<?php

class POP3
{
    public string $ERROR = '';
    public int $TIMEOUT = 60;
    public int $COUNT = -1;
    public int $BUFFER = 512;
    public $FP = null;
    public string $MAILSERVER = '';
    public bool $DEBUG = false;
    public string $BANNER = '';
    public bool $ALLOWAPOP = false;

    private const DEFAULT_PORT = 110;

    public function __construct(string $server = '', int $timeout = 0)
    {
        if ($server !== '' && $this->MAILSERVER === '') {
            $this->MAILSERVER = $server;
        }

        if ($timeout > 0) {
            $this->TIMEOUT = $timeout;
            $this->setTimeLimit($timeout);
        }
    }

    public function update_timer(): bool
    {
        $this->setTimeLimit($this->TIMEOUT);

        return true;
    }

    public function connect(string $server = '', int $port = self::DEFAULT_PORT): bool
    {
        if ($this->MAILSERVER !== '') {
            $server = $this->MAILSERVER;
        }

        if ($server === '') {
            $this->ERROR = "POP3 connect: " . _("No server specified");
            $this->FP = null;

            return false;
        }

        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($server, $port, $errno, $errstr, $this->TIMEOUT);

        if ($fp === false) {
            $this->ERROR = "POP3 connect: " . _("Error ") . "[$errno] [$errstr]";
            $this->FP = null;

            return false;
        }

        stream_set_blocking($fp, true);
        $this->update_timer();

        $reply = fgets($fp, $this->BUFFER);
        if ($reply === false) {
            $this->ERROR = "POP3 connect: " . _("No response from server");
            fclose($fp);
            $this->FP = null;

            return false;
        }

        $reply = $this->strip_clf($reply);

        if ($this->DEBUG) {
            error_log(sprintf('POP3 SEND [connect: %s] GOT [%s]', $server, $reply));
        }

        if (!$this->is_ok($reply)) {
            $this->ERROR = "POP3 connect: " . _("Error ") . "[$reply]";
            fclose($fp);
            $this->FP = null;

            return false;
        }

        $this->FP = $fp;
        $this->BANNER = $this->parse_banner($reply);

        return true;
    }

    public function user(string $user = ''): bool
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

        if (!$this->is_ok($reply)) {
            $this->ERROR = "POP3 user: " . _("Error ") . "[$reply]";

            return false;
        }

        return true;
    }

    public function pass(string $pass = ''): int|false
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

        if (!$this->is_ok($reply)) {
            $this->ERROR = "POP3 pass: " . _("Authentication failed") . " [$reply]";
            $this->quit();

            return false;
        }

        $count = $this->last('count');
        $this->COUNT = is_int($count) ? $count : -1;

        return $this->COUNT;
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

        $banner = $this->BANNER;

        if (!$banner || $banner === '') {
            $this->ERROR = "POP3 apop: " . _("No server banner") . ' - ' . _("abort");

            return $this->login($login, $pass);
        }

        $authString = $banner . $pass;
        $apopString = md5($authString);
        $cmd = "APOP $login $apopString";
        $reply = $this->send_cmd($cmd);

        if (!$this->is_ok($reply)) {
            $this->ERROR = "POP3 apop: " . _("apop authentication failed") . ' - ' . _("abort");

            return $this->login($login, $pass);
        }

        $count = $this->last('count');
        $this->COUNT = is_int($count) ? $count : -1;

        return $this->COUNT;
    }

    public function login(string $login = '', string $pass = ''): int|false
    {
        if ($this->FP === null) {
            $this->ERROR = "POP3 login: " . _("No connection to server");

            return false;
        }

        if (!$this->user($login)) {
            return false;
        }

        $count = $this->pass($pass);

        if ($count === false || $count === -1) {
            return false;
        }

        return $count;
    }

    public function top(int $msgNum, int $numLines = 0): array|false
    {
        if ($this->FP === null) {
            $this->ERROR = "POP3 top: " . _("No connection to server");

            return false;
        }

        $this->update_timer();

        $fp = $this->FP;
        $buffer = $this->BUFFER;
        $cmd = "TOP $msgNum $numLines";

        fwrite($fp, $cmd . "\r\n");

        $reply = fgets($fp, $buffer);
        if ($reply === false) {
            $this->ERROR = "POP3 top: " . _("No response from server");

            return false;
        }

        $reply = $this->strip_clf($reply);

        if ($this->DEBUG) {
            error_log(sprintf('POP3 SEND [%s] GOT [%s]', $cmd, $reply));
        }

        if (!$this->is_ok($reply)) {
            $this->ERROR = "POP3 top: " . _("Error ") . "[$reply]";

            return false;
        }

        $msgArray = [];

        while (($line = fgets($fp, $buffer)) !== false) {
            if (str_starts_with($line, ".\r\n")) {
                break;
            }

            $msgArray[] = $line;
        }

        return $msgArray;
    }

    public function pop_list(?int $msgNum = null)
    {
        if ($this->FP === null) {
            $this->ERROR = "POP3 pop_list: " . _("No connection to server");

            return false;
        }

        $total = $this->COUNT;

        if ((!$total) || $total === -1) {
            return false;
        }

        if ($total === 0) {
            return ['0', '0'];
        }

        $this->update_timer();

        if ($msgNum !== null && $msgNum > 0) {
            $cmd = "LIST $msgNum";
            fwrite($this->FP, $cmd . "\r\n");

            $reply = fgets($this->FP, $this->BUFFER);
            if ($reply === false) {
                $this->ERROR = "POP3 pop_list: " . _("Error ") . "[no response]";

                return false;
            }

            $reply = $this->strip_clf($reply);

            if ($this->DEBUG) {
                error_log(sprintf('POP3 SEND [%s] GOT [%s]', $cmd, $reply));
            }

            if (!$this->is_ok($reply)) {
                $this->ERROR = "POP3 pop_list: " . _("Error ") . "[$reply]";

                return false;
            }

            $parts = preg_split('/\s+/', trim($reply));

            return $parts[2] ?? '';
        }

        $cmd = 'LIST';
        $reply = $this->send_cmd($cmd);

        if (!$this->is_ok($reply)) {
            $reply = $this->strip_clf((string) $reply);
            $this->ERROR = "POP3 pop_list: " . _("Error ") . "[$reply]";

            return false;
        }

        $msgArray = [];
        $msgArray[0] = $total;

        for ($msgC = 1; $msgC <= $total; $msgC++) {
            $line = fgets($this->FP, $this->BUFFER);

            if ($line === false) {
                $this->ERROR = "POP3 pop_list: " . _("Premature end of list");

                return false;
            }

            $line = $this->strip_clf($line);

            if ($line === '.') {
                $this->ERROR = "POP3 pop_list: " . _("Premature end of list");

                return false;
            }

            $parts = preg_split('/\s+/', trim($line));
            $thisMsg = isset($parts[0]) ? (int) $parts[0] : 0;
            $msgSize = $parts[1] ?? '0';

            if ($thisMsg !== $msgC) {
                $msgArray[$msgC] = 'deleted';
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

        $this->update_timer();

        $fp = $this->FP;
        $buffer = $this->BUFFER;
        $cmd = "RETR $msgNum";
        $reply = $this->send_cmd($cmd);

        if (!$this->is_ok($reply)) {
            $this->ERROR = "POP3 get: " . _("Error ") . "[$reply]";

            return false;
        }

        $msgArray = [];

        while (($line = fgets($fp, $buffer)) !== false) {
            if (str_starts_with($line, ".\r\n")) {
                break;
            }

            if (isset($line[0]) && $line[0] === '.') {
                $line = substr($line, 1);
            }

            $msgArray[] = $line;
        }

        return $msgArray;
    }

    public function last(string $type = 'count'): int|array
    {
        $last = -1;

        if ($this->FP === null) {
            $this->ERROR = "POP3 last: " . _("No connection to server");

            return $last;
        }

        $reply = $this->send_cmd('STAT');

        if (!$this->is_ok($reply)) {
            $this->ERROR = "POP3 last: " . _("Error ") . "[$reply]";

            return $last;
        }

        $vars = preg_split('/\s+/', trim((string) $reply));

        if (count($vars) < 3) {
            $this->ERROR = "POP3 last: " . _("Error ") . "[$reply]";

            return $last;
        }

        $count = (int) $vars[1];
        $size = (int) $vars[2];

        if ($type !== 'count') {
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

        $reply = $this->send_cmd('RSET');

        if (!$this->is_ok($reply)) {
            $this->ERROR = "POP3 reset: " . _("Error ") . "[$reply]";
            error_log(sprintf('POP3 reset: ERROR [%s]', $reply));
        }

        $this->quit();

        return true;
    }

    public function send_cmd(string $cmd = ''): string|false
    {
        if ($this->FP === null) {
            $this->ERROR = "POP3 send_cmd: " . _("No connection to server");

            return false;
        }

        if ($cmd === '') {
            $this->ERROR = "POP3 send_cmd: " . _("Empty command string");

            return '';
        }

        $fp = $this->FP;
        $buffer = $this->BUFFER;

        $this->update_timer();
        fwrite($fp, $cmd . "\r\n");

        $reply = fgets($fp, $buffer);

        if ($reply === false) {
            return '';
        }

        $reply = $this->strip_clf($reply);

        if ($this->DEBUG) {
            error_log(sprintf('POP3 SEND [%s] GOT [%s]', $cmd, $reply));
        }

        return $reply;
    }

    public function quit(): bool
    {
        if ($this->FP === null) {
            $this->ERROR = "POP3 quit: " . _("connection does not exist");

            return false;
        }

        $fp = $this->FP;
        $cmd = 'QUIT';

        fwrite($fp, $cmd . "\r\n");

        $reply = fgets($fp, $this->BUFFER);
        $reply = $reply === false ? '' : $this->strip_clf($reply);

        if ($this->DEBUG) {
            error_log(sprintf('POP3 SEND [%s] GOT [%s]', $cmd, $reply));
        }

        if (is_resource($fp)) {
            fclose($fp);
        }

        $this->FP = null;

        return true;
    }

    public function popstat(): array|false
    {
        $popArray = $this->last('array');

        if ($popArray === -1) {
            return false;
        }

        if (!$popArray || empty($popArray)) {
            return false;
        }

        return $popArray;
    }

    public function uidl(?int $msgNum = null): array|string|false
    {
        if ($this->FP === null) {
            $this->ERROR = "POP3 uidl: " . _("No connection to server");

            return false;
        }

        if ($msgNum !== null && $msgNum > 0) {
            $cmd = "UIDL $msgNum";
            $reply = $this->send_cmd($cmd);

            if (!$this->is_ok($reply)) {
                $this->ERROR = "POP3 uidl: " . _("Error ") . "[$reply]";

                return false;
            }

            $parts = preg_split('/\s+/', trim((string) $reply));

            return $parts[2] ?? '';
        }

        $this->update_timer();

        $uidlArray = [];
        $total = $this->COUNT;
        $uidlArray[0] = $total;

        if ($total < 1) {
            return $uidlArray;
        }

        $cmd = 'UIDL';
        fwrite($this->FP, $cmd . "\r\n");

        $reply = fgets($this->FP, $this->BUFFER);
        if ($reply === false) {
            $this->ERROR = "POP3 uidl: " . _("Error ") . "[no response]";

            return false;
        }

        $reply = $this->strip_clf($reply);

        if ($this->DEBUG) {
            error_log(sprintf('POP3 SEND [%s] GOT [%s]', $cmd, $reply));
        }

        if (!$this->is_ok($reply)) {
            $this->ERROR = "POP3 uidl: " . _("Error ") . "[$reply]";

            return false;
        }

        $count = 1;

        while (($line = fgets($this->FP, $this->BUFFER)) !== false) {
            if (str_starts_with($line, ".\r\n")) {
                break;
            }

            $parts = preg_split('/\s+/', trim($line));

            if (count($parts) < 2) {
                $count++;
                continue;
            }

            $msg = (int) $parts[0];
            $msgUidl = $this->strip_clf($parts[1]);

            if ($count === $msg) {
                $uidlArray[$msg] = $msgUidl;
            } else {
                $uidlArray[$count] = 'deleted';
            }

            $count++;
        }

        return $uidlArray;
    }

    public function delete(?int $msgNum = null): bool
    {
        if ($this->FP === null) {
            $this->ERROR = "POP3 delete: " . _("No connection to server");

            return false;
        }

        if ($msgNum === null || $msgNum <= 0) {
            $this->ERROR = "POP3 delete: " . _("No msg number submitted");

            return false;
        }

        $reply = $this->send_cmd("DELE $msgNum");

        if (!$this->is_ok($reply)) {
            $this->ERROR = "POP3 delete: " . _("Command failed ") . "[$reply]";

            return false;
        }

        return true;
    }

    private function is_ok(string $cmd = ''): bool
    {
        if ($cmd === '') {
            return false;
        }

        return stripos($cmd, '+OK') !== false;
    }

    private function strip_clf(string $text = ''): string
    {
        if ($text === '') {
            return $text;
        }

        return str_replace(["\r", "\n"], '', $text);
    }

    private function parse_banner(string $server_text): string
    {
        $outside = true;
        $banner = '';
        $length = strlen($server_text);

        for ($count = 0; $count < $length; $count++) {
            $digit = $server_text[$count];

            if ($digit === '<') {
                $outside = false;
                continue;
            }

            if ($digit === '>') {
                $outside = true;
                continue;
            }

            if (!$outside) {
                $banner .= $digit;
            }
        }

        $banner = $this->strip_clf($banner);

        return "<{$banner}>";
    }

    private function setTimeLimit(int $timeout): void
    {
        if ($timeout <= 0 || !function_exists('set_time_limit')) {
            return;
        }

        try {
            set_time_limit($timeout);
        } catch (\Throwable) {
            // Ignore inability to set time limit.
        }
    }
}
?>