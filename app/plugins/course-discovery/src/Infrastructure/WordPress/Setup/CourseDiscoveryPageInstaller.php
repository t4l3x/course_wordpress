<?php
/**
 * Course Discovery landing-page installation.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Setup;

use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend\CourseDiscoveryShortcode;
use RuntimeException;
use WP_Error;
use WP_Post;

/**
 * Creates or reuses the public page that owns the Course Discovery shortcode.
 */
final class CourseDiscoveryPageInstaller {
	public const string PAGE_OPTION  = 'course_discovery_page_id';
	public const string PAGE_SLUG    = 'course-discovery';
	public const string PAGE_CONTENT = '<!-- wp:group {"align":"full","layout":{"type":"default"}} -->'
		. "\n<div class=\"wp-block-group alignfull\">"
		. "\n<!-- wp:shortcode -->\n[course_discovery]\n<!-- /wp:shortcode -->"
		. "\n</div>\n<!-- /wp:group -->";

	/**
	 * Create the page during plugin activation without replacing existing page content.
	 *
	 * @throws RuntimeException When WordPress cannot create or record the page.
	 */
	public function install(): void {
		$this->ensure_page();
	}

	/**
	 * Create, reuse, or explicitly normalize the Course Discovery page.
	 *
	 * @param bool $force Replace an existing matching page with canonical shortcode-block content.
	 *
	 * @return array{page_id: int, created: bool, updated: bool, url: string}
	 *
	 * @throws RuntimeException When WordPress cannot create, update, or record the page.
	 */
	public function ensure_page( bool $force = false ): array {
		$page    = $this->existing_page();
		$created = false;
		$updated = false;

		if ( $page instanceof WP_Post ) {
			$requires_shortcode = ! has_shortcode( $page->post_content, CourseDiscoveryShortcode::SHORTCODE );

			if ( $force || $requires_shortcode || 'publish' !== $page->post_status ) {
				$content = $force
					? self::PAGE_CONTENT
					: $this->content_with_shortcode( $page->post_content );
				$result  = wp_update_post(
					array(
						'ID'           => $page->ID,
						'post_content' => $content,
						'post_status'  => 'publish',
					),
					true
				);

				if ( $result instanceof WP_Error ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The WordPress error is preserved, not output.
					throw new RuntimeException( $result->get_error_message() );
				}

				$page    = get_post( $result );
				$updated = true;
			}
		} else {
			$result = wp_insert_post(
				array(
					'post_content' => self::PAGE_CONTENT,
					'post_name'    => self::PAGE_SLUG,
					'post_status'  => 'publish',
					'post_title'   => __( 'Course Discovery', 'course-discovery' ),
					'post_type'    => 'page',
				),
				true
			);

			if ( $result instanceof WP_Error ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The WordPress error is preserved, not output.
				throw new RuntimeException( $result->get_error_message() );
			}

			$page    = get_post( $result );
			$created = true;
		}

		if ( ! $page instanceof WP_Post ) {
			throw new RuntimeException( 'WordPress could not load the Course Discovery page after installation.' );
		}

		$stored = update_option( self::PAGE_OPTION, $page->ID, false );

		if ( false === $stored && (int) get_option( self::PAGE_OPTION, 0 ) !== $page->ID ) {
			throw new RuntimeException( 'WordPress could not record the Course Discovery page.' );
		}

		$url = get_permalink( $page );

		if ( '' === $url ) {
			throw new RuntimeException( 'WordPress could not resolve the Course Discovery page URL.' );
		}

		return array(
			'page_id' => $page->ID,
			'created' => $created,
			'updated' => $updated,
			'url'     => $url,
		);
	}

	/**
	 * Find a valid stored page, reserved slug, or existing shortcode page.
	 */
	private function existing_page(): ?WP_Post {
		$stored_id = filter_var(
			get_option( self::PAGE_OPTION, 0 ),
			FILTER_VALIDATE_INT,
			array(
				'options' => array(
					'min_range' => 1,
				),
			)
		);

		if ( false !== $stored_id ) {
			$stored = get_post( $stored_id );

			if ( $stored instanceof WP_Post && 'page' === $stored->post_type && 'trash' !== $stored->post_status ) {
				return $stored;
			}
		}

		$reserved = get_page_by_path( self::PAGE_SLUG, OBJECT, 'page' );

		if ( $reserved instanceof WP_Post && 'trash' !== $reserved->post_status ) {
			return $reserved;
		}

		$pages = get_posts(
			array(
				'no_found_rows'    => true,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				'post_type'        => 'page',
				'posts_per_page'   => -1,
				'suppress_filters' => false,
			)
		);

		foreach ( $pages as $page ) {
			if ( has_shortcode( $page->post_content, CourseDiscoveryShortcode::SHORTCODE ) ) {
				return $page;
			}
		}

		return null;
	}

	/**
	 * Preserve existing page content while adding the missing shortcode block.
	 *
	 * @param string $content Existing page content.
	 */
	private function content_with_shortcode( string $content ): string {
		$content = trim( $content );

		return '' === $content ? self::PAGE_CONTENT : $content . "\n\n" . self::PAGE_CONTENT;
	}
}
