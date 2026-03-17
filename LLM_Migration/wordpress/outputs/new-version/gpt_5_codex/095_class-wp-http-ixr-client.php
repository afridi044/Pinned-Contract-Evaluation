<?php

class WP_HTTP_IXR_Client extends IXR_Client
{
    public function __construct(string $server, string|false $path = false, int|false $port = false, int $timeout = 15)
    {
        if ($path === false) {
            $bits         = parse_url($server) ?: [];
            $this->scheme = $bits['scheme'] ?? 'http';
            $this->server = $bits['host'] ?? '';
            $this->port   = $bits['port'] ?? $port;
            $this->path   = !empty($bits['path']) ? $bits['path'] : '/';

            if (!$this->path) {
                $this->path = '/';
            }

            if (!empty($bits['query'])) {
                $this->path .= '?' . $bits['query'];
            }
        } else {
            $this->scheme = 'http';
            $this->server = $server;
            $this->path   = $path;
            $this->port   = $port;
        }

        $this->useragent = 'The Incutio XML-RPC PHP Library';
        $this->timeout   = $timeout;
    }

    public function query(string $method, mixed ...$parameters): bool
    {
        $request = new IXR_Request($method, $parameters);
        $xml     = $request->getXml();

        $port = $this->port ? ":{$this->port}" : '';
        $url  = "{$this->scheme}://{$this->server}{$port}{$this->path}";

        $args = [
            'headers'    => ['Content-Type' => 'text/xml'],
            'user-agent' => $this->useragent,
            'body'       => $xml,
        ];

        foreach ($this->headers ?? [] as $header => $value) {
            $args['headers'][$header] = $value;
        }

        if ($this->timeout !== false) {
            $args['timeout'] = $this->timeout;
        }

        if ($this->debug) {
            echo '<pre class="ixr_request">' . htmlspecialchars($xml) . "\n</pre>\n\n";
        }

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            $errno          = $response->get_error_code();
            $errorstr       = $response->get_error_message();
            $this->error    = new IXR_Error(-32300, "transport error: {$errno} {$errorstr}");
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200) {
            $this->error = new IXR_Error(-32301, 'transport error - HTTP status code was not 200 (' . $response_code . ')');
            return false;
        }

        $body = wp_remote_retrieve_body($response);

        if ($this->debug) {
            echo '<pre class="ixr_response">' . htmlspecialchars($body) . "\n</pre>\n\n";
        }

        $this->message = new IXR_Message($body);

        if (!$this->message->parse()) {
            $this->error = new IXR_Error(-32700, 'parse error. not well formed');
            return false;
        }

        if ($this->message->messageType === 'fault') {
            $this->error = new IXR_Error($this->message->faultCode, $this->message->faultString);
            return false;
        }

        return true;
    }
}
?>