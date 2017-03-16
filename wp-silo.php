<?php

/*
 *
 *	Plugin Name: WP SILO
 *	Plugin URI: https://www.binaryturf.com/
 *	Description: This plugin helps you to establish a well-structured SILO architecture on your WordPress website.
 *	Version: 1.0
 *	Author: Shivanand Sharma
 *	Author URI: https://www.binaryturf.com/
 *	License: GPL-2.0+
 *	License URI: http://www.opensource.org/licenses/gpl-license.php
 *	Text Domain: wp-silo
 *
 */
 
 
/* Bail if accessing directly */
if ( !defined( 'ABSPATH' ) ) {
	wp_die( "Sorry, you are not allowed to access this page directly." );
}

 
define( 'WPSILO_PLUGIN_NAME', 'WP SILO' );
define( 'WPSILO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPSILO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPSILO_SETTINGS', 'wpsilo-settings' );


register_deactivation_hook( __FILE__, 'wpsilo_deactivated' );

function wpsilo_deactivated() {
		
	//if( function_exists( 'wpsilo_flush_rewrite_rules' ) ) {
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	//}
	
}

require_once( WPSILO_PLUGIN_DIR . 'admin/wpsilo-admin.php' );

function wpsilo_option( $field ) {
	printf( '%s[%s]', WPSILO_SETTINGS, $field );
}

function wpsilo_get_option( $field ) {
	$options = get_option( WPSILO_SETTINGS );
	$opval = false;
	
	if( $options ) {
		$opval = $options[$field];
	}
	else {
		return $opval;
	}
	
	return $opval;		
}

require_once( WPSILO_PLUGIN_DIR . 'lib/wpsilo-functions.php' );


require_once( WPSILO_PLUGIN_DIR . 'lib/wpsilo-extra-edge.php' );



// return the ids of excluded posts (posts that need to be treated like pages)

function wpsilo_excluded_posts() {
		
	global $wpdb;
	$wpsilo_extra_edge_meta = '_wpsilo_extra_edge';
	
	$post_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT post_id FROM $wpdb->postmeta WHERE meta_key=%s",
			$wpsilo_extra_edge_meta
		)
	);
	
	if( empty( $post_ids ) )
		return;
	
	foreach( $post_ids as $post_id ) {
		$wpsilo_meta = get_post_meta( $post_id, '_wpsilo_extra_edge', true );
		
		if(array_key_exists('wpsilo-post-as-page', $wpsilo_meta) && !empty($wpsilo_meta['wpsilo-post-as-page'])) {
			$wpsilo_posts['wpsilo-post-as-page'][] = $post_id;
		}
	}
	
	return empty($wpsilo_posts)?false:$wpsilo_posts;
	
}


/**
 * 
 * Disable hierarchy for pages
 * 
 * */

//add_action( 'registered_post_type', 'wp_silo_mod_page_cpt', 10, 2 );

function wp_silo_mod_page_cpt( $post_type, $args ) {
    // Make sure we're only editing the post type we want
    if ( 'page' != $post_type )
        return;

    // Set menu icon
    $args->hierarchical = false;
    $args->with_front = true;

    // Modify post type object
    $wp_post_types[$post_type] = $args;
}
 

/**
 * 
 * Kills the rule that matches pages and triggers 404 if a page is reached without its category permalink
 * 
 * */

//add_filter('rewrite_rules_array', 'kill_page_match');

function kill_page_match($rules){
    unset($rules['(.?.+?).html(/[0-9]+)?/?$']);
    return $rules;
}

/* Add a rule that doesn't overwrite the one meant to match "post". Also, if you don't do this then is_page() doesn't return true on singular-pages. */

add_action( 'init', 'wpsilo_rewrite_basic' );

function wpsilo_rewrite_basic() {
	//add_rewrite_rule( '(.+?)/([^/]+).html?(/[0-9]+)?/?$', 'index.php?category_name=$matches[1]&pagename=$matches[2]&page=$matches[3]', 'top' );
	add_rewrite_rule( '(.+?)/([^/]+)?(/[0-9]+)?/?$', 'index.php?category_name=$matches[1]&pagename=$matches[2]&page=$matches[3]', 'top' );
}


/**
 * 
 * Inserts %category% into page permalink structure
 * 
 * */

add_filter( 'page_link', 'wpsilo_mod_page_link', 10, 3 );

function wpsilo_mod_page_link( $link, $id, $sample ) {

	$leavename = false;	
	
	if ( 'page' == get_option( 'show_on_front' ) && $id == get_option( 'page_on_front' ) )
		return home_url('/');

	//global $wp_rewrite;
	//llog($wp_rewrite->get_page_permastruct());
//function wpsilo_mod_page_link( $id = 0, $leavename = false ) {
	$rewritecode = array(
		'%year%',
		'%monthnum%',
		'%day%',
		'%hour%',
		'%minute%',
		'%second%',
		$leavename? '' : '%postname%',
		'%post_id%',
		'%category%',
		'%author%',
		$leavename? '' : '%pagename%',
	);

	if ( is_object($id) && isset($id->filter) && 'sample' == $id->filter ) {
		$post = $id;
		$sample = true;
	} else {
		$post = get_post($id);
		$sample = false;
	}

	if ( empty($post->ID) )
		return false;

	if ( $post->post_type != 'page' ) { 
		return $link; 
	}

	$permalink = get_option('permalink_structure');

	if ( '' != $permalink && !in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft', 'future' ) ) ) {
		$unixtime = strtotime($post->post_date);

		$category = '';
		if ( strpos($permalink, '%category%') !== false ) {
			$cats = get_the_category($post->ID);
			if ( $cats ) {
				usort($cats, '_usort_terms_by_ID'); // order by ID

				$category_object = $cats[0];

				$category_object = get_term( $category_object, 'category' );
				$category = $category_object->slug;
				if ( $parent = $category_object->parent )
					$category = get_category_parents($parent, false, '/', true) . $category;
			}

			// allow pages to be published without a category
			if ( empty($category) ) {
				//$default_category = get_term( get_option( 'default_category' ), 'category' );
				//$category = is_wp_error( $default_category ) ? '' : $default_category->slug;
				$category = '';
			}
		}

		$author = '';
		if ( strpos($permalink, '%author%') !== false ) {
			$authordata = get_userdata($post->post_author);
			$author = $authordata->user_nicename;
		}

		$date = explode(" ",date('Y m d H i s', $unixtime));
		$rewritereplace =
		array(
			$date[0],
			$date[1],
			$date[2],
			$date[3],
			$date[4],
			$date[5],
			$post->post_name,
			$post->ID,
			$category,
			$author,
			$post->post_name,
		);

		$permalink = home_url( str_replace($rewritecode, $rewritereplace, $permalink) );
		//$permalink = user_trailingslashit($permalink, 'single');
	} else { // if they're not using the fancy permalink option
		$permalink = home_url('?p=' . $post->ID);
	}
	if(strpos( $link, '%pagename%' )) {		// if we don't do this then the slud is not editable in the page editor
		$permalink = str_replace( $post->post_name,'%pagename%', $permalink );
	}
	return $permalink;
}

// for directory links, add a trailing slash
//add_filter( 'user_trailingslashit', 'wpsilo_fix_category_trailingslash', 9999, 2 );

function wpsilo_fix_category_trailingslash($s='',$t = 'single') {
	if( $t == 'category' ) {
		$s = trailingslashit($s);
	}
	return $s;
}


// disable rearrangement of checked cats to the top
add_filter( 'wp_terms_checklist_args', 'wpsilo_checked_cat_fix', 10, 2);

function wpsilo_checked_cat_fix( $args, $post_id ) {
	$args['checked_ontop'] = false;
	return $args;
}


// Add html to pages
# Removed the negative priority previously supplied owing to this: https://core.trac.wordpress.org/ticket/30862
//add_action('init', 'wpsilo_html_page_permalink', 1);

function wpsilo_html_page_permalink() {
	global $wp_rewrite;
	if ( !strpos($wp_rewrite->get_page_permastruct(), '.html')){
		$wp_rewrite->page_structure = $wp_rewrite->page_structure . '.html';
	}
}



// Add category and tags to pages
add_action( 'init', 'add_taxonomies_to_pages' );
function add_taxonomies_to_pages() {
      register_taxonomy_for_object_type( 'post_tag', 'page' );
      register_taxonomy_for_object_type( 'category', 'page' );
  } 


// Add Page as a post_type in the archive.php and tag.php. but only if is_singular

if ( ! is_admin() ) {
	add_action( 'pre_get_posts', 'category_and_tag_archives' );
}

function category_and_tag_archives( $wp_query ) {
	if(!$wp_query->is_singular) {
		return;
	}

	$my_post_array = array('post','page');

	if ( $wp_query->get( 'category_name' ) || $wp_query->get( 'cat' ) ) {
		$wp_query->set( 'post_type', $my_post_array );
	}

	if ( $wp_query->get( 'tag' ) ) {
		$wp_query->set( 'post_type', $my_post_array );
	}

}


//add_action('genesis_before','silo_debug',9999);

function silo_debug(){
	if(!current_user_can('delete_plugins')) return;

	global $wp_query;
	llog($wp_query);
}

//add_filter('wp_redirect','silo_redirect_debug',999,2);

function silo_redirect_debug($location, $status){
	if(!current_user_can('delete_plugins')) return $location;
llog($location);
die();
}

//https://www.binaryturf.com/⁠⁠⁠?p=17921