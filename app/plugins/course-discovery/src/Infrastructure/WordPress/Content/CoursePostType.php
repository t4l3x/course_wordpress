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
			'labels'             => array(
				'name'               => __( 'Courses', 'course-discovery' ),
				'singular_name'      => __( 'Course', 'course-discovery' ),
				'add_new'            => __( 'Add Course', 'course-discovery' ),
				'add_new_item'       => __( 'Add New Course', 'course-discovery' ),
				'edit_item'          => __( 'Edit Course', 'course-discovery' ),
				'new_item'           => __( 'New Course', 'course-discovery' ),
				'all_items'          => __( 'All Courses', 'course-discovery' ),
				'search_items'       => __( 'Search Courses', 'course-discovery' ),
				'not_found'          => __( 'No courses found.', 'course-discovery' ),
				'not_found_in_trash' => __( 'No courses found in Trash.', 'course-discovery' ),
			),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'has_archive'        => false,
			'rewrite'            => false,
			'menu_icon'          => 'dashicons-welcome-learn-more',
			'supports'           => array( 'title', 'editor', 'excerpt', 'custom-fields' ),
		);

		/**
		 * Filters the Course post type registration arguments.
		 *
		 * Extensions must return a WordPress post type registration argument array.
		 *
		 * @param array<string, mixed> $args Course post type arguments.
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
