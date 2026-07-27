<?php
/**
 * Development catalogue data generator.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Development;

use InvalidArgumentException;
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
use RuntimeException;
use WP_Error;
use WP_Post;
use WP_Term;

/**
 * Creates a deterministic catalogue through WordPress and the supported metadata store.
 */
final class CatalogueSeeder {
	public const string MARKER_META_KEY = '_course_discovery_demo_seed';
	public const string SEED_ID         = 'course-discovery-demo-v1';

	private const int MIN_COURSES = 30;
	private const int MAX_COURSES = 50;

	/**
	 * Location names indexed by deterministic slug.
	 *
	 * @var array<string, string>
	 */
	private const array LOCATIONS = array(
		'london'     => 'London, United Kingdom',
		'oxford'     => 'Oxford, United Kingdom',
		'manchester' => 'Manchester, United Kingdom',
		'edinburgh'  => 'Edinburgh, United Kingdom',
		'toronto'    => 'Toronto, Canada',
		'vancouver'  => 'Vancouver, Canada',
		'sydney'     => 'Sydney, Australia',
		'online'     => 'Online',
	);

	/**
	 * Provider fixtures and their Location slugs.
	 *
	 * @var list<array{slug: string, title: string, locations: list<string>}>
	 */
	private const array PROVIDERS = array(
		array(
			'slug'      => 'oxford-global-learning',
			'title'     => 'Oxford Global Learning',
			'locations' => array( 'oxford', 'online' ),
		),
		array(
			'slug'      => 'london-institute-of-arts',
			'title'     => 'London Institute of Arts',
			'locations' => array( 'london' ),
		),
		array(
			'slug'      => 'northbridge-business-school',
			'title'     => 'Northbridge Business School',
			'locations' => array( 'manchester', 'online' ),
		),
		array(
			'slug'      => 'edinburgh-science-academy',
			'title'     => 'Edinburgh Science Academy',
			'locations' => array( 'edinburgh', 'online' ),
		),
		array(
			'slug'      => 'maple-leaf-college',
			'title'     => 'Maple Leaf College',
			'locations' => array( 'toronto', 'vancouver' ),
		),
		array(
			'slug'      => 'pacific-technology-university',
			'title'     => 'Pacific Technology University',
			'locations' => array( 'sydney', 'online' ),
		),
		array(
			'slug'      => 'international-health-institute',
			'title'     => 'International Health Institute',
			'locations' => array( 'london', 'toronto' ),
		),
		array(
			'slug'      => 'open-learning-network',
			'title'     => 'Open Learning Network',
			'locations' => array( 'online' ),
		),
	);

	/**
	 * Instructor names indexed by deterministic slug.
	 *
	 * @var array<string, string>
	 */
	private const array INSTRUCTORS = array(
		'amelia-hart'     => 'Dr Amelia Hart',
		'daniel-okafor'   => 'Professor Daniel Okafor',
		'elena-rodriguez' => 'Elena Rodriguez',
		'farah-khan'      => 'Dr Farah Khan',
		'grace-chen'      => 'Grace Chen',
		'henry-morgan'    => 'Henry Morgan',
		'ines-silva'      => 'Ines Silva',
		'james-bennett'   => 'Dr James Bennett',
		'leila-hassan'    => 'Leila Hassan',
		'marcus-lee'      => 'Marcus Lee',
		'nadia-patel'     => 'Nadia Patel',
		'oliver-wright'   => 'Oliver Wright',
	);

	/**
	 * Hierarchical Course Category fixtures.
	 *
	 * @var array<string, array{name: string, children: array<string, string>}>
	 */
	private const array CATEGORIES = array(
		'business'               => array(
			'name'     => 'Business',
			'children' => array(
				'management'       => 'Management',
				'marketing'        => 'Marketing',
				'entrepreneurship' => 'Entrepreneurship',
			),
		),
		'technology'             => array(
			'name'     => 'Technology',
			'children' => array(
				'software-development' => 'Software Development',
				'data-and-ai'          => 'Data and AI',
				'cloud-computing'      => 'Cloud Computing',
			),
		),
		'creative-arts'          => array(
			'name'     => 'Creative Arts',
			'children' => array(
				'design'        => 'Design',
				'digital-media' => 'Digital Media',
				'writing'       => 'Writing',
			),
		),
		'health-and-education'   => array(
			'name'     => 'Health and Education',
			'children' => array(
				'public-health' => 'Public Health',
				'education'     => 'Education',
				'psychology'    => 'Psychology',
			),
		),
		'science-sustainability' => array(
			'name'     => 'Science and Sustainability',
			'children' => array(
				'environmental-science' => 'Environmental Science',
				'life-sciences'         => 'Life Sciences',
				'engineering'           => 'Engineering',
			),
		),
	);

	/**
	 * Course subject fixtures and matching child Category slugs.
	 *
	 * @var list<array{title: string, description: string, categories: list<string>}>
	 */
	private const array SUBJECTS = array(
		array(
			'title'       => 'Strategic Product Management',
			'description' => 'Shape product strategy, validate user needs, and guide ideas from discovery through launch.',
			'categories'  => array( 'management', 'entrepreneurship' ),
		),
		array(
			'title'       => 'Digital Marketing Analytics',
			'description' => 'Turn campaign and audience data into clear, evidence-led marketing decisions.',
			'categories'  => array( 'marketing', 'data-and-ai' ),
		),
		array(
			'title'       => 'Entrepreneurial Finance',
			'description' => 'Build practical financial plans for new ventures, investment, and sustainable growth.',
			'categories'  => array( 'entrepreneurship', 'management' ),
		),
		array(
			'title'       => 'Modern Web Application Development',
			'description' => 'Create maintainable web applications with accessible interfaces and reliable backend services.',
			'categories'  => array( 'software-development' ),
		),
		array(
			'title'       => 'Applied Data Science',
			'description' => 'Explore, model, and communicate real-world data using reproducible analytical methods.',
			'categories'  => array( 'data-and-ai' ),
		),
		array(
			'title'       => 'Cloud Architecture and Operations',
			'description' => 'Design resilient cloud systems and operate them with security, observability, and cost in mind.',
			'categories'  => array( 'cloud-computing', 'engineering' ),
		),
		array(
			'title'       => 'Visual Communication and Graphic Design',
			'description' => 'Use typography, colour, composition, and critique to create effective visual communication.',
			'categories'  => array( 'design', 'digital-media' ),
		),
		array(
			'title'       => 'Digital Storytelling',
			'description' => 'Plan and produce engaging stories across written, audio, and visual digital formats.',
			'categories'  => array( 'digital-media', 'writing' ),
		),
		array(
			'title'       => 'Professional Writing and Editing',
			'description' => 'Develop clear professional prose and an editorial process for varied audiences and channels.',
			'categories'  => array( 'writing' ),
		),
		array(
			'title'       => 'Global Public Health',
			'description' => 'Examine health systems, prevention, and policy through practical international case studies.',
			'categories'  => array( 'public-health' ),
		),
		array(
			'title'       => 'Inclusive Learning Design',
			'description' => 'Design inclusive learning experiences grounded in evidence, accessibility, and learner needs.',
			'categories'  => array( 'education', 'design' ),
		),
		array(
			'title'       => 'Psychology of Leadership',
			'description' => 'Apply psychological insight to communication, motivation, team culture, and ethical leadership.',
			'categories'  => array( 'psychology', 'management' ),
		),
		array(
			'title'       => 'Climate and Environmental Change',
			'description' => 'Understand environmental systems and evaluate practical responses to a changing climate.',
			'categories'  => array( 'environmental-science' ),
		),
		array(
			'title'       => 'Biotechnology and Society',
			'description' => 'Explore biotechnology concepts alongside their ethical, commercial, and social implications.',
			'categories'  => array( 'life-sciences', 'entrepreneurship' ),
		),
		array(
			'title'       => 'Sustainable Engineering Practice',
			'description' => 'Evaluate materials, systems, and lifecycle choices for responsible engineering projects.',
			'categories'  => array( 'engineering', 'environmental-science' ),
		),
		array(
			'title'       => 'Artificial Intelligence for Business',
			'description' => 'Assess useful AI opportunities, risks, data needs, and responsible implementation choices.',
			'categories'  => array( 'data-and-ai', 'management' ),
		),
		array(
			'title'       => 'Cybersecurity for Cloud Teams',
			'description' => 'Build secure cloud practices around identity, data protection, monitoring, and response.',
			'categories'  => array( 'cloud-computing', 'software-development' ),
		),
		array(
			'title'       => 'Brand Strategy and Identity',
			'description' => 'Connect research, positioning, messaging, and visual identity into a coherent brand system.',
			'categories'  => array( 'marketing', 'design' ),
		),
		array(
			'title'       => 'Health Data and Evidence',
			'description' => 'Interpret health data, evaluate evidence quality, and communicate findings responsibly.',
			'categories'  => array( 'public-health', 'data-and-ai' ),
		),
		array(
			'title'       => 'Learning Technology Innovation',
			'description' => 'Select and evaluate digital tools that improve teaching, participation, and learner outcomes.',
			'categories'  => array( 'education', 'software-development' ),
		),
	);

	/**
	 * Course variants used to build distinct titles and descriptions.
	 *
	 * @var list<array{title: string, description: string}>
	 */
	private const array TRACKS = array(
		array(
			'title'       => 'Foundations',
			'description' => 'The course builds core concepts through guided exercises and practical examples.',
		),
		array(
			'title'       => 'Professional Practice',
			'description' => 'The course emphasises applied projects, peer discussion, and workplace-ready decisions.',
		),
		array(
			'title'       => 'Advanced Study',
			'description' => 'The course challenges experienced learners with complex scenarios and independent work.',
		),
	);

	/**
	 * Canonical start months spanning multiple years.
	 *
	 * @var list<string>
	 */
	private const array START_DATES = array(
		'2027-01',
		'2027-02',
		'2027-03',
		'2027-04',
		'2027-05',
		'2027-06',
		'2027-07',
		'2027-08',
		'2027-09',
		'2027-10',
		'2027-11',
		'2027-12',
		'2028-01',
		'2028-02',
		'2028-03',
		'2028-04',
		'2028-05',
		'2028-06',
	);

	/**
	 * Create a catalogue seeder.
	 *
	 * @param CourseMetadataStore $metadata_store Supported Course metadata write boundary.
	 */
	public function __construct(
		private CourseMetadataStore $metadata_store
	) {
	}

	/**
	 * Upsert a deterministic demo catalogue.
	 *
	 * @param int $course_count Number of Courses to create.
	 *
	 * @return array{courses: int, providers: int, instructors: int, locations: int, categories: int}
	 *
	 * @throws InvalidArgumentException When the requested count is outside the supported demo range.
	 */
	public function seed( int $course_count = 40 ): array {
		if ( self::MIN_COURSES > $course_count || self::MAX_COURSES < $course_count ) {
			throw new InvalidArgumentException( 'Demo catalogue size must be between 30 and 50 Courses.' );
		}

		$location_ids   = $this->seed_locations();
		$provider_ids   = $this->seed_providers( $location_ids );
		$instructor_ids = $this->seed_instructors();
		$category_ids   = $this->seed_categories();

		$this->seed_courses( $course_count, $provider_ids, $instructor_ids, $category_ids );
		$this->delete_surplus_courses( $course_count );

		return array(
			'courses'     => $course_count,
			'providers'   => count( $provider_ids ),
			'instructors' => count( $instructor_ids ),
			'locations'   => count( $location_ids ),
			'categories'  => count( $category_ids ),
		);
	}

	/**
	 * Remove only posts and terms marked as belonging to this demo seed.
	 *
	 * @return array{posts: int, terms: int}
	 *
	 * @throws RuntimeException When WordPress cannot remove marked demo content.
	 */
	public function reset(): array {
		$post_count = 0;
		$term_count = 0;
		$posts      = get_posts(
			array(
				'fields'           => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Development-only marker lookup.
				'meta_key'         => self::MARKER_META_KEY,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Development-only marker lookup.
				'meta_value'       => self::SEED_ID,
				'no_found_rows'    => true,
				'post_status'      => 'any',
				'post_type'        => array(
					CoursePostType::POST_TYPE,
					ProviderPostType::POST_TYPE,
					InstructorPostType::POST_TYPE,
				),
				'posts_per_page'   => -1,
				'suppress_filters' => false,
			)
		);

		foreach ( $posts as $post_id ) {
			if ( false === wp_delete_post( $post_id, true ) ) {
				throw new RuntimeException( 'WordPress could not delete a seeded post.' );
			}

			++$post_count;
		}

		$terms = get_terms(
			array(
				'hide_empty' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Development-only marker lookup.
				'meta_key'   => self::MARKER_META_KEY,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Development-only marker lookup.
				'meta_value' => self::SEED_ID,
				'taxonomy'   => array( CourseCategoryTaxonomy::TAXONOMY, LocationTaxonomy::TAXONOMY ),
			)
		);

		if ( $terms instanceof WP_Error ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The WordPress error is preserved, not output.
			throw new RuntimeException( $terms->get_error_message() );
		}

		usort(
			$terms,
			static fn ( WP_Term $left, WP_Term $right ): int => $right->parent <=> $left->parent
		);

		foreach ( $terms as $term ) {
			$deleted = wp_delete_term( $term->term_id, $term->taxonomy );

			if ( $deleted instanceof WP_Error || false === $deleted ) {
				throw new RuntimeException( 'WordPress could not delete a seeded term.' );
			}

			++$term_count;
		}

		return array(
			'posts' => $post_count,
			'terms' => $term_count,
		);
	}

	/**
	 * Seed flat Location terms.
	 *
	 * @return array<string, int>
	 */
	private function seed_locations(): array {
		$identifiers = array();

		foreach ( self::LOCATIONS as $slug => $name ) {
			$identifiers[ $slug ] = $this->upsert_term( $name, $slug, LocationTaxonomy::TAXONOMY );
		}

		return $identifiers;
	}

	/**
	 * Seed Providers and attach existing Location terms.
	 *
	 * @param array<string, int> $location_ids Location IDs indexed by seed slug.
	 *
	 * @return list<int>
	 *
	 * @throws RuntimeException When WordPress cannot persist a Provider or its Locations.
	 */
	private function seed_providers( array $location_ids ): array {
		$identifiers = array();

		foreach ( self::PROVIDERS as $provider ) {
			$provider_id    = $this->upsert_post(
				ProviderPostType::POST_TYPE,
				'course-discovery-demo-' . $provider['slug'],
				$provider['title']
			);
			$provider_terms = array_map(
				static fn ( string $slug ): int => $location_ids[ $slug ],
				$provider['locations']
			);
			$assigned       = wp_set_object_terms( $provider_id, $provider_terms, LocationTaxonomy::TAXONOMY );

			if ( $assigned instanceof WP_Error ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The WordPress error is preserved, not output.
				throw new RuntimeException( $assigned->get_error_message() );
			}

			$identifiers[] = $provider_id;
		}

		return $identifiers;
	}

	/**
	 * Seed Instructor posts.
	 *
	 * @return list<int>
	 */
	private function seed_instructors(): array {
		$identifiers = array();

		foreach ( self::INSTRUCTORS as $slug => $title ) {
			$identifiers[] = $this->upsert_post(
				InstructorPostType::POST_TYPE,
				'course-discovery-demo-' . $slug,
				$title
			);
		}

		return $identifiers;
	}

	/**
	 * Seed parent and child Course Categories.
	 *
	 * @return array<string, int>
	 */
	private function seed_categories(): array {
		$identifiers = array();

		foreach ( self::CATEGORIES as $slug => $category ) {
			$parent_id            = $this->upsert_term(
				$category['name'],
				'course-discovery-demo-' . $slug,
				CourseCategoryTaxonomy::TAXONOMY
			);
			$identifiers[ $slug ] = $parent_id;

			foreach ( $category['children'] as $child_slug => $child_name ) {
				$identifiers[ $child_slug ] = $this->upsert_term(
					$child_name,
					'course-discovery-demo-' . $child_slug,
					CourseCategoryTaxonomy::TAXONOMY,
					$parent_id
				);
			}
		}

		return $identifiers;
	}

	/**
	 * Seed Courses and persist modeled relationships through CourseMetadataStore.
	 *
	 * @param int   $course_count   Course count.
	 * @param array $provider_ids   Provider IDs.
	 * @param array $instructor_ids Instructor IDs.
	 * @param array $category_ids   Category IDs indexed by seed slug.
	 *
	 * @phpstan-param list<int> $provider_ids
	 * @phpstan-param list<int> $instructor_ids
	 * @phpstan-param array<string, int> $category_ids
	 *
	 * @throws RuntimeException When WordPress cannot persist modeled Course data.
	 */
	private function seed_courses(
		int $course_count,
		array $provider_ids,
		array $instructor_ids,
		array $category_ids
	): void {
		$subject_count    = count( self::SUBJECTS );
		$provider_count   = count( $provider_ids );
		$instructor_count = count( $instructor_ids );
		$date_count       = count( self::START_DATES );

		for ( $index = 0; $index < $course_count; ++$index ) {
			$subject     = self::SUBJECTS[ $index % $subject_count ];
			$track       = self::TRACKS[ intdiv( $index, $subject_count ) % count( self::TRACKS ) ];
			$description = $subject['description'] . ' ' . $track['description'];
			$course_id   = $this->upsert_post(
				CoursePostType::POST_TYPE,
				sprintf( 'course-discovery-demo-course-%02d', $index + 1 ),
				$subject['title'] . ': ' . $track['title'],
				$description,
				$description . ' Learners work with realistic scenarios and leave with practical approaches they can adapt.'
			);
			$course      = new CourseId( $course_id );
			$price       = sprintf( '%d.%02d', 650 + ( ( $index * 185 ) % 3350 ), ( $index % 4 ) * 25 );
			$providers   = array( $provider_ids[ $index % $provider_count ] );
			$instructors = array( $instructor_ids[ ( $index * 2 ) % $instructor_count ] );
			$dates       = array( self::START_DATES[ $index % $date_count ] );

			if ( 0 === $index % 3 ) {
				$providers[] = $provider_ids[ ( $index + 3 ) % $provider_count ];
			}

			if ( 0 === $index % 10 ) {
				$providers[] = $provider_ids[ ( $index + 5 ) % $provider_count ];
			}

			if ( 0 === $index % 2 ) {
				$instructors[] = $instructor_ids[ ( $index * 2 + 5 ) % $instructor_count ];
			}

			if ( 0 === $index % 3 ) {
				$dates[] = self::START_DATES[ ( $index + 5 ) % $date_count ];
			}

			if ( 0 === $index % 7 ) {
				$dates[] = self::START_DATES[ ( $index + 10 ) % $date_count ];
			}

			$this->metadata_store->save_price( $course, Price::from_decimal( $price ) );
			$this->metadata_store->replace_providers(
				$course,
				...array_map( static fn ( int $identifier ): ProviderId => new ProviderId( $identifier ), $providers )
			);
			$this->metadata_store->replace_instructors(
				$course,
				...array_map( static fn ( int $identifier ): InstructorId => new InstructorId( $identifier ), $instructors )
			);
			$this->metadata_store->replace_start_dates(
				$course,
				...array_map( static fn ( string $date ): StartDate => new StartDate( $date ), $dates )
			);

			$assigned = wp_set_object_terms(
				$course_id,
				array_map(
					static fn ( string $slug ): int => $category_ids[ $slug ],
					$subject['categories']
				),
				CourseCategoryTaxonomy::TAXONOMY
			);

			if ( $assigned instanceof WP_Error ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The WordPress error is preserved, not output.
				throw new RuntimeException( $assigned->get_error_message() );
			}
		}
	}

	/**
	 * Insert or update one marked demo post by its deterministic slug.
	 *
	 * @param string $post_type Post type.
	 * @param string $slug      Deterministic slug.
	 * @param string $title     Post title.
	 * @param string $excerpt   Optional Course excerpt.
	 * @param string $content   Optional Course content.
	 *
	 * @throws RuntimeException When WordPress cannot safely upsert or mark the post.
	 */
	private function upsert_post(
		string $post_type,
		string $slug,
		string $title,
		string $excerpt = '',
		string $content = ''
	): int {
		$post = get_page_by_path( $slug, OBJECT, $post_type );

		if ( $post instanceof WP_Post && self::SEED_ID !== get_post_meta( $post->ID, self::MARKER_META_KEY, true ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The reserved internal slug is not output.
			throw new RuntimeException( 'A non-demo post already uses the reserved demo slug: ' . $slug );
		}

		$post_data = array(
			'post_content' => $content,
			'post_excerpt' => $excerpt,
			'post_name'    => $slug,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_type'    => $post_type,
		);

		if ( $post instanceof WP_Post ) {
			$post_data['ID'] = $post->ID;
			$result          = wp_update_post( $post_data, true );
		} else {
			$result = wp_insert_post( $post_data, true );
		}

		if ( $result instanceof WP_Error ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The WordPress error is preserved, not output.
			throw new RuntimeException( $result->get_error_message() );
		}

		$marked = update_post_meta( $result, self::MARKER_META_KEY, self::SEED_ID );

		if ( false === $marked && self::SEED_ID !== get_post_meta( $result, self::MARKER_META_KEY, true ) ) {
			throw new RuntimeException( 'WordPress could not mark a seeded post.' );
		}

		return $result;
	}

	/**
	 * Insert or update one marked demo term by its deterministic slug.
	 *
	 * @param string $name     Term name.
	 * @param string $slug     Deterministic slug.
	 * @param string $taxonomy Taxonomy.
	 * @param int    $parent_id Optional parent term ID.
	 *
	 * @throws RuntimeException When WordPress cannot safely upsert or mark the term.
	 */
	private function upsert_term( string $name, string $slug, string $taxonomy, int $parent_id = 0 ): int {
		$term = get_term_by( 'slug', $slug, $taxonomy );

		if ( $term instanceof WP_Term && self::SEED_ID !== get_term_meta( $term->term_id, self::MARKER_META_KEY, true ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The reserved internal slug is not output.
			throw new RuntimeException( 'A non-demo term already uses the reserved demo slug: ' . $slug );
		}

		if ( $term instanceof WP_Term ) {
			$result = wp_update_term(
				$term->term_id,
				$taxonomy,
				array(
					'name'   => $name,
					'parent' => $parent_id,
					'slug'   => $slug,
				)
			);
		} else {
			$result = wp_insert_term(
				$name,
				$taxonomy,
				array(
					'parent' => $parent_id,
					'slug'   => $slug,
				)
			);
		}

		if ( $result instanceof WP_Error ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The WordPress error is preserved, not output.
			throw new RuntimeException( $result->get_error_message() );
		}

		$term_id = (int) $result['term_id'];
		$marked  = update_term_meta( $term_id, self::MARKER_META_KEY, self::SEED_ID );

		if ( $marked instanceof WP_Error ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The WordPress error is preserved, not output.
			throw new RuntimeException( $marked->get_error_message() );
		}

		if ( false === $marked && self::SEED_ID !== get_term_meta( $term_id, self::MARKER_META_KEY, true ) ) {
			throw new RuntimeException( 'WordPress could not mark a seeded term.' );
		}

		return $term_id;
	}

	/**
	 * Remove marked Course rows above a newly requested lower count.
	 *
	 * @param int $course_count Requested Course count.
	 *
	 * @throws RuntimeException When WordPress cannot remove a surplus marked Course.
	 */
	private function delete_surplus_courses( int $course_count ): void {
		$posts = get_posts(
			array(
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Development-only marker lookup.
				'meta_key'         => self::MARKER_META_KEY,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Development-only marker lookup.
				'meta_value'       => self::SEED_ID,
				'no_found_rows'    => true,
				'post_status'      => 'any',
				'post_type'        => CoursePostType::POST_TYPE,
				'posts_per_page'   => -1,
				'suppress_filters' => false,
			)
		);

		foreach ( $posts as $post ) {
			if ( 1 !== preg_match( '/\Acourse-discovery-demo-course-([0-9]+)\z/', $post->post_name, $matches ) ) {
				continue;
			}

			if ( $course_count >= (int) $matches[1] ) {
				continue;
			}

			if ( false === wp_delete_post( $post->ID, true ) ) {
				throw new RuntimeException( 'WordPress could not remove a surplus seeded Course.' );
			}
		}
	}
}
