<?php
/**
 * Bootstrap isolated unit tests without loading WordPress.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

( static function (): void {
	$autoload_file = dirname( __DIR__, 2 ) . '/vendor/autoload.php';

	if ( ! is_readable( $autoload_file ) ) {
		fwrite( STDERR, "Composer dependencies are missing. Run composer install before unit tests.\n" );
		exit( 1 );
	}

	require_once $autoload_file;
} )();
