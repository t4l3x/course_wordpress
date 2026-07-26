<?php
/**
 * Provider post type registration.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content;

/**
 * Registers course providers with WordPress.
 */
final class ProviderPostType {
	public const string POST_TYPE   = 'cd_provider';
	public const string ARGS_FILTER = 'course_discovery/provider_post_type_args';

	/**
	 * Register the provider post type.
	 */
	public function register(): void {
		$args = array(
			'labels'       => array(
				'name'          => __( 'Providers', 'course-discovery' ),
				'singular_name' => __( 'Provider', 'course-discovery' ),
				'add_new_item'  => __( 'Add New Provider', 'course-discovery' ),
				'edit_item'     => __( 'Edit Provider', 'course-discovery' ),
				'new_item'      => __( 'New Provider', 'course-discovery' ),
				'view_item'     => __( 'View Provider', 'course-discovery' ),
				'search_items'  => __( 'Search Providers', 'course-discovery' ),
				'not_found'     => __( 'No providers found.', 'course-discovery' ),
			),
			'public'       => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-building',
			'rewrite'      => false,
			'supports'     => array( 'title', 'editor' ),
		);

		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Slash separates the plugin namespace.
		$args = apply_filters( 'course_discovery/provider_post_type_args', $args );
		if ( ! is_array( $args ) ) {
			return;
		}

		register_post_type( self::POST_TYPE, $args );
	}
}
