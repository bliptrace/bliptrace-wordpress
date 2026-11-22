# g8scope for WordPress

A WordPress plugin that captures PHP errors and exceptions on your site and ships
them to the [g8scope](https://scope.g8suite.com) ingest API. It is a **thin
adapter** over the runtime [`bliptrace/php`](../bliptrace-php) SDK — all the envelope
building, scrubbing and transport live there; this plugin only wires the PHP
error handlers and enriches reports with request context.

**It will never break your site.** Every entry point is guarded — if g8scope is
misconfigured, unreachable, or throws internally, your site keeps running.

## How capture works

On `plugins_loaded` the plugin registers two PHP handlers:

- `set_exception_handler` — catches uncaught exceptions and ships them as
  `error` level, then chains to any previously registered handler.
- `register_shutdown_function` — inspects `error_get_last()` and ships genuine
  fatal errors (`E_ERROR`, `E_PARSE`, `E_CORE_ERROR`, `E_COMPILE_ERROR`,
  `E_USER_ERROR`) as `fatal` level. Warnings and notices are ignored.

Each report carries request context (method, URL, headers) with the
`authorization` and `cookie` headers stripped before anything leaves the box.

The **project is resolved server-side** from your ingest key — it is never sent
in the payload, so it cannot be spoofed.

## Installation

### Via Composer (recommended)

From the plugin directory:

```bash
composer install
```

This pulls in `bliptrace/php`. Then place the plugin folder under
`wp-content/plugins/g8scope/` and activate it in **wp-admin → Plugins**.

### Drop-in

If you ship a built copy that already contains `vendor/`, just drop the folder
into `wp-content/plugins/g8scope/` and activate it.

## Configuration

You can configure the plugin via constants (recommended for the ingest key) or
the `g8scope_options` option.

### Constants (in `wp-config.php`)

```php
define('G8SCOPE_KEY', 'your-ingest-key');                 // required
define('G8SCOPE_ENDPOINT', 'https://ingest.scope.g8suite.com'); // optional
define('G8SCOPE_ENVIRONMENT', 'production');              // optional
define('G8SCOPE_RELEASE', '1.4.2');                       // optional
define('G8SCOPE_ENABLED', true);                          // optional
```

Constants always win over stored options. The ingest key is the only required
value — without it the plugin stays dormant.

### Stored options

The plugin reads the `g8scope_options` option (an associative array). Supported
keys map directly to `G8Scope\Php\Config`: `key`, `endpoint`, `environment`,
`release`, `enabled`, `timeout`, `scrub_keys`, `ignore_exceptions`, `base_path`.

## Testing

The test suite depends only on `bliptrace/php` — WordPress core is **not**
required. WordPress functions are referenced behind `function_exists` guards so
the plugin file is include-safe under PHPUnit without WordPress loaded.

```bash
composer install
vendor/bin/pest
```

## Licence

MIT © Developers Hub Sdn Bhd. British spelling is used throughout the docs.
