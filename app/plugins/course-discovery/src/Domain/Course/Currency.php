<?php
/**
 * Supported course price currencies.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Domain\Course;

/**
 * ISO 4217 currencies currently supported for Course prices.
 */
enum Currency: string {
	case GBP = 'GBP';
	case EUR = 'EUR';
	case USD = 'USD';
}
