<?php
/**
 * PemFTP - A Ftp implementation in pure PHP
 *
 * @package PemFTP
 * @since 2.5.0
 *
 * @version 1.0
 * @copyright Alexey Dotsenko
 * @author Alexey Dotsenko
 * @link http://www.phpclasses.org/browse/package/1743.html Site
 * @license LGPL http://www.opensource.org/licenses/lgpl-license.html
 */

/**
 * FTP implementation using fsockopen to connect.
 *
 * @package PemFTP
 * @subpackage Pure
 * @since 2.5.0
 *
 * @version 1.0
 * @copyright Alexey Dotsenko
 * @author Alexey Dotsenko
 * @link http://www.phpclasses.org/browse/package/1743.html Site
 * @license LGPL http://www.opensource.org/licenses/lgpl-license.html
 */
class PemFtp extends ftp_base { // Renamed class from 'ftp' to 'PemFtp' to avoid reserved keyword conflict in PHP 7.0+

    // Removed legacy constructor 'ftp()' as it's deprecated in PHP 7.0 and removed in PHP 8.0+

    public function __construct(bool $verb = false, bool $le = false) { // Added scalar type hints
        parent::__construct(false, $verb, $le);
    }

// <!-- --------------------------------------------------------------------------------------- -->
// <!--       Private functions                                                                 -->
// <!-- --------------------------------------------------------------------------------------- -->

    /**
     * Sets the timeout for a socket resource.
     *
     * @param resource $sock The socket resource.
     * @return bool True on success, false on failure.
     */
    protected function _settimeout($sock): bool { // Added return type hint, $sock is a resource
        if (!@stream_set_timeout($sock, $this->_timeout)) {
            $this->PushError('_settimeout', 'socket set send timeout');
            $this->_quit();
            return false;
        }
        return true;
    }

    /**
     * Connects to the FTP host.
     *
     * @param string $host The FTP host.
     * @param int $port The FTP port.
     * @return resource|false The socket resource on success, false on failure.
     */
    protected function _connect(string $host, int $port) { // Added scalar type hints and return type hint
        $this->SendMSG("Creating socket");
        $sock = @fsockopen($host, $port, $errno, $errstr, $this->_timeout);
        if (!$sock) {
            $this->PushError('_connect', 'socket connect failed', $errstr . " (" . $errno . ")");
            return false;
        }
        $this->_connected = true;
        return $sock;
    }

    /**
     * Reads a message from the control socket until a complete response is received.
     *
     * @param string $fnction The calling function name for error reporting.
     * @return bool True on success, false on failure.
     */
    protected function _readmsg(string $fnction = "_readmsg"): bool { // Added scalar type hints and return type hint
        if (!$this->_connected) {
            $this->PushError($fnction, 'Connect first');
            return false;
        }
        $result = true;
        $this->_message = "";
        $this->_code = 0;
        $go = true;
        do {
            $tmp = @fgets($this->_ftp_control_sock, 512);
            if ($tmp === false) {
                $go = $result = false;
                $this->PushError($fnction, 'Read failed');
            } else {
                $this->_message .= $tmp;
                // Original regex: preg_match("/^([0-9]{3})(-(.*[".CRLF."]{1,2})+\\1)? [^".CRLF."]+[".CRLF."]{1,2}$/", $this->_message, $regs)
                // Assuming CRLF is "\r\n" and the intent was to match actual line endings.
                // The original [".CRLF."] would match literal '.', 'C', 'R', 'L', 'F' if CRLF is a string.
                // This is a common bug. Replaced with [\r\n] to match actual carriage return or newline characters.
                if (preg_match("/^([0-9]{3})(-(.*[\r\n]{1,2})+\\1)? [^\r\n]+[\r\n]{1,2}$/", $this->_message, $regs)) {
                    $go = false;
                }
            }
        } while ($go);
        if ($this->LocalEcho) {
            echo "GET < " . rtrim($this->_message, CRLF) . CRLF;
        }
        $this->_code = (int)$regs[1];
        return $result;
    }

    /**
     * Executes an FTP command by writing to the control socket and reading the response.
     *
     * @param string $cmd The command to execute.
     * @param string $fnction The calling function name for error reporting.
     * @return bool True on success, false on failure.
     */
    protected function _exec(string $cmd, string $fnction = "_exec"): bool { // Added scalar type hints and return type hint
        if (!$this->_ready) {
            $this->PushError($fnction, 'Connect first');
            return false;
        }
        if ($this->LocalEcho) {
            echo "PUT > ", $cmd, CRLF;
        }
        $status = @fputs($this->_ftp_control_sock, $cmd . CRLF);
        if ($status === false) {
            $this->PushError($fnction, 'socket write failed');
            return false;
        }
        $this->_lastaction = time();
        if (!$this->_readmsg($fnction)) {
            return false;
        }
        return true;
    }

    /**
     * Prepares the data connection for transfer, typically using PASV mode.
     *
     * @param int $mode The transfer mode (e.g., FTP_ASCII or FTP_BINARY).
     * @return bool True on success, false on failure.
     */
    protected function _data_prepare(int $mode = FTP_ASCII): bool { // Added scalar type hints and return type hint
        if (!$this->_settype($mode)) {
            return false;
        }
        if ($this->_passive) {
            if (!$this->_exec("PASV", "pasv")) {
                $this->_data_close();
                return false;
            }
            if (!$this->_checkCode()) {
                $this->_data_close();
                return false;
            }
            // Replaced ereg_replace with preg_replace.
            // preg_quote(CRLF) is used to ensure CRLF is treated as a literal string in the regex.
            $ip_port_string = preg_replace(
                "/^.+ \\(?([0-9]{1,3},[0-9]{1,3},[0-9]{1,3},[0-9]{1,3},[0-9]+,[0-9]+)\\)?.*" . preg_quote(CRLF) . "$/",
                "\\1",
                $this->_message
            );
            $ip_port = explode(",", $ip_port_string);
            $this->_datahost = $ip_port[0] . "." . $ip_port[1] . "." . $ip_port[2] . "." . $ip_port[3];
            $this->_dataport = (((int)$ip_port[4]) << 8) + ((int)$ip_port[5]);
            $this->SendMSG("Connecting to " . $this->_datahost . ":" . $this->_dataport);
            $this->_ftp_data_sock = @fsockopen($this->_datahost, $this->_dataport, $errno, $errstr, $this->_timeout);
            if (!$this->_ftp_data_sock) {
                $this->PushError("_data_prepare", "fsockopen fails", $errstr . " (" . $errno . ")");
                $this->_data_close();
                return false;
            }
            // Original code had an 'else $this->_ftp_data_sock;' which was a no-op and has been removed.
        } else {
            $this->SendMSG("Only passive connections available!");
            return false;
        }
        return true;
    }

    /**
     * Reads data from the data connection.
     *
     * @param int $mode The transfer mode (e.g., FTP_ASCII or FTP_BINARY).
     * @param resource|null $fp An optional file pointer to write data to.
     * @return int|string|false The number of bytes written (if $fp is resource), the data as a string, or false on failure.
     */
    protected function _data_read(int $mode = FTP_ASCII, $fp = null) { // Added scalar type hints and return type hint
        if (is_resource($fp)) {
            $out = 0;
        } else {
            $out = "";
        }
        if (!$this->_passive) {
            $this->SendMSG("Only passive connections available!");
            return false;
        }
        while (!feof($this->_ftp_data_sock)) {
            $block = fread($this->_ftp_data_sock, $this->_ftp_buff_size);
            if ($mode != FTP_BINARY) {
                $block = preg_replace("/\r\n|\r|\n/", $this->_eol_code[$this->OS_local], $block);
            }
            if (is_resource($fp)) {
                $out += fwrite($fp, $block, strlen($block));
            } else {
                $out .= $block;
            }
        }
        return $out;
    }

    /**
     * Writes data to the data connection.
     *
     * @param int $mode The transfer mode (e.g., FTP_ASCII or FTP_BINARY).
     * @param resource|string|null $fp A file pointer to read data from, or the data as a string to write.
     * @return bool True on success, false on failure.
     */
    protected function _data_write(int $mode = FTP_ASCII, $fp = null): bool { // Added scalar type hints and return type hint
        if (!$this->_passive) {
            $this->SendMSG("Only passive connections available!");
            return false;
        }
        if (is_resource($fp)) {
            while (!feof($fp)) {
                $block = fread($fp, $this->_ftp_buff_size);
                if (!$this->_data_write_block($mode, $block)) {
                    return false;
                }
            }
        } elseif (!$this->_data_write_block($mode, $fp)) { // If $fp is not a resource, it's treated as the block itself
            return false;
        }
        return true;
    }

    /**
     * Writes a block of data to the data connection.
     *
     * @param int $mode The transfer mode (e.g., FTP_ASCII or FTP_BINARY).
     * @param string $block The data block to write.
     * @return bool True on success, false on failure.
     */
    protected function _data_write_block(int $mode, string $block): bool { // Added scalar type hints and return type hint
        if ($mode != FTP_BINARY) {
            $block = preg_replace("/\r\n|\r|\n/", $this->_eol_code[$this->OS_remote], $block);
        }
        do {
            if (($t = @fwrite($this->_ftp_data_sock, $block)) === false) {
                $this->PushError("_data_write", "Can't write to socket");
                return false;
            }
            $block = substr($block, $t);
        } while (!empty($block));
        return true;
    }

    /**
     * Closes the data connection.
     *
     * @return bool True on success.
     */
    protected function _data_close(): bool { // Added return type hint
        @fclose($this->_ftp_data_sock);
        $this->SendMSG("Disconnected data from remote host");
        return true;
    }

    /**
     * Quits the FTP connection, optionally forcing closure.
     *
     * @param bool $force Whether to force close the connection even if not connected.
     * @return void
     */
    protected function _quit(bool $force = false): void { // Added scalar type hints and return type hint
        if ($this->_connected || $force) {
            @fclose($this->_ftp_control_sock);
            $this->_connected = false;
            $this->SendMSG("Socket closed");
        }
    }
}

?>