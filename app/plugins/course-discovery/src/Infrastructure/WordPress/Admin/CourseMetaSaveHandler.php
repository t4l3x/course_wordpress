<?php
/**
 * Course administration metadata save boundary.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Admin;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Domain\Course\CourseId;
use OxfordInternational\CourseDiscovery\Domain\Course\Currency;
use OxfordInternational\CourseDiscovery\Domain\Course\Price;
use OxfordInternational\CourseDiscovery\Domain\Course\StartDate;
use OxfordInternational\CourseDiscovery\Domain\Instructor\InstructorId;
use OxfordInternational\CourseDiscovery\Domain\Provider\ProviderId;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMetadataStore;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CoursePostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\InstructorPostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\ProviderPostType;
use WP_Post;

/**
 * Validates Course admin requests before passing typed values to persistence.
 */
final class CourseMetaSaveHandler {
	public const string ERROR_QUERY_ARG = 'course_discovery_error';
	public const string ERROR_CODE      = 'invalid_course_details';

	/**
	 * Create the Course metadata save handler.
	 *
	 * @param CourseMetadataStore $metadata_store Course metadata persistence.
	 */
	public function __construct(
		private CourseMetadataStore $metadata_store
	) {
	}

	/**
	 * Persist a valid Course metadata request.
	 *
	 * @param int     $post_id Course post identifier.
	 * @param WP_Post $post    Course post.
	 * @param bool    $update  Whether WordPress is updating an existing post.
	 */
	public function save( int $post_id, WP_Post $post, bool $update ): void {
		unset( $update );

		if (
			CoursePostType::POST_TYPE !== $post->post_type
			|| CoursePostType::POST_TYPE !== get_post_type( $post_id )
			|| false !== wp_is_post_revision( $post_id )
			|| false !== wp_is_post_autosave( $post_id )
			|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| ! $this->has_valid_nonce()
			|| ! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is verified above before form input is read.
		$request = wp_unslash( $_POST );

		try {
			$price       = $this->price_from_request( $request );
			$providers   = array_map(
				static fn ( int $provider_id ): ProviderId => new ProviderId( $provider_id ),
				$this->relationship_ids_from_request(
					$request,
					CourseMetaBox::PROVIDERS_FIELD,
					ProviderPostType::POST_TYPE
				)
			);
			$instructors = array_map(
				static fn ( int $instructor_id ): InstructorId => new InstructorId( $instructor_id ),
				$this->relationship_ids_from_request(
					$request,
					CourseMetaBox::INSTRUCTORS_FIELD,
					InstructorPostType::POST_TYPE
				)
			);
			$start_dates = $this->start_dates_from_request( $request );
		} catch ( InvalidArgumentException ) {
			$this->flag_invalid_request();

			return;
		}

		$course_id = new CourseId( $post_id );

		if ( null === $price ) {
			$this->metadata_store->remove_price( $course_id );
		} else {
			$this->metadata_store->save_price( $course_id, $price );
		}

		$this->metadata_store->replace_providers( $course_id, ...$providers );
		$this->metadata_store->replace_instructors( $course_id, ...$instructors );
		$this->metadata_store->replace_start_dates( $course_id, ...$start_dates );
	}

	/**
	 * Render a native error notice after rejected Course metadata input.
	 */
	public function render_notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- This read-only flag cannot change state.
		$error_code = isset( $_GET[ self::ERROR_QUERY_ARG ] ) && is_string( $_GET[ self::ERROR_QUERY_ARG ] )
			? sanitize_key( wp_unslash( $_GET[ self::ERROR_QUERY_ARG ] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$screen = get_current_screen();

		if (
			self::ERROR_CODE !== $error_code
			|| null === $screen
			|| CoursePostType::POST_TYPE !== $screen->post_type
		) {
			return;
		}
		?>
		<div class="notice notice-error is-dismissible">
			<p>
				<?php
				esc_html_e(
					'Course details were not saved because one or more values were invalid. Review the fields and try again.',
					'course-discovery'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Verify the Course details nonce.
	 */
	private function has_valid_nonce(): bool {
		if (
			! isset( $_POST[ CourseMetaBox::NONCE_NAME ] )
			|| ! is_string( $_POST[ CourseMetaBox::NONCE_NAME ] )
		) {
			return false;
		}

		$nonce = sanitize_text_field(
			wp_unslash( $_POST[ CourseMetaBox::NONCE_NAME ] )
		);

		return false !== wp_verify_nonce( $nonce, CourseMetaBox::NONCE_ACTION );
	}

	/**
	 * Mark the post-save redirect so WordPress can explain a rejected request.
	 */
	private function flag_invalid_request(): void {
		add_filter(
			'redirect_post_location',
			static fn ( string $location ): string => add_query_arg(
				self::ERROR_QUERY_ARG,
				self::ERROR_CODE,
				remove_query_arg( 'message', $location )
			)
		);
	}

	/**
	 * Parse an optional price from the request.
	 *
	 * @param array<string, mixed> $request Unslashed request input.
	 *
	 * @throws InvalidArgumentException When the field is missing or invalid.
	 */
	private function price_from_request( array $request ): ?Price {
		$amount        = $request[ CourseMetaBox::PRICE_AMOUNT_FIELD ] ?? null;
		$currency_code = $request[ CourseMetaBox::PRICE_CURRENCY_FIELD ] ?? null;

		if ( ! is_string( $amount ) || ! is_string( $currency_code ) ) {
			throw new InvalidArgumentException( 'The Course price fields are invalid.' );
		}

		$currency = Currency::tryFrom( $currency_code );

		if ( null === $currency ) {
			throw new InvalidArgumentException( 'The Course price currency is unsupported.' );
		}

		return '' === $amount ? null : Price::from_decimal( $amount, $currency );
	}

	/**
	 * Parse and validate relationship identifiers from the request.
	 *
	 * @param array<string, mixed> $request    Unslashed request input.
	 * @param string               $field_name Request field name.
	 * @param string               $post_type  Required related post type.
	 *
	 * @return list<int>
	 *
	 * @throws InvalidArgumentException When the field or a related post is invalid.
	 */
	private function relationship_ids_from_request( array $request, string $field_name, string $post_type ): array {
		$values = $request[ $field_name ] ?? null;

		if ( ! is_array( $values ) ) {
			throw new InvalidArgumentException( 'A Course relationship field is invalid.' );
		}

		$identifiers = array();

		foreach ( $values as $value ) {
			if ( ! is_string( $value ) ) {
				throw new InvalidArgumentException( 'A Course relationship identifier must be a string.' );
			}

			if ( '' === $value ) {
				continue;
			}

			$identifier = filter_var(
				$value,
				FILTER_VALIDATE_INT,
				array(
					'options' => array(
						'min_range' => 1,
					),
				)
			);

			if ( false === $identifier ) {
				throw new InvalidArgumentException( 'A Course relationship identifier is invalid.' );
			}

			$related_post = get_post( $identifier );

			if ( ! $related_post instanceof WP_Post || $post_type !== $related_post->post_type ) {
				throw new InvalidArgumentException( 'A Course relationship points to the wrong content type.' );
			}

			$identifiers[ $identifier ] = $identifier;
		}

		return array_values( $identifiers );
	}

	/**
	 * Parse start months from the request.
	 *
	 * @param array<string, mixed> $request Unslashed request input.
	 *
	 * @return list<StartDate>
	 *
	 * @throws InvalidArgumentException When the field or a start month is invalid.
	 */
	private function start_dates_from_request( array $request ): array {
		$values = $request[ CourseMetaBox::START_DATES_FIELD ] ?? null;

		if ( ! is_array( $values ) ) {
			throw new InvalidArgumentException( 'The Course start dates field is invalid.' );
		}

		$dates = array();

		foreach ( $values as $value ) {
			if ( ! is_string( $value ) ) {
				throw new InvalidArgumentException( 'A Course start date must be a string.' );
			}

			if ( '' === $value ) {
				continue;
			}

			$start_date                    = new StartDate( $value );
			$dates[ $start_date->value() ] = $start_date;
		}

		return array_values( $dates );
	}
}
