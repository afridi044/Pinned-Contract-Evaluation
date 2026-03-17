<?php

/**
 * Deprecated. Use WP_HTTP (http.php, class-http.php) instead.
 */
_deprecated_file( basename( __FILE__ ), '3.0', WPINC . '/http.php' );

if ( !class_exists( 'Snoopy' ) ) :
/*************************************************

Snoopy - the PHP net client
Author: Monte Ohrt <monte@ispi.net>
Copyright (c): 1999-2008 New Digital Group, all rights reserved
Version: 1.2.4

 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

You may contact the author of Snoopy by e-mail at:
monte@ohrt.com

The latest version of Snoopy can be obtained from:
http://snoopy.sourceforge.net/

*************************************************/

class Snoopy
{
	/**** Public variables ****/

	/* user definable vars */

	public $host			=	'www.php.net';		// host name we are connecting to
	public $port			=	80;					// port we are connecting to
	public $proxy_host		=	'';					// proxy host to use
	public $proxy_port		=	'';					// proxy port to use
	public $proxy_user		=	'';					// proxy user to use
	public $proxy_pass		=	'';					// proxy password to use

	public $agent			=	'Snoopy v1.2.4';	// agent we masquerade as
	public $referer		=	'';					// referer info to pass
	public $cookies		=	[];			// array of cookies to pass
												// $cookies["username"]="joe";
	public $rawheaders		=	[];			// array of raw headers to send
												// $rawheaders["Content-type"]="text/html";

	public $maxredirs		=	5;					// http redirection depth maximum. 0 = disallow
	public $lastredirectaddr	=	'';				// contains address of last redirected address
	public $offsiteok		=	true;				// allows redirection off-site
	public $maxframes		=	0;					// frame content depth maximum. 0 = disallow
	public $expandlinks	=	true;				// expand links to fully qualified URLs.
												// this only applies to fetchlinks()
												// submitlinks(), and submittext()
	public $passcookies	=	true;				// pass set cookies back through redirects
												// NOTE: this currently does not respect
												// dates, domains or paths.

	public $user			=	'';					// user for http authentication
	public $pass			=	'';					// password for http authentication

	// http accept types
	public $accept			=	'image/gif, image/x-xbitmap, image/jpeg, image/pjpeg, */*';

	public $results		=	'';					// where the content is put

	public $error			=	'';					// error messages sent here
	public $response_code	=	'';					// response code returned from server
	public $headers		=	[];			// headers returned from server sent here
	public $maxlength		=	500000;				// max return data length (body)
	public $read_timeout	=	0;					// timeout on read operations, in seconds
												// supported only since PHP 4 Beta 4
												// set to 0 to disallow timeouts
	public $timed_out		=	false;				// if a read operation timed out
	public $status			=	0;					// http request status

	public $temp_dir		=	'/tmp';				// temporary directory that the webserver
												// has permission to write to.
												// under Windows, this should be C:\temp

	public $curl_path		=	'/usr/local/bin/curl';
												// Snoopy will use cURL for fetching
												// SSL content if a full system path to
												// the cURL binary is supplied here.
												// set to false if you do not have
												// cURL installed. See http://curl.haxx.se
												// for details on installing cURL.
												// Snoopy does *not* use the cURL
												// library functions built into php,
												// as these functions are not stable
												// as of this Snoopy release.

	/**** Private variables ****/

	protected $_maxlinelen	=	4096;				// max line length (headers)

	protected $_httpmethod	=	'GET';				// default http request method
	protected $_httpversion	=	'HTTP/1.0';			// default http request version
	protected $_submit_method	=	'POST';				// default submit method
	protected $_submit_type	=	'application/x-www-form-urlencoded';	// default submit type
	protected $_mime_boundary	=   '';					// MIME boundary for multipart/form-data submit type
	protected $_redirectaddr	=	false;				// will be set if page fetched is a redirect
	protected $_redirectdepth	=	0;					// increments on an http redirect
	protected $_frameurls		= 	[];			// frame src urls
	protected $_framedepth	=	0;					// increments on frame depth

	protected $_isproxy		=	false;				// set if using a proxy server
	protected $_fp_timeout	=	30;					// timeout for socket connection

/*======================================================================*\
	Function:	fetch
	Purpose:	fetch the contents of a web page
				(and possibly other protocols in the
				future like ftp, nntp, gopher, etc.)
	Input:		$URI	the location of the page to fetch
	Output:		$this->results	the output text from the fetch
\*======================================================================*/

	public function fetch(string $URI): bool
	{

		//preg_match("|^([^:]+)://([^:/]+)(:[\d]+)*(.*)|",$URI,$URI_PARTS);
		$URI_PARTS = parse_url($URI);
		if (false === $URI_PARTS) {
			$this->error = 'Unable to parse URI';
			return false;
		}
		if (!empty($URI_PARTS["user"]))
			$this->user = $URI_PARTS["user"];
		if (!empty($URI_PARTS["pass"]))
			$this->pass = $URI_PARTS["pass"];
		$URI_PARTS["query"] = $URI_PARTS["query"] ?? '';
		$URI_PARTS["path"] = $URI_PARTS["path"] ?? '';
		if (empty($URI_PARTS["scheme"])) {
			$this->error = 'Invalid URI scheme';
			return false;
		}

		switch(strtolower($URI_PARTS["scheme"]))
		{
			case "http":
				$this->host = $URI_PARTS["host"];
				if(!empty($URI_PARTS["port"]))
					$this->port = $URI_PARTS["port"];
				if($this->_connect($fp))
				{
					if($this->_isproxy)
					{
						// using proxy, send entire URI
						$this->_httprequest($URI,$fp,$URI,$this->_httpmethod);
					}
					else
					{
						$path = $URI_PARTS["path"].($URI_PARTS["query"] ? "?".$URI_PARTS["query"] : "");
						// no proxy, send only the path
						$this->_httprequest($path, $fp, $URI, $this->_httpmethod);
					}

					$this->_disconnect($fp);

					if($this->_redirectaddr)
					{
						/* url was redirected, check if we've hit the max depth */
						if($this->maxredirs > $this->_redirectdepth)
						{
							// only follow redirect if it's on this site, or offsiteok is true
							if(preg_match("|^http://".preg_quote($this->host)."|i",$this->_redirectaddr) || $this->offsiteok)
							{
								/* follow the redirect */
								$this->_redirectdepth++;
								$this->lastredirectaddr=$this->_redirectaddr;
								$this->fetch($this->_redirectaddr);
							}
						}
					}

					if($this->_framedepth < $this->maxframes && count($this->_frameurls) > 0)
					{
						$frameurls = $this->_frameurls;
						$this->_frameurls = [];

						foreach ($frameurls as $frameurl)
						{
							if($this->_framedepth < $this->maxframes)
							{
								$this->fetch($frameurl);
								$this->_framedepth++;
							}
							else
								break;
						}
					}
				}
				else
				{
					return false;
				}
				return true;
			case "https":
				if(!$this->curl_path)
					return false;
				if(function_exists("is_executable"))
				    if (!is_executable($this->curl_path))
				        return false;
				$this->host = $URI_PARTS["host"];
				if(!empty($URI_PARTS["port"]))
					$this->port = $URI_PARTS["port"];
				if($this->_isproxy)
				{
					// using proxy, send entire URI
					$this->_httpsrequest($URI,$URI,$this->_httpmethod);
				}
				else
				{
					$path = $URI_PARTS["path"].($URI_PARTS["query"] ? "?".$URI_PARTS["query"] : "");
					// no proxy, send only the path
					$this->_httpsrequest($path, $URI, $this->_httpmethod);
				}

				if($this->_redirectaddr)
				{
					/* url was redirected, check if we've hit the max depth */
					if($this->maxredirs > $this->_redirectdepth)
					{
						// only follow redirect if it's on this site, or offsiteok is true
						if(preg_match("|^http://".preg_quote($this->host)."|i",$this->_redirectaddr) || $this->offsiteok)
						{
							/* follow the redirect */
							$this->_redirectdepth++;
							$this->lastredirectaddr=$this->_redirectaddr;
							$this->fetch($this->_redirectaddr);
						}
					}
				}

				if($this->_framedepth < $this->maxframes && count($this->_frameurls) > 0)
				{
					$frameurls = $this->_frameurls;
					$this->_frameurls = [];

					foreach ($frameurls as $frameurl)
					{
						if($this->_framedepth < $this->maxframes)
						{
							$this->fetch($frameurl);
							$this->_framedepth++;
						}
						else
							break;
					}
				}
				return true;
			default:
				// not a valid protocol
				$this->error	=	'Invalid protocol "'.$URI_PARTS["scheme"].'"\n';
				return false;
		}
		return true;
	}

/*======================================================================*\
	Function:	submit
	Purpose:	submit an http form
	Input:		$URI	the location to post the data
				$formvars	the formvars to use.
					format: $formvars["var"] = "val";
				$formfiles  an array of files to submit
					format: $formfiles["var"] = "/dir/filename.ext";
	Output:		$this->results	the text output from the post
\*======================================================================*/

	public function submit(string $URI, $formvars="", $formfiles=""): bool
	{
		unset($postdata);

		$postdata = $this->_prepare_post_body($formvars, $formfiles);

		$URI_PARTS = parse_url($URI);
		if (false === $URI_PARTS) {
			$this->error = 'Unable to parse URI';
			return false;
		}
		if (!empty($URI_PARTS["user"]))
			$this->user = $URI_PARTS["user"];
		if (!empty($URI_PARTS["pass"]))
			$this->pass = $URI_PARTS["pass"];
		$URI_PARTS["query"] = $URI_PARTS["query"] ?? '';
		$URI_PARTS["path"] = $URI_PARTS["path"] ?? '';

		switch(strtolower($URI_PARTS["scheme"]))
		{
			case "http":
				$this->host = $URI_PARTS["host"];
				if(!empty($URI_PARTS["port"]))
					$this->port = $URI_PARTS["port"];
				if($this->_connect($fp))
				{
					if($this->_isproxy)
					{
						// using proxy, send entire URI
						$this->_httprequest($URI,$fp,$URI,$this->_submit_method,$this->_submit_type,$postdata);
					}
					else
					{
						$path = $URI_PARTS["path"].($URI_PARTS["query"] ? "?".$URI_PARTS["query"] : "");
						// no proxy, send only the path
						$this->_httprequest($path, $fp, $URI, $this->_submit_method, $this->_submit_type, $postdata);
					}

					$this->_disconnect($fp);

					if($this->_redirectaddr)
					{
						/* url was redirected, check if we've hit the max depth */
						if($this->maxredirs > $this->_redirectdepth)
						{
							if(!preg_match("|^".$URI_PARTS["scheme"]."://|", $this->_redirectaddr))
								$this->_redirectaddr = $this->_expandlinks($this->_redirectaddr,$URI_PARTS["scheme"]."://".$URI_PARTS["host"]);

							// only follow redirect if it's on this site, or offsiteok is true
							if(preg_match("|^http://".preg_quote($this->host)."|i",$this->_redirectaddr) || $this->offsiteok)
							{
								/* follow the redirect */
								$this->_redirectdepth++;
								$this->lastredirectaddr=$this->_redirectaddr;
								if( strpos( $this->_redirectaddr, "?" ) > 0 )
									$this->fetch($this->_redirectaddr); // the redirect has changed the request method from post to get
								else
									$this->submit($this->_redirectaddr,$formvars, $formfiles);
							}
						}
					}

					if($this->_framedepth < $this->maxframes && count($this->_frameurls) > 0)
					{
						$frameurls = $this->_frameurls;
						$this->_frameurls = [];

						foreach ($frameurls as $frameurl)
						{
							if($this->_framedepth < $this->maxframes)
							{
								$this->fetch($frameurl);
								$this->_framedepth++;
							}
							else
								break;
						}
					}

				}
				else
				{
					return false;
				}
				return true;
			case "https":
				if(!$this->curl_path)
					return false;
				if(function_exists("is_executable"))
				    if (!is_executable($this->curl_path))
				        return false;
				$this->host = $URI_PARTS["host"];
				if(!empty($URI_PARTS["port"]))
					$this->port = $URI_PARTS["port"];
				if($this->_isproxy)
				{
					// using proxy, send entire URI
					$this->_httpsrequest($URI, $URI, $this->_submit_method, $this->_submit_type, $postdata);
				}
				else
				{
					$path = $URI_PARTS["path"].($URI_PARTS["query"] ? "?".$URI_PARTS["query"] : "");
					// no proxy, send only the path
					$this->_httpsrequest($path, $URI, $this->_submit_method, $this->_submit_type, $postdata);
				}

				if($this->_redirectaddr)
				{
					/* url was redirected, check if we've hit the max depth */
					if($this->maxredirs > $this->_redirectdepth)
					{
						if(!preg_match("|^".$URI_PARTS["scheme"]."://|", $this->_redirectaddr))
							$this->_redirectaddr = $this->_expandlinks($this->_redirectaddr,$URI_PARTS["scheme"]."://".$URI_PARTS["host"]);

						// only follow redirect if it's on this site, or offsiteok is true
						if(preg_match("|^http://".preg_quote($this->host)."|i",$this->_redirectaddr) || $this->offsiteok)
						{
							/* follow the redirect */
							$this->_redirectdepth++;
							$this->lastredirectaddr=$this->_redirectaddr;
							if( strpos( $this->_redirectaddr, "?" ) > 0 )
								$this->fetch($this->_redirectaddr); // the redirect has changed the request method from post to get
							else
								$this->submit($this->_redirectaddr,$formvars, $formfiles);
						}
					}
				}

				if($this->_framedepth < $this->maxframes && count($this->_frameurls) > 0)
				{
					$frameurls = $this->_frameurls;
					$this->_frameurls = [];

					foreach ($frameurls as $frameurl)
					{
						if($this->_framedepth < $this->maxframes)
						{
							$this->fetch($frameurl);
							$this->_framedepth++;
						}
						else
							break;
					}
				}
				return true;

			default:
				// not a valid protocol
				$this->error	=	'Invalid protocol "'.$URI_PARTS["scheme"].'"\n';
				return false;
		}
		return true;
	}

/*======================================================================*\
	Function:	fetchlinks
	Purpose:	fetch the links from a web page
	Input:		$URI	where you are fetching from
	Output:		$this->results	an array of the URLs
\*======================================================================*/

	public function fetchlinks(string $URI): bool
	{
		if ($this->fetch($URI))
		{
			if($this->lastredirectaddr)
				$URI = $this->lastredirectaddr;
			if(is_array($this->results))
			{
				foreach ($this->results as $index => $result) {
					$this->results[$index] = $this->_striplinks($result);
				}
			}
			else
				$this->results = $this->_striplinks($this->results);

			if($this->expandlinks)
				$this->results = $this->_expandlinks($this->results, $URI);
			return true;
		}
		else
			return false;
	}

/*======================================================================*\
	Function:	fetchform
	Purpose:	fetch the form elements from a web page
	Input:		$URI	where you are fetching from
	Output:		$this->results	the resulting html form
\*======================================================================*/

	public function fetchform(string $URI): bool
	{

		if ($this->fetch($URI))
		{

			if(is_array($this->results))
			{
				foreach ($this->results as $index => $result) {
					$this->results[$index] = $this->_stripform($result);
				}
			}
			else
				$this->results = $this->_stripform($this->results);

			return true;
		}
		else
			return false;
	}


/*======================================================================*\
	Function:	fetchtext
	Purpose:	fetch the text from a web page, stripping the links
	Input:		$URI	where you are fetching from
	Output:		$this->results	the text from the web page
\*======================================================================*/

	public function fetchtext(string $URI): bool
	{
		if($this->fetch($URI))
		{
			if(is_array($this->results))
			{
				foreach ($this->results as $index => $result) {
					$this->results[$index] = $this->_striptext($result);
				}
			}
			else
				$this->results = $this->_striptext($this->results);
			return true;
		}
		else
			return false;
	}

/*======================================================================*\
	Function:	submitlinks
	Purpose:	grab links from a form submission
	Input:		$URI	where you are submitting from
	Output:		$this->results	an array of the links from the post
\*======================================================================*/

	public function submitlinks(string $URI, $formvars="", $formfiles=""): bool
	{
		if($this->submit($URI,$formvars, $formfiles))
		{
			if($this->lastredirectaddr)
				$URI = $this->lastredirectaddr;
			if(is_array($this->results))
			{
				foreach ($this->results as $index => $result)
				{
					$this->results[$index] = $this->_striplinks($result);
					if($this->expandlinks)
						$this->results[$index] = $this->_expandlinks($this->results[$index],$URI);
				}
			}
			else
			{
				$this->results = $this->_striplinks($this->results);
				if($this->expandlinks)
					$this->results = $this->_expandlinks($this->results,$URI);
			}
			return true;
		}
		else
			return false;
	}

/*======================================================================*\
	Function:	submittext
	Purpose:	grab text from a form submission
	Input:		$URI	where you are submitting from
	Output:		$this->results	the text from the web page
\*======================================================================*/

	public function submittext(string $URI, $formvars = "", $formfiles = ""): bool
	{
		if($this->submit($URI,$formvars, $formfiles))
		{
			if($this->lastredirectaddr)
				$URI = $this->lastredirectaddr;
			if(is_array($this->results))
			{
				foreach ($this->results as $index => $result)
				{
					$this->results[$index] = $this->_striptext($result);
					if($this->expandlinks)
						$this->results[$index] = $this->_expandlinks($this->results[$index],$URI);
				}
			}
			else
			{
				$this->results = $this->_striptext($this->results);
				if($this->expandlinks)
					$this->results = $this->_expandlinks($this->results,$URI);
			}
			return true;
		}
		else
			return false;
	}



/*======================================================================*\
	Function:	set_submit_multipart
	Purpose:	Set the form submission content type to
				multipart/form-data
\*======================================================================*/
	public function set_submit_multipart(): void
	{
		$this->_submit_type = "multipart/form-data";
	}


/*======================================================================*\
	Function:	set_submit_normal
	Purpose:	Set the form submission content type to
				application/x-www-form-urlencoded
\*======================================================================*/
	public function set_submit_normal(): void
	{
		$this->_submit_type = "application/x-www-form-urlencoded";
	}




/*======================================================================*\
	Private functions
\*======================================================================*/


/*======================================================================*\
	Function:	_striplinks
	Purpose:	strip the hyperlinks from an html document
	Input:		$document	document to strip.
	Output:		$match		an array of the links
\*======================================================================*/

	protected function _striplinks(string $document): array
	{
		preg_match_all("'<\s*a\s.*?href\s*=\s*			# find <a href=
						([\"\'])?					# find single or double quote
						(?(1) (.*?)\\1 | ([^\s\>]+))		# if quote found, match up to next matching
													# quote, otherwise match up to next space
						'isx",$document,$links);


		// catenate the non-empty matches from the conditional subpattern

		$match = [];
		foreach ($links[2] as $val)
		{
			if(!empty($val))
				$match[] = $val;
		}

		foreach ($links[3] as $val)
		{
			if(!empty($val))
				$match[] = $val;
		}

		// return the links
		return $match;
	}

/*======================================================================*\
	Function:	_stripform
	Purpose:	strip the form elements from an html document
	Input:		$document	document to strip.
	Output:		$match		an array of the links
\*======================================================================*/

	protected function _stripform(string $document): string
	{
		preg_match_all("'<\/?(FORM|INPUT|SELECT|TEXTAREA|(OPTION))[^<>]*>(?(2)(.*(?=<\/?(option|select)[^<>]*>[\r\n]*)|(?=[\r\n]*))|(?=[\r\n]*))'Usi",$document,$elements);

		// catenate the matches
		$match = implode("\r\n",$elements[0]);

		// return the links
		return $match;
	}



/*======================================================================*\
	Function:	_striptext
	Purpose:	strip the text from an html document
	Input:		$document	document to strip.
	Output:		$text		the resulting text
\*======================================================================*/

	protected function _striptext(string $document): string
	{

		// I didn't use preg eval (//e) since that is only available in PHP 4.0.
		// so, list your entities one by one here. I included some of the
		// more common ones.

		$search = array("'<script[^>]*?>.*?</script>'si",	// strip out javascript
						"'<[\/\!]*?[^<>]*?>'si",			// strip out html tags
						"'([\r\n])[\s]+'",					// strip out white space
						"'&(quot|#34|#034|#x22);'i",		// replace html entities
						"'&(amp|#38|#038|#x26);'i",			// added hexadecimal values
						"'&(lt|#60|#060|#x3c);'i",
						"'&(gt|#62|#062|#x3e);'i",
						"'&(nbsp|#160|#xa0);'i",
						"'&(iexcl|#161);'i",
						"'&(cent|#162);'i",
						"'&(pound|#163);'i",
						"'&(copy|#169);'i",
						"'&(reg|#174);'i",
						"'&(deg|#176);'i",
						"'&(#39|#039|#x27);'",
						"'&(euro|#8364);'i",				// europe
						"'&a(uml|UML);'",					// german
						"'&o(uml|UML);'",
						"'&u(uml|UML);'",
						"'&A(uml|UML);'",
						"'&O(uml|UML);'",
						"'&U(uml|UML);'",
						"'&szlig;'i",
						);
		$replace = array(	"",
							"",
							"\\1",
							"\"",
							"&",
							"<",
							">",
							" ",
							chr(161),
							chr(162),
							chr(163),
							chr(169),
							chr(174),
							chr(176),
							chr(39),
							chr(128),
							chr(0xE4), // ANSI &auml;
							chr(0xF6), // ANSI &ouml;
							chr(0xFC), // ANSI &uuml;
							chr(0xC4), // ANSI &Auml;
							chr(0xD6), // ANSI &Ouml;
							chr(0xDC), // ANSI &Uuml;
							chr(0xDF), // ANSI &szlig;
						);

		$text = preg_replace($search,$replace,$document);

		return $text;
	}

/*======================================================================*\
	Function:	_expandlinks
	Purpose:	expand each link into a fully qualified URL
	Input:		$links			the links to qualify
				$URI			the full URI to get the base from
	Output:		$expandedLinks	the expanded links
\*======================================================================*/

	protected function _expandlinks($links,$URI)
	{

		if(!preg_match("/^[^\?]+/",$URI,$match))
			$match[0] = $URI;

		$match = preg_replace("|/[^\/\.]+\.[^\/\.]+$|","",$match[0]);
		$match = preg_replace("|/$|","",$match);
		$match_part = parse_url($match);
		$match_root =
		$match_part["scheme"]."://".$match_part["host"];

		$search = array( 	"|^http://".preg_quote($this->host)."|i",
							"|^(\/)|i",
							"|^(?!http://)(?!mailto:)|i",
							"|/\./|",
							"|/[^\/]+/\.\./|"
						);

		$replace = array(	"",
							$match_root."/",
							$match."/",
							"/",
							"/"
						);

		$expandedLinks = preg_replace($search,$replace,$links);

		return $expandedLinks;
	}

/*======================================================================*\
	Function:	_httprequest
	Purpose:	go get the http data from the server
	Input:		$url		the url to fetch
				$fp			the current open file pointer
				$URI		the full URI
				$body		body contents to send if any (POST)
	Output:
\*======================================================================*/

	protected function _httprequest($url,$fp,$URI,$http_method,$content_type="",$body="")
	{
		$cookie_headers = '';
		if($this->passcookies && $this->_redirectaddr)
			$this->setcookies();

		$URI_PARTS = parse_url($URI);
		if(empty($url))
			$url = "/";
		$headers = $http_method." ".$url." ".$this->_httpversion."\r\n";
		if(!empty($this->agent))
			$headers .= "User-Agent: ".$this->agent."\r\n";
		if(!empty($this->host) && !isset($this->rawheaders['Host'])) {
			$headers .= "Host: ".$this->host;
			if(!empty($this->port) && $this->port != 80)
				$headers .= ":".$this->port;
			$headers .= "\r\n";
		}
		if(!empty($this->accept))
			$headers .= "Accept: ".$this->accept."\r\n";
		if(!empty($this->referer))
			$headers .= "Referer: ".$this->referer."\r\n";
		if(!empty($this->cookies))
		{
			if(!is_array($this->cookies))
				$this->cookies = (array)$this->cookies;

			if ( count($this->cookies) > 0 ) {
				$cookie_headers .= 'Cookie: ';
				foreach ( $this->cookies as $cookieKey => $cookieVal ) {
					$cookie_headers .= $cookieKey."=".urlencode($cookieVal)."; ";
				}
				$headers .= substr($cookie_headers,0,-2) . "\r\n";
			}
		}
		if(!empty($this->rawheaders))
		{
			if(!is_array($this->rawheaders))
				$this->rawheaders = (array)$this->rawheaders;
			foreach ($this->rawheaders as $headerKey => $headerVal)
				$headers .= $headerKey.": ".$headerVal."\r\n";
		}
		if(!empty($content_type)) {
			$headers .= "Content-type: $content_type";
			if ($content_type == "multipart/form-data")
				$headers .= "; boundary=".$this->_mime_boundary;
			$headers .= "\r\n";
		}
		if(!empty($body))
			$headers .= "Content-length: ".strlen($body)."\r\n";
		if(!empty($this->user) || !empty($this->pass))
			$headers .= "Authorization: Basic ".base64_encode($this->user.":".$this->pass)."\r\n";

		//add proxy auth headers
		if(!empty($this->proxy_user))
			$headers .= 'Proxy-Authorization: ' . 'Basic ' . base64_encode($this->proxy_user . ':' . $this->proxy_pass)."\r\n";


		$headers .= "\r\n";

		// set the read timeout if needed
		if ($this->read_timeout > 0)
			socket_set_timeout($fp, $this->read_timeout);
		$this->timed_out = false;

		fwrite($fp, $headers.$body);

		$this->_redirectaddr = false;
		unset($this->headers);

		while(($currentHeader = fgets($fp,$this->_maxlinelen)) !== false)
		{
			if ($this->read_timeout > 0 && $this->_check_timeout($fp))
			{
				$this->status=-100;
				return false;
			}

			if($currentHeader == "\r\n")
				break;

			// if a header begins with Location: or URI:, set the redirect
			if(preg_match("/^(Location:|URI:)/i",$currentHeader))
			{
				// get URL portion of the redirect
				preg_match("/^(Location:|URI:)[ ]+(.*)/i",rtrim($currentHeader),$matches);
				// look for :// in the Location header to see if hostname is included
				if(!preg_match("|\:\/\/|",$matches[2]))
				{
					// no host in the path, so prepend
					$this->_redirectaddr = $URI_PARTS["scheme"]."://".$this->host.":".$this->port;
					// eliminate double slash
					if(!preg_match("|^/|",$matches[2]))
							$this->_redirectaddr .= "/".$matches[2];
					else
							$this->_redirectaddr .= $matches[2];
				}
				else
					$this->_redirectaddr = $matches[2];
			}

			if(preg_match("|^HTTP/|",$currentHeader))
			{
                if(preg_match("|^HTTP/[^\s]*\s(.*?)\s|",$currentHeader, $status))
				{
					$this->status= $status[1];
                }
				$this->response_code = $currentHeader;
			}

			$this->headers[] = $currentHeader;
		}

		$results = stream_get_contents($fp, $this->maxlength);
		if ($results === false) {
			$results = '';
		}

		if ($this->read_timeout > 0 && $this->_check_timeout($fp))
		{
			$this->status=-100;
			return false;
		}

		// check if there is a redirect meta tag

		if(preg_match("'<meta[\s]*http-equiv[^>]*?content[\s]*=[\s]*[\"\']?\d+;[\s]*URL[\s]*=[\s]*([^\"\']*?)[\"\']?>'i",$results,$match))

		{
			$this->_redirectaddr = $this->_expandlinks($match[1],$URI);
		}

		// have we hit our frame depth and is there frame src to fetch?
		if(($this->_framedepth < $this->maxframes) && preg_match_all("'<frame\s+.*src[\s]*=[\'\"]?([^\'\"\>]+)'i",$results,$match))
		{
			$this->results[] = $results;
			foreach ($match[1] as $frameurl)
				$this->_frameurls[] = $this->_expandlinks($frameurl,$URI_PARTS["scheme"]."://".$this->host);
		}
		// have we already fetched framed content?
		elseif(is_array($this->results))
			$this->results[] = $results;
		// no framed content
		else
			$this->results = $results;

		return true;
	}

/*======================================================================*\
	Function:	_httpsrequest
	Purpose:	go get the https data from the server using curl
	Input:		$url		the url to fetch
				$URI		the full URI
				$body		body contents to send if any (POST)
	Output:
\*======================================================================*/

	protected function _httpsrequest($url,$URI,$http_method,$content_type="",$body="")
	{
		if($this->passcookies && $this->_redirectaddr)
			$this->setcookies();

		$headers = [];

		$URI_PARTS = parse_url($URI);
		if(empty($url))
			$url = "/";
		// GET ... header not needed for curl
		//$headers[] = $http_method." ".$url." ".$this->_httpversion;
		if(!empty($this->agent))
			$headers[] = "User-Agent: ".$this->agent;
		if(!empty($this->host))
			if(!empty($this->port))
				$headers[] = "Host: ".$this->host.":".$this->port;
			else
				$headers[] = "Host: ".$this->host;
		if(!empty($this->accept))
			$headers[] = "Accept: ".$this->accept;
		if(!empty($this->referer))
			$headers[] = "Referer: ".$this->referer;
		if(!empty($this->cookies))
		{
			if(!is_array($this->cookies))
				$this->cookies = (array)$this->cookies;

			if ( count($this->cookies) > 0 ) {
				$cookie_str = 'Cookie: ';
				foreach ( $this->cookies as $cookieKey => $cookieVal ) {
					$cookie_str .= $cookieKey."=".urlencode($cookieVal)."; ";
				}
				$headers[] = substr($cookie_str,0,-2);
			}
		}
		if(!empty($this->rawheaders))
		{
			if(!is_array($this->rawheaders))
				$this->rawheaders = (array)$this->rawheaders;
			foreach ($this->rawheaders as $headerKey => $headerVal)
				$headers[] = $headerKey.": ".$headerVal;
		}
		if(!empty($content_type)) {
			if ($content_type == "multipart/form-data")
				$headers[] = "Content-type: $content_type; boundary=".$this->_mime_boundary;
			else
				$headers[] = "Content-type: $content_type";
		}
		if(!empty($body))
			$headers[] = "Content-length: ".strlen($body);
		if(!empty($this->user) || !empty($this->pass))
			$headers[] = "Authorization: BASIC ".base64_encode($this->user.":".$this->pass);

		$cmdline_params = '';
		foreach($headers as $header_line) {
			$safer_header = strtr( $header_line, "\"", " " );
			$cmdline_params .= ' -H ' . escapeshellarg($safer_header);
		}

		if(!empty($body))
			$cmdline_params .= ' --data ' . escapeshellarg($body);

		if($this->read_timeout > 0)
			$cmdline_params .= ' -m '.(int) $this->read_timeout;

		$headerfile = tempnam($this->temp_dir, "sno");

		$curl_executable = escapeshellcmd($this->curl_path);
		$command = $curl_executable." -k -D ".escapeshellarg($headerfile).$cmdline_params." ".escapeshellarg($URI);
		exec($command,$results,$return);

		if($return)
		{
			$this->error = "Error: cURL could not retrieve the document, error $return.";
			return false;
		}


		$resultBody = implode("\r\n",$results);

		$result_headers = file($headerfile);

		$this->_redirectaddr = false;
		unset($this->headers);

		foreach ($result_headers as $headerLine)
		{

			// if a header begins with Location: or URI:, set the redirect
			if(preg_match("/^(Location: |URI: )/i",$headerLine))
			{
				// get URL portion of the redirect
				preg_match("/^(Location: |URI:)\s+(.*)/",rtrim($headerLine),$matches);
				// look for :// in the Location header to see if hostname is included
				if(!preg_match("|\:\/\/|",$matches[2]))
				{
					// no host in the path, so prepend
					$this->_redirectaddr = $URI_PARTS["scheme"]."://".$this->host.":".$this->port;
					// eliminate double slash
					if(!preg_match("|^/|",$matches[2]))
							$this->_redirectaddr .= "/".$matches[2];
					else
							$this->_redirectaddr .= $matches[2];
				}
				else
					$this->_redirectaddr = $matches[2];
			}

			if(preg_match("|^HTTP/|",$headerLine))
				$this->response_code = $headerLine;

			$this->headers[] = $headerLine;
		}

		// check if there is a redirect meta tag

		if(preg_match("'<meta[\s]*http-equiv[^>]*?content[\s]*=[\s]*[\"\']?\d+;[\s]*URL[\s]*=[\s]*([^\"\']*?)[\"\']?>'i",$resultBody,$match))
		{
			$this->_redirectaddr = $this->_expandlinks($match[1],$URI);
		}

		// have we hit our frame depth and is there frame src to fetch?
		if(($this->_framedepth < $this->maxframes) && preg_match_all("'<frame\s+.*src[\s]*=[\'\"]?([^\'\"\>]+)'i",$resultBody,$match))
		{
			$this->results[] = $resultBody;
			foreach ($match[1] as $frameurl)
				$this->_frameurls[] = $this->_expandlinks($frameurl,$URI_PARTS["scheme"]."://".$this->host);
		}
		// have we already fetched framed content?
		elseif(is_array($this->results))
			$this->results[] = $resultBody;
		// no framed content
		else
			$this->results = $resultBody;

		@unlink($headerfile);

		return true;
	}/*======================================================================*\
	Function:	setcookies()
	Purpose:	set cookies for a redirection
\*======================================================================*/

	function setcookies()
	{
		foreach ((array) $this->headers as $header) {
			if (preg_match('/^set-cookie:[\s]+([^=]+)=([^;]+)/i', $header, $match)) {
				$this->cookies[$match[1]] = urldecode($match[2]);
			}
		}
	}


/*======================================================================*\
	Function:	_check_timeout
	Purpose:	checks whether timeout has occurred
	Input:		$fp	file pointer
\*======================================================================*/

	function _check_timeout($fp)
	{
		if ($this->read_timeout > 0) {
			$fp_status = socket_get_status($fp);
			if ($fp_status["timed_out"]) {
				$this->timed_out = true;
				return true;
			}
		}
		return false;
	}

/*======================================================================*\
	Function:	_connect
	Purpose:	make a socket connection
	Input:		$fp	file pointer
\*======================================================================*/

	function _connect(&$fp)
	{
		$this->_isproxy = !empty($this->proxy_host) && !empty($this->proxy_port);

		$host = $this->_isproxy ? $this->proxy_host : $this->host;
		$port = $this->_isproxy ? $this->proxy_port : $this->port;

		$this->status = 0;

		$fp = fsockopen(
			$host,
			$port,
			$errno,
			$errstr,
			$this->_fp_timeout
		);

		if ($fp !== false) {
			// socket connection succeeded
			return true;
		}

		// socket connection failed
		$this->status = $errno;
		$this->error = match ($errno) {
			-3 => "socket creation failed (-3)",
			-4 => "dns lookup failure (-4)",
			-5 => "connection refused or timed out (-5)",
			default => "connection failed ({$errno})",
		};

		return false;
	}
/*======================================================================*\
	Function:	_disconnect
	Purpose:	disconnect a socket connection
	Input:		$fp	file pointer
\*======================================================================*/

	function _disconnect($fp)
	{
		return fclose($fp);
	}


/*======================================================================*\
	Function:	_prepare_post_body
	Purpose:	Prepare post body according to encoding type
	Input:		$formvars  - form variables
				$formfiles - form upload files
	Output:		post body
\*======================================================================*/

	function _prepare_post_body($formvars, $formfiles)
	{
		$formvars = (array) $formvars;
		$formfiles = (array) $formfiles;
		$postdata = '';

		if (count($formvars) === 0 && count($formfiles) === 0)
			return;

		$normalizeIterable = static function ($value): array {
			if ($value instanceof Traversable) {
				return iterator_to_array($value, false);
			}
			if (is_object($value)) {
				return get_object_vars($value);
			}
			return (array) $value;
		};

		switch ($this->_submit_type) {
			case "application/x-www-form-urlencoded":
				foreach ($formvars as $key => $val) {
					if (is_array($val) || $val instanceof Traversable || is_object($val)) {
						foreach ($normalizeIterable($val) as $cur_val) {
							$postdata .= urlencode((string) $key) . "[]=" . urlencode((string) $cur_val) . "&";
						}
					} else {
						$postdata .= urlencode((string) $key) . "=" . urlencode((string) $val) . "&";
					}
				}
				break;

			case "multipart/form-data":
				$this->_mime_boundary = "Snoopy" . md5(uniqid((string) microtime(), true));

				foreach ($formvars as $key => $val) {
					if (is_array($val) || $val instanceof Traversable || is_object($val)) {
						foreach ($normalizeIterable($val) as $cur_val) {
							$postdata .= "--" . $this->_mime_boundary . "\r\n";
							$postdata .= "Content-Disposition: form-data; name=\"{$key}[]\"\r\n\r\n";
							$postdata .= "{$cur_val}\r\n";
						}
					} else {
						$postdata .= "--" . $this->_mime_boundary . "\r\n";
						$postdata .= "Content-Disposition: form-data; name=\"{$key}\"\r\n\r\n";
						$postdata .= "{$val}\r\n";
					}
				}

				foreach ($formfiles as $field_name => $file_names) {
					if ($file_names instanceof Traversable) {
						$file_names = iterator_to_array($file_names, false);
					} elseif (!is_array($file_names)) {
						$file_names = $file_names !== null ? [$file_names] : [];
					}

					foreach ($file_names as $file_name) {
						if (!is_string($file_name) || $file_name === '' || !is_readable($file_name)) {
							continue;
						}

						$file_content = file_get_contents($file_name);
						if ($file_content === false) {
							continue;
						}
						$base_name = basename($file_name);

						$postdata .= "--" . $this->_mime_boundary . "\r\n";
						$postdata .= "Content-Disposition: form-data; name=\"{$field_name}\"; filename=\"{$base_name}\"\r\n\r\n";
						$postdata .= $file_content . "\r\n";
					}
				}
				$postdata .= "--" . $this->_mime_boundary . "--\r\n";
				break;
		}

		return $postdata;
	}
}
endif;
?>