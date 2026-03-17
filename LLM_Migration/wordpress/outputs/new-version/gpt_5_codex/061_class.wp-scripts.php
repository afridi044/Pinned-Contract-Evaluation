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
class WP_Scripts extends WP_Dependencies {
	public ?string $base_url = null; // Full URL with trailing slash
	public ?string $content_url = null;
	public ?string $default_version = null;
	public array $in_footer = [];
	public string $concat = '';
	public string $concat_version = '';
	public bool $do_concat = false;
	public string $print_html = '';
	public string $print_code = '';
	public string $ext_handles = '';
	public string $ext_version = '';
	public array $default_dirs = [];

	public function __construct() {
		$this->init();
		add_action( 'init', [ $this, 'init' ], 0 );
	}

	public function init(): void {
		/**
		 * Fires when the WP_Scripts instance is initialized.
		 *
		 * @since 2.6.0
		 *
		 * @param WP_Scripts &$this WP_Scripts instance, passed by reference.
		 */
		$scripts = $this;
		do_action_ref_array( 'wp_default_scripts', [ &$scripts ] );
	}

	/**
	 * Prints scripts
	 *
	 * Prints the scripts passed to it or the print queue. Also prints all necessary dependencies.
	 *
	 * @param mixed     $handles Scripts to be printed. (void) prints queue, (string) prints that script, (array of strings) prints those scripts.
	 * @param bool|int  $group   If scripts were queued in groups prints this group number.
	 * @return array Scripts that have been printed
	 */
	public function print_scripts( mixed $handles = false, bool|int $group = false ): array {
		return $this->do_items( $handles, $group );
	}

	// Deprecated since 3.3, see print_extra_script()
	public function print_scripts_l10n( string $handle, bool $echo = true ): bool|string|null {
		_deprecated_function( __FUNCTION__, '3.3', 'print_extra_script()' );
		return $this->print_extra_script( $handle, $echo );
	}

	public function print_extra_script( string $handle, bool $echo = true ): bool|string|null {
		$output = $this->get_data( $handle, 'data' );

		if ( ! $output ) {
			return null;
		}

		if ( ! $echo ) {
			return $output;
		}

		echo "<script type='text/javascript'>\n"; // CDATA and type='text/javascript' is not needed for HTML 5
		echo "/* <![CDATA[ */\n";
		echo "{$output}\n";
		echo "/* ]]> */\n";
		echo "</script>\n";

		return true;
	}

	public function do_item( string $handle, bool|int $group = false ): bool {
		if ( ! parent::do_item( $handle ) ) {
			return false;
		}

		if ( 0 === $group && $this->groups[ $handle ] > 0 ) {
			$this->in_footer[] = $handle;
			return false;
		}

		if ( false === $group && in_array( $handle, $this->in_footer, true ) ) {
			$this->in_footer = array_diff( $this->in_footer, [ $handle ] );
		}

		$registered = $this->registered[ $handle ];

		if ( null === $registered->ver ) {
			$ver = '';
		} else {
			$ver = $registered->ver !== '' ? $registered->ver : ( $this->default_version ?? '' );
		}

		if ( isset( $this->args[ $handle ] ) ) {
			$ver = $ver !== '' ? "{$ver}&amp;{$this->args[ $handle ]}" : $this->args[ $handle ];
		}

		$src = $registered->src;

		if ( $this->do_concat ) {
			/**
			 * Filter the script loader source.
			 *
			 * @since 2.2.0
			 *
			 * @param string $src    Script loader source path.
			 * @param string $handle Script handle.
			 */
			$srce = apply_filters( 'script_loader_src', $src, $handle );
			if ( $this->in_default_dir( $srce ) ) {
				$this->print_code .= $this->print_extra_script( $handle, false ) ?? '';
				$this->concat          .= "{$handle},";
				$this->concat_version  .= "{$handle}{$ver}";
				return true;
			}

			$this->ext_handles  .= "{$handle},";
			$this->ext_version  .= "{$handle}{$ver}";
		}

		$this->print_extra_script( $handle );

		if ( ! preg_match( '/^(https?:)?\/\//', $src ) && ( empty( $this->content_url ) || ! str_starts_with( $src, (string) $this->content_url ) ) ) {
			$src = ( $this->base_url ?? '' ) . $src;
		}

		if ( $ver !== '' ) {
			$src = add_query_arg( 'ver', $ver, $src );
		}

		/** This filter is documented in wp-includes/class.wp-scripts.php */
		$src = esc_url( apply_filters( 'script_loader_src', $src, $handle ) );

		if ( ! $src ) {
			return true;
		}

		if ( $this->do_concat ) {
			$this->print_html .= "<script type='text/javascript' src='{$src}'></script>\n";
		} else {
			echo "<script type='text/javascript' src='{$src}'></script>\n";
		}

		return true;
	}

	/**
	 * Localizes a script
	 *
	 * Localizes only if the script has already been added
	 */
	public function localize( string $handle, string $object_name, array $l10n ): bool {
		if ( $handle === 'jquery' ) {
			$handle = 'jquery-core';
		}

		$after = '';

		if ( isset( $l10n['l10n_print_after'] ) ) {
			$after = (string) $l10n['l10n_print_after'];
			unset( $l10n['l10n_print_after'] );
		}

		foreach ( $l10n as $key => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$l10n[ $key ] = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
		}

		$script = "var {$object_name} = " . json_encode( $l10n ) . ';';

		if ( $after !== '' ) {
			$script .= "\n{$after};";
		}

		$data = $this->get_data( $handle, 'data' );

		if ( ! empty( $data ) ) {
			$script = "{$data}\n{$script}";
		}

		return $this->add_data( $handle, 'data', $script );
	}

	public function set_group( string $handle, bool $recursion, bool|int $group = false ): bool {
		if ( $this->registered[ $handle ]->args === 1 ) {
			$grp = 1;
		} else {
			$grp = (int) $this->get_data( $handle, 'group' );
		}

		if ( false !== $group && $grp > $group ) {
			$grp = $group;
		}

		return parent::set_group( $handle, $recursion, $grp );
	}

	public function all_deps( mixed $handles, bool $recursion = false, bool|int $group = false ): bool {
		$r = parent::all_deps( $handles, $recursion );
		if ( ! $recursion ) {
			/**
			 * Filter the list of script dependencies left to print.
			 *
			 * @since 2.3.0
			 *
			 * @param array $to_do An array of script dependencies.
			 */
			$this->to_do = apply_filters( 'print_scripts_array', $this->to_do );
		}
		return $r;
	}

	public function do_head_items(): array {
		$this->do_items( false, 0 );
		return $this->done;
	}

	public function do_footer_items(): array {
		$this->do_items( false, 1 );
		return $this->done;
	}

	public function in_default_dir( string $src ): bool {
		if ( $this->default_dirs === [] ) {
			return true;
		}

		if ( str_starts_with( $src, '/' . WPINC . '/js/l10n' ) ) {
			return false;
		}

		foreach ( $this->default_dirs as $test ) {
			if ( str_starts_with( $src, $test ) ) {
				return true;
			}
		}

		return false;
	}

	public function reset(): void {
		$this->do_concat = false;
		$this->print_code = '';
		$this->concat = '';
		$this->concat_version = '';
		$this->print_html = '';
		$this->ext_version = '';
		$this->ext_handles = '';
	}
}
?>