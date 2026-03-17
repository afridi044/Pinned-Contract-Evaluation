<?php

declare(strict_types=1);

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

global $pagenow,
	$is_lynx, $is_gecko, $is_winIE, $is_macIE, $is_opera, $is_NS4, $is_safari, $is_chrome, $is_iphone, $is_IE,
	$is_apache, $is_IIS, $is_iis7, $is_nginx;

// On which page are we ?
$phpSelf = $_SERVER['PHP_SELF'] ?? '';

if (is_admin()) {
	// wp-admin pages are checked more carefully
	if (is_network_admin()) {
		preg_match('#/wp-admin/network/?(.*?)$#i', $phpSelf, $self_matches);
	} elseif (is_user_admin()) {
		preg_match('#/wp-admin/user/?(.*?)$#i', $phpSelf, $self_matches);
	} else {
		preg_match('#/wp-admin/?(.*?)$#i', $phpSelf, $self_matches);
	}

	$pagenow = $self_matches[1] ?? '';
	$pagenow = trim($pagenow, '/');
	$pagenow = preg_replace('#\?.*?$#', '', $pagenow);

	if ($pagenow === '' || $pagenow === 'index' || $pagenow === 'index.php') {
		$pagenow = 'index.php';
	} else {
		preg_match('#(.*?)(/|$)#', $pagenow, $self_matches);
		$pagenow = strtolower($self_matches[1] ?? '');
		if (!str_ends_with($pagenow, '.php')) {
			$pagenow .= '.php'; // for Options +Multiviews: /wp-admin/themes/index.php (themes.php is queried)
		}
	}
} else {
	if (preg_match('#([^/]+\.php)([?/].*?)?$#i', $phpSelf, $self_matches)) {
		$pagenow = strtolower($self_matches[1]);
	} else {
		$pagenow = 'index.php';
	}
}
unset($self_matches);

// Simple browser detection
$is_lynx = $is_gecko = $is_winIE = $is_macIE = $is_opera = $is_NS4 = $is_safari = $is_chrome = $is_iphone = false;

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

if ($userAgent !== '') {
	// str_contains is case-sensitive. For case-insensitivity (like original stripos), we use strtolower.
	$userAgentLower = strtolower($userAgent);

	if (str_contains($userAgent, 'Lynx')) {
		$is_lynx = true;
	} elseif (str_contains($userAgentLower, 'chrome')) {
		if (str_contains($userAgentLower, 'chromeframe')) {
			$is_admin = is_admin();
			/**
			 * Filter whether Google Chrome Frame should be used, if available.
			 *
			 * @since 3.2.0
			 *
			 * @param bool $is_admin Whether to use the Google Chrome Frame. Default is the value of is_admin().
			 */
			if ($is_chrome = apply_filters('use_google_chrome_frame', $is_admin)) {
				header('X-UA-Compatible: chrome=1');
			}
			$is_winIE = !$is_chrome;
		} else {
			$is_chrome = true;
		}
	} elseif (str_contains($userAgentLower, 'safari')) {
		$is_safari = true;
	} elseif ((str_contains($userAgent, 'MSIE') || str_contains($userAgent, 'Trident')) && str_contains($userAgent, 'Win')) {
		$is_winIE = true;
	} elseif (str_contains($userAgent, 'MSIE') && str_contains($userAgent, 'Mac')) {
		$is_macIE = true;
	} elseif (str_contains($userAgent, 'Gecko')) {
		$is_gecko = true;
	} elseif (str_contains($userAgent, 'Opera')) {
		$is_opera = true;
	} elseif (str_contains($userAgent, 'Nav') && str_contains($userAgent, 'Mozilla/4.')) {
		$is_NS4 = true;
	}
}

if ($is_safari && isset($userAgentLower) && str_contains($userAgentLower, 'mobile')) {
	$is_iphone = true;
}

$is_IE = ($is_macIE || $is_winIE);

// Server detection
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '';

/**
 * Whether the server software is Apache or something else.
 * @global bool $is_apache
 */
$is_apache = str_contains($serverSoftware, 'Apache') || str_contains($serverSoftware, 'LiteSpeed');

/**
 * Whether the server software is Nginx or something else.
 * @global bool $is_nginx
 */
$is_nginx = str_contains($serverSoftware, 'nginx');

/**
 * Whether the server software is IIS or something else.
 * @global bool $is_IIS
 */
$is_IIS = !$is_apache && (str_contains($serverSoftware, 'Microsoft-IIS') || str_contains($serverSoftware, 'ExpressionDevServer'));

/**
 * Whether the server software is IIS 7.X or greater.
 * @global bool $is_iis7
 */
$is_iis7 = false;
if ($is_IIS) {
	$iisVersionPosition = strpos($serverSoftware, 'Microsoft-IIS/');
	if ($iisVersionPosition !== false) {
		$versionString = substr($serverSoftware, $iisVersionPosition + 14);
		$is_iis7 = (int) $versionString >= 7;
	}
}

/**
 * Test if the current browser runs on a mobile device (smart phone, tablet, etc.).
 *
 * @return bool True if the browser is a mobile device, false otherwise.
 */
function wp_is_mobile(): bool
{
	static $is_mobile = null;

	if ($is_mobile !== null) {
		return $is_mobile;
	}

	$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
	if ($userAgent === '') {
		return $is_mobile = false;
	}

	$mobileKeywords = [
		'Mobile',
		'Android',
		'Silk/',
		'Kindle',
		'BlackBerry',
		'Opera Mini',
		'Opera Mobi',
	];

	foreach ($mobileKeywords as $keyword) {
		if (str_contains($userAgent, $keyword)) {
			return $is_mobile = true;
		}
	}

	return $is_mobile = false;
}