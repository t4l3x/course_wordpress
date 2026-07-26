<?php
/**
 * Tests for course start dates.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Unit\Domain;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Domain\Course\StartDate;
use PHPUnit\Framework\TestCase;

/**
 * Verifies canonical month-level course dates and ordering.
 */
final class StartDateTest extends TestCase {
	/**
	 * A valid start date retains the required canonical representation.
	 */
	public function test_start_date_has_canonical_representation(): void {
		$start_date = new StartDate( '2026-09' );

		self::assertSame( '2026-09', $start_date->value() );
		self::assertSame( '2026-09', (string) $start_date );
	}

	/**
	 * Canonical dates compare in chronological order.
	 */
	public function test_start_dates_compare_chronologically(): void {
		$september = new StartDate( '2026-09' );
		$january   = new StartDate( '2027-01' );

		self::assertLessThan( 0, $september->compare_to( $january ) );
		self::assertGreaterThan( 0, $january->compare_to( $september ) );
		self::assertSame( 0, $september->compare_to( new StartDate( '2026-09' ) ) );
	}

	/**
	 * Non-canonical or impossible months are rejected.
	 *
	 * @dataProvider invalid_start_date_provider
	 *
	 * @param string $value Invalid start date representation.
	 */
	public function test_invalid_start_dates_are_rejected( string $value ): void {
		$this->expectException( InvalidArgumentException::class );

		new StartDate( $value );
	}

	/**
	 * Invalid start date examples.
	 *
	 * @return array<string, array{string}>
	 */
	public static function invalid_start_date_provider(): array {
		return array(
			'empty'                  => array( '' ),
			'single digit month'     => array( '2026-9' ),
			'month zero'             => array( '2026-00' ),
			'month thirteen'         => array( '2026-13' ),
			'two digit year'         => array( '26-09' ),
			'full calendar date'     => array( '2026-09-01' ),
			'wrong separator'        => array( '2026/09' ),
			'surrounding whitespace' => array( ' 2026-09 ' ),
		);
	}
}
