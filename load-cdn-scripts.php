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
	static $cdn_urls = array(
		'cdnjs' => '//cdnjs.cloudflare.com/ajax/libs/',
		'google' => '//ajax.googleapis.com/ajax/libs/',
	);
	
	// list of all CDN scripts hosted on cdnjs
	static $cdn_scripts;
	
	// added to "init" action
	static function init() {
		if (is_admin()) {
			// Load up $cdn_scripts with list from cdnjs
			self::$cdn_scripts = (get_option('cdn_scripts')) ? get_option('cdn_scripts') : self::get_cdn();
			// add submenu item for plugin options page
			add_action('admin_menu', array(__CLASS__,'submenu_page'));
			// add settings to db
			add_action( 'admin_init', array(__CLASS__,'register_settings') );
			add_action( 'admin_init', array(__CLASS__,'setup_cron') );
			wp_register_style(self::$ID,plugins_url('load_cdn_style.css',__FILE__));
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
	static function setup_cron() {
		if ( ! wp_next_scheduled( self::$ID ) ) wp_schedule_event( time(),'hourly',self::$ID );
		add_action(self::$ID, array(__CLASS__,'check_cdns') );
	}
	static function check_cdns($check_scripts = false) {
		if ($check_scripts === false) $check_scripts = get_option('override_scripts');
		if ($check_scripts) {
			foreach($check_scripts as $key => $script) {
				$fileexists = file_get_contents("http:".$script['src'],0,null,0,1);
				$check_scripts[$key]['status'] = $fileexists;
			}
			update_option('override_scripts',$check_scripts);
		}

	}
	
	static function update_options() {
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
		//var_dump($_POST);
		foreach ( $_POST as $key => $val ) {
			if (substr($key,0,4) == 'opt_') {
				$handle = substr($key,4);
				$cdn_url = ($handle == 'jquery-core') ? $_POST['src_jquery-core'] : $_POST['src_'.$handle];
				$registered_scripts[$handle] = array ( 'src' => $val, 'cdn_url' => $cdn_url);
				if ($val == 'cdn') {
					$is_up = false;
					$override_scripts[$handle] = array(
						'src' =>$_POST['src_'.$handle],
						'status' => $is_up,
					);
				}
			}
		}
		//var_dump($override_scripts);
		// update options in db
		update_option('registered_scripts',$registered_scripts);
		update_option('override_scripts',$override_scripts);
	}
	
	static function options_page() { ?>
		<h2>Load CDN Scripts</h2>
        <?php if (!current_user_can('manage_options')) wp_die('You do not have sufficient permissions to access this page.');
			// submitted by self?
			if (isset($_POST["_wp_http_referer"]) && $_POST["_wp_http_referer"] == $_SERVER[REQUEST_URI]) self::update_options();
			global $wp_scripts;
			wp_enqueue_style(self::$ID);
		?>
		<div class="wrap">
			<h2>Registered</h2>
            <form method="post" action="">                
                <table class="widefat" id="registered-scripts">
                <thead>
                    <th>Handle</th>
                    <th>Load from</th>
                    <th>Default</th>
                    <th>CDN Version</th>
                    <th>Source</th>
                    <th>Dependencies</th>
                </thead>
                <tbody>
                <?php
				settings_fields('load_cdn_scripts');
				
				// get saved options
				$pluginoptions = get_option('registered_scripts');
				//var_dump($pluginoptions);
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
						// no css class yet
						$css = null;
						if (preg_match('/^\/\//',$scripts->src)) :
							$css = 'iscdn';
						else :
							// get md5 hash for the CDN script
							$cdn_md5 = md5_file('http:'.self::$cdn_scripts[$scripts->handle]);
							// get md5 hash of default script
							$default_md5 = md5_file(get_bloginfo('url').$scripts->src);
							// if the md5 hasheds match, flag for cdnifying
							if ($cdn_md5 == $default_md5) $css = 'samever';
						endif;
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
		// empty vars for options
		$opt = 'native';
		// load cdn vers
		$vers = ($handle == 'jquery-core') ? self::$cdn_scripts['jquery']['versions'] : self::$cdn_scripts[$handle]['versions'];
		$cdn_version = (in_array($scripts->ver,$vers)) ? $scripts->ver : $vers[0];
		// set cdn $src;
		if ($pluginoptions) {
			$opt = $pluginoptions[$handle]['src'];
			$src = $pluginoptions[$handle]['cdn_url'];
		} else {
			if ($class == 'samever') $opt = 'cdn';
			$tmphandle = ($handle == 'jquery-core') ? 'jquery' : $handle;
			$cdnurl = self::$cdn_urls[self::$cdn_scripts[$tmphandle]['cdn']];
			$src = (empty($vers)) ? $src = self::$cdn_scripts[$tmphandle]['src'] : $cdnurl . $tmphandle . '/' . $cdn_version . '/' . self::$cdn_scripts[$tmphandle]['filename'];
		}
		
		// start fresh
		$return = '';
		$return .= "<tr class='row $class'>";
		// echo handle
		$return .= '<td><p>'.$handle.'</p></td>';
		
		
		// echo src option
		$return .= '<td>';
			$checked = '';
			$return .= '<label><input type="radio" '.(($opt == 'native') ? 'checked' : '').' name="opt_'.$handle.'" value="native" />Default</label> &nbsp; ';
			$return .= '<label><input type="radio" '.(($opt == 'cdn') ? 'checked' : '').' name="opt_'.$handle.'" value="cdn" />CDN</label></p>';
		$return .= '</td>';
		
		// echo default script info as registered with wordpress
		$return .= '<td>';
			// echo default src
			$return .= '<input type="text" class="default-src" disabled="disabled" value="'.$scripts->src.'" />';
			// echo md5 hash
			$return .= '<small>md5 hash: '.md5_file(get_bloginfo('url').$scripts->src).'</small><br />';
			// echo version
			$return .= '<small>Version: '.$scripts->ver.'</small>';
			// if it should default to the custom src, check that and echo option
		
		$return .= '</td><td>';
			if (!empty($vers)) {
				$return .= '<select>';
				foreach ( $vers as $ver ) {
					$selected = ($ver == $cdn_version) ? 'selected' : '';
					$return .= "<option $selected>$ver</option>";
				}
				$return .= '</select>';
			}
		
		$return .= '</td><td>';
			// WP calls jquery by the handle jquery-core
			// if it should default to the registered src, check that and echo option
			$return .= '<input type="text" class="cdn_src" name="src_'.$handle.'" value="'.$src.'" />';
			$return .= '<a tilte="Reset to most recent CDN source" class="btn ab-icon reset_'.$handle.'" ></a>';
			
			// echo md5 hash
			$return .= '<small>md5 hash: '.md5_file('http:'.$src).'</small><br />';
			// echo likely CDN version currently in use
			preg_match('/\d+(\.\d+)+/', $src, $matches);
			if (!$matches[0]) $matches[0] = 'unknown';
			$return .= '<small>Version: '.$cdn_version.'</small>';
			/*// echo likely latest CDN version
			$temphandle = ($handle == 'jquery-core') ? 'jquery' : $handle;
			preg_match('/\d+(\.\d+)+/', self::$cdn_scripts[$temphandle], $latest_matches);
			//$return .= ' '.$matches[0].' '.$latest_matches[0];
			if ($latest_matches[0] !== $matches[0] && $matches[0] !=='unknown') $return .= ' - <small>Latest CDN Version: '.$latest_matches[0].'</small>';*/
		// echo any deps
		$return .= '</td>';
		$return .= '<td>';
		if ($scripts->deps) foreach($scripts->deps as $val) $return .= "$val<br>";
		$return .= '</td>';
		$return .= '</tr>';
		
		// send it back to be echoed
		return $return;
	}
	
	static function override_scripts() {
		
		global $wp_scripts;
		//var_dump($wp_scripts);
		
		// get scripts to override
		$override_scripts = get_option('override_scripts');
		// loop through and replace any scripts with CDN sources
		foreach ($override_scripts as $handle => $script) {
			if(array_key_exists($handle,$wp_scripts->registered) && $script['status'] !== false) $wp_scripts->registered[$handle]->src = $script['src'];
		}
	}
	
	static function check_override() {
		global $wp_scripts;
		var_dump($wp_scripts);
	}
	
	static function get_cdn() {
		
		ini_set('memory_limit', '512M');
		// get libraries on cdnjs
		$cdnjslibs = json_decode(file_get_contents("http://cdnjs.com/packages.json"))->packages;
		
		/*// get cdnjs page
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
		}*/
		
		foreach($cdnjslibs as $cdnjslib) {
			$name = strtolower($cdnjslib->name);
			$versions = array();
			foreach ( $cdnjslib->assets as $asset ) $versions[] .= $asset->version;
			$cdn_scripts[$name] = array(
				'src' => '//cdnjs.cloudflare.com/ajax/libs/'.$name.'/'.$cdnjslib->version.'/'.$cdnjslib->filename ,
				'versions' => $versions,
				'cdn' => 'cdnjs',
				'filename' => $cdnjslib->filename,
			);
		}
		
		// handles and srcs for Google Ajax Libs
		$ajaxlibs = array(
			'angularjs' => array(
				'src' => '//ajax.googleapis.com/ajax/libs/angularjs/1.2.19/angular.min.js',
				'versions' => array('1.2.19', '1.2.18', '1.2.17', '1.2.16', '1.2.15', '1.2.14', '1.2.13', '1.2.12', '1.2.11', '1.2.10', '1.2.9', '1.2.8', '1.2.7', '1.2.6', '1.2.5', '1.2.4', '1.2.3', '1.2.2', '1.2.1', '1.2.0', '1.0.8', '1.0.7', '1.0.6', '1.0.5', '1.0.4', '1.0.3', '1.0.2', '1.0.1'),
				'filename' => 'angular.min.js',
			),
			'dojo' => array(
				'src' => '//ajax.googleapis.com/ajax/libs/dojo/1.10.0/dojo/dojo.js',
				'versions' => array('1.10.0', '1.9.3', '1.9.2', '1.9.1', '1.9.0', '1.8.6', '1.8.5', '1.8.4', '1.8.3', '1.8.2', '1.8.1', '1.8.0', '1.7.5', '1.7.4', '1.7.3', '1.7.2', '1.7.1', '1.7.0', '1.6.2', '1.6.1', '1.6.0', '1.5.3', '1.5.2', '1.5.1', '1.5.0', '1.4.5', '1.4.4', '1.4.3', '1.4.1', '1.4.0', '1.3.2', '1.3.1', '1.3.0', '1.2.3', '1.2.0', '1.1.1'),
				'filename' => 'dojo/dojo.js',
			),
			'ext-core' => array(
				'src' => '//ajax.googleapis.com/ajax/libs/ext-core/3.1.0/ext-core.js',
				'versions' => array('3.1.0', '3.0.0'),
				'filename' => 'ext-core.js',
			),
			'jquery' => array(
				'src' => '//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js',
				'versions' => array('2.1.1', '2.1.0', '2.0.3', '2.0.2', '2.0.1', '2.0.0', '1.11.1', '1.11.0', '1.10.2', '1.10.1', '1.10.0', '1.9.1', '1.9.0', '1.8.3', '1.8.2', '1.8.1', '1.8.0', '1.7.2', '1.7.1', '1.7.0', '1.6.4', '1.6.3', '1.6.2', '1.6.1', '1.6.0', '1.5.2', '1.5.1', '1.5.0', '1.4.4', '1.4.3', '1.4.2', '1.4.1', '1.4.0', '1.3.2', '1.3.1', '1.3.0', '1.2.6', '1.2.3'),
				'filename' => 'jquery.min.js',
			),
			'jquery-mobile' => array(
				'src' => '//ajax.googleapis.com/ajax/libs/jquerymobile/1.4.3/jquery.mobile.min.js',
				'style' => '//ajax.googleapis.com/ajax/libs/jquerymobile/1.4.3/jquery.mobile.min.css',
				'versions' => array('1.4.3', '1.4.2', '1.4.1', '1.4.0'),
				'filename' => 'jquery.mobile.min.js',
			),
			'mootools' => array(
				'src' => '//ajax.googleapis.com/ajax/libs/mootools/1.5.0/mootools-yui-compressed.js',
				'versions' => array('1.5.0', '1.4.5', '1.4.4', '1.4.3', '1.4.2', '1.4.1', '1.4.0', '1.3.2', '1.3.1', '1.3.0', '1.2.5', '1.2.4', '1.2.3', '1.2.2', '1.2.1', '1.1.2', '1.1.1'),
				'filename' => 'mootools-yui-compressed.js',
			),
			'prototype' => array(
				'src' => '//ajax.googleapis.com/ajax/libs/prototype/1.7.2.0/prototype.js',
				'versions' => array('1.7.2.0', '1.7.1.0', '1.7.0.0', '1.6.1.0', '1.6.0.3', '1.6.0.2'),
				'filename' => 'prototype.js',
			),
			'scriptaculous' => array(
				'src' => '//ajax.googleapis.com/ajax/libs/scriptaculous/1.9.0/scriptaculous.js',
				'versions' => array('1.9.0', '1.8.3', '1.8.2', '1.8.1'),
				'filename' => 'scriptaculous.js',
			),
			'swfobject' => array(
				'src' => '//ajax.googleapis.com/ajax/libs/swfobject/2.2/swfobject.js',
				'versions' => array('2.2', '2.1'),
				'filename' => 'swfobject.js',
			),
			'webfont' => array(
				'src' => '//ajax.googleapis.com/ajax/libs/webfont/1.5.3/webfont.js',
				'versions' => array('1.5.3', '1.5.2', '1.5.0', '1.4.10', '1.4.8', '1.4.7', '1.4.6', '1.4.2', '1.3.0', '1.1.2', '1.1.1', '1.1.0', '1.0.31', '1.0.30', '1.0.29', '1.0.28', '1.0.27', '1.0.26', '1.0.25', '1.0.24', '1.0.23', '1.0.22', '1.0.21', '1.0.19', '1.0.18', '1.0.17', '1.0.16', '1.0.15', '1.0.14', '1.0.13', '1.0.12', '1.0.11', '1.0.10', '1.0.9', '1.0.6', '1.0.5', '1.0.4', '1.0.3', '1.0.2', '1.0.1', '1.0.0'),
				'filename' => 'webfont.js',
			),
		);
		
		foreach ($ajaxlibs as $handle => $lib) {
			$lib['cdn'] = 'google';
			$cdn_scripts[$handle] = $lib;
		}
		
		// send it back
		return $cdn_scripts;
	}
	
}
// get us started
add_action('init', array('load_cdn_scripts', 'init'));

?>