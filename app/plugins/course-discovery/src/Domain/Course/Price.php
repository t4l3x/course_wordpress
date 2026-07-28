<?php
/**
 * Course price.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Domain\Course;

use InvalidArgumentException;

/**
 * Represents an exact non-negative amount in a supported currency.
 */
final readonly class Price {
	/**
	 * Canonical decimal amount.
	 *
	 * @var string
	 */
	private string $amount;

	/**
	 * Create a price from its exact decimal amount and currency.
	 *
	 * @param string   $amount   Decimal amount.
	 * @param Currency $currency ISO 4217 currency.
	 *
	 * @throws InvalidArgumentException When the value is not a non-negative decimal.
	 */
	public static function from_decimal( string $amount, Currency $currency ): self {
		return new self( self::canonicalize_amount( $amount ), $currency );
	}

	/**
	 * Validate and canonicalize an exact decimal amount.
	 *
	 * This supports scalar persistence boundaries where the amount and currency
	 * are intentionally stored separately.
	 *
	 * @param string $amount Decimal amount.
	 *
	 * @throws InvalidArgumentException When the value is not a non-negative decimal.
	 */
	public static function canonicalize_amount( string $amount ): string {
		if ( 1 !== preg_match( '/\A[0-9]+(?:\.[0-9]+)?\z/', $amount ) ) {
			throw new InvalidArgumentException( 'A price amount must be a non-negative decimal string.' );
		}

		$parts    = explode( '.', $amount, 2 );
		$whole    = ltrim( $parts[0], '0' );
		$whole    = '' === $whole ? '0' : $whole;
		$fraction = isset( $parts[1] ) ? rtrim( $parts[1], '0' ) : '';

		return '' === $fraction ? $whole : $whole . '.' . $fraction;
	}

	/**
	 * Return the canonical decimal amount.
	 */
	public function amount(): string {
		return $this->amount;
	}

	/**
	 * Return the ISO 4217 currency.
	 */
	public function currency(): Currency {
		return $this->currency;
	}

	/**
	 * Store an already validated amount and currency.
	 *
	 * @param string   $amount   Canonical decimal amount.
	 * @param Currency $currency ISO 4217 currency.
	 */
	private function __construct( string $amount, private Currency $currency ) {
		$this->amount = $amount;
	}
}
