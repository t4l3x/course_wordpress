<?php
/**
 * Provider location taxonomy registration.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content;

/**
 * Registers locations for providers.
 */
final class LocationTaxonomy {
	public const string TAXONOMY    = 'cd_location';
	public const string ARGS_FILTER = 'course_discovery/location_taxonomy_args';

	/**
	 * Register the provider location taxonomy.
	 */
	public function register(): void {
		$args = array(
			'labels'            => array(
				'name'          => __( 'Locations', 'course-discovery' ),
				'singular_name' => __( 'Location', 'course-discovery' ),
				'search_items'  => __( 'Search Locations', 'course-discovery' ),
				'all_items'     => __( 'All Locations', 'course-discovery' ),
				'edit_item'     => __( 'Edit Location', 'course-discovery' ),
				'add_new_item'  => __( 'Add New Location', 'course-discovery' ),
			),
			'public'            => true,
			'hierarchical'      => false,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => false,
		);

		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Slash separates the plugin namespace.
		$args = apply_filters( 'course_discovery/location_taxonomy_args', $args );
		if ( ! is_array( $args ) ) {
			return;
		}

		register_taxonomy( self::TAXONOMY, array( ProviderPostType::POST_TYPE ), $args );
	}
}
