<?php
/**
 * @file class-gravityview-field-date.php
 * @package GravityView
 * @subpackage includes\fields
 */

/**
 * Add custom options for date fields
 */
class GravityView_Field_Date extends GravityView_Field {

	var $name = 'date';

	var $_gf_field_class_name = 'GF_Field_Date';

	var $group = 'advanced';

	public function __construct() {
		$this->label = esc_html__( 'Date', 'gravityview' );
		parent::__construct();
		GravityView_Item_Settings::set_visibility_condition( 'date_display', 'field_type', 'is', $this->name );
	}

	function field_options( $field_options, $template_id = '', $field_id = '', $context = '', $input_type = '' ) {

		if( 'edit' === $context ) {
			return $field_options;
		}

		$this->add_field_support( 'date_display', $field_options );

		return $field_options;
	}

}

new GravityView_Field_Date;
