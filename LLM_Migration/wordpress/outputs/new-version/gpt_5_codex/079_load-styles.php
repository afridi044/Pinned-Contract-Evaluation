<?php
declare(strict_types=1);

/**
 * Disable error reporting
 *
 * Set this to error_reporting(-1) for debugging.
 */
error_reporting(0);

/** Set ABSPATH for execution */
const ABSPATH = dirname(__DIR__) . DIRECTORY_SEPARATOR;
const WPINC = 'wp-includes';

/**
 * @ignore
 */
function __(): void {}

/**
 * @ignore
 */
function _x(): void {}

/**
 * @ignore
 */
function add_filter(): void {}

/**
 * @ignore
 */
function esc_attr(): void {}

/**
 * @ignore
 */
function apply_filters(): void {}

/**
 * @ignore
 */
function get_option(): void {}

/**
 * @ignore
 */
function is_lighttpd_before_150(): void {}

/**
 * @ignore
 */
function add_action(): void {}

/**
 * @ignore
 */
function do_action_ref_array(): void {}

/**
 * @ignore
 */
function get_bloginfo(): void {}

/**
 * @ignore
 */
function is_admin(): bool
{
    return true;
}

/**
 * @ignore
 */
function site_url(): void {}

/**
 * @ignore
 */
function admin_url(): void {}

/**
 * @ignore
 */
function wp_guess_url(): void {}

function get_file(string $path): string
{
    $resolvedPath = function_exists('realpath') ? realpath($path) : $path;
    if ($resolvedPath === false || $resolvedPath === '' || !is_file($resolvedPath)) {
        return '';
    }

    return file_get_contents($resolvedPath) ?: '';
}

require_once ABSPATH . WPINC . '/script-loader.php';
require_once ABSPATH . WPINC . '/version.php';

$loadParam = preg_replace('/[^a-z0-9,_-]+/i', '', $_GET['load'] ?? '');
$load       = array_unique(array_filter(explode(',', $loadParam)));

if ($load === []) {
    exit;
}

$compress      = isset($_GET['c']) && (bool) $_GET['c'];
$forceGzip     = $compress && ($_GET['c'] ?? '') === 'gzip';
$rtl           = ($_GET['dir'] ?? '') === 'rtl';
$expiresOffset = 31536000; // 1 year
$out           = '';

$wp_styles = new WP_Styles();
wp_default_styles($wp_styles);

foreach ($load as $handle) {
    if (!array_key_exists($handle, $wp_styles->registered)) {
        continue;
    }

    $style = $wp_styles->registered[$handle];
    $path  = ABSPATH . ltrim($style->src, '/');

    if ($rtl && !empty($style->extra['rtl'])) {
        // All default styles have fully independent RTL files.
        $path = str_replace('.min.css', '-rtl.min.css', $path);
    }

    $content = get_file($path) . "\n";

    if (str_starts_with($style->src, '/' . WPINC . '/css/')) {
        $content = str_replace('../images/', '../' . WPINC . '/images/', $content);
        $content = str_replace('../js/tinymce/', '../' . WPINC . '/js/tinymce/', $content);
        $content = str_replace('../fonts/', '../' . WPINC . '/fonts/', $content);
        $out    .= $content;
    } else {
        $out .= str_replace('../images/', 'images/', $content);
    }
}

header('Content-Type: text/css; charset=UTF-8');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expiresOffset) . ' GMT');
header("Cache-Control: public, max-age={$expiresOffset}");

if (
    $compress
    && !ini_get('zlib.output_compression')
    && ini_get('output_handler') !== 'ob_gzhandler'
    && isset($_SERVER['HTTP_ACCEPT_ENCODING'])
) {
    header('Vary: Accept-Encoding'); // Handle proxies
    $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'];

    if (!$forceGzip && str_contains($acceptEncoding, 'deflate') && function_exists('gzdeflate')) {
        header('Content-Encoding: deflate');
        $out = gzdeflate($out, 3);
    } elseif (str_contains($acceptEncoding, 'gzip') && function_exists('gzencode')) {
        header('Content-Encoding: gzip');
        $out = gzencode($out, 3);
    }
}

echo $out;
exit;
?>