<?php
/**
 * WordPress user administration API.
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * Creates a new user from the "Users" form using $_POST information.
 *
 * @since 2.0.0
 *
 * @return WP_Error|int
 */
function add_user(): WP_Error|int {
	return edit_user();
}

/**
 * Edit user settings based on contents of $_POST
 *
 * Used on user-edit.php and profile.php to manage and process user options, passwords etc.
 *
 * @since 2.0.0
 *
 * @param int $user_id Optional. User ID.
 * @return WP_Error|int User id of the updated user.
 */
function edit_user( int $user_id = 0 ): WP_Error|int {
	global $wp_roles;

	$user            = new stdClass();
	$user->user_login = '';

	if ( $user_id !== 0 ) {
		$update         = true;
		$user->ID       = $user_id;
		$userdata       = get_userdata( $user_id );
		if ( $userdata instanceof WP_User ) {
			$user->user_login = wp_slash( $userdata->user_login );
		}
	} else {
		$update = false;
	}

	if ( ! $update && isset( $_POST['user_login'] ) ) {
		$user->user_login = sanitize_user( (string) wp_unslash( $_POST['user_login'] ), true );
	}

	$pass1 = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : '';
	$pass2 = isset( $_POST['pass2'] ) ? (string) wp_unslash( $_POST['pass2'] ) : '';

	if ( isset( $_POST['role'] ) && current_user_can( 'edit_users' ) ) {
		$new_role       = sanitize_text_field( (string) wp_unslash( $_POST['role'] ) );
		$potential_role = $wp_roles->role_objects[ $new_role ] ?? null;

		if (
			( is_multisite() && current_user_can( 'manage_sites' ) )
			|| $user_id !== get_current_user_id()
			|| ( $potential_role && $potential_role->has_cap( 'edit_users' ) )
		) {
			$user->role = $new_role;
		}

		$editable_roles = get_editable_roles();
		if ( $new_role !== '' && empty( $editable_roles[ $new_role ] ) ) {
			wp_die( __( 'You can&#8217;t give users that role.' ) );
		}
	}

	if ( isset( $_POST['email'] ) ) {
		$user->user_email = sanitize_text_field( (string) wp_unslash( $_POST['email'] ) );
	}

	if ( isset( $_POST['url'] ) ) {
		$url = trim( (string) wp_unslash( $_POST['url'] ) );
		if ( $url === '' || $url === 'http://' ) {
			$user->user_url = '';
		} else {
			$user->user_url = esc_url_raw( $url );
			$protocols      = implode(
				'|',
				array_map(
					static fn( string $protocol ): string => preg_quote( $protocol, '/' ),
					wp_allowed_protocols()
				)
			);
			$user->user_url = preg_match( '/^(' . $protocols . '):/is', $user->user_url ) ? $user->user_url : 'http://' . $user->user_url;
		}
	}

	if ( isset( $_POST['first_name'] ) ) {
		$user->first_name = sanitize_text_field( (string) wp_unslash( $_POST['first_name'] ) );
	}

	if ( isset( $_POST['last_name'] ) ) {
		$user->last_name = sanitize_text_field( (string) wp_unslash( $_POST['last_name'] ) );
	}

	if ( isset( $_POST['nickname'] ) ) {
		$user->nickname = sanitize_text_field( (string) wp_unslash( $_POST['nickname'] ) );
	}

	if ( isset( $_POST['display_name'] ) ) {
		$user->display_name = sanitize_text_field( (string) wp_unslash( $_POST['display_name'] ) );
	}

	if ( isset( $_POST['description'] ) ) {
		$user->description = trim( (string) wp_unslash( $_POST['description'] ) );
	}

	foreach ( wp_get_user_contact_methods( $user ) as $method => $name ) {
		if ( isset( $_POST[ $method ] ) ) {
			$user->{$method} = sanitize_text_field( (string) wp_unslash( $_POST[ $method ] ) );
		}
	}

	if ( $update ) {
		$user->rich_editing        = ( isset( $_POST['rich_editing'] ) && 'false' === (string) wp_unslash( $_POST['rich_editing'] ) ) ? 'false' : 'true';
		$user->admin_color         = isset( $_POST['admin_color'] )
			? sanitize_text_field( (string) wp_unslash( $_POST['admin_color'] ) )
			: 'fresh';
		$user->show_admin_bar_front = isset( $_POST['admin_bar_front'] ) ? 'true' : 'false';
	}

	$user->comment_shortcuts = ( isset( $_POST['comment_shortcuts'] ) && 'true' === (string) wp_unslash( $_POST['comment_shortcuts'] ) ) ? 'true' : '';
	$user->use_ssl           = empty( $_POST['use_ssl'] ) ? 0 : 1;

	$errors = new WP_Error();

	if ( $user->user_login === '' ) {
		$errors->add( 'user_login', __( '<strong>ERROR</strong>: Please enter a username.' ) );
	}

	/**
	 * Fires before the password and confirm password fields are checked for congruity.
	 *
	 * @since 1.5.1
	 *
	 * @param string $user_login The username.
	 * @param string &$pass1     The password, passed by reference.
	 * @param string &$pass2     The confirmed password, passed by reference.
	 */
	do_action_ref_array( 'check_passwords', [ $user->user_login, &$pass1, &$pass2 ] );

	if ( $update ) {
		if ( $pass1 === '' && $pass2 !== '' ) {
			$errors->add( 'pass', __( '<strong>ERROR</strong>: You entered your new password only once.' ), [ 'form-field' => 'pass1' ] );
		} elseif ( $pass1 !== '' && $pass2 === '' ) {
			$errors->add( 'pass', __( '<strong>ERROR</strong>: You entered your new password only once.' ), [ 'form-field' => 'pass2' ] );
		}
	} else {
		if ( $pass1 === '' ) {
			$errors->add( 'pass', __( '<strong>ERROR</strong>: Please enter your password.' ), [ 'form-field' => 'pass1' ] );
		} elseif ( $pass2 === '' ) {
			$errors->add( 'pass', __( '<strong>ERROR</strong>: Please enter your password twice.' ), [ 'form-field' => 'pass2' ] );
		}
	}

	if ( str_contains( $pass1, '\\' ) ) {
		$errors->add( 'pass', __( '<strong>ERROR</strong>: Passwords may not contain the character "\\".' ), [ 'form-field' => 'pass1' ] );
	}

	if ( $pass1 !== $pass2 ) {
		$errors->add( 'pass', __( '<strong>ERROR</strong>: Please enter the same password in the two password fields.' ), [ 'form-field' => 'pass1' ] );
	}

	if ( $pass1 !== '' ) {
		$user->user_pass = $pass1;
	}

	if ( ! $update && isset( $_POST['user_login'] ) && ! validate_username( (string) wp_unslash( $_POST['user_login'] ) ) ) {
		$errors->add( 'user_login', __( '<strong>ERROR</strong>: This username is invalid because it uses illegal characters. Please enter a valid username.' ) );
	}

	if ( ! $update && username_exists( $user->user_login ) ) {
		$errors->add( 'user_login', __( '<strong>ERROR</strong>: This username is already registered. Please choose another one.' ) );
	}

	if ( empty( $user->user_email ) ) {
		$errors->add( 'empty_email', __( '<strong>ERROR</strong>: Please enter an e-mail address.' ), [ 'form-field' => 'email' ] );
	} elseif ( ! is_email( $user->user_email ) ) {
		$errors->add( 'invalid_email', __( '<strong>ERROR</strong>: The email address isn&#8217;t correct.' ), [ 'form-field' => 'email' ] );
	} elseif ( ( $owner_id = email_exists( $user->user_email ) ) && ( ! $update || ( $owner_id !== ( $user->ID ?? 0 ) ) ) ) {
		$errors->add( 'email_exists', __( '<strong>ERROR</strong>: This email is already registered, please choose another one.' ), [ 'form-field' => 'email' ] );
	}

	/**
	 * Fires before user profile update errors are returned.
	 *
	 * @since 2.8.0
	 *
	 * @param WP_Error &$errors An array of user profile update errors, passed by reference.
	 * @param bool     $update  Whether this is a user update.
	 * @param stdClass &$user   WP_User object, passed by reference.
	 */
	do_action_ref_array( 'user_profile_update_errors', [ &$errors, $update, &$user ] );

	if ( $errors->get_error_codes() ) {
		return $errors;
	}

	if ( $update ) {
		$user_id = wp_update_user( $user );
	} else {
		$user_id           = wp_insert_user( $user );
		$password_to_email = isset( $_POST['send_password'] ) ? $pass1 : '';
		wp_new_user_notification( $user_id, $password_to_email );
	}

	return $user_id;
}

/**
 * Fetch a filtered list of user roles that the current user is
 * allowed to edit.
 *
 * @since 2.8.0
 *
 * @return array
 */
function get_editable_roles(): array {
	global $wp_roles;

	$all_roles = $wp_roles->roles;

	/**
	 * Filter the list of editable roles.
	 *
	 * @since 2.8.0
	 *
	 * @param array $all_roles List of roles.
	 */
	return apply_filters( 'editable_roles', $all_roles );
}

/**
 * Retrieve user data and filter it.
 *
 * @since 2.0.5
 *
 * @param int $user_id User ID.
 * @return WP_User|false WP_User object on success, false on failure.
 */
function get_user_to_edit( int $user_id ): WP_User|false {
	$user = get_userdata( $user_id );

	if ( $user instanceof WP_User ) {
		$user->filter = 'edit';
	}

	return $user;
}

/**
 * Retrieve the user's drafts.
 *
 * @since 2.0.0
 *
 * @param int $user_id User ID.
 * @return array
 */
function get_users_drafts( int $user_id ): array {
	global $wpdb;

	$query = $wpdb->prepare(
		"SELECT ID, post_title FROM $wpdb->posts WHERE post_type = %s AND post_status = %s AND post_author = %d ORDER BY post_modified DESC",
		'post',
		'draft',
		$user_id
	);

	/**
	 * Filter the user's drafts query string.
	 *
	 * @since 2.0.0
	 *
	 * @param string $query The user's drafts query string.
	 */
	$query = apply_filters( 'get_users_drafts', $query );

	return $wpdb->get_results( $query );
}

/**
 * Remove user and optionally reassign posts and links to another user.
 *
 * @since 2.0.0
 *
 * @param int              $id       User ID.
 * @param int|string|null  $reassign Optional. Reassign posts and links to new User ID.
 * @return bool True when finished.
 */
function wp_delete_user( int $id, int|string|null $reassign = null ): bool {
	global $wpdb;

	$user = new WP_User( $id );

	if ( ! $user->exists() ) {
		return false;
	}

	if ( 'novalue' === $reassign ) {
		$reassign = null;
	} elseif ( null !== $reassign ) {
		$reassign = (int) $reassign;
	}

	/**
	 * Fires immediately before a user is deleted from the database.
	 *
	 * @since 2.0.0
	 *
	 * @param int      $id       ID of the user to delete.
	 * @param int|null $reassign ID of the user to reassign posts and links to.
	 */
	do_action( 'delete_user', $id, $reassign );

	if ( null === $reassign ) {
		$post_types_to_delete = [];

		foreach ( get_post_types( [], 'objects' ) as $post_type ) {
			if ( $post_type->delete_with_user ) {
				$post_types_to_delete[] = $post_type->name;
			} elseif ( null === $post_type->delete_with_user && post_type_supports( $post_type->name, 'author' ) ) {
				$post_types_to_delete[] = $post_type->name;
			}
		}

		/**
		 * Filter the list of post types to delete with a user.
		 *
		 * @since 3.4.0
		 *
		 * @param array $post_types_to_delete Post types to delete.
		 * @param int   $id                   User ID.
		 */
		$post_types_to_delete = apply_filters( 'post_types_to_delete_with_user', $post_types_to_delete, $id );

		if ( ! empty( $post_types_to_delete ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $post_types_to_delete ), '%s' ) );
			$sql          = $wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE post_author = %d AND post_type IN ($placeholders)",
				$id,
				...$post_types_to_delete
			);
			$post_ids     = $wpdb->get_col( $sql );

			foreach ( $post_ids as $post_id ) {
				wp_delete_post( (int) $post_id );
			}
		}

		$link_ids = $wpdb->get_col( $wpdb->prepare( "SELECT link_id FROM $wpdb->links WHERE link_owner = %d", $id ) );

		foreach ( $link_ids as $link_id ) {
			wp_delete_link( (int) $link_id );
		}
	} else {
		$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_author = %d", $id ) );
		$wpdb->update(
			$wpdb->posts,
			[ 'post_author' => $reassign ],
			[ 'post_author' => $id ]
		);

		foreach ( $post_ids as $post_id ) {
			clean_post_cache( (int) $post_id );
		}

		$link_ids = $wpdb->get_col( $wpdb->prepare( "SELECT link_id FROM $wpdb->links WHERE link_owner = %d", $id ) );
		$wpdb->update(
			$wpdb->links,
			[ 'link_owner' => $reassign ],
			[ 'link_owner' => $id ]
		);

		foreach ( $link_ids as $link_id ) {
			clean_bookmark_cache( (int) $link_id );
		}
	}

	if ( is_multisite() ) {
		remove_user_from_blog( $id, get_current_blog_id() );
	} else {
		$meta = $wpdb->get_col( $wpdb->prepare( "SELECT umeta_id FROM $wpdb->usermeta WHERE user_id = %d", $id ) );

		foreach ( $meta as $mid ) {
			delete_metadata_by_mid( 'user', (int) $mid );
		}

		$wpdb->delete( $wpdb->users, [ 'ID' => $id ] );
	}

	clean_user_cache( $user );

	/**
	 * Fires immediately after a user is deleted from the database.
	 *
	 * @since 2.9.0
	 *
	 * @param int      $id       ID of the deleted user.
	 * @param int|null $reassign ID of the user to reassign posts and links to.
	 */
	do_action( 'deleted_user', $id, $reassign );

	return true;
}

/**
 * Remove all capabilities from user.
 *
 * @since 2.1.0
 *
 * @param int $id User ID.
 */
function wp_revoke_user( int $id ): void {
	$user = new WP_User( $id );

	if ( $user->exists() ) {
		$user->remove_all_caps();
	}
}

add_action( 'admin_init', 'default_password_nag_handler' );

/**
 * @since 2.8.0
 *
 * @param mixed $errors Optional error parameter.
 */
function default_password_nag_handler( mixed $errors = false ): void {
	$user_id = get_current_user_id();

	if ( ! $user_id || ! get_user_option( 'default_password_nag', $user_id ) ) {
		return;
	}

	$nag_setting = get_user_setting( 'default_password_nag' );
	$hide_param  = isset( $_GET['default_password_nag'] )
		? sanitize_text_field( (string) wp_unslash( $_GET['default_password_nag'] ) )
		: null;

	if ( 'hide' === $nag_setting || '0' === $hide_param ) {
		delete_user_setting( 'default_password_nag' );
		update_user_option( $user_id, 'default_password_nag', false, true );
	}
}

add_action( 'profile_update', 'default_password_nag_edit_user', 10, 2 );

/**
 * @since 2.8.0
 *
 * @param int     $user_ID The user ID.
 * @param WP_User $old_data The user's previous data object.
 */
function default_password_nag_edit_user( int $user_ID, WP_User $old_data ): void {
	if ( ! get_user_option( 'default_password_nag', $user_ID ) ) {
		return;
	}

	$new_data = get_userdata( $user_ID );

	if ( $new_data instanceof WP_User && $new_data->user_pass !== $old_data->user_pass ) {
		delete_user_setting( 'default_password_nag' );
		update_user_option( $user_ID, 'default_password_nag', false, true );
	}
}

add_action( 'admin_notices', 'default_password_nag' );

/**
 * @since 2.8.0
 */
function default_password_nag(): void {
	global $pagenow;

	if ( 'profile.php' === $pagenow || ! get_user_option( 'default_password_nag' ) ) {
		return;
	}

	$profile_url = esc_url( get_edit_profile_url() . '#password' );
	$dismiss_url = esc_url( add_query_arg( 'default_password_nag', '0' ) );

	echo '<div class="error default-password-nag">';
	echo '<p>';
	echo '<strong>' . esc_html__( 'Notice:' ) . '</strong> ';
	echo esc_html__( 'You&rsquo;re using the auto-generated password for your account. Would you like to change it to something easier to remember?' );
	echo '</p><p>';
	printf(
		'<a href="%s">%s</a> | ',
		$profile_url,
		esc_html__( 'Yes, take me to my profile page' )
	);
	printf(
		'<a href="%s" id="default-password-nag-no">%s</a>',
		$dismiss_url,
		esc_html__( 'No thanks, do not remind me again' )
	);
	echo '</p></div>';
}
?>