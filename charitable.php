<?php
/**
 * Plugin Name:       Charitable
 * Plugin URI:        https://www.wpcharitable.com
 * Description:       The WordPress fundraising alternative for non-profits, created to help non-profits raise money on their own website.
 * Version:           1.5.0-beta.1
 * Author:            WP Charitable
 * Author URI:        https://wpcharitable.com
 * Requires at least: 4.1
 * Tested up to:      4.8.2
 *
 * Text Domain:       charitable
 * Domain Path:       /i18n/languages/
 *
 * @package           Charitable
 * @author            Eric Daams
 * @copyright         Copyright (c) 2017, Studio 164a
 * @license           http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Charitable' ) ) :

    /**
     * Main Charitable class
     *
     * @class       Charitable
     * @version     1.5.0
     */
    class Charitable {

        /**
         * Plugin version.
         *
         * @var     string
         */
        const VERSION = '1.5.0-beta.1';

        /**
         * Version of database schema.
         *
         * @var     string A date in the format: YYYYMMDD
         */
        const DB_VERSION = '20150615';

        /**
         * Campaign post type.
         *
         * @var     string
         */
        const CAMPAIGN_POST_TYPE = 'campaign';

        /**
         * Donation post type.
         *
         * @var     string
         */
        const DONATION_POST_TYPE = 'donation';

        /**
         * Single instance of this class.
         *
         * @var     Charitable
         */
        private static $instance = null;

        /**
         * The absolute path to this plugin's directory.
         *
         * @var     string
         */
        private $directory_path;

        /**
         * The URL of this plugin's directory.
         *
         * @var     string
         */
        private $directory_url;

        /**
         * Directory path for the includes folder of the plugin.
         *
         * @var     string
         */
        private $includes_path;

        /**
         * Store of registered objects.
         *
         * @var     array
         */
        private $registry;

        /**
         * Donation factory instance.
         *
         * @var Charitable_Donation_Factory
         */
        public $donation_factory = null;

        /**
         * Endpoints registry object.
         *
         * @since 1.5.0
         *
         * @var   Charitable_Endpoints|null
         */
        public $endpoints = null;

        /**
         * Donation Fields.
         *
         * @var Charitable_Donation_Field_Registry
         */
        public $donation_fields;

        /**
         * Create class instance.
         *
         * @since 1.0.0
         */
        public function __construct() {
            $this->directory_path = plugin_dir_path( __FILE__ );
            $this->directory_url  = plugin_dir_url( __FILE__ );
            $this->includes_path  = $this->directory_path . 'includes/';

            $this->load_dependencies();

            register_activation_hook( __FILE__, array( $this, 'activate' ) );
            register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

            add_action( 'plugins_loaded', array( $this, 'start' ), 1 );
        }

        /**
         * Returns the original instance of this class.
         *
         * @since  1.0.0
         *
         * @return Charitable
         */
        public static function get_instance() {
            return self::$instance;
        }

        /**
         * Run the startup sequence.
         *
         * This is only ever executed once.
         *
         * @since  1.0.0
         *
         * @return void
         */
        public function start() {
            /* If we've already started (i.e. run this function once before), do not pass go. */
            if ( $this->started() ) {
                return;
            }

            /* Set static instance. */
            self::$instance = $this;

            /* Factory to create new donation instances. */
            $this->donation_factory = new Charitable_Donation_Factory();

            $this->maybe_start_ajax();

            $this->attach_hooks_and_filters();

            $this->maybe_start_admin();

            $this->maybe_start_public();

            Charitable_Addons::load( $this );
        }

        /**
         * Include necessary files.
         *
         * @since  1.0.0
         *
         * @return void
         */
        private function load_dependencies() {
            $includes_path = $this->get_path( 'includes' );

            /* Autoload Mapping */
            require_once( $includes_path . 'charitable-autoloader.php' );

            /* Core Functions */
            require_once( $includes_path . 'charitable-core-functions.php' );

            /* Campaigns */
            require_once( $includes_path . 'campaigns/charitable-campaign-functions.php' );
            require_once( $includes_path . 'campaigns/charitable-campaign-hooks.php' );

            /* Currency */
            require_once( $includes_path . 'currency/charitable-currency-functions.php' );

            /* Deprecated */
            require_once( $includes_path . 'deprecated/charitable-deprecated-functions.php' );
            require_once( $includes_path . 'deprecated/deprecated-class-charitable-templates.php' );

            /* Donations */
            require_once( $includes_path . 'donations/charitable-donation-hooks.php' );
            require_once( $includes_path . 'donations/charitable-donation-functions.php' );

            /* Emails */
            require_once( $includes_path . 'emails/charitable-email-hooks.php' );

            /* Endpoints */
            require_once( $includes_path . 'endpoints/charitable-endpoints-functions.php' );

            /* Public */
            require_once( $includes_path . 'public/charitable-template-helpers.php' );

            /* Shortcodes */
            require_once( $includes_path . 'shortcodes/charitable-shortcodes-hooks.php' );

            /* Users */
			require_once( $includes_path . 'users/charitable-user-functions.php' );

            /* User Management */
            require_once( $includes_path . 'user-management/charitable-user-management-hooks.php' );

            /* Utilities */
            require_once( $includes_path . 'utilities/charitable-utility-functions.php' );

            /**
             * We are registering this object only for backwards compatibility. It
             * will be removed in or after Charitable 1.3.
             *
             * @deprecated
             */
            $this->register_object( Charitable_Emails::get_instance() );
            $this->register_object( Charitable_Request::get_instance() );
            $this->register_object( Charitable_Gateways::get_instance() );
            $this->register_object( Charitable_i18n::get_instance() );
            $this->register_object( Charitable_Post_Types::get_instance() );
            $this->register_object( Charitable_Cron::get_instance() );
            $this->register_object( Charitable_Widgets::get_instance() );
            $this->register_object( Charitable_Licenses::get_instance() );
            $this->register_object( Charitable_User_Dashboard::get_instance() );
        }

        /**
         * Set up hook and filter callback functions.
         *
         * @since  1.0.0
         *
         * @return void
         */
        private function attach_hooks_and_filters() {
            add_action( 'wpmu_new_blog', array( $this, 'maybe_activate_charitable_on_new_site' ) );
            add_action( 'plugins_loaded', array( $this, 'charitable_install' ), 100 );
            add_action( 'plugins_loaded', array( $this, 'charitable_start' ), 100 );
            add_action( 'plugins_loaded', array( $this, 'setup_endpoints' ), 100 );
            add_action( 'plugins_loaded', array( $this, 'donation_fields' ), 100 );
            add_action( 'plugins_loaded', array( $this, 'load_plugin_compat_files' ) );
            add_action( 'setup_theme', array( 'Charitable_Customizer', 'start' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'maybe_start_qunit' ), 100 );

            /**
             * We do this on priority 20 so that any functionality that is loaded on init (such
             * as addons) has a chance to run before the event.
             */
            add_action( 'init', array( $this, 'do_charitable_actions' ), 20 );
        }

        /**
         * Checks whether we're in the admin area and if so, loads the admin-only functionality.
         *
         * @since  1.0.0
         *
         * @return void
         */
        private function maybe_start_admin() {
            if ( ! is_admin() ) {
                return;
            }

            require_once( $this->get_path( 'admin' ) . 'class-charitable-admin.php' );
            require_once( $this->get_path( 'admin' ) . 'charitable-admin-hooks.php' );

            /**
             * We are registering this object only for backwards compatibility. It
             * will be removed in or after Charitable 1.3.
             *
             * @deprecated
             */
            $this->register_object( Charitable_Admin::get_instance() );
        }

        /**
         * Checks whether we're on the public-facing side and if so, loads the public-facing functionality.
         *
         * @since  1.0.0
         *
         * @return void
         */
        private function maybe_start_public() {
            if ( is_admin() && ! $this->is_ajax() ) {
                return;
            }

            require_once( $this->get_path( 'public' ) . 'class-charitable-public.php' );

            /**
             * We are registering this object only for backwards compatibility. It
             * will be removed in or after Charitable 1.3.
             *
             * @deprecated
             */
            $this->register_object( Charitable_Public::get_instance() );
        }

        /**
         * Load the QUnit tests if ?qunit is appended to the request.
         *
         * @since  1.4.17
         *
         * @return boolean
         */
        public function maybe_start_qunit() {
            /* Skip out early if ?qunit isn't included in the request. */
            if ( ! array_key_exists( 'qunit', $_GET ) ) {
                return false;
            }

            /* The unit tests have to exist. */
            if ( ! file_exists( $this->get_path( 'directory' ) . 'tests/qunit/tests.js' ) ) {
                return false;
            }

            wp_register_script( 'qunit', 'https://code.jquery.com/qunit/qunit-2.3.3.js', array(), '2.3.3', true );
            /* Version: '20170615-15:44' */
            wp_register_script( 'qunit-tests', $this->get_path( 'directory', false ) . 'tests/qunit/tests.js', array( 'jquery-core', 'qunit' ), time(), true );
            wp_enqueue_script( 'qunit-tests' );

            wp_register_style( 'qunit', 'https://code.jquery.com/qunit/qunit-2.3.3.css', array(), '2.3.3', 'all' );
            wp_enqueue_style( 'qunit' );
        }

        /**
         * Checks whether the current request is an AJAX request.
         *
         * @since  1.5.0
         *
         * @return boolean
         */
        private function is_ajax() {
            return false !== ( defined( 'DOING_AJAX' ) && DOING_AJAX );
        }

        /**
         * Checks whether we're executing an AJAX hook and if so, loads some AJAX functionality.
         *
         * @since  1.0.0
         *
         * @return void
         */
        private function maybe_start_ajax() {
            if ( ! $this->is_ajax() ) {
                return;
            }

            require_once( $this->get_path( 'includes' ) . 'ajax/charitable-ajax-functions.php' );
            require_once( $this->get_path( 'includes' ) . 'ajax/charitable-ajax-hooks.php' );

            /**
             * We are registering this object only for backwards compatibility. It
             * will be removed in or after Charitable 1.3.
             *
             * @deprecated
             */
            $this->register_object( Charitable_Session::get_instance() );
        }

        /**
         * This method is fired after all plugins are loaded and simply fires the charitable_start hook.
         *
         * Extensions can use the charitable_start event to load their own functionality.
         *
         * @since  1.0.0
         *
         * @return void
         */
        public function charitable_start() {
            do_action( 'charitable_start', $this );
        }

        /**
         * Set up the default donation fields.
         *
         * @since  1.5.0
         *
         * @return Charitable_Donation_Field_Registry
         */
        public function donation_fields() {
            if ( ! isset( $this->donation_fields ) ) {
                /* Instantiate Registry and set default sections. */
                $this->donation_fields = new Charitable_Donation_Field_Registry();
                $this->donation_fields->set_default_section( 'user', 'public' );
                $this->donation_fields->set_default_section( 'user', 'admin' );

                $fields = include( $this->get_path( 'includes' ) . 'fields/default-fields/donation-fields.php' );

                foreach ( $fields as $key => $args ) {
                    $this->donation_fields->register_field( new Charitable_Donation_Field( $key, $args ) );
                }
            }

            return $this->donation_fields;
        }

        /**
         * Setup the Endpoints API.
         *
         * @since  1.5.0
         *
         * @return void
         */
        public function setup_endpoints() {
            $api = $this->get_endpoints();

            /**
             * The order in which we register endpoints is important, because
             * it determines the order in which the endpoints are checked to
             * find whether they are the current page.
             *
             * Any endpoint that builds on another endpoint should be registered
             * BEFORE the endpoint it builds on. In other words, move from
             * most specific to least specific.
             */
            $api->register( new Charitable_Campaign_Donation_Endpoint );
            $api->register( new Charitable_Campaign_Widget_Endpoint );
            $api->register( new Charitable_Campaign_Endpoint );
            $api->register( new Charitable_Donation_Cancellation_Endpoint );
            $api->register( new Charitable_Donation_Processing_Endpoint );
            $api->register( new Charitable_Donation_Receipt_Endpoint );
            $api->register( new Charitable_Email_Preview_Endpoint );
            $api->register( new Charitable_Email_Verification_Endpoint );
            $api->register( new Charitable_Registration_Endpoint );
            $api->register( new Charitable_Forgot_Password_Endpoint );
            $api->register( new Charitable_Reset_Password_Endpoint );
            $api->register( new Charitable_Login_Endpoint );
            $api->register( new Charitable_Profile_Endpoint );

        }

        /**
         * Return the Endpoints API object.
         *
         * @since  1.5.0
         *
         * @return Charitable_Endpoints
         */
        public function get_endpoints() {
            if ( is_null( $this->endpoints ) ) {
                $this->endpoints = new Charitable_Endpoints();
            }

            return $this->endpoints;
        }

        /**
         * Load plugin compatibility files on plugins_loaded hook.
         *
         * @since  1.4.18
         *
         * @return void
         */
        public function load_plugin_compat_files() {
            $includes_path = $this->get_path( 'includes' );

            /* Divi */
            if ( class_exists( 'ET_Builder_Plugin' ) || 'divi' == strtolower( wp_get_theme()->get_template() ) ) {
                require_once( $includes_path . 'compat/charitable-divi-compat-functions.php' );
            }

            /* WP Super Cache */
            if ( function_exists( 'wp_super_cache_text_domain' ) ) {
                require_once( $includes_path . 'compat/charitable-wp-super-cache-compat-functions.php' );
            }

            /* W3TC */
            if ( defined( 'W3TC' ) && W3TC ) {
                require_once( $includes_path . 'compat/charitable-w3tc-compat-functions.php' );
            }

            /* WP Rocket */
            if ( defined( 'WP_ROCKET_VERSION' )  ) {
                require_once( $includes_path . 'compat/charitable-wp-rocket-compat-functions.php' );
            }

            /* WP Fastest Cache */
            if ( class_exists( 'WpFastestCache' ) ) {
                require_once( $includes_path . 'compat/charitable-wp-fastest-cache-compat-functions.php' );
            }
        }

        /**
         * Fires off an action right after Charitable is installed, allowing other
         * plugins/themes to do something at this point.
         *
         * @since  1.0.1
         *
         * @return void
         */
        public function charitable_install() {
            $install = get_transient( 'charitable_install' );

            if ( ! $install ) {
                return;
            }

            require_once( $this->get_path( 'includes' ) . 'class-charitable-install.php' );

            Charitable_Install::finish_installing();

            do_action( 'charitable_install' );

            delete_transient( 'charitable_install' );
        }

        /**
         * Returns whether we are currently in the start phase of the plugin.
         *
         * @since  1.0.0
         *
         * @return boolean
         */
        public function is_start() {
            return current_filter() == 'charitable_start';
        }

        /**
         * Returns whether the plugin has already started.
         *
         * @since  1.0.0
         *
         * @return boolean
         */
        public function started() {
            return did_action( 'charitable_start' ) || current_filter() == 'charitable_start';
        }

        /**
         * Returns whether the plugin is being activated.
         *
         * @since  1.0.0
         *
         * @return boolean
         */
        public function is_activation() {
            return current_filter() == 'activate_charitable/charitable.php';
        }

        /**
         * Returns whether the plugin is being deactivated.
         *
         * @since  1.0.0
         *
         * @return boolean
         */
        public function is_deactivation() {
            return current_filter() == 'deactivate_charitable/charitable.php';
        }

        /**
         * Stores an object in the plugin's registry.
         *
         * @since  1.0.0
         *
         * @param  mixed $object Object to be registered.
         * @return void
         */
        public function register_object( $object ) {
            if ( ! is_object( $object ) ) {
                return;
            }

            $class = get_class( $object );

            $this->registry[ $class ] = $object;
        }

        /**
         * Returns a registered object.
         *
         * @since  1.0.0
         *
         * @param  string $class The type of class you want to retrieve.
         * @return mixed The object if it's registered. Otherwise false.
         */
        public function get_registered_object( $class ) {
            return isset( $this->registry[ $class ] ) ? $this->registry[ $class ] : false;
        }

        /**
         * Returns plugin paths.
         *
         * @since  1.0.0
         *
         * @param  string  $type          If empty, returns the path to the plugin.
         * @param  boolean $absolute_path If true, returns the file system path. If false, returns it as a URL.
         * @return string
         */
        public function get_path( $type = '', $absolute_path = true ) {
            $base = $absolute_path ? $this->directory_path : $this->directory_url;

            switch ( $type ) {
                case 'includes' :
                    $path = $base . 'includes/';
                    break;

                case 'admin' :
                    $path = $base . 'includes/admin/';
                    break;

                case 'public' :
                    $path = $base . 'includes/public/';
                    break;

                case 'assets' :
                    $path = $base . 'assets/';
                    break;

                case 'templates' :
                    $path = $base . 'templates/';
                    break;

                case 'directory' :
                    $path = $base;
                    break;

                default :
                    $path = __FILE__;

            }//end switch

            return $path;
        }

        /**
         * Returns the plugin's version number.
         *
         * @since  1.0.0
         *
         * @return string
         */
        public function get_version() {
            $version = self::VERSION;

            if ( false !== strpos( $version, '-' ) ) {
                $parts   = explode( '-', $version );
                $version = $parts[0];
            }

            return $version;
        }

        /**
         * Returns the public class.
         *
         * @since  1.0.0
         *
         * @return Charitable_Public
         */
        public function get_public() {
            return $this->get_registered_object( 'Charitable_Public' );
        }

        /**
         * Returns the admin class.
         *
         * @since  1.0.0
         *
         * @return Charitable_Admin
         */
        public function get_admin() {
            return $this->get_registered_object( 'Charitable_Admin' );
        }

        /**
         * Return the current request object.
         *
         * @since  1.0.0
         *
         * @return Charitable_Request
         */
        public function get_request() {
            $request = $this->get_registered_object( 'Charitable_Request' );

            if ( false === $request ) {
                $request = new Charitable_Request();
                $this->register_object( $request );
            }

            return $request;
        }

        /**
         * Returns the model for one of Charitable's database tables.
         *
         * @since  1.0.0
         *
         * @param  string $table The database table to retrieve.
         * @return Charitable_DB
         */
        public function get_db_table( $table ) {
            $tables = $this->get_tables();

            if ( ! isset( $tables[ $table ] ) ) {
                charitable_get_deprecated()->doing_it_wrong(
                    __METHOD__,
                    sprintf( 'Invalid table %s passed', $table ),
                    '1.0.0'
                );
                return null;
            }

            $class_name = $tables[ $table ];

            $db_table = $this->get_registered_object( $class_name );

            if ( false === $db_table ) {
                $db_table = new $class_name;
                $this->register_object( $db_table );
            }

            return $db_table;
        }

        /**
         * Return the filtered list of registered tables.
         *
         * @since  1.0.0
         *
         * @return string[]
         */
        private function get_tables() {
            /**
             * Filter the array of available Charitable table classes.
             *
             * @since 1.0.0
             *
             * @param array $tables List of tables as a key=>value array.
             */
            return apply_filters( 'charitable_db_tables', array(
                'campaign_donations' => 'Charitable_Campaign_Donations_DB',
                'donors'             => 'Charitable_Donors_DB',
            ) );
        }

        /**
         * Maybe activate Charitable when a new site is added in a multisite network.
         *
         * @since  1.4.6
         *
         * @param  int $blog_id The blog to activate Charitable on.
         * @return boolean
         */
        public function maybe_activate_charitable_on_new_site( $blog_id ) {
            if ( is_plugin_active_for_network( basename( $this->directory_path ) . '/charitable.php' ) ) {
                switch_to_blog( $blog_id );
                $this->activate( false );
                restore_current_blog();
            }
        }

        /**
         * Runs on plugin activation.
         *
         * @see    register_activation_hook
         *
         * @since  1.0.0
         *
         * @param  boolean $network_wide Whether to enable the plugin for all sites in the network
         *                               or just the current site. Multisite only. Default is false.
         * @return void
         */
        public function activate( $network_wide = false ) {
            require_once( $this->get_path( 'includes' ) . 'class-charitable-install.php' );

            if ( is_multisite() && $network_wide ) {
                global $wpdb;

                foreach ( $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" ) as $blog_id ) {
                    switch_to_blog( $blog_id );
                    new Charitable_Install();
                    restore_current_blog();
                }
            } else {
                new Charitable_Install();
            }
        }

        /**
         * Runs on plugin deactivation.
         *
         * @see    register_deactivation_hook
         *
         * @since  1.0.0
         *
         * @return void
         */
        public function deactivate() {
            require_once( $this->get_path( 'includes' ) . 'class-charitable-uninstall.php' );
            new Charitable_Uninstall();
        }

        /**
         * If a charitable_action event is triggered, delegate the event using do_action.
         *
         * @since  1.0.0
         *
         * @return void
         */
        public function do_charitable_actions() {
            if ( isset( $_REQUEST['charitable_action'] ) ) {

                $action = $_REQUEST['charitable_action'];

                do_action( 'charitable_' . $action );
            }
        }

        /**
         * Throw error on object clone.
         *
         * This class is specifically designed to be instantiated once. You can retrieve the instance using charitable()
         *
         * @since  1.0.0
         *
         * @return void
         */
        public function __clone() {
            charitable_get_deprecated()->doing_it_wrong(
                __FUNCTION__,
                __( 'Cheatin&#8217; huh?', 'charitable' ),
                '1.0.0'
            );
        }

        /**
         * Disable unserializing of the class.
         *
         * @since  1.0.0
         *
         * @return void
         */
        public function __wakeup() {
            charitable_get_deprecated()->doing_it_wrong(
                __FUNCTION__,
                __( 'Cheatin&#8217; huh?', 'charitable' ),
                '1.0.0'
            );
        }

        /**
         * DEPRECATED METHODS
         */

        /**
         * @deprecated
         */
        public function get_currency_helper() {
            charitable_get_deprecated()->deprecated_function( __METHOD__, '1.4.0', 'charitable_get_currency_helper' );
            return charitable_get_currency_helper();
        }

        /**
         * @deprecated
         */
        public function get_location_helper() {
            charitable_get_deprecated()->deprecated_function( __METHOD__, '1.2.0', 'charitable_get_location_helper' );
            return charitable_get_location_helper();
        }
    }

    $charitable = new Charitable();

endif;
