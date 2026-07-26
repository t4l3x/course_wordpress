<?php
/**
 * Plugin composition root.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery;

use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Admin\AdminAssets;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Admin\CourseMetaBox;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Admin\CourseMetaSaveHandler;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Admin\EditorPlaceholders;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseCategoryTaxonomy;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMeta;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMetadataStore;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CoursePostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\InstructorPostType;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\LocationTaxonomy;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\ProviderPostType;

/**
 * Connects the plugin's WordPress infrastructure to the runtime.
 */
final class Plugin {
	/**
	 * Register plugin hooks.
	 */
	public function register(): void {
		add_action( 'init', array( new CoursePostType(), 'register' ) );
		add_action( 'init', array( new ProviderPostType(), 'register' ) );
		add_action( 'init', array( new InstructorPostType(), 'register' ) );
		add_action( 'init', array( new CourseCategoryTaxonomy(), 'register' ) );
		add_action( 'init', array( new LocationTaxonomy(), 'register' ) );
		add_action( 'init', array( new CourseMeta(), 'register' ) );

		$metadata_store = new CourseMetadataStore();
		$meta_box       = new CourseMetaBox( $metadata_store );
		$save_handler   = new CourseMetaSaveHandler( $metadata_store );
		$placeholders   = new EditorPlaceholders();
		$admin_assets   = new AdminAssets(
			dirname( __DIR__ ) . '/course-discovery.php',
			COURSE_DISCOVERY_VERSION
		);

		add_action(
			'add_meta_boxes_' . CoursePostType::POST_TYPE,
			array( $meta_box, 'register' )
		);
		add_action(
			'save_post_' . CoursePostType::POST_TYPE,
			array( $save_handler, 'save' ),
			10,
			3
		);
		add_action( 'admin_notices', array( $save_handler, 'render_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $admin_assets, 'enqueue' ) );
		add_filter( 'enter_title_here', array( $placeholders, 'title' ), 10, 2 );
		add_filter( 'write_your_story', array( $placeholders, 'content' ), 10, 2 );
	}
}
