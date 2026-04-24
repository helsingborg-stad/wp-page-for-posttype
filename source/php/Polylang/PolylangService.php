<?php

declare(strict_types=1);

namespace WpPageForPostType\Polylang;

/**
 * Default Polylang service implementation backed by the global Polylang
 * functions (pll_languages_list, pll_get_post, pll_switch_language).
 *
 * Methods degrade gracefully when Polylang is not installed: {@see isActive()}
 * returns false and other methods return empty / null values.
 */
class PolylangService implements PolylangServiceInterface
{
    public function isActive(): bool
    {
        return function_exists('pll_languages_list');
    }

    /**
     * @return string[]
     */
    public function getLanguages(): array
    {
        if (!$this->isActive()) {
            return array();
        }

        $languages = \pll_languages_list();

        if (!is_array($languages)) {
            return array();
        }

        return array_values(array_filter($languages, 'is_string'));
    }

    public function getTranslatedPostId(int $postId, string $language): ?int
    {
        if (!$this->isActive() || !function_exists('pll_get_post')) {
            return null;
        }

        $translatedId = \pll_get_post($postId, $language);

        if (!is_numeric($translatedId) || (int) $translatedId <= 0) {
            return null;
        }

        return (int) $translatedId;
    }

    public function switchLanguage(string $language): void
    {
        if (!$this->isActive() || !function_exists('pll_switch_language')) {
            return;
        }

        \pll_switch_language($language);
    }
}
