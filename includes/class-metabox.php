<?php
/**
 * Schema preview metabox shown on the edit screen of any post type that has
 * a schema mapping configured. Displays the resolved field values for the
 * post and the final JSON-LD payload that will be emitted on the front end.
 */

defined( 'ABSPATH' ) || exit;

class Schema_Mapper_MetaBox {

	const ID = 'schema-mapper-preview';

	/** @var Schema_Mapper */
	private $plugin;

	public function __construct( Schema_Mapper $plugin ) {
		$this->plugin = $plugin;
		add_action( 'add_meta_boxes', array( $this, 'register' ) );
	}

	public function register() {
		$settings = $this->plugin->get_settings();
		$cpt_mappings = $settings['cpt_mappings'] ?? array();
		foreach ( $cpt_mappings as $post_type => $mapping ) {
			if ( empty( $mapping['enabled'] ) ) {
				continue;
			}
			add_meta_box(
				self::ID . '-' . sanitize_key( $post_type ),
				sprintf(
					/* translators: %s = schema type */
					__( 'Schema Mapper: %s preview', 'schema-mapper' ),
					$mapping['schema_type'] ?? __( 'unmapped', 'schema-mapper' )
				),
				array( $this, 'render' ),
				$post_type,
				'normal',
				'low'
			);
		}
	}

	public function render( $post ) {
		$preview = $this->plugin->preview_for_post( $post->ID );
		$handler = $this->plugin->get_schema_type( $preview['schema_slug'] );

		$status_labels = array(
			'will_emit'        => array( 'label' => __( 'Will emit JSON-LD on the front end', 'schema-mapper' ), 'class' => 'is-ok' ),
			'gate_blocked'     => array( 'label' => __( 'Output blocked by gate condition', 'schema-mapper' ),     'class' => 'is-blocked' ),
			'missing_required' => array( 'label' => __( 'Required fields are empty', 'schema-mapper' ),            'class' => 'is-warn' ),
			'no_handler'       => array( 'label' => __( 'Schema type handler is missing', 'schema-mapper' ),       'class' => 'is-warn' ),
			'no_mapping'       => array( 'label' => __( 'No mapping configured for this post type', 'schema-mapper' ), 'class' => 'is-warn' ),
			'no_schema'        => array( 'label' => __( 'No schema type selected', 'schema-mapper' ),              'class' => 'is-warn' ),
		);
		$status = $status_labels[ $preview['status'] ] ?? array( 'label' => $preview['status'], 'class' => '' );

		$gate = $preview['mapping']['gate'] ?? null;
		$gate_passes = Schema_Mapper_Type::gate_passes( $gate, $post->ID );

		echo '<div class="schema-mapper-preview">';

		// Status pill
		printf(
			'<p class="schema-mapper-status %s"><strong>%s:</strong> %s</p>',
			esc_attr( $status['class'] ),
			esc_html__( 'Status', 'schema-mapper' ),
			esc_html( $status['label'] )
		);

		if ( ! $handler ) {
			echo '<p>' . esc_html__( 'Go to Settings > Schema Mapper to configure this post type.', 'schema-mapper' ) . '</p>';
			echo '</div>';
			return;
		}

		// Gate panel
		if ( ! empty( $gate ) ) {
			$gate_actual = Schema_Mapper_Field_Resolver::resolve( $gate['field'] ?? array(), $post->ID );
			printf(
				'<p class="schema-mapper-gate"><strong>%s:</strong> <code>%s %s %s</code> %s actual: <code>%s</code></p>',
				esc_html__( 'Gate', 'schema-mapper' ),
				esc_html( ( $gate['field']['source'] ?? '' ) . ':' . ( $gate['field']['key'] ?? '' ) ),
				esc_html( $gate['comparison'] ?? 'equals' ),
				esc_html( '"' . ( $gate['value'] ?? '' ) . '"' ),
				$gate_passes ? '<span class="dashicons dashicons-yes" title="passes"></span>' : '<span class="dashicons dashicons-no" title="blocks"></span>',
				esc_html( is_scalar( $gate_actual ) ? (string) $gate_actual : wp_json_encode( $gate_actual ) )
			);
		}

		// Field-by-field resolution table
		echo '<table class="widefat schema-mapper-fields-table" role="presentation"><thead><tr>';
		echo '<th>' . esc_html__( 'Schema field', 'schema-mapper' ) . '</th>';
		echo '<th>' . esc_html__( 'Source', 'schema-mapper' ) . '</th>';
		echo '<th>' . esc_html__( 'Resolved value for this post', 'schema-mapper' ) . '</th>';
		echo '</tr></thead><tbody>';

		$fields = $handler->fields();
		foreach ( $fields as $field_name => $spec ) {
			$user_mapping = $preview['mapping']['fields'][ $field_name ] ?? array();
			$source = $user_mapping['source'] ?? '';
			$key    = $user_mapping['key']    ?? ( $user_mapping['value'] ?? '' );
			$resolved = $preview['resolved'][ $field_name ] ?? null;

			$source_label = '';
			if ( '' === $source ) {
				$source_label = '<span class="schema-mapper-muted">' . esc_html__( 'unmapped', 'schema-mapper' ) . '</span>';
			} else {
				$source_label = sprintf(
					'<span class="schema-mapper-source"><code>%s</code> %s <code>%s</code></span>',
					esc_html( $source ),
					$key !== '' ? '&middot;' : '',
					esc_html( $key )
				);
			}
			if ( ! empty( $user_mapping['transform'] ) ) {
				$source_label .= sprintf( '<br><small class="schema-mapper-muted">%s <code>%s</code></small>', esc_html__( 'via transform', 'schema-mapper' ), esc_html( $user_mapping['transform'] ) );
			}

			$is_required = ! empty( $spec['required'] );
			$empty       = ( $resolved === null || $resolved === '' || $resolved === array() );

			$row_class = '';
			if ( $is_required && $empty ) $row_class = 'is-missing-required';

			echo '<tr class="' . esc_attr( $row_class ) . '">';
			echo '<td><strong>' . esc_html( $spec['label'] ?? $field_name ) . '</strong>';
			if ( $is_required ) echo ' <span class="schema-mapper-required" title="required">✱</span>';
			echo '<br><small class="schema-mapper-muted">' . esc_html( $field_name ) . '</small></td>';
			echo '<td>' . wp_kses_post( $source_label ) . '</td>';
			echo '<td class="schema-mapper-value-cell">' . wp_kses_post( self::format_resolved_value( $resolved ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		// Final JSON-LD payload (or explanation of why none)
		echo '<h4>' . esc_html__( 'JSON-LD payload that will be emitted', 'schema-mapper' ) . '</h4>';
		if ( $preview['payload'] ) {
			echo '<pre class="schema-mapper-payload">' . esc_html( wp_json_encode( $preview['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) . '</pre>';

			$test_url = 'https://search.google.com/test/rich-results?url=' . urlencode( get_permalink( $post->ID ) );
			printf(
				'<p><a class="button" href="%s" target="_blank" rel="noopener">%s</a></p>',
				esc_url( $test_url ),
				esc_html__( 'Open in Google Rich Results Test', 'schema-mapper' )
			);
		} else {
			echo '<p class="schema-mapper-muted">' . esc_html__( 'No JSON-LD will be emitted for this post.', 'schema-mapper' );
			if ( $preview['missing_required'] ) {
				echo ' ' . esc_html__( 'Missing required:', 'schema-mapper' ) . ' <code>' . esc_html( implode( '</code>, <code>', $preview['missing_required'] ) ) . '</code>';
			}
			echo '</p>';
		}
		echo '</div>';
	}

	/**
	 * Format a resolved value for display. Truncates long text, pretty-prints arrays.
	 *
	 * @param mixed $value
	 * @return string HTML
	 */
	private static function format_resolved_value( $value ) {
		if ( null === $value || '' === $value ) {
			return '<span class="schema-mapper-muted">(empty)</span>';
		}
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( is_scalar( $value ) ) {
			$str = (string) $value;
			if ( strlen( $str ) > 280 ) {
				return '<span class="schema-mapper-truncated">' . esc_html( substr( $str, 0, 280 ) ) . '…</span>'
					. '<details><summary>' . esc_html__( 'show full', 'schema-mapper' ) . '</summary><pre>' . esc_html( $str ) . '</pre></details>';
			}
			return esc_html( $str );
		}
		// array / object → JSON
		return '<details open><summary>' . esc_html__( 'structured value', 'schema-mapper' ) . '</summary><pre class="schema-mapper-payload schema-mapper-payload-inline">'
			. esc_html( wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) )
			. '</pre></details>';
	}
}
