<?php

declare(strict_types=1);
use G8Scope\WordPress\Plugin;

/**
 * Plugin Name: g8scope
 * Description: Captures PHP errors and exceptions on your WordPress site and ships them to the g8scope ingest API. Thin adapter over the g8scope/php runtime SDK. Never breaks your site.
 * Version: 1.0.0
 * Author: Developers Hub Sdn Bhd
 * License: MIT
 * Requires PHP: 8.2
 */
if (! defined('ABSPATH') && ! defined('G8SCOPE_TESTING')) {
    // Allow direct inclusion under tests; otherwise only run inside WordPress.
    if (PHP_SAPI !== 'cli') {
        return;
    }
}

// Load the composer autoloader if it ships with the plugin.
$g8scopeAutoloader = __DIR__.'/vendor/autoload.php';
if (is_file($g8scopeAutoloader)) {
    require_once $g8scopeAutoloader;
}

/**
 * Resolve g8scope options from constants (preferred for secrets) and the
 * wp_options table. Constants win over stored options.
 *
 * @return array<string,mixed>
 */
function g8scope_resolve_options(): array
{
    $stored = [];
    if (function_exists('get_option')) {
        $option = get_option('g8scope_options');
        if (is_array($option)) {
            $stored = $option;
        }
    }

    $constants = [
        'key' => 'G8SCOPE_KEY',
        'endpoint' => 'G8SCOPE_ENDPOINT',
        'environment' => 'G8SCOPE_ENVIRONMENT',
        'release' => 'G8SCOPE_RELEASE',
    ];

    foreach ($constants as $optionKey => $constant) {
        if (defined($constant)) {
            $stored[$optionKey] = constant($constant);
        }
    }

    if (defined('G8SCOPE_ENABLED')) {
        $stored['enabled'] = (bool) constant('G8SCOPE_ENABLED');
    }

    // The base path lets the fingerprinter mark in-app frames.
    if (! isset($stored['base_path']) && defined('ABSPATH')) {
        $stored['base_path'] = (string) constant('ABSPATH');
    }

    $stored['sdk_name'] = 'g8scope-wordpress';
    $stored['sdk_version'] = '1.0.0';

    return $stored;
}

/**
 * Boot the reporter. Hooked on plugins_loaded so options/constants are ready.
 */
function g8scope_boot(): void
{
    if (! class_exists(Plugin::class)) {
        return; // Autoloader not present — fail silently, never break the site.
    }

    Plugin::boot(g8scope_resolve_options());
}

if (function_exists('add_action')) {
    add_action('plugins_loaded', 'g8scope_boot');
}

if (function_exists('register_activation_hook')) {
    register_activation_hook(__FILE__, static function (): void {
        if (function_exists('add_option')) {
            add_option('g8scope_options', []);
        }
    });
}
