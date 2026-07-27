<?php
/**
 * WordPress Course Provider-condition translator.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\Translator;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Application\Search\Condition\ProviderCondition;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQueryConditionInterface;
use OxfordInternational\CourseDiscovery\Domain\Provider\ProviderId;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMeta;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressConditionTranslatorInterface;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressQueryConstraint;

/**
 * Matches any selected Provider relationship metadata row.
 */
final class ProviderConditionTranslator implements WordPressConditionTranslatorInterface {
	/**
	 * Return the handled condition key.
	 */
	public function key(): string {
		return ProviderCondition::KEY;
	}

	/**
	 * Translate Provider intent to one OR-valued metadata condition.
	 *
	 * @param CourseQueryConditionInterface $condition Backend-independent condition.
	 *
	 * @throws InvalidArgumentException When the condition type does not match the registered key.
	 */
	public function translate( CourseQueryConditionInterface $condition ): WordPressQueryConstraint {
		if ( ! $condition instanceof ProviderCondition ) {
			throw new InvalidArgumentException( 'The Provider translator requires ProviderCondition.' );
		}

		return new WordPressQueryConstraint(
			meta_query_clauses: array(
				array(
					'key'     => CourseMeta::PROVIDER_ID_KEY,
					'value'   => array_map(
						static fn ( ProviderId $provider ): int => $provider->value(),
						$condition->providers()
					),
					'compare' => 'IN',
					'type'    => 'NUMERIC',
				),
			)
		);
	}
}
