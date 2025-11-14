<?php
/**
 * User badges system
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDV_Badges {

    /**
     * Available badge types (loaded lazily so we can safely call translation functions)
     *
     * @var array|null
     */
    private static $badge_types = null;

    /**
     * Lazy-load badge type definitions.
     */
    private static function load_badge_types() {
        if (self::$badge_types !== null) {
            return;
        }

        self::$badge_types = array(
            'early_adopter' => array(
                'name' => __('Early Adopter', 'compagni-di-viaggi'),
                'icon' => '⭐',
                'description' => __('Among the very first members of the community.', 'compagni-di-viaggi'),
            ),
            'verified' => array(
                'name' => __('Verified', 'compagni-di-viaggi'),
                'icon' => '✓',
                'description' => __('Identity manually verified.', 'compagni-di-viaggi'),
            ),
            'first_travel' => array(
                'name' => __('First Trip', 'compagni-di-viaggi'),
                'icon' => '🚀',
                'description' => __('Organized their first trip.', 'compagni-di-viaggi'),
            ),
            'first_story' => array(
                'name' => __('Storyteller', 'compagni-di-viaggi'),
                'icon' => '📖',
                'description' => __('Published the first travel story.', 'compagni-di-viaggi'),
            ),
            'explorer' => array(
                'name' => __('Explorer', 'compagni-di-viaggi'),
                'icon' => '🧭',
                'description' => __('Joined 5 trips.', 'compagni-di-viaggi'),
            ),
            'globetrotter' => array(
                'name' => __('Globetrotter', 'compagni-di-viaggi'),
                'icon' => '✈️',
                'description' => __('Joined 10 trips.', 'compagni-di-viaggi'),
            ),
            'organizer' => array(
                'name' => __('Organizer', 'compagni-di-viaggi'),
                'icon' => '📅',
                'description' => __('Organized 5 trips.', 'compagni-di-viaggi'),
            ),
            'trusted' => array(
                'name' => __('Trusted', 'compagni-di-viaggi'),
                'icon' => '🌟',
                'description' => __('Reputation score above 4.5.', 'compagni-di-viaggi'),
            ),
            'social' => array(
                'name' => __('Social Butterfly', 'compagni-di-viaggi'),
                'icon' => '🎉',
                'description' => __('Left 10 positive reviews.', 'compagni-di-viaggi'),
            ),
            'storyteller' => array(
                'name' => __('Story Collector', 'compagni-di-viaggi'),
                'icon' => '📚',
                'description' => __('Published 10 travel stories.', 'compagni-di-viaggi'),
            ),
        );
    }

    /**
     * Initialize
     */
    public static function init() {
        add_action('user_register', array(__CLASS__, 'award_early_adopter'));
        add_action('cdv_participant_accepted', array(__CLASS__, 'check_travel_badges'));
        add_action('cdv_review_added', array(__CLASS__, 'check_review_badges'));
    }

    /**
     * Award early adopter badge to new users
     */
    public static function award_early_adopter($user_id) {
        self::award_badge($user_id, 'early_adopter');
    }

    /**
     * Award badge to user
     */
    public static function award_badge($user_id, $badge_type) {
        global $wpdb;

        $table = $wpdb->prefix . 'cdv_user_badges';

        // Check if badge already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND badge_type = %s",
            $user_id,
            $badge_type
        ));

        if ($exists) {
            return false;
        }

        $result = $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'badge_type' => $badge_type,
            ),
            array('%d', '%s')
        );

        if ($result) {
            do_action('cdv_badge_awarded', $user_id, $badge_type);
            return true;
        }

        return false;
    }

    /**
     * Get user badges
     */
    public static function get_user_badges($user_id) {
        global $wpdb;

        self::load_badge_types();

        $table = $wpdb->prefix . 'cdv_user_badges';

        $badges = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY earned_at DESC",
            $user_id
        ));

        $result = array();
        foreach ($badges as $badge) {
            if (isset(self::$badge_types[$badge->badge_type])) {
                $result[] = array_merge(
                    (array) $badge,
                    self::$badge_types[$badge->badge_type]
                );
            }
        }

        return $result;
    }

    /**
     * Check and award travel-related badges
     */
    public static function check_travel_badges($user_id) {
        global $wpdb;

        $table_participants = $wpdb->prefix . 'cdv_travel_participants';

        // Count completed travels
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT tp.travel_id)
            FROM $table_participants tp
            INNER JOIN {$wpdb->postmeta} pm ON tp.travel_id = pm.post_id
            WHERE tp.user_id = %d
            AND tp.status = 'accepted'
            AND pm.meta_key = 'cdv_travel_status'
            AND pm.meta_value = 'completed'",
            $user_id
        ));

        if ($count >= 10) {
            self::award_badge($user_id, 'globetrotter');
        } elseif ($count >= 5) {
            self::award_badge($user_id, 'explorer');
        }

        // Count organized travels
        $organized = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_author = %d
            AND p.post_type = 'viaggio'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'cdv_travel_status'
            AND pm.meta_value = 'completed'",
            $user_id
        ));

        if ($organized >= 5) {
            self::award_badge($user_id, 'organizer');
        }

        // Check reputation
        $reputation = get_user_meta($user_id, 'cdv_reputation_score', true);
        if ($reputation >= 4.5) {
            self::award_badge($user_id, 'trusted');
        }
    }

    /**
     * Check and award review-related badges
     */
    public static function check_review_badges($user_id) {
        global $wpdb;

        $table_reviews = $wpdb->prefix . 'cdv_reviews';

        // Count positive reviews given
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
            FROM $table_reviews
            WHERE reviewer_id = %d
            AND ((punctuality + group_spirit + respect + adaptability) / 4) >= 4",
            $user_id
        ));

        if ($count >= 10) {
            self::award_badge($user_id, 'social');
        }
    }

    /**
     * Get all badge types
     */
    public static function get_badge_types() {
        self::load_badge_types();

        return self::$badge_types;
    }

    /**
     * Check if user has badge
     */
    public static function has_badge($user_id, $badge_type) {
        global $wpdb;

        $table = $wpdb->prefix . 'cdv_user_badges';

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND badge_type = %s",
            $user_id,
            $badge_type
        ));

        return !is_null($result);
    }
}
