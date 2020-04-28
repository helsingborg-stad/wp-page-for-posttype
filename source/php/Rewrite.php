<?php

namespace WpPageForPostType;

class Rewrite
{

    private $generatorId = "?generator=wp-pfp"; 

    public function __construct()
    {
        //Redo rewriterules whenever options page is visited (before save, after save)
        add_action(__NAMESPACE__ . '/renderOptionsPage', array($this, 'updateRewrite'), 10, 2);

        //Change slug (before cpt registration)
        add_filter('register_post_type_args', array($this, 'filterPostTypeRegistration'), 500, 2); 

        //Reorder rewrite rules
        add_filter('rewrite_rules_array', array($this, 'reorderRewriteRules')); 

    }

    /**
     * Filter base rewrites for posttypes
     * @param  array  $args The arguments provided in posttype registration
     * @param  string $name The name of the posttype
     * @return array  $args Contains the new filtered array with modified rewrites
     */

    public function filterPostTypeRegistration($args, $name) {

        $pageUrl = get_option('page_for_' . $name . '_url');

        if(!empty($pageUrl)) {
            $args['rewrite']['slug'] = str_replace(home_url(), "", rtrim($pageUrl, "/")); 
        }

        return $args; 
    }

    /**
     * Make rewrite rules important
     * @param  array $rewriteRules
     * @return array
     */

    public function reorderRewriteRules($rewriteRules) {

        //Sanity check
        if(!is_array($rewriteRules) ||empty($rewriteRules)) {
            return $rewriteRules; 
        }

        //Stack all rewrites matching generator
        $stack = array(); 
        foreach($rewriteRules as $ruleKey => $rule) {
            if(strpos($rule, $this->generatorId) !== false) {
                $stack[$ruleKey] = str_replace($this->generatorId, "", $rule); 
                unset($rewriteRules[$ruleKey]);
            }

            if(strpos($ruleKey, $this->generatorId) !== false) {
                $stack[str_replace($this->generatorId, "", $ruleKey)] = $rule; 
                unset($rewriteRules[$ruleKey]);
            } 
        }

        //Prepend to array of rewrite rules
        $rewriteRules = array_merge($stack, $rewriteRules); 

        //Return reordered array
        return $rewriteRules; 
    }

    /**
     * Updates the rewrite rules for the posttype
     * @param  string $postType
     * @param  array $args
     * @return void
     */
    public function updateRewrite(string $postType, $args)
    {
        global $wp_post_types, $wp_rewrite;

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

        // Get original rewrite rule
        $args->rewrite = (array) $args->rewrite;
        $oldRewrite = isset($args->rewrite['slug']) ? $args->rewrite['slug'] : $postType;
        \WpPageForPostType\Settings::$originalSlugs[$postType] = $oldRewrite;

        // Get the new slug
        $newSlug = $this->getPageSlug($pageForPostType);

        $args->rewrite     = wp_parse_args(array('slug' => $newSlug), $args->rewrite);
        $args->has_archive = $newSlug;

        // Update global
        $wp_post_types[$postType] = $args;

        // Rebuild rewrite rules
        $this->rebuildRewriteRules($postType, $args, $newSlug);

        //Flush
        $this->flushRewriteRules(); 
    }

    /**
     * Rebuild rewrite rules
     * @param  string $postType
     * @param  array $args
     * @return bool
     */
    public function rebuildRewriteRules($postType, $args, $newSlug)
    {
        global $wp_post_types, $wp_rewrite;

        if (!is_admin() && empty(get_option('permalink_structure'))) {
            return;
        }

        if ($args->has_archive) {
            $archiveSlug = $args->has_archive === true ? $args->rewrite['slug'] : $args->has_archive;

            // Maybe append blogfront
            if ($args->rewrite['with_front']) {
                $archiveSlug = substr($wp_rewrite->front, 1) . $archiveSlug;
            } else {
                $archiveSlug = $wp_rewrite->root . $archiveSlug;
            }

            // Add rewrite rule for the archive
            add_rewrite_rule("{$archiveSlug}/?$", "index.php?post_type=$postType$this->generatorId", 'top');

            // Add rewrite rules for feeds
            if ($args->rewrite['feeds'] && $wp_rewrite->feeds) {
                $feeds = '(' . trim(implode('|', $wp_rewrite->feeds)) . ')';

                add_rewrite_rule(
                    "{$archiveSlug}/feed/$feeds/?$",
                    "index.php?post_type=$postType$this->generatorId" . '&feed=$matches[1]',
                    'top'
                );

                add_rewrite_rule(
                    "{$archiveSlug}/$feeds/?$",
                    "index.php?post_type=$postType$this->generatorId" . '&feed=$matches[1]',
                    'top'
                );
                
            }
 
            // Add rewrite rules for pagination
            if ($args->rewrite['pages']) {
                add_rewrite_rule(
                    "{$archiveSlug}/{$wp_rewrite->pagination_base}/([0-9]{1,})/?$",
                    "index.php?post_type=$postType$this->generatorId" . '&paged=$matches[1]',
                    'top'
                );
            } 
        }

        $permastructArgs = $args->rewrite;
        $permastructArgs['feed'] = $permastructArgs['feeds'];

        // Support plugins that enable 'permastruct' option
        if (isset($args->rewrite['permastruct'])) {
            $permastruct = str_replace($oldRewrite, $slug, $args->rewrite['permastruct']);
        } else {
            $permastruct = "{$args->rewrite['slug']}/%$postType%";
        }

        add_permastruct($postType, $permastruct . $this->generatorId, $permastructArgs);

        return true;
    }

    /**
     * Get permalink (without home url) for a specific post
     * @param  int    $postId
     * @return string
     */
    public function getPageSlug(int $postId)
    {
        $slug = get_permalink($postId);
        $slug = str_replace(home_url(), '', $slug);
        $slug = trim($slug, '/');
        
        return $slug;
    }

    /**
     * Keep rewrites up to date
     * @return void
     */
    public function flushRewriteRules()
    {
        flush_rewrite_rules(false); 
    }

}
