<?php

declare(strict_types=1);

namespace WpPageForPostType\Polylang;

/**
 * Encapsulates interaction with the Polylang plugin so that multilingual logic
 * can be consumed through a narrow, mockable interface.
 */
interface PolylangServiceInterface
{
    /**
     * Whether Polylang is active and its API is available.
     */
    public function isActive(): bool;

    /**
     * Returns a list of active language slugs (e.g. ['en', 'sv']).
     *
     * @return string[]
     */
    public function getLanguages(): array;

    /**
     * Returns the translated post ID for a given post in a given language,
     * or null when no translation exists.
     */
    public function getTranslatedPostId(int $postId, string $language): ?int;

    /**
     * Temporarily switch the Polylang language context. Useful when generating
     * permalinks that depend on the current language.
     */
    public function switchLanguage(string $language): void;
}
