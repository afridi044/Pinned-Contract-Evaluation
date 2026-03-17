<?php
/**
 * Import WordPress Administration Screen
 *
 * @package WordPress
 * @subpackage Administration
 */

define('WP_LOAD_IMPORTERS', true);

/** Load WordPress Bootstrap */
require_once __DIR__ . '/admin.php';

if (!current_user_can('import')) {
	wp_die(__('You do not have sufficient permissions to import content in this site.'));
}

$title = __('Import');

get_current_screen()->add_help_tab([
	'id'      => 'overview',
	'title'   => __('Overview'),
	'content' => '<p>' . __('This screen lists links to plugins to import data from blogging/content management platforms. Choose the platform you want to import from, and click Install Now when you are prompted in the popup window. If your platform is not listed, click the link to search the plugin directory for other importer plugins to see if there is one for your platform.') . '</p>' .
		'<p>' . __('In previous versions of WordPress, all importers were built-in. They have been turned into plugins since most people only use them once or infrequently.') . '</p>',
]);

get_current_screen()->set_help_sidebar(
	'<p><strong>' . __('For more information:') . '</strong></p>' .
	'<p>' . __('<a href="https://wordpress.org/documentation/article/tools-import-screen/" target="_blank">Documentation on Import</a>') . '</p>' .
	'<p>' . __('<a href="https://wordpress.org/support/" target="_blank">Support Forums</a>') . '</p>'
);

$popular_importers = current_user_can('install_plugins') ? wp_get_popular_importers() : [];

// Detect and redirect invalid importers like 'movabletype', which is registered as 'mt'.
$invalid_importer_slug = $_GET['invalid'] ?? null;
if ($invalid_importer_slug && isset($popular_importers[$invalid_importer_slug])) {
	$importer_id = $popular_importers[$invalid_importer_slug]['importer-id'];
	if ($importer_id !== $invalid_importer_slug) { // Prevent redirect loops.
		wp_redirect(admin_url('admin.php?import=' . $importer_id));
		exit;
	}
}

add_thickbox();
wp_enqueue_script('plugin-install');

$parent_file = 'tools.php';
require_once ABSPATH . 'wp-admin/admin-header.php';
?>

<div class="wrap">
	<h1><?php echo esc_html($title); ?></h1>

	<?php if ($invalid_importer_slug) : ?>
		<div class="error">
			<p>
				<strong><?php _e('ERROR:'); ?></strong>
				<?php
				printf(
					/* translators: %s: Importer name. */
					__('The <strong>%s</strong> importer is invalid or is not installed.'),
					esc_html($invalid_importer_slug)
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<p><?php _e('If you have posts or comments in another system, WordPress can import those into this site. To get started, choose a system to import from below:'); ?></p>

	<?php
	$importers = get_importers();

	// If a popular importer is not registered, create a dummy registration that links to the plugin installer.
	foreach ($popular_importers as $pop_importer => $pop_data) {
		if (isset($importers[$pop_importer]) || isset($importers[$pop_data['importer-id']])) {
			continue;
		}
		$importers[$pop_data['importer-id']] = [
			$pop_data['name'],
			$pop_data['description'],
			'install' => $pop_data['plugin-slug'],
		];
	}

	if (empty($importers)) :
		echo '<p>' . __('No importers are available.') . '</p>'; // TODO: make more helpful
	else :
		uasort($importers, '_usort_by_first_member');
		?>
		<table class="widefat importers">
			<?php
			$is_alternate_row = false;
			foreach ($importers as $importer_id => $data) :
				$name = $data[0];
				$description = $data[1];
				$plugin_slug = $data['install'] ?? null;
				$action_html = '';

				if ($plugin_slug) {
					if (file_exists(WP_PLUGIN_DIR . '/' . $plugin_slug)) {
						// Importer is installed, but not active.
						$plugins = get_plugins('/' . $plugin_slug);
						if (!empty($plugins)) {
							$plugin_file = $plugin_slug . '/' . array_key_first($plugins);
							$url = esc_url(wp_nonce_url(admin_url('plugins.php?action=activate&plugin=' . $plugin_file . '&from=import'), 'activate-plugin_' . $plugin_file));
							$action_html = sprintf('<a href="%s" title="%s">%s</a>', $url, esc_attr__('Activate importer'), esc_html($name));
						}
					}

					if (empty($action_html)) {
						if (is_main_site()) {
							$url = esc_url(network_admin_url('plugin-install.php?tab=plugin-information&plugin=' . $plugin_slug . '&from=import&TB_iframe=true&width=600&height=550'));
							$action_html = sprintf('<a href="%s" class="thickbox" title="%s">%s</a>', $url, esc_attr__('Install importer'), esc_html($name));
						} else {
							$action_html = esc_html($name);
							$main_site_url = get_admin_url($current_site->blog_id, 'import.php');
							$description = sprintf(__('This importer is not installed. Please install importers from <a href="%s">the main site</a>.'), esc_url($main_site_url));
						}
					}
				} else {
					$url = esc_url("admin.php?import={$importer_id}");
					$title_attr = esc_attr(wptexturize(strip_tags($description)));
					$action_html = sprintf('<a href="%s" title="%s">%s</a>', $url, $title_attr, esc_html($name));
				}

				$row_class = $is_alternate_row ? ' class="alternate"' : '';
				?>
				<tr<?php echo $row_class; ?>>
					<td class="import-system row-title"><?php echo $action_html; ?></td>
					<td class="desc"><?php echo $description; ?></td>
				</tr>
				<?php
				$is_alternate_row = !$is_alternate_row;
			endforeach;
			?>
		</table>
		<?php
	endif;

	if (current_user_can('install_plugins')) {
		$search_url = esc_url(network_admin_url('plugin-install.php?tab=search&type=tag&s=importer'));
		printf(
			'<p>%s</p>',
			sprintf(
				/* translators: %s: URL to search for importer plugins. */
				__('If the importer you need is not listed, <a href="%s">search the plugin directory</a> to see if an importer is available.'),
				$search_url
			)
		);
	}
	?>
</div>

<?php
include ABSPATH . 'wp-admin/admin-footer.php';