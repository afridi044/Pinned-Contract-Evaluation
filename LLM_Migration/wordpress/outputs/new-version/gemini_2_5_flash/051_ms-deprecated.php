<?php
declare(strict_types=1); // Enable strict type checking for better code quality

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
 * @return int
 */
function get_dashboard_blog(): int {
    _deprecated_function( __FUNCTION__, '3.1' );

    $blog_id_option = get_site_option( 'dashboard_blog' );

    // get_site_option can return mixed. We expect a numeric ID for get_blog_details.
    if ( is_numeric( $blog_id_option ) ) {
        // get_blog_details returns WP_Site|null. The docblock for this function specifies an int return.
        // We assume the intent is to return the ID property of the WP_Site object.
        $blog_details = get_blog_details( (int) $blog_id_option );
        if ( $blog_details instanceof WP_Site ) {
            return $blog_details->id;
        }
    }

    // Fallback to current_site->blog_id, which is an int.
    // This assumes $GLOBALS['current_site'] is always available and has a blog_id property.
    return $GLOBALS['current_site']->blog_id;
}

/**
 * @since MU
 * @deprecated 3.0.0
 * @deprecated Use wp_generate_password()
 * @see wp_generate_password()
 */
function generate_random_password( int $len = 8 ): string {
	_deprecated_function( __FUNCTION__, '3.0', 'wp_generate_password()' );
	return wp_generate_password( $len );
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
 * @deprecated 3.0.0
 * @deprecated Use is_super_admin()
 * @see is_super_admin()
 * @see is_multisite()
 *
 */
function is_site_admin( string $user_login = '' ): bool {
	_deprecated_function( __FUNCTION__, '3.0', 'is_super_admin()' );

	$user_id = 0; // Initialize to ensure it's always set.

	if ( empty( $user_login ) ) {
		$user_id = get_current_user_id();
		if ( $user_id === 0 ) { // get_current_user_id returns 0 if no user is logged in.
			return false;
		}
	} else {
		// get_user_by returns WP_User|false.
		$user = get_user_by( 'login', $user_login );
		if ( ! $user instanceof WP_User ) { // Check if a WP_User object was successfully returned.
			return false;
		}
		$user_id = $user->ID;
	}

	return is_super_admin( $user_id );
}

if ( !function_exists( 'graceful_fail' ) ) :
/**
 * @since MU
 * @deprecated 3.0.0
 * @deprecated Use wp_die()
 * @see wp_die()
 */
function graceful_fail( string $message ): void {
	_deprecated_function( __FUNCTION__, '3.0', 'wp_die()' );
	$message = apply_filters( 'graceful_fail', $message );
	$message_template = apply_filters( 'graceful_fail_template',
'<!DOCTYPE html>
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
</html>' );
	die( sprintf( $message_template, $message ) );
}
endif;

/**
 * @since MU
 * @deprecated 3.0.0
 * @deprecated Use get_user_by()
 * @see get_user_by()
 */
function get_user_details( string $username ): WP_User|false {
	_deprecated_function( __FUNCTION__, '3.0', 'get_user_by()' );
	return get_user_by('login', $username);
}

/**
 * @since MU
 * @deprecated 3.0.0
 * @deprecated Use clean_post_cache()
 * @see clean_post_cache()
 */
function clear_global_post_cache( int $post_id ): void {
	_deprecated_function( __FUNCTION__, '3.0', 'clean_post_cache()' );
	// This function intentionally does nothing in its original deprecated form.
	// We maintain functional equivalence by keeping it empty.
}

/**
 * @since MU
 * @deprecated 3.0.0
 * @deprecated Use is_main_site()
 * @see is_main_site()
 */
function is_main_blog(): bool {
	_deprecated_function( __FUNCTION__, '3.0', 'is_main_site()' );
	return is_main_site();
}

/**
 * @since MU
 * @deprecated 3.0.0
 * @deprecated Use is_email()
 * @see is_email()
 */
function validate_email( string $email, bool $check_domain = true ): string|false {
	_deprecated_function( __FUNCTION__, '3.0', 'is_email()' );
	return is_email( $email, $check_domain );
}

/**
 * @since MU
 * @deprecated 3.0.0
 * @deprecated No alternative available. For performance reasons this function is not recommended.
 */
function get_blog_list( int $start = 0, int|string $num = 10, string $deprecated = '' ): array {
	_deprecated_function( __FUNCTION__, '3.0', 'wp_get_sites()' );

	global $wpdb;
	// Use string interpolation for global variables in SQL queries.
	$blogs_raw = $wpdb->get_results( $wpdb->prepare(
		"SELECT blog_id, domain, path FROM {$wpdb->blogs} WHERE site_id = %d AND public = '1' AND archived = '0' AND mature = '0' AND spam = '0' AND deleted = '0' ORDER BY registered DESC",
		$wpdb->siteid
	), ARRAY_A );

	$blog_list = []; // Use short array syntax.
	foreach ( (array) $blogs_raw as $details ) {
		$blog_list[ $details['blog_id'] ] = $details;
		// Cast result of get_var to int as postcount is expected to be numeric.
		$blog_list[ $details['blog_id'] ]['postcount'] = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM " . $wpdb->get_blog_prefix( $details['blog_id'] ). "posts WHERE post_status='publish' AND post_type='post'" );
	}
	// The original code had `unset( $blogs ); $blogs = $blog_list;`.
	// This is functionally equivalent to just assigning `$blog_list` to `$blogs`.
	$blogs = $blog_list;

	if ( ! is_array( $blogs ) ) { // Simplified condition.
		return []; // Use short array syntax.
	}

	if ( $num === 'all' ) {
		return array_slice( $blogs, $start, count( $blogs ) );
	} else {
		return array_slice( $blogs, $start, $num );
	}
}

/**
 * @since MU
 * @deprecated 3.0.0
 * @deprecated No alternative available. For performance reasons this function is not recommended.
 */
function get_most_active_blogs( int $num = 10, bool $display = true ): array {
	_deprecated_function( __FUNCTION__, '3.0' );

	$blogs = get_blog_list( 0, 'all', false ); // $blog_id -> $details
	$most_active_post_counts = []; // Use short array syntax.
	$blog_details_map = []; // Renamed from $blog_list for better clarity.

	if ( is_array( $blogs ) ) {
		// `reset()` calls are generally not needed with modern `foreach` loops.
		foreach ( $blogs as $details ) {
			$most_active_post_counts[ $details['blog_id'] ] = $details['postcount'];
			$blog_details_map[ $details['blog_id'] ] = $details;
		}
		arsort( $most_active_post_counts );

		$sorted_blogs = []; // Use short array syntax.
		foreach ( $most_active_post_counts as $blog_id => $post_count ) {
			$sorted_blogs[ $blog_id ] = $blog_details_map[ $blog_id ];
		}
		$most_active = $sorted_blogs;
	} else {
		$most_active = []; // Ensure $most_active is an array even if $blogs is not.
	}

	if ( $display === true ) {
		if ( is_array( $most_active ) ) {
			foreach ( $most_active as $details ) {
				$url = esc_url('http://' . $details['domain'] . $details['path']);
				echo '<li>' . $details['postcount'] . " <a href='$url'>$url</a></li>";
			}
		}
	}
	return array_slice( $most_active, 0, $num );
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
 * @deprecated 3.3.0
 * @deprecated Use wp_redirect()
 * @uses wpmu_admin_redirect_add_updated_param()
 *
 * @param string $url
 */
function wpmu_admin_do_redirect( string $url = '' ): void {
	_deprecated_function( __FUNCTION__, '3.3' );

	// Use null coalescing operator for cleaner retrieval of 'ref' parameter.
	$ref = $_GET['ref'] ?? $_POST['ref'] ?? '';

	if ( $ref ) {
		$ref = wpmu_admin_redirect_add_updated_param( $ref );
		wp_redirect( $ref );
		exit();
	}

	if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) { // Simplified condition.
		wp_redirect( $_SERVER['HTTP_REFERER'] );
		exit();
	}

	$url = wpmu_admin_redirect_add_updated_param( $url );

	// Handle 'redirect' parameter.
	if ( isset( $_GET['redirect'] ) ) {
		$redirect_get = $_GET['redirect'];
		// Use str_starts_with for more readable string comparison.
		if ( str_starts_with( $redirect_get, 's_' ) ) {
			$url .= '&action=blogs&s=' . esc_html( substr( $redirect_get, 2 ) );
		}
	} elseif ( isset( $_POST['redirect'] ) ) {
		$url = wpmu_admin_redirect_add_updated_param( $_POST['redirect'] );
	}

	wp_redirect( $url );
	exit();
}

/**
 * Adds an 'updated=true' argument to a URL.
 *
 * @since MU
 * @deprecated 3.3.0
 * @deprecated Use add_query_arg()
 *
 * @param string $url
 * @return string
 */
function wpmu_admin_redirect_add_updated_param( string $url = '' ): string {
	_deprecated_function( __FUNCTION__, '3.3' );

	// Use str_contains for more readable substring checks.
	if ( ! str_contains( $url, 'updated=true' ) ) {
		if ( ! str_contains( $url, '?' ) ) {
			return $url . '?updated=true';
		} else {
			return $url . '&updated=true';
		}
	}
	return $url;
}

/**
 * Get a numeric user ID from either an email address or a login.
 *
 * A numeric string is considered to be an existing user ID
 * and is simply returned as such.
 *
 * @since MU
 * @deprecated 3.6.0
 * @deprecated Use get_user_by()
 * @uses get_user_by()
 *
 * @param string $string Either an email address or a login.
 * @return int
 */
function get_user_id_from_string( string $string ): int {
	_deprecated_function( __FUNCTION__, '3.6', 'get_user_by()' );

	$user = null; // Initialize $user to null.

	if ( is_email( $string ) ) {
		$user = get_user_by( 'email', $string );
	} elseif ( is_numeric( $string ) ) {
		return (int) $string; // Ensure explicit integer return type.
	} else {
		$user = get_user_by( 'login', $string );
	}

	// get_user_by returns WP_User|false. Use instanceof for type-safe check.
	return $user instanceof WP_User ? $user->ID : 0;
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
function get_blogaddress_by_domain( string $domain, string $path ): string {
	_deprecated_function( __FUNCTION__, '3.7' );

	$url = '';
	if ( is_subdomain_install() ) {
		$url = "http://" . $domain . $path;
	} else {
		// Use null coalescing for $_SERVER['HTTP_HOST'] for robustness.
		$http_host = $_SERVER['HTTP_HOST'] ?? '';
		if ( $domain !== $http_host ) {
			// In PHP 8.3, `strpos` returning `false` will be implicitly converted to `0`
			// when used in an integer context (like `substr` offset/length).
			// This maintains functional equivalence with the original code's behavior.
			$blogname = substr( $domain, 0, strpos( $domain, '.' ) );
			$url = 'http://' . substr( $domain, strpos( $domain, '.' ) + 1 ) . $path;
			// we're not installing the main blog
			// Use str_starts_with for more readable string comparison.
			if ( ! str_starts_with( $blogname, 'www.' ) ) {
				$url .= $blogname . '/';
			}
		} else { // main blog
			$url = 'http://' . $domain . $path;
		}
	}
	return esc_url_raw( $url );
}