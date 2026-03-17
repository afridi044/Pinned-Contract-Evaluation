<?php

declare(strict_types=1);

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

// Assuming these constants are defined globally or in an included file.
// For a modern approach, these might be enums or class constants within a dedicated constants class.
if (!defined('SIMPLEPIE_FILE_SOURCE_NONE')) {
    define('SIMPLEPIE_FILE_SOURCE_NONE', 0);
}
if (!defined('SIMPLEPIE_FILE_SOURCE_REMOTE')) {
    define('SIMPLEPIE_FILE_SOURCE_REMOTE', 1);
}
if (!defined('SIMPLEPIE_FILE_SOURCE_CURL')) {
    define('SIMPLEPIE_FILE_SOURCE_CURL', 2);
}
if (!defined('SIMPLEPIE_FILE_SOURCE_FSOCKOPEN')) {
    define('SIMPLEPIE_FILE_SOURCE_FSOCKOPEN', 4);
}
if (!defined('SIMPLEPIE_FILE_SOURCE_LOCAL')) {
    define('SIMPLEPIE_FILE_SOURCE_LOCAL', 8);
}
if (!defined('SIMPLEPIE_FILE_SOURCE_FILE_GET_CONTENTS')) {
    define('SIMPLEPIE_FILE_SOURCE_FILE_GET_CONTENTS', 16);
}

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
    public ?string $useragent = null;
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
        int $maxRedirects = 5, // Renamed to avoid conflict with property $redirects
        ?array $headers = null,
        ?string $userAgentParam = null, // Renamed to avoid conflict with property $useragent
        bool $force_fsockopen = false
    ) {
        if (class_exists('idna_convert')) {
            $idn = new idna_convert();
            $parsed = SimplePie_Misc::parse_url($url);
            $url = SimplePie_Misc::compress_parse_url($parsed['scheme'], $idn->encode($parsed['authority']), $parsed['path'], $parsed['query'], $parsed['fragment']);
        }

        $this->url = $url;
        $this->useragent = $userAgentParam;

        if (preg_match('/^http(s)?:\/\//i', $url)) {
            if ($this->useragent === null) {
                $this->useragent = ini_get('user_agent') ?: 'SimplePie/1.3.1 (PHP/' . PHP_VERSION . '; +http://simplepie.org)';
            }
            $headers ??= []; // Use null coalescing assignment operator

            if (!$force_fsockopen && function_exists('curl_exec')) {
                $this->method = SIMPLEPIE_FILE_SOURCE_REMOTE | SIMPLEPIE_FILE_SOURCE_CURL;
                $fp = curl_init();
                $headers2 = [];
                foreach ($headers as $key => $value) {
                    $headers2[] = "$key: $value";
                }

                if (version_compare(SimplePie_Misc::get_curl_version(), '7.10.5', '>=')) {
                    curl_setopt($fp, CURLOPT_ENCODING, '');
                }
                curl_setopt($fp, CURLOPT_URL, $url);
                curl_setopt($fp, CURLOPT_HEADER, 1);
                curl_setopt($fp, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($fp, CURLOPT_TIMEOUT, $timeout);
                curl_setopt($fp, CURLOPT_CONNECTTIMEOUT, $timeout);
                curl_setopt($fp, CURLOPT_REFERER, $url);
                curl_setopt($fp, CURLOPT_USERAGENT, $this->useragent);
                curl_setopt($fp, CURLOPT_HTTPHEADER, $headers2);

                if (!ini_get('open_basedir') && !ini_get('safe_mode') && version_compare(SimplePie_Misc::get_curl_version(), '7.15.2', '>=')) {
                    curl_setopt($fp, CURLOPT_FOLLOWLOCATION, 1);
                    curl_setopt($fp, CURLOPT_MAXREDIRS, $maxRedirects);
                }

                $rawHeaders = curl_exec($fp);
                if (curl_errno($fp) === 23 || curl_errno($fp) === 61) {
                    curl_setopt($fp, CURLOPT_ENCODING, 'none');
                    $rawHeaders = curl_exec($fp);
                }

                if (curl_errno($fp)) {
                    $this->error = 'cURL error ' . curl_errno($fp) . ': ' . curl_error($fp);
                    $this->success = false;
                } else {
                    $info = curl_getinfo($fp);
                    curl_close($fp);

                    // Handle multiple sets of headers if redirects occurred
                    $headerParts = explode("\r\n\r\n", (string) $rawHeaders, $info['redirect_count'] + 1);
                    $lastHeaders = array_pop($headerParts);

                    $parser = new SimplePie_HTTP_Parser($lastHeaders);
                    if ($parser->parse()) {
                        $this->headers = $parser->headers;
                        $this->body = $parser->body;
                        $this->status_code = $parser->status_code;

                        if ((in_array($this->status_code, [300, 301, 302, 303, 307], true) || ($this->status_code > 307 && $this->status_code < 400)) && isset($this->headers['location']) && $this->redirects < $maxRedirects) {
                            $this->redirects++;
                            $location = SimplePie_Misc::absolutize_url($this->headers['location'], $url);
                            // Recursive call to handle redirects
                            return $this->__construct($location, $timeout, $maxRedirects, $headers, $userAgentParam, $force_fsockopen);
                        }
                    } else {
                        $this->error = 'HTTP header parsing failed for cURL response.';
                        $this->success = false;
                    }
                }
            } else {
                $this->method = SIMPLEPIE_FILE_SOURCE_REMOTE | SIMPLEPIE_FILE_SOURCE_FSOCKOPEN;
                $url_parts = parse_url($url);
                $socket_host = $url_parts['host'];

                if (isset($url_parts['scheme']) && strtolower($url_parts['scheme']) === 'https') {
                    $socket_host = "ssl://$url_parts[host]";
                    $url_parts['port'] = 443;
                }
                $url_parts['port'] ??= 80; // Use null coalescing assignment

                $fp = @fsockopen($socket_host, $url_parts['port'], $errno, $errstr, $timeout);
                if (!$fp) {
                    $this->error = 'fsockopen error: ' . $errstr;
                    $this->success = false;
                } else {
                    stream_set_timeout($fp, $timeout);

                    $path = $url_parts['path'] ?? '/';
                    $query = $url_parts['query'] ?? '';
                    $get = $path . ($query ? '?' . $query : '');

                    $out = "GET $get HTTP/1.1\r\n";
                    $out .= "Host: $url_parts[host]\r\n";
                    $out .= "User-Agent: {$this->useragent}\r\n"; // Use property
                    if (extension_loaded('zlib')) {
                        $out .= "Accept-Encoding: x-gzip,gzip,deflate\r\n";
                    }

                    if (isset($url_parts['user']) && isset($url_parts['pass'])) {
                        $out .= "Authorization: Basic " . base64_encode("$url_parts[user]:$url_parts[pass]") . "\r\n";
                    }
                    foreach ($headers as $key => $value) {
                        $out .= "$key: $value\r\n";
                    }
                    $out .= "Connection: Close\r\n\r\n";
                    fwrite($fp, $out);

                    $info = stream_get_meta_data($fp);

                    $rawHeaders = '';
                    while (!$info['eof'] && !$info['timed_out']) {
                        $rawHeaders .= fread($fp, 1160);
                        $info = stream_get_meta_data($fp);
                    }

                    if (!$info['timed_out']) {
                        $parser = new SimplePie_HTTP_Parser($rawHeaders);
                        if ($parser->parse()) {
                            $this->headers = $parser->headers;
                            $this->body = $parser->body;
                            $this->status_code = $parser->status_code;

                            if ((in_array($this->status_code, [300, 301, 302, 303, 307], true) || ($this->status_code > 307 && $this->status_code < 400)) && isset($this->headers['location']) && $this->redirects < $maxRedirects) {
                                $this->redirects++;
                                $location = SimplePie_Misc::absolutize_url($this->headers['location'], $url);
                                // Recursive call to handle redirects
                                return $this->__construct($location, $timeout, $maxRedirects, $headers, $userAgentParam, $force_fsockopen);
                            }

                            if (isset($this->headers['content-encoding'])) {
                                $encoding = strtolower(trim($this->headers['content-encoding'], "\x09\x0A\x0D\x20"));
                                match ($encoding) {
                                    'gzip', 'x-gzip' => function () {
                                        $decoder = new SimplePie_gzdecode($this->body);
                                        if (!$decoder->parse()) {
                                            $this->error = 'Unable to decode HTTP "gzip" stream';
                                            $this->success = false;
                                        } else {
                                            $this->body = $decoder->data;
                                        }
                                    }(),
                                    'deflate' => function () {
                                        if (($decompressed = gzinflate($this->body)) !== false) {
                                            $this->body = $decompressed;
                                        } elseif (($decompressed = gzuncompress($this->body)) !== false) {
                                            $this->body = $decompressed;
                                        } elseif (function_exists('gzdecode') && ($decompressed = gzdecode($this->body)) !== false) {
                                            $this->body = $decompressed;
                                        } else {
                                            $this->error = 'Unable to decode HTTP "deflate" stream';
                                            $this->success = false;
                                        }
                                    }(),
                                    default => function () {
                                        $this->error = 'Unknown content coding';
                                        $this->success = false;
                                    }(),
                                };
                            }
                        } else {
                            $this->error = 'HTTP header parsing failed for fsockopen response.';
                            $this->success = false;
                        }
                    } else {
                        $this->error = 'fsocket timed out';
                        $this->success = false;
                    }
                    fclose($fp);
                }
            }
        } else {
            $this->method = SIMPLEPIE_FILE_SOURCE_LOCAL | SIMPLEPIE_FILE_SOURCE_FILE_GET_CONTENTS;
            $body_content = file_get_contents($url);
            if ($body_content === false) { // Explicitly check for false on failure
                $this->error = 'file_get_contents could not read the file';
                $this->success = false;
            } else {
                $this->body = $body_content;
            }
        }
    }
}