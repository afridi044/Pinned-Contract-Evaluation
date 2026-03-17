<?php
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
 * @return WP_Site|false|null
 */
function get_dashboard_blog() {
	_deprecated_function( __FUNCTION__, '3.1' );

	$blog = get_site_option( 'dashboard_blog' );
	if ( ! empty( $blog ) ) {
		return get_blog_details( $blog );
	}

	$current_site = $GLOBALS['current_site'] ?? null;
	$current_blog_id = is_object( $current_site ) && isset( $current_site->blog_id ) ? $current_site->blog_id : 0;

	return get_blog_details( $current_blog_id );
}

/**
 * @since MU
 * @deprecated 3.0.0 Use wp_generate_password()
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
 * @deprecated 3.0.0 Use is_super_admin()
 * @see is_super_admin()
 * @see is_multisite()
 *
 */
function is_site_admin( string $user_login = '' ): bool {
	_deprecated_function( __FUNCTION__, '3.0', 'is_super_admin()' );

	if ( $user_login === '' ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
	} else {
		$user = get_user_by( 'login', $user_login );
		if ( ! ( $user instanceof WP_User ) || ! $user->exists() ) {
			return false;
		}
		$user_id = $user->ID;
	}

	return is_super_admin( $user_id );
}

if ( ! function_exists( 'graceful_fail' ) ) {
	/**
	 * @since MU
	 * @deprecated 3.0.0 Use wp_die()
	 * @see wp_die()
	 */
	function graceful_fail( string $message ): never {
		_deprecated_function( __FUNCTION__, '3.0', 'wp_die()' );

		$message = apply_filters( 'graceful_fail', $message );
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

		die( sprintf( $message_template, $message ) );
	}
}

/**
 * @since MU
 * @deprecated 3.0.0 Use get_user_by()
 * @see get_user_by()
 */
function get_user_details( string $username ) {
	_deprecated_function( __FUNCTION__, '3.0', 'get_user_by()' );

	return get_user_by( 'login', $username );
}

/**
 * @since MU
 * @deprecated 3.0.0 Use clean_post_cache()
 * @see clean_post_cache()
 */
function clear_global_post_cache( int $post_id ): void { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	_deprecated_function( __FUNCTION__, '3.0', 'clean_post_cache()' );
}

/**
 * @since MU
 * @deprecated 3.0.0 Use is_main_site()
 * @see is_main_site()
 */
function is_main_blog(): bool {
	_deprecated_function( __FUNCTION__, '3.0', 'is_main_site()' );

	return is_main_site();
}

/**
 * @since MU
 * @deprecated 3.0.0 Use is_email()
 * @see is_email()
 */
function validate_email( string $email, bool $check_domain = true ): string|false {
	_deprecated_function( __FUNCTION__, '3.0', 'is_email()' );

	return is_email( $email, $check_domain );
}

/**
 * @since MU
 * @deprecated 3.0.0 No alternative available. For performance reasons this function is not recommended.
 */
function get_blog_list( int $start = 0, int|string $num = 10, string $deprecated = '' ): array { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	_deprecated_function( __FUNCTION__, '3.0', 'wp_get_sites()' );

	global $wpdb;

	$blogs = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT blog_id, domain, path FROM $wpdb->blogs WHERE site_id = %d AND public = '1' AND archived = '0' AND mature = '0' AND spam = '0' AND deleted = '0' ORDER BY registered DESC",
			$wpdb->siteid
		),
		ARRAY_A
	);

	$blog_list = [];

	foreach ( (array) $blogs as $details ) {
		if ( ! isset( $details['blog_id'] ) ) {
			continue;
		}

		$blog_id = (int) $details['blog_id'];
		$blog_list[ $blog_id ] = $details;
		$blog_list[ $blog_id ]['postcount'] = (int) $wpdb->get_var(
			"SELECT COUNT(ID) FROM " . $wpdb->get_blog_prefix( $blog_id ) . "posts WHERE post_status='publish' AND post_type='post'"
		);
	}

	$blogs = $blog_list;

	if ( ! is_array( $blogs ) || $blogs === [] ) {
		return [];
	}

	if ( $num === 'all' ) {
		return array_slice( $blogs, $start );
	}

	return array_slice( $blogs, $start, (int) $num );
}

/**
 * @since MU
 * @deprecated 3.0.0 No alternative available. For performance reasons this function is not recommended.
 */
function get_most_active_blogs( int|string $num = 10, bool $display = true ): array {
	_deprecated_function( __FUNCTION__, '3.0' );

	$blogs       = get_blog_list( 0, 'all', false );
	$most_active = [];
	$blog_list   = [];

	if ( is_array( $blogs ) ) {
		foreach ( $blogs as $details ) {
			if ( ! isset( $details['blog_id'] ) ) {
				continue;
			}

			$blog_id                    = (int) $details['blog_id'];
			$most_active[ $blog_id ]    = (int) ( $details['postcount'] ?? 0 );
			$blog_list[ $blog_id ]      = $details;
		}

		arsort( $most_active );

		$sorted = [];
		foreach ( array_keys( $most_active ) as $blog_id ) {
			$sorted[ $blog_id ] = $blog_list[ $blog_id ] ?? [];
		}

		$most_active = $sorted;
	}

	if ( $display && ! empty( $most_active ) ) {
		foreach ( $most_active as $details ) {
			$domain    = $details['domain'] ?? '';
			$path      = $details['path'] ?? '';
			$postcount = (int) ( $details['postcount'] ?? 0 );
			$url       = esc_url( 'http://' . $domain . $path );

			printf(
				'<li>%1$d <a href="%2$s">%2$s</a></li>',
				$postcount,
				$url
			);
		}
	}

	$length = is_numeric( $num ) ? (int) $num : 0;

	return array_slice( $most_active, 0, $length );
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
function wpmu_admin_do_redirect( string $url = '' ): void {
	_deprecated_function( __FUNCTION__, '3.3' );

	$ref = isset( $_GET['ref'] ) ? (string) $_GET['ref'] : '';

	if ( $ref === '' && isset( $_POST['ref'] ) ) {
		$ref = (string) $_POST['ref'];
	}

	if ( $ref !== '' ) {
		$ref = wpmu_admin_redirect_add_updated_param( $ref );
		wp_redirect( $ref );
		exit;
	}

	$http_referer = $_SERVER['HTTP_REFERER'] ?? '';

	if ( $http_referer !== '' ) {
		wp_redirect( $http_referer );
		exit;
	}

	$url = wpmu_admin_redirect_add_updated_param( $url );

	if ( isset( $_GET['redirect'] ) ) {
		$redirect = (string) $_GET['redirect'];

		if ( str_starts_with( $redirect, 's_' ) ) {
			$url .= '&action=blogs&s=' . esc_html( substr( $redirect, 2 ) );
		}
	} elseif ( isset( $_POST['redirect'] ) ) {
		$url = wpmu_admin_redirect_add_updated_param( (string) $_POST['redirect'] );
	}

	wp_redirect( $url );
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
function wpmu_admin_redirect_add_updated_param( string $url = '' ): string {
	_deprecated_function( __FUNCTION__, '3.3' );

	if ( str_contains( $url, 'updated=true' ) ) {
		return $url;
	}

	return str_contains( $url, '?' )
		? $url . '&updated=true'
		: $url . '?updated=true';
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
function get_user_id_from_string( string $string ): int {
	_deprecated_function( __FUNCTION__, '3.6', 'get_user_by()' );

	if ( is_email( $string ) ) {
		$user = get_user_by( 'email', $string );
	} elseif ( is_numeric( $string ) ) {
		return (int) $string;
	} else {
		$user = get_user_by( 'login', $string );
	}

	return ( $user instanceof WP_User ) ? (int) $user->ID : 0;
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

	if ( is_subdomain_install() ) {
		$url = 'http://' . $domain . $path;
	} else {
		$http_host = $_SERVER['HTTP_HOST'] ?? '';

		if ( $domain !== $http_host ) {
			$dot_position = strpos( $domain, '.' );

			if ( $dot_position === false ) {
				$url = 'http://' . $domain . $path;
			} else {
				$blogname   = substr( $domain, 0, $dot_position );
				$base_domain = substr( $domain, $dot_position + 1 );
				$url        = 'http://' . $base_domain . $path;

				if ( $blogname !== 'www.' && $blogname !== '' ) {
					$url .= $blogname . '/';
				}
			}
		} else {
			$url = 'http://' . $domain . $path;
		}
	}

	return esc_url_raw( $url );
}
?>