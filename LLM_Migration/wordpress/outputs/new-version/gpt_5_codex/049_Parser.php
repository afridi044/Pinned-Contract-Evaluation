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
 * 	* Redistributions in binary form must reproduce the above copyright notice, this list of
 * 	  conditions and the following disclaimer in the documentation and/or other materials
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
	public ?int $error_code = null;
	public ?string $error_string = null;
	public ?int $current_line = null;
	public ?int $current_column = null;
	public ?int $current_byte = null;
	public string $separator = ' ';
	public array $namespace = [''];
	public array $element = [''];
	public array $xml_base = [''];
	public array $xml_base_explicit = [false];
	public array $xml_lang = [''];
	public array $data = [];
	public array $datas = [[]];
	public int $current_xhtml_construct = -1;
	public ?string $encoding = null;
	protected ?SimplePie_Registry $registry = null;

	public function set_registry(SimplePie_Registry $registry): void
	{
		$this->registry = $registry;
	}

	public function parse(string &$data, string $encoding): bool
	{
		$this->encoding = strtoupper($encoding) === 'US-ASCII' ? 'UTF-8' : $encoding;

		if (str_starts_with($data, "\x00\x00\xFE\xFF"))
		{
			$data = substr($data, 4);
		}
		elseif (str_starts_with($data, "\xFF\xFE\x00\x00"))
		{
			$data = substr($data, 4);
		}
		elseif (str_starts_with($data, "\xFE\xFF"))
		{
			$data = substr($data, 2);
		}
		elseif (str_starts_with($data, "\xFF\xFE"))
		{
			$data = substr($data, 2);
		}
		elseif (str_starts_with($data, "\xEF\xBB\xBF"))
		{
			$data = substr($data, 3);
		}

		if (str_starts_with($data, '<?xml'))
		{
			$after = substr($data, 5, 1);
			if (strspn($after, "\x09\x0A\x0D\x20") === 1 && ($pos = strpos($data, '?>')) !== false)
			{
				$declaration = $this->registry?->create('XML_Declaration_Parser', [substr($data, 5, $pos - 5)]);
				if ($declaration !== null && $declaration->parse())
				{
					$data = substr($data, $pos + 2);
					$data = sprintf(
						'<?xml version="%s" encoding="%s" standalone="%s"?>%s',
						$declaration->version,
						$encoding,
						$declaration->standalone ? 'yes' : 'no',
						$data
					);
				}
				else
				{
					$this->error_string = 'SimplePie bug! Please report this!';
					return false;
				}
			}
		}

		$return = true;

		static ?bool $xml_is_sane = null;
		if ($xml_is_sane === null)
		{
			$parser_check = xml_parser_create();
			$values = [];
			xml_parse_into_struct($parser_check, '<foo>&amp;</foo>', $values);
			xml_parser_free($parser_check);
			$xml_is_sane = isset($values[0]['value']);
		}

		if ($xml_is_sane)
		{
			$xml = xml_parser_create_ns($this->encoding ?? 'UTF-8', $this->separator);
			xml_parser_set_option($xml, XML_OPTION_SKIP_WHITE, 1);
			xml_parser_set_option($xml, XML_OPTION_CASE_FOLDING, 0);
			xml_set_object($xml, $this);
			xml_set_character_data_handler($xml, [ $this, 'cdata' ]);
			xml_set_element_handler($xml, [ $this, 'tag_open' ], [ $this, 'tag_close' ]);

			if (!xml_parse($xml, $data, true))
			{
				$this->error_code = xml_get_error_code($xml);
				$this->error_string = xml_error_string($this->error_code);
				$return = false;
			}
			$this->current_line = xml_get_current_line_number($xml);
			$this->current_column = xml_get_current_column_number($xml);
			$this->current_byte = xml_get_current_byte_index($xml);
			xml_parser_free($xml);
			return $return;
		}

		libxml_clear_errors();
		$xml = new XMLReader();
		$xml->xml($data);
		while (@$xml->read())
		{
			switch ($xml->nodeType)
			{
				case XMLReader::END_ELEMENT:
					$tagName = $xml->namespaceURI !== ''
						? $xml->namespaceURI . $this->separator . $xml->localName
						: $xml->localName;
					$this->tag_close(null, $tagName);
					break;

				case XMLReader::ELEMENT:
					$empty = $xml->isEmptyElement;
					$tagName = $xml->namespaceURI !== ''
						? $xml->namespaceURI . $this->separator . $xml->localName
						: $xml->localName;
					$attributes = [];
					while ($xml->moveToNextAttribute())
					{
						$attrName = $xml->namespaceURI !== ''
							? $xml->namespaceURI . $this->separator . $xml->localName
							: $xml->localName;
						$attributes[$attrName] = $xml->value;
					}
					$this->tag_open(null, $tagName, $attributes);
					if ($empty)
					{
						$this->tag_close(null, $tagName);
					}
					break;

				case XMLReader::TEXT:
				case XMLReader::CDATA:
					$this->cdata(null, $xml->value);
					break;
			}
		}
		if ($error = libxml_get_last_error())
		{
			$this->error_code = $error->code;
			$this->error_string = $error->message;
			$this->current_line = $error->line;
			$this->current_column = $error->column;
			return false;
		}

		return true;
	}

	public function get_error_code(): ?int
	{
		return $this->error_code;
	}

	public function get_error_string(): ?string
	{
		return $this->error_string;
	}

	public function get_current_line(): ?int
	{
		return $this->current_line;
	}

	public function get_current_column(): ?int
	{
		return $this->current_column;
	}

	public function get_current_byte(): ?int
	{
		return $this->current_byte;
	}

	public function get_data(): array
	{
		return $this->data;
	}

	public function tag_open(mixed $parser, string $tag, array $attributes): void
	{
		[$namespace, $element] = $this->split_ns($tag);
		$this->namespace[] = $namespace;
		$this->element[] = $element;

		$attribs = [];
		foreach ($attributes as $name => $value)
		{
			[$attrib_namespace, $attribute] = $this->split_ns($name);
			$attribs[$attrib_namespace][$attribute] = $value;
		}

		if (isset($attribs[SIMPLEPIE_NAMESPACE_XML]['base']))
		{
			$base = $this->registry?->call('Misc', 'absolutize_url', [$attribs[SIMPLEPIE_NAMESPACE_XML]['base'], end($this->xml_base)]);
			if ($base !== false)
			{
				$this->xml_base[] = $base;
				$this->xml_base_explicit[] = true;
			}
		}
		else
		{
			$this->xml_base[] = end($this->xml_base);
			$this->xml_base_explicit[] = end($this->xml_base_explicit);
		}

		if (isset($attribs[SIMPLEPIE_NAMESPACE_XML]['lang']))
		{
			$this->xml_lang[] = $attribs[SIMPLEPIE_NAMESPACE_XML]['lang'];
		}
		else
		{
			$this->xml_lang[] = end($this->xml_lang);
		}

		if ($this->current_xhtml_construct >= 0)
		{
			$this->current_xhtml_construct++;
			$currentNamespace = end($this->namespace);
			if ($currentNamespace === SIMPLEPIE_NAMESPACE_XHTML)
			{
				$this->data['data'] .= '<' . end($this->element);
				if (isset($attribs['']))
				{
					foreach ($attribs[''] as $name => $value)
					{
						$this->data['data'] .= ' ' . $name . '="' . htmlspecialchars($value, ENT_COMPAT, $this->encoding ?? 'UTF-8') . '"';
					}
				}
				$this->data['data'] .= '>';
			}
			return;
		}

		$this->datas[] =& $this->data;
		$currentNamespace = end($this->namespace);
		$currentElement = end($this->element);
		$this->data =& $this->data['child'][$currentNamespace][$currentElement][];
		$this->data = [
			'data' => '',
			'attribs' => $attribs,
			'xml_base' => end($this->xml_base),
			'xml_base_explicit' => end($this->xml_base_explicit),
			'xml_lang' => end($this->xml_lang),
		];

		$currentNamespace = end($this->namespace);
		$currentElement = end($this->element);

		if (
			(
				$currentNamespace === SIMPLEPIE_NAMESPACE_ATOM_03
				&& in_array($currentElement, ['title', 'tagline', 'copyright', 'info', 'summary', 'content'], true)
				&& isset($attribs['']['mode'])
				&& $attribs['']['mode'] === 'xml'
			)
			|| (
				$currentNamespace === SIMPLEPIE_NAMESPACE_ATOM_10
				&& in_array($currentElement, ['rights', 'subtitle', 'summary', 'info', 'title', 'content'], true)
				&& isset($attribs['']['type'])
				&& $attribs['']['type'] === 'xhtml'
			)
			|| (
				$currentNamespace === SIMPLEPIE_NAMESPACE_RSS_20
				&& in_array($currentElement, ['title'], true)
			)
			|| (
				$currentNamespace === SIMPLEPIE_NAMESPACE_RSS_090
				&& in_array($currentElement, ['title'], true)
			)
			|| (
				$currentNamespace === SIMPLEPIE_NAMESPACE_RSS_10
				&& in_array($currentElement, ['title'], true)
			)
		)
		{
			$this->current_xhtml_construct = 0;
		}
	}

	public function cdata(mixed $parser, string $cdata): void
	{
		if ($this->current_xhtml_construct >= 0)
		{
			$this->data['data'] .= htmlspecialchars($cdata, ENT_QUOTES, $this->encoding ?? 'UTF-8');
		}
		else
		{
			$this->data['data'] .= $cdata;
		}
	}

	public function tag_close(mixed $parser, string $tag): void
	{
		if ($this->current_xhtml_construct >= 0)
		{
			$this->current_xhtml_construct--;
			$currentNamespace = end($this->namespace);
			$currentElement = end($this->element);
			if (
				$currentNamespace === SIMPLEPIE_NAMESPACE_XHTML
				&& !in_array($currentElement, ['area', 'base', 'basefont', 'br', 'col', 'frame', 'hr', 'img', 'input', 'isindex', 'link', 'meta', 'param'], true)
			)
			{
				$this->data['data'] .= '</' . $currentElement . '>';
			}
		}
		if ($this->current_xhtml_construct === -1 && !empty($this->datas))
		{
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
		static array $cache = [];
		if (!isset($cache[$string]))
		{
			if (($pos = strpos($string, $this->separator)) !== false)
			{
				static ?int $separator_length = null;
				if ($separator_length === null)
				{
					$separator_length = strlen($this->separator);
				}
				$namespace = substr($string, 0, $pos);
				$local_name = substr($string, $pos + $separator_length);
				if (strtolower($namespace) === SIMPLEPIE_NAMESPACE_ITUNES)
				{
					$namespace = SIMPLEPIE_NAMESPACE_ITUNES;
				}

				if (
					in_array(
						$namespace,
						[
							SIMPLEPIE_NAMESPACE_MEDIARSS_WRONG,
							SIMPLEPIE_NAMESPACE_MEDIARSS_WRONG2,
							SIMPLEPIE_NAMESPACE_MEDIARSS_WRONG3,
							SIMPLEPIE_NAMESPACE_MEDIARSS_WRONG4,
							SIMPLEPIE_NAMESPACE_MEDIARSS_WRONG5,
						],
						true
					)
				)
				{
					$namespace = SIMPLEPIE_NAMESPACE_MEDIARSS;
				}
				$cache[$string] = [$namespace, $local_name];
			}
			else
			{
				$cache[$string] = ['', $string];
			}
		}
		return $cache[$string];
	}
}
?>