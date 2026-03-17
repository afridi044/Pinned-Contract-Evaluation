<?php

declare(strict_types=1);

/**
 * Edit Site Settings Administration Screen
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
	wp_die(__('You do not have sufficient permissions to edit this site.'));
}

$themes_help_text = sprintf(
	/* translators: %s: Network Themes screen URL. */
	__('<strong>Themes</strong> - This area shows themes that are not already enabled across the network. Enabling a theme in this menu makes it accessible to this site. It does not activate the theme, but allows it to show in the site&#8217;s Appearance menu. To enable a theme for the entire network, see the <a href="%s">Network Themes</a> screen.'),
	network_admin_url('themes.php')
);

$help_tab_content = <<<HTML
<p>The menu is for editing information specific to individual sites, particularly if the admin area of a site is unavailable.</p>
<p><strong>Info</strong> - The domain and path are rarely edited as this can cause the site to not work properly. The Registered date and Last Updated date are displayed. Network admins can mark a site as archived, spam, deleted and mature, to remove from public listings or disable.</p>
<p><strong>Users</strong> - This displays the users associated with this site. You can also change their role, reset their password, or remove them from the site. Removing the user from the site does not remove the user from the network.</p>
<p>{$themes_help_text}</p>
<p><strong>Settings</strong> - This page shows a list of all settings associated with this site. Some are created by WordPress and others are created by plugins you activate. Note that some fields are grayed out and say Serialized Data. You cannot modify these values due to the way the setting is stored in the database.</p>
HTML;


get_current_screen()->add_help_tab([
	'id'      => 'overview',
	'title'   => __('Overview'),
	'content' => $help_tab_content,
]);

$sidebar_title    = __('For more information:');
$sidebar_link_1   = __('<a href="http://codex.wordpress.org/Network_Admin_Sites_Screen" target="_blank">Documentation on Site Management</a>');
$sidebar_link_2   = __('<a href="https://wordpress.org/support/forum/multisite/" target="_blank">Support Forums</a>');
$help_sidebar_content = <<<HTML
<p><strong>{$sidebar_title}</strong></p>
<p>{$sidebar_link_1}</p>
<p>{$sidebar_link_2}</p>
HTML;

get_current_screen()->set_help_sidebar($help_sidebar_content);

$id = (int) ($_REQUEST['id'] ?? 0);

if (!$id) {
	wp_die(__('Invalid site ID.'));
}

$details = get_blog_details($id);
if (!can_edit_network($details->site_id)) {
	wp_die(__('You do not have permission to access this page.'));
}

$is_main_site = is_main_site($id);

if (($_REQUEST['action'] ?? '') === 'update-site' && !empty($_POST['option']) && is_array($_POST['option'])) {
	check_admin_referer('edit-site');

	switch_to_blog($id);

	// Don't update these options since they are handled elsewhere in the form.
	$skip_options = ['allowedthemes'];
	foreach ($_POST['option'] as $key => $val) {
		$key = wp_unslash((string) $key);
		$val = wp_unslash($val);
		// Avoids "0 is a protected WP option and may not be modified" error when edit blog options.
		// The key from $_POST will be a string, so we must check for '0'.
		if ($key === '0' || is_array($val) || in_array($key, $skip_options, true)) {
			continue;
		}
		update_option($key, $val);
	}

	/**
	 * Fires after the site options are updated.
	 *
	 * @since 3.0.0
	 */
	do_action('wpmu_update_blog_options');
	restore_current_blog();
	wp_redirect(add_query_arg(['update' => 'updated', 'id' => $id], 'site-settings.php'));
	exit;
}

$messages = [];
if (($_GET['update'] ?? '') === 'updated') {
	$messages[] = __('Site options updated.');
}

$site_url_no_http    = preg_replace('#^https?://#', '', get_blogaddress_by_id($id));
$title_site_url_linked = sprintf(__('Edit Site: <a href="%1$s">%2$s</a>'), get_blogaddress_by_id($id), $site_url_no_http);
$title               = sprintf(__('Edit Site: %s'), $site_url_no_http);

$parent_file  = 'sites.php';
$submenu_file = 'sites.php';

require ABSPATH . 'wp-admin/admin-header.php';

?>

<div class="wrap">
<h2 id="edit-site"><?= $title_site_url_linked ?></h2>
<h3 class="nav-tab-wrapper">
<?php
$tabs = [
	'site-info'     => ['label' => __('Info'), 'url' => 'site-info.php'],
	'site-users'    => ['label' => __('Users'), 'url' => 'site-users.php'],
	'site-themes'   => ['label' => __('Themes'), 'url' => 'site-themes.php'],
	'site-settings' => ['label' => __('Settings'), 'url' => 'site-settings.php'],
];
foreach ($tabs as $tab_id => $tab) :
	$class = ($tab['url'] === $pagenow) ? ' nav-tab-active' : '';
	?>
	<a href="<?= $tab['url'] ?>?id=<?= $id ?>" class="nav-tab<?= $class ?>"><?= esc_html($tab['label']) ?></a>
<?php endforeach; ?>
</h3>
<?php
if (!empty($messages)) :
	foreach ($messages as $msg) :
		?>
		<div id="message" class="updated"><p><?= $msg ?></p></div>
		<?php
	endforeach;
endif;
?>
<form method="post" action="site-settings.php?action=update-site">
	<?php wp_nonce_field('edit-site'); ?>
	<input type="hidden" name="id" value="<?= esc_attr($id) ?>" />
	<table class="form-table">
		<?php
		$blog_prefix = $wpdb->get_blog_prefix($id);
		$sql         = "SELECT * FROM {$blog_prefix}options
			WHERE option_name NOT LIKE %s
			AND option_name NOT LIKE %s";
		$query       = $wpdb->prepare(
			$sql,
			$wpdb->esc_like('_') . '%',
			'%' . $wpdb->esc_like('user_roles')
		);
		$options     = $wpdb->get_results($query);
		foreach ($options as $option) :
			if ($option->option_name === 'default_role') {
				$editblog_default_role = $option->option_value;
			}
			$disabled = false;
			$class    = 'all-options';
			if (is_serialized($option->option_value)) {
				if (is_serialized_string($option->option_value)) {
					$option->option_value = esc_html(maybe_unserialize($option->option_value));
				} else {
					$option->option_value = 'SERIALIZED DATA';
					$disabled             = true;
					$class                = 'all-options disabled';
				}
			}

			if (str_contains((string) $option->option_value, "\n")) :
				?>
				<tr class="form-field">
					<th scope="row"><?= ucwords(str_replace('_', ' ', $option->option_name)) ?></th>
					<td><textarea class="<?= $class ?>" rows="5" cols="40" name="option[<?= esc_attr($option->option_name) ?>]" id="<?= esc_attr($option->option_name) ?>" <?php disabled($disabled); ?>><?= esc_textarea($option->option_value) ?></textarea></td>
				</tr>
			<?php else : ?>
				<tr class="form-field">
					<th scope="row"><?= esc_html(ucwords(str_replace('_', ' ', $option->option_name))) ?></th>
					<?php if ($is_main_site && in_array($option->option_name, ['siteurl', 'home'], true)) : ?>
					<td><code><?= esc_html($option->option_value) ?></code></td>
					<?php else : ?>
					<td><input class="<?= $class ?>" name="option[<?= esc_attr($option->option_name) ?>]" type="text" id="<?= esc_attr($option->option_name) ?>" value="<?= esc_attr($option->option_value) ?>" size="40" <?php disabled($disabled); ?> /></td>
					<?php endif; ?>
				</tr>
				<?php
			endif;
		endforeach; // End foreach

		/**
		 * Fires at the end of the Edit Site form, before the submit button.
		 *
		 * @since 3.0.0
		 *
		 * @param int $id Site ID.
		 */
		do_action('wpmueditblogaction', $id);
		?>
	</table>
	<?php submit_button(); ?>
</form>

</div>
<?php
require ABSPATH . 'wp-admin/admin-footer.php';