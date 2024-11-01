<?php
/*
Plugin Name: Woocommerce Order Quantity Control Min Max
Plugin URI:  http://#
Description: Control your minimum and maximum product purchase for every new order . 
Version: 1.0
Author: <a href="http://jploft.com" target="_blank">Jploft Solutions Pvt. Ltd.</a>
Text Domain: jplwocom
Author URI: http://jploft.com
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

add_filter( 'woocommerce_product_data_tabs', 'jplwocom_custom_product_tabs' );
add_filter( 'woocommerce_product_data_panels', 'jplwocom_min_max_options_product_tab_content' ); // WC 2.6 and up
add_action( 'woocommerce_process_product_meta', 'jplwocom_qty_save_product_field' );


function jplwocom_custom_product_tabs( $tabs) {

	$tabs['min-max-quantity'] = array(
		'label'		=> __( 'Min-Max Quantity Setting ', 'woocommerce' ),
		'target'	=> 'min_max_product_options',
		'class'		=> array( 'show_if_simple', 'show_if_variable'  ),
	);

	return $tabs;

}


function jplwocom_min_max_options_product_tab_content() {

	global $post;
	
	?><div id='min_max_product_options' class='panel woocommerce_options_panel'>

		<?php
		
		
		echo '<div class="options_group">';
	woocommerce_wp_text_input( 
		array( 
			'id'          => '_wc_min_qty_product', 
			'label'       => __( 'Minimum Quantity', 'woocommerce-max-quantity' ), 
			'placeholder' => 'Enter product minimum quantity',
			'desc_tip'    => 'true',
			'type' => 'number',
			'description' => __( 'Optional. Set a minimum quantity limit allowed per order. Enter a number, 1 or greater.', 'woocommerce-max-quantity' ) 
		)
	);
	echo '</div>';
	echo '<div class="options_group">';
	woocommerce_wp_text_input( 
		array( 
			'id'          => '_wc_max_qty_product', 
			'label'       => __( 'Maximum Quantity', 'woocommerce-max-quantity' ), 
			'placeholder' => 'Enter product maximum quantity',
			'desc_tip'    => 'true',
			'type' => 'number',
			'description' => __( 'Optional. Set a maximum quantity limit allowed per order. Enter a number, 1 or greater.', 'woocommerce-max-quantity' ) 
		)
	);
	echo '</div>';			

		?>

	</div><?php

}

function jplwocom_qty_save_product_field( $post_id ) {
	
	$val_min = trim( get_post_meta( $post_id, '_wc_min_qty_product', true ) );
	$new_min = sanitize_text_field( $_POST['_wc_min_qty_product'] );
	$val_max = trim( get_post_meta( $post_id, '_wc_max_qty_product', true ) );
	$new_max = sanitize_text_field( $_POST['_wc_max_qty_product'] );
	
	if ( $val_min != $new_min ) {
		update_post_meta( $post_id, '_wc_min_qty_product', $new_min );
	}
	if ( $val_max != $new_max ) {
		update_post_meta( $post_id, '_wc_max_qty_product', $new_max );
	}
}


add_filter( 'woocommerce_quantity_input_args', 'jplwocom_qty_input_args', 10, 2 );
add_filter( 'woocommerce_add_to_cart_validation', 'jplwocom_qty_add_to_cart_validation', 1, 5 );


function jplwocom_qty_input_args( $args, $product ) {
	
	$product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
	
	$product_min = wc_get_product_min_limit( $product_id );
	$product_max = wc_get_product_max_limit( $product_id );	
	if ( ! empty( $product_min ) ) {
		// min is empty
		if ( false !== $product_min ) {
			$args['min_value'] = $product_min;
		}
	}
	if ( ! empty( $product_max ) ) {
		// max is empty
		if ( false !== $product_max ) {
			$args['max_value'] = $product_max;
		}
	}
	if ( $product->managing_stock() && ! $product->backorders_allowed() ) {
		$stock = $product->get_stock_quantity();
		$args['max_value'] = min( $stock, $args['max_value'] );	
	}
	return $args;
}

function wc_get_product_max_limit( $product_id ) {
	$qty = get_post_meta( $product_id, '_wc_max_qty_product', true );
	if ( empty( $qty ) ) {
		$limit = false;
	} else {
		$limit = (int) $qty;
	}
	return $limit;
}
function wc_get_product_min_limit( $product_id ) {
	$qty = get_post_meta( $product_id, '_wc_min_qty_product', true );
	if ( empty( $qty ) ) {
		$limit = false;
	} else {
		$limit = (int) $qty;
	}
	return $limit;
}



/*
* Validating the quantity on add to cart action 
*/
function jplwocom_qty_add_to_cart_validation( $passed, $product_id, $quantity, $variation_id = '', $variations = '' ) {
	$product_min = wc_get_product_min_limit( $product_id );
	$product_max = wc_get_product_max_limit( $product_id );
	if ( ! empty( $product_min ) ) {
		
		if ( false !== $product_min ) {
			$new_min = $product_min;
		} else {
			
			return $passed;
		}
	}
	if ( ! empty( $product_max ) ) {
		
		if ( false !== $product_max ) {
			$new_max = $product_max;
		} else {
			
			return $passed;
		}
	}
	$already_in_cart 	= wc_qty_get_cart_qty( $product_id );
	$product 			= wc_get_product( $product_id );
	$product_title 		= $product->get_title();
	
	if ( !is_null( $new_max ) && !empty( $already_in_cart ) ) {
		
		if ( ( $already_in_cart + $quantity ) > $new_max ) {
			
			$passed = false;			
			wc_add_notice( apply_filters( 'isa_wc_max_qty_error_message_already_had', sprintf( __( 'You can add a maximum of %1$s %2$s\'s to %3$s. You already have %4$s.', 'woocommerce-max-quantity' ), 
						$new_max,
						$product_title,
						'<a href="' . esc_url( wc_get_cart_url() ) . '">' . __( 'your cart', 'woocommerce-max-quantity' ) . '</a>',
						$already_in_cart ),
					$new_max,
					$already_in_cart ),
			'error' );
		}
	}
	return $passed;
}
/*
* Get the total quantity of the product in cart.
*/ 
function wc_qty_get_cart_qty( $product_id ) {
	global $woocommerce;
	$running_qty = 0; 
	
	foreach($woocommerce->cart->get_cart() as $other_cart_item_keys => $values ) {
		if ( $product_id == $values['product_id'] ) {				
			$running_qty += (int) $values['quantity'];
		}
	}
	return $running_qty;
}


?>