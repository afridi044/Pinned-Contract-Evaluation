<?php

declare(strict_types=1);

/**
 * MS Themes List Table class.
 *
 * @package WordPress
 * @subpackage List_Table
 * @since 3.1.0
 * @access private
 */
class WP_MS_Themes_List_Table extends WP_List_Table
{
	public ?int $site_id = null;
	public bool $is_site_themes;

	private string $status;
	private int $page;
	private ?string $orderby;
	private string $order;
	private string $s;
	private array $totals = [];
	private bool $has_items = false;

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
			'plural' => 'themes',
			'screen' => $args['screen'] ?? null,
		]);

		$this->is_site_themes = ($this->screen?->id === 'site-themes-network');

		if ($this->is_site_themes) {
			$this->site_id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : null;
		}

		$this->status = $_REQUEST['theme_status'] ?? 'all';
		if (!in_array($this->status, ['all', 'enabled', 'disabled', 'upgrade', 'search', 'broken'], true)) {
			$this->status = 'all';
		}

		$this->page = $this->get_pagenum();
		$this->s = wp_unslash($_REQUEST['s'] ?? '');
		$this->orderby = $_REQUEST['orderby'] ?? null;
		$this->order = strtoupper($_REQUEST['order'] ?? 'ASC');
	}

	protected function get_table_classes(): array
	{
		// todo: remove and add CSS for .themes
		return ['widefat', 'plugins'];
	}

	public function ajax_user_can(): bool
	{
		return $this->is_site_themes
			? current_user_can('manage_sites')
			: current_user_can('manage_network_themes');
	}

	public function prepare_items(): void
	{
		$themes = [
			/**
			 * Filter the full array of WP_Theme objects to list in the Multisite
			 * themes list table.
			 *
			 * @since 3.1.0
			 *
			 * @param WP_Theme[] $all An array of WP_Theme objects to display in the list table.
			 */
			'all' => apply_filters('all_themes', wp_get_themes()),
			'search' => [],
			'enabled' => [],
			'disabled' => [],
			'upgrade' => [],
			'broken' => $this->is_site_themes ? [] : wp_get_themes(['errors' => true]),
		];

		$themes_per_page = $this->is_site_themes
			? $this->get_items_per_page('site_themes_network_per_page')
			: $this->get_items_per_page('themes_network_per_page');

		$allowed_where = $this->is_site_themes ? 'site' : 'network';

		$maybe_update = current_user_can('update_themes') && !$this->is_site_themes && ($current = get_site_transient('update_themes'));

		foreach ($themes['all'] as $key => $theme) {
			if ($this->is_site_themes && $theme->is_allowed('network')) {
				unset($themes['all'][$key]);
				continue;
			}

			if ($maybe_update && isset($current->response[$key])) {
				$themes['all'][$key]->update = true;
				$themes['upgrade'][$key] = $themes['all'][$key];
			}

			$filter = $theme->is_allowed($allowed_where, $this->site_id) ? 'enabled' : 'disabled';
			$themes[$filter][$key] = $themes['all'][$key];
		}

		if ($this->s) {
			$this->status = 'search';
			$themes['search'] = array_filter(array_merge($themes['all'], $themes['broken']), [$this, 'search_callback']);
		}

		foreach ($themes as $type => $list) {
			$this->totals[$type] = count($list);
		}

		if (empty($themes[$this->status]) && !in_array($this->status, ['all', 'search'], true)) {
			$this->status = 'all';
		}

		$this->items = $themes[$this->status];
		WP_Theme::sort_by_name($this->items);

		$this->has_items = !empty($themes['all']);
		$total_this_page = $this->totals[$this->status];

		if ($this->orderby) {
			$orderby_capitalized = ucfirst($this->orderby);

			if ('Name' === $orderby_capitalized) {
				// This logic is intentionally preserved for functional equivalence.
				// It reverses on ASC, and does nothing on DESC, which is counter-intuitive.
				if ('ASC' === $this->order) {
					$this->items = array_reverse($this->items);
				}
			} else {
				uasort($this->items, [$this, 'order_callback']);
			}
		}

		$start = ($this->page - 1) * $themes_per_page;

		if ($total_this_page > $themes_per_page) {
			$this->items = array_slice($this->items, $start, $themes_per_page, true);
		}

		$this->set_pagination_args([
			'total_items' => $total_this_page,
			'per_page' => $themes_per_page,
		]);
	}

	private function search_callback(WP_Theme $theme): bool
	{
		if ('' === $this->s) {
			return false;
		}

		$fields_to_search = ['Name', 'Description', 'Author', 'AuthorURI'];
		foreach ($fields_to_search as $field) {
			if (false !== stripos($theme->display($field, false, true), $this->s)) {
				return true;
			}
		}

		if (false !== stripos($theme->get_stylesheet(), $this->s)) {
			return true;
		}

		if (false !== stripos($theme->get_template(), $this->s)) {
			return true;
		}

		return false;
	}

	private function order_callback(WP_Theme $theme_a, WP_Theme $theme_b): int
	{
		$orderby_capitalized = ucfirst($this->orderby);
		$a = $theme_a->get($orderby_capitalized);
		$b = $theme_b->get($orderby_capitalized);

		if ('DESC' === $this->order) {
			return $b <=> $a;
		}

		return $a <=> $b;
	}

	public function no_items(): void
	{
		if (!$this->has_items) {
			_e('No themes found.');
		} else {
			_e('You do not appear to have any themes available at this time.');
		}
	}

	public function get_columns(): array
	{
		return [
			'cb' => '<input type="checkbox" />',
			'name' => __('Theme'),
			'description' => __('Description'),
		];
	}

	protected function get_sortable_columns(): array
	{
		return [
			'name' => 'name',
		];
	}

	protected function get_views(): array
	{
		$status_links = [];
		foreach ($this->totals as $type => $count) {
			if (!$count) {
				continue;
			}

			$text = match ($type) {
				'all' => _nx('All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $count, 'themes'),
				'enabled' => _n('Enabled <span class="count">(%s)</span>', 'Enabled <span class="count">(%s)</span>', $count),
				'disabled' => _n('Disabled <span class="count">(%s)</span>', 'Disabled <span class="count">(%s)</span>', $count),
				'upgrade' => _n('Update Available <span class="count">(%s)</span>', 'Update Available <span class="count">(%s)</span>', $count),
				'broken' => _n('Broken <span class="count">(%s)</span>', 'Broken <span class="count">(%s)</span>', $count),
				default => '',
			};

			if ('' === $text) {
				continue;
			}

			$url = $this->is_site_themes
				? 'site-themes.php?id=' . $this->site_id
				: 'themes.php';

			if ('search' !== $type) {
				$status_links[$type] = sprintf(
					'<a href="%s"%s>%s</a>',
					esc_url(add_query_arg('theme_status', $type, $url)),
					($type === $this->status) ? ' class="current"' : '',
					sprintf($text, number_format_i18n($count))
				);
			}
		}

		return $status_links;
	}

	protected function get_bulk_actions(): array
	{
		$actions = [];
		if ('enabled' !== $this->status) {
			$actions['enable-selected'] = $this->is_site_themes ? __('Enable') : __('Network Enable');
		}
		if ('disabled' !== $this->status) {
			$actions['disable-selected'] = $this->is_site_themes ? __('Disable') : __('Network Disable');
		}

		if (!$this->is_site_themes) {
			if (current_user_can('update_themes')) {
				$actions['update-selected'] = __('Update');
			}
			if (current_user_can('delete_themes')) {
				$actions['delete-selected'] = __('Delete');
			}
		}
		return $actions;
	}

	public function display_rows(): void
	{
		foreach ($this->items as $theme) {
			$this->single_row($theme);
		}
	}

	public function single_row(WP_Theme $theme): void
	{
		$context = $this->status;

		$url = $this->is_site_themes
			? "site-themes.php?id={$this->site_id}&"
			: 'themes.php?';

		$allowed = $this->is_site_themes
			? $theme->is_allowed('site', $this->site_id)
			: $theme->is_allowed('network');

		$actions = [
			'enable' => '',
			'disable' => '',
			'edit' => '',
			'delete' => '',
		];

		$stylesheet = $theme->get_stylesheet();
		$theme_key = urlencode($stylesheet);

		if (!$allowed) {
			if (!$theme->errors()) {
				$enable_url = wp_nonce_url($url . "action=enable&theme={$theme_key}&paged={$this->page}&s={$this->s}", "enable-theme_{$stylesheet}");
				$actions['enable'] = sprintf(
					'<a href="%s" title="%s" class="edit">%s</a>',
					esc_url($enable_url),
					esc_attr__('Enable this theme'),
					$this->is_site_themes ? __('Enable') : __('Network Enable')
				);
			}
		} else {
			$disable_url = wp_nonce_url($url . "action=disable&theme={$theme_key}&paged={$this->page}&s={$this->s}", "disable-theme_{$stylesheet}");
			$actions['disable'] = sprintf(
				'<a href="%s" title="%s">%s</a>',
				esc_url($disable_url),
				esc_attr__('Disable this theme'),
				$this->is_site_themes ? __('Disable') : __('Network Disable')
			);
		}

		if (current_user_can('edit_themes')) {
			$actions['edit'] = sprintf(
				'<a href="%s" title="%s" class="edit">%s</a>',
				esc_url("theme-editor.php?theme={$theme_key}"),
				esc_attr__('Open this theme in the Theme Editor'),
				__('Edit')
			);
		}

		if (!$allowed && current_user_can('delete_themes') && !$this->is_site_themes && $stylesheet !== get_option('stylesheet') && $stylesheet !== get_option('template')) {
			$delete_url = wp_nonce_url("themes.php?action=delete-selected&checked[]={$theme_key}&theme_status={$context}&paged={$this->page}&s={$this->s}", 'bulk-themes');
			$actions['delete'] = sprintf(
				'<a href="%s" title="%s" class="delete">%s</a>',
				esc_url($delete_url),
				esc_attr__('Delete this theme'),
				__('Delete')
			);
		}

		/**
		 * @param array<string, string> $actions
		 * @param WP_Theme $theme
		 * @param string $context
		 */
		$actions = apply_filters('theme_action_links', array_filter($actions), $theme, $context);
		/**
		 * @param array<string, string> $actions
		 * @param WP_Theme $theme
		 * @param string $context
		 */
		$actions = apply_filters("theme_action_links_{$stylesheet}", $actions, $theme, $context);

		$class = !$allowed ? 'inactive' : 'active';
		$checkbox_id = 'checkbox_' . md5($theme->get('Name'));
		$checkbox = sprintf(
			'<input type="checkbox" name="checked[]" value="%s" id="%s" /><label class="screen-reader-text" for="%s">%s</label>',
			esc_attr($stylesheet),
			$checkbox_id,
			$checkbox_id,
			sprintf('%s %s', __('Select'), $theme->display('Name'))
		);

		$id = sanitize_html_class($theme->get_stylesheet());

		if (!empty($this->totals['upgrade']) && !empty($theme->update)) {
			$class .= ' update';
		}

		echo "<tr id='{$id}' class='{$class}'>";

		[$columns, $hidden] = $this->get_column_info();

		foreach ($columns as $column_name => $column_display_name) {
			$style = in_array($column_name, $hidden, true) ? ' style="display:none;"' : '';

			switch ($column_name) {
				case 'cb':
					echo "<th scope='row' class='check-column'>{$checkbox}</th>";
					break;

				case 'name':
					echo "<td class='theme-title'{$style}><strong>" . $theme->display('Name') . '</strong>';
					echo $this->row_actions($actions, true);
					echo '</td>';
					break;

				case 'description':
					echo "<td class='column-description desc'{$style}>";
					if ($theme->errors()) {
						$pre = ('broken' === $this->status) ? __('Broken Theme:') . ' ' : '';
						echo '<p><strong class="attention">' . $pre . $theme->errors()->get_error_message() . '</strong></p>';
					}
					echo "<div class='theme-description'><p>" . $theme->display('Description') . "</p></div>
						<div class='{$class} second theme-version-author-uri'>";

					$theme_meta = [];

					if ($theme->get('Version')) {
						$theme_meta[] = sprintf(__('Version %s'), $theme->display('Version'));
					}

					$theme_meta[] = sprintf(__('By %s'), $theme->display('Author'));

					if ($theme->get('ThemeURI')) {
						$theme_meta[] = sprintf(
							'<a href="%s" title="%s">%s</a>',
							$theme->display('ThemeURI'),
							esc_attr__('Visit theme homepage'),
							__('Visit Theme Site')
						);
					}

					/**
					 * @param string[] $theme_meta
					 * @param string $stylesheet
					 * @param WP_Theme $theme
					 * @param string $status
					 */
					$theme_meta = apply_filters('theme_row_meta', $theme_meta, $stylesheet, $theme, $this->status);
					echo implode(' | ', $theme_meta);

					echo '</div></td>';
					break;

				default:
					echo "<td class='{$column_name} column-{$column_name}'{$style}>";
					/**
					 * @param string $column_name
					 * @param string $stylesheet
					 * @param WP_Theme $theme
					 */
					do_action('manage_themes_custom_column', $column_name, $stylesheet, $theme);
					echo '</td>';
			}
		}

		echo '</tr>';

		if ($this->is_site_themes) {
			remove_action("after_theme_row_{$stylesheet}", 'wp_theme_update_row');
		}

		/**
		 * @param string $stylesheet
		 * @param WP_Theme $theme
		 * @param string $status
		 */
		do_action('after_theme_row', $stylesheet, $theme, $this->status);
		/**
		 * @param string $stylesheet
		 * @param WP_Theme $theme
		 * @param string $status
		 */
		do_action("after_theme_row_{$stylesheet}", $stylesheet, $theme, $this->status);
	}
}