<?php
/**
 * Sites List Table class.
 *
 * @package WordPress
 * @subpackage List_Table
 * @since 3.1.0
 * @access private
 */
class WP_MS_Sites_List_Table extends WP_List_Table {

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
	public function __construct( $args = [] ) {
		parent::__construct(
			[
				'plural' => 'sites',
				'screen' => $args['screen'] ?? null,
			]
		);
	}

	public function ajax_user_can() {
		return current_user_can( 'manage_sites' );
	}

	public function prepare_items() {
		global $s, $mode, $wpdb;

		$current_site = get_current_site();

		$mode     = empty( $_REQUEST['mode'] ) ? 'list' : $_REQUEST['mode']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page = (int) $this->get_items_per_page( 'sites_network_per_page' );
		$pagenum  = $this->get_pagenum();

		$s = isset( $_REQUEST['s'] ) ? wp_unslash( trim( (string) $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$wild = '';

		if ( str_contains( $s, '*' ) ) {
			$wild = '%';
			$s    = trim( $s, '*' );
		}

		/*
		 * If the network is large and a search is not being performed, show only
		 * the latest blogs with no paging in order to avoid expensive count queries.
		 */
		if ( $s === '' && wp_is_large_network() ) {
			if ( ! isset( $_REQUEST['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$_GET['orderby']    = ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$_REQUEST['orderby'] = ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
			if ( ! isset( $_REQUEST['order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$_GET['order']    = 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$_REQUEST['order'] = 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}

		$query = "SELECT * FROM {$wpdb->blogs} WHERE site_id = '{$wpdb->siteid}' ";

		if ( $s !== '' ) {
			if (
				preg_match( '/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $s )
				|| preg_match( '/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.?$/', $s )
				|| preg_match( '/^[0-9]{1,3}\.[0-9]{1,3}\.?$/', $s )
				|| preg_match( '/^[0-9]{1,3}\.$/', $s )
			) {
				$sql           = $wpdb->prepare(
					"SELECT blog_id FROM {$wpdb->registration_log} WHERE {$wpdb->registration_log}.IP LIKE %s",
					$wpdb->esc_like( $s ) . $wild
				);
				$reg_blog_ids = $wpdb->get_col( $sql );

				if ( ! $reg_blog_ids ) {
					$reg_blog_ids = [ 0 ];
				}

				$query = "SELECT *
				FROM {$wpdb->blogs}
				WHERE site_id = '{$wpdb->siteid}'
				AND {$wpdb->blogs}.blog_id IN (" . implode( ', ', array_map( 'absint', $reg_blog_ids ) ) . ')';
			} elseif ( is_numeric( $s ) && $wild === '' ) {
				$query .= $wpdb->prepare( " AND ( {$wpdb->blogs}.blog_id = %s )", $s );
			} elseif ( is_subdomain_install() ) {
				$blog_s = str_replace( '.' . $current_site->domain, '', $s );
				$blog_s = $wpdb->esc_like( $blog_s ) . $wild . $wpdb->esc_like( '.' . $current_site->domain );
				$query .= $wpdb->prepare( " AND ( {$wpdb->blogs}.domain LIKE %s ) ", $blog_s );
			} else {
				if ( $s !== trim( '/', $current_site->path ) ) {
					$blog_s = $wpdb->esc_like( $current_site->path . $s ) . $wild . $wpdb->esc_like( '/' );
				} else {
					$blog_s = $wpdb->esc_like( $s );
				}
				$query .= $wpdb->prepare( " AND  ( {$wpdb->blogs}.path LIKE %s )", $blog_s );
			}
		}

		$order_by = isset( $_REQUEST['orderby'] ) ? (string) $_REQUEST['orderby'] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		switch ( $order_by ) {
			case 'registered':
				$query .= ' ORDER BY registered ';
				break;
			case 'lastupdated':
				$query .= ' ORDER BY last_updated ';
				break;
			case 'blogname':
				$query .= is_subdomain_install() ? ' ORDER BY domain ' : ' ORDER BY path ';
				break;
			case 'blog_id':
				$query .= ' ORDER BY blog_id ';
				break;
			default:
				$order_by = null;
				break;
		}

		if ( null !== $order_by ) {
			$order   = isset( $_REQUEST['order'] ) ? strtoupper( (string) $_REQUEST['order'] ) : 'ASC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order   = 'DESC' === $order ? 'DESC' : 'ASC';
			$query  .= $order;
		}

		$total = 0;

		// Don't do an unbounded count on large networks.
		if ( ! wp_is_large_network() ) {
			$total = (int) $wpdb->get_var( str_replace( 'SELECT *', 'SELECT COUNT( blog_id )', $query ) );
		}

		$query        .= sprintf( ' LIMIT %d, %d', ( ( $pagenum - 1 ) * $per_page ), $per_page );
		$this->items   = $wpdb->get_results( $query, ARRAY_A );

		if ( wp_is_large_network() ) {
			$total = count( $this->items );
		}

		$this->set_pagination_args(
			[
				'total_items' => $total,
				'per_page'    => $per_page,
			]
		);
	}

	public function no_items() {
		esc_html_e( 'No sites found.' );
	}

	protected function get_bulk_actions() {
		$actions = [];

		if ( current_user_can( 'delete_sites' ) ) {
			$actions['delete'] = __( 'Delete' );
		}

		$actions['spam']    = _x( 'Mark as Spam', 'site' );
		$actions['notspam'] = _x( 'Not Spam', 'site' );

		return $actions;
	}

	protected function pagination( $which ) {
		global $mode;

		parent::pagination( $which );

		if ( 'top' === $which ) {
			$this->view_switcher( $mode );
		}
	}

	public function get_columns() {
		$blogname_columns = is_subdomain_install() ? __( 'Domain' ) : __( 'Path' );
		$sites_columns    = [
			'cb'          => '<input type="checkbox" />',
			'blogname'    => $blogname_columns,
			'lastupdated' => __( 'Last Updated' ),
			'registered'  => _x( 'Registered', 'site' ),
			'users'       => __( 'Users' ),
		];

		if ( has_filter( 'wpmublogsaction' ) ) {
			$sites_columns['plugins'] = __( 'Actions' );
		}

		/**
		 * Filter the displayed site columns in Sites list table.
		 *
		 * @since MU
		 *
		 * @param array $sites_columns An array of displayed site columns. Default 'cb',
		 *                             'blogname', 'lastupdated', 'registered', 'users'.
		 */
		return apply_filters( 'wpmu_blogs_columns', $sites_columns );
	}

	protected function get_sortable_columns() {
		return [
			'blogname'    => 'blogname',
			'lastupdated' => 'lastupdated',
			'registered'  => 'blog_id',
		];
	}

	public function display_rows() {
		global $mode;

		$status_list = [
			'archived' => [ 'site-archived', __( 'Archived' ) ],
			'spam'     => [ 'site-spammed', _x( 'Spam', 'site' ) ],
			'deleted'  => [ 'site-deleted', __( 'Deleted' ) ],
			'mature'   => [ 'site-mature', __( 'Mature' ) ],
		];

		$date = 'list' === $mode ? 'Y/m/d' : 'Y/m/d \<\b\r \/\> g:i:s a';

		$class = '';

		$current_site = get_current_site();

		foreach ( $this->items as $blog ) {
			$class = 'alternate' === $class ? '' : 'alternate';

			$blog_states = [];

			foreach ( $status_list as $status => $col ) {
				if ( '1' === (string) get_blog_status( $blog['blog_id'], $status ) ) {
					$class         = $col[0];
					$blog_states[] = $col[1];
				}
			}

			$blog_state = '';

			if ( ! empty( $blog_states ) ) {
				$blog_state = ' - ' . implode(
					', ',
					array_map(
						static fn ( $state ) => "<span class='post-state'>{$state}</span>",
						$blog_states
					)
				);
			}

			$blogname = is_subdomain_install()
				? str_replace( '.' . $current_site->domain, '', $blog['domain'] )
				: $blog['path'];

			printf( "<tr class='%s'>", esc_attr( $class ) );

			[ $columns, $hidden ] = $this->get_column_info();

			foreach ( $columns as $column_name => $column_display_name ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
				switch ( $column_name ) {
					case 'cb':
						echo '<th scope="row" class="check-column">';
						if ( ! is_main_site( (int) $blog['blog_id'] ) ) {
							printf(
								'<label class="screen-reader-text" for="blog_%1$d">%2$s</label>',
								(int) $blog['blog_id'],
								esc_html( sprintf( __( 'Select %s' ), $blogname ) )
							);
							printf(
								'<input type="checkbox" id="blog_%1$d" name="allblogs[]" value="%2$s" />',
								(int) $blog['blog_id'],
								esc_attr( $blog['blog_id'] )
							);
						}
						echo '</th>';
						break;

					case 'id':
						printf(
							'<th scope="row">%s</th>',
							esc_html( (string) $blog['blog_id'] )
						);
						break;

					case 'blogname':
						$hidden_style = in_array( $column_name, $hidden, true ) ? ' style="display:none;"' : '';
						printf(
							'<td class="%1$s column-%1$s"%2$s>',
							esc_attr( $column_name ),
							$hidden_style
						);

						printf(
							'<a href="%1$s" class="edit">%2$s%3$s</a>',
							esc_url( network_admin_url( 'site-info.php?id=' . $blog['blog_id'] ) ),
							esc_html( $blogname ),
							$blog_state // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						);

						if ( 'list' !== $mode ) {
							switch_to_blog( (int) $blog['blog_id'] );
							printf(
								'<p>%s</p>',
								sprintf(
									/* translators: 1: Site name. 2: Site tagline. */
									_x( '%1$s &#8211; <em>%2$s</em>', '%1$s: site name. %2$s: site tagline.' ),
									esc_html( get_option( 'blogname' ) ),
									esc_html( get_option( 'blogdescription' ) )
								)
							);
							restore_current_blog();
						}

						$actions = array_fill_keys(
							[
								'edit',
								'backend',
								'activate',
								'deactivate',
								'archive',
								'unarchive',
								'spam',
								'unspam',
								'delete',
								'visit',
							],
							''
						);

						$actions['edit']    = sprintf(
							'<span class="edit"><a href="%1$s">%2$s</a></span>',
							esc_url( network_admin_url( 'site-info.php?id=' . $blog['blog_id'] ) ),
							__( 'Edit' )
						);
						$actions['backend'] = sprintf(
							"<span class='backend'><a href='%1\$s' class='edit'>%2\$s</a></span>",
							esc_url( get_admin_url( $blog['blog_id'] ) ),
							__( 'Dashboard' )
						);

						$confirm_url = static function ( string $action, int $blog_id, string $message ): string {
							return wp_nonce_url(
								network_admin_url(
									sprintf(
										'sites.php?action=confirm&action2=%1$s&id=%2$d&msg=%3$s',
										$action,
										$blog_id,
										rawurlencode( $message )
									)
								),
								'confirm'
							);
						};

						if ( (int) get_current_site()->blog_id !== (int) $blog['blog_id'] ) {
							if ( '1' === (string) get_blog_status( $blog['blog_id'], 'deleted' ) ) {
								$actions['activate'] = sprintf(
									'<span class="activate"><a href="%1$s">%2$s</a></span>',
									esc_url(
										$confirm_url(
											'activateblog',
											(int) $blog['blog_id'],
											sprintf( __( 'You are about to activate the site %s' ), $blogname )
										)
									),
									__( 'Activate' )
								);
							} else {
								$actions['deactivate'] = sprintf(
									'<span class="activate"><a href="%1$s">%2$s</a></span>',
									esc_url(
										$confirm_url(
											'deactivateblog',
											(int) $blog['blog_id'],
											sprintf( __( 'You are about to deactivate the site %s' ), $blogname )
										)
									),
									__( 'Deactivate' )
								);
							}

							if ( '1' === (string) get_blog_status( $blog['blog_id'], 'archived' ) ) {
								$actions['unarchive'] = sprintf(
									'<span class="archive"><a href="%1$s">%2$s</a></span>',
									esc_url(
										$confirm_url(
											'unarchiveblog',
											(int) $blog['blog_id'],
											sprintf( __( 'You are about to unarchive the site %s.' ), $blogname )
										)
									),
									__( 'Unarchive' )
								);
							} else {
								$actions['archive'] = sprintf(
									'<span class="archive"><a href="%1$s">%2$s</a></span>',
									esc_url(
										$confirm_url(
											'archiveblog',
											(int) $blog['blog_id'],
											sprintf( __( 'You are about to archive the site %s.' ), $blogname )
										)
									),
									_x( 'Archive', 'verb; site' )
								);
							}

							if ( '1' === (string) get_blog_status( $blog['blog_id'], 'spam' ) ) {
								$actions['unspam'] = sprintf(
									'<span class="spam"><a href="%1$s">%2$s</a></span>',
									esc_url(
										$confirm_url(
											'unspamblog',
											(int) $blog['blog_id'],
											sprintf( __( 'You are about to unspam the site %s.' ), $blogname )
										)
									),
									_x( 'Not Spam', 'site' )
								);
							} else {
								$actions['spam'] = sprintf(
									'<span class="spam"><a href="%1$s">%2$s</a></span>',
									esc_url(
										$confirm_url(
											'spamblog',
											(int) $blog['blog_id'],
											sprintf( __( 'You are about to mark the site %s as spam.' ), $blogname )
										)
									),
									_x( 'Spam', 'site' )
								);
							}

							if ( current_user_can( 'delete_site', $blog['blog_id'] ) ) {
								$actions['delete'] = sprintf(
									'<span class="delete"><a href="%1$s">%2$s</a></span>',
									esc_url(
										$confirm_url(
											'deleteblog',
											(int) $blog['blog_id'],
											sprintf( __( 'You are about to delete the site %s.' ), $blogname )
										)
									),
									__( 'Delete' )
								);
							}
						}

						$actions['visit'] = sprintf(
							"<span class='view'><a href='%1\$s' rel='permalink'>%2\$s</a></span>",
							esc_url( get_home_url( $blog['blog_id'], '/' ) ),
							__( 'Visit' )
						);

						/**
						 * Filter the action links displayed for each site in the Sites list table.
						 *
						 * The 'Edit', 'Dashboard', 'Delete', and 'Visit' links are displayed by
						 * default for each site. The site's status determines whether to show the
						 * 'Activate' or 'Deactivate' link, 'Unarchive' or 'Archive' links, and
						 * 'Not Spam' or 'Spam' link for each site.
						 *
						 * @since 3.1.0
						 *
						 * @param array  $actions  An array of action links to be displayed.
						 * @param int    $blog_id  The site ID.
						 * @param string $blogname Site path, formatted depending on whether it is a sub-domain
						 *                         or subdirectory multisite install.
						 */
						$actions = apply_filters( 'manage_sites_action_links', array_filter( $actions ), $blog['blog_id'], $blogname );

						echo $this->row_actions( $actions ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo '</td>';
						break;

					case 'lastupdated':
						$hidden_style = in_array( $column_name, $hidden, true ) ? ' style="display:none;"' : '';
						printf(
							'<td class="%1$s column-%1$s"%2$s>',
							esc_attr( $column_name ),
							$hidden_style
						);
						echo '0000-00-00 00:00:00' === $blog['last_updated']
							? esc_html__( 'Never' )
							: esc_html( mysql2date( $date, $blog['last_updated'] ) );
						echo '</td>';
						break;

					case 'registered':
						$hidden_style = in_array( $column_name, $hidden, true ) ? ' style="display:none;"' : '';
						printf(
							'<td class="%1$s column-%1$s"%2$s>',
							esc_attr( $column_name ),
							$hidden_style
						);
						if ( '0000-00-00 00:00:00' === $blog['registered'] ) {
							echo '&#x2014;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						} else {
							echo esc_html( mysql2date( $date, $blog['registered'] ) );
						}
						echo '</td>';
						break;

					case 'users':
						$hidden_style = in_array( $column_name, $hidden, true ) ? ' style="display:none;"' : '';
						printf(
							'<td class="%1$s column-%1$s"%2$s>',
							esc_attr( $column_name ),
							$hidden_style
						);

						$blogusers = get_users(
							[
								'blog_id' => $blog['blog_id'],
								'number'  => 6,
							]
						);

						if ( is_array( $blogusers ) && ! empty( $blogusers ) ) {
							$blogusers_warning = '';

							if ( count( $blogusers ) > 5 ) {
								$blogusers          = array_slice( $blogusers, 0, 5 );
								$blogusers_warning  = sprintf(
									'%1$s <a href="%2$s">%3$s</a>',
									esc_html__( 'Only showing first 5 users.' ),
									esc_url( network_admin_url( 'site-users.php?id=' . $blog['blog_id'] ) ),
									esc_html__( 'More' )
								);
							}

							foreach ( $blogusers as $user_object ) {
								printf(
									'<a href="%1$s">%2$s</a>',
									esc_url( network_admin_url( 'user-edit.php?user_id=' . $user_object->ID ) ),
									esc_html( $user_object->user_login )
								);

								if ( 'list' !== $mode ) {
									printf(
										' (%s)',
										esc_html( $user_object->user_email )
									);
								}

								echo '<br />';
							}

							if ( '' !== $blogusers_warning ) {
								printf(
									'<strong>%s</strong><br />',
									$blogusers_warning // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								);
							}
						}

						echo '</td>';
						break;

					case 'plugins':
						if ( has_filter( 'wpmublogsaction' ) ) {
							$hidden_style = in_array( $column_name, $hidden, true ) ? ' style="display:none;"' : '';
							printf(
								'<td class="%1$s column-%1$s"%2$s>',
								esc_attr( $column_name ),
								$hidden_style
							);

							/**
							 * Fires inside the auxiliary 'Actions' column of the Sites list table.
							 *
							 * By default this column is hidden unless something is hooked to the action.
							 *
							 * @since MU
							 *
							 * @param int $blog_id The site ID.
							 */
							do_action( 'wpmublogsaction', $blog['blog_id'] );
							echo '</td>';
						}
						break;

					default:
						$hidden_style = in_array( $column_name, $hidden, true ) ? ' style="display:none;"' : '';
						printf(
							'<td class="%1$s column-%1$s"%2$s>',
							esc_attr( $column_name ),
							$hidden_style
						);

						/**
						 * Fires for each registered custom column in the Sites list table.
						 *
						 * @since 3.1.0
						 *
						 * @param string $column_name The name of the column to display.
						 * @param int    $blog_id     The site ID.
						 */
						do_action( 'manage_sites_custom_column', $column_name, $blog['blog_id'] );
						echo '</td>';
						break;
				}
			}
			echo '</tr>';
		}
	}
}
?>