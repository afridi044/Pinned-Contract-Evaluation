<?php

declare(strict_types=1);

/**
 * Navigation Menu functions
 *
 * @package WordPress
 * @subpackage Nav_Menus
 * @since 3.0.0
 */

/**
 * Returns a navigation menu object.
 *
 * @since 3.0.0
 *
 * @uses get_term
 * @uses get_term_by
 *
 * @param int|string|null $menu Menu ID, slug, or name.
 * @return \WP_Term|\WP_Error|false false if $menu param isn't supplied or term does not exist, menu object if successful.
 */
function wp_get_nav_menu_object(int|string|null $menu): \WP_Term|\WP_Error|false
{
	if (empty($menu)) {
		return false;
	}

	$menu_obj = get_term($menu, 'nav_menu');

	if (!$menu_obj) {
		$menu_obj = get_term_by('slug', $menu, 'nav_menu');
	}

	if (!$menu_obj) {
		$menu_obj = get_term_by('name', $menu, 'nav_menu');
	}

	if (!$menu_obj) {
		return false;
	}

	return $menu_obj;
}

/**
 * Check if the given ID is a navigation menu.
 *
 * Returns true if it is; false otherwise.
 *
 * @since 3.0.0
 *
 * @param int|string $menu The menu to check (ID, slug, or name).
 * @return bool Whether the menu exists.
 */
function is_nav_menu(int|string $menu): bool
{
	if (!$menu) {
		return false;
	}

	$menu_obj = wp_get_nav_menu_object($menu);

	return $menu_obj
		&& !$menu_obj instanceof \WP_Error
		&& !empty($menu_obj->taxonomy)
		&& $menu_obj->taxonomy === 'nav_menu';
}

/**
 * Register navigation menus for a theme.
 *
 * @since 3.0.0
 *
 * @param array<string, string> $locations Associative array of menu location identifiers (like a slug) and descriptive text.
 */
function register_nav_menus(array $locations = []): void
{
	global $_wp_registered_nav_menus;

	add_theme_support('menus');

	$_wp_registered_nav_menus = array_merge($_wp_registered_nav_menus ?? [], $locations);
}

/**
 * Unregisters a navigation menu for a theme.
 *
 * @param string $location the menu location identifier
 *
 * @return bool True on success, false on failure.
 */
function unregister_nav_menu(string $location): bool
{
	global $_wp_registered_nav_menus;

	if (isset($_wp_registered_nav_menus[$location])) {
		unset($_wp_registered_nav_menus[$location]);
		if (empty($_wp_registered_nav_menus)) {
			_remove_theme_support('menus');
		}
		return true;
	}
	return false;
}

/**
 * Register a navigation menu for a theme.
 *
 * @since 3.0.0
 *
 * @param string $location Menu location identifier, like a slug.
 * @param string $description Menu location descriptive text.
 */
function register_nav_menu(string $location, string $description): void
{
	register_nav_menus([$location => $description]);
}

/**
 * Returns an array of all registered navigation menus in a theme
 *
 * @since 3.0.0
 * @return array<string, string>
 */
function get_registered_nav_menus(): array
{
	global $_wp_registered_nav_menus;
	return $_wp_registered_nav_menus ?? [];
}

/**
 * Returns an array with the registered navigation menu locations and the menu assigned to it
 *
 * @since 3.0.0
 * @return array<string, int>
 */
function get_nav_menu_locations(): array
{
	$locations = get_theme_mod('nav_menu_locations');
	return is_array($locations) ? $locations : [];
}

/**
 * Whether a registered nav menu location has a menu assigned to it.
 *
 * @since 3.0.0
 * @param string $location Menu location identifier.
 * @return bool Whether location has a menu.
 */
function has_nav_menu(string $location): bool
{
	global $_wp_registered_nav_menus;

	if (!isset($_wp_registered_nav_menus[$location])) {
		return false;
	}

	$locations = get_nav_menu_locations();
	return !empty($locations[$location]);
}

/**
 * Determine whether the given ID is a nav menu item.
 *
 * @since 3.0.0
 *
 * @param int $menu_item_id The ID of the potential nav menu item.
 * @return bool Whether the given ID is that of a nav menu item.
 */
function is_nav_menu_item(int $menu_item_id = 0): bool
{
	return !$menu_item_id instanceof \WP_Error && get_post_type($menu_item_id) === 'nav_menu_item';
}

/**
 * Create a Navigation Menu.
 *
 * @since 3.0.0
 *
 * @param string $menu_name Menu name.
 * @return int|\WP_Error Menu ID on success, WP_Error object on failure.
 */
function wp_create_nav_menu(string $menu_name): int|\WP_Error
{
	return wp_update_nav_menu_object(0, ['menu-name' => $menu_name]);
}

/**
 * Delete a Navigation Menu.
 *
 * @since 3.0.0
 *
 * @param int|string $menu Menu ID, slug, or name.
 * @return bool|\WP_Error True on success, false or WP_Error object on failure.
 */
function wp_delete_nav_menu(int|string $menu): bool|\WP_Error
{
	$menu = wp_get_nav_menu_object($menu);
	if (!$menu || $menu instanceof \WP_Error) {
		return false;
	}

	$menu_objects = get_objects_in_term($menu->term_id, 'nav_menu');
	if (!empty($menu_objects)) {
		foreach ($menu_objects as $item) {
			wp_delete_post($item);
		}
	}

	$result = wp_delete_term($menu->term_id, 'nav_menu');

	// Remove this menu from any locations.
	$locations = get_nav_menu_locations();
	foreach ($locations as $location => $menu_id) {
		if ($menu_id === $menu->term_id) {
			$locations[$location] = 0;
		}
	}
	set_theme_mod('nav_menu_locations', $locations);

	if ($result && !$result instanceof \WP_Error) {
		/**
		 * Fires after a navigation menu has been successfully deleted.
		 *
		 * @since 3.0.0
		 *
		 * @param int $term_id ID of the deleted menu.
		 */
		do_action('wp_delete_nav_menu', $menu->term_id);
	}

	return $result;
}

/**
 * Save the properties of a menu or create a new menu with those properties.
 *
 * @since 3.0.0
 *
 * @param int   $menu_id   The ID of the menu or "0" to create a new menu.
 * @param array $menu_data The array of menu data.
 * @return int|\WP_Error Menu ID on success, WP_Error object on failure.
 */
function wp_update_nav_menu_object(int $menu_id = 0, array $menu_data = []): int|\WP_Error
{
	$_menu = wp_get_nav_menu_object($menu_id);

	$args = [
		'description' => $menu_data['description'] ?? '',
		'name'        => $menu_data['menu-name'] ?? '',
		'parent'      => (int) ($menu_data['parent'] ?? 0),
		'slug'        => null,
	];

	// double-check that we're not going to have one menu take the name of another
	$_possible_existing = get_term_by('name', $menu_data['menu-name'], 'nav_menu');
	if (
		$_possible_existing
		&& !$_possible_existing instanceof \WP_Error
		&& isset($_possible_existing->term_id)
		&& $_possible_existing->term_id != $menu_id
	) {
		return new \WP_Error('menu_exists', sprintf(__('The menu name <strong>%s</strong> conflicts with another menu name. Please try another.'), esc_html($menu_data['menu-name'])));
	}

	// menu doesn't already exist, so create a new menu
	if (!$_menu || $_menu instanceof \WP_Error) {
		$menu_exists = get_term_by('name', $menu_data['menu-name'], 'nav_menu');

		if ($menu_exists) {
			return new \WP_Error('menu_exists', sprintf(__('The menu name <strong>%s</strong> conflicts with another menu name. Please try another.'), esc_html($menu_data['menu-name'])));
		}

		$_menu = wp_insert_term($menu_data['menu-name'], 'nav_menu', $args);

		if ($_menu instanceof \WP_Error) {
			return $_menu;
		}

		/**
		 * Fires after a navigation menu is successfully created.
		 *
		 * @since 3.0.0
		 *
		 * @param int   $term_id   ID of the new menu.
		 * @param array $menu_data An array of menu data.
		 */
		do_action('wp_create_nav_menu', $_menu['term_id'], $menu_data);

		return (int) $_menu['term_id'];
	}

	if (!isset($_menu->term_id)) {
		return 0;
	}

	$menu_id = (int) $_menu->term_id;

	$update_response = wp_update_term($menu_id, 'nav_menu', $args);

	if ($update_response instanceof \WP_Error) {
		return $update_response;
	}

	/**
	 * Fires after a navigation menu has been successfully updated.
	 *
	 * @since 3.0.0
	 *
	 * @param int   $menu_id   ID of the updated menu.
	 * @param array $menu_data An array of menu data.
	 */
	do_action('wp_update_nav_menu', $menu_id, $menu_data);
	return $menu_id;
}

/**
 * Save the properties of a menu item or create a new one.
 *
 * @since 3.0.0
 *
 * @param int   $menu_id         The ID of the menu. Required. If "0", makes the menu item a draft orphan.
 * @param int   $menu_item_db_id The ID of the menu item. If "0", creates a new menu item.
 * @param array $menu_item_data  The menu item's data.
 * @return int|\WP_Error The menu item's database ID or WP_Error object on failure.
 */
function wp_update_nav_menu_item(int $menu_id = 0, int $menu_item_db_id = 0, array $menu_item_data = []): int|\WP_Error
{
	// make sure that we don't convert non-nav_menu_item objects into nav_menu_item objects
	if (!empty($menu_item_db_id) && !is_nav_menu_item($menu_item_db_id)) {
		return new \WP_Error('update_nav_menu_item_failed', __('The given object ID is not that of a menu item.'));
	}

	$menu = wp_get_nav_menu_object($menu_id);

	if (!$menu && $menu_id !== 0) {
		return new \WP_Error('invalid_menu_id', __('Invalid menu ID.'));
	}

	if ($menu instanceof \WP_Error) {
		return $menu;
	}

	$defaults = [
		'menu-item-db-id'       => $menu_item_db_id,
		'menu-item-object-id'   => 0,
		'menu-item-object'      => '',
		'menu-item-parent-id'   => 0,
		'menu-item-position'    => 0,
		'menu-item-type'        => 'custom',
		'menu-item-title'       => '',
		'menu-item-url'         => '',
		'menu-item-description' => '',
		'menu-item-attr-title'  => '',
		'menu-item-target'      => '',
		'menu-item-classes'     => '',
		'menu-item-xfn'         => '',
		'menu-item-status'      => '',
	];

	$args = wp_parse_args($menu_item_data, $defaults);

	if ($menu_id === 0) {
		$args['menu-item-position'] = 1;
	} elseif ((int) $args['menu-item-position'] === 0) {
		$menu_items = $menu_id === 0 ? [] : (array) wp_get_nav_menu_items($menu_id, ['post_status' => 'publish,draft']);
		$last_item = array_pop($menu_items);
		$args['menu-item-position'] = ($last_item && isset($last_item->menu_order)) ? 1 + $last_item->menu_order : count($menu_items);
	}

	$original_parent = $menu_item_db_id > 0 ? get_post_field('post_parent', $menu_item_db_id) : 0;

	if ($args['menu-item-type'] !== 'custom') {
		/* if non-custom menu item, then:
			* use original object's URL
			* blank default title to sync with original object's
		*/
		$args['menu-item-url'] = '';
		$original_title = '';

		if ($args['menu-item-type'] === 'taxonomy') {
			$original_parent = get_term_field('parent', $args['menu-item-object-id'], $args['menu-item-object'], 'raw');
			$original_title = get_term_field('name', $args['menu-item-object-id'], $args['menu-item-object'], 'raw');
		} elseif ($args['menu-item-type'] === 'post_type') {
			$original_object = get_post($args['menu-item-object-id']);
			$original_parent = (int) $original_object->post_parent;
			$original_title = $original_object->post_title;
		}

		if ($args['menu-item-title'] === $original_title) {
			$args['menu-item-title'] = '';
		}

		// hack to get wp to create a post object when too many properties are empty
		if ($args['menu-item-title'] === '' && $args['menu-item-description'] === '') {
			$args['menu-item-description'] = ' ';
		}
	}

	$post = [
		'menu_order'   => $args['menu-item-position'],
		'ping_status'  => 0,
		'post_content' => $args['menu-item-description'],
		'post_excerpt' => $args['menu-item-attr-title'],
		'post_parent'  => $original_parent,
		'post_title'   => $args['menu-item-title'],
		'post_type'    => 'nav_menu_item',
	];

	$update = $menu_item_db_id !== 0;

	if (!$update) {
		$post['ID'] = 0;
		$post['post_status'] = $args['menu-item-status'] === 'publish' ? 'publish' : 'draft';
		$menu_item_db_id = wp_insert_post($post);
		if (!$menu_item_db_id || $menu_item_db_id instanceof \WP_Error) {
			return $menu_item_db_id;
		}
	}

	if ($menu_id && (!$update || !is_object_in_term($menu_item_db_id, 'nav_menu', (int) $menu->term_id))) {
		wp_set_object_terms($menu_item_db_id, [$menu->term_id], 'nav_menu');
	}

	if ($args['menu-item-type'] === 'custom') {
		$args['menu-item-object-id'] = $menu_item_db_id;
		$args['menu-item-object'] = 'custom';
	}

	update_post_meta($menu_item_db_id, '_menu_item_type', sanitize_key($args['menu-item-type']));
	update_post_meta($menu_item_db_id, '_menu_item_menu_item_parent', (string) (int) $args['menu-item-parent-id']);
	update_post_meta($menu_item_db_id, '_menu_item_object_id', (string) (int) $args['menu-item-object-id']);
	update_post_meta($menu_item_db_id, '_menu_item_object', sanitize_key($args['menu-item-object']));
	update_post_meta($menu_item_db_id, '_menu_item_target', sanitize_key($args['menu-item-target']));

	$args['menu-item-classes'] = array_map('sanitize_html_class', explode(' ', $args['menu-item-classes']));
	$args['menu-item-xfn'] = implode(' ', array_map('sanitize_html_class', explode(' ', $args['menu-item-xfn'])));
	update_post_meta($menu_item_db_id, '_menu_item_classes', $args['menu-item-classes']);
	update_post_meta($menu_item_db_id, '_menu_item_xfn', $args['menu-item-xfn']);
	update_post_meta($menu_item_db_id, '_menu_item_url', esc_url_raw($args['menu-item-url']));

	if ($menu_id === 0) {
		update_post_meta($menu_item_db_id, '_menu_item_orphaned', (string) time());
	} elseif (get_post_meta($menu_item_db_id, '_menu_item_orphaned')) {
		delete_post_meta($menu_item_db_id, '_menu_item_orphaned');
	}

	if ($update) {
		$post['ID'] = $menu_item_db_id;
		$post['post_status'] = $args['menu-item-status'] === 'draft' ? 'draft' : 'publish';
		wp_update_post($post);
	}

	/**
	 * Fires after a navigation menu item has been updated.
	 *
	 * @since 3.0.0
	 *
	 * @see wp_update_nav_menu_items()
	 *
	 * @param int   $menu_id         ID of the updated menu.
	 * @param int   $menu_item_db_id ID of the updated menu item.
	 * @param array $args            An array of arguments used to update a menu item.
	 */
	do_action('wp_update_nav_menu_item', $menu_id, $menu_item_db_id, $args);

	return $menu_item_db_id;
}

/**
 * Returns all navigation menu objects.
 *
 * @since 3.0.0
 *
 * @param array $args Array of arguments passed on to get_terms().
 * @return array|\WP_Error Menu objects.
 */
function wp_get_nav_menus(array $args = []): array|\WP_Error
{
	$defaults = ['hide_empty' => false, 'orderby' => 'none'];
	$args = wp_parse_args($args, $defaults);

	/**
	 * Filter the navigation menu objects being returned.
	 *
	 * @since 3.0.0
	 *
	 * @see get_terms()
	 *
	 * @param array|\WP_Error $menus An array of menu objects or a WP_Error on failure.
	 * @param array           $args  An array of arguments used to retrieve menu objects.
	 */
	return apply_filters('wp_get_nav_menus', get_terms('nav_menu', $args), $args);
}

/**
 * Sort menu items by the desired key.
 *
 * @since 3.0.0
 * @access private
 *
 * @param object $a The first object to compare
 * @param object $b The second object to compare
 * @return int -1, 0, or 1 if $a is considered to be respectively less than, equal to, or greater than $b.
 */
function _sort_nav_menu_items(object $a, object $b): int
{
	global $_menu_item_sort_prop;

	if (empty($_menu_item_sort_prop)) {
		return 0;
	}

	if (!isset($a->$_menu_item_sort_prop, $b->$_menu_item_sort_prop)) {
		return 0;
	}

	if ($a->$_menu_item_sort_prop == $b->$_menu_item_sort_prop) {
		return 0;
	}

	return $a->$_menu_item_sort_prop < $b->$_menu_item_sort_prop ? -1 : 1;
}

/**
 * Returns if a menu item is valid. Bug #13958
 *
 * @since 3.2.0
 * @access private
 *
 * @param object $item The menu item to check
 * @return bool false if invalid, else true.
 */
function _is_valid_nav_menu_item(object $item): bool
{
	return empty($item->_invalid);
}

/**
 * Returns all menu items of a navigation menu.
 *
 * @since 3.0.0
 *
 * @param int|string $menu menu name, id, or slug
 * @param array      $args
 * @return array|false Array of menu items, else false.
 */
function wp_get_nav_menu_items(int|string $menu, array $args = []): array|false
{
	$menu = wp_get_nav_menu_object($menu);

	if (!$menu || $menu instanceof \WP_Error) {
		return false;
	}

	static $fetched = [];

	$items = get_objects_in_term($menu->term_id, 'nav_menu');

	if (empty($items)) {
		return $items;
	}

	$defaults = [
		'order'       => 'ASC',
		'orderby'     => 'menu_order',
		'post_type'   => 'nav_menu_item',
		'post_status' => 'publish',
		'output'      => ARRAY_A,
		'output_key'  => 'menu_order',
		'nopaging'    => true,
	];
	$args = wp_parse_args($args, $defaults);
	$args['include'] = $items;

	$items = get_posts($args);

	if ($items instanceof \WP_Error || !is_array($items)) {
		return false;
	}

	// Get all posts and terms at once to prime the caches
	if (empty($fetched[$menu->term_id]) || wp_using_ext_object_cache()) {
		$fetched[$menu->term_id] = true;
		$posts = [];
		$terms = [];
		foreach ($items as $item) {
			$object_id = get_post_meta($item->ID, '_menu_item_object_id', true);
			$object    = get_post_meta($item->ID, '_menu_item_object', true);
			$type      = get_post_meta($item->ID, '_menu_item_type', true);

			if ($type === 'post_type') {
				$posts[$object][] = $object_id;
			} elseif ($type === 'taxonomy') {
				$terms[$object][] = $object_id;
			}
		}

		if (!empty($posts)) {
			foreach (array_keys($posts) as $post_type) {
				get_posts(['post__in' => $posts[$post_type], 'post_type' => $post_type, 'nopaging' => true, 'update_post_term_cache' => false]);
			}
		}
		unset($posts);

		if (!empty($terms)) {
			foreach (array_keys($terms) as $taxonomy) {
				get_terms($taxonomy, ['include' => $terms[$taxonomy]]);
			}
		}
		unset($terms);
	}

	$items = array_map('wp_setup_nav_menu_item', $items);

	if (!is_admin()) { // Remove invalid items only in frontend
		$items = array_filter($items, '_is_valid_nav_menu_item');
	}

	if (ARRAY_A === $args['output']) {
		$GLOBALS['_menu_item_sort_prop'] = $args['output_key'];
		usort($items, '_sort_nav_menu_items');
		$i = 1;
		foreach ($items as $k => $item) {
			$items[$k]->{$args['output_key']} = $i++;
		}
	}

	/**
	 * Filter the navigation menu items being returned.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $items An array of menu item post objects.
	 * @param object $menu  The menu object.
	 * @param array  $args  An array of arguments used to retrieve menu item objects.
	 */
	return apply_filters('wp_get_nav_menu_items', $items, $menu, $args);
}

/**
 * Decorates a menu item object with the shared navigation menu item properties.
 *
 * @since 3.0.0
 *
 * @param object $menu_item The menu item to modify.
 * @return object The menu item with standard menu item properties.
 */
function wp_setup_nav_menu_item(object $menu_item): object
{
	if (isset($menu_item->post_type)) {
		if ($menu_item->post_type === 'nav_menu_item') {
			$menu_item->db_id = (int) $menu_item->ID;
			$menu_item->menu_item_parent ??= get_post_meta($menu_item->ID, '_menu_item_menu_item_parent', true);
			$menu_item->object_id ??= get_post_meta($menu_item->ID, '_menu_item_object_id', true);
			$menu_item->object ??= get_post_meta($menu_item->ID, '_menu_item_object', true);
			$menu_item->type ??= get_post_meta($menu_item->ID, '_menu_item_type', true);

			if ($menu_item->type === 'post_type') {
				$object = get_post_type_object($menu_item->object);
				if ($object) {
					$menu_item->type_label = $object->labels->singular_name;
				} else {
					$menu_item->type_label = $menu_item->object;
					$menu_item->_invalid = true;
				}

				$menu_item->url = get_permalink($menu_item->object_id);
				$original_object = get_post($menu_item->object_id);
				$original_title = $original_object->post_title;
				$menu_item->title = $menu_item->post_title === '' ? $original_title : $menu_item->post_title;
			} elseif ($menu_item->type === 'taxonomy') {
				$object = get_taxonomy($menu_item->object);
				if ($object) {
					$menu_item->type_label = $object->labels->singular_name;
				} else {
					$menu_item->type_label = $menu_item->object;
					$menu_item->_invalid = true;
				}

				$term_url = get_term_link((int) $menu_item->object_id, $menu_item->object);
				$menu_item->url = !$term_url instanceof \WP_Error ? $term_url : '';
				$original_title = get_term_field('name', $menu_item->object_id, $menu_item->object, 'raw');
				if ($original_title instanceof \WP_Error) {
					$original_title = false;
				}
				$menu_item->title = $menu_item->post_title === '' ? $original_title : $menu_item->post_title;
			} else {
				$menu_item->type_label = __('Custom');
				$menu_item->title = $menu_item->post_title;
				$menu_item->url ??= get_post_meta($menu_item->ID, '_menu_item_url', true);
			}

			$menu_item->target ??= get_post_meta($menu_item->ID, '_menu_item_target', true);
			$menu_item->attr_title ??= apply_filters('nav_menu_attr_title', $menu_item->post_excerpt);
			$menu_item->description ??= apply_filters('nav_menu_description', wp_trim_words($menu_item->post_content, 200));
			$menu_item->classes ??= (array) get_post_meta($menu_item->ID, '_menu_item_classes', true);
			$menu_item->xfn ??= get_post_meta($menu_item->ID, '_menu_item_xfn', true);
		} else {
			$menu_item->db_id = 0;
			$menu_item->menu_item_parent = 0;
			$menu_item->object_id = (int) $menu_item->ID;
			$menu_item->type = 'post_type';
			$object = get_post_type_object($menu_item->post_type);
			$menu_item->object = $object->name;
			$menu_item->type_label = $object->labels->singular_name;
			$menu_item->title = $menu_item->post_title ?: sprintf(__('#%d (no title)'), $menu_item->ID);
			$menu_item->url = get_permalink($menu_item->ID);
			$menu_item->target = '';
			$menu_item->attr_title = apply_filters('nav_menu_attr_title', '');
			$menu_item->description = apply_filters('nav_menu_description', '');
			$menu_item->classes = [];
			$menu_item->xfn = '';
		}
	} elseif (isset($menu_item->taxonomy)) {
		$menu_item->ID = $menu_item->term_id;
		$menu_item->db_id = 0;
		$menu_item->menu_item_parent = 0;
		$menu_item->object_id = (int) $menu_item->term_id;
		$menu_item->post_parent = (int) $menu_item->parent;
		$menu_item->type = 'taxonomy';
		$object = get_taxonomy($menu_item->taxonomy);
		$menu_item->object = $object->name;
		$menu_item->type_label = $object->labels->singular_name;
		$menu_item->title = $menu_item->name;
		$menu_item->url = get_term_link($menu_item, $menu_item->taxonomy);
		$menu_item->target = '';
		$menu_item->attr_title = '';
		$menu_item->description = get_term_field('description', $menu_item->term_id, $menu_item->taxonomy);
		$menu_item->classes = [];
		$menu_item->xfn = '';
	}

	/**
	 * Filter a navigation menu item object.
	 *
	 * @since 3.0.0
	 *
	 * @param object $menu_item The menu item object.
	 */
	return apply_filters('wp_setup_nav_menu_item', $menu_item);
}

/**
 * Get the menu items associated with a particular object.
 *
 * @since 3.0.0
 *
 * @param int    $object_id   The ID of the original object.
 * @param string $object_type The type of object, such as "taxonomy" or "post_type."
 * @param string $taxonomy    If $object_type is "taxonomy", $taxonomy is the name of the tax that $object_id belongs to
 * @return int[] The array of menu item IDs; empty array if none;
 */
function wp_get_associated_nav_menu_items(int $object_id = 0, string $object_type = 'post_type', string $taxonomy = ''): array
{
	$query = new \WP_Query();
	$menu_items = $query->query([
		'meta_key'       => '_menu_item_object_id',
		'meta_value'     => $object_id,
		'post_status'    => 'any',
		'post_type'      => 'nav_menu_item',
		'posts_per_page' => -1,
	]);

	$menu_item_ids = [];
	foreach ($menu_items as $menu_item) {
		if (isset($menu_item->ID) && is_nav_menu_item($menu_item->ID)) {
			$menu_item_type = get_post_meta($menu_item->ID, '_menu_item_type', true);
			if (
				$object_type === 'post_type' &&
				$menu_item_type === 'post_type'
			) {
				$menu_item_ids[] = (int) $menu_item->ID;
			} elseif (
				$object_type === 'taxonomy' &&
				$menu_item_type === 'taxonomy' &&
				get_post_meta($menu_item->ID, '_menu_item_object', true) === $taxonomy
			) {
				$menu_item_ids[] = (int) $menu_item->ID;
			}
		}
	}

	return array_unique($menu_item_ids);
}

/**
 * Callback for handling a menu item when its original object is deleted.
 *
 * @since 3.0.0
 * @access private
 *
 * @param int $object_id The ID of the original object being trashed.
 */
function _wp_delete_post_menu_item(int $object_id = 0): void
{
	$menu_item_ids = wp_get_associated_nav_menu_items($object_id, 'post_type');

	foreach ($menu_item_ids as $menu_item_id) {
		wp_delete_post($menu_item_id, true);
	}
}

/**
 * Callback for handling a menu item when its original object is deleted.
 *
 * @since 3.0.0
 * @access private
 *
 * @param int    $object_id The ID of the original object being trashed.
 * @param int    $tt_id     Term Taxonomy ID.
 * @param string $taxonomy  Taxonomy slug.
 */
function _wp_delete_tax_menu_item(int $object_id, int $tt_id, string $taxonomy): void
{
	$menu_item_ids = wp_get_associated_nav_menu_items($object_id, 'taxonomy', $taxonomy);

	foreach ($menu_item_ids as $menu_item_id) {
		wp_delete_post($menu_item_id, true);
	}
}

/**
 * Automatically add newly published page objects to menus with that as an option.
 *
 * @since 3.0.0
 * @access private
 *
 * @param string   $new_status The new status of the post object.
 * @param string   $old_status The old status of the post object.
 * @param \WP_Post $post       The post object being transitioned from one status to another.
 */
function _wp_auto_add_pages_to_menu(string $new_status, string $old_status, \WP_Post $post): void
{
	if (
		$new_status !== 'publish'
		|| $old_status === 'publish'
		|| $post->post_type !== 'page'
		|| !empty($post->post_parent)
	) {
		return;
	}

	$options = get_option('nav_menu_options');
	$auto_add = $options['auto_add'] ?? [];

	if (empty($auto_add) || !is_array($auto_add)) {
		return;
	}

	$args = [
		'menu-item-object-id' => $post->ID,
		'menu-item-object'    => $post->post_type,
		'menu-item-type'      => 'post_type',
		'menu-item-status'    => 'publish',
	];

	foreach ($auto_add as $menu_id) {
		$items = wp_get_nav_menu_items($menu_id, ['post_status' => 'publish,draft']);
		if (!is_array($items)) {
			continue;
		}
		foreach ($items as $item) {
			if ($post->ID == $item->object_id) {
				continue 2;
			}
		}
		wp_update_nav_menu_item($menu_id, 0, $args);
	}
}