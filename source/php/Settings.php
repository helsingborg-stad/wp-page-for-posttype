<?php

namespace WpPageForPostType;

class Settings
{
    public static $originalSlugs = array();

    public function __construct()
    {
        add_action('admin_init', array($this, 'register'));
        add_filter('get_pages', array($this, 'addCustomPostTypes'), 10, 2);
    }

    /**
     * Registers settings
     * @return void
     */
    public function register()
    {
        $postTypes = get_post_types(array(), 'objects');

        add_settings_section(
            'wp_page_for_post_type',
            __('Page and template for post type archives', 'wp-page-for-post-type'),
            '__return_false',
            'reading'
        );

        $postTypes = array_filter($postTypes, function ($item) {
            return $item->has_archive;
        });

        foreach ($postTypes as $postType) {
            $id = 'page_for_' . $postType->name;

            register_setting('reading', $id, array($this, 'flush'));
            register_setting('reading', 'page_for_' . $postType->name . '_template');
            register_setting('reading', 'page_for_' . $postType->name . '_content');

            add_settings_field(
                $id,
                $postType->label,
                array($this, 'pageDropDown'),
                'reading',
                'wp_page_for_post_type',
                array(
                    'name' => $id,
                    'post_type' => $postType,
                    'value'     => get_option($id)
                )
            );
        }
    }

    /**
     * Flush rewrite rules and force intval for page for post type settings
     * @param  string $value
     * @return int
     */
    public function flush($value)
    {
        flush_rewrite_rules();
        return intval($value);
    }

    /**
     * Renders page dropdown selector
     * @param  array $args Args
     * @return void
     */
    public function pageDropDown(array $args)
    {
        $default = $args['post_type']->name;
        if (isset($this->original_slugs[$args['post_type']->name])) {
            $default = self::$originalSlugs[$args['post_type']->name];
        }

        wp_dropdown_pages(array(
            'post_status'      => array('publish', 'private'),
            'name'             => esc_attr($args['name']),
            'id'               => esc_attr($args['name'] . '_dropdown'),
            'selected'         => esc_attr($args['value']),
            'show_option_none' => sprintf(__('Default (/%s/)'), $default)
        ));

        $useTemplate = checked(get_option('page_for_' . $args['post_type']->name . '_template'), 'on', false);
        if ($useTemplate) {
            echo '<label style="margin-left: 10px;"><input type="checkbox" name="page_for_' . $args['post_type']->name . '_template" checked> ' . __('Use template from page', 'wp-page-for-post-type') . '</label>';
        } else {
            echo '<label style="margin-left: 10px;"><input type="checkbox" name="page_for_' . $args['post_type']->name . '_template"> ' . __('Use template from page', 'wp-page-for-post-type') . '</label>';
        }

        $useContent = checked(get_option('page_for_' . $args['post_type']->name . '_content'), 'on', false);
        if ($useContent) {
            echo '<label style="margin-left: 10px;"><input type="checkbox" name="page_for_' . $args['post_type']->name . '_content" checked> ' . __('Use content from page', 'wp-page-for-post-type') . '</label>';
        } else {
            echo '<label style="margin-left: 10px;"><input type="checkbox" name="page_for_' . $args['post_type']->name . '_content"> ' . __('Use content from page', 'wp-page-for-post-type') . '</label>';
        }
    }

    public function addCustomPostTypes($pages, $parsedArgs) {

        //Limit to reading settings
        $screen = get_current_screen(); 
        if(is_null($screen) || !(is_a($screen, 'WP_Screen') && isset($screen->base) && $screen->base == "options-reading")) {
            return $pages; 
        }

        //Get avabile post types that are "page like'sh"
        $postTypes = get_post_types(array(
            'hierarchical' => true,
            'public' => true
        )); 

        //Set neeeded args 
        $args['post_type'] = $postTypes;
        $args['numberposts'] = -1; 

        //Get posts 
        $postsAndPages = get_posts($args); 

        //Return posts and pages
        if(!empty($postsAndPages)) {

            //Suffix with post type
            array_walk($postsAndPages, function(&$item, $key){
                $item->post_title = $item->post_title . " (" . $item->post_type. ")"; 
            }); 

            //Return multi posttype 
            return $postsAndPages; 
        }
     
        //Fallback to normal behaviour
        return $pages; 
    }
}
