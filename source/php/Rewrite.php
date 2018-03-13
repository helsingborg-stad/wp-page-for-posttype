<?php

namespace WpPageForPostType;

class Rewrite
{
    public function __construct()
    {
        add_action('registered_post_type', array($this, 'updateRewrite'), 11, 2);
    }

    /**
     * Updates the rewrite rules for the posttype
     * @param  string $postType
     * @param  array $args
     * @return void
     */
    public function updateRewrite(string $postType, $args)
    {
        global $wp_post_types, $wp_rewrite;

        // Bail if page not set
        $pageForPostType = get_option('page_for_' . $postType);
        if (!$pageForPostType) {
            return;
        }

        // Bail if page not published or private
        $postStatus = get_post_status($pageForPostType);
        if (!in_array($postStatus, array('publish', 'private'))) {
            return;
        }

        // Get original rewrite rule
        $args->rewrite = (array) $args->rewrite;
        $oldRewrite = isset($args->rewrite['slug']) ? $args->rewrite['slug'] : $postType;
        \WpPageForPostType\Settings::$originalSlugs[$postType] = $oldRewrite;

        // Get the new slug
        $newSlug = $this->getPageSlug($pageForPostType);

        $args->rewrite     = wp_parse_args(array('slug' => $newSlug), $args->rewrite);
        $args->has_archive = $newSlug;

        // Rebuild rewrite rules
        $this->rebuildRewriteRules($postType, $args, $oldRewrite);

        // Update global
        $wp_post_types[$postType] = $args;
    }

    /**
     * Rebuild rewrite rules
     * @param  string $postType
     * @param  array $args
     * @return bool
     */
    public function rebuildRewriteRules($postType, $args)
    {
        global $wp_post_types, $wp_rewrite;

        if (!is_admin() && empty(get_option('permalink_structure'))) {
            return;
        }

        if ($args->has_archive) {
            $archiveSlug = $args->has_archive === true ? $args->rewrite['slug'] : $args->has_archive;

            // Maybe append blogfront
            if ($args->rewrite['with_front']) {
                $archiveSlug = substr($wp_rewrite->front, 1) . $archiveSlug;
            } else {
                $archiveSlug = $wp_rewrite->root . $archiveSlug;
            }

            // Add rewrite rule for the archive
            add_rewrite_rule("{$archiveSlug}/?$", "index.php?post_type=$postType", 'top');

            // Add rewrite rules for feeds
            if ($args->rewrite['feeds'] && $wp_rewrite->feeds) {
                $feeds = '(' . trim(implode('|', $wp_rewrite->feeds)) . ')';

                add_rewrite_rule(
                    "{$archiveSlug}/feed/$feeds/?$",
                    "index.php?post_type=$postType" . '&feed=$matches[1]',
                    'top'
                );

                add_rewrite_rule(
                    "{$archiveSlug}/$feeds/?$",
                    "index.php?post_type=$postType" . '&feed=$matches[1]',
                    'top'
                );
            }

            // Add rewrite rules for pagination
            if ($args->rewrite['pages']) {
                add_rewrite_rule(
                    "{$archiveSlug}/{$wp_rewrite->pagination_base}/([0-9]{1,})/?$",
                    "index.php?post_type=$postType" . '&paged=$matches[1]',
                    'top'
                );
            }
        }

        $permastructArgs = $args->rewrite;
        $permastructArgs['feed'] = $permastructArgs['feeds'];

        // Support plugins that enable 'permastruct' option
        if (isset($args->rewrite['permastruct'])) {
            $permastruct = str_replace($oldRewrite, $slug, $args->rewrite['permastruct']);
        } else {
            $permastruct = "{$args->rewrite['slug']}/%$postType%";
        }

        add_permastruct($postType, $permastruct, $permastructArgs);

        return true;
    }

    /**
     * Get permalink (without home url) for a specific post
     * @param  int    $postId
     * @return string
     */
    public function getPageSlug(int $postId)
    {
        $slug = get_permalink($postId);
        $slug = str_replace(home_url(), '', $slug);
        $slug = trim($slug, '/');

        return $slug;
    }
}
