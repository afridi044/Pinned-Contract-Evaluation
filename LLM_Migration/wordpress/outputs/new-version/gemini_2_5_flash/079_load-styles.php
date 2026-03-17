<?php

/**
 * Disable error reporting
 *
 * Set this to error_reporting( -1 ) for debugging
 */
error_reporting(0);

/** Set ABSPATH for execution */
define( 'ABSPATH', dirname(__DIR__, 2) . '/' ); // Modernized: Use __DIR__ and depth for dirname
define( 'WPINC', 'wp-includes' );

/**
 * @ignore
 */
function __(): void {} // Added return type void

/**
 * @ignore
 */
function _x(): void {} // Added return type void

/**
 * @ignore
 */
function add_filter(): void {} // Added return type void

/**
 * @ignore
 */
function esc_attr(): void {} // Added return type void

/**
 * @ignore
 */
function apply_filters(): void {} // Added return type void

/**
 * @ignore
 */
function get_option(): void {} // Added return type void

/**
 * @ignore
 */
function is_lighttpd_before_150(): void {} // Added return type void

/**
 * @ignore
 */
function add_action(): void {} // Added return type void

/**
 * @ignore
 */
function do_action_ref_array(): void {} // Added return type void

/**
 * @ignore
 */
function get_bloginfo(): void {} // Added return type void

/**
 * @ignore
 */
function is_admin(): bool { return true; } // Added return type bool

/**
 * @ignore
 */
function site_url(): void {} // Added return type void

/**
 * @ignore
 */
function admin_url(): void {} // Added return type void

/**
 * @ignore
 */
function wp_guess_url(): void {} // Added return type void

function get_file(string $path): string { // Added scalar type hints

	// realpath is always available, no need for function_exists check
	$path = realpath($path);

	// Use block statements for clarity
	if ( ! $path || ! @is_file($path) ) {
		return '';
	}

	return @file_get_contents($path);
}

require ABSPATH . WPINC . '/script-loader.php'; // Removed parentheses for require/include
require ABSPATH . WPINC . '/version.php'; // Removed parentheses for require/include

// Use null coalescing operator for $_GET access for safety
$load = preg_replace( '/[^a-z0-9,_-]+/i', '', $_GET['load'] ?? '' );
$load = array_unique( explode( ',', $load ) );

if ( empty($load) ) { // Converted to block statement
	exit;
}

// Maintain original truthiness logic for $compress
$compress = ( isset($_GET['c']) && $_GET['c'] );
$force_gzip = ( $compress && 'gzip' === ($_GET['c'] ?? '') ); // Use strict comparison and null coalescing
$rtl = ( 'rtl' === ($_GET['dir'] ?? '') ); // Use strict comparison and null coalescing
$expires_offset = 31536000; // 1 year
$out = '';

$wp_styles = new WP_Styles();
wp_default_styles($wp_styles);

foreach( $load as $handle ) {
	// Use isset for checking array keys, which is generally preferred over array_key_exists
	if ( !isset($wp_styles->registered[$handle]) ) { // Converted to block statement
		continue;
	}

	$style = $wp_styles->registered[$handle];
	$path = ABSPATH . $style->src;

	if ( $rtl && ! empty( $style->extra['rtl'] ) ) {
		// All default styles have fully independent RTL files.
		$path = str_replace( '.min.css', '-rtl.min.css', $path );
	}

	$content = get_file( $path ) . "\n";

	// Modernized: Use str_starts_with for cleaner check than strpos === 0
	if ( str_starts_with( $style->src, '/' . WPINC . '/css/' ) ) {
		$content = str_replace( '../images/', '../' . WPINC . '/images/', $content );
		$content = str_replace( '../js/tinymce/', '../' . WPINC . '/js/tinymce/', $content );
		$content = str_replace( '../fonts/', '../' . WPINC . '/fonts/', $content );
		$out .= $content;
	} else {
		$out .= str_replace( '../images/', 'images/', $content );
	}
}

header('Content-Type: text/css; charset=UTF-8');
header('Expires: ' . gmdate( "D, d M Y H:i:s", time() + $expires_offset ) . ' GMT');
header("Cache-Control: public, max-age=$expires_offset");

// Use strict comparison for ini_get and null coalescing for $_SERVER access
if ( $compress && ! ini_get('zlib.output_compression') && 'ob_gzhandler' !== ini_get('output_handler') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) ) {
	header('Vary: Accept-Encoding'); // Handle proxies
	$accept_encoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''; // Get encoding once, safely
	// Retain stripos for case-insensitive matching as in original code
	if ( false !== stripos($accept_encoding, 'deflate') && function_exists('gzdeflate') && ! $force_gzip ) {
		header('Content-Encoding: deflate');
		$out = gzdeflate( $out, 3 );
	} elseif ( false !== stripos($accept_encoding, 'gzip') && function_exists('gzencode') ) {
		header('Content-Encoding: gzip');
		$out = gzencode( $out, 3 );
	}
}

echo $out;
exit;