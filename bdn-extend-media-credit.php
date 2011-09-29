<?php
/*
Plugin Name: BDN Extend Media Credit
Contributors: wpdavis
Tags: images, image, media, Media Credit
Requires at least: 3.0
Tested up to: 3.2
Version: 0.1
*/

// Automatically fill in the field using exif data
add_filter( 'wp_generate_attachment_metadata', 'bdn_update_image_author', 10, 2 );
function bdn_update_image_author( $metadata, $attachment_id ) {

	$upload_dir = wp_upload_dir();
	$file = $upload_dir['basedir'] . '/' . $metadata['file'];

	//wp_get_attachment_meta was giving us inconsistent results, so we decided to read straight from the file.
	getimagesize( $file , $info);
	$iptc = iptcparse($info["APP13"]);
	
	//We use the instructions field for the caption
	$caption = $iptc[ '2#040' ][0];
	
	//We're not using WordPress' built-in parsing functions because they're not always accurate.
	$image_credit_line = $iptc[ '2#080' ][0];
	
	// If the image's credit line is empty there's no need to go through with this
	if( empty( $image_credit_line ) )
		return $metadata;
	
	// Get the blog's users and put them into an array
	
	$users_array = get_transient( 'mediacreditusers' );
	if( false === $users_array ) {
		$users = get_users( );
		$users_array = array();
		foreach ( $users as $user ) {
			$users_array[ $user->display_name ] = $user->ID;
			$users_array[ $user->user_login ] = $user->ID;
		}
		set_transient( 'mediacreditusers', $users_array, 60 * 60 * 24 );
	}
	
	$user_id = false;
	
	// For each user, check if the credit line is in the array

	if( !empty( $users_array[ $image_credit_line ] ) )
		$user_id = $users_array[ $image_credit_line ];

	if( empty( $user_id ) ) {
	
		$user_id = 132;
	
		// Set the byline as a custom field. I'm also getting the organization from the image and attaching that after the separator			

		if( !empty( $iptc['2#110'][0] ) )
			$image_credit_line .= ' | ' . $iptc['2#110'][0];
		
		update_post_meta( $attachment_id, MEDIA_CREDIT_POSTMETA_KEY, $image_credit_line ); // insert '_media_credit' metadata field for image with free-form text
	}
	
	
	if( !empty( $user_id ) || !empty( $caption ) ) {
	
		// The byline matched a current user -- make that user the author of the file
		// Also set the caption.
		$post_data = array();
		$post_data['ID'] = $attachment_id;
		
		if( !empty( $caption ) )
			$post_data['post_excerpt'] = $caption;
			
		if( !empty( $user_id ) )
			$post_data['post_author'] = $user_id;
			
		wp_update_post( $post_data );
	
	}
	
	return $metadata;
}
