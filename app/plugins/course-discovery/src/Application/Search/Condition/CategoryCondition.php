<?php
/**
 * Course Category-selection condition.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Application\Search\Condition;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQueryConditionInterface;
use OxfordInternational\CourseDiscovery\Domain\Category\CategoryId;

/**
 * Matches a Course in any selected hierarchical Course Category.
 */
final readonly class CategoryCondition implements CourseQueryConditionInterface {
	public const string KEY = 'category';

	/**
	 * Create a Course Category-selection condition.
	 *
	 * @param array $categories Selected Course Category terms.
	 *
	 * @throws InvalidArgumentException When no Course Category is selected.
	 *
	 * @phpstan-param list<CategoryId> $categories
	 */
	public function __construct(
		private array $categories
	) {
		if ( array() === $categories ) {
			throw new InvalidArgumentException( 'A Category condition requires at least one Course Category.' );
		}
	}

	/**
	 * Return the stable condition key.
	 */
	public function key(): string {
		return self::KEY;
	}

	/**
	 * Return selected Course Categories.
	 *
	 * @return list<CategoryId>
	 */
	public function categories(): array {
		return $this->categories;
	}
}
