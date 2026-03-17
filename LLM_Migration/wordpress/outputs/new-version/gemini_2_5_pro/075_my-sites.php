<?php

declare(strict_types=1);

/**
 * My Sites dashboard.
 *
 * @package WordPress
 * @subpackage Multisite
 * @since 3.0.0
 */

require_once __DIR__ . '/admin.php';

if (!is_multisite()) {
	wp_die(__('Multisite support is not enabled.'));
}

if (!current_user_can('read')) {
	wp_die(__('You do not have sufficient permissions to view this page.'));
}

$action = $_POST['action'] ?? 'splash';

$blogs = get_blogs_of_user($current_user->ID);

$updated = false;
if ($action === 'updateblogsettings' && isset($_POST['primary_blog'])) {
	check_admin_referer('update-my-sites');

	$primary_blog_id = (int) $_POST['primary_blog'];
	$blog = get_blog_details($primary_blog_id);

	if ($blog) {
		update_user_option($current_user->ID, 'primary_blog', $primary_blog_id, true);
		$updated = true;
	} else {
		wp_die(__('The primary site you chose does not exist.'));
	}
}

$title = __('My Sites');
$parent_file = 'index.php';

get_current_screen()->add_help_tab([
	'id'      => 'overview',
	'title'   => __('Overview'),
	'content' => '<p>' . __('This screen shows an individual user all of their sites in this network, and also allows that user to set a primary site. They can use the links under each site to visit either the frontend or the dashboard for that site.') . '</p>' .
				 '<p>' . __('Up until WordPress version 3.0, what is now called a Multisite Network had to be installed separately as WordPress MU (multi-user).') . '</p>',
]);

get_current_screen()->set_help_sidebar(
	'<p><strong>' . __('For more information:') . '</strong></p>' .
	'<p>' . __('<a href="http://codex.wordpress.org/Dashboard_My_Sites_Screen" target="_blank">Documentation on My Sites</a>') . '</p>' .
	'<p>' . __('<a href="https://wordpress.org/support/" target="_blank">Support Forums</a>') . '</p>'
);

require_once ABSPATH . 'wp-admin/admin-header.php';

if ($updated) { ?>
	<div id="message" class="updated"><p><strong><?php _e('Settings saved.'); ?></strong></p></div>
<?php } ?>

<div class="wrap">
	<h2><?php echo esc_html($title); ?></h2>
	<?php
	if (empty($blogs)) :
		echo '<p>';
		_e('You must be a member of at least one site to use this page.');
		echo '</p>';
	else :
	?>
		<form id="myblogs" action="" method="post">
			<?php
			choose_primary_blog();
			/**
			 * Fires before the sites table on the My Sites screen.
			 *
			 * @since 3.0.0
			 */
			do_action('myblogs_allblogs_options');
			?>
			<br clear="all" />
			<table class="widefat fixed">
				<?php
				/** This filter is documented in wp-admin/my-sites.php */
				$settings_html = apply_filters('myblogs_options', '', 'global');
				if ($settings_html !== '') {
					echo '<tr><td><h3>' . esc_html__('Global Settings') . '</h3></td><td>';
					echo $settings_html; // Assuming HTML from filter is safe.
					echo '</td></tr>';
				}

				$blog_count = count($blogs);
				$cols = match (true) {
					$blog_count >= 20 => 4,
					$blog_count >= 10 => 2,
					default => 1,
				};
				$blog_rows = array_chunk($blogs, $cols);

				foreach ($blog_rows as $row_index => $row) {
					$row_class = ($row_index % 2 === 1) ? 'alternate' : '';
					echo "<tr class='{$row_class}'>";

					$cell_count = count($row);
					foreach ($row as $cell_index => $user_blog) {
						$style = ($cell_index < $cell_count - 1) ? 'border-right: 1px solid #ccc;' : '';
						echo "<td style='{$style}'>";
						echo '<h3>' . esc_html($user_blog->blogname) . '</h3>';

						$visit_url = esc_url(get_home_url($user_blog->userblog_id));
						$dashboard_url = esc_url(get_admin_url($user_blog->userblog_id));
						$default_actions = sprintf(
							'<a href="%s">%s</a> | <a href="%s">%s</a>',
							$visit_url,
							__('Visit'),
							$dashboard_url,
							__('Dashboard')
						);

						/** This filter is documented in wp-admin/my-sites.php */
						$actions = apply_filters('myblogs_blog_actions', $default_actions, $user_blog);
						echo "<p>{$actions}</p>"; // Assuming HTML from filter is safe.

						/** This filter is documented in wp-admin/my-sites.php */
						echo apply_filters('myblogs_options', '', $user_blog); // Assuming HTML from filter is safe.
						echo '</td>';
					}
					echo '</tr>';
				}
				?>
			</table>
			<input type="hidden" name="action" value="updateblogsettings" />
			<?php wp_nonce_field('update-my-sites'); ?>
			<?php submit_button(); ?>
		</form>
	<?php endif; ?>
</div>
<?php
include ABSPATH . 'wp-admin/admin-footer.php';