<?php
/*
Plugin Name: WPC Smart Upsell Funnel for WooCommerce
Plugin URI: https://wpclever.net/
Description: Suggest additional products and offer discounts to customers on the checkout page with flexible and smart conditions.
Version: 3.0.0
Author: WPClever
Author URI: https://wpclever.net
Text Domain: wpc-smart-upsell-funnel
Domain Path: /languages/
Requires Plugins: woocommerce
Requires at least: 4.0
Tested up to: 6.7
WC requires at least: 3.0
WC tested up to: 9.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) || exit;

! defined( 'WPCUF_VERSION' ) && define( 'WPCUF_VERSION', '3.0.0' );
! defined( 'WPCUF_LITE' ) && define( 'WPCUF_LITE', __FILE__ );
! defined( 'WPCUF_FILE' ) && define( 'WPCUF_FILE', __FILE__ );
! defined( 'WPCUF_URI' ) && define( 'WPCUF_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WPCUF_DIR' ) && define( 'WPCUF_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WPCUF_SUPPORT' ) && define( 'WPCUF_SUPPORT', 'https://wpclever.net/support?utm_source=support&utm_medium=wpcuf&utm_campaign=wporg' );
! defined( 'WPCUF_REVIEWS' ) && define( 'WPCUF_REVIEWS', 'https://wordpress.org/support/plugin/wpc-smart-upsell-funnel/reviews/?filter=5' );
! defined( 'WPCUF_CHANGELOG' ) && define( 'WPCUF_CHANGELOG', 'https://wordpress.org/plugins/wpc-smart-upsell-funnel/#developers' );
! defined( 'WPCUF_DISCUSSION' ) && define( 'WPCUF_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-smart-upsell-funnel' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WPCUF_URI );

include 'includes/dashboard/wpc-dashboard.php';
include 'includes/kit/wpc-kit.php';
include 'includes/hpos.php';

if ( ! function_exists( 'wpcuf_init' ) ) {
	add_action( 'plugins_loaded', 'wpcuf_init', 11 );

	function wpcuf_init() {
		// load text-domain
		load_plugin_textdomain( 'wpc-smart-upsell-funnel', false, basename( __DIR__ ) . '/languages/' );

		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
			add_action( 'admin_notices', 'wpcuf_notice_wc' );

			return null;
		}

		if ( ! class_exists( 'Wpcuf' ) && class_exists( 'WC_Product' ) ) {
			class Wpcuf {
				protected static $settings = [];
				public static $uf_rules = [];
				public static $ob_rules = [];
				protected static $instance = null;

				public static function instance() {
					if ( is_null( self::$instance ) ) {
						self::$instance = new self();
					}

					return self::$instance;
				}

				function __construct() {
					self::$settings = (array) get_option( 'wpcuf_settings', [] );
					self::$uf_rules = (array) get_option( 'wpcuf_uf', [] );
					self::$ob_rules = (array) get_option( 'wpcuf_ob', [] );

					// Init
					add_action( 'init', [ $this, 'init' ] );

					// Settings
					add_action( 'admin_init', [ $this, 'register_settings' ] );
					add_action( 'admin_menu', [ $this, 'admin_menu' ] );

					// Enqueue backend scripts
					add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

					// Add settings link
					add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
					add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

					// Backend AJAX
					add_action( 'wp_ajax_wpcuf_add_rule', [ $this, 'ajax_add_rule' ] );
					add_action( 'wp_ajax_wpcuf_add_combination', [ $this, 'ajax_add_combination' ] );
					add_action( 'wp_ajax_wpcuf_search_term', [ $this, 'ajax_search_term' ] );
					add_action( 'wp_ajax_wpcuf_import_export', [ $this, 'ajax_import_export' ] );
					add_action( 'wp_ajax_wpcuf_import_export_save', [ $this, 'ajax_import_export_save' ] );
					add_action( 'wp_ajax_wpcuf_add_time', [ $this, 'ajax_add_time' ] );

					// Enqueue frontend scripts
					add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

					// Frontend AJAX
					add_action( 'wc_ajax_wpcuf_add_to_cart', [ $this, 'ajax_add_to_cart' ] );
					add_action( 'wc_ajax_wpcuf_remove_from_cart', [ $this, 'ajax_remove_from_cart' ] );

					// Upsell funnel on cart
					switch ( self::get_setting( 'uf_position_cart', 'before_cart' ) ) {
						case 'before_cart':
							add_action( 'woocommerce_before_cart', [ $this, 'show_uf' ] );
							break;
						case 'after_cart_table':
							add_action( 'woocommerce_after_cart_table', [ $this, 'show_uf' ] );
							break;
						case 'before_totals':
							add_action( 'woocommerce_before_cart_totals', [ $this, 'show_uf' ] );
							break;
						case 'after_cart':
							add_action( 'woocommerce_after_cart', [ $this, 'show_uf' ] );
							break;
					}

					// Upsell funnel on checkout
					switch ( self::get_setting( 'uf_position_checkout', 'none' ) ) {
						case 'before_checkout_form':
							add_action( 'woocommerce_before_checkout_form', [ $this, 'show_uf' ] );
							break;
						case 'before_order_notes':
							add_action( 'woocommerce_before_order_notes', [ $this, 'show_uf' ] );
							break;
						case 'after_customer_details':
							add_action( 'woocommerce_checkout_after_customer_details', [ $this, 'show_uf' ] );
							break;
						case 'before_order_review':
							add_action( 'woocommerce_checkout_before_order_review_heading', [ $this, 'show_uf' ] );
							break;
						case 'after_checkout_form':
							add_action( 'woocommerce_after_checkout_form', [ $this, 'show_uf' ] );
							break;
					}

					// Order bump on checkout
					switch ( self::get_setting( 'ob_position_checkout', 'after_order_review' ) ) {
						case 'before_checkout_form':
							add_action( 'woocommerce_before_checkout_form', [ $this, 'show_ob' ] );
							break;
						case 'before_order_notes':
							add_action( 'woocommerce_before_order_notes', [ $this, 'show_ob' ] );
							break;
						case 'after_customer_details':
							add_action( 'woocommerce_checkout_after_customer_details', [ $this, 'show_ob' ] );
							break;
						case 'before_order_review':
							add_action( 'woocommerce_checkout_before_order_review_heading', [ $this, 'show_ob' ] );
							break;
						case 'after_order_review':
							add_action( 'woocommerce_checkout_order_review', [ $this, 'show_ob' ], 11 );
							break;
						case 'after_checkout_form':
							add_action( 'woocommerce_after_checkout_form', [ $this, 'show_ob' ] );
							break;
					}

					// Calculate totals
					add_action( 'woocommerce_add_to_cart', [ $this, 'update_cart_item' ] );
					add_action( 'woocommerce_cart_item_removed', [ $this, 'update_cart_item' ] );
					add_action( 'woocommerce_cart_item_restored', [ $this, 'update_cart_item' ] );
					add_action( 'woocommerce_check_cart_items', [ $this, 'update_cart_item' ] );
					add_action( 'woocommerce_before_mini_cart_contents', [ $this, 'before_mini_cart_contents' ], 9999 );
					add_action( 'woocommerce_before_calculate_totals', [ $this, 'before_calculate_totals' ], 9999 );
					add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'order_review_fragments' ] );

					// Order
					add_action( 'woocommerce_checkout_create_order_line_item', [
						$this,
						'create_order_line_item'
					], 10, 3 );
					add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'hidden_order_itemmeta' ] );
				}

				function init() {
					// shortcode
					add_shortcode( 'wpcuf_uf', [ $this, 'shortcode_uf' ] );
					add_shortcode( 'wpcuf_ob', [ $this, 'shortcode_ob' ] );
				}

				function register_settings() {
					// settings
					register_setting( 'wpcuf_settings', 'wpcuf_settings' );
					register_setting( 'wpcuf_ob', 'wpcuf_ob' );
					register_setting( 'wpcuf_uf', 'wpcuf_uf' );
				}

				function admin_enqueue_scripts( $hook ) {
					if ( apply_filters( 'wpcuf_ignore_backend_scripts', false, $hook ) ) {
						return null;
					}

					// wpcdpk
					wp_enqueue_style( 'wpcdpk', WPCUF_URI . 'assets/libs/wpcdpk/css/datepicker.css' );
					wp_enqueue_script( 'wpcdpk', WPCUF_URI . 'assets/libs/wpcdpk/js/datepicker.js', [ 'jquery' ], WPCUF_VERSION, true );

					// hint
					wp_enqueue_style( 'hint', WPCUF_URI . 'assets/css/hint.css' );

					// backend scripts
					wp_enqueue_style( 'wpcuf-backend', WPCUF_URI . 'assets/css/backend.css', [ 'woocommerce_admin_styles' ], WPCUF_VERSION );
					wp_enqueue_script( 'wpcuf-backend', WPCUF_URI . 'assets/js/backend.js', [
						'jquery',
						'jquery-ui-sortable',
						'jquery-ui-dialog',
						'wc-enhanced-select',
						'selectWoo',
					], WPCUF_VERSION, true );
					wp_localize_script( 'wpcuf-backend', 'wpcuf_vars', [
						'nonce' => wp_create_nonce( 'wpcuf-security' ),
					] );
				}

				function admin_menu() {
					add_submenu_page( 'wpclever', esc_html__( 'WPC Smart Upsell Funnel', 'wpc-smart-upsell-funnel' ), esc_html__( 'Smart Upsell Funnel', 'wpc-smart-upsell-funnel' ), 'manage_options', 'wpclever-wpcuf', [
						$this,
						'admin_menu_content'
					] );
				}

				function admin_menu_content() {
					$active_tab = sanitize_key( $_GET['tab'] ?? 'settings' );
					?>
                    <div class="wpclever_settings_page wrap">
                        <h1 class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Smart Upsell Funnel', 'wpc-smart-upsell-funnel' ) . ' ' . esc_html( WPCUF_VERSION ) . ' ' . ( defined( 'WPCUF_PREMIUM' ) ? '<span class="premium" style="display: none">' . esc_html__( 'Premium', 'wpc-smart-upsell-funnel' ) . '</span>' : '' ); ?></h1>
                        <div class="wpclever_settings_page_desc about-text">
                            <p>
								<?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-smart-upsell-funnel' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                                <br/>
                                <a href="<?php echo esc_url( WPCUF_REVIEWS ); ?>" target="_blank"><?php esc_html_e( 'Reviews', 'wpc-smart-upsell-funnel' ); ?></a> |
                                <a href="<?php echo esc_url( WPCUF_CHANGELOG ); ?>" target="_blank"><?php esc_html_e( 'Changelog', 'wpc-smart-upsell-funnel' ); ?></a> |
                                <a href="<?php echo esc_url( WPCUF_DISCUSSION ); ?>" target="_blank"><?php esc_html_e( 'Discussion', 'wpc-smart-upsell-funnel' ); ?></a>
                            </p>
                        </div>
						<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
                            <div class="notice notice-success is-dismissible">
                                <p><?php esc_html_e( 'Settings updated.', 'wpc-smart-upsell-funnel' ); ?></p>
                            </div>
						<?php } ?>
                        <div class="wpclever_settings_page_nav">
                            <h2 class="nav-tab-wrapper">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcuf&tab=settings' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'Settings', 'wpc-smart-upsell-funnel' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcuf&tab=uf' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'uf' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'Upsell Funnel', 'wpc-smart-upsell-funnel' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcuf&tab=ob' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'ob' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'Order Bump', 'wpc-smart-upsell-funnel' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcuf&tab=premium' ) ); ?>" class="<?php echo $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>" style="color: #c9356e">
									<?php esc_html_e( 'Premium Version', 'wpc-smart-upsell-funnel' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>" class="nav-tab">
									<?php esc_html_e( 'Essential Kit', 'wpc-smart-upsell-funnel' ); ?>
                                </a>
                            </h2>
                        </div>
                        <div class="wpclever_settings_page_content">
							<?php
							if ( $active_tab === 'settings' ) {
								$uf_position_cart       = self::get_setting( 'uf_position_cart', 'before_cart' );
								$uf_position_checkout   = self::get_setting( 'uf_position_checkout', 'none' );
								$uf_variations_selector = self::get_setting( 'uf_variations_selector', 'default' );
								$uf_heading             = self::get_setting( 'uf_heading', '' );
								$uf_limit               = self::get_setting( 'uf_limit', 5 );
								$uf_order               = self::get_setting( 'uf_order', 'default' );
								$uf_link                = self::get_setting( 'uf_link', 'yes_blank' );
								$uf_add_to_cart         = self::get_setting( 'uf_add_to_cart', '' );
								$ob_position_checkout   = self::get_setting( 'ob_position_checkout', 'after_order_review' );
								$ob_label               = self::get_setting( 'ob_label', '' );
								$ob_text                = self::get_setting( 'ob_text', '' );
								$ob_order               = self::get_setting( 'ob_order', 'default' );
								$ob_product_image       = self::get_setting( 'ob_product_image', 'yes' );
								$ob_product_name        = self::get_setting( 'ob_product_name', 'yes' );
								$ob_product_desc        = self::get_setting( 'ob_product_desc', 'yes' );
								$ob_link                = self::get_setting( 'ob_link', 'yes_blank' );
								?>
                                <form method="post" action="options.php">
                                    <table class="form-table">
                                        <tr class="heading">
                                            <th colspan="2">
												<?php esc_html_e( 'Upsell Funnel', 'wpc-smart-upsell-funnel' ); ?>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Position on cart page', 'wpc-smart-upsell-funnel' ); ?></th>
                                            <td>
                                                <label><select name="wpcuf_settings[uf_position_cart]">
                                                        <option value="before_cart" <?php selected( $uf_position_cart, 'before_cart' ); ?>><?php esc_html_e( 'Before cart', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="after_cart_table" <?php selected( $uf_position_cart, 'after_cart_table' ); ?>><?php esc_html_e( 'After cart table', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="before_totals" <?php selected( $uf_position_cart, 'before_totals' ); ?>><?php esc_html_e( 'Before totals', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="after_cart" <?php selected( $uf_position_cart, 'after_cart' ); ?>><?php esc_html_e( 'After cart', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="none" <?php selected( $uf_position_cart, 'none' ); ?>><?php esc_html_e( 'None (hide it)', 'wpc-smart-upsell-funnel' ); ?></option>
                                                    </select></label>
                                                <span class="description"><?php esc_html_e( 'You also can use shortcode [wpcuf_uf] to place it where you want.', 'wpc-smart-upsell-funnel' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Position on checkout page', 'wpc-smart-upsell-funnel' ); ?></th>
                                            <td>
                                                <label><select name="wpcuf_settings[uf_position_checkout]">
                                                        <option value="before_checkout_form" <?php selected( $uf_position_checkout, 'before_checkout_form' ); ?>><?php esc_html_e( 'Before checkout form', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="before_order_notes" <?php selected( $uf_position_checkout, 'before_order_notes' ); ?>><?php esc_html_e( 'Before order details', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="after_customer_details" <?php selected( $uf_position_checkout, 'after_customer_details' ); ?>><?php esc_html_e( 'After customer details', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="before_order_review" <?php selected( $uf_position_checkout, 'before_order_review' ); ?>><?php esc_html_e( 'Before order review', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="after_checkout_form" <?php selected( $uf_position_checkout, 'after_checkout_form' ); ?>><?php esc_html_e( 'After checkout form', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="none" <?php selected( $uf_position_checkout, 'none' ); ?>><?php esc_html_e( 'None (hide it)', 'wpc-smart-upsell-funnel' ); ?></option>
                                                    </select></label>
                                                <span class="description"><?php esc_html_e( 'You also can use shortcode [wpcuf_uf] to place it where you want.', 'wpc-smart-upsell-funnel' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Heading', 'wpc-smart-upsell-funnel' ); ?></th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcuf_settings[uf_heading]" value="<?php echo esc_attr( $uf_heading ); ?>" placeholder="<?php esc_attr_e( 'Hang on! Maybe you don\'t want to miss this deal:', 'wpc-smart-upsell-funnel' ); ?>"/>
                                                </label>
                                                <p class="description"><?php esc_html_e( 'Leave blank to use the default text and its equivalent translation in multiple languages.', 'wpc-smart-upsell-funnel' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Variations selector', 'wpc-smart-upsell-funnel' ); ?></th>
                                            <td>
                                                <label> <select name="wpcuf_settings[uf_variations_selector]">
                                                        <option value="default" <?php selected( $uf_variations_selector, 'default' ); ?>><?php esc_html_e( 'Default', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="woovr" <?php selected( $uf_variations_selector, 'woovr' ); ?>><?php esc_html_e( 'Use WPC Variations Radio Buttons', 'wpc-smart-upsell-funnel' ); ?></option>
                                                    </select></label>
                                                <p class="description">If you choose "Use WPC Variations Radio Buttons", please install
                                                    <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wpc-variations-radio-buttons&TB_iframe=true&width=800&height=550' ) ); ?>" class="thickbox" title="WPC Variations Radio Buttons">WPC Variations Radio Buttons</a> to make it work.
                                                </p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Products order', 'wpc-smart-upsell-funnel' ); ?></th>
                                            <td>
                                                <label><select name="wpcuf_settings[uf_order]">
                                                        <option value="default" <?php selected( $uf_order, 'default' ); ?>><?php esc_html_e( 'Default', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="price_asc" <?php selected( $uf_order, 'price_asc' ); ?>><?php esc_html_e( 'Original price: low to high', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="price_desc" <?php selected( $uf_order, 'price_desc' ); ?>><?php esc_html_e( 'Original price: high to low', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="new_price_asc" <?php selected( $uf_order, 'new_price_asc' ); ?>><?php esc_html_e( 'New price: low to high', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="new_price_desc" <?php selected( $uf_order, 'new_price_desc' ); ?>><?php esc_html_e( 'New price: high to low', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="saved_amount_asc" <?php selected( $uf_order, 'saved_amount_asc' ); ?>><?php esc_html_e( 'Saved amount: low to high', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="saved_amount_desc" <?php selected( $uf_order, 'saved_amount_desc' ); ?>><?php esc_html_e( 'Saved amount: high to low', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="saved_percentage_asc" <?php selected( $uf_order, 'saved_percentage_asc' ); ?>><?php esc_html_e( 'Saved percentage: low to high', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="saved_percentage_desc" <?php selected( $uf_order, 'saved_percentage_desc' ); ?>><?php esc_html_e( 'Saved percentage: high to low', 'wpc-smart-upsell-funnel' ); ?></option>
                                                    </select></label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Products limit', 'wpc-smart-upsell-funnel' ); ?></th>
                                            <td>
                                                <label><input type="number" min="0" step="1" max="20" name="wpcuf_settings[uf_limit]" value="<?php echo esc_attr( $uf_limit ); ?>"/></label>
                                                <span class="description"><?php esc_html_e( 'Maximum products will be shown.', 'wpc-smart-upsell-funnel' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Link to individual product', 'wpc-smart-upsell-funnel' ); ?></th>
                                            <td>
                                                <label><select name="wpcuf_settings[uf_link]">
                                                        <option value="yes" <?php selected( $uf_link, 'yes' ); ?>><?php esc_html_e( 'Yes, open in the same tab', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="yes_blank" <?php selected( $uf_link, 'yes_blank' ); ?>><?php esc_html_e( 'Yes, open in the new tab', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="yes_popup" <?php selected( $uf_link, 'yes_popup' ); ?>><?php esc_html_e( 'Yes, open quick view popup', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="no" <?php selected( $uf_link, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-smart-upsell-funnel' ); ?></option>
                                                    </select></label>
                                                <p class="description">If you choose "Open quick view popup", please install
                                                    <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woo-smart-quick-view&TB_iframe=true&width=800&height=550' ) ); ?>" class="thickbox" title="WPC Smart Quick View">WPC Smart Quick View</a> to make it work.
                                                </p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Add to cart', 'wpc-smart-upsell-funnel' ); ?></th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcuf_settings[uf_add_to_cart]" value="<?php echo esc_attr( $uf_add_to_cart ); ?>" placeholder="<?php esc_attr_e( 'Add to cart', 'wpc-smart-upsell-funnel' ); ?>"/>
                                                </label>
                                                <p class="description"><?php esc_html_e( 'Leave blank to use the default text and its equivalent translation in multiple languages.', 'wpc-smart-upsell-funnel' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th colspan="2">
												<?php esc_html_e( 'Order Bump', 'wpc-smart-upsell-funnel' ); ?>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Position on checkout page', 'wpc-smart-upsell-funnel' ); ?></th>
                                            <td>
                                                <label><select name="wpcuf_settings[ob_position_checkout]">
                                                        <option value="before_checkout_form" <?php selected( $ob_position_checkout, 'before_checkout_form' ); ?>><?php esc_html_e( 'Before checkout form', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="before_order_notes" <?php selected( $ob_position_checkout, 'before_order_notes' ); ?>><?php esc_html_e( 'Before order details', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="after_customer_details" <?php selected( $ob_position_checkout, 'after_customer_details' ); ?>><?php esc_html_e( 'After customer details', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="before_order_review" <?php selected( $ob_position_checkout, 'before_order_review' ); ?>><?php esc_html_e( 'Before order review', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="after_order_review" <?php selected( $ob_position_checkout, 'after_order_review' ); ?>><?php esc_html_e( 'After order review', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="after_checkout_form" <?php selected( $ob_position_checkout, 'after_checkout_form' ); ?>><?php esc_html_e( 'After checkout form', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="none" <?php selected( $ob_position_checkout, 'none' ); ?>><?php esc_html_e( 'None (hide it)', 'wpc-smart-upsell-funnel' ); ?></option>
                                                    </select></label>
                                                <span class="description"><?php esc_html_e( 'You also can use shortcode [wpcuf_ob] to place it where you want.', 'wpc-smart-upsell-funnel' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Checkbox label', 'wpc-smart-upsell-funnel' ); ?></th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcuf_settings[ob_label]" value="<?php echo esc_attr( $ob_label ); ?>" placeholder="<?php esc_attr_e( 'Yes, add it in!', 'wpc-smart-upsell-funnel' ); ?>"/>
                                                </label>
                                                <p class="description"><?php esc_html_e( 'Leave blank to use the default text and its equivalent translation in multiple languages.', 'wpc-smart-upsell-funnel' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Promotion text', 'wpc-smart-upsell-funnel' ); ?></th>
                                            <td>
                                                <label>
                                                    <textarea class="text large-text" name="wpcuf_settings[ob_text]" placeholder="<?php esc_attr_e( 'We\'ve got something special for you!', 'wpc-smart-upsell-funnel' ); ?>"><?php echo esc_textarea( $ob_text ); ?></textarea>
                                                </label>
                                                <p class="description"><?php esc_html_e( 'Leave blank to use the default text and its equivalent translation in multiple languages.', 'wpc-smart-upsell-funnel' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Product selection', 'wpc-smart-upsell-funnel' ); ?></th>
                                            <td>
                                                <label><select name="wpcuf_settings[ob_order]">
                                                        <option value="default" <?php selected( $ob_order, 'default' ); ?>><?php esc_html_e( 'Default', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="price_asc" <?php selected( $ob_order, 'price_asc' ); ?>><?php esc_html_e( 'Original price: lowest', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="price_desc" <?php selected( $ob_order, 'price_desc' ); ?>><?php esc_html_e( 'Original price: highest', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="new_price_asc" <?php selected( $ob_order, 'new_price_asc' ); ?>><?php esc_html_e( 'New price: lowest', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="new_price_desc" <?php selected( $ob_order, 'new_price_desc' ); ?>><?php esc_html_e( 'New price: highest', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="saved_amount_asc" <?php selected( $ob_order, 'saved_amount_asc' ); ?>><?php esc_html_e( 'Saved amount: lowest', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="saved_amount_desc" <?php selected( $ob_order, 'saved_amount_desc' ); ?>><?php esc_html_e( 'Saved amount: highest', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="saved_percentage_asc" <?php selected( $ob_order, 'saved_percentage_asc' ); ?>><?php esc_html_e( 'Saved percentage: lowest', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="saved_percentage_desc" <?php selected( $ob_order, 'saved_percentage_desc' ); ?>><?php esc_html_e( 'Saved percentage: highest', 'wpc-smart-upsell-funnel' ); ?></option>
                                                    </select></label>
                                                <span class="description"><?php esc_html_e( 'Criteria for picking a single product.', 'wpc-smart-upsell-funnel' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Show product image', 'wpc-smart-upsell-funnel' ); ?></th>
                                            <td>
                                                <label><select name="wpcuf_settings[ob_product_image]">
                                                        <option value="yes" <?php selected( $ob_product_image, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="no" <?php selected( $ob_product_image, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-smart-upsell-funnel' ); ?></option>
                                                    </select></label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Show product name', 'wpc-smart-upsell-funnel' ); ?></th>
                                            <td>
                                                <label><select name="wpcuf_settings[ob_product_name]">
                                                        <option value="yes" <?php selected( $ob_product_name, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="no" <?php selected( $ob_product_name, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-smart-upsell-funnel' ); ?></option>
                                                    </select></label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Show product description', 'wpc-smart-upsell-funnel' ); ?></th>
                                            <td>
                                                <label><select name="wpcuf_settings[ob_product_desc]">
                                                        <option value="yes" <?php selected( $ob_product_desc, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="no" <?php selected( $ob_product_desc, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-smart-upsell-funnel' ); ?></option>
                                                    </select></label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Link to individual product', 'wpc-smart-upsell-funnel' ); ?></th>
                                            <td>
                                                <label><select name="wpcuf_settings[ob_link]">
                                                        <option value="yes_blank" <?php selected( $ob_link, 'yes_blank' ); ?>><?php esc_html_e( 'Yes, open in the new tab', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="yes_popup" <?php selected( $ob_link, 'yes_popup' ); ?>><?php esc_html_e( 'Yes, open quick view popup', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="no" <?php selected( $ob_link, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-smart-upsell-funnel' ); ?></option>
                                                    </select></label>
                                                <p class="description">If you choose "Open quick view popup", please install
                                                    <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woo-smart-quick-view&TB_iframe=true&width=800&height=550' ) ); ?>" class="thickbox" title="WPC Smart Quick View">WPC Smart Quick View</a> to make it work.
                                                </p>
                                            </td>
                                        </tr>
                                        <tr class="submit">
                                            <th colspan="2">
												<?php settings_fields( 'wpcuf_settings' ); ?><?php submit_button(); ?>
                                            </th>
                                        </tr>
                                    </table>
                                </form>
								<?php
							} elseif ( $active_tab === 'uf' ) {
								self::rules( 'wpcuf_uf', self::$uf_rules );
							} elseif ( $active_tab === 'ob' ) {
								self::rules( 'wpcuf_ob', self::$ob_rules );
							} elseif ( $active_tab === 'premium' ) {
								?>
                                <div class="wpclever_settings_page_content_text">
                                    <p>Get the Premium Version just $29!
                                        <a href="https://wpclever.net/downloads/wpc-smart-upsell-funnel?utm_source=pro&utm_medium=wpcuf&utm_campaign=wporg" target="_blank">https://wpclever.net/downloads/wpc-smart-upsell-funnel</a>
                                    </p>
                                    <p><strong>Extra features for Premium Version:</strong></p>
                                    <ul style="margin-bottom: 0">
                                        <li>- Using time conditions.</li>
                                        <li>- Using user roles conditions.</li>
                                        <li>- Get lifetime update & premium support.</li>
                                    </ul>
                                </div>
								<?php
							}
							?>
                        </div><!-- /.wpclever_settings_page_content -->
                        <div class="wpclever_settings_page_suggestion">
                            <div class="wpclever_settings_page_suggestion_label">
                                <span class="dashicons dashicons-yes-alt"></span> Suggestion
                            </div>
                            <div class="wpclever_settings_page_suggestion_content">
                                <div>
                                    To display custom engaging real-time messages on any wished positions, please install
                                    <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC Smart Messages</a> plugin. It's free!
                                </div>
                                <div>
                                    Wanna save your precious time working on variations? Try our brand-new free plugin
                                    <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC Variation Bulk Editor</a> and
                                    <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC Variation Duplicator</a>.
                                </div>
                            </div>
                        </div>
                    </div>
					<?php
				}

				function get_products( $rule, $product = null ) {
					if ( ! empty( $rule['get'] ) ) {
						if ( is_a( $product, 'WC_Product' ) ) {
							$product_id = $product->get_id();
						} elseif ( is_int( $product ) ) {
							$product_id = absint( $product );
						} else {
							$product_id = 0;
						}

						$limit   = absint( $rule['get_limit'] ?? 3 );
						$orderby = $rule['get_orderby'] ?? 'default';
						$order   = $rule['get_order'] ?? 'default';

						switch ( $rule['get'] ) {
							case 'all':
								return wc_get_products( [
									'status'  => 'publish',
									'limit'   => $limit,
									'orderby' => $orderby,
									'order'   => $order,
									'exclude' => [ $product_id ],
									'return'  => 'ids',
								] );
							case 'products':
								if ( ! empty( $rule['get_products'] ) && is_array( $rule['get_products'] ) ) {
									return array_diff( $rule['get_products'], [ $product_id ] );
								} else {
									return [];
								}
							case 'combination':
								if ( ! empty( $rule['get_combination'] ) && is_array( $rule['get_combination'] ) ) {
									$tax_query  = [];
									$meta_query = [];
									$terms_arr  = [];

									foreach ( $rule['get_combination'] as $combination ) {
										// term
										if ( ! empty( $combination['apply'] ) && ! empty( $combination['compare'] ) && ! empty( $combination['terms'] ) && is_array( $combination['terms'] ) ) {
											$tax_query[] = [
												'taxonomy' => $combination['apply'],
												'field'    => 'slug',
												'terms'    => $combination['terms'],
												'operator' => $combination['compare'] === 'is' ? 'IN' : 'NOT IN'
											];
										}

										// price
										if ( ! empty( $combination['apply'] ) && $combination['apply'] === 'price' && ! empty( $combination['number_compare'] ) && isset( $combination['number_value'] ) && $combination['number_value'] !== '' ) {
											switch ( $combination['number_compare'] ) {
												case 'equal':
													$compare = '=';
													break;
												case 'not_equal':
													$compare = '!=';
													break;
												case 'greater':
													$compare = '>';
													break;
												case 'greater_equal':
													$compare = '>=';
													break;
												case 'less':
													$compare = '<';
													break;
												case 'less_equal':
													$compare = '<=';
													break;
												default:
													$compare = '=';
											}

											$meta_query[] = [
												'key'     => '_price',
												'value'   => (float) $combination['number_value'],
												'compare' => $compare,
												'type'    => 'NUMERIC'
											];
										}
									}

									$args = [
										'post_type'      => 'product',
										'post_status'    => 'publish',
										'posts_per_page' => $limit,
										'orderby'        => $orderby,
										'order'          => $order,
										'tax_query'      => $tax_query,
										'meta_query'     => $meta_query,
										'post__not_in'   => [ $product_id ],
										'fields'         => 'ids'
									];

									$ids = new WP_Query( $args );

									return $ids->posts;
								} else {
									return [];
								}
							default:
								if ( ! empty( $rule['get_terms'] ) && is_array( $rule['get_terms'] ) ) {
									$args = [
										'post_type'      => 'product',
										'post_status'    => 'publish',
										'posts_per_page' => $limit,
										'orderby'        => $orderby,
										'order'          => $order,
										'tax_query'      => [
											[
												'taxonomy' => $rule['get'],
												'field'    => 'slug',
												'terms'    => $rule['get_terms'],
											],
										],
										'post__not_in'   => [ $product_id ],
										'fields'         => 'ids'
									];

									$ids = new WP_Query( $args );

									return $ids->posts;
								} else {
									return [];
								}
						}
					}

					return [];
				}

				function rules( $name = 'wpcuf_uf', $rules = [] ) {
					?>
                    <form method="post" action="options.php">
                        <table class="form-table">
                            <tr>
                                <td>
                                    <div class="wpcuf_rules">
										<?php
										if ( is_array( $rules ) && ( count( $rules ) > 0 ) ) {
											foreach ( $rules as $key => $rule ) {
												self::rule( $name, $key, $rule, false );
											}
										} else {
											self::rule( $name, '', [], true );
										}
										?>
                                    </div>
                                    <div class="wpcuf_add_rule">
                                        <div>
                                            <a href="#" class="wpcuf_new_rule button" data-name="<?php echo esc_attr( $name ); ?>">
												<?php esc_html_e( '+ Add rule', 'wpc-smart-upsell-funnel' ); ?>
                                            </a> <a href="#" class="wpcuf_expand_all">
												<?php esc_html_e( 'Expand All', 'wpc-smart-upsell-funnel' ); ?>
                                            </a> <a href="#" class="wpcuf_collapse_all">
												<?php esc_html_e( 'Collapse All', 'wpc-smart-upsell-funnel' ); ?>
                                            </a>
                                        </div>
                                        <div>
                                            <a href="#" class="wpcuf_import_export hint--left" aria-label="<?php esc_attr_e( 'Remember to save current rules before exporting to get the latest version.', 'wpc-smart-upsell-funnel' ); ?>" data-name="<?php echo esc_attr( $name ); ?>" style="color: #999999"><?php esc_html_e( 'Import/Export', 'wpc-smart-upsell-funnel' ); ?></a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr class="submit">
                                <th colspan="2">
									<?php settings_fields( $name ); ?><?php submit_button(); ?>
                                </th>
                            </tr>
                        </table>
                    </form>
					<?php
				}

				function ajax_import_export() {
					if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcuf-security' ) ) {
						die( 'Permissions check failed!' );
					}

					$rules = [];
					$name  = sanitize_key( $_POST['name'] ?? 'wpcuf_uf' );

					if ( $name === 'wpcuf_uf' ) {
						$rules = self::$uf_rules;
					}

					if ( $name === 'wpcuf_ob' ) {
						$rules = self::$ob_rules;
					}

					echo '<textarea class="wpcuf_import_export_data" style="width: 100%; height: 200px">' . esc_textarea( ( ! empty( $rules ) ? wp_json_encode( $rules ) : '' ) ) . '</textarea>';
					echo '<div style="display: flex; align-items: center; margin-top: 10px;"><button class="button button-primary wpcuf_import_export_save" data-name="' . esc_attr( $name ) . '">' . esc_html__( 'Update', 'wpc-smart-upsell-funnel' ) . '</button>';
					echo '<span style="color: #ff4f3b; font-size: 10px; margin-left: 10px;">' . esc_html__( '* All current rules will be replaced after pressing Update!', 'wpc-smart-upsell-funnel' ) . '</span>';
					echo '</div>';

					wp_die();
				}

				function ajax_import_export_save() {
					if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcuf-security' ) ) {
						die( 'Permissions check failed!' );
					}

					$rules = sanitize_textarea_field( trim( $_POST['rules'] ) );
					$name  = sanitize_key( $_POST['name'] ?? 'wpcuf_uf' );

					if ( ! empty( $rules ) ) {
						$rules = json_decode( stripcslashes( $rules ), true );

						if ( $rules !== null ) {
							update_option( $name, $rules );
						}

						echo 'Done!';
					}

					wp_die();
				}

				function ajax_add_to_cart() {
					if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcuf-security' ) ) {
						die( 'Permissions check failed!' );
					}

					if ( ! empty( $_POST['product_id'] ) ) {
						$key          = sanitize_text_field( $_POST['key'] ?? '' );
						$type         = sanitize_text_field( $_POST['type'] ?? 'uf' );
						$product_id   = sanitize_text_field( $_POST['product_id'] );
						$variation_id = sanitize_text_field( $_POST['variation_id'] ?? 0 );
						$variation    = self::sanitize_array( isset( $_POST['variation'] ) && is_array( $_POST['variation'] ) ? $_POST['variation'] : [] );
						$data         = [
							'wpcuf_' . $type => $key,
						];

						if ( false !== WC()->cart->add_to_cart( $product_id, 1, $variation_id, $variation, $data ) ) {
							do_action( 'woocommerce_ajax_added_to_cart', $product_id );

							if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
								wc_add_to_cart_message( [ $product_id => 1 ], true );
							}

							WC_AJAX::get_refreshed_fragments();
						} else {
							$data = [
								'error'       => true,
								'product_url' => apply_filters( 'woocommerce_cart_redirect_after_error', get_permalink( $product_id ), $product_id ),
							];

							wp_send_json( $data );
						}
					}

					wp_die();
				}

				function ajax_remove_from_cart() {
					if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcuf-security' ) ) {
						die( 'Permissions check failed!' );
					}

					if ( ! empty( $_POST['item_key'] ) ) {
						$item_key = sanitize_text_field( $_POST['item_key'] );

						WC()->cart->remove_cart_item( $item_key );
					}

					wp_die();
				}

				function rule( $name = 'wpcuf_uf', $key = '', $rule = [], $active = false ) {
					$rule = array_merge( [
						'apply'             => 'none',
						'apply_number'      => [ 'compare' => 'equal', 'value' => '0' ],
						'apply_products'    => [],
						'apply_terms'       => [],
						'apply_combination' => [],
						'get'               => 'all',
						'get_number'        => [ 'compare' => 'equal', 'value' => '0' ],
						'get_products'      => [],
						'get_terms'         => [],
						'get_combination'   => [],
						'get_limit'         => 3,
						'get_orderby'       => 'default',
						'get_order'         => 'default',
						'name'              => '',
						'price'             => [ 'type' => 'percentage', 'val' => '100' ],
						'timer'             => [],
						'roles'             => [],
					], (array) $rule );

					if ( empty( $key ) || is_numeric( $key ) ) {
						$key = self::generate_key();
					}

					$apply             = $rule['apply'] ?? 'none';
					$apply_number      = (array) ( $rule['apply_number'] ?? [] );
					$apply_products    = (array) ( $rule['apply_products'] ?? [] );
					$apply_terms       = (array) ( $rule['apply_terms'] ?? [] );
					$apply_combination = (array) ( $rule['apply_combination'] ?? [] );
					$get               = $rule['get'] ?? 'all';
					$get_number        = (array) ( $rule['get_number'] ?? [] );
					$get_products      = (array) ( $rule['get_products'] ?? [] );
					$get_terms         = (array) ( $rule['get_terms'] ?? [] );
					$get_combination   = (array) ( $rule['get_combination'] ?? [] );
					$get_limit         = absint( $rule['get_limit'] ?? 3 );
					$get_orderby       = $rule['get_orderby'] ?? 'default';
					$get_order         = $rule['get_order'] ?? 'default';
					$rule_name         = $rule['name'] ?? '';
					$rule_price_type   = $rule['price']['type'] ?? 'percentage';
					$rule_price_val    = $rule['price']['val'] ?? '100';
					$rule_timer        = $rule['timer'] ?? [];
					$rule_roles        = $rule['roles'] ?? [];
					?>
                    <div class="<?php echo esc_attr( $active ? 'wpcuf_rule active' : 'wpcuf_rule' ); ?>" data-key="<?php echo esc_attr( $key ); ?>">
                        <div class="wpcuf_rule_heading">
                            <span class="wpcuf_rule_move"></span>
                            <span class="wpcuf_rule_label"><span class="wpcuf_rule_name"><?php echo esc_html( $rule_name ); ?></span> <span class="wpcuf_rule_apply_get"><?php echo esc_html( $apply . ' | ' . $get ); ?></span></span>
                            <a href="#" class="wpcuf_rule_duplicate" data-name="<?php echo esc_attr( $name ); ?>"><?php esc_html_e( 'duplicate', 'wpc-smart-upsell-funnel' ); ?></a>
                            <a href="#" class="wpcuf_rule_remove"><?php esc_html_e( 'remove', 'wpc-smart-upsell-funnel' ); ?></a>
                        </div>
                        <div class="wpcuf_rule_content">
                            <div class="wpcuf_tr">
                                <div class="wpcuf_th">
									<?php esc_html_e( 'Name', 'wpc-smart-upsell-funnel' ); ?>
                                </div>
                                <div class="wpcuf_td">
                                    <input type="text" class="regular-text wpcuf_rule_name_val" name="<?php echo esc_attr( $name . '[' . $key . '][name]' ); ?>" value="<?php echo esc_attr( $rule_name ); ?>"/>
                                    <span class="description"><?php esc_html_e( 'For management only.', 'wpc-smart-upsell-funnel' ); ?></span>
                                </div>
                            </div>
                            <div class="wpcuf_tr">
                                <div class="wpcuf_th wpcuf_th_full">
                                    <span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Applicable conditions', 'wpc-smart-upsell-funnel' ); ?>
                                </div>
                            </div>
							<?php self::source( $name, $key, $apply, $apply_number, $apply_products, $apply_terms, $apply_combination, 'apply' ); ?>
                            <div class="wpcuf_tr">
                                <div class="wpcuf_th">
									<?php esc_html_e( 'Time', 'wpc-smart-upsell-funnel' ); ?>
                                </div>
                                <div class="wpcuf_td">
                                    <p class="description" style="color: #c9356e;">
                                        Using time conditions is available only in the Premium Version.
                                        <a href="https://wpclever.net/downloads/wpc-smart-upsell-funnel?utm_source=pro&utm_medium=wpcuf&utm_campaign=wporg" target="_blank">Click here</a> to buy, just $29!
                                    </p>
                                    <p class="description"><?php esc_html_e( 'Configure date and time that must match all listed conditions.', 'wpc-smart-upsell-funnel' ); ?></p>
                                    <div class="wpcuf_timer_wrap">
                                        <div class="wpcuf_timer">
											<?php
											if ( ! empty( $rule_timer ) ) {
												foreach ( $rule_timer as $tm_k => $tm_v ) {
													self::time( $name, $key, $tm_k, $tm_v );
												}
											} else {
												self::time( $name, $key );
											}
											?>
                                        </div>
                                        <div class="wpcuf_add_time">
                                            <a href="#" class="button wpcuf_new_time" data-name="<?php echo esc_attr( $name ); ?>" data-key="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( '+ Add time', 'wpc-smart-upsell-funnel' ); ?></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="wpcuf_tr">
                                <div class="wpcuf_th">
									<?php esc_html_e( 'User roles', 'wpc-smart-upsell-funnel' ); ?>
                                </div>
                                <div class="wpcuf_td">
                                    <p class="description" style="color: #c9356e;">
                                        Using user roles conditions is available only in the Premium Version.
                                        <a href="https://wpclever.net/downloads/wpc-smart-upsell-funnel?utm_source=pro&utm_medium=wpcuf&utm_campaign=wporg" target="_blank">Click here</a> to buy, just $29!
                                    </p>
                                    <p class="description"><?php esc_html_e( 'Specify the user roles that are applied.', 'wpc-smart-upsell-funnel' ); ?></p>
                                    <label>
                                        <select name="<?php echo esc_attr( $name . '[' . $key . '][roles][]' ); ?>" multiple class="wpcuf_roles">
											<?php
											global $wp_roles;
											echo '<option value="wpcuf_all" ' . ( in_array( 'wpcuf_all', $rule_roles ) ? 'selected' : '' ) . '>' . esc_html__( 'All', 'wpc-smart-upsell-funnel' ) . '</option>';
											echo '<option value="wpcuf_user" ' . ( in_array( 'wpcuf_user', $rule_roles ) ? 'selected' : '' ) . '>' . esc_html__( 'User (logged in)', 'wpc-smart-upsell-funnel' ) . '</option>';
											echo '<option value="wpcuf_guest" ' . ( in_array( 'wpcuf_guest', $rule_roles ) ? 'selected' : '' ) . '>' . esc_html__( 'Guest (not logged in)', 'wpc-smart-upsell-funnel' ) . '</option>';

											foreach ( $wp_roles->roles as $role => $details ) {
												echo '<option value="' . esc_attr( $role ) . '" ' . ( in_array( $role, $rule_roles ) ? 'selected' : '' ) . '>' . esc_html( $details['name'] ) . '</option>';
											}
											?>
                                        </select> </label>
                                </div>
                            </div>
                            <div class="wpcuf_tr">
                                <div class="wpcuf_th wpcuf_th_full">
									<?php if ( $name === 'wpcuf_ob' ) {
										echo '<span class="dashicons dashicons-awards"></span> ' . esc_html__( 'Order bump products', 'wpc-smart-upsell-funnel' );
									} else {
										echo '<span class="dashicons dashicons-thumbs-up"></span> ' . esc_html__( 'Upsell products', 'wpc-smart-upsell-funnel' );
									} ?>
                                </div>
                            </div>
							<?php self::source( $name, $key, $get, $get_number, $get_products, $get_terms, $get_combination, 'get', $get_limit, $get_orderby, $get_order ); ?>
                            <div class="wpcuf_tr">
                                <div class="wpcuf_th"><?php esc_html_e( 'New price', 'wpc-smart-upsell-funnel' ); ?></div>
                                <div class="wpcuf_td wpcuf_td_flex">
                                    <select class="wpcuf_rule_price_type" name="<?php echo esc_attr( $name . '[' . $key . '][price][type]' ); ?>">
                                        <option value="percentage" <?php selected( $rule_price_type, 'percentage' ); ?>><?php echo esc_html( sprintf( /* translators: percentage symbol */ esc_html__( 'Percentage (%s)', 'wpc-smart-upsell-funnel' ), '%' ) ); ?></option>
                                        <option value="fixed" <?php selected( $rule_price_type, 'fixed' ); ?>><?php echo esc_html( sprintf( /* translators: currency symbol */ esc_html__( 'Fixed (%s)', 'wpc-smart-upsell-funnel' ), get_woocommerce_currency_symbol() ) ); ?></option>
                                    </select>
                                    <input type="number" step="any" min="0" class="small-text wpcuf_rule_price_val" name="<?php echo esc_attr( $name . '[' . $key . '][price][val]' ); ?>" value="<?php echo esc_attr( $rule_price_val ); ?>"/>
                                </div>
                            </div>
                        </div>
                    </div>
					<?php
				}

				function source( $name = 'wpcuf_uf', $key = '', $apply = 'all', $number = [], $products = [], $terms = [], $combination = [], $type = 'apply', $get_limit = null, $get_orderby = null, $get_order = null ) {
					?>
                    <div class="wpcuf_tr">
                        <div class="wpcuf_th">
							<?php if ( $type === 'apply' ) {
								esc_html_e( 'Type', 'wpc-smart-upsell-funnel' );
							} else {
								esc_html_e( 'Source', 'wpc-smart-upsell-funnel' );
							} ?>
                        </div>
                        <div class="wpcuf_td wpcuf_td_flex wpcuf_rule_td">
                            <select class="wpcuf_source_selector wpcuf_source_selector_<?php echo esc_attr( $type ); ?>" data-type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $name . '[' . $key . '][' . $type . ']' ); ?>">
								<?php if ( $type === 'apply' ) { ?>
                                    <option value="none"><?php esc_html_e( 'None (Disable)', 'wpc-smart-upsell-funnel' ); ?></option>
								<?php } ?>
                                <option value="combination" <?php selected( $apply, 'combination' ); ?>><?php esc_html_e( 'Combined', 'wpc-smart-upsell-funnel' ); ?></option>
								<?php if ( $type === 'apply' ) {
									echo '<option data-type="number" value="cart_subtotal" ' . selected( $apply, 'cart_subtotal', false ) . '>' . esc_html__( 'Cart subtotal', 'wpc-smart-upsell-funnel' ) . '</option>';
									echo '<option data-type="number" value="cart_total" ' . selected( $apply, 'cart_total', false ) . '>' . esc_html__( 'Cart total', 'wpc-smart-upsell-funnel' ) . '</option>';
									echo '<option data-type="number" value="cart_count" ' . selected( $apply, 'cart_count', false ) . '>' . esc_html__( 'Cart count', 'wpc-smart-upsell-funnel' ) . '</option>';
									echo '<optgroup label="' . esc_attr__( 'Cart contains', 'wpc-smart-upsell-funnel' ) . '">';
								} ?>
                                <option value="all" <?php selected( $apply, 'all' ); ?>><?php esc_html_e( 'Any products', 'wpc-smart-upsell-funnel' ); ?></option>
                                <option value="products" <?php selected( $apply, 'products' ); ?>><?php esc_html_e( 'Selected products', 'wpc-smart-upsell-funnel' ); ?></option>
								<?php
								$taxonomies = get_object_taxonomies( 'product', 'objects' );

								foreach ( $taxonomies as $taxonomy ) {
									echo '<option data-type="terms" value="' . esc_attr( $taxonomy->name ) . '" ' . ( $apply === $taxonomy->name ? 'selected' : '' ) . '>' . esc_html( $taxonomy->label ) . '</option>';
								}

								if ( $type === 'apply' ) {
									echo '</optgroup>';
								}
								?>
                            </select>
							<?php if ( $type === 'get' ) { ?>
                                <span class="show_get hide_if_get_products">
										<span><?php esc_html_e( 'Limit', 'wpc-smart-upsell-funnel' ); ?> <input type="number" min="1" max="50" name="<?php echo esc_attr( $name . '[' . $key . '][get_limit]' ); ?>" value="<?php echo esc_attr( $get_limit ); ?>"/></span>
										<span>
										<?php esc_html_e( 'Order by', 'wpc-smart-upsell-funnel' ); ?> <select name="<?php echo esc_attr( $name . '[' . $key . '][get_orderby]' ); ?>">
                                                        <option value="default" <?php selected( $get_orderby, 'default' ); ?>><?php esc_html_e( 'Default', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="none" <?php selected( $get_orderby, 'none' ); ?>><?php esc_html_e( 'None', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="ID" <?php selected( $get_orderby, 'ID' ); ?>><?php esc_html_e( 'ID', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="name" <?php selected( $get_orderby, 'name' ); ?>><?php esc_html_e( 'Name', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="type" <?php selected( $get_orderby, 'type' ); ?>><?php esc_html_e( 'Type', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="rand" <?php selected( $get_orderby, 'rand' ); ?>><?php esc_html_e( 'Rand', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="date" <?php selected( $get_orderby, 'date' ); ?>><?php esc_html_e( 'Date', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="price" <?php selected( $get_orderby, 'price' ); ?>><?php esc_html_e( 'Price', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="modified" <?php selected( $get_orderby, 'modified' ); ?>><?php esc_html_e( 'Modified', 'wpc-smart-upsell-funnel' ); ?></option>
                                                    </select>
									</span>
										<span><?php esc_html_e( 'Order', 'wpc-smart-upsell-funnel' ); ?> <select name="<?php echo esc_attr( $name . '[' . $key . '][get_order]' ); ?>">
                                                        <option value="default" <?php selected( $get_order, 'default' ); ?>><?php esc_html_e( 'Default', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="DESC" <?php selected( $get_order, 'DESC' ); ?>><?php esc_html_e( 'DESC', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        <option value="ASC" <?php selected( $get_order, 'ASC' ); ?>><?php esc_html_e( 'ASC', 'wpc-smart-upsell-funnel' ); ?></option>
                                                        </select></span>
									</span>
							<?php } ?>
                            <select class="wpcuf_source_number_compare hide_<?php echo esc_attr( $type ); ?> show_if_<?php echo esc_attr( $type ); ?>_cart_subtotal show_if_<?php echo esc_attr( $type ); ?>_cart_total show_if_<?php echo esc_attr( $type ); ?>_cart_count" name="<?php echo esc_attr( $name . '[' . $key . '][' . $type . '_number' . '][compare]' ); ?>">
                                <option value="equal" <?php selected( $number['compare'], 'equal' ); ?>><?php esc_html_e( 'equal', 'wpc-smart-upsell-funnel' ); ?></option>
                                <option value="not_equal" <?php selected( $number['compare'], 'not_equal' ); ?>><?php esc_html_e( 'not equal', 'wpc-smart-upsell-funnel' ); ?></option>
                                <option value="greater" <?php selected( $number['compare'], 'greater' ); ?>><?php esc_html_e( 'greater', 'wpc-smart-upsell-funnel' ); ?></option>
                                <option value="less" <?php selected( $number['compare'], 'less' ); ?>><?php esc_html_e( 'less than', 'wpc-smart-upsell-funnel' ); ?></option>
                                <option value="greater_equal" <?php selected( $number['compare'], 'greater_equal' ); ?>><?php esc_html_e( 'greater or equal', 'wpc-smart-upsell-funnel' ); ?></option>
                                <option value="less_equal" <?php selected( $number['compare'], 'less_equal' ); ?>><?php esc_html_e( 'less or equal', 'wpc-smart-upsell-funnel' ); ?></option>
                            </select>
                            <input type="number" class="wpcuf_source_number_val hide_<?php echo esc_attr( $type ); ?> show_if_<?php echo esc_attr( $type ); ?>_cart_subtotal show_if_<?php echo esc_attr( $type ); ?>_cart_total show_if_<?php echo esc_attr( $type ); ?>_cart_count" value="<?php echo esc_attr( $number['value'] ); ?>" name="<?php echo esc_attr( $name . '[' . $key . '][' . $type . '_number' . '][value]' ); ?>"/>
                        </div>
                    </div>
                    <div class="wpcuf_tr hide_<?php echo esc_attr( $type ); ?> show_if_<?php echo esc_attr( $type ); ?>_products">
                        <div class="wpcuf_th"><?php esc_html_e( 'Products', 'wpc-smart-upsell-funnel' ); ?></div>
                        <div class="wpcuf_td wpcuf_rule_td">
                            <select class="wc-product-search wpcuf-product-search" multiple="multiple" name="<?php echo esc_attr( $name . '[' . $key . '][' . $type . '_products' . '][]' ); ?>" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'wpc-smart-upsell-funnel' ); ?>" data-action="woocommerce_json_search_products_and_variations">
								<?php
								if ( ! empty( $products ) ) {
									foreach ( $products as $_product_id ) {
										if ( $_product = wc_get_product( $_product_id ) ) {
											echo '<option value="' . esc_attr( $_product_id ) . '" selected>' . esc_html( $_product->get_formatted_name() ) . '</option>';
										}
									}
								}
								?>
                            </select>
                        </div>
                    </div>
                    <div class="wpcuf_tr hide_<?php echo esc_attr( $type ); ?> show_if_<?php echo esc_attr( $type ); ?>_combination">
                        <div class="wpcuf_th"><?php esc_html_e( 'Combined', 'wpc-smart-upsell-funnel' ); ?></div>
                        <div class="wpcuf_td wpcuf_rule_td">
                            <div class="wpcuf_combinations">
                                <p class="description"><?php esc_html_e( '* Configure to find products that match all listed conditions.', 'wpc-smart-upsell-funnel' ); ?></p>
								<?php
								if ( ! empty( $combination ) ) {
									foreach ( $combination as $ck => $cmb ) {
										self::combination( $ck, $name, $cmb, $key, $type );
									}
								}
								?>
                            </div>
                            <div class="wpcuf_add_combination">
                                <a class="wpcuf_new_combination button" href="#" data-name="<?php echo esc_attr( $name ); ?>" data-type="<?php echo esc_attr( $type ); ?>"><?php esc_attr_e( '+ Add condition', 'wpc-smart-upsell-funnel' ); ?></a>
                            </div>
                        </div>
                    </div>
                    <div class="wpcuf_tr show_<?php echo esc_attr( $type ); ?> hide_if_<?php echo esc_attr( $type ); ?>_all hide_if_<?php echo esc_attr( $type ); ?>_any hide_if_<?php echo esc_attr( $type ); ?>_none hide_if_<?php echo esc_attr( $type ); ?>_products hide_if_<?php echo esc_attr( $type ); ?>_combination hide_if_<?php echo esc_attr( $type ); ?>_cart_subtotal hide_if_<?php echo esc_attr( $type ); ?>_cart_total hide_if_<?php echo esc_attr( $type ); ?>_cart_count">
                        <div class="wpcuf_th wpcuf_<?php echo esc_attr( $type ); ?>_text"><?php esc_html_e( 'Terms', 'wpc-smart-upsell-funnel' ); ?></div>
                        <div class="wpcuf_td wpcuf_rule_td">
                            <select class="wpcuf_terms" data-type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $name . '[' . $key . '][' . $type . '_terms' . '][]' ); ?>" multiple="multiple" data-<?php echo esc_attr( $apply ); ?>="<?php echo esc_attr( implode( ',', $terms ) ); ?>">
								<?php
								if ( ! empty( $terms ) ) {
									foreach ( $terms as $at ) {
										if ( $term = get_term_by( 'slug', $at, $apply ) ) {
											echo '<option value="' . esc_attr( $at ) . '" selected>' . esc_html( $term->name ) . '</option>';
										}
									}
								}
								?>
                            </select>
                        </div>
                    </div>
					<?php
				}

				function combination( $c_key = '', $name = 'wpcuf_uf', $combination = [], $key = '', $type = 'apply' ) {
					if ( empty( $c_key ) || is_numeric( $c_key ) ) {
						$c_key = self::generate_key();
					}

					$apply      = $combination['apply'] ?? '';
					$compare    = $combination['compare'] ?? 'is';
					$terms      = (array) ( $combination['terms'] ?? [] );
					$n_compare  = $combination['number_compare'] ?? 'equal';
					$n_value    = $combination['number_value'] ?? '0';
					$taxonomies = get_object_taxonomies( 'product', 'objects' );
					?>
                    <div class="wpcuf_combination">
                        <span class="wpcuf_combination_remove">&times;</span>
                        <span class="wpcuf_combination_selector_wrap">
                                    <select class="wpcuf_combination_selector" name="<?php echo esc_attr( $name . '[' . $key . '][' . $type . '_combination' . '][' . $c_key . '][apply]' ); ?>">
	                                    <?php
	                                    if ( $type === 'get' ) { ?>
                                            <option value="price" <?php selected( $apply, 'price' ); ?>><?php esc_html_e( 'Product price', 'wpc-smart-upsell-funnel' ); ?></option>
		                                    <?php
	                                    } elseif ( $type === 'apply' ) {
	                                    ?>
                                            <option data-type="number" value="cart_subtotal" <?php selected( $apply, 'cart_subtotal' ); ?>><?php esc_html_e( 'Cart subtotal', 'wpc-smart-upsell-funnel' ); ?></option>
                                            <option data-type="number" value="cart_total" <?php selected( $apply, 'cart_total' ); ?>><?php esc_html_e( 'Cart total', 'wpc-smart-upsell-funnel' ); ?></option>
                                            <option data-type="number" value="cart_count" <?php selected( $apply, 'cart_count' ); ?>><?php esc_html_e( 'Cart count', 'wpc-smart-upsell-funnel' ); ?></option>
                                            <optgroup label="<?php esc_attr_e( 'Cart contains', 'wpc-smart-upsell-funnel' ); ?>">
	                                    <?php }

	                                    foreach ( $taxonomies as $taxonomy ) {
		                                    echo '<option data-type="terms" value="' . esc_attr( $taxonomy->name ) . '" ' . ( $apply === $taxonomy->name ? 'selected' : '' ) . '>' . esc_html( $taxonomy->label ) . '</option>';
	                                    }

	                                    if ( $type === 'apply' ) {
		                                    echo '</optgroup>';
	                                    }
	                                    ?>
                                    </select>
                                </span> <span class="wpcuf_combination_compare_wrap">
							<select class="wpcuf_combination_compare" name="<?php echo esc_attr( $name . '[' . $key . '][' . $type . '_combination' . '][' . $c_key . '][compare]' ); ?>">
								<option value="is" <?php selected( $compare, 'is' ); ?>><?php esc_html_e( 'including', 'wpc-smart-upsell-funnel' ); ?></option>
								<option value="is_not" <?php selected( $compare, 'is_not' ); ?>><?php esc_html_e( 'excluding', 'wpc-smart-upsell-funnel' ); ?></option>
							</select></span> <span class="wpcuf_combination_val_wrap">
                                    <select class="wpcuf_combination_val wpcuf_apply_terms" multiple="multiple" name="<?php echo esc_attr( $name . '[' . $key . '][' . $type . '_combination' . '][' . $c_key . '][terms][]' ); ?>">
                                        <?php
                                        if ( ! empty( $terms ) ) {
	                                        foreach ( $terms as $ct ) {
		                                        if ( $term = get_term_by( 'slug', $ct, $apply ) ) {
			                                        echo '<option value="' . esc_attr( $ct ) . '" selected>' . esc_html( $term->name ) . '</option>';
		                                        }
	                                        }
                                        }
                                        ?>
                                    </select>
                                </span> <span class="wpcuf_combination_number_compare_wrap">
							<select class="wpcuf_combination_number_compare" name="<?php echo esc_attr( $name . '[' . $key . '][' . $type . '_combination' . '][' . $c_key . '][number_compare]' ); ?>">
								<option value="equal" <?php selected( $n_compare, 'equal' ); ?>><?php esc_html_e( 'equal', 'wpc-smart-upsell-funnel' ); ?></option>
								<option value="not_equal" <?php selected( $n_compare, 'not_equal' ); ?>><?php esc_html_e( 'not equal', 'wpc-smart-upsell-funnel' ); ?></option>
								<option value="greater" <?php selected( $n_compare, 'greater' ); ?>><?php esc_html_e( 'greater', 'wpc-smart-upsell-funnel' ); ?></option>
								<option value="less" <?php selected( $n_compare, 'less' ); ?>><?php esc_html_e( 'less than', 'wpc-smart-upsell-funnel' ); ?></option>
								<option value="greater_equal" <?php selected( $n_compare, 'greater_equal' ); ?>><?php esc_html_e( 'greater or equal', 'wpc-smart-upsell-funnel' ); ?></option>
								<option value="less_equal" <?php selected( $n_compare, 'less_equal' ); ?>><?php esc_html_e( 'less or equal', 'wpc-smart-upsell-funnel' ); ?></option>
							</select></span> <span class="wpcuf_combination_number_value_wrap">
                                    <input type="number" class="wpcuf_combination_number_value" value="<?php echo esc_attr( $n_value ); ?>" name="<?php echo esc_attr( $name . '[' . $key . '][' . $type . '_combination' . '][' . $c_key . '][number_value]' ); ?>"/>
                                </span>
                    </div>
					<?php
				}

				function time( $name, $key, $time_key = 0, $time_data = [] ) {
					if ( empty( $time_key ) || is_numeric( $time_key ) ) {
						$time_key = self::generate_key();
					}

					$type = ! empty( $time_data['type'] ) ? $time_data['type'] : 'every_day';
					$val  = ! empty( $time_data['val'] ) ? $time_data['val'] : '';
					$date = $date_time = $date_multi = $date_range = $from = $to = $time = $weekday = $monthday = $weekno = $monthno = $number = '';

					switch ( $type ) {
						case 'date_on':
						case 'date_before':
						case 'date_after':
							$date = $val;
							break;
						case 'date_time_before':
						case 'date_time_after':
							$date_time = $val;
							break;
						case 'date_multi':
							$date_multi = $val;
							break;
						case 'date_range':
							$date_range = $val;
							break;
						case 'time_range':
							$time_range = array_map( 'trim', explode( '-', (string) $val ) );
							$from       = ! empty( $time_range[0] ) ? $time_range[0] : '';
							$to         = ! empty( $time_range[1] ) ? $time_range[1] : '';
							break;
						case 'time_before':
						case 'time_after':
							$time = $val;
							break;
						case 'weekly_every':
							$weekday = $val;
							break;
						case 'week_no':
							$weekno = $val;
							break;
						case 'monthly_every':
							$monthday = $val;
							break;
						case 'month_no':
							$monthno = $val;
							break;
						default:
							$val = '';
					}
					?>
                    <div class="wpcuf_time">
                        <input type="hidden" class="wpcuf_time_val" name="<?php echo esc_attr( $name . '[' . $key . '][timer][' . $time_key . '][val]' ); ?>" value="<?php echo esc_attr( $val ); ?>"/>
                        <span class="wpcuf_time_remove">&times;</span> <span>
							<label>
<select class="wpcuf_time_type" name="<?php echo esc_attr( $name . '[' . $key . '][timer][' . $time_key . '][type]' ); ?>">
    <option value=""><?php esc_html_e( 'Choose the time', 'wpc-smart-upsell-funnel' ); ?></option>
    <option value="date_on" data-show="date" <?php selected( $type, 'date_on' ); ?>><?php esc_html_e( 'On the date', 'wpc-smart-upsell-funnel' ); ?></option>
<option value="date_time_before" data-show="date_time" <?php selected( $type, 'date_time_before' ); ?>><?php esc_html_e( 'Before date & time', 'wpc-smart-upsell-funnel' ); ?></option>
    <option value="date_time_after" data-show="date_time" <?php selected( $type, 'date_time_after' ); ?>><?php esc_html_e( 'After date & time', 'wpc-smart-upsell-funnel' ); ?></option>
    <option value="date_before" data-show="date" <?php selected( $type, 'date_before' ); ?>><?php esc_html_e( 'Before date', 'wpc-smart-upsell-funnel' ); ?></option>
    <option value="date_after" data-show="date" <?php selected( $type, 'date_after' ); ?>><?php esc_html_e( 'After date', 'wpc-smart-upsell-funnel' ); ?></option>
    <option value="date_multi" data-show="date_multi" <?php selected( $type, 'date_multi' ); ?>><?php esc_html_e( 'Multiple dates', 'wpc-smart-upsell-funnel' ); ?></option>
    <option value="date_range" data-show="date_range" <?php selected( $type, 'date_range' ); ?>><?php esc_html_e( 'Date range', 'wpc-smart-upsell-funnel' ); ?></option>
    <option value="date_even" data-show="none" <?php selected( $type, 'date_even' ); ?>><?php esc_html_e( 'All even dates', 'wpc-smart-upsell-funnel' ); ?></option>
    <option value="date_odd" data-show="none" <?php selected( $type, 'date_odd' ); ?>><?php esc_html_e( 'All odd dates', 'wpc-smart-upsell-funnel' ); ?></option>
    <option value="time_range" data-show="time_range" <?php selected( $type, 'time_range' ); ?>><?php esc_html_e( 'Daily time range', 'wpc-smart-upsell-funnel' ); ?></option>
<option value="time_before" data-show="time" <?php selected( $type, 'time_before' ); ?>><?php esc_html_e( 'Daily before time', 'wpc-smart-upsell-funnel' ); ?></option>
    <option value="time_after" data-show="time" <?php selected( $type, 'time_after' ); ?>><?php esc_html_e( 'Daily after time', 'wpc-smart-upsell-funnel' ); ?></option>
<option value="weekly_every" data-show="weekday" <?php selected( $type, 'weekly_every' ); ?>><?php esc_html_e( 'Weekly on every', 'wpc-smart-upsell-funnel' ); ?></option>
<option value="week_even" data-show="none" <?php selected( $type, 'week_even' ); ?>><?php esc_html_e( 'All even weeks', 'wpc-smart-upsell-funnel' ); ?></option>
    <option value="week_odd" data-show="none" <?php selected( $type, 'week_odd' ); ?>><?php esc_html_e( 'All odd weeks', 'wpc-smart-upsell-funnel' ); ?></option>
<option value="week_no" data-show="weekno" <?php selected( $type, 'week_no' ); ?>><?php esc_html_e( 'On week No.', 'wpc-smart-upsell-funnel' ); ?></option>
<option value="monthly_every" data-show="monthday" <?php selected( $type, 'monthly_every' ); ?>><?php esc_html_e( 'Monthly on the', 'wpc-smart-upsell-funnel' ); ?></option>
<option value="month_no" data-show="monthno" <?php selected( $type, 'month_no' ); ?>><?php esc_html_e( 'On month No.', 'wpc-smart-upsell-funnel' ); ?></option>
<option value="every_day" data-show="none" <?php selected( $type, 'every_day' ); ?>><?php esc_html_e( 'Everyday', 'wpc-smart-upsell-funnel' ); ?></option>
</select>
</label>
						</span> <span class="wpcuf_hide wpcuf_show_if_date_time">
							<label>
<input value="<?php echo esc_attr( $date_time ); ?>" class="wpcuf_dpk_date_time wpcuf_date_time_input" type="text" readonly="readonly" style="width: 300px"/>
</label>
						</span> <span class="wpcuf_hide wpcuf_show_if_date">
							<label>
<input value="<?php echo esc_attr( $date ); ?>" class="wpcuf_dpk_date wpcuf_date_input" type="text" readonly="readonly" style="width: 300px"/>
</label>
						</span> <span class="wpcuf_hide wpcuf_show_if_date_range">
							<label>
<input value="<?php echo esc_attr( $date_range ); ?>" class="wpcuf_dpk_date_range wpcuf_date_input" type="text" readonly="readonly" style="width: 300px"/>
</label>
						</span> <span class="wpcuf_hide wpcuf_show_if_date_multi">
							<label>
<input value="<?php echo esc_attr( $date_multi ); ?>" class="wpcuf_dpk_date_multi wpcuf_date_input" type="text" readonly="readonly" style="width: 300px"/>
</label>
						</span> <span class="wpcuf_hide wpcuf_show_if_time_range">
							<label>
<input value="<?php echo esc_attr( $from ); ?>" class="wpcuf_dpk_time wpcuf_time_from wpcuf_time_input" type="text" readonly="readonly" style="width: 300px" placeholder="from"/>
</label>
							<label>
<input value="<?php echo esc_attr( $to ); ?>" class="wpcuf_dpk_time wpcuf_time_to wpcuf_time_input" type="text" readonly="readonly" style="width: 300px" placeholder="to"/>
</label>
						</span> <span class="wpcuf_hide wpcuf_show_if_time">
							<label>
<input value="<?php echo esc_attr( $time ); ?>" class="wpcuf_dpk_time wpcuf_time_on wpcuf_time_input" type="text" readonly="readonly" style="width: 300px"/>
</label>
						</span> <span class="wpcuf_hide wpcuf_show_if_weekday">
							<label>
<select class="wpcuf_weekday">
<option value="mon" <?php selected( $weekday, 'mon' ); ?>><?php esc_html_e( 'Monday', 'wpc-smart-upsell-funnel' ); ?></option>
<option value="tue" <?php selected( $weekday, 'tue' ); ?>><?php esc_html_e( 'Tuesday', 'wpc-smart-upsell-funnel' ); ?></option>
<option value="wed" <?php selected( $weekday, 'wed' ); ?>><?php esc_html_e( 'Wednesday', 'wpc-smart-upsell-funnel' ); ?></option>
<option value="thu" <?php selected( $weekday, 'thu' ); ?>><?php esc_html_e( 'Thursday', 'wpc-smart-upsell-funnel' ); ?></option>
<option value="fri" <?php selected( $weekday, 'fri' ); ?>><?php esc_html_e( 'Friday', 'wpc-smart-upsell-funnel' ); ?></option>
<option value="sat" <?php selected( $weekday, 'sat' ); ?>><?php esc_html_e( 'Saturday', 'wpc-smart-upsell-funnel' ); ?></option>
<option value="sun" <?php selected( $weekday, 'sun' ); ?>><?php esc_html_e( 'Sunday', 'wpc-smart-upsell-funnel' ); ?></option>
</select>
</label>
						</span> <span class="wpcuf_hide wpcuf_show_if_monthday">
							<label>
<select class="wpcuf_monthday">
<?php for ( $i = 1; $i < 32; $i ++ ) {
	echo '<option value="' . esc_attr( $i ) . '" ' . ( (int) $monthday === $i ? 'selected' : '' ) . '>' . esc_html( $i ) . '</option>';
} ?>
</select>
</label>
						</span> <span class="wpcuf_hide wpcuf_show_if_weekno">
							<label>
<select class="wpcuf_weekno">
<?php
for ( $i = 1; $i < 54; $i ++ ) {
	echo '<option value="' . esc_attr( $i ) . '" ' . ( (int) $weekno === $i ? 'selected' : '' ) . '>' . esc_html( $i ) . '</option>';
}
?>
</select>
</label>
						</span> <span class="wpcuf_hide wpcuf_show_if_monthno">
							<label>
<select class="wpcuf_monthno">
<?php
for ( $i = 1; $i < 13; $i ++ ) {
	echo '<option value="' . esc_attr( $i ) . '" ' . ( (int) $monthno === $i ? 'selected' : '' ) . '>' . esc_html( $i ) . '</option>';
}
?>
</select>
</label>
						</span> <span class="wpcuf_hide wpcuf_show_if_number">
							<label>
<input type="number" step="1" min="0" class="wpcuf_number" value="<?php echo esc_attr( (int) $number ); ?>"/>
</label>
						</span>
                    </div>
					<?php
					return;
				}

				function ajax_add_time() {
					if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcuf-security' ) ) {
						die( 'Permissions check failed!' );
					}

					$name = sanitize_text_field( $_POST['name'] ?? '' );
					$key  = sanitize_text_field( $_POST['key'] ?? '' );

					if ( ! empty( $name ) && ! empty( $key ) ) {
						self::time( $name, $key );
					}

					wp_die();
				}

				function ajax_add_rule() {
					if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcuf-security' ) ) {
						die( 'Permissions check failed!' );
					}

					$rule      = [];
					$name      = sanitize_key( $_POST['name'] ?? 'wpcuf_uf' );
					$rule_data = $_POST['rule_data'] ?? ''; // parse_str and sanitize later
					$form_rule = [];

					if ( ! empty( $rule_data ) ) {
						wp_parse_str( $rule_data, $form_rule );

						if ( isset( $form_rule[ $name ] ) && is_array( $form_rule[ $name ] ) ) {
							$rule = self::sanitize_array( reset( $form_rule[ $name ] ) );
						}
					}

					self::rule( $name, '', $rule, true );
					wp_die();
				}

				function ajax_add_combination() {
					if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcuf-security' ) ) {
						die( 'Permissions check failed!' );
					}

					$key  = sanitize_key( $_POST['key'] ?? self::generate_key() );
					$name = sanitize_key( $_POST['name'] ?? 'wpcuf_uf' );
					$type = sanitize_key( $_POST['type'] ?? 'apply' );

					self::combination( '', $name, [], $key, $type );
					wp_die();
				}

				function ajax_search_term() {
					$return = [];

					$args = [
						'taxonomy'   => sanitize_text_field( $_REQUEST['taxonomy'] ),
						'orderby'    => 'id',
						'order'      => 'ASC',
						'hide_empty' => false,
						'fields'     => 'all',
						'name__like' => sanitize_text_field( $_REQUEST['q'] ),
					];

					$terms = get_terms( $args );

					if ( count( $terms ) ) {
						foreach ( $terms as $term ) {
							$return[] = [ $term->slug, $term->name ];
						}
					}

					wp_send_json( $return );
				}

				function enqueue_scripts() {
					wp_enqueue_script( 'wc-add-to-cart-variation' );
					wp_enqueue_style( 'hint', WPCUF_URI . 'assets/css/hint.css' );

					// frontend css & js
					wp_enqueue_style( 'wpcuf-frontend', WPCUF_URI . 'assets/css/frontend.css', [], WPCUF_VERSION );
					wp_enqueue_script( 'wpcuf-frontend', WPCUF_URI . 'assets/js/frontend.js', [ 'jquery' ], WPCUF_VERSION, true );
					wp_localize_script( 'wpcuf-frontend', 'wpcuf_vars', [
							'wc_ajax_url' => WC_AJAX::get_endpoint( '%%endpoint%%' ),
							'nonce'       => wp_create_nonce( 'wpcuf-security' ),
						]
					);
				}

				function shortcode_uf( $attrs ) {
					$attrs = shortcode_atts( [
						'context' => 'cart'
					], $attrs, 'wpcuf_uf' );

					if ( ! isset( WC()->cart ) || ! WC()->cart->get_cart() || empty( self::$uf_rules ) ) {
						return '';
					}

					$in_cart     = [];
					$uf_ids      = [];
					$uf_products = [];

					foreach ( WC()->cart->get_cart() as $cart_item ) {
						$in_cart[] = $cart_item['product_id'] ?? 0;
					}

					foreach ( self::$uf_rules as $key => $rule ) {
						if ( self::check_timer( $rule ) && self::check_roles( $rule ) && self::check_apply_cart( $rule ) ) {
							$rule_products = self::get_products( $rule );

							if ( ! empty( $rule_products ) ) {
								foreach ( $rule_products as $rule_product ) {
									if ( $rule_product && ! in_array( $rule_product, $uf_ids ) && ( $rule_product_obj = wc_get_product( $rule_product ) ) ) {
										$exclude = ! $rule_product_obj->is_purchasable() || ! $rule_product_obj->is_in_stock();

										if ( apply_filters( 'wpcuf_uf_exclude', $exclude, $rule_product_obj ) ) {
											continue;
										}

										$uf_ids[]   = $rule_product;
										$price      = (float) $rule_product_obj->get_price();
										$rule_price = $rule['price'] ?? [];
										$rule_price = array_merge( [
											'type' => 'percentage',
											'val'  => '100',
										], $rule_price );

										if ( $rule_price['type'] === 'percentage' ) {
											$new_price = $price * floatval( $rule_price['val'] ) / 100;
										} else {
											$new_price = floatval( $rule_price['val'] );
										}

										$saved_amount     = $price > $new_price ? $price - $new_price : 0;
										$saved_percentage = $price > $new_price ? round( 100 * ( $price - $new_price ) / $price ) : 0;

										$uf_products[] = [
											'key'              => $key,
											'id'               => $rule_product,
											'price'            => $price,
											'new_price'        => $new_price,
											'saved_amount'     => $saved_amount,
											'saved_percentage' => $saved_percentage,
											'product'          => $rule_product_obj
										];
									}
								}
							}
						}
					}

					foreach ( $uf_products as $k => $uf_product ) {
						if ( in_array( $uf_product['id'], $in_cart ) ) {
							unset( $uf_products[ $k ] );
						}
					}

					if ( empty( $uf_products ) ) {
						return '';
					}

					$uf_order = self::get_setting( 'uf_order', 'default' );

					switch ( $uf_order ) {
						case 'price_asc':
							array_multisort( array_column( $uf_products, 'price' ), SORT_ASC, $uf_products );
							break;
						case 'price_desc':
							array_multisort( array_column( $uf_products, 'price' ), SORT_DESC, $uf_products );
							break;
						case 'new_price_asc':
							array_multisort( array_column( $uf_products, 'new_price' ), SORT_ASC, $uf_products );
							break;
						case 'new_price_desc':
							array_multisort( array_column( $uf_products, 'new_price' ), SORT_DESC, $uf_products );
							break;
						case 'saved_amount_asc':
							array_multisort( array_column( $uf_products, 'saved_amount' ), SORT_ASC, $uf_products );
							break;
						case 'saved_amount_desc':
							array_multisort( array_column( $uf_products, 'saved_amount' ), SORT_DESC, $uf_products );
							break;
						case 'saved_percentage_asc':
							array_multisort( array_column( $uf_products, 'saved_percentage' ), SORT_ASC, $uf_products );
							break;
						case 'saved_percentage_desc':
							array_multisort( array_column( $uf_products, 'saved_percentage' ), SORT_DESC, $uf_products );
							break;
					}

					if ( $uf_limit = absint( self::get_setting( 'uf_limit', 5 ) ) ) {
						$uf_products = array_slice( $uf_products, 0, $uf_limit );
					}

					$uf_heading     = self::get_setting( 'uf_heading', '' );
					$uf_add_to_cart = self::get_setting( 'uf_add_to_cart', '' );

					if ( empty( $uf_heading ) ) {
						$uf_heading = esc_html__( 'Hang on! Maybe you don\'t want to miss this deal:', 'wpc-smart-upsell-funnel' );
					}

					if ( empty( $uf_add_to_cart ) ) {
						$uf_add_to_cart = esc_html__( 'Add to cart', 'wpc-smart-upsell-funnel' );
					}

					ob_start();

					echo '<div class="wpcuf-uf-wrap">';
					echo '<div class="wpcuf-uf-inner">';
					echo '<div class="wpcuf-uf-header"><div class="wpcuf-uf-heading">' . esc_html( $uf_heading ) . '</div></div>';
					echo '<div class="wpcuf-uf-content">';
					echo '<div class="wpcuf-uf-products">';

					foreach ( $uf_products as $uf_product_data ) {
						if ( ( $uf_product = $uf_product_data['product'] ) && is_a( $uf_product, 'WC_Product' ) ) {
							$uf_product_id    = $uf_product_data['id'] ?? 0;
							$uf_product_key   = $uf_product_data['key'] ?? '';
							$uf_product_type  = $uf_product->get_type();
							$uf_product_class = apply_filters( 'wpcuf_uf_product_class', 'wpcuf-uf-product wpcuf-uf-product-' . $uf_product_type . ' ' . ( $uf_product_type !== 'simple' ? 'wpcuf-uf-product-disabled' : '' ) );
							?>
                            <div class="<?php echo esc_attr( $uf_product_class ); ?>" data-key="<?php echo esc_attr( $uf_product_key ); ?>" data-product_id="<?php echo esc_attr( $uf_product_id ); ?>" data-product_type="<?php echo esc_attr( $uf_product_type ); ?>" data-context="<?php echo esc_attr( $attrs['context'] ); ?>" data-variation_id="0" data-attrs="">
                                <div class="wpcuf-uf-product-atc hint--left" aria-label="<?php echo esc_attr( $uf_add_to_cart ); ?>">
                                    <span class="wpcuf-uf-product-add">+</span>
                                </div>
                                <div class="wpcuf-uf-product-image wpcuf-image">
									<?php if ( $uf_product->is_visible() && ( self::get_setting( 'uf_link', 'yes_blank' ) !== 'no' ) ) {
										echo wp_kses_post( '<a ' . ( self::get_setting( 'uf_link', 'yes_blank' ) === 'yes_popup' ? 'class="woosq-link no-ajaxy" data-id="' . esc_attr( $uf_product_id ) . '" data-context="wpcuf-uf"' : '' ) . ' href="' . esc_url( $uf_product->get_permalink() ) . '" ' . ( self::get_setting( 'uf_link', 'yes_blank' ) === 'yes_blank' ? 'target="_blank"' : '' ) . '>' . apply_filters( 'wpcuf_uf_product_image', $uf_product->get_image(), $uf_product, $attrs ) . '</a>' );
									} else {
										echo wp_kses_post( apply_filters( 'wpcuf_uf_product_image', $uf_product->get_image(), $uf_product, $attrs ) );
									} ?>
                                </div>
                                <div class="wpcuf-uf-product-info wpcuf-info">
                                    <div class="wpcuf-uf-product-name wpcuf-name">
										<?php if ( $uf_product->is_visible() && ( self::get_setting( 'uf_link', 'yes_blank' ) !== 'no' ) ) {
											echo wp_kses_post( '<a ' . ( self::get_setting( 'uf_link', 'yes_blank' ) === 'yes_popup' ? 'class="woosq-link no-ajaxy" data-id="' . esc_attr( $uf_product_id ) . '" data-context="wpcuf-uf"' : '' ) . ' href="' . esc_url( $uf_product->get_permalink() ) . '" ' . ( self::get_setting( 'uf_link', 'yes_blank' ) === 'yes_blank' ? 'target="_blank"' : '' ) . '>' . apply_filters( 'wpcuf_uf_product_name', $uf_product->get_name(), $uf_product, $attrs ) . '</a>' );
										} else {
											echo wp_kses_post( apply_filters( 'wpcuf_uf_product_name', $uf_product->get_name(), $uf_product, $attrs ) );
										} ?>
                                    </div>
                                    <div class="wpcuf-uf-product-price wpcuf-price">
										<?php
										$uf_product_price = $uf_product->get_price_html();

										if ( $uf_product_data['new_price'] !== $uf_product_data['price'] ) {
											if ( $uf_product_data['new_price'] > $uf_product_data['price'] ) {
												$uf_product_price = wc_price( $uf_product_data['new_price'] );
											}

											if ( $uf_product_data['new_price'] < $uf_product_data['price'] ) {
												$uf_product_price = wc_format_sale_price( $uf_product_data['price'], $uf_product_data['new_price'] );
											}
										}

										echo wp_kses_post( apply_filters( 'wpcuf_uf_product_price', $uf_product_price, $uf_product, $attrs ) );
										?>
                                    </div>
									<?php
									if ( $uf_product->is_type( 'variable' ) ) {
										if ( ( self::get_setting( 'uf_variations_selector', 'default' ) === 'woovr' ) && class_exists( 'WPClever_Woovr' ) ) {
											WPClever_Woovr::woovr_variations_form( $uf_product, false, 'wpcuf' );
										} else {
											$attributes           = $uf_product->get_variation_attributes();
											$available_variations = $uf_product->get_available_variations();
											$variations_json      = wp_json_encode( $available_variations );
											$variations_attr      = function_exists( 'wc_esc_json' ) ? wc_esc_json( $variations_json ) : _wp_specialchars( $variations_json, ENT_QUOTES, 'UTF-8', true );

											if ( is_array( $attributes ) && ( count( $attributes ) > 0 ) ) {
												echo '<div class="variations_form wpcuf_variations_form" action="' . esc_url( $uf_product->get_permalink() ) . '" data-product_id="' . esc_attr( absint( $uf_product->get_id() ) ) . '" data-product_variations="' . esc_attr( $variations_attr ) . '">';
												echo '<div class="variations">';

												foreach ( $attributes as $attribute_name => $options ) {
													$attribute_name_sz = sanitize_title( $attribute_name );
													?>
                                                    <div class="variation">
                                                        <div class="label">
                                                            <label for="<?php echo esc_attr( $attribute_name_sz ); ?>"><?php echo esc_html( wc_attribute_label( $attribute_name ) ); ?></label>
                                                        </div>
                                                        <div class="select">
															<?php
															wc_dropdown_variation_attribute_options( [
																'attribute'        => $attribute_name,
																'product'          => $uf_product,
																'show_option_none' => sprintf( /* translators: attribute name */ esc_html__( 'Choose %s', 'wpc-smart-upsell-funnel' ), esc_html( wc_attribute_label( $attribute_name ) ) )
															] );
															?>
                                                        </div>
                                                    </div>
												<?php }

												echo '<div class="reset">' . apply_filters( 'woocommerce_reset_variations_link', '<a class="reset_variations" href="#">' . esc_html__( 'Clear', 'wpc-smart-upsell-funnel' ) . '</a>' ) . '</div>';
												echo '</div>';
												echo '</div>';
											}
										}
									}
									?>
                                </div>
                            </div>
							<?php
						}
					}

					echo '</div>';
					echo '</div>';
					echo '<div class="wpcuf-uf-footer"></div>';
					echo '</div>';
					echo '</div>';

					return apply_filters( 'wpcuf_shortcode_uf', ob_get_clean(), $attrs );
				}

				function shortcode_ob( $attrs ) {
					$attrs = shortcode_atts( [
						'context' => 'checkout'
					], $attrs, 'wpcuf_ob' );

					if ( ! isset( WC()->cart ) || ! WC()->cart->get_cart() || empty( self::$ob_rules ) ) {
						return '';
					}

					$in_cart     = [];
					$ob_ids      = [];
					$ob_products = [];
					$ob_applied  = [];

					foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
						$in_cart[] = $cart_item['product_id'] ?? 0;

						if ( ! empty( $cart_item['wpcuf_ob'] ) ) {
							// already has order bump product
							$ob_applied = [
								'item_key' => $cart_item_key,
								'key'      => $cart_item['wpcuf_ob'],
								'id'       => $cart_item['product_id'],
								'product'  => $cart_item['data']
							];

							break;
						}
					}

					if ( empty( $ob_applied ) ) {
						foreach ( self::$ob_rules as $key => $rule ) {
							if ( self::check_timer( $rule ) && self::check_roles( $rule ) && self::check_apply_cart( $rule ) ) {
								$rule_products = self::get_products( $rule );

								if ( ! empty( $rule_products ) ) {
									foreach ( $rule_products as $rule_product ) {
										if ( $rule_product && ! in_array( $rule_product, $ob_ids ) && ( $rule_product_obj = wc_get_product( $rule_product ) ) ) {
											$exclude = ! $rule_product_obj->is_purchasable() || ! $rule_product_obj->is_in_stock();

											if ( apply_filters( 'wpcuf_uf_exclude', $exclude, $rule_product_obj ) ) {
												continue;
											}

											$ob_ids[]   = $rule_product;
											$price      = (float) $rule_product_obj->get_price();
											$rule_price = $rule['price'] ?? [];
											$rule_price = array_merge( [
												'type' => 'percentage',
												'val'  => '100',
											], $rule_price );

											if ( $rule_price['type'] === 'percentage' ) {
												$new_price = $price * floatval( $rule_price['val'] ) / 100;
											} else {
												$new_price = floatval( $rule_price['val'] );
											}

											$saved_amount     = $price > $new_price ? $price - $new_price : 0;
											$saved_percentage = $price > $new_price ? round( 100 * ( $price - $new_price ) / $price ) : 0;

											$ob_products[] = [
												'key'              => $key,
												'id'               => $rule_product,
												'price'            => $price,
												'new_price'        => $new_price,
												'saved_amount'     => $saved_amount,
												'saved_percentage' => $saved_percentage,
												'product'          => $rule_product_obj
											];
										}
									}
								}
							}
						}

						foreach ( $ob_products as $k => $p ) {
							if ( in_array( $p['id'], $in_cart ) ) {
								unset( $ob_products[ $k ] );
							}
						}

						if ( empty( $ob_products ) ) {
							return '';
						}

						$ob_order = self::get_setting( 'ob_order', 'default' );

						switch ( $ob_order ) {
							case 'price_asc':
								array_multisort( array_column( $ob_products, 'price' ), SORT_ASC, $ob_products );
								break;
							case 'price_desc':
								array_multisort( array_column( $ob_products, 'price' ), SORT_DESC, $ob_products );
								break;
							case 'new_price_asc':
								array_multisort( array_column( $ob_products, 'new_price' ), SORT_ASC, $ob_products );
								break;
							case 'new_price_desc':
								array_multisort( array_column( $ob_products, 'new_price' ), SORT_DESC, $ob_products );
								break;
							case 'saved_amount_asc':
								array_multisort( array_column( $ob_products, 'saved_amount' ), SORT_ASC, $ob_products );
								break;
							case 'saved_amount_desc':
								array_multisort( array_column( $ob_products, 'saved_amount' ), SORT_DESC, $ob_products );
								break;
							case 'saved_percentage_asc':
								array_multisort( array_column( $ob_products, 'saved_percentage' ), SORT_ASC, $ob_products );
								break;
							case 'saved_percentage_desc':
								array_multisort( array_column( $ob_products, 'saved_percentage' ), SORT_DESC, $ob_products );
								break;
						}

						// get single product
						$ob_product_data = $ob_products[0];

						if ( empty( $ob_product_data['key'] ) || empty( $ob_product_data['product'] ) || ! is_a( $ob_product_data['product'], 'WC_Product' ) ) {
							return '';
						}
					} else {
						$ob_product_data = $ob_applied;
					}

					$ob_product    = $ob_product_data['product'];
					$ob_product_id = $ob_product_data['id'];

					$ob_label = self::get_setting( 'ob_label', '' );
					$ob_text  = self::get_setting( 'ob_text', '' );

					if ( empty( $ob_label ) ) {
						$ob_label = esc_html__( 'Yes, add it in!', 'wpc-smart-upsell-funnel' );
					}

					if ( empty( $ob_text ) ) {
						$ob_text = esc_html__( 'We\'ve got something special for you!', 'wpc-smart-upsell-funnel' );
					}

					ob_start();

					echo '<div class="wpcuf-ob-wrap" data-key="' . esc_attr( $ob_product_data['key'] ) . '" data-product_id="' . esc_attr( $ob_product_data['id'] ) . '" data-item_key="' . esc_attr( $ob_product_data['item_key'] ?? '' ) . '" data-variation_id="0" data-attrs="">';
					echo '<div class="wpcuf-ob-inner">';
					echo '<div class="wpcuf-ob-header">';
					echo '<div class="wpcuf-ob-checkbox"><span class="wpcuf-ob-checkbox-input"><input id="wpcuf_ob_checkbox" type="checkbox" ' . ( ! empty( $ob_product_data['item_key'] ) ? 'checked' : '' ) . '/><span class="checkmark"></span></span><label class="wpcuf-ob-checkbox-label" for="wpcuf_ob_checkbox">' . esc_html( $ob_label ) . '</label></div>';
					echo '<div class="wpcuf-ob-price">';

					$ob_product_price = $ob_product->get_price_html();

					if ( isset( $ob_product_data['new_price'], $ob_product_data['price'] ) && ( $ob_product_data['new_price'] !== $ob_product_data['price'] ) ) {
						if ( $ob_product_data['new_price'] > $ob_product_data['price'] ) {
							$ob_product_price = wc_price( $ob_product_data['new_price'] );
						}

						if ( $ob_product_data['new_price'] < $ob_product_data['price'] ) {
							$ob_product_price = wc_format_sale_price( $ob_product_data['price'], $ob_product_data['new_price'] );
						}
					}

					echo wp_kses_post( apply_filters( 'wpcuf_ob_product_price', $ob_product_price, $ob_product, $attrs ) );

					echo '</div>';
					echo '</div><!-- /wpcuf-ob-header -->';
					echo '<div class="wpcuf-ob-content">';
					echo '<div class="wpcuf-ob-product">';

					if ( self::get_setting( 'ob_product_image', 'yes' ) === 'yes' ) {
						echo '<div class="wpcuf-ob-product-image">';

						if ( $ob_product->is_visible() && ( self::get_setting( 'ob_link', 'yes_blank' ) !== 'no' ) ) {
							echo wp_kses_post( '<a ' . ( self::get_setting( 'ob_link', 'yes_blank' ) === 'yes_popup' ? 'class="woosq-link no-ajaxy" data-id="' . esc_attr( $ob_product_id ) . '" data-context="wpcuf-ob"' : '' ) . ' href="' . esc_url( $ob_product->get_permalink() ) . '" ' . ( self::get_setting( 'ob_link', 'yes_blank' ) === 'yes_blank' ? 'target="_blank"' : '' ) . '>' . apply_filters( 'wpcuf_ob_product_image', $ob_product->get_image(), $ob_product, $attrs ) . '</a>' );
						} else {
							echo wp_kses_post( apply_filters( 'wpcuf_ob_product_image', $ob_product->get_image(), $ob_product, $attrs ) );
						}

						echo '</div>';
					}

					echo '<div class="wpcuf-ob-product-info">';

					if ( self::get_setting( 'ob_product_name', 'yes' ) === 'yes' ) {
						echo '<div class="wpcuf-ob-product-name">';

						if ( $ob_product->is_visible() && ( self::get_setting( 'ob_link', 'yes_blank' ) !== 'no' ) ) {
							echo wp_kses_post( '<a ' . ( self::get_setting( 'ob_link', 'yes_blank' ) === 'yes_popup' ? 'class="woosq-link no-ajaxy" data-id="' . esc_attr( $ob_product_id ) . '" data-context="wpcuf-ob"' : '' ) . ' href="' . esc_url( $ob_product->get_permalink() ) . '" ' . ( self::get_setting( 'ob_link', 'yes_blank' ) === 'yes_blank' ? 'target="_blank"' : '' ) . '>' . apply_filters( 'wpcuf_ob_product_name', $ob_product->get_name(), $ob_product, $attrs ) . '</a>' );
						} else {
							echo wp_kses_post( apply_filters( 'wpcuf_ob_product_name', $ob_product->get_name(), $ob_product, $attrs ) );
						}

						echo '</div>';
					}

					if ( self::get_setting( 'ob_product_desc', 'yes' ) === 'yes' ) {
						echo '<div class="wpcuf-ob-product-desc">' . esc_html( $ob_product->get_short_description() ) . '</div>';
					}

					echo '</div><!-- /wpcuf-ob-product-info -->';
					echo '</div><!-- /wpcuf-ob-product -->';
					echo '</div><!-- /wpcuf-ob-content -->';

					if ( ! empty( $ob_text ) ) {
						echo '<div class="wpcuf-ob-footer"><div class="wpcuf-ob-text">' . esc_html( $ob_text ) . '</div></div>';
					}

					echo '</div><!-- /wpcuf-ob-inner -->';
					echo '</div><!-- /wpcuf-ob-wrap -->';

					return apply_filters( 'wpcuf_shortcode_ob', ob_get_clean(), $attrs );
				}

				function show_uf() {
					echo do_shortcode( '[wpcuf_uf]' );
				}

				function show_ob() {
					echo do_shortcode( '[wpcuf_ob]' );
				}

				function action_links( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$st                   = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcuf&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'wpc-smart-upsell-funnel' ) . '</a>';
						$uf                   = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcuf&tab=uf' ) ) . '">' . esc_html__( 'Upsell Funnel', 'wpc-smart-upsell-funnel' ) . '</a>';
						$ob                   = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcuf&tab=ob' ) ) . '">' . esc_html__( 'Order Bump', 'wpc-smart-upsell-funnel' ) . '</a>';
						$links['wpc-premium'] = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcuf&tab=premium' ) ) . '" style="color: #c9356e">' . esc_html__( 'Premium Version', 'wpc-smart-upsell-funnel' ) . '</a>';
						array_unshift( $links, $st, $uf, $ob );
					}

					return (array) $links;
				}

				function row_meta( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$row_meta = [
							'support' => '<a href="' . esc_url( WPCUF_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-smart-upsell-funnel' ) . '</a>',
						];

						return array_merge( $links, $row_meta );
					}

					return (array) $links;
				}

				public static function check_roles( $rule ) {
					return true;
				}

				public static function check_timer( $rule ) {
					return true;
				}

				public static function check_apply_product( $rule, $product ) {
					if ( is_a( $product, 'WC_Product' ) ) {
						$product_id = $product->get_id();
					} elseif ( is_int( $product ) ) {
						$product_id = $product;
					} else {
						$product_id = 0;
					}

					if ( ! $product_id || empty( $rule['apply'] ) ) {
						return false;
					}

					switch ( $rule['apply'] ) {
						case 'any':
						case 'all':
							return true;
						case 'cart_subtotal':
						case 'cart_total':
						case 'cart_count':
							$number         = 0;
							$number_compare = $rule['apply_number']['compare'] ?? 'equal';
							$number_value   = $rule['apply_number']['value'] ?? 0;

							if ( $rule['apply'] === 'cart_subtotal' ) {
								$totals = WC()->cart->get_totals();
								$number = floatval( $totals['subtotal'] );
							}

							if ( $rule['apply'] === 'cart_total' ) {
								$totals = WC()->cart->get_totals();
								$number = floatval( $totals['total'] );
							}

							if ( $rule['apply'] === 'cart_count' ) {
								$number = WC()->cart->get_cart_contents_count();
							}

							switch ( $number_compare ) {
								case 'not_equal':
									if ( $number !== $number_value ) {
										return true;
									}

									break;
								case 'greater':
									if ( $number > $number_value ) {
										return true;
									}

									break;
								case 'greater_equal':
									if ( $number >= $number_value ) {
										return true;
									}

									break;
								case 'less':
									if ( $number < $number_value ) {
										return true;
									}

									break;
								case 'less_equal':
									if ( $number <= $number_value ) {
										return true;
									}

									break;
								default:
									if ( $number === $number_value ) {
										return true;
									}
							}

							return false;
						case 'products':
							if ( ! empty( $rule['apply_products'] ) && is_array( $rule['apply_products'] ) ) {
								if ( in_array( $product_id, $rule['apply_products'] ) ) {
									return true;
								}
							}

							return false;
						case 'combination':
							if ( ! empty( $rule['apply_combination'] ) && is_array( $rule['apply_combination'] ) ) {
								$match_all = true;

								foreach ( $rule['apply_combination'] as $combination ) {
									$match = true;

									if ( ! empty( $combination['apply'] ) && ! empty( $combination['compare'] ) && ! empty( $combination['terms'] ) && is_array( $combination['terms'] ) ) {
										if ( ( $combination['apply'] === 'product_cat' || $combination['apply'] === 'product_tag' ) && ( $_product = wc_get_product( $product_id ) ) && is_a( $_product, 'WC_Product_Variation' ) ) {
											$parent_id = $_product->get_parent_id();

											if ( ( $combination['compare'] === 'is' ) && ! has_term( $combination['terms'], $combination['apply'], $parent_id ) ) {
												$match = false;
											}

											if ( ( $combination['compare'] === 'is_not' ) && has_term( $combination['terms'], $combination['apply'], $parent_id ) ) {
												$match = false;
											}
										} else {
											if ( ( $combination['compare'] === 'is' ) && ! has_term( $combination['terms'], $combination['apply'], $product_id ) ) {
												$match = false;
											}

											if ( ( $combination['compare'] === 'is_not' ) && has_term( $combination['terms'], $combination['apply'], $product_id ) ) {
												$match = false;
											}
										}
									}

									if ( ! empty( $combination['apply'] ) && ( $combination['apply'] === 'cart_subtotal' || $combination['apply'] === 'cart_total' || $combination['apply'] === 'cart_count' ) && ! empty( $combination['number_compare'] ) && isset( $combination['number_value'] ) && $combination['number_value'] !== '' ) {
										$number       = 0;
										$number_value = floatval( $combination['number_value'] );

										if ( $combination['apply'] === 'cart_subtotal' ) {
											$totals = WC()->cart->get_totals();
											$number = floatval( $totals['subtotal'] );
										}

										if ( $combination['apply'] === 'cart_total' ) {
											$totals = WC()->cart->get_totals();
											$number = floatval( $totals['total'] );
										}

										if ( $combination['apply'] === 'cart_count' ) {
											$number = WC()->cart->get_cart_contents_count();
										}

										switch ( $combination['number_compare'] ) {
											case 'not_equal':
												if ( $number === $number_value ) {
													$match = false;
												}

												break;
											case 'greater':
												if ( $number <= $number_value ) {
													$match = false;
												}

												break;
											case 'greater_equal':
												if ( $number < $number_value ) {
													$match = false;
												}

												break;
											case 'less':
												if ( $number >= $number_value ) {
													$match = false;
												}

												break;
											case 'less_equal':
												if ( $number > $number_value ) {
													$match = false;
												}

												break;
											default:
												if ( $number !== $number_value ) {
													$match = false;
												}
										}
									}

									$match_all &= $match;
								}

								return $match_all;
							}

							return false;
						default:
							if ( ! empty( $rule['apply_terms'] ) && is_array( $rule['apply_terms'] ) ) {
								if ( ( $rule['apply'] === 'product_cat' || $rule['apply'] === 'product_tag' ) && ( $_product = wc_get_product( $product_id ) ) && is_a( $_product, 'WC_Product_Variation' ) ) {
									$product_id = $_product->get_parent_id();
								}

								if ( has_term( $rule['apply_terms'], $rule['apply'], $product_id ) ) {
									return true;
								}
							}

							return false;
					}
				}

				public static function check_apply_cart( $rule, $cart_contents = null ) {
					if ( ! $cart_contents ) {
						$cart_contents = WC()->cart->get_cart();
					}

					foreach ( $cart_contents as $cart_item ) {
						if ( empty( $cart_item['wpcuf_uf'] ) && empty( $cart_item['wpcuf_ob'] ) && self::check_apply_product( $rule, $cart_item['product_id'] ) ) {
							// don't check upsell or order bump products
							return true;
						}
					}

					return false;
				}

				function update_cart_item() {
					$cart_contents = WC()->cart->get_cart();

					foreach ( $cart_contents as $cart_item_key => $cart_item ) {
						if ( ! empty( $cart_item['wpcuf_uf'] ) ) {
							$key         = $cart_item['wpcuf_uf'];
							$rule        = self::$uf_rules[ $key ] ?? [];
							$product_obj = wc_get_product( ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'] );
							$ori_price   = floatval( $product_obj->get_price() );

							if ( ! empty( $rule ) && self::check_timer( $rule ) && self::check_roles( $rule ) && self::check_apply_cart( $rule ) ) {
								$rule_price = $rule['price'] ?? [];
								$rule_price = array_merge( [
									'type' => 'percentage',
									'val'  => '100',
								], $rule_price );

								if ( $rule_price['type'] === 'percentage' ) {
									$new_price = $ori_price * floatval( $rule_price['val'] ) / 100;
								} else {
									$new_price = floatval( $rule_price['val'] );
								}

								if ( $new_price !== $ori_price ) {
									$cart_item['data']->set_price( $new_price );
									$cart_item['wpcuf_uf_price'] = $new_price;
								}

								$cart_item['wpcuf_uf_apply'] = true;
							} else {
								$cart_item['data']->set_price( $ori_price );
								$cart_item['wpcuf_uf_apply'] = false;
							}

							$cart_contents[ $cart_item_key ] = $cart_item;
						} else if ( ! empty( $cart_item['wpcuf_ob'] ) ) {
							$key         = $cart_item['wpcuf_ob'];
							$rule        = self::$ob_rules[ $key ] ?? [];
							$product_obj = wc_get_product( ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'] );
							$ori_price   = floatval( $product_obj->get_price() );

							if ( ! empty( $rule ) && self::check_timer( $rule ) && self::check_roles( $rule ) && self::check_apply_cart( $rule ) ) {
								$rule_price = $rule['price'] ?? [];
								$rule_price = array_merge( [
									'type' => 'percentage',
									'val'  => '100',
								], $rule_price );

								if ( $rule_price['type'] === 'percentage' ) {
									$new_price = $ori_price * floatval( $rule_price['val'] ) / 100;
								} else {
									$new_price = floatval( $rule_price['val'] );
								}

								if ( $new_price !== $ori_price ) {
									$cart_item['data']->set_price( $new_price );
									$cart_item['wpcuf_ob_price'] = $new_price;
								}

								$cart_item['wpcuf_ob_apply'] = true;
							} else {
								$cart_item['data']->set_price( $ori_price );
								$cart_item['wpcuf_ob_apply'] = false;
							}

							$cart_contents[ $cart_item_key ] = $cart_item;
						}
					}

					WC()->cart->set_cart_contents( $cart_contents );
				}

				function before_mini_cart_contents() {
					WC()->cart->calculate_totals();
				}

				function before_calculate_totals( $cart_object ) {
					if ( ! defined( 'DOING_AJAX' ) && is_admin() ) {
						// This is necessary for WC 3.0+
						return;
					}

					foreach ( $cart_object->cart_contents as $cart_item ) {
						if ( ! empty( $cart_item['wpcuf_uf_apply'] ) && isset( $cart_item['wpcuf_uf_price'] ) ) {
							$cart_item['data']->set_price( $cart_item['wpcuf_uf_price'] );
						}

						if ( ! empty( $cart_item['wpcuf_ob_apply'] ) && isset( $cart_item['wpcuf_ob_price'] ) ) {
							$cart_item['data']->set_price( $cart_item['wpcuf_ob_price'] );
						}
					}
				}

				function order_review_fragments( $fragments ) {
					$fragments['.wpcuf-ob-wrap'] = do_shortcode( '[wpcuf_ob]' );

					return $fragments;
				}

				function create_order_line_item( $order_item, $cart_item_key, $values ) {
					// use _ to hide the data
					if ( isset( $values['wpcuf_uf'] ) ) {
						$order_item->update_meta_data( '_wpcuf_uf', $values['wpcuf_uf'] );
					}

					if ( isset( $values['wpcuf_uf_price'] ) ) {
						$order_item->update_meta_data( '_wpcuf_uf_price', $values['wpcuf_uf_price'] );
					}

					if ( isset( $values['wpcuf_ob'] ) ) {
						$order_item->update_meta_data( '_wpcuf_ob', $values['wpcuf_ob'] );
					}

					if ( isset( $values['wpcuf_ob_price'] ) ) {
						$order_item->update_meta_data( '_wpcuf_ob_price', $values['wpcuf_ob_price'] );
					}
				}

				function hidden_order_itemmeta( $hidden ) {
					return array_merge( $hidden, [
						'_wpcuf_uf',
						'_wpcuf_uf_price',
						'_wpcuf_ob',
						'_wpcuf_ob_price'
					] );
				}

				public static function get_settings() {
					return apply_filters( 'wpcuf_get_settings', self::$settings );
				}

				public static function get_setting( $name, $default = false ) {
					if ( ! empty( self::$settings ) && isset( self::$settings[ $name ] ) ) {
						$setting = self::$settings[ $name ];
					} else {
						$setting = get_option( 'wpcuf_' . $name, $default );
					}

					return apply_filters( 'wpcuf_get_setting', $setting, $name, $default );
				}

				public static function generate_key() {
					$key         = '';
					$key_str     = apply_filters( 'wpcuf_key_characters', 'abcdefghijklmnopqrstuvwxyz0123456789' );
					$key_str_len = strlen( $key_str );

					for ( $i = 0; $i < apply_filters( 'wpcuf_key_length', 4 ); $i ++ ) {
						$key .= $key_str[ random_int( 0, $key_str_len - 1 ) ];
					}

					if ( is_numeric( $key ) ) {
						$key = self::generate_key();
					}

					return apply_filters( 'wpcuf_generate_key', $key );
				}

				public static function sanitize_array( $arr ) {
					foreach ( (array) $arr as $k => $v ) {
						if ( is_array( $v ) ) {
							$arr[ $k ] = self::sanitize_array( $v );
						} else {
							$arr[ $k ] = sanitize_post_field( 'post_content', $v, 0, 'display' );
						}
					}

					return $arr;
				}

				function write_log( $log ) {
					if ( is_array( $log ) || is_object( $log ) ) {
						error_log( print_r( $log, true ) );
					} else {
						error_log( $log );
					}
				}
			}

			return Wpcuf::instance();
		}

		return null;
	}
}

if ( ! function_exists( 'wpcuf_notice_wc' ) ) {
	function wpcuf_notice_wc() {
		?>
        <div class="error">
            <p><strong>WPC Smart Upsell Funnel</strong> require WooCommerce version 3.0 or greater.</p>
        </div>
		<?php
	}
}
