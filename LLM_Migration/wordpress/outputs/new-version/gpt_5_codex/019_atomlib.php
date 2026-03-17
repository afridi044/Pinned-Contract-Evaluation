<?php

declare(strict_types=1);

#[\AllowDynamicProperties]
class AtomFeed
{
    public array $links = [];
    public array $categories = [];
    public array $entries = [];
}

#[\AllowDynamicProperties]
class AtomEntry
{
    public array $links = [];
    public array $categories = [];
}

class AtomParser
{
    public string $NS = 'http://www.w3.org/2005/Atom';
    public array $ATOM_CONTENT_ELEMENTS = ['content', 'summary', 'title', 'subtitle', 'rights'];
    public array $ATOM_SIMPLE_ELEMENTS = ['id', 'updated', 'published', 'draft'];

    public bool $debug = false;

    public int $depth = 0;
    public int $indent = 2;
    public array $in_content = [];
    public array $ns_contexts = [];
    public array $ns_decls = [];
    public array $content_ns_decls = [];
    public array $content_ns_contexts = [];
    public bool $is_xhtml = false;
    public bool $is_html = false;
    public bool $is_text = true;
    public bool $skipped_div = false;

    public string $FILE = 'php://input';

    public AtomFeed $feed;
    public AtomEntry|AtomFeed|null $current = null;

    public ?string $error = null;
    public string $content = '';

    public function __construct()
    {
        $this->feed = new AtomFeed();
    }

    private function _p(string $msg): void
    {
        if ($this->debug) {
            print str_repeat(' ', $this->depth * $this->indent) . $msg . "\n";
        }
    }

    public function error_handler(int $log_level, string $log_text, ?string $error_file = null, ?int $error_line = null, array $error_context = []): void
    {
        $this->error = $log_text;
    }

    public function parse(): bool
    {
        set_error_handler([$this, 'error_handler']);

        try {
            $this->depth = 0;
            $this->ns_contexts = [];
            $this->ns_decls = [];
            $this->content_ns_decls = [];
            $this->content_ns_contexts = [];
            $this->in_content = [];
            $this->is_xhtml = false;
            $this->is_html = false;
            $this->is_text = true;
            $this->skipped_div = false;

            array_unshift($this->ns_contexts, []);

            $parser = xml_parser_create_ns();
            if ($parser === false) {
                return false;
            }

            xml_set_object($parser, $this);
            xml_set_element_handler($parser, 'start_element', 'end_element');
            xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
            xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 0);
            xml_set_character_data_handler($parser, 'cdata');
            xml_set_default_handler($parser, '_default');
            xml_set_start_namespace_decl_handler($parser, 'start_ns');
            xml_set_end_namespace_decl_handler($parser, 'end_ns');

            $this->content = '';

            $ret = true;

            $fp = fopen($this->FILE, 'r');
            if ($fp === false) {
                xml_parser_free($parser);
                return false;
            }

            while (true) {
                $data = fread($fp, 4096);
                if ($data === false) {
                    $ret = false;
                    break;
                }

                if ($data === '') {
                    if (feof($fp)) {
                        break;
                    }
                    continue;
                }

                if ($this->debug) {
                    $this->content .= $data;
                }

                if (!xml_parse($parser, $data, feof($fp))) {
                    trigger_error(sprintf(__('XML error: %s at line %d') . "\n",
                        xml_error_string(xml_get_error_code($parser)),
                        xml_get_current_line_number($parser)
                    ));
                    $ret = false;
                    break;
                }
            }

            fclose($fp);

            xml_parser_free($parser);

            return $ret;
        } finally {
            restore_error_handler();
        }
    }

    public function start_element($parser, string $name, array $attrs): void
    {
        $parts = explode(':', $name);
        $tag = array_pop($parts);

        switch ($name) {
            case $this->NS . ':feed':
                $this->current = $this->feed;
                break;
            case $this->NS . ':entry':
                $this->current = new AtomEntry();
                break;
        }

        $this->_p("start_element('{$name}')");

        array_unshift($this->ns_contexts, $this->ns_decls);

        $this->depth++;

        if (!empty($this->in_content)) {
            $this->content_ns_decls = [];

            if ($this->is_html || $this->is_text) {
                trigger_error('Invalid content in element found. Content must not be of type text or html if it contains markup.');
            }

            $attrs_prefix = [];

            foreach ($attrs as $key => $value) {
                $with_prefix = $this->ns_to_prefix($key, true);
                $attrs_prefix[$with_prefix[1]] = $this->xml_escape((string) $value);
            }

            $attrs_str = $this->formatAttributes($attrs_prefix);

            $with_prefix = $this->ns_to_prefix($name);

            if (!$this->is_declared_content_ns($with_prefix[0])) {
                $this->content_ns_decls[] = $with_prefix[0];
            }

            $xmlns_str = '';
            if (!empty($this->content_ns_decls)) {
                array_unshift($this->content_ns_contexts, $this->content_ns_decls);
                $xmlns_str = $this->formatNamespaceDeclarations($this->content_ns_contexts[0]);
            }

            $elementStart = sprintf('<%s%s%s>', $with_prefix[1], $xmlns_str, $attrs_str);
            $this->in_content[] = [$tag, $this->depth, $elementStart];
        } elseif (in_array($tag, $this->ATOM_CONTENT_ELEMENTS, true) || in_array($tag, $this->ATOM_SIMPLE_ELEMENTS, true)) {
            $this->in_content = [];
            $typeValue = $attrs['type'] ?? '';
            $this->is_xhtml = $typeValue === 'xhtml';
            $this->is_html = $typeValue === 'html' || $typeValue === 'text/html';
            $this->is_text = !array_key_exists('type', $attrs) || $typeValue === 'text';
            $type = $this->is_xhtml ? 'XHTML' : ($this->is_html ? 'HTML' : ($this->is_text ? 'TEXT' : $typeValue));

            if (array_key_exists('src', $attrs)) {
                $this->current->$tag = $attrs;
            } else {
                $this->in_content[] = [$tag, $this->depth, $type];
            }
        } elseif ($tag === 'link') {
            $this->current->links[] = $attrs;
        } elseif ($tag === 'category') {
            $this->current->categories[] = $attrs;
        }

        $this->ns_decls = [];
    }

    public function end_element($parser, string $name): void
    {
        $parts = explode(':', $name);
        $tag = array_pop($parts);

        $ccount = count($this->in_content);

        if (!empty($this->in_content)) {
            if ($this->in_content[0][0] === $tag && $this->in_content[0][1] === $this->depth) {
                $origtype = $this->in_content[0][2];
                array_shift($this->in_content);
                $newcontent = [];
                foreach ($this->in_content as $c) {
                    if (is_array($c) && count($c) === 3) {
                        $newcontent[] = $c[2];
                    } else {
                        $newcontent[] = ($this->is_xhtml || $this->is_text) ? $this->xml_escape((string) $c) : (string) $c;
                    }
                }
                if (in_array($tag, $this->ATOM_CONTENT_ELEMENTS, true)) {
                    $this->current->$tag = [$origtype, implode('', $newcontent)];
                } else {
                    $this->current->$tag = implode('', $newcontent);
                }
                $this->in_content = [];
            } elseif ($ccount > 0 && $this->in_content[$ccount - 1][0] === $tag &&
                $this->in_content[$ccount - 1][1] === $this->depth) {
                $this->in_content[$ccount - 1][2] = substr((string) $this->in_content[$ccount - 1][2], 0, -1) . '/>';
            } else {
                $endtag = $this->ns_to_prefix($name);
                $this->in_content[] = [$tag, $this->depth, sprintf('</%s>', $endtag[1])];
            }
        }

        array_shift($this->ns_contexts);

        $this->depth--;

        if ($name === ($this->NS . ':entry') && $this->current instanceof AtomEntry) {
            $this->feed->entries[] = $this->current;
            $this->current = null;
        }

        $this->_p("end_element('{$name}')");
    }

    public function start_ns($parser, ?string $prefix, string $uri): void
    {
        $this->_p('starting: ' . ($prefix ?? '') . ':' . $uri);
        $this->ns_decls[] = [$prefix, $uri];
    }

    public function end_ns($parser, ?string $prefix): void
    {
        $this->_p('ending: #' . ($prefix ?? '') . '#');
    }

    public function cdata($parser, string $data): void
    {
        $this->_p('data: #' . str_replace(["\n"], ["\\n"], trim($data)) . '#');
        if (!empty($this->in_content)) {
            $this->in_content[] = $data;
        }
    }

    public function _default($parser, string $data): void
    {
    }

    public function ns_to_prefix(string $qname, bool $attr = false): array
    {
        $components = explode(':', $qname);
        $name = array_pop($components);

        if (!empty($components)) {
            $ns = implode(':', $components);
            foreach ($this->ns_contexts as $context) {
                foreach ($context as $mapping) {
                    if (is_array($mapping) && $mapping[1] === $ns && strlen((string) $mapping[0]) > 0) {
                        return [$mapping, "{$mapping[0]}:{$name}"];
                    }
                }
            }
        }

        if ($attr) {
            return [null, $name];
        }

        foreach ($this->ns_contexts as $context) {
            foreach ($context as $mapping) {
                if (is_array($mapping) && strlen((string) $mapping[0]) === 0) {
                    return [$mapping, $name];
                }
            }
        }

        return [null, $name];
    }

    public function is_declared_content_ns($new_mapping): bool
    {
        foreach ($this->content_ns_contexts as $context) {
            foreach ($context as $mapping) {
                if ($new_mapping == $mapping) {
                    return true;
                }
            }
        }
        return false;
    }

    public function xml_escape(string $string): string
    {
        return str_replace(
            ['&', '"', "'", '<', '>'],
            ['&amp;', '&quot;', '&apos;', '&lt;', '&gt;'],
            $string
        );
    }

    private function formatAttributes(array $attributes): string
    {
        if ($attributes === []) {
            return '';
        }

        $parts = [];
        foreach ($attributes as $key => $value) {
            $parts[] = sprintf('%s="%s"', $key, $value);
        }

        return ' ' . implode(' ', $parts);
    }

    private function formatNamespaceDeclarations(array $context): string
    {
        $parts = [];
        foreach ($context as $mapping) {
            if (!is_array($mapping) || count($mapping) < 2) {
                continue;
            }
            [$prefix, $uri] = $mapping;
            $xmlns = 'xmlns';
            if ($prefix !== null && $prefix !== '') {
                $xmlns .= ':' . $prefix;
            }
            $parts[] = sprintf('%s="%s"', $xmlns, $uri);
        }

        if ($parts === []) {
            return '';
        }

        return ' ' . implode(' ', $parts);
    }
}
?>