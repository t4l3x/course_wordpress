<?php
/**
 * Course category taxonomy registration.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content;

/**
 * Registers hierarchical categories for courses.
 */
final class CourseCategoryTaxonomy {
	public const string TAXONOMY    = 'cd_course_category';
	public const string ARGS_FILTER = 'course_discovery/course_category_taxonomy_args';

	/**
	 * Register the course category taxonomy.
	 */
	public function register(): void {
		$args = array(
			'labels'            => array(
				'name'              => __( 'Course Categories', 'course-discovery' ),
				'singular_name'     => __( 'Course Category', 'course-discovery' ),
				'search_items'      => __( 'Search Course Categories', 'course-discovery' ),
				'all_items'         => __( 'All Course Categories', 'course-discovery' ),
				'parent_item'       => __( 'Parent Course Category', 'course-discovery' ),
				'parent_item_colon' => __( 'Parent Course Category:', 'course-discovery' ),
				'edit_item'         => __( 'Edit Course Category', 'course-discovery' ),
				'add_new_item'      => __( 'Add New Course Category', 'course-discovery' ),
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => false,
		);

		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Slash separates the plugin namespace.
		$args = apply_filters( 'course_discovery/course_category_taxonomy_args', $args );
		if ( ! is_array( $args ) ) {
			return;
		}

		register_taxonomy( self::TAXONOMY, array( CoursePostType::POST_TYPE ), $args );
	}
}
