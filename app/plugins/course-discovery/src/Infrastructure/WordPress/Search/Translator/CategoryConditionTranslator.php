<?php
/**
 * WordPress Course Category-condition translator.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\Translator;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Application\Search\Condition\CategoryCondition;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQueryConditionInterface;
use OxfordInternational\CourseDiscovery\Domain\Category\CategoryId;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseCategoryTaxonomy;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressConditionTranslatorInterface;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressQueryConstraint;

/**
 * Matches any selected Course Category, including descendants.
 */
final class CategoryConditionTranslator implements WordPressConditionTranslatorInterface {
	/**
	 * Return the handled condition key.
	 */
	public function key(): string {
		return CategoryCondition::KEY;
	}

	/**
	 * Translate Category intent to one OR-valued taxonomy condition.
	 *
	 * @param CourseQueryConditionInterface $condition Backend-independent condition.
	 *
	 * @throws InvalidArgumentException When the condition type does not match the registered key.
	 */
	public function translate( CourseQueryConditionInterface $condition ): WordPressQueryConstraint {
		if ( ! $condition instanceof CategoryCondition ) {
			throw new InvalidArgumentException( 'The Category translator requires CategoryCondition.' );
		}

		return new WordPressQueryConstraint(
			tax_query_clauses: array(
				array(
					'taxonomy'         => CourseCategoryTaxonomy::TAXONOMY,
					'field'            => 'term_id',
					'terms'            => array_map(
						static fn ( CategoryId $category ): int => $category->value(),
						$condition->categories()
					),
					'operator'         => 'IN',
					'include_children' => true,
				),
			)
		);
	}
}
