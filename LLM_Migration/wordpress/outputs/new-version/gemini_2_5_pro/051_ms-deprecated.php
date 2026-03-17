<?php

declare(strict_types=1);

/**
 * Deprecated functions from WordPress MU and the multisite feature. You shouldn't
 * use these functions and look for the alternatives instead. The functions will be
 * removed in a later version.
 *
 * @package WordPress
 * @subpackage Deprecated
 * @since 3.0.0
 */

/*
 * Deprecated functions come here to die.
 */

/**
 * Get the "dashboard blog", the blog where users without a blog edit their profile data.
 * Dashboard blog functionality was removed in WordPress 3.1, replaced by the user admin.
 *
 * @since MU
 * @deprecated 3.1.0
 * @see get_blog_details()
 * @return object|null A blog details object or null if not found.
 */
function get_dashboard_blog(): ?object
{
    _deprecated_function(__FUNCTION__, '3.1');
    if ($blog = get_site_option('dashboard_blog')) {
        return get_blog_details($blog);
    }

    return get_blog_details($GLOBALS['current_site']->blog_id);
}

/**
 * @since MU
 * @deprecated 3.0.0 Use wp_generate_password()
 * @see wp_generate_password()
 */
function generate_random_password(int $len = 8): string
{
    _deprecated_function(__FUNCTION__, '3.0', 'wp_generate_password()');
    return wp_generate_password($len);
}

/**
 * Determine if user is a site admin.
 *
 * Plugins should use is_multisite() instead of checking if this function exists
 * to determine if multisite is enabled.
 *
 * This function must reside in a file included only if is_multisite() due to
 * legacy function_exists() checks to determine if multisite is enabled.
 *
 * @since MU
 * @deprecated 3.0.0 Use is_super_admin()
 * @see is_super_admin()
 * @see is_multisite()
 */
function is_site_admin(string $user_login = ''): bool
{
    _deprecated_function(__FUNCTION__, '3.0', 'is_super_admin()');

    if (empty($user_login)) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }
    } else {
        $user = get_user_by('login', $user_login);
        if (!$user) {
            return false;
        }
        $user_id = $user->ID;
    }

    return is_super_admin($user_id);
}

if (!function_exists('graceful_fail')) :
    /**
     * @since MU
     * @deprecated 3.0.0 Use wp_die()
     * @see wp_die()
     */
    function graceful_fail(string $message): never
    {
        _deprecated_function(__FUNCTION__, '3.0', 'wp_die()');
        $message = apply_filters('graceful_fail', $message);
        $message_template = apply_filters(
            'graceful_fail_template',
            <<<'HTML'
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml"><head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Error!</title>
<style type="text/css">
img {
	border: 0;
}
body {
line-height: 1.6em; font-family: Georgia, serif; width: 390px; margin: auto;
text-align: center;
}
.message {
	font-size: 22px;
	width: 350px;
	margin: auto;
}
</style>
</head>
<body>
<p class="message">%s</p>
</body>
</html>
HTML
        );
        die(sprintf($message_template, $message));
    }
endif;

/**
 * @since MU
 * @deprecated 3.0.0 Use get_user_by()
 * @see get_user_by()
 */
function get_user_details(string $username): object|false
{
    _deprecated_function(__FUNCTION__, '3.0', 'get_user_by()');
    return get_user_by('login', $username);
}

/**
 * @since MU
 * @deprecated 3.0.0 Use clean_post_cache()
 * @see clean_post_cache()
 */
function clear_global_post_cache(int $post_id): void
{
    _deprecated_function(__FUNCTION__, '3.0', 'clean_post_cache()');
}

/**
 * @since MU
 * @deprecated 3.0.0 Use is_main_site()
 * @see is_main_site()
 */
function is_main_blog(): bool
{
    _deprecated_function(__FUNCTION__, '3.0', 'is_main_site()');
    return is_main_site();
}

/**
 * @since MU
 * @deprecated 3.0.0 Use is_email()
 * @see is_email()
 */
function validate_email(string $email, bool $check_domain = true): string|false
{
    _deprecated_function(__FUNCTION__, '3.0', 'is_email()');
    return is_email($email, $check_domain);
}

/**
 * @since MU
 * @deprecated 3.0.0 No alternative available. For performance reasons this function is not recommended.
 */
function get_blog_list(int $start = 0, int|string $num = 10, string $deprecated = ''): array
{
    _deprecated_function(__FUNCTION__, '3.0', 'wp_get_sites()');

    global $wpdb;
    $blogs_data = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT blog_id, domain, path FROM $wpdb->blogs WHERE site_id = %d AND public = '1' AND archived = '0' AND mature = '0' AND spam = '0' AND deleted = '0' ORDER BY registered DESC",
            $wpdb->siteid
        ),
        ARRAY_A
    );

    if (!is_array($blogs_data)) {
        return [];
    }

    $blogs = [];
    foreach ($blogs_data as $details) {
        $details['postcount'] = (int) $wpdb->get_var("SELECT COUNT(ID) FROM " . $wpdb->get_blog_prefix($details['blog_id']) . "posts WHERE post_status='publish' AND post_type='post'");
        $blogs[$details['blog_id']] = $details;
    }

    $length = ('all' === $num) ? null : (int) $num;

    return array_slice($blogs, $start, $length);
}

/**
 * @since MU
 * @deprecated 3.0.0 No alternative available. For performance reasons this function is not recommended.
 */
function get_most_active_blogs(int $num = 10, bool $display = true): array
{
    _deprecated_function(__FUNCTION__, '3.0');

    // Note: get_blog_list() re-indexes the array, so we lose the blog_id keys.
    // The original code re-creates a blog_id keyed array. This is inefficient but preserved.
    $blogs = get_blog_list(0, 'all');
    if (empty($blogs)) {
        return [];
    }

    $blog_list = [];
    $post_counts = [];
    foreach ($blogs as $details) {
        // Re-key the array by blog_id, as get_blog_list() loses keys.
        $blog_list[$details['blog_id']] = $details;
        $post_counts[$details['blog_id']] = $details['postcount'];
    }

    arsort($post_counts);

    $sorted_blogs = [];
    foreach ($post_counts as $blog_id => $count) {
        $sorted_blogs[$blog_id] = $blog_list[$blog_id];
    }

    if ($display) {
        foreach ($sorted_blogs as $details) {
            $url = esc_url("http://{$details['domain']}{$details['path']}");
            echo "<li>{$details['postcount']} <a href='{$url}'>{$url}</a></li>";
        }
    }

    return array_slice($sorted_blogs, 0, $num);
}

/**
 * Redirect a user based on $_GET or $_POST arguments.
 *
 * The function looks for redirect arguments in the following order:
 * 1) $_GET['ref']
 * 2) $_POST['ref']
 * 3) $_SERVER['HTTP_REFERER']
 * 4) $_GET['redirect']
 * 5) $_POST['redirect']
 * 6) $url
 *
 * @since MU
 * @deprecated 3.3.0 Use wp_redirect()
 * @uses wpmu_admin_redirect_add_updated_param()
 *
 * @param string $url
 */
function wpmu_admin_do_redirect(string $url = ''): never
{
    _deprecated_function(__FUNCTION__, '3.3');

    $ref = $_GET['ref'] ?? $_POST['ref'] ?? null;
    if ($ref) {
        wp_redirect(wpmu_admin_redirect_add_updated_param($ref));
        exit;
    }

    if (!empty($_SERVER['HTTP_REFERER'])) {
        wp_redirect($_SERVER['HTTP_REFERER']);
        exit;
    }

    $final_url = wpmu_admin_redirect_add_updated_param($url);

    if (isset($_GET['redirect'])) {
        if (str_starts_with($_GET['redirect'], 's_')) {
            $final_url .= '&action=blogs&s=' . esc_html(substr($_GET['redirect'], 2));
        }
    } elseif (isset($_POST['redirect'])) {
        $final_url = wpmu_admin_redirect_add_updated_param($_POST['redirect']);
    }

    wp_redirect($final_url);
    exit;
}

/**
 * Adds an 'updated=true' argument to a URL.
 *
 * @since MU
 * @deprecated 3.3.0 Use add_query_arg()
 *
 * @param string $url
 * @return string
 */
function wpmu_admin_redirect_add_updated_param(string $url = ''): string
{
    _deprecated_function(__FUNCTION__, '3.3');

    if (str_contains($url, 'updated=true')) {
        return $url;
    }

    $separator = str_contains($url, '?') ? '&' : '?';
    return $url . $separator . 'updated=true';
}

/**
 * Get a numeric user ID from either an email address or a login.
 *
 * A numeric string is considered to be an existing user ID
 * and is simply returned as such.
 *
 * @since MU
 * @deprecated 3.6.0 Use get_user_by()
 * @uses get_user_by()
 *
 * @param string $string Either an email address or a login.
 * @return int
 */
function get_user_id_from_string(string $string): int
{
    _deprecated_function(__FUNCTION__, '3.6', 'get_user_by()');

    if (is_numeric($string)) {
        return (int) $string;
    }

    $field = is_email($string) ? 'email' : 'login';
    $user = get_user_by($field, $string);

    return $user?->ID ?? 0;
}

/**
 * Get a full blog URL, given a domain and a path.
 *
 * @since MU
 * @deprecated 3.7.0
 *
 * @param string $domain
 * @param string $path
 * @return string
 */
function get_blogaddress_by_domain(string $domain, string $path): string
{
    _deprecated_function(__FUNCTION__, '3.7');

    if (is_subdomain_install()) {
        $url = "http://{$domain}{$path}";
    } else {
        if ($domain !== ($_SERVER['HTTP_HOST'] ?? '')) {
            $blogname = substr($domain, 0, strpos($domain, '.'));
            $url = 'http://' . substr($domain, strpos($domain, '.') + 1) . $path;
            // we're not installing the main blog
            if ('www.' !== $blogname) {
                $url .= $blogname . '/';
            }
        } else { // main blog
            $url = "http://{$domain}{$path}";
        }
    }
    return esc_url_raw($url);
}