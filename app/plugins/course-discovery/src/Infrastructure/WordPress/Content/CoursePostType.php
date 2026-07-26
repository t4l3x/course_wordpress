<?php
/**
 * Course post type registration.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content;

/**
 * Registers courses with WordPress.
 */
final class CoursePostType {
	public const string POST_TYPE   = 'cd_course';
	public const string ARGS_FILTER = 'course_discovery/course_post_type_args';

	/**
	 * Register the course post type.
	 */
	public function register(): void {
		$args = array(
			'labels'       => array(
				'name'          => __( 'Courses', 'course-discovery' ),
				'singular_name' => __( 'Course', 'course-discovery' ),
				'add_new_item'  => __( 'Add New Course', 'course-discovery' ),
				'edit_item'     => __( 'Edit Course', 'course-discovery' ),
				'new_item'      => __( 'New Course', 'course-discovery' ),
				'view_item'     => __( 'View Course', 'course-discovery' ),
				'search_items'  => __( 'Search Courses', 'course-discovery' ),
				'not_found'     => __( 'No courses found.', 'course-discovery' ),
			),
			'public'       => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-welcome-learn-more',
			'rewrite'      => false,
			'supports'     => array( 'title', 'editor', 'excerpt', 'custom-fields' ),
		);

		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Slash separates the plugin namespace.
		$args = apply_filters( 'course_discovery/course_post_type_args', $args );
		if ( ! is_array( $args ) ) {
			return;
		}

		register_post_type( self::POST_TYPE, $args );
	}
}
