<?php

declare(strict_types=1);

/**
 * Disable error reporting.
 *
 * This is a production script for serving assets. Errors are suppressed
 * to prevent breaking the CSS output. For debugging, set to error_reporting(-1).
 */
error_reporting(0);

/** Set ABSPATH for execution */
define('ABSPATH', dirname(__DIR__) . '/');
define('WPINC', 'wp-includes');

/**
 * WordPress function stubs.
 *
 * These mock functions are defined to prevent fatal errors when the included
 * WordPress files (`script-loader.php`, `version.php`) are processed.
 * They use variadic arguments (`...$args`) to maintain compatibility
 * with any function signature used in the included files.
 *
 * @ignore
 */
function __(...$args): string { return (string)($args[0] ?? ''); }
function _x(...$args): string { return (string)($args[0] ?? ''); }
function add_filter(...$args): void {}
function esc_attr(...$args): string { return (string)($args[0] ?? ''); }
function apply_filters(...$args): mixed { return $args[1] ?? null; }
function get_option(...$args): mixed { return $args[1] ?? false; }
function is_lighttpd_before_150(): bool { return false; }
function add_action(...$args): void {}
function do_action_ref_array(...$args): void {}
function get_bloginfo(...$args): string { return ''; }
function is_admin(): bool { return true; }
function site_url(...$args): string { return ''; }
function admin_url(...$args): string { return ''; }
function wp_guess_url(...$args): string { return ''; }

/**
 * Safely retrieves the contents of a file.
 *
 * @param string $path The path to the file.
 * @return string The file contents or an empty string on failure.
 */
function get_file(string $path): string
{
    $realPath = realpath($path);

    if ($realPath === false || !is_file($realPath)) {
        return '';
    }

    $content = file_get_contents($realPath);

    return $content === false ? '' : $content;
}

// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
// These files are expected to define globals like $wp_version and classes like WP_Styles.
require ABSPATH . WPINC . '/script-loader.php';
require ABSPATH . WPINC . '/version.php';
// phpcs:enable

$loadRaw = $_GET['load'] ?? '';
$loadSanitized = preg_replace('/[^a-z0-9,_-]+/i', '', $loadRaw);
$load = array_filter(array_unique(explode(',', $loadSanitized)));

if (empty($load)) {
    exit;
}

$compress = !empty($_GET['c']);
$force_gzip = $compress && ($_GET['c'] === 'gzip');
$rtl = ($_GET['dir'] ?? '') === 'rtl';
$expires_offset = 31536000; // 1 year
$out = '';

$wp_styles = new WP_Styles();
wp_default_styles($wp_styles);

foreach ($load as $handle) {
    if (!isset($wp_styles->registered[$handle])) {
        continue;
    }

    $style = $wp_styles->registered[$handle];
    $path = ABSPATH . $style->src;

    if ($rtl && !empty($style->extra['rtl'])) {
        // All default styles have fully independent RTL files.
        $path = str_replace('.min.css', '-rtl.min.css', $path);
    }

    $content = get_file($path) . "\n";

    if (str_starts_with($style->src, '/' . WPINC . '/css/')) {
        $content = str_replace(
            ['../images/', '../js/tinymce/', '../fonts/'],
            ['../' . WPINC . '/images/', '../' . WPINC . '/js/tinymce/', '../' . WPINC . '/fonts/'],
            $content
        );
        $out .= $content;
    } else {
        $out .= str_replace('../images/', 'images/', $content);
    }
}

header('Content-Type: text/css; charset=UTF-8');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires_offset) . ' GMT');
header("Cache-Control: public, max-age=$expires_offset");

if ($compress && !ini_get('zlib.output_compression') && 'ob_gzhandler' !== ini_get('output_handler') && isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
    header('Vary: Accept-Encoding'); // Handle proxies

    $acceptEncoding = strtolower($_SERVER['HTTP_ACCEPT_ENCODING']);

    if (!$force_gzip && str_contains($acceptEncoding, 'deflate') && function_exists('gzdeflate')) {
        header('Content-Encoding: deflate');
        $out = gzdeflate($out, 3);
    } elseif (str_contains($acceptEncoding, 'gzip') && function_exists('gzencode')) {
        header('Content-Encoding: gzip');
        $out = gzencode($out, 3);
    }
}

echo $out;
exit;