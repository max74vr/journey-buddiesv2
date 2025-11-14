<?php
/**
 * Travel Moderation System
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDV_Travel_Moderation {

    /**
     * Initialize
     */
    public static function init() {
        // Force pending status for new travels
        add_filter('wp_insert_post_data', array(__CLASS__, 'force_pending_status'), 10, 2);

        // Add moderation columns to admin
        add_filter('manage_viaggio_posts_columns', array(__CLASS__, 'add_moderation_column'));
        add_action('manage_viaggio_posts_custom_column', array(__CLASS__, 'moderation_column_content'), 10, 2);

        // Bulk actions
        add_filter('bulk_actions-edit-viaggio', array(__CLASS__, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-edit-viaggio', array(__CLASS__, 'handle_bulk_actions'), 10, 3);

        // Quick approve/reject
        add_action('wp_ajax_cdv_approve_travel', array(__CLASS__, 'ajax_approve_travel'));
        add_action('wp_ajax_cdv_reject_travel', array(__CLASS__, 'ajax_reject_travel'));

        // Admin notices
        add_action('admin_notices', array(__CLASS__, 'pending_travels_notice'));
    }

    /**
     * Force pending status for non-admin users
     */
    public static function force_pending_status($data, $postarr) {
        // Only for viaggio post type
        if ($data['post_type'] !== 'viaggio') {
            return $data;
        }

        // Skip for admins
        if (current_user_can('publish_posts')) {
            return $data;
        }

        // Force pending status
        if ($data['post_status'] === 'publish' || $data['post_status'] === 'auto-draft') {
            $data['post_status'] = 'pending';
        }

        return $data;
    }

    /**
     * Add moderation column
     */
    public static function add_moderation_column($columns) {
        $new_columns = array();

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            if ($key === 'title') {
                $new_columns['moderation'] = __('Moderation', 'compagni-di-viaggi');
                $new_columns['organizer'] = __('Organizer', 'compagni-di-viaggi');
            }
        }

        return $new_columns;
    }

    /**
     * Moderation column content
     */
    public static function moderation_column_content($column, $post_id) {
        if ($column === 'moderation') {
            $status = get_post_status($post_id);

            if ($status === 'pending') {
                echo '<span class="cdv-pending">⏳ Pending</span><br>';
                echo '<button class="button button-small cdv-approve-travel" data-travel-id="' . $post_id . '">✓ Approve</button> ';
                echo '<button class="button button-small cdv-reject-travel" data-travel-id="' . $post_id . '">✗ Reject</button>';
            } elseif ($status === 'publish') {
                echo '<span class="cdv-approved">✓ Approved</span>';
            } else {
                echo '<span class="cdv-rejected">✗ Rejected</span>';
            }
        }

        if ($column === 'organizer') {
            $author_id = get_post_field('post_author', $post_id);
            $author = get_user_by('id', $author_id);
            $approved = CDV_User_Roles::is_user_approved($author_id);

            echo get_avatar($author_id, 32) . ' ';
            echo esc_html($author->display_name);

            if (!$approved) {
                echo ' <span class="cdv-user-pending" title="User not approved">⚠️</span>';
            }
        }
    }

    /**
     * Add bulk actions
     */
    public static function add_bulk_actions($actions) {
        $actions['cdv_approve'] = __('Approve Trips', 'compagni-di-viaggi');
        $actions['cdv_reject'] = __('Reject Trips', 'compagni-di-viaggi');
        return $actions;
    }

    /**
     * Handle bulk actions
     */
    public static function handle_bulk_actions($redirect_to, $action, $post_ids) {
        if ($action === 'cdv_approve') {
            foreach ($post_ids as $post_id) {
                self::approve_travel($post_id);
            }

            $redirect_to = add_query_arg('cdv_approved', count($post_ids), $redirect_to);
        }

        if ($action === 'cdv_reject') {
            foreach ($post_ids as $post_id) {
                self::reject_travel($post_id);
            }

            $redirect_to = add_query_arg('cdv_rejected', count($post_ids), $redirect_to);
        }

        return $redirect_to;
    }

    /**
     * AJAX: Approve travel
     */
    public static function ajax_approve_travel() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!current_user_can('approve_viaggi')) {
            wp_send_json_error(array('message' => 'Insufficient permissions.'));
        }

        $travel_id = intval($_POST['travel_id']);

        if (self::approve_travel($travel_id)) {
            wp_send_json_success(array('message' => 'Trip approved.'));
        } else {
            wp_send_json_error(array('message' => 'Error while approving the trip.'));
        }
    }

    /**
     * AJAX: Reject travel
     */
    public static function ajax_reject_travel() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!current_user_can('approve_viaggi')) {
            wp_send_json_error(array('message' => 'Insufficient permissions.'));
        }

        $travel_id = intval($_POST['travel_id']);
        $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';

        if (self::reject_travel($travel_id, $reason)) {
            wp_send_json_success(array('message' => 'Trip rejected.'));
        } else {
            wp_send_json_error(array('message' => 'Error while rejecting the trip.'));
        }
    }

    /**
     * Approve travel
     */
    public static function approve_travel($travel_id) {
        $result = wp_update_post(array(
            'ID' => $travel_id,
            'post_status' => 'publish',
        ));

        if ($result) {
            update_post_meta($travel_id, 'cdv_approved_date', current_time('mysql'));
            update_post_meta($travel_id, 'cdv_travel_status', 'open');

            // Notify author
            self::notify_author_approved($travel_id);

            do_action('cdv_travel_approved', $travel_id);
        }

        return $result;
    }

    /**
     * Reject travel
     */
    public static function reject_travel($travel_id, $reason = '') {
        $result = wp_update_post(array(
            'ID' => $travel_id,
            'post_status' => 'draft',
        ));

        if ($result) {
            update_post_meta($travel_id, 'cdv_rejected_date', current_time('mysql'));

            if ($reason) {
                update_post_meta($travel_id, 'cdv_rejection_reason', $reason);
            }

            // Notify author
            self::notify_author_rejected($travel_id, $reason);

            do_action('cdv_travel_rejected', $travel_id, $reason);
        }

        return $result;
    }

    /**
     * Notify author of approval
     */
    private static function notify_author_approved($travel_id) {
        $post = get_post($travel_id);
        $author = get_user_by('id', $post->post_author);

        $subject = __('Your trip has been approved!', 'compagni-di-viaggi');
        $message = sprintf(
            __("Hi %s,\n\nYour trip \"%s\" has been approved and is now live on the platform!\n\nView it here: %s\n\nHappy organizing!\nThe Compagni di Viaggi team", 'compagni-di-viaggi'),
            $author->display_name,
            $post->post_title,
            get_permalink($travel_id)
        );

        wp_mail($author->user_email, $subject, $message);
    }

    /**
     * Notify author of rejection
     */
    private static function notify_author_rejected($travel_id, $reason) {
        $post = get_post($travel_id);
        $author = get_user_by('id', $post->post_author);

        $subject = __('Your trip was not approved', 'compagni-di-viaggi');
        $message = sprintf(
            __("Hi %s,\n\nUnfortunately your trip \"%s\" was not approved.\n\n", 'compagni-di-viaggi'),
            $author->display_name,
            $post->post_title
        );

        if ($reason) {
            $message .= sprintf(__("Reason: %s\n\n", 'compagni-di-viaggi'), $reason);
        }

        $message .= __("You can edit the trip and submit it again for review.\n\nEdit here: ", 'compagni-di-viaggi') . get_edit_post_link($travel_id, '') . "\n\n" . __('The Compagni di Viaggi team', 'compagni-di-viaggi');

        wp_mail($author->user_email, $subject, $message);
    }

    /**
     * Admin notice for pending travels
     */
    public static function pending_travels_notice() {
        $screen = get_current_screen();

        if ($screen->id !== 'edit-viaggio') {
            return;
        }

        $pending_count = wp_count_posts('viaggio')->pending;

        if ($pending_count > 0) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>' . sprintf(__('%s trip(s) awaiting approval', 'compagni-di-viaggi'), $pending_count) . '</strong></p>';
            echo '</div>';
        }

        // Bulk action success messages
        if (isset($_GET['cdv_approved'])) {
            $count = intval($_GET['cdv_approved']);
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . sprintf(__('%s trip(s) approved successfully.', 'compagni-di-viaggi'), $count) . '</p>';
            echo '</div>';
        }

        if (isset($_GET['cdv_rejected'])) {
            $count = intval($_GET['cdv_rejected']);
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p>' . sprintf(__('%s trip(s) rejected.', 'compagni-di-viaggi'), $count) . '</p>';
            echo '</div>';
        }
    }

    /**
     * Get pending travels count
     */
    public static function get_pending_travels_count() {
        return wp_count_posts('viaggio')->pending;
    }

    /**
     * Check if user can publish travel
     */
    public static function can_user_publish_travel($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Must be approved user
        if (!CDV_User_Roles::is_user_approved($user_id)) {
            return false;
        }

        return true;
    }
}
