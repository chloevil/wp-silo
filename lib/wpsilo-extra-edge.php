<?php

/**
 *
 * WP-SILO treat post as page UI & Save functionality
 * Description: This file controls the admin functionality of the "treat post as page" functionality
 *
 * @package WP-SILO
 * @author Shivanand Sharma
 * @since 1.0
 *
 */

add_action( 'add_meta_boxes', 'wpsilo_extra_edge_box' );

function wpsilo_extra_edge_box() {
	add_meta_box( 'wpsilo-extra-edge', __( 'WP SILO Extra Edge', 'wp-silo' ), 'wpsilo_extra_edge', 'post', 'side', 'high' );
}


/* Add metaboxes on posts edit screen to enable users to hide posts from RSS feeds and / or archives */
	
function wpsilo_extra_edge( $post ) {
	
	$wpsilo_extra_edge = get_post_meta( $post->ID, '_wpsilo_extra_edge', true );
	
	$post_as_page = isset( $wpsilo_extra_edge['wpsilo-post-as-page'] ) ? $wpsilo_extra_edge['wpsilo-post-as-page'] : false;
	
	wp_nonce_field( 'wpsilo_save_settings', 'wpsilo_settings_nonce' );
	
	?>
	<p><?php _e( 'Use these settings to hide this post from showing up in RSS feeds, archive listings etc.', 'wp-silo' ); ?></p>
	
	<p>
	<input type="checkbox" id="wpsilo-post-as-page" name="wpsilo-post-as-page" value="1" <?php checked( $post_as_page, true ); ?> />
	<label for="wpsilo-post-as-page"><?php _e( 'Treat this post as a page', 'wp-silo' ); ?></label>
	</p>
	
	
	<?php
	
}


add_action( 'save_post', 'wpsilo_extra_edge_save' );

function wpsilo_extra_edge_save( $post_id ) {
		
	// Check if our nonce is set.
	if ( !isset( $_POST['wpsilo_settings_nonce'] ) ) {
		return;
	}
	// Verify that the nonce is valid.
	if ( !wp_verify_nonce( $_POST['wpsilo_settings_nonce'], 'wpsilo_save_settings' ) ) {
		return;
	}
	// If this is an autosave, our form has not been submitted, so we don't want to do anything.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	
	if ( !current_user_can( 'edit_post', $post_id ) ) {
			return;
	}
	
	// It is safe for us to save the data now
	$wpsilo_extra_edge = array();
	
	$wpsilo_extra_edge['wpsilo-post-as-page'] = isset( $_POST['wpsilo-post-as-page'] ) ? true : false;
	
	//update_post_meta( $post_id, 'comment_status', '0' );
	update_post_meta( $post_id, '_wpsilo_extra_edge', $wpsilo_extra_edge );
	
}


//add_action('genesis_after','wps_custom');

function wps_custom(){
	global $post;
	llog(get_post_custom($post->ID));
}


// Disable comment from if this post is treated like a page

add_filter( 'comments_open', 'wpsilo_disable_comments', 10, 2 );

function wpsilo_disable_comments($open, $post_id){
	
	if(is_singular()) {
		$pages = wpsilo_excluded_posts();
		
		$pages = $pages['wpsilo-post-as-page'];

		if(empty($post_id)) {
			$post_id = get_the_ID();
		}
		
		if($pages && in_array($post_id,$pages)) {
			return false;
		}
	}

	return $open;

}


add_filter( 'hybrid_attr_post', 'wp_silo_entry_schema', 99 );
add_filter( 'genesis_attr_entry', 'wp_silo_entry_schema', 99 );

function wp_silo_entry_schema($attributes){
	if(is_singular() && 'post' === get_post_type()) {

		$wpsilo_extra_edge = get_post_meta( get_the_ID(), '_wpsilo_extra_edge', true );
	
		//llog(!empty($wpsilo_extra_edge['wpsilo-post-as-page']));

		$post_as_page = !empty( $wpsilo_extra_edge['wpsilo-post-as-page'] ) ? $wpsilo_extra_edge['wpsilo-post-as-page'] : false;
		if($post_as_page) {
			$attributes['itemtype']  = 'http://schema.org/CreativeWork';
			unset($attributes['itemprop']);
		}
	}
	return $attributes;
}

add_filter( 'hybrid_attr_body', 'wp_silo_content_schema', 99 );
add_filter( 'genesis_attr_body', 'wp_silo_content_schema', 99 );

function wp_silo_content_schema($attributes){

	if(is_singular() && 'post' === get_post_type()) {

		$wpsilo_extra_edge = get_post_meta( get_the_ID(), '_wpsilo_extra_edge', true );

		$post_as_page = !empty( $wpsilo_extra_edge['wpsilo-post-as-page'] ) ? $wpsilo_extra_edge['wpsilo-post-as-page'] : false;

		if($post_as_page) {
			$attributes['itemtype'] = 'http://schema.org/WebPage';
		}
	}
	return $attributes;
}


