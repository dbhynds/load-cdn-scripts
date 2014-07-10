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
		if (is_admin()) {
			// Load up $cdn_scripts with list from cdnjs
			self::$cdn_scripts = self::get_cdn();
			// add submenu item for plugin options page
			add_action('admin_menu', array(__CLASS__,'submenu_page'));
			// add settings to db
			add_action( 'admin_init', array(__CLASS__,'register_settings') );
		} else {
			// override the scripts
			add_action('get_header',array(__CLASS__,'override_scripts'));
			// Verify that the scripts got overridden
			//add_action('wp_head',array(__CLASS__,'check_override'));
		}
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
			var_dump($_POST);
			echo "<br /><br />";
			foreach ( $_POST as $key => $val ) {
				if (substr($key,0,4) == 'opt_') {
					$handle = substr($key,4);
					$registered_scripts[$handle] = array ( 'src' => $val, 'custom' => $_POST[$key.'_custom']);
					if ($val == 'cdn') $override_scripts[$handle] = self::$cdn_scripts[$handle];
					if ($val == 'custom') $override_scripts[$handle] = $_POST[$handle.'_custom'];
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
				}
				#registered-scripts input[type="text"] {
					width: 100%;
				}
			</style>
            <form method="post" action="">                
                <table class="widefat" id="registered-scripts">
                <thead>
                    <th>Handle</th>
                    <th>Default</th>
                    <th>CDN</th>
                    <th>Dependencies</th>
                </thead>
                <tbody>
                <?php
				settings_fields('load_cdn_scripts');
				
				// get saved options
				$pluginoptions = get_option('registered_scripts');
				var_dump($pluginoptions);
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
					if ($scripts->src) {
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
		// set the handle
		$handle = $scripts->handle;
		
		// start fresh
		$return = '';
		$return .= "<tr class='row $class'>";
		// echo handle
		$return .= '<td>'.$handle.'</td>';
		// echo default script info as registered with wordpress
		$return .= '<td>';
			// echo default src
			$return .= '<input type="text" disabled="disabled" value="'.$scripts->src.'" />';
			// echo version
			$return .= 'Version: '.$scripts->ver.'<br />';
			// if it should default to the custom src, check that and echo option
			$checked = ($pluginoptions[$handle]['src'] == 'native') ? 'checked' : '';
			$return .= '<label><input type="checkbox" '.$checked.' name="opt_'.$handle.'" value="native" />Use default source</label></p>';
		
		$return .= '</td><td>';
		
			// WP calls jquery by the handle jquery-core
			if ($handle == 'jquery-core') $handle = 'jquery';
			// if it should default to the registered src, check that and echo option
			$return .= '<input type="text" name="'.$handle.'_src" value="'.self::$cdn_scripts[$handle].'" />';
			
			// echo any likely CDN version
			preg_match('/\d+(\.\d+)+/', self::$cdn_scripts[$handle], $matches);
			$return .= 'Version: '.$matches[0].'';
			
		$return .= '</td>';
		// echo any deps
		$return .= '<td class="">';
		if ($scripts->deps) foreach($scripts->deps as $val) $return .= "$val<br>";
		$return .= '</td>';
		$return .= '</tr>';
		
		// send it back to be echoed
		return $return;
	}
	
	static function override_scripts() {
		global $wp_scripts;
		//var_dump($wp_scripts);
		$override_scripts = get_option('override_scripts');
		foreach ($override_scripts as $handle => $script) {
			if(array_key_exists($handle,$wp_scripts->registered)) $wp_scripts->registered[$handle]->src = $script;
		}
	}
	
	static function check_override() {
		global $wp_scripts;
		var_dump($wp_scripts);
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
		
		// handles and srcs for Google Ajax Libs
		$ajaxlibs = array(
			'angularjs' => array(
				'src' => '//ajax.googleapis.com/ajax/libs/angularjs/1.2.19/angular.min.js',
				'versions' => array('1.2.19', '1.2.18', '1.2.17', '1.2.16', '1.2.15', '1.2.14', '1.2.13', '1.2.12', '1.2.11', '1.2.10', '1.2.9', '1.2.8', '1.2.7', '1.2.6', '1.2.5', '1.2.4', '1.2.3', '1.2.2', '1.2.1', '1.2.0', '1.0.8', '1.0.7', '1.0.6', '1.0.5', '1.0.4', '1.0.3', '1.0.2', '1.0.1'),
			),
			'dojo' => array(
				'src' => '//ajax.googleapis.com/ajax/libs/dojo/1.10.0/dojo/dojo.js',
				'versions' => array('1.10.0', '1.9.3', '1.9.2', '1.9.1', '1.9.0', '1.8.6', '1.8.5', '1.8.4', '1.8.3', '1.8.2', '1.8.1', '1.8.0', '1.7.5', '1.7.4', '1.7.3', '1.7.2', '1.7.1', '1.7.0', '1.6.2', '1.6.1', '1.6.0', '1.5.3', '1.5.2', '1.5.1', '1.5.0', '1.4.5', '1.4.4', '1.4.3', '1.4.1', '1.4.0', '1.3.2', '1.3.1', '1.3.0', '1.2.3', '1.2.0', '1.1.1'),
			),
			'ext-core' => array(
				'src' => '//ajax.googleapis.com/ajax/libs/ext-core/3.1.0/ext-core.js',
				'versions' => array('3.1.0', '3.0.0'),
			),
			'jquery' => array(
				'src' => '//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js',
				'versions' => array('2.1.1', '2.1.0', '2.0.3', '2.0.2', '2.0.1', '2.0.0', '1.11.1', '1.11.0', '1.10.2', '1.10.1', '1.10.0', '1.9.1', '1.9.0', '1.8.3', '1.8.2', '1.8.1', '1.8.0', '1.7.2', '1.7.1', '1.7.0', '1.6.4', '1.6.3', '1.6.2', '1.6.1', '1.6.0', '1.5.2', '1.5.1', '1.5.0', '1.4.4', '1.4.3', '1.4.2', '1.4.1', '1.4.0', '1.3.2', '1.3.1', '1.3.0', '1.2.6', '1.2.3'),
			),
			'jquery-mobile' => array(
				'src' => '//ajax.googleapis.com/ajax/libs/jquerymobile/1.4.3/jquery.mobile.min.js',
				'style' => '//ajax.googleapis.com/ajax/libs/jquerymobile/1.4.3/jquery.mobile.min.css',
				'versions' => array('1.4.3', '1.4.2', '1.4.1', '1.4.0'),
			),
			'mootools' => array(
				'src' => '//ajax.googleapis.com/ajax/libs/mootools/1.5.0/mootools-yui-compressed.js',
				'versions' => array('1.5.0', '1.4.5', '1.4.4', '1.4.3', '1.4.2', '1.4.1', '1.4.0', '1.3.2', '1.3.1', '1.3.0', '1.2.5', '1.2.4', '1.2.3', '1.2.2', '1.2.1', '1.1.2', '1.1.1'),
			),
			'prototype' => array(
				'src' => '//ajax.googleapis.com/ajax/libs/prototype/1.7.2.0/prototype.js',
				'versions' => array('1.7.2.0', '1.7.1.0', '1.7.0.0', '1.6.1.0', '1.6.0.3', '1.6.0.2'),
			),
			'scriptaculous' => array(
				'src' => '//ajax.googleapis.com/ajax/libs/scriptaculous/1.9.0/scriptaculous.js',
				'versions' => array('1.9.0', '1.8.3', '1.8.2', '1.8.1'),
			),
			'swfobject' => array(
				'src' => '//ajax.googleapis.com/ajax/libs/swfobject/2.2/swfobject.js',
				'versions' => array('2.2', '2.1'),
			),
			'webfont' => array(
				'src' => '//ajax.googleapis.com/ajax/libs/webfont/1.5.3/webfont.js',
				'versions' => array('1.5.3', '1.5.2', '1.5.0', '1.4.10', '1.4.8', '1.4.7', '1.4.6', '1.4.2', '1.3.0', '1.1.2', '1.1.1', '1.1.0', '1.0.31', '1.0.30', '1.0.29', '1.0.28', '1.0.27', '1.0.26', '1.0.25', '1.0.24', '1.0.23', '1.0.22', '1.0.21', '1.0.19', '1.0.18', '1.0.17', '1.0.16', '1.0.15', '1.0.14', '1.0.13', '1.0.12', '1.0.11', '1.0.10', '1.0.9', '1.0.6', '1.0.5', '1.0.4', '1.0.3', '1.0.2', '1.0.1', '1.0.0'),
			),
		);
		
		foreach ($ajaxlibs as $handle => $lib) {
			$cdn_scripts[$handle] = $lib['src'];
		}
		
		// send it back
		return $cdn_scripts;
	}
	
}
// get us started
add_action('init', array('load_cdn_scripts', 'init'));


















?>