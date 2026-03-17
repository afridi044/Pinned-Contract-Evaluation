<?php

declare(strict_types=1);

class ftp extends ftp_base
{
    public function __construct(bool $verb = false, bool $le = false)
    {
        parent::__construct(false, $verb, $le);
    }

    public function ftp(bool $verb = false, bool $le = false): void
    {
        $this->__construct($verb, $le);
    }

    public function _settimeout($sock): bool
    {
        if (!@stream_set_timeout($sock, $this->_timeout)) {
            $this->PushError('_settimeout', 'socket set send timeout');
            $this->_quit();

            return false;
        }

        return true;
    }

    public function _connect(string $host, int $port)
    {
        $this->SendMSG('Creating socket');
        $sock = @fsockopen($host, $port, $errno, $errstr, $this->_timeout);
        if (!$sock) {
            $this->PushError('_connect', 'socket connect failed', $errstr . ' (' . $errno . ')');

            return false;
        }

        $this->_connected = true;

        return $sock;
    }

    public function _readmsg(string $function = '_readmsg'): bool
    {
        if (!$this->_connected) {
            $this->PushError($function, 'Connect first');

            return false;
        }

        $result = true;
        $this->_message = '';
        $this->_code = 0;
        $go = true;
        $regs = [];
        $pattern = "/^([0-9]{3})(-(.*[" . CRLF . "]{1,2})+\\1)? [^" . CRLF . "]+[" . CRLF . "]{1,2}$/";

        do {
            $tmp = @fgets($this->_ftp_control_sock, 512);
            if ($tmp === false) {
                $go = $result = false;
                $this->PushError($function, 'Read failed');
            } else {
                $this->_message .= $tmp;
                if (preg_match($pattern, $this->_message, $regs)) {
                    $go = false;
                }
            }
        } while ($go);

        if (!$result) {
            return false;
        }

        if (!isset($regs[1])) {
            $this->PushError($function, 'Invalid response');

            return false;
        }

        if ($this->LocalEcho) {
            echo 'GET < ' . rtrim($this->_message, CRLF) . CRLF;
        }

        $this->_code = (int) $regs[1];

        return true;
    }

    public function _exec(string $cmd, string $function = '_exec'): bool
    {
        if (!$this->_ready) {
            $this->PushError($function, 'Connect first');

            return false;
        }

        if ($this->LocalEcho) {
            echo 'PUT > ' . $cmd . CRLF;
        }

        $status = @fputs($this->_ftp_control_sock, $cmd . CRLF);
        if ($status === false) {
            $this->PushError($function, 'socket write failed');

            return false;
        }

        $this->_lastaction = time();

        return $this->_readmsg($function);
    }

    public function _data_prepare(int $mode = FTP_ASCII): bool
    {
        if (!$this->_settype($mode)) {
            return false;
        }

        if (!$this->_passive) {
            $this->SendMSG('Only passive connections available!');

            return false;
        }

        if (!$this->_exec('PASV', 'pasv')) {
            $this->_data_close();

            return false;
        }

        if (!$this->_checkCode()) {
            $this->_data_close();

            return false;
        }

        if (!preg_match('/(\d{1,3}(?:,\d{1,3}){5})/', $this->_message, $matches)) {
            $this->PushError('_data_prepare', 'Invalid PASV response');
            $this->_data_close();

            return false;
        }

        $ipPort = explode(',', $matches[1]);
        $this->_datahost = sprintf('%s.%s.%s.%s', $ipPort[0], $ipPort[1], $ipPort[2], $ipPort[3]);
        $this->_dataport = ((int) $ipPort[4] << 8) + (int) $ipPort[5];

        $this->SendMSG("Connecting to {$this->_datahost}:{$this->_dataport}");
        $this->_ftp_data_sock = @fsockopen($this->_datahost, $this->_dataport, $errno, $errstr, $this->_timeout);
        if (!$this->_ftp_data_sock) {
            $this->PushError('_data_prepare', 'fsockopen fails', $errstr . ' (' . $errno . ')');
            $this->_data_close();

            return false;
        }

        return true;
    }

    public function _data_read(int $mode = FTP_ASCII, $fp = null)
    {
        $out = is_resource($fp) ? 0 : '';

        if (!$this->_passive) {
            $this->SendMSG('Only passive connections available!');

            return false;
        }

        while (!feof($this->_ftp_data_sock)) {
            $block = fread($this->_ftp_data_sock, $this->_ftp_buff_size);
            if ($block === false) {
                $this->PushError('_data_read', 'Failed to read from data socket');

                return false;
            }

            if ($mode !== FTP_BINARY) {
                $block = str_replace(["\r\n", "\r", "\n"], $this->_eol_code[$this->OS_local], $block);
            }

            if (is_resource($fp)) {
                $written = fwrite($fp, $block);
                if ($written === false) {
                    $this->PushError('_data_read', 'Failed to write to resource');

                    return false;
                }
                $out += $written;
            } else {
                $out .= $block;
            }
        }

        return $out;
    }

    public function _data_write(int $mode = FTP_ASCII, $fp = null): bool
    {
        if (!$this->_passive) {
            $this->SendMSG('Only passive connections available!');

            return false;
        }

        if (is_resource($fp)) {
            while (!feof($fp)) {
                $block = fread($fp, $this->_ftp_buff_size);
                if ($block === false) {
                    $this->PushError('_data_write', 'Failed to read from resource');

                    return false;
                }

                if (!$this->_data_write_block($mode, $block)) {
                    return false;
                }
            }

            return true;
        }

        return $this->_data_write_block($mode, (string) $fp);
    }

    public function _data_write_block(int $mode, string $block): bool
    {
        if ($mode !== FTP_BINARY) {
            $block = str_replace(["\r\n", "\r", "\n"], $this->_eol_code[$this->OS_remote], $block);
        }

        while ($block !== '') {
            $written = @fwrite($this->_ftp_data_sock, $block);
            if ($written === false || $written === 0) {
                $this->PushError('_data_write', "Can't write to socket");

                return false;
            }

            $block = substr($block, $written);
        }

        return true;
    }

    public function _data_close(): bool
    {
        if (is_resource($this->_ftp_data_sock)) {
            @fclose($this->_ftp_data_sock);
        }

        $this->SendMSG('Disconnected data from remote host');

        return true;
    }

    public function _quit(bool $force = false): void
    {
        if ($this->_connected || $force) {
            @fclose($this->_ftp_control_sock);
            $this->_connected = false;
            $this->SendMSG('Socket closed');
        }
    }
}

?>