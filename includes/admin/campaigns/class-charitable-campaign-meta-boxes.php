<?php
/**
 * The class that defines how campaigns are managed on the admin side.
 *
 * @package     Charitable/Admin/Charitable_Campaign_Meta_Boxes
 * @version     1.5.0
 * @author      Eric Daams
 * @copyright   Copyright (c) 2017, Studio 164a
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Charitable_Campaign_Meta_Boxes' ) ) :

	/**
	 * Charitable_Campaign_Meta_Boxes class.
	 *
	 * @final
	 * @since 1.0.0
	 */
	final class Charitable_Campaign_Meta_Boxes {

		/**
		 * The single instance of this class.
		 *
		 * @var     Charitable_Campaign_Meta_Boxes|null
		 */
		private static $instance = null;

		/**
		 * Meta Box Helper class instance.
		 *
		 * @var     Charitable_Meta_Box_Helper
		 */
		private $meta_box_helper;

		/**
		 * Create object instance.
		 *
		 * @since  1.0.0
		 */
		private function __construct() {
			$this->meta_box_helper = new Charitable_Meta_Box_Helper( 'charitable-campaign' );
		}

		/**
		 * Returns and/or create the single instance of this class.
		 *
		 * @since  1.2.0
		 *
		 * @return Charitable_Campaign_Meta_Boxes
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Add meta boxes.
		 *
		 * @see     add_meta_boxes hook
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public function add_meta_boxes() {
			$meta_boxes = array(
				array(
					'id'            => 'campaign-description',
					'title'         => __( 'Campaign Description', 'charitable' ),
					'context'       => 'campaign-top',
					'priority'      => 'high',
					'view'          => 'metaboxes/campaign-description',
				),
				array(
					'id'            => 'campaign-goal',
					'title'         => __( 'Fundraising Goal ($)', 'charitable' ),
					'context'       => 'campaign-top',
					'priority'      => 'high',
					'view'          => 'metaboxes/campaign-goal',
					'description'   => __( 'Leave empty for campaigns without a fundraising goal.', 'charitable' ),
				),
				array(
					'id'            => 'campaign-end-date',
					'title'         => __( 'End Date', 'charitable' ),
					'context'       => 'campaign-top',
					'priority'      => 'high',
					'view'          => 'metaboxes/campaign-end-date',
					'description'   => __( 'Leave empty for ongoing campaigns.', 'charitable' ),
				),
				array(
					'id'            => 'campaign-donation-options',
					'title'         => __( 'Donation Options', 'charitable' ),
					'context'       => 'campaign-advanced',
					'priority'      => 'high',
					'view'          => 'metaboxes/campaign-donation-options',
				),
				array(
					'id'            => 'campaign-extended-description',
					'title'         => __( 'Extended Description', 'charitable' ),
					'context'       => 'campaign-advanced',
					'priority'      => 'high',
					'view'          => 'metaboxes/campaign-extended-description',
				),
				array(
					'id'            => 'campaign-creator',
					'title'         => __( 'Campaign Creator', 'charitable' ),
					'context'       => 'campaign-advanced',
					'priority'      => 'high',
					'view'          => 'metaboxes/campaign-creator',
				),
			);

			/**
			 * Filter campaign meta boxes.
			 *
			 * @since 1.0.0
			 *
			 * @param array $meta_boxes The meta boxes.
			 */
			$meta_boxes = apply_filters( 'charitable_campaign_meta_boxes', $meta_boxes );

			foreach ( $meta_boxes as $meta_box ) {
				add_meta_box(
					$meta_box['id'],
					$meta_box['title'],
					array( $this->meta_box_helper, 'metabox_display' ),
					Charitable::CAMPAIGN_POST_TYPE,
					$meta_box['context'],
					$meta_box['priority'],
					$meta_box
				);
			}
		}

		/**
		 * Display fields at the very top of the page.
		 *
		 * @since  1.0.0
		 *
		 * @param  WP_Post $post Current post.
		 * @return void
		 */
		public function campaign_form_top( $post ) {
			if ( Charitable::CAMPAIGN_POST_TYPE == $post->post_type ) {
				do_meta_boxes( Charitable::CAMPAIGN_POST_TYPE, 'campaign-top', $post );
			}
		}

		/**
		 * Wrap elements around the main editor.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public function wrap_editor() {
			add_filter( 'edit_form_after_title', array( $this, 'advanced_campaign_settings' ), 20 );
		}

		/**
		 * Wrap editor (and other advanced settings).
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public function editor_wrap_before() {
			charitable_admin_view( 'metaboxes/campaign-advanced-wrap-before', array( 'meta_boxes' => $this->get_advanced_meta_boxes() ) );
		}

		/**
		 * End wrapper around editor and other advanced settings.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public function editor_wrap_after() {
			charitable_admin_view( 'metaboxes/campaign-advanced-wrap-after' );
		}

		/**
		 * Display advanced campaign fields.
		 *
		 * @since  1.0.0
		 *
		 * @param  WP_Post $post Current post object.
		 * @return void
		 */
		public function advanced_campaign_settings( $post ) {
			charitable_admin_view( 'metaboxes/campaign-advanced-settings', array( 'meta_boxes' => $this->get_advanced_meta_boxes() ) );
		}

		/**
		 * Return flat array of meta boxes, ordered by priority.
		 *
		 * @since  1.0.0
		 *
		 * @global array $wp_meta_boxes
		 * @return array
		 */
		private function get_advanced_meta_boxes() {
			global $wp_meta_boxes;

			$meta_boxes = array();

			if ( ! isset( $wp_meta_boxes['campaign']['campaign-advanced'] ) ) {
				return $meta_boxes;
			}

			foreach ( array( 'high', 'sorted', 'core', 'default', 'low' ) as $priority ) {
				if ( isset( $wp_meta_boxes['campaign']['campaign-advanced'][ $priority ] ) ) {
					foreach ( (array) $wp_meta_boxes['campaign']['campaign-advanced'][ $priority ] as $box ) {
						$meta_boxes[] = $box;
					}
				}
			}

			return $meta_boxes;
		}

		/**
		 * Adds fields to the campaign donation options metabox.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public function campaign_donation_options_metabox() {
			/* Get the array of fields to be displayed within the campaign donations metabox. */
			$fields = array(
				'donations'     => array(
					'priority'  => 4,
					'view'      => 'metaboxes/campaign-donation-options/suggested-amounts',
					'label'     => __( 'Suggested Donation Amounts', 'charitable' ),
					'fields'    => apply_filters( 'charitable_campaign_donation_suggested_amounts_fields', array(
						'amount'    => array(
							'column_header' => __( 'Amount', 'charitable' ),
							'placeholder'   => __( 'Amount', 'charitable' ),
						),
						'description'   => array(
							'column_header' => __( 'Description (optional)', 'charitable' ),
							'placeholder'   => __( 'Optional Description', 'charitable' ),
						),
					) ),
				),
				'permit_custom' => array(
					'priority'  => 6,
					'view'      => 'metaboxes/campaign-donation-options/permit-custom',
					'label'     => __( 'Allow Custom Donations', 'charitable' ),
				),
			);

			$this->meta_box_helper->display_fields( apply_filters( 'charitable_campaign_donation_options_fields', $fields ) );
		}

		/**
		 * Save meta for the campaign.
		 *
		 * @since  1.0.0
		 *
		 * @param  int     $campaign_id The campaign ID.
		 * @param  WP_Post $post        Current Post object.
		 * @return void
		 */
		public function save_campaign( $campaign_id, WP_Post $post ) {
			if ( ! $this->meta_box_helper->user_can_save( $campaign_id ) ) {
				return;
			}

			/**
			 * Filter the set of meta keys to be saved.
			 *
			 * @since 1.0.0
			 *
			 * @param string[] $meta_keys An array of strings representing meta keys.
			 */
			$meta_keys = apply_filters( 'charitable_campaign_meta_keys', array(
				'_campaign_end_date',
				'_campaign_goal',
				'_campaign_suggested_donations',
				'_campaign_allow_custom_donations',
				'_campaign_description',
			) );

			$submitted = $_POST;

			foreach ( $meta_keys as $key ) {

				$value = isset( $submitted[ $key ] ) ? $submitted[ $key ] : false;

				/**
				 * This filter is deprecated. Use charitable_sanitize_campaign_meta{$key} instead.
				 *
				 * @deprecated 1.7.0
				 *
				 * @since 1.4.12 Deprecated.
				 */
				$value = apply_filters( 'charitable_sanitize_campaign_meta', $value, $key, $submitted, $campaign_id );

				/**
				 * Filter this meta value. The filter hook is
				 * charitable_sanitize_campaign_meta{$key}. For example,
				 * for _campaign_end_date the filter hook will be:
				 * charitable_sanitize_campaign_meta_campaign_end_date
				 *
				 * @since 1.4.12
				 *
				 * @param mixed $value       The submitted value, or false.
				 * @param array $submitted   All of the submitted values.
				 * @param int   $campaign_id The campaign ID.
				 */
				$value = apply_filters( 'charitable_sanitize_campaign_meta' . $key, $value, $submitted, $campaign_id );

				update_post_meta( $campaign_id, $key, $value );

			}//end foreach

			/**
			 * Do something with the posted data.
			 *
			 * @since 1.0.0
			 *
			 * @param WP_Post $post An instance of `WP_Post`.
			 */
			do_action( 'charitable_campaign_save', $post );
		}

		/**
		 * Set default post content when the extended description is left empty.
		 *
		 * @since  1.4.0
		 *
		 * @param  array $data    Submitted data.
		 * @return array
		 */
		public function set_default_post_content( $data ) {
			if ( Charitable::CAMPAIGN_POST_TYPE != $data['post_type'] ) {
				return $data;
			}

			if ( 0 === strlen( $data['post_content'] ) ) {
				$data['post_content'] = '<!-- Code is poetry -->';
			}

			return $data;
		}

		/**
		 * Sets the placeholder text of the campaign title field.
		 *
		 * @since  1.0.0
		 *
		 * @param  string  $placeholder Placeholder text.
		 * @param  WP_Post $post        Current Post object.
		 * @return string
		 */
		public function campaign_enter_title( $placeholder, WP_Post $post ) {
			if ( 'campaign' == $post->post_type ) {
				$placeholder = __( 'Enter campaign title', 'charitable' );
			}

			return $placeholder;
		}

		/**
		 * Change messages when a post type is updated.
		 *
		 * @global WP_Post $post
		 * @global int     $post_ID
		 * @param  array $messages Messages to display.
		 * @return array
		 */
		public function post_messages( $messages ) {
			global $post, $post_ID;

			$messages[ Charitable::CAMPAIGN_POST_TYPE ] = array(
				// Unused. Messages start at index 1.
				0 => '',
				1 => sprintf(
					__( 'Campaign updated. <a href="%s">View Campaign</a>', 'charitable' ),
					esc_url( get_permalink( $post_ID ) )
				),
				2 => __( 'Custom field updated.', 'charitable' ),
				3 => __( 'Custom field deleted.', 'charitable' ),
				4 => __( 'Campaign updated.', 'charitable' ),
				5 => isset( $_GET['revision'] )
					? sprintf( __( 'Campaign restored to revision from %s', 'charitable' ), wp_post_revision_title( (int) $_GET['revision'], false ) )
					: false,
				6 => sprintf(
					__( 'Campaign published. <a href="%s">View Campaign</a>', 'charitable' ),
					esc_url( get_permalink( $post_ID ) )
				),
				7 => __( 'Campaign saved.', 'charitable' ),
				8 => sprintf(
					__( 'Campaign submitted. <a target="_blank" href="%s">Preview Campaign</a>', 'charitable' ),
					esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) )
				),
				9 => sprintf(
					__( 'Campaign scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview Campaign</a>', 'charitable' ),
					date_i18n( __( 'M j, Y @ G:i', 'charitable' ),strtotime( $post->post_date ) ),
					esc_url( get_permalink( $post_ID ) )
				),
				10 => sprintf(
					__( 'Campaign draft updated. <a target="_blank" href="%s">Preview Campaign</a>', 'charitable' ),
					esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) )
				),
			);

			return $messages;
		}
	}

endif;
