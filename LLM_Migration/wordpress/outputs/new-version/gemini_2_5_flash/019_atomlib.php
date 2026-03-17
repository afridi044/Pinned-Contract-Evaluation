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
 * Structure that store common Atom Feed Properties
 *
 * @package AtomLib
 */
class AtomFeed {
	/**
	 * Stores Links
	 * @var array<array<string, string>>
	 */
    public array $links = [];
    /**
     * Stores Categories
     * @var array<array<string, string>>
     */
    public array $categories = [];
	/**
	 * Stores Entries
	 *
	 * @var array<AtomEntry>
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
	 * @var array<array<string, string>>
	 */
    public array $links = [];
    /**
     * Stores Categories
     * @var array<array<string, string>>
     */
    public array $categories = [];
    /**
     * Stores content. Can be an array (if 'src' attribute is present) or a string (content value).
     * @var array<string, string>|array<int, string>|string|null
     */
    public array|string|null $content = null;
    /**
     * Stores summary. Can be an array (if 'src' attribute is present) or a string (content value).
     * @var array<string, string>|array<int, string>|string|null
     */
    public array|string|null $summary = null;
    /**
     * Stores title. Can be an array (if 'src' attribute is present) or a string (content value).
     * @var array<string, string>|array<int, string>|string|null
     */
    public array|string|null $title = null;
    /**
     * Stores subtitle. Can be an array (if 'src' attribute is present) or a string (content value).
     * @var array<string, string>|array<int, string>|string|null
     */
    public array|string|null $subtitle = null;
    /**
     * Stores rights. Can be an array (if 'src' attribute is present) or a string (content value).
     * @var array<string, string>|array<int, string>|string|null
     */
    public array|string|null $rights = null;
    /**
     * Stores id.
     * @var string|null
     */
    public ?string $id = null;
    /**
     * Stores updated timestamp.
     * @var string|null
     */
    public ?string $updated = null;
    /**
     * Stores published timestamp.
     * @var string|null
     */
    public ?string $published = null;
    /**
     * Stores draft status.
     * @var string|null
     */
    public ?string $draft = null;
}

/**
 * AtomLib Atom Parser API
 *
 * @package AtomLib
 */
class AtomParser {

    public string $NS = 'http://www.w3.org/2005/Atom';
    public array $ATOM_CONTENT_ELEMENTS = ['content','summary','title','subtitle','rights'];
    public array $ATOM_SIMPLE_ELEMENTS = ['id','updated','published','draft'];

    public bool $debug = false;

    public int $depth = 0;
    public int $indent = 2;
    /**
     * Stores content elements being parsed. Can be null if not currently parsing content,
     * or an array of arrays/strings representing nested elements and character data.
     * @var array<array<int, string>|string>|null
     */
    public ?array $in_content = null;
    /**
     * @var array<array<array<string, string>>>
     */
    public array $ns_contexts = [];
    /**
     * @var array<array<string, string>>
     */
    public array $ns_decls = [];
    /**
     * @var array<array<string, string>>
     */
    public array $content_ns_decls = [];
    /**
     * @var array<array<array<string, string>>>
     */
    public array $content_ns_contexts = [];
    public bool $is_xhtml = false;
    public bool $is_html = false;
    public bool $is_text = true;
    public bool $skipped_div = false;

    public string $FILE = "php://input";

    public ?AtomFeed $feed = null;
    public AtomFeed|AtomEntry|null $current = null;

    private \Closure $map_attrs_func;
    private \Closure $map_xmlns_func;

    public ?string $error = null;

    public function __construct() {
        $this->feed = new AtomFeed();
        $this->current = null;
        // Replaced create_function with arrow functions (closures)
        $this->map_attrs_func = fn(string $k, string $v): string => "$k=\"$v\"";
        // $n is expected to be an array like [$prefix, $uri]
        $this->map_xmlns_func = fn(int $idx, array $n): string => (strlen($n[0]) > 0 ? "xmlns:{$n[0]}" : "xmlns") . "=\"{$n[1]}\"";
    }

    public function _p(string $msg): void {
        if($this->debug) {
            echo str_repeat(" ", $this->depth * $this->indent) . $msg ."\n";
        }
    }

    public function error_handler(int $log_level, string $log_text, string $error_file, int $error_line): void {
        $this->error = $log_text;
    }

    public function parse(): bool {

        // Removed `&` from `&$this` as it's not needed in modern PHP
        set_error_handler([$this, 'error_handler']);

        array_unshift($this->ns_contexts, []);

        $parser = xml_parser_create_ns();
        xml_set_object($parser, $this);
        xml_set_element_handler($parser, "start_element", "end_element");
        xml_parser_set_option($parser,XML_OPTION_CASE_FOLDING,0);
        xml_parser_set_option($parser,XML_OPTION_SKIP_WHITE,0);
        xml_set_character_data_handler($parser, "cdata");
        xml_set_default_handler($parser, "_default");
        xml_set_start_namespace_decl_handler($parser, "start_ns");
        xml_set_end_namespace_decl_handler($parser, "end_ns");

        // The original code had $this->content = ''; but $this->content is not a declared property.
        // Based on usage, it seems to be a debug buffer.
        $content_buffer = '';

        $ret = true;

        $fp = fopen($this->FILE, "r");
        if ($fp === false) {
            trigger_error("Failed to open file: " . $this->FILE, E_USER_WARNING);
            restore_error_handler();
            return false;
        }

        while (!feof($fp)) {
            $data = fread($fp, 4096);
            if ($data === false) {
                trigger_error("Failed to read from file: " . $this->FILE, E_USER_WARNING);
                $ret = false;
                break;
            }

            if($this->debug) $content_buffer .= $data;

            if(!xml_parse($parser, $data, feof($fp))) {
                // Removed `__()` call as it's an undefined function in this context
                trigger_error(sprintf('XML error: %s at line %d'."\n",
                    xml_error_string(xml_get_error_code($parser)),
                    xml_get_current_line_number($parser)), E_USER_WARNING);
                $ret = false;
                break;
            }
        }
        fclose($fp);

        xml_parser_free($parser);

        restore_error_handler();

        return $ret;
    }

    public function start_element(mixed $parser, string $name, array $attrs): void {

        // Replaced split() with explode()
        $tag_parts = explode(":", $name);
        $tag = array_pop($tag_parts);

        switch($name) {
            case $this->NS . ':feed':
                $this->current = $this->feed;
                break;
            case $this->NS . ':entry':
                $this->current = new AtomEntry();
                break;
        }

        $this->_p("start_element('$name')");

        array_unshift($this->ns_contexts, $this->ns_decls);

        $this->depth++;

        if($this->in_content !== null) { // Check if in_content is active (i.e., we are inside a content element)

            $this->content_ns_decls = [];

            // The original logic here was problematic:
            // if($this->is_html || $this->is_text) trigger_error(...)
            // This would trigger if content was html/text and contained markup, which is valid for HTML.
            // For functional equivalence, keeping the original logic.
            if($this->is_html || $this->is_text) {
                trigger_error("Invalid content in element found. Content must not be of type text or html if it contains markup.", E_USER_WARNING);
            }

            $attrs_prefix = [];

            // resolve prefixes for attributes
            foreach($attrs as $key => $value) {
                $with_prefix = $this->ns_to_prefix($key, true);
                if ($with_prefix !== null) { // Ensure ns_to_prefix returns a valid array
                    $attrs_prefix[$with_prefix[1]] = $this->xml_escape($value);
                }
            }

            $attrs_str = join(' ', array_map($this->map_attrs_func, array_keys($attrs_prefix), array_values($attrs_prefix)));
            if(strlen($attrs_str) > 0) {
                $attrs_str = " " . $attrs_str;
            }

            $with_prefix = $this->ns_to_prefix($name);
            if ($with_prefix === null) {
                // Handle case where ns_to_prefix returns null for the element name itself
                trigger_error("Could not resolve namespace for element: $name", E_USER_WARNING);
                $with_prefix = [null, $tag]; // Fallback to no prefix, just tag name
            }

            if(!$this->is_declared_content_ns($with_prefix[0])) {
                array_push($this->content_ns_decls, $with_prefix[0]);
            }

            $xmlns_str = '';
            if(count($this->content_ns_decls) > 0) {
                array_unshift($this->content_ns_contexts, $this->content_ns_decls);
                $xmlns_str .= join(' ', array_map($this->map_xmlns_func, array_keys($this->content_ns_contexts[0]), array_values($this->content_ns_contexts[0])));
                if(strlen($xmlns_str) > 0) {
                    $xmlns_str = " " . $xmlns_str;
                }
            }

            // $this->in_content is guaranteed to be an array here because it was set to []
            // when the content parsing started.
            array_push($this->in_content, [$tag, $this->depth, "<". $with_prefix[1] ."{$xmlns_str}{$attrs_str}" . ">"]);

        } else if(in_array($tag, $this->ATOM_CONTENT_ELEMENTS) || in_array($tag, $this->ATOM_SIMPLE_ELEMENTS)) {
            $this->in_content = []; // Initialize as an empty array to start capturing content
            $type_attr = $attrs['type'] ?? 'text'; // Default to 'text' if 'type' attribute is missing

            $this->is_xhtml = ($type_attr === 'xhtml');
            $this->is_html = ($type_attr === 'html' || $type_attr === 'text/html');
            $this->is_text = ($type_attr === 'text');

            $type_display = $this->is_xhtml ? 'XHTML' : ($this->is_html ? 'HTML' : ($this->is_text ? 'TEXT' : $type_attr));

            if(isset($attrs['src'])) { // Replaced in_array('src',array_keys($attrs)) with isset()
                if ($this->current instanceof AtomEntry) { // Ensure current is an AtomEntry
                    $this->current->$tag = $attrs;
                }
            } else {
                // $this->in_content is guaranteed to be an array here.
                array_push($this->in_content, [$tag,$this->depth, $type_display]);
            }
        } else if($tag === 'link') {
            if ($this->current instanceof AtomFeed || $this->current instanceof AtomEntry) {
                array_push($this->current->links, $attrs);
            }
        } else if($tag === 'category') {
            if ($this->current instanceof AtomFeed || $this->current instanceof AtomEntry) {
                array_push($this->current->categories, $attrs);
            }
        }

        $this->ns_decls = [];
    }

    public function end_element(mixed $parser, string $name): void {

        // Replaced split() with explode()
        $tag_parts = explode(":", $name);
        $tag = array_pop($tag_parts);

        $ccount = count($this->in_content ?? []); // Use null coalescing for count

        # if we are *in* content, then let's proceed to serialize it
        if($this->in_content !== null && !empty($this->in_content)) {
            # if we are ending the original content element
            # then let's finalize the content
            if($this->in_content[0][0] === $tag &&
                $this->in_content[0][1] === $this->depth) {
                $origtype = $this->in_content[0][2];
                array_shift($this->in_content);
                $newcontent = [];
                foreach($this->in_content as $c) {
                    // Check if $c is an array (representing a tag) or string (representing cdata)
                    if(is_array($c) && count($c) === 3) { // It's a nested element like [$tag, $depth, "<tag>"]
                        array_push($newcontent, $c[2]);
                    } else { // $c is a string (cdata)
                        if($this->is_xhtml || $this->is_text) {
                            array_push($newcontent, $this->xml_escape((string)$c));
                        } else {
                            array_push($newcontent, (string)$c);
                        }
                    }
                }
                if(in_array($tag, $this->ATOM_CONTENT_ELEMENTS)) {
                    if ($this->current instanceof AtomEntry) { // Ensure current is an AtomEntry
                        $this->current->$tag = [$origtype, join('',$newcontent)];
                    }
                } else { // This branch handles ATOM_SIMPLE_ELEMENTS
                    if ($this->current instanceof AtomEntry) { // Ensure current is an AtomEntry
                        $this->current->$tag = join('',$newcontent);
                    }
                }
                $this->in_content = null; // Reset in_content after processing
            } else if($ccount > 0 && $this->in_content[$ccount-1][0] === $tag &&
                $this->in_content[$ccount-1][1] === $this->depth) {
                // This condition seems to handle self-closing tags within content.
                // The original code `substr($this->in_content[$ccount-1][2],0,-1) . "/>"`
                // implies that the last element's third item is a string like "<tag>"
                // and it's being converted to "<tag/>".
                // This is a bit fragile if the string isn't exactly "<tag>".
                // Assuming it's always `<tag>`
                $this->in_content[$ccount-1][2] = substr($this->in_content[$ccount-1][2],0,-1) . "/>";
            } else {
                # else, just finalize the current element's content
                $endtag = $this->ns_to_prefix($name);
                if ($endtag !== null) { // Ensure ns_to_prefix returns a valid array
                    // $this->in_content is guaranteed to be an array here.
                    array_push($this->in_content, [$tag, $this->depth, "</{$endtag[1]}>"]);
                } else {
                    trigger_error("Could not resolve namespace for end element: $name", E_USER_WARNING);
                }
            }
        }

        array_shift($this->ns_contexts);

        $this->depth--;

        if($name === ($this->NS . ':entry')) {
            if ($this->feed instanceof AtomFeed && $this->current instanceof AtomEntry) {
                array_push($this->feed->entries, $this->current);
            }
            $this->current = null;
        }

        $this->_p("end_element('$name')");
    }

    public function start_ns(mixed $parser, string $prefix, string $uri): void {
        $this->_p("starting: " . $prefix . ":" . $uri);
        array_push($this->ns_decls, [$prefix,$uri]);
    }

    public function end_ns(mixed $parser, string $prefix): void {
        $this->_p("ending: #" . $prefix . "#");
    }

    public function cdata(mixed $parser, string $data): void {
        $this->_p("data: #" . str_replace(["\n"], ["\\n"], trim($data)) . "#");
        if($this->in_content !== null) { // Check if in_content is active
            // $this->in_content is guaranteed to be an array here.
            array_push($this->in_content, $data);
        }
    }

    public function _default(mixed $parser, string $data): void {
        # when does this gets called?
    }


    /**
     * Resolves a qualified name (qname) to its prefix and local name based on current namespace contexts.
     *
     * @param string $qname The qualified name (e.g., "http://www.w3.org/1999/xhtml:div" or "div").
     * @param bool $attr Whether the qname represents an attribute.
     * @return array{0: array<string, string>|null, 1: string}|null Returns an array where the first element is the
     *                                                                namespace mapping (prefix, uri) or null,
     *                                                                and the second is the prefixed local name.
     *                                                                Returns null if no matching prefix can be found.
     */
    public function ns_to_prefix(string $qname, bool $attr = false): ?array {
        // Replaced split() with explode()
        $components = explode(":", $qname);

        # grab the last one (e.g 'div')
        $name = array_pop($components);

        if(!empty($components)) {
            # re-join back the namespace component
            $ns = join(":",$components);
            foreach($this->ns_contexts as $context) {
                foreach($context as $mapping) {
                    if(isset($mapping[1]) && $mapping[1] === $ns && strlen($mapping[0]) > 0) {
                        return [$mapping, "{$mapping[0]}:$name"];
                    }
                }
            }
        }

        if($attr) {
            return [null, $name];
        } else {
            foreach($this->ns_contexts as $context) {
                foreach($context as $mapping) {
                    if(isset($mapping[0]) && strlen($mapping[0]) === 0) {
                        return [$mapping, $name];
                    }
                }
            }
        }
        return null; // Added explicit return null if no match, as per return type hint
    }

    /**
     * Checks if a given namespace mapping is already declared in the content namespace contexts.
     *
     * @param array<string, string>|null $new_mapping The new namespace mapping to check (e.g., ['prefix', 'uri']).
     * @return bool True if the mapping is declared, false otherwise.
     */
    public function is_declared_content_ns(?array $new_mapping): bool {
        if ($new_mapping === null) {
            return false; // A null mapping is not declared.
        }
        foreach($this->content_ns_contexts as $context) {
            foreach($context as $mapping) {
                if($new_mapping === $mapping) {
                    return true;
                }
            }
        }
        return false;
    }

    public function xml_escape(string $string): string {
        return str_replace(['&','"',"'",'<','>'],
            ['&amp;','&quot;','&apos;','&lt;','&gt;'],
            $string );
    }
}