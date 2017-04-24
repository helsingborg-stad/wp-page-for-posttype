<?php

namespace WpPageForPostType;

class Settings
{
    public static $originalSlugs = array();

    public function __construct()
    {
        add_action('admin_init', array($this, 'register'));
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
            __('Post types', 'wp-page-for-post-type'),
            '__return_false',
            'reading'
        );

        $postTypes = array_filter($postTypes, function ($item) {
            return $item->has_archive;
        });

        foreach ($postTypes as $postType) {
            $id = 'page_for_' . $postType->name;

            register_setting('reading', $id, array($this, 'flush'));

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
            'name'             => esc_attr($args['name']),
            'id'               => esc_attr($args['name'] . '_dropdown'),
            'selected'         => esc_attr($args['value']),
            'show_option_none' => sprintf(__('Default (/%s/)'), $default),
        ));
    }
}
