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
			new Schema_Mapper_MetaBox( $this );
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

	/**
	 * Compute everything the front-end renderer would compute for this post,
	 * without actually emitting markup. Used by the admin preview metabox.
	 *
	 * Return shape:
	 *   array{
	 *     status: 'will_emit'|'gate_blocked'|'missing_required'|'no_mapping'|'no_schema'|'no_handler',
	 *     schema_slug: string,
	 *     mapping: array,
	 *     resolved: array<string,mixed>,
	 *     payload: array|null,
	 *     missing_required: string[],
	 *   }
	 *
	 * @param int $post_id
	 */
	public function preview_for_post( $post_id ) {
		$post_type = get_post_type( $post_id );
		$result = array(
			'status'           => 'no_mapping',
			'schema_slug'      => '',
			'mapping'          => array(),
			'resolved'         => array(),
			'payload'          => null,
			'missing_required' => array(),
		);
		if ( ! $post_type ) {
			return $result;
		}
		$mapping = $this->get_cpt_mapping( $post_type );
		if ( ! $mapping ) {
			return $result;
		}
		$result['mapping']     = $mapping;
		$result['schema_slug'] = $mapping['schema_type'] ?? '';

		$handler = $this->get_schema_type( $result['schema_slug'] );
		if ( ! $handler ) {
			$result['status'] = 'no_handler';
			return $result;
		}

		$result['resolved'] = Schema_Mapper_Renderer::resolve_fields( $handler, $mapping, $post_id );

		// Identify required fields that came back empty.
		foreach ( $handler->fields() as $name => $spec ) {
			if ( ! empty( $spec['required'] ) && empty( $result['resolved'][ $name ] ) ) {
				$result['missing_required'][] = $name;
			}
		}

		$gate_passes = Schema_Mapper_Type::gate_passes( $mapping['gate'] ?? null, $post_id );
		if ( ! $gate_passes ) {
			$result['status'] = 'gate_blocked';
			return $result;
		}

		$payload = $handler->build( $post_id, $mapping, $result['resolved'] );
		if ( null === $payload ) {
			$result['status'] = $result['missing_required'] ? 'missing_required' : 'gate_blocked';
			return $result;
		}

		$result['payload'] = $payload;
		$result['status']  = 'will_emit';
		return $result;
	}
}
