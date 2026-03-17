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
	public ?int $errorCode = null;
	public ?string $errorString = null;
	public ?int $currentLine = null;
	public ?int $currentColumn = null;
	public ?int $currentByte = null;
	public string $separator = ' ';
	/** @var array<string> */
	public array $namespace = [''];
	/** @var array<string> */
	public array $element = [''];
	/** @var array<string> */
	public array $xmlBase = [''];
	/** @var array<bool> */
	public array $xmlBaseExplicit = [false];
	/** @var array<string> */
	public array $xmlLang = [''];
	/** @var array<mixed> */
	public array $data = [];
	/** @var array<array<mixed>> */
	public array $datas = [[]];
	public int $currentXhtmlConstruct = -1;
	public string $encoding;
	protected SimplePie_Registry $registry;

	public function setRegistry(SimplePie_Registry $registry): void
	{
		$this->registry = $registry;
	}

	public function parse(string &$data, string $encoding): bool
	{
		// Use UTF-8 if we get passed US-ASCII, as every US-ASCII character is a UTF-8 character
		if (strtoupper($encoding) === 'US-ASCII')
		{
			$this->encoding = 'UTF-8';
		}
		else
		{
			$this->encoding = $encoding;
		}

		// Strip BOM:
		// UTF-32 Big Endian BOM
		if (str_starts_with($data, "\x00\x00\xFE\xFF"))
		{
			$data = substr($data, 4);
		}
		// UTF-32 Little Endian BOM
		elseif (str_starts_with($data, "\xFF\xFE\x00\x00"))
		{
			$data = substr($data, 4);
		}
		// UTF-16 Big Endian BOM
		elseif (str_starts_with($data, "\xFE\xFF"))
		{
			$data = substr($data, 2);
		}
		// UTF-16 Little Endian BOM
		elseif (str_starts_with($data, "\xFF\xFE"))
		{
			$data = substr($data, 2);
		}
		// UTF-8 BOM
		elseif (str_starts_with($data, "\xEF\xBB\xBF"))
		{
			$data = substr($data, 3);
		}

		if (str_starts_with($data, '<?xml') && strspn(substr($data, 5, 1), "\x09\x0A\x0D\x20") && ($pos = strpos($data, '?>')) !== false)
		{
			$declaration = $this->registry->create('XML_Declaration_Parser', [substr($data, 5, $pos - 5)]);
			if ($declaration->parse())
			{
				$data = substr($data, $pos + 2);
				$data = '<?xml version="' . $declaration->version . '" encoding="' . $encoding . '" standalone="' . (($declaration->standalone) ? 'yes' : 'no') . '"?>' . $data;
			}
			else
			{
				$this->errorString = 'SimplePie bug! Please report this!';
				return false;
			}
		}

		$return = true;

		static ?bool $xmlIsSane = null;
		if ($xmlIsSane === null)
		{
			$parser_check = xml_parser_create();
			xml_parse_into_struct($parser_check, '<foo>&amp;</foo>', $values);
			xml_parser_free($parser_check);
			$xmlIsSane = isset($values[0]['value']);
		}

		// Create the parser
		if ($xmlIsSane)
		{
			$xml = xml_parser_create_ns($this->encoding, $this->separator);
			xml_parser_set_option($xml, XML_OPTION_SKIP_WHITE, 1);
			xml_parser_set_option($xml, XML_OPTION_CASE_FOLDING, 0);
			xml_set_object($xml, $this);
			xml_set_character_data_handler($xml, [$this, 'cdata']);
			xml_set_element_handler($xml, [$this, 'tagOpen'], [$this, 'tagClose']);

			// Parse!
			if (!xml_parse($xml, $data, true))
			{
				$this->errorCode = xml_get_error_code($xml);
				$this->errorString = xml_error_string($this->errorCode);
				$return = false;
			}
			$this->currentLine = xml_get_current_line_number($xml);
			$this->currentColumn = xml_get_current_column_number($xml);
			$this->currentByte = xml_get_current_byte_index($xml);
			xml_parser_free($xml);
			return $return;
		}
		else
		{
			libxml_clear_errors();
			$xml = new XMLReader();
			$xml->xml($data);
			while (@$xml->read())
			{
				switch ($xml->nodeType)
				{
					case XMLReader::END_ELEMENT:
						$tagName = $xml->namespaceURI !== '' ? $xml->namespaceURI . $this->separator . $xml->localName : $xml->localName;
						$this->tagClose(null, $tagName);
						break;
					case XMLReader::ELEMENT:
						$empty = $xml->isEmptyElement;
						$tagName = $xml->namespaceURI !== '' ? $xml->namespaceURI . $this->separator . $xml->localName : $xml->localName;
						$attributes = [];
						while ($xml->moveToNextAttribute())
						{
							$attrName = $xml->namespaceURI !== '' ? $xml->namespaceURI . $this->separator . $xml->localName : $xml->localName;
							$attributes[$attrName] = $xml->value;
						}
						$this->tagOpen(null, $tagName, $attributes);
						if ($empty)
						{
							$this->tagClose(null, $tagName);
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
				$this->errorCode = $error->code;
				$this->errorString = $error->message;
				$this->currentLine = $error->line;
				$this->currentColumn = $error->column;
				return false;
			}
			else
			{
				return true;
			}
		}
	}

	public function getErrorCode(): ?int
	{
		return $this->errorCode;
	}

	public function getErrorString(): ?string
	{
		return $this->errorString;
	}

	public function getCurrentLine(): ?int
	{
		return $this->currentLine;
	}

	public function getCurrentColumn(): ?int
	{
		return $this->currentColumn;
	}

	public function getCurrentByte(): ?int
	{
		return $this->currentByte;
	}

	/**
	 * @return array<mixed>
	 */
	public function getData(): array
	{
		return $this->data;
	}

	/**
	 * @param object|null $parser
	 * @param string $tag
	 * @param array<string, string> $attributes
	 */
	public function tagOpen(?object $parser, string $tag, array $attributes): void
	{
		[$this->namespace[], $this->element[]] = $this->splitNs($tag);

		$attribs = [];
		foreach ($attributes as $name => $value)
		{
			[$attribNamespace, $attribute] = $this->splitNs($name);
			$attribs[$attribNamespace][$attribute] = $value;
		}

		if (isset($attribs[SIMPLEPIE_NAMESPACE_XML]['base']))
		{
			$base = $this->registry->call('Misc', 'absolutize_url', [$attribs[SIMPLEPIE_NAMESPACE_XML]['base'], end($this->xmlBase)]);
			if ($base !== false)
			{
				$this->xmlBase[] = $base;
				$this->xmlBaseExplicit[] = true;
			}
		}
		else
		{
			$this->xmlBase[] = end($this->xmlBase);
			$this->xmlBaseExplicit[] = end($this->xmlBaseExplicit);
		}

		if (isset($attribs[SIMPLEPIE_NAMESPACE_XML]['lang']))
		{
			$this->xmlLang[] = $attribs[SIMPLEPIE_NAMESPACE_XML]['lang'];
		}
		else
		{
			$this->xmlLang[] = end($this->xmlLang);
		}

		if ($this->currentXhtmlConstruct >= 0)
		{
			$this->currentXhtmlConstruct++;
			if (end($this->namespace) === SIMPLEPIE_NAMESPACE_XHTML)
			{
				$this->data['data'] .= '<' . end($this->element);
				if (isset($attribs['']))
				{
					foreach ($attribs[''] as $name => $value)
					{
						$this->data['data'] .= ' ' . $name . '="' . htmlspecialchars($value, ENT_COMPAT, $this->encoding) . '"';
					}
				}
				$this->data['data'] .= '>';
			}
		}
		else
		{
			$this->datas[] =& $this->data;
			$this->data =& $this->data['child'][end($this->namespace)][end($this->element)][];
			$this->data = [
				'data' => '',
				'attribs' => $attribs,
				'xml_base' => end($this->xmlBase),
				'xml_base_explicit' => end($this->xmlBaseExplicit),
				'xml_lang' => end($this->xmlLang)
			];
			if (
				(end($this->namespace) === SIMPLEPIE_NAMESPACE_ATOM_03 && in_array(end($this->element), ['title', 'tagline', 'copyright', 'info', 'summary', 'content']) && ($attribs['']['mode'] ?? null) === 'xml')
				|| (end($this->namespace) === SIMPLEPIE_NAMESPACE_ATOM_10 && in_array(end($this->element), ['rights', 'subtitle', 'summary', 'info', 'title', 'content']) && ($attribs['']['type'] ?? null) === 'xhtml')
				|| (end($this->namespace) === SIMPLEPIE_NAMESPACE_RSS_20 && in_array(end($this->element), ['title']))
				|| (end($this->namespace) === SIMPLEPIE_NAMESPACE_RSS_090 && in_array(end($this->element), ['title']))
				|| (end($this->namespace) === SIMPLEPIE_NAMESPACE_RSS_10 && in_array(end($this->element), ['title']))
			)
			{
				$this->currentXhtmlConstruct = 0;
			}
		}
	}

	/**
	 * @param object|null $parser
	 * @param string $cdata
	 */
	public function cdata(?object $parser, string $cdata): void
	{
		if ($this->currentXhtmlConstruct >= 0)
		{
			$this->data['data'] .= htmlspecialchars($cdata, ENT_QUOTES, $this->encoding);
		}
		else
		{
			$this->data['data'] .= $cdata;
		}
	}

	/**
	 * @param object|null $parser
	 * @param string $tag
	 */
	public function tagClose(?object $parser, string $tag): void
	{
		if ($this->currentXhtmlConstruct >= 0)
		{
			$this->currentXhtmlConstruct--;
			if (end($this->namespace) === SIMPLEPIE_NAMESPACE_XHTML && !in_array(end($this->element), ['area', 'base', 'basefont', 'br', 'col', 'frame', 'hr', 'img', 'input', 'isindex', 'link', 'meta', 'param']))
			{
				$this->data['data'] .= '</' . end($this->element) . '>';
			}
		}
		if ($this->currentXhtmlConstruct === -1)
		{
			$this->data =& $this->datas[count($this->datas) - 1];
			array_pop($this->datas);
		}

		array_pop($this->element);
		array_pop($this->namespace);
		array_pop($this->xmlBase);
		array_pop($this->xmlBaseExplicit);
		array_pop($this->xmlLang);
	}

	/**
	 * @param string $string
	 * @return array{0: string, 1: string}
	 */
	public function splitNs(string $string): array
	{
		/** @var array<string, array{0: string, 1: string}> $cache */
		static $cache = [];
		if (!isset($cache[$string]))
		{
			if (($pos = strpos($string, $this->separator)) !== false)
			{
				static ?int $separatorLength = null;
				if ($separatorLength === null)
				{
					$separatorLength = strlen($this->separator);
				}
				$namespace = substr($string, 0, $pos);
				$localName = substr($string, $pos + $separatorLength);
				if (strtolower($namespace) === SIMPLEPIE_NAMESPACE_ITUNES)
				{
					$namespace = SIMPLEPIE_NAMESPACE_ITUNES;
				}

				// Normalize the Media RSS namespaces
				if ($namespace === SIMPLEPIE_NAMESPACE_MEDIARSS_WRONG ||
					$namespace === SIMPLEPIE_NAMESPACE_MEDIARSS_WRONG2 ||
					$namespace === SIMPLEPIE_NAMESPACE_MEDIARSS_WRONG3 ||
					$namespace === SIMPLEPIE_NAMESPACE_MEDIARSS_WRONG4 ||
					$namespace === SIMPLEPIE_NAMESPACE_MEDIARSS_WRONG5 )
				{
					$namespace = SIMPLEPIE_NAMESPACE_MEDIARSS;
				}
				$cache[$string] = [$namespace, $localName];
			}
			else
			{
				$cache[$string] = ['', $string];
			}
		}
		return $cache[$string];
	}
}