<?php
/**
 * Helpers for discovering ACF fields. Degrades gracefully when ACF is missing.
 */

defined( 'ABSPATH' ) || exit;

class Schema_Mapper_ACF_Helper {

	public static function is_available() {
		return function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' );
	}

	/**
	 * Return ACF text-like fields available for a given post type.
	 *
	 * @param string $post_type
	 * @return array<string,string> field name => "Label (type)"
	 */
	public static function get_fields_for_post_type( $post_type ) {
		if ( ! self::is_available() ) {
			return array();
		}
		$out    = array();
		$groups = acf_get_field_groups( array( 'post_type' => $post_type ) );
		if ( ! is_array( $groups ) ) {
			return array();
		}
		foreach ( $groups as $group ) {
			$fields = acf_get_fields( $group );
			if ( ! is_array( $fields ) ) {
				continue;
			}
			foreach ( $fields as $field ) {
				if ( empty( $field['name'] ) ) {
					continue;
				}
				$out[ $field['name'] ] = sprintf( '%s (%s)', $field['label'] ?: $field['name'], $field['type'] );
			}
		}
		return $out;
	}

	/**
	 * Returns the value of an ACF field for a post, or the raw post meta value as fallback.
	 *
	 * @param string $field_name
	 * @param int    $post_id
	 * @return mixed
	 */
	public static function get_field_value( $field_name, $post_id ) {
		if ( self::is_available() && function_exists( 'get_field' ) ) {
			return get_field( $field_name, $post_id );
		}
		return get_post_meta( $post_id, $field_name, true );
	}
}
