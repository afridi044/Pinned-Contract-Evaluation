<?php

declare(strict_types=1);

/**
 * Multisite upgrade administration panel.
 *
 * @package WordPress
 * @subpackage Multisite
 * @since 3.0.0
 */

/** Load WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if (!is_multisite()) {
	wp_die(__('Multisite support is not enabled.'));
}

require_once ABSPATH . WPINC . '/http.php';

$title = __('Upgrade Network');
$parent_file = 'upgrade.php';

get_current_screen()->add_help_tab([
	'id'      => 'overview',
	'title'   => __('Overview'),
	'content' => '<p>' . __('Only use this screen once you have updated to a new version of WordPress through Updates/Available Updates (via the Network Administration navigation menu or the Toolbar). Clicking the Upgrade Network button will step through each site in the network, five at a time, and make sure any database updates are applied.') . '</p>' .
		'<p>' . __('If a version update to core has not happened, clicking this button won&#8217;t affect anything.') . '</p>' .
		'<p>' . __('If this process fails for any reason, users logging in to their sites will force the same update.') . '</p>',
]);

get_current_screen()->set_help_sidebar(
	'<p><strong>' . __('For more information:') . '</strong></p>' .
	'<p>' . __('<a href="https://codex.wordpress.org/Network_Admin_Updates_Screen" target="_blank">Documentation on Upgrade Network</a>') . '</p>' .
	'<p>' . __('<a href="https://wordpress.org/support/" target="_blank">Support Forums</a>') . '</p>'
);

require_once ABSPATH . 'wp-admin/admin-header.php';

if (!current_user_can('manage_network')) {
	wp_die(__('You do not have permission to access this page.'));
}

echo '<div class="wrap">';
echo '<h2>' . esc_html__('Upgrade Network') . '</h2>';

$action = $_GET['action'] ?? 'show';

match ($action) {
	'upgrade' => (function () {
		global $wp_db_version, $wpdb;

		$n = (int) ($_GET['n'] ?? 0);

		if ($n === 0) {
			update_site_option('wpmu_upgrade_site', $wp_db_version);
		}

		// The query is safe as $wpdb->siteid is an internal property and $n is cast to int.
		$blogs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->blogs} WHERE site_id = %d AND spam = '0' AND deleted = '0' AND archived = '0' ORDER BY registered DESC LIMIT %d, 5",
				$wpdb->siteid,
				$n
			),
			ARRAY_A
		);

		if (empty($blogs)) {
			echo '<p>' . esc_html__('All done!') . '</p>';
			return;
		}

		echo '<ul>';
		foreach ($blogs as $details) {
			switch_to_blog((int) $details['blog_id']);
			$siteurl = site_url();
			$upgrade_url = admin_url('upgrade.php?step=upgrade_db');
			restore_current_blog();

			echo '<li>' . esc_html($siteurl) . '</li>';

			$response = wp_remote_get($upgrade_url, ['timeout' => 120, 'httpversion' => '1.1']);

			if (is_wp_error($response)) {
				wp_die(
					sprintf(
						/* translators: 1: Site URL, 2: Error message. */
						__('Warning! Problem updating %1$s. Your server may not be able to connect to sites running on it. Error message: <em>%2$s</em>'),
						esc_html($siteurl),
						$response->get_error_message()
					)
				);
			}

			/**
			 * Fires after the Multisite DB upgrade for each site is complete.
			 * @param array|WP_Error $response The upgrade response array or WP_Error on failure.
			 */
			do_action('after_mu_upgrade', $response);

			/**
			 * Fires after each site has been upgraded.
			 * @param int $blog_id The id of the blog.
			 */
			do_action('wpmu_upgrade_site', (int) $details['blog_id']);
		}
		echo '</ul>';

		$next_n = $n + 5;
		$next_url = "upgrade.php?action=upgrade&n={$next_n}";
?>
		<p><?php esc_html_e('If your browser doesn’t start loading the next page automatically, click this link:'); ?> <a class="button" href="<?php echo esc_url($next_url); ?>"><?php esc_html_e('Next Sites'); ?></a></p>
		<script>
			(function() {
				'use strict';
				setTimeout(function() {
					window.location.href = "<?php echo esc_url($next_url); ?>";
				}, 250);
			})();
		</script>
<?php
	})(),

	default => (function () { // 'show' and default cases
		global $wp_db_version;

		if (get_site_option('wpmu_upgrade_site') != $wp_db_version) :
?>
			<h3><?php esc_html_e('Database Upgrade Required'); ?></h3>
			<p><?php esc_html_e('WordPress has been updated! Before we send you on your way, we need to individually upgrade the sites in your network.'); ?></p>
		<?php endif; ?>

		<p><?php esc_html_e('The database upgrade process may take a little while, so please be patient.'); ?></p>
		<p><a class="button" href="upgrade.php?action=upgrade"><?php esc_html_e('Upgrade Network'); ?></a></p>
<?php
		/**
		 * Fires before the footer on the network upgrade screen.
		 */
		do_action('wpmu_upgrade_page');
	})(),
};
?>
</div>

<?php include ABSPATH . 'wp-admin/admin-footer.php'; ?>