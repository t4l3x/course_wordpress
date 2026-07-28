<?php
/**
 * Public-input parser for the example maximum-price criterion.
 *
 * @package CourseDiscoveryPriceCeilingExample
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscoveryExample\PriceCeiling;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Domain\Course\Currency;
use OxfordInternational\CourseDiscovery\Domain\Course\Price;

/**
 * Converts already-sanitized scalar input to the example's typed criterion.
 */
final class PriceCeilingRequest {
	/** Maximum safe raw length: 35 integer digits, a point, and 30 decimals. */
	public const int MAX_AMOUNT_INPUT_LENGTH = 66;

	/**
	 * Parse an exact amount and supported ISO currency code.
	 *
	 * @param string $amount        Canonicalizable decimal input.
	 * @param string $currency_code ISO 4217 currency code.
	 */
	public static function criterion( string $amount, string $currency_code ): ?PriceCeilingCriterion {
		if ( self::MAX_AMOUNT_INPUT_LENGTH < strlen( $amount ) ) {
			return null;
		}

		$currency = Currency::tryFrom( $currency_code );

		if ( null === $currency ) {
			return null;
		}

		try {
			return new PriceCeilingCriterion( Price::from_decimal( $amount, $currency ) );
		} catch ( InvalidArgumentException ) {
			return null;
		}
	}
}
