<?php
/**
 * Plugin Name:       Oxford Course Discovery
 * Description:       Extensible course discovery domain and application functionality.
 * Version:           0.1.0
 * Requires at least: 7.0
 * Requires PHP:      8.5
 * Author:            Taleh Aghazada
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       course-discovery
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const COURSE_DISCOVERY_VERSION       = '0.1.0';
const COURSE_DISCOVERY_AUTOLOAD_FILE = __DIR__ . '/vendor/autoload.php';

if ( ! is_readable( COURSE_DISCOVERY_AUTOLOAD_FILE ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__(
					'Course Discovery dependencies are missing. Run composer install in the plugin directory.',
					'course-discovery'
				)
			);
		}
	);

	return;
}

require_once COURSE_DISCOVERY_AUTOLOAD_FILE;

( new OxfordInternational\CourseDiscovery\Plugin() )->register();
