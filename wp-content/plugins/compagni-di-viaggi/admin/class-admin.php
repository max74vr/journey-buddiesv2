<?php
/**
 * Admin panel
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDV_Admin {

    /**
     * Initialize
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_menu'));
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        add_action('save_post_viaggio', array(__CLASS__, 'save_meta_boxes'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
    }

    /**
     * Add admin menu
     */
    public static function add_menu() {
        add_menu_page(
            'Travel Companions',
            'Travel Companions',
            'manage_options',
            'cdv-dashboard',
            array(__CLASS__, 'dashboard_page'),
            'dashicons-palmtree',
            6
        );

        // Pending users submenu
        $pending_users = CDV_User_Roles::get_pending_users_count();
        $users_badge = $pending_users > 0 ? ' <span class="awaiting-mod">' . $pending_users . '</span>' : '';

        add_submenu_page(
            'cdv-dashboard',
            'Pending Users',
            'Pending Users' . $users_badge,
            'approve_users',
            'cdv-pending-users',
            array(__CLASS__, 'pending_users_page')
        );

        // Pending travels submenu
        $pending_travels = CDV_Travel_Moderation::get_pending_travels_count();
        $travels_badge = $pending_travels > 0 ? ' <span class="awaiting-mod">' . $pending_travels . '</span>' : '';

        add_submenu_page(
            'cdv-dashboard',
            'Pending Trips',
            'Pending Trips' . $travels_badge,
            'approve_viaggi',
            'cdv-pending-travels',
            array(__CLASS__, 'pending_travels_page')
        );

        // Pending stories submenu
        $pending_stories = self::get_pending_stories_count();
        $stories_badge = $pending_stories > 0 ? ' <span class="awaiting-mod">' . $pending_stories . '</span>' : '';

        add_submenu_page(
            'cdv-dashboard',
            'Pending Travel Stories',
            'Pending Travel Stories' . $stories_badge,
            'manage_options',
            'cdv-pending-stories',
            array(__CLASS__, 'pending_stories_page')
        );

        add_submenu_page(
            'cdv-dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'cdv-settings',
            array(__CLASS__, 'settings_page')
        );
    }

    /**
     * Dashboard page
     */
    public static function dashboard_page() {
        global $wpdb;

        $stats = array(
            'total_travels' => wp_count_posts('viaggio')->publish,
            'total_users' => count_users()['total_users'],
            'total_participants' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cdv_travel_participants WHERE status = 'accepted'"),
            'total_reviews' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cdv_reviews"),
        );

        ?>
        <div class="wrap">
            <h1>Compagni di Viaggi Dashboard</h1>

            <div class="cdv-stats">
                <div class="cdv-stat-box">
                    <h3>Published Trips</h3>
                    <p class="cdv-stat-number"><?php echo $stats['total_travels']; ?></p>
                </div>
                <div class="cdv-stat-box">
                    <h3>Registered Users</h3>
                    <p class="cdv-stat-number"><?php echo $stats['total_users']; ?></p>
                </div>
                <div class="cdv-stat-box">
                    <h3>Participants</h3>
                    <p class="cdv-stat-number"><?php echo $stats['total_participants']; ?></p>
                </div>
                <div class="cdv-stat-box">
                    <h3>Reviews</h3>
                    <p class="cdv-stat-number"><?php echo $stats['total_reviews']; ?></p>
                </div>
            </div>

            <style>
                .cdv-stats {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 20px;
                    margin: 30px 0;
                }
                .cdv-stat-box {
                    background: white;
                    padding: 20px;
                    border-left: 4px solid #667eea;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                .cdv-stat-box h3 {
                    margin: 0 0 10px 0;
                    color: #667eea;
                }
                .cdv-stat-number {
                    font-size: 32px;
                    font-weight: bold;
                    margin: 0;
                }
            </style>
        </div>
        <?php
    }

    /**
     * Settings page
     */
    public static function settings_page() {
        // Handle email test
        if (isset($_POST['cdv_test_email'])) {
            check_admin_referer('cdv_settings_nonce');

            $test_email = sanitize_email($_POST['test_email_address']);
            if (!$test_email || !is_email($test_email)) {
                $test_email = get_option('admin_email');
            }

            $subject = '[Compagni di Viaggi] Test Email';
            $message = "This is a test email sent by Compagni di Viaggi.\n\n";
            $message .= "If you receive this message, the email configuration is working correctly.\n\n";
            $message .= "Sent on: " . current_time('m/d/Y H:i:s') . "\n";
            $message .= "Recipient: " . $test_email . "\n";

            $headers = array('Content-Type: text/plain; charset=UTF-8');

            $result = wp_mail($test_email, $subject, $message, $headers);

            if ($result) {
                echo '<div class="notice notice-success"><p><strong>Test email sent!</strong> Check the inbox for <code>' . esc_html($test_email) . '</code> (including spam folders).</p></div>';
            } else {
                echo '<div class="notice notice-error"><p><strong>Test email failed.</strong> Review <code>wp-content/debug.log</code>; configuring SMTP may be required.</p></div>';
            }
        }

        if (isset($_POST['cdv_save_settings'])) {
            check_admin_referer('cdv_settings_nonce');

            update_option('cdv_max_participants', intval($_POST['cdv_max_participants']));
            update_option('cdv_min_age', intval($_POST['cdv_min_age']));
            update_option('cdv_chat_enabled', isset($_POST['cdv_chat_enabled']));
            update_option('cdv_reviews_enabled', isset($_POST['cdv_reviews_enabled']));
            update_option('cdv_auto_approve_participants', isset($_POST['cdv_auto_approve_participants']));

            echo '<div class="notice notice-success"><p>Settings saved successfully.</p></div>';
        }

        $max_participants = get_option('cdv_max_participants', 10);
        $min_age = get_option('cdv_min_age', 18);
        $chat_enabled = get_option('cdv_chat_enabled', true);
        $reviews_enabled = get_option('cdv_reviews_enabled', true);
        $auto_approve = get_option('cdv_auto_approve_participants', false);

        ?>
        <div class="wrap">
            <h1>Compagni di Viaggi Settings</h1>

            <form method="post" action="">
                <?php wp_nonce_field('cdv_settings_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cdv_max_participants">Default Maximum Participants</label>
                        </th>
                        <td>
                            <input type="number" name="cdv_max_participants" id="cdv_max_participants" value="<?php echo esc_attr($max_participants); ?>" class="regular-text" />
                            <p class="description">Maximum allowed participants per trip (can be overridden per trip).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cdv_min_age">Minimum Age</label>
                        </th>
                        <td>
                            <input type="number" name="cdv_min_age" id="cdv_min_age" value="<?php echo esc_attr($min_age); ?>" class="regular-text" />
                            <p class="description">Minimum age required to sign up.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Chat</th>
                        <td>
                            <label>
                                <input type="checkbox" name="cdv_chat_enabled" value="1" <?php checked($chat_enabled, true); ?> />
                                Enable chat system
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Reviews</th>
                        <td>
                            <label>
                                <input type="checkbox" name="cdv_reviews_enabled" value="1" <?php checked($reviews_enabled, true); ?> />
                                Enable review system
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Auto-approve Participants</th>
                        <td>
                            <label>
                                <input type="checkbox" name="cdv_auto_approve_participants" value="1" <?php checked($auto_approve, true); ?> />
                                Automatically approve participation requests
                            </label>
                            <p class="description">When enabled, requests are accepted without organizer approval.</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="cdv_save_settings" class="button button-primary" value="Save Settings" />
                </p>
            </form>

            <hr style="margin: 40px 0;">

            <h2>Email Configuration</h2>

            <div class="card" style="max-width: none; padding: 20px; background: #f9f9f9; border-left: 4px solid #667eea;">
                <h3 style="margin-top: 0;">Send Test Email</h3>
                <p>Make sure WordPress can send emails. If the test fails, install and configure an SMTP plugin.</p>

                <form method="post" action="">
                    <?php wp_nonce_field('cdv_settings_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="test_email_address">Send test email to:</label>
                            </th>
                            <td>
                                <input type="email" name="test_email_address" id="test_email_address" value="<?php echo esc_attr(get_option('admin_email')); ?>" class="regular-text" />
                                <p class="description">Leave empty to use the admin email address.</p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="cdv_test_email" class="button button-secondary" value="Send Test Email" />
                    </p>
                </form>
            </div>

            <div class="card" style="max-width: none; padding: 20px; margin-top: 20px;">
                <h3 style="margin-top: 0;">Recommended SMTP Setup</h3>
                <p>For reliable email delivery (account verification, admin notices) we recommend configuring SMTP:</p>

                <ol>
                    <li><strong>Install the "WP Mail SMTP" plugin</strong>
                        <ul>
                            <li>Go to Plugins → Add New</li>
                            <li>Search for "WP Mail SMTP by WPForms"</li>
                            <li>Install and activate it</li>
                        </ul>
                    </li>
                    <li><strong>Configure your SMTP provider</strong>
                        <ul>
                            <li>Gmail, SendGrid, Mailgun, Amazon SES, etc.</li>
                            <li>Follow the plugin setup wizard</li>
                        </ul>
                    </li>
                    <li><strong>Run another test</strong>
                        <ul>
                            <li>Use the "Send Test Email" button above</li>
                            <li>Confirm the message lands in your inbox</li>
                        </ul>
                    </li>
                </ol>

                <h4>Emails sent by the system:</h4>
                <ul>
                    <li>✉️ <strong>Email verification</strong> - Sent to users during registration</li>
                    <li>✉️ <strong>Admin notification</strong> - Sent to the administrator when a new user registers</li>
                    <li>✉️ <strong>Trip notifications</strong> - For participation requests, approvals, etc.</li>
                </ul>

                <p><strong>Note:</strong> Verification emails are optional. Users can still log in even without verifying their email; delivery failures will not block new registrations.</p>
            </div>
        </div>
        <?php
    }

    /**
     * Add meta boxes
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'cdv_travel_details',
            'Trip Details',
            array(__CLASS__, 'travel_details_meta_box'),
            'viaggio',
            'normal',
            'high'
        );

        add_meta_box(
            'cdv_travel_participants',
            'Participants',
            array(__CLASS__, 'travel_participants_meta_box'),
            'viaggio',
            'side',
            'default'
        );
    }

    /**
     * Travel details meta box
     */
    public static function travel_details_meta_box($post) {
        wp_nonce_field('cdv_meta_box_nonce', 'cdv_meta_box_nonce');

        $start_date = get_post_meta($post->ID, 'cdv_start_date', true);
        $end_date = get_post_meta($post->ID, 'cdv_end_date', true);
        $destination = get_post_meta($post->ID, 'cdv_destination', true);
        $country = get_post_meta($post->ID, 'cdv_country', true);
        $budget = get_post_meta($post->ID, 'cdv_budget', true);
        $max_participants = get_post_meta($post->ID, 'cdv_max_participants', true);
        $status = get_post_meta($post->ID, 'cdv_travel_status', true);

        ?>
        <table class="form-table">
            <tr>
                <th><label for="cdv_start_date">Start Date</label></th>
                <td><input type="date" name="cdv_start_date" id="cdv_start_date" value="<?php echo esc_attr($start_date); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="cdv_end_date">End Date</label></th>
                <td><input type="date" name="cdv_end_date" id="cdv_end_date" value="<?php echo esc_attr($end_date); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="cdv_destination">Destination</label></th>
                <td><input type="text" name="cdv_destination" id="cdv_destination" value="<?php echo esc_attr($destination); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="cdv_country">Country</label></th>
                <td><input type="text" name="cdv_country" id="cdv_country" value="<?php echo esc_attr($country); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="cdv_budget">Estimated Budget (€)</label></th>
                <td><input type="number" name="cdv_budget" id="cdv_budget" value="<?php echo esc_attr($budget); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="cdv_max_participants">Max Participants</label></th>
                <td><input type="number" name="cdv_max_participants" id="cdv_max_participants" value="<?php echo esc_attr($max_participants); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="cdv_travel_status">Trip Status</label></th>
                <td>
                    <select name="cdv_travel_status" id="cdv_travel_status">
                        <option value="open" <?php selected($status, 'open'); ?>>Open</option>
                        <option value="full" <?php selected($status, 'full'); ?>>Full</option>
                        <option value="in_progress" <?php selected($status, 'in_progress'); ?>>In Progress</option>
                        <option value="completed" <?php selected($status, 'completed'); ?>>Completed</option>
                        <option value="cancelled" <?php selected($status, 'cancelled'); ?>>Cancelled</option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Travel participants meta box
     */
    public static function travel_participants_meta_box($post) {
        $participants = CDV_Participants::get_participants($post->ID);

        if (empty($participants)) {
            echo '<p>No participants yet.</p>';
            return;
        }

        echo '<ul>';
        foreach ($participants as $participant) {
            $user = get_user_by('id', $participant->user_id);
            $status_label = array(
                'pending' => 'Pending',
                'accepted' => 'Accepted',
                'rejected' => 'Rejected',
            );
            echo '<li>';
            echo esc_html($user->display_name);
            echo ' - <strong>' . $status_label[$participant->status] . '</strong>';
            echo '</li>';
        }
        echo '</ul>';
    }

    /**
     * Save meta boxes
     */
    public static function save_meta_boxes($post_id) {
        if (!isset($_POST['cdv_meta_box_nonce']) || !wp_verify_nonce($_POST['cdv_meta_box_nonce'], 'cdv_meta_box_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = array(
            'cdv_start_date',
            'cdv_end_date',
            'cdv_destination',
            'cdv_country',
            'cdv_budget',
            'cdv_max_participants',
            'cdv_travel_status',
        );

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
    }

    /**
     * Pending users page
     */
    public static function pending_users_page() {
        $pending_users = CDV_User_Roles::get_pending_users();

        ?>
        <div class="wrap">
            <h1>Users Awaiting Approval</h1>

            <?php if (isset($_GET['approved'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>User approved successfully!</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['rejected'])) : ?>
                <div class="notice notice-info is-dismissible">
                    <p>User rejected.</p>
                </div>
            <?php endif; ?>

            <?php if (empty($pending_users)) : ?>
                <p>No users awaiting approval.</p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Registered On</th>
                            <th>Profile</th>
                            <th>Completion</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_users as $user) :
                            $profile_completion = CDV_User_Roles::get_profile_completion($user->ID);
                            $registration_date = get_user_meta($user->ID, 'cdv_registration_date', true);
                            $bio = get_user_meta($user->ID, 'cdv_bio', true);
                            $city = get_user_meta($user->ID, 'cdv_city', true);
                            ?>
                            <tr>
                                <td>
                                    <?php echo get_avatar($user->ID, 50); ?>
                                    <strong><?php echo esc_html($user->display_name); ?></strong><br>
                                    <small>@<?php echo esc_html($user->user_login); ?></small>
                                </td>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td><?php echo $registration_date ? date_i18n('d/m/Y H:i', strtotime($registration_date)) : '-'; ?></td>
                                <td>
                                    <?php if ($city) : ?>
                                        📍 <?php echo esc_html($city); ?><br>
                                    <?php endif; ?>
                                    <?php if ($bio) : ?>
                                        <small><?php echo esc_html(wp_trim_words($bio, 15)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="progress-bar" style="width: 100%; background: #f0f0f0; height: 20px; border-radius: 10px; overflow: hidden;">
                                        <div style="width: <?php echo $profile_completion; ?>%; background: #667eea; height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-size: 11px; font-weight: bold;">
                                            <?php echo $profile_completion; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $user->ID); ?>" class="button" target="_blank">View Profile</a>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('cdv_approve_user_' . $user->ID); ?>
                                        <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                        <button type="submit" name="approve_user" class="button button-primary">✓ Approve</button>
                                        <button type="submit" name="reject_user" class="button button-link-delete" onclick="return confirm('Are you sure you want to reject this user?');">✗ Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php
        // Handle form submissions
        if (isset($_POST['approve_user']) && isset($_POST['user_id'])) {
            $user_id = intval($_POST['user_id']);
            check_admin_referer('cdv_approve_user_' . $user_id);

            CDV_User_Roles::approve_user($user_id);
            wp_redirect(add_query_arg('approved', '1', admin_url('admin.php?page=cdv-pending-users')));
            exit;
        }

        if (isset($_POST['reject_user']) && isset($_POST['user_id'])) {
            $user_id = intval($_POST['user_id']);
            check_admin_referer('cdv_approve_user_' . $user_id);

            CDV_User_Roles::reject_user($user_id, 'Profile not compliant with the guidelines');
            wp_redirect(add_query_arg('rejected', '1', admin_url('admin.php?page=cdv-pending-users')));
            exit;
        }
    }

    /**
     * Pending travels page
     */
    public static function pending_travels_page() {
        // Handle form submissions FIRST, before any output
        if (isset($_POST['approve_travel']) && isset($_POST['travel_id'])) {
            $travel_id = intval($_POST['travel_id']);
            check_admin_referer('cdv_approve_travel_' . $travel_id);

            CDV_Travel_Moderation::approve_travel($travel_id);
            wp_redirect(add_query_arg('approved', '1', admin_url('admin.php?page=cdv-pending-travels')));
            exit;
        }

        if (isset($_POST['reject_travel']) && isset($_POST['travel_id'])) {
            $travel_id = intval($_POST['travel_id']);
            check_admin_referer('cdv_approve_travel_' . $travel_id);

            CDV_Travel_Moderation::reject_travel($travel_id, 'Contenuto non conforme alle linee guida');
            wp_redirect(add_query_arg('rejected', '1', admin_url('admin.php?page=cdv-pending-travels')));
            exit;
        }

        $args = array(
            'post_type' => 'viaggio',
            'post_status' => 'pending',
            'posts_per_page' => -1,
        );

        $pending_travels = get_posts($args);

        ?>
        <div class="wrap">
            <h1>Trips Awaiting Approval</h1>

            <?php if (isset($_GET['approved'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>Trip approved successfully!</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['rejected'])) : ?>
                <div class="notice notice-info is-dismissible">
                    <p>Trip rejected.</p>
                </div>
            <?php endif; ?>

            <?php if (empty($pending_travels)) : ?>
                <p>No trips awaiting approval.</p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Trip</th>
                            <th>Organizer</th>
                            <th>Destination</th>
                            <th>Date</th>
                            <th>Budget</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_travels as $travel) :
                            $author = get_user_by('id', $travel->post_author);
                            $destination = get_post_meta($travel->ID, 'cdv_destination', true);
                            $country = get_post_meta($travel->ID, 'cdv_country', true);
                            $start_date = get_post_meta($travel->ID, 'cdv_start_date', true);
                            $end_date = get_post_meta($travel->ID, 'cdv_end_date', true);
                            $budget = get_post_meta($travel->ID, 'cdv_budget', true);
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($travel->post_title); ?></strong><br>
                                    <small><?php echo esc_html(wp_trim_words($travel->post_content, 20)); ?></small>
                                </td>
                                <td>
                                    <?php echo get_avatar($author->ID, 40); ?>
                                    <?php echo esc_html($author->display_name); ?><br>
                                    <?php if (!CDV_User_Roles::is_user_approved($author->ID)) : ?>
                                        <span style="color: orange;">⚠️ User not yet approved</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($destination . ', ' . $country); ?></td>
                                <td>
                                    <?php if ($start_date) : ?>
                                        <?php echo date_i18n('d/m/Y', strtotime($start_date)); ?><br>
                                        <?php echo date_i18n('d/m/Y', strtotime($end_date)); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $budget ? '€' . number_format($budget, 0, ',', '.') : '-'; ?></td>
                                <td>
                                    <a href="<?php echo get_edit_post_link($travel->ID); ?>" class="button" target="_blank">View/Edit</a>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('cdv_approve_travel_' . $travel->ID); ?>
                                        <input type="hidden" name="travel_id" value="<?php echo $travel->ID; ?>">
                                        <button type="submit" name="approve_travel" class="button button-primary">✓ Approve</button>
                                        <button type="submit" name="reject_travel" class="button button-link-delete" onclick="return confirm('Are you sure you want to reject this trip?');">✗ Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Pending stories page
     */
    public static function pending_stories_page() {
        // Handle form submissions FIRST, before any output
        if (isset($_POST['approve_story']) && isset($_POST['story_id'])) {
            $story_id = intval($_POST['story_id']);
            check_admin_referer('cdv_approve_story_' . $story_id);

            wp_update_post(array(
                'ID' => $story_id,
                'post_status' => 'publish',
            ));

            wp_redirect(add_query_arg('approved', '1', admin_url('admin.php?page=cdv-pending-stories')));
            exit;
        }

        if (isset($_POST['reject_story']) && isset($_POST['story_id'])) {
            $story_id = intval($_POST['story_id']);
            check_admin_referer('cdv_approve_story_' . $story_id);

            wp_delete_post($story_id, true);

            wp_redirect(add_query_arg('rejected', '1', admin_url('admin.php?page=cdv-pending-stories')));
            exit;
        }

        $args = array(
            'post_type' => 'racconto',
            'post_status' => 'pending',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $pending_stories = get_posts($args);

        ?>
        <div class="wrap">
            <h1>Stories Awaiting Approval</h1>

            <?php if (isset($_GET['approved'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>Story approved successfully!</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['rejected'])) : ?>
                <div class="notice notice-info is-dismissible">
                    <p>Story rejected.</p>
                </div>
            <?php endif; ?>

            <?php if (empty($pending_stories)) : ?>
                <p>No stories awaiting approval.</p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Story</th>
                            <th>Author</th>
                            <th>Destination</th>
                            <th>Trip Date</th>
                            <th>Submitted On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_stories as $story) :
                            $author = get_user_by('id', $story->post_author);
                            $destination = get_post_meta($story->ID, 'cdv_destination', true);
                            $travel_date = get_post_meta($story->ID, 'cdv_travel_date', true);
                            $categories = get_the_terms($story->ID, 'categoria_racconto');
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($story->post_title); ?></strong><br>
                                    <small><?php echo esc_html(wp_trim_words($story->post_content, 30)); ?></small>
                                    <?php if (!empty($categories)) : ?>
                                        <br><span class="dashicons dashicons-category"></span>
                                        <em><?php echo esc_html($categories[0]->name); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo get_avatar($author->ID, 40); ?>
                                    <strong><?php echo esc_html($author->display_name); ?></strong><br>
                                    <small><?php echo esc_html($author->user_email); ?></small>
                                </td>
                                <td>
                                    <?php echo $destination ? esc_html($destination) : '<em>Not specified</em>'; ?>
                                </td>
                                <td>
                                    <?php
                                    if ($travel_date) {
                                        $date = DateTime::createFromFormat('Y-m', $travel_date);
                                        echo $date ? $date->format('F Y') : esc_html($travel_date);
                                    } else {
                                        echo '<em>Not specified</em>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php echo date_i18n('d/m/Y H:i', strtotime($story->post_date)); ?>
                                </td>
                                <td>
                                    <a href="<?php echo get_edit_post_link($story->ID); ?>" class="button button-small">View</a><br><br>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('cdv_approve_story_' . $story->ID); ?>
                                        <input type="hidden" name="story_id" value="<?php echo $story->ID; ?>">
                                        <button type="submit" name="approve_story" class="button button-primary">✓ Approve</button>
                                        <button type="submit" name="reject_story" class="button button-link-delete" onclick="return confirm('Are you sure you want to reject this story? It will be permanently deleted.');">✗ Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get pending stories count
     */
    public static function get_pending_stories_count() {
        $count = wp_count_posts('racconto');
        return isset($count->pending) ? intval($count->pending) : 0;
    }

    /**
     * Enqueue admin scripts
     */
    public static function enqueue_scripts($hook) {
        // Add admin-specific CSS/JS if needed
    }
}
