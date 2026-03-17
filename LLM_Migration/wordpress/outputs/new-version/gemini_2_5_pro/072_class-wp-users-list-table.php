<?php
/**
 * Users List Table class.
 *
 * @since 3.1.0
 * @access private
 *
 * @package WordPress
 * @subpackage List_Table
 */
class WP_Users_List_Table extends WP_List_Table {

	/**
	 * Site ID to generate the Users list table for.
	 *
	 * @since 3.1.0
	 */
	public readonly ?int $site_id;

	/**
	 * Whether or not the current Users list table is for Multisite.
	 *
	 * @since 3.1.0
	 */
	public readonly bool $is_site_users;

	/**
	 * Constructor.
	 *
	 * @since 3.1.0
	 * @access public
	 *
	 * @see WP_List_Table::__construct() for more information on default arguments.
	 *
	 * @param array $args An associative array of arguments.
	 */
	public function __construct( array $args = [] ) {
		parent::__construct( [
			'singular' => 'user',
			'plural'   => 'users',
			'screen'   => $args['screen'] ?? null,
		] );

		$this->is_site_users = 'site-users-network' === $this->screen->id;

		if ( $this->is_site_users ) {
			$this->site_id = (int) ( $_REQUEST['id'] ?? 0 );
		} else {
			$this->site_id = null;
		}
	}

	/**
	 * Check the current user's permissions.
	 *
 	 * @since 3.1.0
	 * @access public
	 */
	public function ajax_user_can(): bool {
		return $this->is_site_users
			? current_user_can( 'manage_sites' )
			: current_user_can( 'list_users' );
	}

	/**
	 * Prepare the users list for display.
	 *
	 * @since 3.1.0
	 * @access public
	 */
	public function prepare_items(): void {
		global $role, $usersearch;

		$usersearch     = wp_unslash( trim( $_REQUEST['s'] ?? '' ) );
		$role           = $_REQUEST['role'] ?? '';
		$per_page       = $this->is_site_users ? 'site_users_network_per_page' : 'users_per_page';
		$users_per_page = $this->get_items_per_page( $per_page );
		$paged          = $this->get_pagenum();

		$args = [
			'number'  => $users_per_page,
			'offset'  => ( $paged - 1 ) * $users_per_page,
			'role'    => $role,
			'search'  => $usersearch,
			'fields'  => 'all_with_meta',
			'orderby' => $_REQUEST['orderby'] ?? null,
			'order'   => $_REQUEST['order'] ?? null,
		];

		if ( '' !== $args['search'] ) {
			$args['search'] = "*{$args['search']}*";
		}

		if ( $this->is_site_users ) {
			$args['blog_id'] = $this->site_id;
		}

		// Query the user IDs for this page.
		$wp_user_search = new WP_User_Query( $args );

		$this->items = $wp_user_search->get_results();

		$this->set_pagination_args( [
			'total_items' => $wp_user_search->get_total(),
			'per_page'    => $users_per_page,
		] );
	}

	/**
	 * Output 'no users' message.
	 *
	 * @since 3.1.0
	 * @access public
	 */
	public function no_items(): void {
		_e( 'No matching users were found.' );
	}

	/**
	 * Return an associative array listing all the views that can be used
	 * with this table.
	 *
	 * Provides a list of roles and user count for that role for easy
	 * filtering of the user table.
	 *
	 * @since  3.1.0
	 * @access protected
	 *
	 * @return array An array of HTML links, one for each view.
	 */
	protected function get_views(): array {
		global $wp_roles, $role;

		if ( $this->is_site_users ) {
			$url = "site-users.php?id={$this->site_id}";
			switch_to_blog( $this->site_id );
			$users_of_blog = count_users();
			restore_current_blog();
		} else {
			$url           = 'users.php';
			$users_of_blog = count_users();
		}
		$total_users = $users_of_blog['total_users'];
		$avail_roles = $users_of_blog['avail_roles'];
		unset( $users_of_blog );

		$class      = empty( $role ) ? ' class="current"' : '';
		$role_links = [];

		$role_links['all'] = sprintf(
			'<a href="%s"%s>%s</a>',
			$url,
			$class,
			sprintf(
				_nx( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $total_users, 'users' ),
				number_format_i18n( $total_users )
			)
		);

		foreach ( $wp_roles->get_names() as $this_role => $name ) {
			if ( ! isset( $avail_roles[ $this_role ] ) ) {
				continue;
			}

			$class = ( $this_role === $role ) ? ' class="current"' : '';

			$name = translate_user_role( $name );
			/* translators: User role name with count */
			$name = sprintf( __( '%1$s <span class="count">(%2$s)</span>' ), $name, number_format_i18n( $avail_roles[ $this_role ] ) );

			$role_links[ $this_role ] = sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url( add_query_arg( 'role', $this_role, $url ) ),
				$class,
				$name
			);
		}

		return $role_links;
	}

	/**
	 * Retrieve an associative array of bulk actions available on this table.
	 *
	 * @since  3.1.0
	 * @access protected
	 *
	 * @return array Array of bulk actions.
	 */
	protected function get_bulk_actions(): array {
		$actions = [];

		if ( is_multisite() ) {
			if ( current_user_can( 'remove_users' ) ) {
				$actions['remove'] = __( 'Remove' );
			}
		} elseif ( current_user_can( 'delete_users' ) ) {
			$actions['delete'] = __( 'Delete' );
		}

		return $actions;
	}

	/**
	 * Output the controls to allow user roles to be changed in bulk.
	 *
	 * @since 3.1.0
	 * @access protected
	 *
	 * @param string $which Whether this is being invoked above ("top")
	 *                      or below the table ("bottom").
	 */
	protected function extra_tablenav( string $which ): void {
		if ( 'top' !== $which ) {
			return;
		}
		?>
	<div class="alignleft actions">
		<?php if ( current_user_can( 'promote_users' ) ) : ?>
		<label class="screen-reader-text" for="new_role"><?php _e( 'Change role to&hellip;' ); ?></label>
		<select name="new_role" id="new_role">
			<option value=""><?php _e( 'Change role to&hellip;' ); ?></option>
			<?php wp_dropdown_roles(); ?>
		</select>
			<?php
			submit_button( __( 'Change' ), 'button', 'changeit', false );
		endif;

		/**
		 * Fires just before the closing div containing the bulk role-change controls
		 * in the Users list table.
		 *
		 * @since 3.5.0
		 */
		do_action( 'restrict_manage_users' );
		?>
	</div>
		<?php
	}

	/**
	 * Capture the bulk action required, and return it.
	 *
	 * Overridden from the base class implementation to capture
	 * the role change drop-down.
	 *
	 * @since  3.1.0
	 * @access public
	 *
	 * @return string|false The bulk action required.
	 */
	public function current_action(): string|false {
		if ( isset( $_REQUEST['changeit'] ) && ! empty( $_REQUEST['new_role'] ) ) {
			return 'promote';
		}

		return parent::current_action();
	}

	/**
	 * Get a list of columns for the list table.
	 *
	 * @since  3.1.0
	 * @access public
	 *
	 * @return array Array in which the key is the ID of the column,
	 *               and the value is the description.
	 */
	public function get_columns(): array {
		$columns = [
			'cb'       => '<input type="checkbox" />',
			'username' => __( 'Username' ),
			'name'     => __( 'Name' ),
			'email'    => __( 'E-mail' ),
			'role'     => __( 'Role' ),
			'posts'    => __( 'Posts' ),
		];

		if ( $this->is_site_users ) {
			unset( $columns['posts'] );
		}

		return $columns;
	}

	/**
	 * Get a list of sortable columns for the list table.
	 *
	 * @since 3.1.0
	 * @access protected
	 *
	 * @return array Array of sortable columns.
	 */
	protected function get_sortable_columns(): array {
		$columns = [
			'username' => 'login',
			'name'     => 'name',
			'email'    => 'email',
		];

		if ( $this->is_site_users ) {
			unset( $columns['posts'] );
		}

		return $columns;
	}

	/**
	 * Generate the list table rows.
	 *
	 * @since 3.1.0
	 * @access public
	 */
	public function display_rows(): void {
		// Query the post counts for this page.
		$post_counts = [];
		if ( ! $this->is_site_users ) {
			$post_counts = count_many_users_posts( array_keys( $this->items ) );
		}

		$editable_roles = array_keys( get_editable_roles() );

		$style = '';
		foreach ( $this->items as $userid => $user_object ) {
			if ( count( $user_object->roles ) <= 1 ) {
				$role = reset( $user_object->roles );
			} elseif ( $roles = array_intersect( array_values( $user_object->roles ), $editable_roles ) ) {
				$role = reset( $roles );
			} else {
				$role = reset( $user_object->roles );
			}

			if ( is_multisite() && empty( $user_object->allcaps ) ) {
				continue;
			}

			$style = ( '' === $style ) ? ' class="alternate"' : '';
			echo "\n\t" . $this->single_row( $user_object, $style, (string) $role, $post_counts[ $userid ] ?? 0 );
		}
	}

	/**
	 * Generate HTML for a single row on the users.php admin panel.
	 *
	 * @since 3.1.0
	 * @access public
	 *
	 * @param WP_User|object|int $user_object The current user object or ID.
	 * @param string             $style       Optional. Style attributes added to the <tr> element.
	 *                                        Must be sanitized. Default empty.
	 * @param string             $role        Optional. Key for the $wp_roles array. Default empty.
	 * @param int                $numposts    Optional. Post count to display for this user. Defaults
	 *                                        to zero, as in, a new user has made zero posts.
	 * @return string Output for a single row.
	 */
	public function single_row( object|int $user_object, string $style = '', string $role = '', int $numposts = 0 ): string {
		global $wp_roles;

		if ( ! $user_object instanceof WP_User ) {
			$user_object = get_userdata( (int) $user_object );
			if ( ! $user_object ) {
				return ''; // User not found, return empty row.
			}
		}
		$user_object->filter = 'display';
		$email               = $user_object->user_email;

		$url = $this->is_site_users
			? "site-users.php?id={$this->site_id}&amp;"
			: 'users.php?';

		$checkbox = '';
		$edit     = '';

		// Check if the user for this row is editable.
		if ( current_user_can( 'list_users' ) ) {
			// Set up the user editing link.
			$edit_link = esc_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), get_edit_user_link( $user_object->ID ) ) );

			// Set up the hover actions for this user.
			$actions = [];

			if ( current_user_can( 'edit_user', $user_object->ID ) ) {
				$edit           = sprintf( '<strong><a href="%s">%s</a></strong><br />', $edit_link, $user_object->user_login );
				$actions['edit'] = sprintf( '<a href="%s">%s</a>', $edit_link, __( 'Edit' ) );
			} else {
				$edit = "<strong>{$user_object->user_login}</strong><br />";
			}

			if ( ! is_multisite() && get_current_user_id() !== $user_object->ID && current_user_can( 'delete_user', $user_object->ID ) ) {
				$actions['delete'] = sprintf(
					'<a class="submitdelete" href="%s">%s</a>',
					wp_nonce_url( "users.php?action=delete&amp;user={$user_object->ID}", 'bulk-users' ),
					__( 'Delete' )
				);
			}
			if ( is_multisite() && get_current_user_id() !== $user_object->ID && current_user_can( 'remove_user', $user_object->ID ) ) {
				$actions['remove'] = sprintf(
					'<a class="submitdelete" href="%s">%s</a>',
					wp_nonce_url( "{$url}action=remove&amp;user={$user_object->ID}", 'bulk-users' ),
					__( 'Remove' )
				);
			}

			/** This filter is documented in wp-admin/includes/class-wp-users-list-table.php */
			$actions = apply_filters( 'user_row_actions', $actions, $user_object );
			$edit   .= $this->row_actions( $actions );

			// Set up the checkbox.
			$checkbox = sprintf(
				'<label class="screen-reader-text" for="cb-select-%1$d">%2$s</label><input type="checkbox" name="users[]" id="user_%1$d" class="%3$s" value="%1$d" />',
				$user_object->ID,
				sprintf( __( 'Select %s' ), $user_object->user_login ),
				$role
			);
		} else {
			$edit = "<strong>{$user_object->user_login}</strong>";
		}

		$role_name = $wp_roles->role_names[ $role ] ?? __( 'None' );
		$role_name = translate_user_role( $role_name );
		$avatar    = get_avatar( $user_object->ID, 32 );

		$row_cells = [];
		[ $columns, $hidden ] = $this->get_column_info();

		foreach ( $columns as $column_name => $column_display_name ) {
			$css_class  = "class=\"{$column_name} column-{$column_name}\"";
			$css_style  = in_array( $column_name, $hidden, true ) ? ' style="display:none;"' : '';
			$attributes = $css_class . $css_style;

			switch ( $column_name ) {
				case 'cb':
					$row_cells[] = "<th scope='row' class='check-column'>{$checkbox}</th>";
					break;
				case 'username':
					$row_cells[] = "<td {$attributes}>{$avatar} {$edit}</td>";
					break;
				case 'name':
					$row_cells[] = "<td {$attributes}>{$user_object->first_name} {$user_object->last_name}</td>";
					break;
				case 'email':
					$row_cells[] = sprintf(
						'<td %s><a href="mailto:%s" title="%s">%s</a></td>',
						$attributes,
						$email,
						esc_attr( sprintf( __( 'E-mail: %s' ), $email ) ),
						$email
					);
					break;
				case 'role':
					$row_cells[] = "<td {$attributes}>{$role_name}</td>";
					break;
				case 'posts':
					$posts_attributes = 'class="posts column-posts num"' . $css_style;
					if ( $numposts > 0 ) {
						$posts_link  = sprintf(
							'<a href="edit.php?author=%d" title="%s" class="edit">%s</a>',
							$user_object->ID,
							esc_attr__( 'View posts by this author' ),
							$numposts
						);
						$row_cells[] = "<td {$posts_attributes}>{$posts_link}</td>";
					} else {
						$row_cells[] = "<td {$posts_attributes}>0</td>";
					}
					break;
				default:
					/** This filter is documented in wp-admin/includes/class-wp-users-list-table.php */
					$custom_column_output = apply_filters( 'manage_users_custom_column', '', $column_name, $user_object->ID );
					$row_cells[]          = "<td {$attributes}>{$custom_column_output}</td>";
			}
		}

		return "<tr id='user-{$user_object->ID}'{$style}>" . implode( '', $row_cells ) . '</tr>';
	}
}