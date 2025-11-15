<?php
/**
 * Wishlist System
 *
 * Allows users to save trips to their wishlist
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDV_Wishlist {

    /**
     * Initialize
     */
    public static function init() {
        // AJAX handlers
        add_action('wp_ajax_cdv_toggle_wishlist', array(__CLASS__, 'ajax_toggle_wishlist'));
    }

    /**
     * Create wishlist table
     */
    public static function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cdv_wishlist';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            travel_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_wishlist (user_id, travel_id),
            KEY user_id (user_id),
            KEY travel_id (travel_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Add travel to wishlist
     */
    public static function add_to_wishlist($user_id, $travel_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cdv_wishlist';

        // Check if already in wishlist
        if (self::is_in_wishlist($user_id, $travel_id)) {
            return true;
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'travel_id' => $travel_id,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s')
        );

        return $result !== false;
    }

    /**
     * Remove travel from wishlist
     */
    public static function remove_from_wishlist($user_id, $travel_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cdv_wishlist';

        return $wpdb->delete(
            $table_name,
            array(
                'user_id' => $user_id,
                'travel_id' => $travel_id,
            ),
            array('%d', '%d')
        );
    }

    /**
     * Check if travel is in user's wishlist
     */
    public static function is_in_wishlist($user_id, $travel_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cdv_wishlist';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND travel_id = %d",
            $user_id,
            $travel_id
        ));

        return $count > 0;
    }

    /**
     * Get wishlist count for user
     */
    public static function get_wishlist_count($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cdv_wishlist';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
            $user_id
        ));
    }

    /**
     * Get all wishlist travels for user
     */
    public static function get_wishlist_travels($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cdv_wishlist';

        $travel_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT travel_id FROM $table_name WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ));

        if (empty($travel_ids)) {
            return array();
        }

        // Get WP_Post objects
        $args = array(
            'post_type' => 'viaggio',
            'post__in' => $travel_ids,
            'orderby' => 'post__in',
            'posts_per_page' => -1,
        );

        $query = new WP_Query($args);
        return $query->posts;
    }

    /**
     * Get wishlist button HTML
     */
    public static function get_wishlist_button_html($travel_id, $classes = '') {
        if (!is_user_logged_in()) {
            return sprintf(
                '<a href="%s" class="%s" title="Login to save">
                    <span class="wishlist-icon">♡</span>
                    <span class="wishlist-text">Save</span>
                </a>',
                wp_login_url(get_permalink()),
                esc_attr($classes)
            );
        }

        $user_id = get_current_user_id();
        $in_wishlist = self::is_in_wishlist($user_id, $travel_id);
        $active_class = $in_wishlist ? 'wishlist-active' : '';
        $icon = $in_wishlist ? '♥' : '♡';
        $text = $in_wishlist ? 'Saved' : 'Save';

        return sprintf(
            '<button type="button" class="wishlist-btn %s %s" data-travel-id="%d" title="%s">
                <span class="wishlist-icon">%s</span>
                <span class="wishlist-text">%s</span>
            </button>',
            esc_attr($classes),
            esc_attr($active_class),
            $travel_id,
            esc_attr($text),
            $icon,
            esc_html($text)
        );
    }

    /**
     * AJAX: Toggle wishlist
     */
    public static function ajax_toggle_wishlist() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to save trips.'));
        }

        $user_id = get_current_user_id();
        $travel_id = isset($_POST['travel_id']) ? intval($_POST['travel_id']) : 0;

        if (!$travel_id) {
            wp_send_json_error(array('message' => 'Invalid trip ID.'));
        }

        // Check if travel exists
        $travel = get_post($travel_id);
        if (!$travel || $travel->post_type !== 'viaggio') {
            wp_send_json_error(array('message' => 'Trip not found.'));
        }

        // Toggle wishlist
        $is_in_wishlist = self::is_in_wishlist($user_id, $travel_id);

        if ($is_in_wishlist) {
            // Remove from wishlist
            $result = self::remove_from_wishlist($user_id, $travel_id);
            if ($result !== false) {
                wp_send_json_success(array(
                    'action' => 'removed',
                    'in_wishlist' => false,
                    'message' => 'Trip removed from wishlist.',
                ));
            } else {
                wp_send_json_error(array('message' => 'Error removing from wishlist.'));
            }
        } else {
            // Add to wishlist
            $result = self::add_to_wishlist($user_id, $travel_id);
            if ($result) {
                wp_send_json_success(array(
                    'action' => 'added',
                    'in_wishlist' => true,
                    'message' => 'Trip added to wishlist!',
                ));
            } else {
                wp_send_json_error(array('message' => 'Error adding to wishlist.'));
            }
        }
    }
}
