<?php
/**
 * WordPress Course condition translator contract.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search;

use OxfordInternational\CourseDiscovery\Application\Search\CourseQueryConditionInterface;

/**
 * Translates one Course condition type into WordPress query constraints.
 */
interface WordPressConditionTranslatorInterface {
	/**
	 * Return the stable condition key handled by this translator.
	 */
	public function key(): string;

	/**
	 * Translate one typed condition into WordPress query constraints.
	 *
	 * @param CourseQueryConditionInterface $condition Backend-independent condition.
	 */
	public function translate( CourseQueryConditionInterface $condition ): WordPressQueryConstraint;
}
