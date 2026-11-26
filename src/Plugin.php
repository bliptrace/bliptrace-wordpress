<?php

declare(strict_types=1);

namespace Bliptrace\WordPress;

use Throwable;

/**
 * Wires the Bliptrace error handlers into the PHP runtime.
 *
 * Pure PHP — no WordPress dependency. The bootstrap file (bliptrace.php) is what
 * reads WP options and calls boot(); this class only deals with PHP handlers so
 * it is import/autoload-safe under PHPUnit without WordPress loaded.
 */
final class Plugin
{
    private static ?Reporter $reporter = null;

    /**
     * Register the exception + shutdown handlers from the given options.
     * Returns the Reporter (or null when disabled / unconfigured).
     *
     * @param  array<string,mixed>  $options
     */
    public static function boot(array $options): ?Reporter
    {
        try {
            if (empty($options['key']) || (isset($options['enabled']) && ! $options['enabled'])) {
                return null;
            }

            $reporter = new Reporter($options);
            self::$reporter = $reporter;

            $previous = set_exception_handler(static function (Throwable $e) use ($reporter, &$previous): void {
                $reporter->captureThrowable($e);

                if (is_callable($previous)) {
                    $previous($e);
                }
            });

            register_shutdown_function(static function () use ($reporter): void {
                $reporter->captureFatal(error_get_last());
            });

            return $reporter;
        } catch (Throwable) {
            // Never break the site.
            return null;
        }
    }

    public static function reporter(): ?Reporter
    {
        return self::$reporter;
    }
}
