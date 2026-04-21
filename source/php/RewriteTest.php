<?php

declare(strict_types=1);

namespace {

    /**
     * Reset the shared rewrite test state.
     *
     * @return void
     */
    function rewriteTestResetState(): void
    {
        $GLOBALS['rewriteTestState'] = array(
            'options'           => array(),
            'postStatuses'      => array(),
            'permalinks'        => array(),
            'translations'      => array(),
            'currentLanguage'   => 'sv',
            'homeUrl'           => 'https://example.com',
            'isAdmin'           => false,
            'isPostTypeArchive' => false,
            'queryVars'         => array(),
            'addedRules'        => array(),
            'permastructs'      => array(),
        );
    }

    rewriteTestResetState();

    /**
     * @param string $hook
     * @param mixed  $callback
     * @param int    $priority
     * @param int    $acceptedArgs
     * @return void
     */
    function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
    }

    /**
     * @param string $hook
     * @param mixed  $callback
     * @param int    $priority
     * @param int    $acceptedArgs
     * @return void
     */
    function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
    }

    /**
     * @param string $name
     * @return mixed
     */
    function get_option(string $name)
    {
        return $GLOBALS['rewriteTestState']['options'][$name] ?? null;
    }

    /**
     * @param int|string $postId
     * @return string
     */
    function get_post_status($postId): string
    {
        return $GLOBALS['rewriteTestState']['postStatuses'][(int) $postId] ?? 'publish';
    }

    /**
     * @param int $postId
     * @return string
     */
    function get_permalink(int $postId): string
    {
        return $GLOBALS['rewriteTestState']['permalinks'][$postId] ?? '';
    }

    /**
     * @return string
     */
    function home_url(): string
    {
        return $GLOBALS['rewriteTestState']['homeUrl'];
    }

    /**
     * @param array $args
     * @param array $defaults
     * @return array
     */
    function wp_parse_args(array $args, array $defaults): array
    {
        return array_merge($defaults, $args);
    }

    /**
     * @return bool
     */
    function is_admin(): bool
    {
        return $GLOBALS['rewriteTestState']['isAdmin'];
    }

    /**
     * @param string $regex
     * @param string $query
     * @param string $position
     * @return void
     */
    function add_rewrite_rule(string $regex, string $query, string $position): void
    {
        $GLOBALS['rewriteTestState']['addedRules'][] = compact('regex', 'query', 'position');
    }

    /**
     * @param string $postType
     * @param string $permastruct
     * @param array  $args
     * @return void
     */
    function add_permastruct(string $postType, string $permastruct, array $args): void
    {
        $GLOBALS['rewriteTestState']['permastructs'][] = compact('postType', 'permastruct', 'args');
    }

    /**
     * @return bool
     */
    function is_post_type_archive(): bool
    {
        return $GLOBALS['rewriteTestState']['isPostTypeArchive'];
    }

    /**
     * @param string $key
     * @return mixed
     */
    function get_query_var(string $key)
    {
        return $GLOBALS['rewriteTestState']['queryVars'][$key] ?? null;
    }

    /**
     * @param int    $postId
     * @param string $language
     * @return int|false
     */
    function pll_get_post(int $postId, string $language)
    {
        return $GLOBALS['rewriteTestState']['translations'][$postId][$language] ?? false;
    }

    /**
     * @param int $postId
     * @return array<string, int>
     */
    function pll_get_post_translations(int $postId): array
    {
        return $GLOBALS['rewriteTestState']['translations'][$postId] ?? array();
    }

    /**
     * @param string $field
     * @return string
     */
    function pll_current_language(string $field = 'slug'): string
    {
        return $GLOBALS['rewriteTestState']['currentLanguage'];
    }
}

namespace WpPageForPostType {

use PHPUnit\Framework\TestCase;

/**
 * @covers \WpPageForPostType\Rewrite
 */
class RewriteTest extends TestCase
{
    protected function setUp(): void
    {
        \rewriteTestResetState();
    }

    /**
     * @return void
     */
    public function testFilterArchiveLinkReturnsTranslatedPagePermalinkForCurrentLanguage(): void
    {
        $GLOBALS['rewriteTestState']['options']['page_for_news']      = 1;
        $GLOBALS['rewriteTestState']['postStatuses'][1]               = 'publish';
        $GLOBALS['rewriteTestState']['postStatuses'][2]               = 'publish';
        $GLOBALS['rewriteTestState']['permalinks'][1]                 = 'https://example.com/nyheter/';
        $GLOBALS['rewriteTestState']['permalinks'][2]                 = 'https://example.com/en/news/';
        $GLOBALS['rewriteTestState']['translations'][1]['en']         = 2;
        $GLOBALS['rewriteTestState']['currentLanguage']               = 'en';

        $rewrite = new Rewrite();

        $localizedArchiveLink = $rewrite->filterArchiveLink('https://example.com/en/nyheter/', 'news');

        self::assertSame('https://example.com/en/news/', $localizedArchiveLink);
    }

    /**
     * @return void
     */
    public function testFilterPolylangArchiveUrlReturnsTranslatedPagePermalink(): void
    {
        $GLOBALS['rewriteTestState']['options']['page_for_news']      = 1;
        $GLOBALS['rewriteTestState']['postStatuses'][1]               = 'publish';
        $GLOBALS['rewriteTestState']['postStatuses'][2]               = 'publish';
        $GLOBALS['rewriteTestState']['permalinks'][1]                 = 'https://example.com/nyheter/';
        $GLOBALS['rewriteTestState']['permalinks'][2]                 = 'https://example.com/en/news/';
        $GLOBALS['rewriteTestState']['translations'][1]['en']         = 2;
        $GLOBALS['rewriteTestState']['isPostTypeArchive']             = true;
        $GLOBALS['rewriteTestState']['queryVars']['post_type']        = 'news';

        $rewrite = new Rewrite();

        $localizedArchiveLink = $rewrite->filterPolylangArchiveUrl(
            'https://example.com/en/nyheter/',
            (object) array('slug' => 'en'),
        );

        self::assertSame('https://example.com/en/news/', $localizedArchiveLink);
    }

    /**
     * @return void
     */
    public function testRebuildRewriteRulesRegistersLocalizedArchiveRules(): void
    {
        global $wp_rewrite;

        $GLOBALS['rewriteTestState']['options']['page_for_news']          = 1;
        $GLOBALS['rewriteTestState']['options']['permalink_structure']    = '/%postname%/';
        $GLOBALS['rewriteTestState']['permalinks'][1]                     = 'https://example.com/nyheter/';
        $GLOBALS['rewriteTestState']['permalinks'][2]                     = 'https://example.com/en/news/';
        $GLOBALS['rewriteTestState']['translations'][1]                   = array(
            'sv' => 1,
            'en' => 2,
        );
        $wp_rewrite = (object) array(
            'front'           => '/',
            'root'            => '',
            'feeds'           => array('feed', 'rdf'),
            'pagination_base' => 'page',
        );

        $rewrite = new Rewrite();
        $args    = (object) array(
            'has_archive' => 'nyheter',
            'rewrite'     => array(
                'slug'       => 'nyheter',
                'with_front' => false,
                'feeds'      => true,
                'pages'      => true,
            ),
        );

        $rewrite->rebuildRewriteRules('news', $args, 'news');

        $registeredRules = array_column($GLOBALS['rewriteTestState']['addedRules'], 'regex');

        self::assertContains('nyheter/?$', $registeredRules);
        self::assertContains('en/news/?$', $registeredRules);
    }
}
}
