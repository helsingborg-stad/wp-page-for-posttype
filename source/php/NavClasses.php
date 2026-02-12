<?php

declare(strict_types=1);

namespace WpPageForPostType;

class NavClasses
{
    public function __construct()
    {
        add_filter('wp_nav_menu_objects', array($this, 'updateNavCLasses'), 1, 2);
    }

    /**
     * Set nav classes
     * @param  array    $sorted_menu_items The menu items, sorted by each menu item's menu order
     * @param  stdClass $args              An object containing wp_nav_menu() arguments
     * @return array
     */
    public function updateNavCLasses($sorted_menu_items, $args)
    {
        $object_post_type = get_post_type();
        if (!get_post_type()) {
            return $sorted_menu_items;
        }

        // Get post page ID array
        $page_ids = $this->get_page_ids();
        if (!isset($page_ids[$object_post_type])) {
            return $sorted_menu_items;
        }

        foreach ($sorted_menu_items as &$item) {
            if ($item->type === 'post_type' && $item->object === 'page' && intval($item->object_id) === intval($page_ids[$object_post_type])) {
                if (is_singular($object_post_type)) {
                    $item->classes[] = 'current-menu-ancestor';
                    $item->current_item_ancestor = true;
                }
                if (is_post_type_archive($object_post_type)) {
                    $item->classes[] = 'current-menu-item';
                    $item->current_item = true;
                }
            }
        }

        return $sorted_menu_items;
    }

    /**
     * Return array with page for post type Ids
     * @return array
     */
    protected function get_page_ids()
    {
        $page_ids = array();

        foreach (get_post_types(array(), 'objects') as $post_type) {
            if (!$post_type->has_archive) {
                continue;
            }

            if (!get_option("page_for_{$post_type->name}")) {
                continue;
            }

            $page_ids[$post_type->name] = get_option("page_for_{$post_type->name}");
        }

        return $page_ids;
    }
}
