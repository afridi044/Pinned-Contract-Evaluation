<?php
declare(strict_types=1);

/**
 * MagpieRSS: a simple RSS integration tool
 *
 * A compiled file for RSS syndication
 *
 * @author Kellan Elliott-McCrea <kellan@protest.net>
 * @version 0.51
 * @license GPL
 *
 * @package External
 * @subpackage MagpieRSS
 * @deprecated 3.0.0 Use SimplePie instead.
 */

/**
 * Deprecated. Use SimplePie (class-simplepie.php) instead.
 */
_deprecated_file( basename( __FILE__ ), '3.0', WPINC . '/class-simplepie.php' );

/**
 * Fires before MagpieRSS is loaded, to optionally replace it.
 *
 * @since 2.3.0
 * @deprecated 3.0.0
 */
do_action( 'load_feed_engine' );

/** RSS feed constant. */
define('RSS', 'RSS');
define('ATOM', 'Atom');
define('MAGPIE_USER_AGENT', 'WordPress/' . $GLOBALS['wp_version']);

class MagpieRSS {
	public XMLParser $parser;
	public array $current_item = [];
	public array $items = [];
	public array $channel = [];
	public array $textinput = [];
	public array $image = [];
	public ?string $feed_type = null;
	public ?string $feed_version = null;
	public array $stack = [];
	public bool $inchannel = false;
	public bool $initem = false;
	public ?string $incontent = null;
	public bool $intextinput = false;
	public bool $inimage = false;
	public string $current_field = '';
	public ?string $current_namespace = null;
	protected array $_CONTENT_CONSTRUCTS = ['content', 'summary', 'info', 'title', 'tagline', 'copyright'];
	public string $ERROR = '';
	public int $from_cache = 0;
	public ?string $etag = null;
	public ?string $last_modified = null;

	public function __construct(string $source) {
		if (!function_exists('xml_parser_create')) {
			$this->error("Failed to load PHP's XML Extension. http://www.php.net/manual/en/ref.xml.php");
			return;
		}

		$parser = xml_parser_create();
		if ($parser === false) {
			$this->error("Failed to create an instance of PHP's XML parser. http://www.php.net/manual/en/ref.xml.php");
			return;
		}

		$this->parser = $parser;

		xml_set_object($this->parser, $this);
		xml_set_element_handler($this->parser, [$this, 'feed_start_element'], [$this, 'feed_end_element']);
		xml_set_character_data_handler($this->parser, [$this, 'feed_cdata']);

		if (!xml_parse($this->parser, $source, true)) {
			$errorCode = xml_get_error_code($this->parser);
			if ($errorCode !== XML_ERROR_NONE) {
				$xmlError = xml_error_string($errorCode);
				$errorLine = xml_get_current_line_number($this->parser);
				$errorCol = xml_get_current_column_number($this->parser);
				$errormsg = "$xmlError at line $errorLine, column $errorCol";
				$this->error($errormsg);
			}
		}

		xml_parser_free($this->parser);

		$this->normalize();
	}

	public function feed_start_element(XMLParser $parser, string $element, array $attrs): void {
		$element = strtolower($element);
		$attrs = array_change_key_case($attrs, CASE_LOWER);

		$el = $element;
		$ns = null;

		if (str_contains($element, ':')) {
			[$ns, $el] = explode(':', $element, 2);
		}

		if (!empty($ns) && $ns !== 'rdf') {
			$this->current_namespace = $ns;
		} else {
			$this->current_namespace = null;
		}

		if (!isset($this->feed_type)) {
			if ($el === 'rdf') {
				$this->feed_type = RSS;
				$this->feed_version = '1.0';
			} elseif ($el === 'rss') {
				$this->feed_type = RSS;
				$this->feed_version = $attrs['version'] ?? null;
			} elseif ($el === 'feed') {
				$this->feed_type = ATOM;
				$this->feed_version = $attrs['version'] ?? null;
				$this->inchannel = true;
			}
			return;
		}

		if ($el === 'channel') {
			$this->inchannel = true;
		} elseif ($el === 'item' || $el === 'entry') {
			$this->initem = true;
			if (isset($attrs['rdf:about'])) {
				$this->current_item['about'] = $attrs['rdf:about'];
			}
		} elseif ($this->feed_type === RSS && empty($this->current_namespace) && $el === 'textinput') {
			$this->intextinput = true;
		} elseif ($this->feed_type === RSS && empty($this->current_namespace) && $el === 'image') {
			$this->inimage = true;
		} elseif ($this->feed_type === ATOM && in_array($el, $this->_CONTENT_CONSTRUCTS, true)) {
			if ($el === 'content') {
				$el = 'atom_content';
			}
			$this->incontent = $el;
		} elseif ($this->feed_type === ATOM && $this->incontent !== null) {
			$attrsStr = implode(
				' ',
				array_map(
					static fn ($key, $value): string => self::map_attrs((string) $key, (string) $value),
					array_keys($attrs),
					$attrs
				)
			);

			$this->append_content("<$element" . ($attrsStr !== '' ? " $attrsStr" : '') . '>');
			array_unshift($this->stack, $el);
		} elseif ($this->feed_type === ATOM && $el === 'link') {
			$rel = $attrs['rel'] ?? 'alternate';
			$linkEl = $rel === 'alternate' ? 'link' : 'link_' . $rel;
			$this->append($linkEl, $attrs['href'] ?? '');
		} else {
			array_unshift($this->stack, $el);
		}
	}

	public function feed_cdata(XMLParser $parser, string $text): void {
		if ($this->feed_type === ATOM && $this->incontent !== null) {
			$this->append_content($text);
		} else {
			$currentEl = implode('_', array_reverse($this->stack));
			$this->append($currentEl, $text);
		}
	}

	public function feed_end_element(XMLParser $parser, string $el): void {
		$el = strtolower($el);

		if ($el === 'item' || $el === 'entry') {
			$this->items[] = $this->current_item;
			$this->current_item = [];
			$this->initem = false;
		} elseif ($this->feed_type === RSS && empty($this->current_namespace) && $el === 'textinput') {
			$this->intextinput = false;
		} elseif ($this->feed_type === RSS && empty($this->current_namespace) && $el === 'image') {
			$this->inimage = false;
		} elseif ($this->feed_type === ATOM && in_array($el, $this->_CONTENT_CONSTRUCTS, true)) {
			$this->incontent = null;
		} elseif ($el === 'channel' || $el === 'feed') {
			$this->inchannel = false;
		} elseif ($this->feed_type === ATOM && $this->incontent !== null) {
			if (!empty($this->stack) && $this->stack[0] === $el) {
				$this->append_content("</$el>");
			} else {
				$this->append_content("<$el />");
			}

			if (!empty($this->stack)) {
				array_shift($this->stack);
			}
		} else {
			if (!empty($this->stack)) {
				array_shift($this->stack);
			}
		}

		$this->current_namespace = null;
	}

	public function append_content(string $text): void {
		if ($this->incontent === null) {
			return;
		}

		if ($this->initem) {
			$this->current_item[$this->incontent] = ($this->current_item[$this->incontent] ?? '') . $text;
		} elseif ($this->inchannel) {
			$this->channel[$this->incontent] = ($this->channel[$this->incontent] ?? '') . $text;
		}
	}

	public function append(string $el, string $text): void {
		if ($el === '') {
			return;
		}

		if (!empty($this->current_namespace)) {
			$namespace = $this->current_namespace;

			if ($this->initem) {
				$this->current_item[$namespace] = $this->current_item[$namespace] ?? [];
				$this->current_item[$namespace][$el] = ($this->current_item[$namespace][$el] ?? '') . $text;
			} elseif ($this->inchannel) {
				$this->channel[$namespace] = $this->channel[$namespace] ?? [];
				$this->channel[$namespace][$el] = ($this->channel[$namespace][$el] ?? '') . $text;
			} elseif ($this->intextinput) {
				$this->textinput[$namespace] = $this->textinput[$namespace] ?? [];
				$this->textinput[$namespace][$el] = ($this->textinput[$namespace][$el] ?? '') . $text;
			} elseif ($this->inimage) {
				$this->image[$namespace] = $this->image[$namespace] ?? [];
				$this->image[$namespace][$el] = ($this->image[$namespace][$el] ?? '') . $text;
			}
		} else {
			if ($this->initem) {
				$this->current_item[$el] = ($this->current_item[$el] ?? '') . $text;
			} elseif ($this->intextinput) {
				$this->textinput[$el] = ($this->textinput[$el] ?? '') . $text;
			} elseif ($this->inimage) {
				$this->image[$el] = ($this->image[$el] ?? '') . $text;
			} elseif ($this->inchannel) {
				$this->channel[$el] = ($this->channel[$el] ?? '') . $text;
			}
		}
	}

	public function normalize(): void {
		if ($this->is_atom()) {
			if (isset($this->channel['tagline'])) {
				$this->channel['description'] = $this->channel['tagline'];
			}
			foreach ($this->items as &$item) {
				if (isset($item['summary'])) {
					$item['description'] = $item['summary'];
				}
				if (isset($item['atom_content'])) {
					$item['content']['encoded'] = $item['atom_content'];
				}
			}
			unset($item);
		} elseif ($this->is_rss()) {
			if (isset($this->channel['description'])) {
				$this->channel['tagline'] = $this->channel['description'];
			}
			foreach ($this->items as &$item) {
				if (isset($item['description'])) {
					$item['summary'] = $item['description'];
				}
				if (isset($item['content']['encoded'])) {
					$item['atom_content'] = $item['content']['encoded'];
				}
			}
			unset($item);
		}
	}

	public function is_rss(): string|false {
		return $this->feed_type === RSS ? ($this->feed_version ?? '') : false;
	}

	public function is_atom(): string|false {
		return $this->feed_type === ATOM ? ($this->feed_version ?? '') : false;
	}

	public static function map_attrs(string $k, string $v): string {
		return sprintf('%s="%s"', $k, $v);
	}

	public function error(string $errormsg, int $lvl = E_USER_WARNING): void {
		$lastError = error_get_last();
		if ($lastError !== null) {
			$errormsg .= ' (' . $lastError['message'] . ')';
		}

		$this->ERROR = $errormsg;

		if (defined('MAGPIE_DEBUG') && MAGPIE_DEBUG) {
			trigger_error($errormsg, $lvl);
		} else {
			error_log($errormsg);
		}
	}
}

if ( !function_exists('fetch_rss') ) :
function fetch_rss(?string $url): MagpieRSS|false {
	init();

	if ($url === null || $url === '') {
		return false;
	}

	if (!MAGPIE_CACHE_ON) {
		$resp = _fetch_remote_file($url);
		if (is_success((int) $resp->status)) {
			return _response_to_rss($resp);
		}
		return false;
	}

	$cache = new RSSCache(MAGPIE_CACHE_DIR, MAGPIE_CACHE_AGE);

	if (MAGPIE_DEBUG && $cache->ERROR !== '') {
		debug($cache->ERROR, E_USER_WARNING);
	}

	$cache_status = 'MISS';
	$request_headers = [];
	$rss = null;
	$errormsg = '';

	if ($cache->ERROR === '') {
		$cache_status = $cache->check_cache($url);
	}

	if ($cache_status === 'HIT') {
		$rss = $cache->get($url);
		if ($rss instanceof MagpieRSS) {
			$rss->from_cache = 1;
			if (MAGPIE_DEBUG > 1) {
				debug("MagpieRSS: Cache HIT", E_USER_NOTICE);
			}
			return $rss;
		}
	}

	if ($cache_status === 'STALE') {
		$rss = $cache->get($url);
		if ($rss instanceof MagpieRSS && isset($rss->etag, $rss->last_modified)) {
			$request_headers['If-None-Match'] = $rss->etag;
			$request_headers['If-Last-Modified'] = $rss->last_modified;
		}
	}

	$resp = _fetch_remote_file($url, $request_headers);

	if ($resp !== null) {
		if ((string) $resp->status === '304' && $rss instanceof MagpieRSS) {
			if (MAGPIE_DEBUG > 1) {
				debug("Got 304 for $url");
			}
			$cache->set($url, $rss);
			return $rss;
		}

		if (is_success((int) $resp->status)) {
			$rss = _response_to_rss($resp);
			if ($rss instanceof MagpieRSS) {
				if (MAGPIE_DEBUG > 1) {
					debug('Fetch successful');
				}
				$cache->set($url, $rss);
				return $rss;
			}
		} else {
			$errormsg = "Failed to fetch $url. ";
			if (!empty($resp->error)) {
				$http_error = rtrim((string) $resp->error, "\n");
				$errormsg .= "(HTTP Error: $http_error)";
			} else {
				$errormsg .= "(HTTP Response: " . ($resp->response_code ?? 'unknown') . ')';
			}
		}
	} else {
		$errormsg = 'Unable to retrieve RSS file for unknown reasons.';
	}

	if ($rss instanceof MagpieRSS) {
		if (MAGPIE_DEBUG) {
			debug("Returning STALE object for $url");
		}
		return $rss;
	}

	return false;
}
endif;

function _fetch_remote_file(string $url, array $headers = []): stdClass {
	$resp = wp_safe_remote_request($url, [
		'headers' => $headers,
		'timeout' => MAGPIE_FETCH_TIME_OUT,
	]);

	if (is_wp_error($resp)) {
		$error = array_shift($resp->errors);

		$response = new stdClass();
		$response->status = 500;
		$response->response_code = 500;
		$response->error = ($error[0] ?? '') . "\n";
		return $response;
	}

	$return_headers = [];
	foreach (wp_remote_retrieve_headers($resp) as $key => $value) {
		if (!is_array($value)) {
			$return_headers[] = "$key: $value";
		} else {
			foreach ($value as $v) {
				$return_headers[] = "$key: $v";
			}
		}
	}

	$response = new stdClass();
	$response->status = wp_remote_retrieve_response_code($resp);
	$response->response_code = wp_remote_retrieve_response_code($resp);
	$response->headers = $return_headers;
	$response->results = wp_remote_retrieve_body($resp);

	return $response;
}

function _response_to_rss(stdClass $resp): MagpieRSS|false {
	$rss = new MagpieRSS((string) $resp->results);

	if ($rss instanceof MagpieRSS && $rss->ERROR === '') {
		foreach ((array) ($resp->headers ?? []) as $header) {
			if (str_contains($header, ': ')) {
				[$field, $val] = explode(': ', $header, 2);
			} else {
				$field = $header;
				$val = '';
			}

			$fieldLower = strtolower($field);
			if ($fieldLower === 'etag') {
				$rss->etag = $val;
			}

			if ($fieldLower === 'last-modified') {
				$rss->last_modified = $val;
			}
		}

		return $rss;
	}

	return false;
}

/**
 * Set up constants with default values, unless user overrides.
 */
function init(): void {
	if (defined('MAGPIE_INITALIZED')) {
		return;
	}

	define('MAGPIE_INITALIZED', 1);

	if (!defined('MAGPIE_CACHE_ON')) {
		define('MAGPIE_CACHE_ON', 1);
	}

	if (!defined('MAGPIE_CACHE_DIR')) {
		define('MAGPIE_CACHE_DIR', './cache');
	}

	if (!defined('MAGPIE_CACHE_AGE')) {
		define('MAGPIE_CACHE_AGE', 60 * 60);
	}

	if (!defined('MAGPIE_CACHE_FRESH_ONLY')) {
		define('MAGPIE_CACHE_FRESH_ONLY', 0);
	}

	if (!defined('MAGPIE_DEBUG')) {
		define('MAGPIE_DEBUG', 0);
	}

	if (!defined('MAGPIE_USER_AGENT')) {
		$ua = 'WordPress/' . $GLOBALS['wp_version'];
		$ua .= MAGPIE_CACHE_ON ? ')' : '; No cache)';
		define('MAGPIE_USER_AGENT', $ua);
	}

	if (!defined('MAGPIE_FETCH_TIME_OUT')) {
		define('MAGPIE_FETCH_TIME_OUT', 2);
	}

	if (!defined('MAGPIE_USE_GZIP')) {
		define('MAGPIE_USE_GZIP', true);
	}
}

function is_info(int $sc): bool {
	return $sc >= 100 && $sc < 200;
}

function is_success(int $sc): bool {
	return $sc >= 200 && $sc < 300;
}

function is_redirect(int $sc): bool {
	return $sc >= 300 && $sc < 400;
}

function is_error(int $sc): bool {
	return $sc >= 400 && $sc < 600;
}

function is_client_error(int $sc): bool {
	return $sc >= 400 && $sc < 500;
}

function is_server_error(int $sc): bool {
	return $sc >= 500 && $sc < 600;
}

class RSSCache {
	public string $BASE_CACHE;
	public int $MAX_AGE = 43200;
	public string $ERROR = '';

	public function __construct(string $base = '', int $age = 0) {
		$this->BASE_CACHE = WP_CONTENT_DIR . '/cache';
		if ($base !== '') {
			$this->BASE_CACHE = $base;
		}
		if ($age > 0) {
			$this->MAX_AGE = $age;
		}
	}

	public function set(string $url, MagpieRSS $rss): string {
		$cache_option = 'rss_' . $this->file_name($url);
		set_transient($cache_option, $rss, $this->MAX_AGE);
		return $cache_option;
	}

	public function get(string $url): MagpieRSS|false {
		$this->ERROR = '';
		$cache_option = 'rss_' . $this->file_name($url);

		$rss = get_transient($cache_option);
		if (!$rss instanceof MagpieRSS) {
			$this->debug("Cache doesn't contain: $url (cache option: $cache_option)");
			return false;
		}

		return $rss;
	}

	public function check_cache(string $url): string {
		$this->ERROR = '';
		$cache_option = 'rss_' . $this->file_name($url);

		if (get_transient($cache_option) !== false) {
			return 'HIT';
		}

		return 'MISS';
	}

	public function serialize(mixed $rss): string {
		return serialize($rss);
	}

	public function unserialize(string $data): mixed {
		return unserialize($data, ['allowed_classes' => true]);
	}

	public function file_name(string $url): string {
		return md5($url);
	}

	public function error(string $errormsg, int $lvl = E_USER_WARNING): void {
		$lastError = error_get_last();
		if ($lastError !== null) {
			$errormsg .= ' (' . $lastError['message'] . ')';
		}
		$this->ERROR = $errormsg;
		if (MAGPIE_DEBUG) {
			trigger_error($errormsg, $lvl);
		} else {
			error_log($errormsg);
		}
	}

	public function debug(string $debugmsg, int $lvl = E_USER_NOTICE): void {
		if (MAGPIE_DEBUG) {
			$this->error("MagpieRSS [debug] $debugmsg", $lvl);
		}
	}
}

if ( !function_exists('parse_w3cdtf') ) :
function parse_w3cdtf(string $date_str): int {
	try {
		return (new DateTimeImmutable($date_str))->getTimestamp();
	} catch (Exception) {
		return -1;
	}
}
endif;

if ( !function_exists('wp_rss') ) :
function wp_rss(string $url, int $num_items = -1): void {
	$rss = fetch_rss($url);

	if ($rss instanceof MagpieRSS) {
		echo '<ul>';

		if ($num_items !== -1) {
			$rss->items = array_slice($rss->items, 0, $num_items);
		}

		foreach ((array) $rss->items as $item) {
			printf(
				'<li><a href="%1$s" title="%2$s">%3$s</a></li>',
				esc_url($item['link'] ?? ''),
				esc_attr(strip_tags($item['description'] ?? '')),
				esc_html($item['title'] ?? '')
			);
		}

		echo '</ul>';
	} else {
		_e('An error has occurred, which probably means the feed is down. Try again later.');
	}
}
endif;

if ( !function_exists('get_rss') ) :
function get_rss(string $url, int $num_items = 5): bool {
	$rss = fetch_rss($url);
	if (!($rss instanceof MagpieRSS)) {
		return false;
	}

	$rss->items = array_slice($rss->items, 0, $num_items);
	foreach ((array) $rss->items as $item) {
		echo "<li>\n";
		echo '<a href="' . esc_url($item['link'] ?? '') . '" title="' . esc_attr($item['description'] ?? '') . '">';
		echo esc_html($item['title'] ?? '');
		echo "</a><br />\n";
		echo "</li>\n";
	}

	return true;
}
endif;
?>