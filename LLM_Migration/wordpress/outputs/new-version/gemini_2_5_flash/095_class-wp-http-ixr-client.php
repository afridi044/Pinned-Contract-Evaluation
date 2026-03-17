<?php
/**
 * WP_HTTP_IXR_Client
 *
 * @package WordPress
 * @since 3.1.0
 *
 */
class WP_HTTP_IXR_Client extends IXR_Client {

	// Declare properties explicitly with types and visibility.
	// Assuming public visibility as per legacy implicit behavior or parent class.
	public string $scheme;
	public string $server;
	public int|false $port;
	public string $path;
	public string $useragent;
	public int|false $timeout;
	public ?IXR_Error $error = null;
	public ?IXR_Message $message = null;

	// These properties are used in query() but not initialized in __construct.
	// They are likely inherited from IXR_Client or implicitly created in legacy PHP.
	// Declaring them explicitly to avoid dynamic property warnings in PHP 8.2+.
	public bool $debug = false;
	public array $headers = [];

	/**
	 * Constructor for WP_HTTP_IXR_Client.
	 *
	 * @param string      $server  The server URL or hostname.
	 * @param string|false $path    Optional. The path to the XML-RPC endpoint. Default false.
	 * @param int|false    $port    Optional. The port number. Default false.
	 * @param int         $timeout Optional. The timeout in seconds. Default 15.
	 */
	public function __construct(string $server, string|false $path = false, int|false $port = false, int $timeout = 15) {
		// The original code does not call parent::__construct().
		// Maintaining this behavior for functional equivalence.

		if (false === $path) {
			$bits = parse_url($server);

			// Handle potential parse_url failures or missing keys.
			// The original code would have triggered E_NOTICE for undefined array keys
			// if $bits was false or keys were missing.
			// We'll provide sensible defaults to prevent errors while maintaining
			// similar functional behavior.
			if (false === $bits) {
				// If parsing fails entirely, fall back to basic defaults.
				$this->scheme    = 'http';
				$this->server    = $server; // Use the original server string as host.
				$this->port      = $port;
				$this->path      = '/';
			} else {
				$this->scheme    = $bits['scheme'] ?? 'http';
				$this->server    = $bits['host'] ?? $server; // Fallback to original $server if host missing.
				$this->port      = $bits['port'] ?? $port;
				$this->path      = $bits['path'] ?? '/';

				if (!empty($bits['query'])) {
					$this->path .= '?' . $bits['query'];
				}
			}
		} else {
			$this->scheme = 'http';
			$this->server = $server;
			$this->path = $path;
			$this->port = $port;
		}
		$this->useragent = 'The Incutio XML-RPC PHP Library';
		$this->timeout = $timeout;
	}

	/**
	 * Sends an XML-RPC query to the server.
	 *
	 * @param string $method The XML-RPC method to call.
	 * @param mixed  ...$args The arguments for the XML-RPC method.
	 * @return bool True on success, false on failure.
	 */
	public function query(string $method, ...$args): bool {
		$request = new IXR_Request($method, $args);
		$xml = $request->getXml();

		$port_suffix = (false !== $this->port) ? ':' . $this->port : '';
		$url = $this->scheme . '://' . $this->server . $port_suffix . $this->path;

		$request_args = [ // Use short array syntax.
			'headers'    => ['Content-Type' => 'text/xml'],
			'user-agent' => $this->useragent,
			'body'       => $xml,
		];

		// Merge Custom headers ala #8145.
		foreach ($this->headers as $header => $value) { // Use block statement.
			$request_args['headers'][$header] = $value;
		}

		if (false !== $this->timeout) { // Use strict comparison.
			$request_args['timeout'] = $this->timeout;
		}

		// Now send the request.
		if ($this->debug) { // Use block statement.
			echo '<pre class="ixr_request">' . htmlspecialchars($xml) . "\n</pre>\n\n";
		}

		// Assuming wp_remote_post, is_wp_error, wp_remote_retrieve_response_code,
		// and wp_remote_retrieve_body are globally available WordPress functions.
		$response = wp_remote_post($url, $request_args);

		if (is_wp_error($response)) {
			$errno    = $response->get_error_code();
			$errorstr = $response->get_error_message();
			$this->error = new IXR_Error(-32300, "transport error: {$errno} {$errorstr}"); // Use curly braces for string interpolation.
			return false;
		}

		if (200 !== wp_remote_retrieve_response_code($response)) { // Use strict comparison.
			$this->error = new IXR_Error(-32301, 'transport error - HTTP status code was not 200 (' . wp_remote_retrieve_response_code($response) . ')');
			return false;
		}

		if ($this->debug) { // Use block statement.
			echo '<pre class="ixr_response">' . htmlspecialchars(wp_remote_retrieve_body($response)) . "\n</pre>\n\n";
		}

		// Now parse what we've got back.
		$this->message = new IXR_Message(wp_remote_retrieve_body($response));
		if (!$this->message->parse()) {
			// XML error.
			$this->error = new IXR_Error(-32700, 'parse error. not well formed');
			return false;
		}

		// Is the message a fault?
		if ('fault' === $this->message->messageType) { // Use strict comparison.
			$this->error = new IXR_Error($this->message->faultCode, $this->message->faultString);
			return false;
		}

		// Message must be OK.
		return true;
	}
}