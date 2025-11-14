<?php
/**
 * Register Custom Post Types
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDV_Post_Types {

    /**
     * Initialize
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_types'));
    }

    /**
     * Register custom post types
     */
    public static function register_post_types() {
        self::register_viaggio();
    }

    /**
     * Register 'Viaggio' post type
     */
    private static function register_viaggio() {
        $labels = array(
            'name'                  => _x('Trips', 'Post Type General Name', 'compagni-di-viaggi'),
            'singular_name'         => _x('Trip', 'Post Type Singular Name', 'compagni-di-viaggi'),
            'menu_name'            => __('Trips', 'compagni-di-viaggi'),
            'name_admin_bar'       => __('Trip', 'compagni-di-viaggi'),
            'archives'             => __('Trip Archive', 'compagni-di-viaggi'),
            'attributes'           => __('Trip Attributes', 'compagni-di-viaggi'),
            'parent_item_colon'    => __('Parent Trip:', 'compagni-di-viaggi'),
            'all_items'            => __('All Trips', 'compagni-di-viaggi'),
            'add_new_item'         => __('Add New Trip', 'compagni-di-viaggi'),
            'add_new'              => __('Add New', 'compagni-di-viaggi'),
            'new_item'             => __('New Trip', 'compagni-di-viaggi'),
            'edit_item'            => __('Edit Trip', 'compagni-di-viaggi'),
            'update_item'          => __('Update Trip', 'compagni-di-viaggi'),
            'view_item'            => __('View Trip', 'compagni-di-viaggi'),
            'view_items'           => __('View Trips', 'compagni-di-viaggi'),
            'search_items'         => __('Search Trip', 'compagni-di-viaggi'),
            'not_found'            => __('No trips found', 'compagni-di-viaggi'),
            'not_found_in_trash'   => __('No trips found in Trash', 'compagni-di-viaggi'),
            'featured_image'       => __('Featured image', 'compagni-di-viaggi'),
            'set_featured_image'   => __('Set featured image', 'compagni-di-viaggi'),
            'remove_featured_image'=> __('Remove featured image', 'compagni-di-viaggi'),
            'use_featured_image'   => __('Use as featured image', 'compagni-di-viaggi'),
            'insert_into_item'     => __('Insert into trip', 'compagni-di-viaggi'),
            'uploaded_to_this_item'=> __('Uploaded to this trip', 'compagni-di-viaggi'),
            'items_list'           => __('Trip list', 'compagni-di-viaggi'),
            'items_list_navigation'=> __('Trip list navigation', 'compagni-di-viaggi'),
            'filter_items_list'    => __('Filter trips', 'compagni-di-viaggi'),
        );

        $args = array(
            'label'               => __('Trip', 'compagni-di-viaggi'),
            'description'         => __('Community trips', 'compagni-di-viaggi'),
            'labels'              => $labels,
            'supports'            => array('title', 'editor', 'thumbnail', 'author', 'comments', 'revisions'),
            'taxonomies'          => array('tipo_viaggio', 'destinazione'),
            'hierarchical'        => false,
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_position'       => 5,
            'menu_icon'           => 'dashicons-palmtree',
            'show_in_admin_bar'   => true,
            'show_in_nav_menus'   => true,
            'can_export'          => true,
            'has_archive'         => true,
            'exclude_from_search' => false,
            'publicly_queryable'  => true,
            'capability_type'     => 'post',
            'show_in_rest'        => true,
            'rest_base'           => 'trips',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'rewrite'             => array('slug' => 'trips'),
        );

        register_post_type('viaggio', $args);
    }
}
