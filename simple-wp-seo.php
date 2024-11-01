<?php
/**
 * Plugin Name: Simple WP SEO
 * Text Domain: simplewpseo
 * Description: A simple way to manage your SEO tags. Auto generates Open graph tags for Facebook, LinkedIn etc.
 * Author:      Mikkel Bundgaard @ Inspire Me
 * Author URI:  http://inspireme.dk
 * Version:     1.1.6
 *
 * @package WordPress
 * @author  Mikkel Bundgaard <info@inspireme.dk>
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @created 2016-09-19
 * @version 2019-02-20
 */

//Security
defined('ABSPATH') or die('No script kiddies please!');

//Globalize
global $wp_version;

/**
 * Translate this plugin
 * @return void
 */
function simple_wp_seo_load_textdomain()
{
    load_plugin_textdomain('simplewpseo', false, dirname(plugin_basename(__FILE__)) . '/lang/');
}
add_action('plugins_loaded', 'simple_wp_seo_load_textdomain');

/**
 * Register meta box(es).
 * @return void
 */
function simple_wp_seo_register_meta_boxes()
{
    //Meta boxes
    add_meta_box('simple-seo-pageeditor-meta', __( 'SEO settings', 'simplewpseo' ), 'simple_wp_seo_pageeditor_meta_settings', 'page', 'normal');
    add_meta_box('simple-seo-pageeditor-meta', __( 'SEO settings', 'simplewpseo' ), 'simple_wp_seo_pageeditor_meta_settings', 'post', 'normal');
}
add_action('add_meta_boxes', 'simple_wp_seo_register_meta_boxes');

/**
 * Add the SEO Meta box
 * This is for both the page and post
 * - but can be split if needed
 *
 * @param object $page
 * @return print
 */
function simple_wp_seo_pageeditor_meta_settings($page)
{
    //Print the save lock
    echo '<input type="hidden" name="simple_wp_seo_prevent_delete_meta_movetotrash" id="simple_wp_seo_prevent_delete_meta_movetotrash" value="'.wp_create_nonce($page->ID).'" />';

    //Print the settings
    echo '
        <br /><strong>'.__( 'Meta Title', 'simplewpseo' ).'</strong>
        <br /><input type="text" name="simplewpseoMetaTitle" placeholder="'.__( 'Meta Title', 'simplewpseo' ).'" value="'.get_post_meta($page->ID, "_simplewpseoMetaTitle", true).'" style="width: 100%; max-width: 600px;" />
        <br />
        <br /><strong>'.__( 'Meta Description', 'simplewpseo' ).'</strong>
        <br /><textarea name="simplewpseoMetaDescription" placeholder="'.__( 'Meta Description', 'simplewpseo' ).'" style="width: 100%; max-width: 600px; height: 100%; max-height: 400px;">'.get_post_meta($page->ID, "_simplewpseoMetaDescription", true).'</textarea>
        <br />
        <br /><i>'.__( 'If these fields are left blank, a title and description will be generated.', 'simplewpseo' ).'</i>
    ';
}

/**
 * On page save
 * 
 * @param int $postId
 * @return void|int
 */
function simple_wp_seo_save_page_settings($postId)
{
    //Save lock
    if(!isset($_POST['simple_wp_seo_prevent_delete_meta_movetotrash']) || !wp_verify_nonce(sanitize_text_field($_POST['simple_wp_seo_prevent_delete_meta_movetotrash']), $postId))
        return $postId;

	//Meta Title
	if(!add_post_meta($postId, '_simplewpseoMetaTitle', sanitize_text_field($_POST["simplewpseoMetaTitle"]), true))
	   update_post_meta($postId, '_simplewpseoMetaTitle', sanitize_text_field($_POST["simplewpseoMetaTitle"]));

    //Meta Title
    if(!add_post_meta($postId, '_simplewpseoMetaDescription', sanitize_text_field($_POST["simplewpseoMetaDescription"]), true))
       update_post_meta($postId, '_simplewpseoMetaDescription', sanitize_text_field($_POST["simplewpseoMetaDescription"]));
}
add_action('save_post', 'simple_wp_seo_save_page_settings');

/**
 * Set the title
 * 
 * @param string $title
 * @param string $sep default ""
 * @return string
 */
function simple_wp_seo_filter_title($title, $sep = "")
{
    //Globalize
    global $wp_query;

    //Is this a valid post?
    if(isset($wp_query->post->ID))
    {
        //Get the title
        if($simpleTitle = get_post_meta($wp_query->post->ID, "_simplewpseoMetaTitle", true))
            $title = $simpleTitle.($sep != "" ? " ".$sep." " : "");
            
        //Blank title?
        if($title == "")
            $title = get_the_title($wp_query->post->ID);
    }

    //Fallback
    return $title;
}
if($wp_version > '4.4')
    add_filter('pre_get_document_title', 'simple_wp_seo_filter_title', 10, 2);
else
    add_filter('wp_title', 'simple_wp_seo_filter_title', 10, 2);

/**
 * Add the SEO tags to the header
 * 
 * @return print
 */
function simple_wp_seo_add_tags()
{
    //Globalize
    global $wp_query;

    //Add the tags
    if(isset($wp_query->post->ID))
    {
        //Get the description
        $description = get_post_meta($wp_query->post->ID, "_simplewpseoMetaDescription", true);

        //Get excerpt if the description is blank
        if($description == "")
            $description = simple_wp_seo_getExcerpt($wp_query->post->ID);

        //Trim description
        $description = trim(str_replace("[&hellip;]", "", $description));

        //Open Graph
        echo '<meta property="og:description" content="'.$description.'" />' . "\n";
        echo '<meta property="og:title" content="'.simple_wp_seo_filter_title("").'" />' . "\n";
        echo '<meta property="og:url" content="'.(isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]".'" />' . "\n";
        echo '<meta property="og:site_name" content="'.get_bloginfo( 'name' ).'" />' . "\n";

        //Open Graph Image?
        if(has_post_thumbnail())
            echo '<meta property="og:image" content="'.get_the_post_thumbnail_url(null, 'medium').'" />' . "\n";

        //Description
        echo '<meta name="description" content="'.$description.'" />' . "\n";
    }
}
add_action('wp_head', 'simple_wp_seo_add_tags', 2);

/**
 * Get excerpt
 *
 * @param int $postId
 * @return string
 */
function simple_wp_seo_getExcerpt($postId)
{
    //Globalize
    global $post;

    //Save the post and get the excerpt
    $save_post = $post;
    $post = get_post($postId);
    setup_postdata($post); // hello
    $output = strip_tags(get_the_excerpt());
    $post = $save_post;

    //Remove blanks at the end
    if(substr($output, -6) == "&nbsp;")
        $output = substr($output, 0, -6);

    //Replace end trails
    $output = str_replace(" ...", "...", $output);

    //Return
    return trim($output);
}
?>