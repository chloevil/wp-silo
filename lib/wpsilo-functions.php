<?php

/**
 * Base plugin class that includes all the plugin core functions
 * Manages the activation / deactivation of the plugin and impelements the SILO functionality
 *
 * @author Shivanand Sharma
 * @since 1.0
 */
 
class BT_WPSILO_Exec {
	
	public $wpsilo_settings_field = WPSILO_SETTINGS;
	
	function __construct() {
		
		add_action( 'parse_tax_query', array( $this, 'wpsilo_parent_exclude_child_cats' ) );
		//add_filter( 'pre_get_posts', array( $this, 'wpsilo_parent_exclude_child_cats' ) );
		add_action( 'loop_start' , array( $this, 'wpsilo_insert_child_cats' ) );
		add_filter( 'user_trailingslashit', array( $this, 'wpsilo_trailingslash_cat_archives' ), 9999, 2 );
		
		$wpsilo_settings = get_option( $this->wpsilo_settings_field );
		if( !empty( $wpsilo_settings ) && wpsilo_get_option( 'remove_category_base' ) == true ) {
			add_action( 'created_category', array( $this, 'wpsilo_flush_rewrite_rules' ) );
			add_action( 'edited_category', array( $this, 'wpsilo_flush_rewrite_rules' ) );
			add_action( 'delete_category', array( $this, 'wpsilo_flush_rewrite_rules' ) );
			add_filter( 'query_vars', array( $this, 'wpsilo_query_vars' ) );
			add_filter( 'request', array( $this, 'wpsilo_redirect' ) );
			add_filter( 'category_link', array( $this, 'wpsilo_remove_cat_link' ) );
			add_filter( 'category_rewrite_rules', array( $this, 'wpsilo_category_rewrite_rules' ) );
		}
		
		//add_action( 'init', array( $this, 'wpsilo_flush_rewrite_rules' ), 999 );
		
		
		add_filter( 'pre_get_posts', array( $this, 'wpsilo_treat_post_as_page' ) );
		
		
	}
	
	function wpsilo_flush_rewrite_rules() {
		add_action( 'shutdown', 'flush_rewrite_rules' );		
	}
	
	/* Exclude all the sub-category posts from parent category archive */
	
	public function wpsilo_parent_exclude_child_cats( $query ) {
		
		$exclude_child_cats = wpsilo_get_option( 'disable_child_cat' );
		if( !$exclude_child_cats )
			return $query;
		
		/*
		if( $query->is_category ) {
			$current_queried_obj = get_queried_object();
			$child_categories = (array) get_term_children( $current_queried_obj->term_id, 'category' );	
			// Bail for admin screen queries
			if( !$query->is_admin ) {
				$query->set( 'category__not_in', array_merge( $child_categories ) ); // Set the query to exclude the child categories
			}
		}
		*/
		
		if ( $query->is_category() ) {
			$query->tax_query->queries[0]['include_children'] = 0;
		}
		
		return $query;
		
	}
	
	/* Insert the links to top-level child categories on parent category archives */
	
	public function wpsilo_insert_child_cats() {
		
		$insert_child_cats = wpsilo_get_option( 'include_child_cat_links' );
		if( !$insert_child_cats )
			return;
		
		if( is_category() ) {
			$current_category = get_query_var( 'cat' );
			$args = apply_filters( 'wpsilo_subcategory_args', array(
				'parent'		=> $current_category,
				'hierarchical'	=> 0,
			) );
			$sub_categories = get_categories( $args );
			//glps_log($sub_categories);
			
			if( empty( $sub_categories ) )
				return;
			
			$outer_wrapper_open = apply_filters( 'wpsilo_subcat_outer_markup_open', '<div class="subcategory-container">' );
			$outer_wrapper_close = apply_filters( 'wpsilo_subcat_outer_markup_close', '</div>' );
			
			$title_markup_open = apply_filters( 'wpsilo_subcat_title_markup_open', '<h2 class="subcategory-title">' );
			$title_markup_close = apply_filters( 'wpsilo_subcat_title_markup_close', '</h2>' );
			
			$description_markup_open = apply_filters( 'wpsilo_subcat_description_markup_open', '<p class="subcategory-description">' );
			$description_markup_close = apply_filters( 'wpsilo_subcat_description_markup_close', '</p>' );
			
			$count = 1;
			
			do_action( 'wpsilo_subcategories_before' );
			
			echo $outer_wrapper_open;
			
			foreach( $sub_categories as $sub_category ) {
				
				//glps_log($sub_category);
				$subcat_wrapper_open = apply_filters( 'wpsilo_subcategory_wrapper_open', '<div class="subcategory-' . $sub_category->slug . '">' );
				$subcat_wrapper_close = apply_filters( 'wpsilo_subcategory_wrapper_close', '</div>' );
				
				do_action( 'wpsilo_single_subcat_' . $count  . '_before' );
				echo $subcat_wrapper_open;
				echo $title_markup_open . '<a href="' . get_category_link( $sub_category ) . '">' . $sub_category->name . '</a>' . $title_markup_close;
				echo $description_markup_open . $sub_category->description . $description_markup_close;
				echo $subcat_wrapper_close;
				do_action( 'wpsilo_single_subcat_' . $count  . '_after' );
				
				$count++;
				
			}
			
			echo $outer_wrapper_close;
			
			do_action( 'wpsilo_subcategories_after' );
		}
		
	}
	
	/* Add a trailing slash to all URLs with 'category' in the slug */
	
	public function wpsilo_trailingslash_cat_archives( $url, $url_type ) {
		
		if( $url_type == 'category' ) {
			$url = trailingslashit( $url );
		}
		
		return $url;
		
	}
	
	/* Helper function to add query vars for category redirects */
	
	public function wpsilo_query_vars( $vars ) {
		$vars[] = 'wpsilo_strip_category';
		return $vars;
	}
	
	/* Establish redirects to newer permalink rules for category */
	
	public function wpsilo_redirect( $vars ) {
		
		if ( isset( $vars['wpsilo_strip_category'] ) ) {
			$new_url = trailingslashit( get_option( 'home' ) ) . user_trailingslashit( $vars['wpsilo_strip_category'], 'category' );

			wp_redirect( $new_url, 301 );
			exit;
		}

		return $vars;
		
	}
	
	/* Remove category base */
	
	public function wpsilo_remove_cat_link( $link ) {
		
		$current_cat_base = get_option( 'category_base' );

		if ( '' == $current_cat_base ) {
			$current_cat_base = 'category';
		}

		// Eliminate initial slash
		if ( '/' == substr( $current_cat_base, 0, 1 ) ) {
			$current_cat_base = substr( $current_cat_base, 1 );
		}

		$current_cat_base .= '/';

		return preg_replace( '`' . preg_quote( $current_cat_base, '`' ) . '`u', '', $link, 1 );
		
	}
	
	/* Rewrite rules to establish category slug elimination */
	
	public function wpsilo_category_rewrite_rules() {
		
		global $wp_rewrite;

		$rewrite_rules = array();

		$taxonomy = get_taxonomy( 'category' );

		$blog_prefix = '';
		if ( is_multisite() && !is_subdomain_install() && is_main_site() ) {
			$blog_prefix = 'blog/';
		}

		$categories = get_categories( array( 'hide_empty' => false ) );
		if ( is_array( $categories ) && $categories !== array() ) {
			foreach ( $categories as $category ) {
				$cat_slug = $category->slug;
				if ( $category->parent == $category->cat_ID ) {
					$category->parent = 0;
				}
				elseif ( $taxonomy->rewrite['hierarchical'] != 0 && $category->parent != 0 ) {
					$parents = get_category_parents( $category->parent, false, '/', true );
					if ( ! is_wp_error( $parents ) ) {
						$cat_slug = $parents . $cat_slug;
					}
					unset( $parents );
				}

				$rewrite_rules[ $blog_prefix . '(' . $cat_slug . ')/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$' ]                = 'index.php?category_name=$matches[1]&feed=$matches[2]';
				$rewrite_rules[ $blog_prefix . '(' . $cat_slug . ')/' . $wp_rewrite->pagination_base . '/?([0-9]{1,})/?$' ] = 'index.php?category_name=$matches[1]&paged=$matches[2]';
				$rewrite_rules[ $blog_prefix . '(' . $cat_slug . ')/?$' ]                                                   = 'index.php?category_name=$matches[1]';
			}
			unset( $categories, $category, $cat_slug );
		}

		// Redirect old category base.
		$old_base = $wp_rewrite->get_category_permastruct();
		$old_base = str_replace( '%category%', '(.+)', $old_base );
		$old_base = trim( $old_base, '/' );
		$rewrite_rules[ $old_base . '$' ] = 'index.php?wpsilo_strip_category=$matches[1]';

		return $rewrite_rules;
		
	}
	
	function wpsilo_treat_post_as_page( $query ) {
		if($query->is_admin){
			return $query;
		}

		$extra_edge_posts = wpsilo_excluded_posts();
		
		if( empty( $extra_edge_posts ) || !array_key_exists( 'wpsilo-post-as-page', $extra_edge_posts ) ) {
			return;
		}

		foreach( $extra_edge_posts['wpsilo-post-as-page'] as $ids ) {
			$post_id[] = $ids;
		}		

		if ( $query->is_feed || !$query->is_single) {
			$query->set( 'post__not_in', $post_id ); // id of page or post
		}

		return $query;
		
	}
	
}

$wpsilo_init = new BT_WPSILO_Exec();
