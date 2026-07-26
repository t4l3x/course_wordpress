<?php
/**
 * Environment-driven configuration for the isolated WordPress test database.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

( static function (): void {
	$required_variables = array(
		'WP_CORE_DIR',
		'WP_TESTS_DB_HOST',
		'WP_TESTS_DB_NAME',
		'WP_TESTS_DB_USER',
		'WP_TESTS_DB_PASSWORD',
	);

	foreach ( $required_variables as $variable ) {
		$value = getenv( $variable );

		if ( false === $value || '' === $value ) {
			fwrite( STDERR, sprintf( "Required integration-test environment variable %s is missing.\n", $variable ) );
			exit( 1 );
		}
	}

	define( 'DB_NAME', (string) getenv( 'WP_TESTS_DB_NAME' ) );
	define( 'DB_USER', (string) getenv( 'WP_TESTS_DB_USER' ) );
	define( 'DB_PASSWORD', (string) getenv( 'WP_TESTS_DB_PASSWORD' ) );
	define( 'DB_HOST', (string) getenv( 'WP_TESTS_DB_HOST' ) );
	define( 'DB_CHARSET', 'utf8mb4' );
	define( 'DB_COLLATE', '' );

	define( 'WP_DEBUG', true );
	define( 'WP_TESTS_DOMAIN', 'example.test' );
	define( 'WP_TESTS_EMAIL', 'admin@example.test' );
	define( 'WP_TESTS_TITLE', 'Course Discovery Tests' );
	define( 'WP_PHP_BINARY', 'php' );
	define( 'WPLANG', '' );

	define( 'ABSPATH', rtrim( (string) getenv( 'WP_CORE_DIR' ), '/\\' ) . '/' );

	if ( ! is_readable( ABSPATH . 'wp-settings.php' ) ) {
		fwrite( STDERR, "WP_CORE_DIR does not contain a readable WordPress installation.\n" );
		exit( 1 );
	}

	define( 'AUTH_KEY', 'course-discovery-tests-auth-key' );
	define( 'SECURE_AUTH_KEY', 'course-discovery-tests-secure-auth-key' );
	define( 'LOGGED_IN_KEY', 'course-discovery-tests-logged-in-key' );
	define( 'NONCE_KEY', 'course-discovery-tests-nonce-key' );
	define( 'AUTH_SALT', 'course-discovery-tests-auth-salt' );
	define( 'SECURE_AUTH_SALT', 'course-discovery-tests-secure-auth-salt' );
	define( 'LOGGED_IN_SALT', 'course-discovery-tests-logged-in-salt' );
	define( 'NONCE_SALT', 'course-discovery-tests-nonce-salt' );
} )();
