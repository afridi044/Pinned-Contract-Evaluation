<?php

declare(strict_types=1);

/**
 * Accepts file uploads from swfupload or other asynchronous upload methods.
 *
 * @package WordPress
 * @subpackage Administration
 */

if (($_REQUEST['action'] ?? '') === 'upload-attachment') {
    define('DOING_AJAX', true);
}

if (!defined('WP_ADMIN')) {
    define('WP_ADMIN', true);
}

// Define the path to wp-load.php and load it.
$wp_load_path = defined('ABSPATH')
    ? ABSPATH . 'wp-load.php'
    : dirname(__DIR__, 2) . '/wp-load.php';

require_once $wp_load_path;

// For non-AJAX requests, handle auth cookies passed in the request body.
if (($_REQUEST['action'] ?? '') !== 'upload-attachment') {
    // Flash often fails to send cookies, so we pass them in the request.
    $auth_cookie = $_REQUEST['auth_cookie'] ?? null;
    if ($auth_cookie) {
        $cookie_name = is_ssl() ? SECURE_AUTH_COOKIE : AUTH_COOKIE;
        if (empty($_COOKIE[$cookie_name])) {
            $_COOKIE[$cookie_name] = $auth_cookie;
        }
    }

    $logged_in_cookie = $_REQUEST['logged_in_cookie'] ?? null;
    if ($logged_in_cookie && empty($_COOKIE[LOGGED_IN_COOKIE])) {
        $_COOKIE[LOGGED_IN_COOKIE] = $logged_in_cookie;
    }
    unset($current_user);
}

require_once ABSPATH . 'wp-admin/admin.php';

if (!current_user_can('upload_files')) {
    wp_die(__('You do not have permission to upload files.'));
}

header('Content-Type: text/html; charset=' . get_option('blog_charset'));

// Handle the AJAX upload action.
if (($_REQUEST['action'] ?? '') === 'upload-attachment') {
    include ABSPATH . 'wp-admin/includes/ajax-actions.php';

    send_nosniff_header();
    nocache_headers();

    wp_ajax_upload_attachment();
    // die() is necessary to prevent the rest of the script from running.
    die('0');
}

// Handle fetching attachment details.
$attachment_id = isset($_REQUEST['attachment_id']) ? (int) $_REQUEST['attachment_id'] : 0;
$fetch_action = $_REQUEST['fetch'] ?? null;

if ($attachment_id > 0 && $fetch_action) {
    $post = get_post($attachment_id);

    if (!$post || 'attachment' !== $post->post_type) {
        wp_die(__('Unknown post type.'));
    }

    if (!current_user_can('edit_post', $attachment_id)) {
        wp_die(__('You are not allowed to edit this item.'));
    }

    switch ((string) $fetch_action) {
        case '3':
            if ($thumb_url_data = wp_get_attachment_image_src($attachment_id, 'thumbnail', true)) {
                $thumb_url = esc_url($thumb_url_data[0]);
                echo "<img class=\"pinkynail\" src=\"{$thumb_url}\" alt=\"\" />";
            }

            $edit_link = esc_url(get_edit_post_link($attachment_id));
            $edit_text = _x('Edit', 'media item');
            $title = $post->post_title ?: wp_basename($post->guid);
            $title_safe = esc_html(wp_html_excerpt($title, 60, '&hellip;'));

            echo <<<HTML
            <a class="edit-attachment" href="{$edit_link}" target="_blank">{$edit_text}</a>
            <div class="filename new"><span class="title">{$title_safe}</span></div>
            HTML;
            break;

        case '2':
            add_filter('attachment_fields_to_edit', 'media_single_attachment_fields_to_edit', 10, 2);
            echo get_media_item($attachment_id, ['send' => false, 'delete' => true]);
            break;

        default:
            add_filter('attachment_fields_to_edit', 'media_post_single_attachment_fields_to_edit', 10, 2);
            echo get_media_item($attachment_id);
            break;
    }
    exit;
}

check_admin_referer('media-form');

$post_id = isset($_REQUEST['post_id']) ? absint($_REQUEST['post_id']) : 0;
if ($post_id > 0 && (!get_post($post_id) || !current_user_can('edit_post', $post_id))) {
    $post_id = 0;
}

$id = media_handle_upload('async-upload', $post_id);

if (is_wp_error($id)) {
    $file_name = esc_html($_FILES['async-upload']['name'] ?? 'The file');
    $error_message = esc_html($id->get_error_message());
    $dismiss_text = __('Dismiss');
    $error_intro = sprintf(__('&#8220;%s&#8221; has failed to upload due to an error'), $file_name);

    echo <<<HTML
    <div class="error-div error">
        <a class="dismiss" href="#" onclick="jQuery(this).parents('div.media-item').slideUp(200, function(){jQuery(this).remove();});">{$dismiss_text}</a>
        <strong>{$error_intro}</strong><br />
        {$error_message}
    </div>
    HTML;
    exit;
}

// Final response.
if (!empty($_REQUEST['short'])) {
    // Short response: attachment ID only.
    echo $id;
} else {
    // Long response: HTML chunk.
    $type = $_REQUEST['type'] ?? 'file';

    /**
     * Filter the returned ID of an uploaded attachment.
     *
     * The dynamic portion of the hook name, $type, refers to the attachment type,
     * such as 'image', 'audio', 'video', 'file', etc.
     *
     * @since 2.5.0
     *
     * @param int $id Uploaded attachment ID.
     */
    echo apply_filters("async_upload_{$type}", $id);
}