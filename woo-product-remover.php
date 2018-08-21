<?php
/*
  Plugin Name: Woo Product Remover
  Plugin URI: http://www.greateck.com/
  Description: Woo Product Remover allows you to, via a single click, remove all woocommerce products from your site. It cleans up your database from products, their metadata, relationships, as well as product variations and their related meta data
  Version: 1.1.0
  Author: Mohammad Farhat
  Author URI: http://www.greateck.com
  License: GPLv2
 */

add_action( 'admin_menu', 'gk_wpr_prod_admin_menu' );

function gk_wpr_prod_admin_menu() {
	add_menu_page( 'Woo Product Remover', 'Woo Product Remover', 'manage_options', 'woo-product-remover', 'gk_wpr_prod_remove_action');
}

function gk_wpr_prod_remove_action() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	echo '<h2>Woo Product Remover</h2>';
	echo '<div class="wrap">';
	// print_r($_POST);
	if (isset($_POST['delete_process'])){
		//validating the nonce
		check_admin_referer( 'gk-wpr-remove-products_'.get_current_user_id() );
		$remove_cats = false;
		if (!empty($_POST['chckbx_cats'])){
			$remove_cats = true;
		}
		gk_wpr_prod_remove_db($remove_cats);
	}else{
		echo '<form method="post">';
		echo '<p>This is as simple as it gets! Just click the button below to remove all woocommerce products.</p>';
		echo '<p>Please be cautious as this action is irreversible!</p>';
		echo '<p><input type="checkbox" name="chckbx_cats" id="chckbx_cats" value="chckbx_cats">Also remove related categories, tags, and taxonomies</p>';
		echo '<input type="hidden" id="delete_process" name="delete_process">';
		//adding nonce support
		wp_nonce_field( 'gk-wpr-remove-products_'.get_current_user_id() );
		echo '<input type="submit" value="Delete All">';
		echo '</form>';
	}
	echo '</div>';
	echo '<br />';
	echo 'Thank you for using Woo Product Remover by <a href="http://www.greateck.com">Greateck</a>';
}

function gk_wpr_prod_remove_db($remove_cats) {
	global $wpdb;
	
	// Current site db prefix
	$pref = $wpdb->prefix;
	
	//delete all product relationships first

	$wpdb->query( 
		"DELETE relations.*".( $remove_cats ? ", taxes.* , terms.* " : "" ).
		"FROM ".$pref."term_relationships AS relations
		INNER JOIN ".$pref."term_taxonomy AS taxes
		ON relations.term_taxonomy_id=taxes.term_taxonomy_id
		INNER JOIN ".$pref."terms AS terms
		ON taxes.term_id=terms.term_id
		WHERE object_id IN (SELECT ID FROM ".$pref."posts WHERE post_type='product');"
	);
	
	//keep taxonomies just fix the link count to be zero
	//taxonomies that need updating include product_tag, product_type and product_cat
	if (!$remove_cats){
		$wpdb->query( 
			"UPDATE ".$pref."term_taxonomy SET count = 0 WHERE taxonomy LIKE 'product%';"
		);
	}

	//delete product meta data
	$wpdb->query( 
		"DELETE FROM ".$pref."postmeta WHERE post_id IN (SELECT ID FROM ".$pref."posts WHERE post_type = 'product');"
	);
	
	//delete variation meta data
	$wpdb->query( 
		"DELETE FROM ".$pref."postmeta WHERE post_id IN (SELECT ID FROM ".$pref."posts WHERE post_type = 'product_variation');"
	);
	
	//delete actual products
	$prods_count = $wpdb->query(
		"DELETE FROM ".$pref."posts WHERE post_type = 'product';"
	);
	
	//delete product variations
	$vars_count = $wpdb->query(
		"DELETE FROM ".$pref."posts WHERE post_type = 'product_variation';"
	);
	
	//delete product's attachments
	$attachments = get_posts( array( 
		'post_type'   => 'attachment',
		'numberposts' => -1,
		'post_status' => null 
	) );
	$attachments_count = 0;
	foreach( $attachments as $attachment ) {
		
		$parent_id = $attachment->post_parent;
		$parent_post = get_post( $parent_id );
		
		if( ! $parent_post ) {
			
			wp_delete_attachment( $attachment->ID, true );
			
			$attachments_count++;
			
		}
		
	}
	
	if (is_numeric($prods_count)){
		if ($prods_count == 0){
			echo "No products found.";
		}else{
			echo $prods_count." product(s) successfully removed!";
		}
	}else{
		echo 'Error removing products';
	}
	echo '<br />';
	if (is_numeric($vars_count)){
		if ($vars_count > 0){
			echo $vars_count." product variation(s) successfully removed!";
			echo '<br />';
		}
	}
	
	// Echo if any attachments was deleted
	echo '<br />';
	if (is_numeric($attachments_count)){
		if ($attachments_count > 0){
			echo $attachments_count." product images successfully removed!";
			echo '<br />';
		}
	}
}
?>