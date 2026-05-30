<?php
/**
 * Main plugin singleton. Boots admin and front-end.
 */

defined( 'ABSPATH' ) || exit;

class Schema_Mapper {

	/**
	 * @var Schema_Mapper
	 */
	private static $instance;

	/**
	 * Registered schema type handlers, keyed by slug.
	 *
	 * @var Schema_Mapper_Type[]
	 */
	private $schema_types = array();

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	private function boot() {
		$this->register_schema_types();

		if ( is_admin() ) {
			new Schema_Mapper_Settings( $this );
		}

		new Schema_Mapper_Renderer( $this );
	}

	private function register_schema_types() {
		$this->schema_types['JobPosting'] = new Schema_Mapper_JobPosting();

		/**
		 * Lets third parties register additional schema type handlers.
		 *
		 * @param array<string, Schema_Mapper_Type> $types
		 */
		$this->schema_types = apply_filters( 'schema_mapper/types', $this->schema_types );
	}

	/**
	 * @return Schema_Mapper_Type[]
	 */
	public function get_schema_types() {
		return $this->schema_types;
	}

	public function get_schema_type( $slug ) {
		return $this->schema_types[ $slug ] ?? null;
	}

	/**
	 * Read settings option, normalised.
	 *
	 * @return array
	 */
	public function get_settings() {
		$raw = get_option( SCHEMA_MAPPER_OPTION, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		$raw['cpt_mappings'] = $raw['cpt_mappings'] ?? array();
		return $raw;
	}

	/**
	 * Get the mapping for a specific post type.
	 *
	 * @param string $post_type
	 * @return array|null
	 */
	public function get_cpt_mapping( $post_type ) {
		$settings = $this->get_settings();
		$mapping  = $settings['cpt_mappings'][ $post_type ] ?? null;
		if ( ! $mapping || empty( $mapping['enabled'] ) ) {
			return null;
		}
		return $mapping;
	}
}
