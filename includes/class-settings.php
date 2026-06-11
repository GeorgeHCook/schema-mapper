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
		$org       = $settings['organization'] ?? array();
		$schemas   = $this->plugin->get_schema_types();
		$post_types = $this->get_eligible_post_types();

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'post-types';
		if ( ! in_array( $active_tab, array( 'post-types', 'organization' ), true ) ) {
			$active_tab = 'post-types';
		}
		$base_url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		?>
		<div class="wrap schema-mapper-wrap">
			<h1><?php esc_html_e( 'Schema Mapper', 'schema-mapper' ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'post-types', $base_url ) ); ?>" class="nav-tab <?php echo $active_tab === 'post-types' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Post Type Mappings', 'schema-mapper' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'organization', $base_url ) ); ?>" class="nav-tab <?php echo $active_tab === 'organization' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Employment Agency / Organization', 'schema-mapper' ); ?>
				</a>
			</h2>

			<?php if ( ! Schema_Mapper_ACF_Helper::is_available() ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'ACF is not active. You can still map post fields and static values, but ACF field options will be empty.', 'schema-mapper' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'schema_mapper_group' ); ?>

				<?php if ( $active_tab === 'post-types' ) : ?>
					<p class="description">
						<?php esc_html_e( 'Choose a schema type for each post type and map fields. The plugin outputs JSON-LD on single post pages.', 'schema-mapper' ); ?>
					</p>

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
				<?php else : ?>
					<?php $this->render_organization_tab( $org ); ?>
				<?php endif; ?>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the Employment Agency / Organization settings tab. The fields
	 * here are the LocalBusiness + EmploymentAgency superset; every value
	 * filled in is merged onto Yoast's Organization graph node site-wide.
	 *
	 * @param array $org Saved organization settings.
	 */
	private function render_organization_tab( $org ) {
		$option = SCHEMA_MAPPER_OPTION . '[organization]';

		// Preview: render the JSON-LD that will actually emit on the front end,
		// based on the last saved settings. Empty fields don't appear.
		$emitter = $this->plugin->organization_emitter();
		$preview = $emitter ? $emitter->preview() : null;
		?>
		<div class="schema-mapper-org-preview">
			<details<?php echo $preview ? ' open' : ''; ?>>
				<summary>
					<strong><?php esc_html_e( 'Preview: this is what gets emitted to JSON-LD on every page', 'schema-mapper' ); ?></strong>
					<span class="description"><?php esc_html_e( 'Reflects last saved values. Empty fields are not output.', 'schema-mapper' ); ?></span>
				</summary>
				<?php if ( $preview ) : ?>
					<pre class="schema-mapper-preview-code"><?php
						echo esc_html( wp_json_encode( $preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
					?></pre>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'No organization settings saved yet. Fill the fields below, save, and the preview will appear here.', 'schema-mapper' ); ?></p>
				<?php endif; ?>
			</details>
		</div>
		<?php

		$enabled       = ! empty( $org['enabled'] );
		$types         = isset( $org['types'] ) && is_array( $org['types'] ) ? $org['types'] : array( 'EmploymentAgency' );

		$legal_name    = $org['legal_name']   ?? '';
		$slogan        = $org['slogan']       ?? '';
		$description   = $org['description']  ?? '';
		$founded       = $org['founded_year'] ?? '';
		$employees     = $org['employees']    ?? '';

		$telephone     = $org['telephone']    ?? '';
		$email         = $org['email']        ?? '';

		$addr          = isset( $org['address'] ) && is_array( $org['address'] ) ? $org['address'] : array();
		$street        = $addr['street']     ?? '';
		$locality      = $addr['locality']   ?? '';
		$region        = $addr['region']     ?? '';
		$postcode      = $addr['postcode']   ?? '';
		$country       = $addr['country']    ?? 'GB';

		$geo           = isset( $org['geo'] ) && is_array( $org['geo'] ) ? $org['geo'] : array();
		$lat           = $geo['latitude']    ?? '';
		$lng           = $geo['longitude']   ?? '';

		$price_range   = $org['price_range']  ?? '';
		$area_served   = is_array( $org['area_served'] ?? null ) ? implode( "\n", $org['area_served'] ) : ( $org['area_served'] ?? '' );
		$knows_about   = is_array( $org['knows_about'] ?? null ) ? implode( "\n", $org['knows_about'] ) : ( $org['knows_about'] ?? '' );

		$hours         = isset( $org['hours'] ) && is_array( $org['hours'] ) ? $org['hours'] : array();

		$ar            = isset( $org['aggregate_rating'] ) && is_array( $org['aggregate_rating'] ) ? $org['aggregate_rating'] : array();
		$ar_value      = $ar['rating_value'] ?? '';
		$ar_count      = $ar['review_count'] ?? '';
		$ar_source     = $ar['source_url']   ?? '';

		$days = array(
			'Monday'    => __( 'Monday',    'schema-mapper' ),
			'Tuesday'   => __( 'Tuesday',   'schema-mapper' ),
			'Wednesday' => __( 'Wednesday', 'schema-mapper' ),
			'Thursday'  => __( 'Thursday',  'schema-mapper' ),
			'Friday'    => __( 'Friday',    'schema-mapper' ),
			'Saturday'  => __( 'Saturday',  'schema-mapper' ),
			'Sunday'    => __( 'Sunday',    'schema-mapper' ),
		);

		$type_choices = array(
			'Organization'     => __( 'Organization (base type, always emitted by Yoast)', 'schema-mapper' ),
			'LocalBusiness'    => __( 'LocalBusiness',    'schema-mapper' ),
			'EmploymentAgency' => __( 'EmploymentAgency', 'schema-mapper' ),
		);
		?>
		<p class="description">
			<?php esc_html_e( 'These fields augment the site-wide Organization node that Yoast outputs on every page. Filling them in adds LocalBusiness and EmploymentAgency signals for search and AI-citation purposes. Leave anything blank to skip it.', 'schema-mapper' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable', 'schema-mapper' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[enabled]" value="1" <?php checked( $enabled ); ?>>
						<?php esc_html_e( 'Merge these settings into the site-wide Organization schema', 'schema-mapper' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Additional schema types', 'schema-mapper' ); ?></th>
				<td>
					<?php foreach ( $type_choices as $type => $label ) :
						$disabled = $type === 'Organization';
						$checked  = $disabled || in_array( $type, $types, true );
					?>
						<label style="display:block;margin-bottom:4px;">
							<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[types][]" value="<?php echo esc_attr( $type ); ?>" <?php checked( $checked ); disabled( $disabled ); ?>>
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
					<?php // Disabled checkboxes don't submit; include the Organization base type as a hidden field. ?>
					<input type="hidden" name="<?php echo esc_attr( $option ); ?>[types][]" value="Organization">
					<p class="description"><?php esc_html_e( 'EmploymentAgency inherits LocalBusiness which inherits Organization. Most recruitment sites tick EmploymentAgency only.', 'schema-mapper' ); ?></p>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Identity', 'schema-mapper' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="sm-org-legal-name"><?php esc_html_e( 'Legal name', 'schema-mapper' ); ?></label></th>
				<td><input id="sm-org-legal-name" type="text" class="regular-text" name="<?php echo esc_attr( $option ); ?>[legal_name]" value="<?php echo esc_attr( $legal_name ); ?>" placeholder="<?php esc_attr_e( 'e.g. Joyce Guiness Ltd', 'schema-mapper' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="sm-org-slogan"><?php esc_html_e( 'Slogan / tagline', 'schema-mapper' ); ?></label></th>
				<td><input id="sm-org-slogan" type="text" class="regular-text" name="<?php echo esc_attr( $option ); ?>[slogan]" value="<?php echo esc_attr( $slogan ); ?>" placeholder="<?php esc_attr_e( 'e.g. Boutique PA & EA Recruitment in London Since 1969', 'schema-mapper' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="sm-org-description"><?php esc_html_e( 'Short description', 'schema-mapper' ); ?></label></th>
				<td><textarea id="sm-org-description" rows="3" class="large-text" name="<?php echo esc_attr( $option ); ?>[description]" placeholder="<?php esc_attr_e( '250 characters or less. Used by AI search and entity panels.', 'schema-mapper' ); ?>"><?php echo esc_textarea( $description ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="sm-org-founded"><?php esc_html_e( 'Founded year', 'schema-mapper' ); ?></label></th>
				<td><input id="sm-org-founded" type="number" min="1800" max="2100" step="1" class="small-text" name="<?php echo esc_attr( $option ); ?>[founded_year]" value="<?php echo esc_attr( $founded ); ?>" placeholder="1969"></td>
			</tr>
			<tr>
				<th><label for="sm-org-employees"><?php esc_html_e( 'Number of employees', 'schema-mapper' ); ?></label></th>
				<td>
					<input id="sm-org-employees" type="text" class="regular-text" name="<?php echo esc_attr( $option ); ?>[employees]" value="<?php echo esc_attr( $employees ); ?>" placeholder="e.g. 1-10">
					<p class="description"><?php esc_html_e( 'Range or exact value. Examples: "1-10", "50-100", "12".', 'schema-mapper' ); ?></p>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Contact', 'schema-mapper' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="sm-org-tel"><?php esc_html_e( 'Telephone', 'schema-mapper' ); ?></label></th>
				<td><input id="sm-org-tel" type="text" class="regular-text" name="<?php echo esc_attr( $option ); ?>[telephone]" value="<?php echo esc_attr( $telephone ); ?>" placeholder="+44 20 7589 8807"></td>
			</tr>
			<tr>
				<th><label for="sm-org-email"><?php esc_html_e( 'Email', 'schema-mapper' ); ?></label></th>
				<td><input id="sm-org-email" type="email" class="regular-text" name="<?php echo esc_attr( $option ); ?>[email]" value="<?php echo esc_attr( $email ); ?>" placeholder="info@joyceguiness.co.uk"></td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Address', 'schema-mapper' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="sm-org-street"><?php esc_html_e( 'Street', 'schema-mapper' ); ?></label></th>
				<td><input id="sm-org-street" type="text" class="regular-text" name="<?php echo esc_attr( $option ); ?>[address][street]" value="<?php echo esc_attr( $street ); ?>" placeholder="7 Walton Street"></td>
			</tr>
			<tr>
				<th><label for="sm-org-locality"><?php esc_html_e( 'Locality', 'schema-mapper' ); ?></label></th>
				<td><input id="sm-org-locality" type="text" class="regular-text" name="<?php echo esc_attr( $option ); ?>[address][locality]" value="<?php echo esc_attr( $locality ); ?>" placeholder="Knightsbridge"></td>
			</tr>
			<tr>
				<th><label for="sm-org-region"><?php esc_html_e( 'Region / county', 'schema-mapper' ); ?></label></th>
				<td><input id="sm-org-region" type="text" class="regular-text" name="<?php echo esc_attr( $option ); ?>[address][region]" value="<?php echo esc_attr( $region ); ?>" placeholder="London"></td>
			</tr>
			<tr>
				<th><label for="sm-org-postcode"><?php esc_html_e( 'Postcode', 'schema-mapper' ); ?></label></th>
				<td><input id="sm-org-postcode" type="text" class="regular-text" name="<?php echo esc_attr( $option ); ?>[address][postcode]" value="<?php echo esc_attr( $postcode ); ?>" placeholder="SW3 2HX"></td>
			</tr>
			<tr>
				<th><label for="sm-org-country"><?php esc_html_e( 'Country (ISO 3166-1 alpha-2)', 'schema-mapper' ); ?></label></th>
				<td><input id="sm-org-country" type="text" maxlength="2" class="small-text" name="<?php echo esc_attr( $option ); ?>[address][country]" value="<?php echo esc_attr( $country ); ?>" placeholder="GB"></td>
			</tr>
			<tr>
				<th><label for="sm-org-lat"><?php esc_html_e( 'Latitude', 'schema-mapper' ); ?></label></th>
				<td>
					<input id="sm-org-lat" type="text" class="regular-text" name="<?php echo esc_attr( $option ); ?>[geo][latitude]"  value="<?php echo esc_attr( $lat ); ?>" placeholder="51.494">
				</td>
			</tr>
			<tr>
				<th><label for="sm-org-lng"><?php esc_html_e( 'Longitude', 'schema-mapper' ); ?></label></th>
				<td>
					<input id="sm-org-lng" type="text" class="regular-text" name="<?php echo esc_attr( $option ); ?>[geo][longitude]" value="<?php echo esc_attr( $lng ); ?>" placeholder="-0.164">
					<p class="description"><?php esc_html_e( 'Pair lat+long to enable map pin behaviour. Use Google Maps to find decimal coordinates.', 'schema-mapper' ); ?></p>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Operating profile', 'schema-mapper' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="sm-org-price-range"><?php esc_html_e( 'Price range', 'schema-mapper' ); ?></label></th>
				<td>
					<select id="sm-org-price-range" name="<?php echo esc_attr( $option ); ?>[price_range]">
						<?php foreach ( array( '' => __( '— none —', 'schema-mapper' ), '£' => '£', '££' => '££', '£££' => '£££', '££££' => '££££' ) as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $price_range, $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="sm-org-area-served"><?php esc_html_e( 'Areas served', 'schema-mapper' ); ?></label></th>
				<td>
					<textarea id="sm-org-area-served" rows="3" class="large-text" name="<?php echo esc_attr( $option ); ?>[area_served]" placeholder="London&#10;South East England&#10;United Kingdom"><?php echo esc_textarea( $area_served ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One area per line.', 'schema-mapper' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="sm-org-knows-about"><?php esc_html_e( 'Knows about / specialties', 'schema-mapper' ); ?></label></th>
				<td>
					<textarea id="sm-org-knows-about" rows="5" class="large-text" name="<?php echo esc_attr( $option ); ?>[knows_about]" placeholder="Personal Assistant recruitment&#10;Executive Assistant recruitment"><?php echo esc_textarea( $knows_about ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One specialty per line. These map to schema.org "knowsAbout" and reinforce topical authority.', 'schema-mapper' ); ?></p>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Opening hours', 'schema-mapper' ); ?></h3>
		<table class="form-table" role="presentation">
			<?php foreach ( $days as $day => $label ) :
				$row    = isset( $hours[ $day ] ) && is_array( $hours[ $day ] ) ? $hours[ $day ] : array();
				$closed = ! empty( $row['closed'] );
				$opens  = $row['opens']  ?? '';
				$closes = $row['closes'] ?? '';
				?>
				<tr>
					<th><?php echo esc_html( $label ); ?></th>
					<td>
						<label style="margin-right:16px;">
							<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[hours][<?php echo esc_attr( $day ); ?>][closed]" value="1" <?php checked( $closed ); ?>>
							<?php esc_html_e( 'Closed', 'schema-mapper' ); ?>
						</label>
						<label><?php esc_html_e( 'Opens', 'schema-mapper' ); ?>
							<input type="time" name="<?php echo esc_attr( $option ); ?>[hours][<?php echo esc_attr( $day ); ?>][opens]"  value="<?php echo esc_attr( $opens ); ?>">
						</label>
						<label style="margin-left:8px;"><?php esc_html_e( 'Closes', 'schema-mapper' ); ?>
							<input type="time" name="<?php echo esc_attr( $option ); ?>[hours][<?php echo esc_attr( $day ); ?>][closes]" value="<?php echo esc_attr( $closes ); ?>">
						</label>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>

		<h3><?php esc_html_e( 'Aggregate rating (optional)', 'schema-mapper' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th colspan="2">
					<p class="description" style="font-weight:normal;">
						<?php esc_html_e( 'Only enable if real review data is available. Google penalises sites that emit fake or unsourced rating schema. Leave blank if unsure.', 'schema-mapper' ); ?>
					</p>
				</th>
			</tr>
			<tr>
				<th><label for="sm-org-rating-value"><?php esc_html_e( 'Rating value (1–5)', 'schema-mapper' ); ?></label></th>
				<td><input id="sm-org-rating-value" type="text" class="small-text" name="<?php echo esc_attr( $option ); ?>[aggregate_rating][rating_value]" value="<?php echo esc_attr( $ar_value ); ?>" placeholder="4.8"></td>
			</tr>
			<tr>
				<th><label for="sm-org-rating-count"><?php esc_html_e( 'Review count', 'schema-mapper' ); ?></label></th>
				<td><input id="sm-org-rating-count" type="number" min="0" step="1" class="small-text" name="<?php echo esc_attr( $option ); ?>[aggregate_rating][review_count]" value="<?php echo esc_attr( $ar_count ); ?>" placeholder="42"></td>
			</tr>
			<tr>
				<th><label for="sm-org-rating-source"><?php esc_html_e( 'Source URL', 'schema-mapper' ); ?></label></th>
				<td>
					<input id="sm-org-rating-source" type="url" class="regular-text" name="<?php echo esc_attr( $option ); ?>[aggregate_rating][source_url]" value="<?php echo esc_attr( $ar_source ); ?>" placeholder="https://www.reviews.io/...">
					<p class="description"><?php esc_html_e( 'Required: link to where the reviews live (Reviews.io, Google Business Profile, Trustpilot, etc).', 'schema-mapper' ); ?></p>
				</td>
			</tr>
		</table>
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
		$field_meta  = Schema_Mapper_ACF_Helper::get_field_meta_for_post_type( $post_type );
		$gate_field  = $gate['field'] ?? array();
		$gate_source = $gate_field['source'] ?? '';
		$gate_key    = $gate_field['key']    ?? '';
		$gate_value  = $gate['value']        ?? '';
		$gate_mode   = $gate['comparison']   ?? 'equals';

		// Choices for the currently-selected ACF field (used to render the
		// value control as a dropdown rather than a free-text input).
		$current_choices = array();
		if ( $gate_source === 'acf' && $gate_key && isset( $field_meta[ $gate_key ]['choices'] ) ) {
			$current_choices = $field_meta[ $gate_key ]['choices'];
		}
		?>
		<h3><?php esc_html_e( 'Output condition (optional)', 'schema-mapper' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Only output schema when a field matches a condition. Leave the field blank to always output.', 'schema-mapper' ); ?>
		</p>
		<table class="form-table" role="presentation" data-sm-gate="<?php echo esc_attr( $post_type ); ?>">
			<tr>
				<th scope="row"><?php esc_html_e( 'Condition field', 'schema-mapper' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( $option_name ); ?>[gate][field][source]" data-sm-gate-source>
						<option value=""    <?php selected( $gate_source, '' ); ?>>— <?php esc_html_e( 'Always output', 'schema-mapper' ); ?> —</option>
						<option value="acf"  <?php selected( $gate_source, 'acf' ); ?>><?php esc_html_e( 'ACF field', 'schema-mapper' ); ?></option>
						<option value="post" <?php selected( $gate_source, 'post' ); ?>><?php esc_html_e( 'Post field', 'schema-mapper' ); ?></option>
					</select>
					<select name="<?php echo esc_attr( $option_name ); ?>[gate][field][key]" data-sm-gate-key>
						<option value="">—</option>
						<optgroup label="<?php esc_attr_e( 'Post fields', 'schema-mapper' ); ?>">
							<?php foreach ( Schema_Mapper_Field_Resolver::post_fields() as $pkey => $plabel ) : ?>
								<option value="<?php echo esc_attr( $pkey ); ?>" <?php selected( $gate_key, $pkey ); ?> data-source="post"><?php echo esc_html( $plabel ); ?></option>
							<?php endforeach; ?>
						</optgroup>
						<?php if ( $acf_fields ) : ?>
							<optgroup label="<?php esc_attr_e( 'ACF fields', 'schema-mapper' ); ?>">
								<?php foreach ( $acf_fields as $akey => $alabel ) : ?>
									<option value="<?php echo esc_attr( $akey ); ?>" <?php selected( $gate_key, $akey ); ?> data-source="acf"><?php echo esc_html( $alabel ); ?></option>
								<?php endforeach; ?>
							</optgroup>
						<?php endif; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Comparison', 'schema-mapper' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( $option_name ); ?>[gate][comparison]" data-sm-gate-mode>
						<option value="equals"     <?php selected( $gate_mode, 'equals' ); ?>><?php esc_html_e( 'Equals', 'schema-mapper' ); ?></option>
						<option value="not_equals" <?php selected( $gate_mode, 'not_equals' ); ?>><?php esc_html_e( 'Does not equal', 'schema-mapper' ); ?></option>
						<option value="not_empty"  <?php selected( $gate_mode, 'not_empty' ); ?>><?php esc_html_e( 'Is not empty', 'schema-mapper' ); ?></option>
						<option value="empty"      <?php selected( $gate_mode, 'empty' ); ?>><?php esc_html_e( 'Is empty', 'schema-mapper' ); ?></option>
					</select>

					<?php /*
					 * Value control: a <select> when the chosen ACF field has discrete
					 * choices, otherwise a free-text <input>. A hidden mirror keeps the
					 * stored value in sync no matter which control is visible, so swapping
					 * between field types via JS never drops the saved value.
					 */ ?>
					<?php
					// Only one of the two controls below carries the submit name at any time;
					// the JS swaps which is active as the field selection changes.
					$value_name      = $option_name . '[gate][value]';
					$select_name     = $current_choices ? $value_name : '';
					$input_name      = $current_choices ? ''           : $value_name;
					$select_hidden   = $current_choices ? '' : 'display:none;';
					$input_hidden    = $current_choices ? 'display:none;' : '';
					?>
					<span class="sm-gate-value" data-sm-gate-value data-sm-value-name="<?php echo esc_attr( $value_name ); ?>">
						<select name="<?php echo esc_attr( $select_name ); ?>"
							data-sm-gate-value-select
							style="<?php echo esc_attr( $select_hidden ); ?>">
							<option value="">— <?php esc_html_e( 'Choose a value', 'schema-mapper' ); ?> —</option>
							<?php foreach ( $current_choices as $cv => $cl ) : ?>
								<option value="<?php echo esc_attr( $cv ); ?>" <?php selected( $gate_value, $cv ); ?>><?php echo esc_html( $cl ); ?> (<?php echo esc_html( $cv ); ?>)</option>
							<?php endforeach; ?>
						</select>
						<input type="text"
							name="<?php echo esc_attr( $input_name ); ?>"
							value="<?php echo esc_attr( $gate_value ); ?>"
							placeholder="<?php esc_attr_e( 'Compared value', 'schema-mapper' ); ?>"
							data-sm-gate-value-input
							style="<?php echo esc_attr( $input_hidden ); ?>" />
					</span>
				</td>
			</tr>
		</table>

		<?php // Per-field choice map for the JS so it can swap the value control on field change. ?>
		<script type="application/json" data-sm-gate-choices="<?php echo esc_attr( $post_type ); ?>">
			<?php echo wp_json_encode( $field_meta ); ?>
		</script>
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

		// Settings UI uses tabs but a single options form, so each save POSTs
		// only the visible tab's fields. Carry over whatever existed for the
		// other tab so saving one doesn't wipe the other.
		$existing       = $this->plugin->get_settings();
		$mappings_in    = is_array( $input ) && isset( $input['cpt_mappings'] ) && is_array( $input['cpt_mappings'] ) ? $input['cpt_mappings'] : null;
		$org_in         = is_array( $input ) && isset( $input['organization'] ) && is_array( $input['organization'] ) ? $input['organization'] : null;

		if ( $mappings_in === null ) {
			// Org tab was saved; preserve existing post-type mappings verbatim.
			$clean['cpt_mappings'] = is_array( $existing['cpt_mappings'] ?? null ) ? $existing['cpt_mappings'] : array();
			$mappings_in = array();
		}

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

		// Organization tab.
		if ( $org_in === null ) {
			// Post-types tab was saved; preserve existing org settings verbatim.
			$clean['organization'] = is_array( $existing['organization'] ?? null ) ? $existing['organization'] : array();
		} else {
			$clean['organization'] = $this->sanitize_organization( $org_in );
		}

		return $clean;
	}

	/**
	 * Sanitize the organization payload. Anything that doesn't validate is
	 * dropped silently so the saved option always reflects a usable shape.
	 *
	 * @param array $raw
	 * @return array
	 */
	private function sanitize_organization( $raw ) {
		$clean = array();

		$clean['enabled'] = ! empty( $raw['enabled'] );

		// Schema types: only allow the curated set; Organization is always present.
		$allowed_types = array( 'Organization', 'LocalBusiness', 'EmploymentAgency' );
		$types_in      = isset( $raw['types'] ) && is_array( $raw['types'] ) ? $raw['types'] : array();
		$types_clean   = array( 'Organization' );
		foreach ( $types_in as $t ) {
			$t = sanitize_text_field( (string) $t );
			if ( in_array( $t, $allowed_types, true ) && ! in_array( $t, $types_clean, true ) ) {
				$types_clean[] = $t;
			}
		}
		$clean['types'] = $types_clean;

		foreach ( array( 'legal_name', 'slogan', 'employees', 'price_range' ) as $key ) {
			if ( isset( $raw[ $key ] ) ) {
				$clean[ $key ] = sanitize_text_field( (string) $raw[ $key ] );
			}
		}
		if ( isset( $raw['description'] ) ) {
			$clean['description'] = sanitize_textarea_field( (string) $raw['description'] );
		}
		if ( isset( $raw['founded_year'] ) ) {
			$year = (int) $raw['founded_year'];
			$clean['founded_year'] = ( $year >= 1800 && $year <= 2100 ) ? $year : '';
		}
		if ( isset( $raw['telephone'] ) ) {
			$clean['telephone'] = sanitize_text_field( (string) $raw['telephone'] );
		}
		if ( isset( $raw['email'] ) ) {
			$email = sanitize_email( (string) $raw['email'] );
			$clean['email'] = $email ?: '';
		}

		// Address.
		$addr_in = isset( $raw['address'] ) && is_array( $raw['address'] ) ? $raw['address'] : array();
		$addr    = array();
		foreach ( array( 'street', 'locality', 'region', 'postcode' ) as $k ) {
			if ( isset( $addr_in[ $k ] ) ) {
				$addr[ $k ] = sanitize_text_field( (string) $addr_in[ $k ] );
			}
		}
		if ( isset( $addr_in['country'] ) ) {
			$addr['country'] = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) $addr_in['country'] ), 0, 2 ) );
		}
		if ( array_filter( $addr ) ) {
			$clean['address'] = $addr;
		}

		// Geo.
		$geo_in = isset( $raw['geo'] ) && is_array( $raw['geo'] ) ? $raw['geo'] : array();
		$lat = isset( $geo_in['latitude'] )  ? trim( (string) $geo_in['latitude'] )  : '';
		$lng = isset( $geo_in['longitude'] ) ? trim( (string) $geo_in['longitude'] ) : '';
		if ( $lat !== '' && $lng !== '' && is_numeric( $lat ) && is_numeric( $lng ) ) {
			$clean['geo'] = array(
				'latitude'  => (float) $lat,
				'longitude' => (float) $lng,
			);
		}

		// Newline-delimited list helpers.
		foreach ( array( 'area_served', 'knows_about' ) as $list_key ) {
			if ( ! isset( $raw[ $list_key ] ) ) {
				continue;
			}
			$lines = array_filter( array_map( 'trim', preg_split( "/\r?\n/", (string) $raw[ $list_key ] ) ) );
			$lines = array_values( array_map( 'sanitize_text_field', $lines ) );
			if ( $lines ) {
				$clean[ $list_key ] = $lines;
			}
		}

		// Opening hours.
		$hours_in    = isset( $raw['hours'] ) && is_array( $raw['hours'] ) ? $raw['hours'] : array();
		$valid_days  = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );
		$hours_clean = array();
		foreach ( $hours_in as $day => $row ) {
			if ( ! in_array( $day, $valid_days, true ) || ! is_array( $row ) ) {
				continue;
			}
			$closed = ! empty( $row['closed'] );
			$opens  = isset( $row['opens'] )  ? preg_replace( '/[^0-9:]/', '', (string) $row['opens'] )  : '';
			$closes = isset( $row['closes'] ) ? preg_replace( '/[^0-9:]/', '', (string) $row['closes'] ) : '';
			if ( $closed || ( $opens !== '' && $closes !== '' ) ) {
				$hours_clean[ $day ] = array(
					'closed' => $closed,
					'opens'  => $closed ? '' : $opens,
					'closes' => $closed ? '' : $closes,
				);
			}
		}
		if ( $hours_clean ) {
			$clean['hours'] = $hours_clean;
		}

		// Aggregate rating. All three fields must be present, otherwise discard.
		$ar_in = isset( $raw['aggregate_rating'] ) && is_array( $raw['aggregate_rating'] ) ? $raw['aggregate_rating'] : array();
		$rv    = isset( $ar_in['rating_value'] ) ? trim( (string) $ar_in['rating_value'] ) : '';
		$rc    = isset( $ar_in['review_count'] ) ? (int) $ar_in['review_count'] : 0;
		$src   = isset( $ar_in['source_url'] )   ? esc_url_raw( (string) $ar_in['source_url'] ) : '';
		if ( $rv !== '' && is_numeric( $rv ) && (float) $rv > 0 && $rc > 0 && $src !== '' ) {
			$clean['aggregate_rating'] = array(
				'rating_value' => (float) $rv,
				'review_count' => $rc,
				'source_url'   => $src,
			);
		}

		return $clean;
	}
}
