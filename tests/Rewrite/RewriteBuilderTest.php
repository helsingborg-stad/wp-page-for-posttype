<?php

declare(strict_types=1);

namespace WpPageForPostType\Tests\Rewrite;

use PHPUnit\Framework\TestCase;
use WpPageForPostType\Polylang\PolylangServiceInterface;
use WpPageForPostType\Rewrite\RewriteBuilder;
use WpPageForPostType\Rewrite\SlugResolver;

final class RewriteBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wp_stub_permalinks']    = array();
        $GLOBALS['wp_stub_home_url']      = 'http://example.com';
        $GLOBALS['wp_stub_rewrite_rules'] = array();
        $GLOBALS['wp_stub_permastructs']  = array();

        // A minimal $wp_rewrite double.
        $GLOBALS['wp_rewrite']                  = new \stdClass();
        $GLOBALS['wp_rewrite']->front           = '/';
        $GLOBALS['wp_rewrite']->root            = '';
        $GLOBALS['wp_rewrite']->feeds           = array('rss', 'atom');
        $GLOBALS['wp_rewrite']->pagination_base = 'page';
    }

    private function makeArgs(): object
    {
        $args              = new \stdClass();
        $args->rewrite     = array('with_front' => false, 'feeds' => true, 'pages' => true);
        $args->has_archive = true;

        return $args;
    }

    public function testSingleLanguageBehaviourRegistersSingleRuleWithoutLangQueryVar(): void
    {
        // Arrange
        $GLOBALS['wp_stub_permalinks'][42] = 'http://example.com/books/';

        $polylang = $this->createPolylangStub(false, array(), array());
        $builder  = new RewriteBuilder(new SlugResolver($polylang), $polylang);
        $args     = $this->makeArgs();

        // Act
        $builder->registerArchiveRewriteRules('book', $args, 42);

        // Assert
        $rules = $GLOBALS['wp_stub_rewrite_rules'];
        $this->assertNotEmpty($rules);
        $archive = $rules[0];
        $this->assertSame('books/?$', $archive['regex']);
        $this->assertSame('index.php?post_type=book', $archive['query']);
        $this->assertSame('top', $archive['priority']);
        $this->assertSame('books', $args->has_archive);
        $this->assertArrayHasKey('book', $GLOBALS['wp_stub_permastructs']);
    }

    public function testPolylangActiveRegistersPerLanguageRulesWithLangQueryVar(): void
    {
        // Arrange
        $GLOBALS['wp_stub_permalinks'][100] = 'http://example.com/en/books/';
        $GLOBALS['wp_stub_permalinks'][200] = 'http://example.com/sv/bocker/';

        $polylang = $this->createPolylangStub(
            true,
            array('en', 'sv'),
            array('en' => 100, 'sv' => 200),
        );

        $builder = new RewriteBuilder(new SlugResolver($polylang), $polylang);
        $args    = $this->makeArgs();
        $hadArchiveBefore = $args->has_archive;

        // Act
        $builder->registerArchiveRewriteRules('book', $args, 100);

        // Assert
        $rules = $GLOBALS['wp_stub_rewrite_rules'];
        $archiveRules = array_values(array_filter($rules, static fn($r) => !str_contains($r['regex'], '(') && !str_contains($r['regex'], 'feed')));

        $this->assertCount(2, $archiveRules, 'Expected one archive rule per language.');

        $queries = array_column($archiveRules, 'query');
        $this->assertContains('index.php?post_type=book&lang=en', $queries);
        $this->assertContains('index.php?post_type=book&lang=sv', $queries);

        // has_archive must not be mutated in multilingual mode to avoid
        // global conflicts between languages.
        $this->assertSame($hadArchiveBefore, $args->has_archive);

        // No global permastruct should be registered in multilingual mode.
        $this->assertArrayNotHasKey('book', $GLOBALS['wp_stub_permastructs']);
    }

    public function testPolylangActiveSkipsLanguagesWithoutTranslation(): void
    {
        // Arrange
        $GLOBALS['wp_stub_permalinks'][100] = 'http://example.com/en/books/';

        $polylang = $this->createPolylangStub(
            true,
            array('en', 'sv'),
            array('en' => 100),   // no 'sv' translation
        );

        $builder = new RewriteBuilder(new SlugResolver($polylang), $polylang);
        $args    = $this->makeArgs();

        // Act
        $builder->registerArchiveRewriteRules('book', $args, 100);

        // Assert
        $archiveRules = array_values(array_filter(
            $GLOBALS['wp_stub_rewrite_rules'],
            static fn($r) => !str_contains($r['regex'], '(') && !str_contains($r['regex'], 'feed'),
        ));

        $this->assertCount(1, $archiveRules);
        $this->assertSame('index.php?post_type=book&lang=en', $archiveRules[0]['query']);
    }

    public function testPolylangActiveRegistersPaginationAndFeedRulesPerLanguage(): void
    {
        // Arrange
        $GLOBALS['wp_stub_permalinks'][100] = 'http://example.com/en/books/';
        $GLOBALS['wp_stub_permalinks'][200] = 'http://example.com/sv/bocker/';

        $polylang = $this->createPolylangStub(
            true,
            array('en', 'sv'),
            array('en' => 100, 'sv' => 200),
        );

        $builder = new RewriteBuilder(new SlugResolver($polylang), $polylang);
        $args    = $this->makeArgs();

        // Act
        $builder->registerArchiveRewriteRules('book', $args, 100);

        // Assert
        $rules = $GLOBALS['wp_stub_rewrite_rules'];

        // Pagination rule for each language.
        $paginationEn = array_filter($rules, static fn($r) => $r['regex'] === 'en/books/page/([0-9]{1,})/?$');
        $paginationSv = array_filter($rules, static fn($r) => $r['regex'] === 'sv/bocker/page/([0-9]{1,})/?$');
        $this->assertCount(1, $paginationEn);
        $this->assertCount(1, $paginationSv);
        $this->assertSame(
            'index.php?post_type=book&lang=en&paged=$matches[1]',
            array_values($paginationEn)[0]['query'],
        );

        // Feed rules for each language.
        $feedsEn = array_filter($rules, static fn($r) => str_contains($r['regex'], 'en/books') && str_contains($r['regex'], 'rss|atom'));
        $this->assertNotEmpty($feedsEn);
        foreach ($feedsEn as $rule) {
            $this->assertStringContainsString('lang=en', $rule['query']);
        }
    }

    public function testPolylangActiveButNoLanguagesFallsBackToBasePage(): void
    {
        // Arrange
        $GLOBALS['wp_stub_permalinks'][42] = 'http://example.com/books/';

        $polylang = $this->createPolylangStub(true, array(), array());
        $builder  = new RewriteBuilder(new SlugResolver($polylang), $polylang);
        $args     = $this->makeArgs();

        // Act
        $builder->registerArchiveRewriteRules('book', $args, 42);

        // Assert
        $rules = $GLOBALS['wp_stub_rewrite_rules'];
        $this->assertNotEmpty($rules);
        $this->assertSame('books/?$', $rules[0]['regex']);
        $this->assertSame('index.php?post_type=book', $rules[0]['query']);
    }

    /**
     * @param array<int,string>     $languages
     * @param array<string,int>     $translations language => pageId
     */
    private function createPolylangStub(bool $active, array $languages, array $translations): PolylangServiceInterface
    {
        return new class ($active, $languages, $translations) implements PolylangServiceInterface {
            public function __construct(
                private bool $active,
                private array $languages,
                private array $translations,
            ) {
            }

            public function isActive(): bool
            {
                return $this->active;
            }
            public function getLanguages(): array
            {
                return $this->languages;
            }
            public function getTranslatedPostId(int $postId, string $language): ?int
            {
                return $this->translations[$language] ?? null;
            }
            public function switchLanguage(string $language): void
            {
                // no-op in tests
            }
        };
    }
}
