<?php
/**
 * Course Discovery GET request parser.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Application\Search\ResultOrder;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriteria;
use OxfordInternational\CourseDiscovery\Application\Search\SearchTerm;
use OxfordInternational\CourseDiscovery\Domain\Category\CategoryId;
use OxfordInternational\CourseDiscovery\Domain\Course\StartDate;
use OxfordInternational\CourseDiscovery\Domain\Location\LocationId;
use OxfordInternational\CourseDiscovery\Domain\Provider\ProviderId;

/**
 * Converts untrusted web input to typed search intent.
 */
final class CourseSearchRequestParser {
	public const int DEFAULT_PER_PAGE       = 12;
	public const int MAX_PER_PAGE           = 48;
	public const int MAX_PAGE               = 1000;
	public const int MAX_SEARCH_TERM_LENGTH = 200;
	public const int MAX_FILTER_VALUES      = 50;

	/**
	 * Parse an unslashed request array.
	 *
	 * Invalid values are ignored and never coerced to a different filter value.
	 *
	 * @param array<string, mixed> $input Untrusted, unslashed request input.
	 */
	public function parse( array $input ): CourseSearchRequest {
		$criteria = new SearchCriteria(
			$this->search_term( $input['q'] ?? null ),
			array_map(
				static fn ( int $value ): ProviderId => new ProviderId( $value ),
				$this->positive_integers( $input['provider'] ?? null )
			),
			array_map(
				static fn ( int $value ): LocationId => new LocationId( $value ),
				$this->positive_integers( $input['location'] ?? null )
			),
			$this->start_dates( $input['start_date'] ?? null ),
			array_map(
				static fn ( int $value ): CategoryId => new CategoryId( $value ),
				$this->positive_integers( $input['category'] ?? null )
			),
			$this->result_order( $input['order'] ?? null )
		);

		return new CourseSearchRequest(
			$criteria,
			$this->page( $input['course_page'] ?? $input['page'] ?? null ),
			$this->per_page( $input['per_page'] ?? null )
		);
	}

	/**
	 * Parse one optional search term.
	 *
	 * @param mixed $value Untrusted search input.
	 */
	private function search_term( mixed $value ): ?SearchTerm {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$value = sanitize_text_field( $value );

		if ( self::MAX_SEARCH_TERM_LENGTH * 4 < strlen( $value ) ) {
			return null;
		}

		$length = preg_match_all( '/./us', $value );

		if ( false === $length || self::MAX_SEARCH_TERM_LENGTH < $length ) {
			return null;
		}

		try {
			return new SearchTerm( $value );
		} catch ( InvalidArgumentException ) {
			return null;
		}
	}

	/**
	 * Parse positive integer selections.
	 *
	 * @param mixed $value Untrusted scalar or list input.
	 *
	 * @return list<int>
	 */
	private function positive_integers( mixed $value ): array {
		$values  = array_slice(
			is_array( $value ) ? $value : array( $value ),
			0,
			self::MAX_FILTER_VALUES
		);
		$parsed  = array();
		$indexed = array();

		foreach ( $values as $candidate ) {
			$identifier = $this->validated_positive_integer( $candidate );

			if ( null !== $identifier && ! isset( $indexed[ $identifier ] ) ) {
				$parsed[]               = $identifier;
				$indexed[ $identifier ] = true;
			}
		}

		return $parsed;
	}

	/**
	 * Parse canonical start-month selections.
	 *
	 * @param mixed $value Untrusted scalar or list input.
	 *
	 * @return list<StartDate>
	 */
	private function start_dates( mixed $value ): array {
		$values  = array_slice(
			is_array( $value ) ? $value : array( $value ),
			0,
			self::MAX_FILTER_VALUES
		);
		$dates   = array();
		$indexed = array();

		foreach ( $values as $candidate ) {
			if ( ! is_string( $candidate ) ) {
				continue;
			}

			try {
				$date = new StartDate( $candidate );
			} catch ( InvalidArgumentException ) {
				continue;
			}

			if ( isset( $indexed[ $date->value() ] ) ) {
				continue;
			}

			$dates[]                   = $date;
			$indexed[ $date->value() ] = true;
		}

		return $dates;
	}

	/**
	 * Parse the supported semantic order.
	 *
	 * The current public UI exposes only the deterministic default order.
	 *
	 * @param mixed $value Untrusted order key.
	 */
	private function result_order( mixed $value ): ResultOrder {
		if ( ! is_string( $value ) || ResultOrder::DEFAULT_KEY !== $value ) {
			return ResultOrder::default();
		}

		return new ResultOrder( $value );
	}

	/**
	 * Parse a bounded one-based result page.
	 *
	 * @param mixed $value Untrusted page value.
	 */
	private function page( mixed $value ): int {
		$page = $this->validated_positive_integer( $value );

		return null !== $page && self::MAX_PAGE >= $page ? $page : 1;
	}

	/**
	 * Parse a bounded page size.
	 *
	 * @param mixed $value Untrusted page size.
	 */
	private function per_page( mixed $value ): int {
		$per_page = $this->validated_positive_integer( $value );

		return null !== $per_page && self::MAX_PER_PAGE >= $per_page
			? $per_page
			: self::DEFAULT_PER_PAGE;
	}

	/**
	 * Validate one positive integer without normalization.
	 *
	 * @param mixed $value Untrusted integer value.
	 */
	private function validated_positive_integer( mixed $value ): ?int {
		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return null;
		}

		$validated = filter_var(
			$value,
			FILTER_VALIDATE_INT,
			array(
				'options' => array(
					'min_range' => 1,
				),
			)
		);

		return false === $validated ? null : $validated;
	}
}
