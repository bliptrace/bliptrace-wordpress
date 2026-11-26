<?php

declare(strict_types=1);
use Bliptrace\WordPress\Plugin;

/**
 * Plugin Name: Bliptrace
 * Description: Captures PHP errors and exceptions on your WordPress site and ships them to the Bliptrace ingest API. Thin adapter over the bliptrace/php runtime SDK. Never breaks your site.
 * Version: 1.0.0
 * Author: Developers Hub Sdn Bhd
 * License: MIT
 * Requires PHP: 8.2
 */
if (! defined('ABSPATH') && ! defined('BLIPTRACE_TESTING')) {
    // Allow direct inclusion under tests; otherwise only run inside WordPress.
    if (PHP_SAPI !== 'cli') {
        return;
    }
}

// Load the composer autoloader if it ships with the plugin.
$bliptraceAutoloader = __DIR__.'/vendor/autoload.php';
if (is_file($bliptraceAutoloader)) {
    require_once $bliptraceAutoloader;
}

/**
 * Value of the BLIPTRACE_<name> constant, else the deprecated legacy-prefix
 * constant. Null when neither is defined.
 */
function bliptrace_constant(string $name): mixed
{
    foreach (['BLIPTRACE_', 'G8SCOPE_'] as $prefix) {
        if (defined($prefix.$name)) {
            return constant($prefix.$name);
        }
    }

    return null;
}

/**
 * Resolve Bliptrace options from constants (preferred for secrets) and the
 * wp_options table. Constants win over stored options.
 *
 * @return array<string,mixed>
 */
function bliptrace_resolve_options(): array
{
    $stored = [];
    if (function_exists('get_option')) {
        $option = get_option('bliptrace_options');
        if (is_array($option)) {
            $stored = $option;
        }
    }

    foreach (['key' => 'KEY', 'endpoint' => 'ENDPOINT', 'environment' => 'ENVIRONMENT', 'release' => 'RELEASE'] as $optionKey => $name) {
        $value = bliptrace_constant($name);
        if ($value !== null) {
            $stored[$optionKey] = $value;
        }
    }

    $enabled = bliptrace_constant('ENABLED');
    if ($enabled !== null) {
        $stored['enabled'] = (bool) $enabled;
    }

    // The base path lets the fingerprinter mark in-app frames.
    if (! isset($stored['base_path']) && defined('ABSPATH')) {
        $stored['base_path'] = (string) constant('ABSPATH');
    }

    $stored['sdk_name'] = 'bliptrace-wordpress';
    $stored['sdk_version'] = '1.0.0';

    return $stored;
}

/**
 * Boot the reporter. Hooked on plugins_loaded so options/constants are ready.
 */
function bliptrace_boot(): void
{
    if (! class_exists(Plugin::class)) {
        return; // Autoloader not present — fail silently, never break the site.
    }

    Plugin::boot(bliptrace_resolve_options());
}

if (function_exists('add_action')) {
    add_action('plugins_loaded', 'bliptrace_boot');
}

if (function_exists('register_activation_hook')) {
    register_activation_hook(__FILE__, static function (): void {
        if (function_exists('add_option')) {
            add_option('bliptrace_options', []);
        }
    });
}
