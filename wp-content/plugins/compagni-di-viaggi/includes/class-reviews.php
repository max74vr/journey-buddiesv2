<?php
/**
 * Reviews system
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDV_Reviews {

    /**
     * Initialize
     */
    public static function init() {
        // Hooks will be added here
    }

    /**
     * Add review
     */
    public static function add_review($travel_id, $reviewer_id, $reviewed_id, $scores, $comment = '') {
        global $wpdb;

        $table = $wpdb->prefix . 'cdv_reviews';

        // Validate scores (1-5)
        foreach ($scores as $score) {
            if ($score < 1 || $score > 5) {
                return new WP_Error('invalid_score', __('Scores must be between 1 and 5.', 'compagni-di-viaggi'));
            }
        }

        // Check if reviewer and reviewed were both participants
        if (!self::can_review($travel_id, $reviewer_id, $reviewed_id)) {
            return new WP_Error('cannot_review', __('You are not allowed to review this user.', 'compagni-di-viaggi'));
        }

        // Check if review already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE travel_id = %d AND reviewer_id = %d AND reviewed_id = %d",
            $travel_id,
            $reviewer_id,
            $reviewed_id
        ));

        if ($exists) {
            return new WP_Error('review_exists', __('You already reviewed this user for this trip.', 'compagni-di-viaggi'));
        }

        // Insert review
        $result = $wpdb->insert(
            $table,
            array(
                'travel_id' => $travel_id,
                'reviewer_id' => $reviewer_id,
                'reviewed_id' => $reviewed_id,
                'punctuality' => $scores['punctuality'],
                'group_spirit' => $scores['group_spirit'],
                'respect' => $scores['respect'],
                'adaptability' => $scores['adaptability'],
                'comment' => sanitize_textarea_field($comment),
            ),
            array('%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s')
        );

        if ($result) {
            // Update reviewed user's reputation
            CDV_User_Meta::update_user_reputation($reviewed_id);
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Check if user can review another user for a travel
     */
    public static function can_review($travel_id, $reviewer_id, $reviewed_id) {
        // Travel must be completed
        $status = get_post_meta($travel_id, 'cdv_travel_status', true);
        if ($status !== 'completed') {
            return false;
        }

        // Both must have been participants
        $reviewer_participated = CDV_Participants::is_participant($travel_id, $reviewer_id, 'accepted');
        $reviewed_participated = CDV_Participants::is_participant($travel_id, $reviewed_id, 'accepted');

        // Or reviewer is the organizer
        $travel = get_post($travel_id);
        $reviewer_is_organizer = ($travel->post_author == $reviewer_id);

        return ($reviewer_is_organizer || $reviewer_participated) && $reviewed_participated;
    }

    /**
     * Get reviews for a user
     */
    public static function get_user_reviews($user_id, $limit = 10) {
        global $wpdb;

        $table = $wpdb->prefix . 'cdv_reviews';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table
            WHERE reviewed_id = %d
            ORDER BY created_at DESC
            LIMIT %d",
            $user_id,
            $limit
        ));
    }

    /**
     * Get pending reviews for a user (travels they should review)
     */
    public static function get_pending_reviews($user_id) {
        global $wpdb;

        $table_participants = $wpdb->prefix . 'cdv_travel_participants';
        $table_reviews = $wpdb->prefix . 'cdv_reviews';

        // Get completed travels where user was a participant
        $travels = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT tp.travel_id
            FROM $table_participants tp
            INNER JOIN {$wpdb->posts} p ON tp.travel_id = p.ID
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE tp.user_id = %d
            AND tp.status = 'accepted'
            AND pm.meta_key = 'cdv_travel_status'
            AND pm.meta_value = 'completed'",
            $user_id
        ));

        $pending = array();

        foreach ($travels as $travel) {
            // Get other participants
            $participants = CDV_Participants::get_participants($travel->travel_id, 'accepted');

            foreach ($participants as $participant) {
                if ($participant->user_id == $user_id) {
                    continue;
                }

                // Check if already reviewed
                $reviewed = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table_reviews
                    WHERE travel_id = %d
                    AND reviewer_id = %d
                    AND reviewed_id = %d",
                    $travel->travel_id,
                    $user_id,
                    $participant->user_id
                ));

                if (!$reviewed) {
                    $pending[] = array(
                        'travel_id' => $travel->travel_id,
                        'user_id' => $participant->user_id,
                    );
                }
            }
        }

        return $pending;
    }
}
