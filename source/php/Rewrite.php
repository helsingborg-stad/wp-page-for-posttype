<?php

declare(strict_types=1);

namespace WpPageForPostType;

use WpPageForPostType\Polylang\PolylangService;
use WpPageForPostType\Polylang\PolylangServiceInterface;
use WpPageForPostType\Rewrite\RewriteBuilder;
use WpPageForPostType\Rewrite\SlugResolver;

/**
 * Wires the `registered_post_type` hook into the rewrite builder.
 *
 * This class retains its historical name/position so existing consumers keep
 * working, but the actual rewrite-rule generation logic now lives in
 * {@see RewriteBuilder} and is split across dedicated collaborators:
 *  - {@see PolylangServiceInterface} — language resolver
 *  - {@see SlugResolver}             — slug generator
 *  - {@see RewriteBuilder}           — rewrite rule builder
 *
 * The split makes the rewrite logic Polylang-aware: when Polylang is active
 * the builder registers per-language rewrite rules (including `lang=`),
 * otherwise it falls back to the original single-rule behaviour.
 */
class Rewrite
{
    private RewriteBuilder $rewriteBuilder;

    public function __construct(?RewriteBuilder $rewriteBuilder = null)
    {
        if ($rewriteBuilder === null) {
            $polylangService      = new PolylangService();
            $slugResolver         = new SlugResolver($polylangService);
            $this->rewriteBuilder = new RewriteBuilder($slugResolver, $polylangService);
        } else {
            $this->rewriteBuilder = $rewriteBuilder;
        }

        add_action('registered_post_type', array($this, 'updateRewrite'), 11, 2);
    }

    /**
     * Updates the rewrite rules for the posttype.
     *
     * @param string $postType
     * @param object $args
     * @return void
     */
    public function updateRewrite(string $postType, $args)
    {
        global $wp_post_types;

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

        // Record the original slug for the settings screen "Default (/…/)" label.
        $args->rewrite                                             = (array) $args->rewrite;
        $oldRewrite                                                = isset($args->rewrite['slug']) ? $args->rewrite['slug'] : $postType;
        \WpPageForPostType\Settings::$originalSlugs[$postType]     = $oldRewrite;

        if (!is_numeric($pageForPostType)) {
            return;
        }

        if (!is_admin() && empty(get_option('permalink_structure'))) {
            return;
        }

        $this->rewriteBuilder->registerArchiveRewriteRules($postType, $args, (int) $pageForPostType);

        // Keep the post type args mutation visible to the rest of WordPress,
        // matching the historical behaviour of this class.
        $wp_post_types[$postType] = $args;
    }

    /**
     * Get permalink (without home url) for a specific post.
     *
     * Preserved for backward compatibility with any external callers that
     * may have relied on this method. Internally, {@see SlugResolver} is
     * now responsible for slug generation.
     *
     * @param  int $postId
     * @return string
     */
    public function getPageSlug(int $postId): string
    {
        $slug = get_permalink($postId);
        $slug = str_replace(home_url(), '', (string) $slug);

        return trim($slug, '/');
    }
}
