<?php

namespace WpPageForPostType;

class Template
{
    public function __construct()
    {
        add_filter('template_include', array($this, 'template'), 1);
        add_action('pre_get_posts', array($this, 'post'), 5);
    }

    public function template($template)
    {
        if (!is_post_type_archive()) {
            return $template;
        }

        if (get_option('page_for_' . get_post_type() . '_template') !== 'on') {
            return $template;
        }

        // Get page template
        $pageForPostType = get_option('page_for_' . get_post_type());
        $template = get_page_template_slug($pageForPostType);

        // Page-template if page is using default template
        if (empty($template)) {
            $template = 'page';
        }

        return $template;
    }

    /**
     * Manipilate fetched posts in order to reflect settings.
     *
     * @param WP_Query $query Original query
     *
     * @return void Nothing returned
     */
    public function post($query)
    {
        if (!$query->is_main_query()) {
            return;
        }

        if (!$query->is_archive()) {
            return;
        }

        $useContent = get_option('page_for_' . $query->get('post_type') . '_content');
        if (!$useContent || $useContent == 'off') {
            return;
        }

        $pageForPostType = get_option('page_for_' . $query->get('post_type'));
        if (is_numeric($pageForPostType)) {
            $query = new \WP_Query(
                [
                    'p' => $pageForPostType,
                    'post_type' => 'page'
                ]
            );

            define('PAGE_FOR_POSTTYPE_ID', $pageForPostType);
        }

    }
}
