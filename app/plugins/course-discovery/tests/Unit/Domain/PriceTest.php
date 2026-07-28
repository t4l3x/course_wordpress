<?php
/**
 * Tests for course prices.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Unit\Domain;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Domain\Course\Currency;
use OxfordInternational\CourseDiscovery\Domain\Course\Price;
use PHPUnit\Framework\TestCase;
use ValueError;

/**
 * Verifies the exact amount and supported currency contract.
 */
final class PriceTest extends TestCase {
	/**
	 * Prices use a canonical decimal representation without using a float.
	 *
	 * @dataProvider canonical_price_provider
	 *
	 * @param string $input    Decimal input.
	 * @param string $expected Canonical decimal.
	 */
	public function test_price_has_a_canonical_decimal_representation( string $input, string $expected ): void {
		$price = Price::from_decimal( $input, Currency::GBP );

		self::assertSame( $expected, $price->amount() );
		self::assertSame( Currency::GBP, $price->currency() );
	}

	/**
	 * Exactly the currently supported ISO 4217 currencies are retained.
	 *
	 * @dataProvider supported_currency_provider
	 *
	 * @param Currency $currency Supported currency.
	 */
	public function test_supported_currencies_are_retained( Currency $currency ): void {
		$price = Price::from_decimal( '1250.00', $currency );

		self::assertSame( $currency, $price->currency() );
		self::assertSame( '1250', $price->amount() );
	}

	/**
	 * Supported currency examples.
	 *
	 * @return array<string, array{Currency}>
	 */
	public static function supported_currency_provider(): array {
		return array(
			'GBP' => array( Currency::GBP ),
			'EUR' => array( Currency::EUR ),
			'USD' => array( Currency::USD ),
		);
	}

	/**
	 * The backed enum accepts no currency outside the supported set.
	 */
	public function test_unsupported_currency_is_rejected(): void {
		$this->expectException( ValueError::class );

		Currency::from( 'CAD' );
	}

	/**
	 * Canonical decimal examples.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function canonical_price_provider(): array {
		return array(
			'integer'             => array( '00125', '125' ),
			'fraction'            => array( '00125.5000', '125.5' ),
			'zero'                => array( '000.000', '0' ),
			'small fraction'      => array( '000.0100', '0.01' ),
			'arbitrary precision' => array(
				'999999999999999999999999999999.12345678901234567890123456789',
				'999999999999999999999999999999.12345678901234567890123456789',
			),
		);
	}

	/**
	 * Invalid price values are rejected.
	 *
	 * @dataProvider invalid_price_provider
	 *
	 * @param string $value Invalid price representation.
	 */
	public function test_invalid_prices_are_rejected( string $value ): void {
		$this->expectException( InvalidArgumentException::class );

		Price::from_decimal( $value, Currency::GBP );
	}

	/**
	 * Invalid price examples.
	 *
	 * @return array<string, array{string}>
	 */
	public static function invalid_price_provider(): array {
		return array(
			'empty'                  => array( '' ),
			'negative'               => array( '-1' ),
			'negative zero'          => array( '-0' ),
			'leading decimal point'  => array( '.5' ),
			'trailing decimal point' => array( '1.' ),
			'exponent'               => array( '1e2' ),
			'leading plus'           => array( '+1' ),
			'non-numeric'            => array( 'free' ),
			'currency included'      => array( 'GBP 12.00' ),
			'surrounding space'      => array( ' 12.00 ' ),
			'trailing newline'       => array( "1\n" ),
		);
	}
}
