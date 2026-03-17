<?php
/**
 * IXR - The Incutio XML-RPC Library
 *
 * Copyright (c) 2010, Incutio Ltd.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  - Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *  - Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *  - Neither the name of Incutio Ltd. nor the names of its contributors
 *    may be used to endorse or promote products derived from this software
 *    without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS
 * IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO,
 * THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR
 * CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
 * OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
 * USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @package IXR
 * @since 1.5.0
 *
 * @copyright  Incutio Ltd 2010 (http://www.incutio.com)
 * @version    1.7.4 7th September 2010
 * @author     Simon Willison
 * @link       http://scripts.incutio.com/xmlrpc/ Site/manual
 * @license    http://www.opensource.org/licenses/bsd-license.php BSD
 */

/**
 * IXR_Value
 *
 * @package IXR
 * @since 1.5.0
 */
class IXR_Value {
    public mixed $data;
    public string $type;

    public function __construct(mixed $data, ?string $type = null)
    {
        $this->data = $data;
        if ($type === null) {
            $type = $this->calculateType();
        }
        $this->type = $type;
        if ($type === 'struct' && is_array($this->data)) {
            foreach ($this->data as $key => $value) {
                $this->data[$key] = new self($value);
            }
        }
        if ($type === 'array' && is_array($this->data)) {
            foreach ($this->data as $key => $value) {
                $this->data[$key] = new self($value);
            }
        }
    }

    public function calculateType(): string
    {
        if (is_bool($this->data)) {
            return 'boolean';
        }
        if (is_int($this->data)) {
            return 'int';
        }
        if (is_float($this->data)) {
            return 'double';
        }

        if ($this->data instanceof IXR_Date) {
            return 'date';
        }
        if ($this->data instanceof IXR_Base64) {
            return 'base64';
        }

        if (is_object($this->data)) {
            $this->data = get_object_vars($this->data);
            return 'struct';
        }
        if (!is_array($this->data)) {
            return 'string';
        }

        return $this->isStruct($this->data) ? 'struct' : 'array';
    }

    public function getXml(): string|false
    {
        switch ($this->type) {
            case 'boolean':
                return '<boolean>' . ($this->data ? '1' : '0') . '</boolean>';
            case 'int':
                return '<int>' . $this->data . '</int>';
            case 'double':
                return '<double>' . $this->data . '</double>';
            case 'string':
                return '<string>' . htmlspecialchars((string) $this->data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</string>';
            case 'array':
                $return = "<array><data>\n";
                if (is_array($this->data)) {
                    foreach ($this->data as $item) {
                        $return .= '  <value>' . $item->getXml() . "</value>\n";
                    }
                }
                $return .= '</data></array>';
                return $return;
            case 'struct':
                $return = "<struct>\n";
                if (is_array($this->data)) {
                    foreach ($this->data as $name => $value) {
                        $name = htmlspecialchars((string) $name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $return .= "  <member><name>{$name}</name><value>";
                        $return .= $value->getXml() . "</value></member>\n";
                    }
                }
                $return .= '</struct>';
                return $return;
            case 'date':
            case 'base64':
                return $this->data->getXml();
        }
        return false;
    }

    /**
     * Checks whether or not the supplied array is a struct or not
     *
     * @param unknown_type $array
     * @return boolean
     */
    public function isStruct(array $array): bool
    {
        $expected = 0;
        foreach ($array as $key => $value) {
            if ((string) $key !== (string) $expected) {
                return true;
            }
            $expected++;
        }
        return false;
    }
}

/**
 * IXR_MESSAGE
 *
 * @package IXR
 * @since 1.5.0
 *
 */
class IXR_Message
{
    public string $message;
    public ?string $messageType = null;
    public ?int $faultCode = null;
    public ?string $faultString = null;
    public ?string $methodName = null;
    public array $params = [];

    public array $_arraystructs = array();
    public array $_arraystructstypes = array();
    public array $_currentStructName = array();
    public mixed $_param = null;
    public mixed $_value = null;
    public ?string $_currentTag = null;
    public string $_currentTagContents = '';
    public ?string $currentTag = null;
    public $ _parser = null;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    public function parse(): bool
    {
        $header = preg_replace('/<\?xml.*?\?' . '>/s', '', substr($this->message, 0, 100), 1);
        $this->message = trim(substr_replace($this->message, (string) $header, 0, 100));
        if ($this->message === '') {
            return false;
        }

        $header = preg_replace('/^<!DOCTYPE[^>]*+>/i', '', substr($this->message, 0, 200), 1);
        $this->message = trim(substr_replace($this->message, (string) $header, 0, 200));
        if ($this->message === '') {
            return false;
        }

        $root_tag = substr($this->message, 0, strcspn(substr($this->message, 0, 20), "> \t\r\n"));
        if ('<!DOCTYPE' === strtoupper($root_tag)) {
            return false;
        }
        if (!in_array($root_tag, array('<methodCall', '<methodResponse', '<fault'), true)) {
            return false;
        }

        $element_limit = 30000;
        if (function_exists('apply_filters')) {
            $element_limit = apply_filters('xmlrpc_element_limit', $element_limit);
        }
        if ($element_limit && (2 * $element_limit) < substr_count($this->message, '<')) {
            return false;
        }

        $this->_parser = xml_parser_create();
        xml_parser_set_option($this->_parser, XML_OPTION_CASE_FOLDING, false);
        xml_set_object($this->_parser, $this);
        xml_set_element_handler($this->_parser, array($this, 'tag_open'), array($this, 'tag_close'));
        xml_set_character_data_handler($this->_parser, array($this, 'cdata'));
        $chunk_size = 262144;
        $final = false;
        do {
            if (strlen($this->message) <= $chunk_size) {
                $final = true;
            }
            $part = substr($this->message, 0, $chunk_size);
            $this->message = substr($this->message, $chunk_size);
            if (!xml_parse($this->_parser, $part, (int) $final)) {
                xml_parser_free($this->_parser);
                return false;
            }
            if ($final) {
                break;
            }
        } while (true);
        xml_parser_free($this->_parser);

        if ($this->messageType === 'fault') {
            $this->faultCode = $this->params[0]['faultCode'];
            $this->faultString = $this->params[0]['faultString'];
        }
        return true;
    }

    public function tag_open($parser, string $tag, array $attr): void
    {
        $this->_currentTagContents = '';
        $this->_currentTag = $tag;
        $this->currentTag = $tag;
        switch ($tag) {
            case 'methodCall':
            case 'methodResponse':
            case 'fault':
                $this->messageType = $tag;
                break;
            case 'data':
                $this->_arraystructstypes[] = 'array';
                $this->_arraystructs[] = array();
                break;
            case 'struct':
                $this->_arraystructstypes[] = 'struct';
                $this->_arraystructs[] = array();
                break;
        }
    }

    public function cdata($parser, string $cdata): void
    {
        $this->_currentTagContents .= $cdata;
    }

    public function tag_close($parser, string $tag): void
    {
        $valueFlag = false;
        $value = null;
        switch ($tag) {
            case 'int':
            case 'i4':
                $value = (int) trim($this->_currentTagContents);
                $valueFlag = true;
                break;
            case 'double':
                $value = (float) trim($this->_currentTagContents);
                $valueFlag = true;
                break;
            case 'string':
                $value = (string) trim($this->_currentTagContents);
                $valueFlag = true;
                break;
            case 'dateTime.iso8601':
                $value = new IXR_Date(trim($this->_currentTagContents));
                $valueFlag = true;
                break;
            case 'value':
                if (trim($this->_currentTagContents) !== '') {
                    $value = (string) $this->_currentTagContents;
                    $valueFlag = true;
                }
                break;
            case 'boolean':
                $value = (bool) trim($this->_currentTagContents);
                $valueFlag = true;
                break;
            case 'base64':
                $value = base64_decode($this->_currentTagContents);
                $valueFlag = true;
                break;
            case 'data':
            case 'struct':
                $value = array_pop($this->_arraystructs);
                array_pop($this->_arraystructstypes);
                $valueFlag = true;
                break;
            case 'member':
                array_pop($this->_currentStructName);
                break;
            case 'name':
                $this->_currentStructName[] = trim($this->_currentTagContents);
                break;
            case 'methodName':
                $this->methodName = trim($this->_currentTagContents);
                break;
        }

        if ($valueFlag) {
            if (!empty($this->_arraystructs)) {
                $lastTypeIndex = array_key_last($this->_arraystructstypes);
                $lastStructIndex = array_key_last($this->_arraystructs);
                if ($lastStructIndex !== null) {
                    if ($lastTypeIndex !== null && $this->_arraystructstypes[$lastTypeIndex] === 'struct') {
                        $currentStructNameIndex = array_key_last($this->_currentStructName);
                        if ($currentStructNameIndex !== null) {
                            $this->_arraystructs[$lastStructIndex][$this->_currentStructName[$currentStructNameIndex]] = $value;
                        }
                    } else {
                        $this->_arraystructs[$lastStructIndex][] = $value;
                    }
                }
            } else {
                $this->params[] = $value;
            }
        }
        $this->_currentTagContents = '';
    }
}

/**
 * IXR_Server
 *
 * @package IXR
 * @since 1.5.0
 */
class IXR_Server
{
    public mixed $data = null;
    public array $callbacks = array();
    public ?IXR_Message $message = null;
    public array $capabilities = array();

    public function __construct(array $callbacks = array(), mixed $data = null, bool $wait = false)
    {
        $this->setCapabilities();
        if (!empty($callbacks)) {
            $this->callbacks = $callbacks;
        }
        $this->setCallbacks();
        if (!$wait) {
            $this->serve($data);
        }
    }

    public function serve(?string $data = null): void
    {
        if ($data === null) {
            if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
                header('Content-Type: text/plain');
                exit('XML-RPC server accepts POST requests only.');
            }

            $rawPostData = file_get_contents('php://input');
            if ($rawPostData === false) {
                $rawPostData = '';
            }
            $data = $rawPostData;
        }
        $this->message = new IXR_Message($data);
        if (!$this->message->parse()) {
            $this->error(-32700, 'parse error. not well formed');
        }
        if ($this->message->messageType !== 'methodCall') {
            $this->error(-32600, 'server error. invalid xml-rpc. not conforming to spec. Request must be a methodCall');
        }
        $result = $this->call((string) $this->message->methodName, $this->message->params);

        if ($result instanceof IXR_Error) {
            $this->error($result);
        }

        $r = new IXR_Value($result);
        $resultxml = $r->getXml();

        $xml = <<<EOD
<methodResponse>
  <params>
    <param>
      <value>
      $resultxml
      </value>
    </param>
  </params>
</methodResponse>

EOD;
        $this->output($xml);
    }

    public function call(string $methodname, array $args)
    {
        if (!$this->hasMethod($methodname)) {
            return new IXR_Error(-32601, 'server error. requested method ' . $methodname . ' does not exist.');
        }
        $method = $this->callbacks[$methodname];

        if (count($args) === 1) {
            $args = $args[0];
        }

        if (is_string($method) && str_starts_with($method, 'this:')) {
            $method = substr($method, 5);
            if (!method_exists($this, $method)) {
                return new IXR_Error(-32601, 'server error. requested class method "' . $method . '" does not exist.');
            }

            return $this->{$method}($args);
        }

        if (is_array($method)) {
            if (!is_callable($method)) {
                return new IXR_Error(-32601, 'server error. requested object method "' . $method[1] . '" does not exist.');
            }
        } elseif (!function_exists($method)) {
            return new IXR_Error(-32601, 'server error. requested function "' . $method . '" does not exist.');
        }

        return call_user_func($method, $args);
    }

    public function error(IXR_Error|int $error, ?string $message = null): void
    {
        if ($message !== null && !($error instanceof IXR_Error)) {
            $error = new IXR_Error($error, $message);
        }
        if (!($error instanceof IXR_Error)) {
            $error = new IXR_Error(-32603, 'server error. unspecified');
        }
        $this->output($error->getXml());
    }

    public function output(string $xml): void
    {
        $charset = function_exists('get_option') ? (string) get_option('blog_charset') : '';
        if ($charset) {
            $xml = '<?xml version="1.0" encoding="' . $charset . "\"?>\n" . $xml;
        } else {
            $xml = "<?xml version=\"1.0\"?>\n" . $xml;
        }
        $length = strlen($xml);
        header('Connection: close');
        header('Content-Length: ' . $length);
        if ($charset) {
            header('Content-Type: text/xml; charset=' . $charset);
        } else {
            header('Content-Type: text/xml');
        }
        header('Date: ' . date('r'));
        echo $xml;
        exit;
    }

    public function hasMethod(string $method): bool
    {
        return array_key_exists($method, $this->callbacks);
    }

    public function setCapabilities(): void
    {
        $this->capabilities = array(
            'xmlrpc' => array(
                'specUrl' => 'http://www.xmlrpc.com/spec',
                'specVersion' => 1
        ),
            'faults_interop' => array(
                'specUrl' => 'http://xmlrpc-epi.sourceforge.net/specs/rfc.fault_codes.php',
                'specVersion' => 20010516
        ),
            'system.multicall' => array(
                'specUrl' => 'http://www.xmlrpc.com/discuss/msgReader$1208',
                'specVersion' => 1
        ),
        );
    }

    public function getCapabilities($args)
    {
        return $this->capabilities;
    }

    public function setCallbacks(): void
    {
        $this->callbacks['system.getCapabilities'] = 'this:getCapabilities';
        $this->callbacks['system.listMethods'] = 'this:listMethods';
        $this->callbacks['system.multicall'] = 'this:multiCall';
    }

    public function listMethods($args)
    {
        return array_reverse(array_keys($this->callbacks));
    }

    public function multiCall(array $methodcalls)
    {
        $return = array();
        foreach ($methodcalls as $call) {
            $method = $call['methodName'];
            $params = $call['params'];
            if ($method === 'system.multicall') {
                $result = new IXR_Error(-32600, 'Recursive calls to system.multicall are forbidden');
            } else {
                $result = $this->call($method, $params);
            }
            if ($result instanceof IXR_Error) {
                $return[] = array(
                    'faultCode' => $result->code,
                    'faultString' => $result->message
                );
            } else {
                $return[] = array($result);
            }
        }
        return $return;
    }
}

/**
 * IXR_Request
 *
 * @package IXR
 * @since 1.5.0
 */
class IXR_Request
{
    public string $method;
    public array $args;
    public string $xml;

    public function __construct(string $method, array $args)
    {
        $this->method = $method;
        $this->args = $args;
        $this->xml = <<<EOD
<?xml version="1.0"?>
<methodCall>
<methodName>{$this->method}</methodName>
<params>

EOD;
        foreach ($this->args as $arg) {
            $this->xml .= '<param><value>';
            $v = new IXR_Value($arg);
            $valueXml = $v->getXml();
            if ($valueXml !== false) {
                $this->xml .= $valueXml;
            }
            $this->xml .= "</value></param>\n";
        }
        $this->xml .= '</params></methodCall>';
    }

    public function getLength(): int
    {
        return strlen($this->xml);
    }

    public function getXml(): string
    {
        return $this->xml;
    }
}

/**
 * IXR_Client
 *
 * @package IXR
 * @since 1.5.0
 *
 */
class IXR_Client
{
    public string $server = '';
    public int $port = 80;
    public string $path = '/';
    public string $useragent = 'The Incutio XML-RPC PHP Library';
    public ?string $response = null;
    public ?IXR_Message $message = null;
    public bool $debug = false;
    public int $timeout = 15;
    public array $headers = array();

    public ?IXR_Error $error = null;

    public function __construct(string $server, ?string $path = null, int $port = 80, int $timeout = 15)
    {
        if (!$path) {
            $bits = parse_url($server);
            if ($bits !== false && isset($bits['host'])) {
                $this->server = $bits['host'];
                $this->port = isset($bits['port']) ? (int) $bits['port'] : 80;
                $this->path = $bits['path'] ?? '/';

                if (!$this->path) {
                    $this->path = '/';
                }

                if (!empty($bits['query'])) {
                    $this->path .= '?' . $bits['query'];
                }
            } else {
                $this->server = $server;
                $this->port = $port;
                $this->path = '/';
            }
        } else {
            $this->server = $server;
            $this->path = $path;
            $this->port = $port;
        }
        $this->timeout = $timeout;
    }

    public function query(string $method, mixed ...$args): bool
    {
        $this->error = null;
        $requestObject = new IXR_Request($method, $args);
        $length = $requestObject->getLength();
        $xml = $requestObject->getXml();
        $r = "\r\n";
        $httpRequest  = "POST {$this->path} HTTP/1.0{$r}";

        $this->headers['Host'] = $this->server;
        $this->headers['Content-Type'] = 'text/xml';
        $this->headers['User-Agent'] = $this->useragent;
        $this->headers['Content-Length'] = $length;

        foreach ($this->headers as $header => $value) {
            $httpRequest .= "{$header}: {$value}{$r}";
        }
        $httpRequest .= $r;

        $httpRequest .= $xml;

        if ($this->debug) {
            echo '<pre class="ixr_request">' . htmlspecialchars($httpRequest, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n</pre>\n\n";
        }

        if ($this->timeout > 0) {
            $fp = @fsockopen($this->server, $this->port, $errno, $errstr, $this->timeout);
        } else {
            $fp = @fsockopen($this->server, $this->port, $errno, $errstr);
        }
        if (!$fp) {
            $this->error = new IXR_Error(-32300, 'transport error - could not open socket');
            return false;
        }
        fwrite($fp, $httpRequest);
        $contents = '';
        $debugContents = '';
        $gotFirstLine = false;
        $gettingHeaders = true;
        while (!feof($fp)) {
            $line = fgets($fp, 4096);
            if ($line === false) {
                break;
            }
            if (!$gotFirstLine) {
                if (!str_contains($line, '200')) {
                    $this->error = new IXR_Error(-32300, 'transport error - HTTP status code was not 200');
                    fclose($fp);
                    return false;
                }
                $gotFirstLine = true;
            }
            if ($this->debug) {
                $debugContents .= $line;
            }
            if (trim($line) === '') {
                $gettingHeaders = false;
                continue;
            }
            if (!$gettingHeaders) {
                $contents .= $line;
            }
        }
        fclose($fp);
        if ($this->debug) {
            echo '<pre class="ixr_response">' . htmlspecialchars($debugContents, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n</pre>\n\n";
        }

        $this->message = new IXR_Message($contents);
        if (!$this->message->parse()) {
            $this->error = new IXR_Error(-32700, 'parse error. not well formed');
            return false;
        }

        if ($this->message->messageType === 'fault') {
            $this->error = new IXR_Error((int) $this->message->faultCode, (string) $this->message->faultString);
            return false;
        }

        return true;
    }

    public function getResponse(): mixed
    {
        return $this->message->params[0] ?? null;
    }

    public function isError(): bool
    {
        return ($this->error instanceof IXR_Error);
    }

    public function getErrorCode(): ?int
    {
        return $this->error?->code;
    }

    public function getErrorMessage(): ?string
    {
        return $this->error?->message;
    }
}


/**
 * IXR_Error
 *
 * @package IXR
 * @since 1.5.0
 */
class IXR_Error
{
    public int $code;
    public string $message;

    public function __construct(int $code, string $message)
    {
        $this->code = $code;
        $this->message = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function getXml(): string
    {
        $xml = <<<EOD
<methodResponse>
  <fault>
    <value>
      <struct>
        <member>
          <name>faultCode</name>
          <value><int>{$this->code}</int></value>
        </member>
        <member>
          <name>faultString</name>
          <value><string>{$this->message}</string></value>
        </member>
      </struct>
    </value>
  </fault>
</methodResponse>

EOD;
        return $xml;
    }
}

/**
 * IXR_Date
 *
 * @package IXR
 * @since 1.5.0
 */
class IXR_Date {
    public string $year;
    public string $month;
    public string $day;
    public string $hour;
    public string $minute;
    public string $second;
    public string $timezone = '';

    public function __construct(int|string $time)
    {
        if (is_numeric($time)) {
            $this->parseTimestamp((int) $time);
        } else {
            $this->parseIso((string) $time);
        }
    }

    public function parseTimestamp(int $timestamp): void
    {
        $this->year = date('Y', $timestamp);
        $this->month = date('m', $timestamp);
        $this->day = date('d', $timestamp);
        $this->hour = date('H', $timestamp);
        $this->minute = date('i', $timestamp);
        $this->second = date('s', $timestamp);
        $this->timezone = '';
    }

    public function parseIso(string $iso): void
    {
        $this->year = substr($iso, 0, 4);
        $this->month = substr($iso, 4, 2);
        $this->day = substr($iso, 6, 2);
        $this->hour = substr($iso, 9, 2);
        $this->minute = substr($iso, 12, 2);
        $this->second = substr($iso, 15, 2);
        $this->timezone = substr($iso, 17);
    }

    public function getIso(): string
    {
        return $this->year . $this->month . $this->day . 'T' . $this->hour . ':' . $this->minute . ':' . $this->second . $this->timezone;
    }

    public function getXml(): string
    {
        return '<dateTime.iso8601>' . $this->getIso() . '</dateTime.iso8601>';
    }

    public function getTimestamp(): int
    {
        return mktime((int) $this->hour, (int) $this->minute, (int) $this->second, (int) $this->month, (int) $this->day, (int) $this->year);
    }
}

/**
 * IXR_Base64
 *
 * @package IXR
 * @since 1.5.0
 */
class IXR_Base64
{
    public string $data;

    public function __construct(string $data)
    {
        $this->data = $data;
    }

    public function getXml(): string
    {
        return '<base64>' . base64_encode($this->data) . '</base64>';
    }
}

/**
 * IXR_IntrospectionServer
 *
 * @package IXR
 * @since 1.5.0
 */
class IXR_IntrospectionServer extends IXR_Server
{
    public array $signatures = array();
    public array $help = array();

    public function __construct()
    {
        $this->setCallbacks();
        $this->setCapabilities();
        $this->capabilities['introspection'] = array(
            'specUrl' => 'http://xmlrpc.usefulinc.com/doc/reserved.html',
            'specVersion' => 1
        );
        $this->addCallback(
            'system.methodSignature',
            'this:methodSignature',
            array('array', 'string'),
            'Returns an array describing the return type and required parameters of a method'
        );
        $this->addCallback(
            'system.getCapabilities',
            'this:getCapabilities',
            array('struct'),
            'Returns a struct describing the XML-RPC specifications supported by this server'
        );
        $this->addCallback(
            'system.listMethods',
            'this:listMethods',
            array('array'),
            'Returns an array of available methods on this server'
        );
        $this->addCallback(
            'system.methodHelp',
            'this:methodHelp',
            array('string', 'string'),
            'Returns a documentation string for the specified method'
        );
    }

    public function addCallback(string $method, string $callback, array $args, string $help): void
    {
        $this->callbacks[$method] = $callback;
        $this->signatures[$method] = $args;
        $this->help[$method] = $help;
    }

    public function call(string $methodname, array $args)
    {
        if ($args && !is_array($args)) {
            $args = array($args);
        }

        if (!$this->hasMethod($methodname)) {
            return new IXR_Error(-32601, 'server error. requested method "' . $this->message->methodName . '" not specified.');
        }
        $method = $this->callbacks[$methodname];
        $signature = $this->signatures[$methodname];
        $returnType = array_shift($signature);

        if (count($args) !== count($signature)) {
            return new IXR_Error(-32602, 'server error. wrong number of method parameters');
        }

        $ok = true;
        $argsbackup = $args;
        foreach ($signature as $index => $type) {
            $arg = $args[$index];
            switch ($type) {
                case 'int':
                case 'i4':
                    if (is_array($arg) || !is_int($arg)) {
                        $ok = false;
                    }
                    break;
                case 'base64':
                case 'string':
                    if (!is_string($arg)) {
                        $ok = false;
                    }
                    break;
                case 'boolean':
                    if ($arg !== false && $arg !== true) {
                        $ok = false;
                    }
                    break;
                case 'float':
                case 'double':
                    if (!is_float($arg)) {
                        $ok = false;
                    }
                    break;
                case 'date':
                case 'dateTime.iso8601':
                    if (!($arg instanceof IXR_Date)) {
                        $ok = false;
                    }
                    break;
            }
            if (!$ok) {
                return new IXR_Error(-32602, 'server error. invalid method parameters');
            }
        }
        return parent::call($methodname, $argsbackup);
    }
}public function methodSignature(string $method): IXR_Error|array
    {
        if (!$this->hasMethod($method)) {
            return new IXR_Error(-32601, 'server error. requested method "' . $method . '" not specified.');
        }
        // We should be returning an array of types
        $types = $this->signatures[$method];
        $return = [];
        foreach ($types as $type) {
            $value = match ($type) {
                'string' => 'string',
                'int', 'i4' => 42,
                'double' => 3.1415,
                'dateTime.iso8601' => new IXR_Date(time()),
                'boolean' => true,
                'base64' => new IXR_Base64('base64'),
                'array' => ['array'],
                'struct' => ['struct' => 'struct'],
                default => null,
            };

            if (null !== $value) {
                $return[] = $value;
            }
        }
        return $return;
    }

    public function methodHelp(string $method): mixed
    {
        return $this->help[$method] ?? null;
    }
}

/**
 * IXR_ClientMulticall
 *
 * @package IXR
 * @since 1.5.0
 */
class IXR_ClientMulticall extends IXR_Client
{
    protected array $calls = [];

    public function __construct($server, $path = false, $port = 80)
    {
        parent::__construct($server, $path, $port);
        $this->useragent = 'The Incutio XML-RPC PHP Library (multicall client)';
    }

    public function addCall(string $methodName, mixed ...$args): void
    {
        $struct = [
            'methodName' => $methodName,
            'params' => $args,
        ];
        $this->calls[] = $struct;
    }

    public function query(): mixed
    {
        // Prepare multicall, then call the parent::query() method
        return parent::query('system.multicall', $this->calls);
    }
}