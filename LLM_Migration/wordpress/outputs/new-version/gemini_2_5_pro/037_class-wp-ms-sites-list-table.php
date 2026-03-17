<?php

declare(strict_types=1);

/**
 * Sites List Table class.
 *
 * @package WordPress
 * @subpackage List_Table
 * @since 3.1.0
 * @access private
 */
class WP_MS_Sites_List_Table extends WP_List_Table
{
	private string $s = '';
	private string $mode = 'list';

	/**
	 * Constructor.
	 *
	 * @since 3.1.0
	 *
	 * @see WP_List_Table::__construct() for more information on default arguments.
	 *
	 * @param array<string, mixed> $args An associative array of arguments.
	 */
	public function __construct(array $args = [])
	{
		parent::__construct([
			'plural' => 'sites',
			'screen' => $args['screen'] ?? null,
		]);
	}

	public function ajax_user_can(): bool
	{
		return current_user_can('manage_sites');
	}

	public function prepare_items(): void
	{
		global $wpdb;

		$current_site = get_current_site();
		$per_page = $this->get_items_per_page('sites_network_per_page');
		$pagenum = $this->get_pagenum();

		$this->mode = $_REQUEST['mode'] ?? 'list';
		$this->s = wp_unslash(trim($_REQUEST['s'] ?? ''));

		$wild = '';
		if (str_contains($this->s, '*')) {
			$wild = '%';
			$this->s = trim($this->s, '*');
		}

		/*
		 * If the network is large and a search is not being performed, show only
		 * the latest blogs with no paging in order to avoid expensive count queries.
		 */
		if ('' === $this->s && wp_is_large_network()) {
			if (! isset($_REQUEST['orderby'])) {
				$_GET['orderby'] = $_REQUEST['orderby'] = '';
			}
			if (! isset($_REQUEST['order'])) {
				$_GET['order'] = $_REQUEST['order'] = 'DESC';
			}
		}

		$where_clauses = ['site_id = %d'];
		$params = [(int) $wpdb->siteid];

		if ('' !== $this->s) {
			$ip_pattern = '/(?:^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$)|(?:^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.?$)|(?:^[0-9]{1,3}\.[0-9]{1,3}\.?$)|(?:^[0-9]{1,3}\.$)/';
			$is_ip_address = preg_match($ip_pattern, $this->s);

			if ($is_ip_address) {
				$sql = $wpdb->prepare(
					"SELECT blog_id FROM {$wpdb->registration_log} WHERE IP LIKE %s",
					$wpdb->esc_like($this->s) . $wild
				);
				$reg_blog_ids = $wpdb->get_col($sql);

				if ($reg_blog_ids) {
					$ids_placeholders = implode(', ', array_fill(0, count($reg_blog_ids), '%d'));
					$where_clauses[] = "blog_id IN ({$ids_placeholders})";
					$params = array_merge($params, array_map('intval', $reg_blog_ids));
				} else {
					$where_clauses[] = '0=1'; // Force no results.
				}
			} else {
				if (is_numeric($this->s) && '' === $wild) {
					$where_clauses[] = 'blog_id = %d';
					$params[] = (int) $this->s;
				} elseif (is_subdomain_install()) {
					$blog_s = str_replace('.' . $current_site->domain, '', $this->s);
					$blog_s = $wpdb->esc_like($blog_s) . $wild . $wpdb->esc_like('.' . $current_site->domain);
					$where_clauses[] = 'domain LIKE %s';
					$params[] = $blog_s;
				} else {
					if ($this->s !== trim('/', $current_site->path)) {
						$blog_s = $wpdb->esc_like($current_site->path . $this->s) . $wild . $wpdb->esc_like('/');
					} else {
						$blog_s = $wpdb->esc_like($this->s);
					}
					$where_clauses[] = 'path LIKE %s';
					$params[] = $blog_s;
				}
			}
		}

		$where_sql = ' WHERE ' . implode(' AND ', $where_clauses);

		$order_by_column = match ($_REQUEST['orderby'] ?? null) {
			'registered' => 'registered',
			'lastupdated' => 'last_updated',
			'blogname' => is_subdomain_install() ? 'domain' : 'path',
			'blog_id' => 'blog_id',
			default => null,
		};

		$order_sql = '';
		if ($order_by_column) {
			$order_direction = (isset($_REQUEST['order']) && 'DESC' === strtoupper($_REQUEST['order'])) ? 'DESC' : 'ASC';
			$order_sql = " ORDER BY {$order_by_column} {$order_direction}";
		}

		$query_from_where = "FROM {$wpdb->blogs} {$where_sql}";
		$total = 0;

		// Don't do an unbounded count on large networks.
		if (! wp_is_large_network()) {
			$count_query = "SELECT COUNT(blog_id) {$query_from_where}";
			$total = (int) $wpdb->get_var($wpdb->prepare($count_query, ...$params));
		}

		$limit_sql = $wpdb->prepare(' LIMIT %d, %d', ($pagenum - 1) * $per_page, $per_page);
		$main_query = "SELECT * {$query_from_where} {$order_sql} {$limit_sql}";

		$this->items = $wpdb->get_results($wpdb->prepare($main_query, ...$params), ARRAY_A);

		if (wp_is_large_network()) {
			$total = count($this->items);
		}

		$this->set_pagination_args([
			'total_items' => $total,
			'per_page' => $per_page,
		]);
	}

	public function no_items(): void
	{
		_e('No sites found.');
	}

	protected function get_bulk_actions(): array
	{
		$actions = [];
		if (current_user_can('delete_sites')) {
			$actions['delete'] = __('Delete');
		}
		$actions['spam'] = _x('Mark as Spam', 'site');
		$actions['notspam'] = _x('Not Spam', 'site');

		return $actions;
	}

	protected function pagination(string $which): void
	{
		parent::pagination($which);

		if ('top' === $which) {
			$this->view_switcher($this->mode);
		}
	}

	public function get_columns(): array
	{
		$blogname_columns = is_subdomain_install() ? __('Domain') : __('Path');
		$sites_columns = [
			'cb' => '<input type="checkbox" />',
			'blogname' => $blogname_columns,
			'lastupdated' => __('Last Updated'),
			'registered' => _x('Registered', 'site'),
			'users' => __('Users'),
		];

		if (has_filter('wpmublogsaction')) {
			$sites_columns['plugins'] = __('Actions');
		}

		/**
		 * Filter the displayed site columns in Sites list table.
		 *
		 * @since MU
		 *
		 * @param array $sites_columns An array of displayed site columns. Default 'cb',
		 *                             'blogname', 'lastupdated', 'registered', 'users'.
		 */
		return apply_filters('wpmu_blogs_columns', $sites_columns);
	}

	protected function get_sortable_columns(): array
	{
		return [
			'blogname' => 'blogname',
			'lastupdated' => 'lastupdated',
			'registered' => 'blog_id',
		];
	}

	public function display_rows(): void
	{
		$status_list = [
			'archived' => ['site-archived', __('Archived')],
			'spam' => ['site-spammed', _x('Spam', 'site')],
			'deleted' => ['site-deleted', __('Deleted')],
			'mature' => ['site-mature', __('Mature')],
		];

		$date_format = 'list' === $this->mode ? 'Y/m/d' : 'Y/m/d \<\b\r \/\> g:i:s a';

		$row_class = '';
		foreach ($this->items as $blog) {
			$row_class = 'alternate' === $row_class ? '' : 'alternate';
			$blog_states = [];
			$class_name = $row_class;

			foreach ($status_list as $status => $col) {
				if ('1' === get_blog_status($blog['blog_id'], $status)) {
					$class_name = $col[0];
					$blog_states[] = $col[1];
				}
			}

			$blog_state_output = '';
			if (! empty($blog_states)) {
				$state_count = count($blog_states);
				$formatted_states = [];
				foreach ($blog_states as $i => $state) {
					$sep = ($i < $state_count - 1) ? ', ' : '';
					$formatted_states[] = sprintf("<span class='post-state'>%s%s</span>", esc_html($state), $sep);
				}
				$blog_state_output = ' - ' . implode('', $formatted_states);
			}

			printf('<tr class="%s">', esc_attr($class_name));

			$blogname = is_subdomain_install()
				? str_replace('.' . get_current_site()->domain, '', $blog['domain'])
				: $blog['path'];

			[$columns, $hidden] = $this->get_column_info();

			foreach ($columns as $column_name => $column_display_name) {
				$style = in_array($column_name, $hidden, true) ? ' style="display:none;"' : '';

				switch ($column_name) {
					case 'cb':
						?>
						<th scope="row" class="check-column">
							<?php if (! is_main_site((int) $blog['blog_id'])) : ?>
							<label class="screen-reader-text" for="blog_<?php echo (int) $blog['blog_id']; ?>"><?php printf(__('Select %s'), esc_html($blogname)); ?></label>
							<input type="checkbox" id="blog_<?php echo (int) $blog['blog_id']; ?>" name="allblogs[]" value="<?php echo esc_attr($blog['blog_id']); ?>" />
							<?php endif; ?>
						</th>
						<?php
						break;

					case 'id':
						?>
						<th scope="row">
							<?php echo (int) $blog['blog_id']; ?>
						</th>
						<?php
						break;

					case 'blogname':
						echo "<td class='column-{$column_name} {$column_name}'{$style}>";
						printf(
							'<a href="%s" class="edit">%s</a>',
							esc_url(network_admin_url('site-info.php?id=' . $blog['blog_id'])),
							esc_html($blogname) . $blog_state_output
						);

						if ('list' !== $this->mode) {
							switch_to_blog((int) $blog['blog_id']);
							echo '<p>' . sprintf(
								_x('%1$s &#8211; <em>%2$s</em>', '%1$s: site name. %2$s: site tagline.'),
								get_option('blogname'),
								get_option('blogdescription ') // Original code has a trailing space.
							) . '</p>';
							restore_current_blog();
						}

						$actions = [
							'edit' => sprintf('<span class="edit"><a href="%s">%s</a></span>', esc_url(network_admin_url('site-info.php?id=' . $blog['blog_id'])), __('Edit')),
							'backend' => sprintf('<span class="backend"><a href="%s" class="edit">%s</a></span>', esc_url(get_admin_url((int) $blog['blog_id'])), __('Dashboard')),
							'visit' => sprintf('<span class="view"><a href="%s" rel="permalink">%s</a></span>', esc_url(get_home_url((int) $blog['blog_id'], '/')), __('Visit')),
						];

						if (get_current_site()->blog_id !== (int) $blog['blog_id']) {
							$confirm_url = fn(string $action, string $msg): string => esc_url(wp_nonce_url(network_admin_url("sites.php?action=confirm&action2={$action}&id={$blog['blog_id']}&msg=" . urlencode($msg)), 'confirm'));

							if ('1' === get_blog_status($blog['blog_id'], 'deleted')) {
								$actions['activate'] = sprintf('<span class="activate"><a href="%s">%s</a></span>', $confirm_url('activateblog', sprintf(__('You are about to activate the site %s'), $blogname)), __('Activate'));
							} else {
								$actions['deactivate'] = sprintf('<span class="activate"><a href="%s">%s</a></span>', $confirm_url('deactivateblog', sprintf(__('You are about to deactivate the site %s'), $blogname)), __('Deactivate'));
							}

							if ('1' === get_blog_status($blog['blog_id'], 'archived')) {
								$actions['unarchive'] = sprintf('<span class="archive"><a href="%s">%s</a></span>', $confirm_url('unarchiveblog', sprintf(__('You are about to unarchive the site %s.'), $blogname)), __('Unarchive'));
							} else {
								$actions['archive'] = sprintf('<span class="archive"><a href="%s">%s</a></span>', $confirm_url('archiveblog', sprintf(__('You are about to archive the site %s.'), $blogname)), _x('Archive', 'verb; site'));
							}

							if ('1' === get_blog_status($blog['blog_id'], 'spam')) {
								$actions['unspam'] = sprintf('<span class="spam"><a href="%s">%s</a></span>', $confirm_url('unspamblog', sprintf(__('You are about to unspam the site %s.'), $blogname)), _x('Not Spam', 'site'));
							} else {
								$actions['spam'] = sprintf('<span class="spam"><a href="%s">%s</a></span>', $confirm_url('spamblog', sprintf(__('You are about to mark the site %s as spam.'), $blogname)), _x('Spam', 'site'));
							}

							if (current_user_can('delete_site', (int) $blog['blog_id'])) {
								$actions['delete'] = sprintf('<span class="delete"><a href="%s">%s</a></span>', $confirm_url('deleteblog', sprintf(__('You are about to delete the site %s.'), $blogname)), __('Delete'));
							}
						}

						/** @var array<string, string> $actions */
						$actions = apply_filters('manage_sites_action_links', array_filter($actions), (int) $blog['blog_id'], $blogname);
						echo $this->row_actions($actions);
						echo '</td>';
						break;

					case 'lastupdated':
						echo "<td class='{$column_name} column-{$column_name}'{$style}>";
						echo '0000-00-00 00:00:00' === $blog['last_updated'] ? __('Never') : mysql2date($date_format, $blog['last_updated']);
						echo '</td>';
						break;

					case 'registered':
						echo "<td class='{$column_name} column-{$column_name}'{$style}>";
						echo '0000-00-00 00:00:00' === $blog['registered'] ? '&#x2014;' : mysql2date($date_format, $blog['registered']);
						echo '</td>';
						break;

					case 'users':
						echo "<td class='{$column_name} column-{$column_name}'{$style}>";
						$blogusers = get_users(['blog_id' => (int) $blog['blog_id'], 'number' => 6]);
						if (is_array($blogusers)) {
							$blogusers_warning = '';
							if (count($blogusers) > 5) {
								$blogusers = array_slice($blogusers, 0, 5);
								$blogusers_warning = sprintf(
									'%s <a href="%s">%s</a>',
									__('Only showing first 5 users.'),
									esc_url(network_admin_url('site-users.php?id=' . $blog['blog_id'])),
									__('More')
								);
							}
							foreach ($blogusers as $user_object) {
								printf(
									'<a href="%s">%s</a> %s<br />',
									esc_url(network_admin_url('user-edit.php?user_id=' . $user_object->ID)),
									esc_html($user_object->user_login),
									'list' !== $this->mode ? '( ' . esc_html($user_object->user_email) . ' )' : ''
								);
							}
							if ('' !== $blogusers_warning) {
								echo '<strong>' . $blogusers_warning . '</strong><br />';
							}
						}
						echo '</td>';
						break;

					case 'plugins':
						if (has_filter('wpmublogsaction')) {
							echo "<td class='{$column_name} column-{$column_name}'{$style}>";
							/**
							 * Fires inside the auxiliary 'Actions' column of the Sites list table.
							 * @since MU
							 * @param int $blog_id The site ID.
							 */
							do_action('wpmublogsaction', (int) $blog['blog_id']);
							echo '</td>';
						}
						break;

					default:
						echo "<td class='{$column_name} column-{$column_name}'{$style}>";
						/**
						 * Fires for each registered custom column in the Sites list table.
						 * @since 3.1.0
						 * @param string $column_name The name of the column to display.
						 * @param int    $blog_id     The site ID.
						 */
						do_action('manage_sites_custom_column', $column_name, (int) $blog['blog_id']);
						echo '</td>';
						break;
				}
			}
			?>
			</tr>
			<?php
		}
	}
}