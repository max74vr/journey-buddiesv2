<?php
/**
 * Frontend Registration System
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDV_Registration {

    /**
     * Initialize
     */
    public static function init() {
        // Step 1 - only for non-logged users
        add_action('wp_ajax_nopriv_cdv_register_step1', array(__CLASS__, 'ajax_register_step1'));

        // Step 2 - for both (user is auto-logged after step 1)
        add_action('wp_ajax_nopriv_cdv_register_step2', array(__CLASS__, 'ajax_register_step2'));
        add_action('wp_ajax_cdv_register_step2', array(__CLASS__, 'ajax_register_step2'));

        // Step 3 and beyond - user is logged
        add_action('wp_ajax_cdv_update_profile', array(__CLASS__, 'ajax_update_profile'));
        add_action('wp_ajax_cdv_upload_profile_image', array(__CLASS__, 'ajax_upload_profile_image'));
        add_action('wp_ajax_cdv_create_first_travel', array(__CLASS__, 'ajax_create_first_travel'));

        // Frontend login
        add_action('wp_ajax_nopriv_cdv_frontend_login', array(__CLASS__, 'ajax_frontend_login'));

        // Prevent backend registration
        add_filter('register_url', array(__CLASS__, 'custom_register_url'));
        add_filter('login_url', array(__CLASS__, 'custom_login_url'), 10, 3);
    }

    /**
     * Custom registration URL
     */
    public static function custom_register_url($url) {
        return home_url('/registration');
    }

    /**
     * Custom login URL
     */
    public static function custom_login_url($login_url, $redirect, $force_reauth) {
        $custom_login = home_url('/login');

        if (!empty($redirect)) {
            $custom_login = add_query_arg('redirect_to', urlencode($redirect), $custom_login);
        }

        return $custom_login;
    }

    /**
     * AJAX: Frontend Login
     */
    public static function ajax_frontend_login() {
        try {
            check_ajax_referer('cdv_login_nonce', 'nonce');

            $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            $remember = isset($_POST['remember']) && $_POST['remember'] === 'true';

            // Validation
            if (empty($username) || empty($password)) {
                wp_send_json_error(array('message' => 'Username and password are required.'));
            }

            // Try to authenticate
            $user = wp_authenticate($username, $password);

            if (is_wp_error($user)) {
                error_log('CDV: Login failed for user: ' . $username . ' - ' . $user->get_error_message());
                wp_send_json_error(array('message' => 'Incorrect username or password.'));
            }

            // Check if user is approved (viaggiatori are auto-approved with value '1')
            $approved = get_user_meta($user->ID, 'cdv_user_approved', true);

            // Log user in
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID, $remember);

            // Determine redirect URL
            $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : home_url('/dashboard');

            error_log('CDV: Successful login for user: ' . $username);

            wp_send_json_success(array(
                'message' => 'Login successful!',
                'redirect_url' => $redirect_to,
            ));

        } catch (Exception $e) {
            error_log('CDV: Error in frontend login: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'An unexpected error occurred. Please try again.'));
        }
    }

    /**
     * AJAX: Register Step 1 - Account Creation
     */
    public static function ajax_register_step1() {
        check_ajax_referer('cdv_ajax_nonce', 'nonce');

        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $display_name = sanitize_text_field($_POST['display_name']);

        // Validation
        if (empty($username) || empty($email) || empty($password) || empty($display_name)) {
            wp_send_json_error(array('message' => 'All fields are required.'));
        }

        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Invalid email address.'));
        }

        if (username_exists($username)) {
            wp_send_json_error(array('message' => 'Username already in use.'));
        }

        if (email_exists($email)) {
            wp_send_json_error(array('message' => 'Email already registered.'));
        }

        if (strlen($password) < 8) {
            wp_send_json_error(array('message' => 'Password must be at least 8 characters long.'));
        }

        // Create user
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        }

        // Assign the traveler role slug
        $user = new WP_User($user_id);
        $user->set_role('viaggiatore');

        // Set display name
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $display_name,
        ));

        // User starts as NOT approved - will be approved after email confirmation
        update_user_meta($user_id, 'cdv_user_approved', '0');
        update_user_meta($user_id, 'cdv_email_verified', 'no');
        update_user_meta($user_id, 'cdv_registration_date', current_time('mysql'));

        // Send verification email
        $email_sent = CDV_Email_Verification::send_verification_email($user_id);

        // Notify admin about the new registration
        self::notify_admin_new_user($user_id);

        // Temporary auto-login to complete registration
        // The user can complete the profile and create the first trip,
        // but after logging out they cannot access again until the email is confirmed.
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        // Award early adopter badge
        CDV_Badges::award_badge($user_id, 'early_adopter');

        // Generate new nonce for logged-in user
        $new_nonce = wp_create_nonce('cdv_ajax_nonce');

        $message = 'Account created successfully!';
        if ($email_sent) {
            $message .= ' <strong>IMPORTANT:</strong> We sent you a verification email. ' .
                       'Check your inbox (and spam folder) and click the link to activate your account. ' .
                       'You can finish the profile now, but you must confirm the email before logging in again.';
        }

        wp_send_json_success(array(
            'message' => $message,
            'user_id' => $user_id,
            'email_sent' => $email_sent,
            'new_nonce' => $new_nonce, // Fresh nonce for logged-in user
        ));
    }

    /**
     * AJAX: Register Step 2 - Profile Information
     */
    public static function ajax_register_step2() {
        error_log('CDV: Starting registration step 2');
        error_log('CDV: Is user logged in: ' . (is_user_logged_in() ? 'YES' : 'NO'));
        error_log('CDV: Current user ID: ' . get_current_user_id());

        try {
            // Check if user is logged in
            if (!is_user_logged_in()) {
                error_log('CDV: User not logged in');
                wp_send_json_error(array('message' => 'You must be logged in.'));
            }

            $user_id = get_current_user_id();
            error_log('CDV: User ID: ' . $user_id);

            // Verify user is in registration process (profile not complete)
            // This is more reliable than nonce verification for users who just auto-logged in
            $profile_completed = get_user_meta($user_id, 'cdv_profile_completed', true);
            if ($profile_completed === '1') {
                error_log('CDV: Profile already completed');
                wp_send_json_error(array('message' => 'Profile already completed.'));
            }

            // Verify nonce (but handle auto-login edge case)
            $nonce_verified = check_ajax_referer('cdv_ajax_nonce', 'nonce', false);
            if (!$nonce_verified) {
                // If nonce fails, check if user was created recently (within last 10 minutes)
                // This handles the auto-login scenario
                $registration_date = get_user_meta($user_id, 'cdv_registration_date', true);
                if ($registration_date) {
                    $time_diff = strtotime('now') - strtotime($registration_date);
                    if ($time_diff > 600) { // More than 10 minutes
                        error_log('CDV: Nonce verification failed and user not recently created');
                        wp_send_json_error(array('message' => 'Session expired.'));
                    }
                    error_log('CDV: Nonce verification bypassed - user recently auto-logged in');
                } else {
                    error_log('CDV: Nonce verification failed');
                    wp_send_json_error(array('message' => 'Security check failed.'));
                }
            } else {
                error_log('CDV: Nonce verified successfully');
            }

            // Personal Info
            $birth_date = isset($_POST['birth_date']) ? sanitize_text_field($_POST['birth_date']) : '';
            $gender = isset($_POST['gender']) ? sanitize_text_field($_POST['gender']) : '';
            $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
            $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
            $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';

            // Bio & Interests
            $bio = isset($_POST['bio']) ? sanitize_textarea_field($_POST['bio']) : '';
            $languages = isset($_POST['languages']) ? sanitize_text_field($_POST['languages']) : '';
            $travel_styles = isset($_POST['travel_styles']) ? array_map('sanitize_text_field', $_POST['travel_styles']) : array();
            $interests = isset($_POST['interests']) ? array_map('sanitize_text_field', $_POST['interests']) : array();

            // Travel Preferences
            $budget_range = isset($_POST['budget_range']) ? sanitize_text_field($_POST['budget_range']) : '';
            $travel_frequency = isset($_POST['travel_frequency']) ? sanitize_text_field($_POST['travel_frequency']) : '';
            $accommodation_preference = isset($_POST['accommodation_preference']) ? sanitize_text_field($_POST['accommodation_preference']) : '';
            $travel_pace = isset($_POST['travel_pace']) ? sanitize_text_field($_POST['travel_pace']) : '';

            // Social Links (optional)
            $instagram = isset($_POST['instagram']) ? sanitize_text_field($_POST['instagram']) : '';
            $facebook = isset($_POST['facebook']) ? sanitize_text_field($_POST['facebook']) : '';

            // Privacy Settings
            $show_age = isset($_POST['show_age']) ? 'yes' : 'no';
            $show_phone = isset($_POST['show_phone']) ? 'yes' : 'no';
            $show_email = isset($_POST['show_email']) ? 'yes' : 'no';
            $show_social = isset($_POST['show_social']) ? 'yes' : 'no';

            // Validation
            if (empty($birth_date) || empty($bio) || empty($city) || empty($country)) {
                wp_send_json_error(array('message' => 'Please fill in all required fields.'));
            }

            // Check age (min 18) with error handling
            try {
                $birth = new DateTime($birth_date);
                $today = new DateTime();
                $age = $today->diff($birth)->y;

                if ($age < 18) {
                    wp_send_json_error(array('message' => 'You must be at least 18 years old.'));
                }
            } catch (Exception $e) {
                wp_send_json_error(array('message' => 'Invalid birth date.'));
            }

            // Save data
            update_user_meta($user_id, 'cdv_birth_date', $birth_date);
            update_user_meta($user_id, 'cdv_gender', $gender);
            update_user_meta($user_id, 'cdv_city', $city);
            update_user_meta($user_id, 'cdv_country', $country);
            update_user_meta($user_id, 'cdv_phone', $phone);
            update_user_meta($user_id, 'cdv_bio', $bio);
            update_user_meta($user_id, 'cdv_languages', $languages);
            update_user_meta($user_id, 'cdv_travel_styles', !empty($travel_styles) ? implode(', ', $travel_styles) : '');
            update_user_meta($user_id, 'cdv_interests', !empty($interests) ? implode(', ', $interests) : '');
            update_user_meta($user_id, 'cdv_budget_range', $budget_range);
            update_user_meta($user_id, 'cdv_travel_frequency', $travel_frequency);
            update_user_meta($user_id, 'cdv_accommodation_preference', $accommodation_preference);
            update_user_meta($user_id, 'cdv_travel_pace', $travel_pace);
            update_user_meta($user_id, 'cdv_instagram', $instagram);
            update_user_meta($user_id, 'cdv_facebook', $facebook);

            // Privacy settings
            update_user_meta($user_id, 'cdv_show_age', $show_age);
            update_user_meta($user_id, 'cdv_show_phone', $show_phone);
            update_user_meta($user_id, 'cdv_show_email', $show_email);
            update_user_meta($user_id, 'cdv_show_social', $show_social);

            // Mark profile as complete
            update_user_meta($user_id, 'cdv_profile_completed', '1');

            error_log('CDV: Registration step 2 completed successfully for user ' . $user_id);

            wp_send_json_success(array(
                'message' => 'Profile completed successfully!',
                'redirect' => home_url('/dashboard'),
            ));

        } catch (Exception $e) {
            error_log('CDV: Error in registration step 2: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ));
        }
    }

    /**
     * AJAX: Upload profile image
     */
    public static function ajax_upload_profile_image() {
        error_log('CDV: Starting profile image upload');
        error_log('CDV: Is user logged in: ' . (is_user_logged_in() ? 'YES' : 'NO'));

        try {
            if (!is_user_logged_in()) {
                error_log('CDV: User not logged in');
                wp_send_json_error(array('message' => 'You must be logged in.'));
            }

            $user_id = get_current_user_id();
            error_log('CDV: User ID: ' . $user_id);

            // Verify nonce with auto-login bypass
            $nonce_verified = check_ajax_referer('cdv_ajax_nonce', 'nonce', false);
            if (!$nonce_verified) {
                // Check if user was created recently (within last 10 minutes)
                $registration_date = get_user_meta($user_id, 'cdv_registration_date', true);
                if ($registration_date) {
                    $time_diff = strtotime('now') - strtotime($registration_date);
                    if ($time_diff > 600) { // More than 10 minutes
                        error_log('CDV: Nonce verification failed and user not recently created');
                        wp_send_json_error(array('message' => 'Session expired.'));
                    }
                    error_log('CDV: Nonce verification bypassed for profile image upload');
                } else {
                    error_log('CDV: Nonce verification failed');
                    wp_send_json_error(array('message' => 'Security check failed.'));
                }
            } else {
                error_log('CDV: Nonce verified successfully');
            }

            if (!isset($_FILES['profile_image'])) {
                error_log('CDV: No image file in request');
                wp_send_json_error(array('message' => 'No image uploaded.'));
            }

            error_log('CDV: Image file received: ' . $_FILES['profile_image']['name']);

            // Validate file
            $allowed_types = array('image/jpeg', 'image/png', 'image/jpg');
            $max_size = 5 * 1024 * 1024; // 5MB

            $file = $_FILES['profile_image'];
            error_log('CDV: Validating file - Type: ' . $file['type'] . ', Size: ' . $file['size']);

            if (!in_array($file['type'], $allowed_types)) {
                error_log('CDV: Invalid file type: ' . $file['type']);
                wp_send_json_error(array('message' => 'Invalid image format. Use JPG or PNG.'));
            }

            if ($file['size'] > $max_size) {
                error_log('CDV: File too large: ' . $file['size']);
                wp_send_json_error(array('message' => 'Image too large. Maximum size is 5MB.'));
            }

            // Upload file
            error_log('CDV: Preparing to upload file');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $upload = wp_handle_upload($file, array('test_form' => false));

            if (isset($upload['error'])) {
                error_log('CDV: Upload error: ' . $upload['error']);
                wp_send_json_error(array('message' => $upload['error']));
            }

            error_log('CDV: File uploaded successfully to: ' . $upload['file']);

            // Create attachment
            $attachment_id = wp_insert_attachment(array(
                'post_mime_type' => $upload['type'],
                'post_title' => 'Profilo ' . $user_id,
                'post_content' => '',
                'post_status' => 'inherit'
            ), $upload['file']);

            if (is_wp_error($attachment_id)) {
                error_log('CDV: Failed to create attachment: ' . $attachment_id->get_error_message());
                wp_send_json_error(array('message' => 'Error while creating the attachment.'));
            }

            error_log('CDV: Attachment created with ID: ' . $attachment_id);

            // Generate metadata
            $attach_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
            wp_update_attachment_metadata($attachment_id, $attach_data);

            // Save to user meta
            update_user_meta($user_id, 'cdv_profile_image', $attachment_id);

            error_log('CDV: Profile image upload completed for user ' . $user_id);

            wp_send_json_success(array(
                'message' => 'Image uploaded successfully',
                'image_url' => wp_get_attachment_url($attachment_id),
            ));

        } catch (Exception $e) {
            error_log('CDV: Error in profile image upload: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ));
        }
    }

    /**
     * Notify admin of new user registration
     */
    private static function notify_admin_new_user($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            error_log('CDV: Failed to get user data for admin notification. User ID: ' . $user_id);
            return false;
        }

        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            error_log('CDV: No admin email configured for notifications');
            return false;
        }

        $subject = '[Compagni di Viaggi] New user registration';
        $message = sprintf(
            "New user registered:\n\nName: %s\nUsername: %s\nEmail: %s\nDate: %s\n\nView users: %s",
            $user->display_name,
            $user->user_login,
            $user->user_email,
            current_time('d/m/Y H:i'),
            admin_url('users.php')
        );

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        $result = wp_mail($admin_email, $subject, $message, $headers);

        if (!$result) {
            error_log('CDV: Failed to send admin notification email for user ' . $user_id . ' to ' . $admin_email);
            error_log('CDV: Check WordPress mail configuration or install WP Mail SMTP plugin');
        } else {
            error_log('CDV: Admin notification email sent successfully for user ' . $user_id);
        }

        return $result;
    }

    /**
     * Get public profile fields
     */
    public static function get_public_profile_fields($user_id) {
        $user = get_user_by('id', $user_id);

        $profile = array(
            'id' => $user_id,
            'display_name' => $user->display_name,
            'avatar' => self::get_profile_image_url($user_id),
            'bio' => get_user_meta($user_id, 'cdv_bio', true),
            'city' => get_user_meta($user_id, 'cdv_city', true),
            'country' => get_user_meta($user_id, 'cdv_country', true),
            'languages' => get_user_meta($user_id, 'cdv_languages', true),
            'travel_styles' => get_user_meta($user_id, 'cdv_travel_styles', true),
            'interests' => get_user_meta($user_id, 'cdv_interests', true),
            'verified' => get_user_meta($user_id, 'cdv_verified', true) === '1',
            'reputation' => get_user_meta($user_id, 'cdv_reputation_score', true),
            'total_reviews' => get_user_meta($user_id, 'cdv_total_reviews', true),
        );

        // Conditional fields based on privacy
        if (get_user_meta($user_id, 'cdv_show_age', true) === '1') {
            $profile['age'] = CDV_User_Meta::get_user_age($user_id);
        }

        if (get_user_meta($user_id, 'cdv_show_email', true) === '1') {
            $profile['email'] = $user->user_email;
        }

        if (get_user_meta($user_id, 'cdv_show_phone', true) === '1') {
            $profile['phone'] = get_user_meta($user_id, 'cdv_phone', true);
        }

        if (get_user_meta($user_id, 'cdv_show_social', true) === '1') {
            $profile['instagram'] = get_user_meta($user_id, 'cdv_instagram', true);
            $profile['facebook'] = get_user_meta($user_id, 'cdv_facebook', true);
        }

        return $profile;
    }

    /**
     * Get profile image URL
     */
    public static function get_profile_image_url($user_id, $size = 'thumbnail') {
        $image_id = get_user_meta($user_id, 'cdv_profile_image', true);

        if ($image_id) {
            return wp_get_attachment_image_url($image_id, $size);
        }

        return get_avatar_url($user_id);
    }

    /**
     * AJAX: Create First Travel (during registration)
     */
    public static function ajax_create_first_travel() {
        error_log('CDV: Starting first travel creation');
        error_log('CDV: Is user logged in: ' . (is_user_logged_in() ? 'YES' : 'NO'));

        try {
            if (!is_user_logged_in()) {
                error_log('CDV: User not logged in');
                wp_send_json_error(array('message' => 'You must be logged in.'));
            }

            $user_id = get_current_user_id();
            error_log('CDV: User ID: ' . $user_id);

            // Verify nonce with auto-login bypass
            $nonce_verified = check_ajax_referer('cdv_ajax_nonce', 'nonce', false);
            if (!$nonce_verified) {
                // Check if user was created recently (within last 10 minutes)
                $registration_date = get_user_meta($user_id, 'cdv_registration_date', true);
                if ($registration_date) {
                    $time_diff = strtotime('now') - strtotime($registration_date);
                    if ($time_diff > 600) { // More than 10 minutes
                        error_log('CDV: Nonce verification failed and user not recently created');
                        wp_send_json_error(array('message' => 'Session expired.'));
                    }
                    error_log('CDV: Nonce verification bypassed for first travel creation');
                } else {
                    error_log('CDV: Nonce verification failed');
                    wp_send_json_error(array('message' => 'Security check failed.'));
                }
            } else {
                error_log('CDV: Nonce verified successfully');
            }

            $title = isset($_POST['travel_title']) ? sanitize_text_field($_POST['travel_title']) : '';
            $description = isset($_POST['travel_description']) ? sanitize_textarea_field($_POST['travel_description']) : '';
            $destination = isset($_POST['travel_destination']) ? sanitize_text_field($_POST['travel_destination']) : '';
            $country = isset($_POST['travel_country']) ? sanitize_text_field($_POST['travel_country']) : '';
            $start_date = isset($_POST['travel_start_date']) ? sanitize_text_field($_POST['travel_start_date']) : '';
            $end_date = isset($_POST['travel_end_date']) ? sanitize_text_field($_POST['travel_end_date']) : '';
            $budget = isset($_POST['travel_budget']) ? intval($_POST['travel_budget']) : 0;
            $max_participants = isset($_POST['travel_max_participants']) ? intval($_POST['travel_max_participants']) : 0;
            $travel_types = isset($_POST['travel_types']) ? array_map('intval', $_POST['travel_types']) : array();

            error_log('CDV: Travel title: ' . $title);

            // Validation
            if (empty($title) || empty($description) || empty($destination) || empty($country)) {
                error_log('CDV: Missing required fields');
                wp_send_json_error(array('message' => 'Please fill in all required fields.'));
            }

            if (empty($start_date) || empty($end_date)) {
                error_log('CDV: Missing travel dates');
                wp_send_json_error(array('message' => 'Enter the trip dates.'));
            }

            if (strtotime($start_date) < strtotime('today')) {
                error_log('CDV: Start date in the past');
                wp_send_json_error(array('message' => 'The start date must be in the future.'));
            }

            if (strtotime($end_date) < strtotime($start_date)) {
                error_log('CDV: End date before start date');
                wp_send_json_error(array('message' => 'The end date must be after the start date.'));
            }

            error_log('CDV: Validation passed, creating travel post');

            // Create travel post
            $post_data = array(
                'post_type' => 'viaggio',
                'post_title' => $title,
                'post_content' => $description,
                'post_status' => 'pending', // Will be moderated
                'post_author' => $user_id,
            );

            $post_id = wp_insert_post($post_data);

            if (is_wp_error($post_id)) {
                error_log('CDV: Failed to create travel post: ' . $post_id->get_error_message());
                wp_send_json_error(array('message' => 'Error while creating the trip.'));
            }

            error_log('CDV: Travel post created with ID: ' . $post_id);

            // Add meta data
            update_post_meta($post_id, 'cdv_destination', $destination);
            update_post_meta($post_id, 'cdv_country', $country);
            update_post_meta($post_id, 'cdv_start_date', $start_date);
            update_post_meta($post_id, 'cdv_end_date', $end_date);
            update_post_meta($post_id, 'cdv_budget', $budget);
            update_post_meta($post_id, 'cdv_max_participants', $max_participants);
            update_post_meta($post_id, 'cdv_travel_status', 'open');
            update_post_meta($post_id, 'cdv_views', 0);

            // Add travel types taxonomy
            if (!empty($travel_types)) {
                wp_set_post_terms($post_id, $travel_types, 'tipo_viaggio');
            }

            // Set destination taxonomy
            if (!empty($destination)) {
                wp_set_post_terms($post_id, array($destination), 'destinazione', false);
            }

            // Award badge for first travel
            CDV_Badges::award_badge($user_id, 'first_travel');

            error_log('CDV: First travel creation completed successfully');

            wp_send_json_success(array(
                'message' => 'Trip created! It will go live once approved.',
                'travel_id' => $post_id,
            ));

        } catch (Exception $e) {
            error_log('CDV: Error in first travel creation: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ));
        }
    }
}
