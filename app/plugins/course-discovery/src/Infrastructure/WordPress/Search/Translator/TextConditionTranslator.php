<?php
/**
 * WordPress Course text-condition translator.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\Translator;

use InvalidArgumentException;
use OxfordInternational\CourseDiscovery\Application\Search\Condition\TextCondition;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQueryConditionInterface;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressConditionTranslatorInterface;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressQueryConstraint;

/**
 * Uses WordPress native search across Course title, excerpt, and content.
 */
final class TextConditionTranslator implements WordPressConditionTranslatorInterface {
	/**
	 * Return the handled condition key.
	 */
	public function key(): string {
		return TextCondition::KEY;
	}

	/**
	 * Translate Course text intent to native WP_Query search arguments.
	 *
	 * @param CourseQueryConditionInterface $condition Backend-independent condition.
	 *
	 * @throws InvalidArgumentException When the condition type does not match the registered key.
	 */
	public function translate( CourseQueryConditionInterface $condition ): WordPressQueryConstraint {
		if ( ! $condition instanceof TextCondition ) {
			throw new InvalidArgumentException( 'The text translator requires TextCondition.' );
		}

		return new WordPressQueryConstraint(
			query_arguments: array(
				's'              => $condition->search_term()->value(),
				'search_columns' => array( 'post_title', 'post_excerpt', 'post_content' ),
			)
		);
	}
}
