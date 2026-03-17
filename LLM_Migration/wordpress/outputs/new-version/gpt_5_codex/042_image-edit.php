<?php
declare(strict_types=1);

/**
 * WordPress Image Editor
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * Checks whether the provided value is a valid GD image resource.
 *
 * @param mixed $image Image resource or object.
 * @return bool
 */
function _wp_is_valid_image_resource(mixed $image): bool
{
	return $image instanceof GdImage || is_resource($image);
}

function wp_image_editor(int $post_id, mixed $msg = false): void
{
	$nonce     = wp_create_nonce("image_editor-$post_id");
	$meta      = wp_get_attachment_metadata($post_id);
	$thumb     = image_get_intermediate_size($post_id, 'thumbnail');
	$sub_sizes = isset($meta['sizes']) && is_array($meta['sizes']);
	$note      = '';

	if (isset($meta['width'], $meta['height'])) {
		$big = max($meta['width'], $meta['height']);
	} else {
		wp_die(__('Image data does not exist. Please re-upload the image.'));
	}

	$sizer        = $big > 400 ? 400 / $big : 1;
	$backup_sizes = get_post_meta($post_id, '_wp_attachment_backup_sizes', true);
	$can_restore  = false;

	if (! empty($backup_sizes) && isset($backup_sizes['full-orig'], $meta['file'])) {
		$can_restore = $backup_sizes['full-orig']['file'] !== basename((string) $meta['file']);
	}

	if ($msg && is_object($msg)) {
		if (isset($msg->error)) {
			$note = "<div class='error'><p>{$msg->error}</p></div>";
		} elseif (isset($msg->msg)) {
			$note = "<div class='updated'><p>{$msg->msg}</p></div>";
		}
	}
	?>
	<div class="imgedit-wrap">
	<div id="imgedit-panel-<?php echo (int) $post_id; ?>">

	<div class="imgedit-settings">
	<div class="imgedit-group">
	<div class="imgedit-group-top">
		<h3><?php _e('Scale Image'); ?> <a href="#" class="dashicons dashicons-editor-help imgedit-help-toggle" onclick="imageEdit.toggleHelp(this);return false;"></a></h3>
		<div class="imgedit-help">
		<p><?php _e('You can proportionally scale the original image. For best results, scaling should be done before you crop, flip, or rotate. Images can only be scaled down, not up.'); ?></p>
		</div>
		<?php if (isset($meta['width'], $meta['height'])) : ?>
		<p><?php printf(__('Original dimensions %s'), esc_html($meta['width'] . ' × ' . $meta['height'])); ?></p>
		<?php endif; ?>
		<div class="imgedit-submit">
		<span class="nowrap"><input type="text" id="imgedit-scale-width-<?php echo (int) $post_id; ?>" onkeyup="imageEdit.scaleChanged(<?php echo (int) $post_id; ?>, 1)" onblur="imageEdit.scaleChanged(<?php echo (int) $post_id; ?>, 1)" style="width:4em;" value="<?php echo isset($meta['width']) ? (int) $meta['width'] : 0; ?>" /> × <input type="text" id="imgedit-scale-height-<?php echo (int) $post_id; ?>" onkeyup="imageEdit.scaleChanged(<?php echo (int) $post_id; ?>, 0)" onblur="imageEdit.scaleChanged(<?php echo (int) $post_id; ?>, 0)" style="width:4em;" value="<?php echo isset($meta['height']) ? (int) $meta['height'] : 0; ?>" />
		<span class="imgedit-scale-warn" id="imgedit-scale-warn-<?php echo (int) $post_id; ?>">!</span></span>
		<input type="button" onclick="imageEdit.action(<?php echo (int) $post_id . ", '" . esc_js($nonce) . "'"; ?>, 'scale')" class="button button-primary" value="<?php esc_attr_e('Scale'); ?>" />
		</div>
	</div>
	</div>

<?php if ($can_restore) : ?>

	<div class="imgedit-group">
	<div class="imgedit-group-top">
		<h3><a onclick="imageEdit.toggleHelp(this);return false;" href="#"><?php _e('Restore Original Image'); ?> <span class="dashicons dashicons-arrow-down imgedit-help-toggle"></span></a></h3>
		<div class="imgedit-help">
		<p><?php
			_e('Discard any changes and restore the original image.');

			if (! defined('IMAGE_EDIT_OVERWRITE') || ! IMAGE_EDIT_OVERWRITE) {
				echo ' ' . __('Previously edited copies of the image will not be deleted.');
			}
		?></p>
		<div class="imgedit-submit">
		<input type="button" onclick="imageEdit.action(<?php echo (int) $post_id . ", '" . esc_js($nonce) . "'"; ?>, 'restore')" class="button button-primary" value="<?php esc_attr_e('Restore image'); ?>" />
		</div>
		</div>
	</div>
	</div>

<?php endif; ?>

	<div class="imgedit-group">
	<div class="imgedit-group-top">
		<h3><?php _e('Image Crop'); ?> <a href="#" class="dashicons dashicons-editor-help imgedit-help-toggle" onclick="imageEdit.toggleHelp(this);return false;"></a></h3>

		<div class="imgedit-help">
		<p><?php _e('To crop the image, click on it and drag to make your selection.'); ?></p>

		<p><strong><?php _e('Crop Aspect Ratio'); ?></strong><br />
		<?php _e('The aspect ratio is the relationship between the width and height. You can preserve the aspect ratio by holding down the shift key while resizing your selection. Use the input box to specify the aspect ratio, e.g. 1:1 (square), 4:3, 16:9, etc.'); ?></p>

		<p><strong><?php _e('Crop Selection'); ?></strong><br />
		<?php _e('Once you have made your selection, you can adjust it by entering the size in pixels. The minimum selection size is the thumbnail size as set in the Media settings.'); ?></p>
		</div>
	</div>

	<p>
		<?php _e('Aspect ratio:'); ?>
		<span  class="nowrap">
		<input type="text" id="imgedit-crop-width-<?php echo (int) $post_id; ?>" onkeyup="imageEdit.setRatioSelection(<?php echo (int) $post_id; ?>, 0, this)" style="width:3em;" />
		:
		<input type="text" id="imgedit-crop-height-<?php echo (int) $post_id; ?>" onkeyup="imageEdit.setRatioSelection(<?php echo (int) $post_id; ?>, 1, this)" style="width:3em;" />
		</span>
	</p>

	<p id="imgedit-crop-sel-<?php echo (int) $post_id; ?>">
		<?php _e('Selection:'); ?>
		<span  class="nowrap">
		<input type="text" id="imgedit-sel-width-<?php echo (int) $post_id; ?>" onkeyup="imageEdit.setNumSelection(<?php echo (int) $post_id; ?>)" style="width:4em;" />
		×
		<input type="text" id="imgedit-sel-height-<?php echo (int) $post_id; ?>" onkeyup="imageEdit.setNumSelection(<?php echo (int) $post_id; ?>)" style="width:4em;" />
		</span>
	</p>
	</div>

	<?php if ($thumb && $sub_sizes) :
		$thumb_img = wp_constrain_dimensions((int) $thumb['width'], (int) $thumb['height'], 160, 120);
	?>

	<div class="imgedit-group imgedit-applyto">
	<div class="imgedit-group-top">
		<h3><?php _e('Thumbnail Settings'); ?> <a href="#" class="dashicons dashicons-editor-help imgedit-help-toggle" onclick="imageEdit.toggleHelp(this);return false;"></a></h3>
		<p class="imgedit-help"><?php _e('You can edit the image while preserving the thumbnail. For example, you may wish to have a square thumbnail that displays just a section of the image.'); ?></p>
	</div>

	<p>
		<img src="<?php echo esc_url($thumb['url']); ?>" width="<?php echo (int) $thumb_img[0]; ?>" height="<?php echo (int) $thumb_img[1]; ?>" class="imgedit-size-preview" alt="" draggable="false" />
		<br /><?php _e('Current thumbnail'); ?>
	</p>

	<p id="imgedit-save-target-<?php echo (int) $post_id; ?>">
		<strong><?php _e('Apply changes to:'); ?></strong><br />

		<label class="imgedit-label">
		<input type="radio" name="imgedit-target-<?php echo (int) $post_id; ?>" value="all" checked="checked" />
		<?php _e('All image sizes'); ?></label>

		<label class="imgedit-label">
		<input type="radio" name="imgedit-target-<?php echo (int) $post_id; ?>" value="thumbnail" />
		<?php _e('Thumbnail'); ?></label>

		<label class="imgedit-label">
		<input type="radio" name="imgedit-target-<?php echo (int) $post_id; ?>" value="nothumb" />
		<?php _e('All sizes except thumbnail'); ?></label>
	</p>
	</div>

	<?php endif; ?>

	</div>

	<div class="imgedit-panel-content">
		<?php echo $note; ?>
		<div class="imgedit-menu">
			<div onclick="imageEdit.crop(<?php echo (int) $post_id . ", '" . esc_js($nonce) . "'"; ?>, this)" class="imgedit-crop disabled" title="<?php esc_attr_e('Crop'); ?>"></div><?php

		// On some setups GD library does not provide imagerotate() - Ticket #11536
		if (wp_image_editor_supports(['mime_type' => get_post_mime_type($post_id), 'methods' => ['rotate']])) { ?>
			<div class="imgedit-rleft"  onclick="imageEdit.rotate( 90, <?php echo (int) $post_id . ", '" . esc_js($nonce) . "'"; ?>, this)" title="<?php esc_attr_e('Rotate counter-clockwise'); ?>"></div>
			<div class="imgedit-rright" onclick="imageEdit.rotate(-90, <?php echo (int) $post_id . ", '" . esc_js($nonce) . "'"; ?>, this)" title="<?php esc_attr_e('Rotate clockwise'); ?>"></div>
	<?php } else {
			$note_no_rotate = esc_attr__('Image rotation is not supported by your web host.');
	?>
		    <div class="imgedit-rleft disabled"  title="<?php echo $note_no_rotate; ?>"></div>
		    <div class="imgedit-rright disabled" title="<?php echo $note_no_rotate; ?>"></div>
	<?php } ?>

			<div onclick="imageEdit.flip(1, <?php echo (int) $post_id . ", '" . esc_js($nonce) . "'"; ?>, this)" class="imgedit-flipv" title="<?php esc_attr_e('Flip vertically'); ?>"></div>
			<div onclick="imageEdit.flip(2, <?php echo (int) $post_id . ", '" . esc_js($nonce) . "'"; ?>, this)" class="imgedit-fliph" title="<?php esc_attr_e('Flip horizontally'); ?>"></div>

			<div id="image-undo-<?php echo (int) $post_id; ?>" onclick="imageEdit.undo(<?php echo (int) $post_id . ", '" . esc_js($nonce) . "'"; ?>, this)" class="imgedit-undo disabled" title="<?php esc_attr_e('Undo'); ?>"></div>
			<div id="image-redo-<?php echo (int) $post_id; ?>" onclick="imageEdit.redo(<?php echo (int) $post_id . ", '" . esc_js($nonce) . "'"; ?>, this)" class="imgedit-redo disabled" title="<?php esc_attr_e('Redo'); ?>"></div>
			<br class="clear" />
		</div>

		<input type="hidden" id="imgedit-sizer-<?php echo (int) $post_id; ?>" value="<?php echo esc_attr((string) $sizer); ?>" />
		<input type="hidden" id="imgedit-history-<?php echo (int) $post_id; ?>" value="" />
		<input type="hidden" id="imgedit-undone-<?php echo (int) $post_id; ?>" value="0" />
		<input type="hidden" id="imgedit-selection-<?php echo (int) $post_id; ?>" value="" />
		<input type="hidden" id="imgedit-x-<?php echo (int) $post_id; ?>" value="<?php echo isset($meta['width']) ? (int) $meta['width'] : 0; ?>" />
		<input type="hidden" id="imgedit-y-<?php echo (int) $post_id; ?>" value="<?php echo isset($meta['height']) ? (int) $meta['height'] : 0; ?>" />

		<div id="imgedit-crop-<?php echo (int) $post_id; ?>" class="imgedit-crop-wrap">
		<img id="image-preview-<?php echo (int) $post_id; ?>" onload="imageEdit.imgLoaded('<?php echo (int) $post_id; ?>')" src="<?php echo esc_url(admin_url('admin-ajax.php', 'relative')); ?>?action=imgedit-preview&amp;_ajax_nonce=<?php echo esc_attr($nonce); ?>&amp;postid=<?php echo (int) $post_id; ?>&amp;rand=<?php echo (int) wp_rand(1, 99999); ?>" />
		</div>

		<div class="imgedit-submit">
			<input type="button" onclick="imageEdit.close(<?php echo (int) $post_id; ?>, 1)" class="button" value="<?php esc_attr_e('Cancel'); ?>" />
			<input type="button" onclick="imageEdit.save(<?php echo (int) $post_id . ", '" . esc_js($nonce) . "'"; ?>)" disabled="disabled" class="button button-primary imgedit-submit-btn" value="<?php esc_attr_e('Save'); ?>" />
		</div>
	</div>

	</div>
	<div class="imgedit-wait" id="imgedit-wait-<?php echo (int) $post_id; ?>"></div>
	<script type="text/javascript">jQuery( function() { imageEdit.init(<?php echo (int) $post_id; ?>); });</script>
	<div class="hidden" id="imgedit-leaving-<?php echo (int) $post_id; ?>"><?php _e("There are unsaved changes that will be lost. 'OK' to continue, 'Cancel' to return to the Image Editor."); ?></div>
	</div>
	<?php
}

/**
 * Streams image in WP_Image_Editor to browser.
 * Provided for backcompat reasons
 *
 * @param WP_Image_Editor|mixed $image
 * @param string                $mime_type
 * @param int                   $post_id
 * @return bool
 */
function wp_stream_image($image, string $mime_type, int $post_id): bool
{
	if ($image instanceof WP_Image_Editor) {

		/**
		 * Filter the WP_Image_Editor instance for the image to be streamed to the browser.
		 *
		 * @since 3.5.0
		 *
		 * @param WP_Image_Editor $image   WP_Image_Editor instance.
		 * @param int             $post_id Post ID.
		 */
		$image = apply_filters('image_editor_save_pre', $image, $post_id);

		if (is_wp_error($image->stream($mime_type))) {
			return false;
		}

		return true;
	}

	_deprecated_argument(__FUNCTION__, '3.5', __('$image needs to be an WP_Image_Editor object'));

	/**
	 * Filter the GD image resource to be streamed to the browser.
	 *
	 * @since 2.9.0
	 * @deprecated 3.5.0 Use image_editor_save_pre instead.
	 *
	 * @param resource|GdImage $image   Image resource to be streamed.
	 * @param int              $post_id Post ID.
	 */
	$image = apply_filters('image_save_pre', $image, $post_id);

	switch ($mime_type) {
		case 'image/jpeg':
			header('Content-Type: image/jpeg');
			return imagejpeg($image, null, 90);
		case 'image/png':
			header('Content-Type: image/png');
			return imagepng($image);
		case 'image/gif':
			header('Content-Type: image/gif');
			return imagegif($image);
		default:
			return false;
	}
}

/**
 * Saves Image to File
 *
 * @param string $filename
 * @param mixed  $image
 * @param string $mime_type
 * @param int    $post_id
 * @return bool|WP_Error
 */
function wp_save_image_file(string $filename, $image, string $mime_type, int $post_id)
{
	if ($image instanceof WP_Image_Editor) {

		/** This filter is documented in wp-admin/includes/image-edit.php */
		$image = apply_filters('image_editor_save_pre', $image, $post_id);

		/**
		 * Filter whether to skip saving the image file.
		 *
		 * Returning a non-null value will short-circuit the save method,
		 * returning that value instead.
		 *
		 * @since 3.5.0
		 *
		 * @param mixed           $override  Value to return instead of saving. Default null.
		 * @param string          $filename  Name of the file to be saved.
		 * @param WP_Image_Editor $image     WP_Image_Editor instance.
		 * @param string          $mime_type Image mime type.
		 * @param int             $post_id   Post ID.
		 */
		$saved = apply_filters('wp_save_image_editor_file', null, $filename, $image, $mime_type, $post_id);

		if (null !== $saved) {
			return $saved;
		}

		return $image->save($filename, $mime_type);
	}

	_deprecated_argument(__FUNCTION__, '3.5', __('$image needs to be an WP_Image_Editor object'));

	/** This filter is documented in wp-admin/includes/image-edit.php */
	$image = apply_filters('image_save_pre', $image, $post_id);

	/**
	 * Filter whether to skip saving the image file.
	 *
	 * Returning a non-null value will short-circuit the save method,
	 * returning that value instead.
	 *
	 * @since 2.9.0
	 * @deprecated 3.5.0 Use wp_save_image_editor_file instead.
	 *
	 * @param mixed           $override  Value to return instead of saving. Default null.
	 * @param string          $filename  Name of the file to be saved.
	 * @param WP_Image_Editor $image     WP_Image_Editor instance.
	 * @param string          $mime_type Image mime type.
	 * @param int             $post_id   Post ID.
	 */
	$saved = apply_filters('wp_save_image_file', null, $filename, $image, $mime_type, $post_id);

	if (null !== $saved) {
		return $saved;
	}

	return match ($mime_type) {
		'image/jpeg' => imagejpeg($image, $filename, apply_filters('jpeg_quality', 90, 'edit_image')),
		'image/png'  => imagepng($image, $filename),
		'image/gif'  => imagegif($image, $filename),
		default      => false,
	};
}

function _image_get_preview_ratio(int $w, int $h): float
{
	$max = max($w, $h);
	return $max > 400 ? (400 / $max) : 1.0;
}

// @TODO: Returns GD resource, but is NOT public
function _rotate_image_resource($img, float $angle)
{
	_deprecated_function(__FUNCTION__, '3.5', __('Use WP_Image_Editor::rotate'));
	if (function_exists('imagerotate')) {
		$rotated = imagerotate($img, $angle, 0);

		if (_wp_is_valid_image_resource($rotated)) {
			if (_wp_is_valid_image_resource($img)) {
				imagedestroy($img);
			}
			$img = $rotated;
		}
	}
	return $img;
}

/**
 * @TODO: Only used within image_edit_apply_changes
 *        and receives/returns GD Resource.
 *        Consider removal.
 *
 * @param GdImage|resource $img
 * @param bool             $horz
 * @param bool             $vert
 * @return GdImage|resource
 */
function _flip_image_resource($img, bool $horz, bool $vert)
{
	_deprecated_function(__FUNCTION__, '3.5', __('Use WP_Image_Editor::flip'));
	$w   = imagesx($img);
	$h   = imagesy($img);
	$dst = wp_imagecreatetruecolor($w, $h);

	if (_wp_is_valid_image_resource($dst)) {
		$sx = $vert ? ($w - 1) : 0;
		$sy = $horz ? ($h - 1) : 0;
		$sw = $vert ? -$w : $w;
		$sh = $horz ? -$h : $h;

		if (imagecopyresampled($dst, $img, 0, 0, $sx, $sy, $w, $h, $sw, $sh)) {
			if (_wp_is_valid_image_resource($img)) {
				imagedestroy($img);
			}
			$img = $dst;
		}
	}
	return $img;
}

/**
 * @TODO: Only used within image_edit_apply_changes
 *        and receives/returns GD Resource.
 *        Consider removal.
 *
 * @param GdImage|resource $img
 * @param float            $x
 * @param float            $y
 * @param float            $w
 * @param float            $h
 * @return GdImage|resource
 */
function _crop_image_resource($img, float $x, float $y, float $w, float $h)
{
	$dst = wp_imagecreatetruecolor((int) round($w), (int) round($h));

	if (_wp_is_valid_image_resource($dst)) {
		if (imagecopy($dst, $img, 0, 0, (int) round($x), (int) round($y), (int) round($w), (int) round($h))) {
			if (_wp_is_valid_image_resource($img)) {
				imagedestroy($img);
			}
			$img = $dst;
		}
	}
	return $img;
}

/**
 * Performs group of changes on Editor specified.
 *
 * @param WP_Image_Editor|GdImage|resource $image
 * @param mixed                             $changes
 * @return WP_Image_Editor|GdImage|resource
 */
function image_edit_apply_changes($image, $changes)
{
	if (is_resource($image)) {
		_deprecated_argument(__FUNCTION__, '3.5', __('$image needs to be an WP_Image_Editor object'));
	}

	if (! is_array($changes)) {
		return $image;
	}

	// Expand change operations.
	foreach ($changes as $key => $obj) {
		if (isset($obj->r)) {
			$obj->type  = 'rotate';
			$obj->angle = $obj->r;
			unset($obj->r);
		} elseif (isset($obj->f)) {
			$obj->type = 'flip';
			$obj->axis = $obj->f;
			unset($obj->f);
		} elseif (isset($obj->c)) {
			$obj->type = 'crop';
			$obj->sel  = $obj->c;
			unset($obj->c);
		}
		$changes[$key] = $obj;
	}

	// Combine operations.
	if (count($changes) > 1) {
		$filtered         = [$changes[0]];
		$changes_count    = count($changes);
		$current_filtered = 0;

		for ($j = 1; $j < $changes_count; $j++) {
			$combined = false;
			if ($filtered[$current_filtered]->type === $changes[$j]->type) {
				switch ($filtered[$current_filtered]->type) {
					case 'rotate':
						$filtered[$current_filtered]->angle += $changes[$j]->angle;
						$combined = true;
						break;
					case 'flip':
						$filtered[$current_filtered]->axis ^= $changes[$j]->axis;
						$combined = true;
						break;
				}
			}
			if (! $combined) {
				$filtered[++$current_filtered] = $changes[$j];
			}
		}
		$changes = $filtered;
		unset($filtered);
	}

	// Image resource before applying the changes.
	if ($image instanceof WP_Image_Editor) {

		/**
		 * Filter the WP_Image_Editor instance before applying changes to the image.
		 *
		 * @since 3.5.0
		 *
		 * @param WP_Image_Editor $image   WP_Image_Editor instance.
		 * @param array           $changes Array of change operations.
		 */
		$image = apply_filters('wp_image_editor_before_change', $image, $changes);
	} elseif (_wp_is_valid_image_resource($image)) {

		/**
		 * Filter the GD image resource before applying changes to the image.
		 *
		 * @since 2.9.0
		 * @deprecated 3.5.0 Use wp_image_editor_before_change instead.
		 *
		 * @param resource|GdImage $image   GD image resource.
		 * @param array            $changes Array of change operations.
		 */
		$image = apply_filters('image_edit_before_change', $image, $changes);
	}

	foreach ($changes as $operation) {
		switch ($operation->type) {
			case 'rotate':
				if (0 !== (int) $operation->angle) {
					if ($image instanceof WP_Image_Editor) {
						$image->rotate($operation->angle);
					} else {
						$image = _rotate_image_resource($image, $operation->angle);
					}
				}
				break;
			case 'flip':
				if (0 !== (int) $operation->axis) {
					if ($image instanceof WP_Image_Editor) {
						$image->flip(($operation->axis & 1) !== 0, ($operation->axis & 2) !== 0);
					} else {
						$image = _flip_image_resource($image, ($operation->axis & 1) !== 0, ($operation->axis & 2) !== 0);
					}
				}
				break;
			case 'crop':
				$sel = $operation->sel;

				if ($image instanceof WP_Image_Editor) {
					$size = $image->get_size();
					$w    = $size['width'];
					$h    = $size['height'];

					$scale = 1 / _image_get_preview_ratio($w, $h); // discard preview scaling
					$image->crop($sel->x * $scale, $sel->y * $scale, $sel->w * $scale, $sel->h * $scale);
				} else {
					$scale = 1 / _image_get_preview_ratio(imagesx($image), imagesy($image)); // discard preview scaling
					$image = _crop_image_resource($image, $sel->x * $scale, $sel->y * $scale, $sel->w * $scale, $sel->h * $scale);
				}
				break;
		}
	}

	return $image;
}


/**
 * Streams image in post to browser, along with enqueued changes
 * in $_REQUEST['history']
 *
 * @param int $post_id
 * @return bool
 */
function stream_preview_image(int $post_id): bool
{
	$post = get_post($post_id);

	/** This filter is documented in wp-admin/admin.php */
	@ini_set('memory_limit', apply_filters('admin_memory_limit', WP_MAX_MEMORY_LIMIT));

	$img = wp_get_image_editor(_load_image_to_edit_path($post_id));

	if (is_wp_error($img)) {
		return false;
	}

	$history = $_REQUEST['history'] ?? '';
	$changes = ! empty($history) ? json_decode(wp_unslash($history)) : null;

	if (! empty($changes)) {
		$img = image_edit_apply_changes($img, $changes);
	}

	// Scale the image.
	$size = $img->get_size();
	$w    = (int) $size['width'];
	$h    = (int) $size['height'];

	$ratio = _image_get_preview_ratio($w, $h);
	$w2    = max(1, (int) round($w * $ratio));
	$h2    = max(1, (int) round($h * $ratio));

	if (is_wp_error($img->resize($w2, $h2))) {
		return false;
	}

	return wp_stream_image($img, (string) $post->post_mime_type, $post_id);
}

function wp_restore_image(int $post_id): stdClass
{
	$meta         = wp_get_attachment_metadata($post_id);
	$file         = get_attached_file($post_id);
	$backup_sizes = get_post_meta($post_id, '_wp_attachment_backup_sizes', true);
	$restored     = false;
	$msg          = new stdClass();

	if (! is_array($backup_sizes)) {
		$msg->error = __('Cannot load image metadata.');
		return $msg;
	}

	$parts          = pathinfo((string) $file);
	$suffix         = time() . wp_rand(100, 999);
	$default_sizes  = get_intermediate_image_sizes();

	if (isset($backup_sizes['full-orig']) && is_array($backup_sizes['full-orig'])) {
		$data = $backup_sizes['full-orig'];

		if ($parts['basename'] !== $data['file']) {
			if (defined('IMAGE_EDIT_OVERWRITE') && IMAGE_EDIT_OVERWRITE) {

				// Delete only if it's edited image.
				if (preg_match('/-e[0-9]{13}\./', $parts['basename'])) {

					/** This filter is documented in wp-admin/custom-header.php */
					$delpath = apply_filters('wp_delete_file', $file);
					@unlink($delpath);
				}
			} elseif (isset($meta['width'], $meta['height'])) {
				$backup_sizes["full-$suffix"] = [
					'width'  => $meta['width'],
					'height' => $meta['height'],
					'file'   => $parts['basename'],
				];
			}
		}

		$restored_file = path_join($parts['dirname'], $data['file']);
		$restored      = update_attached_file($post_id, $restored_file);

		$meta['file']   = _wp_relative_upload_path($restored_file);
		$meta['width']  = $data['width'];
		$meta['height'] = $data['height'];
	}

	foreach ($default_sizes as $default_size) {
		if (isset($backup_sizes["$default_size-orig"])) {
			$data = $backup_sizes["$default_size-orig"];
			if (isset($meta['sizes'][$default_size]) && $meta['sizes'][$default_size]['file'] !== $data['file']) {
				if (defined('IMAGE_EDIT_OVERWRITE') && IMAGE_EDIT_OVERWRITE) {

					// Delete only if it's edited image
					if (preg_match('/-e[0-9]{13}-/', $meta['sizes'][$default_size]['file'])) {
						/** This filter is documented in wp-admin/custom-header.php */
						$delpath = apply_filters('wp_delete_file', path_join($parts['dirname'], $meta['sizes'][$default_size]['file']));
						@unlink($delpath);
					}
				} else {
					$backup_sizes["$default_size-{$suffix}"] = $meta['sizes'][$default_size];
				}
			}

			$meta['sizes'][$default_size] = $data;
		} else {
			unset($meta['sizes'][$default_size]);
		}
	}

	if (! wp_update_attachment_metadata($post_id, $meta) || ! update_post_meta($post_id, '_wp_attachment_backup_sizes', $backup_sizes)) {
		$msg->error = __('Cannot save image metadata.');
		return $msg;
	}

	if (! $restored) {
		$msg->error = __('Image metadata is inconsistent.');
	} else {
		$msg->msg = __('Image restored successfully.');
	}

	return $msg;
}

/**
 * Saves image to post along with enqueued changes
 * in $_REQUEST['history']
 *
 * @param int $post_id
 * @return stdClass
 */
function wp_save_image(int $post_id): stdClass
{
	global $_wp_additional_image_sizes;

	$return  = new stdClass();
	$success = $delete = $scaled = $nocrop = false;
	$post    = get_post($post_id);

	$img = wp_get_image_editor(_load_image_to_edit_path($post_id, 'full'));
	if (is_wp_error($img)) {
		$return->error = esc_js(__('Unable to create new image.'));
		return $return;
	}

	$fwidth = isset($_REQUEST['fwidth']) ? (int) $_REQUEST['fwidth'] : 0;
	$fheight = isset($_REQUEST['fheight']) ? (int) $_REQUEST['fheight'] : 0;
	$target  = isset($_REQUEST['target']) ? preg_replace('/[^a-z0-9_-]+/i', '', (string) $_REQUEST['target']) : '';
	$do      = $_REQUEST['do'] ?? '';
	$scale   = ! empty($do) && 'scale' === $do;

	if ($scale && $fwidth > 0 && $fheight > 0) {
		$size = $img->get_size();
		$sX   = $size['width'];
		$sY   = $size['height'];

		// Check if it has roughly the same w / h ratio.
		$diff = round($sX / $sY, 2) - round($fwidth / $fheight, 2);
		if ($diff > -0.1 && $diff < 0.1) {
			// Scale the full size image.
			$scaled = ! is_wp_error($img->resize($fwidth, $fheight));
		}

		if (! $scaled) {
			$return->error = esc_js(__('Error while saving the scaled image. Please reload the page and try again.'));
			return $return;
		}
	} elseif (! empty($_REQUEST['history'])) {
		$changes = json_decode(wp_unslash((string) $_REQUEST['history']));
		if (! empty($changes)) {
			$img = image_edit_apply_changes($img, $changes);
		}
	} else {
		$return->error = esc_js(__('Nothing to save, the image has not changed.'));
		return $return;
	}

	$meta         = wp_get_attachment_metadata($post_id);
	$backup_sizes = get_post_meta($post->ID, '_wp_attachment_backup_sizes', true);

	if (! is_array($meta)) {
		$return->error = esc_js(__('Image data does not exist. Please re-upload the image.'));
		return $return;
	}

	if (! is_array($backup_sizes)) {
		$backup_sizes = [];
	}

	// Generate new filename.
	$path       = get_attached_file($post_id);
	$path_parts = pathinfo((string) $path);
	$filename   = $path_parts['filename'];
	$suffix     = time() . wp_rand(100, 999);

	if (
		defined('IMAGE_EDIT_OVERWRITE') && IMAGE_EDIT_OVERWRITE &&
		isset($backup_sizes['full-orig']) && $backup_sizes['full-orig']['file'] !== $path_parts['basename']
	) {

		if ('thumbnail' === $target) {
			$new_path = "{$path_parts['dirname']}/{$filename}-temp.{$path_parts['extension']}";
		} else {
			$new_path = $path;
		}
	} else {
		while (true) {
			$filename     = preg_replace('/-e([0-9]+)$/', '', $filename);
			$filename    .= "-e{$suffix}";
			$new_filename = "{$filename}.{$path_parts['extension']}";
			$new_path     = "{$path_parts['dirname']}/$new_filename";
			if (file_exists($new_path)) {
				$suffix++;
			} else {
				break;
			}
		}
	}

	// Save the full-size file, also needed to create sub-sizes.
	if (! wp_save_image_file($new_path, $img, (string) $post->post_mime_type, $post_id)) {
		$return->error = esc_js(__('Unable to save the image.'));
		return $return;
	}

	if ('nothumb' === $target || 'all' === $target || 'full' === $target || $scaled) {
		$tag = false;
		if (isset($backup_sizes['full-orig'])) {
			if ((! defined('IMAGE_EDIT_OVERWRITE') || ! IMAGE_EDIT_OVERWRITE) && $backup_sizes['full-orig']['file'] !== $path_parts['basename']) {
				$tag = "full-$suffix";
			}
		} else {
			$tag = 'full-orig';
		}

		if ($tag) {
			$backup_sizes[$tag] = [
				'width'  => $meta['width'],
				'height' => $meta['height'],
				'file'   => $path_parts['basename'],
			];
		}

		$success = update_attached_file($post_id, $new_path);

		$meta['file'] = _wp_relative_upload_path($new_path);

		$size           = $img->get_size();
		$meta['width']  = $size['width'];
		$meta['height'] = $size['height'];

		if ($success && ('nothumb' === $target || 'all' === $target)) {
			$sizes = get_intermediate_image_sizes();
			if ('nothumb' === $target) {
				$sizes = array_diff($sizes, ['thumbnail']);
			}
		}

		$return->fw = $meta['width'];
		$return->fh = $meta['height'];
	} elseif ('thumbnail' === $target) {
		$sizes   = ['thumbnail'];
		$success = $delete = $nocrop = true;
	}

	if (isset($sizes)) {
		$_sizes = [];
		$meta['sizes'] = $meta['sizes'] ?? [];

		foreach ($sizes as $size) {
			$tag = false;
			if (isset($meta['sizes'][$size])) {
				if (isset($backup_sizes["$size-orig"])) {
					if ((! defined('IMAGE_EDIT_OVERWRITE') || ! IMAGE_EDIT_OVERWRITE) && $backup_sizes["$size-orig"]['file'] !== $meta['sizes'][$size]['file']) {
						$tag = "$size-$suffix";
					}
				} else {
					$tag = "$size-orig";
				}

				if ($tag) {
					$backup_sizes[$tag] = $meta['sizes'][$size];
				}
			}

			if (isset($_wp_additional_image_sizes[$size])) {
				$width  = (int) $_wp_additional_image_sizes[$size]['width'];
				$height = (int) $_wp_additional_image_sizes[$size]['height'];
				$crop   = $nocrop ? false : $_wp_additional_image_sizes[$size]['crop'];
			} else {
				$height = (int) get_option("{$size}_size_h");
				$width  = (int) get_option("{$size}_size_w");
				$crop   = $nocrop ? false : get_option("{$size}_crop");
			}

			$_sizes[$size] = [
				'width'  => $width,
				'height' => $height,
				'crop'   => $crop,
			];
		}

		$resized = $img->multi_resize($_sizes);

		if (! is_wp_error($resized) && is_array($resized)) {
			$meta['sizes'] = array_merge($meta['sizes'], $resized);
		}
	}

	unset($img);

	if ($success) {
		wp_update_attachment_metadata($post_id, $meta);
		update_post_meta($post_id, '_wp_attachment_backup_sizes', $backup_sizes);

		if ('thumbnail' === $target || 'all' === $target || 'full' === $target) {
			// Check if it's an image edit from attachment edit screen
			if (! empty($_REQUEST['context']) && 'edit-attachment' === $_REQUEST['context']) {
				$thumb_url            = wp_get_attachment_image_src($post_id, [900, 600], true);
				$return->thumbnail = $thumb_url[0];
			} else {
				$file_url = wp_get_attachment_url($post_id);
				if (! empty($meta['sizes']['thumbnail']) && $thumb = $meta['sizes']['thumbnail']) {
					$return->thumbnail = path_join(dirname($file_url), $thumb['file']);
				} else {
					$return->thumbnail = "{$file_url}?w=128&h=128";
				}
			}
		}
	} else {
		$delete = true;
	}

	if ($delete) {

		/** This filter is documented in wp-admin/custom-header.php */
		$delpath = apply_filters('wp_delete_file', $new_path);
		@unlink($delpath);
	}

	$return->msg = esc_js(__('Image saved'));
	return $return;
}
?>