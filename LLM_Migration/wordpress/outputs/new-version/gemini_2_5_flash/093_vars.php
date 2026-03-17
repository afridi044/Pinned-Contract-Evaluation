<?php
/**
 * Creates common globals for the rest of WordPress
 *
 * Sets $pagenow global which is the current page. Checks
 * for the browser to set which one is currently being used.
 *
 * Detects which user environment WordPress is being used on.
 * Only attempts to check for Apache, Nginx and IIS -- three web
 * servers with known pretty permalink capability.
 *
 * Note: Though Nginx is detected, WordPress does not currently
 * generate rewrite rules for it. See http://codex.wordpress.org/Nginx
 *
 * @package WordPress
 */

/**
 * @global string $pagenow The current page.
 * @global bool   $is_lynx Whether the browser is Lynx.
 * @global bool   $is_gecko Whether the browser is Gecko.
 * @global bool   $is_winIE Whether the browser is Windows Internet Explorer.
 * @global bool   $is_macIE Whether the browser is Mac Internet Explorer.
 * @global bool   $is_opera Whether the browser is Opera.
 * @global bool   $is_NS4 Whether the browser is Netscape 4.
 * @global bool   $is_safari Whether the browser is Safari.
 * @global bool   $is_chrome Whether the browser is Chrome.
 * @global bool   $is_iphone Whether the browser is iPhone.
 * @global bool   $is_IE Whether the browser is Internet Explorer (Windows or Mac).
 * @global bool   $is_apache Whether the server software is Apache or LiteSpeed.
 * @global bool   $is_IIS Whether the server software is IIS.
 * @global bool   $is_iis7 Whether the server software is IIS 7.X or greater.
 * @global bool   $is_nginx Whether the server software is Nginx.
 */
global $pagenow,
	$is_lynx, $is_gecko, $is_winIE, $is_macIE, $is_opera, $is_NS4, $is_safari, $is_chrome, $is_iphone, $is_IE,
	$is_apache, $is_IIS, $is_iis7, $is_nginx;

// On which page are we ?
$php_self = $_SERVER['PHP_SELF'] ?? '';
$self_matches = []; // Initialize to an empty array for robustness

if ( is_admin() ) {
	// wp-admin pages are checked more carefully
	if ( is_network_admin() ) {
		preg_match('#/wp-admin/network/?(.*?)$#i', $php_self, $self_matches);
	} elseif ( is_user_admin() ) {
		preg_match('#/wp-admin/user/?(.*?)$#i', $php_self, $self_matches);
	} else {
		preg_match('#/wp-admin/?(.*?)$#i', $php_self, $self_matches);
	}

	$pagenow = $self_matches[1] ?? ''; // Use null coalescing for safety
	$pagenow = trim($pagenow, '/');
	$pagenow = preg_replace('#\?.*?$#', '', $pagenow);

	if ( '' === $pagenow || 'index' === $pagenow || 'index.php' === $pagenow ) {
		$pagenow = 'index.php';
	} else {
		$inner_self_matches = []; // Use a new variable for inner preg_match
		preg_match('#(.*?)(/|$)#', $pagenow, $inner_self_matches);
		$pagenow = strtolower($inner_self_matches[1] ?? ''); // Use null coalescing for safety
		if ( '.php' !== substr($pagenow, -4, 4) ) {
			$pagenow .= '.php'; // for Options +Multiviews: /wp-admin/themes/index.php (themes.php is queried)
		}
	}
} else {
	if ( preg_match('#([^/]+\.php)([?/].*?)?$#i', $php_self, $self_matches) ) {
		$pagenow = strtolower($self_matches[1] ?? ''); // Use null coalescing for safety
	} else {
		$pagenow = 'index.php';
	}
}
unset($self_matches);

// Simple browser detection
$is_lynx = $is_gecko = $is_winIE = $is_macIE = $is_opera = $is_NS4 = $is_safari = $is_chrome = $is_iphone = false;

$http_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

if ( '' !== $http_user_agent ) { // Check if user agent string is not empty
	if ( str_contains($http_user_agent, 'Lynx') ) {
		$is_lynx = true;
	} elseif ( stripos($http_user_agent, 'chrome') !== false ) {
		if ( stripos( $http_user_agent, 'chromeframe' ) !== false ) {
			$is_admin = is_admin();
			/**
			 * Filter whether Google Chrome Frame should be used, if available.
			 *
			 * @since 3.2.0
			 *
			 * @param bool $is_admin Whether to use the Google Chrome Frame. Default is the value of is_admin().
			 */
			if ( $is_chrome = apply_filters( 'use_google_chrome_frame', $is_admin ) ) {
				header( 'X-UA-Compatible: chrome=1' );
			}
			$is_winIE = ! $is_chrome;
		} else {
			$is_chrome = true;
		}
	} elseif ( stripos($http_user_agent, 'safari') !== false ) {
		$is_safari = true;
	} elseif ( ( str_contains($http_user_agent, 'MSIE') || str_contains($http_user_agent, 'Trident') ) && str_contains($http_user_agent, 'Win') ) {
		$is_winIE = true;
	} elseif ( str_contains($http_user_agent, 'MSIE') && str_contains($http_user_agent, 'Mac') ) {
		$is_macIE = true;
	} elseif ( str_contains($http_user_agent, 'Gecko') ) {
		$is_gecko = true;
	} elseif ( str_contains($http_user_agent, 'Opera') ) {
		$is_opera = true;
	} elseif ( str_contains($http_user_agent, 'Nav') && str_contains($http_user_agent, 'Mozilla/4.') ) {
		$is_NS4 = true;
	}
}

if ( $is_safari && stripos($http_user_agent, 'mobile') !== false ) {
	$is_iphone = true;
}

$is_IE = ( $is_macIE || $is_winIE );

// Server detection

$server_software = $_SERVER['SERVER_SOFTWARE'] ?? '';

/**
 * Whether the server software is Apache or something else
 * @global bool $is_apache
 */
$is_apache = (str_contains($server_software, 'Apache') || str_contains($server_software, 'LiteSpeed'));

/**
 * Whether the server software is Nginx or something else
 * @global bool $is_nginx
 */
$is_nginx = str_contains($server_software, 'nginx');

/**
 * Whether the server software is IIS or something else
 * @global bool $is_IIS
 */
$is_IIS = !$is_apache && (str_contains($server_software, 'Microsoft-IIS') || str_contains($server_software, 'ExpressionDevServer'));

/**
 * Whether the server software is IIS 7.X or greater
 * @global bool $is_iis7
 */
$iis_version_pos = strpos( $server_software, 'Microsoft-IIS/' );
$is_iis7 = $is_IIS && intval( substr( $server_software, ( $iis_version_pos !== false ? $iis_version_pos + 14 : 0 ) ) ) >= 7;

/**
 * Test if the current browser runs on a mobile device (smart phone, tablet, etc.)
 *
 * @return bool true|false
 */
function wp_is_mobile(): bool {
	static $is_mobile;

	if ( isset($is_mobile) ) {
		return $is_mobile;
	}

	$http_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

	if ( empty($http_user_agent) ) {
		$is_mobile = false;
	} elseif ( str_contains($http_user_agent, 'Mobile') // many mobile devices (all iPhone, iPad, etc.)
		|| str_contains($http_user_agent, 'Android')
		|| str_contains($http_user_agent, 'Silk/')
		|| str_contains($http_user_agent, 'Kindle')
		|| str_contains($http_user_agent, 'BlackBerry')
		|| str_contains($http_user_agent, 'Opera Mini')
		|| str_contains($http_user_agent, 'Opera Mobi') ) {
			$is_mobile = true;
	} else {
		$is_mobile = false;
	}

	return $is_mobile;
}