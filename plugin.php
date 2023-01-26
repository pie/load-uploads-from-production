<?php
/*
Plugin Name: Load Uploads From Production
Description: This plugin overwrites attachment URLs with the production site if they are not found on the staging site. Please de-activate in production.
Version: 1.0.0
Author: The team at PIE
Author URI: http://pie.co.de
*/

namespace PIE\LoadUploadsFromProduction;

const PROD_URL_SETTING_ID = __NAMESPACE__ . ':production_url';
const PROD_IMG_URL_CACHE_ID = __NAMESPACE__ . ':cached_image_urls';

add_action('admin_init', __NAMESPACE__ . '\hookup_setting_field');
add_action('init', __NAMESPACE__ . '\maybe_hookup_uploads_filter');

/**
 * Checks to see if we're on the production site, and if not we hook up the filter
 *
 * @hooked init @10
 * @return void
 */
function maybe_hookup_uploads_filter()
{
    if (get_option(PROD_URL_SETTING_ID) && get_option(PROD_URL_SETTING_ID) !== home_url()) {
        add_filter('wp_get_attachment_image_src', __NAMESPACE__ . '\replace_home_url_with_production');
    }
}

/**
 * Loads the required settings for our field
 *
 * @hooked admin_init@10
 * @return void
 */
function hookup_setting_field(){

	register_setting( 'general', PROD_URL_SETTING_ID , [
		'type'=>'string',
		'sanitize_callback'=>'sanitize_url',
		]
	);	

	add_settings_field(  
		PROD_URL_SETTING_ID,                      
		'Production Address (URL)',               
		__NAMESPACE__ . '\textbox_callback',   
		'general' ,                     
		'default'
	);

}

/**
 * Outputs the HTML for URL field
 *
 * @return void
 */
function textbox_callback() { 
	$production_url_value = get_option(PROD_URL_SETTING_ID)?:''; 
	echo sprintf(file_get_contents(plugin_dir_path(__FILE__) . 'templates/production-url-field.html'), PROD_URL_SETTING_ID, $production_url_value);
}

/**
 * Looks for a valid url in the first element of an array and if found, checks if the file is accessible via HTTP.
 * If not, it replaces any instances of the home URL with the production URL. This is primarily used to fall back 
 * to the live site for images that are not found in the dev or staging environments.
 * 
 * Note that we cache these lookups once and once only to save on multiple HTTP requests in the background on
 * every page load. The cache needs to be removed from the DB manually to purge.
 *
 * @todo Implement Cache Management
 * @hooked wp_get_attachment_image_src @10
 * @param array $src an array with image URL in the first element
 * @return array
 */
function replace_home_url_with_production($src)
{
    if (is_array($src) && filter_var($src[0], FILTER_VALIDATE_URL)) {

		$cache = get_option(PROD_IMG_URL_CACHE_ID);
		if(isset($cache[$src[0]])){
			$src[0] = $cache[$src[0]];
		} else {
			$file_headers = @get_headers($src[0]);
			if ($file_headers[0] == 'HTTP/1.1 404 Not Found') {
					$src[0] = str_replace(home_url(), PROD_URL_SETTING_ID, $src[0]);
			}
			$cache[$src[0]] = $src[0];
			update_option(PROD_IMG_URL_CACHE_ID, $cache);
		}
    }
    return $src;
}