<?php
/**
 * Abstract base for a schema type handler.
 *
 * A schema type declares:
 *  - Its slug ("JobPosting"), used as @type in JSON-LD
 *  - The list of mappable fields and which are required
 *  - Optional field-level transformations
 *  - A build() method that turns a post into the final JSON-LD array
 */

defined( 'ABSPATH' ) || exit;

abstract class Schema_Mapper_Type {

	/** @return string e.g. "JobPosting" */
	abstract public function slug();

	/** @return string Human label for the admin UI */
	abstract public function label();

	/**
	 * Schema field definitions.
	 *
	 * @return array<string, array{label:string, required:bool, description:string, transform?:string}>
	 */
	abstract public function fields();

	/**
	 * Transformations the field can pick from, keyed by slug.
	 *
	 * @return array<string, array{label:string, callable:callable}>
	 */
	public function transforms() {
		return array();
	}

	/**
	 * Build the JSON-LD payload for a post given the resolved field values.
	 *
	 * @param int   $post_id
	 * @param array $mapping       Per-CPT mapping config from settings.
	 * @param array $resolved      field_name => resolved raw value.
	 * @return array|null          JSON-LD array or null to skip output.
	 */
	abstract public function build( $post_id, array $mapping, array $resolved );

	/**
	 * Evaluate a gate condition (used to skip output e.g. when "live_or_archive" != "Live").
	 *
	 * @param array $gate
	 * @param int   $post_id
	 * @return bool true when output is allowed
	 */
	public static function gate_passes( $gate, $post_id ) {
		if ( empty( $gate ) || ! is_array( $gate ) ) {
			return true;
		}
		if ( empty( $gate['field'] ) || ! isset( $gate['value'] ) ) {
			return true;
		}
		$actual = Schema_Mapper_Field_Resolver::resolve( $gate['field'], $post_id );
		$expected = (string) $gate['value'];
		$mode     = $gate['comparison'] ?? 'equals';

		switch ( $mode ) {
			case 'equals':
				return is_scalar( $actual ) && (string) $actual === $expected;
			case 'not_equals':
				return ! is_scalar( $actual ) || (string) $actual !== $expected;
			case 'not_empty':
				return ! empty( $actual );
			case 'empty':
				return empty( $actual );
		}
		return true;
	}
}
