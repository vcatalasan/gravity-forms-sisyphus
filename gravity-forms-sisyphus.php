<?php
/*
Plugin Name: Gravity Forms Sisyphus
Plugin URI: https://github.com/bhays/gravity-forms-sisyphus
Description: Persist your form's data in a browser's Local Storage and never loose them on occasional tabs closing, browser crashes and other disasters with Sisyphus.
Version: 20180214
Author: Ben Hays
Author URI: http://benhays.com

------------------------------------------------------------------------
Copyright 2013 Ben Hays

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA

*/


add_action('init',  array('GFSisyphus', 'init'));

class GFSisyphus {

	private static $path = "gravity-forms-sisyphus/gravity-forms-sisyphus.php";
	private static $url = "http://www.gravityforms.com";
	private static $slug = "gravity-forms-sisyphus";
	private static $version = "2.0.1";
	private static $min_gravityforms_version = "1.7";
	private static $sisyphus_version = '1.1.107';

	//Plugin starting point. Will load appropriate files
	public static function init(){

		//supports logging
		add_filter("gform_logging_supported", array("GFSisyphus", "set_logging_supported"));

        add_action('after_plugin_row_' . self::$path, array('GFSisyphus', 'plugin_row') );

		if(!self::is_gravityforms_supported())
		{
			return;
		}

		// Add option to form settings
		add_action('gform_form_settings', array('GFSisyphus', 'add_form_setting'), 10, 2);
		add_filter('gform_pre_form_settings_save', array('GFSisyphus', 'save_form_setting'));

		// Load Sisyphus JS when necessary
		add_action('gform_enqueue_scripts', array( 'GFSisyphus', 'gform_enqueue_scripts' ), '', 2 );
	}

	public static function gform_enqueue_scripts( $form = null )
	{
		if ( ! $form == null )
		{
			// Check form for enabled sisyphus
			if( array_key_exists('enable_sisyphus', $form) && $form['enable_sisyphus'] == 1 )
			{
                wp_enqueue_script('localstorage', plugins_url( 'js/jstorage.min.js' , __FILE__ ));
				wp_enqueue_script('sisyphus', plugins_url( 'js/sisyphus.min.js' , __FILE__ ), array('jquery'),self::$sisyphus_version);

				// Add sisyphus script to page
				add_action('gform_register_init_scripts', array('GFSisyphus', 'add_page_script'));

				// Add support for gravityform list field
				add_filter('gform_column_input_content', array('GFSisyphus', 'reset_list_column_input_id'), 10, 6);
			}
		}
	}

	public static function add_page_script($form)
	{
		self::log_debug('Adding page script to '.$form['id']);

		$form_id = $form['id'];
		$form_state = rgpost("state_{$form_id}");

		// get resume token from 'Save and Continue' (incomplete application)
		$resume_token = $_GET['gf_token'] or $resume_token = rgpost( 'gform_resume_token' )
			or $resume_token = $_GET['edit']    // gravity view edit entry
			or $resume_token = '__new__';       // new form

        $gform_vars = array(
            'form_id' => $form_id,
            'form_state' => $form_state,
            'resume_token' => $resume_token
        );

        wp_enqueue_script('gform-scripts', plugins_url( 'js/gform-scripts.js' , __FILE__ ));
        wp_localize_script( 'gform-scripts', 'gform_vars', $gform_vars);
	}

	// Reset input id to gravityform list field to have a unique id required to autosave by Sisyphus
	public static function reset_list_column_input_id($input, $input_info, $field, $column, $value, $form_id) {
		if (!($field->type == 'list')) return $input;

		// since gf list field is not using unique id for each cell input, we're generating one
        static $cell = array();

        $cell[ $field->id ] += 1;

        // new input id
        $id = "input_{$form_id}_{$field->id}_cell{$cell[$field->id]}";

		// add input id if not yet set
		if (!preg_match('/ id=/', $input)) {
			$input = str_replace( "<input", "<input id='{$id}'", $input );
		} else {
		    // replace existing id
            $matches = array();
            preg_match("/id='([^']+)'/", $input,$matches);
            $input = str_replace( $matches[1], $id, $input );
        }

		return $input;
	}

	public static function add_form_setting( $settings, $form )
	{
		$current = rgar($form, 'enable_sisyphus');
		$checked = !empty($current) ? 'checked="checked"' : '';

	    $settings['Form Options']['enable_sisyphus'] = '
	        <tr>
	        	<th>Sisyphus <a href="#" onclick="return false;" class="tooltip tooltip_form_animation" tooltip="&lt;h6&gt;Enable Sisyphus Saving&lt;/h6&gt;Check this option to enable saving forms with local storage through the Sisyphus plugin."></a></th>
	            <td><input type="checkbox" value="1" '.$checked.' name="enable_sisyphus"> <label for="enable_sisyphus">'.__('Enable local saving of forms', 'gravity-forms-sisyphus').'</label></td>
	        </tr>';

	    return $settings;
	}

	public static function save_form_setting($form)
	{
	    $form['enable_sisyphus'] = rgpost('enable_sisyphus');
	    return $form;
	}

	public static function plugin_row()
	{
		if(!self::is_gravityforms_supported())
		{
			$message = sprintf(__("%sGravity Forms%s 1.7 is required. Activate it now or %spurchase it today!%s"), "<a href='http://benjaminhays.com/gravityforms'>", "</a>", "<a href='http://benjaminhays.com/gravityforms'>", "</a>");
			self::display_plugin_message($message, true);
		}
    }

	public static function display_plugin_message($message, $is_error = false)
	{
		$style = '';
		if($is_error)
		{
			$style = 'style="background-color: #ffebe8;"';
		}
		echo '</tr><tr class="plugin-update-tr"><td colspan="5" class="plugin-update"><div class="update-message" ' . $style . '>' . $message . '</div></td>';
	}

	private static function is_gravityforms_installed(){
		return class_exists("RGForms");
	}

	private static function is_gravityforms_supported(){
		if(class_exists("GFCommon")){
			$is_correct_version = version_compare(GFCommon::$version, self::$min_gravityforms_version, ">=");
			return $is_correct_version;
		}
		else{
			return false;
		}
	}
	function set_logging_supported($plugins)
	{
		$plugins[self::$slug] = "Sisyphus";
		return $plugins;
	}

	private static function log_error($message){
		if(class_exists("GFLogging"))
		{
			GFLogging::include_logger();
			GFLogging::log_message(self::$slug, $message, KLogger::ERROR);
		}
	}

	private static function log_debug($message){
		if(class_exists("GFLogging"))
		{
			GFLogging::include_logger();
			GFLogging::log_message(self::$slug, $message, KLogger::DEBUG);
		}
	}
}
if(!function_exists("rgget")){
	function rgget($name, $array=null){
		if(!isset($array))
			$array = $_GET;

		if(isset($array[$name]))
			return $array[$name];

		return "";
	}
}

if(!function_exists("rgpost")){
	function rgpost($name, $do_stripslashes=true){
		if(isset($_POST[$name]))
			return $do_stripslashes ? stripslashes_deep($_POST[$name]) : $_POST[$name];

		return "";
	}
}

if(!function_exists("rgar")){
	function rgar($array, $name){
		if(isset($array[$name]))
			return $array[$name];

		return '';
	}
}

if(!function_exists("rgempty")){
	function rgempty($name, $array = null){
		if(!$array)
			$array = $_POST;

		$val = rgget($name, $array);
		return empty($val);
	}
}

if(!function_exists("rgblank")){
	function rgblank($text){
		return empty($text) && strval($text) != "0";
	}
}