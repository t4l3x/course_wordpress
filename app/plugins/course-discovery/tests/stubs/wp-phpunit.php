<?php
/**
 * Static-analysis declarations for the WordPress PHPUnit environment.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static-analysis shape of the WordPress integration test base class.
 */
abstract class WP_UnitTestCase extends TestCase {
	/**
	 * Expect one WordPress incorrect-usage diagnostic.
	 *
	 * @param string $doing_it_wrong Class or function reporting incorrect usage.
	 */
	public function setExpectedIncorrectUsage( string $doing_it_wrong ): void {
		throw new LogicException( 'Static-analysis stub only.' );
	}

    /**
     * Return WordPress's test-data factory.
     */
    protected static function factory(): WP_UnitTest_Factory {
        throw new LogicException( 'Static-analysis stub only.' );
    }
}

/**
 * Static-analysis shape of the WordPress test-data factory.
 */
class WP_UnitTest_Factory {
    public WP_UnitTest_Factory_For_Post $post;
}

/**
 * Static-analysis shape of the WordPress post factory.
 */
class WP_UnitTest_Factory_For_Post {
    /**
     * Create a post fixture.
     *
     * @param array<string, mixed> $arguments Post fields.
     */
    public function create( array $arguments = array() ): int {
        throw new LogicException( 'Static-analysis stub only.' );
    }
}

/**
 * Register a callback before the WordPress test environment loads.
 *
 * @param string   $hook_name     Hook name.
 * @param callable $callback      Hook callback.
 * @param int      $priority      Hook priority.
 * @param int      $accepted_args Accepted argument count.
 *
 * @return true
 */
function tests_add_filter( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
    return true;
}
