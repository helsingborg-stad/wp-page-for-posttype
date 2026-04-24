<?php

declare(strict_types=1);

/**
 * Minimal WordPress/Polylang function stubs for unit tests.
 *
 * The stubs consult `$GLOBALS['wp_stub_*']` arrays so individual tests can
 * configure the return values they need.
 */

if (!function_exists('get_permalink')) {
    function get_permalink($postId)
    {
        $permalinks = $GLOBALS['wp_stub_permalinks'] ?? array();

        return $permalinks[$postId] ?? false;
    }
}

if (!function_exists('home_url')) {
    function home_url()
    {
        return $GLOBALS['wp_stub_home_url'] ?? 'http://example.com';
    }
}

if (!function_exists('add_rewrite_rule')) {
    function add_rewrite_rule($regex, $query, $priority = 'bottom')
    {
        $GLOBALS['wp_stub_rewrite_rules'][] = array(
            'regex' => $regex,
            'query' => $query,
            'priority' => $priority,
        );
    }
}

if (!function_exists('add_permastruct')) {
    function add_permastruct($name, $struct, $args = array())
    {
        $GLOBALS['wp_stub_permastructs'][$name] = array(
            'struct' => $struct,
            'args' => $args,
        );
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = array())
    {
        if (is_object($args)) {
            $args = get_object_vars($args);
        }

        if (!is_array($args)) {
            $args = array();
        }

        return array_merge($defaults, $args);
    }
}
