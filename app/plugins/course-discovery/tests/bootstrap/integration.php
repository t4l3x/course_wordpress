<?php
/**
 * Bootstrap tests that run inside the WordPress PHPUnit environment.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

( static function (): void {
	$plugin_root   = dirname( __DIR__, 2 );
	$autoload_file = $plugin_root . '/vendor/autoload.php';

	if ( ! is_readable( $autoload_file ) ) {
		fwrite( STDERR, "Composer dependencies are missing. Run composer install before integration tests.\n" );
		exit( 1 );
	}

	require_once $autoload_file;

	$tests_directory = getenv( 'WP_TESTS_DIR' );
	if ( false === $tests_directory || '' === $tests_directory ) {
		$tests_directory = getenv( 'WP_PHPUNIT__DIR' );
	}
	if ( false === $tests_directory || '' === $tests_directory ) {
		$tests_directory = $plugin_root . '/vendor/wp-phpunit/wp-phpunit';
	}

	$functions_file = rtrim( $tests_directory, '/\\' ) . '/includes/functions.php';
	$bootstrap_file = rtrim( $tests_directory, '/\\' ) . '/includes/bootstrap.php';
	$config_file    = $plugin_root . '/tests/wp-tests-config.php';

	if ( ! is_readable( $functions_file ) || ! is_readable( $bootstrap_file ) ) {
		fwrite( STDERR, "The WordPress PHPUnit environment is missing. Run composer install and verify WP_TESTS_DIR.\n" );
		exit( 1 );
	}

	if ( ! is_readable( $config_file ) ) {
		fwrite( STDERR, "The WordPress test configuration is missing: tests/wp-tests-config.php.\n" );
		exit( 1 );
	}

	$configured_test_file = getenv( 'WP_PHPUNIT__TESTS_CONFIG' );
	if ( false === $configured_test_file || '' === $configured_test_file ) {
		putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . $config_file );
	}

	if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
		define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $plugin_root . '/vendor/yoast/phpunit-polyfills' );
	}

	require_once $functions_file;

	tests_add_filter(
		'muplugins_loaded',
		static function () use ( $plugin_root ): void {
			require $plugin_root . '/course-discovery.php';
		}
	);

	require $bootstrap_file;
} )();
