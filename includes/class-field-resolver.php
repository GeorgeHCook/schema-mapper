<?php
/**
 * Resolves a field mapping spec (source + key) to a value for a given post.
 *
 * A mapping is shaped like:
 *   array( 'source' => 'acf'|'post'|'static'|'permalink'|'none', 'key' => '<name>', 'value' => '<static value>' )
 */

defined( 'ABSPATH' ) || exit;

class Schema_Mapper_Field_Resolver {

	const SOURCE_NONE      = 'none';
	const SOURCE_ACF       = 'acf';
	const SOURCE_POST      = 'post';
	const SOURCE_STATIC    = 'static';
	const SOURCE_PERMALINK = 'permalink';

	/** Built-in post-field names available. Order matters for the UI dropdown. */
	public static function post_fields() {
		return array(
			'post_title'    => __( 'Post title', 'schema-mapper' ),
			'post_content'  => __( 'Post content (HTML)', 'schema-mapper' ),
			'post_excerpt'  => __( 'Post excerpt', 'schema-mapper' ),
			'post_date'     => __( 'Post date (ISO 8601)', 'schema-mapper' ),
			'post_modified' => __( 'Post modified date (ISO 8601)', 'schema-mapper' ),
			'post_author'   => __( 'Post author display name', 'schema-mapper' ),
			'post_id'       => __( 'Post ID', 'schema-mapper' ),
		);
	}

	/**
	 * Resolve a mapping for a specific post id.
	 *
	 * @param array $mapping
	 * @param int   $post_id
	 * @return mixed null when unmapped / empty / non-existent
	 */
	public static function resolve( $mapping, $post_id ) {
		if ( ! is_array( $mapping ) || empty( $mapping['source'] ) ) {
			return null;
		}
		$source = $mapping['source'];
		$key    = isset( $mapping['key'] ) ? (string) $mapping['key'] : '';

		switch ( $source ) {
			case self::SOURCE_NONE:
				return null;

			case self::SOURCE_STATIC:
				$value = isset( $mapping['value'] ) ? $mapping['value'] : '';
				return '' === $value ? null : $value;

			case self::SOURCE_PERMALINK:
				return get_permalink( $post_id ) ?: null;

			case self::SOURCE_POST:
				return self::resolve_post_field( $key, $post_id );

			case self::SOURCE_ACF:
				$value = Schema_Mapper_ACF_Helper::get_field_value( $key, $post_id );
				if ( is_string( $value ) && '' === trim( $value ) ) {
					return null;
				}
				return $value;
		}

		return null;
	}

	private static function resolve_post_field( $field, $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}
		switch ( $field ) {
			case 'post_title':
				return get_the_title( $post );
			case 'post_content':
				return apply_filters( 'the_content', $post->post_content );
			case 'post_excerpt':
				return $post->post_excerpt ?: null;
			case 'post_date':
				return mysql2date( 'c', $post->post_date_gmt ?: $post->post_date, false );
			case 'post_modified':
				return mysql2date( 'c', $post->post_modified_gmt ?: $post->post_modified, false );
			case 'post_author':
				return get_the_author_meta( 'display_name', (int) $post->post_author ) ?: null;
			case 'post_id':
				return (int) $post->ID;
		}
		return null;
	}
}
