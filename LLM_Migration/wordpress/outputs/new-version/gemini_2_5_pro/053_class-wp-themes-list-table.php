<?php

use WP_Theme;

/**
 * Themes List Table class.
 *
 * @package WordPress
 * @subpackage List_Table
 * @since 3.1.0
 * @access private
 */
class WP_Themes_List_Table extends WP_List_Table {

	protected array $search_terms = [];
	public array $features = [];

	/**
	 * Constructor.
	 *
	 * @since 3.1.0
	 *
	 * @see WP_List_Table::__construct() for more information on default arguments.
	 *
	 * @param array<string, mixed> $args An associative array of arguments.
	 */
	public function __construct( array $args = [] ) {
		parent::__construct( [
			'ajax'   => true,
			'screen' => $args['screen'] ?? null,
		] );
	}

	public function ajax_user_can(): bool {
		// Do not check edit_theme_options here. AJAX calls for available themes require switch_themes.
		return current_user_can( 'switch_themes' );
	}

	public function prepare_items(): void {
		$themes = wp_get_themes( [ 'allowed' => true ] );

		$search_query = trim( wp_unslash( $_REQUEST['s'] ?? '' ) );
		if ( '' !== $search_query ) {
			$this->search_terms = array_unique( array_filter( array_map( 'trim', explode( ',', strtolower( $search_query ) ) ) ) );
		}

		if ( ! empty( $_REQUEST['features'] ) && is_array( $_REQUEST['features'] ) ) {
			$this->features = $_REQUEST['features'];
		}

		if ( $this->search_terms || $this->features ) {
			$themes = array_filter( $themes, fn( WP_Theme $theme ): bool => $this->search_theme( $theme ) );
		}

		unset( $themes[ get_option( 'stylesheet' ) ] );
		WP_Theme::sort_by_name( $themes );

		$per_page = 36;
		$page     = $this->get_pagenum();

		$start = ( $page - 1 ) * $per_page;

		$this->items = array_slice( $themes, $start, $per_page, true );

		$this->set_pagination_args( [
			'total_items'     => count( $themes ),
			'per_page'        => $per_page,
			'infinite_scroll' => true,
		] );
	}

	public function no_items(): void {
		if ( $this->search_terms || $this->features ) {
			_e( 'No items found.' );
			return;
		}

		if ( is_multisite() ) {
			if ( current_user_can( 'install_themes' ) && current_user_can( 'manage_network_themes' ) ) {
				printf(
					/* translators: 1: Link to Network Admin site-themes.php screen, 2: Link to Network Admin theme-install.php screen. */
					__( 'You only have one theme enabled for this site right now. Visit the Network Admin to <a href="%1$s">enable</a> or <a href="%2$s">install</a> more themes.' ),
					network_admin_url( 'site-themes.php?id=' . $GLOBALS['blog_id'] ),
					network_admin_url( 'theme-install.php' )
				);
				return;
			}

			if ( current_user_can( 'manage_network_themes' ) ) {
				printf(
					/* translators: %s: Link to Network Admin site-themes.php screen. */
					__( 'You only have one theme enabled for this site right now. Visit the Network Admin to <a href="%s">enable</a> more themes.' ),
					network_admin_url( 'site-themes.php?id=' . $GLOBALS['blog_id'] )
				);
				return;
			}
			// Else, fallthrough. install_themes doesn't help if you can't enable it.
		} else {
			if ( current_user_can( 'install_themes' ) ) {
				printf(
					/* translators: %s: Link to theme-install.php screen. */
					__( 'You only have one theme installed right now. Live a little! You can choose from over 1,000 free themes in the WordPress.org Theme Directory at any time: just click on the <a href="%s">Install Themes</a> tab above.' ),
					admin_url( 'theme-install.php' )
				);
				return;
			}
		}
		// Fallthrough.
		printf(
			/* translators: %s: Site name. */
			__( 'Only the current theme is available to you. Contact the %s administrator for information about accessing additional themes.' ),
			get_site_option( 'site_name' )
		);
	}

	public function tablenav( string $which = 'top' ): void {
		if ( $this->get_pagination_arg( 'total_pages' ) <= 1 ) {
			return;
		}
		?>
		<div class="tablenav themes <?php echo esc_attr( $which ); ?>">
			<?php $this->pagination( $which ); ?>
			<span class="spinner"></span>
			<br class="clear" />
		</div>
		<?php
	}

	public function display(): void {
		wp_nonce_field( 'fetch-list-' . self::class, '_ajax_fetch_list_nonce' );
		?>
		<?php $this->tablenav( 'top' ); ?>

		<div id="availablethemes">
			<?php $this->display_rows_or_placeholder(); ?>
		</div>

		<?php $this->tablenav( 'bottom' ); ?>
		<?php
	}

	public function get_columns(): array {
		return [];
	}

	public function display_rows_or_placeholder(): void {
		if ( $this->has_items() ) {
			$this->display_rows();
		} else {
			echo '<div class="no-items">';
			$this->no_items();
			echo '</div>';
		}
	}

	public function display_rows(): void {
		/** @var WP_Theme[] $themes */
		$themes = $this->items;

		foreach ( $themes as $theme ) :
			$template   = $theme->get_template();
			$stylesheet = $theme->get_stylesheet();
			$title      = $theme->display( 'Name' );
			$version    = $theme->display( 'Version' );
			$author     = $theme->display( 'Author' );

			$activate_link = wp_nonce_url( "themes.php?action=activate&amp;template=" . urlencode( $template ) . "&amp;stylesheet=" . urlencode( $stylesheet ), 'switch-theme_' . $stylesheet );
			$preview_link  = esc_url(
				add_query_arg(
					[
						'preview'        => 1,
						'template'       => urlencode( $template ),
						'stylesheet'     => urlencode( $stylesheet ),
						'preview_iframe' => true,
						'TB_iframe'      => 'true',
					],
					home_url( '/' )
				)
			);
			$customize_link = wp_customize_url( $stylesheet );

			$actions = [];
			$actions['activate'] = sprintf(
				'<a href="%s" class="activatelink" title="%s">%s</a>',
				$activate_link,
				esc_attr( sprintf( __( 'Activate &#8220;%s&#8221;' ), $title ) ),
				__( 'Activate' )
			);

			$actions['preview'] = sprintf(
				'<a href="%s" class="hide-if-customize" title="%s">%s</a>',
				$preview_link,
				esc_attr( sprintf( __( 'Preview &#8220;%s&#8221;' ), $title ) ),
				__( 'Preview' )
			);

			if ( current_user_can( 'edit_theme_options' ) && current_user_can( 'customize' ) ) {
				$actions['preview'] .= sprintf(
					'<a href="%s" class="load-customize hide-if-no-customize">%s</a>',
					$customize_link,
					__( 'Live Preview' )
				);
			}

			if ( ! is_multisite() && current_user_can( 'delete_themes' ) ) {
				$delete_url      = wp_nonce_url( 'themes.php?action=delete&amp;stylesheet=' . urlencode( $stylesheet ), 'delete-theme_' . $stylesheet );
				$confirm_message = esc_js( sprintf( __( "You are about to delete this theme '%s'\n  'Cancel' to stop, 'OK' to delete." ), $title ) );
				$actions['delete'] = sprintf(
					'<a class="submitdelete deletion" href="%s" onclick="return confirm(\'%s\');">%s</a>',
					$delete_url,
					$confirm_message,
					__( 'Delete' )
				);
			}

			/** This filter is documented in wp-admin/includes/class-wp-ms-themes-list-table.php */
			$actions = apply_filters( 'theme_action_links', $actions, $theme );

			/** This filter is documented in wp-admin/includes/class-wp-ms-themes-list-table.php */
			$actions = apply_filters( "theme_action_links_{$stylesheet}", $actions, $theme );

			$delete_action = $actions['delete'] ?? null;
			unset( $actions['delete'] );

			?>
			<div class="available-theme">
				<a href="<?php echo $preview_link; ?>" class="screenshot hide-if-customize">
					<?php if ( $screenshot = $theme->get_screenshot() ) : ?>
						<img src="<?php echo esc_url( $screenshot ); ?>" alt="" />
					<?php endif; ?>
				</a>
				<a href="<?php echo $customize_link; ?>" class="screenshot load-customize hide-if-no-customize">
					<?php if ( $screenshot = $theme->get_screenshot() ) : ?>
						<img src="<?php echo esc_url( $screenshot ); ?>" alt="" />
					<?php endif; ?>
				</a>

				<h3><?php echo $title; ?></h3>
				<div class="theme-author"><?php printf( __( 'By %s' ), $author ); ?></div>
				<div class="action-links">
					<ul>
						<?php foreach ( $actions as $action ) : ?>
							<li><?php echo $action; ?></li>
						<?php endforeach; ?>
						<li class="hide-if-no-js"><a href="#" class="theme-detail"><?php _e( 'Details' ); ?></a></li>
					</ul>
					<?php if ( $delete_action ) : ?>
						<div class="delete-theme"><?php echo $delete_action; ?></div>
					<?php endif; ?>

					<?php theme_update_available( $theme ); ?>
				</div>

				<div class="themedetaildiv hide-if-js">
					<p><strong><?php _e( 'Version: ' ); ?></strong><?php echo $version; ?></p>
					<p><?php echo $theme->display( 'Description' ); ?></p>
					<?php
					if ( $theme->parent() ) {
						printf(
							' <p class="howto">' . __( 'This <a href="%1$s">child theme</a> requires its parent theme, %2$s.' ) . '</p>',
							__( 'http://codex.wordpress.org/Child_Themes' ),
							$theme->parent()->display( 'Name' )
						);
					}
					?>
				</div>
			</div>
			<?php
		endforeach;
	}

	public function search_theme( WP_Theme $theme ): bool {
		// Search the features.
		foreach ( $this->features as $word ) {
			if ( ! in_array( $word, $theme->get( 'Tags' ), true ) ) {
				return false;
			}
		}

		// Match all phrases.
		foreach ( $this->search_terms as $word ) {
			if ( in_array( $word, $theme->get( 'Tags' ), true ) ) {
				continue;
			}

			foreach ( [ 'Name', 'Description', 'Author', 'AuthorURI' ] as $header ) {
				// Don't mark up; Do translate.
				if ( false !== stripos( strip_tags( $theme->display( $header, false, true ) ), $word ) ) {
					continue 2;
				}
			}

			if ( false !== stripos( $theme->get_stylesheet(), $word ) ) {
				continue;
			}

			if ( false !== stripos( $theme->get_template(), $word ) ) {
				continue;
			}

			return false;
		}

		return true;
	}

	/**
	 * Send required variables to JavaScript land.
	 *
	 * @since 3.4.0
	 *
	 * @param array<string, mixed> $extra_args
	 */
	public function _js_vars( array $extra_args = [] ): void {
		$search_string = esc_attr( wp_unslash( $_REQUEST['s'] ?? '' ) );

		$args = [
			'search'      => $search_string,
			'features'    => $this->features,
			'paged'       => $this->get_pagenum(),
			'total_pages' => $this->_pagination_args['total_pages'] ?? 1,
		];

		$args = array_merge( $args, $extra_args );

		printf( "<script type='text/javascript'>var theme_list_args = %s;</script>\n", json_encode( $args ) );
		parent::_js_vars();
	}
}