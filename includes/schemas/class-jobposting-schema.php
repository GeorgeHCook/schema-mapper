<?php
/**
 * JobPosting schema type.
 *
 * Reference: https://developers.google.com/search/docs/appearance/structured-data/job-posting
 *
 * Notes on anonymous employers (typical for executive recruitment): Google for Jobs
 * lets a recruiter use itself as hiringOrganization when the actual employer is
 * confidential. Set directApply: false because candidates apply via the recruiter.
 */

defined( 'ABSPATH' ) || exit;

class Schema_Mapper_JobPosting extends Schema_Mapper_Type {

	public function slug() {
		return 'JobPosting';
	}

	public function label() {
		return __( 'Job Posting', 'schema-mapper' );
	}

	public function fields() {
		return array(
			'title' => array(
				'label'       => __( 'title', 'schema-mapper' ),
				'required'    => true,
				'description' => __( 'Clean, human-readable job title. Avoid internal shorthand or codes.', 'schema-mapper' ),
			),
			'description' => array(
				'label'       => __( 'description', 'schema-mapper' ),
				'required'    => true,
				'description' => __( 'Full job description, HTML allowed. Should be 300+ characters.', 'schema-mapper' ),
				'transform'   => 'wpautop',
			),
			'datePosted' => array(
				'label'       => __( 'datePosted', 'schema-mapper' ),
				'required'    => true,
				'description' => __( 'Date the listing was first posted. Defaults to post_date.', 'schema-mapper' ),
				'transform'   => 'iso8601',
			),
			'validThrough' => array(
				'label'       => __( 'validThrough', 'schema-mapper' ),
				'required'    => false,
				'description' => __( 'When the listing closes. If unmapped, defaults to datePosted + 30 days.', 'schema-mapper' ),
				'transform'   => 'iso8601',
			),
			'employmentType' => array(
				'label'       => __( 'employmentType', 'schema-mapper' ),
				'required'    => true,
				'description' => __( 'FULL_TIME, PART_TIME, CONTRACTOR, TEMPORARY, INTERN, VOLUNTEER, PER_DIEM, OTHER. Picks up "Temp"/"Perm" etc. via the employment_type_map transform.', 'schema-mapper' ),
				'transform'   => 'employment_type_map',
			),
			'baseSalary' => array(
				'label'       => __( 'baseSalary', 'schema-mapper' ),
				'required'    => false,
				'description' => __( 'Salary. Use gbp_salary_range to parse strings like "£20,000-£30,000".', 'schema-mapper' ),
				'transform'   => 'gbp_salary_range',
			),
			'jobLocation_locality' => array(
				'label'       => __( 'jobLocation.addressLocality', 'schema-mapper' ),
				'required'    => true,
				'description' => __( 'City / neighbourhood text, e.g. "Central London".', 'schema-mapper' ),
			),
			'jobLocation_region' => array(
				'label'       => __( 'jobLocation.addressRegion', 'schema-mapper' ),
				'required'    => false,
				'description' => __( 'Region. Defaults to "London" if unmapped.', 'schema-mapper' ),
			),
			'jobLocation_country' => array(
				'label'       => __( 'jobLocation.addressCountry', 'schema-mapper' ),
				'required'    => false,
				'description' => __( 'ISO country code. Defaults to "GB" if unmapped.', 'schema-mapper' ),
			),
			'jobLocationType' => array(
				'label'       => __( 'jobLocationType', 'schema-mapper' ),
				'required'    => false,
				'description' => __( '"TELECOMMUTE" for fully remote. Use the work_setting transform with values like "Office", "Home", "Hybrid".', 'schema-mapper' ),
				'transform'   => 'work_setting',
			),
			'identifier' => array(
				'label'       => __( 'identifier (job id)', 'schema-mapper' ),
				'required'    => false,
				'description' => __( 'Internal job ID. Defaults to the WordPress post ID.', 'schema-mapper' ),
			),
			'industry' => array(
				'label'       => __( 'industry / hiringOrganization.disambiguatingDescription', 'schema-mapper' ),
				'required'    => false,
				'description' => __( 'Describes the anonymised employer, e.g. "Independent school". Surfaces under hiringOrganization.disambiguatingDescription so Google for Jobs still gets useful context.', 'schema-mapper' ),
			),
			'recruiter_name' => array(
				'label'       => __( 'hiringOrganization.name (recruiter)', 'schema-mapper' ),
				'required'    => true,
				'description' => __( 'Recruiter / agency name shown as the hiring organisation. Set as a static value, e.g. "Joyce Guiness".', 'schema-mapper' ),
			),
			'recruiter_url' => array(
				'label'       => __( 'hiringOrganization.url', 'schema-mapper' ),
				'required'    => false,
				'description' => __( 'Recruiter homepage URL. Defaults to the site home URL.', 'schema-mapper' ),
			),
			'recruiter_logo' => array(
				'label'       => __( 'hiringOrganization.logo', 'schema-mapper' ),
				'required'    => false,
				'description' => __( 'Recruiter logo URL.', 'schema-mapper' ),
			),
		);
	}

	public function transforms() {
		return array(
			'employment_type_map' => array(
				'label'    => __( 'Employment type map (Temp → TEMPORARY, Perm → FULL_TIME, etc.)', 'schema-mapper' ),
				'callable' => array( __CLASS__, 'transform_employment_type' ),
			),
			'gbp_salary_range' => array(
				'label'    => __( 'GBP salary range parser ("£20,000-£30,000" → MonetaryAmount)', 'schema-mapper' ),
				'callable' => array( __CLASS__, 'transform_gbp_salary' ),
			),
			'work_setting' => array(
				'label'    => __( 'Work setting → jobLocationType (Home/Remote → TELECOMMUTE)', 'schema-mapper' ),
				'callable' => array( __CLASS__, 'transform_work_setting' ),
			),
			'iso8601' => array(
				'label'    => __( 'ISO 8601 datetime', 'schema-mapper' ),
				'callable' => array( __CLASS__, 'transform_iso8601' ),
			),
			'wpautop' => array(
				'label'    => __( 'Wrap text in paragraphs (wpautop)', 'schema-mapper' ),
				'callable' => 'wpautop',
			),
		);
	}

	public function build( $post_id, array $mapping, array $resolved ) {

		if ( ! self::gate_passes( $mapping['gate'] ?? null, $post_id ) ) {
			return null;
		}

		// Required: title, description, datePosted, employmentType, locality, recruiter name
		foreach ( array( 'title', 'description', 'datePosted', 'employmentType', 'jobLocation_locality', 'recruiter_name' ) as $required ) {
			if ( empty( $resolved[ $required ] ) ) {
				return null;
			}
		}

		$date_posted_iso  = $resolved['datePosted'];

		// validThrough handling: Google requires this to be in the future when present.
		// If unmapped, omit. If mapped but resolved to a past timestamp, omit so we don't
		// emit a broken listing.
		$valid_through = $resolved['validThrough'] ?? null;
		if ( $valid_through ) {
			$ts = strtotime( $valid_through );
			if ( ! $ts || $ts < time() ) {
				$valid_through = null;
			}
		}

		$hiring_org = array(
			'@type' => 'Organization',
			'name'  => $resolved['recruiter_name'],
		);
		if ( ! empty( $resolved['recruiter_url'] ) )  $hiring_org['url']  = $resolved['recruiter_url'];
		if ( empty( $resolved['recruiter_url'] ) )    $hiring_org['url']  = home_url( '/' );
		if ( ! empty( $resolved['recruiter_logo'] ) ) $hiring_org['logo'] = $resolved['recruiter_logo'];
		if ( ! empty( $resolved['industry'] ) ) {
			$hiring_org['disambiguatingDescription'] = sprintf(
				/* translators: %s is the anonymised employer description, e.g. "Independent school" */
				__( 'Confidential client recruited by %1$s: %2$s.', 'schema-mapper' ),
				$resolved['recruiter_name'],
				$resolved['industry']
			);
		}

		$job_location = array(
			'@type'   => 'Place',
			'address' => array(
				'@type'           => 'PostalAddress',
				'addressLocality' => $resolved['jobLocation_locality'],
				'addressRegion'   => $resolved['jobLocation_region'] ?: 'London',
				'addressCountry'  => $resolved['jobLocation_country'] ?: 'GB',
			),
		);

		$schema = array(
			'@context'           => 'https://schema.org',
			'@type'              => 'JobPosting',
			'title'              => $resolved['title'],
			'description'        => $resolved['description'],
			'datePosted'         => $date_posted_iso,
			'employmentType'     => $resolved['employmentType'],
			'hiringOrganization' => $hiring_org,
			'jobLocation'        => $job_location,
			'directApply'        => false,
			'identifier'         => array(
				'@type' => 'PropertyValue',
				'name'  => $resolved['recruiter_name'],
				'value' => $resolved['identifier'] ?: (string) $post_id,
			),
		);

		if ( $valid_through )                            $schema['validThrough']    = $valid_through;
		if ( ! empty( $resolved['baseSalary'] ) )       $schema['baseSalary']      = $resolved['baseSalary'];
		if ( ! empty( $resolved['jobLocationType'] ) ) {
			$schema['jobLocationType']                = $resolved['jobLocationType'];
			if ( 'TELECOMMUTE' === $resolved['jobLocationType'] ) {
				$schema['applicantLocationRequirements'] = array(
					'@type' => 'Country',
					'name'  => 'United Kingdom',
				);
			}
		}
		if ( ! empty( $resolved['industry'] ) )         $schema['industry']        = $resolved['industry'];

		return $schema;
	}

	// ------------------------------------------------------------------------
	// Transforms
	// ------------------------------------------------------------------------

	public static function transform_employment_type( $value ) {
		if ( ! is_scalar( $value ) ) {
			return $value;
		}
		$key = strtolower( trim( (string) $value ) );
		$map = array(
			'temp'           => 'TEMPORARY',
			'temporary'      => 'TEMPORARY',
			'perm'           => 'FULL_TIME',
			'permanent'      => 'FULL_TIME',
			'full time'      => 'FULL_TIME',
			'full-time'      => 'FULL_TIME',
			'fulltime'       => 'FULL_TIME',
			'part time'      => 'PART_TIME',
			'part-time'      => 'PART_TIME',
			'parttime'       => 'PART_TIME',
			'contract'       => 'CONTRACTOR',
			'contractor'     => 'CONTRACTOR',
			'fixed term'     => 'CONTRACTOR',
			'fixed-term'     => 'CONTRACTOR',
			'ftc'            => 'CONTRACTOR',
			'internship'     => 'INTERN',
			'intern'         => 'INTERN',
		);
		return $map[ $key ] ?? 'FULL_TIME';
	}

	/**
	 * Parse strings like:
	 *   "£20,000-£30,000"
	 *   "£20,000 - £30,000"
	 *   "£60,000+"
	 * into MonetaryAmount.
	 */
	public static function transform_gbp_salary( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return null;
		}
		$value = str_replace( array( "\xC2\xA3", '£' ), '£', $value ); // normalise
		if ( preg_match( '/£\s*([\d,]+)\s*[-–]\s*£?\s*([\d,]+)/u', $value, $m ) ) {
			return array(
				'@type'    => 'MonetaryAmount',
				'currency' => 'GBP',
				'value'    => array(
					'@type'    => 'QuantitativeValue',
					'minValue' => (int) str_replace( ',', '', $m[1] ),
					'maxValue' => (int) str_replace( ',', '', $m[2] ),
					'unitText' => 'YEAR',
				),
			);
		}
		if ( preg_match( '/£\s*([\d,]+)\+/u', $value, $m ) ) {
			return array(
				'@type'    => 'MonetaryAmount',
				'currency' => 'GBP',
				'value'    => array(
					'@type'    => 'QuantitativeValue',
					'minValue' => (int) str_replace( ',', '', $m[1] ),
					'unitText' => 'YEAR',
				),
			);
		}
		if ( preg_match( '/£\s*([\d,]+)/u', $value, $m ) ) {
			return array(
				'@type'    => 'MonetaryAmount',
				'currency' => 'GBP',
				'value'    => array(
					'@type'    => 'QuantitativeValue',
					'value'    => (int) str_replace( ',', '', $m[1] ),
					'unitText' => 'YEAR',
				),
			);
		}
		return null;
	}

	public static function transform_work_setting( $value ) {
		if ( ! is_scalar( $value ) ) {
			return null;
		}
		$key = strtolower( trim( (string) $value ) );
		if ( in_array( $key, array( 'home', 'remote', 'wfh' ), true ) ) {
			return 'TELECOMMUTE';
		}
		return null;
	}

	public static function transform_iso8601( $value ) {
		if ( is_numeric( $value ) ) {
			return gmdate( 'c', (int) $value );
		}
		if ( is_string( $value ) ) {
			$ts = strtotime( $value );
			if ( $ts ) {
				return gmdate( 'c', $ts );
			}
		}
		return $value;
	}
}
