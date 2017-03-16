<?php

/**
 *
 * WP-SILO plugin settings page
 * Description: This file builds the plugin admin settings page where users can configure the plugin options to use the plugin features.
 *
 * @package WP-SILO
 * @author Shivanand Sharma
 * @since 1.0
 *
 */
 

/* Bail if accessing directly */
if ( !defined( 'ABSPATH' ) ) {
	wp_die( "Sorry, you are not allowed to access this page directly." );
}


/**
 * Class that contructs the entire plugin settings page
 */
 
class WPSILO_Settings {
	
	public $settings_field = WPSILO_SETTINGS;
	
	function __construct() {		
		add_action( 'admin_menu', array( $this, 'wpsilo_settings_menu' ) );
        add_action( 'admin_init', array( $this, 'wpsilo_register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'wpsilo_admin_styles' ) );
	}
	
	function wpsilo_settings_menu() {
		
		add_options_page(
			WPSILO_PLUGIN_NAME, 
			WPSILO_PLUGIN_NAME, 
			'manage_options', 
			WPSILO_SETTINGS, 
			array( $this, 'wpsilo_settings_page' )
        );
		
	}
	
	function wpsilo_settings_page() {
		
		if( !current_user_can( 'manage_options' ) )
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );

		else {
			?>
			<div class="wrap wpsilo-admin">
				<h2><?php printf( __( '%s', 'wp-silo' ), WPSILO_PLUGIN_NAME ); ?></h2>
				<form method="post" action="options.php">
				<?php
					$this->option = get_option( WPSILO_SETTINGS );
					settings_fields( $this->settings_field );
					do_settings_sections( WPSILO_SETTINGS );
					submit_button();
				?>
				</form>
			</div>
			<?php
		}
		
	}
	
	function wpsilo_register_settings() {        
        
		register_setting( $this->settings_field, $this->settings_field, array( $this, 'wpsilo_validate_options' ) );
		
		/** Sets options defaults on plugin activation (saves the options in the database) **/
		$default_settings = $this->wpsilo_defaults();
		add_option( $this->settings_field, $default_settings );

		add_settings_section(
            'wpsilo-admin-options',
            __( 'WP-SILO Settings', 'wp-silo' ),
            array( $this, 'wpsilo_settings_box' ),
            WPSILO_SETTINGS
        );
		
		// Setting to disable the child categories from showing up on parent category archives
		add_settings_field(
            'wpsilo-disable-child-cat',
            __( 'Disable child category posts from showing up on parent category archives?', 'wp-silo' ),
            array( $this, 'wpsilo_disable_child_cat' ),
            WPSILO_SETTINGS,
            'wpsilo-admin-options',
			array( 'label_for' => WPSILO_SETTINGS . '[disable_child_cat]' )
        );
		
		// Setting to include link to the child categories on parent category archives
		add_settings_field(
            'wpsilo-include-child-cat-links',
            __( 'Display child category links and description on parent category archives?', 'wp-silo' ),
            array( $this, 'wpsilo_include_child_cat_links' ),
            WPSILO_SETTINGS,
            'wpsilo-admin-options',
			array( 'label_for' => WPSILO_SETTINGS . '[include_child_cat_links]' )
        );
		
		$wpseo_options = get_option( 'wpseo_permalinks' );
		$wpseo_stripped_cat = ( !empty( $wpseo_options ) ) ? ( isset( $wpseo_options['stripcategorybase'] ) ? $wpseo_options['stripcategorybase'] : false ) : false;
		
		// Setting to remove category slug in the URLs for category posts
		if( function_exists( 'yoast_breadcrumb' ) && $wpseo_stripped_cat == false ) {
			add_settings_field(
				'wpsilo-remove-cat-base',
				__( 'Remove category base from category URL?', 'wp-silo' ),
				array( $this, 'wpsilo_remove_cat_base' ),
				WPSILO_SETTINGS,
				'wpsilo-admin-options',
				array( 'label_for' => WPSILO_SETTINGS . '[remove_category_base]' )
			);
		}
		
    }
	
	function wpsilo_validate_options( $input ) {
		
		$defaults = $this->wpsilo_defaults();
		
		if ( !is_array( $input ) ) // Check if valid input
			return $defaults;

		$output = array();

		foreach ( $defaults as $key=>$value ) {
			if ( empty ( $input[$key] ) ) {
				$output[$key] = $value;
			}		
			else {
				$output[$key] = $input[$key];
			}
		}
		
		return $output;
		
	}
	
	function wpsilo_defaults() {
		
		$defaults = array(
			'disable_child_cat'			=> 0,
			'include_child_cat_links'	=> 0,
			'remove_category_base'		=> 0,
			'enable_extra_edge'			=> 0,
		);
		
		return $defaults;
		
	}
	
	// Builds the markup for the settings section
	function wpsilo_settings_box() {
		
		?>
		<p><?php _e( 'These settings allow you to achieve a SILO content architecture on your website. Use these options to tweak the content architecture on your site to your taste.', 'wp-silo' ); ?></p>
		<?php
		
	}
	
	// Builds the markup for the settings to disable the child category psots on parent archive settings
	function wpsilo_disable_child_cat() {
		
		?>
		<input type="checkbox" id="<?php wpsilo_option( 'disable_child_cat' ); ?>" name="<?php wpsilo_option( 'disable_child_cat' ); ?>" value="1" <?php checked( wpsilo_get_option( 'disable_child_cat' ), 1 ); ?> />
		<?php
		
	}
	
	// Builds the markup for the setting to include the child category links on parent category archives loop
	function wpsilo_include_child_cat_links() {
		
		?>
		<input type="checkbox" id="<?php wpsilo_option( 'include_child_cat_links' ); ?>" name="<?php wpsilo_option( 'include_child_cat_links' ); ?>" value="1" <?php checked( wpsilo_get_option( 'include_child_cat_links' ), 1 ); ?> />
		<?php
		
	}
	
	// Builds the markup for the setting to remove category base from category URL
	function wpsilo_remove_cat_base() {
		
		?>
		<input type="checkbox" id="<?php wpsilo_option( 'remove_category_base' ); ?>" name="<?php wpsilo_option( 'remove_category_base' ); ?>" value="1" <?php checked( wpsilo_get_option( 'remove_category_base' ), 1 ); ?> />
		<?php
		
	}
	
	
	function wpsilo_admin_styles() {
		
		$screen = get_current_screen();
		if( $screen->id == 'settings_page_' . WPSILO_SETTINGS ) {
			wp_enqueue_style( 'wpsilo-stylesheet', WPSILO_PLUGIN_URL . 'admin/css/admin-style.css' );
		}
		
	}
	
}

$wpsilo_init_admin = new WPSILO_Settings();
