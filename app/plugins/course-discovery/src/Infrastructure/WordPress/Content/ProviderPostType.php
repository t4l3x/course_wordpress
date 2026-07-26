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
			'labels'             => array(
				'name'               => __( 'Providers', 'course-discovery' ),
				'singular_name'      => __( 'Provider', 'course-discovery' ),
				'add_new'            => __( 'Add Provider', 'course-discovery' ),
				'add_new_item'       => __( 'Add New Provider', 'course-discovery' ),
				'edit_item'          => __( 'Edit Provider', 'course-discovery' ),
				'new_item'           => __( 'New Provider', 'course-discovery' ),
				'all_items'          => __( 'All Providers', 'course-discovery' ),
				'search_items'       => __( 'Search Providers', 'course-discovery' ),
				'not_found'          => __( 'No providers found.', 'course-discovery' ),
				'not_found_in_trash' => __( 'No providers found in Trash.', 'course-discovery' ),
			),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => false,
			'has_archive'        => false,
			'rewrite'            => false,
			'menu_icon'          => 'dashicons-building',
			'supports'           => array( 'title' ),
		);

		/**
		 * Filters the Provider post type registration arguments.
		 *
		 * Extensions must return a WordPress post type registration argument array.
		 *
		 * @param array<string, mixed> $args Provider post type arguments.
		 */
		/**
		 * Runtime extension output is untrusted.
		 *
		 * @var mixed $filtered_args
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- The declared constant contains the plugin-prefixed hook name.
		$filtered_args = apply_filters( self::ARGS_FILTER, $args );

		if ( ! is_array( $filtered_args ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'Post type argument filters must return an array.', 'course-discovery' ),
				esc_html( COURSE_DISCOVERY_VERSION )
			);
			$filtered_args = $args;
		}

		register_post_type( self::POST_TYPE, $filtered_args );
	}
}
