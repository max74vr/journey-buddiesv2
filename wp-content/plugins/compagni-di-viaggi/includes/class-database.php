<?php
/**
 * Database tables management
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDV_Database {

    /**
     * Create custom database tables
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Table: travel_participants
        $table_participants = $wpdb->prefix . 'cdv_travel_participants';
        $sql_participants = "CREATE TABLE IF NOT EXISTS $table_participants (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            travel_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            message text,
            requested_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY travel_id (travel_id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";

        dbDelta($sql_participants);

        // Table: chat_groups
        $table_chat_groups = $wpdb->prefix . 'cdv_chat_groups';
        $sql_chat_groups = "CREATE TABLE IF NOT EXISTS $table_chat_groups (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            travel_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY travel_id (travel_id)
        ) $charset_collate;";

        dbDelta($sql_chat_groups);

        // Table: chat_messages
        $table_chat_messages = $wpdb->prefix . 'cdv_chat_messages';
        $sql_chat_messages = "CREATE TABLE IF NOT EXISTS $table_chat_messages (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            chat_group_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            message text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY chat_group_id (chat_group_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta($sql_chat_messages);

        // Table: reviews
        $table_reviews = $wpdb->prefix . 'cdv_reviews';
        $sql_reviews = "CREATE TABLE IF NOT EXISTS $table_reviews (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            travel_id bigint(20) UNSIGNED NOT NULL,
            reviewer_id bigint(20) UNSIGNED NOT NULL,
            reviewed_id bigint(20) UNSIGNED NOT NULL,
            punctuality tinyint(1) NOT NULL,
            group_spirit tinyint(1) NOT NULL,
            respect tinyint(1) NOT NULL,
            adaptability tinyint(1) NOT NULL,
            comment text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_review (travel_id, reviewer_id, reviewed_id),
            KEY travel_id (travel_id),
            KEY reviewer_id (reviewer_id),
            KEY reviewed_id (reviewed_id)
        ) $charset_collate;";

        dbDelta($sql_reviews);

        // Table: user_badges
        $table_badges = $wpdb->prefix . 'cdv_user_badges';
        $sql_badges = "CREATE TABLE IF NOT EXISTS $table_badges (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            badge_type varchar(50) NOT NULL,
            earned_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_badge (user_id, badge_type),
            KEY user_id (user_id)
        ) $charset_collate;";

        dbDelta($sql_badges);

        // Table: email_verification_tokens
        $table_email_tokens = $wpdb->prefix . 'cdv_email_verification';
        $sql_email_tokens = "CREATE TABLE IF NOT EXISTS $table_email_tokens (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            token varchar(64) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            verified_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY user_id (user_id)
        ) $charset_collate;";

        dbDelta($sql_email_tokens);

        // Table: private_messages
        CDV_Private_Messages::create_table();

        // Update version
        update_option('cdv_db_version', '1.2.0');
    }

    /**
     * Drop custom tables (used on uninstall)
     */
    public static function drop_tables() {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'cdv_travel_participants',
            $wpdb->prefix . 'cdv_chat_groups',
            $wpdb->prefix . 'cdv_chat_messages',
            $wpdb->prefix . 'cdv_reviews',
            $wpdb->prefix . 'cdv_user_badges',
            $wpdb->prefix . 'cdv_email_verification',
            $wpdb->prefix . 'cdv_private_messages',
            $wpdb->prefix . 'cdv_blocked_conversations',
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        delete_option('cdv_db_version');
    }
}
