<?php
/**
 * My Sites dashboard.
 *
 * @package WordPress
 * @subpackage Multisite
 * @since 3.0.0
 */

require_once( __DIR__ . '/admin.php' );

if ( !is_multisite() )
	wp_die( __() );

if ( ! current_user_can('read') )
	wp_die( __() );

$action = $_POST['action'] ?? 'splash';

$blogs = get_blogs_of_user( $current_user->ID );

$updated = false;
if ( 'updateblogsettings' == $action && isset( $_POST['primary_blog'] ) ) {
	check_admin_referer( 'update-my-sites' );

	$blog = get_blog_details( (int) $_POST['primary_blog'] );
	if ( $blog && isset( $blog->domain ) ) {
		update_user_option( $current_user->ID, 'primary_blog', (int) $_POST['primary_blog'], true );
		$updated = true;
	} else {
		wp_die( __() );
	}
}

$title = __();
$parent_file = 'index.php';

get_current_screen()->add_help_tab( [
	'id'      => 'overview',
	'title'   => __(),
	'content' =>
		'<p>' . __() . '</p>' .
		'<p>' . __() . '</p>'
] );

get_current_screen()->set_help_sidebar(
	'<p><strong>' . __() . '</strong></p>' .
	'<p>' . __() . '</p>' .
	'<p>' . __() . '</p>'
);

require_once( ABSPATH . 'wp-admin/admin-header.php' );

if ( $updated ) { ?>
	<div id="message" class="updated"><p><strong><?php _e( 'Settings saved.' ); ?></strong></p></div>
<?php } ?>

<div class="wrap">
<h2><?php echo esc_html( $title ); ?></h2>
<?php
if ( empty( $blogs ) ) :
	echo '<p>';
	_e( 'You must be a member of at least one site to use this page.' );
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
	do_action( 'myblogs_allblogs_options' );
	?>
	<br clear="all" />
	<table class="widefat fixed">
	<?php
	/**
	 * Enable the Global Settings section on the My Sites screen.
	 *
	 * By default, the Global Settings section is hidden. Passing a non-empty
	 * string to this filter will enable the section, and allow new settings
	 * to be added, either globally or for specific sites.
	 *
	 * @since MU
	 *
	 * @param string $settings_html The settings HTML markup. Default empty.
	 * @param object $context       Context of the setting (global or site-specific). Default 'global'.
	 */
	$settings_html = apply_filters();
	if ( $settings_html != '' ) {
		echo '<tr><td><h3>' . __() . '</h3></td><td>';
		echo $settings_html;
		echo '</td></tr>';
	}
	reset( $blogs );
	$num = count( $blogs );
	$cols = 1;
	if ( $num >= 20 )
		$cols = 4;
	elseif ( $num >= 10 )
		$cols = 2;
	$num_rows = ceil( $num / $cols );
	$split = 0;
	for ( $i = 1; $i <= $num_rows; $i++ ) {
		$rows[] = array_slice( $blogs, $split, $cols );
		$split = $split + $cols;
	}

	$c = '';
	foreach ( $rows as $row ) {
		$c = $c == 'alternate' ? '' : 'alternate';
		echo "<tr class='$c'>";
		$i = 0;
		foreach ( $row as $user_blog ) {
			$s = $i == 3 ? '' : 'border-right: 1px solid #ccc;';
			echo "<td style='$s'>";
			echo "<h3>{$user_blog->blogname}</h3>";
			/**
			 * Filter the row links displayed for each site on the My Sites screen.
			 *
			 * @since MU
			 *
			 * @param string $string    The HTML site link markup.
			 * @param object $user_blog An object containing the site data.
			 */
			echo "<p>" . apply_filters() . "</p>";
			/** This filter is documented in wp-admin/my-sites.php */
			echo apply_filters();
			echo "</td>";
			$i++;
		}
		echo "</tr>";
	}?>
	</table>
	<input type="hidden" name="action" value="updateblogsettings" />
	<?php wp_nonce_field( 'update-my-sites' ); ?>
	<?php submit_button(); ?>
	</form>
<?php endif; ?>
	</div>
<?php
include( ABSPATH . 'wp-admin/admin-footer.php' );
