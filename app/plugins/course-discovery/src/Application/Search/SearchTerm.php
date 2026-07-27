<?php
/**
 * Course search term.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Application\Search;

use InvalidArgumentException;

/**
 * Represents a non-empty plain-text course search term.
 */
final readonly class SearchTerm {
	/**
	 * Normalized search text.
	 *
	 * @var string
	 */
	private string $value;

	/**
	 * Create a normalized search term.
	 *
	 * @param string $value Search text.
	 *
	 * @throws InvalidArgumentException When the value contains no searchable text.
	 */
	public function __construct( string $value ) {
		$normalized = trim( $value );

		if ( '' === $normalized ) {
			throw new InvalidArgumentException( 'A search term must contain non-whitespace text.' );
		}

		$this->value = $normalized;
	}

	/**
	 * Return the normalized search text.
	 */
	public function value(): string {
		return $this->value;
	}
}
