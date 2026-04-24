<?php

declare(strict_types=1);

namespace WpPageForPostType\Tests\Rewrite;

use PHPUnit\Framework\TestCase;
use WpPageForPostType\Polylang\PolylangServiceInterface;
use WpPageForPostType\Rewrite\SlugResolver;

final class SlugResolverTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wp_stub_permalinks'] = array();
        $GLOBALS['wp_stub_home_url']   = 'http://example.com';
    }

    public function testGetSlugForPageStripsHomeUrlAndTrimsSlashes(): void
    {
        // Arrange
        $GLOBALS['wp_stub_permalinks'][42] = 'http://example.com/books/';
        $resolver                          = new SlugResolver();

        // Act
        $slug = $resolver->getSlugForPage(42);

        // Assert
        $this->assertSame('books', $slug);
    }

    public function testGetSlugForPageReturnsEmptyStringWhenPermalinkIsFalse(): void
    {
        // Arrange
        $resolver = new SlugResolver();

        // Act
        $slug = $resolver->getSlugForPage(99);

        // Assert
        $this->assertSame('', $slug);
    }

    public function testGetSlugForPageSwitchesLanguageWhenPolylangActive(): void
    {
        // Arrange
        $GLOBALS['wp_stub_permalinks'][7] = 'http://example.com/sv/bocker/';

        $polylang = new class implements PolylangServiceInterface {
            public ?string $switched = null;

            public function isActive(): bool
            {
                return true;
            }
            public function getLanguages(): array
            {
                return array('en', 'sv');
            }
            public function getTranslatedPostId(int $postId, string $language): ?int
            {
                return $postId;
            }
            public function switchLanguage(string $language): void
            {
                $this->switched = $language;
            }
        };

        $resolver = new SlugResolver($polylang);

        // Act
        $slug = $resolver->getSlugForPage(7, 'sv');

        // Assert
        $this->assertSame('sv/bocker', $slug);
        $this->assertSame('sv', $polylang->switched);
    }

    public function testGetSlugForPageDoesNotSwitchLanguageWhenPolylangInactive(): void
    {
        // Arrange
        $GLOBALS['wp_stub_permalinks'][11] = 'http://example.com/books/';

        $polylang = new class implements PolylangServiceInterface {
            public ?string $switched = null;

            public function isActive(): bool
            {
                return false;
            }
            public function getLanguages(): array
            {
                return array();
            }
            public function getTranslatedPostId(int $postId, string $language): ?int
            {
                return null;
            }
            public function switchLanguage(string $language): void
            {
                $this->switched = $language;
            }
        };

        $resolver = new SlugResolver($polylang);

        // Act
        $slug = $resolver->getSlugForPage(11, 'en');

        // Assert
        $this->assertSame('books', $slug);
        $this->assertNull($polylang->switched);
    }
}
