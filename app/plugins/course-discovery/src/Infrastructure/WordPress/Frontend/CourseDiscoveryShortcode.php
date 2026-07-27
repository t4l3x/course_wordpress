<?php
/**
 * Course Discovery shortcode.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend;

use OxfordInternational\CourseDiscovery\Application\Search\CourseFilterPipeline;
use OxfordInternational\CourseDiscovery\Application\Search\CourseSearchInterface;
use OxfordInternational\CourseDiscovery\Application\Search\ResultOrder;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriteria;
use OxfordInternational\CourseDiscovery\Domain\Category\CategoryId;
use OxfordInternational\CourseDiscovery\Domain\Course\StartDate;
use OxfordInternational\CourseDiscovery\Domain\Location\LocationId;
use OxfordInternational\CourseDiscovery\Domain\Provider\ProviderId;
use WP_Post;

/**
 * Coordinates GET input, typed search execution, view preparation, and template rendering.
 */
final class CourseDiscoveryShortcode {
	public const string SHORTCODE     = 'course_discovery';
	public const string STYLE_HANDLE  = 'course-discovery-frontend';
	public const string SCRIPT_HANDLE = 'course-discovery-frontend';

	/**
	 * Number of shortcode instances rendered during this request.
	 *
	 * @var int
	 */
	private static int $instance_number = 0;

	/**
	 * Create the Course Discovery shortcode coordinator.
	 *
	 * @param CourseSearchRequestParser $request_parser Request parsing boundary.
	 * @param CourseFilterPipeline      $pipeline       Typed filter pipeline.
	 * @param CourseSearchInterface     $search         Course search execution boundary.
	 * @param CourseFilterOptions       $filter_options Public filter option loader.
	 * @param CourseResultPresenter     $presenter      Result display preparation.
	 * @param string                    $plugin_file    Main plugin file.
	 * @param string                    $template_file  Plugin-owned template file.
	 * @param string                    $version        Asset version.
	 */
	public function __construct(
		private CourseSearchRequestParser $request_parser,
		private CourseFilterPipeline $pipeline,
		private CourseSearchInterface $search,
		private CourseFilterOptions $filter_options,
		private CourseResultPresenter $presenter,
		private string $plugin_file,
		private string $template_file,
		private string $version
	) {
	}

	/**
	 * Register the shortcode and early asset detection hook.
	 */
	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_for_current_page' ) );
	}

	/**
	 * Enqueue assets before the page header when the current post contains the shortcode.
	 */
	public function enqueue_for_current_page(): void {
		global $post;

		if ( $post instanceof WP_Post && has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			$this->enqueue_assets();
		}
	}

	/**
	 * Render one complete Course Discovery interface.
	 *
	 * @param array<string, mixed>|string $attributes Shortcode attributes; currently unused.
	 * @param string|null                 $content    Enclosed content; currently unused.
	 * @param string                      $tag        Shortcode tag.
	 */
	public function render( array|string $attributes = array(), ?string $content = null, string $tag = '' ): string {
		unset( $attributes, $content, $tag );

		if ( ! is_readable( $this->template_file ) ) {
			return '';
		}

		$this->enqueue_assets();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public GET filters are read-only and validated by the request parser.
		$raw_request           = wp_unslash( $_GET );
		$request               = $this->request_parser->parse( $raw_request );
		$criteria              = $request->criteria();
		$query                 = $this->pipeline->compose( $criteria );
		$result                = $this->search->search( $query, $request->page(), $request->per_page() );
		$base_url              = $this->current_page_url();
		$selected              = $this->selected_values( $criteria );
		$options               = $this->filter_options->all();
		$course_discovery_view = array(
			'instance_id'         => $this->next_instance_id(),
			'form_action'         => $base_url,
			'clear_url'           => $base_url,
			'search_term'         => $criteria->search_term()?->value() ?? '',
			'per_page'            => $request->per_page(),
			'active_filter_count' => count( $criteria->providers() )
				+ count( $criteria->locations() )
				+ count( $criteria->start_dates() )
				+ count( $criteria->categories() ),
			'has_active_filters'  => ! $criteria->is_empty(),
			'selected'            => $selected,
			'options'             => $options,
			'active_filters'      => $this->active_filters(
				$selected,
				$options,
				$criteria,
				$request->per_page(),
				$base_url
			),
			'courses'             => $this->presenter->prepare( $result ),
			'total_results'       => $result->total_results(),
			'empty_message'       => $criteria->is_empty()
				? __( 'No courses are available yet.', 'course-discovery' )
				: __( 'No courses match your current filters.', 'course-discovery' ),
			'pagination'          => $this->pagination(
				$result->current_page(),
				$result->total_pages(),
				$base_url,
				$this->criteria_query_arguments( $criteria, $request->per_page() )
			),
		);

		ob_start();
		require $this->template_file;
		$output = ob_get_clean();

		return $output;
	}

	/**
	 * Enqueue scoped public assets.
	 */
	private function enqueue_assets(): void {
		wp_enqueue_style(
			self::STYLE_HANDLE,
			plugins_url( 'assets/css/course-discovery.css', $this->plugin_file ),
			array(),
			$this->version
		);
		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			plugins_url( 'assets/js/course-discovery.js', $this->plugin_file ),
			array(),
			$this->version,
			true
		);
	}

	/**
	 * Return selected scalar values for template comparisons.
	 *
	 * @param SearchCriteria $criteria Typed selected criteria.
	 *
	 * @return array{
	 *     providers: list<string>,
	 *     locations: list<string>,
	 *     start_dates: list<string>,
	 *     categories: list<string>
	 * }
	 */
	private function selected_values( SearchCriteria $criteria ): array {
		return array(
			'providers'   => array_map(
				static fn ( ProviderId $provider ): string => (string) $provider->value(),
				$criteria->providers()
			),
			'locations'   => array_map(
				static fn ( LocationId $location ): string => (string) $location->value(),
				$criteria->locations()
			),
			'start_dates' => array_map(
				static fn ( StartDate $date ): string => $date->value(),
				$criteria->start_dates()
			),
			'categories'  => array_map(
				static fn ( CategoryId $category ): string => (string) $category->value(),
				$criteria->categories()
			),
		);
	}

	/**
	 * Build canonical query arguments from typed criteria.
	 *
	 * @param SearchCriteria $criteria Typed search criteria.
	 * @param int            $per_page Validated page size.
	 *
	 * @return array<string, int|string|list<int|string>>
	 */
	private function criteria_query_arguments( SearchCriteria $criteria, int $per_page ): array {
		$arguments = array(
			'per_page' => $per_page,
		);

		if ( null !== $criteria->search_term() ) {
			$arguments['q'] = $criteria->search_term()->value();
		}

		if ( array() !== $criteria->providers() ) {
			$arguments['provider'] = array_map(
				static fn ( ProviderId $provider ): int => $provider->value(),
				$criteria->providers()
			);
		}

		if ( array() !== $criteria->locations() ) {
			$arguments['location'] = array_map(
				static fn ( LocationId $location ): int => $location->value(),
				$criteria->locations()
			);
		}

		if ( array() !== $criteria->start_dates() ) {
			$arguments['start_date'] = array_map(
				static fn ( StartDate $date ): string => $date->value(),
				$criteria->start_dates()
			);
		}

		if ( array() !== $criteria->categories() ) {
			$arguments['category'] = array_map(
				static fn ( CategoryId $category ): int => $category->value(),
				$criteria->categories()
			);
		}

		if ( ResultOrder::DEFAULT_KEY !== $criteria->result_order()->key() ) {
			$arguments['order'] = $criteria->result_order()->key();
		}

		return $arguments;
	}

	/**
	 * Build removable active-filter chips from canonical selected values.
	 *
	 * @param array          $selected Selected scalar values.
	 * @param array          $options  Available filter options.
	 * @param SearchCriteria $criteria Typed selected criteria.
	 * @param int            $per_page Validated page size.
	 * @param string         $base_url Current page URL.
	 *
	 * @phpstan-param array{
	 *     providers: list<string>,
	 *     locations: list<string>,
	 *     start_dates: list<string>,
	 *     categories: list<string>
	 * } $selected Selected scalar values.
	 * @phpstan-param array{
	 *     providers: list<CourseFilterOption>,
	 *     locations: list<CourseFilterOption>,
	 *     start_dates: list<CourseFilterOption>,
	 *     categories: list<CourseFilterOption>
	 * } $options Available filter options.
	 *
	 * @return list<array{label: string, aria_label: string, remove_url: string}>
	 */
	private function active_filters(
		array $selected,
		array $options,
		SearchCriteria $criteria,
		int $per_page,
		string $base_url
	): array {
		$arguments = $this->criteria_query_arguments( $criteria, $per_page );
		$chips     = array();
		$groups    = array(
			'providers'   => 'provider',
			'locations'   => 'location',
			'start_dates' => 'start_date',
			'categories'  => 'category',
		);

		foreach ( $groups as $group => $parameter ) {
			$labels = array();

			foreach ( $options[ $group ] as $option ) {
				$labels[ $option->value() ] = $option->label();
			}

			foreach ( $selected[ $group ] as $value ) {
				$remaining = array_values(
					array_filter(
						$selected[ $group ],
						static fn ( string $candidate ): bool => $candidate !== $value
					)
				);
				$next      = $arguments;

				if ( array() === $remaining ) {
					unset( $next[ $parameter ] );
				} else {
					$next[ $parameter ] = $remaining;
				}

				$label   = $labels[ $value ] ?? $value;
				$chips[] = array(
					'label'      => $label,
					'aria_label' => sprintf(
						/* translators: %s is the display label of an active Course filter. */
						__( 'Remove %s filter', 'course-discovery' ),
						$label
					),
					'remove_url' => add_query_arg( $next, $base_url ),
				);
			}
		}

		return $chips;
	}

	/**
	 * Build a compact accessible pagination model.
	 *
	 * @param int                                        $current_page Current page.
	 * @param int                                        $total_pages Total pages.
	 * @param string                                     $base_url    Current page URL.
	 * @param array<string, int|string|list<int|string>> $arguments Active query arguments.
	 *
	 * @return list<array{label: string, aria_label: string, url: ?string, current: bool}>
	 */
	private function pagination( int $current_page, int $total_pages, string $base_url, array $arguments ): array {
		if ( 2 > $total_pages ) {
			return array();
		}

		$items = array();

		if ( 1 < $current_page ) {
			$items[] = $this->pagination_link(
				__( 'Previous', 'course-discovery' ),
				__( 'Previous results page', 'course-discovery' ),
				$current_page - 1,
				$base_url,
				$arguments
			);
		}

		$pages = array( 1, $total_pages );

		$last_page = min( $total_pages, $current_page + 2 );

		for ( $page = max( 1, $current_page - 2 ); $page <= $last_page; ++$page ) {
			$pages[] = $page;
		}

		$pages = array_values( array_unique( $pages ) );
		sort( $pages, SORT_NUMERIC );
		$previous_page = 0;

		foreach ( $pages as $page ) {
			if ( 0 !== $previous_page && 1 < $page - $previous_page ) {
				$items[] = array(
					'label'      => '…',
					'aria_label' => __( 'More result pages', 'course-discovery' ),
					'url'        => null,
					'current'    => false,
				);
			}

			$items[] = $page === $current_page
				? array(
					'label'      => (string) $page,
					'aria_label' => sprintf(
						/* translators: %d is the current results page number. */
						__( 'Results page %d, current page', 'course-discovery' ),
						$page
					),
					'url'        => null,
					'current'    => true,
				)
				: $this->pagination_link(
					(string) $page,
					sprintf(
						/* translators: %d is a results page number. */
						__( 'Go to results page %d', 'course-discovery' ),
						$page
					),
					$page,
					$base_url,
					$arguments
				);
			$previous_page = $page;
		}

		if ( $current_page < $total_pages ) {
			$items[] = $this->pagination_link(
				__( 'Next', 'course-discovery' ),
				__( 'Next results page', 'course-discovery' ),
				$current_page + 1,
				$base_url,
				$arguments
			);
		}

		return $items;
	}

	/**
	 * Build one pagination link item.
	 *
	 * @param string                                     $label      Visible label.
	 * @param string                                     $aria_label Accessible label.
	 * @param int                                        $page       Destination page.
	 * @param string                                     $base_url   Current page URL.
	 * @param array<string, int|string|list<int|string>> $arguments Active query arguments.
	 *
	 * @return array{label: string, aria_label: string, url: string, current: false}
	 */
	private function pagination_link(
		string $label,
		string $aria_label,
		int $page,
		string $base_url,
		array $arguments
	): array {
		return array(
			'label'      => $label,
			'aria_label' => $aria_label,
			'url'        => add_query_arg(
				array(
					...$arguments,
					'course_page' => $page,
				),
				$base_url
			),
			'current'    => false,
		);
	}

	/**
	 * Resolve the current page permalink without retaining unvalidated query input.
	 */
	private function current_page_url(): string {
		$object_id = get_queried_object_id();
		$permalink = 0 < $object_id ? get_permalink( $object_id ) : false;

		return is_string( $permalink ) && '' !== $permalink ? $permalink : home_url( '/' );
	}

	/**
	 * Return a unique HTML ID prefix for one shortcode instance.
	 */
	private function next_instance_id(): string {
		++self::$instance_number;

		return 'course-discovery-' . self::$instance_number;
	}
}
