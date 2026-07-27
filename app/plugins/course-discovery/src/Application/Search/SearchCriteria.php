<?php
/**
 * Course search criteria.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Application\Search;

use InvalidArgumentException;
use LogicException;
use OxfordInternational\CourseDiscovery\Domain\Course\StartDate;
use OxfordInternational\CourseDiscovery\Domain\Category\CategoryId;
use OxfordInternational\CourseDiscovery\Domain\Location\LocationId;
use OxfordInternational\CourseDiscovery\Domain\Provider\ProviderId;

/**
 * Immutable, normalized description of a requested Course search.
 */
final readonly class SearchCriteria {
	/**
	 * Selected providers.
	 *
	 * @var list<ProviderId>
	 */
	private array $providers;

	/**
	 * Selected locations.
	 *
	 * @var list<LocationId>
	 */
	private array $locations;

	/**
	 * Selected start dates.
	 *
	 * @var list<StartDate>
	 */
	private array $start_dates;

	/**
	 * Selected categories.
	 *
	 * @var list<CategoryId>
	 */
	private array $categories;

	/**
	 * Requested result ordering.
	 *
	 * @var ResultOrder
	 */
	private ResultOrder $result_order;

	/**
	 * Typed criteria contributed by extensions, indexed by stable key.
	 *
	 * @var array<string, SearchCriterionInterface>
	 */
	private array $custom_criteria;

	/**
	 * Create normalized Course search criteria.
	 *
	 * Empty collections mean that the corresponding constraint is absent.
	 *
	 * @param SearchTerm|null  $search_term Search text, or null for no text constraint.
	 * @param array            $providers   Selected Providers.
	 * @param array            $locations   Selected Location terms.
	 * @param array            $start_dates Selected start months.
	 * @param array            $categories  Selected Course Category terms.
	 * @param ResultOrder|null $result_order Requested order, or the deterministic default.
	 * @param array            $custom_criteria Typed criteria contributed by extensions.
	 *
	 * @phpstan-param list<ProviderId> $providers
	 * @phpstan-param list<LocationId> $locations
	 * @phpstan-param list<StartDate> $start_dates
	 * @phpstan-param list<CategoryId> $categories
	 * @phpstan-param list<SearchCriterionInterface> $custom_criteria
	 */
	public function __construct(
		private ?SearchTerm $search_term = null,
		array $providers = array(),
		array $locations = array(),
		array $start_dates = array(),
		array $categories = array(),
		?ResultOrder $result_order = null,
		array $custom_criteria = array()
	) {
		$this->providers       = self::unique_values( $providers );
		$this->locations       = self::unique_values( $locations );
		$this->start_dates     = self::unique_values( $start_dates );
		$this->categories      = self::unique_values( $categories );
		$this->result_order    = $result_order ?? ResultOrder::default();
		$this->custom_criteria = self::index_custom_criteria( $custom_criteria );
	}

	/**
	 * Return the optional search term.
	 */
	public function search_term(): ?SearchTerm {
		return $this->search_term;
	}

	/**
	 * Return selected Providers.
	 *
	 * @return list<ProviderId>
	 */
	public function providers(): array {
		return $this->providers;
	}

	/**
	 * Return selected Locations.
	 *
	 * @return list<LocationId>
	 */
	public function locations(): array {
		return $this->locations;
	}

	/**
	 * Return selected start months.
	 *
	 * @return list<StartDate>
	 */
	public function start_dates(): array {
		return $this->start_dates;
	}

	/**
	 * Return selected Course Categories.
	 *
	 * @return list<CategoryId>
	 */
	public function categories(): array {
		return $this->categories;
	}

	/**
	 * Return the requested backend-independent result order.
	 */
	public function result_order(): ResultOrder {
		return $this->result_order;
	}

	/**
	 * Return one extension criterion by key.
	 *
	 * @param string $key Stable criterion key.
	 */
	public function custom_criterion( string $key ): ?SearchCriterionInterface {
		return $this->custom_criteria[ $key ] ?? null;
	}

	/**
	 * Determine whether an extension criterion is present.
	 *
	 * @param string $key Stable criterion key.
	 */
	public function has_custom_criterion( string $key ): bool {
		return array_key_exists( $key, $this->custom_criteria );
	}

	/**
	 * Determine whether the criteria contain no filtering constraints.
	 */
	public function is_empty(): bool {
		return null === $this->search_term
			&& array() === $this->providers
			&& array() === $this->locations
			&& array() === $this->start_dates
			&& array() === $this->categories
			&& array() === $this->custom_criteria;
	}

	/**
	 * Return criteria with a different optional search term.
	 *
	 * @param SearchTerm|null $search_term Search text, or null for no constraint.
	 */
	public function with_search_term( ?SearchTerm $search_term ): self {
		return new self(
			$search_term,
			$this->providers,
			$this->locations,
			$this->start_dates,
			$this->categories,
			$this->result_order,
			array_values( $this->custom_criteria )
		);
	}

	/**
	 * Return criteria with a different Provider selection.
	 *
	 * @param ProviderId ...$providers Selected Providers.
	 */
	public function with_providers( ProviderId ...$providers ): self {
		return new self(
			$this->search_term,
			array_values( $providers ),
			$this->locations,
			$this->start_dates,
			$this->categories,
			$this->result_order,
			array_values( $this->custom_criteria )
		);
	}

	/**
	 * Return criteria with a different Location selection.
	 *
	 * @param LocationId ...$locations Selected Locations.
	 */
	public function with_locations( LocationId ...$locations ): self {
		return new self(
			$this->search_term,
			$this->providers,
			array_values( $locations ),
			$this->start_dates,
			$this->categories,
			$this->result_order,
			array_values( $this->custom_criteria )
		);
	}

	/**
	 * Return criteria with a different start-date selection.
	 *
	 * @param StartDate ...$start_dates Selected start months.
	 */
	public function with_start_dates( StartDate ...$start_dates ): self {
		return new self(
			$this->search_term,
			$this->providers,
			$this->locations,
			array_values( $start_dates ),
			$this->categories,
			$this->result_order,
			array_values( $this->custom_criteria )
		);
	}

	/**
	 * Return criteria with a different Course Category selection.
	 *
	 * @param CategoryId ...$categories Selected Course Categories.
	 */
	public function with_categories( CategoryId ...$categories ): self {
		return new self(
			$this->search_term,
			$this->providers,
			$this->locations,
			$this->start_dates,
			array_values( $categories ),
			$this->result_order,
			array_values( $this->custom_criteria )
		);
	}

	/**
	 * Return criteria with a different result order.
	 *
	 * @param ResultOrder $result_order Result ordering intent.
	 */
	public function with_result_order( ResultOrder $result_order ): self {
		return new self(
			$this->search_term,
			$this->providers,
			$this->locations,
			$this->start_dates,
			$this->categories,
			$result_order,
			array_values( $this->custom_criteria )
		);
	}

	/**
	 * Return criteria with one additional extension criterion.
	 *
	 * @param SearchCriterionInterface $criterion Typed extension criterion.
	 *
	 * @throws InvalidArgumentException When the criterion key is invalid.
	 * @throws LogicException When the criterion key is already present.
	 */
	public function with_custom_criterion( SearchCriterionInterface $criterion ): self {
		return new self(
			$this->search_term,
			$this->providers,
			$this->locations,
			$this->start_dates,
			$this->categories,
			$this->result_order,
			array( ...array_values( $this->custom_criteria ), $criterion )
		);
	}

	/**
	 * Return criteria with an existing extension criterion replaced in place.
	 *
	 * @param SearchCriterionInterface $criterion Replacement extension criterion.
	 *
	 * @throws InvalidArgumentException|LogicException When the key is invalid or not present.
	 */
	public function with_replaced_custom_criterion( SearchCriterionInterface $criterion ): self {
		$key = $criterion->key();

		self::validate_custom_criterion_key( $key );

		if ( ! array_key_exists( $key, $this->custom_criteria ) ) {
			throw new LogicException( 'A custom search criterion with this key is not present.' );
		}

		$custom_criteria         = $this->custom_criteria;
		$custom_criteria[ $key ] = $criterion;

		return new self(
			$this->search_term,
			$this->providers,
			$this->locations,
			$this->start_dates,
			$this->categories,
			$this->result_order,
			array_values( $custom_criteria )
		);
	}

	/**
	 * Normalize duplicate typed values while preserving first-selection order.
	 *
	 * @template T of ProviderId|LocationId|StartDate|CategoryId
	 *
	 * @param array $values Typed values.
	 *
	 * @return list<T>
	 *
	 * @phpstan-param list<T> $values
	 */
	private static function unique_values( array $values ): array {
		$unique = array();

		foreach ( $values as $value ) {
			$key = (string) $value->value();

			if ( ! array_key_exists( $key, $unique ) ) {
				$unique[ $key ] = $value;
			}
		}

		return array_values( $unique );
	}

	/**
	 * Validate and index typed extension criteria in insertion order.
	 *
	 * @param array $criteria Extension criteria.
	 *
	 * @return array<string, SearchCriterionInterface>
	 *
	 * @throws InvalidArgumentException|LogicException When a key is invalid or duplicated.
	 *
	 * @phpstan-param list<SearchCriterionInterface> $criteria
	 */
	private static function index_custom_criteria( array $criteria ): array {
		$indexed = array();

		foreach ( $criteria as $criterion ) {
			$key = $criterion->key();

			self::validate_custom_criterion_key( $key );

			if ( array_key_exists( $key, $indexed ) ) {
				throw new LogicException( 'A custom search criterion with this key is already present.' );
			}

			$indexed[ $key ] = $criterion;
		}

		return $indexed;
	}

	/**
	 * Validate one public extension criterion key.
	 *
	 * @param string $key Criterion key.
	 *
	 * @throws InvalidArgumentException When the criterion key is invalid.
	 */
	private static function validate_custom_criterion_key( string $key ): void {
		if ( '' === $key || 1 !== preg_match( '/\A[a-z0-9_-]+\z/', $key ) ) {
			throw new InvalidArgumentException( 'A custom search criterion key must be a stable lowercase identifier.' );
		}
	}
}
