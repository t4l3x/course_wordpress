<?php
/**
 * Course start-date selection condition.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Application\Search\Condition;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQueryConditionInterface;
use OxfordInternational\CourseDiscovery\Domain\Course\StartDate;

/**
 * Matches a Course with any selected start month.
 */
final readonly class StartDateCondition implements CourseQueryConditionInterface {
	public const string KEY = 'start_date';

	/**
	 * Create a start-date selection condition.
	 *
	 * @param array $start_dates Selected start months.
	 *
	 * @throws InvalidArgumentException When no start month is selected.
	 *
	 * @phpstan-param list<StartDate> $start_dates
	 */
	public function __construct(
		private array $start_dates
	) {
		if ( array() === $start_dates ) {
			throw new InvalidArgumentException( 'A start-date condition requires at least one start month.' );
		}
	}

	/**
	 * Return the stable condition key.
	 */
	public function key(): string {
		return self::KEY;
	}

	/**
	 * Return selected start months.
	 *
	 * @return list<StartDate>
	 */
	public function start_dates(): array {
		return $this->start_dates;
	}
}
