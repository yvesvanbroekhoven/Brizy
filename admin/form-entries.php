<?php


class Brizy_Admin_FormEntries {

	const CP_FORM_ENTRY = 'brizy-form-entry';
	const OPTION_SUBMIT_LOG = 'brizy-form-log';
	const NONCE_KEY = 'form-log';

	private $enableLog = true;

	/**
	 * @return Brizy_Admin_FormLeads
	 */
	public static function _init() {
		static $instance;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Brizy_Admin_FormLeads constructor.
	 */
	public function __construct() {

		if ( is_admin() && Brizy_Editor::is_administrator() ) {
			add_action( 'admin_menu', array( $this, 'addSubmenuPage' ), 11 );
			//add_action( 'admin_init', array( $this, 'handleEnableButton' ) );
			//add_action( 'admin_footer', array( $this, 'addOnOffOption' ) );
			add_action( 'admin_footer', array( $this, 'customStylesForList' ) );
			add_action( 'admin_init', [ $this, 'export_leads' ] );

			add_filter( 'post_row_actions', array( $this, 'filterRowActions' ), 10, 2 );
			add_filter( 'manage_' . self::CP_FORM_ENTRY . '_posts_columns', array( $this, 'replaceTitleColumn' ) );
			add_action( 'manage_' . self::CP_FORM_ENTRY . '_posts_custom_column', array(
				$this,
				'manageCustomColumns'
			), 10, 2 );
		}

		$this->enableLog = get_option( self::OPTION_SUBMIT_LOG, true );

		if ( $this->enableLog ) {
			add_filter( 'brizy_form_submit_data', array( $this, 'form_submit_data' ), 10, 2 );
		}
	}

	public function replaceTitleColumn( $columns ) {
		$newColumns = array();

		unset( $columns['title'] );
		unset( $columns['date'] );

		foreach ( $columns as $key => $column ) {
			$newColumns[ $key ] = $column;
			if ( $key == 'cb' ) {
				$newColumns['data']         = __( 'Leads details', 'brizy' );
				$newColumns['created_date'] = __( 'Date', 'brizy' );
			}
		}

		return $newColumns;
	}

	public function manageCustomColumns( $column_name, $post_ID ) {
		if ( $column_name == 'data' ) {
			$post = get_post( $post_ID );
			$data = json_decode( $post->post_content );

			// We use html_entity_decode the user can insert text in some languages like German, Hindi, etc.
			// and the function json_encode broke the json or encode the characters like this ud83dude00.
			if ( isset( $data->formData ) ) {
				foreach ( $data->formData as $i => $field ) {
					$data->formData[ $i ]->name  = html_entity_decode( $field->name );
					$data->formData[ $i ]->value = html_entity_decode( $field->value );
				}
			}

			echo Brizy_TwigEngine::instance( path_join( BRIZY_PLUGIN_PATH, "admin/views" ) )
			                     ->render( 'form-data.html.twig', array( 'data' => $data ) );
		}

		if ( $column_name == 'created_date' ) {
			$post = get_post( $post_ID );
			echo $post->post_date;
		}

	}

	/**
	 * @param $actions
	 * @param $post
	 *
	 * @return mixed
	 */
	public function filterRowActions( $actions, $post ) {

		$is_allowed = Brizy_Editor_User::is_user_allowed();

		if ( ! $is_allowed ) {
			return $actions;
		}

		if ( $post->post_type != self::CP_FORM_ENTRY ) {
			return $actions;
		}

		unset( $actions['edit'] );
		unset( $actions['inline hide-if-no-js'] );
		unset( $actions['view'] );
		unset( $actions['trash'] );

		return $actions;
	}

	public function addSubmenuPage() {
		add_submenu_page( 'brizy-settings', __( 'Leads', 'brizy' ), __( 'Leads', 'brizy' ), 'manage_options', 'edit.php?post_type=' . self::CP_FORM_ENTRY, null );
	}

	public function handleEnableButton() {

		if ( ! isset( $_REQUEST['hash'] ) || ! wp_verify_nonce( $_REQUEST['hash'], self::NONCE_KEY ) ) {
			return;
		}

		if ( isset( $_REQUEST['enabled-form-log'] ) ) {
			update_option( self::OPTION_SUBMIT_LOG, $_REQUEST['enabled-form-log'] == 1 ? true : false );

			wp_redirect( admin_url( 'edit.php?post_type=' . self::CP_FORM_ENTRY ) );
			exit;
		}
	}

	public function addOnOffOption() {

		$screen = get_current_screen();

		if ( self::CP_FORM_ENTRY == $screen->post_type ) {
			if ( $this->enableLog ) {
				$label = __( 'Disable', 'brizy' );
				$class = 'disableFormLogs';
				$val   = 0;
			} else {
				$label = __( 'Enable ', 'brizy' );
				$class = 'enableFormLogs';
				$val   = 1;
			}

			$hash = wp_create_nonce( self::NONCE_KEY );
			$url  = 'edit.php?post_type=' . self::CP_FORM_ENTRY . '&enabled-form-log=' . $val . '&hash=' . $hash;
			?>
            <script>
                jQuery('<a href="<?php echo admin_url( $url )?>" class="page-title-action <?php echo $class;?>"><?php echo $label;?></a>')
                    .insertBefore(jQuery('.wp-header-end'));
            </script>
            <style>
                .wrap .page-title-action.disableFormLogs {
                    background: red !important;
                    color: white !important;
                }

                .enableFormLogs {
                    background: green !important;
                    color: white !important;
                }

                .subsubsub .publish {
                    display: none;
                }
            </style>
			<?php
		}
	}

	public function customStylesForList() {
		$screen = get_current_screen();

		if ( self::CP_FORM_ENTRY == $screen->post_type ) {

			$disable = get_posts( [ 'post_type'      => self::CP_FORM_ENTRY,
			                        'posts_per_page' => 1
			] ) ? '' : ' brz-leads-export-disable';
			?>
            <style>
                .subsubsub {
                    display: none;
                }
            </style>
            <script type="text/javascript">
                jQuery(document).ready(function ($) {
                    $($(".wrap h1")[0]).append($('#brz-leads-export-tpl-buttons').html());
                });
            </script>
            <template id="brz-leads-export-tpl-buttons">
                <a class="brz-leads-export add-new-h2<?php echo $disable; ?>"
                   href="<?php echo admin_url( 'edit.php?post_type=' . self::CP_FORM_ENTRY . '&brizy-export-leads=' . wp_create_nonce( 'brizy-admin-export-leads' ) ); ?>">
					<?php esc_html_e( 'Export to .csv', 'brizy' ); ?>
                </a>
            </template>

			<?php
		}
	}

	public function export_leads() {

		if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['brizy-export-leads'] ) || ! wp_verify_nonce( $_GET['brizy-export-leads'], 'brizy-admin-export-leads' ) ) {
			return;
		}

		$leads = get_posts( [ 'post_type' => self::CP_FORM_ENTRY, 'posts_per_page' => - 1 ] );

		if ( ! $leads ) {
			return;
		}

		$cols = [];
		$data = [];

		foreach ( $leads as $lead ) {
			$lead_fields = json_decode( $lead->post_content, true );
			if ( empty( $lead_fields['formData'] ) ) {
				continue;
			}

			$data[] = $lead_fields['formData'];

			foreach ( $lead_fields['formData'] as $field ) {
				if ( ! in_array( $field['label'], $cols ) ) {
					$cols[] = $field['label'];
				}
			}
		}

		if ( empty( $data ) || empty( $cols ) ) {
			return;
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=leads.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$fp = fopen( 'php://output', 'wb' );
		fputcsv( $fp, $cols );

		$cols  = array_flip( $cols );
		$range = count( $cols );

		foreach ( $data as $lines ) {

			$val = array_fill( 0, $range, '' );

			foreach ( $lines as $line ) {
				$val[ $cols[ $line['label'] ] ] = $line['value'];
			}

			fputcsv( $fp, $val );
		}

		fclose( $fp );

		die();
	}

	/**
	 * @param $fields
	 * @param Brizy_Editor_Forms_Form $form
	 *
	 * @return mixed
	 */
	public function form_submit_data( $fields, $form ) {

		$title = '';

		foreach ( $fields as $i => $field ) {
			if ( strtolower( $field->type ) == 'email' ) {
				$title = $field->value;
			}

			if ( $field->name == 'g-recaptcha-response' ) {
				unset( $fields[ $i ] );
				$fields = array_values( $fields );
				continue;
			}

			// We use htmlentities the user can insert text in some languages like German, Hindi, etc.
			// and the function json_encode broke the json or encode the characters.
			$fields[ $i ]->name  = htmlentities( $field->name );
			$fields[ $i ]->value = htmlentities( $field->value );
		}

		$params = array(
			'post_title'   => $title,
			'post_type'    => self::CP_FORM_ENTRY,
			'post_status'  => 'publish',
			'post_content' => json_encode( array( 'formId'   => $form->getId(),
			                                      'formData' => $fields
			), JSON_UNESCAPED_UNICODE )
		);

		wp_insert_post( $params );

		return $fields;
	}

	static public function registerCustomPost() {

		$labels = array(
			'name'               => _x( 'Leads', 'post type general name', 'brizy' ),
			'singular_name'      => _x( 'Lead', 'post type singular name', 'brizy' ),
			'menu_name'          => _x( 'Leads', 'admin menu', 'brizy' ),
			'name_admin_bar'     => _x( 'Lead', 'add new on admin bar', 'brizy' ),
			'add_new'            => _x( 'Add New', self::CP_FORM_ENTRY, 'brizy' ),
			'add_new_item'       => __( 'Add New Lead', 'brizy' ),
			'new_item'           => __( 'New Lead', 'brizy' ),
			'edit_item'          => __( 'Edit Lead', 'brizy' ),
			'view_item'          => __( 'View Lead', 'brizy' ),
			'all_items'          => __( 'Leads', 'brizy' ),
			'search_items'       => __( 'Search Leads', 'brizy' ),
			'parent_item_colon'  => __( 'Parent Leads:', 'brizy' ),
			'not_found'          => __( 'No Leads found.', 'brizy' ),
			'not_found_in_trash' => __( 'No Leads found in Trash.', 'brizy' )
		);

		register_post_type( self::CP_FORM_ENTRY,
			array(
				'labels'              => $labels,
				'public'              => false,
				'has_archive'         => false,
				'description'         => __( 'Leads', 'brizy' ),
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => false, //Brizy_Admin_Settings::menu_slug(),
				'query_var'           => false,
				'rewrite'             => array( 'slug' => self::CP_FORM_ENTRY ),
				//'map_meta_cap'        => true,
				'hierarchical'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'supports'            => array( 'title' ),
				'menu_position'       => 15,
				'capability_type'     => 'post',
				'capabilities'        => array(
					'create_posts' => 'do_not_allow', // false < WP 4.5, credit @Ewout
				),
				'map_meta_cap'        => true,
			)
		);
	}
}
