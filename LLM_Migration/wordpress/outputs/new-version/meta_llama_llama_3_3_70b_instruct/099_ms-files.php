<?php
/**
 * Multisite upload handler.
 *
 * @since 3.0.0
 *
 * @package WordPress
 * @subpackage Multisite
 */

define('SHORTINIT', true);
require_once(dirname(dirname(__FILE__)) . '/wp-load.php');

if (!is_multisite()) {
    die('Multisite support not enabled');
}

ms_file_constants();

error_reporting(0);

if ($current_blog->archived === '1' || $current_blog->spam === '1' || $current_blog->deleted === '1') {
    status_header(404);
    die('404 &#8212; File not found.');
}

$file = rtrim(BLOGUPLOADDIR, '/') . '/' . str_replace('..', '', $_GET['file'] ?? '');
if (!is_file($file)) {
    status_header(404);
    die('404 &#8212; File not found.');
}

$mime = wp_check_filetype($file);
if (false === $mime['type'] && function_exists('mime_content_type')) {
    $mime['type'] = mime_content_type($file);
}

$mimetype = $mime['type'] ?? 'image/' . substr($file, strrpos($file, '.') + 1);

header('Content-Type: ' . $mimetype); // always send this
if (false === strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS')) {
    header('Content-Length: ' . filesize($file));
}

// Optional support for X-Sendfile and X-Accel-Redirect
if (WPMU_ACCEL_REDIRECT) {
    header('X-Accel-Redirect: ' . str_replace(WP_CONTENT_DIR, '', $file));
    exit;
} elseif (WPMU_SENDFILE) {
    header('X-Sendfile: ' . $file);
    exit;
}

$lastModified = gmdate('D, d M Y H:i:s', filemtime($file));
$etag = '"' . md5($lastModified) . '"';
header("Last-Modified: $lastModified GMT");
header('ETag: ' . $etag);
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 100000000) . ' GMT');

// Support for Conditional GET - use stripslashes to avoid formatting.php dependency
$clientEtag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? false;
$clientEtag = stripslashes($clientEtag);

$_SERVER['HTTP_IF_MODIFIED_SINCE'] ??= false;
$clientLastModified = trim($_SERVER['HTTP_IF_MODIFIED_SINCE']);
// If string is empty, return 0. If not, attempt to parse into a timestamp
$clientModifiedTimestamp = $clientLastModified ? strtotime($clientLastModified) : 0;

// Make a timestamp for our most recent modification...
$modifiedTimestamp = strtotime($lastModified);

if (($clientLastModified && $clientEtag)
    ? (($clientModifiedTimestamp >= $modifiedTimestamp) && ($clientEtag === $etag))
    : (($clientModifiedTimestamp >= $modifiedTimestamp) || ($clientEtag === $etag))
) {
    status_header(304);
    exit;
}

// If we made it this far, just serve the file
readfile($file);