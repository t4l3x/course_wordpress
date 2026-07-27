<?php
/**
 * WordPress Course condition translator registry.
 *
 * @package CourseDiscovery
 */

declare(strict_types=1);

namespace OxfordInternational\CourseDiscovery\Infrastructure\WordPress\Search;

use InvalidArgumentException;
use LogicException;

/**
 * Stores WordPress condition translators in deterministic registration order.
 */
final class WordPressConditionTranslatorRegistry {
	/**
	 * Translators indexed by stable condition key.
	 *
	 * @var array<string, WordPressConditionTranslatorInterface>
	 */
	private array $translators = array();

	/**
	 * Create a registry with optional initial translators.
	 *
	 * @param WordPressConditionTranslatorInterface ...$translators Initial translators.
	 */
	public function __construct( WordPressConditionTranslatorInterface ...$translators ) {
		foreach ( $translators as $translator ) {
			$this->register( $translator );
		}
	}

	/**
	 * Register one condition translator.
	 *
	 * @param WordPressConditionTranslatorInterface $translator Translator to register.
	 *
	 * @throws InvalidArgumentException When the translator key is invalid.
	 * @throws LogicException When the translator key is already registered.
	 */
	public function register( WordPressConditionTranslatorInterface $translator ): void {
		$key = $translator->key();

		if (
			'' === $key
			|| sanitize_key( $key ) !== $key
		) {
			throw new InvalidArgumentException(
				'A WordPress condition translator key must be a stable lowercase identifier.'
			);
		}

		if ( array_key_exists( $key, $this->translators ) ) {
			throw new LogicException( 'A WordPress condition translator with this key is already registered.' );
		}

		$this->translators[ $key ] = $translator;
	}

	/**
	 * Return one translator by condition key.
	 *
	 * @param string $key Stable condition key.
	 */
	public function translator( string $key ): ?WordPressConditionTranslatorInterface {
		return $this->translators[ $key ] ?? null;
	}

	/**
	 * Return translators in registration order.
	 *
	 * @return list<WordPressConditionTranslatorInterface>
	 */
	public function translators(): array {
		return array_values( $this->translators );
	}
}
