<?php
/**
 * Settings UI under Settings > Schema Mapper.
 *
 * One page, one form. Each public post type gets a section:
 *   - Enable toggle
 *   - Schema type dropdown
 *   - Field mapping rows (driven by the selected schema type)
 *   - Optional gate condition
 */

defined( 'ABSPATH' ) || exit;

class Schema_Mapper_Settings {

	const PAGE_SLUG = 'schema-mapper';

	/** @var Schema_Mapper */
	private $plugin;

	public function __construct( Schema_Mapper $plugin ) {
		$this->plugin = $plugin;
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu() {
		add_options_page(
			__( 'Schema Mapper', 'schema-mapper' ),
			__( 'Schema Mapper', 'schema-mapper' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function register_setting() {
		register_setting(
			'schema_mapper_group',
			SCHEMA_MAPPER_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);
	}

	public function enqueue_assets( $hook ) {
		$is_settings_page = false !== strpos( (string) $hook, self::PAGE_SLUG );
		$is_post_edit     = in_array( $hook, array( 'post.php', 'post-new.php' ), true );
		if ( ! $is_settings_page && ! $is_post_edit ) {
			return;
		}
		wp_enqueue_style( 'schema-mapper-admin', SCHEMA_MAPPER_URL . 'assets/admin.css', array(), SCHEMA_MAPPER_VERSION );
		if ( $is_settings_page ) {
			wp_enqueue_script( 'schema-mapper-admin', SCHEMA_MAPPER_URL . 'assets/admin.js', array(), SCHEMA_MAPPER_VERSION, true );
		}
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings  = $this->plugin->get_settings();
		$mappings  = $settings['cpt_mappings'] ?? array();
		$schemas   = $this->plugin->get_schema_types();
		$post_types = $this->get_eligible_post_types();
		?>
		<div class="wrap schema-mapper-wrap">
			<h1><?php esc_html_e( 'Schema Mapper', 'schema-mapper' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Choose a schema type for each post type and map fields. The plugin outputs JSON-LD on single post pages.', 'schema-mapper' ); ?>
			</p>

			<?php if ( ! Schema_Mapper_ACF_Helper::is_available() ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'ACF is not active. You can still map post fields and static values, but ACF field options will be empty.', 'schema-mapper' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'schema_mapper_group' ); ?>

				<?php foreach ( $post_types as $pt => $label ) :
					$cpt_mapping = $mappings[ $pt ] ?? array();
					$enabled     = ! empty( $cpt_mapping['enabled'] );
					$schema_slug = $cpt_mapping['schema_type'] ?? '';
					$field_map   = $cpt_mapping['fields']      ?? array();
					$gate        = $cpt_mapping['gate']        ?? array();
					$option_name = sprintf( '%s[cpt_mappings][%s]', SCHEMA_MAPPER_OPTION, esc_attr( $pt ) );
					?>
					<section class="schema-mapper-cpt" data-cpt="<?php echo esc_attr( $pt ); ?>">
						<h2><?php echo esc_html( $label ); ?> <code><?php echo esc_html( $pt ); ?></code></h2>

						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Enable schema output', 'schema-mapper' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[enabled]" value="1" <?php checked( $enabled ); ?>>
										<?php esc_html_e( 'Output JSON-LD on single pages of this post type', 'schema-mapper' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Schema type', 'schema-mapper' ); ?></th>
								<td>
									<select name="<?php echo esc_attr( $option_name ); ?>[schema_type]" class="schema-mapper-type-select" data-cpt="<?php echo esc_attr( $pt ); ?>">
										<option value="">— <?php esc_html_e( 'Select a schema type', 'schema-mapper' ); ?> —</option>
										<?php foreach ( $schemas as $slug => $handler ) : ?>
											<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $schema_slug, $slug ); ?>>
												<?php echo esc_html( $handler->label() . ' (' . $slug . ')' ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e( 'Save after changing the schema type to reveal its field mappings.', 'schema-mapper' ); ?></p>
								</td>
							</tr>
						</table>

						<?php if ( $schema_slug && isset( $schemas[ $schema_slug ] ) ) :
							$this->render_field_table( $schemas[ $schema_slug ], $option_name, $field_map, $pt );
							$this->render_gate( $option_name, $gate, $pt );
						endif; ?>
					</section>
				<?php endforeach; ?>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private function render_field_table( Schema_Mapper_Type $handler, $option_name, $field_map, $post_type ) {
		$fields     = $handler->fields();
		$transforms = $handler->transforms();
		$acf_fields = Schema_Mapper_ACF_Helper::get_fields_for_post_type( $post_type );
		?>
		<h3><?php printf( esc_html__( '%s field mappings', 'schema-mapper' ), esc_html( $handler->label() ) ); ?></h3>
		<table class="widefat schema-mapper-fields">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Schema field', 'schema-mapper' ); ?></th>
					<th><?php esc_html_e( 'Source', 'schema-mapper' ); ?></th>
					<th><?php esc_html_e( 'Value', 'schema-mapper' ); ?></th>
					<th><?php esc_html_e( 'Transform', 'schema-mapper' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $fields as $field_key => $field_spec ) :
					$current = $field_map[ $field_key ] ?? array();
					$source  = $current['source']    ?? '';
					$key     = $current['key']       ?? '';
					$static  = $current['value']     ?? '';
					$tr      = $current['transform'] ?? ( $field_spec['transform'] ?? '' );
					$field_name = sprintf( '%s[fields][%s]', $option_name, esc_attr( $field_key ) );
					$required   = ! empty( $field_spec['required'] );
					?>
					<tr class="schema-mapper-field-row">
						<td>
							<strong><?php echo esc_html( $field_spec['label'] ); ?></strong>
							<?php if ( $required ) : ?> <span class="schema-mapper-required" title="<?php esc_attr_e( 'Required', 'schema-mapper' ); ?>">✱</span><?php endif; ?>
							<p class="description"><?php echo esc_html( $field_spec['description'] ); ?></p>
						</td>
						<td>
							<select name="<?php echo esc_attr( $field_name ); ?>[source]" class="schema-mapper-source-select">
								<option value=""        <?php selected( $source, '' ); ?>>— <?php esc_html_e( 'Unmapped', 'schema-mapper' ); ?> —</option>
								<option value="acf"     <?php selected( $source, 'acf' ); ?>><?php esc_html_e( 'ACF field', 'schema-mapper' ); ?></option>
								<option value="post"    <?php selected( $source, 'post' ); ?>><?php esc_html_e( 'Post field', 'schema-mapper' ); ?></option>
								<option value="static"  <?php selected( $source, 'static' ); ?>><?php esc_html_e( 'Static value', 'schema-mapper' ); ?></option>
								<option value="permalink" <?php selected( $source, 'permalink' ); ?>><?php esc_html_e( 'Permalink', 'schema-mapper' ); ?></option>
							</select>
						</td>
						<td>
							<select name="<?php echo esc_attr( $field_name ); ?>[key]" class="schema-mapper-acf-key">
								<option value="">—</option>
								<optgroup label="<?php esc_attr_e( 'Post fields', 'schema-mapper' ); ?>">
									<?php foreach ( Schema_Mapper_Field_Resolver::post_fields() as $pkey => $plabel ) : ?>
										<option value="<?php echo esc_attr( $pkey ); ?>" <?php selected( $key, $pkey ); ?> data-source="post"><?php echo esc_html( $plabel ); ?></option>
									<?php endforeach; ?>
								</optgroup>
								<?php if ( $acf_fields ) : ?>
									<optgroup label="<?php esc_attr_e( 'ACF fields', 'schema-mapper' ); ?>">
										<?php foreach ( $acf_fields as $akey => $alabel ) : ?>
											<option value="<?php echo esc_attr( $akey ); ?>" <?php selected( $key, $akey ); ?> data-source="acf"><?php echo esc_html( $alabel ); ?></option>
										<?php endforeach; ?>
									</optgroup>
								<?php endif; ?>
							</select>
							<input type="text" class="regular-text schema-mapper-static-value" name="<?php echo esc_attr( $field_name ); ?>[value]" value="<?php echo esc_attr( $static ); ?>" placeholder="<?php esc_attr_e( 'Static value', 'schema-mapper' ); ?>" />
						</td>
						<td>
							<?php if ( $transforms ) : ?>
								<select name="<?php echo esc_attr( $field_name ); ?>[transform]">
									<option value="">— <?php esc_html_e( 'None', 'schema-mapper' ); ?> —</option>
									<?php foreach ( $transforms as $tkey => $tspec ) : ?>
										<option value="<?php echo esc_attr( $tkey ); ?>" <?php selected( $tr, $tkey ); ?>><?php echo esc_html( $tspec['label'] ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_gate( $option_name, $gate, $post_type ) {
		$acf_fields  = Schema_Mapper_ACF_Helper::get_fields_for_post_type( $post_type );
		$gate_field  = $gate['field'] ?? array();
		$gate_source = $gate_field['source'] ?? '';
		$gate_key    = $gate_field['key']    ?? '';
		$gate_value  = $gate['value']        ?? '';
		$gate_mode   = $gate['comparison']   ?? 'equals';
		?>
		<h3><?php esc_html_e( 'Output condition (optional)', 'schema-mapper' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Only output schema when a field matches a condition. Leave the field blank to always output.', 'schema-mapper' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Condition field', 'schema-mapper' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( $option_name ); ?>[gate][field][source]">
						<option value=""    <?php selected( $gate_source, '' ); ?>>— <?php esc_html_e( 'Always output', 'schema-mapper' ); ?> —</option>
						<option value="acf"  <?php selected( $gate_source, 'acf' ); ?>><?php esc_html_e( 'ACF field', 'schema-mapper' ); ?></option>
						<option value="post" <?php selected( $gate_source, 'post' ); ?>><?php esc_html_e( 'Post field', 'schema-mapper' ); ?></option>
					</select>
					<select name="<?php echo esc_attr( $option_name ); ?>[gate][field][key]">
						<option value="">—</option>
						<optgroup label="<?php esc_attr_e( 'Post fields', 'schema-mapper' ); ?>">
							<?php foreach ( Schema_Mapper_Field_Resolver::post_fields() as $pkey => $plabel ) : ?>
								<option value="<?php echo esc_attr( $pkey ); ?>" <?php selected( $gate_key, $pkey ); ?>><?php echo esc_html( $plabel ); ?></option>
							<?php endforeach; ?>
						</optgroup>
						<?php if ( $acf_fields ) : ?>
							<optgroup label="<?php esc_attr_e( 'ACF fields', 'schema-mapper' ); ?>">
								<?php foreach ( $acf_fields as $akey => $alabel ) : ?>
									<option value="<?php echo esc_attr( $akey ); ?>" <?php selected( $gate_key, $akey ); ?>><?php echo esc_html( $alabel ); ?></option>
								<?php endforeach; ?>
							</optgroup>
						<?php endif; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Comparison', 'schema-mapper' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( $option_name ); ?>[gate][comparison]">
						<option value="equals"     <?php selected( $gate_mode, 'equals' ); ?>><?php esc_html_e( 'Equals', 'schema-mapper' ); ?></option>
						<option value="not_equals" <?php selected( $gate_mode, 'not_equals' ); ?>><?php esc_html_e( 'Does not equal', 'schema-mapper' ); ?></option>
						<option value="not_empty"  <?php selected( $gate_mode, 'not_empty' ); ?>><?php esc_html_e( 'Is not empty', 'schema-mapper' ); ?></option>
						<option value="empty"      <?php selected( $gate_mode, 'empty' ); ?>><?php esc_html_e( 'Is empty', 'schema-mapper' ); ?></option>
					</select>
					<input type="text" name="<?php echo esc_attr( $option_name ); ?>[gate][value]" value="<?php echo esc_attr( $gate_value ); ?>" placeholder="<?php esc_attr_e( 'Compared value', 'schema-mapper' ); ?>" />
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * @return array<string,string>
	 */
	private function get_eligible_post_types() {
		$pts = get_post_types( array( 'public' => true ), 'objects' );
		unset( $pts['attachment'] );
		$out = array();
		foreach ( $pts as $pt ) {
			$out[ $pt->name ] = $pt->labels->name ?: $pt->name;
		}
		ksort( $out );
		return $out;
	}

	public function sanitize_settings( $input ) {
		$clean = array();
		$mappings_in = is_array( $input ) && isset( $input['cpt_mappings'] ) && is_array( $input['cpt_mappings'] ) ? $input['cpt_mappings'] : array();

		foreach ( $mappings_in as $pt => $mapping ) {
			$pt = sanitize_key( $pt );
			if ( ! $pt ) continue;

			$clean_cpt = array(
				'enabled'     => ! empty( $mapping['enabled'] ),
				'schema_type' => isset( $mapping['schema_type'] ) ? sanitize_text_field( $mapping['schema_type'] ) : '',
				'fields'      => array(),
			);

			if ( isset( $mapping['fields'] ) && is_array( $mapping['fields'] ) ) {
				foreach ( $mapping['fields'] as $field_key => $field ) {
					$field_key = sanitize_key( $field_key );
					if ( ! $field_key ) continue;
					$source = isset( $field['source'] ) ? sanitize_key( $field['source'] ) : '';
					$row = array( 'source' => $source );
					if ( in_array( $source, array( 'acf', 'post' ), true ) ) {
						$row['key'] = isset( $field['key'] ) ? sanitize_text_field( $field['key'] ) : '';
					} elseif ( 'static' === $source ) {
						$row['value'] = isset( $field['value'] ) ? wp_kses_post( $field['value'] ) : '';
					}
					if ( ! empty( $field['transform'] ) ) {
						$row['transform'] = sanitize_key( $field['transform'] );
					}
					$clean_cpt['fields'][ $field_key ] = $row;
				}
			}

			if ( isset( $mapping['gate'] ) && is_array( $mapping['gate'] ) ) {
				$gate_field = isset( $mapping['gate']['field'] ) && is_array( $mapping['gate']['field'] ) ? $mapping['gate']['field'] : array();
				$src = isset( $gate_field['source'] ) ? sanitize_key( $gate_field['source'] ) : '';
				$key = isset( $gate_field['key'] )    ? sanitize_text_field( $gate_field['key'] ) : '';
				if ( $src ) {
					$clean_cpt['gate'] = array(
						'field' => array( 'source' => $src, 'key' => $key ),
						'comparison' => isset( $mapping['gate']['comparison'] ) ? sanitize_key( $mapping['gate']['comparison'] ) : 'equals',
						'value' => isset( $mapping['gate']['value'] ) ? sanitize_text_field( $mapping['gate']['value'] ) : '',
					);
				}
			}

			$clean['cpt_mappings'][ $pt ] = $clean_cpt;
		}

		return $clean;
	}
}
