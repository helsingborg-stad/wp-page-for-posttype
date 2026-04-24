<?php

declare(strict_types=1);

namespace WpPageForPostType\Rewrite;

use WpPageForPostType\Polylang\PolylangServiceInterface;

/**
 * Registers rewrite rules so that a custom post type archive is served from
 * the slug of a selected "page for post type".
 *
 * In single-language installations a single rewrite rule is registered
 * (matching the historical behaviour). When Polylang is active, one rule is
 * registered per active language, each including the corresponding `lang`
 * query var so Polylang can resolve the correct language on the archive.
 */
class RewriteBuilder
{
    public function __construct(
        private SlugResolver $slugResolver,
        private PolylangServiceInterface $polylangService,
    ) {
    }

    /**
     * Register archive rewrite rules for the given post type.
     *
     * When Polylang is active and the base page has translations, a rewrite
     * rule is registered per language. Otherwise a single (non language-aware)
     * rule is registered.
     *
     * @param string $postType The post type name.
     * @param object $args     Post type args (mutated to update $args->rewrite['slug']).
     * @param int    $basePageId The page ID selected as "page for post type" in the default language.
     * @return void
     */
    public function registerArchiveRewriteRules(string $postType, object $args, int $basePageId): void
    {
        global $wp_rewrite;

        $args->rewrite = (array) ($args->rewrite ?? array());

        if ($this->polylangService->isActive()) {
            $this->registerPerLanguageRules($postType, $args, $basePageId);
            return;
        }

        // Single-language fallback: keep the original behaviour so existing
        // sites do not regress.
        $slug = $this->slugResolver->getSlugForPage($basePageId);

        if ($slug === '') {
            return;
        }

        $args->rewrite     = wp_parse_args(array('slug' => $slug), $args->rewrite);
        $args->has_archive = $slug;

        $this->addArchiveRules($postType, $slug, $args, $wp_rewrite);

        $permastructArgs         = $args->rewrite;
        $permastructArgs['feed'] = $permastructArgs['feeds'] ?? false;
        $permastruct             = "{$slug}/%{$postType}%";

        add_permastruct($postType, $permastruct, $permastructArgs);
    }

    /**
     * Register one rewrite rule per active Polylang language.
     *
     * Languages without a translation of the base page are skipped so the
     * original default-language archive keeps working.
     *
     * When operating in multilingual mode we intentionally avoid setting
     * `$args->has_archive` / calling add_permastruct(): those are global and
     * would otherwise conflict between languages. Archives are served
     * entirely via the rewrite rules below.
     */
    private function registerPerLanguageRules(string $postType, object $args, int $basePageId): void
    {
        global $wp_rewrite;

        $languages = $this->polylangService->getLanguages();

        if (empty($languages)) {
            // Polylang is active but reports no languages; fall back to the
            // single-language behaviour using the base page.
            $slug = $this->slugResolver->getSlugForPage($basePageId);

            if ($slug === '') {
                return;
            }

            $args->rewrite     = wp_parse_args(array('slug' => $slug), $args->rewrite);
            $this->addArchiveRules($postType, $slug, $args, $wp_rewrite);
            return;
        }

        $seenSlugs = array();

        foreach ($languages as $language) {
            $translatedPageId = $this->polylangService->getTranslatedPostId($basePageId, $language);

            if ($translatedPageId === null) {
                // No translation for this language – skip it so WordPress
                // keeps serving whatever default resolution applies.
                continue;
            }

            $slug = $this->slugResolver->getSlugForPage($translatedPageId, $language);

            if ($slug === '') {
                continue;
            }

            if (isset($seenSlugs[$slug])) {
                // Defensive: two languages ended up producing the same slug.
                // The first one wins; registering a second identical rule
                // would overwrite it silently and is almost certainly a
                // configuration error.
                continue;
            }
            $seenSlugs[$slug] = true;

            $this->addArchiveRules($postType, $slug, $args, $wp_rewrite, $language);
        }
    }

    /**
     * Add the archive + pagination + feed rewrite rules for a given slug.
     *
     * @param string      $postType
     * @param string      $slug       Slug relative to the site root, no surrounding slashes.
     * @param object      $args       Post type args (used for feeds/pages/with_front settings).
     * @param object|null $wpRewrite  Global $wp_rewrite, injected for prefixing.
     * @param string|null $language   Polylang language slug; when supplied, adds `lang=` query var.
     */
    private function addArchiveRules(
        string $postType,
        string $slug,
        object $args,
        ?object $wpRewrite,
        ?string $language = null,
    ): void {
        $rewrite = (array) ($args->rewrite ?? array());

        // Respect the post type's with_front / root settings, mirroring
        // WordPress core's archive rewrite generation.
        if ($wpRewrite !== null) {
            if (!empty($rewrite['with_front']) && isset($wpRewrite->front)) {
                $slug = substr((string) $wpRewrite->front, 1) . $slug;
            } elseif (isset($wpRewrite->root)) {
                $slug = $wpRewrite->root . $slug;
            }
        }

        $langSuffix = $language !== null ? '&lang=' . $language : '';

        add_rewrite_rule(
            "{$slug}/?$",
            "index.php?post_type={$postType}{$langSuffix}",
            'top',
        );

        if (!empty($rewrite['feeds']) && $wpRewrite !== null && !empty($wpRewrite->feeds)) {
            $feeds = '(' . trim(implode('|', $wpRewrite->feeds)) . ')';

            add_rewrite_rule(
                "{$slug}/feed/{$feeds}/?$",
                "index.php?post_type={$postType}{$langSuffix}" . '&feed=$matches[1]',
                'top',
            );

            add_rewrite_rule(
                "{$slug}/{$feeds}/?$",
                "index.php?post_type={$postType}{$langSuffix}" . '&feed=$matches[1]',
                'top',
            );
        }

        if (!empty($rewrite['pages']) && $wpRewrite !== null && isset($wpRewrite->pagination_base)) {
            add_rewrite_rule(
                "{$slug}/{$wpRewrite->pagination_base}/([0-9]{1,})/?$",
                "index.php?post_type={$postType}{$langSuffix}" . '&paged=$matches[1]',
                'top',
            );
        }
    }
}
