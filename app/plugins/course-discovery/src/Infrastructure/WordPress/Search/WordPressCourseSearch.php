<?php
/**
 * WordPress Course search execution.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search;

use InvalidArgumentException;
use LogicException;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQuery;
use OxfordInternational\CourseDiscovery\Application\Search\CourseSearchInterface;
use OxfordInternational\CourseDiscovery\Application\Search\CourseSearchResult;
use OxfordInternational\CourseDiscovery\Application\Search\ResultOrder;
use OxfordInternational\CourseDiscovery\Domain\Course\CourseId;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CoursePostType;
use WP_Query;

/**
 * Executes backend-independent Course queries using WP_Query.
 */
final readonly class WordPressCourseSearch implements CourseSearchInterface {
	public const string REGISTER_TRANSLATORS_ACTION = 'course_discovery/register_wordpress_condition_translators';
	public const string RESULT_ORDER_ARGS_FILTER    = 'course_discovery/wordpress_result_order_args';

	/**
	 * Arguments owned by Course search execution rather than condition translators.
	 *
	 * @var list<string>
	 */
	private const array RESERVED_QUERY_ARGUMENTS = array(
		'post_type',
		'post_status',
		'fields',
		'posts_per_page',
		'paged',
		'ignore_sticky_posts',
		'no_found_rows',
		'update_post_meta_cache',
		'update_post_term_cache',
		'meta_query',
		'tax_query',
		'orderby',
		'order',
		'meta_key',
		'meta_type',
	);

	/**
	 * WP_Query arguments accepted from the result-order translation hook.
	 *
	 * @var list<string>
	 */
	private const array RESULT_ORDER_ARGUMENTS = array(
		'orderby',
		'order',
		'meta_key',
		'meta_type',
	);

	/**
	 * Translators available before runtime hook registration.
	 *
	 * @var WordPressConditionTranslatorRegistry
	 */
	private WordPressConditionTranslatorRegistry $base_registry;

	/**
	 * Create the WordPress Course search service.
	 *
	 * @param WordPressConditionTranslatorRegistry|null $base_registry Optional base translators.
	 */
	public function __construct( ?WordPressConditionTranslatorRegistry $base_registry = null ) {
		$this->base_registry = $base_registry ?? new WordPressConditionTranslatorRegistry();
	}

	/**
	 * Execute one page of Course results through WP_Query.
	 *
	 * @param CourseQuery $query    Backend-independent query.
	 * @param int         $page     One-based result page.
	 * @param int         $per_page Maximum results per page.
	 *
	 * @throws InvalidArgumentException When pagination values are invalid.
	 * @throws LogicException When query intent or WordPress ID output is invalid.
	 */
	public function search( CourseQuery $query, int $page = 1, int $per_page = 20 ): CourseSearchResult {
		if ( 1 > $page || 1 > $per_page ) {
			throw new InvalidArgumentException( 'Course search pagination values must be positive integers.' );
		}

		$wp_query   = new WP_Query( $this->query_arguments( $query, $page, $per_page ) );
		$course_ids = array();

		foreach ( $wp_query->posts as $post_id ) {
			if ( ! is_int( $post_id ) ) {
				throw new LogicException( 'WordPress did not return a Course ID for an ID-only query.' );
			}

			$course_ids[] = new CourseId( $post_id );
		}

		return new CourseSearchResult(
			$course_ids,
			$wp_query->found_posts,
			$page,
			$per_page
		);
	}

	/**
	 * Build WP_Query arguments from typed Course query intent.
	 *
	 * @param CourseQuery $query    Backend-independent query.
	 * @param int         $page     One-based result page.
	 * @param int         $per_page Maximum results per page.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws LogicException When query intent has no unambiguous WordPress translation.
	 */
	private function query_arguments( CourseQuery $query, int $page, int $per_page ): array {
		$registry = new WordPressConditionTranslatorRegistry( ...$this->base_registry->translators() );

		/**
		 * Fires when WordPress condition translators are assembled for one search.
		 *
		 * Extensions register translators through WordPressConditionTranslatorRegistry::register().
		 *
		 * @param WordPressConditionTranslatorRegistry $registry Registry for this search execution.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- The declared constant contains the plugin-prefixed public hook name.
		do_action( self::REGISTER_TRANSLATORS_ACTION, $registry );

		$meta_query_clauses = array();
		$tax_query_clauses  = array();
		$condition_args     = array();

		foreach ( $query->conditions() as $condition ) {
			$translator = $registry->translator( $condition->key() );

			if ( null === $translator ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is not rendered here.
				throw new LogicException(
					sprintf(
						'No WordPress condition translator is registered for "%s".',
						$condition->key()
					)
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			$constraint         = $translator->translate( $condition );
			$meta_query_clauses = array(
				...$meta_query_clauses,
				...$constraint->meta_query_clauses(),
			);
			$tax_query_clauses  = array(
				...$tax_query_clauses,
				...$constraint->tax_query_clauses(),
			);
			$condition_args     = $this->merge_condition_arguments(
				$condition_args,
				$constraint->query_arguments()
			);
		}

		$arguments = array(
			'post_type'              => CoursePostType::POST_TYPE,
			'post_status'            => 'publish',
			'fields'                 => 'ids',
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			...$condition_args,
			...$this->result_order_arguments( $query ),
		);

		if ( array() !== $meta_query_clauses ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required by the current native metadata model.
			$arguments['meta_query'] = array(
				'relation' => 'AND',
				...$meta_query_clauses,
			);
		}

		if ( array() !== $tax_query_clauses ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required by the current native taxonomy model.
			$arguments['tax_query'] = array(
				'relation' => 'AND',
				...$tax_query_clauses,
			);
		}

		return $arguments;
	}

	/**
	 * Merge condition arguments without silently overwriting another condition.
	 *
	 * @param array<string, mixed> $current    Arguments composed so far.
	 * @param array<string, mixed> $additional Arguments from one translator.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws LogicException When an argument is reserved or already contributed.
	 */
	private function merge_condition_arguments( array $current, array $additional ): array {
		foreach ( $additional as $key => $value ) {
			if ( in_array( $key, self::RESERVED_QUERY_ARGUMENTS, true ) ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is not rendered here.
				throw new LogicException(
					sprintf( 'WordPress condition translators cannot set the reserved "%s" argument.', $key )
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			if ( array_key_exists( $key, $current ) ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is not rendered here.
				throw new LogicException(
					sprintf( 'More than one WordPress condition translator contributed "%s".', $key )
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			$current[ $key ] = $value;
		}

		return $current;
	}

	/**
	 * Translate semantic result ordering to WordPress arguments.
	 *
	 * @param CourseQuery $query Backend-independent query.
	 *
	 * @return array<string, mixed>
	 */
	private function result_order_arguments( CourseQuery $query ): array {
		$result_order = $query->result_order();
		$arguments    = ResultOrder::DEFAULT_KEY === $result_order->key()
			? self::default_result_order_arguments()
			: array();

		/**
		 * Filters WP_Query ordering arguments for one semantic result order.
		 *
		 * Custom ResultOrder keys must be translated here rather than exposing
		 * WordPress orderby values to Application.
		 *
		 * @param array<string, mixed> $arguments    WordPress ordering arguments.
		 * @param ResultOrder          $result_order Semantic result ordering.
		 * @param CourseQuery          $query        Backend-independent query.
		 */
		/**
		 * Runtime extension output is untrusted.
		 *
		 * @var mixed $filtered_arguments
		 */
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- The declared constant contains the plugin-prefixed public hook name.
		$filtered_arguments = apply_filters(
			self::RESULT_ORDER_ARGS_FILTER,
			$arguments,
			$result_order,
			$query
		);
		// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

		if (
			! is_array( $filtered_arguments )
			|| ! self::valid_result_order_arguments( $filtered_arguments )
		) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Diagnostic arguments are passed to _doing_it_wrong(), not rendered here.
			_doing_it_wrong(
				__METHOD__,
				__( 'WordPress result ordering filters must return supported ordering arguments including orderby.', 'course-discovery' ),
				COURSE_DISCOVERY_VERSION
			);
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

			return self::default_result_order_arguments();
		}

		return $filtered_arguments;
	}

	/**
	 * Validate that a hook returned only WordPress ordering arguments.
	 *
	 * @param array<mixed> $arguments Untrusted hook output.
	 */
	private static function valid_result_order_arguments( array $arguments ): bool {
		if ( ! array_key_exists( 'orderby', $arguments ) ) {
			return false;
		}

		foreach ( array_keys( $arguments ) as $key ) {
			if ( ! is_string( $key ) || ! in_array( $key, self::RESULT_ORDER_ARGUMENTS, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Return deterministic default WordPress ordering.
	 *
	 * @return array<string, mixed>
	 */
	private static function default_result_order_arguments(): array {
		return array(
			'orderby' => array(
				'title' => 'ASC',
				'ID'    => 'ASC',
			),
		);
	}
}
