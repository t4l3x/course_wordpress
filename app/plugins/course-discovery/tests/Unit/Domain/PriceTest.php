<?php
/**
 * Tests for course prices.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Unit\Domain;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Domain\Course\Price;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the currency-neutral price contract.
 */
final class PriceTest extends TestCase {
	/**
	 * Prices use a canonical decimal representation without using a float.
	 */
	public function test_price_has_a_canonical_decimal_representation(): void {
		$price = new Price( '00125.5000' );

		self::assertSame( '125.5', $price->value() );
		self::assertSame( '125.5', (string) $price );
	}

	/**
	 * Zero is a valid numeric price.
	 */
	public function test_zero_is_a_valid_price(): void {
		$price = new Price( '0' );

		self::assertSame( '0', $price->value() );
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

		new Price( $value );
	}

	/**
	 * Invalid price examples.
	 *
	 * @return array<string, array{string}>
	 */
	public static function invalid_price_provider(): array {
		return array(
			'empty'             => array( '' ),
			'negative'          => array( '-1' ),
			'non-numeric'       => array( 'free' ),
			'currency included' => array( 'GBP 12.00' ),
			'surrounding space' => array( ' 12.00 ' ),
		);
	}
}
