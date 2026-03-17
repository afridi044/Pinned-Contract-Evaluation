<?php
/**
 * WordPress GD Image Editor
 *
 * @package WordPress
 * @subpackage Image_Editor
 */

/**
 * WordPress Image Editor Class for Image Manipulation through GD
 *
 * @since 3.5.0
 * @package WordPress
 * @subpackage Image_Editor
 * @uses WP_Image_Editor Extends class
 */
class WP_Image_Editor_GD extends WP_Image_Editor {

	protected GdImage|false $image = false; // GD Resource

	public function __destruct() {
		if ( $this->image instanceof GdImage ) { // Check for GdImage object
			// we don't need the original in memory anymore
			imagedestroy( $this->image );
		}
	}

	/**
	 * Checks to see if current environment supports GD.
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param array<string, mixed> $args
	 * @return bool
	 */
	public static function test( array $args = array() ): bool {
		if ( ! extension_loaded('gd') || ! function_exists('gd_info') ) {
			return false;
		}

		// On some setups GD library does not provide imagerotate() - Ticket #11536
		if ( isset( $args['methods'] ) &&
			 is_array( $args['methods'] ) && // Added type check for 'methods'
			 in_array( 'rotate', $args['methods'], true ) && // Added strict check
			 ! function_exists('imagerotate') ){

				return false;
		}

		return true;
	}

	/**
	 * Checks to see if editor supports the mime-type specified.
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param string $mime_type
	 * @return bool
	 */
	public static function supports_mime_type( string $mime_type ): bool {
		$image_types = imagetypes();
		return match ( $mime_type ) {
			'image/jpeg' => ( $image_types & IMG_JPG ) !== 0,
			'image/png'  => ( $image_types & IMG_PNG ) !== 0,
			'image/gif'  => ( $image_types & IMG_GIF ) !== 0,
			default      => false,
		};
	}

	/**
	 * Loads image from $this->file into new GD Resource.
	 *
	 * @since 3.5.0
	 * @access protected
	 *
	 * @return bool|WP_Error True if loaded successfully; WP_Error on failure.
	 */
	public function load(): bool|WP_Error {
		if ( $this->image instanceof GdImage ) { // Check for GdImage object
			return true;
		}

		if ( ! is_file( $this->file ) && ! preg_match( '|^https?://|', $this->file ) ) {
			return new WP_Error( 'error_loading_image', __('File doesn&#8217;t exist?'), $this->file );
		}

		/**
		 * Filter the memory limit allocated for image manipulation.
		 *
		 * @since 3.5.0
		 *
		 * @param int|string $limit Maximum memory limit to allocate for images. Default WP_MAX_MEMORY_LIMIT.
		 *                          Accepts an integer (bytes), or a shorthand string notation, such as '256M'.
		 */
		// Set artificially high because GD uses uncompressed images in memory
		@ini_set( 'memory_limit', apply_filters( 'image_memory_limit', WP_MAX_MEMORY_LIMIT ) );

		$file_contents = file_get_contents( $this->file );
		if ( $file_contents === false ) {
			return new WP_Error( 'error_loading_image', __('Could not read file contents.'), $this->file );
		}

		$this->image = @imagecreatefromstring( $file_contents );

		if ( ! ( $this->image instanceof GdImage ) ) { // Check for GdImage object
			return new WP_Error( 'invalid_image', __('File is not an image.'), $this->file );
		}

		$size = @getimagesize( $this->file );
		if ( ! $size ) {
			return new WP_Error( 'invalid_image', __('Could not read image size.'), $this->file );
		}

		if ( function_exists( 'imagealphablending' ) && function_exists( 'imagesavealpha' ) ) {
			imagealphablending( $this->image, false );
			imagesavealpha( $this->image, true );
		}

		$this->update_size( $size[0], $size[1] );
		$this->mime_type = $size['mime'];

		return true;
	}

	/**
	 * Sets or updates current image size.
	 *
	 * @since 3.5.0
	 * @access protected
	 *
	 * @param int|false $width
	 * @param int|false $height
	 * @return bool
	 */
	protected function update_size( int|false $width = false, int|false $height = false ): bool {
		if ( ! $width ) {
			$width = imagesx( $this->image );
		}

		if ( ! $height ) {
			$height = imagesy( $this->image );
		}

		return parent::update_size( $width, $height );
	}

	/**
	 * Resizes current image.
	 * Wraps _resize, since _resize returns a GD Resource.
	 *
	 * At minimum, either a height or width must be provided.
	 * If one of the two is set to null, the resize will
	 * maintain aspect ratio according to the provided dimension.
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param  int|null $max_w Image width.
	 * @param  int|null $max_h Image height.
	 * @param  bool  $crop
	 * @return bool|WP_Error
	 */
	public function resize( ?int $max_w, ?int $max_h, bool $crop = false ): bool|WP_Error {
		if ( ( $this->size['width'] === $max_w ) && ( $this->size['height'] === $max_h ) ) { // Use strict comparison
			return true;
		}

		$resized = $this->_resize( $max_w, $max_h, $crop );

		if ( $resized instanceof GdImage ) { // Check for GdImage object
			imagedestroy( $this->image );
			$this->image = $resized;
			return true;

		} elseif ( is_wp_error( $resized ) ) {
			return $resized;
		}

		return new WP_Error( 'image_resize_error', __('Image resize failed.'), $this->file );
	}

	/**
	 * @param int|null $max_w
	 * @param int|null $max_h
	 * @param bool $crop
	 * @return GdImage|WP_Error
	 */
	protected function _resize( ?int $max_w, ?int $max_h, bool $crop = false ): GdImage|WP_Error {
		$dims = image_resize_dimensions( $this->size['width'], $this->size['height'], $max_w, $max_h, $crop );
		if ( ! $dims ) {
			return new WP_Error( 'error_getting_dimensions', __('Could not calculate resized image dimensions'), $this->file );
		}
		list( $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h ) = $dims;

		$resized = wp_imagecreatetruecolor( $dst_w, $dst_h );
		if ( ! ( $resized instanceof GdImage ) ) { // Ensure wp_imagecreatetruecolor returns GdImage
			return new WP_Error( 'image_resize_error', __('Image resize failed: could not create true color image.'), $this->file );
		}

		// Preserve transparency for PNGs and GIFs
		if ( 'image/png' === $this->mime_type || 'image/gif' === $this->mime_type ) {
			imagealphablending( $resized, false );
			imagesavealpha( $resized, true );
		}

		imagecopyresampled( $resized, $this->image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h );

		if ( $resized instanceof GdImage ) { // Check for GdImage object
			$this->update_size( $dst_w, $dst_h );
			return $resized;
		}

		return new WP_Error( 'image_resize_error', __('Image resize failed.'), $this->file );
	}

	/**
	 * Resize multiple images from a single source.
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param array<string, array{width?: int|null, height?: int|null, crop?: bool}> $sizes {
	 *     An array of image size arrays. Default sizes are 'small', 'medium', 'large'.
	 *
	 *     Either a height or width must be provided.
	 *     If one of the two is set to null, the resize will
	 *     maintain aspect ratio according to the provided dimension.
	 *
	 *     @type array $size {
	 *         @type int  ['width']  Optional. Image width.
	 *         @type int  ['height'] Optional. Image height.
	 *         @type bool ['crop']   Optional. Whether to crop the image. Default false.
	 *     }
	 * }
	 * @return array<string, array{file: string, width: int, height: int, mime-type: string}> An array of resized images' metadata by size.
	 */
	public function multi_resize( array $sizes ): array {
		$metadata = array();
		$orig_size = $this->size;

		foreach ( $sizes as $size => $size_data ) {
			// Use null coalescing operator for defaults
			$width  = $size_data['width']  ?? null;
			$height = $size_data['height'] ?? null;
			$crop   = $size_data['crop']   ?? false;

			if ( null === $width && null === $height ) {
				continue;
			}

			$image = $this->_resize( $width, $height, $crop );

			if( ! is_wp_error( $image ) ) {
				$resized = $this->_save( $image );

				imagedestroy( $image );

				if ( ! is_wp_error( $resized ) && $resized ) {
					unset( $resized['path'] );
					$metadata[$size] = $resized;
				}
			}

			$this->size = $orig_size;
		}

		return $metadata;
	}

	/**
	 * Crops Image.
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param int $src_x The start x position to crop from.
	 * @param int $src_y The start y position to crop from.
	 * @param int $src_w The width to crop.
	 * @param int $src_h The height to crop.
	 * @param int|null $dst_w Optional. The destination width.
	 * @param int|null $dst_h Optional. The destination height.
	 * @param bool $src_abs Optional. If the source crop points are absolute.
	 * @return bool|WP_Error
	 */
	public function crop( int $src_x, int $src_y, int $src_w, int $src_h, ?int $dst_w = null, ?int $dst_h = null, bool $src_abs = false ): bool|WP_Error {
		// If destination width/height isn't specified, use same as
		// width/height from source.
		$dst_w = $dst_w ?? $src_w;
		$dst_h = $dst_h ?? $src_h;

		$dst = wp_imagecreatetruecolor( $dst_w, $dst_h );
		if ( ! ( $dst instanceof GdImage ) ) { // Ensure wp_imagecreatetruecolor returns GdImage
			return new WP_Error( 'image_crop_error', __('Image crop failed: could not create true color image.'), $this->file );
		}

		if ( $src_abs ) {
			$src_w -= $src_x;
			$src_h -= $src_y;
		}

		if ( function_exists( 'imageantialias' ) ) {
			imageantialias( $dst, true );
		}

		// Preserve transparency for PNGs and GIFs
		if ( 'image/png' === $this->mime_type || 'image/gif' === $this->mime_type ) {
			imagealphablending( $dst, false );
			imagesavealpha( $dst, true );
		}

		imagecopyresampled( $dst, $this->image, 0, 0, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h );

		if ( $dst instanceof GdImage ) { // Check for GdImage object
			imagedestroy( $this->image );
			$this->image = $dst;
			$this->update_size();
			return true;
		}

		return new WP_Error( 'image_crop_error', __('Image crop failed.'), $this->file );
	}

	/**
	 * Rotates current image counter-clockwise by $angle.
	 * Ported from image-edit.php
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param float $angle
	 * @return bool|WP_Error
	 */
	public function rotate( float $angle ): bool|WP_Error {
		if ( function_exists('imagerotate') ) {
			// imagerotate expects background color. 0 for black.
			// For transparency, we need to handle it.
			// WordPress's original imagerotate implementation in image-edit.php
			// passes 0 for the background color, which might not be transparent.
			// For GD, 0 is usually black. If transparency is needed,
			// imagecolorallocatealpha would be used.
			// Assuming original behavior for functional equivalence, but adding transparency preservation.
			$rotated = imagerotate( $this->image, $angle, 0 );

			if ( $rotated instanceof GdImage ) { // Check for GdImage object
				// Preserve transparency for PNGs and GIFs
				if ( 'image/png' === $this->mime_type || 'image/gif' === $this->mime_type ) {
					imagealphablending( $rotated, false );
					imagesavealpha( $rotated, true );
				}

				imagedestroy( $this->image );
				$this->image = $rotated;
				$this->update_size();
				return true;
			}
		}
		return new WP_Error( 'image_rotate_error', __('Image rotate failed.'), $this->file );
	}

	/**
	 * Flips current image.
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param bool $horz Flip along Horizontal Axis
	 * @param bool $vert Flip along Vertical Axis
	 * @returns bool|WP_Error
	 */
	public function flip( bool $horz, bool $vert ): bool|WP_Error {
		$w = $this->size['width'];
		$h = $this->size['height'];
		$dst = wp_imagecreatetruecolor( $w, $h );

		if ( ! ( $dst instanceof GdImage ) ) { // Ensure wp_imagecreatetruecolor returns GdImage
			return new WP_Error( 'image_flip_error', __('Image flip failed: could not create true color image.'), $this->file );
		}

		$sx = $vert ? ( $w - 1 ) : 0;
		$sy = $horz ? ( $h - 1 ) : 0;
		$sw = $vert ? -$w : $w;
		$sh = $horz ? -$h : $h;

		if ( imagecopyresampled( $dst, $this->image, 0, 0, $sx, $sy, $w, $h, $sw, $sh ) ) {
			// Preserve transparency for PNGs and GIFs
			if ( 'image/png' === $this->mime_type || 'image/gif' === $this->mime_type ) {
				imagealphablending( $dst, false );
				imagesavealpha( $dst, true );
			}

			imagedestroy( $this->image );
			$this->image = $dst;
			return true;
		}
		return new WP_Error( 'image_flip_error', __('Image flip failed.'), $this->file );
	}

	/**
	 * Saves current in-memory image to file.
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param string|null $filename
	 * @param string|null $mime_type
	 * @return array<string, string|int>|WP_Error {'path'=>string, 'file'=>string, 'width'=>int, 'height'=>int, 'mime-type'=>string}
	 */
	public function save( ?string $filename = null, ?string $mime_type = null ): array|WP_Error {
		$saved = $this->_save( $this->image, $filename, $mime_type );

		if ( ! is_wp_error( $saved ) ) {
			$this->file = $saved['path'];
			$this->mime_type = $saved['mime-type'];
		}

		return $saved;
	}

	/**
	 * @param GdImage $image
	 * @param string|null $filename
	 * @param string|null $mime_type
	 * @return array<string, string|int>|WP_Error
	 */
	protected function _save( GdImage $image, ?string $filename = null, ?string $mime_type = null ): array|WP_Error {
		list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );

		if ( ! $filename ) {
			$filename = $this->generate_filename( null, null, $extension );
		}

		$result = false;
		switch ( $mime_type ) {
			case 'image/gif':
				$result = $this->make_image( $filename, 'imagegif', array( $image, $filename ) );
				break;
			case 'image/png':
				// convert from full colors to index colors, like original PNG.
				if ( function_exists('imageistruecolor') && ! imageistruecolor( $image ) ) {
					imagetruecolortopalette( $image, false, imagecolorstotal( $image ) );
				}
				$result = $this->make_image( $filename, 'imagepng', array( $image, $filename ) );
				break;
			case 'image/jpeg':
				$result = $this->make_image( $filename, 'imagejpeg', array( $image, $filename, $this->get_quality() ) );
				break;
			default:
				return new WP_Error( 'image_save_error', __('Image Editor Save Failed: Unsupported MIME type.') );
		}

		if ( ! $result ) {
			return new WP_Error( 'image_save_error', __('Image Editor Save Failed') );
		}

		// Set correct file permissions
		$stat = stat( dirname( $filename ) );
		if ( $stat !== false ) { // Check if stat was successful
			$perms = $stat['mode'] & 0000666; //same permissions as parent folder, strip off the executable bits
			@ chmod( $filename, $perms );
		}


		/**
		 * Filter the name of the saved image file.
		 *
		 * @since 2.6.0
		 *
		 * @param string $filename Name of the file.
		 */
		return array(
			'path'      => $filename,
			'file'      => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
			'width'     => $this->size['width'],
			'height'    => $this->size['height'],
			'mime-type' => $mime_type,
		);
	}

	/**
	 * Returns stream of current image.
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param string|null $mime_type
	 * @return bool
	 */
	public function stream( ?string $mime_type = null ): bool {
		list( $filename, $extension, $mime_type ) = $this->get_output_format( null, $mime_type );

		switch ( $mime_type ) {
			case 'image/png':
				header( 'Content-Type: image/png' );
				return imagepng( $this->image );
			case 'image/gif':
				header( 'Content-Type: image/gif' );
				return imagegif( $this->image );
			default:
				header( 'Content-Type: image/jpeg' );
				return imagejpeg( $this->image, null, $this->get_quality() );
		}
	}

	/**
	 * Either calls editor's save function or handles file as a stream.
	 *
	 * @since 3.5.0
	 * @access protected
	 *
	 * @param string $filename
	 * @param callable $function
	 * @param array<int, mixed> $arguments
	 * @return bool
	 */
	protected function make_image( string $filename, callable $function, array $arguments ): bool {
		if ( wp_is_stream( $filename ) ) {
			$arguments[1] = null;
		}

		return parent::make_image( $filename, $function, $arguments );
	}
}