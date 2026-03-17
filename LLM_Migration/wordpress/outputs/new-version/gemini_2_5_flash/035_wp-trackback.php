<?php
/**
 * Handle Trackbacks and Pingbacks Sent to WordPress
 *
 * @since 0.71
 *
 * @package WordPress
 * @subpackage Trackbacks
 */

if (empty($wp)) {
	require_once( __DIR__ . '/wp-load.php' );
	wp( ['tb' => '1'] );
}

/**
 * Response to a trackback.
 *
 * Responds with an error or success XML message.
 *
 * @since 0.71
 *
 * @param int    $error         Whether there was an error.
 *                                Default '0'. Accepts '0' or '1'.
 * @param string $error_message Error message if an error occurred.
 */
function trackback_response(int $error = 0, string $error_message = ''): void {
	header('Content-Type: text/xml; charset=' . get_option('blog_charset') );
	if ($error) {
		echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
		echo "<response>\n";
		echo "<error>1</error>\n";
		echo "<message>" . htmlspecialchars($error_message, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</message>\n";
		echo "</response>";
		exit();
	} else {
		echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
		echo "<response>\n";
		echo "<error>0</error>\n";
		echo "</response>";
	}
}

// Trackback is done by a POST.
// $request_array = 'HTTP_POST_VARS'; // This variable was never used and is deprecated. Removed.

// Initialize $tb_id to null, as it might not be set until later in the script based on original logic.
$tb_id = null;

if ( empty($_GET['tb_id']) ) {
	$tb_id_segments = explode('/', $_SERVER['REQUEST_URI']);
	// Filter out empty segments to handle cases like // or trailing slashes more robustly.
	$tb_id_segments = array_filter($tb_id_segments, fn($segment) => $segment !== '');
	$tb_id = !empty($tb_id_segments) ? (int) $tb_id_segments[ array_key_last($tb_id_segments) ] : 0;
}
// Note: If $_GET['tb_id'] is set and truthy, $tb_id remains null here,
// replicating the original script's behavior where it would be set later by is_single()/is_page() or error out.

$tb_url    = $_POST['url']     ?? '';
$charset   = $_POST['charset'] ?? '';

// These three are stripslashed here so they can be properly escaped after mb_convert_encoding().
$title     = wp_unslash($_POST['title']     ?? '');
$excerpt   = wp_unslash($_POST['excerpt']   ?? '');
$blog_name = wp_unslash($_POST['blog_name'] ?? '');

if ($charset) {
	$charset = str_replace( [',', ' '], '', strtoupper( trim($charset) ) );
} else {
	$charset = 'ASCII, UTF-8, ISO-8859-1, JIS, EUC-JP, SJIS';
}

// No valid uses for UTF-7.
if ( false !== strpos($charset, 'UTF-7') ) {
	exit();
}

// For international trackbacks.
if ( function_exists('mb_convert_encoding') ) {
	$blog_charset = get_option('blog_charset');
	$title     = mb_convert_encoding($title, $blog_charset, $charset);
	$excerpt   = mb_convert_encoding($excerpt, $blog_charset, $charset);
	$blog_name = mb_convert_encoding($blog_name, $blog_charset, $charset);
}

// Now that mb_convert_encoding() has been given a swing, we need to escape these three.
$title     = wp_slash($title);
$excerpt   = wp_slash($excerpt);
$blog_name = wp_slash($blog_name);

if ( is_single() || is_page() ) {
	global $posts; // Ensure $posts is accessible in this scope.
	$tb_id = $posts[0]->ID;
}

if ( empty($tb_id) ) { // This covers !isset($tb_id) || !intval($tb_id)
	trackback_response(1, 'I really need an ID for this to work.');
}

if (empty($title) && empty($tb_url) && empty($blog_name)) {
	// If it doesn't look like a trackback at all.
	wp_redirect(get_permalink($tb_id));
	exit;
}

if ( !empty($tb_url) && !empty($title) ) {
	header('Content-Type: text/xml; charset=' . get_option('blog_charset') );

	if ( !pings_open($tb_id) ) {
		trackback_response(1, 'Sorry, trackbacks are closed for this item.');
	}

	$title =  wp_html_excerpt( $title, 250, '&#8230;' );
	$excerpt = wp_html_excerpt( $excerpt, 252, '&#8230;' );

	$comment_post_ID      = (int) $tb_id;
	$comment_author       = $blog_name;
	$comment_author_email = '';
	$comment_author_url   = $tb_url;
	$comment_content      = "<strong>$title</strong>\n\n$excerpt";
	$comment_type         = 'trackback';

	global $wpdb; // Ensure $wpdb is accessible in this scope.
	$dupe = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_author_url = %s", $comment_post_ID, $comment_author_url) );
	if ( $dupe ) {
		trackback_response(1, 'We already have a ping from that URL for this post.');
	}

	$commentdata = compact('comment_post_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_content', 'comment_type');

	wp_new_comment($commentdata);
	$trackback_id = $wpdb->insert_id;

	/**
	 * Fires after a trackback is added to a post.
	 *
	 * @since 1.2.0
	 *
	 * @param int $trackback_id Trackback ID.
	 */
	do_action( 'trackback_post', $trackback_id );
	trackback_response( 0 );
}