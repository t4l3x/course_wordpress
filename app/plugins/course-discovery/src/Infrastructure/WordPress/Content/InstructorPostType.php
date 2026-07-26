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
			'labels'       => array(
				'name'          => __( 'Instructors', 'course-discovery' ),
				'singular_name' => __( 'Instructor', 'course-discovery' ),
				'add_new_item'  => __( 'Add New Instructor', 'course-discovery' ),
				'edit_item'     => __( 'Edit Instructor', 'course-discovery' ),
				'new_item'      => __( 'New Instructor', 'course-discovery' ),
				'view_item'     => __( 'View Instructor', 'course-discovery' ),
				'search_items'  => __( 'Search Instructors', 'course-discovery' ),
				'not_found'     => __( 'No instructors found.', 'course-discovery' ),
			),
			'public'       => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-businessperson',
			'rewrite'      => false,
			'supports'     => array( 'title', 'editor' ),
		);

		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Slash separates the plugin namespace.
		$args = apply_filters( 'course_discovery/instructor_post_type_args', $args );
		if ( ! is_array( $args ) ) {
			return;
		}

		register_post_type( self::POST_TYPE, $args );
	}
}
