<?php
/**
 * Wishlist management for travels
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
     * Create database table for wishlist
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
            PRIMARY KEY  (id),
            UNIQUE KEY unique_wishlist (user_id, travel_id),
            KEY user_id (user_id),
            KEY travel_id (travel_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Check if travel is in user's wishlist
     */
    public static function is_in_wishlist($user_id, $travel_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'cdv_wishlist';

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND travel_id = %d",
            $user_id,
            $travel_id
        ));

        return !empty($exists);
    }

    /**
     * Add travel to wishlist
     */
    public static function add_to_wishlist($user_id, $travel_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'cdv_wishlist';

        // Check if already in wishlist
        if (self::is_in_wishlist($user_id, $travel_id)) {
            return new WP_Error('already_in_wishlist', __('This trip is already in your wishlist.', 'compagni-di-viaggi'));
        }

        $result = $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'travel_id' => $travel_id,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s')
        );

        if ($result) {
            return $wpdb->insert_id;
        }

        return new WP_Error('db_error', __('Error adding to wishlist.', 'compagni-di-viaggi'));
    }

    /**
     * Remove travel from wishlist
     */
    public static function remove_from_wishlist($user_id, $travel_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'cdv_wishlist';

        $result = $wpdb->delete(
            $table,
            array(
                'user_id' => $user_id,
                'travel_id' => $travel_id,
            ),
            array('%d', '%d')
        );

        return $result !== false;
    }

    /**
     * Toggle wishlist (add/remove)
     */
    public static function toggle_wishlist($user_id, $travel_id) {
        if (self::is_in_wishlist($user_id, $travel_id)) {
            $result = self::remove_from_wishlist($user_id, $travel_id);
            return array(
                'action' => 'removed',
                'success' => $result,
            );
        } else {
            $result = self::add_to_wishlist($user_id, $travel_id);
            if (is_wp_error($result)) {
                return array(
                    'action' => 'add_failed',
                    'success' => false,
                    'error' => $result->get_error_message(),
                );
            }
            return array(
                'action' => 'added',
                'success' => true,
            );
        }
    }

    /**
     * Get user's wishlist travels
     */
    public static function get_wishlist_travels($user_id, $args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'cdv_wishlist';

        // Get travel IDs from wishlist
        $travel_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT travel_id FROM $table WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ));

        if (empty($travel_ids)) {
            // Return empty query
            return new WP_Query(array('post__in' => array(0)));
        }

        // Query travels
        $defaults = array(
            'post_type' => 'viaggio',
            'post__in' => $travel_ids,
            'orderby' => 'post__in',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        );

        $args = wp_parse_args($args, $defaults);

        return new WP_Query($args);
    }

    /**
     * Get wishlist count for user
     */
    public static function get_wishlist_count($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'cdv_wishlist';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d",
            $user_id
        ));
    }

    /**
     * AJAX: Toggle wishlist
     */
    public static function ajax_toggle_wishlist() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();
        $travel_id = isset($_POST['travel_id']) ? intval($_POST['travel_id']) : 0;

        if (!$travel_id) {
            wp_send_json_error(array('message' => 'Invalid travel ID.'));
        }

        // Check if travel exists
        $travel = get_post($travel_id);
        if (!$travel || $travel->post_type !== 'viaggio') {
            wp_send_json_error(array('message' => 'Travel not found.'));
        }

        $result = self::toggle_wishlist($user_id, $travel_id);

        if ($result['success']) {
            wp_send_json_success(array(
                'action' => $result['action'],
                'message' => $result['action'] === 'added' ? 'Added to wishlist' : 'Removed from wishlist',
                'in_wishlist' => $result['action'] === 'added',
            ));
        } else {
            wp_send_json_error(array(
                'message' => isset($result['error']) ? $result['error'] : 'Error updating wishlist.',
            ));
        }
    }
}
