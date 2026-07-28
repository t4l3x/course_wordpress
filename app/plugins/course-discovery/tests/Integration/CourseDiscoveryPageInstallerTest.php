<?php
/**
 * Integration tests for the public Course Discovery page installer.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Tests\Integration;

use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend\CourseDiscoveryShortcode;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Setup\CourseDiscoveryPageInstaller;
use WP_Post;
use WP_UnitTestCase;

/**
 * Verifies portable and repeatable landing-page setup through real WordPress.
 */
final class CourseDiscoveryPageInstallerTest extends WP_UnitTestCase {
	/**
	 * Setup creates one published canonical Shortcode-block page and then reuses it.
	 */
	public function test_setup_creates_and_reuses_canonical_page(): void {
		$installer = new CourseDiscoveryPageInstaller();
		$first     = $installer->ensure_page();
		$second    = $installer->ensure_page();
		$page      = get_post( $first['page_id'] );

		self::assertTrue( $first['created'] );
		self::assertFalse( $first['updated'] );
		self::assertFalse( $second['created'] );
		self::assertFalse( $second['updated'] );
		self::assertSame( $first['page_id'], $second['page_id'] );
		self::assertSame( $first['page_id'], (int) get_option( CourseDiscoveryPageInstaller::PAGE_OPTION ) );
		self::assertInstanceOf( WP_Post::class, $page );
		self::assertSame( 'publish', $page->post_status );
		self::assertSame( CourseDiscoveryPageInstaller::PAGE_CONTENT, $page->post_content );
		self::assertStringContainsString( '<!-- wp:group {"align":"full"', $page->post_content );
		self::assertTrue( has_shortcode( $page->post_content, CourseDiscoveryShortcode::SHORTCODE ) );
		self::assertSame( $first['url'], get_permalink( $page ) );
	}

	/**
	 * Non-forced setup preserves existing reserved-page content and adds the shortcode.
	 */
	public function test_setup_preserves_existing_page_content_when_adding_shortcode(): void {
		$page_id = self::factory()->post->create(
			array(
				'post_content' => '<p>Existing introduction.</p>',
				'post_name'    => CourseDiscoveryPageInstaller::PAGE_SLUG,
				'post_status'  => 'draft',
				'post_title'   => 'Existing Course Page',
				'post_type'    => 'page',
			)
		);

		$result = ( new CourseDiscoveryPageInstaller() )->ensure_page();
		$page   = get_post( $page_id );

		self::assertSame( $page_id, $result['page_id'] );
		self::assertFalse( $result['created'] );
		self::assertTrue( $result['updated'] );
		self::assertInstanceOf( WP_Post::class, $page );
		self::assertSame( 'publish', $page->post_status );
		self::assertStringStartsWith( '<p>Existing introduction.</p>', $page->post_content );
		self::assertStringContainsString( CourseDiscoveryPageInstaller::PAGE_CONTENT, $page->post_content );
	}

	/**
	 * Forced setup normalizes a paragraph shortcode into the canonical Shortcode block.
	 */
	public function test_forced_setup_normalizes_existing_shortcode_page(): void {
		$page_id = self::factory()->post->create(
			array(
				'post_content' => "<!-- wp:paragraph -->\n<p>[course_discovery]</p>\n<!-- /wp:paragraph -->",
				'post_name'    => CourseDiscoveryPageInstaller::PAGE_SLUG,
				'post_status'  => 'publish',
				'post_title'   => 'Course Discovery',
				'post_type'    => 'page',
			)
		);

		$result = ( new CourseDiscoveryPageInstaller() )->ensure_page( true );
		$page   = get_post( $page_id );

		self::assertSame( $page_id, $result['page_id'] );
		self::assertFalse( $result['created'] );
		self::assertTrue( $result['updated'] );
		self::assertInstanceOf( WP_Post::class, $page );
		self::assertSame( CourseDiscoveryPageInstaller::PAGE_CONTENT, $page->post_content );
	}
}
