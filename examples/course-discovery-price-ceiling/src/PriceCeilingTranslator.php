<?php
/**
 * WordPress maximum-price condition translator.
 *
 * @package CourseDiscoveryPriceCeilingExample
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscoveryExample\PriceCeiling;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQueryConditionInterface;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMeta;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressConditionTranslatorInterface;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressQueryConstraint;

/**
 * Translates maximum-price intent to exact amount and currency metadata.
 */
final class PriceCeilingTranslator implements WordPressConditionTranslatorInterface {
	/**
	 * Return the handled condition key.
	 */
	public function key(): string {
		return PriceCeilingCondition::KEY;
	}

	/**
	 * Translate one maximum-price condition for WP_Query.
	 *
	 * @param CourseQueryConditionInterface $condition Backend-independent condition.
	 *
	 * @throws InvalidArgumentException When the condition does not match this translator.
	 */
	public function translate( CourseQueryConditionInterface $condition ): WordPressQueryConstraint {
		if ( ! $condition instanceof PriceCeilingCondition ) {
			throw new InvalidArgumentException( 'The price-ceiling translator requires PriceCeilingCondition.' );
		}

		PriceCeilingCriterion::assert_representable( $condition->maximum() );

		return new WordPressQueryConstraint(
			meta_query_clauses: array(
				array(
					'key'     => CourseMeta::PRICE_AMOUNT_KEY,
					'value'   => PriceCeilingCriterion::REPRESENTABLE_AMOUNT_PATTERN,
					'compare' => 'REGEXP',
					'type'    => 'CHAR',
				),
				array(
					'key'     => CourseMeta::PRICE_AMOUNT_KEY,
					'value'   => $condition->maximum()->amount(),
					'compare' => '<=',
					'type'    => 'DECIMAL(65,30)',
				),
				array(
					'key'     => CourseMeta::PRICE_CURRENCY_KEY,
					'value'   => $condition->maximum()->currency()->value,
					'compare' => '=',
					'type'    => 'CHAR',
				),
			)
		);
	}
}
