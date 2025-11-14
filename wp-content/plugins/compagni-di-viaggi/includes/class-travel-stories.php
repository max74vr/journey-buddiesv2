<?php
/**
 * Travel Stories
 *
 * Manages travel stories submitted by users
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDV_Travel_Stories {

    /**
     * Initialize
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_type'));
        add_action('init', array(__CLASS__, 'register_taxonomies'));
        add_action('wp_ajax_cdv_submit_story', array(__CLASS__, 'ajax_submit_story'));
        add_action('wp_ajax_cdv_delete_story', array(__CLASS__, 'ajax_delete_story'));
        add_action('wp_ajax_cdv_upload_story_image', array(__CLASS__, 'ajax_upload_story_image'));
    }

    /**
     * Register Custom Post Type
     */
    public static function register_post_type() {
        $labels = array(
            'name'                  => __('Travel Stories', 'compagni-di-viaggi'),
            'singular_name'         => __('Story', 'compagni-di-viaggi'),
            'menu_name'             => __('Stories', 'compagni-di-viaggi'),
            'add_new'               => __('Add Story', 'compagni-di-viaggi'),
            'add_new_item'          => __('Add New Story', 'compagni-di-viaggi'),
            'edit_item'             => __('Edit Story', 'compagni-di-viaggi'),
            'new_item'              => __('New Story', 'compagni-di-viaggi'),
            'view_item'             => __('View Story', 'compagni-di-viaggi'),
            'search_items'          => __('Search Stories', 'compagni-di-viaggi'),
            'not_found'             => __('No stories found', 'compagni-di-viaggi'),
            'not_found_in_trash'    => __('No stories found in Trash', 'compagni-di-viaggi'),
            'all_items'             => __('All Stories', 'compagni-di-viaggi'),
        );

        $args = array(
            'labels'                => $labels,
            'public'                => true,
            'has_archive'           => true,
            'publicly_queryable'    => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'show_in_nav_menus'     => true,
            'show_in_rest'          => true,
            'menu_position'         => 21,
            'menu_icon'             => 'dashicons-book-alt',
            'supports'              => array('title', 'editor', 'thumbnail', 'author', 'comments', 'excerpt'),
            'rewrite'               => array('slug' => 'travel-stories'),
            'capability_type'       => 'post',
            'map_meta_cap'          => true,
        );

        register_post_type('racconto', $args);
    }

    /**
     * Register Taxonomies
     */
    public static function register_taxonomies() {
        register_taxonomy('categoria_racconto', 'racconto', array(
            'labels' => array(
                'name'          => __('Story Categories', 'compagni-di-viaggi'),
                'singular_name' => __('Story Category', 'compagni-di-viaggi'),
                'search_items'  => __('Search Categories', 'compagni-di-viaggi'),
                'all_items'     => __('All Categories', 'compagni-di-viaggi'),
                'edit_item'     => __('Edit Category', 'compagni-di-viaggi'),
                'add_new_item'  => __('Add Category', 'compagni-di-viaggi'),
            ),
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => array('slug' => 'story-category'),
        ));

        register_taxonomy('tag_racconto', 'racconto', array(
            'labels' => array(
                'name'          => __('Story Tags', 'compagni-di-viaggi'),
                'singular_name' => __('Story Tag', 'compagni-di-viaggi'),
                'search_items'  => __('Search Tags', 'compagni-di-viaggi'),
                'all_items'     => __('All Tags', 'compagni-di-viaggi'),
                'edit_item'     => __('Edit Tag', 'compagni-di-viaggi'),
                'add_new_item'  => __('Add Tag', 'compagni-di-viaggi'),
            ),
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => array('slug' => 'story-tag'),
        ));

        // Use the same destination taxonomy used for trips
        register_taxonomy_for_object_type('destinazione', 'racconto');
    }

    /**
     * AJAX: Submit Story
     */
    public static function ajax_submit_story() {
        try {
            // Security check
            if (!is_user_logged_in()) {
                wp_send_json_error(array('message' => 'You must be logged in.'));
            }

            check_ajax_referer('cdv_ajax_nonce', 'nonce');

            $user_id = get_current_user_id();
            $user = wp_get_current_user();

            // Check if the user has the traveler role
            if (!in_array('viaggiatore', $user->roles) && !in_array('administrator', $user->roles)) {
                wp_send_json_error(array('message' => 'Only travelers can publish stories.'));
            }

            // Get data
            $story_id = isset($_POST['story_id']) ? intval($_POST['story_id']) : 0;
            $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
            $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
            $destination = isset($_POST['destination']) ? sanitize_text_field($_POST['destination']) : '';
            $category = isset($_POST['category']) ? intval($_POST['category']) : 0;
            $tags = isset($_POST['tags']) ? sanitize_text_field($_POST['tags']) : '';
            $travel_date = isset($_POST['travel_date']) ? sanitize_text_field($_POST['travel_date']) : '';
            $duration = isset($_POST['duration']) ? sanitize_text_field($_POST['duration']) : '';

            // Validation
            if (empty($title) || empty($content)) {
                wp_send_json_error(array('message' => 'Title and content are required.'));
            }

            // If editing, check ownership
            if ($story_id > 0) {
                $existing_post = get_post($story_id);
                if (!$existing_post || $existing_post->post_type !== 'racconto') {
                    wp_send_json_error(array('message' => 'Story not found.'));
                }
                if ($existing_post->post_author != $user_id && !current_user_can('edit_others_posts')) {
                    wp_send_json_error(array('message' => 'You do not have permission to edit this story.'));
                }
            }

            // Create or update post
            $post_data = array(
                'post_type'    => 'racconto',
                'post_title'   => $title,
                'post_content' => $content,
                'post_status'  => $story_id > 0 ? get_post_status($story_id) : 'pending', // New stories are pending
                'post_author'  => $user_id,
            );

            if ($story_id > 0) {
                $post_data['ID'] = $story_id;
                $result = wp_update_post($post_data);
            } else {
                $result = wp_insert_post($post_data);
            }

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => 'Error while saving: ' . $result->get_error_message()));
            }

            $post_id = $story_id > 0 ? $story_id : $result;

            // Save meta data
            if ($destination) {
                update_post_meta($post_id, 'cdv_destination', $destination);
                // Also set as taxonomy
                wp_set_post_terms($post_id, array($destination), 'destinazione');
            }

            if ($travel_date) {
                update_post_meta($post_id, 'cdv_travel_date', $travel_date);
            }

            if ($duration) {
                update_post_meta($post_id, 'cdv_duration', $duration);
            }

            // Set category
            if ($category > 0) {
                wp_set_post_terms($post_id, array($category), 'categoria_racconto');
            }

            // Set tags
            if (!empty($tags)) {
                $tags_array = array_map('trim', explode(',', $tags));
                wp_set_post_terms($post_id, $tags_array, 'tag_racconto');
            }

            // Award badge for first story
            if ($story_id === 0) {
                $user_stories = get_posts(array(
                    'post_type' => 'racconto',
                    'author' => $user_id,
                    'posts_per_page' => 1,
                ));

                if (count($user_stories) === 1) {
                    CDV_Badges::award_badge($user_id, 'first_story');
                }
            }

            // Get the final post status
            $final_status = get_post_status($post_id);
            $is_pending = $final_status === 'pending';

            $message = $story_id > 0
                ? __('Story updated successfully!', 'compagni-di-viaggi')
                : ($is_pending
                    ? __('Story submitted! It will go live once approved by an admin.', 'compagni-di-viaggi')
                    : __('Story published successfully!', 'compagni-di-viaggi'));

            wp_send_json_success(array(
                'message' => $message,
                'story_id' => $post_id,
                'story_url' => get_permalink($post_id),
                'is_pending' => $is_pending,
            ));

        } catch (Exception $e) {
            error_log('CDV: Error in story submission: ' . $e->getMessage());
            wp_send_json_error(array('message' => sprintf(__('An unexpected error occurred: %s', 'compagni-di-viaggi'), $e->getMessage())));
        }
    }

    /**
     * AJAX: Delete Story
     */
    public static function ajax_delete_story() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(array('message' => __('You must be logged in.', 'compagni-di-viaggi')));
            }

            check_ajax_referer('cdv_ajax_nonce', 'nonce');

            $user_id = get_current_user_id();
            $story_id = isset($_POST['story_id']) ? intval($_POST['story_id']) : 0;

            if (!$story_id) {
                wp_send_json_error(array('message' => __('Invalid story ID.', 'compagni-di-viaggi')));
            }

            $post = get_post($story_id);
            if (!$post || $post->post_type !== 'racconto') {
                wp_send_json_error(array('message' => __('Story not found.', 'compagni-di-viaggi')));
            }

            // Check ownership
            if ($post->post_author != $user_id && !current_user_can('delete_others_posts')) {
                wp_send_json_error(array('message' => __('You do not have permission to delete this story.', 'compagni-di-viaggi')));
            }

            $result = wp_delete_post($story_id, true);

            if (!$result) {
                wp_send_json_error(array('message' => __('Deletion failed.', 'compagni-di-viaggi')));
            }

            wp_send_json_success(array('message' => __('Story deleted successfully.', 'compagni-di-viaggi')));

        } catch (Exception $e) {
            error_log('CDV: Error in story deletion: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('An unexpected error occurred.', 'compagni-di-viaggi')));
        }
    }

    /**
     * AJAX: Upload Story Image
     */
    public static function ajax_upload_story_image() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(array('message' => __('You must be logged in.', 'compagni-di-viaggi')));
            }

            check_ajax_referer('cdv_ajax_nonce', 'nonce');

            $story_id = isset($_POST['story_id']) ? intval($_POST['story_id']) : 0;

            if (!$story_id) {
                wp_send_json_error(array('message' => __('Invalid story ID.', 'compagni-di-viaggi')));
            }

            // Check ownership
            $post = get_post($story_id);
            if ($post->post_author != get_current_user_id() && !current_user_can('edit_others_posts')) {
                wp_send_json_error(array('message' => __('You do not have permission to edit this story.', 'compagni-di-viaggi')));
            }

            if (!isset($_FILES['story_image'])) {
                wp_send_json_error(array('message' => __('No image uploaded.', 'compagni-di-viaggi')));
            }

            // Validate file
            $allowed_types = array('image/jpeg', 'image/png', 'image/jpg');
            $max_size = 10 * 1024 * 1024; // 10MB

            $file = $_FILES['story_image'];

            if (!in_array($file['type'], $allowed_types)) {
                wp_send_json_error(array('message' => __('Invalid image format. Use JPG or PNG.', 'compagni-di-viaggi')));
            }

            if ($file['size'] > $max_size) {
                wp_send_json_error(array('message' => __('Image too large. Max 10MB.', 'compagni-di-viaggi')));
            }

            // Upload file
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $upload = wp_handle_upload($file, array('test_form' => false));

            if (isset($upload['error'])) {
                wp_send_json_error(array('message' => $upload['error']));
            }

            // Create attachment
            $attachment_id = wp_insert_attachment(array(
                'post_mime_type' => $upload['type'],
                'post_title'     => 'Story Image ' . $story_id,
                'post_content'   => '',
                'post_status'    => 'inherit',
                'post_parent'    => $story_id,
            ), $upload['file'], $story_id);

            if (is_wp_error($attachment_id)) {
                wp_send_json_error(array('message' => 'Error while creating the attachment.'));
            }

            // Generate metadata
            $attach_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
            wp_update_attachment_metadata($attachment_id, $attach_data);

            // Set as featured image
            set_post_thumbnail($story_id, $attachment_id);

            wp_send_json_success(array(
                'message' => __('Image uploaded successfully.', 'compagni-di-viaggi'),
                'image_url' => wp_get_attachment_url($attachment_id),
                'attachment_id' => $attachment_id,
            ));

        } catch (Exception $e) {
            error_log('CDV: Error in story image upload: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('An unexpected error occurred.', 'compagni-di-viaggi')));
        }
    }

    /**
     * Get user stories
     */
    public static function get_user_stories($user_id, $status = 'publish') {
        $args = array(
            'post_type'      => 'racconto',
            'author'         => $user_id,
            'post_status'    => $status,
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        return new WP_Query($args);
    }

    /**
     * Get story stats
     */
    public static function get_story_stats($story_id) {
        $views = get_post_meta($story_id, 'cdv_views', true);
        $likes = get_post_meta($story_id, 'cdv_likes', true);

        return array(
            'views' => $views ? intval($views) : 0,
            'likes' => $likes ? intval($likes) : 0,
            'comments' => get_comments_number($story_id),
        );
    }

    /**
     * Increment story views
     */
    public static function increment_views($story_id) {
        $views = get_post_meta($story_id, 'cdv_views', true);
        $views = $views ? intval($views) + 1 : 1;
        update_post_meta($story_id, 'cdv_views', $views);
    }
}
