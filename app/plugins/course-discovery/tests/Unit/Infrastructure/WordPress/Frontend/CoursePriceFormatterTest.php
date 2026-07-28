<?php
/**
 * Tests for exact Course price display formatting.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Unit\Infrastructure\WordPress\Frontend;

use OxfordInternational\CourseDiscovery\Domain\Course\Currency;
use OxfordInternational\CourseDiscovery\Domain\Course\Price;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend\CoursePriceFormatter;
use OxfordInternational\CourseDiscovery\Tests\Unit\TestCase;

/**
 * Verifies symbol selection and exact decimal presentation without float coercion.
 */
final class CoursePriceFormatterTest extends TestCase {
	/**
	 * Supported prices render with grouped integer digits and at least two fraction digits.
	 *
	 * @dataProvider formatted_price_provider
	 *
	 * @param string   $amount   Exact decimal amount.
	 * @param Currency $currency Supported currency.
	 * @param string   $expected Expected display string.
	 */
	public function test_price_is_formatted_exactly(
		string $amount,
		Currency $currency,
		string $expected
	): void {
		$formatter = new CoursePriceFormatter();

		self::assertSame(
			$expected,
			$formatter->format( Price::from_decimal( $amount, $currency ) )
		);
	}

	/**
	 * Exact Course price display examples.
	 *
	 * @return array<string, array{string, Currency, string}>
	 */
	public static function formatted_price_provider(): array {
		return array(
			'GBP with grouping'               => array( '1250', Currency::GBP, '£1,250.00' ),
			'EUR with two decimal places'     => array( '980.25', Currency::EUR, '€980.25' ),
			'USD pads one decimal place'      => array( '1400.5', Currency::USD, '$1,400.50' ),
			'zero'                            => array( '0', Currency::GBP, '£0.00' ),
			'precision is never truncated'    => array( '12.3456', Currency::EUR, '€12.3456' ),
			'large exact amount is preserved' => array(
				'12345678901234567890.123',
				Currency::USD,
				'$12,345,678,901,234,567,890.123',
			),
		);
	}
}
