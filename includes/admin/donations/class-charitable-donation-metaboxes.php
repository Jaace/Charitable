<?php
/**
 * Sets up the donation metaboxes.
 *
 * @package   Charitable/Classes/Charitable_Donation_Metaboxes
 * @version   1.5.0
 * @author    Eric Daams
 * @copyright Copyright (c) 2017, Studio 164a
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Charitable_Donation_Metaboxes' ) ) :

	/**
	 * Charitable_Donation_Metaboxes class.
	 *
	 * @final
	 * @since 1.5.0
	 */
	final class Charitable_Donation_Metaboxes {

		/**
		 * The single instance of this class.
		 *
		 * @var Charitable_Donation_Metaboxes|null
		 */
		private static $instance = null;

		/**
		 * @var Charitable_Meta_Box_Helper $meta_box_helper
		 */
		private $meta_box_helper;

		/**
		 * Create object instance.
		 *
		 * @since 1.5.0
		 *
		 * @param Charitable_Meta_Box_Helper $helper The meta box helper class.
		 */
		public function __construct( Charitable_Meta_Box_Helper $helper ) {
			$this->meta_box_helper = $helper;
		}

		/**
		 * Returns and/or create the single instance of this class.
		 *
		 * @since  1.5.0
		 *
		 * @return Charitable_Donation_Metaboxes
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self(
					new Charitable_Meta_Box_Helper( 'charitable-donation' )
				);
			}

			return self::$instance;
		}

		/**
		 * Sets up the meta boxes to display on the donation admin page.
		 *
		 * @since  1.5.0
		 *
		 * @return void
		 */
		public function add_meta_boxes() {
			foreach ( $this->get_meta_boxes() as $meta_box_id => $meta_box ) {
				add_meta_box(
					$meta_box_id,
					$meta_box['title'],
					array( $this->meta_box_helper, 'metabox_display' ),
					Charitable::DONATION_POST_TYPE,
					$meta_box['context'],
					$meta_box['priority'],
					$meta_box
				);
			}
		}

		/**
		 * Remove default meta boxes.
		 *
		 * @since  1.5.0
		 *
		 * @global array $wp_meta_boxes Registered meta boxes in WP.
		 * @return void
		 */
		public function remove_meta_boxes() {
			global $wp_meta_boxes;

			$charitable_meta_boxes = $this->get_meta_boxes();

			foreach ( $wp_meta_boxes[ Charitable::DONATION_POST_TYPE ] as $context => $priorities ) {
				foreach ( $priorities as $priority => $meta_boxes ) {
					foreach ( $meta_boxes as $meta_box_id => $meta_box ) {
						if ( ! isset( $charitable_meta_boxes[ $meta_box_id ] ) ) {
							remove_meta_box( $meta_box_id, Charitable::DONATION_POST_TYPE, $context );
						}
					}
				}
			}
		}

		/**
		 * Returns an array of all meta boxes added to the donation post type screen.
		 *
		 * @since  1.5.0
		 *
		 * @return array
		 */
		private function get_meta_boxes() {
			$screen  = get_current_screen();

			if ( 'donation' == $screen->post_type && ( 'add' == $screen->action || isset( $_GET['show_form'] ) ) ) {
				$meta_boxes = $this->get_form_meta_box();
			} else {
				$meta_boxes = $this->get_view_meta_boxes();
			}

			/**
			 * Filter the meta boxes to be displayed on a donation overview page.
			 *
			 * @since 1.0.0
			 *
			 * @param array $meta_boxes The array of meta boxes and their details.
			 */
			return apply_filters( 'charitable_donation_meta_boxes', $meta_boxes );
		}

		/**
		 * Return the form meta box.
		 *
		 * @since  1.5.0
		 *
		 * @return array
		 */
		public function get_form_meta_box() {
			global $post;

			$form       = new Charitable_Admin_Donation_Form( charitable_get_donation( $post->ID ) );
			$meta_boxes = array(
				'donation-form' => array(
					'title'    => __( 'Donation Form', 'charitable' ),
					'context'  => 'normal',
					'priority' => 'high',
					'view'     => 'metaboxes/donation/donation-form',
					'form'     => $form,
				),
				'donation-form-meta' => array(
					'title'    => __( 'Additional Details', 'charitable' ),
					'context'  => 'side',
					'priority' => 'high',
					'view'     => 'metaboxes/donation/donation-form-meta',
					'form'     => $form,
				),
			);

			/**
			 * Filter the meta boxes to be displayed on a donation add/edit page.
			 *
			 * @since 1.0.0
			 *
			 * @param array $meta_boxes The array of meta boxes and their details.
			 */
			return apply_filters( 'charitable_donation_form_meta_boxes', $meta_boxes );
		}

		/**
		 * Return the view meta boxes.
		 *
		 * @since  1.5.0
		 *
		 * @return array
		 */
		public function get_view_meta_boxes() {
			global $post;

			$meta_boxes = array(
				'donation-overview' => array(
					'title'    => __( 'Donation Overview', 'charitable' ),
					'context'  => 'normal',
					'priority' => 'high',
					'view'     => 'metaboxes/donation/donation-overview',
				),
				'donation-actions' => array(
					'title'    => __( 'Donation Actions', 'charitable' ),
					'context'  => 'side',
					'priority' => 'high',
					'view'     => 'metaboxes/donation/donation-actions',
				),
				'donation-details' => array(
					'title'    => __( 'Donation Details', 'charitable' ),
					'context'  => 'side',
					'priority' => 'high',
					'view'     => 'metaboxes/donation/donation-details',
				),
				'donation-log' => array(
					'title'    => __( 'Donation Log', 'charitable' ),
					'context'  => 'normal',
					'priority' => 'low',
					'view'     => 'metaboxes/donation/donation-log',
				),
			);

			/* Get rid of the donation actions meta box if it doesn't apply to this donation. */
			if ( empty( charitable_get_donation_actions()->get_available_actions( $post->ID ) ) ) {
				unset( $meta_boxes['donation-actions'] );
			}

			/**
			 * Filter the meta boxes to be displayed on a donation overview page.
			 *
			 * @since 1.5.0
			 *
			 * @param array $meta_boxes The array of meta boxes and their details.
			 */
			return apply_filters( 'charitable_donation_view_meta_boxes', $meta_boxes );
		}

		/**
		 * Save meta for the donation.
		 *
		 * @since  1.5.0
		 *
		 * @param  int     $donation_id
		 * @param  WP_Post $post
		 * @return void
		 */
		public function save_donation( $donation_id, WP_Post $post ) {
			if ( ! $this->meta_box_helper->user_can_save( $donation_id ) ) {
				return;
			}

			if ( array_key_exists( 'charitable_action', $_POST ) && ! did_action( 'charitable_before_save_donation' ) ) {
				$form = new Charitable_Admin_Donation_Form( charitable_get_donation( $donation_id ) );

				if ( $form->validate_submission() ) {
					$this->disable_automatic_emails();

					charitable_create_donation( $form->get_donation_values() );

					$this->reenable_automatic_emails();
				}

				update_post_meta( $donation_id, '_donation_manually_edited', true );
			}

			/* Handle any fired actions */
			if ( ! empty( $_POST['charitable_donation_action'] ) ) {
				charitable_get_donation_actions()->do_action( sanitize_text_field( $_POST['charitable_donation_action'] ), $donation_id );
			}

			/* Hook for plugins to do something else with the posted data */
			do_action( 'charitable_donation_save', $donation_id, $post );
		}

		/**
		 * Change messages when a post type is updated.
		 *
		 * @param  array $messages
		 * @return array
		 */
		public function post_messages( $messages ) {
			global $post, $post_ID;

			$messages[ Charitable::DONATION_POST_TYPE ] = array(
				0 => '', // Unused. Messages start at index 1.
				1 => sprintf( __( 'Donation updated. <a href="%s">View Donation</a>', 'charitable' ), esc_url( get_permalink( $post_ID ) ) ),
				2 => __( 'Custom field updated.', 'charitable' ),
				3 => __( 'Custom field deleted.', 'charitable' ),
				4 => __( 'Donation updated.', 'charitable' ),
				5 => isset( $_GET['revision'] ) ? sprintf( __( 'Donation restored to revision from %s', 'charitable' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
				6 => sprintf( __( 'Donation published. <a href="%s">View Donation</a>', 'charitable' ), esc_url( get_permalink( $post_ID ) ) ),
				7 => __( 'Donation saved.', 'charitable' ),
				8 => sprintf(
					__( 'Donation submitted. <a target="_blank" href="%s">Preview Donation</a>', 'charitable' ),
					esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) )
				),
				9 => sprintf(
					__( 'Donation scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview Donation</a>', 'charitable' ),
					date_i18n( __( 'M j, Y @ G:i', 'charitable' ), strtotime( $post->post_date ) ),
					esc_url( get_permalink( $post_ID ) )
				),
				10 => sprintf(
					__( 'Donation draft updated. <a target="_blank" href="%s">Preview Donation</a>', 'charitable' ),
					esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) )
				),
				11 => __( 'Email resent.', 'charitable' ),
				12 => __( 'Email could not be resent.', 'charitable' ),
			);

			return $messages;
		}

		/**
		 * Disable automatic emails when a donation is created.
		 *
		 * @since  1.5.0
		 *
		 * @return void
		 */
		public function disable_automatic_emails() {
			$send_receipt = array_key_exists( 'send_donation_receipt', $_POST ) && 'on' == $_POST['send_donation_receipt'];

			remove_action( 'charitable_after_save_donation', array( 'Charitable_Email_New_Donation', 'send_with_donation_id' ) );

			if ( ! $send_receipt ) {
				remove_action( 'charitable_after_save_donation', array( 'Charitable_Email_Donation_Receipt', 'send_with_donation_id' ) );
			}

			foreach ( charitable_get_approval_statuses() as $status ) {
				remove_action( $status . '_' . Charitable::DONATION_POST_TYPE, array( 'Charitable_Email_New_Donation', 'send_with_donation_id' ) );

				if ( ! $send_receipt ) {
					remove_action( $status . '_' . Charitable::DONATION_POST_TYPE, array( 'Charitable_Email_Donation_Receipt', 'send_with_donation_id' ) );
				}
			}
		}

		/**
		 * Re-enable automatic emails after the donation has been saved.
		 *
		 * @since  1.5.0
		 *
		 * @return void
		 */
		public function reenable_automatic_emails() {
			$send_receipt = array_key_exists( 'send_donation_receipt', $_POST ) && 'on' == $_POST['send_donation_receipt'];

			add_action( 'charitable_after_save_donation', array( 'Charitable_Email_New_Donation', 'send_with_donation_id' ) );

			if ( ! $send_receipt ) {
				add_action( 'charitable_after_save_donation', array( 'Charitable_Email_Donation_Receipt', 'send_with_donation_id' ) );
			}

			foreach ( charitable_get_approval_statuses() as $status ) {
				add_action( $status . '_' . Charitable::DONATION_POST_TYPE, array( 'Charitable_Email_New_Donation', 'send_with_donation_id' ) );

				if ( ! $send_receipt ) {
					add_action( $status . '_' . Charitable::DONATION_POST_TYPE, array( 'Charitable_Email_Donation_Receipt', 'send_with_donation_id' ) );
				}
			}

		}
	}

endif;
