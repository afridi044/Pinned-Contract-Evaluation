<?php
declare(strict_types=1);

/**
 * Handle Trackbacks and Pingbacks Sent to WordPress
 *
 * @since 0.71
 *
 * @package WordPress
 * @subpackage Trackbacks
 */

global $wp, $wpdb, $posts;

if (empty($wp)) {
	require_once __DIR__ . '/wp-load.php';
	wp(['tb' => '1']);
}

/**
 * Response to a trackback.
 *
 * Responds with an error or success XML message.
 *
 * @since 0.71
 *
 * @param int|bool $error         Whether there was an error.
 *                                Default '0'. Accepts '0' or '1'.
 * @param string   $error_message Error message if an error occurred.
 */
function trackback_response(int|bool $error = 0, string $error_message = ''): void {
	header('Content-Type: text/xml; charset=' . get_option('blog_charset'));

	echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
	echo "<response>\n";

	if ($error) {
		echo "<error>1</error>\n";
		echo "<message>{$error_message}</message>\n";
		echo "</response>";
		exit;
	}

	echo "<error>0</error>\n";
	echo "</response>";
}

$tb_id = !empty($_GET['tb_id']) ? (int) $_GET['tb_id'] : null;

if ($tb_id === null) {
	$request_uri = $_SERVER['REQUEST_URI'] ?? '';
	if ($request_uri !== '') {
		$tb_id_segments = explode('/', $request_uri);
		$last_segment   = $tb_id_segments[array_key_last($tb_id_segments)] ?? '';
		$tb_id          = (int) $last_segment;
	}
}

$tb_url    = $_POST['url'] ?? '';
$charset   = $_POST['charset'] ?? '';
$title     = wp_unslash($_POST['title'] ?? '');
$excerpt   = wp_unslash($_POST['excerpt'] ?? '');
$blog_name = wp_unslash($_POST['blog_name'] ?? '');

if ($charset !== '') {
	$charset = str_replace([',', ' '], '', strtoupper(trim($charset)));
} else {
	$charset = 'ASCII, UTF-8, ISO-8859-1, JIS, EUC-JP, SJIS';
}

if (str_contains($charset, 'UTF-7')) {
	exit;
}

$blog_charset = (string) get_option('blog_charset');

if (function_exists('mb_convert_encoding')) {
	$title     = mb_convert_encoding($title, $blog_charset, $charset);
	$excerpt   = mb_convert_encoding($excerpt, $blog_charset, $charset);
	$blog_name = mb_convert_encoding($blog_name, $blog_charset, $charset);
}

$title     = wp_slash($title);
$excerpt   = wp_slash($excerpt);
$blog_name = wp_slash($blog_name);

if (is_single() || is_page()) {
	$tb_id = $posts[0]->ID ?? $tb_id;
}

if (!isset($tb_id) || $tb_id <= 0) {
	trackback_response(1, 'I really need an ID for this to work.');
}

if ($title === '' && $tb_url === '' && $blog_name === '') {
	wp_redirect(get_permalink($tb_id));
	exit;
}

if ($tb_url !== '' && $title !== '') {
	header('Content-Type: text/xml; charset=' . $blog_charset);

	if (!pings_open($tb_id)) {
		trackback_response(1, 'Sorry, trackbacks are closed for this item.');
	}

	$title   = wp_html_excerpt($title, 250, '&#8230;');
	$excerpt = wp_html_excerpt($excerpt, 252, '&#8230;');

	$comment_post_ID      = (int) $tb_id;
	$comment_author       = $blog_name;
	$comment_author_email = '';
	$comment_author_url   = $tb_url;
	$comment_content      = "<strong>{$title}</strong>\n\n{$excerpt}";
	$comment_type         = 'trackback';

	$dupe = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_author_url = %s",
			$comment_post_ID,
			$comment_author_url
		)
	);

	if ($dupe) {
		trackback_response(1, 'We already have a ping from that URL for this post.');
	}

	$commentdata = [
		'comment_post_ID'      => $comment_post_ID,
		'comment_author'       => $comment_author,
		'comment_author_email' => $comment_author_email,
		'comment_author_url'   => $comment_author_url,
		'comment_content'      => $comment_content,
		'comment_type'         => $comment_type,
	];

	wp_new_comment($commentdata);
	$trackback_id = (int) $wpdb->insert_id;

	/**
	 * Fires after a trackback is added to a post.
	 *
	 * @since 1.2.0
	 *
	 * @param int $trackback_id Trackback ID.
	 */
	do_action('trackback_post', $trackback_id);
	trackback_response(0);
}
?>