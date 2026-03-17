<?php

declare(strict_types=1);

/**
 * BackPress Scripts enqueue.
 *
 * These classes were refactored from the WordPress WP_Scripts and WordPress
 * script enqueue API.
 *
 * @package BackPress
 * @since r16
 */

/**
 * BackPress Scripts enqueue class.
 *
 * @package BackPress
 * @uses WP_Dependencies
 * @since r16
 */
class WP_Scripts extends WP_Dependencies
{
	public string $base_url = ''; // Full URL with trailing slash
	public string $content_url = '';
	public string $default_version = '';
	public array $in_footer = [];
	public string $concat = '';
	public string $concat_version = '';
	public bool $do_concat = false;
	public string $print_html = '';
	public string $print_code = '';
	public string $ext_handles = '';
	public string $ext_version = '';
	public ?array $default_dirs = null;

	public function __construct()
	{
		$this->init();
		add_action('init', [$this, 'init'], 0);
	}

	public function init(): void
	{
		/**
		 * Fires when the WP_Scripts instance is initialized.
		 *
		 * @since 2.6.0
		 *
		 * @param WP_Scripts $this WP_Scripts instance, passed by reference.
		 */
		do_action_ref_array('wp_default_scripts', [&$this]);
	}

	/**
	 * Prints scripts.
	 *
	 * Prints the scripts passed to it or the print queue. Also prints all necessary dependencies.
	 *
	 * @param array|string|false $handles (Optional) Scripts to be printed. Default false.
	 *                                    (false) prints queue, (string) prints that script, (array) prints those scripts.
	 * @param int|false          $group   (Optional) If scripts were queued in groups, prints this group number. Default false.
	 * @return array Scripts that have been printed.
	 */
	public function print_scripts(array|string|false $handles = false, int|false $group = false): array
	{
		return $this->do_items($handles, $group);
	}

	/**
	 * @deprecated 3.3.0 Use print_extra_script()
	 */
	#[Deprecated(since: '3.3', replacement: 'print_extra_script()')]
	public function print_scripts_l10n(string $handle, bool $echo = true): bool|string|null
	{
		_deprecated_function(__FUNCTION__, '3.3', 'print_extra_script()');
		return $this->print_extra_script($handle, $echo);
	}

	public function print_extra_script(string $handle, bool $echo = true): bool|string|null
	{
		$output = $this->get_data($handle, 'data');
		if (!$output) {
			return null;
		}

		if (!$echo) {
			return $output;
		}

		echo "<script type='text/javascript'>\n"; // CDATA and type='text/javascript' is not needed for HTML 5
		echo "/* <![CDATA[ */\n";
		echo "$output\n";
		echo "/* ]]> */\n";
		echo "</script>\n";

		return true;
	}

	public function do_item(string $handle, int|false $group = false): bool
	{
		if (!parent::do_item($handle)) {
			return false;
		}

		if ($group === 0 && $this->groups[$handle] > 0) {
			$this->in_footer[] = $handle;
			return false;
		}

		if ($group === false && in_array($handle, $this->in_footer, true)) {
			$this->in_footer = array_diff($this->in_footer, [$handle]);
		}

		$registered_ver = $this->registered[$handle]->ver;
		$ver = ($registered_ver === null)
			? ''
			: ($registered_ver ?: $this->default_version);

		if (isset($this->args[$handle])) {
			$ver = $ver ? $ver . '&amp;' . $this->args[$handle] : $this->args[$handle];
		}

		$src = $this->registered[$handle]->src;

		if ($this->do_concat) {
			/**
			 * Filter the script loader source.
			 *
			 * @since 2.2.0
			 *
			 * @param string $src    Script loader source path.
			 * @param string $handle Script handle.
			 */
			$srce = apply_filters('script_loader_src', $src, $handle);
			if ($this->in_default_dir($srce)) {
				$this->print_code .= $this->print_extra_script($handle, false);
				$this->concat .= "$handle,";
				$this->concat_version .= "$handle$ver";
				return true;
			}

			$this->ext_handles .= "$handle,";
			$this->ext_version .= "$handle$ver";
		}

		$this->print_extra_script($handle);

		if (!preg_match('|^(https?:)?//|', $src) && !($this->content_url && str_starts_with($src, $this->content_url))) {
			$src = $this->base_url . $src;
		}

		if (!empty($ver)) {
			$src = add_query_arg('ver', $ver, $src);
		}

		/** This filter is documented in wp-includes/class.wp-scripts.php */
		$src = esc_url(apply_filters('script_loader_src', $src, $handle));

		if (!$src) {
			return true;
		}

		$script_tag = "<script type='text/javascript' src='$src'></script>\n";

		if ($this->do_concat) {
			$this->print_html .= $script_tag;
		} else {
			echo $script_tag;
		}

		return true;
	}

	/**
	 * Localizes a script.
	 *
	 * Localizes only if the script has already been added.
	 */
	public function localize(string $handle, string $object_name, array $l10n): bool
	{
		if ($handle === 'jquery') {
			$handle = 'jquery-core';
		}

		$after = '';
		if (isset($l10n['l10n_print_after'])) { // back compat
			$after = (string) $l10n['l10n_print_after'];
			unset($l10n['l10n_print_after']);
		}

		foreach ($l10n as $key => $value) {
			if (!is_scalar($value)) {
				continue;
			}

			$l10n[$key] = html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
		}

		$script = "var $object_name = " . json_encode($l10n) . ';';

		if ($after !== '') {
			$script .= "\n$after;";
		}

		$data = $this->get_data($handle, 'data');

		if (!empty($data)) {
			$script = "$data\n$script";
		}

		return $this->add_data($handle, 'data', $script);
	}

	public function set_group(string $handle, bool $recursion, int|false $group = false): bool
	{
		$grp = ($this->registered[$handle]->args === 1)
			? 1
			: (int) $this->get_data($handle, 'group');

		if ($group !== false && $grp > $group) {
			$grp = $group;
		}

		return parent::set_group($handle, $recursion, $grp);
	}

	public function all_deps(array|string $handles, bool $recursion = false, int|false $group = false): array
	{
		$r = parent::all_deps($handles, $recursion);
		if (!$recursion) {
			/**
			 * Filter the list of script dependencies left to print.
			 *
			 * @since 2.3.0
			 *
			 * @param array $to_do An array of script dependencies.
			 */
			$this->to_do = apply_filters('print_scripts_array', $this->to_do);
		}
		return $r;
	}

	public function do_head_items(): array
	{
		$this->do_items(false, 0);
		return $this->done;
	}

	public function do_footer_items(): array
	{
		$this->do_items(false, 1);
		return $this->done;
	}

	public function in_default_dir(string $src): bool
	{
		if ($this->default_dirs === null) {
			return true;
		}

		if (str_starts_with($src, '/' . WPINC . '/js/l10n')) {
			return false;
		}

		foreach ($this->default_dirs as $test) {
			if (str_starts_with($src, $test)) {
				return true;
			}
		}
		return false;
	}

	public function reset(): void
	{
		$this->do_concat = false;
		$this->print_code = '';
		$this->concat = '';
		$this->concat_version = '';
		$this->print_html = '';
		$this->ext_version = '';
		$this->ext_handles = '';
	}
}