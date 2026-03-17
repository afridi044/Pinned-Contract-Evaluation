<?php
/**
 * Base WordPress Image Editor
 *
 * @package WordPress
 * @subpackage Image_Editor
 */

/**
 * Base image editor class from which implementations extend
 *
 * @since 3.5.0
 */
abstract class WP_Image_Editor {
    protected string $file = null;
    protected ?array $size = null;
    protected ?string $mime_type = null;
    protected string $default_mime_type = 'image/jpeg';
    protected ?int $quality = null;
    protected int $default_quality = 90;

    /**
     * Each instance handles a single file.
     */
    public function __construct(string $file) {
        $this->file = $file;
    }

    /**
     * Checks to see if current environment supports the editor chosen.
     * Must be overridden in a sub-class.
     *
     * @since 3.5.0
     * @access public
     * @abstract
     *
     * @param array $args
     * @return bool
     */
    public static function test(array $args = []): bool {
        return false;
    }

    /**
     * Checks to see if editor supports the mime-type specified.
     * Must be overridden in a sub-class.
     *
     * @since 3.5.0
     * @access public
     * @abstract
     *
     * @param string $mime_type
     * @return bool
     */
    public static function supports_mime_type(string $mime_type): bool {
        return false;
    }

    /**
     * Loads image from $this->file into editor.
     *
     * @since 3.5.0
     * @access protected
     * @abstract
     *
     * @return bool|WP_Error
     */
    abstract public function load(): bool|WP_Error;

    /**
     * Saves current image to file.
     *
     * @since 3.5.0
     * @access public
     * @abstract
     *
     * @param string|null $destfilename
     * @param string|null $mime_type
     * @return array|WP_Error
     */
    abstract public function save(?string $destfilename = null, ?string $mime_type = null): array|WP_Error;

    /**
     * Resizes current image.
     *
     * At minimum, either a height or width must be provided.
     * If one of the two is set to null, the resize will
     * maintain aspect ratio according to the provided dimension.
     *
     * @since 3.5.0
     * @access public
     * @abstract
     *
     * @param  int|null $max_w
     * @param  int|null $max_h
     * @param  bool $crop
     * @return bool|WP_Error
     */
    abstract public function resize(?int $max_w, ?int $max_h, bool $crop = false): bool|WP_Error;

    /**
     * Resize multiple images from a single source.
     *
     * @since 3.5.0
     * @access public
     * @abstract
     *
     * @param array $sizes
     * @return array
     */
    abstract public function multi_resize(array $sizes): array;

    /**
     * Crops Image.
     *
     * @since 3.5.0
     * @access public
     * @abstract
     *
     * @param int $src_x
     * @param int $src_y
     * @param int $src_w
     * @param int $src_h
     * @param int|null $dst_w
     * @param int|null $dst_h
     * @param bool $src_abs
     * @return bool|WP_Error
     */
    abstract public function crop(int $src_x, int $src_y, int $src_w, int $src_h, ?int $dst_w = null, ?int $dst_h = null, bool $src_abs = false): bool|WP_Error;

    /**
     * Rotates current image counter-clockwise by $angle.
     *
     * @since 3.5.0
     * @access public
     * @abstract
     *
     * @param float $angle
     * @return bool|WP_Error
     */
    abstract public function rotate(float $angle): bool|WP_Error;

    /**
     * Flips current image.
     *
     * @since 3.5.0
     * @access public
     * @abstract
     *
     * @param bool $horz
     * @param bool $vert
     * @return bool|WP_Error
     */
    abstract public function flip(bool $horz, bool $vert): bool|WP_Error;

    /**
     * Streams current image to browser.
     *
     * @since 3.5.0
     * @access public
     * @abstract
     *
     * @param string|null $mime_type
     * @return bool|WP_Error
     */
    abstract public function stream(?string $mime_type = null): bool|WP_Error;

    /**
     * Gets dimensions of image.
     *
     * @since 3.5.0
     * @access public
     *
     * @return array
     */
    public function get_size(): ?array {
        return $this->size;
    }

    /**
     * Sets current image size.
     *
     * @since 3.5.0
     * @access protected
     *
     * @param int|null $width
     * @param int|null $height
     * @return bool
     */
    protected function update_size(?int $width = null, ?int $height = null): bool {
        $this->size = [
            'width' => (int) $width,
            'height' => (int) $height,
        ];
        return true;
    }

    /**
     * Gets the Image Compression quality on a 1-100% scale.
     *
     * @since 4.0.0
     * @access public
     *
     * @return int
     */
    public function get_quality(): int {
        if ($this->quality === null) {
            $quality = apply_filters('wp_editor_set_quality', $this->default_quality, $this->mime_type);

            if ($this->mime_type === 'image/jpeg') {
                $quality = apply_filters('jpeg_quality', $quality, 'image_resize');
            }

            if (!$this->set_quality($quality)) {
                $this->quality = $this->default_quality;
            }
        }

        return $this->quality;
    }

    /**
     * Sets Image Compression quality on a 1-100% scale.
     *
     * @since 3.5.0
     * @access public
     *
     * @param int|null $quality
     * @return bool|WP_Error
     */
    public function set_quality(?int $quality = null): bool|WP_Error {
        if ($quality === 0) {
            $quality = 1;
        }

        if ($quality >= 1 && $quality <= 100) {
            $this->quality = $quality;
            return true;
        } else {
            return new WP_Error('invalid_image_quality', __('Attempted to set image quality outside of the range [1,100].'));
        }
    }

    /**
     * Returns preferred mime-type and extension based on provided
     * file's extension and mime, or current file's extension and mime.
     *
     * Will default to $this->default_mime_type if requested is not supported.
     *
     * Provides corrected filename only if filename is provided.
     *
     * @since 3.5.0
     * @access protected
     *
     * @param string|null $filename
     * @param string|null $mime_type
     * @return array
     */
    protected function get_output_format(?string $filename = null, ?string $mime_type = null): array {
        $new_ext = null;

        if ($mime_type) {
            $new_ext = $this->get_extension($mime_type);
        }

        if ($filename) {
            $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $file_mime = $this->get_mime_type($file_ext);
        } else {
            $file_ext = strtolower(pathinfo($this->file, PATHINFO_EXTENSION));
            $file_mime = $this->mime_type;
        }

        if (!$mime_type || $file_mime === $mime_type) {
            $mime_type = $file_mime;
            $new_ext = $file_ext;
        }

        if (!$this->supports_mime_type($mime_type)) {
            $mime_type = apply_filters('image_editor_default_mime_type', $this->default_mime_type);
            $new_ext = $this->get_extension($mime_type);
        }

        if ($filename) {
            $ext = '';
            $info = pathinfo($filename);
            $dir = $info['dirname'];

            if (isset($info['extension'])) {
                $ext = $info['extension'];
            }

            $filename = trailingslashit($dir) . wp_basename($filename, ".$ext") . ".{$new_ext}";
        }

        return [$filename, $new_ext, $mime_type];
    }

    /**
     * Builds an output filename based on current file, and adding proper suffix
     *
     * @since 3.5.0
     * @access public
     *
     * @param string|null $suffix
     * @param string|null $dest_path
     * @param string|null $extension
     * @return string
     */
    public function generate_filename(?string $suffix = null, ?string $dest_path = null, ?string $extension = null): string {
        if ($suffix === null) {
            $suffix = $this->get_suffix();
        }

        $info = pathinfo($this->file);
        $dir = $info['dirname'];
        $ext = $info['extension'];

        $name = wp_basename($this->file, ".$ext");
        $new_ext = strtolower($extension ?: $ext);

        if ($dest_path && $_dest_path = realpath($dest_path)) {
            $dir = $_dest_path;
        }

        return trailingslashit($dir) . "{$name}-{$suffix}.{$new_ext}";
    }

    /**
     * Builds and returns proper suffix for file based on height and width.
     *
     * @since 3.5.0
     * @access public
     *
     * @return string|false
     */
    public function get_suffix(): string|false {
        if (!$this->get_size()) {
            return false;
        }

        return "{$this->size['width']}x{$this->size['height']}";
    }

    /**
     * Either calls editor's save function or handles file as a stream.
     *
     * @since 3.5.0
     * @access protected
     *
     * @param string|resource $filename
     * @param callable $function
     * @param array $arguments
     * @return bool
     */
    protected function make_image($filename, callable $function, array $arguments): bool {
        if ($stream = wp_is_stream($filename)) {
            ob_start();
        } else {
            wp_mkdir_p(dirname($filename));
        }

        $result = call_user_func_array($function, $arguments);

        if ($result && $stream) {
            $contents = ob_get_contents();

            $fp = fopen($filename, 'w');

            if (!$fp) {
                return false;
            }

            fwrite($fp, $contents);
            fclose($fp);
        }

        if ($stream) {
            ob_end_clean();
        }

        return $result;
    }

    /**
     * Returns first matched mime-type from extension,
     * as mapped from wp_get_mime_types()
     *
     * @since 3.5.0
     * @access protected
     *
     * @param string|null $extension
     * @return string|false
     */
    protected static function get_mime_type(?string $extension = null): string|false {
        if (!$extension) {
            return false;
        }

        $mime_types = wp_get_mime_types();
        $extensions = array_keys($mime_types);

        foreach ($extensions as $_extension) {
            if (preg_match("/{$extension}/i", $_extension)) {
                return $mime_types[$_extension];
            }
        }

        return false;
    }

    /**
     * Returns first matched extension from Mime-type,
     * as mapped from wp_get_mime_types()
     *
     * @since 3.5.0
     * @access protected
     *
     * @param string|null $mime_type
     * @return string|false
     */
    protected static function get_extension(?string $mime_type = null): string|false {
        $extensions = explode('|', array_search($mime_type, wp_get_mime_types()));

        if (empty($extensions[0])) {
            return false;
        }

        return $extensions[0];
    }
}