<?php
/**
 * Public Course filter options.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend;

use DateTimeImmutable;
use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Application\Search\Condition\CategoryCondition;
use OxfordInternational\CourseDiscovery\Application\Search\Condition\LocationCondition;
use OxfordInternational\CourseDiscovery\Application\Search\Condition\ProviderCondition;
use OxfordInternational\CourseDiscovery\Application\Search\Condition\StartDateCondition;
use OxfordInternational\CourseDiscovery\Domain\Course\StartDate;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseCategoryTaxonomy;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMeta;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CoursePostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\LocationTaxonomy;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\ProviderPostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressCourseSearchExtensions;
use WP_Error;
use WP_Term;

/**
 * Loads the finite option lists needed by the server-rendered filter form.
 */
final class CourseFilterOptions {
	/**
	 * Load all core Course filter options.
	 *
	 * @return array{
	 *     providers: list<CourseFilterOption>,
	 *     locations: list<CourseFilterOption>,
	 *     start_dates: list<CourseFilterOption>,
	 *     categories: list<CourseFilterOption>
	 * }
	 */
	public function all(): array {
		return array(
			'providers'   => $this->providers(),
			'locations'   => $this->locations(),
			'start_dates' => $this->start_dates(),
			'categories'  => $this->categories(),
		);
	}

	/**
	 * Load published Provider options.
	 *
	 * @return list<CourseFilterOption>
	 */
	public function providers(): array {
		$posts   = get_posts(
			array(
				'post_type'        => ProviderPostType::POST_TYPE,
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);
		$options = array();

		foreach ( $posts as $post ) {
			$title     = get_the_title( $post );
			$options[] = new CourseFilterOption(
				(string) $post->ID,
				'' === $title ? __( 'Unnamed Provider', 'course-discovery' ) : $title
			);
		}

		return $this->extension_options( ProviderCondition::KEY, $options );
	}

	/**
	 * Load Location taxonomy options.
	 *
	 * @return list<CourseFilterOption>
	 */
	public function locations(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => LocationTaxonomy::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( $terms instanceof WP_Error ) {
			return $this->extension_options( LocationCondition::KEY, array() );
		}

		$options = array_map(
			static fn ( WP_Term $term ): CourseFilterOption => new CourseFilterOption(
				(string) $term->term_id,
				$term->name
			),
			array_values( $terms )
		);

		return $this->extension_options( LocationCondition::KEY, $options );
	}

	/**
	 * Load distinct canonical start months without querying each Course.
	 *
	 * @return list<CourseFilterOption>
	 */
	public function start_dates(): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- A distinct metadata projection avoids loading every Course.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- Option hooks may add caching when catalogue size justifies it.
		$values = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_value
				FROM {$wpdb->postmeta} AS pm
				INNER JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s
				AND p.post_type = %s
				AND p.post_status = %s
				ORDER BY pm.meta_value ASC",
				CourseMeta::START_DATE_KEY,
				CoursePostType::POST_TYPE,
				'publish'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		$options = array();

		foreach ( $values as $value ) {
			if ( ! is_string( $value ) ) {
				continue;
			}

			try {
				$date = new StartDate( $value );
			} catch ( InvalidArgumentException ) {
				continue;
			}

			$options[] = new CourseFilterOption( $date->value(), self::start_date_label( $date ) );
		}

		return $this->extension_options( StartDateCondition::KEY, $options );
	}

	/**
	 * Load Course Categories in parent-before-child order.
	 *
	 * @return list<CourseFilterOption>
	 */
	public function categories(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => CourseCategoryTaxonomy::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( $terms instanceof WP_Error ) {
			return $this->extension_options( CategoryCondition::KEY, array() );
		}

		/**
		 * Category terms grouped by parent ID.
		 *
		 * @var array<int, list<WP_Term>> $children
		 */
		$children = array();

		foreach ( $terms as $term ) {
			$children[ $term->parent ][] = $term;
		}

		$options = array();
		$append  = static function ( int $parent_id, int $depth ) use ( &$append, &$options, $children ): void {
			foreach ( $children[ $parent_id ] ?? array() as $term ) {
				$options[] = new CourseFilterOption( (string) $term->term_id, $term->name, $depth );
				$append( $term->term_id, $depth + 1 );
			}
		};

		$append( 0, 0 );

		return $this->extension_options( CategoryCondition::KEY, $options );
	}

	/**
	 * Format a canonical start month for public display.
	 *
	 * @param StartDate $date Canonical start month.
	 */
	public static function start_date_label( StartDate $date ): string {
		$value     = DateTimeImmutable::createFromFormat( '!Y-m', $date->value() );
		$timestamp = false === $value ? false : $value->getTimestamp();

		if ( false === $timestamp ) {
			return $date->value();
		}

		$label = wp_date( 'F Y', $timestamp );

		return false === $label ? $date->value() : $label;
	}

	/**
	 * Allow extensions to modify one typed option list.
	 *
	 * @param string $key     Core filter key.
	 * @param array  $options Core options.
	 *
	 * @return list<CourseFilterOption>
	 *
	 * @phpstan-param list<CourseFilterOption> $options
	 */
	private function extension_options( string $key, array $options ): array {
		$hook = WordPressCourseSearchExtensions::FILTER_OPTIONS_PREFIX . $key;

		/**
		 * Untrusted extension output.
		 *
		 * @var mixed $filtered
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- The declared prefix and validated filter key form the public hook name.
		$filtered = apply_filters( $hook, $options );

		if ( ! is_array( $filtered ) ) {
			$this->report_invalid_extension_options();

			return $options;
		}

		foreach ( $filtered as $option ) {
			if ( ! $option instanceof CourseFilterOption ) {
				$this->report_invalid_extension_options();

				return $options;
			}
		}

		return array_values( $filtered );
	}

	/**
	 * Report invalid filter-option extension output.
	 */
	private function report_invalid_extension_options(): void {
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Diagnostic arguments are passed to _doing_it_wrong(), not rendered here.
		_doing_it_wrong(
			__METHOD__,
			__( 'Course filter option hooks must return CourseFilterOption objects.', 'course-discovery' ),
			COURSE_DISCOVERY_VERSION
		);
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
