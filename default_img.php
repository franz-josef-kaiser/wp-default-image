<?php
/*
Plugin Name: Default Image
Plugin URI: http://unserkaiser.com
Description: Adds a default image that can be used during development. Also crops on demand
Author: Franz Josef Kaiser
Version: 0.3
Author URI: http://unserkaiser.com
License: MIT

Adding default images to themes is not an easy task. They have to play nicely with different 
image sizes that are built-in and/or added via add_image_size();. If they don't, 
then they will simply break the layout or won't change with user changes. 
Sadly we can't simply use most of the core media/image functions as those functions check 
if the image is an attachment image and - if not - abort.

So I want to introduce wp_default_img();. It works with an input array of attributes 
and offers two filters (wp_default_img_attr & wp_default_img). 
So setting up default images is as easy as using a filter 
(if the theme developer isn't satisfied with the functions default args) and finally just adding

	# @example
	// functions.php during init:
	add_image_size( 'default_img', 80, 80, true );

	// Inside some template
	$placeholder = get_site_url( null, 'your_path' ).'/some_img.jpg';
	echo wp_default_img( array( 'url' => $placeholder, 'size' => 'default_img' ) );

The function also cares about cropping images, 
if 4th argument set to true when registering the size using add_image_size();.
 */

// Prevent loading this file directly - Busted!
if ( ! class_exists('WP') ) 
{
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

#################### @TODO Align with dynamic_image_resize class

/** 
 * Default images for themes 
 *  
 * Builds an default <img> for use in themes or plugins before any other images are added. 
 * Resizes & crops the image using the built-in (retireved via `get_intermediate_image_sizes();`)  
 * or custom image (added via `add_image_size();`) sizes. 
 *  
 * Retrieves calculated resize dimension @uses image_resize_dimensions(); 
 * Builds the width and height string @uses image_hwstring(); 
 *  
 * @param $args (array) 
 *              string $url URl to the given default image. 
 *              string $size Optional. Default 'medium'. 
 *              string (optional) $alt Image Description for the alt attribute. 
 *              string (optional) $title Image Description for the title attribute. 
 *              string (optional) $align Part of the class name for aligning the image. Default: 'none'
 *              string (optional) $echo Wheter to return or echo the $image 
 * @return string HTML IMG element for given image attachment 
 */
function wp_default_img( $attr )
{
	// Sizes registered via add_image_size();
	global $_wp_additional_image_sizes;

	// Error handling, if no default url
	$error = ! isset( $attr['url'] ) 
		? new WP_Error( 'url_missing', __( 'You need to specify an URl for the default img.' ), __FILE__ )
		: false
	;
	if ( is_wp_error( $error ) )
	{
		// No error message for Guests or Subscribers
		// Assuming that no one has activated caching plugins when debugging
		// and not set WP_DEBUG to TRUE on a live site
		if ( 
			! is_user_logged_in()
			AND ! current_user_can( 'edit_posts' ) 
			AND ( ! defined( 'WP_DEBUG' ) OR ! WP_DEBUG )
		)
			return '';

		return "{$error->get_error_message( 'no_attachment' )}: {$error->get_error_data()}";
	}

	$attr = wp_parse_args( $attr, array(
		 'size'    => 'medium'
		,'classes' => ''
		,'alt'     => ''
		,'title'   => ''
		,'align'   => 'none'
		,'echo'	   => true 
	) );

	// Sanitize
	array_map( 'esc_attr', $attr );

	if ( 'thumb' === $attr['size'] )
		$attr['size'] = 'thumbnail';

	// Size in built in sizes - call size setting from DB
	# behavoir in here, dependent on outcome of @link http://core.trac.wordpress.org/ticket/18947
	if ( ! in_array( $attr['size'], array_keys( $_wp_additional_image_sizes ) ) )
	{
		$sizes = get_intermediate_image_sizes();

		// Get option - gladly autoloaded/can use wp_cache_get();
		$size_data['width']  = intval( get_option( "{$attr['size']}_size_w") );
		$size_data['height'] = intval( get_option( "{$attr['size']}_size_h") );

		// Not sure how this will behave if cropped is false (autoloaded option not added)
		$crop_opt = get_option( "{$attr['size']}_crop" );
		$size_data['crop']   = $crop_opt ? $crop_opt : false;
	}
	// Size array from global registered additional/custom sizes array
	else 
	{
		$size_data = $_wp_additional_image_sizes[ $attr['size'] ];
	}

	// Retrieve image width & height
	$img_info  = @getimagesize( $attr['url'] );

	// Calculate final dimensions
	// If "crop" was set to true during add_image_size(), the img will get ... cropped
	$end_sizes = image_resize_dimensions( 
		 $img_info[0]
		,$img_info[1]
		,$size_data['width']
		,$size_data['height']
		,$size_data['crop'] 
	);

	// defaults to px units - can't get changed, as applying units is not possible
	$hwstring  = ' '.trim( image_hwstring( $end_sizes[4], $end_sizes[5] ) );

	// >>>> Attributes:
	// Not made required as users tend to do funky things (...and lock screen readers out)
	$attr['alt'] = ! emtpy( $attr['alt'] ) ? " alt='{$attr['alt']}'" : '';

	if ( ! $attr['title'] )
	{
		$mime = explode( "/", $img_info['mime'] );
		$mime = ucfirst( $mime[1] );
		$attr['title'] = sprintf( __( 'default image of type: %1$s' ), $mime );
	}
	$attr['title']   = $attr['title'] ? " title='{$attr['title']}'" : '';

	// Allow filtering of the default attributes
	$attr    = apply_filters( 'wp_default_img_attr', $attr );
	// <<<<

	$classes = " class='wp-img-default {$attr['classes']} size-{$attr['size']} {$attr['align']}'";

	$url     = trim( $attr['url'] );
	$image   = "<img src='{$url}'{$hwstring}{$classes}{$attr['alt']}{$attr['title']} />";

	// Allow filtering of output
	$image   = apply_filters( 'wp_default_img', $image );

	if ( ! $attr['echo'] )
		return $image;

	return print $image;
}