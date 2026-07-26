<?php
/**
 * Course administration assets.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Admin;

use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CoursePostType;
use WP_Screen;

/**
 * Loads Course admin assets only where their controls exist.
 */
final class AdminAssets {
	public const string SCRIPT_HANDLE = 'course-discovery-course-admin';

	/**
	 * Create the admin asset loader.
	 *
	 * @param string $plugin_file Main plugin file.
	 * @param string $version     Plugin asset version.
	 */
	public function __construct(
		private string $plugin_file,
		private string $version
	) {
	}

	/**
	 * Enqueue the repeatable start-date behavior on Course edit screens.
	 *
	 * @param string $hook_suffix Current administration page.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if (
			! $screen instanceof WP_Screen
			|| 'post' !== $screen->base
			|| CoursePostType::POST_TYPE !== $screen->post_type
		) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			plugins_url( 'assets/js/course-admin.js', $this->plugin_file ),
			array(),
			$this->version,
			true
		);
	}
}
