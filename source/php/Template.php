<?php

namespace WpPageForPostType;

class Template
{
    public function __construct()
    {
        add_filter('template_include', array($this, 'template'), 1);
        add_filter('template_include', array($this, 'post'), 2);
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

    public function post($template)
    {
        if (!is_post_type_archive()) {
            return $template;
        }

        $useContent = get_option('page_for_' . get_post_type() . '_content');
        if (!$useContent) {
            return $template;
        }

        $pageForPostType = get_option('page_for_' . get_post_type());

        if (!$pageForPostType) {
            return $template;
        }

        global $wp_query;
        $wp_query->posts = array(get_post($pageForPostType));
        $wp_query->post_count = 1;

        return $template;
    }
}
