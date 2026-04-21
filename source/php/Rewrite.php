<?php

declare(strict_types=1);

namespace WpPageForPostType;

/**
 * Handles custom post type rewrite rules and localized archive URLs.
 */
class Rewrite
{
    public function __construct()
    {
        add_action('registered_post_type', array($this, 'updateRewrite'), 11, 2);
        add_filter('post_type_archive_link', array($this, 'filterArchiveLink'), 30, 2);
        add_filter('pll_get_archive_url', array($this, 'filterPolylangArchiveUrl'), 10, 2);
    }

    /**
     * Updates the rewrite rules for the post type.
     *
     * @param string $postType
     * @param object $args
     * @return void
     */
    public function updateRewrite(string $postType, object $args): void
    {
        global $wp_post_types;

        $pageForPostType = get_option('page_for_' . $postType);
        if (!$pageForPostType) {
            return;
        }

        $postStatus = get_post_status($pageForPostType);
        if (!in_array($postStatus, array('publish', 'private'), true)) {
            return;
        }

        $args->rewrite = (array) $args->rewrite;
        $oldRewrite    = isset($args->rewrite['slug']) ? $args->rewrite['slug'] : $postType;
        Settings::$originalSlugs[$postType] = $oldRewrite;

        if (!is_numeric($pageForPostType)) {
            return;
        }

        $newSlug = $this->getPageSlug((int) $pageForPostType);

        $args->rewrite     = wp_parse_args(array('slug' => $newSlug), $args->rewrite);
        $args->has_archive = $newSlug;

        $this->rebuildRewriteRules($postType, $args, $oldRewrite);

        $wp_post_types[$postType] = $args;
    }

    /**
     * Rebuild rewrite rules.
     *
     * @param string $postType
     * @param object $args
     * @param string $oldRewrite
     * @return bool
     */
    public function rebuildRewriteRules(string $postType, object $args, string $oldRewrite = ''): bool
    {
        global $wp_rewrite;

        if (!is_admin() && empty(get_option('permalink_structure'))) {
            return false;
        }

        if ($args->has_archive) {
            $archiveSlug     = $args->has_archive === true ? $args->rewrite['slug'] : $args->has_archive;
            $pageForPostType = get_option('page_for_' . $postType);
            $archiveSlugs = array_merge(
                array((string) $archiveSlug),
                $this->getLocalizedPageSlugs((int) $pageForPostType),
            );

            foreach (array_unique($archiveSlugs) as $localizedArchiveSlug) {
                $this->addArchiveRewriteRules($postType, $args, $localizedArchiveSlug, $wp_rewrite);
            }
        }

        $permastructArgs = $args->rewrite;
        $permastructArgs['feed'] = $permastructArgs['feeds'];

        if (isset($args->rewrite['permastruct'])) {
            $originalRewriteSlug = $oldRewrite !== '' ? $oldRewrite : $postType;
            $permastruct         = str_replace(
                $originalRewriteSlug,
                $args->rewrite['slug'],
                $args->rewrite['permastruct'],
            );
        } else {
            $permastruct = "{$args->rewrite['slug']}/%$postType%";
        }

        add_permastruct($postType, $permastruct, $permastructArgs);

        return true;
    }

    /**
     * Add archive rewrite rules for a specific slug.
     *
     * @param string $postType
     * @param object $args
     * @param string $archiveSlug
     * @param object $wpRewrite
     * @return void
     */
    protected function addArchiveRewriteRules(
        string $postType,
        object $args,
        string $archiveSlug,
        object $wpRewrite,
    ): void {
        if ($args->rewrite['with_front']) {
            $archiveSlug = substr($wpRewrite->front, 1) . $archiveSlug;
        } else {
            $archiveSlug = $wpRewrite->root . $archiveSlug;
        }

        add_rewrite_rule("{$archiveSlug}/?$", "index.php?post_type=$postType", 'top');

        if ($args->rewrite['feeds'] && $wpRewrite->feeds) {
            $feeds = '(' . trim(implode('|', $wpRewrite->feeds)) . ')';

            add_rewrite_rule(
                "{$archiveSlug}/feed/$feeds/?$",
                "index.php?post_type=$postType" . '&feed=$matches[1]',
                'top',
            );

            add_rewrite_rule(
                "{$archiveSlug}/$feeds/?$",
                "index.php?post_type=$postType" . '&feed=$matches[1]',
                'top',
            );
        }

        if ($args->rewrite['pages']) {
            add_rewrite_rule(
                "{$archiveSlug}/{$wpRewrite->pagination_base}/([0-9]{1,})/?$",
                "index.php?post_type=$postType" . '&paged=$matches[1]',
                'top',
            );
        }
    }

    /**
     * Return the localized archive link for the current language.
     *
     * @param string $link
     * @param string $postType
     * @return string
     */
    public function filterArchiveLink(string $link, string $postType): string
    {
        $pageForPostType = get_option('page_for_' . $postType);
        if (!is_numeric($pageForPostType)) {
            return $link;
        }

        $localizedLink = $this->getLocalizedPageUrl(
            (int) $pageForPostType,
            function_exists('pll_current_language') ? pll_current_language('slug') : null,
        );

        return $localizedLink ?? $link;
    }

    /**
     * Return the localized archive URL for Polylang's language switcher.
     *
     * @param string $url
     * @param mixed $language
     * @return string
     */
    public function filterPolylangArchiveUrl(string $url, $language): string
    {
        if (!is_post_type_archive()) {
            return $url;
        }

        $postType = get_query_var('post_type');
        if (is_array($postType)) {
            $postType = reset($postType);
        }

        if (!is_string($postType) || $postType === '') {
            return $url;
        }

        $pageForPostType = get_option('page_for_' . $postType);
        if (!is_numeric($pageForPostType)) {
            return $url;
        }

        $languageSlug = is_object($language) && isset($language->slug) && is_string($language->slug)
            ? $language->slug
            : null;
        $localizedLink = $this->getLocalizedPageUrl((int) $pageForPostType, $languageSlug);

        return $localizedLink ?? $url;
    }

    /**
     * Get all localized page slugs for a specific post.
     *
     * @param int $postId
     * @return array<int, string>
     */
    protected function getLocalizedPageSlugs(int $postId): array
    {
        if ($postId <= 0) {
            return array();
        }

        $pageIds = array($postId);

        if (function_exists('pll_get_post_translations')) {
            $translations = pll_get_post_translations($postId);
            if (is_array($translations)) {
                $pageIds = array_merge($pageIds, array_values($translations));
            }
        }

        $slugs = array();

        foreach (array_unique($pageIds) as $pageId) {
            if (!is_numeric($pageId)) {
                continue;
            }

            $pageSlug = $this->getPageSlug((int) $pageId);
            if ($pageSlug !== '') {
                $slugs[] = $pageSlug;
            }
        }

        return $slugs;
    }

    /**
     * Get the localized page URL for a page and language.
     *
     * @param int         $postId
     * @param string|null $language
     * @return string|null
     */
    protected function getLocalizedPageUrl(int $postId, ?string $language = null): ?string
    {
        $localizedPageId = $postId;

        if ($language !== null && function_exists('pll_get_post')) {
            $translatedPostId = pll_get_post($postId, $language);
            if (is_numeric($translatedPostId)) {
                $localizedPageId = (int) $translatedPostId;
            }
        }

        $postStatus = get_post_status($localizedPageId);
        if (!in_array($postStatus, array('publish', 'private'), true)) {
            return null;
        }

        $localizedLink = get_permalink($localizedPageId);

        return is_string($localizedLink) && $localizedLink !== '' ? $localizedLink : null;
    }

    /**
     * Get permalink without the home URL for a specific post.
     *
     * @param int $postId
     * @return string
     */
    public function getPageSlug(int $postId): string
    {
        $slug = get_permalink($postId);
        $slug = str_replace(home_url(), '', $slug);
        $slug = trim($slug, '/');

        return $slug;
    }
}
