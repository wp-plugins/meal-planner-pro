<?php
/*
Plugin Name: MealPlannerPro Recipe Plugin
Plugin URI: http://www.mealplannerpro.com/recipe_plugin
Plugin GitHub: https://github.com/Ziplist/recipe_plugin
Description: A plugin that adds all the necessary microdata to your recipes, so they will show up in Google's Recipe Search
Version: 3.7
Author: MealPlannerPro.com
Author URI: http://www.mealplannerpro.com/
License: GPLv3 or later

Copyright 2011, 2012, 2013, 2014 MealPlannerPro, Inc.
This code is derived from the 1.3.1 build of RecipeSEO released by codeswan: http://sushiday.com/recipe-seo-plugin/ and licensed under GPLv2 or later
*/

/*
    This file is part of MealPlannerPro Recipe Plugin.

    MealPlannerPro Recipe Plugin is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    MealPlannerPro Recipe Plugin is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with MealPlannerPro Recipe Plugin. If not, see <http://www.gnu.org/licenses/>.
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo "Hey!  This is just a plugin, not much it can do when called directly.";
	exit;
}

if (!defined('MPPRECIPE_VERSION_KEY'))
    define('MPPRECIPE_VERSION_KEY', 'mpprecipe_version');

if (!defined('MPPRECIPE_VERSION_NUM'))
    define('MPPRECIPE_VERSION_NUM', '3.7');

if (!defined('MPPRECIPE_PLUGIN_DIRECTORY'))
		define('MPPRECIPE_PLUGIN_DIRECTORY', plugins_url() . '/' . dirname(plugin_basename(__FILE__)) . '/');


function strip( $i )
{
    // Strip JS, HTML, CSS, Comments
    $search = array(
        '@<script[^>]*?>.*?</script>@si', '@<[\/\!]*?[^<>]*?>@si',
        '@<style[^>]*?>.*?</style>@siU',  '@<![\s\S]*?--[ \t\n\r]*>@'
    );

    $o = preg_replace($search, '', $i);
    return $o;
}
function sanitize( $i )
{
    if (is_array($i)) 
    {
        foreach($i as $v=>$val)
            $o[$v] = sanitize($val);
    }
    else 
    {
        if (get_magic_quotes_gpc())
            $i = stripslashes($i);

        $i = strip($i);
        $o = mysql_escape_string($i);
    }
    return $o;
}

add_option(MPPRECIPE_VERSION_KEY, MPPRECIPE_VERSION_NUM);  // sort of useless as is never updated
add_option("mpprecipe_db_version"); // used to store DB version

add_option('mealplannerpro_partner_key', '');
add_option('mealplannerpro_recipe_button_hide', '');
add_option('mealplannerpro_attribution_hide', '');
add_option('mpprecipe_printed_permalink_hide', '');
add_option('mpprecipe_printed_copyright_statement', '');
add_option('mpprecipe_stylesheet', 'mpprecipe-std');
add_option('recipe_title_hide', '');
add_option('mpprecipe_image_hide', '');
add_option('mpprecipe_image_hide_print', 'Hide');
add_option('mpprecipe_print_link_hide', '');
add_option('mpprecipe_ingredient_label', 'Ingredients');
add_option('mpprecipe_ingredient_label_hide', '');
add_option('mpprecipe_ingredient_list_type', 'ul');
add_option('mpprecipe_instruction_label', 'Instructions');
add_option('mpprecipe_instruction_label_hide', '');
add_option('mpprecipe_instruction_list_type', 'ol');
add_option('mpprecipe_notes_label', 'Notes');
add_option('mpprecipe_notes_label_hide', '');
add_option('mpprecipe_prep_time_label', 'Prep Time:');
add_option('mpprecipe_prep_time_label_hide', '');
add_option('mpprecipe_cook_time_label', 'Cook Time:');
add_option('mpprecipe_cook_time_label_hide', '');
add_option('mpprecipe_total_time_label', 'Total Time:');
add_option('mpprecipe_total_time_label_hide', '');
add_option('mpprecipe_yield_label', 'Yield:');
add_option('mpprecipe_yield_label_hide', '');
add_option('mpprecipe_serving_size_label', 'Serving Size:');
add_option('mpprecipe_serving_size_label_hide', '');
add_option('mpprecipe_calories_label', 'Calories per serving:');
add_option('mpprecipe_calories_label_hide', '');
add_option('mpprecipe_fat_label', 'Fat per serving:');
add_option('mpprecipe_fat_label_hide', '');
add_option('mpprecipe_rating_label', 'Rating:');
add_option('mpprecipe_rating_label_hide', '');
add_option('mpprecipe_image_width', '');
add_option('mpprecipe_outer_border_style', '');
add_option('mpprecipe_custom_save_image', '');
add_option('mpprecipe_custom_print_image', '');

define('MPPRECIPE_AUTO_HANDLE_TOTALTIME',0);

register_activation_hook(__FILE__, 'mpprecipe_install');
add_action('plugins_loaded', 'mpprecipe_install');

add_action('admin_init', 'mpprecipe_add_recipe_button');
add_action('admin_head','mpprecipe_js_vars');

function mpprecipe_js_vars() {

    global $current_screen;
    $type = $current_screen->post_type;

    if (is_admin()) {
        ?>
        <script type="text/javascript">
        var mpp_post_id = '<?php global $post; echo $post->ID; ?>';
        </script>
        <?php
    }
}

if (strpos($_SERVER['REQUEST_URI'], 'media-upload.php') && strpos($_SERVER['REQUEST_URI'], '&type=mpprecipe') && !strpos($_SERVER['REQUEST_URI'], '&wrt='))
{
    if (!empty($_POST)) sanitize($_POST);
    if (!empty($_GET )) sanitize($_GET );

	mpprecipe_iframe_content($_POST, $_REQUEST);
	exit;
}


global $mpprecipe_db_version;
// This must be changed when the DB structure is modified
$mpprecipe_db_version = "3.7";	

// Creates MPPRecipe tables in the db if they don't exist already.
// Don't do any data initialization in this routine as it is called on both install as well as
//   every plugin load as an upgrade check.
//
// Updates the table if needed
// Plugin Ver         DB Ver
//   1.0 - 1.3        3.0
//   1.4x - 3.1       3.1  Adds Notes column to recipes table

function mpprecipe_install() {
    global $wpdb;
    global $mpprecipe_db_version;

    $recipes_table = $wpdb->prefix . "mpprecipe_recipes";
    $installed_db_ver = get_option("mpprecipe_db_version");

    // An older (or no) database table exists
    if(strcmp($installed_db_ver, $mpprecipe_db_version) != 0) {				
        $sql = "CREATE TABLE " . $recipes_table . " (
            recipe_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            recipe_title TEXT,
            recipe_image TEXT,
            summary TEXT,
            rating TEXT,
            prep_time TEXT,
            cook_time TEXT,
            total_time TEXT,
            yield TEXT,
            serving_size VARCHAR(50),
            calories VARCHAR(50),
            fat VARCHAR(50),
            ingredients TEXT,
            instructions TEXT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT NOW()
        	);";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        update_option("mpprecipe_db_version", $mpprecipe_db_version);

    }
}

add_action('admin_menu', 'mpprecipe_menu_pages');

// Adds module to left sidebar in wp-admin for MPPRecipe
function mpprecipe_menu_pages() {
    // Add the top-level admin menu
    $page_title = 'MealPlannerPro Recipe Plugin Settings';
    $menu_title = 'MealPlannerPro Recipe Plugin';
    $capability = 'manage_options';
    $menu_slug = 'mpprecipe-settings';
    $function = 'mpprecipe_settings';
    add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function);

    // Add submenu page with same slug as parent to ensure no duplicates
    $settings_title = 'Settings';
    add_submenu_page($menu_slug, $page_title, $settings_title, $capability, $menu_slug, $function);
}

// Adds 'Settings' page to the MPPRecipe module
function mpprecipe_settings() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }

    $mpprecipe_icon = MPPRECIPE_PLUGIN_DIRECTORY . "mpprecipe.png";

    if ($_POST['ingredient-list-type']) {

        sanitize($_POST);

    	$mealplannerpro_partner_key        = $_POST['mealplannerpro-partner-key'];
        $mealplannerpro_recipe_button_hide = $_POST['mealplannerpro-recipe-button-hide'];
        $mealplannerpro_attribution_hide   = $_POST['mealplannerpro-attribution-hide'];
        $printed_permalink_hide            = $_POST['printed-permalink-hide'];
        $printed_copyright_statement       = $_POST['printed-copyright-statement'];
        $stylesheet                        = $_POST['stylesheet'];
        $recipe_title_hide                 = $_POST['recipe-title-hide'];
        $image_hide                        = $_POST['image-hide'];
        $image_hide_print                  = $_POST['image-hide-print'];
        $print_link_hide                   = $_POST['print-link-hide'];
        $ingredient_label                  = $_POST['ingredient-label'];
        $ingredient_label_hide             = $_POST['ingredient-label-hide'];
        $ingredient_list_type              = $_POST['ingredient-list-type'];
        $instruction_label                 = $_POST['instruction-label'];
        $instruction_label_hide            = $_POST['instruction-label-hide'];
        $instruction_list_type             = $_POST['instruction-list-type'];
        $notes_label                       = $_POST['notes-label'];
        $notes_label_hide                  = $_POST['notes-label-hide'];
        $prep_time_label                   = $_POST['prep-time-label'];
        $prep_time_label_hide              = $_POST['prep-time-label-hide'];
        $cook_time_label                   = $_POST['cook-time-label'];
        $cook_time_label_hide              = $_POST['cook-time-label-hide'];
        $total_time_label                  = $_POST['total-time-label'];
        $total_time_label_hide             = $_POST['total-time-label-hide'];
        $yield_label                       = $_POST['yield-label'];
        $yield_label_hide                  = $_POST['yield-label-hide'];
        $serving_size_label                = $_POST['serving-size-label'];
        $serving_size_label_hide           = $_POST['serving-size-label-hide'];
        $calories_label                    = $_POST['calories-label'];
        $calories_label_hide               = $_POST['calories-label-hide'];
        $fat_label                         = $_POST['fat-label'];
        $fat_label_hide                    = $_POST['fat-label-hide'];
        $rating_label                      = $_POST['rating-label'];
        $rating_label_hide                 = $_POST['rating-label-hide'];
        $image_width                       = $_POST['image-width'];
        $outer_border_style                = $_POST['outer-border-style'];
        $custom_save_image                 = $_POST['custom-save-image'];
        $custom_print_image                = $_POST['custom-print-image'];

        update_option('mealplannerpro_partner_key', $mealplannerpro_partner_key);
        update_option('mealplannerpro_recipe_button_hide', $mealplannerpro_recipe_button_hide);
        update_option('mealplannerpro_attribution_hide', $mealplannerpro_attribution_hide);
        update_option('mpprecipe_printed_permalink_hide', $printed_permalink_hide );
        update_option('mpprecipe_printed_copyright_statement', $printed_copyright_statement);
        update_option('mpprecipe_stylesheet', $stylesheet);
        update_option('recipe_title_hide', $recipe_title_hide);
        update_option('mpprecipe_image_hide', $image_hide);
        update_option('mpprecipe_image_hide_print', $image_hide_print);
        update_option('mpprecipe_print_link_hide', $print_link_hide);
        update_option('mpprecipe_ingredient_label', $ingredient_label);
        update_option('mpprecipe_ingredient_label_hide', $ingredient_label_hide);
        update_option('mpprecipe_ingredient_list_type', $ingredient_list_type);
        update_option('mpprecipe_instruction_label', $instruction_label);
        update_option('mpprecipe_instruction_label_hide', $instruction_label_hide);
        update_option('mpprecipe_instruction_list_type', $instruction_list_type);
        update_option('mpprecipe_notes_label', $notes_label);
        update_option('mpprecipe_notes_label_hide', $notes_label_hide);
        update_option('mpprecipe_prep_time_label', $prep_time_label);
        update_option('mpprecipe_prep_time_label_hide', $prep_time_label_hide);
        update_option('mpprecipe_cook_time_label', $cook_time_label);
        update_option('mpprecipe_cook_time_label_hide', $cook_time_label_hide);
        update_option('mpprecipe_total_time_label', $total_time_label);
        update_option('mpprecipe_total_time_label_hide', $total_time_label_hide);
        update_option('mpprecipe_yield_label', $yield_label);
        update_option('mpprecipe_yield_label_hide', $yield_label_hide);
        update_option('mpprecipe_serving_size_label', $serving_size_label);
        update_option('mpprecipe_serving_size_label_hide', $serving_size_label_hide);
        update_option('mpprecipe_calories_label', $calories_label);
        update_option('mpprecipe_calories_label_hide', $calories_label_hide);
        update_option('mpprecipe_fat_label', $fat_label);
        update_option('mpprecipe_fat_label_hide', $fat_label_hide);
        update_option('mpprecipe_rating_label', $rating_label);
        update_option('mpprecipe_rating_label_hide', $rating_label_hide);
        update_option('mpprecipe_image_width', $image_width);
        update_option('mpprecipe_outer_border_style', $outer_border_style);
        update_option('mpprecipe_custom_save_image', $custom_save_image);
        update_option('mpprecipe_custom_print_image', $custom_print_image);
    } else {
        $mealplannerpro_partner_key        = get_option('mealplannerpro_partner_key');
        $mealplannerpro_recipe_button_hide = get_option('mealplannerpro_recipe_button_hide');
        $mealplannerpro_attribution_hide   = get_option('mealplannerpro_attribution_hide');
        $printed_permalink_hide            = get_option('mpprecipe_printed_permalink_hide');
        $printed_copyright_statement       = get_option('mpprecipe_printed_copyright_statement');
        $stylesheet                        = get_option('mpprecipe_stylesheet');
        $recipe_title_hide                 = get_option('recipe_title_hide');
        $image_hide                        = get_option('mpprecipe_image_hide');
        $image_hide_print                  = get_option('mpprecipe_image_hide_print');
        $print_link_hide                   = get_option('mpprecipe_print_link_hide');
        $ingredient_label                  = get_option('mpprecipe_ingredient_label');
        $ingredient_label_hide             = get_option('mpprecipe_ingredient_label_hide');
        $ingredient_list_type              = get_option('mpprecipe_ingredient_list_type');
        $instruction_label                 = get_option('mpprecipe_instruction_label');
        $instruction_label_hide            = get_option('mpprecipe_instruction_label_hide');
        $instruction_list_type             = get_option('mpprecipe_instruction_list_type');
        $notes_label                       = get_option('mpprecipe_notes_label');
        $notes_label_hide                  = get_option('mpprecipe_notes_label_hide');
        $prep_time_label                   = get_option('mpprecipe_prep_time_label');
        $prep_time_label_hide              = get_option('mpprecipe_prep_time_label_hide');
        $cook_time_label                   = get_option('mpprecipe_cook_time_label');
        $cook_time_label_hide              = get_option('mpprecipe_cook_time_label_hide');
        $total_time_label                  = get_option('mpprecipe_total_time_label');
        $total_time_label_hide             = get_option('mpprecipe_total_time_label_hide');
        $yield_label                       = get_option('mpprecipe_yield_label');
        $yield_label_hide                  = get_option('mpprecipe_yield_label_hide');
        $serving_size_label                = get_option('mpprecipe_serving_size_label');
        $serving_size_label_hide           = get_option('mpprecipe_serving_size_label_hide');
        $calories_label                    = get_option('mpprecipe_calories_label');
        $calories_label_hide               = get_option('mpprecipe_calories_label_hide');
        $fat_label                         = get_option('mpprecipe_fat_label');
        $fat_label_hide                    = get_option('mpprecipe_fat_label_hide');
        $rating_label                      = get_option('mpprecipe_rating_label');
        $rating_label_hide                 = get_option('mpprecipe_rating_label_hide');
        $image_width                       = get_option('mpprecipe_image_width');
        $outer_border_style                = get_option('mpprecipe_outer_border_style');
        $custom_save_image                 = get_option('mpprecipe_custom_save_image');
        $custom_print_image                = get_option('mpprecipe_custom_print_image');
    }

    $mealplannerpro_partner_key  = esc_attr($mealplannerpro_partner_key);
    $printed_copyright_statement = esc_attr($printed_copyright_statement);
    $ingredient_label            = esc_attr($ingredient_label);
    $instruction_label           = esc_attr($instruction_label);
    $notes_label                 = esc_attr($notes_label);
    $prep_time_label             = esc_attr($prep_time_label);
    $prep_time_label             = esc_attr($prep_time_label);
    $cook_time_label             = esc_attr($cook_time_label);
    $total_time_label            = esc_attr($total_time_label);
    $total_time_label            = esc_attr($total_time_label);
    $yield_label                 = esc_attr($yield_label);
    $serving_size_label          = esc_attr($serving_size_label);
    $calories_label              = esc_attr($calories_label);
    $fat_label                   = esc_attr($fat_label);
    $rating_label                = esc_attr($rating_label);
    $image_width                 = esc_attr($image_width);
	$custom_save_image           = esc_attr($custom_save_image);
	$custom_print_image          = esc_attr($custom_print_image);

    $mealplannerpro_recipe_button_hide = (strcmp($mealplannerpro_recipe_button_hide, 'Hide') == 0 ? 'checked="checked"' : '');
    $mealplannerpro_attribution_hide = (strcmp($mealplannerpro_attribution_hide, 'Hide') == 0 ? 'checked="checked"' : '');
    $printed_permalink_hide = (strcmp($printed_permalink_hide, 'Hide') == 0 ? 'checked="checked"' : '');
    $recipe_title_hide = (strcmp($recipe_title_hide, 'Hide') == 0 ? 'checked="checked"' : '');
    $image_hide = (strcmp($image_hide, 'Hide') == 0 ? 'checked="checked"' : '');
    $image_hide_print = (strcmp($image_hide_print, 'Hide') == 0 ? 'checked="checked"' : '');
    $print_link_hide = (strcmp($print_link_hide, 'Hide') == 0 ? 'checked="checked"' : '');

    // Stylesheet processing
    $stylesheet = (strcmp($stylesheet, 'mpprecipe-std') == 0 ? 'checked="checked"' : '');

    // Outer (hrecipe) border style
	$obs = '';
	$borders = array('None' => '', 'Solid' => '1px solid', 'Dotted' => '1px dotted', 'Dashed' => '1px dashed', 'Thick Solid' => '2px solid', 'Double' => 'double');
	foreach ($borders as $label => $code) {
		$obs .= '<option value="' . $code . '" ' . (strcmp($outer_border_style, $code) == 0 ? 'selected="true"' : '') . '>' . $label . '</option>';
	}

    $ingredient_label_hide   = (strcmp($ingredient_label_hide, 'Hide') == 0 ? 'checked="checked"' : '');
    $ing_ul                  = (strcmp($ingredient_list_type, 'ul') == 0 ? 'checked="checked"' : '');
    $ing_ol                  = (strcmp($ingredient_list_type, 'ol') == 0 ? 'checked="checked"' : '');
    $ing_p                   = (strcmp($ingredient_list_type, 'p') == 0 ? 'checked="checked"' : '');
    $ing_div                 = (strcmp($ingredient_list_type, 'div') == 0 ? 'checked="checked"' : '');
    $instruction_label_hide  = (strcmp($instruction_label_hide, 'Hide') == 0 ? 'checked="checked"' : '');
    $ins_ul                  = (strcmp($instruction_list_type, 'ul') == 0 ? 'checked="checked"' : '');
    $ins_ol                  = (strcmp($instruction_list_type, 'ol') == 0 ? 'checked="checked"' : '');
    $ins_p                   = (strcmp($instruction_list_type, 'p') == 0 ? 'checked="checked"' : '');
    $ins_div                 = (strcmp($instruction_list_type, 'div') == 0 ? 'checked="checked"' : '');
    $prep_time_label_hide    = (strcmp($prep_time_label_hide, 'Hide') == 0 ? 'checked="checked"' : '');
    $cook_time_label_hide    = (strcmp($cook_time_label_hide, 'Hide') == 0 ? 'checked="checked"' : '');
    $total_time_label_hide   = (strcmp($total_time_label_hide, 'Hide') == 0 ? 'checked="checked"' : '');
    $yield_label_hide        = (strcmp($yield_label_hide, 'Hide') == 0 ? 'checked="checked"' : '');
    $serving_size_label_hide = (strcmp($serving_size_label_hide, 'Hide') == 0 ? 'checked="checked"' : '');
    $calories_label_hide     = (strcmp($calories_label_hide, 'Hide') == 0 ? 'checked="checked"' : '');
    $fat_label_hide          = (strcmp($fat_label_hide, 'Hide') == 0 ? 'checked="checked"' : '');
    $rating_label_hide       = (strcmp($rating_label_hide, 'Hide') == 0 ? 'checked="checked"' : '');
    $notes_label_hide        = (strcmp($notes_label_hide, 'Hide') == 0 ? 'checked="checked"' : '');
    $other_options           = '';
    $other_options_array     = array('Rating', 'Prep Time', 'Cook Time', 'Total Time', 'Yield', 'Serving Size', 'Calories', 'Fat', 'Notes');

    foreach ($other_options_array as $option) {
        $name = strtolower(str_replace(' ', '-', $option));
        $value = strtolower(str_replace(' ', '_', $option)) . '_label';
        $value_hide = strtolower(str_replace(' ', '_', $option)) . '_label_hide';
        $other_options .= '<tr valign="top">
            <th scope="row">\'' . $option . '\' Label</th>
            <td><input type="text" name="' . $name . '-label" value="' . ${$value} . '" class="regular-text" /><br />
            <label><input type="checkbox" name="' . $name . '-label-hide" value="Hide" ' . ${$value_hide} . ' /> Don\'t show ' . $option . ' label</label></td>
        </tr>';
    }

    echo '<style>
        .form-table label { line-height: 2.5; }
        hr { border: 1px solid #DDD; border-left: none; border-right: none; border-bottom: none; margin: 30px 0; }
    </style>
    <div class="wrap">
        <form enctype="multipart/form-data" method="post" action="" name="mpprecipe_settings_form">
            <h2><img src="' . $mpprecipe_icon . '" /> MealPlannerPro Recipe Plugin Settings</h2>
			<h3>General</h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Stylesheet</th>
                    <td><label><input type="checkbox" name="stylesheet" value="mpprecipe-std" ' . $stylesheet . ' /> Use legacy MealPlannerPro recipe style (included)</label></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Recipe Title</th>
                    <td><label><input type="checkbox" name="recipe-title-hide" value="Hide" ' . $recipe_title_hide . ' /> Don\'t show Recipe Title in post (still shows in print view)</label></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Print Button</th>
                    <td><label><input type="checkbox" name="print-link-hide" value="Hide" ' . $print_link_hide . ' /> Don\'t show Print Button</label></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Image Width</th>
                    <td><label><input type="text" name="image-width" value="' . $image_width . '" class="regular-text" /> pixels</label></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Image Display</th>
                    <td>
                    	<label><input type="checkbox" name="image-hide" value="Hide" ' . $image_hide . ' /> Don\'t show Image in post</label>
                    	<br />
                    	<label><input type="checkbox" name="image-hide-print" value="Hide" ' . $image_hide_print . ' /> Don\'t show Image in print view</label>
                    </td>
                </tr>
                <tr valign="top">
                	<th scope="row">Border Style</th>
                	<td>
						<select name="outer-border-style">' . $obs . '</select>
					</td>
				</tr>
            </table>
            <hr />
			<h3>Printing</h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">
                    	Custom Print Button
                    	<br />
                    	(Optional)
                    </th>
                    <td>
                        <input placeholder="URL to custom Print button image" type="text" name="custom-print-image" value="' . $custom_print_image . '" class="regular-text" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Printed Output: Recipe Permalink</th>
                    <td><label><input type="checkbox" name="printed-permalink-hide" value="Hide" ' . $printed_permalink_hide . ' /> Don\'t show permalink in printed output</label></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Printed Output: Copyright Statement</th>
                    <td><input type="text" name="printed-copyright-statement" value="' . $printed_copyright_statement . '" class="regular-text" /></td>
                </tr>
            </table>
            <hr />
            <h3>Ingredients</h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">\'Ingredients\' Label</th>
                    <td><input type="text" name="ingredient-label" value="' . $ingredient_label . '" class="regular-text" /><br />
                    <label><input type="checkbox" name="ingredient-label-hide" value="Hide" ' . $ingredient_label_hide . ' /> Don\'t show Ingredients label</label></td>
                </tr>
                <tr valign="top">
                    <th scope="row">\'Ingredients\' List Type</th>
                    <td><input type="radio" name="ingredient-list-type" value="ul" ' . $ing_ul . ' /> <label>Bulleted List</label><br />
                    <input type="radio" name="ingredient-list-type" value="ol" ' . $ing_ol . ' /> <label>Numbered List</label><br />
                    <input type="radio" name="ingredient-list-type" value="p" ' . $ing_p . ' /> <label>Paragraphs</label><br />
                    <input type="radio" name="ingredient-list-type" value="div" ' . $ing_div . ' /> <label>Divs</label></td>
                </tr>
            </table>

            <hr />

            <h3>Instructions</h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">\'Instructions\' Label</th>
                    <td><input type="text" name="instruction-label" value="' . $instruction_label . '" class="regular-text" /><br />
                    <label><input type="checkbox" name="instruction-label-hide" value="Hide" ' . $instruction_label_hide . ' /> Don\'t show Instructions label</label></td>
                </tr>
                <tr valign="top">
                    <th scope="row">\'Instructions\' List Type</th>
                    <td><input type="radio" name="instruction-list-type" value="ol" ' . $ins_ol . ' /> <label>Numbered List</label><br />
                    <input type="radio" name="instruction-list-type" value="ul" ' . $ins_ul . ' /> <label>Bulleted List</label><br />
                    <input type="radio" name="instruction-list-type" value="p" ' . $ins_p . ' /> <label>Paragraphs</label><br />
                    <input type="radio" name="instruction-list-type" value="div" ' . $ins_div . ' /> <label>Divs</label></td>
                </tr>
            </table>

            <hr />

            <h3>Other Options</h3>
            <table class="form-table">
                ' . $other_options . '
            </table>

            <p><input type="submit" name="submit" id="submit" class="button-primary" value="Save Changes"></p>
        </form>
    </div>'. mpp_convert_ziplist_entries_form();
}


function mpprecipe_tinymce_plugin($plugin_array) {
	$plugin_array['mpprecipe'] = plugins_url( '/mpprecipe_editor_plugin.js?sver=' . MPPRECIPE_VERSION_NUM, __FILE__ );
	return $plugin_array;
}

function mpprecipe_register_tinymce_button($buttons) {
   array_push($buttons, "mpprecipe");
   return $buttons;
}

function mpprecipe_add_recipe_button() {

    // check user permissions
    if ( !current_user_can('edit_posts') && !current_user_can('edit_pages') ) {
   	return;
    }

	// check if WYSIWYG is enabled
	if ( get_user_option('rich_editing') == 'true') {
		add_filter('mce_external_plugins', 'mpprecipe_tinymce_plugin');
		add_filter('mce_buttons', 'mpprecipe_register_tinymce_button');
	}
}

// Content for the popup iframe when creating or editing a recipe
function mpprecipe_iframe_content($post_info = null, $get_info = null) {
    $recipe_id = 0;
    if ($post_info || $get_info) {

    	if( $get_info["add-recipe-button"] || strpos($get_info["post_id"], '-') !== false ) {
        	$iframe_title = "Update Your Recipe";
        	$submit = "Update Recipe";
        } else {
    		$iframe_title = "Add a Recipe";
    		$submit = "Add Recipe";
        }


        if ($get_info["post_id"] && !$get_info["add-recipe-button"] && strpos($get_info["post_id"], '-') !== false) {
            $recipe_id = preg_replace('/[0-9]*?\-/i', '', $get_info["post_id"]);
            $recipe = mpprecipe_select_recipe_db($recipe_id);
            $recipe_title = $recipe->recipe_title;
            $recipe_image = $recipe->recipe_image;
            $summary = $recipe->summary;
            $notes = $recipe->notes;
            $rating = $recipe->rating;
            $ss = array();
            $ss[(int)$rating] = 'selected="true"';
            $prep_time_input = '';
            $cook_time_input = '';
            $total_time_input = '';
            if (class_exists('DateInterval') and MPPRECIPE_AUTO_HANDLE_TOTALTIME ) {
                try {
                    $prep_time = new DateInterval($recipe->prep_time);
                    $prep_time_seconds = $prep_time->s;
                    $prep_time_minutes = $prep_time->i;
                    $prep_time_hours = $prep_time->h;
                    $prep_time_days = $prep_time->d;
                    $prep_time_months = $prep_time->m;
                    $prep_time_years = $prep_time->y;
                } catch (Exception $e) {
                    if ($recipe->prep_time != null) {
                        $prep_time_input = '<input type="text" name="prep_time" value="' . $recipe->prep_time . '"/>';
                    }
                }

                try {
                    $cook_time = new DateInterval($recipe->cook_time);
                    $cook_time_seconds = $cook_time->s;
                    $cook_time_minutes = $cook_time->i;
                    $cook_time_hours = $cook_time->h;
                    $cook_time_days = $cook_time->d;
                    $cook_time_months = $cook_time->m;
                    $cook_time_years = $cook_time->y;
                } catch (Exception $e) {
                    if ($recipe->cook_time != null) {
                        $cook_time_input = '<input type="text" name="cook_time" value="' . $recipe->cook_time . '"/>';
                    }
                }

                try {
                    $total_time = new DateInterval($recipe->total_time);
                    $total_time_seconds = $total_time->s;
                    $total_time_minutes = $total_time->i;
                    $total_time_hours = $total_time->h;
                    $total_time_days = $total_time->d;
                    $total_time_months = $total_time->m;
                    $total_time_years = $total_time->y;
                } catch (Exception $e) {
                    if ($recipe->total_time != null) {
                        $total_time_input = '<input type="text" name="total_time" value="' . $recipe->total_time . '"/>';
                    }
                }
            } else {
                if (preg_match('(^[A-Z0-9]*$)', $recipe->prep_time) == 1) {
                    preg_match('(\d*S)', $recipe->prep_time, $pts);
                    $prep_time_seconds = str_replace('S', '', $pts[0]);
                    preg_match('(\d*M)', $recipe->prep_time, $ptm, PREG_OFFSET_CAPTURE, strpos($recipe->prep_time, 'T'));
                    $prep_time_minutes = str_replace('M', '', $ptm[0][0]);
                    preg_match('(\d*H)', $recipe->prep_time, $pth);
                    $prep_time_hours = str_replace('H', '', $pth[0]);
                    preg_match('(\d*D)', $recipe->prep_time, $ptd);
                    $prep_time_days = str_replace('D', '', $ptd[0]);
                    preg_match('(\d*M)', $recipe->prep_time, $ptmm);
                    $prep_time_months = str_replace('M', '', $ptmm[0]);
                    preg_match('(\d*Y)', $recipe->prep_time, $pty);
                    $prep_time_years = str_replace('Y', '', $pty[0]);
                } else {
                    if ($recipe->prep_time != null) {
                        $prep_time_input = '<input type="text" name="prep_time" value="' . $recipe->prep_time . '"/>';
                    }
                }

                if (preg_match('(^[A-Z0-9]*$)', $recipe->cook_time) == 1) {
                    preg_match('(\d*S)', $recipe->cook_time, $cts);
                    $cook_time_seconds = str_replace('S', '', $cts[0]);
                    preg_match('(\d*M)', $recipe->cook_time, $ctm, PREG_OFFSET_CAPTURE, strpos($recipe->cook_time, 'T'));
                    $cook_time_minutes = str_replace('M', '', $ctm[0][0]);
                    preg_match('(\d*H)', $recipe->cook_time, $cth);
                    $cook_time_hours = str_replace('H', '', $cth[0]);
                    preg_match('(\d*D)', $recipe->cook_time, $ctd);
                    $cook_time_days = str_replace('D', '', $ctd[0]);
                    preg_match('(\d*M)', $recipe->cook_time, $ctmm);
                    $cook_time_months = str_replace('M', '', $ctmm[0]);
                    preg_match('(\d*Y)', $recipe->cook_time, $cty);
                    $cook_time_years = str_replace('Y', '', $cty[0]);
                } else {
                    if ($recipe->cook_time != null) {
                        $cook_time_input = '<input type="text" name="cook_time" value="' . $recipe->cook_time . '"/>';
                    }
                }

                if (preg_match('(^[A-Z0-9]*$)', $recipe->total_time) == 1) {
                    preg_match('(\d*S)', $recipe->total_time, $tts);
                    $total_time_seconds = str_replace('S', '', $tts[0]);
                    preg_match('(\d*M)', $recipe->total_time, $ttm, PREG_OFFSET_CAPTURE, strpos($recipe->total_time, 'T'));
                    $total_time_minutes = str_replace('M', '', $ttm[0][0]);
                    preg_match('(\d*H)', $recipe->total_time, $tth);
                    $total_time_hours = str_replace('H', '', $tth[0]);
                    preg_match('(\d*D)', $recipe->total_time, $ttd);
                    $total_time_days = str_replace('D', '', $ttd[0]);
                    preg_match('(\d*M)', $recipe->total_time, $ttmm);
                    $total_time_months = str_replace('M', '', $ttmm[0]);
                    preg_match('(\d*Y)', $recipe->total_time, $tty);
                    $total_time_years = str_replace('Y', '', $tty[0]);
                } else {
                    if ($recipe->total_time != null) {
                        $total_time_input = '<input type="text" name="total_time" value="' . $recipe->total_time . '"/>';
                    }
                }
            }

            $yield = $recipe->yield;
            $serving_size = $recipe->serving_size;
            $calories = $recipe->calories;
            $fat = $recipe->fat;
            $ingredients = $recipe->ingredients;
            $instructions = $recipe->instructions;
        } else {
        	foreach ($post_info as $key=>$val) {
        		$post_info[$key] = stripslashes($val);
        	}

            $recipe_id = $post_info["recipe_id"];
            if( !$get_info["add-recipe-button"] )
                 $recipe_title = get_the_title( $get_info["post_id"] );
            else
                 $recipe_title = $post_info["recipe_title"];
            $recipe_image = $post_info["recipe_image"];
            $summary = $post_info["summary"];
            $notes = $post_info["notes"];
            $rating = $post_info["rating"];
            $prep_time_seconds = $post_info["prep_time_seconds"];
            $prep_time_minutes = $post_info["prep_time_minutes"];
            $prep_time_hours = $post_info["prep_time_hours"];
            $prep_time_days = $post_info["prep_time_days"];
            $prep_time_weeks = $post_info["prep_time_weeks"];
            $prep_time_months = $post_info["prep_time_months"];
            $prep_time_years = $post_info["prep_time_years"];
            $cook_time_seconds = $post_info["cook_time_seconds"];
            $cook_time_minutes = $post_info["cook_time_minutes"];
            $cook_time_hours = $post_info["cook_time_hours"];
            $cook_time_days = $post_info["cook_time_days"];
            $cook_time_weeks = $post_info["cook_time_weeks"];
            $cook_time_months = $post_info["cook_time_months"];
            $cook_time_years = $post_info["cook_time_years"];
            $total_time_seconds = $post_info["total_time_seconds"];
            $total_time_minutes = $post_info["total_time_minutes"];
            $total_time_hours = $post_info["total_time_hours"];
            $total_time_days = $post_info["total_time_days"];
            $total_time_weeks = $post_info["total_time_weeks"];
            $total_time_months = $post_info["total_time_months"];
            $total_time_years = $post_info["total_time_years"];
            $yield = $post_info["yield"];
            $serving_size = $post_info["serving_size"];
            $calories = $post_info["calories"];
            $fat = $post_info["fat"];
            $ingredients = $post_info["ingredients"];
            $instructions = $post_info["instructions"];
            if ($recipe_title != null && $recipe_title != '' && $ingredients != null && $ingredients != '') {
                $recipe_id = mpprecipe_insert_db($post_info);
            }
        }
    }

	$recipe_title       = esc_attr($recipe_title);
	$recipe_image       = esc_attr($recipe_image);
	$prep_time_hours    = esc_attr($prep_time_hours);
	$prep_time_minutes  = esc_attr($prep_time_minutes);
	$cook_time_hours    = esc_attr($cook_time_hours);
	$cook_time_minutes  = esc_attr($cook_time_minutes);
	$total_time_hours   = esc_attr($total_time_hours);
	$total_time_minutes = esc_attr($total_time_minutes);
	$yield              = esc_attr($yield);
	$serving_size       = esc_attr($serving_size);
	$calories           = esc_attr($calories);
	$fat                = esc_attr($fat);
	$ingredients        = esc_textarea($ingredients);
	$instructions       = esc_textarea($instructions);
	$summary            = esc_textarea($summary);
	$notes              = esc_textarea($notes);

    $id = (int) $_REQUEST["post_id"];
    $plugindir = MPPRECIPE_PLUGIN_DIRECTORY;
    $submitform = '';
    if ($post_info != null) {
        $submitform .= "<script>window.onload = MPPRecipeSubmitForm;</script>";
    }

    if (class_exists('DateInterval') and MPPRECIPE_AUTO_HANDLE_TOTALTIME ) 
        $total_time_input_container = '';
    else
    {
        $total_time_input_container = <<<HTML
                <p class="cls"><label>Total Time</label>
                    $total_time_input
                    <span class="time">
                        <span><input type='number' min="0" max="240" id='total_time_hours' name='total_time_hours' value='$total_time_hours' /><label>hours</label></span>
                        <span><input type='number' min="0" max="60"  id='total_time_minutes' name='total_time_minutes' value='$total_time_minutes' /><label>minutes</label></span>
                    </span>
                </p>
HTML;
    }

    echo <<< HTML

<!DOCTYPE html>
<head>
		<link rel="stylesheet" href="$plugindir/mpprecipe-dlog.css" type="text/css" media="all" />
    <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.5.1/jquery.min.js"></script>
    <script type="text/javascript">//<!CDATA[

        function MPPRecipeSubmitForm() {
            var title = document.forms['recipe_form']['recipe_title'].value;

            if (title==null || title=='') {
                $('#recipe-title input').addClass('input-error');
                $('#recipe-title').append('<p class="error-message">You must enter a title for your recipe.</p>');

                return false;
            }
            var ingredients = $('#mpprecipe_ingredients textarea').val();
            if (ingredients==null || ingredients=='' || ingredients==undefined) {
                $('#mpprecipe_ingredients textarea').addClass('input-error');
                $('#mpprecipe_ingredients').append('<p class="error-message">You must enter at least one ingredient.</p>');

                return false;
            }
            window.parent.MPPRecipeInsertIntoPostEditor('$recipe_id');
            top.tinymce.activeEditor.windowManager.close(window);
        }

        $(document).ready(function() {
            $('#more-options').hide();
            $('#more-options-toggle').click(function() {
                $('#more-options').toggle(400);
                return false;
            });
        });
    //]]>
    </script>
    $submitform
</head>
<body id="mpprecipe-uploader">
    <form enctype='multipart/form-data' method='post' action='' name='recipe_form'>
        <h3 class='mpprecipe-title'>$iframe_title</h3>
        <div id='mpprecipe-form-items'>
            <input type='hidden' name='post_id' value='$id' />
            <input type='hidden' name='recipe_id' value='$recipe_id' />
            <p id='recipe-title'><label>Recipe Title <span class='required'>*</span></label> <input type='text' name='recipe_title' value='$recipe_title' /></p>
            <p id='recipe-image'><label>Recipe Image</label> <input type='text' name='recipe_image' value='$recipe_image' /></p>
            <p id='mpprecipe_ingredients'  class='cls'><label>Ingredients <span class='required'>*</span> <small>Put each ingredient on a separate line.  There is no need to use bullets for your ingredients. To add sub-headings put them on a new line between [...]. Example will be - [for the dressing:]</small></label><textarea name='ingredients'>$ingredients</textarea></label></p>
            <p id='mpprecipe-instructions' class='cls'><label>Instructions <small>Press return after each instruction. There is no need to number your instructions.</small></label><textarea name='instructions'>$instructions</textarea></label></p>
            <p><a href='#' id='more-options-toggle'>More options</a></p>
            <div id='more-options'>
                <p class='cls'><label>Summary</label> <textarea name='summary'>$summary</textarea></label></p>
                <p class='cls'><label>Rating</label>
                	<span class='rating'>
						<select name="rating">
							  <option value="0">None</option>
							  <option value="1" $ss[1]>1 Star</option>
							  <option value="2" $ss[2]>2 Stars</option>
							  <option value="3" $ss[3]>3 Stars</option>
							  <option value="4" $ss[4]>4 Stars</option>
							  <option value="5" $ss[5]>5 Stars</option>
						</select>
					</span>
				</p>
                <p class="cls"><label>Prep Time</label>
                    $prep_time_input
                    <span class="time">
                        <span><input type='number' min="0" max="24" id='prep_time_hours' name='prep_time_hours' value='$prep_time_hours' /><label>hours</label></span>
                        <span><input type='number' min="0" max="60" id='prep_time_minutes' name='prep_time_minutes' value='$prep_time_minutes' /><label>minutes</label></span>
                    </span>
                </p>
                <p class="cls"><label>Cook Time</label>
                    $cook_time_input
                    <span class="time">
                    	<span><input type='number' min="0" max="24" id='cook_time_hours' name='cook_time_hours' value='$cook_time_hours' /><label>hours</label></span>
                        <span><input type='number' min="0" max="60" id='cook_time_minutes' name='cook_time_minutes' value='$cook_time_minutes' /><label>minutes</label></span>
                    </span>
                </p>
                $total_time_input_container
                <p><label>Yield</label> <input type='text' name='yield' value='$yield' /></p>
                <p><label>Serving Size</label> <input type='text' name='serving_size' value='$serving_size' /></p>
                <p><label>Calories</label> <input type='text' name='calories' value='$calories' /></p>
                <p><label>Fat</label> <input type='text' name='fat' value='$fat' /></p>
                <p class='cls'><label>Notes</label> <textarea name='notes'>$notes</textarea></label></p>
            </div>
            <input type='submit' value='$submit' name='add-recipe-button' />
        </div>
    </form>
</body>

<script>
        var g = function( id ) { return document.getElementById( id ) }
        var v = function( id ) {
            var v = parseInt( g(id).value )  
            return isNaN( v ) ? 0 : v
        }

        function calc()
        {
            var h = v('cook_time_hours')   + v('prep_time_hours')
            var m = v('cook_time_minutes') + v('prep_time_minutes')

            var h_from_m  = Math.floor(m/60)

            // minutes after hour-equivalents removed
            var m = m % (60*Math.max(h_from_m,1)) 
            var h = h + h_from_m

            g('total_time_hours').value   =  h
            g('total_time_minutes').value =  m
        }

        g('cook_time_hours').onchange   = calc
        g('cook_time_minutes').onchange = calc
        g('prep_time_hours').onchange   = calc
        g('prep_time_minutes').onchange = calc

</script>

HTML;
}

/**
 * Deal with the aggregation of input duration-time parts.
 */
function collate_time_input( $type, $post )
{
    $duration_units = array(
        $type . '_time_years'  => 'Y',
        $type . '_time_months' => 'M',
        $type . '_time_days'   => 'D',
    );
    $time_units    = array(
        $type . '_time_hours'   => 'H',
        $type . '_time_minutes' => 'M',
        $type . '_time_seconds' => 'S',
    );

    if (!( $post[$type . '_time_years']   || $post[$type . '_time_months'] 
        || $post[$type . '_time_days']    || $post[$type . '_time_hours'] 
        || $post[$type . '_time_minutes'] || $post[$type . '_time_seconds']
    ))
        $o = $post[$type . '_time'];
    else
    {
        $o = 'P';
        foreach($duration_units as $d => $u)
        {
            if( $post[$d] ) $time .= $post[$d] . $u;
        }
        if (   $post[$type . '_time_hours'] 
            || $post[$type . '_time_minutes'] 
            || $post[$type . '_time_seconds']
        )
            $o .= 'T';
        foreach( $time_units as $t => $u )
        {
            if( $post[$t] ) $o .= $post[$t] . $u;
        }
    } 

    return $o;
}

// Inserts the recipe into the database
function mpprecipe_insert_db($post_info) {
    global $wpdb;

    $recipe      = array ();
    $recipe_keys = array (
        "recipe_title" , "recipe_image", "summary", "rating", "yield", 
        "serving_size", "calories", "fat", "ingredients", "instructions", 
        "notes"
    );
    foreach( $recipe_keys as $k )
        $recipe[ $k ] = $post_info[ $k ];

    $recipe["prep_time"]  = collate_time_input( 'prep',  $post_info );
    $recipe["cook_time"]  = collate_time_input( 'cook',  $post_info );
    $recipe["total_time"] = collate_time_input( 'total', $post_info );

    if( mpprecipe_select_recipe_db($recipe_id) )
        $wpdb->update( $wpdb->prefix . "mpprecipe_recipes", $recipe, array( 'recipe_id' => $recipe_id ));
    else
    {
    	$recipe["post_id"] = $post_info["post_id"];
        $wpdb->insert( $wpdb->prefix . "mpprecipe_recipes", $recipe );
        $recipe_id = $wpdb->insert_id;
    }

    return $recipe_id;
}

// Inserts the recipe into the post editor
function mpprecipe_plugin_footer() {
	$url = site_url();
	$plugindir = MPPRECIPE_PLUGIN_DIRECTORY;

    echo <<< HTML
    <style type="text/css" media="screen">
        #wp_editrecipebtns { position:absolute;display:block;z-index:999998; }
        #wp_editrecipebtn { margin-right:20px; }
        #wp_editrecipebtn,#wp_delrecipebtn { cursor:pointer; padding:12px;background:#010101; -moz-border-radius:8px;-khtml-border-radius:8px;-webkit-border-radius:8px;border-radius:8px; filter:alpha(opacity=80); -moz-opacity:0.8; -khtml-opacity: 0.8; opacity: 0.8; }
        #wp_editrecipebtn:hover,#wp_delrecipebtn:hover { background:#000; filter:alpha(opacity=100); -moz-opacity:1; -khtml-opacity: 1; opacity: 1; }
        .mce-window .mce-container-body.mce-abs-layout
        {
            -webkit-overflow-scrolling: touch;
            overflow-y: auto;
        }
    </style>
    <script>//<![CDATA[
    var baseurl = '$url';          // This variable is used by the editor plugin
    var plugindir = '$plugindir';  // This variable is used by the editor plugin

        function MPPRecipeInsertIntoPostEditor(rid) {
            tb_remove();

            var ed;

            var output = '<img id="mpprecipe-recipe-';
            output += rid;
						output += '" class="mpprecipe-recipe" src="' + plugindir + '/mpprecipe-placeholder.png" alt="" />';

        	if ( typeof tinyMCE != 'undefined' && ( ed = tinyMCE.activeEditor ) && !ed.isHidden() && ed.id=='content') {  //path followed when in Visual editor mode
        		ed.focus();
        		if ( tinymce.isIE )
        			ed.selection.moveToBookmark(tinymce.EditorManager.activeEditor.windowManager.bookmark);

        		ed.execCommand('mceInsertContent', false, output);

        	} else if ( typeof edInsertContent == 'function' ) {  // path followed when in HTML editor mode
                output = '[mpprecipe-recipe:';
                output += rid;
                output += ']';
                edInsertContent(edCanvas, output);
        	} else {
                output = '[mpprecipe-recipe:';
                output += rid;
                output += ']';
        		jQuery( edCanvas ).val( jQuery( edCanvas ).val() + output );
        	}
        }
    //]]></script>
HTML;
}

add_action('admin_footer', 'mpprecipe_plugin_footer');

// Converts the image to a recipe for output
function mpprecipe_convert_to_recipe($post_text) {
    $output = $post_text;
    $needle_old = 'id="mpprecipe-recipe-';
    $preg_needle_old = '/(id)=("(mpprecipe-recipe-)[0-9^"]*")/i';
    $needle = '[mpprecipe-recipe:';
    $preg_needle = '/\[mpprecipe-recipe:([0-9]+)\]/i';

    if (strpos($post_text, $needle_old) !== false) {
        // This is for backwards compatability. Please do not delete or alter.
        preg_match_all($preg_needle_old, $post_text, $matches);
        foreach ($matches[0] as $match) {
            $recipe_id = str_replace('id="mpprecipe-recipe-', '', $match);
            $recipe_id = str_replace('"', '', $recipe_id);
            $recipe = mpprecipe_select_recipe_db($recipe_id);
            $formatted_recipe = mpprecipe_format_recipe($recipe);
						$output = str_replace('<img id="mpprecipe-recipe-' . $recipe_id . '" class="mpprecipe-recipe" src="' . plugins_url() . '/' . dirname(plugin_basename(__FILE__)) . '/mpprecipe-placeholder.png?ver=1.0" alt="" />', $formatted_recipe, $output);
        }
    }

    if (strpos($post_text, $needle) !== false) {
        preg_match_all($preg_needle, $post_text, $matches);
        foreach ($matches[0] as $match) {
            $recipe_id = str_replace('[mpprecipe-recipe:', '', $match);
            $recipe_id = str_replace(']', '', $recipe_id);
            $recipe = mpprecipe_select_recipe_db($recipe_id);
            $formatted_recipe = mpprecipe_format_recipe($recipe);
            $output = str_replace('[mpprecipe-recipe:' . $recipe_id . ']', $formatted_recipe, $output);
        }
    }

    return mpp_format_ziplist_entries( $output );
}

function mpp_format_ziplist_entries( $output )
{
    $zl_id   = 'amd-zlrecipe-recipe';
    # Match string that
    # - opens with     <img id="      or     [
    # - contains amd-zlrecipe-recipe 
    # - followed by a : or -
    # - followed by a string of digits
    # - closed by a    "(anything) /> or     ]
    # FIXME: Restore legacy support.
    $regex   = '/\[amd-zlrecipe-recipe:(\d+)\]/i';
    $matches = array();

    if( strpos( $output, $zl_id ) === False )
        return $output;

    preg_match_all( $regex, $output, $matches );

    foreach( $matches[1] as $match_index => $recipe_id )
    {
        $matched_str      = $matches[0][$match_index];

        $recipe           = mpprecipe_select_recipe_db( $recipe_id, 'amd_zlrecipe_recipes' );
        $formatted_recipe = mpprecipe_format_recipe($recipe);

        $output = str_replace( $matched_str, $formatted_recipe, $output );
    }

    return $output;
}


add_filter('the_content', 'mpprecipe_convert_to_recipe');

// Pulls a recipe from the db
function mpprecipe_select_recipe_db($recipe_id, $table = 'mpprecipe_recipes' ) {
    global $wpdb;
    return $wpdb->get_row( "SELECT * FROM $wpdb->prefix$table WHERE recipe_id=$recipe_id" );
}

// Format an ISO8601 duration for human readibility
function mpprecipe_format_duration($duration) 
{
    $date_abbr = array(
        'y' => 'year', 'm' => 'month', 
        'd' => 'day', 'h' => 'hour', 
        'i' => 'minute', 's' => 'second'
    );
	$result = '';

    if (class_exists('DateInterval'))
    {
		try {
            if( !($duration instanceof DateInterval ))
		        $duration = new DateInterval($duration);

            foreach ($date_abbr as $abbr => $name) 
            {
                if ($duration->$abbr > 0) 
                {
					$result .= $duration->$abbr . ' ' . $name;

					if ($duration->$abbr > 1) 
						$result .= 's';

					$result .= ', ';
				}
			}

			$result = trim($result, ' \t,');
		} catch (Exception $e) {
			$result = $duration;
		}
	} else { // else we have to do the work ourselves so the output is pretty
		$arr = explode('T', $duration);

        // This mimics the DateInterval property name
        $arr[1]   = str_replace('M', 'I', $arr[1]); 

		$duration = implode('T', $arr);

        foreach ($date_abbr as $abbr => $name) 
        {
            if (preg_match('/(\d+)' . $abbr . '/i', $duration, $val))
            {
                $result .= $val[1] . ' ' . $name;

                if ($val[1] > 1)
                    $result .= 's';

                $result .= ', ';
            }
		}

		$result = trim($result, ' \t,');
	}
	return $result;
}

// function to include the javascript for the Add Recipe button
function mpprecipe_process_head() {

	// Always add the print script
    $header_html='<script type="text/javascript" async="" src="' . MPPRECIPE_PLUGIN_DIRECTORY . 'mpprecipe_print.js"></script>
';

	// Recipe styling
	$css = get_option('mpprecipe_stylesheet');
	if (strcmp($css, '') != 0) {
		$header_html .= '<link charset="utf-8" href="' . MPPRECIPE_PLUGIN_DIRECTORY .  $css . '.css" rel="stylesheet" type="text/css" />
';
	/* Dev Testing	$header_html .= '<link charset="utf-8" href="http://dev.mealplannerpro.com.s3.amazonaws.com/' . $css . '.css" rel="stylesheet" type="text/css" />
'; */
	}

    echo $header_html;
}
add_filter('wp_head', 'mpprecipe_process_head');

// Replaces the [a|b] pattern with text a that links to b
// Replaces _words_ with an italic span and *words* with a bold span
function mpprecipe_richify_item($item, $class) {
	$output = preg_replace('/\[([^\]\|\[]*)\|([^\]\|\[]*)\]/', '<a href="\\2" class="' . $class . '-link" target="_blank">\\1</a>', $item);
	$output = preg_replace('/(^|\s)\*([^\s\*][^\*]*[^\s\*]|[^\s\*])\*(\W|$)/', '\\1<span class="bold">\\2</span>\\3', $output);
	return preg_replace('/(^|\s)_([^\s_][^_]*[^\s_]|[^\s_])_(\W|$)/', '\\1<span class="italic">\\2</span>\\3', $output);
}

function mpprecipe_break( $otag, $text, $ctag) {
	$output = "";
	$split_string = explode( "\r\n\r\n", $text, 10 );
	foreach ( $split_string as $str )
	{
		$output .= $otag . $str . $ctag;
	}
	return $output;
}

// Processes markup for attributes like labels, images and links
// !Label
// %image
function mpprecipe_format_item($item, $elem, $class, $itemprop, $id, $i) {

	if (preg_match("/^%(\S*)/", $item, $matches)) {	// IMAGE Updated to only pull non-whitespace after some blogs were adding additional returns to the output
		$output = '<img class = "' . $class . '-image" src="' . $matches[1] . '" />';
		return $output; // Images don't also have labels or links so return the line immediately.
	}

	if (preg_match("/^!(.*)/", $item, $matches)) {	// LABEL
		$class .= '-label';
		$elem = 'div';
		$item = $matches[1];
		$output = '<' . $elem . ' id="' . $id . $i . '" class="' . $class . '" >';	// No itemprop for labels
	} else {
		$output = '<' . $elem . ' id="' . $id . $i . '" class="' . $class . '" itemprop="' . $itemprop . '">';
	}

	$output .= mpprecipe_richify_item($item, $class);
	$output .= '</' . $elem . '>';

	return $output;
}

// Formats the recipe for output
function mpprecipe_format_recipe($recipe) {
    $output = "";
    $permalink = get_permalink();

	// Output main recipe div with border style
	$style_tag = '';
	$border_style = get_option('mpprecipe_outer_border_style');
	if ($border_style != null)
		$style_tag = 'style="border: ' . $border_style . ';"';
    $output .= '
    <div id="mpprecipe-container-' . $recipe->recipe_id . '" class="mpprecipe-container-border" ' . $style_tag . '>
    <div itemscope itemtype="http://schema.org/Recipe" id="mpprecipe-container" class="serif mpprecipe">
      <div id="mpprecipe-innerdiv">
        <div class="item b-b">';

    // Add Print and Save Button
    $output .= mpp_buttons( $recipe->recipe_id );

    // add the MealPlannerPro recipe button
    if (strcmp(get_option('mealplannerpro_recipe_button_hide'), 'Hide') != 0) {
		$output .= '<div id="mpp-recipe-link-' . $recipe->recipe_id . '" class="mpp-recipe-link fl-r mpp-rmvd"></div>';
	}

	// add the title and close the item class
	$hide_tag = '';
	if (strcmp(get_option('recipe_title_hide'), 'Hide') == 0)
        $hide_tag = ' texthide';
	$output .= '<div id="mpprecipe-title" itemprop="name" class="b-b h-1 strong' . $hide_tag . '" >' . $recipe->recipe_title . '</div>
      </div>';

	// open the zlmeta and fl-l container divs
	$output .= '<div class="zlmeta zlclear">
      <div class="fl-l width-50">';

    if ($recipe->rating != 0) {
        $output .= '<p id="mpprecipe-rating" itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">';
        if (strcmp(get_option('mpprecipe_rating_label_hide'), 'Hide') != 0) {
        	$output .= get_option('mpprecipe_rating_label') . ' ';
        }
        $output .= '<span class="rating rating-' . $recipe->rating . '"><span itemprop="ratingValue">' . $recipe->rating . '</span><span itemprop="reviewCount" style="display: none;">1</span></span>
       </p>';
    }

    // recipe timing
    if ($recipe->prep_time != null) {
    	$prep_time = mpprecipe_format_duration($recipe->prep_time);

        $output .= '<p id="mpprecipe-prep-time">';
        if (strcmp(get_option('mpprecipe_prep_time_label_hide'), 'Hide') != 0) {
            $output .= get_option('mpprecipe_prep_time_label') . ' ';
        }
        $output .= '<span itemprop="prepTime" content="' . $recipe->prep_time . '">' . $prep_time . '</span></p>';
    }
    if ($recipe->cook_time != null) {
        $cook_time = mpprecipe_format_duration($recipe->cook_time);

        $output .= '<p id="mpprecipe-cook-time">';
        if (strcmp(get_option('mpprecipe_cook_time_label_hide'), 'Hide') != 0) {
            $output .= get_option('mpprecipe_cook_time_label') . ' ';
        }
        $output .= '<span itemprop="cookTime" content="' . $recipe->cook_time . '">' . $cook_time . '</span></p>';
    }


    $total_time         = null;
    $total_time_content = null;

    if ($recipe->total_time != null)
    {
        $total_time         = mpprecipe_format_duration($recipe->total_time);
        $total_time_content = $recipe->total_time;
    }
    elseif( ($recipe->prep_time || $recipe->cook_time ) and class_exists( 'DateInterval' ) and MPPRECIPE_AUTO_HANDLE_TOTALTIME )
    { 
        $t1 = new DateTime();
        $t2 = new DateTime();

        if( $recipe->prep_time ) $t1->add( new DateInterval($recipe->prep_time));
        if( $recipe->cook_time ) $t1->add( new DateInterval($recipe->cook_time));

        $ti = $t2->diff($t1);
        $total_time_content = $ti->format('P%yY%mM%dDT%hH%iM%sS');
    }

    if( $total_time_content )
    {
        $total_time = mpprecipe_format_duration($total_time_content);
        $output .= '<p id="mpprecipe-total-time">';
        if (strcmp(get_option('mpprecipe_total_time_label_hide'), 'Hide') != 0) 
            $output .= get_option('mpprecipe_total_time_label') . ' ';

        $output .= '<span itemprop="totalTime" content="' . $total_time_content . '">' . $total_time . '</span></p>';
    }

    //!! close the first container div and open the second
    $output .= '</div>
      <div class="fl-l width-50">';

    //!! yield and nutrition
    if ($recipe->yield != null) {
        $output .= '<p id="mpprecipe-yield">';
        if (strcmp(get_option('mpprecipe_yield_label_hide'), 'Hide') != 0) {
            $output .= get_option('mpprecipe_yield_label') . ' ';
        }
        $output .= '<span itemprop="recipeYield">' . $recipe->yield . '</span></p>';
    }

    if ($recipe->serving_size != null || $recipe->calories != null || $recipe->fat != null) {
        $output .= '<div id="mpprecipe-nutrition" itemprop="nutrition" itemscope itemtype="http://schema.org/NutritionInformation">';
        if ($recipe->serving_size != null) {
            $output .= '<p id="mpprecipe-serving-size">';
            if (strcmp(get_option('mpprecipe_serving_size_label_hide'), 'Hide') != 0) {
                $output .= get_option('mpprecipe_serving_size_label') . ' ';
            }
            $output .= '<span itemprop="servingSize">' . $recipe->serving_size . '</span></p>';
        }
        if ($recipe->calories != null) {
            $output .= '<p id="mpprecipe-calories">';
            if (strcmp(get_option('mpprecipe_calories_label_hide'), 'Hide') != 0) {
                $output .= get_option('mpprecipe_calories_label') . ' ';
            }
            $output .= '<span itemprop="calories">' . $recipe->calories . '</span></p>';
        }
        if ($recipe->fat != null) {
            $output .= '<p id="mpprecipe-fat">';
            if (strcmp(get_option('mpprecipe_fat_label_hide'), 'Hide') != 0) {
                $output .= get_option('mpprecipe_fat_label') . ' ';
            }
            $output .= '<span itemprop="fatContent">' . $recipe->fat . '</span></p>';
        }
        $output .= '</div>';
    }

    //!! close the second container
    $output .= '</div>
      <div class="zlclear">
      </div>
    </div>';

    //!! create image and summary container
    if ($recipe->recipe_image != null || $recipe->summary != null) {
        $output .= '<div class="img-desc-wrap">';
		if ($recipe->recipe_image != null) {
			$style_tag = '';
			$class_tag = '';
			$image_width = get_option('mpprecipe_image_width');
			if ($image_width != null) {
				$style_tag = 'style="width: ' . $image_width . 'px;"';
			}
			if (strcmp(get_option('mpprecipe_image_hide'), 'Hide') == 0)
				$class_tag .= ' hide-card';
			if (strcmp(get_option('mpprecipe_image_hide_print'), 'Hide') == 0)
				$class_tag .= ' hide-print';
			$output .= '<p class="t-a-c' . $class_tag . '">
			  <img class="photo" itemprop="image" src="' . $recipe->recipe_image . '" title="' . $recipe->recipe_title . '" alt="' . $recipe->recipe_title . '" ' . $style_tag . ' />
			</p>';
		}
		if ($recipe->summary != null) {
			$output .= '<div id="mpprecipe-summary" itemprop="description">';
			$output .= mpprecipe_break( '<p class="summary italic">', mpprecipe_richify_item($recipe->summary, 'summary'), '</p>' );
			$output .= '</div>';
		}
		$output .= '</div>';
	}

    $ingredient_type= '';
    $ingredient_tag = '';
    $ingredient_class = '';
    $ingredient_list_type_option = get_option('mpprecipe_ingredient_list_type');
    if (strcmp($ingredient_list_type_option, 'ul') == 0 || strcmp($ingredient_list_type_option, 'ol') == 0) {
        $ingredient_type = $ingredient_list_type_option;
        $ingredient_tag = 'li';
    } else if (strcmp($ingredient_list_type_option, 'p') == 0 || strcmp($ingredient_list_type_option, 'div') == 0) {
        $ingredient_type = 'span';
        $ingredient_tag = $ingredient_list_type_option;
    }

    if (strcmp(get_option('mpprecipe_ingredient_label_hide'), 'Hide') != 0) {
        $output .= '<p id="mpprecipe-ingredients" class="h-4 strong">' . get_option('mpprecipe_ingredient_label') . '</p>';
    }

    $output .= '<' . $ingredient_type . ' id="mpprecipe-ingredients-list">';
    $i = 0;
    $ingredients = explode("\n", $recipe->ingredients);
    foreach ($ingredients as $ingredient) {
		$output .= mpprecipe_format_item($ingredient, $ingredient_tag, 'ingredient', 'ingredients', 'mpprecipe-ingredient-', $i);
        $i++;
    }

    $output .= '</' . $ingredient_type . '>';

	// add the instructions
    if ($recipe->instructions != null) {

        $instruction_type= '';
        $instruction_tag = '';
        $instruction_list_type_option = get_option('mpprecipe_instruction_list_type');
        if (strcmp($instruction_list_type_option, 'ul') == 0 || strcmp($instruction_list_type_option, 'ol') == 0) {
            $instruction_type = $instruction_list_type_option;
            $instruction_tag = 'li';
        } else if (strcmp($instruction_list_type_option, 'p') == 0 || strcmp($instruction_list_type_option, 'div') == 0) {
            $instruction_type = 'span';
            $instruction_tag = $instruction_list_type_option;
        }

        $instructions = explode("\n", $recipe->instructions);
        if (strcmp(get_option('mpprecipe_instruction_label_hide'), 'Hide') != 0) {
            $output .= '<p id="mpprecipe-instructions" class="h-4 strong">' . get_option('mpprecipe_instruction_label') . '</p>';
        }
        $output .= '<' . $instruction_type . ' id="mpprecipe-instructions-list" class="instructions">';
        $j = 0;
        foreach ($instructions as $instruction) {
            if (strlen($instruction) > 1) {
            	$output .= mpprecipe_format_item($instruction, $instruction_tag, 'instruction', 'recipeInstructions', 'mpprecipe-instruction-', $j);
                $j++;
            }
        }
        $output .= '</' . $instruction_type . '>';
    }

    //!! add notes section
    if ($recipe->notes != null) {
        if (strcmp(get_option('mpprecipe_notes_label_hide'), 'Hide') != 0) {
            $output .= '<p id="mpprecipe-notes" class="h-4 strong">' . get_option('mpprecipe_notes_label') . '</p>';
        }

		$output .= '<div id="mpprecipe-notes-list">';
		$output .= mpprecipe_break( '<p class="notes">', mpprecipe_richify_item($recipe->notes, 'notes'), '</p>' );
		$output .= '</div>';

	}

	// MealPlannerPro version
    $output .= '<div class="mealplannerpro-recipe-plugin" style="display: none;">' . MPPRECIPE_VERSION_NUM . '</div>';

    // Add permalink for printed output before closing the innerdiv
    if (strcmp(get_option('mpprecipe_printed_permalink_hide'), 'Hide') != 0) {
		$output .= '<a id="mpp-printed-permalink" href="' . $permalink . '"title="Permalink to Recipe">' . $permalink . '</a>';
	}

    $output .= '</div>';

    // Add copyright statement for printed output (outside the dotted print line)
    $printed_copyright_statement = get_option('mpprecipe_printed_copyright_statement');
    if (strlen($printed_copyright_statement) > 0) {
		$output .= '<div id="mpp-printed-copyright-statement" itemprop="copyrightHolder">' . $printed_copyright_statement . '</div>';
	}

	$output .= '</div></div>';

    return $output;
}

function mpp_save_recipe_js()
{
    return "javascript:(function(){var host='http://mealplannerpro.com/';var s=document.createElement('script');s.type= 'text/javascript';try{if (!document.body) throw (0);s.src=host + '/javascripts/savebutton.js?date='+(new Date().getTime());document.body.appendChild(s);}catch (e){alert('Please try again after the page has finished loading.');}})();";
}

/* 
 * Add Mealplannerpro.com buttons.
 */
function mpp_buttons( $recipe_id )
{
    $dir = MPPRECIPE_PLUGIN_DIRECTORY;

    if (strcmp(get_option('mpprecipe_print_link_hide'), 'Hide') != 0) 
    {
    	$custom_print_image = get_option('mpprecipe_custom_print_image');
    	$button_type  = 'butn-link';
    	$button_image = "";
		if (strlen($custom_print_image) > 0) {
			$button_type  = 'print-link';
			$button_image = '<img src="' . $custom_print_image . '">';
		}
	}

    return "
        <div id='mpp-buttons'>


            <div
               class = 'save-button mpp-button'
               title = 'Save Recipe to Mealplannerpro.com'
               alt   = 'Save Recipe to Mealplannerpro.com'
               onclick=\"" . mpp_save_recipe_js() . "\"
            >
            </div>
            <div 
                class   = '$button_type mpp-button' 
                title   = 'Print this recipe'
                onclick = 'zlrPrint( \"mpprecipe-container-$recipe_id\", \"$dir\" ); return false'> 
                $button_image
            </div>

        </div>

        <style> 
            div#mpp-buttons { float:right; margin-top: 10px;  }
            .mpp-button  { display:inline-block; }
            .save-button
            {
                width:  68px;
                height: 34px;
                background: url('${dir}save.png');
                cursor: pointer;
            }

            .save-button:hover
            {
                background: url('${dir}savehover.png');
            }

            .butn-link
            {
                width:  68px;
                height: 34px;
                background: url('${dir}print.png');
                cursor: pointer;
            }
            .butn-link:hover
            {
                background: url('${dir}printhover.png');
            }

        </style>
        ";
}





/**
 * Iterates through the Ziplist recipe table, copying every Ziplist recipe to 
 * the Mealplanner pro recipe table, then updates the Wordpress posts to use Mealplanner pro 
 * placemarkers in-place of Ziplist's.
 */
function mpp_convert_ziplist_entries()
{
    global $wpdb;

    $zl_table = $wpdb->prefix.'amd_zlrecipe_recipes';
    $em_table = $wpdb->prefix.'edamam_recipe_recipes';
    $wp_table = $wpdb->prefix.'posts';

    # Prevent copying of previously copied zl recipes.
    # Assumption: Only copied posts share post_id and created_at time.
    $zlrecipes = $wpdb->get_results("
        SELECT z.* FROM $zl_table z
        LEFT JOIN $em_table e
            ON  e.post_id    = z.post_id 
            AND e.created_at = z.created_at
        WHERE e.recipe_id IS NULL
    ");


    if( empty($zlrecipes) )
    {
        print "No Ziplist recipes to convert.";
        die();
    }
        
    $count  = 0;
    $errors = array();
    foreach( $zlrecipes as $zlrecipe )
    {
        $data = array(
            'post_id'       => $zlrecipe->post_id,      'recipe_title'  => $zlrecipe->recipe_title,
            'recipe_image'  => $zlrecipe->recipe_image, 'summary'       => $zlrecipe->summary,
            'rating'        => $zlrecipe->rating,       'prep_time'     => $zlrecipe->prep_time,
            'cook_time'     => $zlrecipe->cook_time,    'total_time'    => $zlrecipe->total_time,
            'serving_size'  => $zlrecipe->serving_size, 'ingredients'   => $zlrecipe->ingredients,
            'instructions'  => $zlrecipe->instructions, 'notes'         => $zlrecipe->notes,
            'created_at'    => $zlrecipe->created_at,   'servings'      => $zlrecipe->yield,
            'calories'      => $zlrecipe->calories,     'fatquantity'   => $zlrecipe->fat,
        );

        $success = $wpdb->insert( $em_table, $data );

        if (!$success )
        {
            $errors[] = array( $zlrecipe->post_id, $zlrecipe->recipe_id );
            continue;
        }

        $zl_placemarker_regex = '/\[amd-zlrecipe-recipe:' . $zlrecipe->recipe_id . '\]/i';

        $post         = $wpdb->get_row( "SELECT * FROM $wp_table where id = $zlrecipe->post_id" );
        $em_recipe_id = $wpdb->insert_id;

        $em_placemarker = "[edamam-recipe-recipe:$em_recipe_id]";
        $em_post        = preg_replace( $zl_placemarker_regex, $em_placemarker, $post->post_content );

        $wpdb->update( 
            $wp_table, 
            array( 'post_content' => $em_post ), 
            array( 'ID' => $zlrecipe->post_id )
        );
        $count += 1;
    }

    if( !empty( $errors ) )
    {
        print "Converted with some errors. <br/>";
        print "Could not convert ";
        print "<ul>";
        foreach( $errors as $pair )
            print "<li>recipe with title '$pair[1]' from Post titlted '$pair[0]'</li>";
        print "</ul>";
    }
    else
        print "Converted $count Ziplist recipe(s) into Mealplanner Pro recipes!";

    die();
}

add_action( 'wp_ajax_convert_ziplist_entries', 'mpp_convert_ziplist_entries' );

function mpp_convert_ziplist_entries_form()
{
    global $wpdb;
  
    $zl_table = $wpdb->prefix.'amd_zlrecipe_recipes';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$zl_table'") !== $zl_table )
        return;

    return ("
        <br/>
        <script type='text/javascript'>

            convert_ziplist_entries = function()
            {
                var c = confirm('This will convert all your Ziplist recipes into Mealplanner Pro recipes. Click OK to proceed.')

                if (!c)
                    return

                var data = '?action=convert_ziplist_entries'

                var r = new XMLHttpRequest()
                r.open( 'GET', ajaxurl+data, true )
                r.onreadystatechange = function() 
                {
                    if( r.readyState == 4 && r.status == 200 )
                        document.getElementById('convert_ziplist_entries_container').innerHTML = r.responseText;
                }
                r.send()
            }
        </script>

        <div id='convert_ziplist_entries_container' style='padding: 15px; background: #ddd; border: 1px dashed #ccc; width: 50%;'>
            <h4> Ziplist Data Detected </h4>
            <p>
                Press this button if you wish to convert all your existing Ziplist recipes to Mealplanner Pro recipes. 
            </p>
            <button onclick='convert_ziplist_entries()'>Convert Ziplist Recipes</button>
            <p>
                The content of all your posts will be the same except Mealplanner Pro will
                be used instead of Ziplist for both display and editing of existing recipes created through the Ziplist plugin.
            </p>
        </div>
    ");
}
