<?php
/**
 * Chat system
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDV_Chat {

    /**
     * Initialize
     */
    public static function init() {
        // AJAX handlers for group chat
        add_action('wp_ajax_cdv_send_group_message', array(__CLASS__, 'ajax_send_group_message'));
        add_action('wp_ajax_cdv_get_group_messages', array(__CLASS__, 'ajax_get_group_messages'));
        add_action('wp_ajax_cdv_contact_organizer', array(__CLASS__, 'ajax_contact_organizer'));
    }

    /**
     * Create chat group for a travel
     */
    public static function create_chat_group($travel_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'cdv_chat_groups';

        $travel = get_post($travel_id);
        if (!$travel) {
            return false;
        }

        $wpdb->insert(
            $table,
            array(
                'travel_id' => $travel_id,
                'name' => 'Chat: ' . get_the_title($travel_id),
            ),
            array('%d', '%s')
        );

        return $wpdb->insert_id;
    }

    /**
     * Get chat group by travel ID
     */
    public static function get_chat_group($travel_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'cdv_chat_groups';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE travel_id = %d",
            $travel_id
        ));
    }

    /**
     * Send message to chat group
     */
    public static function send_message($chat_group_id, $user_id, $message) {
        global $wpdb;

        $table = $wpdb->prefix . 'cdv_chat_messages';

        // Anti-spam check
        if (!self::check_spam($chat_group_id, $user_id)) {
            return new WP_Error('spam', __('You are sending messages too quickly. Please wait a moment.', 'compagni-di-viaggi'));
        }

        $result = $wpdb->insert(
            $table,
            array(
                'chat_group_id' => $chat_group_id,
                'user_id' => $user_id,
                'message' => sanitize_textarea_field($message),
            ),
            array('%d', '%d', '%s')
        );

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get messages from chat group
     */
    public static function get_messages($chat_group_id, $limit = 50, $offset = 0) {
        global $wpdb;

        $table = $wpdb->prefix . 'cdv_chat_messages';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table
            WHERE chat_group_id = %d
            ORDER BY created_at DESC
            LIMIT %d OFFSET %d",
            $chat_group_id,
            $limit,
            $offset
        ));
    }

    /**
     * Get new messages since timestamp
     */
    public static function get_new_messages($chat_group_id, $since) {
        global $wpdb;

        $table = $wpdb->prefix . 'cdv_chat_messages';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table
            WHERE chat_group_id = %d
            AND created_at > %s
            ORDER BY created_at ASC",
            $chat_group_id,
            $since
        ));
    }

    /**
     * Check if user can access chat
     */
    public static function can_user_access_chat($chat_group_id, $user_id) {
        global $wpdb;

        $chat_group = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cdv_chat_groups WHERE id = %d",
            $chat_group_id
        ));

        if (!$chat_group) {
            return false;
        }

        // Check if user is organizer or accepted participant
        $travel = get_post($chat_group->travel_id);
        if ($travel->post_author == $user_id) {
            return true;
        }

        return CDV_Participants::is_participant($chat_group->travel_id, $user_id, 'accepted');
    }

    /**
     * Anti-spam check
     */
    private static function check_spam($chat_group_id, $user_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'cdv_chat_messages';

        // Check messages in last minute
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table
            WHERE chat_group_id = %d
            AND user_id = %d
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            $chat_group_id,
            $user_id
        ));

        // Max 10 messages per minute
        return $count < 10;
    }

    /**
     * AJAX: Send group message
     */
    public static function ajax_send_group_message() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();
        $travel_id = isset($_POST['travel_id']) ? intval($_POST['travel_id']) : 0;
        $message = isset($_POST['message']) ? $_POST['message'] : '';

        if (empty(trim($message))) {
            wp_send_json_error(array('message' => 'The message cannot be empty.'));
        }

        // Get or create chat group
        $chat_group = self::get_chat_group($travel_id);
        if (!$chat_group) {
            $chat_group_id = self::create_chat_group($travel_id);
        } else {
            $chat_group_id = $chat_group->id;
        }

        if (!$chat_group_id) {
            wp_send_json_error(array('message' => 'Error creating chat group.'));
        }

        // Check if user can access chat
        if (!self::can_user_access_chat($chat_group_id, $user_id)) {
            wp_send_json_error(array('message' => 'You do not have access to this chat.'));
        }

        // Send message
        $message_id = self::send_message($chat_group_id, $user_id, $message);

        if (is_wp_error($message_id)) {
            wp_send_json_error(array('message' => $message_id->get_error_message()));
        }

        if ($message_id) {
            wp_send_json_success(array(
                'message' => 'Message sent successfully.',
                'message_id' => $message_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Error sending message.'));
        }
    }

    /**
     * AJAX: Get group messages
     */
    public static function ajax_get_group_messages() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();
        $travel_id = isset($_POST['travel_id']) ? intval($_POST['travel_id']) : 0;

        // Get chat group
        $chat_group = self::get_chat_group($travel_id);
        if (!$chat_group) {
            // No chat yet, return empty
            wp_send_json_success(array(
                'messages' => array(),
                'participants_count' => 0
            ));
        }

        // Check if user can access chat
        if (!self::can_user_access_chat($chat_group->id, $user_id)) {
            wp_send_json_error(array('message' => 'You do not have access to this chat.'));
        }

        // Get messages
        $messages = self::get_messages($chat_group->id, 50, 0);

        // Format messages for display
        $formatted_messages = array();
        foreach ($messages as $msg) {
            $user = get_userdata($msg->user_id);
            $formatted_messages[] = array(
                'id' => $msg->id,
                'user_id' => $msg->user_id,
                'user_name' => $user ? $user->user_login : 'Unknown',
                'avatar' => get_avatar($msg->user_id, 40),
                'message' => wp_kses_post(nl2br($msg->message)),
                'created_at' => $msg->created_at,
                'time_ago' => human_time_diff(strtotime($msg->created_at), current_time('timestamp')) . ' ago',
                'is_own' => $msg->user_id == $user_id
            );
        }

        // Reverse to show oldest first
        $formatted_messages = array_reverse($formatted_messages);

        // Get participants count
        $participants_count = 0;
        if (class_exists('CDV_Participants')) {
            $participants = CDV_Participants::get_participants($travel_id, 'accepted');
            $participants_count = count($participants) + 1; // +1 for organizer
        }

        wp_send_json_success(array(
            'messages' => $formatted_messages,
            'participants_count' => $participants_count
        ));
    }

    /**
     * AJAX: Contact organizer
     */
    public static function ajax_contact_organizer() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $sender_id = get_current_user_id();
        $travel_id = isset($_POST['travel_id']) ? intval($_POST['travel_id']) : 0;
        $organizer_id = isset($_POST['organizer_id']) ? intval($_POST['organizer_id']) : 0;
        $message = isset($_POST['message']) ? $_POST['message'] : '';

        if (empty(trim($message))) {
            wp_send_json_error(array('message' => 'The message cannot be empty.'));
        }

        if (!$travel_id || !$organizer_id) {
            wp_send_json_error(array('message' => 'Invalid data.'));
        }

        // Get travel post
        $travel = get_post($travel_id);
        if (!$travel) {
            wp_send_json_error(array('message' => 'Trip not found.'));
        }

        // Send email to organizer
        $sender = get_userdata($sender_id);
        $organizer = get_userdata($organizer_id);

        if (!$sender || !$organizer) {
            wp_send_json_error(array('message' => 'User not found.'));
        }

        $subject = sprintf('Question about: %s', $travel->post_title);
        $email_message = sprintf(
            "Hi %s,\n\n%s (%s) has sent you a question about the trip \"%s\":\n\n%s\n\nYou can reply to this email to contact them directly.\n\nJourney Buddies",
            $organizer->user_login,
            $sender->user_login,
            $sender->user_email,
            $travel->post_title,
            $message
        );

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: ' . $sender->user_email
        );

        $sent = wp_mail($organizer->user_email, $subject, $email_message, $headers);

        if ($sent) {
            wp_send_json_success(array('message' => 'Message sent successfully!'));
        } else {
            wp_send_json_error(array('message' => 'Error sending the message. Please try again.'));
        }
    }
}
