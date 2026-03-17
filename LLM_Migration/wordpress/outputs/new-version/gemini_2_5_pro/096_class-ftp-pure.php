<?php

declare(strict_types=1);

namespace PemFTP\Pure;

use ftp_base;

// Assuming ftp_base is in the global namespace or autoloadable.
// If it were also namespaced, e.g., PemFTP\ftp_base, we would use:
// use PemFTP\ftp_base;

/**
 * FTP implementation using fsockopen to connect.
 *
 * This class provides a pure PHP implementation of an FTP client.
 *
 * @version 2.0
 * @author Alexey Dotsenko
 * @copyright Alexey Dotsenko
 * @license LGPL http://www.opensource.org/licenses/lgpl-license.html
 * @link http://www.phpclasses.org/browse/package/1743.html Site
 */
class Ftp extends ftp_base
{
    /**
     * The socket for the data connection.
     * @var resource|null
     */
    private mixed $_ftp_data_sock = null;

    /**
     * The host for the data connection.
     */
    private ?string $_datahost = null;

    /**
     * The port for the data connection.
     */
    private ?int $_dataport = null;

    public function __construct(bool $verb = false, bool $le = false)
    {
        parent::__construct(false, $verb, $le);
    }

    // <!-- --------------------------------------------------------------------------------------- -->
    // <!--       Protected functions (implementation)                                              -->
    // <!-- --------------------------------------------------------------------------------------- -->

    /**
     * Set the timeout for a socket stream.
     *
     * @param resource $sock The socket resource.
     * @return bool True on success, false on failure.
     */
    protected function _settimeout(mixed $sock): bool
    {
        if (!stream_set_timeout($sock, $this->_timeout)) {
            $this->PushError('_settimeout', 'socket set send timeout');
            $this->_quit();
            return false;
        }
        return true;
    }

    /**
     * Create the control socket connection.
     *
     * @return resource|false The socket resource on success, false on failure.
     */
    protected function _connect(string $host, int $port): mixed
    {
        $this->SendMSG("Creating socket");
        $sock = fsockopen($host, $port, $errno, $errstr, $this->_timeout);
        if (!$sock) {
            $this->PushError('_connect', 'socket connect failed', $errstr . " (" . $errno . ")");
            return false;
        }
        $this->_connected = true;
        return $sock;
    }

    /**
     * Read a response from the FTP control socket.
     */
    protected function _readmsg(string $fnction = "_readmsg"): bool
    {
        if (!$this->_connected) {
            $this->PushError($fnction, 'Connect first');
            return false;
        }

        $result = true;
        $this->_message = "";
        $this->_code = 0;
        $go = true;
        $regs = [];

        do {
            $tmp = fgets($this->_ftp_control_sock, 512);
            if ($tmp === false) {
                $go = $result = false;
                $this->PushError($fnction, 'Read failed');
            } else {
                $this->_message .= $tmp;
                if (preg_match("/^([0-9]{3})(-(.*[" . CRLF . "]{1,2})+\\1)? [^" . CRLF . "]+[" . CRLF . "]{1,2}$/", $this->_message, $regs)) {
                    $go = false;
                }
            }
        } while ($go);

        if ($this->LocalEcho) {
            echo "GET < " . rtrim($this->_message, CRLF) . CRLF;
        }

        if (isset($regs[1])) {
            $this->_code = (int)$regs[1];
        }

        return $result;
    }

    /**
     * Execute an FTP command.
     */
    protected function _exec(string $cmd, string $fnction = "_exec"): bool
    {
        if (!$this->_ready) {
            $this->PushError($fnction, 'Connect first');
            return false;
        }

        if ($this->LocalEcho) {
            echo "PUT > ", $cmd, CRLF;
        }

        $status = fputs($this->_ftp_control_sock, $cmd . CRLF);
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
     * Prepare the data connection.
     */
    protected function _data_prepare(int $mode = FTP_ASCII): bool
    {
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

            if (!preg_match('/([0-9]{1,3}),([0-9]{1,3}),([0-9]{1,3}),([0-9]{1,3}),([0-9]+),([0-9]+)/', $this->_message, $matches)) {
                $this->PushError("_data_prepare", "Cannot parse PASV response", $this->_message);
                $this->_data_close();
                return false;
            }

            $this->_datahost = $matches[1] . "." . $matches[2] . "." . $matches[3] . "." . $matches[4];
            $this->_dataport = (((int)$matches[5]) << 8) + ((int)$matches[6]);

            $this->SendMSG("Connecting to " . $this->_datahost . ":" . $this->_dataport);
            $this->_ftp_data_sock = fsockopen($this->_datahost, $this->_dataport, $errno, $errstr, $this->_timeout);

            if (!$this->_ftp_data_sock) {
                $this->PushError("_data_prepare", "fsockopen fails", $errstr . " (" . $errno . ")");
                $this->_data_close();
                return false;
            }
        } else {
            $this->SendMSG("Only passive connections available!");
            return false;
        }

        return true;
    }

    /**
     * Read data from the data connection.
     *
     * @param resource|null $fp Optional file pointer to write data to.
     * @return string|int|false The data as a string, or bytes written as an int, or false on failure.
     */
    protected function _data_read(int $mode = FTP_ASCII, mixed $fp = null): string|int|false
    {
        $out = is_resource($fp) ? 0 : "";

        if (!$this->_passive) {
            $this->SendMSG("Only passive connections available!");
            return false;
        }

        while (!feof($this->_ftp_data_sock)) {
            $block = fread($this->_ftp_data_sock, $this->_ftp_buff_size);
            if ($block === false) {
                $this->PushError("_data_read", "Can't read from data socket");
                return false;
            }

            if ($mode !== FTP_BINARY) {
                $block = preg_replace("/\r\n|\r|\n/", $this->_eol_code[$this->OS_local], $block);
            }

            if (is_resource($fp)) {
                $bytes_written = fwrite($fp, $block);
                if ($bytes_written === false) {
                    $this->PushError("_data_read", "Can't write to file pointer");
                    return false;
                }
                $out += $bytes_written;
            } else {
                $out .= $block;
            }
        }

        return $out;
    }

    /**
     * Write data to the data connection.
     *
     * @param resource|string|null $fp A file pointer to read from or a string of data to write.
     */
    protected function _data_write(int $mode = FTP_ASCII, mixed $fp = null): bool
    {
        if (!$this->_passive) {
            $this->SendMSG("Only passive connections available!");
            return false;
        }

        if (is_resource($fp)) {
            while (!feof($fp)) {
                $block = fread($fp, $this->_ftp_buff_size);
                if ($block === false) {
                    $this->PushError("_data_write", "Can't read from file pointer");
                    return false;
                }
                if (!$this->_data_write_block($mode, $block)) {
                    return false;
                }
            }
        } elseif (is_string($fp)) {
            if (!$this->_data_write_block($mode, $fp)) {
                return false;
            }
        } else {
            $this->PushError("_data_write", "Invalid data source provided");
            return false;
        }

        return true;
    }

    /**
     * Write a block of data to the data socket.
     */
    protected function _data_write_block(int $mode, string $block): bool
    {
        if ($mode !== FTP_BINARY) {
            $block = preg_replace("/\r\n|\r|\n/", $this->_eol_code[$this->OS_remote], $block);
        }

        do {
            $written = fwrite($this->_ftp_data_sock, $block);
            if ($written === false) {
                $this->PushError("_data_write_block", "Can't write to socket");
                return false;
            }
            $block = substr($block, $written);
        } while (!empty($block));

        return true;
    }

    /**
     * Close the data connection.
     */
    protected function _data_close(): bool
    {
        if (is_resource($this->_ftp_data_sock)) {
            fclose($this->_ftp_data_sock);
        }
        $this->_ftp_data_sock = null;
        $this->SendMSG("Disconnected data from remote host");
        return true;
    }

    /**
     * Close the control connection.
     */
    protected function _quit(bool $force = false): void
    {
        if ($this->_connected || $force) {
            if (is_resource($this->_ftp_control_sock)) {
                fclose($this->_ftp_control_sock);
            }
            $this->_connected = false;
            $this->SendMSG("Socket closed");
        }
    }
}