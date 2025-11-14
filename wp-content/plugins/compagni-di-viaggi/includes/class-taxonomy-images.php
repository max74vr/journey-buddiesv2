<?php
/**
 * Taxonomy Images
 *
 * Manages featured images for taxonomies
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDV_Taxonomy_Images {

    /**
     * Initialize
     */
    public static function init() {
        // Add image field to tipo_viaggio taxonomy
        add_action('tipo_viaggio_add_form_fields', array(__CLASS__, 'add_image_field'));
        add_action('tipo_viaggio_edit_form_fields', array(__CLASS__, 'edit_image_field'));

        // Save image field
        add_action('created_tipo_viaggio', array(__CLASS__, 'save_image_field'));
        add_action('edited_tipo_viaggio', array(__CLASS__, 'save_image_field'));

        // Enqueue media uploader
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_media'));

        // Add column in admin
        add_filter('manage_edit-tipo_viaggio_columns', array(__CLASS__, 'add_image_column'));
        add_filter('manage_tipo_viaggio_custom_column', array(__CLASS__, 'display_image_column'), 10, 3);
    }

    /**
     * Enqueue media uploader scripts
     */
    public static function enqueue_media($hook) {
        if ($hook === 'edit-tags.php' || $hook === 'term.php') {
            wp_enqueue_media();
            wp_enqueue_script('cdv-taxonomy-image', CDV_PLUGIN_URL . 'assets/js/taxonomy-image.js', array('jquery'), CDV_VERSION, true);
        }
    }

    /**
     * Add image field to add term form
     */
    public static function add_image_field($taxonomy) {
        ?>
        <div class="form-field term-image-wrap">
            <label><?php _e('Featured Image', 'compagni-di-viaggi'); ?></label>
            <div class="cdv-taxonomy-image-wrapper">
                <img src="" style="max-width: 150px; height: auto; display: none;" class="cdv-taxonomy-image-preview" />
                <input type="hidden" name="cdv_taxonomy_image" class="cdv-taxonomy-image-id" value="" />
            </div>
            <p>
                <button type="button" class="button cdv-upload-taxonomy-image">
                    <?php _e('Upload Image', 'compagni-di-viaggi'); ?>
                </button>
                <button type="button" class="button cdv-remove-taxonomy-image" style="display: none;">
                    <?php _e('Remove Image', 'compagni-di-viaggi'); ?>
                </button>
            </p>
            <p class="description">
                <?php _e('Image used as a placeholder when a trip of this type has no featured image.', 'compagni-di-viaggi'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Add image field to edit term form
     */
    public static function edit_image_field($term) {
        $image_id = get_term_meta($term->term_id, 'cdv_taxonomy_image', true);
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
        ?>
        <tr class="form-field term-image-wrap">
            <th scope="row">
                <label><?php _e('Featured Image', 'compagni-di-viaggi'); ?></label>
            </th>
            <td>
                <div class="cdv-taxonomy-image-wrapper">
                    <img src="<?php echo esc_url($image_url); ?>"
                         style="max-width: 150px; height: auto; <?php echo $image_url ? '' : 'display: none;'; ?>"
                         class="cdv-taxonomy-image-preview" />
                    <input type="hidden" name="cdv_taxonomy_image" class="cdv-taxonomy-image-id" value="<?php echo esc_attr($image_id); ?>" />
                </div>
                <p>
                    <button type="button" class="button cdv-upload-taxonomy-image">
                        <?php _e('Upload Image', 'compagni-di-viaggi'); ?>
                    </button>
                    <button type="button" class="button cdv-remove-taxonomy-image" style="<?php echo $image_url ? '' : 'display: none;'; ?>">
                        <?php _e('Remove Image', 'compagni-di-viaggi'); ?>
                    </button>
                </p>
                <p class="description">
                    <?php _e('Image used as a placeholder when a trip of this type has no featured image.', 'compagni-di-viaggi'); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Save image field
     */
    public static function save_image_field($term_id) {
        if (isset($_POST['cdv_taxonomy_image'])) {
            update_term_meta($term_id, 'cdv_taxonomy_image', sanitize_text_field($_POST['cdv_taxonomy_image']));
        }
    }

    /**
     * Add image column to taxonomy list
     */
    public static function add_image_column($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['image'] = __('Image', 'compagni-di-viaggi');

        return array_merge($new_columns, $columns);
    }

    /**
     * Display image in column
     */
    public static function display_image_column($content, $column_name, $term_id) {
        if ($column_name === 'image') {
            $image_id = get_term_meta($term_id, 'cdv_taxonomy_image', true);
            if ($image_id) {
                $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                if ($image_url) {
                    $content = '<img src="' . esc_url($image_url) . '" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;" />';
                }
            }
        }
        return $content;
    }

    /**
     * Get image URL for a term
     */
    public static function get_term_image_url($term_id, $size = 'travel-card') {
        $image_id = get_term_meta($term_id, 'cdv_taxonomy_image', true);
        if ($image_id) {
            return wp_get_attachment_image_url($image_id, $size);
        }
        return false;
    }

    /**
     * Get random term image URL from multiple terms
     */
    public static function get_random_term_image($term_ids, $size = 'travel-card') {
        if (empty($term_ids) || !is_array($term_ids)) {
            return false;
        }

        // Get all images from terms
        $images = array();
        foreach ($term_ids as $term_id) {
            $image_url = self::get_term_image_url($term_id, $size);
            if ($image_url) {
                $images[] = $image_url;
            }
        }

        // Return random image if available
        if (!empty($images)) {
            return $images[array_rand($images)];
        }

        return false;
    }
}
