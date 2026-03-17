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

global $pagenow,
	$is_lynx, $is_gecko, $is_winIE, $is_macIE, $is_opera, $is_NS4, $is_safari, $is_chrome, $is_iphone, $is_IE,
	$is_apache, $is_IIS, $is_iis7, $is_nginx;

$phpSelf = $_SERVER['PHP_SELF'] ?? '';

if (is_admin()) {
	$pattern = '#/wp-admin/?(.*?)$#i';

	if (is_network_admin()) {
		$pattern = '#/wp-admin/network/?(.*?)$#i';
	} elseif (is_user_admin()) {
		$pattern = '#/wp-admin/user/?(.*?)$#i';
	}

	$selfMatches = [];
	preg_match($pattern, $phpSelf, $selfMatches);

	$pagenow = trim($selfMatches[1] ?? '', '/');
	$pagenow = preg_replace('#\?.*?$#', '', $pagenow) ?? '';

	if ($pagenow === '' || $pagenow === 'index' || $pagenow === 'index.php') {
		$pagenow = 'index.php';
	} else {
		$selfMatches = [];
		if (preg_match('#(.*?)(/|$)#', $pagenow, $selfMatches)) {
			$pagenow = strtolower($selfMatches[1] ?? '');
		} else {
			$pagenow = strtolower($pagenow);
		}

		if ($pagenow === '') {
			$pagenow = 'index.php';
		} elseif (!str_ends_with($pagenow, '.php')) {
			$pagenow .= '.php'; // for Options +Multiviews: /wp-admin/themes/index.php (themes.php is queried)
		}
	}
} else {
	$selfMatches = [];
	if (preg_match('#([^/]+\.php)([?/].*?)?$#i', $phpSelf, $selfMatches)) {
		$pagenow = strtolower($selfMatches[1]);
	} else {
		$pagenow = 'index.php';
	}
}

// Simple browser detection
$is_lynx = $is_gecko = $is_winIE = $is_macIE = $is_opera = $is_NS4 = $is_safari = $is_chrome = $is_iphone = false;

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$userAgentLower = strtolower($userAgent);

if ($userAgent !== '') {
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
			$useChromeFrame = apply_filters('use_google_chrome_frame', $is_admin);
			if ($useChromeFrame) {
				header('X-UA-Compatible: chrome=1');
			}
			$is_chrome = (bool) $useChromeFrame;
			$is_winIE = ! $is_chrome;
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

if ($is_safari && str_contains($userAgentLower, 'mobile')) {
	$is_iphone = true;
}

$is_IE = ($is_macIE || $is_winIE);

// Server detection

/**
 * Whether the server software is Apache or something else
 * @global bool $is_apache
 */
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '';
$serverSoftwareLower = strtolower($serverSoftware);

$is_apache = str_contains($serverSoftwareLower, 'apache') || str_contains($serverSoftwareLower, 'litespeed');

/**
 * Whether the server software is Nginx or something else
 * @global bool $is_nginx
 */
$is_nginx = str_contains($serverSoftwareLower, 'nginx');

/**
 * Whether the server software is IIS or something else
 * @global bool $is_IIS
 */
$is_IIS = ! $is_apache && (str_contains($serverSoftwareLower, 'microsoft-iis') || str_contains($serverSoftwareLower, 'expressiondevserver'));

/**
 * Whether the server software is IIS 7.X or greater
 * @global bool $is_iis7
 */
$is_iis7 = false;

if ($is_IIS) {
	$iisPosition = stripos($serverSoftware, 'Microsoft-IIS/');
	if ($iisPosition !== false) {
		$iisVersion = (int) substr($serverSoftware, $iisPosition + 14);
		$is_iis7 = $iisVersion >= 7;
	}
}

/**
 * Test if the current browser runs on a mobile device (smart phone, tablet, etc.)
 *
 * @return bool true|false
 */
function wp_is_mobile(): bool {
	static $is_mobile;

	if (isset($is_mobile)) {
		return $is_mobile;
	}

	$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

	if ($userAgent === '') {
		$is_mobile = false;
	} else {
		$is_mobile = str_contains($userAgent, 'Mobile')
			|| str_contains($userAgent, 'Android')
			|| str_contains($userAgent, 'Silk/')
			|| str_contains($userAgent, 'Kindle')
			|| str_contains($userAgent, 'BlackBerry')
			|| str_contains($userAgent, 'Opera Mini')
			|| str_contains($userAgent, 'Opera Mobi');
	}

	return $is_mobile;
}
?>