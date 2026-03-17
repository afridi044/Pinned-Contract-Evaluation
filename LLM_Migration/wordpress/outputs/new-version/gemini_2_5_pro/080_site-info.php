<?php declare(strict_types=1);

/**
 * Edit Site Info Administration Screen
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

// Help tabs content.
$overview_p1 = __('The menu is for editing information specific to individual sites, particularly if the admin area of a site is unavailable.');
$overview_p2 = __('<strong>Info</strong> - The domain and path are rarely edited as this can cause the site to not work properly. The Registered date and Last Updated date are displayed. Network admins can mark a site as archived, spam, deleted and mature, to remove from public listings or disable.');
$overview_p3 = __('<strong>Users</strong> - This displays the users associated with this site. You can also change their role, reset their password, or remove them from the site. Removing the user from the site does not remove the user from the network.');
$overview_p4 = sprintf(
	__('<strong>Themes</strong> - This area shows themes that are not already enabled across the network. Enabling a theme in this menu makes it accessible to this site. It does not activate the theme, but allows it to show in the site&#8217;s Appearance menu. To enable a theme for the entire network, see the <a href="%s">Network Themes</a> screen.'),
	network_admin_url('themes.php')
);
$overview_p5 = __('<strong>Settings</strong> - This page shows a list of all settings associated with this site. Some are created by WordPress and others are created by plugins you activate. Note that some fields are grayed out and say Serialized Data. You cannot modify these values due to the way the setting is stored in the database.');

$overview_content = <<<HTML
<p>{$overview_p1}</p>
<p>{$overview_p2}</p>
<p>{$overview_p3}</p>
<p>{$overview_p4}</p>
<p>{$overview_p5}</p>
HTML;

get_current_screen()->add_help_tab([
	'id'      => 'overview',
	'title'   => __('Overview'),
	'content' => $overview_content,
]);

// Help sidebar content.
$sidebar_title = __('For more information:');
$sidebar_link1 = __('<a href="https://codex.wordpress.org/Network_Admin_Sites_Screen" target="_blank">Documentation on Site Management</a>');
$sidebar_link2 = __('<a href="https://wordpress.org/support/forum/multisite/" target="_blank">Support Forums</a>');

$sidebar_content = <<<HTML
<p><strong>{$sidebar_title}</strong></p>
<p>{$sidebar_link1}</p>
<p>{$sidebar_link2}</p>
HTML;

get_current_screen()->set_help_sidebar($sidebar_content);

$id = (int) ($_REQUEST['id'] ?? 0);

if ($id === 0) {
	wp_die(__('Invalid site ID.'));
}

$details = get_blog_details($id);
if (!can_edit_network($details->site_id)) {
	wp_die(__('You do not have permission to access this page.'));
}

$parsed = parse_url($details->siteurl);
$is_main_site = is_main_site($id);

if (($_REQUEST['action'] ?? null) === 'update-site') {
	check_admin_referer('edit-site');

	switch_to_blog($id);

	if (($_POST['update_home_url'] ?? null) === 'update') {
		$blog_address = esc_url_raw($_POST['blog']['domain'] . $_POST['blog']['path']);
		if (get_option('siteurl') !== $blog_address) {
			update_option('siteurl', $blog_address);
		}

		if (get_option('home') !== $blog_address) {
			update_option('home', $blog_address);
		}
	}

	// Rewrite rules can't be flushed during switch to blog.
	delete_option('rewrite_rules');

	// Update blogs table.
	$blog_data = wp_unslash($_POST['blog']);
	$existing_details = get_blog_details($id, false);
	$blog_data_checkboxes = ['public', 'archived', 'spam', 'mature', 'deleted'];
	foreach ($blog_data_checkboxes as $c) {
		if (!in_array($existing_details->$c, [0, 1], true)) {
			$blog_data[$c] = $existing_details->$c;
		} else {
			$blog_data[$c] = isset($_POST['blog'][$c]) ? 1 : 0;
		}
	}
	update_blog_details($id, $blog_data);

	restore_current_blog();
	wp_redirect(add_query_arg(['update' => 'updated', 'id' => $id], 'site-info.php'));
	exit;
}

$messages = [];
if (($_GET['update'] ?? null) === 'updated') {
	$messages[] = __('Site info updated.');
}

$site_url_no_http = preg_replace('#^https?://#', '', get_blogaddress_by_id($id));
$title_site_url_linked = sprintf(__('Edit Site: <a href="%1$s">%2$s</a>'), get_blogaddress_by_id($id), $site_url_no_http);
$title = sprintf(__('Edit Site: %s'), $site_url_no_http);

$parent_file = 'sites.php';
$submenu_file = 'sites.php';

require ABSPATH . 'wp-admin/admin-header.php';

?>

<div class="wrap">
<h2 id="edit-site"><?php echo $title_site_url_linked; ?></h2>
<h3 class="nav-tab-wrapper">
<?php
$tabs = [
	'site-info'     => ['label' => __('Info'), 'url' => 'site-info.php'],
	'site-users'    => ['label' => __('Users'), 'url' => 'site-users.php'],
	'site-themes'   => ['label' => __('Themes'), 'url' => 'site-themes.php'],
	'site-settings' => ['label' => __('Settings'), 'url' => 'site-settings.php'],
];
foreach ($tabs as $tab) {
	$class = ($tab['url'] === $pagenow) ? ' nav-tab-active' : '';
	echo '<a href="' . $tab['url'] . '?id=' . $id . '" class="nav-tab' . $class . '">' . esc_html($tab['label']) . '</a>';
}
?>
</h3>
<?php
if (!empty($messages)) {
	foreach ($messages as $msg) {
		echo '<div id="message" class="updated"><p>' . $msg . '</p></div>';
	}
}
?>
<form method="post" action="site-info.php?action=update-site">
	<?php wp_nonce_field('edit-site'); ?>
	<input type="hidden" name="id" value="<?= esc_attr((string) $id) ?>" />
	<table class="form-table">
		<tr class="form-field form-required">
			<th scope="row"><?php _e('Domain') ?></th>
			<?php if ($is_main_site) : ?>
				<td><code><?= $parsed['scheme'] . '://' . esc_attr($details->domain) ?></code></td>
			<?php else : ?>
				<td><?= $parsed['scheme'] . '://'; ?><input name="blog[domain]" type="text" id="domain" value="<?= esc_attr($details->domain) ?>" size="33" /></td>
			<?php endif; ?>
		</tr>
		<tr class="form-field form-required">
			<th scope="row"><?php _e('Path') ?></th>
			<?php if ($is_main_site) : ?>
			<td><code><?= esc_attr($details->path) ?></code></td>
			<?php
			else :
				switch_to_blog($id);
			?>
			<td><input name="blog[path]" type="text" id="path" value="<?= esc_attr($details->path) ?>" size="40" style="margin-bottom:5px;" />
			<br /><input type="checkbox" style="width:20px;" name="update_home_url" value="update" <?php
				$blog_address = untrailingslashit(get_blogaddress_by_id($id));
				checked(get_option('siteurl') === $blog_address || get_option('home') === $blog_address);
			?> /> <?php _e('Update <code>siteurl</code> and <code>home</code> as well.'); ?></td>
			<?php
				restore_current_blog();
			endif;
			?>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php _ex('Registered', 'site') ?></th>
			<td><input name="blog[registered]" type="text" id="blog_registered" value="<?= esc_attr($details->registered) ?>" size="40" /></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php _e('Last Updated'); ?></th>
			<td><input name="blog[last_updated]" type="text" id="blog_last_updated" value="<?= esc_attr($details->last_updated) ?>" size="40" /></td>
		</tr>
		<?php
		$attribute_fields = ['public' => __('Public')];
		if (!$is_main_site) {
			$attribute_fields['archived'] = __('Archived');
			$attribute_fields['spam']     = _x('Spam', 'site');
			$attribute_fields['deleted']  = __('Deleted');
		}
		$attribute_fields['mature'] = __('Mature');
		?>
		<tr>
			<th scope="row"><?php _e('Attributes'); ?></th>
			<td>
			<?php foreach ($attribute_fields as $field_key => $field_label) : ?>
				<label><input type="checkbox" name="blog[<?= $field_key ?>]" value="1" <?php checked((bool) $details->$field_key, true); disabled(!in_array($details->$field_key, [0, 1], true)); ?> />
				<?= $field_label ?></label><br/>
			<?php endforeach; ?>
			</td>
		</tr>
	</table>
	<?php submit_button(); ?>
</form>

</div>
<?php
require ABSPATH . 'wp-admin/admin-footer.php';