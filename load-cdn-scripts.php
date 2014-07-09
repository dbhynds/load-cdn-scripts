<?php

/*
Plugin Name: Load CDN Scripts
Plugin URI: http://wordpress.org/extend/plugins/load-cdn-scripts/
Description: Import posts, pages, comments, custom fields, categories, tags and more from a WordPress export file.
Author: wordpressdotorg
Author URI: http://wordpress.org/
Version: 0.6.1
Text Domain: wordpress-importer
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

defined('ABSPATH') or die("No script kiddies please!");

class load_cdn_scripts {
	
	// plugin ID
	static $ID = 'load_cdn_scripts';
	
	// list of all CDN scripts hosted on cdnjs
	static $cdn_scripts;
	
	// added to "init" action
	static function init() {
		// Load up $cdn_scripts with list from cdnjs
		if (is_admin()) self::$cdn_scripts = self::get_cdn();
		// add submenu item for plugin options page
		add_action('admin_menu', array(__CLASS__,'submenu_page'));
		// add settings to db
		add_action( 'admin_init', array(__CLASS__,'register_settings') );
	}
	static function submenu_page(){
		// add submenu item for options page
		add_submenu_page( 'options-general.php','Load CDN Scripts','Load CDN Scripts','activate_plugins',self::$ID,array(__CLASS__,'options_page'));
	}
	static function register_settings(){
		// save $cdn_scripts as an option
		add_option('cdn_scripts',self::$cdn_scripts);
		// save an empty set of registered scripts
		add_option('registered_scripts',array());
		// save an empty set of override scripts
		add_option('override_scripts',array());
	}
	
	static function update_options() {
		// submitted by self?
		if (isset($_POST["_wp_http_referer"]) && $_POST["_wp_http_referer"] == $_SERVER[REQUEST_URI]) {
			// these will be our options
			$registered_scripts = array();
			$override_scripts = array();
			/* fill $registered_scripts from $_POST as
				[handle] => array (
					'src' => $opt_val,
					'custom' => $custom_val
				)
			*/
			/* fill $override_scripts as [handle] => new_src
			*/
			foreach ( $_POST as $key => $val ) {
				if (substr($key,0,4) == 'opt_') {
					$handle = substr($key,4);
					$registered_scripts[$handle] = array ( 'src' => $val, 'custom' => $_POST[$key.'_custom']);
					if ($val == 'cdn') $override_scripts[$handle] = self::$cdn_scripts[$handle];
					if ($val == 'custom') $override_scripts[$handle] = $_POST[$key.'_custom'];
				}
			}
			var_dump($override_scripts);
			// update options in db
			update_option('registered_scripts',$registered_scripts);
			update_option('override_scripts',$override_scripts);
		}
	}
	
	static function options_page() { ?>
		<h2>Load CDN Scripts</h2>
        <?php if (!current_user_can('manage_options')) wp_die('You do not have sufficient permissions to access this page.');
			self::update_options();
			global $wp_scripts;
		?>
		<div class="wrap">
			<h2>Registered</h2>
            <style>
				.samever {
					background: #afa;
				}
				.nocdn {
					background: #fcc;
				}
				.iscdn {
					background: #acf;
				}
				#registered-scripts table td {
					padding: 0;
					padding-right: .5em;
					line-height: 2em;
				}
				#registered-scripts input[type="text"] {
					width: 80%;
				}
			</style>
            <form method="post" action="">                
                <table class="widefat" id="registered-scripts">
                <thead>
                    <th>handle</th>
                    <th>default version</th>
                    <th>cdn version</th>
                    <th>src</th>
                    <th>deps</th>
                </thead>
                <tbody>
                <?php
				settings_fields('load_cdn_scripts');
				
				// get saved options
				$pluginoptions = get_option('registered_scripts');
				
				// make list of registered scripts
                $registeredscripts = array();
                foreach($wp_scripts->registered as $key => $val) {
					// ifthe  handle of a registered script also exists as a CDN script
                    if (self::$cdn_scripts[$key]) :
						// add it to $registeredscripts, if it doesn't already exist
                        if (!in_array($key, $registeredscripts)) $registeredscripts[] .= $key;
						// if it has any dependencies
                        if ($val->deps) :
							// add those to $registeredscripts, if they don't already exist
                            foreach ($val->deps as $val2) if (!in_array($val2, $registeredscripts)) $registeredscripts[] .= $val2;
                        endif;
                    endif;
                }
				
				sort($registeredscripts);
				
				// now that we have our CDN-able registered scripts, loop and list 'em in the table
                foreach($registeredscripts as $val) {
					// get the script's info from the list of registered scripts
					$scripts = $wp_scripts->registered[$val];
					// search for a likely version number in the [src] of the matching CDN script
					preg_match('/\d+(\.\d+)+/', self::$cdn_scripts[$scripts->handle], $matches);
					// no css class yet
					$css = null;
					// if the registered version matches the probably CDN version, note this and flag it for possible CDN-ifying
					if (strpos($scripts->ver,$matches[0]) === 0 || strpos($matches[0],$scripts->ver) === 0) $css = 'samever';
					// if there isn't a CDN match, flag for warning
                    if (!array_key_exists($val,self::$cdn_scripts)) $css = 'nocdn';
					// if it probably already is a CDN, flag it for likely cdn-ifying
                    if (preg_match('/^\/\//',$scripts->src)) $css = 'iscdn';
					//echo the options
                    echo self::list_scripts($pluginoptions, $scripts, $css);
                }
                ?>
                </tbody>
                </table>
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
                </p>
            </form>
		</div>
		<?php
	}
	
	static function list_scripts($pluginoptions = false, $scripts = array(),$class = null) {
		// start fresh	
		$return = '';
		$return .= "<tr class='row $class'>";
		// echo handle
		$return .= '<td>'.$scripts->handle.'</td>';
		// echo version
		$return .= '<td>'.$scripts->ver.'</td>';
		// echo any likely CDN version
		preg_match('/\d+(\.\d+)+/', self::$cdn_scripts[$scripts->handle], $matches);
		$return .= '<td>'.$matches[0].'</td>';
		$return .= '<td>';
		
			// set default values if this script doesn't have an associated option.
			if (!array_key_exists($scripts->handle,$pluginoptions)) {
				// set to cdn if it's likely a cdn
				if ($class == 'samever') $pluginoptions[$scripts->handle]['src'] = 'cdn';
				// otherwise default to registered src
				else $pluginoptions[$scripts->handle]['src'] = 'native';
			}
		
			// if it should default to the registered src, check that and echo option
			$checked = ($pluginoptions[$scripts->handle]['src'] == 'native') ? 'checked' : '';
			$return .= '<label><input type="radio" '.$checked.' name="opt_'.$scripts->handle.'" value="native" />default src: '.$scripts->src.'</label><br />';
			
			// if it should default to the cdn src, check that and echo option
			$checked = ($pluginoptions[$scripts->handle]['src'] == 'cdn') ? 'checked' : '';
			$return .= '<label><input type="radio" '.$checked.' name="opt_'.$scripts->handle.'" value="cdn" />cdn src: '.self::$cdn_scripts[$scripts->handle].'</label><br />';
			
			// if it should default to the custom src, check that and echo option
			$checked = ($pluginoptions[$scripts->handle]['src'] == 'custom') ? 'checked' : '';
			// if no specified custom src, default to cdn src
			$customsrc = ( strlen($pluginoptions[$scripts->handle]['custom']) != 0) ? $pluginoptions[$scripts->handle]['custom'] : self::$cdn_scripts[$scripts->handle];
			$return .= '<label><input type="radio" '.$checked.' name="opt_'.$scripts->handle.'" value="custom" /> <input type="text" name="'.$scripts->handle.'_custom" value="'.$customsrc.'" /></label>';
			
		$return .= '</td>';
		// echo any deps
		$return .= '<td class="">';
		if ($scripts->deps) foreach($scripts->deps as $val) $return .= "$val<br>";
		$return .= '</td>';
		$return .= '</tr>';
		
		// send it back to be echoed
		return $return;
	}
		
	static function overwrite_srcs() {
		global $wp_scripts;
		foreach($wp_scripts->registered as $key => $val) {
			if (self::$cdn_scripts[$key]) {
				$val->src = self::$cdn_scripts[$key];
				$val->ver = null;
				// wp_register_script($val);
			}
		}
	}
	
	static function get_cdn() {
		
		// get cdnjs page
		$xml = file_get_contents("http://cdnjs.com/");
		// get the table from that page
		$haystack = substr($xml,strpos($xml,'<table'),strpos($xml,'</table>')-strpos($xml,'<table')+9);
		// handle to search for
		$needle = '<a itemprop="name" href="libraries/';
		// src to search for
		$needle2 = '<p itemprop="downloadUrl" class="library-url" style="padding: 0; margin: 0">';
		
		// start fresh
		$cdn_scripts = array();
		
		// and build the variable
		while (($lastPos = strpos($haystack, $needle, $lastPos))!== false) {
			$positions[] = $lastPos;
			$lastPos = $lastPos + strlen($needle);
			
			// find the handle and it's position
			$key = substr($haystack,$lastPos,strpos($haystack,'"',$lastPos)-$lastPos);
			$keyPos = strpos($haystack,$needle2,$lastPos) + strlen($needle2);
			// find the src
			$val = substr($haystack,$keyPos,strpos($haystack,'</p>',$keyPos)-$keyPos);
			// add [handle] => src to array
			$cdn_scripts[$key] = $val;
		}
		
		// send it back
		return $cdn_scripts;
	}
	
}
// get us started
add_action('init', array('load_cdn_scripts', 'init'));


















?>