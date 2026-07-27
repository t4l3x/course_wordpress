<?php
/**
 * WordPress Course Location-condition translator.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\Translator;

use InvalidArgumentException;
use LogicException;
use OxfordInternational\CourseDiscovery\Application\Search\Condition\LocationCondition;
use OxfordInternational\CourseDiscovery\Application\Search\CourseQueryConditionInterface;
use OxfordInternational\CourseDiscovery\Domain\Location\LocationId;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMeta;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\LocationTaxonomy;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\ProviderPostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressConditionTranslatorInterface;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressQueryConstraint;
use WP_Query;

/**
 * Resolves selected Provider Locations before matching Course relationships.
 */
final class LocationConditionTranslator implements WordPressConditionTranslatorInterface {
	/**
	 * Return the handled condition key.
	 */
	public function key(): string {
		return LocationCondition::KEY;
	}

	/**
	 * Translate Location intent through Provider relationships.
	 *
	 * @param CourseQueryConditionInterface $condition Backend-independent condition.
	 *
	 * @throws InvalidArgumentException When the condition type does not match the registered key.
	 * @throws LogicException When WordPress does not return Provider IDs.
	 */
	public function translate( CourseQueryConditionInterface $condition ): WordPressQueryConstraint {
		if ( ! $condition instanceof LocationCondition ) {
			throw new InvalidArgumentException( 'The Location translator requires LocationCondition.' );
		}

		$provider_query = new WP_Query(
			array(
				'post_type'              => ProviderPostType::POST_TYPE,
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'orderby'                => 'none',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Provider Location derivation requires this native taxonomy lookup.
				'tax_query'              => array(
					array(
						'taxonomy' => LocationTaxonomy::TAXONOMY,
						'field'    => 'term_id',
						'terms'    => array_map(
							static fn ( LocationId $location ): int => $location->value(),
							$condition->locations()
						),
						'operator' => 'IN',
					),
				),
			)
		);
		$provider_ids   = array();

		foreach ( $provider_query->posts as $provider_id ) {
			if ( ! is_int( $provider_id ) ) {
				throw new LogicException( 'WordPress did not return a Provider ID for an ID-only query.' );
			}

			$provider_ids[] = $provider_id;
		}

		if ( array() === $provider_ids ) {
			return new WordPressQueryConstraint(
				query_arguments: array(
					'post__in' => array( 0 ),
				)
			);
		}

		return new WordPressQueryConstraint(
			meta_query_clauses: array(
				array(
					'key'     => CourseMeta::PROVIDER_ID_KEY,
					'value'   => $provider_ids,
					'compare' => 'IN',
					'type'    => 'NUMERIC',
				),
			)
		);
	}
}
