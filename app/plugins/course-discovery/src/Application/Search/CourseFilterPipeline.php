<?php
/**
 * Course filter pipeline.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Application\Search;

/**
 * Composes immutable search criteria into a backend-independent Course query.
 */
final readonly class CourseFilterPipeline {
	/**
	 * Create the Course filter pipeline.
	 *
	 * @param CourseFilterRegistry            $base_registry Base filters for this application runtime.
	 * @param CourseSearchExtensionsInterface $extensions  Runtime extension boundary.
	 */
	public function __construct(
		private CourseFilterRegistry $base_registry,
		private CourseSearchExtensionsInterface $extensions
	) {
	}

	/**
	 * Compose criteria into an immutable Course query specification.
	 *
	 * @param SearchCriteria $criteria Requested Course search criteria.
	 */
	public function compose( SearchCriteria $criteria ): CourseQuery {
		$criteria = $this->extensions->search_criteria( $criteria );
		$registry = new CourseFilterRegistry( ...$this->base_registry->filters() );

		$this->extensions->register_filters( $registry );

		$query = CourseQuery::empty( $criteria->result_order() );

		foreach ( $registry->filters() as $filter ) {
			if ( $filter->supports( $criteria ) ) {
				$query = $filter->apply( $query, $criteria );
			}
		}

		$query        = $this->extensions->course_query( $query, $criteria );
		$result_order = $this->extensions->result_order(
			$query->result_order(),
			$criteria,
			$query
		);

		return $query->with_result_order( $result_order );
	}
}
