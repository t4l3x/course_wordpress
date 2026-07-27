<?php
/**
 * Course Discovery public interface.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend\CourseFilterOption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prepared shortcode view data.
 *
 * @var array{
 *     instance_id: string,
 *     form_action: string,
 *     clear_url: string,
 *     search_term: string,
 *     per_page: int,
 *     active_filter_count: int,
 *     has_active_filters: bool,
 *     selected: array{
 *         providers: list<string>,
 *         locations: list<string>,
 *         start_dates: list<string>,
 *         categories: list<string>
 *     },
 *     options: array{
 *         providers: list<CourseFilterOption>,
 *         locations: list<CourseFilterOption>,
 *         start_dates: list<CourseFilterOption>,
 *         categories: list<CourseFilterOption>
 *     },
 *     active_filters: list<array{label: string, aria_label: string, remove_url: string}>,
 *     courses: list<array{
 *         id: int,
 *         name: string,
 *         short_description: string,
 *         price: ?string,
 *         providers: list<string>,
 *         locations: list<string>,
 *         instructors: list<string>,
 *         start_dates: list<string>,
 *         categories: list<string>
 *     }>,
 *     total_results: int,
 *     empty_message: string,
 *     pagination: list<array{label: string, aria_label: string, url: ?string, current: bool}>
 * } $course_discovery_view
 */
?>
<section
	class="course-discovery alignfull"
	data-course-discovery
	id="<?php echo esc_attr( $course_discovery_view['instance_id'] ); ?>"
>
	<form
		class="course-discovery__form"
		action="<?php echo esc_url( $course_discovery_view['form_action'] ); ?>"
		method="get"
	>
		<header class="course-discovery__hero">
			<div class="course-discovery__hero-inner">
				<p class="course-discovery__eyebrow"><?php esc_html_e( 'University course discovery', 'course-discovery' ); ?></p>
				<h2 class="course-discovery__title">
					<?php esc_html_e( 'Discover your next course', 'course-discovery' ); ?>
				</h2>
				<p class="course-discovery__intro">
					<?php esc_html_e( 'Search courses and narrow the catalogue by provider, location, start date, or category.', 'course-discovery' ); ?>
				</p>

				<div class="course-discovery__search-row">
					<label
						class="course-discovery__visually-hidden"
						for="<?php echo esc_attr( $course_discovery_view['instance_id'] . '-search' ); ?>"
					>
						<?php esc_html_e( 'Search for a course', 'course-discovery' ); ?>
					</label>
					<svg aria-hidden="true" class="course-discovery__search-icon" viewBox="0 0 24 24">
						<path d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />
					</svg>
					<input
						class="course-discovery__search-input"
						id="<?php echo esc_attr( $course_discovery_view['instance_id'] . '-search' ); ?>"
						name="q"
						placeholder="<?php esc_attr_e( 'Search courses, subjects, or keywords', 'course-discovery' ); ?>"
						type="search"
						value="<?php echo esc_attr( $course_discovery_view['search_term'] ); ?>"
					/>
					<button class="course-discovery__search-button" type="submit">
						<?php esc_html_e( 'Search', 'course-discovery' ); ?>
					</button>
				</div>

				<div class="course-discovery__hero-toolbar">
					<div aria-label="<?php esc_attr_e( 'Active course filters', 'course-discovery' ); ?>" class="course-discovery__active-filters">
						<?php foreach ( $course_discovery_view['active_filters'] as $course_discovery_filter ) : ?>
							<a
								aria-label="<?php echo esc_attr( $course_discovery_filter['aria_label'] ); ?>"
								class="course-discovery__active-filter"
								href="<?php echo esc_url( $course_discovery_filter['remove_url'] ); ?>"
							>
								<span><?php echo esc_html( $course_discovery_filter['label'] ); ?></span>
								<span aria-hidden="true" class="course-discovery__active-filter-remove">&times;</span>
							</a>
						<?php endforeach; ?>
						<?php if ( array() !== $course_discovery_view['active_filters'] ) : ?>
							<a class="course-discovery__hero-clear" href="<?php echo esc_url( $course_discovery_view['clear_url'] ); ?>">
								<?php esc_html_e( 'Clear all', 'course-discovery' ); ?>
							</a>
						<?php endif; ?>
					</div>

					<a
						aria-controls="<?php echo esc_attr( $course_discovery_view['instance_id'] . '-filters' ); ?>"
						aria-expanded="false"
						class="course-discovery__filter-button"
						data-course-discovery-open-filters
						href="<?php echo esc_url( '#' . $course_discovery_view['instance_id'] . '-filters' ); ?>"
					>
						<svg aria-hidden="true" viewBox="0 0 24 24">
							<path d="M4 7h10m4 0h2M4 17h2m4 0h10M14 4v6M10 14v6" />
						</svg>
						<span><?php esc_html_e( 'Filters', 'course-discovery' ); ?></span>
						<?php if ( 0 < $course_discovery_view['active_filter_count'] ) : ?>
							<span class="course-discovery__filter-count">
								<?php echo esc_html( (string) $course_discovery_view['active_filter_count'] ); ?>
							</span>
						<?php endif; ?>
					</a>
				</div>
			</div>
		</header>

		<div class="course-discovery__backdrop" data-course-discovery-close-filters hidden></div>

		<div class="course-discovery__layout">
			<aside
				aria-label="<?php esc_attr_e( 'Course filters', 'course-discovery' ); ?>"
				class="course-discovery__filters"
				id="<?php echo esc_attr( $course_discovery_view['instance_id'] . '-filters' ); ?>"
			>
				<div class="course-discovery__filters-header">
					<h3 tabindex="-1"><?php esc_html_e( 'Filters', 'course-discovery' ); ?></h3>
					<div class="course-discovery__filters-header-actions">
						<a href="<?php echo esc_url( $course_discovery_view['clear_url'] ); ?>">
							<?php esc_html_e( 'Clear all', 'course-discovery' ); ?>
						</a>
						<button
							aria-label="<?php esc_attr_e( 'Close course filters', 'course-discovery' ); ?>"
							class="course-discovery__close-button"
							data-course-discovery-close-filters
							hidden
							type="button"
						>
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
				</div>

				<input
					name="per_page"
					type="hidden"
					value="<?php echo esc_attr( (string) $course_discovery_view['per_page'] ); ?>"
				/>

				<details class="course-discovery__filter-disclosure" open>
					<summary>
						<span><?php esc_html_e( 'Providers', 'course-discovery' ); ?></span>
						<span class="course-discovery__selected-count"><?php echo esc_html( (string) count( $course_discovery_view['selected']['providers'] ) ); ?></span>
					</summary>
					<fieldset class="course-discovery__filter-group">
						<legend class="course-discovery__visually-hidden"><?php esc_html_e( 'Select Providers', 'course-discovery' ); ?></legend>
						<?php if ( array() === $course_discovery_view['options']['providers'] ) : ?>
							<p class="course-discovery__option-empty"><?php esc_html_e( 'No Providers are available.', 'course-discovery' ); ?></p>
						<?php else : ?>
							<div class="course-discovery__option-list">
								<?php foreach ( $course_discovery_view['options']['providers'] as $course_discovery_index => $course_discovery_option ) : ?>
									<?php $course_discovery_control_id = $course_discovery_view['instance_id'] . '-provider-' . $course_discovery_index; ?>
									<label class="course-discovery__option" for="<?php echo esc_attr( $course_discovery_control_id ); ?>">
										<input
											<?php echo checked( in_array( $course_discovery_option->value(), $course_discovery_view['selected']['providers'], true ), true, false ); ?>
											id="<?php echo esc_attr( $course_discovery_control_id ); ?>"
											name="provider[]"
											type="checkbox"
											value="<?php echo esc_attr( $course_discovery_option->value() ); ?>"
										/>
										<span><?php echo esc_html( $course_discovery_option->label() ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</fieldset>
				</details>

				<details class="course-discovery__filter-disclosure" open>
					<summary>
						<span><?php esc_html_e( 'Locations', 'course-discovery' ); ?></span>
						<span class="course-discovery__selected-count"><?php echo esc_html( (string) count( $course_discovery_view['selected']['locations'] ) ); ?></span>
					</summary>
					<fieldset class="course-discovery__filter-group">
						<legend class="course-discovery__visually-hidden"><?php esc_html_e( 'Select Locations', 'course-discovery' ); ?></legend>
						<?php if ( array() === $course_discovery_view['options']['locations'] ) : ?>
							<p class="course-discovery__option-empty"><?php esc_html_e( 'No Locations are available.', 'course-discovery' ); ?></p>
						<?php else : ?>
							<div class="course-discovery__option-list">
								<?php foreach ( $course_discovery_view['options']['locations'] as $course_discovery_index => $course_discovery_option ) : ?>
									<?php $course_discovery_control_id = $course_discovery_view['instance_id'] . '-location-' . $course_discovery_index; ?>
									<label class="course-discovery__option" for="<?php echo esc_attr( $course_discovery_control_id ); ?>">
										<input
											<?php echo checked( in_array( $course_discovery_option->value(), $course_discovery_view['selected']['locations'], true ), true, false ); ?>
											id="<?php echo esc_attr( $course_discovery_control_id ); ?>"
											name="location[]"
											type="checkbox"
											value="<?php echo esc_attr( $course_discovery_option->value() ); ?>"
										/>
										<span><?php echo esc_html( $course_discovery_option->label() ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</fieldset>
				</details>

				<details class="course-discovery__filter-disclosure" open>
					<summary>
						<span><?php esc_html_e( 'Start dates', 'course-discovery' ); ?></span>
						<span class="course-discovery__selected-count"><?php echo esc_html( (string) count( $course_discovery_view['selected']['start_dates'] ) ); ?></span>
					</summary>
					<fieldset class="course-discovery__filter-group">
						<legend class="course-discovery__visually-hidden"><?php esc_html_e( 'Select Start Dates', 'course-discovery' ); ?></legend>
						<?php if ( array() === $course_discovery_view['options']['start_dates'] ) : ?>
							<p class="course-discovery__option-empty"><?php esc_html_e( 'No Start Dates are available.', 'course-discovery' ); ?></p>
						<?php else : ?>
							<div class="course-discovery__option-list">
								<?php foreach ( $course_discovery_view['options']['start_dates'] as $course_discovery_index => $course_discovery_option ) : ?>
									<?php $course_discovery_control_id = $course_discovery_view['instance_id'] . '-start-date-' . $course_discovery_index; ?>
									<label class="course-discovery__option" for="<?php echo esc_attr( $course_discovery_control_id ); ?>">
										<input
											<?php echo checked( in_array( $course_discovery_option->value(), $course_discovery_view['selected']['start_dates'], true ), true, false ); ?>
											id="<?php echo esc_attr( $course_discovery_control_id ); ?>"
											name="start_date[]"
											type="checkbox"
											value="<?php echo esc_attr( $course_discovery_option->value() ); ?>"
										/>
										<span><?php echo esc_html( $course_discovery_option->label() ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</fieldset>
				</details>

				<details class="course-discovery__filter-disclosure" open>
					<summary>
						<span><?php esc_html_e( 'Categories', 'course-discovery' ); ?></span>
						<span class="course-discovery__selected-count"><?php echo esc_html( (string) count( $course_discovery_view['selected']['categories'] ) ); ?></span>
					</summary>
					<fieldset class="course-discovery__filter-group">
						<legend class="course-discovery__visually-hidden"><?php esc_html_e( 'Select Categories', 'course-discovery' ); ?></legend>
						<?php if ( array() === $course_discovery_view['options']['categories'] ) : ?>
							<p class="course-discovery__option-empty"><?php esc_html_e( 'No Categories are available.', 'course-discovery' ); ?></p>
						<?php else : ?>
							<div class="course-discovery__option-list">
								<?php foreach ( $course_discovery_view['options']['categories'] as $course_discovery_index => $course_discovery_option ) : ?>
									<?php $course_discovery_control_id = $course_discovery_view['instance_id'] . '-category-' . $course_discovery_index; ?>
									<label
										class="course-discovery__option course-discovery__option--depth-<?php echo esc_attr( (string) min( 4, $course_discovery_option->depth() ) ); ?>"
										for="<?php echo esc_attr( $course_discovery_control_id ); ?>"
									>
										<input
											<?php echo checked( in_array( $course_discovery_option->value(), $course_discovery_view['selected']['categories'], true ), true, false ); ?>
											id="<?php echo esc_attr( $course_discovery_control_id ); ?>"
											name="category[]"
											type="checkbox"
											value="<?php echo esc_attr( $course_discovery_option->value() ); ?>"
										/>
										<span><?php echo esc_html( $course_discovery_option->label() ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</fieldset>
				</details>

				<div class="course-discovery__filter-actions">
					<button class="course-discovery__apply-button" type="submit">
						<?php esc_html_e( 'Show results', 'course-discovery' ); ?>
					</button>
					<a class="course-discovery__clear-link" href="<?php echo esc_url( $course_discovery_view['clear_url'] ); ?>">
						<?php esc_html_e( 'Clear filters', 'course-discovery' ); ?>
					</a>
				</div>
			</aside>

			<section
				aria-labelledby="<?php echo esc_attr( $course_discovery_view['instance_id'] . '-results-title' ); ?>"
				class="course-discovery__results"
			>
				<div class="course-discovery__results-header">
					<h2 id="<?php echo esc_attr( $course_discovery_view['instance_id'] . '-results-title' ); ?>">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s is the localized number of matching Courses. */
								_n( '%s course found', '%s courses found', $course_discovery_view['total_results'], 'course-discovery' ),
								number_format_i18n( $course_discovery_view['total_results'] )
							)
						);
						?>
					</h2>
					<p><?php esc_html_e( 'Explore the courses matching your search.', 'course-discovery' ); ?></p>
				</div>

				<?php if ( array() === $course_discovery_view['courses'] ) : ?>
					<div class="course-discovery__empty">
						<svg aria-hidden="true" viewBox="0 0 48 48">
							<path d="M20 7a13 13 0 1 0 0 26 13 13 0 0 0 0-26Zm21 34-11.8-11.8" />
						</svg>
						<h3><?php esc_html_e( 'No courses found', 'course-discovery' ); ?></h3>
						<p><?php echo esc_html( $course_discovery_view['empty_message'] ); ?></p>
						<?php if ( $course_discovery_view['has_active_filters'] ) : ?>
							<a href="<?php echo esc_url( $course_discovery_view['clear_url'] ); ?>">
								<?php esc_html_e( 'Clear all filters', 'course-discovery' ); ?>
							</a>
						<?php endif; ?>
					</div>
				<?php else : ?>
					<div class="course-discovery__course-list">
						<?php foreach ( $course_discovery_view['courses'] as $course_discovery_course ) : ?>
							<article class="course-discovery__card">
								<div class="course-discovery__card-main">
									<?php if ( array() !== $course_discovery_course['categories'] ) : ?>
										<ul aria-label="<?php esc_attr_e( 'Course categories', 'course-discovery' ); ?>" class="course-discovery__category-list">
											<?php foreach ( $course_discovery_course['categories'] as $course_discovery_category ) : ?>
												<li><?php echo esc_html( $course_discovery_category ); ?></li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
									<h3><?php echo esc_html( $course_discovery_course['name'] ); ?></h3>
									<?php if ( '' !== $course_discovery_course['short_description'] ) : ?>
										<p><?php echo esc_html( $course_discovery_course['short_description'] ); ?></p>
									<?php endif; ?>
								</div>

								<dl class="course-discovery__card-details">
									<?php if ( array() !== $course_discovery_course['providers'] ) : ?>
										<div class="course-discovery__card-detail">
											<dt><span aria-hidden="true" class="course-discovery__meta-icon course-discovery__meta-icon--provider"></span><span class="course-discovery__visually-hidden"><?php esc_html_e( 'Providers', 'course-discovery' ); ?></span></dt>
											<dd><?php echo esc_html( implode( ', ', $course_discovery_course['providers'] ) ); ?></dd>
										</div>
									<?php endif; ?>
									<?php if ( array() !== $course_discovery_course['locations'] ) : ?>
										<div class="course-discovery__card-detail">
											<dt><span aria-hidden="true" class="course-discovery__meta-icon course-discovery__meta-icon--location"></span><span class="course-discovery__visually-hidden"><?php esc_html_e( 'Locations', 'course-discovery' ); ?></span></dt>
											<dd><?php echo esc_html( implode( ', ', $course_discovery_course['locations'] ) ); ?></dd>
										</div>
									<?php endif; ?>
									<?php if ( array() !== $course_discovery_course['start_dates'] ) : ?>
										<div class="course-discovery__card-detail">
											<dt><span aria-hidden="true" class="course-discovery__meta-icon course-discovery__meta-icon--date"></span><span class="course-discovery__visually-hidden"><?php esc_html_e( 'Start dates', 'course-discovery' ); ?></span></dt>
											<dd><?php echo esc_html( implode( ', ', $course_discovery_course['start_dates'] ) ); ?></dd>
										</div>
									<?php endif; ?>
									<?php if ( array() !== $course_discovery_course['instructors'] ) : ?>
										<div class="course-discovery__card-detail">
											<dt><span aria-hidden="true" class="course-discovery__meta-icon course-discovery__meta-icon--instructor"></span><span class="course-discovery__visually-hidden"><?php esc_html_e( 'Instructors', 'course-discovery' ); ?></span></dt>
											<dd><?php echo esc_html( implode( ', ', $course_discovery_course['instructors'] ) ); ?></dd>
										</div>
									<?php endif; ?>
									<?php if ( null !== $course_discovery_course['price'] ) : ?>
										<div class="course-discovery__card-price">
											<dt><?php esc_html_e( 'Price', 'course-discovery' ); ?></dt>
											<dd><?php echo esc_html( $course_discovery_course['price'] ); ?></dd>
										</div>
									<?php endif; ?>
								</dl>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( array() !== $course_discovery_view['pagination'] ) : ?>
					<nav aria-label="<?php esc_attr_e( 'Course results pages', 'course-discovery' ); ?>" class="course-discovery__pagination">
						<ul>
							<?php foreach ( $course_discovery_view['pagination'] as $course_discovery_page ) : ?>
								<li>
									<?php if ( $course_discovery_page['current'] ) : ?>
										<span aria-current="page" aria-label="<?php echo esc_attr( $course_discovery_page['aria_label'] ); ?>">
											<?php echo esc_html( $course_discovery_page['label'] ); ?>
										</span>
									<?php elseif ( null === $course_discovery_page['url'] ) : ?>
										<span aria-hidden="true" class="course-discovery__pagination-gap"><?php echo esc_html( $course_discovery_page['label'] ); ?></span>
									<?php else : ?>
										<a
											aria-label="<?php echo esc_attr( $course_discovery_page['aria_label'] ); ?>"
											href="<?php echo esc_url( $course_discovery_page['url'] ); ?>"
										>
											<?php echo esc_html( $course_discovery_page['label'] ); ?>
										</a>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</nav>
				<?php endif; ?>
			</section>
		</div>
	</form>
</section>
