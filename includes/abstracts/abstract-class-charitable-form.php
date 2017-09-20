<?php
/**
 * A base class to be extended by specific form classes.
 *
 * @package		Charitable/Classes/Charitable_Form
 * @version 	1.0.0
 * @author 		Eric Daams
 * @copyright 	Copyright (c) 2017, Studio 164a
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Charitable_Form' ) ) :

	/**
	 * Charitable_Form
	 *
	 * @abstract
	 * @since  1.0.0
	 */
	abstract class Charitable_Form {

		/**
		 * Temporary, unique ID of this form.
		 *
		 * @since 1.0.0
		 *
		 * @var   string
		 */
		protected $id;

		/**
		 * Nonce action.
		 *
		 * @since 1.0.0
		 *
		 * @var   string
		 */
		protected $nonce_action = 'charitable_form';

		/**
		 * Nonce name.
		 *
		 * @since 1.0.0
		 *
		 * @var   string
		 */
		protected $nonce_name = '_charitable_form_nonce';

		/**
		 * Form action.
		 *
		 * @since 1.0.0
		 *
		 * @var   string
		 */
		protected $form_action;

		/**
		 * Errors with the form submission.
		 *
		 * @since 1.0.0
		 *
		 * @var   array
		 */
		protected $errors = array();

		/**
		 * Submitted values.
		 *
		 * @since 1.0.0
		 *
		 * @var   array
		 */
		protected $submitted;

		/**
		 * Form View.
		 *
		 * @since 1.5.0
		 *
		 * @var   Charitable_Form_View_Interface
		 */
		protected $view;

		/**
		 * Set up callbacks for actions and filters.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		protected function attach_hooks_and_filters() {
			add_action( 'charitable_form_before_fields', array( $this, 'render_error_notices' ) );
			add_action( 'charitable_form_before_fields', array( $this, 'add_hidden_fields' ) );
		}

		/**
		 * Set the Form View object.
		 *
		 * @since  1.5.0
		 *
		 * @param  Charitable_Form_View_Interface $form_view An object implementing theCharitable_Form_View_Interface.
		 * @return void
		 */
		public function set_form_view( Charitable_Form_View_Interface $form_view ) {
			$this->view = $form_view;
		}

		/**
		 * Return the Form_View object for this form.
		 *
		 * @since  1.5.0
		 *
		 * @return Charitable_Form_View_Interface
		 */
		public function view() {
			if ( ! isset( $this->view ) ) {
				$this->view = new Charitable_Public_Form_View( $this );
			}

			return $this->view;
		}

		/**
		 * Compares the ID of the form passed by the action and the current form object to ensure they're the same.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $id Current form ID.
		 * @return boolean
		 */
		public function is_current_form( $id ) {
			return $id === $this->id;
		}

		/**
		 * Return the form fields.
		 *
		 * @since  1.5.0
		 *
		 * @return array
		 */
		public function get_fields() {
		}

		/**
		 * Return the form action.
		 *
		 * @since  1.3.1
		 *
		 * @return  string
		 */
		public function get_form_action() {
			return $this->form_action;
		}

		/**
		 * Return the form ID.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_form_identifier() {
			return $this->id;
		}

		/**
		 * Display error notices at the start of the form, if there are any.
		 *
		 * @since  1.0.0
		 *
		 * @param  Charitable_Form $form Form object.
		 * @return boolean Whether the notices were rendered.
		 */
		public function render_error_notices( $form ) {
			if ( ! $form->is_current_form( $this->id ) ) {
				return false;
			}

			$errors = charitable_get_notices()->get_errors();

			if ( ! empty( $errors ) ) {

				charitable_template( 'form-fields/errors.php', array(
					'errors' => $errors,
				) );

			}

			return true;
		}

		/**
		 * Adds hidden fields to the start of the donation form.
		 *
		 * @since  1.0.0
		 *
		 * @param  Charitable_Form $form The form object.
		 * @return boolean Whether the output is added.
		 */
		public function add_hidden_fields( $form ) {
			if ( ! $form->is_current_form( $this->id ) ) {
				return false;
			}

			$this->nonce_field();

			?>			
			<input type="hidden" name="charitable_action" value="<?php echo esc_attr( $this->form_action ) ?>" />
			<input type="hidden" name="charitable_form_id" value="<?php echo esc_attr( $this->id ) ?>" autocomplete="off" />
			<input type="text" name="<?php echo esc_attr( $this->id ) ?>" class="charitable-hidden" value="" autocomplete="off" />			
			<?php

			return true;
		}

		/**
		 * Output the nonce.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public function nonce_field() {
			wp_nonce_field( $this->nonce_action, $this->nonce_name );
		}

		/**
		 * Validate nonce data passed by the submitted form.
		 *
		 * @since  1.0.0
		 *
		 * @return boolean
		 */
		public function validate_nonce() {
			$submitted = $this->get_submitted_values();
			$validated = isset( $submitted[ $this->nonce_name ] ) && wp_verify_nonce( $submitted[ $this->nonce_name ], $this->nonce_action );

			if ( ! $validated ) {
				charitable_get_notices()->add_error( __( 'Unable to submit form. Please try again.', 'charitable' ) );
			}

			return $validated;
		}

		/**
		 * Make sure that the honeypot field is empty.
		 *
		 * @since  1.4.3
		 *
		 * @return boolean
		 */
		public function validate_honeypot() {
			$submitted = $this->get_submitted_values();

			if ( ! isset( $submitted['charitable_form_id'] ) ) {
				return true;
			}

			$form_id = $submitted['charitable_form_id'];

			return array_key_exists( $form_id, $submitted ) && 0 === strlen( $submitted[ $form_id ] );
		}

		/**
		 * Callback method used to filter out non-required fields.
		 *
		 * @since  1.0.0
		 *
		 * @param   array $field Field definition.
		 * @return array
		 */
		public function filter_required_fields( $field ) {
			return isset( $field['required'] ) && true == $field['required'];
		}

		/**
		 * Filters array returning just the required fields.
		 *
		 * @since  1.0.0
		 *
		 * @param  array[] $fields Array of form fields.
		 * @return array[]
		 */
		public function get_required_fields( $fields ) {
			$required_fields = array_filter( $fields, array( $this, 'filter_required_fields' ) );

			return $required_fields;
		}

		/**
		 * Check the passed fields to ensure that all required fields have been submitted.
		 *
		 * @since  1.0.0
		 *
		 * @param  array $fields    Array of form fields.
		 * @param  array $submitted Submitted values.
		 * @return boolean
		 */
		public function check_required_fields( $fields, $submitted = array() ) {
			if ( empty( $submitted ) ) {
				$submitted = $this->get_submitted_values();
			}

			$ret     = true;
			$missing = array();

			foreach ( $this->get_required_fields( $fields ) as $key => $field ) {

				/* We already have a value for this field. */
				if ( ! empty( $field['value'] ) ) {
					continue;
				}

				$exists = isset( $submitted[ $key ] );

				/* Verify that a value was provided. */
				if ( $exists ) {
					$value  = $submitted[ $key ];
					$exists = ! empty( $value ) || ( is_string( $value ) && strlen( $value ) );
				}

				/* If a value was not provided, check if it's in the $_FILES array. */
				if ( ! $exists ) {
					$exists = ( 'picture' == $field['type'] && isset( $_FILES[ $key ] ) && ! empty( $_FILES[ $key ]['name'] ) );
				}

				$exists = apply_filters( 'charitable_required_field_exists', $exists, $key, $field, $submitted, $this );

				if ( ! $exists ) {

					$label = isset( $field['label'] ) ? $field['label'] : $key;

					$missing[] = $label;
				}
			}//end foreach

			$missing = apply_filters( 'charitable_form_missing_fields', $missing, $this, $fields, $submitted );

			if ( count( $missing ) ) {

				$missing_fields = implode( '</li><li>', $missing );

				charitable_get_notices()->add_error(
					sprintf( '<p>%s</p><ul class="error-list"><li>%s</li></ul>',
						__( 'There were problems with your form submission. The following required fields were not filled out:', 'charitable' ),
						$missing_fields
					)
				);

				$ret = false;
			}

			return $ret;
		}

		/**
		 * Organize fields by data type, also filtering out unused parameters (we just need the key and the type).
		 *
		 * @since  1.0.0
		 *
		 * @param  string $key   Key of the field to sort.
		 * @param  array  $field Field definition.
		 * @param  array  $ret   Return value that we're carrying.
		 * @return  array[]
		 */
		public function sort_field_by_data_type( $key, $field, $ret ) {
			/* Filter out paragraphs and fields without a type. */
			if ( ! isset( $field['type'] ) || 'paragraph' == $field['type'] ) {
				return $ret;
			}

			/* Get the data type. Default to meta if no type is set. */
			if ( isset( $field['data_type'] ) ) {
				$ret[ $field['data_type'] ][ $key ] = $field['type'];
			} else {
				$ret[ $key ] = $field['type'];
			}

			return $ret;
		}

		/**
		 * Returns the submitted values.
		 *
		 * Use this method instead of accessing the raw $_POST array to take
		 * advantage of the filter on the values.
		 *
		 * @since  1.0.0
		 *
		 * @return  array
		 */
		public function get_submitted_values() {
			if ( ! isset( $this->submitted ) ) {
				$this->submitted = apply_filters( 'charitable_form_submitted_values', $_POST, $this );
			}

			return $this->submitted;
		}

		/**
		 * Returns the submitted value for a particular field.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $key The key to search for.
		 * @return  mixed Submitted value if set. NULL if value was not set.
		 */
		public function get_submitted_value( $key ) {
			$submitted = $this->get_submitted_values();
			return isset( $submitted[ $key ] ) ? $submitted[ $key ] : null;
		}

		/**
		 * Uploads a file and attaches it to the given post.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $file_key  Key of the file input.
		 * @param  int    $post_id   Post ID.
		 * @param   array  $post_data Overwrite some of the attachment. Optional.
		 * @param   array  $overrides Override the wp_handle_upload() behavior. Optional.
		 * @return int|WP_Error ID of the attachment or a WP_Error object on failure.
		 */
		public function upload_post_attachment( $file_key, $post_id, $post_data = array(), $overrides = array() ) {

			require_once( ABSPATH . 'wp-admin/includes/image.php' );
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			require_once( ABSPATH . 'wp-admin/includes/media.php' );

			$overrides = $this->get_file_overrides( $file_key, $overrides );

			return media_handle_upload( $file_key, $post_id, $post_data, $overrides );
		}

		/**
		 * Upload a file.
		 *
		 * @param  string $file_key  Reference to a single element of `$_FILES`. Call the
		 * 							  function once for each uploaded file.
		 * @param  array  $overrides Optional. An associative array of names=>values to
		 * 							  override default variables. Default false.
		 * @return  array|WP_Error On success, returns an associative array of file attributes.
		 *                         On failure, returns $overrides['upload_error_handler'](&$file, $message )
		 *                         or array( 'error'=>$message ).
		 * @since  1.0.0
		 */
		public function upload_file( $file_key, $overrides = array() ) {

			require_once( ABSPATH . 'wp-admin/includes/file.php' );

			$overrides = $this->get_file_overrides( $file_key, $overrides );
			$file      = wp_handle_upload( $_FILES[ $file_key ], $overrides );

			if ( isset( $file['error'] ) ) {
				return new WP_Error( 'upload_error', $file['error'] );
			}

			return $file;
		}

		/**
		 * Return overrides array for use with upload_file() and upload_post_attachment() methods.
		 *
		 * @param  string $file_key  Reference to a single element of `$_FILES`. Call the
		 * 							  function once for each uploaded file.
		 * @param  array  $overrides Optional. An associative array of names=>values to
		 * 							  override default variables. Default false.
		 * @since  1.0.0
		 *
		 * @return  array
		 */
		protected function get_file_overrides( $file_key, $overrides = array() ) {

			$allowed_mimes = array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'gif'          => 'image/gif',
				'png'          => 'image/png',
				'bmp'          => 'image/bmp',
				'tif|tiff'     => 'image/tiff',
				'ico'          => 'image/x-icon',
			);

			$defaults = array(
				'test_form' => false,
				'mimes'     => apply_filters( 'charitable_file_' . $file_key . '_allowed_mimes', $allowed_mimes ),
			);

			$overrides = wp_parse_args( $overrides, $defaults );

			return $overrides;

		}

		/**
		 * Render a form field.
		 *
		 * @since  1.0.0
		 *
		 * @param  array           $field     Field definition.
		 * @param  string          $key       Field key.
		 * @param  Charitable_Form $form      The form object.
		 * @param  int             $index     The current index.
		 * @param  string         $namespace Namespace for the form field's name attribute.
		 * @return boolean False if the field was not rendered. True otherwise.
		 */
		public function render_field( $field, $key, $form, $index = 0, $namespace = null ) {
			if ( ! $form->is_current_form( $this->id ) ) {
				return false;
			}

			return $form->view()->render_field( $field, $key, array(
				'index'     => $index,
				'namespace' => $namespace,
			) );
		}

		/**
		 * Return the template name used for this field.
		 *
		 * @since  1.0.0
		 *
		 * @param  array $field Field definition.
		 * @return string
		 */
		public function get_template_name( $field ) {
			return $form->view()->get_template_name( $field );			
		}

		/**
		 * Checks whether a template is valid.
		 *
		 * @deprecated
		 *
		 * @since  1.0.0
		 * @since  1.5.0 Deprecated.
		 *
		 * @param  mixed $template Template we're checking.
		 * @return boolean
		 */
		protected function is_valid_template( $template ) {
			charitable_get_deprecated()->deprecated_function(
				__METHOD__,
				'1.5.0',
				'Charitable_Public_Form_View::is_valid_template()'
			);

			return $this->view()->is_valid_template( $template );
		}

		/**
		 * Set how much the index should be incremented by.
		 *
		 * @deprecated
		 *
		 * @since  1.0.0
		 * @since  1.5.0 Deprecated.
		 *
		 * @param  int   $increment The number the index should be incremented by.
		 * @param  array $field     The field definition.
		 * @return int
		 */
		public function increment_index( $increment, $field ) {
			charitable_get_deprecated()->deprecated_function(
				__METHOD__,
				'1.5.0',
				'Charitable_Public_Form_View::increment_index()'
			);

            /**
             * Remove form's hooked filter.
             *
             * Before 1.5, forms used the filter to set the increment level. For
             * backwards-compatibility purposes, we still provide this method in the
             * form class, but it calls the Form View. This method shoud
             * default in the form abstract, but remove it when this function
             * is called directly.
             */
            remove_filter( 'charitable_form_field_increment', array( $this, 'increment_index' ), 10, 2 );

			return $this->view()->increment_index( $field );			
		}

		/**
		 * Whether the given field type can use the default field template.
		 *
		 * @deprecated
		 *
		 * @since  1.0.0
		 * @since  1.5.0
		 *
		 * @param  string $field_type Type of field.
		 * @return boolean
		 */
		protected function use_default_field_template( $field_type ) {
			charitable_get_deprecated()->deprecated_function(
				__METHOD__,
				'1.5.0',
				'Charitable_Public_Form_View::use_default_field_template()'
			);

			return $this->view()->use_default_field_template( $field_type );			
		}
	}

endif;