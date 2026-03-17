<?php
declare(strict_types=1);

/**
 * BackPress Scripts enqueue
 *
 * Classes were refactored from the WP_Scripts and WordPress script enqueue API.
 *
 * @since BackPress r74
 *
 * @package BackPress
 * @uses _WP_Dependency
 * @since r74
 */
class WP_Dependencies
{
	/**
	 * An array of registered handle objects.
	 *
	 * @access public
	 * @since 2.6.8
	 * @var array
	 */
	public array $registered = [];

	/**
	 * An array of queued _WP_Dependency handle objects.
	 *
	 * @access public
	 * @since 2.6.8
	 * @var array
	 */
	public array $queue = [];

	/**
	 * An array of _WP_Dependency handle objects to queue.
	 *
	 * @access public
	 * @since 2.6.0
	 * @var array
	 */
	public array $to_do = [];

	/**
	 * An array of _WP_Dependency handle objects already queued.
	 *
	 * @access public
	 * @since 2.6.0
	 * @var array
	 */
	public array $done = [];

	/**
	 * An array of additional arguments passed when a handle is registered.
	 *
	 * Arguments are appended to the item query string.
	 *
	 * @access public
	 * @since 2.6.0
	 * @var array
	 */
	public array $args = [];

	/**
	 * An array of handle groups to enqueue.
	 *
	 * @access public
	 * @since 2.8.0
	 * @var array
	 */
	public array $groups = [];

	/**
	 * A handle group to enqueue.
	 *
	 * @access public
	 * @since 2.8.0
	 * @var int
	 */
	public int $group = 0;

	/**
	 * Process the items and dependencies.
	 *
	 * Processes the items passed to it or the queue, and their dependencies.
	 *
	 * @access public
	 * @since 2.1.0
	 *
	 * @param string|array|false $handles Optional. Items to be processed: Process queue (false), process item (string), process items (array of strings).
	 * @param int|false          $group   Group level: level (int), no groups (false).
	 * @return array Handles of items that have been processed.
	 */
	public function do_items(string|array|false $handles = false, int|false $group = false): array
	{
		/**
		 * If nothing is passed, print the queue. If a string is passed,
		 * print that item. If an array is passed, print those items.
		 */
		$handles = false === $handles ? $this->queue : (array) $handles;
		$this->all_deps($handles);

		foreach ($this->to_do as $key => $handle) {
			if (!in_array($handle, $this->done, true) && isset($this->registered[$handle])) {

				/**
				 * A single item may alias a set of items, by having dependencies,
				 * but no source. Queuing the item queues the dependencies.
				 *
				 * Example: The extending class WP_Scripts is used to register 'scriptaculous' as a set of registered handles:
				 *   <code>add( 'scriptaculous', false, array( 'scriptaculous-dragdrop', 'scriptaculous-slider', 'scriptaculous-controls' ) );</code>
				 *
				 * The src property is false.
				 **/
				if (!$this->registered[$handle]->src) {
					$this->done[] = $handle;
					continue;
				}

				/**
				 * Attempt to process the item. If successful,
				 * add the handle to the done array.
				 *
				 * Unset the item from the to_do array.
				 */
				if ($this->do_item($handle, $group)) {
					$this->done[] = $handle;
				}

				unset($this->to_do[$key]);
			}
		}

		return $this->done;
	}

	/**
	 * Process a dependency.
	 *
	 * @access public
	 * @since 2.6.0
	 *
	 * @param string     $handle Name of the item. Should be unique.
	 * @param int|false  $group  Group level.
	 * @return bool True on success, false if not set.
	 */
	public function do_item(string $handle, int|false $group = false): bool
	{
		return isset($this->registered[$handle]);
	}

	/**
	 * Determine dependencies.
	 *
	 * Recursively builds an array of items to process taking
	 * dependencies into account. Does NOT catch infinite loops.
	 *
	 * @access public
	 * @since 2.1.0
	 *
	 * @param string|array $handles   Item handle and argument (string) or item handles and arguments (array of strings).
	 * @param bool         $recursion Internal flag that function is calling itself.
	 * @param int|false    $group     Group level: (int) level, (false) no groups.
	 * @return bool True on success, false on failure.
	 */
	public function all_deps(string|array $handles, bool $recursion = false, int|false $group = false): bool
	{
		$handles = (array) $handles;
		if ([] === $handles) {
			return false;
		}

		foreach ($handles as $handle) {
			$handle_parts = explode('?', (string) $handle);
			$handle = $handle_parts[0];
			$queued = in_array($handle, $this->to_do, true);

			if (in_array($handle, $this->done, true)) { // Already done
				continue;
			}

			$moved = $this->set_group($handle, $recursion, $group);

			if ($queued && !$moved) { // already queued and in the right group
				continue;
			}

			$keep_going = true;
			if (!isset($this->registered[$handle])) {
				$keep_going = false; // Item doesn't exist.
			} elseif ($this->registered[$handle]->deps && array_diff($this->registered[$handle]->deps, array_keys($this->registered))) {
				$keep_going = false; // Item requires dependencies that don't exist.
			} elseif ($this->registered[$handle]->deps && !$this->all_deps($this->registered[$handle]->deps, true, $group)) {
				$keep_going = false; // Item requires dependencies that don't exist.
			}

			if (!$keep_going) { // Either item or its dependencies don't exist.
				if ($recursion) {
					return false; // Abort this branch.
				}

				continue; // We're at the top level. Move on to the next one.
			}

			if ($queued) { // Already grabbed it and its dependencies.
				continue;
			}

			if (isset($handle_parts[1])) {
				$this->args[$handle] = $handle_parts[1];
			}

			$this->to_do[] = $handle;
		}

		return true;
	}

	/**
	 * Register an item.
	 *
	 * Registers the item if no item of that name already exists.
	 *
	 * @access public
	 * @since 2.1.0
	 *
	 * @param string                $handle Unique item name.
	 * @param string|false|null     $src    The item url.
	 * @param array                 $deps   Optional. An array of item handle strings on which this item depends.
	 * @param string|false|null     $ver    Optional. Version (used for cache busting).
	 * @param mixed                 $args   Optional. Custom property of the item. NOT the class property $args. Examples: $media, $in_footer.
	 * @return bool True on success, false on failure.
	 */
	public function add(string $handle, string|false|null $src, array $deps = [], string|false|null $ver = false, mixed $args = null): bool
	{
		if (isset($this->registered[$handle])) {
			return false;
		}

		$this->registered[$handle] = new _WP_Dependency($handle, $src, $deps, $ver, $args);

		return true;
	}

	/**
	 * Add extra item data.
	 *
	 * Adds data to a registered item.
	 *
	 * @access public
	 * @since 2.6.0
	 *
	 * @param string $handle Name of the item. Should be unique.
	 * @param string $key    The data key.
	 * @param mixed  $value  The data value.
	 * @return bool True on success, false on failure.
	 */
	public function add_data(string $handle, string $key, mixed $value): bool
	{
		if (!isset($this->registered[$handle])) {
			return false;
		}

		return $this->registered[$handle]->add_data($key, $value);
	}

	/**
	 * Get extra item data.
	 *
	 * Gets data associated with a registered item.
	 *
	 * @access public
	 * @since 3.3.0
	 *
	 * @param string $handle Name of the item. Should be unique.
	 * @param string $key    The data key.
	 * @return mixed Extra item data (string), false otherwise.
	 */
	public function get_data(string $handle, string $key): mixed
	{
		if (!isset($this->registered[$handle])) {
			return false;
		}

		if (!isset($this->registered[$handle]->extra[$key])) {
			return false;
		}

		return $this->registered[$handle]->extra[$key];
	}

	/**
	 * Un-register an item or items.
	 *
	 * @access public
	 * @since 2.1.0
	 *
	 * @param string|array $handles Item handle and argument (string) or item handles and arguments (array of strings).
	 * @return void
	 */
	public function remove(string|array $handles): void
	{
		foreach ((array) $handles as $handle) {
			unset($this->registered[$handle]);
		}
	}

	/**
	 * Queue an item or items.
	 *
	 * Decodes handles and arguments, then queues handles and stores
	 * arguments in the class property $args. For example in extending
	 * classes, $args is appended to the item url as a query string.
	 * Note $args is NOT the $args property of items in the $registered array.
	 *
	 * @access public
	 * @since 2.1.0
	 *
	 * @param string|array $handles Item handle and argument (string) or item handles and arguments (array of strings).
	 */
	public function enqueue(string|array $handles): void
	{
		foreach ((array) $handles as $handle) {
			$handleParts = explode('?', (string) $handle);
			$normalizedHandle = $handleParts[0];
			if (!in_array($normalizedHandle, $this->queue, true) && isset($this->registered[$normalizedHandle])) {
				$this->queue[] = $normalizedHandle;
				if (isset($handleParts[1])) {
					$this->args[$normalizedHandle] = $handleParts[1];
				}
			}
		}
	}

	/**
	 * Dequeue an item or items.
	 *
	 * Decodes handles and arguments, then dequeues handles
	 * and removes arguments from the class property $args.
	 *
	 * @access public
	 * @since 2.1.0
	 *
	 * @param string|array $handles Item handle and argument (string) or item handles and arguments (array of strings).
	 */
	public function dequeue(string|array $handles): void
	{
		foreach ((array) $handles as $handle) {
			$handleParts = explode('?', (string) $handle);
			$normalizedHandle = $handleParts[0];
			$key = array_search($normalizedHandle, $this->queue, true);
			if (false !== $key) {
				unset($this->queue[$key], $this->args[$normalizedHandle]);
			}
		}
	}

	/**
	 * Recursively search the passed dependency tree for $handle
	 *
	 * @since 4.0.0
	 *
	 * @param array  $queue  An array of queued _WP_Dependency handle objects.
	 * @param string $handle Name of the item. Should be unique.
	 * @return boolean Whether the handle is found after recursively searching the dependency tree.
	 */
	protected function recurse_deps(array $queue, string $handle): bool
	{
		foreach ($queue as $queued) {
			if (!isset($this->registered[$queued])) {
				continue;
			}

			if (in_array($handle, $this->registered[$queued]->deps, true)) {
				return true;
			}

			if ($this->recurse_deps($this->registered[$queued]->deps, $handle)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Query list for an item.
	 *
	 * @access public
	 * @since 2.1.0
	 *
	 * @param string $handle Name of the item. Should be unique.
	 * @param string $list   Property name of list array.
	 * @return bool|_WP_Dependency Found, or object Item data.
	 */
	public function query(string $handle, string $list = 'registered'): bool|_WP_Dependency
	{
		return match ($list) {
			'registered', 'scripts' => $this->registered[$handle] ?? false,
			'enqueued', 'queue' => in_array($handle, $this->queue, true) || $this->recurse_deps($this->queue, $handle),
			'to_do', 'to_print' => in_array($handle, $this->to_do, true),
			'done', 'printed' => in_array($handle, $this->done, true),
			default => false,
		};
	}

	/**
	 * Set item group, unless already in a lower group.
	 *
	 * @access public
	 * @since 2.8.0
	 *
	 * @param string    $handle    Name of the item. Should be unique.
	 * @param bool      $recursion Internal flag that calling function was called recursively.
	 * @param int|false $group     Group level.
	 * @return bool Not already in the group or a lower group
	 */
	public function set_group(string $handle, bool $recursion, int|false $group): bool
	{
		$group = (int) $group;

		if ($recursion) {
			$group = min($this->group, $group);
		} else {
			$this->group = $group;
		}

		if (isset($this->groups[$handle]) && $this->groups[$handle] <= $group) {
			return false;
		}

		$this->groups[$handle] = $group;
		return true;
	}
}

/**
 * Class _WP_Dependency
 *
 * Helper class to register a handle and associated data.
 *
 * @access private
 * @since 2.6.0
 */
class _WP_Dependency
{
	/**
	 * The handle name.
	 *
	 * @access public
	 * @since 2.6.0
	 * @var null
	 */
	public ?string $handle;

	/**
	 * The handle source.
	 *
	 * @access public
	 * @since 2.6.0
	 * @var null
	 */
	public string|false|null $src;

	/**
	 * An array of handle dependencies.
	 *
	 * @access public
	 * @since 2.6.0
	 * @var array
	 */
	public array $deps;

	/**
	 * The handle version.
	 *
	 * Used for cache-busting.
	 *
	 * @access public
	 * @since 2.6.0
	 * @var bool|string
	 */
	public string|false|null $ver;

	/**
	 * Additional arguments for the handle.
	 *
	 * @access public
	 * @since 2.6.0
	 * @var null
	 */
	public mixed $args;  // Custom property, such as $in_footer or $media.

	/**
	 * Extra data to supply to the handle.
	 *
	 * @access public
	 * @since 2.6.0
	 * @var array
	 */
	public array $extra = [];

	/**
	 * Setup dependencies.
	 *
	 * @since 2.6.0
	 */
	public function __construct(?string $handle = null, string|false|null $src = null, array $deps = [], string|false|null $ver = false, mixed $args = null)
	{
		$this->handle = $handle;
		$this->src = $src;
		$this->deps = $deps;
		$this->ver = $ver;
		$this->args = $args;
	}

	/**
	 * Add handle data.
	 *
	 * @access public
	 * @since 2.6.0
	 *
	 * @param string $name The data key to add.
	 * @param mixed  $data The data value to add.
	 * @return bool False if not scalar, true otherwise.
	 */
	public function add_data(string $name, mixed $data): bool
	{
		if (!is_scalar($name)) {
			return false;
		}

		$this->extra[$name] = $data;
		return true;
	}
}
?>