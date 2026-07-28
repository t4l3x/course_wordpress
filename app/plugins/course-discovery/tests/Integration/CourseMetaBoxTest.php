<?php
/**
 * Integration tests for the Course administration metadata box.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Integration;

use OxfordInternational\CourseDiscovery\Domain\Course\CourseId;
use OxfordInternational\CourseDiscovery\Domain\Course\Currency;
use OxfordInternational\CourseDiscovery\Domain\Course\Price;
use OxfordInternational\CourseDiscovery\Domain\Course\StartDate;
use OxfordInternational\CourseDiscovery\Domain\Instructor\InstructorId;
use OxfordInternational\CourseDiscovery\Domain\Provider\ProviderId;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Admin\CourseMetaBox;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMetadataStore;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CoursePostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\InstructorPostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\ProviderPostType;
use WP_Post;
use WP_UnitTestCase;

/**
 * Verifies the Course-only placement and accessible native admin controls.
 */
final class CourseMetaBoxTest extends WP_UnitTestCase {
	/**
	 * The metadata boxes render accessible controls only on Course screens.
	 */
	public function test_course_meta_box_is_accessible_and_registered_for_courses_only(): void {
		require_once ABSPATH . 'wp-admin/includes/template.php';

		$course_id     = $this->create_post( CoursePostType::POST_TYPE, 'Admin Course' );
		$provider_id   = $this->create_post( ProviderPostType::POST_TYPE, 'Provider & Partners' );
		$instructor_id = $this->create_post( InstructorPostType::POST_TYPE, 'Instructor One' );
		$store         = new CourseMetadataStore();
		$course        = new CourseId( $course_id );

		$store->save_price( $course, Price::from_decimal( '125.50', Currency::EUR ) );
		$store->replace_providers( $course, new ProviderId( $provider_id ) );
		$store->replace_instructors( $course, new InstructorId( $instructor_id ) );
		$store->replace_start_dates(
			$course,
			new StartDate( '2027-01' ),
			new StartDate( '2026-09' )
		);

		$course_html = $this->registered_meta_boxes_html( $this->post( $course_id ) );

		self::assertStringContainsString( 'id="' . CourseMetaBox::PRICE_META_BOX_ID . '"', $course_html );
		self::assertStringContainsString(
			'id="' . CourseMetaBox::RELATIONSHIPS_META_BOX_ID . '"',
			$course_html
		);
		self::assertStringContainsString(
			'id="' . CourseMetaBox::START_DATES_META_BOX_ID . '"',
			$course_html
		);
		self::assertStringNotContainsString( 'id="postcustom"', $course_html );
		self::assertStringContainsString(
			'name="' . CourseMetaBox::NONCE_NAME . '"',
			$course_html
		);
		self::assertStringContainsString( '<label for="course-discovery-price-amount">', $course_html );
		self::assertStringContainsString( '<label for="course-discovery-price-currency">', $course_html );
		self::assertStringContainsString( '<label for="course-discovery-providers">', $course_html );
		self::assertStringContainsString( '<label for="course-discovery-instructors">', $course_html );
		self::assertStringContainsString(
			'name="' . CourseMetaBox::PRICE_AMOUNT_FIELD . '"',
			$course_html
		);
		self::assertStringContainsString(
			'name="' . CourseMetaBox::PRICE_CURRENCY_FIELD . '"',
			$course_html
		);
		self::assertStringContainsString(
			'name="' . CourseMetaBox::PROVIDERS_FIELD . '[]"',
			$course_html
		);
		self::assertStringContainsString(
			'name="' . CourseMetaBox::INSTRUCTORS_FIELD . '[]"',
			$course_html
		);
		self::assertStringContainsString(
			'name="' . CourseMetaBox::START_DATES_FIELD . '[]"',
			$course_html
		);
		self::assertStringContainsString( 'multiple', $course_html );
		self::assertSame( 2, substr_count( $course_html, 'size="2"' ) );
		self::assertStringNotContainsString( 'size="6"', $course_html );
		self::assertStringContainsString( 'type="month"', $course_html );
		self::assertStringContainsString( 'data-course-discovery-add-start-date', $course_html );
		self::assertStringContainsString( 'data-course-discovery-remove-start-date', $course_html );
		self::assertStringContainsString( 'Add start month', $course_html );
		self::assertStringContainsString( 'Remove start month', $course_html );
		self::assertStringContainsString( 'value="125.5"', $course_html );
		self::assertStringContainsString( 'value="GBP"', $course_html );
		self::assertStringContainsString( 'value="EUR"', $course_html );
		self::assertStringContainsString( 'value="USD"', $course_html );
		self::assertMatchesRegularExpression(
			'/value="EUR"\s+selected=\'selected\'/',
			$course_html
		);
		self::assertStringContainsString( 'value="' . $provider_id . '"', $course_html );
		self::assertStringContainsString( 'value="' . $instructor_id . '"', $course_html );
		self::assertSame( 3, substr_count( $course_html, "selected='selected'" ) );
		self::assertStringContainsString( 'value="2026-09"', $course_html );
		self::assertStringContainsString( 'value="2027-01"', $course_html );
		self::assertStringContainsString( 'Provider &amp; Partners', $course_html );
		self::assertStringNotContainsString( 'Provider & Partners', $course_html );

		$provider_html   = $this->registered_meta_boxes_html( $this->post( $provider_id ) );
		$instructor_html = $this->registered_meta_boxes_html( $this->post( $instructor_id ) );

		$meta_box_ids = array(
			CourseMetaBox::PRICE_META_BOX_ID,
			CourseMetaBox::RELATIONSHIPS_META_BOX_ID,
			CourseMetaBox::START_DATES_META_BOX_ID,
		);

		foreach ( $meta_box_ids as $meta_box_id ) {
			self::assertStringNotContainsString( $meta_box_id, $provider_html );
			self::assertStringNotContainsString( $meta_box_id, $instructor_html );
		}
		self::assertStringNotContainsString( CourseMetaBox::PRICE_AMOUNT_FIELD, $provider_html );
		self::assertStringNotContainsString( CourseMetaBox::PRICE_AMOUNT_FIELD, $instructor_html );
		self::assertStringNotContainsString( CourseMetaBox::PRICE_CURRENCY_FIELD, $provider_html );
		self::assertStringNotContainsString( CourseMetaBox::PRICE_CURRENCY_FIELD, $instructor_html );
	}

	/**
	 * Empty relationship lists use concise guidance instead of blank selectors.
	 */
	public function test_relationship_box_has_compact_empty_states(): void {
		$course_id = $this->create_post( CoursePostType::POST_TYPE, 'Course Without Relationships' );
		$meta_box  = new CourseMetaBox( new CourseMetadataStore() );
		$html      = '';

		ob_start();

		try {
			$meta_box->render_relationships( $this->post( $course_id ) );

			$contents = ob_get_contents();
			self::assertIsString( $contents );
			$html = $contents;
		} finally {
			ob_end_clean();
		}

		self::assertStringContainsString( 'No providers are available.', $html );
		self::assertStringContainsString( 'No instructors are available.', $html );
		self::assertStringContainsString(
			'name="' . CourseMetaBox::PROVIDERS_FIELD . '[]"',
			$html
		);
		self::assertStringContainsString(
			'name="' . CourseMetaBox::INSTRUCTORS_FIELD . '[]"',
			$html
		);
		self::assertStringNotContainsString( '<select', $html );
	}

	/**
	 * Create one published post fixture.
	 *
	 * @param string $post_type Registered post type.
	 * @param string $title     Post title.
	 */
	private function create_post( string $post_type, string $title ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
				'post_title'  => $title,
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
	 * Fire the real meta-box registration hook and capture its rendered output.
	 *
	 * @param WP_Post $post Administration-screen post.
	 */
	private function registered_meta_boxes_html( WP_Post $post ): string {
		if ( CoursePostType::POST_TYPE === $post->post_type ) {
			add_meta_box(
				'postcustom',
				'Custom Fields',
				static function (): void {},
				CoursePostType::POST_TYPE,
				'normal',
				'core'
			);
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress defines this post-type-specific core hook.
		do_action( 'add_meta_boxes_' . $post->post_type, $post );

		$html = '';
		ob_start();

		try {
			do_meta_boxes( $post->post_type, 'normal', $post );
			do_meta_boxes( $post->post_type, 'side', $post );

			$contents = ob_get_contents();
			self::assertIsString( $contents );
			$html = $contents;
		} finally {
			ob_end_clean();
		}

		return $html;
	}
}
