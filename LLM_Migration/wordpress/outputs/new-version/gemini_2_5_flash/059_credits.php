<?php
/**
 * Credits administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php'; // Use __DIR__ for directory path

$title = __( 'Credits' );

/**
 * Retrieve the contributor credits.
 *
 * @global string $wp_version The current WordPress version.
 *
 * @since 3.2.0
 *
 * @return array|false A list of all of the contributors, or false on error.
*/
function wp_credits(): array|false { // Add return type declaration
	global $wp_version;
	$locale = get_locale();

	$results = get_site_transient( 'wordpress_credits_' . $locale );

	// Modernize string checks with str_contains and str_starts_with (PHP 8.0+)
	if (
		! is_array( $results )
		|| str_contains( $wp_version, '-' )
		|| ( isset( $results['data']['version'] ) && ! str_starts_with( $wp_version, $results['data']['version'] ) )
	) {
		$response = wp_remote_get( "http://api.wordpress.org/core/credits/1.1/?version=$wp_version&locale=$locale" );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) { // Use strict comparison
			return false;
		}

		$results = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $results ) ) {
			return false;
		}

		set_site_transient( 'wordpress_credits_' . $locale, $results, DAY_IN_SECONDS );
	}

	return $results;
}

/**
 * Retrieve the link to a contributor's WordPress.org profile page.
 *
 * @access private
 * @since 3.2.0
 *
 * @param string &$display_name The contributor's display name, passed by reference.
 * @param string $username      The contributor's username.
 * @param string $profiles      URL to the contributor's WordPress.org profile page.
 * @return void
 */
function _wp_credits_add_profile_link( string &$display_name, string $username, string $profiles ): void { // Add type declarations, void return
	$display_name = '<a href="' . esc_url( sprintf( $profiles, $username ) ) . '">' . esc_html( $display_name ) . '</a>';
}

/**
 * Retrieve the link to an external library used in WordPress.
 *
 * @access private
 * @since 3.2.0
 *
 * @param array &$data External library data, passed by reference.
 * @return void
 */
function _wp_credits_build_object_link( array &$data ): void { // Add type declarations, void return
	$data = '<a href="' . esc_url( $data[1] ) . '">' . $data[0] . '</a>';
}

[ $display_version ] = explode( '-', $wp_version ); // Use array destructuring for assignment

include ABSPATH . 'wp-admin/admin-header.php'; // Parentheses around include argument are optional
?>
<div class="wrap about-wrap">

<h1><?php printf( __( 'Welcome to WordPress %s' ), $display_version ); ?></h1>

<div class="about-text"><?php printf( __( 'Thank you for updating! WordPress %s brings you a smoother writing and management experience.' ), $display_version ); ?></div>

<div class="wp-badge"><?php printf( __( 'Version %s' ), $display_version ); ?></div>

<h2 class="nav-tab-wrapper">
	<a href="about.php" class="nav-tab">
		<?php _e( 'What&#8217;s New' ); ?>
	</a><a href="credits.php" class="nav-tab nav-tab-active">
		<?php _e( 'Credits' ); ?>
	</a><a href="freedoms.php" class="nav-tab">
		<?php _e( 'Freedoms' ); ?>
	</a>
</h2>

<?php

$credits = wp_credits();

if ( ! $credits ) {
	echo '<p class="about-description">' . sprintf( __( 'WordPress is created by a <a href="%1$s">worldwide team</a> of passionate individuals. <a href="%2$s">Get involved in WordPress</a>.' ),
		'https://wordpress.org/about/',
		/* translators: Url to the codex documentation on contributing to WordPress used on the credits page */
		__( 'http://codex.wordpress.org/Contributing_to_WordPress' ) ) . '</p>';
	include ABSPATH . 'wp-admin/admin-footer.php';
	exit;
}

echo '<p class="about-description">' . __( 'WordPress is created by a worldwide team of passionate individuals.' ) . "</p>\n";

$gravatar = is_ssl() ? 'https://secure.gravatar.com/avatar/' : 'http://0.gravatar.com/avatar/';

foreach ( $credits['groups'] as $group_slug => $group_data ) {
	if ( $group_data['name'] ) {
		if ( 'Translators' === $group_data['name'] ) { // Use strict comparison
			// Considered a special slug in the API response. (Also, will never be returned for en_US.)
			$title = _x( 'Translators', 'Translate this to be the equivalent of English Translators in your language for the credits page Translators section' );
		} elseif ( isset( $group_data['placeholders'] ) ) {
			$title = vsprintf( translate( $group_data['name'] ), $group_data['placeholders'] );
		} else {
			$title = translate( $group_data['name'] );
		}

		echo '<h4 class="wp-people-group">' . esc_html( $title ) . "</h4>\n"; // Escape title for HTML output
	}

	if ( ! empty( $group_data['shuffle'] ) ) {
		shuffle( $group_data['data'] ); // We were going to sort by ability to pronounce "hierarchical," but that wouldn't be fair to Matt.
	}

	switch ( $group_data['type'] ) {
		case 'list' :
			array_walk( $group_data['data'], '_wp_credits_add_profile_link', $credits['data']['profiles'] );
			echo '<p class="wp-credits-list">' . wp_sprintf( '%l.', $group_data['data'] ) . "</p>\n\n";
			break;
		case 'libraries' :
			array_walk( $group_data['data'], '_wp_credits_build_object_link' );
			echo '<p class="wp-credits-list">' . wp_sprintf( '%l.', $group_data['data'] ) . "</p>\n\n";
			break;
		default:
			$compact = 'compact' === $group_data['type']; // Use strict comparison
			$classes = 'wp-people-group ' . ( $compact ? 'compact' : '' );
			echo '<ul class="' . esc_attr( $classes ) . '" id="wp-people-group-' . esc_attr( $group_slug ) . '">' . "\n"; // Escape classes and slug
			foreach ( $group_data['data'] as $person_data ) {
				echo '<li class="wp-person" id="wp-person-' . esc_attr( $person_data[2] ) . '">' . "\n\t"; // Escape person ID
				echo '<a href="' . esc_url( sprintf( $credits['data']['profiles'], $person_data[2] ) ) . '">';
				$size = 'compact' === $group_data['type'] ? '30' : '60'; // Use strict comparison
				echo '<img src="' . esc_url( $gravatar . $person_data[1] . '?s=' . $size ) . '" class="gravatar" alt="' . esc_attr( $person_data[0] ) . '" /></a>' . "\n\t";
				echo '<a class="web" href="' . esc_url( sprintf( $credits['data']['profiles'], $person_data[2] ) ) . '">' . esc_html( $person_data[0] ) . "</a>\n\t"; // Escape person name
				if ( ! $compact ) {
					echo '<span class="title">' . esc_html( translate( $person_data[3] ) ) . "</span>\n"; // Escape title
				}
				echo "</li>\n";
			}
			echo "</ul>\n";
		break;
	}
}

?>
<p class="clear"><?php printf( __( 'Want to see your name in lights on this page? <a href="%s">Get involved in WordPress</a>.' ),
	/* translators: URL to the Make WordPress 'Get Involved' landing page used on the credits page */
	esc_url( __( 'https://make.wordpress.org/' ) ) ); // Escape URL ?></p>

</div>
<?php

include ABSPATH . 'wp-admin/admin-footer.php';

return;

// These are strings returned by the API that we want to be translatable
__( 'Project Leaders' );
__( 'Extended Core Team' );
__( 'Core Developers' );
__( 'Recent Rockstars' );
__( 'Core Contributors to WordPress %s' );
__( 'Contributing Developers' );
__( 'Cofounder, Project Lead' );
__( 'Lead Developer' );
__( 'Release Lead' );
__( 'User Experience Lead' );
__( 'Core Developer' );
__( 'Core Committer' );
__( 'Guest Committer' );
__( 'Developer' );
__( 'Designer' );
__( 'XML-RPC' );
__( 'Internationalization' );
__( 'External Libraries' );
__( 'Icon Design' );