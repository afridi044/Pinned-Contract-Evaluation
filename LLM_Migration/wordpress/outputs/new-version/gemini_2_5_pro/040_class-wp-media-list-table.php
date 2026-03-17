<?php

declare(strict_types=1);

/**
 * Media Library List Table class.
 *
 * @package WordPress
 * @subpackage List_Table
 * @since 3.1.0
 * @access private
 */
class WP_Media_List_Table extends WP_List_Table
{
	protected bool $detached = false;
	protected bool $is_trash = false;

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
		$this->detached = ($_REQUEST['attachment-filter'] ?? null) === 'detached';

		parent::__construct([
			'plural' => 'media',
			'screen' => $args['screen'] ?? null,
		]);
	}

	public function ajax_user_can(): bool
	{
		return current_user_can('upload_files');
	}

	public function prepare_items(): void
	{
		global $wp_query, $post_mime_types, $avail_post_mime_types, $mode;

		[$post_mime_types, $avail_post_mime_types] = wp_edit_attachments_query($_REQUEST);

		$this->is_trash = ($_REQUEST['attachment-filter'] ?? null) === 'trash';

		$mode = $_REQUEST['mode'] ?? 'list';

		$this->set_pagination_args([
			'total_items' => $wp_query->found_posts,
			'total_pages' => $wp_query->max_num_pages,
			'per_page' => $wp_query->query_vars['posts_per_page'],
		]);
	}

	protected function get_views(): array
	{
		global $wpdb, $post_mime_types, $avail_post_mime_types;

		$type_links = [];
		$num_posts = [];
		$_num_posts = (array) wp_count_attachments();
		$_total_posts = array_sum($_num_posts) - ($_num_posts['trash'] ?? 0);
		$total_orphans = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status != 'trash' AND post_parent < 1");
		$matches = wp_match_mime_types(array_keys($post_mime_types), array_keys($_num_posts));

		foreach ($matches as $type => $reals) {
			foreach ($reals as $real) {
				$num_posts[$type] = ($num_posts[$type] ?? 0) + $_num_posts[$real];
			}
		}

		$current_filter = $_GET['attachment-filter'] ?? '';

		$selected = $current_filter === '' ? ' selected="selected"' : '';
		$type_links['all'] = sprintf(
			'<option value=""%s>%s</option>',
			$selected,
			sprintf(_nx('All (%s)', 'All (%s)', $_total_posts, 'uploaded files'), number_format_i18n($_total_posts))
		);

		foreach ($post_mime_types as $mime_type => $label) {
			if (!wp_match_mime_types($mime_type, $avail_post_mime_types)) {
				continue;
			}

			$selected = '';
			if (str_starts_with($current_filter, 'post_mime_type:') && wp_match_mime_types($mime_type, str_replace('post_mime_type:', '', $current_filter))) {
				$selected = ' selected="selected"';
			}

			if (!empty($num_posts[$mime_type])) {
				$type_links[$mime_type] = sprintf(
					'<option value="post_mime_type:%s"%s>%s</option>',
					urlencode($mime_type),
					$selected,
					sprintf(translate_nooped_plural($label[2], $num_posts[$mime_type]), number_format_i18n($num_posts[$mime_type]))
				);
			}
		}

		$selected_detached = $this->detached ? ' selected="selected"' : '';
		$type_links['detached'] = sprintf(
			'<option value="detached"%s>%s</option>',
			$selected_detached,
			sprintf(_nx('Unattached (%s)', 'Unattached (%s)', $total_orphans, 'detached files'), number_format_i18n($total_orphans))
		);

		if (!empty($_num_posts['trash'])) {
			$selected_trash = $current_filter === 'trash' ? ' selected="selected"' : '';
			$type_links['trash'] = sprintf(
				'<option value="trash"%s>%s</option>',
				$selected_trash,
				sprintf(_nx('Trash (%s)', 'Trash (%s)', $_num_posts['trash'], 'uploaded files'), number_format_i18n($_num_posts['trash']))
			);
		}

		return $type_links;
	}

	protected function get_bulk_actions(): array
	{
		$actions = [];
		$actions['delete'] = __('Delete Permanently');
		if ($this->detached) {
			$actions['attach'] = __('Attach to a post');
		}

		return $actions;
	}

	protected function extra_tablenav(string $which): void
	{
		if ('bar' !== $which) {
			return;
		}
		?>
		<div class="actions">
			<?php
			if (! is_singular()) {
				if (! $this->is_trash) {
					$this->months_dropdown('attachment');
				}

				/** This action is documented in wp-admin/includes/class-wp-posts-list-table.php */
				do_action('restrict_manage_posts');
				submit_button(__('Filter'), 'button', 'filter_action', false, ['id' => 'post-query-submit']);
			}

			if ($this->is_trash && current_user_can('edit_others_posts')) {
				submit_button(__('Empty Trash'), 'apply', 'delete_all', false);
			}
			?>
		</div>
		<?php
	}

	public function current_action(): ?string
	{
		if (isset($_REQUEST['found_post_id'], $_REQUEST['media'])) {
			return 'attach';
		}

		if (isset($_REQUEST['delete_all']) || isset($_REQUEST['delete_all2'])) {
			return 'delete_all';
		}

		return parent::current_action();
	}

	public function has_items(): bool
	{
		return have_posts();
	}

	public function no_items(): void
	{
		_e('No media attachments found.');
	}

	protected function pagination(string $which): void
	{
		global $mode;

		parent::pagination($which);
	}

	/**
	 * Display a view switcher.
	 *
	 * @since 3.1.0
	 */
	protected function view_switcher(string $current_mode): void
	{
		$modes = [
			'list' => __('List View'),
			'grid' => __('Grid View'),
		];
		?>
		<input type="hidden" name="mode" value="<?= esc_attr($current_mode); ?>" />
		<div class="view-switch">
			<?php
			foreach ($modes as $mode => $title) {
				$classes = ['view-' . $mode];
				if ($current_mode === $mode) {
					$classes[] = 'current';
				}
				printf(
					'<a href="%s" class="%s" id="view-switch-%s"><span class="screen-reader-text">%s</span></a>' . "\n",
					esc_url(add_query_arg('mode', $mode)),
					implode(' ', $classes),
					$mode,
					$title
				);
			}
			?>
		</div>
		<?php
	}

	/**
	 * Override parent views so we can use the filter bar display.
	 */
	public function views(): void
	{
		global $mode;

		$views = $this->get_views();
		?>
		<div class="wp-filter">
			<?php $this->view_switcher($mode); ?>

			<select class="attachment-filters" name="attachment-filter">
				<?php
				if (! empty($views)) {
					foreach ($views as $class => $view) {
						echo "\t$view\n";
					}
				}
				?>
			</select>

			<?php
			$this->extra_tablenav('bar');

			/** This filter is documented in wp-admin/inclues/class-wp-list-table.php */
			$views = apply_filters("views_{$this->screen->id}", []);

			// Back compat for pre-4.0 view links.
			if (! empty($views)) {
				echo '<ul class="filter-links">';
				foreach ($views as $class => $view) {
					echo "<li class='$class'>$view</li>";
				}
				echo '</ul>';
			}
			?>

			<div class="search-form">
				<label for="media-search-input" class="screen-reader-text"><?php esc_html_e('Search Media'); ?></label>
				<input type="search" placeholder="<?php esc_attr_e('Search') ?>" id="media-search-input" class="search" name="s" value="<?php _admin_search_query(); ?>">
			</div>
		</div>
		<?php
	}

	public function get_columns(): array
	{
		$posts_columns = [
			'cb'   => '<input type="checkbox" />',
			'icon' => '',
			/* translators: column name */
			'title'  => _x('File', 'column name'),
			'author' => __('Author'),
		];

		$taxonomies = get_taxonomies_for_attachments('objects');
		$taxonomies = wp_filter_object_list($taxonomies, ['show_admin_column' => true], 'and', 'name');

		/**
		 * Filter the taxonomy columns for attachments in the Media list table.
		 *
		 * @since 3.5.0
		 *
		 * @param array  $taxonomies An array of registered taxonomies to show for attachments.
		 * @param string $post_type  The post type. Default 'attachment'.
		 */
		$taxonomies = apply_filters('manage_taxonomies_for_attachment_columns', $taxonomies, 'attachment');
		$taxonomies = array_filter($taxonomies, 'taxonomy_exists');

		foreach ($taxonomies as $taxonomy) {
			$column_key = match ($taxonomy) {
				'category' => 'categories',
				'post_tag' => 'tags',
				default    => 'taxonomy-' . $taxonomy,
			};

			$posts_columns[$column_key] = get_taxonomy($taxonomy)->labels->name;
		}

		if (! $this->detached) {
			/* translators: column name */
			$posts_columns['parent'] = _x('Uploaded to', 'column name');
			if (post_type_supports('attachment', 'comments')) {
				$posts_columns['comments'] = '<span class="vers"><span title="' . esc_attr__('Comments') . '" class="comment-grey-bubble"></span></span>';
			}
		}
		/* translators: column name */
		$posts_columns['date'] = _x('Date', 'column name');
		/**
		 * Filter the Media list table columns.
		 *
		 * @since 2.5.0
		 *
		 * @param array $posts_columns An array of columns displayed in the Media list table.
		 * @param bool  $detached      Whether the list table contains media not attached
		 *                             to any posts. Default true.
		 */
		return apply_filters('manage_media_columns', $posts_columns, $this->detached);
	}

	protected function get_sortable_columns(): array
	{
		return [
			'title'    => 'title',
			'author'   => 'author',
			'parent'   => 'parent',
			'comments' => 'comment_count',
			'date'     => ['date', true],
		];
	}

	public function display_rows(): void
	{
		global $post;

		add_filter('the_title', 'esc_html');
		$alt = '';

		while (have_posts()) :
			the_post();
			$user_can_edit = current_user_can('edit_post', $post->ID);

			if (($this->is_trash && $post->post_status !== 'trash')
				|| (! $this->is_trash && $post->post_status === 'trash')
			) {
				continue;
			}

			$alt = ($alt === 'alternate') ? '' : 'alternate';
			$post_owner = (get_current_user_id() === $post->post_author) ? 'self' : 'other';
			$att_title = _draft_or_post_title();
			?>
			<tr id="post-<?= $post->ID; ?>" class="<?= trim($alt . ' author-' . $post_owner . ' status-' . $post->post_status); ?>">
				<?php
				[$columns, $hidden] = $this->get_column_info();
				foreach ($columns as $column_name => $column_display_name) {
					$class = "class='$column_name column-$column_name'";

					$style = '';
					if (in_array($column_name, $hidden, true)) {
						$style = ' style="display:none;"';
					}

					$attributes = $class . $style;

					switch ($column_name) {
						case 'cb':
							?>
							<th scope="row" class="check-column">
								<?php if ($user_can_edit) : ?>
									<label class="screen-reader-text" for="cb-select-<?php the_ID(); ?>"><?= sprintf(__('Select %s'), $att_title); ?></label>
									<input type="checkbox" name="media[]" id="cb-select-<?php the_ID(); ?>" value="<?php the_ID(); ?>" />
								<?php endif; ?>
							</th>
							<?php
							break;

						case 'icon':
							[$mime] = explode('/', $post->post_mime_type);
							$attributes = 'class="column-icon media-icon ' . $mime . '-icon"' . $style;
							?>
							<td <?= $attributes ?>>
								<?php
								if ($thumb = wp_get_attachment_image($post->ID, [80, 60], true)) {
									if ($this->is_trash || ! $user_can_edit) {
										echo $thumb;
									} else {
										?>
										<a href="<?= get_edit_post_link($post->ID, true); ?>" title="<?= esc_attr(sprintf(__('Edit &#8220;%s&#8221;'), $att_title)); ?>">
											<?= $thumb; ?>
										</a>
										<?php
									}
								}
								?>
							</td>
							<?php
							break;

						case 'title':
							?>
							<td <?= $attributes ?>>
								<strong>
									<?php if ($this->is_trash || ! $user_can_edit) : ?>
										<?= $att_title; ?>
									<?php else : ?>
										<a href="<?= get_edit_post_link($post->ID, true); ?>" title="<?= esc_attr(sprintf(__('Edit &#8220;%s&#8221;'), $att_title)); ?>">
											<?= $att_title; ?>
										</a>
									<?php endif;
									_media_states($post); ?>
								</strong>
								<p>
									<?php
									$file = get_attached_file($post->ID);
									$extension = $file ? pathinfo($file, PATHINFO_EXTENSION) : '';
									if ($extension) {
										echo esc_html(strtoupper($extension));
									} else {
										echo esc_html(strtoupper(str_replace('image/', '', get_post_mime_type())));
									}
									?>
								</p>
								<?= $this->row_actions($this->_get_row_actions($post, $att_title)); ?>
							</td>
							<?php
							break;

						case 'author':
							?>
							<td <?= $attributes ?>>
								<?php
								printf(
									'<a href="%s">%s</a>',
									esc_url(add_query_arg(['author' => get_the_author_meta('ID')], 'upload.php')),
									get_the_author()
								);
								?>
							</td>
							<?php
							break;

						case 'desc':
							?>
							<td <?= $attributes ?>><?= has_excerpt() ? $post->post_excerpt : ''; ?></td>
							<?php
							break;

						case 'date':
							if ('0000-00-00 00:00:00' === $post->post_date) {
								$h_time = __('Unpublished');
							} else {
								$m_time = $post->post_date;
								$time = get_post_time('G', true, $post, false);
								if (($t_diff = time() - $time) < DAY_IN_SECONDS) {
									$h_time = sprintf(__('%s ago'), human_time_diff($time));
								} else {
									$h_time = mysql2date(__('Y/m/d'), $m_time);
								}
							}
							?>
							<td <?= $attributes ?>><?= $h_time ?></td>
							<?php
							break;

						case 'parent':
							$parent = $post->post_parent > 0 ? get_post($post->post_parent) : false;

							if ($parent) {
								$title = _draft_or_post_title($post->post_parent);
								$parent_type = get_post_type_object($parent->post_type);
								?>
								<td <?= $attributes ?>>
									<strong>
										<?php if ($parent_type && $parent_type->show_ui && current_user_can('edit_post', $post->post_parent)) : ?>
											<a href="<?= get_edit_post_link($post->post_parent); ?>"><?= $title ?></a>
										<?php else : ?>
											<?= $title; ?>
										<?php endif; ?>
									</strong>,
									<?= get_the_time(__('Y/m/d')); ?>
								</td>
								<?php
							} else {
								?>
								<td <?= $attributes ?>>
									<?php _e('(Unattached)'); ?><br />
									<?php if ($user_can_edit) : ?>
										<a class="hide-if-no-js" onclick="findPosts.open('media[]','<?= $post->ID ?>'); return false;" href="#the-list">
											<?php _e('Attach'); ?>
										</a>
									<?php endif; ?>
								</td>
								<?php
							}
							break;

						case 'comments':
							$attributes = 'class="comments column-comments num"' . $style;
							?>
							<td <?= $attributes ?>>
								<div class="post-com-count-wrapper">
									<?php
									$pending_comments = get_pending_comments_num($post->ID);
									$this->comments_bubble($post->ID, $pending_comments);
									?>
								</div>
							</td>
							<?php
							break;

						default:
							$taxonomy = false;
							if (str_starts_with($column_name, 'taxonomy-')) {
								$taxonomy = substr($column_name, 9);
							} elseif ('categories' === $column_name) {
								$taxonomy = 'category';
							} elseif ('tags' === $column_name) {
								$taxonomy = 'post_tag';
							}

							if ($taxonomy) {
								echo "<td {$attributes}>";
								if ($terms = get_the_terms($post->ID, $taxonomy)) {
									$out = [];
									foreach ($terms as $t) {
										$posts_in_term_qv = [
											'taxonomy' => $taxonomy,
											'term'     => $t->slug,
										];

										$out[] = sprintf(
											'<a href="%s">%s</a>',
											esc_url(add_query_arg($posts_in_term_qv, 'upload.php')),
											esc_html(sanitize_term_field('name', $t->name, $t->term_id, $taxonomy, 'display'))
										);
									}
									/* translators: used between list items, there is a space after the comma */
									echo implode(__(', '), $out);
								} else {
									echo '&#8212;';
								}
								echo '</td>';
								break;
							}
							?>
							<td <?= $attributes ?>>
								<?php
								/**
								 * Fires for each custom column in the Media list table.
								 *
								 * Custom columns are registered using the 'manage_media_columns' filter.
								 *
								 * @since 2.5.0
								 *
								 * @param string $column_name Name of the custom column.
								 * @param int    $post_id     Attachment ID.
								 */
								do_action('manage_media_custom_column', $column_name, $post->ID);
								?>
							</td>
							<?php
							break;
					}
				}
				?>
			</tr>
		<?php endwhile;
	}

	private function _get_row_actions(object $post, string $att_title): array
	{
		$actions = [];
		$post_id = $post->ID;

		if ($this->detached) {
			if (current_user_can('edit_post', $post_id)) {
				$actions['edit'] = sprintf('<a href="%s">%s</a>', get_edit_post_link($post_id, true), __('Edit'));
			}
			if (current_user_can('delete_post', $post_id)) {
				if (EMPTY_TRASH_DAYS && MEDIA_TRASH) {
					$actions['trash'] = sprintf(
						'<a class="submitdelete" href="%s">%s</a>',
						wp_nonce_url("post.php?action=trash&amp;post={$post_id}", "trash-post_{$post_id}"),
						__('Trash')
					);
				} else {
					$delete_ays = ! MEDIA_TRASH ? " onclick='return showNotice.warn();'" : '';
					$actions['delete'] = sprintf(
						'<a class="submitdelete"%s href="%s">%s</a>',
						$delete_ays,
						wp_nonce_url("post.php?action=delete&amp;post={$post_id}", "delete-post_{$post_id}"),
						__('Delete Permanently')
					);
				}
			}
			$actions['view'] = sprintf(
				'<a href="%s" title="%s" rel="permalink">%s</a>',
				get_permalink($post_id),
				esc_attr(sprintf(__('View &#8220;%s&#8221;'), $att_title)),
				__('View')
			);
			if (current_user_can('edit_post', $post_id)) {
				$actions['attach'] = sprintf(
					'<a href="#the-list" onclick="findPosts.open(\'media[]\', \'%d\'); return false;" class="hide-if-no-js">%s</a>',
					$post_id,
					__('Attach')
				);
			}
		} else {
			if (current_user_can('edit_post', $post_id) && ! $this->is_trash) {
				$actions['edit'] = sprintf('<a href="%s">%s</a>', get_edit_post_link($post_id, true), __('Edit'));
			}
			if (current_user_can('delete_post', $post_id)) {
				if ($this->is_trash) {
					$actions['untrash'] = sprintf(
						'<a class="submitdelete" href="%s">%s</a>',
						wp_nonce_url("post.php?action=untrash&amp;post={$post_id}", "untrash-post_{$post_id}"),
						__('Restore')
					);
				} elseif (EMPTY_TRASH_DAYS && MEDIA_TRASH) {
					$actions['trash'] = sprintf(
						'<a class="submitdelete" href="%s">%s</a>',
						wp_nonce_url("post.php?action=trash&amp;post={$post_id}", "trash-post_{$post_id}"),
						__('Trash')
					);
				}
				if ($this->is_trash || ! EMPTY_TRASH_DAYS || ! MEDIA_TRASH) {
					$delete_ays = (! $this->is_trash && ! MEDIA_TRASH) ? " onclick='return showNotice.warn();'" : '';
					$actions['delete'] = sprintf(
						'<a class="submitdelete"%s href="%s">%s</a>',
						$delete_ays,
						wp_nonce_url("post.php?action=delete&amp;post={$post_id}", "delete-post_{$post_id}"),
						__('Delete Permanently')
					);
				}
			}
			if (! $this->is_trash) {
				$title = _draft_or_post_title($post->post_parent);
				$actions['view'] = sprintf(
					'<a href="%s" title="%s" rel="permalink">%s</a>',
					get_permalink($post_id),
					esc_attr(sprintf(__('View &#8220;%s&#8221;'), $title)),
					__('View')
				);
			}
		}

		/**
		 * Filter the action links for each attachment in the Media list table.
		 *
		 * @since 2.8.0
		 *
		 * @param array   $actions  An array of action links for each attachment.
		 *                          Default 'Edit', 'Delete Permanently', 'View'.
		 * @param WP_Post $post     WP_Post object for the current attachment.
		 * @param bool    $detached Whether the list table contains media not attached
		 *                          to any posts. Default true.
		 */
		return apply_filters('media_row_actions', $actions, $post, $this->detached);
	}
}