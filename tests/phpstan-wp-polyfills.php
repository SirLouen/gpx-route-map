<?php
/**
 * The PHP 8 functions WordPress defines on PHP 7.4, in wp-includes/compat.php.
 *
 * PHPStan analyses the plugin as PHP 7.4, whose own functions these are not,
 * but WordPress 6.8, the plugin's minimum, always provides them. Listed in
 * scanFiles, so PHPStan reads this file and never runs it: on PHP 8 these
 * declarations would clash with PHP's own functions.
 *
 * @package GpxRouteMap
 */

/**
 * Since WordPress 5.9.
 *
 * @param string $haystack String to search in.
 * @param string $needle   String to search for.
 */
function str_contains( string $haystack, string $needle ): bool {}

/**
 * Since WordPress 5.9.
 *
 * @param string $haystack String to search in.
 * @param string $needle   String to search for.
 */
function str_starts_with( string $haystack, string $needle ): bool {}

/**
 * Since WordPress 5.9.
 *
 * @param string $haystack String to search in.
 * @param string $needle   String to search for.
 */
function str_ends_with( string $haystack, string $needle ): bool {}

/**
 * Since WordPress 6.5.
 *
 * @param array<mixed> $arr Array to check.
 * @phpstan-assert-if-true list<mixed> $arr
 */
function array_is_list( array $arr ): bool {}
