<?php
/**
 * Multisite upload handler.
 *
 * @since 3.0.0
 *
 * @package WordPress
 * @subpackage Multisite
 */

if (! defined('SHORTINIT')) {
	define('SHORTINIT', true);
}

require dirname(__DIR__) . '/wp-load.php';

if (! is_multisite()) {
	exit('Multisite support not enabled');
}

ms_file_constants();

error_reporting(0);

global $current_blog;

$blogStatuses = [
	(int) ($current_blog->archived ?? 0),
	(int) ($current_blog->spam ?? 0),
	(int) ($current_blog->deleted ?? 0),
];

if (in_array(1, $blogStatuses, true)) {
	status_header(404);
	exit('404 &#8212; File not found.');
}

$requestedFile = filter_input(INPUT_GET, 'file', FILTER_UNSAFE_RAW) ?? '';

if ($requestedFile === '') {
	status_header(404);
	exit('404 &#8212; File not found.');
}

$filePath = rtrim(BLOGUPLOADDIR, "/\\") . '/' . str_replace('..', '', $requestedFile);

if (! is_file($filePath)) {
	status_header(404);
	exit('404 &#8212; File not found.');
}

$mime = wp_check_filetype($filePath);
$mimeType = $mime['type'] ?? null;

if (empty($mimeType) && function_exists('mime_content_type')) {
	$mimeType = mime_content_type($filePath) ?: null;
}

if (empty($mimeType)) {
	$extension = pathinfo($filePath, PATHINFO_EXTENSION);
	$mimeType = $extension ? 'image/' . strtolower((string) $extension) : 'application/octet-stream';
}

header('Content-Type: ' . $mimeType);

$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '';

if (! str_contains($serverSoftware, 'Microsoft-IIS')) {
	header('Content-Length: ' . (string) filesize($filePath));
}

if (defined('WPMU_ACCEL_REDIRECT') && WPMU_ACCEL_REDIRECT) {
	header('X-Accel-Redirect: ' . str_replace(WP_CONTENT_DIR, '', $filePath));
	exit;
}

if (defined('WPMU_SENDFILE') && WPMU_SENDFILE) {
	header('X-Sendfile: ' . $filePath);
	exit;
}

$fileMtime = filemtime($filePath) ?: time();
$lastModified = gmdate('D, d M Y H:i:s', $fileMtime);
$etag = '"' . md5($lastModified) . '"';

header("Last-Modified: {$lastModified} GMT");
header('ETag: ' . $etag);
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 100000000) . ' GMT');

$clientEtag = stripslashes($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
$clientLastModifiedHeader = trim($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '');
$clientModifiedTimestamp = $clientLastModifiedHeader !== '' ? (strtotime($clientLastModifiedHeader) ?: 0) : 0;
$modifiedTimestamp = $fileMtime;

if ($clientLastModifiedHeader !== '' && $clientEtag !== '') {
	if ($clientModifiedTimestamp >= $modifiedTimestamp && $clientEtag === $etag) {
		status_header(304);
		exit;
	}
} elseif ($clientModifiedTimestamp >= $modifiedTimestamp || $clientEtag === $etag) {
	status_header(304);
	exit;
}

readfile($filePath);
exit;
?>