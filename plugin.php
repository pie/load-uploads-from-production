<?php
/*
Plugin Name: Load Uploads From Production
Description: This plugin overwrites attachment URLs with the production site if they are not found on the staging site. Please de-activate in production.
Version: 1.1.0
Author: The team at PIE
Author URI: http://pie.co.de
*/

namespace PIE\LoadUploadsFromProduction;

use \Exception;

const PROD_URL_SETTING_ID = __NAMESPACE__ . ':production_url';
const PROD_IMG_URL_CACHE_ID = __NAMESPACE__ . ':cached_image_urls';
const CLEAR_PROD_IMG_URL_CACHE_ID = __NAMESPACE__ . ':clear_image_urls_cache';

add_action('admin_init', __NAMESPACE__ . '\hookup_setting_field');
add_action('init', __NAMESPACE__ . '\maybe_hookup_uploads_filter');
add_action('init', __NAMESPACE__ . '\enqueue_scripts');

/**
 * Enqueues scripts
 *
 * @hooked init @10
 * @return void
 */
function enqueue_scripts()
{
    wp_enqueue_script(
        $handle = 'load-uploads-from-production-js',
        $src = plugin_dir_url(__FILE__) . '/js/admin.js',
        $deps = ['jquery'],
        $ver = filemtime(plugin_dir_path(__FILE__) . '/js/admin.js'),
        $in_footer = true
    );
    wp_localize_script(
        $handle = 'load-uploads-from-production-js',
        $object_name = 'ajax_obj',
        $l10n = [
            'ajax_url' => admin_url('admin-ajax.php')
        ]
    );

    add_action('wp_ajax_lufp_clear_image_cache', __NAMESPACE__ . '\maybe_clear_image_cache');
}

/**
 * Ajax action to try clear image cahce
 */
function maybe_clear_image_cache()
{
    try {
        $cache = get_option(PROD_IMG_URL_CACHE_ID);
        $cache_size = is_array($cache) ? sizeof($cache) : 0;
        $updated = update_option(PROD_IMG_URL_CACHE_ID, []);
        
        if ($cache_size < 1) {
            throw new Exception("There was no cache to clear", 1);
        }
        if (!$updated) {
            throw new Exception("Internal error. Please try again or seek admin assistance", 2);
        }
        wp_send_json_success([
            'success' => true,
            'cache' => $cache,
            'items_cleared' => $cache_size
            ], 200);
    } catch (\Exception $ex) {
        wp_send_json_error([
            'success' => false,
            'message' => $ex->getMessage(),
            'errCode' => $ex->getCode()
        ]);
    }
}


/**
 * Load Composer autoloader
 */
require plugin_dir_path(__FILE__) . '/vendor/autoload.php';
$update_checker = \Puc_v4_Factory::buildUpdateChecker(
    'http://212.71.239.229/releases/plugins/load-uploads-from-production/release-data.json',
    __FILE__,
    'load-uploads-from-production'
);

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
function hookup_setting_field()
{
    register_setting(
        'general',
        PROD_URL_SETTING_ID,
        [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_url',
        ]
    );


    add_settings_field(
        PROD_URL_SETTING_ID,
        'Production Address (URL)',
        __NAMESPACE__ . '\textbox_callback',
        'general',
        'default'
    );
    
    add_settings_field(
        CLEAR_PROD_IMG_URL_CACHE_ID,
        'Image Cache',
        __NAMESPACE__ . '\cache_clear_button_callback',
        'general',
        'default'
    );

}

/**
 * Outputs the HTML for URL field
 *
 * @return void
 */
function textbox_callback()
{
    $production_url_value = get_option(PROD_URL_SETTING_ID) ?: '';
    echo sprintf(file_get_contents(plugin_dir_path(__FILE__) . 'templates/production-url-field.html'), PROD_URL_SETTING_ID, $production_url_value);
}

/**
 * Outputs the HTML for the Cache Clear button
 *
 * @return void
 */
function cache_clear_button_callback()
{
    echo '<button type="button" id="clear-cache-button" class="button">Clear Cache</button>';
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
        if (isset($cache[$src[0]])) {
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
