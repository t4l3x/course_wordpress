<?php
/**
 * Plugin composition root.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery;

use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseCategoryTaxonomy;
use OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Content\CourseMeta;
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
	}
}
