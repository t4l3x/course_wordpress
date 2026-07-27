<?php
/**
 * Course filter registry.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Application\Search;

use InvalidArgumentException;
use LogicException;

/**
 * Stores available Course filters in deterministic registration order.
 */
final class CourseFilterRegistry {
	/**
	 * Registered filters indexed by stable key.
	 *
	 * @var array<string, CourseFilterInterface>
	 */
	private array $filters = array();

	/**
	 * Create a registry with optional initial filters.
	 *
	 * @param CourseFilterInterface ...$filters Initial filters.
	 */
	public function __construct( CourseFilterInterface ...$filters ) {
		foreach ( $filters as $filter ) {
			$this->register( $filter );
		}
	}

	/**
	 * Register one filter.
	 *
	 * @param CourseFilterInterface $filter Filter to register.
	 *
	 * @throws InvalidArgumentException When the filter key is invalid.
	 * @throws LogicException When the filter key is already registered.
	 */
	public function register( CourseFilterInterface $filter ): void {
		$key = $filter->key();

		if ( '' === $key || 1 !== preg_match( '/\A[a-z0-9_-]+\z/', $key ) ) {
			throw new InvalidArgumentException( 'A Course filter key must be a stable lowercase identifier.' );
		}

		if ( array_key_exists( $key, $this->filters ) ) {
			throw new LogicException( 'A Course filter with this key is already registered.' );
		}

		$this->filters[ $key ] = $filter;
	}

	/**
	 * Determine whether a filter key is registered.
	 *
	 * @param string $key Stable filter key.
	 */
	public function has( string $key ): bool {
		return array_key_exists( $key, $this->filters );
	}

	/**
	 * Return all filters in registration order.
	 *
	 * @return list<CourseFilterInterface>
	 */
	public function filters(): array {
		return array_values( $this->filters );
	}
}
