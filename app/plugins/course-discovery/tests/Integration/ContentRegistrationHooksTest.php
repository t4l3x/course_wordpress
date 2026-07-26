<?php
/**
 * Integration tests for content-registration extension hooks.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Integration;

use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseCategoryTaxonomy;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CoursePostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\InstructorPostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\LocationTaxonomy;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\ProviderPostType;
use RuntimeException;
use WP_Post_Type;
use WP_Taxonomy;
use WP_UnitTestCase;

/**
 * Verifies that each intended arguments hook affects real WordPress registration.
 */
final class ContentRegistrationHooksTest extends WP_UnitTestCase {
	/**
	 * Content types use the intended native visibility and REST exposure.
	 */
	public function test_post_types_use_intended_public_and_rest_visibility(): void {
		$course = get_post_type_object( CoursePostType::POST_TYPE );

		self::assertInstanceOf( WP_Post_Type::class, $course );
		self::assertFalse( $course->public );
		self::assertFalse( $course->publicly_queryable );
		self::assertTrue( $course->show_ui );
		self::assertTrue( $course->show_in_menu );
		self::assertTrue( $course->show_in_rest );
		self::assertFalse( $course->has_archive );
		self::assertFalse( $course->rewrite );

		foreach ( array( ProviderPostType::POST_TYPE, InstructorPostType::POST_TYPE ) as $post_type ) {
			$reference = get_post_type_object( $post_type );

			self::assertInstanceOf( WP_Post_Type::class, $reference );
			self::assertFalse( $reference->public );
			self::assertFalse( $reference->publicly_queryable );
			self::assertTrue( $reference->show_ui );
			self::assertTrue( $reference->show_in_menu );
			self::assertFalse( $reference->show_in_rest );
			self::assertFalse( $reference->has_archive );
			self::assertFalse( $reference->rewrite );
		}
	}

	/**
	 * Registration argument filters can modify their WordPress object.
	 *
	 * @dataProvider registration_provider
	 *
	 * @param class-string $registration_class Registration class.
	 * @param string       $identifier         Post type or taxonomy identifier.
	 * @param string       $filter             Registration arguments filter.
	 * @param string       $object_kind        Either post_type or taxonomy.
	 */
	public function test_registration_arguments_filter_is_applied(
		string $registration_class,
		string $identifier,
		string $filter,
		string $object_kind
	): void {
		$description = 'Changed by a Course Discovery registration filter.';
		$callback    = static function ( array $arguments ) use ( $description ): array {
			$arguments['description'] = $description;

			return $arguments;
		};

		$this->unregister_object( $object_kind, $identifier );
		add_filter( $filter, $callback );

		try {
			$this->register_object( $registration_class );

			$registered_object = $this->registered_object( $object_kind, $identifier );

			self::assertSame( $description, $registered_object->description );
		} finally {
			remove_filter( $filter, $callback );
			$this->unregister_object( $object_kind, $identifier );
			$this->restore_registration( $registration_class );
		}
	}

	/**
	 * Invalid post-type filter output reports misuse and preserves registration.
	 *
	 * @dataProvider post_type_registration_provider
	 *
	 * @param class-string $registration_class Registration class.
	 * @param string       $identifier         Post type identifier.
	 * @param string       $filter             Registration arguments filter.
	 */
	public function test_invalid_post_type_filter_output_uses_default_arguments(
		string $registration_class,
		string $identifier,
		string $filter
	): void {
		$callback = static fn (): string => 'invalid';

		$this->unregister_object( 'post_type', $identifier );
		add_filter( $filter, $callback );
		$this->setExpectedIncorrectUsage( $registration_class . '::register' );

		try {
			$this->register_object( $registration_class );

			self::assertTrue( post_type_exists( $identifier ) );
		} finally {
			remove_filter( $filter, $callback );
			$this->unregister_object( 'post_type', $identifier );
			$this->restore_registration( $registration_class );
		}
	}

	/**
	 * Post-type registration hooks and identifiers.
	 *
	 * @return array<string, array{class-string, string, string}>
	 */
	public static function post_type_registration_provider(): array {
		return array(
			'course'     => array(
				CoursePostType::class,
				CoursePostType::POST_TYPE,
				CoursePostType::ARGS_FILTER,
			),
			'provider'   => array(
				ProviderPostType::class,
				ProviderPostType::POST_TYPE,
				ProviderPostType::ARGS_FILTER,
			),
			'instructor' => array(
				InstructorPostType::class,
				InstructorPostType::POST_TYPE,
				InstructorPostType::ARGS_FILTER,
			),
		);
	}

	/**
	 * Every intentionally exposed registration hook.
	 *
	 * @return array<string, array{class-string, string, string, string}>
	 */
	public static function registration_provider(): array {
		return array(
			'course post type'     => array(
				CoursePostType::class,
				CoursePostType::POST_TYPE,
				CoursePostType::ARGS_FILTER,
				'post_type',
			),
			'provider post type'   => array(
				ProviderPostType::class,
				ProviderPostType::POST_TYPE,
				ProviderPostType::ARGS_FILTER,
				'post_type',
			),
			'instructor post type' => array(
				InstructorPostType::class,
				InstructorPostType::POST_TYPE,
				InstructorPostType::ARGS_FILTER,
				'post_type',
			),
			'course category'      => array(
				CourseCategoryTaxonomy::class,
				CourseCategoryTaxonomy::TAXONOMY,
				CourseCategoryTaxonomy::ARGS_FILTER,
				'taxonomy',
			),
			'provider location'    => array(
				LocationTaxonomy::class,
				LocationTaxonomy::TAXONOMY,
				LocationTaxonomy::ARGS_FILTER,
				'taxonomy',
			),
		);
	}

	/**
	 * Remove a registration so it can be exercised again with a filter.
	 *
	 * @param string $object_kind Post type or taxonomy.
	 * @param string $identifier  Registration identifier.
	 *
	 * @throws RuntimeException When the registration kind is unknown.
	 */
	private function unregister_object( string $object_kind, string $identifier ): void {
		if ( 'post_type' === $object_kind ) {
			if ( post_type_exists( $identifier ) ) {
				unregister_post_type( $identifier );
			}

			return;
		}

		if ( 'taxonomy' === $object_kind ) {
			if ( taxonomy_exists( $identifier ) ) {
				unregister_taxonomy( $identifier );
			}

			return;
		}

		throw new RuntimeException( 'Unknown WordPress registration kind.' );
	}

	/**
	 * Invoke one known registration class.
	 *
	 * @param class-string $registration_class Registration class.
	 *
	 * @throws RuntimeException When the registration class is unknown.
	 */
	private function register_object( string $registration_class ): void {
		switch ( $registration_class ) {
			case CoursePostType::class:
				( new CoursePostType() )->register();
				return;
			case ProviderPostType::class:
				( new ProviderPostType() )->register();
				return;
			case InstructorPostType::class:
				( new InstructorPostType() )->register();
				return;
			case CourseCategoryTaxonomy::class:
				( new CourseCategoryTaxonomy() )->register();
				return;
			case LocationTaxonomy::class:
				( new LocationTaxonomy() )->register();
				return;
		}

		throw new RuntimeException( 'Unknown Course Discovery registration class.' );
	}

	/**
	 * Restore only the registration changed by the current test.
	 *
	 * @param class-string $registration_class Registration class.
	 */
	private function restore_registration( string $registration_class ): void {
		$this->register_object( $registration_class );

		if ( CoursePostType::class === $registration_class ) {
			register_taxonomy_for_object_type(
				CourseCategoryTaxonomy::TAXONOMY,
				CoursePostType::POST_TYPE
			);
		}

		if ( ProviderPostType::class === $registration_class ) {
			register_taxonomy_for_object_type(
				LocationTaxonomy::TAXONOMY,
				ProviderPostType::POST_TYPE
			);
		}
	}

	/**
	 * Read the object created by the registration under test.
	 *
	 * @param string $object_kind Post type or taxonomy.
	 * @param string $identifier  Registration identifier.
	 *
	 * @return WP_Post_Type|WP_Taxonomy
	 *
	 * @throws RuntimeException When WordPress did not create the expected object.
	 */
	private function registered_object( string $object_kind, string $identifier ) {
		$registered_object = 'post_type' === $object_kind
			? get_post_type_object( $identifier )
			: get_taxonomy( $identifier );

		if (
			! $registered_object instanceof WP_Post_Type
			&& ! $registered_object instanceof WP_Taxonomy
		) {
			throw new RuntimeException( 'Expected WordPress registration was not created.' );
		}

		return $registered_object;
	}
}
