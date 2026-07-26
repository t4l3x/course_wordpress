<?php
/**
 * Integration tests for Course administration metadata saves.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Integration;

use OxfordInternational\CourseDiscovery\Domain\Course\CourseId;
use OxfordInternational\CourseDiscovery\Domain\Course\Price;
use OxfordInternational\CourseDiscovery\Domain\Course\StartDate;
use OxfordInternational\CourseDiscovery\Domain\Instructor\InstructorId;
use OxfordInternational\CourseDiscovery\Domain\Provider\ProviderId;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Admin\CourseMetaBox;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Admin\CourseMetaSaveHandler;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMeta;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMetadataStore;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CoursePostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\InstructorPostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\ProviderPostType;
use WP_Post;
use WP_UnitTestCase;

/**
 * Verifies security, validation, and typed persistence at the Course save hook.
 */
final class CourseMetaSaveHandlerTest extends WP_UnitTestCase {
	/**
	 * One valid native admin request persists every Course metadata contract.
	 */
	public function test_valid_request_persists_all_course_metadata(): void {
		$administrator_id = $this->create_user( 'administrator' );
		$course_id        = $this->create_post( CoursePostType::POST_TYPE, 'Admin Course' );
		$provider_one     = $this->create_post( ProviderPostType::POST_TYPE, 'Provider One' );
		$provider_two     = $this->create_post( ProviderPostType::POST_TYPE, 'Provider Two' );
		$instructor_one   = $this->create_post( InstructorPostType::POST_TYPE, 'Instructor One' );
		$instructor_two   = $this->create_post( InstructorPostType::POST_TYPE, 'Instructor Two' );

		$this->submit_course_form(
			$administrator_id,
			$course_id,
			$this->form_fields(
				'125.50',
				array( '', (string) $provider_one, (string) $provider_two, (string) $provider_one ),
				array( '', (string) $instructor_one, (string) $instructor_two, (string) $instructor_one ),
				array( '2027-01', '2026-09', '2026-09', '' )
			)
		);

		self::assertSame(
			array(
				'price'       => '125.5',
				'providers'   => array( $provider_one, $provider_two ),
				'instructors' => array( $instructor_one, $instructor_two ),
				'start_dates' => array( '2026-09', '2027-01' ),
			),
			$this->metadata_snapshot( $course_id )
		);
	}

	/**
	 * A canonically unchanged price remains a successful save.
	 */
	public function test_unchanged_price_is_accepted(): void {
		$administrator_id = $this->create_user( 'administrator' );
		$course_id        = $this->create_post( CoursePostType::POST_TYPE, 'Admin Course' );
		$provider_id      = $this->create_post( ProviderPostType::POST_TYPE, 'Provider One' );
		$instructor_id    = $this->create_post( InstructorPostType::POST_TYPE, 'Instructor One' );

		$this->seed_metadata( $course_id, $provider_id, $instructor_id );
		$before = $this->metadata_snapshot( $course_id );

		$this->submit_course_form(
			$administrator_id,
			$course_id,
			$this->form_fields(
				'10.00',
				array( '', (string) $provider_id ),
				array( '', (string) $instructor_id ),
				array( '2026-09' )
			)
		);

		self::assertSame( $before, $this->metadata_snapshot( $course_id ) );
	}

	/**
	 * A blank price deliberately removes the single-value metadata row.
	 */
	public function test_blank_price_removes_the_existing_price(): void {
		$administrator_id = $this->create_user( 'administrator' );
		$course_id        = $this->create_post( CoursePostType::POST_TYPE, 'Admin Course' );
		$provider_id      = $this->create_post( ProviderPostType::POST_TYPE, 'Provider One' );
		$instructor_id    = $this->create_post( InstructorPostType::POST_TYPE, 'Instructor One' );

		$this->seed_metadata( $course_id, $provider_id, $instructor_id );
		$this->submit_course_form(
			$administrator_id,
			$course_id,
			$this->form_fields(
				'',
				array( '', (string) $provider_id ),
				array( '', (string) $instructor_id ),
				array( '2026-09' )
			)
		);

		self::assertSame(
			array(
				'price'       => null,
				'providers'   => array( $provider_id ),
				'instructors' => array( $instructor_id ),
				'start_dates' => array( '2026-09' ),
			),
			$this->metadata_snapshot( $course_id )
		);
		self::assertFalse(
			metadata_exists( 'post', $course_id, CourseMeta::PRICE_KEY )
		);
	}

	/**
	 * Hidden blank sentinels intentionally clear all repeatable metadata.
	 */
	public function test_blank_sentinel_arrays_clear_relationships_and_start_dates(): void {
		$administrator_id = $this->create_user( 'administrator' );
		$course_id        = $this->create_post( CoursePostType::POST_TYPE, 'Admin Course' );
		$provider_id      = $this->create_post( ProviderPostType::POST_TYPE, 'Provider One' );
		$instructor_id    = $this->create_post( InstructorPostType::POST_TYPE, 'Instructor One' );

		$this->seed_metadata( $course_id, $provider_id, $instructor_id );
		$this->submit_course_form(
			$administrator_id,
			$course_id,
			$this->form_fields(
				'10',
				array( '' ),
				array( '' ),
				array( '' )
			)
		);

		self::assertSame(
			array(
				'price'       => '10',
				'providers'   => array(),
				'instructors' => array(),
				'start_dates' => array(),
			),
			$this->metadata_snapshot( $course_id )
		);
	}

	/**
	 * A malformed or nonexistent relationship rejects the complete request.
	 *
	 * @dataProvider invalid_relationship_value_provider
	 *
	 * @param string $field         Relationship request field.
	 * @param mixed  $invalid_value Invalid request value.
	 */
	public function test_invalid_relationship_values_preserve_all_metadata(
		string $field,
		mixed $invalid_value
	): void {
		$administrator_id = $this->create_user( 'administrator' );
		$course_id        = $this->create_post( CoursePostType::POST_TYPE, 'Admin Course' );
		$provider_one     = $this->create_post( ProviderPostType::POST_TYPE, 'Provider One' );
		$provider_two     = $this->create_post( ProviderPostType::POST_TYPE, 'Provider Two' );
		$instructor_one   = $this->create_post( InstructorPostType::POST_TYPE, 'Instructor One' );
		$instructor_two   = $this->create_post( InstructorPostType::POST_TYPE, 'Instructor Two' );

		$this->seed_metadata( $course_id, $provider_one, $instructor_one );
		$before           = $this->metadata_snapshot( $course_id );
		$fields           = $this->changed_form_fields( $provider_two, $instructor_two );
		$fields[ $field ] = $invalid_value;

		$this->submit_course_form( $administrator_id, $course_id, $fields );

		self::assertSame( $before, $this->metadata_snapshot( $course_id ) );
	}

	/**
	 * Invalid relationship request examples.
	 *
	 * @return array<string, array{string, mixed}>
	 */
	public static function invalid_relationship_value_provider(): array {
		return array(
			'provider field is scalar'       => array( CourseMetaBox::PROVIDERS_FIELD, '1' ),
			'provider identifier is zero'    => array( CourseMetaBox::PROVIDERS_FIELD, array( '0' ) ),
			'provider does not exist'        => array(
				CourseMetaBox::PROVIDERS_FIELD,
				array( '999999999' ),
			),
			'instructor identifier is float' => array(
				CourseMetaBox::INSTRUCTORS_FIELD,
				array( '1.5' ),
			),
			'instructor identifier is array' => array(
				CourseMetaBox::INSTRUCTORS_FIELD,
				array( array( '1' ) ),
			),
		);
	}

	/**
	 * A relationship pointing at the wrong post type rejects the complete request.
	 *
	 * @dataProvider wrong_relationship_post_type_provider
	 *
	 * @param string $field Relationship request field.
	 * @param string $kind  Related fixture kind used as an invalid value.
	 */
	public function test_wrong_relationship_post_types_preserve_all_metadata(
		string $field,
		string $kind
	): void {
		$administrator_id = $this->create_user( 'administrator' );
		$course_id        = $this->create_post( CoursePostType::POST_TYPE, 'Admin Course' );
		$provider_one     = $this->create_post( ProviderPostType::POST_TYPE, 'Provider One' );
		$provider_two     = $this->create_post( ProviderPostType::POST_TYPE, 'Provider Two' );
		$instructor_one   = $this->create_post( InstructorPostType::POST_TYPE, 'Instructor One' );
		$instructor_two   = $this->create_post( InstructorPostType::POST_TYPE, 'Instructor Two' );

		$this->seed_metadata( $course_id, $provider_one, $instructor_one );
		$before           = $this->metadata_snapshot( $course_id );
		$fields           = $this->changed_form_fields( $provider_two, $instructor_two );
		$wrong_identifier = 'provider' === $kind ? $provider_two : $instructor_two;
		$fields[ $field ] = array( (string) $wrong_identifier );

		$this->submit_course_form( $administrator_id, $course_id, $fields );

		self::assertSame( $before, $this->metadata_snapshot( $course_id ) );
	}

	/**
	 * Wrong semantic relationship examples.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function wrong_relationship_post_type_provider(): array {
		return array(
			'provider field points at instructor' => array(
				CourseMetaBox::PROVIDERS_FIELD,
				'instructor',
			),
			'instructor field points at provider' => array(
				CourseMetaBox::INSTRUCTORS_FIELD,
				'provider',
			),
		);
	}

	/**
	 * Invalid start-date syntax or structure rejects the complete request.
	 *
	 * @dataProvider invalid_start_dates_provider
	 *
	 * @param mixed $invalid_dates Invalid start-date request value.
	 */
	public function test_invalid_start_dates_preserve_all_metadata( mixed $invalid_dates ): void {
		$administrator_id = $this->create_user( 'administrator' );
		$course_id        = $this->create_post( CoursePostType::POST_TYPE, 'Admin Course' );
		$provider_one     = $this->create_post( ProviderPostType::POST_TYPE, 'Provider One' );
		$provider_two     = $this->create_post( ProviderPostType::POST_TYPE, 'Provider Two' );
		$instructor_one   = $this->create_post( InstructorPostType::POST_TYPE, 'Instructor One' );
		$instructor_two   = $this->create_post( InstructorPostType::POST_TYPE, 'Instructor Two' );

		$this->seed_metadata( $course_id, $provider_one, $instructor_one );
		$before = $this->metadata_snapshot( $course_id );

		$fields = $this->changed_form_fields( $provider_two, $instructor_two );

		$fields[ CourseMetaBox::START_DATES_FIELD ] = $invalid_dates;

		$this->submit_course_form( $administrator_id, $course_id, $fields );

		self::assertSame( $before, $this->metadata_snapshot( $course_id ) );
		$this->assert_invalid_request_was_flagged();
	}

	/**
	 * Invalid start-date request examples.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function invalid_start_dates_provider(): array {
		return array(
			'impossible month' => array( array( '2026-13' ) ),
			'scalar field'     => array( '2027-01' ),
			'non-string row'   => array( array( 202701 ) ),
		);
	}

	/**
	 * A valid nonce is not authorization for a user who cannot edit the Course.
	 */
	public function test_unauthorized_user_cannot_modify_course_metadata(): void {
		$subscriber_id  = $this->create_user( 'subscriber' );
		$course_id      = $this->create_post( CoursePostType::POST_TYPE, 'Admin Course' );
		$provider_one   = $this->create_post( ProviderPostType::POST_TYPE, 'Provider One' );
		$provider_two   = $this->create_post( ProviderPostType::POST_TYPE, 'Provider Two' );
		$instructor_one = $this->create_post( InstructorPostType::POST_TYPE, 'Instructor One' );
		$instructor_two = $this->create_post( InstructorPostType::POST_TYPE, 'Instructor Two' );

		$this->seed_metadata( $course_id, $provider_one, $instructor_one );
		$before = $this->metadata_snapshot( $course_id );

		self::assertFalse( user_can( $subscriber_id, 'edit_post', $course_id ) );

		$this->submit_course_form(
			$subscriber_id,
			$course_id,
			$this->changed_form_fields( $provider_two, $instructor_two )
		);

		self::assertSame( $before, $this->metadata_snapshot( $course_id ) );
	}

	/**
	 * A missing or invalid nonce rejects the complete request.
	 *
	 * @dataProvider rejected_nonce_provider
	 *
	 * @param bool        $include_nonce Whether the request contains the nonce field.
	 * @param string|null $nonce         Nonce override.
	 */
	public function test_missing_or_invalid_nonce_preserves_all_metadata(
		bool $include_nonce,
		?string $nonce
	): void {
		$administrator_id = $this->create_user( 'administrator' );
		$course_id        = $this->create_post( CoursePostType::POST_TYPE, 'Admin Course' );
		$provider_one     = $this->create_post( ProviderPostType::POST_TYPE, 'Provider One' );
		$provider_two     = $this->create_post( ProviderPostType::POST_TYPE, 'Provider Two' );
		$instructor_one   = $this->create_post( InstructorPostType::POST_TYPE, 'Instructor One' );
		$instructor_two   = $this->create_post( InstructorPostType::POST_TYPE, 'Instructor Two' );

		$this->seed_metadata( $course_id, $provider_one, $instructor_one );
		$before = $this->metadata_snapshot( $course_id );

		$this->submit_course_form(
			$administrator_id,
			$course_id,
			$this->changed_form_fields( $provider_two, $instructor_two ),
			$include_nonce,
			$nonce
		);

		self::assertSame( $before, $this->metadata_snapshot( $course_id ) );
	}

	/**
	 * Rejected nonce examples.
	 *
	 * @return array<string, array{bool, string|null}>
	 */
	public static function rejected_nonce_provider(): array {
		return array(
			'missing nonce' => array( false, null ),
			'invalid nonce' => array( true, 'invalid-course-details-nonce' ),
		);
	}

	/**
	 * Autosave and revision objects cannot modify their parent Course metadata.
	 */
	public function test_autosave_and_revision_paths_preserve_course_metadata(): void {
		$administrator_id = $this->create_user( 'administrator' );
		$course_id        = $this->create_post( CoursePostType::POST_TYPE, 'Admin Course' );
		$provider_one     = $this->create_post( ProviderPostType::POST_TYPE, 'Provider One' );
		$provider_two     = $this->create_post( ProviderPostType::POST_TYPE, 'Provider Two' );
		$instructor_one   = $this->create_post( InstructorPostType::POST_TYPE, 'Instructor One' );
		$instructor_two   = $this->create_post( InstructorPostType::POST_TYPE, 'Instructor Two' );
		$revision_id      = $this->create_post(
			'revision',
			'Course revision',
			array(
				'post_status' => 'inherit',
				'post_parent' => $course_id,
				'post_name'   => $course_id . '-revision-v1',
			)
		);
		$autosave_id      = $this->create_post(
			'revision',
			'Course autosave',
			array(
				'post_status' => 'inherit',
				'post_parent' => $course_id,
				'post_name'   => $course_id . '-autosave-v1',
			)
		);

		$this->seed_metadata( $course_id, $provider_one, $instructor_one );
		$before = $this->metadata_snapshot( $course_id );

		self::assertSame( $course_id, wp_is_post_revision( $revision_id ) );
		self::assertSame( $course_id, wp_is_post_autosave( $autosave_id ) );

		$changed_fields = $this->changed_form_fields( $provider_two, $instructor_two );
		$this->fire_course_save_hook(
			$administrator_id,
			$this->post( $revision_id ),
			$changed_fields
		);
		$this->fire_course_save_hook(
			$administrator_id,
			$this->post( $autosave_id ),
			$changed_fields
		);

		self::assertSame( $before, $this->metadata_snapshot( $course_id ) );
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

	/**
	 * Create one post fixture.
	 *
	 * @param string               $post_type Registered post type.
	 * @param string               $title     Post title.
	 * @param array<string, mixed> $arguments Additional post arguments.
	 */
	private function create_post( string $post_type, string $title, array $arguments = array() ): int {
		return self::factory()->post->create(
			array_merge(
				array(
					'post_type'   => $post_type,
					'post_status' => 'publish',
					'post_title'  => $title,
				),
				$arguments
			)
		);
	}

	/**
	 * Load a post fixture as an object.
	 *
	 * @param int $post_id Post identifier.
	 */
	private function post( int $post_id ): WP_Post {
		$post = get_post( $post_id );

		self::assertInstanceOf( WP_Post::class, $post );

		return $post;
	}

	/**
	 * Submit a Course edit through WordPress's real update and save hooks.
	 *
	 * @param int                  $user_id       Current user identifier.
	 * @param int                  $course_id     Course identifier.
	 * @param array<string, mixed> $fields        Course metadata request fields.
	 * @param bool                 $include_nonce Whether to add the nonce field.
	 * @param string|null          $nonce         Nonce override, or null to create a valid nonce.
	 */
	private function submit_course_form(
		int $user_id,
		int $course_id,
		array $fields,
		bool $include_nonce = true,
		?string $nonce = null
	): void {
		wp_set_current_user( $user_id );

		if ( $include_nonce ) {
			$fields[ CourseMetaBox::NONCE_NAME ] = null === $nonce
				? wp_create_nonce( CourseMetaBox::NONCE_ACTION )
				: $nonce;
		}

		$_POST = $fields;

		try {
			$updated = wp_update_post(
				array(
					'ID'         => $course_id,
					'post_title' => 'Admin save ' . $course_id,
				),
				true
			);

			self::assertSame( $course_id, $updated );
		} finally {
			$_POST = array();
			wp_set_current_user( 0 );
		}
	}

	/**
	 * Invoke the registered Course save callback with a revision-like object.
	 *
	 * @param int                  $user_id Current user identifier.
	 * @param WP_Post              $post    Revision or autosave object.
	 * @param array<string, mixed> $fields  Course metadata request fields.
	 */
	private function fire_course_save_hook( int $user_id, WP_Post $post, array $fields ): void {
		wp_set_current_user( $user_id );
		$fields[ CourseMetaBox::NONCE_NAME ] = wp_create_nonce( CourseMetaBox::NONCE_ACTION );

		$_POST = $fields;

		try {
			do_action(
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress defines this post-type-specific core hook.
				'save_post_' . CoursePostType::POST_TYPE,
				$post->ID,
				$post,
				true
			);
		} finally {
			$_POST = array();
			wp_set_current_user( 0 );
		}
	}

	/**
	 * Build the raw shape emitted by the Course metadata form.
	 *
	 * @param string $price       Price field.
	 * @param array  $providers   Provider identifier fields.
	 * @param array  $instructors Instructor identifier fields.
	 * @param array  $start_dates Start-date fields.
	 *
	 * @phpstan-param list<string> $providers
	 * @phpstan-param list<string> $instructors
	 * @phpstan-param list<string> $start_dates
	 *
	 * @return array<string, mixed>
	 */
	private function form_fields(
		string $price,
		array $providers,
		array $instructors,
		array $start_dates
	): array {
		return array(
			CourseMetaBox::PRICE_FIELD       => $price,
			CourseMetaBox::PROVIDERS_FIELD   => $providers,
			CourseMetaBox::INSTRUCTORS_FIELD => $instructors,
			CourseMetaBox::START_DATES_FIELD => $start_dates,
		);
	}

	/**
	 * Build a valid request that differs from the seeded metadata in every field.
	 *
	 * @param int $provider_id   Replacement Provider identifier.
	 * @param int $instructor_id Replacement Instructor identifier.
	 *
	 * @return array<string, mixed>
	 */
	private function changed_form_fields( int $provider_id, int $instructor_id ): array {
		return $this->form_fields(
			'99.99',
			array( '', (string) $provider_id ),
			array( '', (string) $instructor_id ),
			array( '2027-01' )
		);
	}

	/**
	 * Assert that invalid form input is carried to the post-edit redirect.
	 */
	private function assert_invalid_request_was_flagged(): void {
		$location = apply_filters(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This invokes WordPress's core post-edit redirect filter.
			'redirect_post_location',
			'post.php?action=edit'
		);

		self::assertIsString( $location );
		self::assertStringContainsString(
			CourseMetaSaveHandler::ERROR_QUERY_ARG . '=' . CourseMetaSaveHandler::ERROR_CODE,
			$location
		);
	}

	/**
	 * Seed a non-empty metadata snapshot for rejection tests.
	 *
	 * @param int $course_id     Course identifier.
	 * @param int $provider_id   Provider identifier.
	 * @param int $instructor_id Instructor identifier.
	 */
	private function seed_metadata( int $course_id, int $provider_id, int $instructor_id ): void {
		$course = new CourseId( $course_id );
		$store  = new CourseMetadataStore();

		$store->save_price( $course, Price::from_decimal( '10' ) );
		$store->replace_providers( $course, new ProviderId( $provider_id ) );
		$store->replace_instructors( $course, new InstructorId( $instructor_id ) );
		$store->replace_start_dates( $course, new StartDate( '2026-09' ) );
	}

	/**
	 * Read every Course metadata contract through the typed persistence boundary.
	 *
	 * @param int $course_id Course identifier.
	 *
	 * @return array{
	 *     price: string|null,
	 *     providers: list<int>,
	 *     instructors: list<int>,
	 *     start_dates: list<string>
	 * }
	 */
	private function metadata_snapshot( int $course_id ): array {
		$course = new CourseId( $course_id );
		$store  = new CourseMetadataStore();
		$price  = $store->price( $course );

		return array(
			'price'       => null === $price ? null : $price->decimal(),
			'providers'   => array_map(
				static fn ( ProviderId $provider_id ): int => $provider_id->value(),
				$store->providers( $course )
			),
			'instructors' => array_map(
				static fn ( InstructorId $instructor_id ): int => $instructor_id->value(),
				$store->instructors( $course )
			),
			'start_dates' => array_map(
				static fn ( StartDate $start_date ): string => $start_date->value(),
				$store->start_dates( $course )
			),
		);
	}
}
