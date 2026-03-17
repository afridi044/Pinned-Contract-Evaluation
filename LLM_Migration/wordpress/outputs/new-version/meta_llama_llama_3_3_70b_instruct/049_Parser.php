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
 * Parses XML into something sane
 *
 *
 * This class can be overloaded with {@see SimplePie::set_parser_class()}
 *
 * @package SimplePie
 * @subpackage Parsing
 */
class SimplePie_Parser
{
    private int $error_code;
    private string $error_string;
    private int $current_line;
    private int $current_column;
    private int $current_byte;
    private string $separator = ' ';
    private array $namespace = [''];
    private array $element = [''];
    private array $xml_base = [''];
    private array $xml_base_explicit = [false];
    private array $xml_lang = [''];
    private array $data = [];
    private array $datas = [[]];
    private int $current_xhtml_construct = -1;
    private string $encoding;
    protected SimplePie_Registry $registry;

    public function set_registry(SimplePie_Registry $registry): void
    {
        $this->registry = $registry;
    }

    public function parse(string &$data, string $encoding): bool
    {
        // Use UTF-8 if we get passed US-ASCII, as every US-ASCII character is a UTF-8 character
        if (strtoupper($encoding) === 'US-ASCII') {
            $this->encoding = 'UTF-8';
        } else {
            $this->encoding = $encoding;
        }

        // Strip BOM:
        // UTF-32 Big Endian BOM
        if (substr($data, 0, 4) === "\x00\x00\xFE\xFF") {
            $data = substr($data, 4);
        }
        // UTF-32 Little Endian BOM
        elseif (substr($data, 0, 4) === "\xFF\xFE\x00\x00") {
            $data = substr($data, 4);
        }
        // UTF-16 Big Endian BOM
        elseif (substr($data, 0, 2) === "\xFE\xFF") {
            $data = substr($data, 2);
        }
        // UTF-16 Little Endian BOM
        elseif (substr($data, 0, 2) === "\xFF\xFE") {
            $data = substr($data, 2);
        }
        // UTF-8 BOM
        elseif (substr($data, 0, 3) === "\xEF\xBB\xBF") {
            $data = substr($data, 3);
        }

        if (substr($data, 0, 5) === '<?xml' && strspn(substr($data, 5, 1), "\x09\x0A\x0D\x20") && ($pos = strpos($data, '?>')) !== false) {
            $declaration = $this->registry->create('XML_Declaration_Parser', [substr($data, 5, $pos - 5)]);
            if ($declaration->parse()) {
                $data = substr($data, $pos + 2);
                $data = '<?xml version="' . $declaration->version . '" encoding="' . $encoding . '" standalone="' . (($declaration->standalone) ? 'yes' : 'no') . '"?>' . $data;
            } else {
                $this->error_string = 'SimplePie bug! Please report this!';
                return false;
            }
        }

        $return = true;

        static $xml_is_sane = null;
        if ($xml_is_sane === null) {
            $parser_check = xml_parser_create();
            xml_parse_into_struct($parser_check, '<foo>&amp;</foo>', $values);
            xml_parser_free($parser_check);
            $xml_is_sane = isset($values[0]['value']);
        }

        // Create the parser
        if ($xml_is_sane) {
            $xml = xml_parser_create_ns($this->encoding, $this->separator);
            xml_parser_set_option($xml, XML_OPTION_SKIP_WHITE, 1);
            xml_parser_set_option($xml, XML_OPTION_CASE_FOLDING, 0);
            xml_set_object($xml, $this);
            xml_set_character_data_handler($xml, 'cdata');
            xml_set_element_handler($xml, 'tag_open', 'tag_close');

            // Parse!
            if (!xml_parse($xml, $data, true)) {
                $this->error_code = xml_get_error_code($xml);
                $this->error_string = xml_error_string($this->error_code);
                $return = false;
            }
            $this->current_line = xml_get_current_line_number($xml);
            $this->current_column = xml_get_current_column_number($xml);
            $this->current_byte = xml_get_current_byte_index($xml);
            xml_parser_free($xml);
            return $return;
        } else {
            libxml_clear_errors();
            $xml = new XMLReader();
            $xml->xml($data);
            while (@$xml->read()) {
                switch ($xml->nodeType) {

                    case XMLReader::END_ELEMENT:
                        if ($xml->namespaceURI !== '') {
                            $tagName = $xml->namespaceURI . $this->separator . $xml->localName;
                        } else {
                            $tagName = $xml->localName;
                        }
                        $this->tag_close(null, $tagName);
                        break;
                    case XMLReader::ELEMENT:
                        $empty = $xml->isEmptyElement;
                        if ($xml->namespaceURI !== '') {
                            $tagName = $xml->namespaceURI . $this->separator . $xml->localName;
                        } else {
                            $tagName = $xml->localName;
                        }
                        $attributes = [];
                        while ($xml->moveToNextAttribute()) {
                            if ($xml->namespaceURI !== '') {
                                $attrName = $xml->namespaceURI . $this->separator . $xml->localName;
                            } else {
                                $attrName = $xml->localName;
                            }
                            $attributes[$attrName] = $xml->value;
                        }
                        $this->tag_open(null, $tagName, $attributes);
                        if ($empty) {
                            $this->tag_close(null, $tagName);
                        }
                        break;
                    case XMLReader::TEXT:

                    case XMLReader::CDATA:
                        $this->cdata(null, $xml->value);
                        break;
                }
            }
            if ($error = libxml_get_last_error()) {
                $this->error_code = $error->code;
                $this->error_string = $error->message;
                $this->current_line = $error->line;
                $this->current_column = $error->column;
                return false;
            } else {
                return true;
            }
        }
    }

    public function get_error_code(): int
    {
        return $this->error_code;
    }

    public function get_error_string(): string
    {
        return $this->error_string;
    }

    public function get_current_line(): int
    {
        return $this->current_line;
    }

    public function get_current_column(): int
    {
        return $this->current_column;
    }

    public function get_current_byte(): int
    {
        return $this->current_byte;
    }

    public function get_data(): array
    {
        return $this->data;
    }

    public function tag_open($parser, string $tag, array $attributes): void
    {
        list($this->namespace[], $this->element[]) = $this->split_ns($tag);

        $attribs = [];
        foreach ($attributes as $name => $value) {
            list($attrib_namespace, $attribute) = $this->split_ns($name);
            $attribs[$attrib_namespace][$attribute] = $value;
        }

        if (isset($attribs['http://www.w3.org/XML/1998/namespace']['base'])) {
            $base = $this->registry->call('Misc', 'absolutize_url', [$attribs['http://www.w3.org/XML/1998/namespace']['base'], end($this->xml_base)]);
            if ($base !== false) {
                $this->xml_base[] = $base;
                $this->xml_base_explicit[] = true;
            }
        } else {
            $this->xml_base[] = end($this->xml_base);
            $this->xml_base_explicit[] = end($this->xml_base_explicit);
        }

        if (isset($attribs['http://www.w3.org/XML/1998/namespace']['lang'])) {
            $this->xml_lang[] = $attribs['http://www.w3.org/XML/1998/namespace']['lang'];
        } else {
            $this->xml_lang[] = end($this->xml_lang);
        }

        if ($this->current_xhtml_construct >= 0) {
            $this->current_xhtml_construct++;
            if (end($this->namespace) === 'http://www.w3.org/1999/xhtml') {
                $this->data['data'] .= '<' . end($this->element);
                if (isset($attribs[''])) {
                    foreach ($attribs[''] as $name => $value) {
                        $this->data['data'] .= ' ' . $name . '="' . htmlspecialchars($value, ENT_COMPAT, $this->encoding) . '"';
                    }
                }
                $this->data['data'] .= '>';
            }
        } else {
            $this->datas[] =& $this->data;
            $this->data =& $this->data['child'][end($this->namespace)][end($this->element)][];
            $this->data = [
                'data' => '',
                'attribs' => $attribs,
                'xml_base' => end($this->xml_base),
                'xml_base_explicit' => end($this->xml_base_explicit),
                'xml_lang' => end($this->xml_lang)
            ];
            if ((end($this->namespace) === 'http://purl.org/atom/ns#' && in_array(end($this->element), ['title', 'tagline', 'copyright', 'info', 'summary', 'content']) && isset($attribs['']['mode']) && $attribs['']['mode'] === 'xml')
                || (end($this->namespace) === 'http://www.w3.org/2005/Atom' && in_array(end($this->element), ['rights', 'subtitle', 'summary', 'info', 'title', 'content']) && isset($attribs['']['type']) && $attribs['']['type'] === 'xhtml')
                || (end($this->namespace) === 'http://backend.userland.com/rss2' && in_array(end($this->element), ['title']))
                || (end($this->namespace) === 'http://my.netscape.com/rdf/simple/0.9/' && in_array(end($this->element), ['title']))
                || (end($this->namespace) === 'http://purl.org/rss/1.0/' && in_array(end($this->element), ['title']))
            ) {
                $this->current_xhtml_construct = 0;
            }
        }
    }

    public function cdata($parser, string $cdata): void
    {
        if ($this->current_xhtml_construct >= 0) {
            $this->data['data'] .= htmlspecialchars($cdata, ENT_QUOTES, $this->encoding);
        } else {
            $this->data['data'] .= $cdata;
        }
    }

    public function tag_close($parser, string $tag): void
    {
        if ($this->current_xhtml_construct >= 0) {
            $this->current_xhtml_construct--;
            if (end($this->namespace) === 'http://www.w3.org/1999/xhtml' && !in_array(end($this->element), ['area', 'base', 'basefont', 'br', 'col', 'frame', 'hr', 'img', 'input', 'isindex', 'link', 'meta', 'param'])) {
                $this->data['data'] .= '</' . end($this->element) . '>';
            }
        }
        if ($this->current_xhtml_construct === -1) {
            $this->data =& $this->datas[count($this->datas) - 1];
            array_pop($this->datas);
        }

        array_pop($this->element);
        array_pop($this->namespace);
        array_pop($this->xml_base);
        array_pop($this->xml_base_explicit);
        array_pop($this->xml_lang);
    }

    public function split_ns(string $string): array
    {
        static $cache = [];
        if (!isset($cache[$string])) {
            if ($pos = strpos($string, $this->separator)) {
                static $separator_length;
                if (!$separator_length) {
                    $separator_length = strlen($this->separator);
                }
                $namespace = substr($string, 0, $pos);
                $local_name = substr($string, $pos + $separator_length);
                if (strtolower($namespace) === 'http://www.itunes.com/dtds/podcast-1.0.dtd') {
                    $namespace = 'http://www.itunes.com/dtds/podcast-1.0.dtd';
                }

                // Normalize the Media RSS namespaces
                if (in_array($namespace, ['http://search.yahoo.com/mrss/', 'http://search.yahoo.com/mrss', 'http://video.search.yahoo.com/mrss', 'http://video.search.yahoo.com/mrss/', 'http://media.rss.com/'])) {
                    $namespace = 'http://search.yahoo.com/mrss/';
                }
                $cache[$string] = [$namespace, $local_name];
            } else {
                $cache[$string] = ['', $string];
            }
        }
        return $cache[$string];
    }
}