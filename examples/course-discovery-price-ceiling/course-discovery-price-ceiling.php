<?php
/**
 * Plugin Name:       Course Discovery Price Ceiling Example
 * Description:       Demonstrates a typed third-party Course Discovery search filter.
 * Version:           0.2.0
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Requires Plugins:  course-discovery
 * Author:            Oxford International
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       course-discovery-price-ceiling
 *
 * @package CourseDiscoveryPriceCeilingExample
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscoveryExample\PriceCeiling;

use OxfordInternational\CourseDiscovery\Application\Search\CourseFilterRegistry;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriteria;
use OxfordInternational\CourseDiscovery\Application\Search\SearchCriterionInterface;
use OxfordInternational\CourseDiscovery\Domain\Course\Currency;
use OxfordInternational\CourseDiscovery\Domain\Course\Price;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressConditionTranslatorRegistry;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressCourseSearch;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressCourseSearchExtensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'plugins_loaded',
	static function (): void {
		if (
			! interface_exists( SearchCriterionInterface::class )
			|| ! enum_exists( Currency::class )
			|| ! class_exists( Price::class )
			|| ! class_exists( WordPressCourseSearchExtensions::class )
			|| ! class_exists( WordPressCourseSearch::class )
		) {
			add_action(
				'admin_notices',
				static function (): void {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__(
							'The Price Ceiling example requires Oxford Course Discovery.',
							'course-discovery-price-ceiling'
						)
					);
				}
			);

			return;
		}

		require_once __DIR__ . '/src/PriceCeilingCriterion.php';
		require_once __DIR__ . '/src/PriceCeilingRequest.php';
		require_once __DIR__ . '/src/PriceCeilingCondition.php';
		require_once __DIR__ . '/src/PriceCeilingFilter.php';
		require_once __DIR__ . '/src/PriceCeilingTranslator.php';

		add_filter(
			WordPressCourseSearchExtensions::SEARCH_CRITERIA_FILTER,
			static function ( SearchCriteria $criteria ): SearchCriteria {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only input is compared with the sanitized representation before use.
				$raw_amount = wp_unslash( $_GET['example_max_price'] ?? '' );
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only input is compared with the sanitized representation before use.
				$raw_currency = wp_unslash( $_GET['example_currency'] ?? '' );

				if ( ! is_string( $raw_amount ) || ! is_string( $raw_currency ) ) {
					return $criteria;
				}

				$amount        = sanitize_text_field( $raw_amount );
				$currency_code = sanitize_text_field( $raw_currency );

				if (
					$amount !== $raw_amount
					|| $currency_code !== $raw_currency
					|| 3 !== strlen( $currency_code )
				) {
					return $criteria;
				}

				$criterion = PriceCeilingRequest::criterion( $amount, $currency_code );

				if ( null === $criterion ) {
					return $criteria;
				}

				return $criteria->has_custom_criterion( PriceCeilingCriterion::KEY )
					? $criteria->with_replaced_custom_criterion( $criterion )
					: $criteria->with_custom_criterion( $criterion );
			}
		);

		add_action(
			WordPressCourseSearchExtensions::REGISTER_FILTERS_ACTION,
			static function ( CourseFilterRegistry $registry ): void {
				$registry->register( new PriceCeilingFilter() );
			}
		);

		add_action(
			WordPressCourseSearch::REGISTER_TRANSLATORS_ACTION,
			static function ( WordPressConditionTranslatorRegistry $registry ): void {
				$registry->register( new PriceCeilingTranslator() );
			}
		);
	}
);
