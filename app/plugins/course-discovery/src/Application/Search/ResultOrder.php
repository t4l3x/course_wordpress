<?php
/**
 * Course search result ordering.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Application\Search;

use InvalidArgumentException;

/**
 * Names a backend-independent result ordering strategy.
 */
final readonly class ResultOrder {
	public const string DEFAULT_KEY = 'default';

	/**
	 * Create a result ordering strategy.
	 *
	 * The key describes application intent and is not a WordPress orderby value.
	 *
	 * @param string $key Stable semantic ordering key.
	 *
	 * @throws InvalidArgumentException When the key is not a stable identifier.
	 */
	public function __construct(
		private string $key
	) {
		if ( '' === $key || 1 !== preg_match( '/\A[a-z0-9_-]+\z/', $key ) ) {
			throw new InvalidArgumentException( 'A result order key must be a stable lowercase identifier.' );
		}
	}

	/**
	 * Return the deterministic core ordering strategy.
	 */
	public static function default(): self {
		return new self( self::DEFAULT_KEY );
	}

	/**
	 * Return the semantic ordering key.
	 */
	public function key(): string {
		return $this->key;
	}
}
