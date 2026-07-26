<?php
/**
 * Base class for isolated tests that mock WordPress behavior with Brain Monkey.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Unit;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * Provides deterministic Brain Monkey setup and cleanup for each unit test.
 */
abstract class TestCase extends PhpUnitTestCase {
	/**
	 * Prepare WordPress function and hook mocks.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Remove all WordPress function and hook mocks.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
