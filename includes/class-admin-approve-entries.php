<?php
/**
 *
 *
 * @package   GravityView
 * @license   GPL2+
 * @author    Katz Web Services, Inc.
 * @link      http://gravityview.co
 * @copyright Copyright 2014, Katz Web Services, Inc.
 *
 * @since 1.0.0
 */



class GravityView_Admin_ApproveEntries {

	// hold notification messages
	public $bulk_update_message = '';

	function __construct() {

		/** Edit Gravity Form page */

		// Add button to left menu
		add_filter( 'gform_add_field_buttons', array( $this, 'add_field_buttons' ) );
		// Set defaults
		add_action( 'gform_editor_js_set_default_values', array( $this, 'set_defaults' ) );

		/** gf_entries page - entries table screen */

		// capture bulk actions
		add_action( 'init', array( $this, 'process_bulk_action') );
		// add hidden field with approve status
		add_action( 'gform_entries_first_column', array( $this, 'add_entry_approved_hidden_input' ), 1, 5 );
		// process ajax approve entry requests
		add_action('wp_ajax_gv_update_approved', array( $this, 'ajax_update_approved'));

		// in case entry is edited (on admin or frontend)
		add_action( 'gform_after_update_entry', array( $this, 'update_approved_meta' ), 10, 2);

		add_filter( 'gravityview_tooltips', array( $this, 'tooltips' ) );

		// adding styles and scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'add_scripts_and_styles') );
		// bypass Gravity Forms no-conflict mode
		add_filter( 'gform_noconflict_scripts', array( $this, 'register_gform_noconflict_script' ) );
		add_filter( 'gform_noconflict_styles', array( $this, 'register_gform_noconflict_style' ) );

	}

	function tooltips( $tooltips ) {

		$tooltips['form_gravityview_fields'] = array(
			'title' => __('GravityView Fields', 'gravityview'),
			'value' => __( 'Allow administrators to approve or reject entries and users to opt-in or opt-out of their entries being displayed.', 'gravityview'),
		);

		return $tooltips;
	}


	/**
	 * Inject new add field buttons in the gravity form editor page
	 *
	 * @access public
	 * @param mixed $field_groups
	 * @return void
	 */
	function add_field_buttons( $field_groups ) {

		$gravityview_fields = array(
			'name' => 'gravityview_fields',
			'label' => 'GravityView',
			'fields' => array(
				array(
					'class' => 'button',
					'value' => __( 'Approve/Reject', 'gravityview' ),
					'onclick' => "StartAddField('gravityviewapproved_admin');",
					'data-type' => 'gravityviewapproved_admin'
				),
				array(
					'class' => 'button',
					'value' => __( 'User Opt-In', 'gravityview' ),
					'onclick' => "StartAddField('gravityviewapproved');",
					'data-type' => 'gravityviewapproved'
				),
			)
		);

		array_push( $field_groups, $gravityview_fields );

		return $field_groups;
	}



	/**
	 * At edit form page, set the field Approve defaults
	 *
	 * @access public
	 * @return void
	 */
	function set_defaults() {
		?>
		case 'gravityviewapproved_admin':
			field.label = "<?php _e( 'Approved? (Admin-only)', 'gravityview' ); ?>";

			field.adminLabel = "<?php _e( 'Approved?', 'gravityview' ); ?>";
			field.adminOnly = true;

			field.choices = null;
			field.inputs = null;

			if( !field.choices ) {
				field.choices = new Array( new Choice("<?php _e( 'Approved', 'gravityview' ); ?>") );
			}

			field.inputs = new Array();
			for( var i=1; i<=field.choices.length; i++ ) {
				field.inputs.push(new Input(field.id + (i/10), field.choices[i-1].text));
			}

			field.type = 'checkbox';
			field.gravityview_approved = 1;

			break;
		case 'gravityviewapproved':
			field.label = "<?php _e( 'Show Entry on Website', 'gravityview' ); ?>";

			field.adminLabel = "<?php _e( 'Opt-In', 'gravityview' ); ?>";
			field.adminOnly = false;

			field.choices = null;
			field.inputs = null;

			if( !field.choices ) {
				field.choices = new Array(
					new Choice("<?php _e( 'Yes, display my entry on the website', 'gravityview' ); ?>")
				);
			}

			field.inputs = new Array();
			for( var i=1; i<=field.choices.length; i++ ) {
				field.inputs.push(new Input(field.id + (i/10), field.choices[i-1].text));
			}

			field.type = 'checkbox';
			field.gravityview_approved = 1;

			break;
		<?php
	}




	/**
	 * Capture bulk actions - gf_entries table
	 *
	 * @uses  GravityView_frontend::get_search_criteria() Convert the $_POST search request into a properly formatted request.
	 * @access public
	 * @return void
	 */
	public function process_bulk_action() {
		if( !class_exists( 'RGForms' ) ) {
			return;
		}

		if( RGForms::post('action') === 'bulk' ) {

			check_admin_referer('gforms_entry_list', 'gforms_entry_list');

			// The action is formatted like: approve-16 or disapprove-16, where the first word is the name of the action and the second is the ID of the form. Bulk action 2 is the bottom bulk action select form.
			$bulk_action = !empty( $_POST['bulk_action'] ) ? $_POST['bulk_action'] : $_POST['bulk_action2'];

			list( $approved_status, $form_id ) = explode( '-', $bulk_action );

			if( empty( $form_id ) ) {
				do_action('gravityview_log_error', '[process_bulk_action] Form ID is empty from parsing bulk action.', $bulk_action );
				return false;
			}

			// All entries are set to be updated, not just the visible ones
			if( !empty( $_POST['all_entries'] ) ) {

				// Convert the current entry search into GF-formatted search criteria
				$search = array(
					'search_field' => isset( $_POST['f'] ) ? $_POST['f'][0] : 0,
					'search_value' => isset( $_POST['v'][0] ) ? $_POST['v'][0] : '',
					'search_operator' => isset( $_POST['o'][0] ) ? $_POST['o'][0] : 'contains',
				);

				$search_criteria = GravityView_frontend::get_search_criteria( $search );

				// Get all the entry IDs for the form
				$entries = gravityview_get_entry_ids( $form_id, $search_criteria );

			} else {

				$entries = $_POST['lead'];

			}

			if( empty( $entries ) ) {
				do_action('gravityview_log_error', '[process_bulk_action] Entries are empty');
				return false;
			}

			$entry_count = count( $entries ) > 1 ? sprintf(__("%d entries", 'gravityview' ), count( $entries ) ) : __( '1 entry', 'gravityview' );

			switch( $approved_status ) {
				case 'approve':
					self::update_bulk( $entries, 1, $form_id );
					$this->bulk_update_message = sprintf( __( "%s approved.", 'gravityview' ), $entry_count );
					break;

				case 'unapprove':
					self::update_bulk( $entries, 0, $form_id );
					$this->bulk_update_message = sprintf( __( "%s disapproved.", 'gravityview' ), $entry_count );
					break;
			}
		}
	}





	/**
	 * Process a bulk of entries to update the approve field/property
	 *
	 * @access private
	 * @static
	 * @param array|boolean $entries If array, array of entry IDs that are to be updated. If true: update all entries.
	 * @param int $approved Approved status. If `0`: unapproved, if not empty, `Approved`
	 * @param int $form_id The Gravity Forms Form ID
	 * @return void
	 */
	private static function update_bulk( $entries, $approved, $form_id ) {

		if( empty($entries) || ( $entries !== true && !is_array($entries) ) ) { return false; }

		$approved = empty( $approved ) ? 0 : 'Approved';

		// calculate approved field id
		$approved_column_id = self::get_approved_column( $form_id );

		foreach( $entries as $entry_id ) {
			self::update_approved( (int)$entry_id, $approved, $form_id, $approved_column_id );
		}
	}




	/**
	 * update_approved function.
	 *
	 * @access public
	 * @static
	 * @param int $lead_id (default: 0)
	 * @param int $approved (default: 0)
	 * @param int $form_id (default: 0)
	 * @param int $approvedcolumn (default: 0)
	 * @return boolean True: It worked; False: it failed
	 */
	public static function update_approved( $entry_id = 0, $approved = 0, $form_id = 0, $approvedcolumn = 0) {

		if( !class_exists( 'GFAPI' ) ) {
			return;
		}

		if( empty( $approvedcolumn ) ) {
			$approvedcolumn = self::get_approved_column( $form_id );
		}

		//get the entry
		$entry = GFAPI::get_entry( $entry_id );

		//update entry
		$entry[ (string)$approvedcolumn ] = $approved;
		$result = GFAPI::update_entry( $entry );

		// update entry meta
		if( function_exists('gform_update_meta') ) {
			gform_update_meta( $entry_id, 'is_approved', $approved);
		}

		// add note to entry
		if( $result === true ) {
			$note = empty( $approved ) ? __( 'Disapproved the Entry for GravityView', 'gravityview' ) : __( 'Approved the Entry for GravityView', 'gravityview' );
			if( class_exists( 'RGFormsModel' ) ){
				global $current_user;
      			get_currentuserinfo();
				RGFormsModel::add_note( $entry_id, $current_user->ID, $current_user->display_name, $note );
			}

			/**
			 * Destroy the cache for this form
			 * @see class-cache.php
			 * @since 1.5.1
			 */
			do_action( 'gravityview_clear_form_cache', $form_id );

		}

		return $result;

	}
	/**
	 * Update the is_approved meta whenever the entry is updated
	 * @param  array $form     Gravity Forms form array
	 * @param  int $entry_id ID of the Gravity Forms entry
	 * @return void
	 */
	public static function update_approved_meta( $form, $entry_id = NULL ) {

		$approvedcolumn = (string)self::get_approved_column( $form['id'] );

		$entry = GFAPI::get_entry( $entry_id );
		$approved = !empty( $entry[ $approvedcolumn ] ) ? $entry[ $approvedcolumn ] : 0;

		// update entry meta
		if( function_exists('gform_update_meta') ) {
			gform_update_meta( $entry_id, 'is_approved', $approved );
		}

	}


	/**
	 * Approve/Disapprove entries using the × or ✓ icons in the GF Entries screen
	 * @return void
	 */
	public function ajax_update_approved() {
		$response = false;

		if( empty( $_POST['entry_id'] ) || empty( $_POST['form_id'] ) ) {

			do_action( 'gravityview_log_error', 'GravityView_Admin_ApproveEntries[ajax_update_approved] entry_id or form_id are empty.', $_POST );

			exit( false );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'gravityview_ajaxgfentries' ) ) {

			do_action( 'gravityview_log_error', 'GravityView_Admin_ApproveEntries[ajax_update_approved] Security check failed.', $_POST );

			exit( false );
		}

		$result = self::update_approved( $_POST['entry_id'], $_POST['approved'], $_POST['form_id'] );

		exit( $result );
	}


	/**
	 * Calculate the approve field.input id
	 *
	 * @access public
	 * @static
	 * @param mixed $form_id
	 * @return false|null|string Returns the input ID of the approved field. Returns NULL if no approved fields were found. Returns false if $form_id wasn't set.
	 */
	static public function get_approved_column( $form_id ) {
		if( empty( $form_id ) ) {
			return false;
		}

		$form = gravityview_get_form( $form_id );

		foreach( $form['fields'] as $key => $field ) {

			if( !empty( $field['gravityview_approved'] ) ) {
				if( !empty($field['inputs'][0]['id']) ) {
					return $field['inputs'][0]['id'];
				}
			}

			if( !empty( $field['adminOnly'] ) && 'checkbox' == $field['type'] && isset( $field['inputs'] ) && is_array( $field['inputs'] ) ) {
				foreach( $field['inputs'] as $key2 => $input) {
					if( strtolower( $input['label'] ) == 'approved' ) {
						return $input['id'];
					}
				}
			}
		}

		return null;
	}



	static public function add_entry_approved_hidden_input(  $form_id, $field_id, $value, $entry, $query_string ) {
		if( empty( $entry['id'] ) ) {
			return;
		}
		if( gform_get_meta( $entry['id'], 'is_approved' ) ) {
			echo '<input type="hidden" class="entry_approved" id="entry_approved_'. $entry['id'] .'" value="true" />';
		}
	}




	function add_scripts_and_styles( $hook ) {

		if( !class_exists( 'RGForms' ) ) {

			do_action( 'gravityview_log_error', 'GravityView_Admin_ApproveEntries[add_scripts_and_styles] RGForms does not exist.' );

			return;
		}

		// enqueue styles & scripts gf_entries
		// But only if we're on the main Entries page, not on reports pages
		if( RGForms::get_page() === 'entry_list' ) {

			$form_id = RGForms::get('id');

			// If there are no forms identified, use the first form. That's how GF does it.
			if( empty( $form_id ) && class_exists('RGFormsModel') ) {
				$forms = gravityview_get_forms();
				if( !empty( $forms ) ) {
					$form_id = $forms[0]['id'];
				}
			}

			$approvedcolumn = self::get_approved_column( $form_id );

			wp_register_style( 'gravityview_entries_list', plugins_url('assets/css/admin-entries-list.css', GRAVITYVIEW_FILE), array(), GravityView_Plugin::version );
			wp_enqueue_style( 'gravityview_entries_list' );

			$script_debug = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';

			wp_register_script( 'gravityview_gf_entries_scripts', plugins_url('assets/js/admin-entries-list'.$script_debug.'.js', GRAVITYVIEW_FILE), array( 'jquery' ), GravityView_Plugin::version );
			wp_enqueue_script( 'gravityview_gf_entries_scripts' );

			wp_localize_script( 'gravityview_gf_entries_scripts', 'gvGlobals', array(
				'nonce' => wp_create_nonce( 'gravityview_ajaxgfentries'),
				'form_id' => $form_id,
				'label_approve' => __( 'Approve', 'gravityview' ) ,
				'label_disapprove' => __( 'Disapprove', 'gravityview' ),
				'bulk_message' => $this->bulk_update_message,
				'approve_title' => __( 'Entry not approved for directory viewing. Click to approve this entry.', 'gravityview'),
				'unapprove_title' => __( 'Entry approved for directory viewing. Click to disapprove this entry.', 'gravityview'),
				'column_title' => __( 'Show entry in directory view?', 'gravityview'),
				'column_link' => add_query_arg( array('sort' => $approvedcolumn) ),
			) );

		}

	}

	function register_gform_noconflict_script( $scripts ) {
		$scripts[] = 'gravityview_gf_entries_scripts';
		return $scripts;
	}

	function register_gform_noconflict_style( $styles ) {
		$styles[] = 'gravityview_entries_list';
		return $styles;
	}

}

new GravityView_Admin_ApproveEntries;
