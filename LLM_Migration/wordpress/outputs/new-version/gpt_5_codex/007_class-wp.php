<?php
/**
 * WordPress environment setup class.
 *
 * @package WordPress
 * @since 2.0.0
 */
class WP {
	/**
	 * Public query variables.
	 *
	 * @since 2.0.0
	 * @access public
	 * @var array
	 */
	public array $public_query_vars = [
		'm',
		'p',
		'posts',
		'w',
		'cat',
		'withcomments',
		'withoutcomments',
		's',
		'search',
		'exact',
		'sentence',
		'calendar',
		'page',
		'paged',
		'more',
		'tb',
		'pb',
		'author',
		'order',
		'orderby',
		'year',
		'monthnum',
		'day',
		'hour',
		'minute',
		'second',
		'name',
		'category_name',
		'tag',
		'feed',
		'author_name',
		'static',
		'pagename',
		'page_id',
		'error',
		'comments_popup',
		'attachment',
		'attachment_id',
		'subpost',
		'subpost_id',
		'preview',
		'robots',
		'taxonomy',
		'term',
		'cpage',
		'post_type',
	];

	/**
	 * Private query variables.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	public array $private_query_vars = [
		'offset',
		'posts_per_page',
		'posts_per_archive_page',
		'showposts',
		'nopaging',
		'post_type',
		'post_status',
		'category__in',
		'category__not_in',
		'category__and',
		'tag__in',
		'tag__not_in',
		'tag__and',
		'tag_slug__in',
		'tag_slug__and',
		'tag_id',
		'post_mime_type',
		'perm',
		'comments_per_page',
		'post__in',
		'post__not_in',
		'post_parent',
		'post_parent__in',
		'post_parent__not_in',
	];

	/**
	 * Extra query variables set by the user.
	 *
	 * @since 2.1.0
	 * @var array
	 */
	public array $extra_query_vars = [];

	/**
	 * Query variables for setting up the WordPress Query Loop.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	public array $query_vars = [];

	/**
	 * String parsed to set the query variables.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public string $query_string = '';

	/**
	 * Permalink or requested URI.
	 *
	 * @since 2.0.0
	 * @var string|null
	 */
	public ?string $request = null;

	/**
	 * Rewrite rule the request matched.
	 *
	 * @since 2.0.0
	 * @var string|null
	 */
	public ?string $matched_rule = null;

	/**
	 * Rewrite query the request matched.
	 *
	 * @since 2.0.0
	 * @var string|null
	 */
	public ?string $matched_query = null;

	/**
	 * Whether already did the permalink.
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	public bool $did_permalink = false;

	/**
	 * Add name to list of public query variables.
	 *
	 * @since 2.1.0
	 *
	 * @param string $qv Query variable name.
	 */
	public function add_query_var(string $qv): void {
		if (! in_array($qv, $this->public_query_vars, true)) {
			$this->public_query_vars[] = $qv;
		}
	}

	/**
	 * Set the value of a query variable.
	 *
	 * @since 2.3.0
	 *
	 * @param string $key   Query variable name.
	 * @param mixed  $value Query variable value.
	 */
	public function set_query_var(string $key, mixed $value): void {
		$this->query_vars[$key] = $value;
	}

	/**
	 * Parse request to find correct WordPress query.
	 *
	 * @since 2.0.0
	 *
	 * @param array|string $extra_query_vars Set the extra query variables.
	 */
	public function parse_request(array|string $extra_query_vars = ''): void {
		global $wp_rewrite;

		if (! apply_filters('do_parse_request', true, $this, $extra_query_vars)) {
			return;
		}

		$this->query_vars       = [];
		$post_type_query_vars   = [];
		$perma_query_vars       = [];
		$pathinfo               = '';
		$request                = '';
		$request_match          = '';
		$matches                = [];

		if (is_array($extra_query_vars)) {
			$this->extra_query_vars =& $extra_query_vars;
		} elseif (! empty($extra_query_vars)) {
			parse_str($extra_query_vars, $this->extra_query_vars);
		}

		$rewrite = $wp_rewrite->wp_rewrite_rules();

		if (! empty($rewrite)) {
			$error             = '404';
			$this->did_permalink = true;

			$pathinfo = $_SERVER['PATH_INFO'] ?? '';
			[$pathinfo] = explode('?', $pathinfo);
			$pathinfo = str_replace('%', '%25', $pathinfo);

			[$req_uri] = explode('?', $_SERVER['REQUEST_URI'] ?? '');
			$self      = $_SERVER['PHP_SELF'] ?? '';
			$home_path = trim((string) parse_url(home_url(), PHP_URL_PATH), '/');

			$req_uri  = str_replace($pathinfo, '', $req_uri);
			$req_uri  = trim($req_uri, '/');
			$req_uri  = preg_replace("|^$home_path|i", '', $req_uri);
			$req_uri  = trim($req_uri, '/');
			$pathinfo = trim($pathinfo, '/');
			$pathinfo = preg_replace("|^$home_path|i", '', $pathinfo);
			$pathinfo = trim($pathinfo, '/');
			$self     = trim($self, '/');
			$self     = preg_replace("|^$home_path|i", '', $self);
			$self     = trim($self, '/');

			if (! empty($pathinfo) && ! preg_match('|^.*' . $wp_rewrite->index . '$|', $pathinfo)) {
				$request = $pathinfo;
			} else {
				if ($req_uri === $wp_rewrite->index) {
					$req_uri = '';
				}
				$request = $req_uri;
			}

			$this->request = $request;
			$request_match = $request;

			if (empty($request_match)) {
				if (isset($rewrite['$'])) {
					$this->matched_rule = '$';
					$query              = $rewrite['$'];
					$matches            = [''];
				}
			} else {
				foreach ((array) $rewrite as $match => $query) {
					if (! empty($req_uri) && str_starts_with($match, $req_uri) && $req_uri !== $request) {
						$request_match = $req_uri . '/' . $request;
					}

					if (preg_match("#^$match#", $request_match, $matches) || preg_match("#^$match#", urldecode($request_match), $matches)) {
						if ($wp_rewrite->use_verbose_page_rules && preg_match('/pagename=\$matches\[([0-9]+)\]/', $query, $varmatch)) {
							if (! get_page_by_path($matches[$varmatch[1]])) {
								continue;
							}
						}

						$this->matched_rule = $match;
						break;
					}
				}
			}

			if (null !== $this->matched_rule) {
				$query = preg_replace('!^.+\?!', '', $query);
				$query = addslashes(WP_MatchesMapRegex::apply($query, $matches));

				$this->matched_query = $query;

				parse_str($query, $perma_query_vars);

				if ('404' === $error) {
					unset($error, $_GET['error']);
				}
			}

			if (empty($request) || $req_uri === $self || str_contains($_SERVER['PHP_SELF'] ?? '', 'wp-admin/')) {
				unset($error, $_GET['error']);

				if (! empty($perma_query_vars) && str_contains($_SERVER['PHP_SELF'] ?? '', 'wp-admin/')) {
					$perma_query_vars = [];
				}

				$this->did_permalink = false;
			}
		}

		$this->public_query_vars = apply_filters('query_vars', $this->public_query_vars);

		foreach (get_post_types([], 'objects') as $post_type => $post_type_object) {
			if ($post_type_object->query_var) {
				$post_type_query_vars[$post_type_object->query_var] = $post_type;
			}
		}

		foreach ($this->public_query_vars as $wpvar) {
			if (isset($this->extra_query_vars[$wpvar])) {
				$this->query_vars[$wpvar] = $this->extra_query_vars[$wpvar];
			} elseif (isset($_POST[$wpvar])) {
				$this->query_vars[$wpvar] = $_POST[$wpvar];
			} elseif (isset($_GET[$wpvar])) {
				$this->query_vars[$wpvar] = $_GET[$wpvar];
			} elseif (isset($perma_query_vars[$wpvar])) {
				$this->query_vars[$wpvar] = $perma_query_vars[$wpvar];
			}

			if (! empty($this->query_vars[$wpvar])) {
				if (! is_array($this->query_vars[$wpvar])) {
					$this->query_vars[$wpvar] = (string) $this->query_vars[$wpvar];
				} else {
					foreach ($this->query_vars[$wpvar] as $vkey => $value) {
						if (! is_object($value)) {
							$this->query_vars[$wpvar][$vkey] = (string) $value;
						}
					}
				}

				if (isset($post_type_query_vars[$wpvar])) {
					$this->query_vars['post_type'] = $post_type_query_vars[$wpvar];
					$this->query_vars['name']      = $this->query_vars[$wpvar];
				}
			}
		}

		foreach (get_taxonomies([], 'objects') as $taxonomy => $taxonomy_object) {
			if ($taxonomy_object->query_var && isset($this->query_vars[$taxonomy_object->query_var])) {
				$this->query_vars[$taxonomy_object->query_var] = str_replace(' ', '+', $this->query_vars[$taxonomy_object->query_var]);
			}
		}

		if (isset($this->query_vars['post_type'])) {
			$queryable_post_types = get_post_types(['publicly_queryable' => true]);
			if (! is_array($this->query_vars['post_type'])) {
				if (! in_array($this->query_vars['post_type'], $queryable_post_types, true)) {
					unset($this->query_vars['post_type']);
				}
			} else {
				$this->query_vars['post_type'] = array_intersect($this->query_vars['post_type'], $queryable_post_types);
			}
		}

		foreach ((array) $this->private_query_vars as $var) {
			if (isset($this->extra_query_vars[$var])) {
				$this->query_vars[$var] = $this->extra_query_vars[$var];
			}
		}

		if (isset($error)) {
			$this->query_vars['error'] = $error;
		}

		$this->query_vars = apply_filters('request', $this->query_vars);

		do_action_ref_array('parse_request', [ &$this ]);
	}

	/**
	 * Send additional HTTP headers for caching, content type, etc.
	 *
	 * @since 2.0.0
	 */
	public function send_headers(): void {
		$headers       = ['X-Pingback' => get_bloginfo('pingback_url')];
		$status        = null;
		$exit_required = false;

		if (is_user_logged_in()) {
			$headers = array_merge($headers, wp_get_nocache_headers());
		}

		if (! empty($this->query_vars['error'])) {
			$status = (int) $this->query_vars['error'];
			if (404 === $status) {
				if (! is_user_logged_in()) {
					$headers = array_merge($headers, wp_get_nocache_headers());
				}
				$headers['Content-Type'] = get_option('html_type') . '; charset=' . get_option('blog_charset');
			} elseif (in_array($status, [403, 500, 502, 503], true)) {
				$exit_required = true;
			}
		} elseif (empty($this->query_vars['feed'])) {
			$headers['Content-Type'] = get_option('html_type') . '; charset=' . get_option('blog_charset');
		} else {
			if (
				! empty($this->query_vars['withcomments'])
				|| str_contains($this->query_vars['feed'], 'comments-')
				|| (
					empty($this->query_vars['withoutcomments'])
					&& (
						! empty($this->query_vars['p'])
						|| ! empty($this->query_vars['name'])
						|| ! empty($this->query_vars['page_id'])
						|| ! empty($this->query_vars['pagename'])
						|| ! empty($this->query_vars['attachment'])
						|| ! empty($this->query_vars['attachment_id'])
					)
				)
			) {
				$wp_last_modified = mysql2date('D, d M Y H:i:s', get_lastcommentmodified('GMT'), 0) . ' GMT';
			} else {
				$wp_last_modified = mysql2date('D, d M Y H:i:s', get_lastpostmodified('GMT'), 0) . ' GMT';
			}

			$wp_etag                = '"' . md5($wp_last_modified) . '"';
			$headers['Last-Modified'] = $wp_last_modified;
			$headers['ETag']           = $wp_etag;

			$client_etag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? wp_unslash($_SERVER['HTTP_IF_NONE_MATCH']) : false;
			$client_last_modified      = empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? '' : trim((string) $_SERVER['HTTP_IF_MODIFIED_SINCE']);
			$client_modified_timestamp = $client_last_modified ? (strtotime($client_last_modified) ?: 0) : 0;
			$wp_modified_timestamp     = strtotime($wp_last_modified) ?: 0;

			if ($client_last_modified && $client_etag) {
				if ($client_modified_timestamp >= $wp_modified_timestamp && $client_etag === $wp_etag) {
					$status        = 304;
					$exit_required = true;
				}
			} else {
				if ($client_modified_timestamp >= $wp_modified_timestamp || ($client_etag && $client_etag === $wp_etag)) {
					$status        = 304;
					$exit_required = true;
				}
			}
		}

		$headers = apply_filters('wp_headers', $headers, $this);

		if (! empty($status)) {
			status_header($status);
		}

		if (isset($headers['Last-Modified']) && false === $headers['Last-Modified']) {
			unset($headers['Last-Modified']);

			if (function_exists('header_remove')) {
				header_remove('Last-Modified');
			} else {
				foreach (headers_list() as $header) {
					if (stripos($header, 'Last-Modified') === 0) {
						$headers['Last-Modified'] = '';
						break;
					}
				}
			}
		}

		foreach ((array) $headers as $name => $field_value) {
			@header("{$name}: {$field_value}");
		}

		if ($exit_required) {
			exit;
		}

		do_action_ref_array('send_headers', [ &$this ]);
	}

	/**
	 * Sets the query string property based off of the query variable property.
	 *
	 * @since 2.0.0
	 */
	public function build_query_string(): void {
		$this->query_string = '';
		foreach (array_keys($this->query_vars) as $wpvar) {
			if ($this->query_vars[$wpvar] !== '') {
				$this->query_string .= ($this->query_string === '') ? '' : '&';
				if (! is_scalar($this->query_vars[$wpvar])) {
					continue;
				}
				$this->query_string .= $wpvar . '=' . rawurlencode((string) $this->query_vars[$wpvar]);
			}
		}

		if (has_filter('query_string')) {
			$this->query_string = apply_filters('query_string', $this->query_string);
			parse_str($this->query_string, $this->query_vars);
		}
	}

	/**
	 * Set up the WordPress Globals.
	 *
	 * @since 2.0.0
	 */
	public function register_globals(): void {
		global $wp_query;

		foreach ((array) $wp_query->query_vars as $key => $value) {
			$GLOBALS[$key] = $value;
		}

		$GLOBALS['query_string'] = $this->query_string;
		$GLOBALS['posts']        = &$wp_query->posts;
		$GLOBALS['post']         = $wp_query->post ?? null;
		$GLOBALS['request']      = $wp_query->request;

		if ($wp_query->is_single() || $wp_query->is_page()) {
			$GLOBALS['more']   = 1;
			$GLOBALS['single'] = 1;
		}

		if ($wp_query->is_author() && isset($wp_query->post)) {
			$GLOBALS['authordata'] = get_userdata($wp_query->post->post_author);
		}
	}

	/**
	 * Set up the current user.
	 *
	 * @since 2.0.0
	 */
	public function init(): void {
		wp_get_current_user();
	}

	/**
	 * Set up the Loop based on the query variables.
	 *
	 * @since 2.0.0
	 */
	public function query_posts(): void {
		global $wp_the_query;

		$this->build_query_string();
		$wp_the_query->query($this->query_vars);
	}

	/**
	 * Set the Headers for 404, if nothing is found for requested URL.
	 *
	 * @since 2.0.0
	 */
	public function handle_404(): void {
		global $wp_query;

		if (is_404()) {
			return;
		}

		if (is_admin() || is_robots() || $wp_query->posts) {
			status_header(200);
			return;
		}

		if (! is_paged()) {
			$author = get_query_var('author');

			if (is_author() && is_numeric($author) && (int) $author > 0 && is_user_member_of_blog((int) $author)) {
				status_header(200);
				return;
			}

			if ((is_tag() || is_category() || is_tax() || is_post_type_archive()) && get_queried_object()) {
				status_header(200);
				return;
			}

			if (is_home() || is_search() || is_feed()) {
				status_header(200);
				return;
			}
		}

		$wp_query->set_404();
		status_header(404);
		nocache_headers();
	}

	/**
	 * Sets up all of the variables required by the WordPress environment.
	 *
	 * @since 2.0.0
	 *
	 * @param string|array $query_args Passed to {@link parse_request()}.
	 */
	public function main(array|string $query_args = ''): void {
		$this->init();
		$this->parse_request($query_args);
		$this->send_headers();
		$this->query_posts();
		$this->handle_404();
		$this->register_globals();

		do_action_ref_array('wp', [ &$this ]);
	}

}

/**
 * Helper class to remove the need to use eval to replace $matches[] in query strings.
 *
 * @since 2.9.0
 */
class WP_MatchesMapRegex {
	/**
	 * Store for matches.
	 *
	 * @access private
	 * @var array
	 */
	private array $_matches;

	/**
	 * Store for mapping result.
	 *
	 * @access public
	 * @var string
	 */
	public string $output;

	/**
	 * Subject to perform mapping on.
	 *
	 * @access private
	 * @var string
	 */
	private string $_subject;

	/**
	 * Regexp pattern to match $matches[] references.
	 *
	 * @var string
	 */
	public string $_pattern = '(\$matches\[[1-9]+[0-9]*\])';

	/**
	 * Make private properties readable for backwards compatibility.
	 *
	 * @since 4.0.0
	 *
	 * @param string $name Property to get.
	 * @return mixed Property.
	 */
	public function __get(string $name): mixed {
		return $this->$name;
	}

	/**
	 * Make private properties settable for backwards compatibility.
	 *
	 * @since 4.0.0
	 *
	 * @param string $name  Property to set.
	 * @param mixed  $value Property value.
	 * @return mixed Newly-set property.
	 */
	public function __set(string $name, mixed $value): mixed {
		$this->$name = $value;
		return $value;
	}

	/**
	 * Make private properties checkable for backwards compatibility.
	 *
	 * @since 4.0.0
	 *
	 * @param string $name Property to check if set.
	 * @return bool Whether the property is set.
	 */
	public function __isset(string $name): bool {
		return isset($this->$name);
	}

	/**
	 * Make private properties un-settable for backwards compatibility.
	 *
	 * @since 4.0.0
	 *
	 * @param string $name Property to unset.
	 */
	public function __unset(string $name): void {
		unset($this->$name);
	}

	/**
	 * Make private/protected methods readable for backwards compatibility.
	 *
	 * @since 4.0.0
	 *
	 * @param callable $name      Method to call.
	 * @param array    $arguments Arguments to pass when calling.
	 * @return mixed|bool Return value of the callback, false otherwise.
	 */
	public function __call(string $name, array $arguments): mixed {
		if (! method_exists($this, $name)) {
			return false;
		}

		return $this->$name(...$arguments);
	}

	/**
	 * Constructor.
	 *
	 * @param string $subject Subject of regex.
	 * @param array  $matches Data to use in map.
	 */
	public function __construct(string $subject, array $matches) {
		$this->_subject = $subject;
		$this->_matches = $matches;
		$this->output   = $this->_map();
	}

	/**
	 * Substitute substring matches in subject.
	 *
	 * @param string $subject Subject.
	 * @param array  $matches Data used for substitution.
	 * @return string
	 */
	public static function apply(string $subject, array $matches): string {
		$instance = new self($subject, $matches);
		return $instance->output;
	}

	/**
	 * Do the actual mapping.
	 *
	 * @access private
	 * @return string
	 */
	private function _map(): string {
		return preg_replace_callback($this->_pattern, [$this, 'callback'], $this->_subject);
	}

	/**
	 * preg_replace_callback hook.
	 *
	 * @access public
	 * @param  array $matches preg_replace regexp matches.
	 * @return string
	 */
	public function callback(array $matches): string {
		$index = (int) substr($matches[0], 9, -1);
		return isset($this->_matches[$index]) ? urlencode((string) $this->_matches[$index]) : '';
	}

}
?>