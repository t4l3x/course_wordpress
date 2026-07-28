<?php
/**
 * Public Course result preparation.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend;

use OxfordInternational\CourseDiscovery\Application\Search\CourseSearchResult;
use OxfordInternational\CourseDiscovery\Domain\Course\CourseId;
use OxfordInternational\CourseDiscovery\Domain\Course\Price;
use OxfordInternational\CourseDiscovery\Domain\Course\StartDate;
use OxfordInternational\CourseDiscovery\Domain\Instructor\InstructorId;
use OxfordInternational\CourseDiscovery\Domain\Provider\ProviderId;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseCategoryTaxonomy;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMetadataStore;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CoursePostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\InstructorPostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\LocationTaxonomy;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\ProviderPostType;
use UnexpectedValueException;
use WP_Error;
use WP_Post;
use WP_Term;

/**
 * Hydrates ID-only search results into escaped-at-template display values.
 */
final class CourseResultPresenter {
	/**
	 * Create the Course result presenter.
	 *
	 * @param CourseMetadataStore  $metadata_store  Course metadata read boundary.
	 * @param CoursePriceFormatter $price_formatter Exact Course price formatter.
	 */
	public function __construct(
		private CourseMetadataStore $metadata_store,
		private CoursePriceFormatter $price_formatter
	) {
	}

	/**
	 * Prepare Course cards while priming WordPress caches in batches.
	 *
	 * @param CourseSearchResult $result ID-only Course search result.
	 *
	 * @return list<array{
	 *     id: int,
	 *     name: string,
	 *     short_description: string,
	 *     price: ?string,
	 *     price_currency: ?string,
	 *     providers: list<string>,
	 *     locations: list<string>,
	 *     instructors: list<string>,
	 *     start_dates: list<string>,
	 *     categories: list<string>
	 * }>
	 */
	public function prepare( CourseSearchResult $result ): array {
		$course_ids = array_map(
			static fn ( CourseId $course_id ): int => $course_id->value(),
			$result->course_ids()
		);

		if ( array() === $course_ids ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'        => CoursePostType::POST_TYPE,
				'post_status'      => 'publish',
				'post__in'         => $course_ids,
				'posts_per_page'   => count( $course_ids ),
				'orderby'          => 'post__in',
				'suppress_filters' => false,
			)
		);

		/**
		 * Course data indexed by Course ID.
		 *
		 * @var array<int, array{
		 *     post: WP_Post,
		 *     price: ?Price,
		 *     provider_ids: list<int>,
		 *     instructor_ids: list<int>,
		 *     start_dates: list<StartDate>
		 * }>
		 */
		$courses          = array();
		$relationship_ids = array();

		foreach ( $posts as $post ) {
			$course_id = new CourseId( $post->ID );

			try {
				$price = $this->metadata_store->price( $course_id );
			} catch ( UnexpectedValueException ) {
				$price = null;
			}

			try {
				$provider_ids = array_map(
					static fn ( ProviderId $provider_id ): int => $provider_id->value(),
					$this->metadata_store->providers( $course_id )
				);
			} catch ( UnexpectedValueException ) {
				$provider_ids = array();
			}

			try {
				$instructor_ids = array_map(
					static fn ( InstructorId $instructor_id ): int => $instructor_id->value(),
					$this->metadata_store->instructors( $course_id )
				);
			} catch ( UnexpectedValueException ) {
				$instructor_ids = array();
			}

			try {
				$start_dates = $this->metadata_store->start_dates( $course_id );
			} catch ( UnexpectedValueException ) {
				$start_dates = array();
			}

			$courses[ $post->ID ] = array(
				'post'           => $post,
				'price'          => $price,
				'provider_ids'   => $provider_ids,
				'instructor_ids' => $instructor_ids,
				'start_dates'    => $start_dates,
			);
			$relationship_ids     = array_merge( $relationship_ids, $provider_ids, $instructor_ids );
		}

		$related_posts = $this->related_posts( array_values( array_unique( $relationship_ids ) ) );
		$cards         = array();

		foreach ( $course_ids as $course_id ) {
			if ( ! isset( $courses[ $course_id ] ) ) {
				continue;
			}

			$course       = $courses[ $course_id ];
			$post         = $course['post'];
			$title        = get_the_title( $post );
			$provider_ids = array_values(
				array_filter(
					$course['provider_ids'],
					static fn ( int $provider_id ): bool => isset( $related_posts[ $provider_id ] )
						&& ProviderPostType::POST_TYPE === $related_posts[ $provider_id ]->post_type
				)
			);
			$cards[]      = array(
				'id'                => $post->ID,
				'name'              => '' === $title
					? __( 'Untitled Course', 'course-discovery' )
					: $title,
				'short_description' => trim( wp_strip_all_tags( $post->post_excerpt, true ) ),
				'price'             => null === $course['price']
					? null
					: $this->price_formatter->format( $course['price'] ),
				'price_currency'    => $course['price']?->currency()->value,
				'providers'         => $this->related_names(
					$provider_ids,
					ProviderPostType::POST_TYPE,
					$related_posts
				),
				'locations'         => $this->provider_location_names( $provider_ids ),
				'instructors'       => $this->related_names(
					$course['instructor_ids'],
					InstructorPostType::POST_TYPE,
					$related_posts
				),
				'start_dates'       => array_map(
					static fn ( StartDate $date ): string => CourseFilterOptions::start_date_label( $date ),
					$course['start_dates']
				),
				'categories'        => $this->term_names( $post->ID, CourseCategoryTaxonomy::TAXONOMY ),
			);
		}

		return $cards;
	}

	/**
	 * Load published Provider and Instructor posts in one cache-priming query.
	 *
	 * @param array $relationship_ids Related post IDs.
	 *
	 * @return array<int, WP_Post>
	 *
	 * @phpstan-param list<int> $relationship_ids
	 */
	private function related_posts( array $relationship_ids ): array {
		if ( array() === $relationship_ids ) {
			return array();
		}

		$posts   = get_posts(
			array(
				'post_type'        => array( ProviderPostType::POST_TYPE, InstructorPostType::POST_TYPE ),
				'post_status'      => 'publish',
				'post__in'         => $relationship_ids,
				'posts_per_page'   => count( $relationship_ids ),
				'orderby'          => 'post__in',
				'suppress_filters' => false,
			)
		);
		$indexed = array();

		foreach ( $posts as $post ) {
			$indexed[ $post->ID ] = $post;
		}

		return $indexed;
	}

	/**
	 * Resolve related post titles in relationship order.
	 *
	 * @param array               $identifiers Related post IDs.
	 * @param string              $post_type   Expected related post type.
	 * @param array<int, WP_Post> $posts       Related posts indexed by ID.
	 *
	 * @return list<string>
	 *
	 * @phpstan-param list<int> $identifiers
	 */
	private function related_names( array $identifiers, string $post_type, array $posts ): array {
		$names = array();

		foreach ( $identifiers as $identifier ) {
			if ( ! isset( $posts[ $identifier ] ) || $post_type !== $posts[ $identifier ]->post_type ) {
				continue;
			}

			$title = get_the_title( $posts[ $identifier ] );

			if ( '' !== $title ) {
				$names[] = $title;
			}
		}

		return $names;
	}

	/**
	 * Derive unique Location names from published Providers.
	 *
	 * @param array $provider_ids Published Provider IDs.
	 *
	 * @return list<string>
	 *
	 * @phpstan-param list<int> $provider_ids
	 */
	private function provider_location_names( array $provider_ids ): array {
		$locations = array();

		foreach ( $provider_ids as $provider_id ) {
			foreach ( $this->term_names( $provider_id, LocationTaxonomy::TAXONOMY ) as $location ) {
				$locations[ $location ] = $location;
			}
		}

		natcasesort( $locations );

		return array_values( $locations );
	}

	/**
	 * Return sorted term names for one object and taxonomy.
	 *
	 * @param int    $object_id Object ID.
	 * @param string $taxonomy  Registered taxonomy.
	 *
	 * @return list<string>
	 */
	private function term_names( int $object_id, string $taxonomy ): array {
		$terms = get_the_terms( $object_id, $taxonomy );

		if ( false === $terms || $terms instanceof WP_Error ) {
			return array();
		}

		$names = array_map(
			static fn ( WP_Term $term ): string => $term->name,
			array_values( $terms )
		);
		natcasesort( $names );

		return array_values( $names );
	}
}
