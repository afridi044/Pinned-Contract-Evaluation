<?php
/**
 * SimplePie
 *
 * A PHP-Based RSS and Atom Feed Framework.
 * Takes the hard work out of managing a complete RSS/Atom solution.
 *
 * Copyright (c) 2004-2012, Ryan Parman, Geoffrey Sneddon, Ryan McCue, and contributors
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are
 * permitted provided that the following conditions are met:
 *
 * 	* Redistributions of source code must retain the above copyright notice, this list of
 * 	  conditions and the following disclaimer.
 *
 * 	* Redistributions in binary form must reproduce the above copyright notice, this list
 * 	  of conditions and the following disclaimer in the documentation and/or other materials
 * 	  provided with the distribution.
 *
 * 	* Neither the name of the SimplePie Team nor the names of its contributors may be used
 * 	  to endorse or promote products derived from this software without specific prior
 * 	  written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS
 * OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS
 * AND CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
 * OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package SimplePie
 * @version 1.3.1
 * @copyright 2004-2012 Ryan Parman, Geoffrey Sneddon, Ryan McCue
 * @author Ryan Parman
 * @author Geoffrey Sneddon
 * @author Ryan McCue
 * @link http://simplepie.org/ SimplePie
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

/**
 * Used for fetching remote files and reading local files
 *
 * Supports HTTP 1.0 via cURL or fsockopen, with spotty HTTP 1.1 support
 *
 * This class can be overloaded with {@see SimplePie::set_file_class()}
 *
 * @package SimplePie
 * @subpackage HTTP
 * @todo Move to properly supporting RFC2616 (HTTP/1.1)
 */
class SimplePie_File
{
	public string $url;
	public ?string $useragent;
	public bool $success = true;
	public array $headers = [];
	public ?string $body = null;
	public ?int $status_code = null;
	public int $redirects = 0;
	public ?string $error = null;
	public int $method = SIMPLEPIE_FILE_SOURCE_NONE;

	public function __construct(
		string $url,
		int $timeout = 10,
		int $max_redirects = 5,
		?array $request_headers = null,
		?string $useragent = null,
		bool $force_fsockopen = false
	) {
		$this->useragent = $useragent;
		$current_url = $url;
		$request_headers ??= [];

		// Handle local files first.
		if (!str_starts_with(strtolower($current_url), 'http://') && !str_starts_with(strtolower($current_url), 'https://')) {
			$this->method = SIMPLEPIE_FILE_SOURCE_LOCAL | SIMPLEPIE_FILE_SOURCE_FILE_GET_CONTENTS;
			$this->url = $current_url;
			$this->body = file_get_contents($current_url);
			if ($this->body === false) {
				$this->error = 'file_get_contents could not read the file';
				$this->success = false;
				$this->body = null;
			}
			return;
		}

		// It's a remote file, loop for redirects.
		while ($this->redirects <= $max_redirects) {
			if (class_exists('idna_convert')) {
				$idn = new idna_convert();
				$parsed = SimplePie_Misc::parse_url($current_url);
				$current_url = SimplePie_Misc::compress_parse_url($parsed['scheme'], $idn->encode($parsed['authority']), $parsed['path'], $parsed['query'], $parsed['fragment']);
			}
			$this->url = $current_url;

			if ($this->useragent === null) {
				$this->useragent = ini_get('user_agent');
			}

			if (!$force_fsockopen && function_exists('curl_exec')) {
				$this->method = SIMPLEPIE_FILE_SOURCE_REMOTE | SIMPLEPIE_FILE_SOURCE_CURL;
				$ch = curl_init();

				$curl_headers = [];
				foreach ($request_headers as $key => $value) {
					$curl_headers[] = "$key: $value";
				}

				$options = [
					CURLOPT_URL => $current_url,
					CURLOPT_HEADER => true,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_TIMEOUT => $timeout,
					CURLOPT_CONNECTTIMEOUT => $timeout,
					CURLOPT_REFERER => $current_url,
					CURLOPT_USERAGENT => $this->useragent,
					CURLOPT_HTTPHEADER => $curl_headers,
					CURLOPT_FOLLOWLOCATION => false,
				];

				if (version_compare(SimplePie_Misc::get_curl_version(), '7.10.5', '>=')) {
					$options[CURLOPT_ENCODING] = '';
				}

				curl_setopt_array($ch, $options);

				$response = curl_exec($ch);

				if (curl_errno($ch) === 23 || curl_errno($ch) === 61) {
					curl_setopt($ch, CURLOPT_ENCODING, 'none');
					$response = curl_exec($ch);
				}

				if (curl_errno($ch)) {
					$this->error = 'cURL error ' . curl_errno($ch) . ': ' . curl_error($ch);
					$this->success = false;
					curl_close($ch);
					break;
				}

				$info = curl_getinfo($ch);
				curl_close($ch);

				$header_size = $info['header_size'];
				$raw_headers = substr((string) $response, 0, $header_size);
				$this->body = substr((string) $response, $header_size);

				$parser = new SimplePie_HTTP_Parser($raw_headers);
				if ($parser->parse()) {
					$this->headers = $parser->headers;
					$this->status_code = $parser->status_code;
				} else {
					$this->error = 'Could not parse HTTP headers.';
					$this->success = false;
					break;
				}
			} else {
				$this->method = SIMPLEPIE_FILE_SOURCE_REMOTE | SIMPLEPIE_FILE_SOURCE_FSOCKOPEN;
				$url_parts = parse_url($current_url);
				$socket_host = $url_parts['host'];
				$port = $url_parts['port'] ?? 80;

				if (isset($url_parts['scheme']) && strtolower($url_parts['scheme']) === 'https') {
					$socket_host = "ssl://$url_parts[host]";
					$port = $url_parts['port'] ?? 443;
				}

				$fp = fsockopen($socket_host, $port, $errno, $errstr, $timeout);
				if ($fp === false) {
					$this->error = "fsockopen error: ($errno) $errstr";
					$this->success = false;
					break;
				}

				stream_set_timeout($fp, $timeout);
				$path = $url_parts['path'] ?? '/';
				if (isset($url_parts['query'])) {
					$path .= "?$url_parts[query]";
				}

				$out = "GET $path HTTP/1.1\r\n";
				$out .= "Host: $url_parts[host]\r\n";
				$out .= "User-Agent: $this->useragent\r\n";
				if (extension_loaded('zlib')) {
					$out .= "Accept-Encoding: x-gzip,gzip,deflate\r\n";
				}
				if (isset($url_parts['user'], $url_parts['pass'])) {
					$out .= "Authorization: Basic " . base64_encode("$url_parts[user]:$url_parts[pass]") . "\r\n";
				}
				foreach ($request_headers as $key => $value) {
					$out .= "$key: $value\r\n";
				}
				$out .= "Connection: Close\r\n\r\n";
				fwrite($fp, $out);

				$raw_response = stream_get_contents($fp);
				$info = stream_get_meta_data($fp);
				fclose($fp);

				if ($info['timed_out']) {
					$this->error = 'fsocket timed out';
					$this->success = false;
					break;
				}

				$parser = new SimplePie_HTTP_Parser($raw_response);
				if ($parser->parse()) {
					$this->headers = $parser->headers;
					$this->body = $parser->body;
					$this->status_code = $parser->status_code;

					if (isset($this->headers['content-encoding']) && $this->body !== null) {
						$encoding = strtolower(trim($this->headers['content-encoding']));
						match ($encoding) {
							'gzip', 'x-gzip' => (function() {
								$decoder = new SimplePie_gzdecode($this->body);
								if (!$decoder->parse()) {
									$this->error = 'Unable to decode HTTP "gzip" stream';
									$this->success = false;
								} else {
									$this->body = $decoder->data;
								}
							})(),
							'deflate' => (function() {
								$decompressed = @gzinflate($this->body);
								if ($decompressed === false) {
									$decompressed = @gzuncompress($this->body);
								}
								if ($decompressed === false && function_exists('gzdecode')) {
									$decompressed = @gzdecode($this->body);
								}

								if ($decompressed !== false) {
									$this->body = $decompressed;
								} else {
									$this->error = 'Unable to decode HTTP "deflate" stream';
									$this->success = false;
								}
							})(),
							default => (function() {
								$this->error = 'Unknown content coding';
								$this->success = false;
							})(),
						};
						if (!$this->success) {
							break;
						}
					}
				} else {
					$this->error = 'Could not parse HTTP headers.';
					$this->success = false;
					break;
				}
			}

			$is_redirect = in_array($this->status_code, [300, 301, 302, 303, 307], true) || ($this->status_code > 307 && $this->status_code < 400);
			if ($is_redirect && isset($this->headers['location'])) {
				$this->redirects++;
				$current_url = SimplePie_Misc::absolutize_url($this->headers['location'], $current_url);
			} else {
				break;
			}
		}

		if ($this->redirects > $max_redirects) {
			$this->error = 'Too many redirects';
			$this->success = false;
		}
	}
}