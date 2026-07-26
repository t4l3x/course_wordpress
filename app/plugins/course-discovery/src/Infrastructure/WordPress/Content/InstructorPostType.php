<?php
/**
 * Instructor post type registration.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content;

/**
 * Registers course instructors with WordPress.
 */
final class InstructorPostType {
	public const string POST_TYPE   = 'cd_instructor';
	public const string ARGS_FILTER = 'course_discovery/instructor_post_type_args';

	/**
	 * Register the instructor post type.
	 */
	public function register(): void {
		$args = array(
			'labels'             => array(
				'name'               => __( 'Instructors', 'course-discovery' ),
				'singular_name'      => __( 'Instructor', 'course-discovery' ),
				'add_new'            => __( 'Add Instructor', 'course-discovery' ),
				'add_new_item'       => __( 'Add New Instructor', 'course-discovery' ),
				'edit_item'          => __( 'Edit Instructor', 'course-discovery' ),
				'new_item'           => __( 'New Instructor', 'course-discovery' ),
				'all_items'          => __( 'All Instructors', 'course-discovery' ),
				'search_items'       => __( 'Search Instructors', 'course-discovery' ),
				'not_found'          => __( 'No instructors found.', 'course-discovery' ),
				'not_found_in_trash' => __( 'No instructors found in Trash.', 'course-discovery' ),
			),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => false,
			'has_archive'        => false,
			'rewrite'            => false,
			'menu_icon'          => 'dashicons-businessperson',
			'supports'           => array( 'title' ),
		);

		/**
		 * Filters the Instructor post type registration arguments.
		 *
		 * Extensions must return a WordPress post type registration argument array.
		 *
		 * @param array<string, mixed> $args Instructor post type arguments.
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
