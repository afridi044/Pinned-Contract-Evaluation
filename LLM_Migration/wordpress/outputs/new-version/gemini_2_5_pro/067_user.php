<?php

declare(strict_types=1);

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
 * @return WP_Error|int The new user's ID on success, a WP_Error object on failure.
 */
function add_user(): WP_Error|int
{
	return edit_user();
}

/**
 * Edits a user's settings based on the contents of $_POST.
 *
 * Used on user-edit.php and profile.php to manage and process user options, passwords etc.
 *
 * @since 2.0.0
 *
 * @param int $user_id Optional. User ID. Default 0 for a new user.
 * @return int|WP_Error The updated user's ID on success, a WP_Error object on failure.
 */
function edit_user(int $user_id = 0): int|WP_Error
{
	global $wp_roles;

	$update = (bool) $user_id;
	$user = new stdClass();

	if ($update) {
		$user->ID = $user_id;
		$userdata = get_userdata($user_id);
		$user->user_login = wp_slash($userdata->user_login);
	} else {
		$user->user_login = sanitize_user($_POST['user_login'] ?? '', true);
	}

	$pass1 = $_POST['pass1'] ?? '';
	$pass2 = $_POST['pass2'] ?? '';

	if (isset($_POST['role']) && current_user_can('edit_users')) {
		$new_role = sanitize_text_field($_POST['role']);
		$potential_role = $wp_roles->role_objects[$new_role] ?? null;

		// Don't let anyone with 'edit_users' (admins) edit their own role to something without it.
		// Multisite super admins can freely edit their blog roles -- they possess all caps.
		$can_change_role = (is_multisite() && current_user_can('manage_sites'))
			|| $user_id !== get_current_user_id()
			|| ($potential_role && $potential_role->has_cap('edit_users'));

		if ($can_change_role) {
			$user->role = $new_role;
		}

		// If the new role isn't editable by the logged-in user, die with an error.
		$editable_roles = get_editable_roles();
		if (!empty($new_role) && !isset($editable_roles[$new_role])) {
			wp_die(__('You can&#8217;t give users that role.'));
		}
	}

	$user->user_email = sanitize_text_field($_POST['email'] ?? '');

	$url = $_POST['url'] ?? '';
	if (empty($url) || $url === 'http://') {
		$user->user_url = '';
	} else {
		$user->user_url = esc_url_raw($url);
		$protocols = implode('|', array_map('preg_quote', wp_allowed_protocols()));
		if (!preg_match('/^(' . $protocols . '):/is', $user->user_url)) {
			$user->user_url = 'http://' . $user->user_url;
		}
	}

	$user->first_name = sanitize_text_field($_POST['first_name'] ?? '');
	$user->last_name = sanitize_text_field($_POST['last_name'] ?? '');
	$user->nickname = sanitize_text_field($_POST['nickname'] ?? '');
	$user->display_name = sanitize_text_field($_POST['display_name'] ?? '');
	$user->description = trim($_POST['description'] ?? '');

	foreach (wp_get_user_contact_methods($user) as $method => $name) {
		if (isset($_POST[$method])) {
			$user->$method = sanitize_text_field($_POST[$method]);
		}
	}

	if ($update) {
		$user->rich_editing = ($_POST['rich_editing'] ?? 'true') === 'false' ? 'false' : 'true';
		$user->admin_color = sanitize_text_field($_POST['admin_color'] ?? 'fresh');
		$user->show_admin_bar_front = isset($_POST['admin_bar_front']) ? 'true' : 'false';
	}

	$user->comment_shortcuts = ($_POST['comment_shortcuts'] ?? '') === 'true' ? 'true' : '';
	$user->use_ssl = !empty($_POST['use_ssl']) ? 1 : 0;

	$errors = new WP_Error();

	/* checking that username has been typed */
	if ($user->user_login === '') {
		$errors->add('user_login', __('<strong>ERROR</strong>: Please enter a username.'));
	}

	/* checking the password has been typed twice */
	/**
	 * Fires before the password and confirm password fields are checked for congruity.
	 *
	 * @since 1.5.1
	 *
	 * @param string $user_login The username.
	 * @param string &$pass1     The password, passed by reference.
	 * @param string &$pass2     The confirmed password, passed by reference.
	 */
	do_action_ref_array('check_passwords', [$user->user_login, &$pass1, &$pass2]);

	if ($update) {
		if (empty($pass1) && !empty($pass2)) {
			$errors->add('pass', __('<strong>ERROR</strong>: You entered your new password only once.'), ['form-field' => 'pass1']);
		} elseif (!empty($pass1) && empty($pass2)) {
			$errors->add('pass', __('<strong>ERROR</strong>: You entered your new password only once.'), ['form-field' => 'pass2']);
		}
	} else {
		if (empty($pass1)) {
			$errors->add('pass', __('<strong>ERROR</strong>: Please enter your password.'), ['form-field' => 'pass1']);
		} elseif (empty($pass2)) {
			$errors->add('pass', __('<strong>ERROR</strong>: Please enter your password twice.'), ['form-field' => 'pass2']);
		}
	}

	/* Check for "\" in password */
	if (str_contains(wp_unslash($pass1), "\\")) {
		$errors->add('pass', __('<strong>ERROR</strong>: Passwords may not contain the character "\\".'), ['form-field' => 'pass1']);
	}

	/* checking the password has been typed twice the same */
	if ($pass1 !== $pass2) {
		$errors->add('pass', __('<strong>ERROR</strong>: Please enter the same password in the two password fields.'), ['form-field' => 'pass1']);
	}

	if (!empty($pass1)) {
		$user->user_pass = $pass1;
	}

	if (!$update && isset($_POST['user_login']) && !validate_username($_POST['user_login'])) {
		$errors->add('user_login', __('<strong>ERROR</strong>: This username is invalid because it uses illegal characters. Please enter a valid username.'));
	}

	if (!$update && username_exists($user->user_login)) {
		$errors->add('user_login', __('<strong>ERROR</strong>: This username is already registered. Please choose another one.'));
	}

	/* checking e-mail address */
	if (empty($user->user_email)) {
		$errors->add('empty_email', __('<strong>ERROR</strong>: Please enter an e-mail address.'), ['form-field' => 'email']);
	} elseif (!is_email($user->user_email)) {
		$errors->add('invalid_email', __('<strong>ERROR</strong>: The email address isn&#8217;t correct.'), ['form-field' => 'email']);
	} elseif (($owner_id = email_exists($user->user_email)) && (!$update || ($owner_id !== $user->ID))) {
		$errors->add('email_exists', __('<strong>ERROR</strong>: This email is already registered, please choose another one.'), ['form-field' => 'email']);
	}

	/**
	 * Fires before user profile update errors are returned.
	 *
	 * @since 2.8.0
	 *
	 * @param WP_Error &$errors An array of user profile update errors, passed by reference.
	 * @param bool     $update  Whether this is a user update.
	 * @param stdClass &$user   User object, passed by reference.
	 */
	do_action_ref_array('user_profile_update_errors', [&$errors, $update, &$user]);

	if ($errors->has_errors()) {
		return $errors;
	}

	if ($update) {
		$user_id = wp_update_user($user);
	} else {
		$user_id = wp_insert_user($user);
		$notification_pass = isset($_POST['send_password']) ? wp_unslash($pass1) : '';
		wp_new_user_notification($user_id, $notification_pass);
	}
	return $user_id;
}

/**
 * Fetches a filtered list of user roles that the current user is allowed to edit.
 *
 * Simple function who's main purpose is to allow filtering of the
 * list of roles in the $wp_roles object so that plugins can remove
 * inappropriate ones depending on the situation or user making edits.
 * Specifically because without filtering anyone with the edit_users
 * capability can edit others to be administrators, even if they are
 * only editors or authors. This filter allows admins to delegate
 * user management.
 *
 * @since 2.8.0
 *
 * @return array A list of editable roles.
 */
function get_editable_roles(): array
{
	global $wp_roles;

	$all_roles = $wp_roles->roles;

	/**
	 * Filter the list of editable roles.
	 *
	 * @since 2.8.0
	 *
	 * @param array $all_roles List of roles.
	 */
	return apply_filters('editable_roles', $all_roles);
}

/**
 * Retrieves user data and filters it for editing.
 *
 * @since 2.0.5
 *
 * @param int $user_id User ID.
 * @return WP_User|false WP_User object on success, false on failure.
 */
function get_user_to_edit(int $user_id): WP_User|false
{
	$user = get_userdata($user_id);

	if ($user) {
		$user->filter = 'edit';
	}

	return $user;
}

/**
 * Retrieves the user's drafts.
 *
 * @since 2.0.0
 *
 * @param int $user_id User ID.
 * @return array An array of draft posts.
 */
function get_users_drafts(int $user_id): array
{
	global $wpdb;
	$query = $wpdb->prepare("SELECT ID, post_title FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'draft' AND post_author = %d ORDER BY post_modified DESC", $user_id);

	/**
	 * Filter the user's drafts query string.
	 *
	 * @since 2.0.0
	 *
	 * @param string $query The user's drafts query string.
	 */
	$query = apply_filters('get_users_drafts', $query);
	return $wpdb->get_results($query) ?? [];
}

/**
 * Removes a user and optionally reassigns their posts and links to another user.
 *
 * If the $reassign parameter is not assigned to a User ID, then all posts will
 * be deleted of that user. The action 'delete_user' that is passed the User ID
 * being deleted will be run after the posts are either reassigned or deleted.
 * The user meta will also be deleted that are for that User ID.
 *
 * @since 2.0.0
 *
 * @param int   $id       User ID.
 * @param mixed $reassign Optional. Reassign posts and links to new User ID. Accepts 'novalue', null, or an integer.
 * @return bool True on success, false on failure.
 */
function wp_delete_user(int $id, mixed $reassign = null): bool
{
	global $wpdb;

	$user = new WP_User($id);

	if (!$user->exists()) {
		return false;
	}

	// Normalize $reassign to null or a user ID. 'novalue' was an older default.
	if ($reassign === 'novalue') {
		$reassign = null;
	} elseif ($reassign !== null) {
		$reassign = (int) $reassign;
	}

	/**
	 * Fires immediately before a user is deleted from the database.
	 *
	 * @since 2.0.0
	 *
	 * @param int      $id       ID of the user to delete.
	 * @param int|null $reassign ID of the user to reassign posts and links to. Default null, for no reassignment.
	 */
	do_action('delete_user', $id, $reassign);

	if ($reassign === null) {
		$post_types_to_delete = [];
		foreach (get_post_types([], 'objects') as $post_type) {
			if ($post_type->delete_with_user) {
				$post_types_to_delete[] = $post_type->name;
			} elseif ($post_type->delete_with_user === null && post_type_supports($post_type->name, 'author')) {
				$post_types_to_delete[] = $post_type->name;
			}
		}

		/**
		 * Filter the list of post types to delete with a user.
		 *
		 * @since 3.4.0
		 *
		 * @param string[] $post_types_to_delete Post types to delete.
		 * @param int      $id                   User ID.
		 */
		$post_types_to_delete = apply_filters('post_types_to_delete_with_user', $post_types_to_delete, $id);

		if (!empty($post_types_to_delete)) {
			$post_types_sql = "'" . implode("', '", array_map('esc_sql', $post_types_to_delete)) . "'";
			$post_ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_author = %d AND post_type IN ($post_types_sql)", $id));
			if ($post_ids) {
				foreach ($post_ids as $post_id) {
					wp_delete_post($post_id);
				}
			}
		}

		// Clean links
		$link_ids = $wpdb->get_col($wpdb->prepare("SELECT link_id FROM $wpdb->links WHERE link_owner = %d", $id));
		if ($link_ids) {
			foreach ($link_ids as $link_id) {
				wp_delete_link($link_id);
			}
		}
	} else {
		$post_ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_author = %d", $id));
		$wpdb->update($wpdb->posts, ['post_author' => $reassign], ['post_author' => $id]);
		if (!empty($post_ids)) {
			foreach ($post_ids as $post_id) {
				clean_post_cache($post_id);
			}
		}

		$link_ids = $wpdb->get_col($wpdb->prepare("SELECT link_id FROM $wpdb->links WHERE link_owner = %d", $id));
		$wpdb->update($wpdb->links, ['link_owner' => $reassign], ['link_owner' => $id]);
		if (!empty($link_ids)) {
			foreach ($link_ids as $link_id) {
				clean_bookmark_cache($link_id);
			}
		}
	}

	// FINALLY, delete user
	if (is_multisite()) {
		remove_user_from_blog($id, get_current_blog_id());
	} else {
		$meta_ids = $wpdb->get_col($wpdb->prepare("SELECT umeta_id FROM $wpdb->usermeta WHERE user_id = %d", $id));
		foreach ($meta_ids as $mid) {
			delete_metadata_by_mid('user', $mid);
		}
		$wpdb->delete($wpdb->users, ['ID' => $id]);
	}

	clean_user_cache($user);

	/**
	 * Fires immediately after a user is deleted from the database.
	 *
	 * @since 2.9.0
	 *
	 * @param int      $id       ID of the deleted user.
	 * @param int|null $reassign ID of the user to reassign posts and links to. Default null, for no reassignment.
	 */
	do_action('deleted_user', $id, $reassign);

	return true;
}

/**
 * Removes all capabilities from a user.
 *
 * @since 2.1.0
 *
 * @param int $id User ID.
 */
function wp_revoke_user(int $id): void
{
	$user = new WP_User($id);
	$user->remove_all_caps();
}

add_action('admin_init', 'default_password_nag_handler');
/**
 * Handles the default password nag logic.
 *
 * @since 2.8.0
 * @param mixed $errors Not used in this function.
 */
function default_password_nag_handler(mixed $errors = false): void
{
	global $user_ID;
	// Short-circuit it.
	if (!get_user_option('default_password_nag')) {
		return;
	}

	// get_user_setting = JS saved UI setting. else no-js-fallback code.
	$hide_nag_js = get_user_setting('default_password_nag') === 'hide';
	$hide_nag_get = ($_GET['default_password_nag'] ?? '') === '0';

	if ($hide_nag_js || $hide_nag_get) {
		delete_user_setting('default_password_nag');
		update_user_option($user_ID, 'default_password_nag', false, true);
	}
}

add_action('profile_update', 'default_password_nag_edit_user', 10, 2);
/**
 * Removes the default password nag when a user changes their password.
 *
 * @since 2.8.0
 * @param int    $user_ID  User ID.
 * @param object $old_data Old user data object.
 */
function default_password_nag_edit_user(int $user_ID, object $old_data): void
{
	// Short-circuit it.
	if (!get_user_option('default_password_nag', $user_ID)) {
		return;
	}

	$new_data = get_userdata($user_ID);

	// Remove the nag if the password has been changed.
	if ($new_data && $new_data->user_pass !== $old_data->user_pass) {
		delete_user_setting('default_password_nag');
		update_user_option($user_ID, 'default_password_nag', false, true);
	}
}

add_action('admin_notices', 'default_password_nag');
/**
 * Displays the default password nag notice.
 *
 * @since 2.8.0
 */
function default_password_nag(): void
{
	global $pagenow;
	// Short-circuit it.
	if ($pagenow === 'profile.php' || !get_user_option('default_password_nag')) {
		return;
	}

	echo '<div class="error default-password-nag">';
	echo '<p>';
	echo '<strong>' . __('Notice:') . '</strong> ';
	_e('You&rsquo;re using the auto-generated password for your account. Would you like to change it to something easier to remember?');
	echo '</p><p>';
	printf('<a href="%s">' . __('Yes, take me to my profile page') . '</a> | ', esc_url(get_edit_profile_url() . '#password'));
	printf('<a href="%s" id="default-password-nag-no">' . __('No thanks, do not remind me again') . '</a>', esc_url('?default_password_nag=0'));
	echo '</p></div>';
}