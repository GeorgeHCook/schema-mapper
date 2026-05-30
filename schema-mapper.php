<?php
/**
 * Plugin Name:       Schema Mapper
 * Plugin URI:        https://github.com/GeorgeHCook/schema-mapper
 * Description:       Map ACF fields and post data to Schema.org structured data per post type. Outputs JSON-LD on the front end.
 * Version:           0.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            George Cook
 * Author URI:        https://github.com/GeorgeHCook
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       schema-mapper
 * GitHub Plugin URI: GeorgeHCook/schema-mapper
 * Primary Branch:    main
 */

defined( 'ABSPATH' ) || exit;

define( 'SCHEMA_MAPPER_VERSION', '0.2.0' );
define( 'SCHEMA_MAPPER_FILE', __FILE__ );
define( 'SCHEMA_MAPPER_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCHEMA_MAPPER_URL', plugin_dir_url( __FILE__ ) );
define( 'SCHEMA_MAPPER_OPTION', 'schema_mapper_settings' );

require_once SCHEMA_MAPPER_DIR . 'includes/class-schema-mapper.php';
require_once SCHEMA_MAPPER_DIR . 'includes/class-acf-helper.php';
require_once SCHEMA_MAPPER_DIR . 'includes/class-field-resolver.php';
require_once SCHEMA_MAPPER_DIR . 'includes/class-renderer.php';
require_once SCHEMA_MAPPER_DIR . 'includes/class-settings.php';
require_once SCHEMA_MAPPER_DIR . 'includes/class-metabox.php';
require_once SCHEMA_MAPPER_DIR . 'includes/schemas/class-schema-type.php';
require_once SCHEMA_MAPPER_DIR . 'includes/schemas/class-jobposting-schema.php';

add_action( 'plugins_loaded', array( 'Schema_Mapper', 'instance' ) );
