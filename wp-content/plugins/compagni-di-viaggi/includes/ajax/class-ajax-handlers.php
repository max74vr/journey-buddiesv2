<?php
/**
 * AJAX handlers for frontend interactions
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDV_Ajax_Handlers {

    /**
     * Initialize
     */
    public static function init() {
        // For logged-in users
        add_action('wp_ajax_cdv_join_travel', array(__CLASS__, 'join_travel'));
        add_action('wp_ajax_cdv_send_message', array(__CLASS__, 'send_message'));
        add_action('wp_ajax_cdv_get_new_messages', array(__CLASS__, 'get_new_messages'));
        add_action('wp_ajax_cdv_add_review', array(__CLASS__, 'add_review'));
        add_action('wp_ajax_cdv_accept_participant', array(__CLASS__, 'accept_participant'));
        add_action('wp_ajax_cdv_reject_participant', array(__CLASS__, 'reject_participant'));
        add_action('wp_ajax_cdv_approve_participant', array(__CLASS__, 'accept_participant'));
        add_action('wp_ajax_cdv_change_travel_status', array(__CLASS__, 'change_travel_status'));
        add_action('wp_ajax_cdv_delete_travel', array(__CLASS__, 'delete_travel'));
        add_action('wp_ajax_cdv_resend_verification', array(__CLASS__, 'resend_verification'));

        // Profile management
        add_action('wp_ajax_cdv_update_profile', array(__CLASS__, 'update_profile'));
        add_action('wp_ajax_cdv_change_password', array(__CLASS__, 'change_password'));
        add_action('wp_ajax_cdv_delete_account', array(__CLASS__, 'delete_account'));

        // Travel creation
        add_action('wp_ajax_cdv_create_travel', array(__CLASS__, 'create_travel'));
        add_action('wp_ajax_cdv_update_travel', array(__CLASS__, 'update_travel'));

        // For non-logged-in users (if needed)
        // add_action('wp_ajax_nopriv_action_name', array(__CLASS__, 'method_name'));
    }

    /**
     * AJAX: Join travel
     */
    public static function join_travel() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $travel_id = isset($_POST['travel_id']) ? intval($_POST['travel_id']) : 0;
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';

        if (!$travel_id) {
            wp_send_json_error(array('message' => 'Invalid trip ID.'));
        }

        $result = CDV_Participants::request_join($travel_id, get_current_user_id(), $message);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => 'Request submitted successfully.',
            'id' => $result,
        ));
    }

    /**
     * AJAX: Send chat message
     */
    public static function send_message() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $chat_group_id = isset($_POST['chat_group_id']) ? intval($_POST['chat_group_id']) : 0;
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';

        if (!$chat_group_id || empty($message)) {
            wp_send_json_error(array('message' => 'Invalid data.'));
        }

        // Check access
        if (!CDV_Chat::can_user_access_chat($chat_group_id, get_current_user_id())) {
            wp_send_json_error(array('message' => 'You do not have access to this chat.'));
        }

        $result = CDV_Chat::send_message($chat_group_id, get_current_user_id(), $message);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $user = wp_get_current_user();

        wp_send_json_success(array(
            'message' => array(
                'id' => $result,
                'user' => array(
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'avatar' => get_avatar_url($user->ID, array('size' => 40)),
                ),
                'message' => $message,
                'created_at' => current_time('mysql'),
            ),
        ));
    }

    /**
     * AJAX: Get new chat messages
     */
    public static function get_new_messages() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $chat_group_id = isset($_POST['chat_group_id']) ? intval($_POST['chat_group_id']) : 0;
        $since = isset($_POST['since']) ? sanitize_text_field($_POST['since']) : '';

        if (!$chat_group_id) {
            wp_send_json_error(array('message' => 'Invalid chat ID.'));
        }

        // Check access
        if (!CDV_Chat::can_user_access_chat($chat_group_id, get_current_user_id())) {
            wp_send_json_error(array('message' => 'You do not have access to this chat.'));
        }

        $messages = CDV_Chat::get_new_messages($chat_group_id, $since);

        $formatted = array();
        foreach ($messages as $msg) {
            $user = get_user_by('id', $msg->user_id);
            $formatted[] = array(
                'id' => $msg->id,
                'user' => array(
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'avatar' => get_avatar_url($user->ID, array('size' => 40)),
                ),
                'message' => $msg->message,
                'created_at' => $msg->created_at,
            );
        }

        wp_send_json_success(array('messages' => $formatted));
    }

    /**
     * AJAX: Add review
     */
    public static function add_review() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $travel_id = isset($_POST['travel_id']) ? intval($_POST['travel_id']) : 0;
        $reviewed_id = isset($_POST['reviewed_id']) ? intval($_POST['reviewed_id']) : 0;
        $scores = array(
            'punctuality' => isset($_POST['punctuality']) ? intval($_POST['punctuality']) : 0,
            'group_spirit' => isset($_POST['group_spirit']) ? intval($_POST['group_spirit']) : 0,
            'respect' => isset($_POST['respect']) ? intval($_POST['respect']) : 0,
            'adaptability' => isset($_POST['adaptability']) ? intval($_POST['adaptability']) : 0,
        );
        $comment = isset($_POST['comment']) ? sanitize_textarea_field($_POST['comment']) : '';

        if (!$travel_id || !$reviewed_id) {
            wp_send_json_error(array('message' => 'Invalid data.'));
        }

        $result = CDV_Reviews::add_review($travel_id, get_current_user_id(), $reviewed_id, $scores, $comment);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => 'Review added successfully.',
            'id' => $result,
        ));
    }

    /**
     * AJAX: Accept participant
     */
    public static function accept_participant() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $travel_id = isset($_POST['travel_id']) ? intval($_POST['travel_id']) : 0;
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if (!$travel_id || !$user_id) {
            wp_send_json_error(array('message' => 'Invalid data.'));
        }

        // Check if current user is the organizer
        $travel = get_post($travel_id);
        if ($travel->post_author != get_current_user_id()) {
            wp_send_json_error(array('message' => __('Only the organizer can approve participants.', 'compagni-di-viaggi')));
        }

        $result = CDV_Participants::accept_participant($travel_id, $user_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => 'Participant approved.'));
    }

    /**
     * AJAX: Reject participant
     */
    public static function reject_participant() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $travel_id = isset($_POST['travel_id']) ? intval($_POST['travel_id']) : 0;
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if (!$travel_id || !$user_id) {
            wp_send_json_error(array('message' => 'Invalid data.'));
        }

        // Check if current user is the organizer
        $travel = get_post($travel_id);
        if ($travel->post_author != get_current_user_id()) {
            wp_send_json_error(array('message' => __('Only the organizer can reject participants.', 'compagni-di-viaggi')));
        }

        $result = CDV_Participants::reject_participant($travel_id, $user_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => 'Participant rejected.'));
    }

    /**
     * AJAX: Change travel status
     */
    public static function change_travel_status() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in.');
        }

        $travel_id = isset($_POST['travel_id']) ? intval($_POST['travel_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (!$travel_id || !$status) {
            wp_send_json_error('Invalid data.');
        }

        // Check if current user is the author
        $travel = get_post($travel_id);
        if (!$travel || $travel->post_author != get_current_user_id()) {
            wp_send_json_error('You do not have permission to modify this trip.');
        }

        // Validate status
        $valid_statuses = array('open', 'full', 'closed', 'completed');
        if (!in_array($status, $valid_statuses)) {
            wp_send_json_error('Invalid status.');
        }

        update_post_meta($travel_id, 'cdv_travel_status', $status);

        wp_send_json_success('Status updated successfully.');
    }

    /**
     * AJAX: Delete travel
     */
    public static function delete_travel() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in.');
        }

        $travel_id = isset($_POST['travel_id']) ? intval($_POST['travel_id']) : 0;

        if (!$travel_id) {
            wp_send_json_error('Invalid trip ID.');
        }

        // Check if current user is the author
        $travel = get_post($travel_id);
        if (!$travel || $travel->post_author != get_current_user_id()) {
            wp_send_json_error('You do not have permission to delete this trip.');
        }

        // Delete the post (moves to trash)
        $result = wp_trash_post($travel_id);

        if (!$result) {
            wp_send_json_error('Error while deleting the trip.');
        }

        wp_send_json_success('Trip deleted successfully.');
    }

    /**
     * AJAX: Resend verification email
     */
    public static function resend_verification() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in.');
        }

        $user_id = get_current_user_id();

        // Abort if already verified.
        if (CDV_Email_Verification::is_email_verified($user_id)) {
            wp_send_json_error('Email already verified.');
        }

        // Resend email
        $result = CDV_Email_Verification::resend_verification_email($user_id);

        if ($result) {
            wp_send_json_success('Verification email sent successfully.');
        } else {
            wp_send_json_error('Error while sending the email.');
        }
    }

    /**
     * AJAX: Update Profile
     */
    public static function update_profile() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();

        $display_name = isset($_POST['display_name']) ? sanitize_text_field($_POST['display_name']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $bio = isset($_POST['bio']) ? sanitize_textarea_field($_POST['bio']) : '';

        // Update WordPress user
        $user_data = array(
            'ID' => $user_id,
            'display_name' => $display_name,
            'user_email' => $email,
        );

        $result = wp_update_user($user_data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Update user meta
        update_user_meta($user_id, 'cdv_city', $city);
        update_user_meta($user_id, 'cdv_phone', $phone);
        update_user_meta($user_id, 'cdv_bio', $bio);

        wp_send_json_success(array('message' => 'Profile updated successfully.'));
    }

    /**
     * AJAX: Change Password
     */
    public static function change_password() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();
        $user = get_user_by('id', $user_id);

        $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';

        // Verify current password
        if (!wp_check_password($current_password, $user->user_pass, $user_id)) {
            wp_send_json_error(array('message' => 'Current password is incorrect.'));
        }

        // Update password
        wp_set_password($new_password, $user_id);

        wp_send_json_success(array('message' => 'Password changed successfully.'));
    }

    /**
     * AJAX: Delete Account
     */
    public static function delete_account() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();

        // Don't allow admins to delete themselves via frontend
        if (user_can($user_id, 'manage_options')) {
            wp_send_json_error(array('message' => 'Administrators cannot delete their own account from the frontend.'));
        }

        // Delete user's travels
        $travels = get_posts(array(
            'post_type' => 'viaggio',
            'author' => $user_id,
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));

        foreach ($travels as $travel_id) {
            wp_delete_post($travel_id, true);
        }

        // Delete user
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        wp_delete_user($user_id);

        // Logout
        wp_logout();

        wp_send_json_success(array('message' => 'Account deleted successfully.'));
    }

    /**
     * AJAX: Create Travel
     */
    public static function create_travel() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();

        // Check if user has capability
        if (!current_user_can('create_viaggi')) {
            wp_send_json_error(array('message' => 'You do not have permission to create trips.'));
        }

        // Validate required fields
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $description = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';
        $destination = isset($_POST['destination']) ? sanitize_text_field($_POST['destination']) : '';
        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        $budget = isset($_POST['budget']) ? intval($_POST['budget']) : 0;
        $max_participants = isset($_POST['max_participants']) ? intval($_POST['max_participants']) : 5;
        $date_type = isset($_POST['date_type']) ? sanitize_text_field($_POST['date_type']) : 'precise';

        // Handle dates based on date_type
        $start_date = '';
        $end_date = '';
        $travel_month = '';

        if ($date_type === 'month') {
            // Mese indicativo - converti in date (primo e ultimo giorno del mese)
            $travel_month = isset($_POST['travel_month']) ? sanitize_text_field($_POST['travel_month']) : '';

            if (empty($travel_month)) {
                wp_send_json_error(array('message' => 'Please select a departure month.'));
            }

            // Calcola primo e ultimo giorno del mese (formato: YYYY-MM)
            $start_date = $travel_month . '-01';
            $end_date = date('Y-m-t', strtotime($start_date)); // Ultimo giorno del mese
        } else {
            // Date precise
            $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
            $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';

            if (empty($start_date) || empty($end_date)) {
                wp_send_json_error(array('message' => 'Please provide both start and end dates.'));
            }

            // Validate dates
            if (strtotime($end_date) <= strtotime($start_date)) {
                wp_send_json_error(array('message' => 'The end date must be after the start date.'));
            }
        }

        // Common validation
        if (empty($title) || empty($description) || empty($destination) || empty($country) ||
            $budget <= 0 || $max_participants < 2) {
            wp_send_json_error(array('message' => 'Please fill in all required fields.'));
        }

        // Validate start date is not in the past
        if (strtotime($start_date) < strtotime('today')) {
            wp_send_json_error(array('message' => 'The start date cannot be in the past.'));
        }

        // Create travel post
        $post_data = array(
            'post_type' => 'viaggio',
            'post_title' => $title,
            'post_content' => $description,
            'post_status' => 'pending', // Pending approval
            'post_author' => $user_id,
        );

        $travel_id = wp_insert_post($post_data);

        if (is_wp_error($travel_id)) {
            wp_send_json_error(array('message' => 'Error while creating the trip.'));
        }

        // Save meta data
        update_post_meta($travel_id, 'cdv_destination', $destination);
        update_post_meta($travel_id, 'cdv_country', $country);
        update_post_meta($travel_id, 'cdv_start_date', $start_date);
        update_post_meta($travel_id, 'cdv_end_date', $end_date);
        update_post_meta($travel_id, 'cdv_date_type', $date_type);
        update_post_meta($travel_id, 'cdv_budget', $budget);
        update_post_meta($travel_id, 'cdv_max_participants', $max_participants);
        update_post_meta($travel_id, 'cdv_travel_status', 'open');

        // Save travel_month if date_type is 'month'
        if ($date_type === 'month' && !empty($travel_month)) {
            update_post_meta($travel_id, 'cdv_travel_month', $travel_month);
        }

        // Save optional fields
        if (isset($_POST['travel_transport']) && is_array($_POST['travel_transport'])) {
            $travel_transport = array_map('sanitize_text_field', $_POST['travel_transport']);
            update_post_meta($travel_id, 'cdv_travel_transport', $travel_transport);
        }

        if (isset($_POST['travel_accommodation'])) {
            update_post_meta($travel_id, 'cdv_travel_accommodation', sanitize_text_field($_POST['travel_accommodation']));
        }

        if (isset($_POST['travel_difficulty'])) {
            update_post_meta($travel_id, 'cdv_travel_difficulty', sanitize_text_field($_POST['travel_difficulty']));
        }

        if (isset($_POST['travel_meals'])) {
            update_post_meta($travel_id, 'cdv_travel_meals', sanitize_text_field($_POST['travel_meals']));
        }

        if (isset($_POST['travel_guide_type'])) {
            update_post_meta($travel_id, 'cdv_travel_guide_type', sanitize_text_field($_POST['travel_guide_type']));
        }

        if (isset($_POST['travel_requirements'])) {
            update_post_meta($travel_id, 'cdv_travel_requirements', sanitize_textarea_field($_POST['travel_requirements']));
        }

        // Set travel types
        if (isset($_POST['travel_types']) && is_array($_POST['travel_types'])) {
            $travel_types = array_map('intval', $_POST['travel_types']);
            wp_set_post_terms($travel_id, $travel_types, 'tipo_viaggio');
        }

        // Add organizer as first participant
        global $wpdb;
        $table_name = $wpdb->prefix . 'cdv_travel_participants';

        $wpdb->insert(
            $table_name,
            array(
                'travel_id' => $travel_id,
                'user_id' => $user_id,
                'status' => 'accepted',
                'is_organizer' => 1,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%d', '%s')
        );

        wp_send_json_success(array(
            'message' => 'Trip created successfully! Pending administrator approval.',
            'redirect_url' => home_url('/dashboard'),
        ));
    }

    /**
     * AJAX: Update Travel
     */
    public static function update_travel() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();
        $travel_id = isset($_POST['travel_id']) ? intval($_POST['travel_id']) : 0;

        if (!$travel_id) {
            wp_send_json_error(array('message' => 'Invalid trip ID.'));
        }

        // Get travel post
        $travel = get_post($travel_id);

        if (!$travel || $travel->post_type !== 'viaggio') {
            wp_send_json_error(array('message' => 'Trip not found.'));
        }

        // Check if current user is the author
        if ($travel->post_author != $user_id) {
            wp_send_json_error(array('message' => 'You do not have permission to edit this trip.'));
        }

        // Validate required fields
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $description = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';
        $destination = isset($_POST['destination']) ? sanitize_text_field($_POST['destination']) : '';
        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        $budget = isset($_POST['budget']) ? intval($_POST['budget']) : 0;
        $max_participants = isset($_POST['max_participants']) ? intval($_POST['max_participants']) : 5;
        $date_type = isset($_POST['date_type']) ? sanitize_text_field($_POST['date_type']) : 'precise';

        // Handle dates based on date_type
        $start_date = '';
        $end_date = '';
        $travel_month = '';

        if ($date_type === 'month') {
            // Mese indicativo - converti in date (primo e ultimo giorno del mese)
            $travel_month = isset($_POST['travel_month']) ? sanitize_text_field($_POST['travel_month']) : '';

            if (empty($travel_month)) {
                wp_send_json_error(array('message' => 'Please select a departure month.'));
            }

            // Calcola primo e ultimo giorno del mese (formato: YYYY-MM)
            $start_date = $travel_month . '-01';
            $end_date = date('Y-m-t', strtotime($start_date)); // Ultimo giorno del mese
        } else {
            // Date precise
            $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
            $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';

            if (empty($start_date) || empty($end_date)) {
                wp_send_json_error(array('message' => 'Please provide both start and end dates.'));
            }

            // Validate dates
            if (strtotime($end_date) <= strtotime($start_date)) {
                wp_send_json_error(array('message' => 'The end date must be after the start date.'));
            }
        }

        // Common validation
        if (empty($title) || empty($description) || empty($destination) || empty($country) ||
            $budget <= 0 || $max_participants < 2) {
            wp_send_json_error(array('message' => 'Please fill in all required fields.'));
        }

        // Validate start date is not in the past (only for new dates, not when editing existing trips)
        // Allow editing of past trips, but new dates should be in the future
        if (strtotime($start_date) < strtotime('today')) {
            $old_start_date = get_post_meta($travel_id, 'cdv_start_date', true);
            if ($start_date !== $old_start_date) {
                wp_send_json_error(array('message' => 'The start date cannot be in the past.'));
            }
        }

        // Update travel post
        $post_data = array(
            'ID' => $travel_id,
            'post_title' => $title,
            'post_content' => $description,
        );

        $result = wp_update_post($post_data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => 'Error while updating the trip.'));
        }

        // Update meta data
        update_post_meta($travel_id, 'cdv_destination', $destination);
        update_post_meta($travel_id, 'cdv_country', $country);
        update_post_meta($travel_id, 'cdv_start_date', $start_date);
        update_post_meta($travel_id, 'cdv_end_date', $end_date);
        update_post_meta($travel_id, 'cdv_date_type', $date_type);
        update_post_meta($travel_id, 'cdv_budget', $budget);
        update_post_meta($travel_id, 'cdv_max_participants', $max_participants);

        // Save or delete travel_month based on date_type
        if ($date_type === 'month' && !empty($travel_month)) {
            update_post_meta($travel_id, 'cdv_travel_month', $travel_month);
        } else {
            delete_post_meta($travel_id, 'cdv_travel_month');
        }

        // Update optional fields
        if (isset($_POST['travel_transport']) && is_array($_POST['travel_transport'])) {
            $travel_transport = array_map('sanitize_text_field', $_POST['travel_transport']);
            update_post_meta($travel_id, 'cdv_travel_transport', $travel_transport);
        } else {
            delete_post_meta($travel_id, 'cdv_travel_transport');
        }

        if (isset($_POST['travel_accommodation'])) {
            update_post_meta($travel_id, 'cdv_travel_accommodation', sanitize_text_field($_POST['travel_accommodation']));
        } else {
            delete_post_meta($travel_id, 'cdv_travel_accommodation');
        }

        if (isset($_POST['travel_difficulty'])) {
            update_post_meta($travel_id, 'cdv_travel_difficulty', sanitize_text_field($_POST['travel_difficulty']));
        } else {
            delete_post_meta($travel_id, 'cdv_travel_difficulty');
        }

        if (isset($_POST['travel_meals'])) {
            update_post_meta($travel_id, 'cdv_travel_meals', sanitize_text_field($_POST['travel_meals']));
        } else {
            delete_post_meta($travel_id, 'cdv_travel_meals');
        }

        if (isset($_POST['travel_guide_type'])) {
            update_post_meta($travel_id, 'cdv_travel_guide_type', sanitize_text_field($_POST['travel_guide_type']));
        } else {
            delete_post_meta($travel_id, 'cdv_travel_guide_type');
        }

        if (isset($_POST['travel_requirements'])) {
            update_post_meta($travel_id, 'cdv_travel_requirements', sanitize_textarea_field($_POST['travel_requirements']));
        } else {
            delete_post_meta($travel_id, 'cdv_travel_requirements');
        }

        // Update travel types
        if (isset($_POST['travel_types']) && is_array($_POST['travel_types'])) {
            $travel_types = array_map('intval', $_POST['travel_types']);
            wp_set_post_terms($travel_id, $travel_types, 'tipo_viaggio');
        } else {
            wp_set_post_terms($travel_id, array(), 'tipo_viaggio');
        }

        wp_send_json_success(array(
            'message' => 'Trip updated successfully!',
            'redirect_url' => get_permalink($travel_id),
        ));
    }
}
