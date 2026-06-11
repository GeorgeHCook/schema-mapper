<?php
/**
 * Augments Yoast's Organization schema node with the values configured in
 * Schema Mapper > Employment Agency / Organization.
 *
 * Wires into the `wpseo_schema_organization` filter so emitted JSON-LD layers
 * EmploymentAgency / LocalBusiness fields onto the existing org graph rather
 * than duplicating a separate node.
 */

defined( 'ABSPATH' ) || exit;

class Schema_Mapper_Organization_Emitter {

	/** @var Schema_Mapper */
	private $plugin;

	public function __construct( Schema_Mapper $plugin ) {
		$this->plugin = $plugin;
		add_filter( 'wpseo_schema_organization', array( $this, 'augment' ), 20, 1 );
	}

	/**
	 * Build a representative copy of the Organization graph node that will
	 * actually be emitted on the front-end, for the admin preview pane.
	 *
	 * Reconstructs the base node from Yoast / site settings the same way Yoast
	 * does, then runs it through {@see augment()}. Output is identical to what
	 * lands in JSON-LD on real pages, so the editor sees exactly what Google
	 * will see.
	 *
	 * @return array
	 */
	public function preview() {
		$yoast      = is_array( get_option( 'wpseo_titles' ) ) ? get_option( 'wpseo_titles' ) : array();
		$home       = trailingslashit( home_url( '/' ) );
		$company    = ! empty( $yoast['company_name'] ) ? $yoast['company_name'] : get_bloginfo( 'name' );
		$logo_url   = $yoast['company_logo'] ?? '';

		$base = array(
			'@type' => array( 'Organization' ),
			'@id'   => $home . '#organization',
			'name'  => $company,
			'url'   => $home,
		);
		if ( $logo_url ) {
			$base['logo'] = array(
				'@type' => 'ImageObject',
				'@id'   => $home . '#/schema/logo/image/',
				'url'   => $logo_url,
			);
			$base['image'] = array( '@id' => $home . '#/schema/logo/image/' );
		}

		return $this->augment( $base );
	}

	/**
	 * @param array $data The Organization graph node Yoast is about to emit.
	 * @return array
	 */
	public function augment( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$org = $this->plugin->get_organization_settings();
		if ( empty( $org ) || empty( $org['enabled'] ) ) {
			return $data;
		}

		// @type: merge requested types into whatever Yoast emitted.
		$existing = isset( $data['@type'] ) ? (array) $data['@type'] : array( 'Organization' );
		$requested = isset( $org['types'] ) && is_array( $org['types'] ) ? $org['types'] : array( 'EmploymentAgency' );
		$merged   = array();
		foreach ( array_merge( $existing, $requested ) as $t ) {
			$t = (string) $t;
			if ( $t !== '' && ! in_array( $t, $merged, true ) ) {
				$merged[] = $t;
			}
		}
		$data['@type'] = $merged;

		// Identity fields.
		if ( ! empty( $org['legal_name'] ) && empty( $data['legalName'] ) ) {
			$data['legalName'] = $org['legal_name'];
		}
		if ( ! empty( $org['slogan'] ) && empty( $data['slogan'] ) ) {
			$data['slogan'] = $org['slogan'];
		}
		if ( ! empty( $org['description'] ) && empty( $data['description'] ) ) {
			$data['description'] = $org['description'];
		}
		if ( ! empty( $org['founded_year'] ) && empty( $data['foundingDate'] ) ) {
			$data['foundingDate'] = (string) $org['founded_year'];
		}
		if ( ! empty( $org['employees'] ) && empty( $data['numberOfEmployees'] ) ) {
			$data['numberOfEmployees'] = $org['employees'];
		}

		// Contact.
		if ( ! empty( $org['telephone'] ) && empty( $data['telephone'] ) ) {
			$data['telephone'] = $org['telephone'];
		}
		if ( ! empty( $org['email'] ) && empty( $data['email'] ) ) {
			$data['email'] = $org['email'];
		}

		// Address — emit as a PostalAddress sub-object when at least one field exists.
		if ( ! empty( $org['address'] ) && is_array( $org['address'] ) ) {
			$postal = array( '@type' => 'PostalAddress' );
			$map = array(
				'street'   => 'streetAddress',
				'locality' => 'addressLocality',
				'region'   => 'addressRegion',
				'postcode' => 'postalCode',
				'country'  => 'addressCountry',
			);
			foreach ( $map as $src => $dst ) {
				if ( ! empty( $org['address'][ $src ] ) ) {
					$postal[ $dst ] = $org['address'][ $src ];
				}
			}
			if ( count( $postal ) > 1 ) {
				$data['address'] = $postal;
			}
		}

		// Geo.
		if ( ! empty( $org['geo']['latitude'] ) && ! empty( $org['geo']['longitude'] ) ) {
			$data['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $org['geo']['latitude'],
				'longitude' => (float) $org['geo']['longitude'],
			);
		}

		// Operating profile.
		if ( ! empty( $org['price_range'] ) ) {
			$data['priceRange'] = $org['price_range'];
		}
		if ( ! empty( $org['area_served'] ) && is_array( $org['area_served'] ) ) {
			$data['areaServed'] = array_values( $org['area_served'] );
		}
		if ( ! empty( $org['knows_about'] ) && is_array( $org['knows_about'] ) ) {
			$data['knowsAbout'] = array_values( $org['knows_about'] );
		}

		// Opening hours -> openingHoursSpecification.
		if ( ! empty( $org['hours'] ) && is_array( $org['hours'] ) ) {
			$specs = array();
			foreach ( $org['hours'] as $day => $row ) {
				if ( empty( $row ) || ! empty( $row['closed'] ) ) {
					continue;
				}
				if ( empty( $row['opens'] ) || empty( $row['closes'] ) ) {
					continue;
				}
				$specs[] = array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => $day,
					'opens'     => $row['opens'],
					'closes'    => $row['closes'],
				);
			}
			if ( $specs ) {
				$data['openingHoursSpecification'] = $specs;
			}
		}

		// Aggregate rating — only emit when all three required fields are set,
		// matching Google's policy. Discourages drive-by fake ratings.
		if ( ! empty( $org['aggregate_rating']['rating_value'] )
			&& ! empty( $org['aggregate_rating']['review_count'] )
			&& ! empty( $org['aggregate_rating']['source_url'] )
		) {
			$ar = $org['aggregate_rating'];
			$data['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (float) $ar['rating_value'],
				'reviewCount' => (int) $ar['review_count'],
				'bestRating'  => 5,
				'worstRating' => 1,
				'url'         => $ar['source_url'],
			);
		}

		return $data;
	}
}
