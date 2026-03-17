<?php

declare(strict_types=1);

/**
 * Handle Trackbacks and Pingbacks Sent to WordPress.
 *
 * @since 0.71
 *
 * @package WordPress
 * @subpackage Trackbacks
 */

if (empty($wp)) {
	require_once __DIR__ . '/wp-load.php';
	wp(['tb' => '1']);
}

/**
 * Responds to a trackback with an error or success XML message.
 *
 * @since 0.71
 *
 * @param bool   $error         Whether there was an error. Defaults to false.
 * @param string $error_message Error message if an error occurred.
 */
function trackback_response(bool $error = false, string $error_message = ''): void
{
	$charset = get_option('blog_charset');
	header("Content-Type: text/xml; charset={$charset}");

	if ($error) {
		$xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<response>
<error>1</error>
<message>{$error_message}</message>
</response>
XML;
		echo $xml;
		die();
	}

	$xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<response>
<error>0</error>
</response>
XML;
	echo $xml;
}

// This is a fallback for when the ID isn't in the query vars after wp() is called.
$tb_id = 0;
if (empty($_GET['tb_id'])) {
	$path_parts = explode('/', $_SERVER['REQUEST_URI']);
	$tb_id      = (int) end($path_parts);
}

// Extract trackback data from the POST request using the null coalescing operator.
$tb_url  = $_POST['url'] ?? '';
$charset = $_POST['charset'] ?? '';

// These three are unslashed here so they can be properly escaped after mb_convert_encoding().
$title     = wp_unslash($_POST['title'] ?? '');
$excerpt   = wp_unslash($_POST['excerpt'] ?? '');
$blog_name = wp_unslash($_POST['blog_name'] ?? '');

if ($charset) {
	$charset = str_replace([',', ' '], '', strtoupper(trim($charset)));
} else {
	$charset = 'ASCII,UTF-8,ISO-8859-1,JIS,EUC-JP,SJIS';
}

// No valid uses for UTF-7.
if (str_contains($charset, 'UTF-7')) {
	die;
}

// For international trackbacks, convert encoding to the blog's charset.
if (function_exists('mb_convert_encoding')) {
	$blog_charset = get_option('blog_charset');
	$title        = mb_convert_encoding($title, $blog_charset, $charset);
	$excerpt      = mb_convert_encoding($excerpt, $blog_charset, $charset);
	$blog_name    = mb_convert_encoding($blog_name, $blog_charset, $charset);
}

// Now that mb_convert_encoding() has been run, escape data for database insertion.
$title     = wp_slash($title);
$excerpt   = wp_slash($excerpt);
$blog_name = wp_slash($blog_name);

// The wp() call should have set the query. This is the canonical way to get the post ID.
if (is_single() || is_page()) {
	$tb_id = (int) $posts[0]->ID;
}

if (empty($tb_id)) {
	trackback_response(true, 'I really need an ID for this to work.');
}

// If it doesn't look like a trackback at all, redirect to the post.
if (empty($title) && empty($tb_url) && empty($blog_name)) {
	wp_redirect(get_permalink($tb_id));
	exit;
}

if (! empty($tb_url) && ! empty($title)) {
	if (! pings_open($tb_id)) {
		trackback_response(true, 'Sorry, trackbacks are closed for this item.');
	}

	$title   = wp_html_excerpt($title, 250, '&#8230;');
	$excerpt = wp_html_excerpt($excerpt, 252, '&#8230;');

	$comment_post_ID      = $tb_id;
	$comment_author       = $blog_name;
	$comment_author_email = '';
	$comment_author_url   = $tb_url;
	$comment_content      = "<strong>{$title}</strong>\n\n{$excerpt}";
	$comment_type         = 'trackback';

	// Check for duplicate trackbacks from the same URL to the same post.
	$dupe = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT comment_ID FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_author_url = %s",
			$comment_post_ID,
			$comment_author_url
		)
	);

	if ($dupe) {
		trackback_response(true, 'We already have a ping from that URL for this post.');
	}

	$commentdata = compact(
		'comment_post_ID',
		'comment_author',
		'comment_author_email',
		'comment_author_url',
		'comment_content',
		'comment_type'
	);

	$trackback_id = wp_new_comment($commentdata);

	/**
	 * Fires after a trackback is added to a post.
	 *
	 * @since 1.2.0
	 *
	 * @param int $trackback_id The new trackback/comment ID.
	 */
	do_action('trackback_post', $trackback_id);
	trackback_response();
}