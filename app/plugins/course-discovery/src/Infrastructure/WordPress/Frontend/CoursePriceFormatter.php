<?php
/**
 * Course price display formatting.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend;

use OxfordInternational\CourseDiscovery\Domain\Course\Currency;
use OxfordInternational\CourseDiscovery\Domain\Course\Price;

/**
 * Formats exact Course prices for the three currently supported currencies.
 */
final class CoursePriceFormatter {
	/**
	 * Format a Course price without converting its exact decimal amount to float.
	 *
	 * @param Price $price Course price.
	 */
	public function format( Price $price ): string {
		$parts    = explode( '.', $price->amount(), 2 );
		$whole    = $this->group_thousands( $parts[0] );
		$fraction = str_pad( $parts[1] ?? '', 2, '0' );

		return $this->symbol( $price->currency() ) . $whole . '.' . $fraction;
	}

	/**
	 * Group the integer digits without numeric coercion.
	 *
	 * @param string $whole Canonical non-negative integer digits.
	 */
	private function group_thousands( string $whole ): string {
		$whole_length   = strlen( $whole );
		$leading_digits = $whole_length % 3;
		$groups         = array();

		if ( 0 !== $leading_digits ) {
			$groups[] = substr( $whole, 0, $leading_digits );
		}

		for ( $offset = $leading_digits; $offset < $whole_length; $offset += 3 ) {
			$groups[] = substr( $whole, $offset, 3 );
		}

		return implode( ',', $groups );
	}

	/**
	 * Return the display symbol for a supported currency.
	 *
	 * @param Currency $currency Supported Course currency.
	 */
	private function symbol( Currency $currency ): string {
		return match ( $currency ) {
			Currency::GBP => '£',
			Currency::EUR => '€',
			Currency::USD => '$',
		};
	}
}
