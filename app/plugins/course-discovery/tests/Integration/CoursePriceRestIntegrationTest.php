<?php
/**
 * Integration tests for Course price writes through the WordPress REST API.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Integration;

use OxfordInternational\CourseDiscovery\Domain\Course\CourseId;
use OxfordInternational\CourseDiscovery\Domain\Course\Currency;
use OxfordInternational\CourseDiscovery\Domain\Course\Price;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMeta;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMetadataStore;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CoursePostType;
use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

/**
 * Verifies that REST mutations cannot create a partial Course price.
 */
final class CoursePriceRestIntegrationTest extends WP_UnitTestCase {
	/**
	 * A complete amount and supported currency can be created together.
	 */
	public function test_rest_accepts_a_complete_price_pair(): void {
		$request = new WP_REST_Request( 'POST', '/wp/v2/' . CoursePostType::POST_TYPE );
		$request->set_body_params(
			array(
				'title'  => 'REST-created Course',
				'status' => 'draft',
				'meta'   => array(
					CourseMeta::PRICE_AMOUNT_KEY   => '1250.00',
					CourseMeta::PRICE_CURRENCY_KEY => 'GBP',
				),
			)
		);

		$response = $this->dispatch( $request );

		self::assertSame( 201, $response->get_status() );

		$data = $response->get_data();

		self::assertIsArray( $data );
		self::assertArrayHasKey( 'id', $data );
		self::assertIsInt( $data['id'] );
		self::assertSame( '1250', get_post_meta( $data['id'], CourseMeta::PRICE_AMOUNT_KEY, true ) );
		self::assertSame( 'GBP', get_post_meta( $data['id'], CourseMeta::PRICE_CURRENCY_KEY, true ) );
	}

	/**
	 * Neither half of a new price can be written on its own.
	 *
	 * @dataProvider single_price_field_provider
	 *
	 * @param string $meta_key Price metadata key.
	 * @param string $value    Otherwise valid scalar value.
	 */
	public function test_rest_rejects_a_single_price_field_against_empty_state(
		string $meta_key,
		string $value
	): void {
		$course_id = $this->create_course();
		$response  = $this->update_price_meta( $course_id, array( $meta_key => $value ) );

		self::assertSame( 400, $response->get_status() );
		self::assertFalse( metadata_exists( 'post', $course_id, CourseMeta::PRICE_AMOUNT_KEY ) );
		self::assertFalse( metadata_exists( 'post', $course_id, CourseMeta::PRICE_CURRENCY_KEY ) );
	}

	/**
	 * Individual price fields and otherwise valid values.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function single_price_field_provider(): array {
		return array(
			'amount only'   => array( CourseMeta::PRICE_AMOUNT_KEY, '1250' ),
			'currency only' => array( CourseMeta::PRICE_CURRENCY_KEY, 'GBP' ),
		);
	}

	/**
	 * Either scalar can be changed when the stored pair remains complete.
	 */
	public function test_rest_allows_single_field_updates_against_a_complete_price(): void {
		$course_id = $this->create_course();
		$store     = new CourseMetadataStore();

		$store->save_price( new CourseId( $course_id ), Price::from_decimal( '100', Currency::GBP ) );

		$amount_response = $this->update_price_meta(
			$course_id,
			array( CourseMeta::PRICE_AMOUNT_KEY => '125.50' )
		);

		self::assertSame( 200, $amount_response->get_status() );
		self::assertSame( '125.5', get_post_meta( $course_id, CourseMeta::PRICE_AMOUNT_KEY, true ) );
		self::assertSame( 'GBP', get_post_meta( $course_id, CourseMeta::PRICE_CURRENCY_KEY, true ) );

		$currency_response = $this->update_price_meta(
			$course_id,
			array( CourseMeta::PRICE_CURRENCY_KEY => 'USD' )
		);

		self::assertSame( 200, $currency_response->get_status() );
		self::assertSame( '125.5', get_post_meta( $course_id, CourseMeta::PRICE_AMOUNT_KEY, true ) );
		self::assertSame( 'USD', get_post_meta( $course_id, CourseMeta::PRICE_CURRENCY_KEY, true ) );
	}

	/**
	 * Clearing one half is rejected, while clearing both removes the Price.
	 */
	public function test_rest_requires_both_fields_when_clearing_a_price(): void {
		$course_id = $this->create_course();
		$store     = new CourseMetadataStore();

		$store->save_price( new CourseId( $course_id ), Price::from_decimal( '980', Currency::EUR ) );

		$partial_response = $this->update_price_meta(
			$course_id,
			array( CourseMeta::PRICE_AMOUNT_KEY => null )
		);

		self::assertSame( 400, $partial_response->get_status() );
		self::assertSame( '980', get_post_meta( $course_id, CourseMeta::PRICE_AMOUNT_KEY, true ) );
		self::assertSame( 'EUR', get_post_meta( $course_id, CourseMeta::PRICE_CURRENCY_KEY, true ) );

		$clear_response = $this->update_price_meta(
			$course_id,
			array(
				CourseMeta::PRICE_AMOUNT_KEY   => null,
				CourseMeta::PRICE_CURRENCY_KEY => null,
			)
		);

		self::assertSame( 200, $clear_response->get_status() );
		self::assertFalse( metadata_exists( 'post', $course_id, CourseMeta::PRICE_AMOUNT_KEY ) );
		self::assertFalse( metadata_exists( 'post', $course_id, CourseMeta::PRICE_CURRENCY_KEY ) );
	}

	/**
	 * The REST schema rejects unsupported currency without changing either row.
	 */
	public function test_rest_rejects_an_unsupported_currency(): void {
		$course_id = $this->create_course();
		$response  = $this->update_price_meta(
			$course_id,
			array(
				CourseMeta::PRICE_AMOUNT_KEY   => '1400',
				CourseMeta::PRICE_CURRENCY_KEY => 'CAD',
			)
		);

		self::assertSame( 400, $response->get_status() );
		self::assertFalse( metadata_exists( 'post', $course_id, CourseMeta::PRICE_AMOUNT_KEY ) );
		self::assertFalse( metadata_exists( 'post', $course_id, CourseMeta::PRICE_CURRENCY_KEY ) );
	}

	/**
	 * Create an empty published Course fixture.
	 */
	private function create_course(): int {
		return self::factory()->post->create(
			array(
				'post_type'   => CoursePostType::POST_TYPE,
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Dispatch a real WordPress REST update for Course metadata.
	 *
	 * @param int                  $course_id Course identifier.
	 * @param array<string, mixed> $meta      REST metadata mutation.
	 */
	private function update_price_meta( int $course_id, array $meta ): WP_REST_Response {
		$request = new WP_REST_Request(
			'POST',
			'/wp/v2/' . CoursePostType::POST_TYPE . '/' . $course_id
		);
		$request->set_body_params( array( 'meta' => $meta ) );

		return $this->dispatch( $request );
	}

	/**
	 * Dispatch an authenticated request with the tested metadata registered.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	private function dispatch( WP_REST_Request $request ): WP_REST_Response {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'course-discovery-rest-' . wp_generate_password( 12, false, false ),
				'user_pass'  => wp_generate_password( 20 ),
				'role'       => 'administrator',
			)
		);

		self::assertIsInt( $user_id );

		( new CourseMeta() )->register();
		wp_set_current_user( $user_id );

		try {
			return rest_do_request( $request );
		} finally {
			wp_set_current_user( 0 );
		}
	}
}
