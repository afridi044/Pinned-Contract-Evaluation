<?php

declare(strict_types=1);

/**
 * Credits administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

$title = __('Credits');

/**
 * Retrieve the contributor credits.
 *
 * @global string $wp_version The current WordPress version.
 *
 * @since 3.2.0
 *
 * @return array<string, mixed>|false A list of all of the contributors, or false on error.
 */
function wp_credits(): array|false
{
	global $wp_version;
	$locale = get_locale();

	$transient_key = 'wordpress_credits_' . $locale;
	$results       = get_site_transient($transient_key);

	$is_development_version  = str_contains($wp_version, '-');
	$cached_version_mismatch = isset($results['data']['version']) && ! str_starts_with($wp_version, (string) $results['data']['version']);

	if (! is_array($results) || $is_development_version || $cached_version_mismatch) {
		$response = wp_remote_get("https://api.wordpress.org/core/credits/1.1/?version={$wp_version}&locale={$locale}");

		if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
			return false;
		}

		$results = json_decode(wp_remote_retrieve_body($response), true);

		if (! is_array($results)) {
			return false;
		}

		set_site_transient($transient_key, $results, DAY_IN_SECONDS);
	}

	return $results;
}

[$display_version] = explode('-', $wp_version);

include ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap about-wrap">

<h1><?php printf(__('Welcome to WordPress %s'), $display_version); ?></h1>

<div class="about-text"><?php printf(__('Thank you for updating! WordPress %s brings you a smoother writing and management experience.'), $display_version); ?></div>

<div class="wp-badge"><?php printf(__('Version %s'), $display_version); ?></div>

<h2 class="nav-tab-wrapper">
	<a href="about.php" class="nav-tab">
		<?php _e('What&#8217;s New'); ?>
	</a><a href="credits.php" class="nav-tab nav-tab-active">
		<?php _e('Credits'); ?>
	</a><a href="freedoms.php" class="nav-tab">
		<?php _e('Freedoms'); ?>
	</a>
</h2>

<?php
$credits = wp_credits();

if (! $credits) {
	echo '<p class="about-description">' . sprintf(
		/* translators: 1: WordPress about page URL, 2: Codex URL. */
		__('WordPress is created by a <a href="%1$s">worldwide team</a> of passionate individuals. <a href="%2$s">Get involved in WordPress</a>.'),
		'https://wordpress.org/about/',
		/* translators: URL to the Codex documentation on contributing to WordPress used on the credits page. */
		__('https://codex.wordpress.org/Contributing_to_WordPress')
	) . '</p>';
	include ABSPATH . 'wp-admin/admin-footer.php';
	exit;
}

echo '<p class="about-description">' . __('WordPress is created by a worldwide team of passionate individuals.') . "</p>\n";

$gravatar_base_url = is_ssl() ? 'https://secure.gravatar.com/avatar/' : 'http://0.gravatar.com/avatar/';

foreach ($credits['groups'] as $group_slug => $group_data) {
	if ($group_data['name']) {
		if ('Translators' === $group_data['name']) {
			// Considered a special slug in the API response. (Also, will never be returned for en_US.)
			$group_title = _x('Translators', 'Translate this to be the equivalent of English Translators in your language for the credits page Translators section');
		} elseif (isset($group_data['placeholders'])) {
			$group_title = vsprintf(translate($group_data['name']), $group_data['placeholders']);
		} else {
			$group_title = translate($group_data['name']);
		}

		echo "<h4 class=\"wp-people-group\">{$group_title}</h4>\n";
	}

	if (! empty($group_data['shuffle'])) {
		shuffle($group_data['data']); // We were going to sort by ability to pronounce "hierarchical," but that wouldn't be fair to Matt.
	}

	switch ($group_data['type']) {
		case 'list':
			$profiles_url_template = $credits['data']['profiles'];
			array_walk(
				$group_data['data'],
				static function (string &$display_name, string $username) use ($profiles_url_template): void {
					$profile_url  = esc_url(sprintf($profiles_url_template, $username));
					$display_name = "<a href=\"{$profile_url}\">" . esc_html($display_name) . '</a>';
				}
			);
			echo '<p class="wp-credits-list">' . wp_sprintf('%l.', $group_data['data']) . "</p>\n\n";
			break;

		case 'libraries':
			array_walk(
				$group_data['data'],
				static function (array|string &$data): void {
					if (is_array($data)) {
						$library_url  = esc_url($data[1]);
						$library_name = esc_html($data[0]);
						$data         = "<a href=\"{$library_url}\">{$library_name}</a>";
					}
				}
			);
			echo '<p class="wp-credits-list">' . wp_sprintf('%l.', $group_data['data']) . "</p>\n\n";
			break;

		default:
			$is_compact            = ($group_data['type'] === 'compact');
			$classes               = $is_compact ? 'wp-people-group compact' : 'wp-people-group';
			$profiles_url_template = $credits['data']['profiles'];

			echo "<ul class=\"{$classes}\" id=\"wp-people-group-{$group_slug}\">\n";

			foreach ($group_data['data'] as $person_data) {
				[$display_name, $gravatar_hash, $username, $person_title] = $person_data;
				$profile_url = esc_url(sprintf($profiles_url_template, $username));
				$size        = $is_compact ? 30 : 60;

				echo "<li class=\"wp-person\" id=\"wp-person-{$username}\">\n\t";
				echo "<a href=\"{$profile_url}\">";
				echo "<img src=\"{$gravatar_base_url}{$gravatar_hash}?s={$size}\" class=\"gravatar\" alt=\"" . esc_attr($display_name) . "\" /></a>\n\t";
				echo "<a class=\"web\" href=\"{$profile_url}\">" . esc_html($display_name) . "</a>\n\t";

				if (! $is_compact) {
					echo '<span class="title">' . esc_html(translate($person_title)) . "</span>\n";
				}
				echo "</li>\n";
			}
			echo "</ul>\n";
			break;
	}
}
?>
<p class="clear"><?php
printf(
	/* translators: %s: URL to the Make WordPress 'Get Involved' landing page. */
	__('Want to see your name in lights on this page? <a href="%s">Get involved in WordPress</a>.'),
	/* translators: URL to the Make WordPress 'Get Involved' landing page used on the credits page. */
	__('https://make.wordpress.org/')
);
?></p>

</div>
<?php
include ABSPATH . 'wp-admin/admin-footer.php';

// These are strings returned by the API that we want to be translatable.
__('Project Leaders');
__('Extended Core Team');
__('Core Developers');
__('Recent Rockstars');
__('Core Contributors to WordPress %s');
__('Contributing Developers');
__('Cofounder, Project Lead');
__('Lead Developer');
__('Release Lead');
__('User Experience Lead');
__('Core Developer');
__('Core Committer');
__('Guest Committer');
__('Developer');
__('Designer');
__('XML-RPC');
__('Internationalization');
__('External Libraries');
__('Icon Design');