<?php
declare(strict_types=1);

/**
 * Accepts file uploads from swfupload or other asynchronous upload methods.
 *
 * @package WordPress
 * @subpackage Administration
 */

$action = $_REQUEST['action'] ?? null;

if ($action === 'upload-attachment') {
	define('DOING_AJAX', true);
}

if (! defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}

if (defined('ABSPATH')) {
	require_once ABSPATH . 'wp-load.php';
} else {
	require_once dirname(__DIR__) . '/wp-load.php';
}

if ($action !== 'upload-attachment') {
	$authCookie       = $_REQUEST['auth_cookie'] ?? '';
	$loggedInCookie   = $_REQUEST['logged_in_cookie'] ?? '';
	$isSecureAuthSet  = ! empty($_COOKIE[SECURE_AUTH_COOKIE]);
	$isAuthSet        = ! empty($_COOKIE[AUTH_COOKIE]);
	$isLoggedInSet    = ! empty($_COOKIE[LOGGED_IN_COOKIE]);

	if (is_ssl() && ! $isSecureAuthSet && $authCookie !== '') {
		$_COOKIE[SECURE_AUTH_COOKIE] = $authCookie;
	} elseif (! $isAuthSet && $authCookie !== '') {
		$_COOKIE[AUTH_COOKIE] = $authCookie;
	}

	if (! $isLoggedInSet && $loggedInCookie !== '') {
		$_COOKIE[LOGGED_IN_COOKIE] = $loggedInCookie;
	}

	global $current_user;
	unset($current_user);
}

require_once ABSPATH . 'wp-admin/admin.php';

if (! current_user_can('upload_files')) {
	wp_die(__('You do not have permission to upload files.'));
}

header('Content-Type: text/html; charset=' . get_option('blog_charset'));

if ($action === 'upload-attachment') {
	require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

	send_nosniff_header();
	nocache_headers();

	wp_ajax_upload_attachment();
	exit('0');
}

$fetchRequest = $_REQUEST['fetch'] ?? null;
$attachmentId = isset($_REQUEST['attachment_id']) ? (int) $_REQUEST['attachment_id'] : 0;

if ($attachmentId > 0 && ! empty($fetchRequest)) {
	$post = get_post($attachmentId);

	if (! $post || $post->post_type !== 'attachment') {
		wp_die(__('Unknown post type.'));
	}

	if (! current_user_can('edit_post', $attachmentId)) {
		wp_die(__('You are not allowed to edit this item.'));
	}

	switch ((int) $fetchRequest) {
		case 3:
			$thumbUrl = wp_get_attachment_image_src($attachmentId, 'thumbnail', true);

			if ($thumbUrl) {
				printf(
					'<img class="pinkynail" src="%s" alt="" />',
					esc_url($thumbUrl[0])
				);
			}

			printf(
				'<a class="edit-attachment" href="%s" target="_blank">%s</a>',
				esc_url(get_edit_post_link($attachmentId)),
				_x('Edit', 'media item')
			);

			$title = $post->post_title !== '' ? $post->post_title : wp_basename((string) $post->guid);

			printf(
				'<div class="filename new"><span class="title">%s</span></div>',
				esc_html(wp_html_excerpt($title, 60, '&hellip;'))
			);
			break;

		case 2:
			add_filter('attachment_fields_to_edit', 'media_single_attachment_fields_to_edit', 10, 2);
			echo get_media_item($attachmentId, ['send' => false, 'delete' => true]);
			break;

		default:
			add_filter('attachment_fields_to_edit', 'media_post_single_attachment_fields_to_edit', 10, 2);
			echo get_media_item($attachmentId);
			break;
	}

	exit;
}

check_admin_referer('media-form');

$postId = 0;
if (isset($_REQUEST['post_id'])) {
	$postId = absint($_REQUEST['post_id']);
	if (! get_post($postId) || ! current_user_can('edit_post', $postId)) {
		$postId = 0;
	}
}

$id = media_handle_upload('async-upload', $postId);

if (is_wp_error($id)) {
	printf(
		'<div class="error-div error">
			<a class="dismiss" href="#" onclick="jQuery(this).parents(\'div.media-item\').slideUp(200, function(){jQuery(this).remove();});">%s</a>
			<strong>%s</strong><br />%s
		</div>',
		__('Dismiss'),
		sprintf(
			__('&#8220;%s&#8221; has failed to upload due to an error'),
			esc_html($_FILES['async-upload']['name'] ?? '')
		),
		esc_html($id->get_error_message())
	);
	exit;
}

if (! empty($_REQUEST['short'])) {
	echo (string) $id;
	exit;
}

$type = sanitize_key($_REQUEST['type'] ?? '');

echo apply_filters("async_upload_{$type}", $id);
?>