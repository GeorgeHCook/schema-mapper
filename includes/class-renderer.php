<?php
/**
 * Outputs JSON-LD for the active CPT mappings on wp_head.
 */

defined( 'ABSPATH' ) || exit;

class Schema_Mapper_Renderer {

	/** @var Schema_Mapper */
	private $plugin;

	public function __construct( Schema_Mapper $plugin ) {
		$this->plugin = $plugin;
		add_action( 'wp_head', array( $this, 'render' ), 25 );
	}

	public function render() {
		if ( ! is_singular() ) {
			return;
		}
		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}
		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			return;
		}
		$mapping = $this->plugin->get_cpt_mapping( $post_type );
		if ( ! $mapping ) {
			return;
		}
		$schema_slug = $mapping['schema_type'] ?? '';
		$handler     = $this->plugin->get_schema_type( $schema_slug );
		if ( ! $handler ) {
			return;
		}

		$resolved = $this->resolve_fields( $handler, $mapping, $post_id );
		$payload  = $handler->build( $post_id, $mapping, $resolved );
		if ( ! is_array( $payload ) || empty( $payload ) ) {
			return;
		}

		/**
		 * Filter the final JSON-LD payload before output. Receives the array, the
		 * post id, the schema slug. Return null to skip output.
		 */
		$payload = apply_filters( 'schema_mapper/output', $payload, $post_id, $schema_slug );
		if ( ! is_array( $payload ) ) {
			return;
		}

		echo "\n<script type=\"application/ld+json\" data-source=\"schema-mapper\">\n";
		echo wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		echo "\n</script>\n";
	}

	/**
	 * Resolve every declared field for the schema, applying the configured transform.
	 *
	 * @return array<string,mixed>
	 */
	private function resolve_fields( Schema_Mapper_Type $handler, array $mapping, $post_id ) {
		$fields     = $handler->fields();
		$transforms = $handler->transforms();
		$mappings   = $mapping['fields'] ?? array();
		$resolved   = array();

		foreach ( $fields as $field_name => $field_spec ) {
			$user_mapping = $mappings[ $field_name ] ?? array();
			$value        = Schema_Mapper_Field_Resolver::resolve( $user_mapping, $post_id );

			// Auto-default datePosted from post_date when unmapped.
			if ( null === $value && 'datePosted' === $field_name ) {
				$value = Schema_Mapper_Field_Resolver::resolve(
					array( 'source' => Schema_Mapper_Field_Resolver::SOURCE_POST, 'key' => 'post_date' ),
					$post_id
				);
			}
			// Auto-default title from post_title when unmapped.
			if ( null === $value && 'title' === $field_name ) {
				$value = get_the_title( $post_id ) ?: null;
			}
			// Auto-default description from post_content when unmapped.
			if ( null === $value && 'description' === $field_name ) {
				$value = Schema_Mapper_Field_Resolver::resolve(
					array( 'source' => Schema_Mapper_Field_Resolver::SOURCE_POST, 'key' => 'post_content' ),
					$post_id
				);
			}

			// Apply transform if the field declares one (and it exists).
			$transform_slug = $user_mapping['transform'] ?? ( $field_spec['transform'] ?? '' );
			if ( $value !== null && $transform_slug && isset( $transforms[ $transform_slug ] ) ) {
				$cb = $transforms[ $transform_slug ]['callable'];
				if ( is_callable( $cb ) ) {
					$value = call_user_func( $cb, $value );
				}
			}

			$resolved[ $field_name ] = $value;
		}

		return $resolved;
	}
}
