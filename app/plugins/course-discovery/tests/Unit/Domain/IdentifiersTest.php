<?php
/**
 * Tests for content identifier value objects.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Unit\Domain;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Domain\Course\CourseId;
use OxfordInternational\CourseDiscovery\Domain\Instructor\InstructorId;
use OxfordInternational\CourseDiscovery\Domain\Provider\ProviderId;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that identifiers retain their domain meaning and stay positive.
 */
final class IdentifiersTest extends TestCase {
	/**
	 * Positive identifiers retain their integer value.
	 */
	public function test_identifiers_retain_positive_values(): void {
		self::assertSame( 42, ( new CourseId( 42 ) )->value() );
		self::assertSame( 42, ( new ProviderId( 42 ) )->value() );
		self::assertSame( 42, ( new InstructorId( 42 ) )->value() );
	}

	/**
	 * Non-positive identifiers are rejected.
	 *
	 * @dataProvider invalid_identifier_provider
	 *
	 * @param class-string<CourseId|ProviderId|InstructorId> $identifier_class Identifier class under test.
	 * @param int                                            $value            Invalid identifier value.
	 */
	public function test_identifiers_reject_non_positive_values( string $identifier_class, int $value ): void {
		$this->expectException( InvalidArgumentException::class );

		new $identifier_class( $value );
	}

	/**
	 * Invalid identifier examples for every identifier type.
	 *
	 * @return array<string, array{class-string<CourseId|ProviderId|InstructorId>, int}>
	 */
	public static function invalid_identifier_provider(): array {
		return array(
			'zero course ID'         => array( CourseId::class, 0 ),
			'negative course ID'     => array( CourseId::class, -1 ),
			'zero provider ID'       => array( ProviderId::class, 0 ),
			'negative provider ID'   => array( ProviderId::class, -1 ),
			'zero instructor ID'     => array( InstructorId::class, 0 ),
			'negative instructor ID' => array( InstructorId::class, -1 ),
		);
	}
}
