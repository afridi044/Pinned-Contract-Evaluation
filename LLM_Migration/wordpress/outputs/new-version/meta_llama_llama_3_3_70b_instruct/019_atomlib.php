<?php
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
 * Structure that store common Atom Feed Properties
 *
 * @package AtomLib
 */
class AtomFeed {
    /**
     * Stores Links
     * @var array
     * @access public
     */
    public array $links = [];
    /**
     * Stores Categories
     * @var array
     * @access public
     */
    public array $categories = [];
    /**
     * Stores Entries
     *
     * @var array
     * @access public
     */
    public array $entries = [];
}

/**
 * Structure that store Atom Entry Properties
 *
 * @package AtomLib
 */
class AtomEntry {
    /**
     * Stores Links
     * @var array
     * @access public
     */
    public array $links = [];
    /**
     * Stores Categories
     * @var array
     * @access public
     */
    public array $categories = [];
}

/**
 * AtomLib Atom Parser API
 *
 * @package AtomLib
 */
class AtomParser {

    private const NS = 'http://www.w3.org/2005/Atom';
    private const ATOM_CONTENT_ELEMENTS = ['content', 'summary', 'title', 'subtitle', 'rights'];
    private const ATOM_SIMPLE_ELEMENTS = ['id', 'updated', 'published', 'draft'];

    private bool $debug = false;

    private int $depth = 0;
    private int $indent = 2;
    private array $in_content = [];
    private array $ns_contexts = [];
    private array $ns_decls = [];
    private array $content_ns_decls = [];
    private array $content_ns_contexts = [];
    private bool $is_xhtml = false;
    private bool $is_html = false;
    private bool $is_text = true;
    private bool $skipped_div = false;

    private string $FILE = "php://input";

    private AtomFeed $feed;
    private ?AtomEntry $current;

    public function __construct() {
        $this->feed = new AtomFeed();
        $this->current = null;
        $this->map_attrs_func = fn ($k, $v) => "$k=\"$v\"";
        $this->map_xmlns_func = fn ($p, $n) => "xmlns" . (!empty($n[0]) ? ":{$n[0]}" : "") . "=\"{$n[1]}\"";
    }

    private function _p(string $msg): void {
        if ($this->debug) {
            echo str_repeat(" ", $this->depth * $this->indent) . $msg . "\n";
        }
    }

    private function error_handler(int $log_level, string $log_text, string $error_file, int $error_line): void {
        $this->error = $log_text;
    }

    public function parse(): bool {
        set_error_handler([$this, 'error_handler']);

        $this->ns_contexts[] = [];

        $parser = xml_parser_create_ns();
        xml_set_object($parser, $this);
        xml_set_element_handler($parser, [$this, 'start_element'], [$this, 'end_element']);
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 0);
        xml_set_character_data_handler($parser, [$this, 'cdata']);
        xml_set_default_handler($parser, [$this, '_default']);
        xml_set_start_namespace_decl_handler($parser, [$this, 'start_ns']);
        xml_set_end_namespace_decl_handler($parser, [$this, 'end_ns']);

        $this->content = '';

        $ret = true;

        $fp = fopen($this->FILE, "r");
        while ($data = fread($fp, 4096)) {
            if ($this->debug) {
                $this->content .= $data;
            }

            if (!xml_parse($parser, $data, feof($fp))) {
                trigger_error(sprintf(__('XML error: %s at line %d'), xml_error_string(xml_get_error_code($parser)), xml_get_current_line_number($parser)));
                $ret = false;
                break;
            }
        }
        fclose($fp);

        xml_parser_free($parser);

        restore_error_handler();

        return $ret;
    }

    public function start_element($parser, string $name, array $attrs): void {
        $tag = explode(":", $name);
        $tag = end($tag);

        switch ($name) {
            case self::NS . ':feed':
                $this->current = $this->feed;
                break;
            case self::NS . ':entry':
                $this->current = new AtomEntry();
                break;
        };

        $this->_p("start_element('$name')");
        #$this->_p(print_r($this->ns_contexts, true));
        #$this->_p('current(' . $this->current . ')');

        $this->ns_contexts[] = $this->ns_decls;

        $this->depth++;

        if (!empty($this->in_content)) {

            $this->content_ns_decls = [];

            if ($this->is_html || $this->is_text) {
                trigger_error("Invalid content in element found. Content must not be of type text or html if it contains markup.");
            }

            $attrs_prefix = [];

            // resolve prefixes for attributes
            foreach ($attrs as $key => $value) {
                $with_prefix = $this->ns_to_prefix($key, true);
                $attrs_prefix[$with_prefix[1]] = $this->xml_escape($value);
            }

            $attrs_str = implode(' ', array_map($this->map_attrs_func, array_keys($attrs_prefix), array_values($attrs_prefix)));
            if (strlen($attrs_str) > 0) {
                $attrs_str = " " . $attrs_str;
            }

            $with_prefix = $this->ns_to_prefix($name);

            if (!$this->is_declared_content_ns($with_prefix[0])) {
                $this->content_ns_decls[] = $with_prefix[0];
            }

            $xmlns_str = '';
            if (count($this->content_ns_decls) > 0) {
                $this->content_ns_contexts[] = $this->content_ns_decls;
                $xmlns_str .= implode(' ', array_map($this->map_xmlns_func, array_keys($this->content_ns_contexts[0]), array_values($this->content_ns_contexts[0])));
                if (strlen($xmlns_str) > 0) {
                    $xmlns_str = " " . $xmlns_str;
                }
            }

            $this->in_content[] = [$tag, $this->depth, "<" . $with_prefix[1] . $xmlns_str . $attrs_str . ">"];

        } else if (in_array($tag, self::ATOM_CONTENT_ELEMENTS) || in_array($tag, self::ATOM_SIMPLE_ELEMENTS)) {
            $this->in_content = [];
            $this->is_xhtml = $attrs['type'] == 'xhtml';
            $this->is_html = $attrs['type'] == 'html' || $attrs['type'] == 'text/html';
            $this->is_text = !in_array('type', array_keys($attrs)) || $attrs['type'] == 'text';
            $type = $this->is_xhtml ? 'XHTML' : ($this->is_html ? 'HTML' : ($this->is_text ? 'TEXT' : $attrs['type']));

            if (in_array('src', array_keys($attrs))) {
                $this->current->$tag = $attrs;
            } else {
                $this->in_content[] = [$tag, $this->depth, $type];
            }
        } else if ($tag == 'link') {
            $this->current->links[] = $attrs;
        } else if ($tag == 'category') {
            $this->current->categories[] = $attrs;
        }

        $this->ns_decls = [];
    }

    public function end_element($parser, string $name): void {
        $tag = explode(":", $name);
        $tag = end($tag);

        $ccount = count($this->in_content);

        # if we are *in* content, then let's proceed to serialize it
        if (!empty($this->in_content)) {
            # if we are ending the original content element
            # then let's finalize the content
            if ($this->in_content[0][0] == $tag &&
                $this->in_content[0][1] == $this->depth) {
                $origtype = $this->in_content[0][2];
                array_shift($this->in_content);
                $newcontent = [];
                foreach ($this->in_content as $c) {
                    if (count($c) == 3) {
                        $newcontent[] = $c[2];
                    } else {
                        if ($this->is_xhtml || $this->is_text) {
                            $newcontent[] = $this->xml_escape($c);
                        } else {
                            $newcontent[] = $c;
                        }
                    }
                }
                if (in_array($tag, self::ATOM_CONTENT_ELEMENTS)) {
                    $this->current->$tag = [$origtype, implode('', $newcontent)];
                } else {
                    $this->current->$tag = implode('', $newcontent);
                }
                $this->in_content = [];
            } else if ($this->in_content[$ccount - 1][0] == $tag &&
                $this->in_content[$ccount - 1][1] == $this->depth) {
                $this->in_content[$ccount - 1][2] = substr($this->in_content[$ccount - 1][2], 0, -1) . "/>";
            } else {
                # else, just finalize the current element's content
                $endtag = $this->ns_to_prefix($name);
                $this->in_content[] = [$tag, $this->depth, "</$endtag[1]>"];
            }
        }

        array_shift($this->ns_contexts);

        $this->depth--;

        if ($name == (self::NS . ':entry')) {
            $this->feed->entries[] = $this->current;
            $this->current = null;
        }

        $this->_p("end_element('$name')");
    }

    public function start_ns($parser, string $prefix, string $uri): void {
        $this->_p("starting: " . $prefix . ":" . $uri);
        $this->ns_decls[] = [$prefix, $uri];
    }

    public function end_ns($parser, string $prefix): void {
        $this->_p("ending: #" . $prefix . "#");
    }

    public function cdata($parser, string $data): void {
        $this->_p("data: #" . str_replace(["\n"], ["\\n"], trim($data)) . "#");
        if (!empty($this->in_content)) {
            $this->in_content[] = $data;
        }
    }

    public function _default($parser, string $data): void {
        # when does this gets called?
    }

    private function ns_to_prefix(string $qname, bool $attr = false): array {
        # split 'http://www.w3.org/1999/xhtml:div' into ('http','//www.w3.org/1999/xhtml','div')
        $components = explode(":", $qname);

        # grab the last one (e.g 'div')
        $name = end($components);

        if (!empty($components)) {
            # re-join back the namespace component
            $ns = implode(":", array_slice($components, 0, -1));
            foreach ($this->ns_contexts as $context) {
                foreach ($context as $mapping) {
                    if ($mapping[1] == $ns && strlen($mapping[0]) > 0) {
                        return [$mapping, "$mapping[0]:$name"];
                    }
                }
            }
        }

        if ($attr) {
            return [null, $name];
        } else {
            foreach ($this->ns_contexts as $context) {
                foreach ($context as $mapping) {
                    if (strlen($mapping[0]) == 0) {
                        return [$mapping, $name];
                    }
                }
            }
        }
    }

    private function is_declared_content_ns(string $new_mapping): bool {
        foreach ($this->content_ns_contexts as $context) {
            foreach ($context as $mapping) {
                if ($new_mapping == $mapping) {
                    return true;
                }
            }
        }
        return false;
    }

    private function xml_escape(string $string): string {
        return str_replace(["&", "\"", "'", "<", ">"], ["&amp;", "&quot;", "&apos;", "&lt;", "&gt;"], $string);
    }
}