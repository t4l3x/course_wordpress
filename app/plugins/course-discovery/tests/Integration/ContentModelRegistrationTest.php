<?php
/**
 * Integration tests for WordPress content registration.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Integration;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseCategoryTaxonomy;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMeta;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CoursePostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\InstructorPostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\LocationTaxonomy;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\ProviderPostType;
use WP_Post_Type;
use WP_Taxonomy;
use WP_UnitTestCase;

/**
 * Verifies the native WordPress shape of the content model.
 */
final class ContentModelRegistrationTest extends WP_UnitTestCase {
	/**
	 * Course, Provider, and Instructor are registered for admin and REST use.
	 */
	public function test_all_content_post_types_are_registered(): void {
		$post_types = array(
			'cd_course',
			'cd_provider',
			'cd_instructor',
		);

		foreach ( $post_types as $post_type ) {
			self::assertTrue( post_type_exists( $post_type ) );

			$registration = get_post_type_object( $post_type );

			self::assertInstanceOf( WP_Post_Type::class, $registration );
			self::assertTrue( $registration->show_ui );
			self::assertTrue( $registration->show_in_rest );
		}
	}

	/**
	 * Native post fields hold Course content and expose registered metadata to REST.
	 */
	public function test_course_supports_title_editor_and_excerpt(): void {
		self::assertTrue( post_type_supports( CoursePostType::POST_TYPE, 'title' ) );
		self::assertTrue( post_type_supports( CoursePostType::POST_TYPE, 'editor' ) );
		self::assertTrue( post_type_supports( CoursePostType::POST_TYPE, 'excerpt' ) );
		self::assertTrue( post_type_supports( CoursePostType::POST_TYPE, 'custom-fields' ) );
	}

	/**
	 * Course Category is hierarchical and belongs to Course.
	 */
	public function test_course_category_is_hierarchical_and_attached_to_course(): void {
		self::assertTrue( taxonomy_exists( 'cd_course_category' ) );

		$taxonomy = get_taxonomy( 'cd_course_category' );

		self::assertInstanceOf( WP_Taxonomy::class, $taxonomy );
		self::assertTrue( $taxonomy->hierarchical );
		self::assertContains( CoursePostType::POST_TYPE, $taxonomy->object_type );
		self::assertTrue(
			is_object_in_taxonomy( CoursePostType::POST_TYPE, CourseCategoryTaxonomy::TAXONOMY )
		);
	}

	/**
	 * A course can use more than one native category term.
	 */
	public function test_course_supports_multiple_categories(): void {
		$course_id = self::factory()->post->create(
			array( 'post_type' => CoursePostType::POST_TYPE )
		);
		self::assertIsInt( $course_id );
		$first  = wp_insert_term( 'Category One', CourseCategoryTaxonomy::TAXONOMY );
		$second = wp_insert_term( 'Category Two', CourseCategoryTaxonomy::TAXONOMY );

		self::assertIsArray( $first );
		self::assertIsArray( $second );

		$assigned = wp_set_object_terms(
			$course_id,
			array( $first['term_id'], $second['term_id'] ),
			CourseCategoryTaxonomy::TAXONOMY
		);

		self::assertIsArray( $assigned );

		$term_ids = wp_get_object_terms(
			$course_id,
			CourseCategoryTaxonomy::TAXONOMY,
			array( 'fields' => 'ids' )
		);

		self::assertIsArray( $term_ids );
		self::assertEqualsCanonicalizing(
			array( $first['term_id'], $second['term_id'] ),
			array_map( 'intval', $term_ids )
		);
	}

	/**
	 * Location belongs to Provider and is deliberately not duplicated on Course.
	 */
	public function test_location_is_attached_only_to_provider(): void {
		self::assertTrue( taxonomy_exists( 'cd_location' ) );

		$taxonomy = get_taxonomy( 'cd_location' );

		self::assertInstanceOf( WP_Taxonomy::class, $taxonomy );
		self::assertContains( ProviderPostType::POST_TYPE, $taxonomy->object_type );
		self::assertNotContains( CoursePostType::POST_TYPE, $taxonomy->object_type );
		self::assertTrue(
			is_object_in_taxonomy( ProviderPostType::POST_TYPE, LocationTaxonomy::TAXONOMY )
		);
		self::assertFalse(
			is_object_in_taxonomy( CoursePostType::POST_TYPE, LocationTaxonomy::TAXONOMY )
		);
	}

	/**
	 * Course metadata has explicit types and single/multiple-value contracts.
	 */
	public function test_required_course_metadata_contracts_are_registered(): void {
		( new CourseMeta() )->register();

		$registered_meta = get_registered_meta_keys( 'post', CoursePostType::POST_TYPE );
		$expected_meta   = array(
			CourseMeta::PRICE_KEY         => array(
				'type'   => 'string',
				'single' => true,
			),
			CourseMeta::PROVIDER_ID_KEY   => array(
				'type'   => 'integer',
				'single' => false,
			),
			CourseMeta::INSTRUCTOR_ID_KEY => array(
				'type'   => 'integer',
				'single' => false,
			),
			CourseMeta::START_DATE_KEY    => array(
				'type'   => 'string',
				'single' => false,
			),
		);

		foreach ( $expected_meta as $meta_key => $expected_contract ) {
			self::assertArrayHasKey( $meta_key, $registered_meta );
			self::assertSame( $expected_contract['type'], $registered_meta[ $meta_key ]['type'] );
			self::assertSame( $expected_contract['single'], $registered_meta[ $meta_key ]['single'] );

			$rest_registration = $registered_meta[ $meta_key ]['show_in_rest'];

			self::assertIsArray( $rest_registration );
			self::assertArrayHasKey( 'schema', $rest_registration );
			self::assertIsArray( $rest_registration['schema'] );
			self::assertSame( $expected_contract['type'], $rest_registration['schema']['type'] );
		}
	}

	/**
	 * The registered relationship boundary rejects unsupported identifiers.
	 *
	 * @dataProvider invalid_relationship_value_provider
	 *
	 * @param string $meta_key Registered relationship key.
	 * @param mixed  $value    Invalid identifier value.
	 */
	public function test_relationship_metadata_rejects_invalid_values( string $meta_key, mixed $value ): void {
		$course_id = self::factory()->post->create(
			array( 'post_type' => CoursePostType::POST_TYPE )
		);
		self::assertIsInt( $course_id );

		( new CourseMeta() )->register();
		$this->expectException( InvalidArgumentException::class );

		add_post_meta( $course_id, $meta_key, $value );
	}

	/**
	 * Invalid external relationship identifier examples.
	 *
	 * @return array<string, array{string, mixed}>
	 */
	public static function invalid_relationship_value_provider(): array {
		return array(
			'zero provider'       => array( CourseMeta::PROVIDER_ID_KEY, 0 ),
			'boolean provider'    => array( CourseMeta::PROVIDER_ID_KEY, true ),
			'floating instructor' => array( CourseMeta::INSTRUCTOR_ID_KEY, 1.0 ),
			'text instructor'     => array( CourseMeta::INSTRUCTOR_ID_KEY, 'not-an-id' ),
		);
	}

	/**
	 * Metadata authorization evaluates the user WordPress asks about.
	 */
	public function test_course_metadata_authorization_uses_the_evaluated_user(): void {
		$course_id = self::factory()->post->create(
			array( 'post_type' => CoursePostType::POST_TYPE )
		);
		self::assertIsInt( $course_id );
		$administrator_id = $this->create_user( 'administrator' );
		$subscriber_id    = $this->create_user( 'subscriber' );

		( new CourseMeta() )->register();
		wp_set_current_user( $subscriber_id );

		try {
			self::assertTrue(
				user_can( $administrator_id, 'edit_post_meta', $course_id, CourseMeta::PRICE_KEY )
			);
			self::assertFalse(
				user_can( $subscriber_id, 'edit_post_meta', $course_id, CourseMeta::PRICE_KEY )
			);
		} finally {
			wp_set_current_user( 0 );
		}
	}

	/**
	 * Create a WordPress user fixture with a role.
	 *
	 * @param string $role WordPress role slug.
	 */
	private function create_user( string $role ): int {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'course-discovery-' . wp_generate_password( 12, false, false ),
				'user_pass'  => wp_generate_password( 20 ),
				'role'       => $role,
			)
		);

		self::assertIsInt( $user_id );

		return $user_id;
	}
}
