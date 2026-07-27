<?php
/**
 * Course Discovery setup WP-CLI command.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Setup;

/**
 * Exposes portable landing-page setup through WP-CLI.
 */
final class CourseDiscoverySetupCommand {
	/**
	 * Create the command.
	 *
	 * @param CourseDiscoveryPageInstaller $installer Landing-page installer.
	 */
	public function __construct(
		private CourseDiscoveryPageInstaller $installer
	) {
	}

	/**
	 * Register the command with WP-CLI.
	 */
	public function register(): void {
		\WP_CLI::add_command( 'course-discovery setup', $this );
	}

	/**
	 * Create and publish the page containing `[course_discovery]`.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Replace a matching page's content with one canonical Shortcode block.
	 *
	 * ## EXAMPLES
	 *
	 *     wp course-discovery setup
	 *     wp course-discovery setup --force
	 *
	 * @param array $arguments Positional CLI arguments; none are supported.
	 * @param array $options   Named CLI options.
	 *
	 * @phpstan-param list<string> $arguments
	 * @phpstan-param array<string, mixed> $options
	 *
	 * @when after_wp_load
	 */
	public function __invoke( array $arguments, array $options ): void {
		unset( $arguments );

		$result = $this->installer->ensure_page( array_key_exists( 'force', $options ) );
		$action = $result['created']
			? __( 'created', 'course-discovery' )
			: ( $result['updated'] ? __( 'updated', 'course-discovery' ) : __( 'ready', 'course-discovery' ) );

		\WP_CLI::success(
			sprintf(
				/* translators: 1: setup result, 2: public Course Discovery page URL. */
				__( 'Course Discovery page %1$s: %2$s', 'course-discovery' ),
				$action,
				$result['url']
			)
		);
	}
}
