<?php

namespace WpPageForPostType;

class Template
{
    public function __construct()
    {
        add_filter('template_include', array($this, 'template'), 1);
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
}
