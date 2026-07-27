<?php
/**
 * Public Course filter option.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Frontend;

use InvalidArgumentException;

/**
 * Carries one canonical filter value and its display label.
 */
final readonly class CourseFilterOption {
	/**
	 * Create a filter option.
	 *
	 * @param string $value Canonical submitted value.
	 * @param string $label Public display label.
	 * @param int    $depth Hierarchy depth for visual indentation.
	 *
	 * @throws InvalidArgumentException When the value, label, or depth is invalid.
	 */
	public function __construct(
		private string $value,
		private string $label,
		private int $depth = 0
	) {
		if ( '' === $value || '' === trim( $label ) || 0 > $depth ) {
			throw new InvalidArgumentException( 'A Course filter option requires a value, label, and non-negative depth.' );
		}
	}

	/**
	 * Return the canonical submitted value.
	 */
	public function value(): string {
		return $this->value;
	}

	/**
	 * Return the public display label.
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * Return the hierarchy depth.
	 */
	public function depth(): int {
		return $this->depth;
	}
}
