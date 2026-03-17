<?php

declare(strict_types=1);

/**
 * Atom Syndication Format PHP Library
 *
 * @package AtomLib
 * @link http://code.google.com/p/phpatomlib/
 *
 * @author Elias Torres <elias@torrez.us>
 * @version 0.4
 * @since 2.3.0
 */

/**
 * Structure that stores common Atom Feed Properties.
 *
 * @package AtomLib
 */
class AtomFeed
{
    /**
     * Stores Links.
     * @var array<int, array<string, string>>
     */
    public array $links = [];

    /**
     * Stores Categories.
     * @var array<int, array<string, string>>
     */
    public array $categories = [];

    /**
     * Stores Entries.
     * @var array<int, AtomEntry>
     */
    public array $entries = [];

    // Properties to avoid dynamic property creation deprecation in PHP 8.2+
    public mixed $content = null;
    public mixed $summary = null;
    public mixed $title = null;
    public mixed $subtitle = null;
    public mixed $rights = null;
    public ?string $id = null;
    public ?string $updated = null;
    public ?string $published = null;
}

/**
 * Structure that stores Atom Entry Properties.
 *
 * @package AtomLib
 */
class AtomEntry
{
    /**
     * Stores Links.
     * @var array<int, array<string, string>>
     */
    public array $links = [];

    /**
     * Stores Categories.
     * @var array<int, array<string, string>>
     */
    public array $categories = [];

    // Properties to avoid dynamic property creation deprecation in PHP 8.2+
    public mixed $content = null;
    public mixed $summary = null;
    public mixed $title = null;
    public mixed $rights = null;
    public ?string $id = null;
    public ?string $updated = null;
    public ?string $published = null;
    public ?string $draft = null;
}

/**
 * AtomLib Atom Parser API.
 *
 * @package AtomLib
 */
class AtomParser
{
    private const NS = 'http://www.w3.org/2005/Atom';
    private const ATOM_CONTENT_ELEMENTS = ['content', 'summary', 'title', 'subtitle', 'rights'];
    private const ATOM_SIMPLE_ELEMENTS = ['id', 'updated', 'published', 'draft'];

    public bool $debug = false;
    public string $FILE = "php://input";
    public AtomFeed $feed;
    public AtomFeed|AtomEntry|null $current = null;

    private int $depth = 0;
    private int $indent = 2;
    private ?array $in_content = null;
    private array $ns_contexts = [];
    private array $ns_decls = [];
    private array $content_ns_decls = [];
    private array $content_ns_contexts = [];
    private bool $is_xhtml = false;
    private bool $is_html = false;
    private bool $is_text = true;
    private bool $skipped_div = false;

    private ?string $error = null;
    private string $content = '';

    private \Closure $map_attrs_func;
    private \Closure $map_xmlns_func;

    public function __construct()
    {
        $this->feed = new AtomFeed();

        $this->map_attrs_func = fn(string $k, string $v): string => "$k=\"$v\"";

        $this->map_xmlns_func = function (string $p, array $n): string {
            $xd = 'xmlns';
            if (strlen($n[0]) > 0) {
                $xd .= ":{$n[0]}";
            }
            return "{$xd}=\"{$n[1]}\"";
        };
    }

    private function _p(string $msg): void
    {
        if ($this->debug) {
            echo str_repeat(" ", $this->depth * $this->indent) . $msg . "\n";
        }
    }

    private function error_handler(int $log_level, string $log_text, ?string $error_file = null, ?int $error_line = null): bool
    {
        $this->error = $log_text;
        return true; // Suppress default PHP error handling
    }

    public function parse(): bool
    {
        set_error_handler([$this, 'error_handler']);

        array_unshift($this->ns_contexts, []);

        $parser = xml_parser_create_ns();
        if ($parser === false) {
            trigger_error(__('Failed to create XML parser')."\n");
            restore_error_handler();
            return false;
        }

        xml_set_element_handler($parser, [$this, "start_element"], [$this, "end_element"]);
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 0);
        xml_set_character_data_handler($parser, [$this, "cdata"]);
        xml_set_default_handler($parser, [$this, "_default"]);
        xml_set_start_namespace_decl_handler($parser, [$this, "start_ns"]);
        xml_set_end_namespace_decl_handler($parser, [$this, "end_ns"]);

        $this->content = '';
        $ret = true;

        $fp = @fopen($this->FILE, "r");
        if ($fp === false) {
            trigger_error(__('Failed to open input stream: ') . $this->FILE . "\n");
            xml_parser_free($parser);
            restore_error_handler();
            return false;
        }

        while (!feof($fp)) {
            $data = fread($fp, 4096);
            if ($this->debug) {
                $this->content .= $data;
            }

            if (!xml_parse($parser, $data, feof($fp))) {
                trigger_error(sprintf(__('XML error: %s at line %d')."\n",
                    xml_error_string(xml_get_error_code($parser)),
                    xml_get_current_line_number($parser)));
                $ret = false;
                break;
            }
        }
        fclose($fp);

        xml_parser_free($parser);
        restore_error_handler();

        return $ret;
    }

    /**
     * @internal
     */
    public function start_element(\XMLParser $parser, string $name, array $attrs): void
    {
        $tag_parts = explode(":", $name);
        $tag = end($tag_parts);

        switch ($name) {
            case self::NS . ':feed':
                $this->current = $this->feed;
                break;
            case self::NS . ':entry':
                $this->current = new AtomEntry();
                break;
        }

        $this->_p("start_element('$name')");

        array_unshift($this->ns_contexts, $this->ns_decls);
        $this->depth++;

        if ($this->in_content !== null) {
            $this->content_ns_decls = [];

            if ($this->is_html || $this->is_text) {
                trigger_error("Invalid content in element found. Content must not be of type text or html if it contains markup.");
            }

            $attrs_prefix = [];
            // resolve prefixes for attributes
            foreach ($attrs as $key => $value) {
                $with_prefix = $this->ns_to_prefix($key, true);
                if ($with_prefix !== null) {
                    $attrs_prefix[$with_prefix[1]] = $this->xml_escape((string)$value);
                }
            }

            $attrs_str = implode(' ', array_map($this->map_attrs_func, array_keys($attrs_prefix), array_values($attrs_prefix)));
            if (strlen($attrs_str) > 0) {
                $attrs_str = " " . $attrs_str;
            }

            $with_prefix = $this->ns_to_prefix($name);
            if ($with_prefix !== null) {
                if (!$this->is_declared_content_ns($with_prefix[0])) {
                    $this->content_ns_decls[] = $with_prefix[0];
                }

                $xmlns_str = '';
                if (count($this->content_ns_decls) > 0) {
                    array_unshift($this->content_ns_contexts, $this->content_ns_decls);
                    $xmlns_str .= implode(' ', array_map($this->map_xmlns_func, array_keys($this->content_ns_contexts[0]), array_values($this->content_ns_contexts[0])));
                    if (strlen($xmlns_str) > 0) {
                        $xmlns_str = " " . $xmlns_str;
                    }
                }
                $this->in_content[] = [$tag, $this->depth, "<" . $with_prefix[1] . "{$xmlns_str}{$attrs_str}" . ">"];
            }
        } elseif (in_array($tag, self::ATOM_CONTENT_ELEMENTS) || in_array($tag, self::ATOM_SIMPLE_ELEMENTS)) {
            $this->in_content = [];
            $type_attr = $attrs['type'] ?? null;
            $this->is_xhtml = $type_attr === 'xhtml';
            $this->is_html = $type_attr === 'html' || $type_attr === 'text/html';
            $this->is_text = $type_attr === null || $type_attr === 'text';
            $type = $this->is_xhtml ? 'XHTML' : ($this->is_html ? 'HTML' : ($this->is_text ? 'TEXT' : $type_attr));

            if (isset($attrs['src'])) {
                if ($this->current) {
                    $this->current->{$tag} = $attrs;
                }
            } else {
                $this->in_content[] = [$tag, $this->depth, $type];
            }
        } elseif ($tag === 'link') {
            if ($this->current) {
                $this->current->links[] = $attrs;
            }
        } elseif ($tag === 'category') {
            if ($this->current) {
                $this->current->categories[] = $attrs;
            }
        }

        $this->ns_decls = [];
    }

    /**
     * @internal
     */
    public function end_element(\XMLParser $parser, string $name): void
    {
        $tag_parts = explode(":", $name);
        $tag = end($tag_parts);

        if ($this->in_content !== null) {
            $ccount = count($this->in_content);
            if ($ccount > 0 && $this->in_content[0][0] === $tag && $this->in_content[0][1] === $this->depth) {
                $origtype = $this->in_content[0][2];
                array_shift($this->in_content);
                $newcontent = [];
                foreach ($this->in_content as $c) {
                    if (is_array($c) && count($c) === 3) {
                        $newcontent[] = $c[2];
                    } else {
                        $newcontent[] = ($this->is_xhtml || $this->is_text) ? $this->xml_escape((string)$c) : $c;
                    }
                }

                if ($this->current) {
                    if (in_array($tag, self::ATOM_CONTENT_ELEMENTS)) {
                        $this->current->{$tag} = [$origtype, implode('', $newcontent)];
                    } else {
                        $this->current->{$tag} = implode('', $newcontent);
                    }
                }
                $this->in_content = null;
            } elseif (
                isset($this->in_content[$ccount - 1]) &&
                $this->in_content[$ccount - 1][0] === $tag &&
                $this->in_content[$ccount - 1][1] === $this->depth
            ) {
                $this->in_content[$ccount - 1][2] = substr($this->in_content[$ccount - 1][2], 0, -1) . "/>";
            } else {
                $endtag = $this->ns_to_prefix($name);
                if ($endtag !== null) {
                    $this->in_content[] = [$tag, $this->depth, "</{$endtag[1]}>"];
                }
            }
        }

        array_shift($this->ns_contexts);
        $this->depth--;

        if ($name === (self::NS . ':entry')) {
            if ($this->current instanceof AtomEntry) {
                $this->feed->entries[] = $this->current;
            }
            $this->current = null;
        }

        $this->_p("end_element('$name')");
    }

    /**
     * @internal
     */
    public function start_ns(\XMLParser $parser, string $prefix, string $uri): void
    {
        $this->_p("starting: " . $prefix . ":" . $uri);
        $this->ns_decls[] = [$prefix, $uri];
    }

    /**
     * @internal
     */
    public function end_ns(\XMLParser $parser, string $prefix): void
    {
        $this->_p("ending: #" . $prefix . "#");
    }

    /**
     * @internal
     */
    public function cdata(\XMLParser $parser, string $data): void
    {
        $this->_p("data: #" . str_replace(["\n"], ["\\n"], trim($data)) . "#");
        if ($this->in_content !== null) {
            $this->in_content[] = $data;
        }
    }

    /**
     * @internal
     */
    public function _default(\XMLParser $parser, string $data): void
    {
        // Called for data that is not handled by other handlers (e.g., DOCTYPE).
    }

    private function ns_to_prefix(string $qname, bool $attr = false): ?array
    {
        $components = explode(":", $qname);
        $name = array_pop($components);

        if (!empty($components)) {
            $ns = implode(":", $components);
            foreach ($this->ns_contexts as $context) {
                foreach ($context as $mapping) {
                    if ($mapping[1] === $ns && strlen($mapping[0]) > 0) {
                        return [$mapping, "$mapping[0]:$name"];
                    }
                }
            }
        }

        if ($attr) {
            return [null, $name];
        }

        foreach ($this->ns_contexts as $context) {
            foreach ($context as $mapping) {
                if (strlen($mapping[0]) === 0) {
                    return [$mapping, $name];
                }
            }
        }
        return null;
    }

    private function is_declared_content_ns(array $new_mapping): bool
    {
        foreach ($this->content_ns_contexts as $context) {
            foreach ($context as $mapping) {
                if ($new_mapping === $mapping) {
                    return true;
                }
            }
        }
        return false;
    }

    private function xml_escape(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}