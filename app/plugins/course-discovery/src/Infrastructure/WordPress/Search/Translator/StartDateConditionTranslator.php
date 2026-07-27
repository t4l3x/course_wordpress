<?php
/**
 * WordPress Course start-date-condition translator.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\Translator;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Application\Search\Condition\StartDateCondition;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQueryConditionInterface;
use OxfordInternational\CourseDiscovery\Domain\Course\StartDate;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMeta;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressConditionTranslatorInterface;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressQueryConstraint;

/**
 * Matches any selected canonical start-month metadata row.
 */
final class StartDateConditionTranslator implements WordPressConditionTranslatorInterface {
	/**
	 * Return the handled condition key.
	 */
	public function key(): string {
		return StartDateCondition::KEY;
	}

	/**
	 * Translate start-date intent to one OR-valued metadata condition.
	 *
	 * @param CourseQueryConditionInterface $condition Backend-independent condition.
	 *
	 * @throws InvalidArgumentException When the condition type does not match the registered key.
	 */
	public function translate( CourseQueryConditionInterface $condition ): WordPressQueryConstraint {
		if ( ! $condition instanceof StartDateCondition ) {
			throw new InvalidArgumentException( 'The start-date translator requires StartDateCondition.' );
		}

		return new WordPressQueryConstraint(
			meta_query_clauses: array(
				array(
					'key'     => CourseMeta::START_DATE_KEY,
					'value'   => array_map(
						static fn ( StartDate $start_date ): string => $start_date->value(),
						$condition->start_dates()
					),
					'compare' => 'IN',
					'type'    => 'CHAR',
				),
			)
		);
	}
}
