<?php
/**
 * Register Custom Taxonomies
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDV_Taxonomies {

    /**
     * Initialize
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_taxonomies'));
    }

    /**
     * Register custom taxonomies
     */
    public static function register_taxonomies() {
        self::register_tipo_viaggio();
        self::register_destinazione();
    }

    /**
     * Register 'Tipo Viaggio' taxonomy
     */
    private static function register_tipo_viaggio() {
        $labels = array(
            'name'              => _x('Travel Types', 'taxonomy general name', 'compagni-di-viaggi'),
            'singular_name'     => _x('Travel Type', 'taxonomy singular name', 'compagni-di-viaggi'),
            'search_items'      => __('Search Travel Types', 'compagni-di-viaggi'),
            'all_items'         => __('All Travel Types', 'compagni-di-viaggi'),
            'parent_item'       => __('Parent Travel Type', 'compagni-di-viaggi'),
            'parent_item_colon' => __('Parent Travel Type:', 'compagni-di-viaggi'),
            'edit_item'         => __('Edit Travel Type', 'compagni-di-viaggi'),
            'update_item'       => __('Update Travel Type', 'compagni-di-viaggi'),
            'add_new_item'      => __('Add New Travel Type', 'compagni-di-viaggi'),
            'new_item_name'     => __('New Travel Type', 'compagni-di-viaggi'),
            'menu_name'         => __('Travel Types', 'compagni-di-viaggi'),
        );

        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'travel-type'),
        );

        register_taxonomy('tipo_viaggio', array('viaggio'), $args);

        $default_terms = array(
            array('name' => 'Adventure', 'slug' => 'adventure', 'legacy' => array('avventura')),
            array('name' => 'Sea', 'slug' => 'sea', 'legacy' => array('mare')),
            array('name' => 'Mountain', 'slug' => 'mountain', 'legacy' => array('montagna')),
            array('name' => 'Art Cities', 'slug' => 'art-cities', 'legacy' => array('citta-arte')),
            array('name' => 'Culture', 'slug' => 'culture', 'legacy' => array('cultura')),
            array('name' => 'Relax', 'slug' => 'relax', 'legacy' => array()),
            array('name' => 'Food & Wine', 'slug' => 'food-wine', 'legacy' => array()),
            array('name' => 'Sports', 'slug' => 'sports', 'legacy' => array('sport')),
            array('name' => 'Backpacking', 'slug' => 'backpacking', 'legacy' => array('zaino-spalla')),
        );

        foreach ($default_terms as $term_data) {
            $term_id = self::get_existing_term_id('tipo_viaggio', $term_data['slug'], $term_data['legacy']);

            if ($term_id) {
                wp_update_term($term_id, 'tipo_viaggio', array(
                    'name' => $term_data['name'],
                    'slug' => $term_data['slug'],
                ));
                continue;
            }

            wp_insert_term($term_data['name'], 'tipo_viaggio', array('slug' => $term_data['slug']));
        }
    }

    /**
     * Register 'Destinazione' taxonomy
     */
    private static function register_destinazione() {
        $labels = array(
            'name'              => _x('Destinations', 'taxonomy general name', 'compagni-di-viaggi'),
            'singular_name'     => _x('Destination', 'taxonomy singular name', 'compagni-di-viaggi'),
            'search_items'      => __('Search Destinations', 'compagni-di-viaggi'),
            'all_items'         => __('All Destinations', 'compagni-di-viaggi'),
            'parent_item'       => __('Parent Destination', 'compagni-di-viaggi'),
            'parent_item_colon' => __('Parent Destination:', 'compagni-di-viaggi'),
            'edit_item'         => __('Edit Destination', 'compagni-di-viaggi'),
            'update_item'       => __('Update Destination', 'compagni-di-viaggi'),
            'add_new_item'      => __('Add New Destination', 'compagni-di-viaggi'),
            'new_item_name'     => __('New Destination', 'compagni-di-viaggi'),
            'menu_name'         => __('Destinations', 'compagni-di-viaggi'),
        );

        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'destination'),
        );

        register_taxonomy('destinazione', array('viaggio'), $args);
    }

    private static function get_existing_term_id($taxonomy, $slug, $legacy_slugs = array()) {
        $term = term_exists($slug, $taxonomy);
        if ($term && !is_wp_error($term)) {
            return (int) $term['term_id'];
        }

        foreach ($legacy_slugs as $legacy_slug) {
            $term = term_exists($legacy_slug, $taxonomy);
            if ($term && !is_wp_error($term)) {
                return (int) $term['term_id'];
            }
        }

        return 0;
    }
}
