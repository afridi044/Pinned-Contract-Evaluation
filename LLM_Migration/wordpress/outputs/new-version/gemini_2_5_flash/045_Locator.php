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
 * Used for feed auto-discovery
 *
 *
 * This class can be overloaded with {@see SimplePie::set_locator_class()}
 *
 * @package SimplePie
 */
class SimplePie_Locator
{
    private readonly SimplePie_File $file;
    private readonly ?string $useragent;
    private readonly int $timeout;
    private readonly int $max_checked_feeds;
    private readonly ?DOMDocument $dom;

    private array $local = [];
    private array $elsewhere = [];
    private array $cached_entities = [];
    private ?string $http_base = null;
    private ?string $base = null;
    private int $base_location = 0;
    private int $checked_feeds = 0;

    protected SimplePie_Registry $registry;

    public function __construct(
        SimplePie_File $file,
        int $timeout = 10,
        ?string $useragent = null,
        int $max_checked_feeds = 10
    ) {
        $this->file = $file;
        $this->useragent = $useragent;
        $this->timeout = $timeout;
        $this->max_checked_feeds = $max_checked_feeds;

        if (class_exists('DOMDocument')) {
            $dom = new DOMDocument();

            set_error_handler([SimplePie_Misc::class, 'silence_errors']);
            $dom->loadHTML($this->file->body);
            restore_error_handler();
            $this->dom = $dom;
        } else {
            $this->dom = null;
        }
    }

    public function set_registry(SimplePie_Registry $registry): void
    {
        $this->registry = $registry;
    }

    /**
     * @param int $type
     * @param SimplePie_File|null $working
     * @return SimplePie_File|null
     * @throws SimplePie_Exception
     */
    public function find(int $type = SIMPLEPIE_LOCATOR_ALL, ?SimplePie_File &$working = null): ?SimplePie_File
    {
        if ($this->is_feed($this->file)) {
            return $this->file;
        }

        if (($this->file->method & SIMPLEPIE_FILE_SOURCE_REMOTE) !== 0) {
            $sniffer = $this->registry->create('Content_Type_Sniffer', [$this->file]);
            if ($sniffer->get_type() !== 'text/html') {
                return null;
            }
        }

        if (($type & ~SIMPLEPIE_LOCATOR_NONE) !== 0) {
            $this->get_base();
        }

        if (($type & SIMPLEPIE_LOCATOR_AUTODISCOVERY) !== 0 && ($working = $this->autodiscovery())) {
            return $working[0];
        }

        if (($type & (SIMPLEPIE_LOCATOR_LOCAL_EXTENSION | SIMPLEPIE_LOCATOR_LOCAL_BODY | SIMPLEPIE_LOCATOR_REMOTE_EXTENSION | SIMPLEPIE_LOCATOR_REMOTE_BODY)) !== 0 && $this->get_links()) {
            if (($type & SIMPLEPIE_LOCATOR_LOCAL_EXTENSION) !== 0 && ($working = $this->extension($this->local))) {
                return $working;
            }

            if (($type & SIMPLEPIE_LOCATOR_LOCAL_BODY) !== 0 && ($working = $this->body($this->local))) {
                return $working;
            }

            if (($type & SIMPLEPIE_LOCATOR_REMOTE_EXTENSION) !== 0 && ($working = $this->extension($this->elsewhere))) {
                return $working;
            }

            if (($type & SIMPLEPIE_LOCATOR_REMOTE_BODY) !== 0 && ($working = $this->body($this->elsewhere))) {
                return $working;
            }
        }
        return null;
    }

    public function is_feed(SimplePie_File $file): bool
    {
        if (($file->method & SIMPLEPIE_FILE_SOURCE_REMOTE) !== 0) {
            $sniffer = $this->registry->create('Content_Type_Sniffer', [$file]);
            $sniffed = $sniffer->get_type();
            if (in_array($sniffed, ['application/rss+xml', 'application/rdf+xml', 'text/rdf', 'application/atom+xml', 'text/xml', 'application/xml'], true)) {
                return true;
            } else {
                return false;
            }
        } elseif (($file->method & SIMPLEPIE_FILE_SOURCE_LOCAL) !== 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @throws SimplePie_Exception
     */
    public function get_base(): void
    {
        if ($this->dom === null) {
            throw new SimplePie_Exception('DOMDocument not found, unable to use locator');
        }
        $this->http_base = $this->file->url;
        $this->base = $this->http_base;
        $elements = $this->dom->getElementsByTagName('base');
        foreach ($elements as $element) {
            if ($element->hasAttribute('href')) {
                $base = $this->registry->call('Misc', 'absolutize_url', [trim($element->getAttribute('href')), $this->http_base]);
                if ($base === false) {
                    continue;
                }
                $this->base = $base;
                $this->base_location = method_exists($element, 'getLineNo') ? $element->getLineNo() : 0;
                break;
            }
        }
    }

    /**
     * @return array<int, SimplePie_File>|null
     * @throws SimplePie_Exception
     */
    public function autodiscovery(): ?array
    {
        $done = [];
        $feeds = [];
        $feeds = array_merge($feeds, $this->search_elements_by_tag('link', $done, $feeds));
        $feeds = array_merge($feeds, $this->search_elements_by_tag('a', $done, $feeds));
        $feeds = array_merge($feeds, $this->search_elements_by_tag('area', $done, $feeds));

        if (!empty($feeds)) {
            return array_values($feeds);
        }
        return null;
    }

    /**
     * @param string $name
     * @param array<string> $done
     * @param array<string, SimplePie_File> $feeds
     * @return array<string, SimplePie_File>
     * @throws SimplePie_Exception
     */
    protected function search_elements_by_tag(string $name, array &$done, array $feeds): array
    {
        if ($this->dom === null) {
            throw new SimplePie_Exception('DOMDocument not found, unable to use locator');
        }

        $links = $this->dom->getElementsByTagName($name);
        foreach ($links as $link) {
            if ($this->checked_feeds === $this->max_checked_feeds) {
                break;
            }
            if ($link->hasAttribute('href') && $link->hasAttribute('rel')) {
                $rel = array_unique($this->registry->call('Misc', 'space_seperated_tokens', [strtolower($link->getAttribute('rel'))]));
                $line = method_exists($link, 'getLineNo') ? $link->getLineNo() : 1;

                $href = ($this->base_location < $line)
                    ? $this->registry->call('Misc', 'absolutize_url', [trim($link->getAttribute('href')), $this->base])
                    : $this->registry->call('Misc', 'absolutize_url', [trim($link->getAttribute('href')), $this->http_base]);

                if ($href === false) {
                    continue;
                }

                if ((in_array('feed', $rel, true) || (in_array('alternate', $rel, true) && !in_array('stylesheet', $rel, true) && $link->hasAttribute('type') && in_array(strtolower($this->registry->call('Misc', 'parse_mime', [$link->getAttribute('type')])), ['application/rss+xml', 'application/atom+xml'], true))) && !isset($feeds[$href])) {
                    $this->checked_feeds++;
                    $headers = [
                        'Accept' => 'application/atom+xml, application/rss+xml, application/rdf+xml;q=0.9, application/xml;q=0.8, text/xml;q=0.8, text/html;q=0.7, unknown/unknown;q=0.1, application/unknown;q=0.1, */*;q=0.1',
                    ];
                    $feed = $this->registry->create('File', [$href, $this->timeout, 5, $headers, $this->useragent]);
                    if ($feed->success && (($feed->method & SIMPLEPIE_FILE_SOURCE_REMOTE) === 0 || ($feed->status_code === 200 || ($feed->status_code > 206 && $feed->status_code < 300))) && $this->is_feed($feed)) {
                        $feeds[$href] = $feed;
                    }
                }
                $done[] = $href;
            }
        }

        return $feeds;
    }

    /**
     * @return bool|null
     * @throws SimplePie_Exception
     */
    public function get_links(): ?bool
    {
        if ($this->dom === null) {
            throw new SimplePie_Exception('DOMDocument not found, unable to use locator');
        }

        $links = $this->dom->getElementsByTagName('a');
        foreach ($links as $link) {
            if ($link->hasAttribute('href')) {
                $href = trim($link->getAttribute('href'));
                $parsed = $this->registry->call('Misc', 'parse_url', [$href]);
                if ($parsed['scheme'] === '' || preg_match('/^(http(s)|feed)?$/i', $parsed['scheme'])) {
                    $href = (method_exists($link, 'getLineNo') && $this->base_location < $link->getLineNo())
                        ? $this->registry->call('Misc', 'absolutize_url', [trim($link->getAttribute('href')), $this->base])
                        : $this->registry->call('Misc', 'absolutize_url', [trim($link->getAttribute('href')), $this->http_base]);

                    if ($href === false) {
                        continue;
                    }

                    $current = $this->registry->call('Misc', 'parse_url', [$this->file->url]);

                    if ($parsed['authority'] === '' || $parsed['authority'] === $current['authority']) {
                        $this->local[] = $href;
                    } else {
                        $this->elsewhere[] = $href;
                    }
                }
            }
        }
        $this->local = array_unique($this->local);
        $this->elsewhere = array_unique($this->elsewhere);
        if (!empty($this->local) || !empty($this->elsewhere)) {
            return true;
        }
        return null;
    }

    /**
     * @param array<string> $array
     * @return SimplePie_File|null
     */
    public function extension(array &$array): ?SimplePie_File
    {
        foreach ($array as $key => $value) {
            if ($this->checked_feeds === $this->max_checked_feeds) {
                break;
            }
            if (in_array(strtolower(strrchr($value, '.')), ['.rss', '.rdf', '.atom', '.xml'], true)) {
                $this->checked_feeds++;

                $headers = [
                    'Accept' => 'application/atom+xml, application/rss+xml, application/rdf+xml;q=0.9, application/xml;q=0.8, text/xml;q=0.8, text/html;q=0.7, unknown/unknown;q=0.1, application/unknown;q=0.1, */*;q=0.1',
                ];
                $feed = $this->registry->create('File', [$value, $this->timeout, 5, $headers, $this->useragent]);
                if ($feed->success && (($feed->method & SIMPLEPIE_FILE_SOURCE_REMOTE) === 0 || ($feed->status_code === 200 || ($feed->status_code > 206 && $feed->status_code < 300))) && $this->is_feed($feed)) {
                    return $feed;
                } else {
                    unset($array[$key]);
                }
            }
        }
        return null;
    }

    /**
     * @param array<string> $array
     * @return SimplePie_File|null
     */
    public function body(array &$array): ?SimplePie_File
    {
        foreach ($array as $key => $value) {
            if ($this->checked_feeds === $this->max_checked_feeds) {
                break;
            }
            if (preg_match('/(rss|rdf|atom|xml)/i', $value)) {
                $this->checked_feeds++;
                $headers = [
                    'Accept' => 'application/atom+xml, application/rss+xml, application/rdf+xml;q=0.9, application/xml;q=0.8, text/xml;q=0.8, text/html;q=0.7, unknown/unknown;q=0.1, application/unknown;q=0.1, */*;q=0.1',
                ];
                $feed = $this->registry->create('File', [$value, $this->timeout, 5, null, $this->useragent]);
                if ($feed->success && (($feed->method & SIMPLEPIE_FILE_SOURCE_REMOTE) === 0 || ($feed->status_code === 200 || ($feed->status_code > 206 && $feed->status_code < 300))) && $this->is_feed($feed)) {
                    return $feed;
                } else {
                    unset($array[$key]);
                }
            }
        }
        return null;
    }
}