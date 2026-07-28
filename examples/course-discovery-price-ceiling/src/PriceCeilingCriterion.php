<?php
/**
 * Typed maximum-price search input.
 *
 * @package CourseDiscoveryPriceCeilingExample
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscoveryExample\PriceCeiling;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriterionInterface;
use OxfordInternational\CourseDiscovery\Domain\Course\Price;

/**
 * Carries one validated, currency-specific third-party maximum price.
 */
final readonly class PriceCeilingCriterion implements SearchCriterionInterface {
	public const string KEY = 'price_ceiling';

	/**
	 * Canonical amounts that DECIMAL(65,30) can compare without rounding.
	 *
	 * The translator also applies this expression to stored metadata so a core
	 * Price with greater precision cannot be rounded into a false match.
	 */
	public const string REPRESENTABLE_AMOUNT_PATTERN = '^(0|[1-9][0-9]{0,34})([.][0-9]{1,30})?$';

	/**
	 * Create a maximum-price criterion.
	 *
	 * @param Price $maximum Maximum accepted Course price in one currency.
	 *
	 * @throws InvalidArgumentException When WordPress cannot compare the amount exactly.
	 */
	public function __construct(
		private Price $maximum
	) {
		self::assert_representable( $maximum );
	}

	/**
	 * Reject an amount that the WordPress translator would round.
	 *
	 * @param Price $price Exact price to validate for this example backend.
	 *
	 * @throws InvalidArgumentException When WordPress cannot compare the amount exactly.
	 */
	public static function assert_representable( Price $price ): void {
		if ( 1 !== preg_match( '/' . self::REPRESENTABLE_AMOUNT_PATTERN . '/D', $price->amount() ) ) {
			throw new InvalidArgumentException(
				'The price-ceiling example supports at most 35 integer and 30 fractional digits.'
			);
		}
	}

	/**
	 * Return the stable extension key.
	 */
	public function key(): string {
		return self::KEY;
	}

	/**
	 * Return the maximum accepted Course price in one currency.
	 */
	public function maximum(): Price {
		return $this->maximum;
	}
}
