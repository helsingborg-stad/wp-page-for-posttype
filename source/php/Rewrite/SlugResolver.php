<?php

declare(strict_types=1);

namespace WpPageForPostType\Rewrite;

use WpPageForPostType\Polylang\PolylangServiceInterface;

/**
 * Resolves the relative URL slug of a given page, optionally scoped to a
 * Polylang language so that permalinks are generated with the correct
 * language prefix (e.g. "en/books" or "sv/bocker").
 */
class SlugResolver
{
    public function __construct(private ?PolylangServiceInterface $polylangService = null)
    {
    }

    /**
     * Build a permalink-derived slug for the given page.
     *
     * Strips the site's home_url and trims slashes so the result is suitable
     * for use inside a WordPress rewrite rule regex.
     *
     * When a language is supplied and Polylang is active, the language
     * context is switched before the permalink is resolved so that
     * get_permalink() returns the language-prefixed URL.
     */
    public function getSlugForPage(int $pageId, ?string $language = null): string
    {
        if ($language !== null && $this->polylangService !== null && $this->polylangService->isActive()) {
            $this->polylangService->switchLanguage($language);
        }

        $permalink = (string) get_permalink($pageId);
        $home      = (string) home_url();

        // Strip scheme+host (home_url) so only the path portion remains.
        $slug = str_replace($home, '', $permalink);

        return trim($slug, '/');
    }
}
