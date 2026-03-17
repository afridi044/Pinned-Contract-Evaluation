<?php

declare(strict_types=1);

/**
 * Add Site Administration Screen
 *
 * @package WordPress
 * @subpackage Multisite
 * @since 3.1.0
 */

/** Load WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if (!is_multisite()) {
	wp_die(__('Multisite support is not enabled.'));
}

if (!current_user_can('manage_sites')) {
	wp_die(__('You do not have sufficient permissions to add sites to this network.'));
}

$overview_content = sprintf(
	'<p>%s</p><p>%s</p>',
	__('This screen is for Super Admins to add new sites to the network. This is not affected by the registration settings.'),
	__('If the admin email for the new site does not exist in the database, a new user will also be created.')
);

get_current_screen()->add_help_tab([
	'id'      => 'overview',
	'title'   => __('Overview'),
	'content' => $overview_content,
]);

$sidebar_content = sprintf(
	'<p><strong>%s</strong></p><p><a href="%s" target="_blank">%s</a></p><p><a href="%s" target="_blank">%s</a></p>',
	__('For more information:'),
	'http://codex.wordpress.org/Network_Admin_Sites_Screen',
	__('Documentation on Site Management'),
	'https://wordpress.org/support/forum/multisite/',
	__('Support Forums')
);

get_current_screen()->set_help_sidebar($sidebar_content);

if (($_REQUEST['action'] ?? '') === 'add-site') {
	check_admin_referer('add-blog', '_wpnonce_add-blog');

	$blog_data = $_POST['blog'] ?? [];
	if (empty($blog_data) || !is_array($blog_data)) {
		wp_die(__('Can&#8217;t create an empty site.'));
	}

	$domain = preg_match('/^([a-zA-Z0-9-])+$/', $blog_data['domain'] ?? '')
		? strtolower($blog_data['domain'])
		: '';

	// If not a subdomain install, make sure the domain isn't a reserved word.
	if (!is_subdomain_install()) {
		/** This filter is documented in wp-includes/ms-functions.php */
		$subdirectory_reserved_names = apply_filters('subdirectory_reserved_names', ['page', 'comments', 'blog', 'files', 'feed']);
		if (in_array($domain, $subdirectory_reserved_names, true)) {
			wp_die(
				sprintf(
					__('The following words are reserved for use by WordPress functions and cannot be used as blog names: <code>%s</code>'),
					implode('</code>, <code>', $subdirectory_reserved_names)
				)
			);
		}
	}

	$email = sanitize_email($blog_data['email'] ?? '');
	$title = trim($blog_data['title'] ?? '');

	if ($domain === '') {
		wp_die(__('Missing or invalid site address.'));
	}
	if ($email === '') {
		wp_die(__('Missing email address.'));
	}
	if (!is_email($email)) {
		wp_die(__('Invalid email address.'));
	}

	if (is_subdomain_install()) {
		$newdomain = $domain . '.' . preg_replace('/^www\./', '', $current_site->domain);
		$path      = $current_site->path;
	} else {
		$newdomain = $current_site->domain;
		$path      = $current_site->path . $domain . '/';
	}

	$password = 'N/A';
	$user_id = email_exists($email);
	if ($user_id === false) { // Create a new user with a random password.
		$password = wp_generate_password(length: 12, special_chars: false);
		$user_id = wpmu_create_user($domain, $password, $email);
		if ($user_id === false || is_wp_error($user_id)) {
			$error_message = is_wp_error($user_id) ? $user_id->get_error_message() : __('There was an error creating the user.');
			wp_die($error_message);
		}
		// This function call is deprecated but retained for functional equivalence. Modern WP uses password reset links.
		wp_new_user_notification($user_id, $password);
	}

	$wpdb->hide_errors();
	$id = wpmu_create_blog($newdomain, $path, $title, $user_id, ['public' => 1], $current_site->id);
	$wpdb->show_errors();

	if (!is_wp_error($id)) {
		if (!is_super_admin($user_id) && !get_user_option('primary_blog', $user_id)) {
			update_user_option(user_id: $user_id, option_name: 'primary_blog', newvalue: $id, global: true);
		}

		$content_mail = sprintf(
			__("New site created by %1\$s\n\nAddress: %2\$s\nName: %3\$s"),
			$current_user->user_login,
			get_site_url($id),
			wp_unslash($title)
		);

		$headers = sprintf('From: "Site Admin" <%s>', get_site_option('admin_email'));

		wp_mail(
			to: get_site_option('admin_email'),
			subject: sprintf(__('[%s] New Site Created'), $current_site->site_name),
			message: $content_mail,
			headers: $headers
		);

		wpmu_welcome_notification($id, $user_id, $password, $title, ['public' => 1]);
		wp_redirect(add_query_arg(['update' => 'added', 'id' => $id], 'site-new.php'));
		exit;
	} else {
		wp_die($id->get_error_message());
	}
}

$messages = [];
$update_action = $_GET['update'] ?? '';
$site_id = absint($_GET['id'] ?? 0);

if ($update_action === 'added' && $site_id > 0) {
	$messages[] = sprintf(
		__('Site added. <a href="%1$s">Visit Dashboard</a> or <a href="%2$s">Edit Site</a>'),
		esc_url(get_admin_url($site_id)),
		network_admin_url('site-info.php?id=' . $site_id)
	);
}

$title = __('Add New Site');
$parent_file = 'sites.php';

wp_enqueue_script('user-suggest');

require ABSPATH . 'wp-admin/admin-header.php';

?>

<div class="wrap">
	<h2 id="add-new-site"><?= __('Add New Site') ?></h2>
	<?php
	if (!empty($messages)) {
		foreach ($messages as $msg) {
			echo '<div id="message" class="updated"><p>' . $msg . '</p></div>';
		}
	}
	?>
	<form method="post" action="<?= network_admin_url('site-new.php?action=add-site') ?>" novalidate="novalidate">
		<?php wp_nonce_field('add-blog', '_wpnonce_add-blog'); ?>
		<table class="form-table">
			<tr class="form-field form-required">
				<th scope="row"><label for="blog-domain"><?= __('Site Address') ?></label></th>
				<td>
					<?php if (is_subdomain_install()) : ?>
						<input name="blog[domain]" type="text" class="regular-text" id="blog-domain" title="<?= esc_attr__('Domain') ?>" /><span class="no-break">.<?= preg_replace('/^www\./', '', $current_site->domain) ?></span>
					<?php else : ?>
						<?= $current_site->domain . $current_site->path ?><input name="blog[domain]" class="regular-text" type="text" id="blog-domain" title="<?= esc_attr__('Domain') ?>" />
					<?php endif; ?>
					<p><?= __('Only lowercase letters (a-z) and numbers are allowed.') ?></p>
				</td>
			</tr>
			<tr class="form-field form-required">
				<th scope="row"><label for="blog-title"><?= __('Site Title') ?></label></th>
				<td><input name="blog[title]" type="text" class="regular-text" id="blog-title" title="<?= esc_attr__('Title') ?>" /></td>
			</tr>
			<tr class="form-field form-required">
				<th scope="row"><label for="blog-email"><?= __('Admin Email') ?></label></th>
				<td><input name="blog[email]" type="email" class="regular-text wp-suggest-user" id="blog-email" data-autocomplete-type="search" data-autocomplete-field="user_email" title="<?= esc_attr__('Email') ?>" /></td>
			</tr>
			<tr class="form-field">
				<td colspan="2"><?= __('A new user will be created if the above email address is not in the database.') ?><br /><?= __('The username and password will be mailed to this email address.') ?></td>
			</tr>
		</table>
		<?php submit_button(__('Add Site'), 'primary', 'add-site'); ?>
	</form>
</div>
<?php
require ABSPATH . 'wp-admin/admin-footer.php';