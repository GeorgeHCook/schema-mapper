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

	/**
	 * Returns full field metadata for each ACF field available on a post type,
	 * including the field type and (where applicable) its choices.
	 *
	 * Used by the gate UI in the settings page: when the chosen field is a
	 * select/radio/true_false, the "compared value" control becomes a dropdown
	 * of the field's options rather than a free-text input.
	 *
	 * Return shape:
	 *   [ field_name => [ 'label' => '...', 'type' => '...', 'choices' => ['value' => 'label', ...] ] ]
	 *
	 * @param string $post_type
	 * @return array<string,array{label:string,type:string,choices:array<string,string>}>
	 */
	public static function get_field_meta_for_post_type( $post_type ) {
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
				$type    = $field['type'] ?? '';
				$choices = array();
				if ( in_array( $type, array( 'select', 'radio', 'checkbox', 'button_group' ), true ) ) {
					if ( ! empty( $field['choices'] ) && is_array( $field['choices'] ) ) {
						foreach ( $field['choices'] as $val => $lbl ) {
							$choices[ (string) $val ] = (string) $lbl;
						}
					}
				} elseif ( $type === 'true_false' ) {
					$choices = array( '1' => __( 'True / Yes', 'schema-mapper' ), '0' => __( 'False / No', 'schema-mapper' ) );
				}
				$out[ $field['name'] ] = array(
					'label'   => $field['label'] ?: $field['name'],
					'type'    => $type,
					'choices' => $choices,
				);
			}
		}
		return $out;
	}
}
