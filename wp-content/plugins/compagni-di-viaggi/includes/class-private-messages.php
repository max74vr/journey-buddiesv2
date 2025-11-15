<?php
/**
 * Private Messages System
 *
 * Handles private messaging between users
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDV_Private_Messages {

    /**
     * Initialize
     */
    public static function init() {
        // AJAX handlers
        add_action('wp_ajax_cdv_send_private_message', array(__CLASS__, 'ajax_send_message'));
        add_action('wp_ajax_cdv_get_conversation', array(__CLASS__, 'ajax_get_conversation'));
        add_action('wp_ajax_cdv_get_conversations_list', array(__CLASS__, 'ajax_get_conversations_list'));
        add_action('wp_ajax_cdv_get_user_conversations', array(__CLASS__, 'ajax_get_user_conversations_formatted'));
        add_action('wp_ajax_cdv_block_conversation', array(__CLASS__, 'ajax_block_conversation'));
        add_action('wp_ajax_cdv_unblock_conversation', array(__CLASS__, 'ajax_unblock_conversation'));
        add_action('wp_ajax_cdv_mark_messages_read', array(__CLASS__, 'ajax_mark_messages_read'));

        // Admin actions
        add_action('wp_ajax_cdv_admin_get_all_conversations', array(__CLASS__, 'ajax_admin_get_all_conversations'));
        add_action('wp_ajax_cdv_admin_get_conversation', array(__CLASS__, 'ajax_admin_get_conversation'));

        // Hooks for automatic blocking
        add_action('cdv_participant_rejected', array(__CLASS__, 'block_on_rejection'), 10, 2);
    }

    /**
     * Create database table for private messages
     */
    public static function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cdv_private_messages';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            sender_id bigint(20) UNSIGNED NOT NULL,
            receiver_id bigint(20) UNSIGNED NOT NULL,
            travel_id bigint(20) UNSIGNED NOT NULL,
            message text NOT NULL,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY sender_id (sender_id),
            KEY receiver_id (receiver_id),
            KEY travel_id (travel_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Blocked conversations table
        $blocked_table = $wpdb->prefix . 'cdv_blocked_conversations';
        $sql_blocked = "CREATE TABLE IF NOT EXISTS $blocked_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            blocked_user_id bigint(20) UNSIGNED NOT NULL,
            travel_id bigint(20) UNSIGNED NOT NULL,
            reason varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_block (user_id, blocked_user_id, travel_id),
            KEY user_id (user_id),
            KEY blocked_user_id (blocked_user_id),
            KEY travel_id (travel_id)
        ) $charset_collate;";

        dbDelta($sql_blocked);
    }

    /**
     * Check if user can message another user
     */
    public static function can_message($sender_id, $receiver_id, $travel_id) {
        global $wpdb;

        // Check if they are both participants
        $participants_table = $wpdb->prefix . 'cdv_travel_participants';

        $sender_participant = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $participants_table
            WHERE travel_id = %d AND user_id = %d AND status = 'accepted'",
            $travel_id, $sender_id
        ));

        $receiver_participant = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $participants_table
            WHERE travel_id = %d AND user_id = %d AND status = 'accepted'",
            $travel_id, $receiver_id
        ));

        if (!$sender_participant || !$receiver_participant) {
            return false;
        }

        // Check if conversation is blocked
        if (self::is_conversation_blocked($sender_id, $receiver_id, $travel_id)) {
            return false;
        }

        return true;
    }

    /**
     * Check if conversation is blocked
     */
    public static function is_conversation_blocked($user1_id, $user2_id, $travel_id) {
        global $wpdb;
        $blocked_table = $wpdb->prefix . 'cdv_blocked_conversations';

        $blocked = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $blocked_table
            WHERE travel_id = %d AND (
                (user_id = %d AND blocked_user_id = %d) OR
                (user_id = %d AND blocked_user_id = %d)
            )",
            $travel_id, $user1_id, $user2_id, $user2_id, $user1_id
        ));

        return !empty($blocked);
    }

    /**
     * Send a message
     */
    public static function send_message($sender_id, $receiver_id, $travel_id, $message) {
        global $wpdb;

        // Validate
        if (!self::can_message($sender_id, $receiver_id, $travel_id)) {
            return new WP_Error('cannot_message', 'You cannot send messages to this user for this trip.');
        }

        if (empty(trim($message))) {
            return new WP_Error('empty_message', __('The message cannot be empty.', 'compagni-di-viaggi'));
        }

        $table_name = $wpdb->prefix . 'cdv_private_messages';

        $result = $wpdb->insert(
            $table_name,
            array(
                'sender_id' => $sender_id,
                'receiver_id' => $receiver_id,
                'travel_id' => $travel_id,
                'message' => sanitize_textarea_field($message),
                'is_read' => 0,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%d', '%s', '%d', '%s')
        );

        if (!$result) {
            return new WP_Error('db_error', 'Error while sending the message.');
        }

        $message_id = $wpdb->insert_id;

        // Send email notification
        self::send_notification_email($receiver_id, $sender_id, $travel_id);

        return $message_id;
    }

    /**
     * Send notification email (without message content)
     */
    private static function send_notification_email($receiver_id, $sender_id, $travel_id) {
        $receiver = get_userdata($receiver_id);
        $sender = get_userdata($sender_id);
        $travel = get_post($travel_id);

        if (!$receiver || !$sender || !$travel) {
            return false;
        }

        $subject = 'New message from ' . $sender->display_name;
        $message = sprintf(
            "Hi %s,\n\n%s sent you a new message about the trip \"%s\".\n\nOpen your dashboard to read it:\n%s\n\nDo not reply to this email.\n\nCompagni di Viaggi",
            $receiver->display_name,
            $sender->display_name,
            $travel->post_title,
            home_url('/dashboard?tab=messages&travel_id=' . $travel_id)
        );

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        return wp_mail($receiver->user_email, $subject, $message, $headers);
    }

    /**
     * Get conversation between two users
     */
    public static function get_conversation($user1_id, $user2_id, $travel_id, $limit = 50, $offset = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cdv_private_messages';

        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name
            WHERE travel_id = %d AND (
                (sender_id = %d AND receiver_id = %d) OR
                (sender_id = %d AND receiver_id = %d)
            )
            ORDER BY created_at DESC
            LIMIT %d OFFSET %d",
            $travel_id, $user1_id, $user2_id, $user2_id, $user1_id, $limit, $offset
        ));

        return array_reverse($messages);
    }

    /**
     * Get all conversations for a user
     */
    public static function get_user_conversations($user_id) {
        global $wpdb;
        $messages_table = $wpdb->prefix . 'cdv_private_messages';

        // Get unique conversations with last message
        $conversations = $wpdb->get_results($wpdb->prepare(
            "SELECT
                CASE
                    WHEN sender_id = %d THEN receiver_id
                    ELSE sender_id
                END as other_user_id,
                travel_id,
                MAX(created_at) as last_message_time,
                (SELECT COUNT(*) FROM $messages_table m2
                 WHERE m2.receiver_id = %d
                 AND m2.is_read = 0
                 AND m2.travel_id = $messages_table.travel_id
                 AND (m2.sender_id = CASE WHEN $messages_table.sender_id = %d THEN $messages_table.receiver_id ELSE $messages_table.sender_id END)
                ) as unread_count
            FROM $messages_table
            WHERE sender_id = %d OR receiver_id = %d
            GROUP BY other_user_id, travel_id
            ORDER BY last_message_time DESC",
            $user_id, $user_id, $user_id, $user_id, $user_id
        ));

        return $conversations;
    }

    /**
     * Mark messages as read
     */
    public static function mark_as_read($user_id, $other_user_id, $travel_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cdv_private_messages';

        return $wpdb->update(
            $table_name,
            array('is_read' => 1),
            array(
                'receiver_id' => $user_id,
                'sender_id' => $other_user_id,
                'travel_id' => $travel_id,
                'is_read' => 0,
            ),
            array('%d'),
            array('%d', '%d', '%d', '%d')
        );
    }

    /**
     * Block conversation
     */
    public static function block_conversation($user_id, $blocked_user_id, $travel_id, $reason = '') {
        global $wpdb;
        $blocked_table = $wpdb->prefix . 'cdv_blocked_conversations';

        $result = $wpdb->insert(
            $blocked_table,
            array(
                'user_id' => $user_id,
                'blocked_user_id' => $blocked_user_id,
                'travel_id' => $travel_id,
                'reason' => $reason,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%d', '%s', '%s')
        );

        return !is_wp_error($result);
    }

    /**
     * Unblock conversation
     */
    public static function unblock_conversation($user_id, $blocked_user_id, $travel_id) {
        global $wpdb;
        $blocked_table = $wpdb->prefix . 'cdv_blocked_conversations';

        return $wpdb->delete(
            $blocked_table,
            array(
                'user_id' => $user_id,
                'blocked_user_id' => $blocked_user_id,
                'travel_id' => $travel_id,
            ),
            array('%d', '%d', '%d')
        );
    }

    /**
     * Block conversation when participant is rejected
     */
    public static function block_on_rejection($travel_id, $user_id) {
        $organizer_id = get_post_field('post_author', $travel_id);

        // Block both ways
        self::block_conversation($organizer_id, $user_id, $travel_id, 'Participation request rejected');
        self::block_conversation($user_id, $organizer_id, $travel_id, 'Participation request rejected');
    }

    /**
     * Get unread messages count
     */
    public static function get_unread_count($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cdv_private_messages';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name
            WHERE receiver_id = %d AND is_read = 0",
            $user_id
        ));
    }

    /**
     * AJAX: Send message
     */
    public static function ajax_send_message() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $sender_id = get_current_user_id();
        $receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
        $travel_id = isset($_POST['travel_id']) ? intval($_POST['travel_id']) : 0;
        $message = isset($_POST['message']) ? $_POST['message'] : '';

        $result = self::send_message($sender_id, $receiver_id, $travel_id, $message);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => 'Message sent.',
            'message_id' => $result,
        ));
    }

    /**
     * AJAX: Get conversation
     */
    public static function ajax_get_conversation() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();
        $other_user_id = isset($_POST['other_user_id']) ? intval($_POST['other_user_id']) : 0;
        $travel_id = isset($_POST['travel_id']) ? intval($_POST['travel_id']) : 0;

        $messages = self::get_conversation($user_id, $other_user_id, $travel_id);

        // Mark as read
        self::mark_as_read($user_id, $other_user_id, $travel_id);

        wp_send_json_success(array('messages' => $messages));
    }

    /**
     * AJAX: Get conversations list
     */
    public static function ajax_get_conversations_list() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();
        $conversations = self::get_user_conversations($user_id);

        wp_send_json_success(array('conversations' => $conversations));
    }

    /**
     * AJAX: Get user conversations formatted for dashboard
     */
    public static function ajax_get_user_conversations_formatted() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();
        $conversations = self::get_user_conversations($user_id);

        // Format conversations with user and travel data
        $formatted = array();
        foreach ($conversations as $conv) {
            $other_user = get_userdata($conv->other_user_id);
            $travel = get_post($conv->travel_id);

            if ($other_user && $travel) {
                $formatted[] = array(
                    'other_user_id' => $conv->other_user_id,
                    'other_user_name' => $other_user->display_name,
                    'avatar' => get_avatar($conv->other_user_id, 48),
                    'travel_id' => $conv->travel_id,
                    'travel_title' => $travel->post_title,
                    'last_message_time' => human_time_diff(strtotime($conv->last_message_time), current_time('timestamp')) . ' ago',
                    'unread_count' => $conv->unread_count,
                );
            }
        }

        wp_send_json_success($formatted);
    }

    /**
     * AJAX: Block conversation
     */
    public static function ajax_block_conversation() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();
        $blocked_user_id = isset($_POST['blocked_user_id']) ? intval($_POST['blocked_user_id']) : 0;
        $travel_id = isset($_POST['travel_id']) ? intval($_POST['travel_id']) : 0;

        $result = self::block_conversation($user_id, $blocked_user_id, $travel_id, 'Blocked by the user');

        if ($result) {
            wp_send_json_success(array('message' => 'Conversation blocked.'));
        } else {
            wp_send_json_error(array('message' => 'Error while blocking the conversation.'));
        }
    }

    /**
     * AJAX: Unblock conversation
     */
    public static function ajax_unblock_conversation() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();
        $blocked_user_id = isset($_POST['blocked_user_id']) ? intval($_POST['blocked_user_id']) : 0;
        $travel_id = isset($_POST['travel_id']) ? intval($_POST['travel_id']) : 0;

        $result = self::unblock_conversation($user_id, $blocked_user_id, $travel_id);

        if ($result) {
            wp_send_json_success(array('message' => 'Conversation unblocked.'));
        } else {
            wp_send_json_error(array('message' => 'Error while unblocking the conversation.'));
        }
    }

    /**
     * AJAX: Mark messages as read
     */
    public static function ajax_mark_messages_read() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }

        $user_id = get_current_user_id();
        $other_user_id = isset($_POST['other_user_id']) ? intval($_POST['other_user_id']) : 0;
        $travel_id = isset($_POST['travel_id']) ? intval($_POST['travel_id']) : 0;

        self::mark_as_read($user_id, $other_user_id, $travel_id);

        wp_send_json_success(array('message' => 'Messages marked as read.'));
    }

    /**
     * AJAX: Admin - Get all conversations
     */
    public static function ajax_admin_get_all_conversations() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions.'));
        }

        global $wpdb;
        $messages_table = $wpdb->prefix . 'cdv_private_messages';

        // Get all unique conversations
        $conversations = $wpdb->get_results(
            "SELECT
                sender_id,
                receiver_id,
                travel_id,
                MAX(created_at) as last_message_time,
                COUNT(*) as message_count
            FROM $messages_table
            GROUP BY sender_id, receiver_id, travel_id
            ORDER BY last_message_time DESC
            LIMIT 100"
        );

        wp_send_json_success(array('conversations' => $conversations));
    }

    /**
     * AJAX: Admin - Get specific conversation
     */
    public static function ajax_admin_get_conversation() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions.'));
        }

        $user1_id = isset($_POST['user1_id']) ? intval($_POST['user1_id']) : 0;
        $user2_id = isset($_POST['user2_id']) ? intval($_POST['user2_id']) : 0;
        $travel_id = isset($_POST['travel_id']) ? intval($_POST['travel_id']) : 0;

        $messages = self::get_conversation($user1_id, $user2_id, $travel_id);

        wp_send_json_success(array('messages' => $messages));
    }
}
