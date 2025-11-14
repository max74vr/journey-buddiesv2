<?php
/**
 * Email Verification
 *
 * Handles email verification for new users.
 */

class CDV_Email_Verification {

    public static function init() {
        add_action('init', array(__CLASS__, 'handle_verification'));
        add_filter('authenticate', array(__CLASS__, 'block_unverified_login'), 30, 3);
    }

    /**
     * Generate and send email verification token.
     */
    public static function send_verification_email($user_id) {
        global $wpdb;

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // Ensure the verification table exists.
        $table_name = $wpdb->prefix . 'cdv_email_verification';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if (!$table_exists) {
            // Table missing: skip verification but don't block registration.
            error_log('CDV: Email verification table does not exist. Skipping email verification.');
            return true;
        }

        // Generate unique token.
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Store token in database.
        $result = $wpdb->insert($table_name, array(
            'user_id' => $user_id,
            'token' => $token,
            'expires_at' => $expires_at,
        ));

        if (!$result) {
            error_log('CDV: Failed to insert email verification token for user ' . $user_id);
            return true;
        }

        // Build verification link.
        $verification_link = home_url('/email-confirmation/?token=' . $token);

        // Send email.
        $subject = __('Confirm your account - Compagni di Viaggi', 'compagni-di-viaggi');
        $message = "
Hello {$user->display_name},

Thank you for joining Compagni di Viaggi!

To complete the process and activate your account, click the link below:

{$verification_link}

This link remains valid for 24 hours.

If you did not create this account, simply ignore this email.

See you soon,
The Compagni di Viaggi team
        ";

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        $result = wp_mail($user->user_email, $subject, $message, $headers);

        if (!$result) {
            error_log('CDV: Failed to send verification email to user ' . $user_id . ' (' . $user->user_email . ')');
            error_log('CDV: WordPress wp_mail() returned false. Possible causes:');
            error_log('CDV: 1. Server mail() function not configured');
            error_log('CDV: 2. No SMTP plugin installed (recommended: WP Mail SMTP)');
            error_log('CDV: 3. Email address or domain blocked by hosting provider');
        } else {
            error_log('CDV: Verification email sent successfully to user ' . $user_id . ' (' . $user->user_email . ')');
        }

        return $result;
    }

    /**
     * Validate a token and activate the account.
     */
    public static function verify_token($token) {
        global $wpdb;

        if (empty($token)) {
            return new WP_Error('invalid_token', __('Invalid token.', 'compagni-di-viaggi'));
        }

        $table_name = $wpdb->prefix . 'cdv_email_verification';

        // Look up the token
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE token = %s AND verified_at IS NULL",
            $token
        ));

        if (!$record) {
            return new WP_Error('invalid_token', __('Invalid or already used token.', 'compagni-di-viaggi'));
        }

        // Controlla scadenza
        if (strtotime($record->expires_at) < current_time('timestamp')) {
            return new WP_Error('expired_token', __('Token expired. Request a new verification email.', 'compagni-di-viaggi'));
        }

        // Marca come verificato
        $wpdb->update(
            $table_name,
            array('verified_at' => current_time('mysql')),
            array('id' => $record->id)
        );

        // Update user meta and approve the account
        update_user_meta($record->user_id, 'cdv_email_verified', 'yes');
        update_user_meta($record->user_id, 'cdv_user_approved', '1');
        update_user_meta($record->user_id, 'cdv_email_verified_date', current_time('mysql'));

        return $record->user_id;
    }

    /**
     * Check if user verified email.
     */
    public static function is_email_verified($user_id) {
        return get_user_meta($user_id, 'cdv_email_verified', true) === 'yes';
    }

    /**
     * Block login for unverified users.
     */
    public static function block_unverified_login($user, $username, $password) {
        // Pass through errors untouched.
        if (is_wp_error($user)) {
            return $user;
        }

        // Bail if this is not a WP_User instance.
        if (!is_a($user, 'WP_User')) {
            return $user;
        }

        // Administrators can always log in.
        if (in_array('administrator', $user->roles)) {
            return $user;
        }

        // Check verification/approval flags.
        $email_verified = get_user_meta($user->ID, 'cdv_email_verified', true);
        $user_approved = get_user_meta($user->ID, 'cdv_user_approved', true);

        if ($email_verified !== 'yes' || $user_approved !== '1') {
            return new WP_Error(
                'email_not_verified',
                __('<strong>Error:</strong> Please confirm your email address before logging in. Check your inbox (and spam folder) for the confirmation link.', 'compagni-di-viaggi')
            );
        }

        return $user;
    }

    /**
     * Handle verification callback via URL.
     */
    public static function handle_verification() {
        if (isset($_GET['token']) && is_page('email-confirmation')) {
            $token = sanitize_text_field($_GET['token']);
            $result = self::verify_token($token);

            if (is_wp_error($result)) {
                set_transient('cdv_verification_error_' . session_id(), $result->get_error_message(), 60);
            } else {
                set_transient('cdv_verification_success_' . session_id(), $result, 60);
            }
        }
    }

    /**
     * Resend verification email.
     */
    public static function resend_verification_email($user_id) {
        global $wpdb;

        // Delete pending tokens.
        $table_name = $wpdb->prefix . 'cdv_email_verification';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE user_id = %d AND verified_at IS NULL",
            $user_id
        ));

        // Send new token.
        return self::send_verification_email($user_id);
    }
}
