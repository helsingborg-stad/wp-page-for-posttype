<?php

declare(strict_types=1);

namespace WpPageForPostType\Tests\Polylang;

use PHPUnit\Framework\TestCase;
use WpPageForPostType\Polylang\PolylangService;

final class PolylangServiceTest extends TestCase
{
    public function testIsActiveReturnsFalseWhenPolylangFunctionsNotLoaded(): void
    {
        // Arrange
        $service = new PolylangService();

        // Act & Assert
        // The Polylang functions are not defined in the test environment by default.
        if (function_exists('pll_languages_list')) {
            $this->markTestSkipped('Polylang functions are unexpectedly defined.');
        }

        $this->assertFalse($service->isActive());
        $this->assertSame(array(), $service->getLanguages());
        $this->assertNull($service->getTranslatedPostId(1, 'en'));

        // Should not throw when Polylang is inactive.
        $service->switchLanguage('en');
    }
}
