<?php
/**
 * Course administration metadata controls.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Admin;

use OxfordInternational\CourseDiscovery\Domain\Course\CourseId;
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
 * Registers and renders the Course-specific metadata controls.
 */
final class CourseMetaBox {
	public const string PRICE_META_BOX_ID         = 'course_discovery_course_price';
	public const string RELATIONSHIPS_META_BOX_ID = 'course_discovery_course_relationships';
	public const string START_DATES_META_BOX_ID   = 'course_discovery_course_start_dates';
	public const string NONCE_ACTION              = 'course_discovery_save_course_details';
	public const string NONCE_NAME                = 'course_discovery_course_details_nonce';
	public const string PRICE_FIELD               = 'course_discovery_price';
	public const string PROVIDERS_FIELD           = 'course_discovery_provider_ids';
	public const string INSTRUCTORS_FIELD         = 'course_discovery_instructor_ids';
	public const string START_DATES_FIELD         = 'course_discovery_start_dates';

	/**
	 * Create the Course metadata box.
	 *
	 * @param CourseMetadataStore $metadata_store Course metadata persistence.
	 */
	public function __construct(
		private CourseMetadataStore $metadata_store
	) {
	}

	/**
	 * Register the metadata boxes on Course screens.
	 *
	 * @param WP_Post $post Course being edited.
	 */
	public function register( WP_Post $post ): void {
		if ( CoursePostType::POST_TYPE !== $post->post_type ) {
			return;
		}

		// Course needs custom-fields support for registered REST meta, not the generic editor UI.
		remove_meta_box( 'postcustom', CoursePostType::POST_TYPE, 'normal' );

		add_meta_box(
			self::PRICE_META_BOX_ID,
			__( 'Price', 'course-discovery' ),
			array( $this, 'render_price' ),
			CoursePostType::POST_TYPE,
			'side',
			'default'
		);
		add_meta_box(
			self::RELATIONSHIPS_META_BOX_ID,
			__( 'Providers & Instructors', 'course-discovery' ),
			array( $this, 'render_relationships' ),
			CoursePostType::POST_TYPE,
			'normal',
			'default'
		);
		add_meta_box(
			self::START_DATES_META_BOX_ID,
			__( 'Start dates', 'course-discovery' ),
			array( $this, 'render_start_dates' ),
			CoursePostType::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Render the Course price control.
	 *
	 * @param WP_Post $post Course being edited.
	 */
	public function render_price( WP_Post $post ): void {
		$this->render_price_field(
			$this->metadata_store->price( new CourseId( $post->ID ) )
		);
	}

	/**
	 * Render the Course relationship controls.
	 *
	 * @param WP_Post $post Course being edited.
	 */
	public function render_relationships( WP_Post $post ): void {
		$course_id      = new CourseId( $post->ID );
		$provider_ids   = array_map(
			static fn ( ProviderId $provider_id ): int => $provider_id->value(),
			$this->metadata_store->providers( $course_id )
		);
		$instructor_ids = array_map(
			static fn ( InstructorId $instructor_id ): int => $instructor_id->value(),
			$this->metadata_store->instructors( $course_id )
		);
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$this->render_relationship_select(
			'course-discovery-providers',
			self::PROVIDERS_FIELD,
			__( 'Providers', 'course-discovery' ),
			__(
				'Select one or more providers. Course locations are derived from the selected providers.',
				'course-discovery'
			),
			__( 'No providers are available. Create a Provider first.', 'course-discovery' ),
			$this->related_posts( ProviderPostType::POST_TYPE ),
			$provider_ids
		);
		$this->render_relationship_select(
			'course-discovery-instructors',
			self::INSTRUCTORS_FIELD,
			__( 'Instructors', 'course-discovery' ),
			__( 'Select one or more instructors for this course.', 'course-discovery' ),
			__( 'No instructors are available. Create an Instructor first.', 'course-discovery' ),
			$this->related_posts( InstructorPostType::POST_TYPE ),
			$instructor_ids
		);
	}

	/**
	 * Render the Course start-date controls.
	 *
	 * @param WP_Post $post Course being edited.
	 */
	public function render_start_dates( WP_Post $post ): void {
		$this->render_start_date_controls(
			$this->metadata_store->start_dates( new CourseId( $post->ID ) )
		);
	}

	/**
	 * Render the canonical decimal price field.
	 *
	 * @param Price|null $price Existing Course price.
	 */
	private function render_price_field( ?Price $price ): void {
		$value = null === $price ? '' : $price->decimal();
		?>
		<p>
			<label for="course-discovery-price">
				<strong><?php esc_html_e( 'Price', 'course-discovery' ); ?></strong>
			</label>
		</p>
		<input
			class="widefat"
			id="course-discovery-price"
			name="<?php echo esc_attr( self::PRICE_FIELD ); ?>"
			type="number"
			min="0"
			step="any"
			inputmode="decimal"
			value="<?php echo esc_attr( $value ); ?>"
			aria-describedby="course-discovery-price-description"
		/>
		<p class="description" id="course-discovery-price-description">
			<?php
			esc_html_e(
				'Enter a non-negative decimal amount. No currency or display precision is assumed. Leave blank for no price.',
				'course-discovery'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render one relationship selector.
	 *
	 * @param string              $control_id   HTML control identifier.
	 * @param string              $field_name   Request field name.
	 * @param string              $label        Visible control label.
	 * @param string              $description  Visible helper text.
	 * @param string              $empty_message Message shown when no related posts exist.
	 * @param array<int, WP_Post> $options      Related posts available for selection.
	 * @param array<int, int>     $selected_ids Currently selected post identifiers.
	 */
	private function render_relationship_select(
		string $control_id,
		string $field_name,
		string $label,
		string $description,
		string $empty_message,
		array $options,
		array $selected_ids
	): void {
		$description_id = $control_id . '-description';
		?>
		<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>[]" value="" />
		<?php if ( array() === $options ) : ?>
			<p><strong><?php echo esc_html( $label ); ?></strong></p>
			<p class="description"><?php echo esc_html( $empty_message ); ?></p>
			<?php return; ?>
		<?php endif; ?>
		<p>
			<label for="<?php echo esc_attr( $control_id ); ?>">
				<strong><?php echo esc_html( $label ); ?></strong>
			</label>
		</p>
		<select
			class="widefat"
			id="<?php echo esc_attr( $control_id ); ?>"
			name="<?php echo esc_attr( $field_name ); ?>[]"
			multiple
			size="<?php echo esc_attr( (string) min( 4, max( 2, count( $options ) ) ) ); ?>"
			aria-describedby="<?php echo esc_attr( $description_id ); ?>"
		>
			<?php foreach ( $options as $option ) : ?>
				<?php $title = get_the_title( $option ); ?>
				<option
					value="<?php echo esc_attr( (string) $option->ID ); ?>"
					<?php echo selected( in_array( $option->ID, $selected_ids, true ), true, false ); ?>
				>
					<?php
					echo esc_html(
						'' === $title
							? __( '(no title)', 'course-discovery' )
							: $title
					);
					?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description" id="<?php echo esc_attr( $description_id ); ?>">
			<?php echo esc_html( $description ); ?>
			<?php
			esc_html_e(
				'Use Ctrl on Windows or Command on macOS to select multiple options.',
				'course-discovery'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render repeatable start-month fields.
	 *
	 * @param array<int, StartDate> $start_dates Existing Course start dates.
	 */
	private function render_start_date_controls( array $start_dates ): void {
		$rows = array() === $start_dates ? array( null ) : $start_dates;
		?>
		<fieldset>
			<legend><strong><?php esc_html_e( 'Start dates', 'course-discovery' ); ?></strong></legend>
			<p class="description" id="course-discovery-start-dates-description">
				<?php
				esc_html_e(
					'Add each available start month and year. Values are stored as YYYY-MM, without a day.',
					'course-discovery'
				);
				?>
			</p>
			<div id="course-discovery-start-dates">
				<?php foreach ( $rows as $start_date ) : ?>
					<?php $this->render_start_date_row( $start_date ); ?>
				<?php endforeach; ?>
			</div>
			<p>
				<button
					type="button"
					class="button"
					data-course-discovery-add-start-date
				>
					<?php esc_html_e( 'Add start month', 'course-discovery' ); ?>
				</button>
			</p>
			<template id="course-discovery-start-date-template">
				<?php $this->render_start_date_row( null ); ?>
			</template>
			<noscript>
				<p class="description">
					<?php
					esc_html_e(
						'JavaScript is needed to add extra rows; existing rows can still be edited or cleared.',
						'course-discovery'
					);
					?>
				</p>
			</noscript>
		</fieldset>
		<?php
	}

	/**
	 * Render one start-month row.
	 *
	 * @param StartDate|null $start_date Existing start date, or a blank row.
	 */
	private function render_start_date_row( ?StartDate $start_date ): void {
		$value = null === $start_date ? '' : $start_date->value();
		?>
		<p data-course-discovery-start-date-row>
			<label>
				<span><?php esc_html_e( 'Start month', 'course-discovery' ); ?></span>
				<input
					name="<?php echo esc_attr( self::START_DATES_FIELD ); ?>[]"
					type="month"
					value="<?php echo esc_attr( $value ); ?>"
					aria-describedby="course-discovery-start-dates-description"
				/>
			</label>
			<button
				type="button"
				class="button-link-delete"
				data-course-discovery-remove-start-date
			>
				<?php esc_html_e( 'Remove start month', 'course-discovery' ); ?>
			</button>
		</p>
		<?php
	}

	/**
	 * Load posts available for one relationship selector.
	 *
	 * @param string $post_type Related post type.
	 *
	 * @return list<WP_Post>
	 */
	private function related_posts( string $post_type ): array {
		return array_values(
			get_posts(
				array(
					'post_type'        => $post_type,
					'post_status'      => 'any',
					'posts_per_page'   => -1,
					'orderby'          => 'title',
					'order'            => 'ASC',
					'suppress_filters' => false,
				)
			)
		);
	}
}
