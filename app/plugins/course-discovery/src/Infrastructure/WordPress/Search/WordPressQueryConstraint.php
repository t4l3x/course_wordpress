<?php
/**
 * WordPress Course query constraint.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search;

/**
 * Carries raw WordPress clauses emitted by one condition translator.
 */
final readonly class WordPressQueryConstraint {
	/**
	 * Create one translated condition constraint.
	 *
	 * @param array $meta_query_clauses Meta query clauses.
	 * @param array $tax_query_clauses  Taxonomy query clauses.
	 * @param array $query_arguments    Additional WP_Query arguments.
	 *
	 * @phpstan-param list<array<string, mixed>> $meta_query_clauses
	 * @phpstan-param list<array<string, mixed>> $tax_query_clauses
	 * @phpstan-param array<string, mixed> $query_arguments
	 */
	public function __construct(
		private array $meta_query_clauses = array(),
		private array $tax_query_clauses = array(),
		private array $query_arguments = array()
	) {
	}

	/**
	 * Return meta query clauses for this condition.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function meta_query_clauses(): array {
		return $this->meta_query_clauses;
	}

	/**
	 * Return taxonomy query clauses for this condition.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function tax_query_clauses(): array {
		return $this->tax_query_clauses;
	}

	/**
	 * Return other WP_Query constraints for this condition.
	 *
	 * @return array<string, mixed>
	 */
	public function query_arguments(): array {
		return $this->query_arguments;
	}
}
