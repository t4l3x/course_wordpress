<?php
/**
 * Domain-specific post editor placeholders.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Admin;

use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CoursePostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\InstructorPostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\ProviderPostType;
use WP_Post;

/**
 * Replaces generic post editor prompts with content-model language.
 */
final class EditorPlaceholders {
	/**
	 * Customize the title field placeholder for plugin content.
	 *
	 * @param string  $placeholder Existing WordPress placeholder.
	 * @param WP_Post $post        Post being edited.
	 */
	public function title( string $placeholder, WP_Post $post ): string {
		return match ( $post->post_type ) {
			CoursePostType::POST_TYPE => __( 'Course name', 'course-discovery' ),
			ProviderPostType::POST_TYPE => __( 'Provider name', 'course-discovery' ),
			InstructorPostType::POST_TYPE => __( 'Instructor name', 'course-discovery' ),
			default => $placeholder,
		};
	}

	/**
	 * Customize the content editor placeholder for plugin content.
	 *
	 * @param string  $placeholder Existing WordPress placeholder.
	 * @param WP_Post $post        Post being edited.
	 */
	public function content( string $placeholder, WP_Post $post ): string {
		return match ( $post->post_type ) {
			CoursePostType::POST_TYPE => __( 'Add the long course description', 'course-discovery' ),
			default => $placeholder,
		};
	}
}
