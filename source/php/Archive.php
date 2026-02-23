<?php

declare(strict_types=1);

namespace WpPageForPostType;

class Archive
{
    public function __construct()
    {
        add_action('admin_bar_menu', array($this, 'addCustomAdminBarItems'), 80);
    }

    public function addCustomAdminBarItems()
    {
        $postId = get_option('page_for_' . get_post_type());

        if (!is_archive() || $postId === '0') {
            return;
        }

        global $wp_admin_bar;

        $wp_admin_bar->add_node(array(
            'id' => 'edit',
            'title' => '<span class="ab-item"></span>' . __('Edit Page', 'wp-page-for-post-type'),
            'href' => get_edit_post_link($postId),
        ));
    }
}
