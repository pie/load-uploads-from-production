<?php
/*
Plugin Name: PIE STG Uploads
Description: This plugin rewrites attachment URLs to the production site if they are not found on the staging site. Please de-activate in production.
Version: 0.1
Author: The team at PIE
Author URI: http://pie.co.de
*/

add_filter('wp_get_attachment_image_src',
	function($src){
		$file_headers = @get_headers($src);
		if($file_headers[0] == 'HTTP/1.1 404 Not Found') {
    		$src = str_replace('annamasonsch.staging.wpengine.com', 'www.annamasonart.com', $src);
		}
		return $src;
	}
);