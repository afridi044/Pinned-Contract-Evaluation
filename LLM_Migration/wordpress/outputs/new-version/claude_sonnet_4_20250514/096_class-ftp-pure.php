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
class ftp extends ftp_base {

	public function __construct(bool $verb = false, bool $le = false) {
		parent::__construct(false, $verb, $le);
	}

// <!-- --------------------------------------------------------------------------------------- -->
// <!--       Private functions                                                                 -->
// <!-- --------------------------------------------------------------------------------------- -->

	private function _settimeout($sock): bool {
		if(!@stream_set_timeout($sock, $this->_timeout)) {
			$this->PushError('_settimeout','socket set send timeout');
			$this->_quit();
			return false;
		}
		return true;
	}

	private function _connect(string $host, int $port) {
		$this->SendMSG("Creating socket");
		$sock = @fsockopen($host, $port, $errno, $errstr, $this->_timeout);
		if (!$sock) {
			$this->PushError('_connect','socket connect failed', $errstr." (".$errno.")");
			return false;
		}
		$this->_connected = true;
		return $sock;
	}

	private function _readmsg(string $fnction = "_readmsg"): bool {
		if(!$this->_connected) {
			$this->PushError($fnction, 'Connect first');
			return false;
		}
		$result = true;
		$this->_message = "";
		$this->_code = 0;
		$go = true;
		do {
			$tmp = @fgets($this->_ftp_control_sock, 512);
			if($tmp === false) {
				$go = $result = false;
				$this->PushError($fnction,'Read failed');
			} else {
				$this->_message .= $tmp;
				if(preg_match("/^([0-9]{3})(-(.*[".CRLF."]{1,2})+\\1)? [^".CRLF."]+[".CRLF."]{1,2}$/", $this->_message, $regs)) {
					$go = false;
				}
			}
		} while($go);
		if($this->LocalEcho) echo "GET < ".rtrim($this->_message, CRLF).CRLF;
		$this->_code = (int)$regs[1];
		return $result;
	}

	private function _exec(string $cmd, string $fnction = "_exec"): bool {
		if(!$this->_ready) {
			$this->PushError($fnction,'Connect first');
			return false;
		}
		if($this->LocalEcho) echo "PUT > ",$cmd,CRLF;
		$status = @fputs($this->_ftp_control_sock, $cmd.CRLF);
		if($status === false) {
			$this->PushError($fnction,'socket write failed');
			return false;
		}
		$this->_lastaction = time();
		if(!$this->_readmsg($fnction)) return false;
		return true;
	}

	private function _data_prepare(int $mode = FTP_ASCII): bool {
		if(!$this->_settype($mode)) return false;
		if($this->_passive) {
			if(!$this->_exec("PASV", "pasv")) {
				$this->_data_close();
				return false;
			}
			if(!$this->_checkCode()) {
				$this->_data_close();
				return false;
			}
			$ip_port = explode(",", preg_replace("/^.+ \\(?([0-9]{1,3},[0-9]{1,3},[0-9]{1,3},[0-9]{1,3},[0-9]+,[0-9]+)\\)?.*".CRLF."$/", "\\1", $this->_message));
			$this->_datahost = $ip_port[0].".".$ip_port[1].".".$ip_port[2].".".$ip_port[3];
            $this->_dataport = (((int)$ip_port[4])<<8) + ((int)$ip_port[5]);
			$this->SendMSG("Connecting to ".$this->_datahost.":".$this->_dataport);
			$this->_ftp_data_sock = @fsockopen($this->_datahost, $this->_dataport, $errno, $errstr, $this->_timeout);
			if(!$this->_ftp_data_sock) {
				$this->PushError("_data_prepare","fsockopen fails", $errstr." (".$errno.")");
				$this->_data_close();
				return false;
			}
		} else {
			$this->SendMSG("Only passive connections available!");
			return false;
		}
		return true;
	}

	private function _data_read(int $mode = FTP_ASCII, $fp = null): string|int|false {
		if(is_resource($fp)) $out = 0;
		else $out = "";
		if(!$this->_passive) {
			$this->SendMSG("Only passive connections available!");
			return false;
		}
		while (!feof($this->_ftp_data_sock)) {
			$block = fread($this->_ftp_data_sock, $this->_ftp_buff_size);
			if($mode !== FTP_BINARY) $block = preg_replace("/\r\n|\r|\n/", $this->_eol_code[$this->OS_local], $block);
			if(is_resource($fp)) $out += fwrite($fp, $block, strlen($block));
			else $out .= $block;
		}
		return $out;
	}

	private function _data_write(int $mode = FTP_ASCII, $fp = null): bool {
		if(!$this->_passive) {
			$this->SendMSG("Only passive connections available!");
			return false;
		}
		if(is_resource($fp)) {
			while(!feof($fp)) {
				$block = fread($fp, $this->_ftp_buff_size);
				if(!$this->_data_write_block($mode, $block)) return false;
			}
		} elseif(!$this->_data_write_block($mode, $fp)) return false;
		return true;
	}

	private function _data_write_block(int $mode, string $block): bool {
		if($mode !== FTP_BINARY) $block = preg_replace("/\r\n|\r|\n/", $this->_eol_code[$this->OS_remote], $block);
		do {
			if(($t = @fwrite($this->_ftp_data_sock, $block)) === false) {
				$this->PushError("_data_write","Can't write to socket");
				return false;
			}
			$block = substr($block, $t);
		} while(!empty($block));
		return true;
	}

	private function _data_close(): bool {
		@fclose($this->_ftp_data_sock);
		$this->SendMSG("Disconnected data from remote host");
		return true;
	}

	private function _quit(bool $force = false): void {
		if($this->_connected || $force) {
			@fclose($this->_ftp_control_sock);
			$this->_connected = false;
			$this->SendMSG("Socket closed");
		}
	}
}