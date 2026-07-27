<?php
/**
 * Backend-independent Course query.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Application\Search;

use InvalidArgumentException;
use LogicException;
/**
 * Immutable search specification composed of AND-ed filter conditions.
 */
final readonly class CourseQuery {
	/**
	 * Conditions indexed by stable key.
	 *
	 * @var array<string, CourseQueryConditionInterface>
	 */
	private array $conditions;

	/**
	 * Create a Course query.
	 *
	 * @param array<string, CourseQueryConditionInterface> $conditions   Query conditions.
	 * @param ResultOrder                                  $result_order Result ordering intent.
	 */
	private function __construct( array $conditions, private ResultOrder $result_order ) {
		$this->conditions = $conditions;
	}

	/**
	 * Create an unconstrained query with an explicit result order.
	 *
	 * @param ResultOrder $result_order Result ordering intent.
	 */
	public static function empty( ResultOrder $result_order ): self {
		return new self( array(), $result_order );
	}

	/**
	 * Return the query conditions in registration order.
	 *
	 * @return list<CourseQueryConditionInterface>
	 */
	public function conditions(): array {
		return array_values( $this->conditions );
	}

	/**
	 * Return one query condition by key.
	 *
	 * @param string $key Stable condition key.
	 */
	public function condition( string $key ): ?CourseQueryConditionInterface {
		return $this->conditions[ $key ] ?? null;
	}

	/**
	 * Return the number of top-level query conditions.
	 */
	public function count(): int {
		return count( $this->conditions );
	}

	/**
	 * Return the result ordering intent.
	 */
	public function result_order(): ResultOrder {
		return $this->result_order;
	}

	/**
	 * Return a query with one additional independent condition.
	 *
	 * @param CourseQueryConditionInterface $condition Condition to append.
	 *
	 * @throws InvalidArgumentException|LogicException When the key is invalid or already present.
	 */
	public function with_condition( CourseQueryConditionInterface $condition ): self {
		$key = $condition->key();

		self::validate_condition_key( $key );

		if ( array_key_exists( $key, $this->conditions ) ) {
			throw new LogicException( 'A Course query condition with this key is already present.' );
		}

		$conditions         = $this->conditions;
		$conditions[ $key ] = $condition;

		return new self( $conditions, $this->result_order );
	}

	/**
	 * Return a query without one condition.
	 *
	 * Removing an absent condition is an idempotent no-op.
	 *
	 * @param string $key Stable condition key.
	 *
	 * @throws InvalidArgumentException When the condition key is invalid.
	 */
	public function without_condition( string $key ): self {
		self::validate_condition_key( $key );

		if ( ! array_key_exists( $key, $this->conditions ) ) {
			return $this;
		}

		$conditions = $this->conditions;
		unset( $conditions[ $key ] );

		return new self( $conditions, $this->result_order );
	}

	/**
	 * Return a query with an existing condition replaced in place.
	 *
	 * @param CourseQueryConditionInterface $condition Replacement condition.
	 *
	 * @throws InvalidArgumentException|LogicException When the key is invalid or not present.
	 */
	public function with_replaced_condition( CourseQueryConditionInterface $condition ): self {
		$key = $condition->key();

		self::validate_condition_key( $key );

		if ( ! array_key_exists( $key, $this->conditions ) ) {
			throw new LogicException( 'A Course query condition with this key is not present.' );
		}

		$conditions         = $this->conditions;
		$conditions[ $key ] = $condition;

		return new self( $conditions, $this->result_order );
	}

	/**
	 * Return a query with a different result order.
	 *
	 * @param ResultOrder $result_order Result ordering intent.
	 */
	public function with_result_order( ResultOrder $result_order ): self {
		return new self( $this->conditions, $result_order );
	}

	/**
	 * Validate one public condition key.
	 *
	 * No PHP filter expresses this exact extension-key grammar, so the explicit
	 * format check remains at the query boundary.
	 *
	 * @param string $key Condition key.
	 *
	 * @throws InvalidArgumentException When the condition key is invalid.
	 */
	private static function validate_condition_key( string $key ): void {
		if ( 1 !== preg_match( '/\A[a-z][a-z0-9_-]*\z/', $key ) ) {
			throw new InvalidArgumentException( 'A Course query condition key must be a stable lowercase identifier.' );
		}
	}
}
