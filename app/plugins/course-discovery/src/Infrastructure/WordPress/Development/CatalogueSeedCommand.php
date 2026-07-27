<?php
/**
 * Development catalogue WP-CLI command.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Development;

use WP_CLI;

/**
 * Exposes deterministic demo catalogue generation to local WP-CLI users.
 */
final readonly class CatalogueSeedCommand {
	/**
	 * Create the command.
	 *
	 * @param CatalogueSeeder $seeder Demo catalogue generator.
	 */
	public function __construct(
		private CatalogueSeeder $seeder
	) {
	}

	/**
	 * Register the command with WP-CLI.
	 */
	public function register(): void {
		WP_CLI::add_command( 'course-discovery seed', $this );
	}

	/**
	 * Generate realistic local Course Discovery catalogue data.
	 *
	 * ## OPTIONS
	 *
	 * [--count=<count>]
	 * : Number of Courses to generate, from 30 to 50. Default: 40.
	 *
	 * [--reset]
	 * : Delete only previously seeded demo data before regenerating it.
	 *
	 * ## EXAMPLES
	 *
	 *     wp course-discovery seed
	 *     wp course-discovery seed --reset --count=50
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

		if ( ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
			WP_CLI::error(
				__( 'The Course Discovery demo seeder is available only in local or development environments.', 'course-discovery' )
			);
		}

		$course_count = $this->course_count( $options['count'] ?? '40' );

		if ( array_key_exists( 'reset', $options ) ) {
			$deleted = $this->seeder->reset();
			WP_CLI::log(
				sprintf(
					/* translators: 1: deleted post count, 2: deleted term count. */
					__( 'Removed %1$d seeded posts and %2$d seeded terms.', 'course-discovery' ),
					$deleted['posts'],
					$deleted['terms']
				)
			);
		}

		$created = $this->seeder->seed( $course_count );

		WP_CLI::success(
			sprintf(
				/* translators: 1: Course count, 2: Provider count, 3: Instructor count, 4: Location count, 5: Category count. */
				__( 'Demo catalogue ready: %1$d Courses, %2$d Providers, %3$d Instructors, %4$d Locations, and %5$d Categories.', 'course-discovery' ),
				$created['courses'],
				$created['providers'],
				$created['instructors'],
				$created['locations'],
				$created['categories']
			)
		);
	}

	/**
	 * Parse the bounded Course count option.
	 *
	 * @param mixed $value Raw CLI option.
	 */
	private function course_count( mixed $value ): int {
		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			WP_CLI::error( __( 'The --count option must be an integer from 30 to 50.', 'course-discovery' ) );
		}

		$count = filter_var(
			$value,
			FILTER_VALIDATE_INT,
			array(
				'options' => array(
					'max_range' => 50,
					'min_range' => 30,
				),
			)
		);

		if ( false === $count ) {
			WP_CLI::error( __( 'The --count option must be an integer from 30 to 50.', 'course-discovery' ) );
		}

		return $count;
	}
}
