<?php

declare(strict_types=1);

/**
 * WP_HTTP_IXR_Client
 *
 * @package WordPress
 * @since 3.1.0
 *
 */
class WP_HTTP_IXR_Client extends IXR_Client
{
    /**
     * The connection scheme, e.g., 'http' or 'https'.
     */
    public readonly string $scheme;

    /**
     * The server host to connect to.
     */
    public readonly string $server;

    /**
     * The port to connect to.
     */
    public readonly int|false $port;

    /**
     * The path to the XML-RPC server.
     */
    public readonly string $path;

    /**
     * The user-agent string to send in the request.
     */
    public readonly string $useragent;

    /**
     * The request timeout in seconds.
     */
    public readonly int|false $timeout;

    /**
     * Custom headers to send with the request.
     * @var array<string, string>
     */
    public array $headers = [];

    /**
     * Flag to enable debug output.
     */
    public bool $debug = false;

    /**
     * The last error object, if any.
     */
    public IXR_Error|false $error = false;

    /**
     * The last response message object.
     */
    public IXR_Message|false $message = false;

    public function __construct(string $server, string|false $path = false, int|false $port = false, int|false $timeout = 15)
    {
        $this->useragent = 'The Incutio XML-RPC PHP Library';
        $this->timeout = $timeout;

        if (!$path) {
            // Assume we have been given a full URL.
            $bits = parse_url($server);
            $this->scheme = $bits['scheme'] ?? 'http';
            $this->server = $bits['host'] ?? '';
            $this->port = $bits['port'] ?? $port;

            // Combine path and query string, ensuring a root path if none exists.
            $this->path = ($bits['path'] ?? '/') . (isset($bits['query']) ? '?' . $bits['query'] : '');
        } else {
            $this->scheme = 'http';
            $this->server = $server;
            $this->path = $path;
            $this->port = $port;
        }
    }

    public function query(string $method, mixed ...$args): bool
    {
        $request = new IXR_Request($method, $args);
        $xml = $request->getXml();

        $port = $this->port ? ":{$this->port}" : '';
        $url = "{$this->scheme}://{$this->server}{$port}{$this->path}";

        $httpArgs = [
            'headers'    => ['Content-Type' => 'text/xml', ...$this->headers],
            'user-agent' => $this->useragent,
            'body'       => $xml,
        ];

        if ($this->timeout !== false) {
            $httpArgs['timeout'] = $this->timeout;
        }

        // Now send the request.
        if ($this->debug) {
            echo '<pre class="ixr_request">' . htmlspecialchars($xml) . "\n</pre>\n\n";
        }

        $response = wp_remote_post($url, $httpArgs);

        if (is_wp_error($response)) {
            $errno = $response->get_error_code();
            $errorstr = $response->get_error_message();
            $this->error = new IXR_Error(-32300, "transport error: {$errno} {$errorstr}");
            return false;
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        if (200 !== $responseCode) {
            $this->error = new IXR_Error(-32301, "transport error - HTTP status code was not 200 ({$responseCode})");
            return false;
        }

        $responseBody = wp_remote_retrieve_body($response);
        if ($this->debug) {
            echo '<pre class="ixr_response">' . htmlspecialchars($responseBody) . "\n</pre>\n\n";
        }

        // Now parse what we've got back.
        $this->message = new IXR_Message($responseBody);
        if (!$this->message->parse()) {
            // XML error.
            $this->error = new IXR_Error(-32700, 'parse error. not well formed');
            return false;
        }

        // Is the message a fault?
        if ($this->message->messageType === 'fault') {
            $this->error = new IXR_Error($this->message->faultCode, $this->message->faultString);
            return false;
        }

        // Message must be OK.
        return true;
    }
}