<?php
/**
 * PHPStan-only WP-CLI declarations.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

/**
 * Minimal static WP-CLI facade used by the development command.
 */
final class WP_CLI {
	/**
	 * @param callable $callable Command callback.
	 */
	public static function add_command( string $name, callable $callable ): void {
		unset( $name, $callable );
	}

	public static function log( string $message ): void {
		unset( $message );
	}

	public static function success( string $message ): void {
		unset( $message );
	}

	public static function error( string $message ): never {
		throw new RuntimeException( $message );
	}
}
