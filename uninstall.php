<?php
/**
 * Uninstall handler. Removes the plugin's option.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'schema_mapper_settings' );
