<?php
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
	private ?\XMLParser $parser = null;
	private array $current_item = [];
	public array $items = [];
	public array $channel = [];
	public array $textinput = [];
	public array $image = [];
	public ?string $feed_type = null;
	public ?string $feed_version = null;

	// Properties set by fetch_rss()
	public bool $from_cache = false;
	public ?string $etag = null;
	public ?string $last_modified = null;

	// Parser variables
	private array $stack = [];
	private bool $inchannel = false;
	private bool $initem = false;
	private string|false $incontent = false; // if in Atom <content mode="xml"> field
	private bool $intextinput = false;
	private bool $inimage = false;
	private string|false $current_namespace = false;

	private const array _CONTENT_CONSTRUCTS = ['content', 'summary', 'info', 'title', 'tagline', 'copyright'];

	public function __construct(string $source) {
		if (!function_exists('xml_parser_create')) {
			trigger_error("Failed to load PHP's XML Extension. http://www.php.net/manual/en/ref.xml.php");
			return;
		}

		$parser = xml_parser_create();

		if (!$parser) {
			trigger_error("Failed to create an instance of PHP's XML parser. http://www.php.net/manual/en/ref.xml.php");
			return;
		}

		$this->parser = $parser;

		xml_set_object($this->parser, $this);
		xml_set_element_handler($this->parser, [$this, 'feed_start_element'], [$this, 'feed_end_element']);
		xml_set_character_data_handler($this->parser, [$this, 'feed_cdata']);

		$status = xml_parse($this->parser, $source);

		if (!$status) {
			$error_code = xml_get_error_code($this->parser);
			if ($error_code !== XML_ERROR_NONE) {
				$xml_error = xml_error_string($error_code);
				$error_line = xml_get_current_line_number($this->parser);
				$error_col = xml_get_current_column_number($this->parser);
				$errormsg = "$xml_error at line $error_line, column $error_col";

				$this->error($errormsg);
			}
		}

		xml_parser_free($this->parser);

		$this->normalize();
	}

	public function feed_start_element(\XMLParser $p, string $element, array &$attrs): void {
		$el = $element = strtolower($element);
		$attrs = array_change_key_case($attrs, CASE_LOWER);

		$ns = false;
		if (str_contains($element, ':')) {
			[$ns, $el] = explode(':', $element, 2);
		}
		if ($ns && $ns !== 'rdf') {
			$this->current_namespace = $ns;
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
		} elseif ($this->feed_type === RSS && $this->current_namespace === '' && $el === 'textinput') {
			$this->intextinput = true;
		} elseif ($this->feed_type === RSS && $this->current_namespace === '' && $el === 'image') {
			$this->inimage = true;
		} elseif ($this->feed_type === ATOM && in_array($el, self::_CONTENT_CONSTRUCTS, true)) {
			$this->incontent = ($el === 'content') ? 'atom_content' : $el;
		} elseif ($this->feed_type === ATOM && $this->incontent) {
			$attrs_str = implode(' ', array_map([self::class, 'map_attrs'], array_keys($attrs), array_values($attrs)));
			$this->append_content("<$element $attrs_str>");
			array_unshift($this->stack, $el);
		} elseif ($this->feed_type === ATOM && $el === 'link') {
			$link_el = (isset($attrs['rel']) && $attrs['rel'] === 'alternate') ? 'link' : 'link_' . ($attrs['rel'] ?? 'unknown');
			$this->append($link_el, $attrs['href'] ?? '');
		} else {
			array_unshift($this->stack, $el);
		}
	}

	public function feed_cdata(\XMLParser $p, string $text): void {
		if ($this->feed_type === ATOM && $this->incontent) {
			$this->append_content($text);
		} else {
			$current_el = implode('_', array_reverse($this->stack));
			$this->append($current_el, $text);
		}
	}

	public function feed_end_element(\XMLParser $p, string $el): void {
		$el = strtolower($el);

		if ($el === 'item' || $el === 'entry') {
			$this->items[] = $this->current_item;
			$this->current_item = [];
			$this->initem = false;
		} elseif ($this->feed_type === RSS && $this->current_namespace === '' && $el === 'textinput') {
			$this->intextinput = false;
		} elseif ($this->feed_type === RSS && $this->current_namespace === '' && $el === 'image') {
			$this->inimage = false;
		} elseif ($this->feed_type === ATOM && in_array($el, self::_CONTENT_CONSTRUCTS, true)) {
			$this->incontent = false;
		} elseif ($el === 'channel' || $el === 'feed') {
			$this->inchannel = false;
		} elseif ($this->feed_type === ATOM && $this->incontent) {
			if (($this->stack[0] ?? null) === $el) {
				$this->append_content("</$el>");
			} else {
				$this->append_content("<$el />");
			}
			array_shift($this->stack);
		} else {
			array_shift($this->stack);
		}

		$this->current_namespace = false;
	}

	private function append_content(string $text): void {
		if (!$this->incontent) {
			return;
		}
		if ($this->initem) {
			($this->current_item[$this->incontent] ??= '') .= $text;
		} elseif ($this->inchannel) {
			($this->channel[$this->incontent] ??= '') .= $text;
		}
	}

	private function append(string $el, string $text): void {
		if (!$el) {
			return;
		}

		$target = null;
		if ($this->initem) {
			$target = &$this->current_item;
		} elseif ($this->intextinput) {
			$target = &$this->textinput;
		} elseif ($this->inimage) {
			$target = &$this->image;
		} elseif ($this->inchannel) {
			$target = &$this->channel;
		}

		if ($target === null) {
			return;
		}

		if ($this->current_namespace) {
			($target[$this->current_namespace][$el] ??= '') .= $text;
		} else {
			($target[$el] ??= '') .= $text;
		}
	}

	private function normalize(): void {
		if ($this->is_atom()) {
			$this->channel['description'] = $this->channel['tagline'] ?? null;
			foreach ($this->items as $i => $item) {
				if (isset($item['summary'])) {
					$item['description'] = $item['summary'];
				}
				if (isset($item['atom_content'])) {
					$item['content']['encoded'] = $item['atom_content'];
				}
				$this->items[$i] = $item;
			}
		} elseif ($this->is_rss()) {
			$this->channel['tagline'] = $this->channel['description'] ?? null;
			foreach ($this->items as $i => $item) {
				if (isset($item['description'])) {
					$item['summary'] = $item['description'];
				}
				if (isset($item['content']['encoded'])) {
					$item['atom_content'] = $item['content']['encoded'];
				}
				$this->items[$i] = $item;
			}
		}
	}

	public function is_rss(): string|false {
		return ($this->feed_type === RSS) ? $this->feed_version : false;
	}

	public function is_atom(): string|false {
		return ($this->feed_type === ATOM) ? $this->feed_version : false;
	}

	private static function map_attrs(string $k, string $v): string {
		return "$k=\"$v\"";
	}

	private function error(string $errormsg, int $lvl = E_USER_WARNING): void {
		if (defined('MAGPIE_DEBUG') && MAGPIE_DEBUG) {
			trigger_error($errormsg, $lvl);
		} else {
			error_log($errormsg, 0);
		}
	}
}

if (!function_exists('fetch_rss')) :
/**
 * Build Magpie object based on RSS from URL.
 *
 * @since 1.5.0
 * @package External
 * @subpackage MagpieRSS
 *
 * @param string $url URL to retrieve feed
 * @return MagpieRSS|false false on failure or MagpieRSS object on success.
 */
function fetch_rss(string $url): MagpieRSS|false {
	init();

	if (!$url) {
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
	if (MAGPIE_DEBUG && $cache->ERROR) {
		$cache->debug($cache->ERROR, E_USER_WARNING);
	}

	$rss = null;
	if (!$cache->ERROR) {
		$cache_status = $cache->check_cache($url);
		if ($cache_status === 'HIT') {
			$rss = $cache->get($url);
			if ($rss) {
				$rss->from_cache = true;
				if (MAGPIE_DEBUG > 1) {
					$cache->debug("MagpieRSS: Cache HIT", E_USER_NOTICE);
				}
				return $rss;
			}
		}
		if ($cache_status === 'STALE') {
			$rss = $cache->get($url);
		}
	}

	$request_headers = [];
	if ($rss && isset($rss->etag, $rss->last_modified)) {
		$request_headers['If-None-Match'] = $rss->etag;
		$request_headers['If-Modified-Since'] = $rss->last_modified;
	}

	$resp = _fetch_remote_file($url, $request_headers);

	if ($resp) {
		if ($resp->status == '304') {
			if (MAGPIE_DEBUG > 1) {
				$cache->debug("Got 304 for $url");
			}
			if ($rss) {
				$cache->set($url, $rss);
				return $rss;
			}
		} elseif (is_success((int) $resp->status)) {
			$new_rss = _response_to_rss($resp);
			if ($new_rss) {
				if (MAGPIE_DEBUG > 1) {
					$cache->debug("Fetch successful");
				}
				$cache->set($url, $new_rss);
				return $new_rss;
			}
		}
	}

	if ($rss) {
		if (MAGPIE_DEBUG) {
			$cache->debug("Returning STALE object for $url");
		}
		return $rss;
	}

	return false;
}
endif;

/**
 * Retrieve URL headers and content using WP HTTP Request API.
 *
 * @since 1.5.0
 * @package External
 * @subpackage MagpieRSS
 *
 * @param string $url URL to retrieve
 * @param array $headers Optional. Headers to send to the URL.
 * @return stdClass Snoopy style response
 */
function _fetch_remote_file(string $url, array $headers = []): \stdClass {
	$resp = wp_safe_remote_request($url, ['headers' => $headers, 'timeout' => MAGPIE_FETCH_TIME_OUT]);

	$response = new \stdClass();

	if (is_wp_error($resp)) {
		$error = array_shift($resp->errors);
		$response->status = 500;
		$response->response_code = 500;
		$response->error = ($error[0] ?? 'Unknown error') . "\n";
		$response->headers = [];
		$response->results = '';
		return $response;
	}

	$return_headers = [];
	foreach (wp_remote_retrieve_headers($resp) as $key => $value) {
		$values = is_array($value) ? $value : [$value];
		foreach ($values as $v) {
			$return_headers[] = "$key: $v";
		}
	}

	$response->status = wp_remote_retrieve_response_code($resp);
	$response->response_code = wp_remote_retrieve_response_code($resp);
	$response->headers = $return_headers;
	$response->results = wp_remote_retrieve_body($resp);

	return $response;
}

/**
 * Convert HTTP response into a MagpieRSS object.
 *
 * @since 1.5.0
 * @package External
 * @subpackage MagpieRSS
 *
 * @param stdClass $resp The response object from _fetch_remote_file.
 * @return MagpieRSS|false MagpieRSS object on success, false on failure.
 */
function _response_to_rss(\stdClass $resp): MagpieRSS|false {
	$rss = new MagpieRSS($resp->results);

	if ($rss->feed_type) {
		foreach ((array) $resp->headers as $h) {
			if (str_contains($h, ': ')) {
				[$field, $val] = explode(': ', $h, 2);
				$field = strtolower($field);

				if ($field === 'etag') {
					$rss->etag = $val;
				}
				if ($field === 'last-modified') {
					$rss->last_modified = $val;
				}
			}
		}
		return $rss;
	}

	return false;
}

/**
 * Set up constants with default values, unless user overrides.
 *
 * @since 1.5.0
 * @package External
 * @subpackage MagpieRSS
 */
function init(): void {
	if (defined('MAGPIE_INITALIZED')) {
		return;
	}
	define('MAGPIE_INITALIZED', 1);

	if (!defined('MAGPIE_CACHE_ON')) define('MAGPIE_CACHE_ON', 1);
	if (!defined('MAGPIE_CACHE_DIR')) define('MAGPIE_CACHE_DIR', './cache');
	if (!defined('MAGPIE_CACHE_AGE')) define('MAGPIE_CACHE_AGE', 60 * 60); // one hour
	if (!defined('MAGPIE_CACHE_FRESH_ONLY')) define('MAGPIE_CACHE_FRESH_ONLY', 0);
	if (!defined('MAGPIE_DEBUG')) define('MAGPIE_DEBUG', 0);

	if (!defined('MAGPIE_USER_AGENT')) {
		$ua = 'WordPress/' . ($GLOBALS['wp_version'] ?? '');
		$ua .= MAGPIE_CACHE_ON ? ')' : '; No cache)';
		define('MAGPIE_USER_AGENT', $ua);
	}

	if (!defined('MAGPIE_FETCH_TIME_OUT')) define('MAGPIE_FETCH_TIME_OUT', 2); // 2 second timeout
	if (!defined('MAGPIE_USE_GZIP')) define('MAGPIE_USE_GZIP', true);
}

function is_info(int $sc): bool { return $sc >= 100 && $sc < 200; }
function is_success(int $sc): bool { return $sc >= 200 && $sc < 300; }
function is_redirect(int $sc): bool { return $sc >= 300 && $sc < 400; }
function is_error(int $sc): bool { return $sc >= 400 && $sc < 600; }
function is_client_error(int $sc): bool { return $sc >= 400 && $sc < 500; }
function is_server_error(int $sc): bool { return $sc >= 500 && $sc < 600; }

class RSSCache {
	private string $BASE_CACHE;
	private int $MAX_AGE = 43200;
	public string $ERROR = '';

	public function __construct(string $base = '', int $age = 0) {
		$this->BASE_CACHE = WP_CONTENT_DIR . '/cache';
		if ($base) {
			$this->BASE_CACHE = $base;
		}
		if ($age) {
			$this->MAX_AGE = $age;
		}
	}

	public function set(string $url, MagpieRSS $rss): string {
		$cache_option = 'rss_' . $this->file_name($url);
		set_transient($cache_option, $rss, $this->MAX_AGE);
		return $cache_option;
	}

	public function get(string $url): ?MagpieRSS {
		$this->ERROR = "";
		$cache_option = 'rss_' . $this->file_name($url);
		$rss = get_transient($cache_option);
		if (!$rss instanceof MagpieRSS) {
			$this->debug("Cache doesn't contain: $url (cache option: $cache_option)");
			return null;
		}
		return $rss;
	}

	public function check_cache(string $url): string {
		$this->ERROR = "";
		$cache_option = 'rss_' . $this->file_name($url);
		return get_transient($cache_option) ? 'HIT' : 'MISS';
	}

	public function serialize(MagpieRSS $rss): string {
		return serialize($rss);
	}

	public function unserialize(string $data): MagpieRSS {
		return unserialize($data);
	}

	private function file_name(string $url): string {
		return md5($url);
	}

	public function error(string $errormsg, int $lvl = E_USER_WARNING): void {
		$this->ERROR = $errormsg;
		if (defined('MAGPIE_DEBUG') && MAGPIE_DEBUG) {
			trigger_error($errormsg, $lvl);
		} else {
			error_log($errormsg, 0);
		}
	}

	public function debug(string $debugmsg, int $lvl = E_USER_NOTICE): void {
		if (defined('MAGPIE_DEBUG') && MAGPIE_DEBUG) {
			$this->error("MagpieRSS [debug] $debugmsg", $lvl);
		}
	}
}

if (!function_exists('parse_w3cdtf')) :
function parse_w3cdtf(string $date_str): int {
	$pat = "/(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})?(?:\.\d+)?(?:([-+])(\d{2}):?(\d{2})|(Z))?/";

	if (preg_match($pat, $date_str, $match)) {
		[, $year, $month, $day, $hours, $minutes, $seconds] = array_pad($match, 7, 0);
		$seconds = (int) $seconds;

		$epoch = gmmktime((int) $hours, (int) $minutes, $seconds, (int) $month, (int) $day, (int) $year);

		$offset = 0;
		if (($match[10] ?? null) === 'Z') {
			// Zulu time, aka GMT
		} elseif (isset($match[8], $match[9])) {
			$tz_mod = $match[8];
			$tz_hour = (int) ($match[9] ?? 0);
			$tz_min = (int) ($match[10] ?? 0);
			$offset_secs = (($tz_hour * 60) + $tz_min) * 60;

			if ($tz_mod === '+') {
				$offset_secs *= -1;
			}
			$offset = $offset_secs;
		}
		return $epoch + $offset;
	}

	return -1;
}
endif;

if (!function_exists('wp_rss')) :
/**
 * Display all RSS items in a HTML ordered list.
 *
 * @since 1.5.0
 * @package External
 * @subpackage MagpieRSS
 *
 * @param string $url URL of feed to display. Will not auto sense feed URL.
 * @param int $num_items Optional. Number of items to display, default is all.
 */
function wp_rss(string $url, int $num_items = -1): void {
	$rss = fetch_rss($url);
	if ($rss) {
		echo '<ul>';

		if ($num_items !== -1) {
			$rss->items = array_slice($rss->items, 0, $num_items);
		}

		foreach ((array) $rss->items as $item) {
			printf(
				'<li><a href="%s" title="%s">%s</a></li>',
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

if (!function_exists('get_rss')) :
/**
 * Display RSS items in HTML list items.
 *
 * You have to specify which HTML list you want, either ordered or unordered
 * before using the function. You also have to specify how many items you wish
 * to display. You can't display all of them like you can with wp_rss()
 * function.
 *
 * @since 1.5.0
 * @package External
 * @subpackage MagpieRSS
 *
 * @param string $url URL of feed to display. Will not auto sense feed URL.
 * @param int $num_items Optional. Number of items to display, default is 5.
 * @return bool False on failure, true on success.
 */
function get_rss(string $url, int $num_items = 5): bool {
	$rss = fetch_rss($url);
	if ($rss) {
		$rss->items = array_slice($rss->items, 0, $num_items);
		foreach ((array) $rss->items as $item) {
			printf(
				"<li>\n<a href='%s' title='%s'>%s</a><br />\n</li>\n",
				esc_url($item['link'] ?? ''),
				esc_attr(strip_tags($item['description'] ?? '')),
				esc_html($item['title'] ?? '')
			);
		}
		return true;
	}

	return false;
}
endif;