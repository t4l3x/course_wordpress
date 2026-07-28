<?php
/**
 * Plugin composition root.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery;

use OxfordInternational\CourseDiscovery\Application\Search\CourseFilterPipeline;
use OxfordInternational\CourseDiscovery\Application\Search\CourseFilterRegistry;
use OxfordInternational\CourseDiscovery\Application\Search\CourseSearchInterface;
use OxfordInternational\CourseDiscovery\Application\Search\Filter\CategoryFilter;
use OxfordInternational\CourseDiscovery\Application\Search\Filter\LocationFilter;
use OxfordInternational\CourseDiscovery\Application\Search\Filter\ProviderFilter;
use OxfordInternational\CourseDiscovery\Application\Search\Filter\StartDateFilter;
use OxfordInternational\CourseDiscovery\Application\Search\Filter\TextFilter;
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
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Development\CatalogueSeedCommand;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Development\CatalogueSeeder;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Setup\CourseDiscoveryPageInstaller;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Setup\CourseDiscoverySetupCommand;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend\CourseDiscoveryShortcode;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend\CourseFilterOptions;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend\CoursePriceFormatter;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend\CourseResultPresenter;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend\CourseSearchRequestParser;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\Translator\CategoryConditionTranslator;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\Translator\LocationConditionTranslator;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\Translator\ProviderConditionTranslator;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\Translator\StartDateConditionTranslator;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\Translator\TextConditionTranslator;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressConditionTranslatorRegistry;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressCourseSearch;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search\WordPressCourseSearchExtensions;

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
		$shortcode      = new CourseDiscoveryShortcode(
			new CourseSearchRequestParser(),
			$this->course_filter_pipeline(),
			$this->course_search(),
			new CourseFilterOptions(),
			new CourseResultPresenter( $metadata_store, new CoursePriceFormatter() ),
			dirname( __DIR__ ) . '/course-discovery.php',
			dirname( __DIR__ ) . '/templates/course-discovery.php',
			COURSE_DISCOVERY_VERSION
		);
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
		$shortcode->register();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			new CatalogueSeedCommand( new CatalogueSeeder( $metadata_store ) )->register();
			new CourseDiscoverySetupCommand( new CourseDiscoveryPageInstaller() )->register();
		}
	}

	/**
	 * Compose the Course filter pipeline with core filters and extension hooks.
	 */
	public function course_filter_pipeline(): CourseFilterPipeline {
		return new CourseFilterPipeline(
			new CourseFilterRegistry(
				new TextFilter(),
				new ProviderFilter(),
				new LocationFilter(),
				new StartDateFilter(),
				new CategoryFilter()
			),
			new WordPressCourseSearchExtensions()
		);
	}

	/**
	 * Compose WordPress Course search with the core condition translators.
	 */
	public function course_search(): CourseSearchInterface {
		return new WordPressCourseSearch(
			new WordPressConditionTranslatorRegistry(
				new TextConditionTranslator(),
				new ProviderConditionTranslator(),
				new LocationConditionTranslator(),
				new StartDateConditionTranslator(),
				new CategoryConditionTranslator()
			)
		);
	}
}
