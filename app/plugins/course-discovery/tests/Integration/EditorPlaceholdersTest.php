<?php
/**
 * Integration tests for domain-specific editor placeholders.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Integration;

use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CoursePostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\InstructorPostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\ProviderPostType;
use WP_Post;
use WP_UnitTestCase;

/**
 * Verifies editor prompts use the Course Discovery content language.
 */
final class EditorPlaceholdersTest extends WP_UnitTestCase {
	/**
	 * Plugin post types receive contextual title and body prompts.
	 */
	public function test_editor_placeholders_are_contextual_and_scoped(): void {
		$course     = $this->create_post( CoursePostType::POST_TYPE );
		$provider   = $this->create_post( ProviderPostType::POST_TYPE );
		$instructor = $this->create_post( InstructorPostType::POST_TYPE );
		$blog_post  = $this->create_post( 'post' );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing a WordPress core hook.
		self::assertSame( 'Course name', apply_filters( 'enter_title_here', 'Add title', $course ) );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing a WordPress core hook.
		self::assertSame( 'Provider name', apply_filters( 'enter_title_here', 'Add title', $provider ) );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing a WordPress core hook.
		self::assertSame( 'Instructor name', apply_filters( 'enter_title_here', 'Add title', $instructor ) );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing a WordPress core hook.
		self::assertSame( 'Add title', apply_filters( 'enter_title_here', 'Add title', $blog_post ) );

		self::assertSame(
			'Add the long course description',
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing a WordPress core hook.
			apply_filters( 'write_your_story', 'Type / to choose a block', $course )
		);
		self::assertSame(
			'Type / to choose a block',
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing a WordPress core hook.
			apply_filters( 'write_your_story', 'Type / to choose a block', $provider )
		);
		self::assertSame(
			'Type / to choose a block',
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing a WordPress core hook.
			apply_filters( 'write_your_story', 'Type / to choose a block', $instructor )
		);
	}

	/**
	 * Create and load one post fixture.
	 *
	 * @param string $post_type Post type slug.
	 */
	private function create_post( string $post_type ): WP_Post {
		$post_id = self::factory()->post->create( array( 'post_type' => $post_type ) );
		$post    = get_post( $post_id );

		self::assertInstanceOf( WP_Post::class, $post );

		return $post;
	}
}
