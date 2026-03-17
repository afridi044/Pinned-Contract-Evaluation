<?php

declare(strict_types=1);

/**
 * Multisite upload handler.
 *
 * @since 3.0.0
 *
 * @package WordPress
 * @subpackage Multisite
 */

// Bootstrap a minimal WordPress environment.
define('SHORTINIT', true);
require_once dirname(__DIR__) . '/wp-load.php';

if (!is_multisite()) {
    exit('Multisite support not enabled');
}

// Set up multisite file constants like BLOGUPLOADDIR.
ms_file_constants();

// Suppress errors. This is a legacy practice, but maintained for functional
// equivalence to prevent potential warnings from file operations breaking the output.
error_reporting(0);

// The $current_blog global is set by the WordPress bootstrap.
if ($current_blog->archived === '1' || $current_blog->spam === '1' || $current_blog->deleted === '1') {
    status_header(404);
    exit('404 &#8212; File not found.');
}

// Sanitize the filename to prevent directory traversal attacks.
$filename = str_replace('..', '', $_GET['file'] ?? '');
$file = rtrim(BLOGUPLOADDIR, '/') . '/' . $filename;

if ($filename === '' || !is_file($file)) {
    status_header(404);
    exit('404 &#8212; File not found.');
}

// Determine the file's MIME type.
$mime = wp_check_filetype($file);
$mimeType = $mime['type'];

// Fallback to finfo if WordPress can't determine the type (replaces deprecated mime_content_type).
if ($mimeType === false && function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mimeType = finfo_file($finfo, $file);
        finfo_close($finfo);
    }
}

// Final fallback to a simple guess based on the file extension.
$mimetype = $mimeType ?: 'image/' . pathinfo($file, PATHINFO_EXTENSION);

// --- Send HTTP Headers ---
header('Content-Type: ' . $mimetype);

// Do not send Content-Length for IIS, as it may cause issues.
if (!str_contains($_SERVER['SERVER_SOFTWARE'] ?? '', 'Microsoft-IIS')) {
    header('Content-Length: ' . filesize($file));
}

// Optional: Offload file serving to the web server for better performance.
if (defined('WPMU_ACCEL_REDIRECT') && WPMU_ACCEL_REDIRECT) {
    header('X-Accel-Redirect: ' . str_replace(WP_CONTENT_DIR, '', $file));
    exit;
}

if (defined('WPMU_SENDFILE') && WPMU_SENDFILE) {
    header('X-Sendfile: ' . $file);
    exit;
}

// --- Caching Headers ---
$fileModificationTimestamp = filemtime($file);
$lastModified = gmdate('D, d M Y H:i:s', $fileModificationTimestamp);
$etag = '"' . md5($lastModified) . '"';

header("Last-Modified: {$lastModified} GMT");
header("ETag: {$etag}");
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 100_000_000) . ' GMT');

// --- Conditional GET Support ---
// Check if the client's cached version is still valid.
$clientEtag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? stripslashes($_SERVER['HTTP_IF_NONE_MATCH']) : null;
$clientLastModified = trim($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '');
$clientModifiedTimestamp = $clientLastModified ? strtotime($clientLastModified) : 0;

$etagMatches = ($clientEtag !== null && $clientEtag === $etag);
$timeMatches = ($clientModifiedTimestamp !== 0 && $clientModifiedTimestamp >= $fileModificationTimestamp);

// The original logic: if both headers are present, both must match.
// If only one is present, that one must match.
$notModified = false;
if ($clientLastModified && $clientEtag) {
    if ($timeMatches && $etagMatches) {
        $notModified = true;
    }
} elseif ($timeMatches || $etagMatches) {
    $notModified = true;
}

if ($notModified) {
    status_header(304); // Not Modified
    exit;
}

// If we reach this point, serve the file content.
readfile($file);
exit;