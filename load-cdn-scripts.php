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
	
	static $ID = 'load_cdn_scripts';
	
	static $cdn_scripts;
	
	static function init() {
		self::$cdn_scripts = self::get_cdn();
		add_action('admin_menu', array(__CLASS__,'submenu_page'));
		add_action( 'admin_init', array(__CLASS__,'register_settings') );
	}
	static function submenu_page(){
		add_submenu_page( 'options-general.php','Load CDN Scripts','Load CDN Scripts','activate_plugins',self::$ID,array(__CLASS__,'options_page'));
	}
	static function register_settings(){
		add_option('cdn_scripts',self::$cdn_scripts);
		add_option('registered_scripts',array());
		register_setting(self::$ID,'cdn_scripts');
		register_setting(self::$ID,'registered_scripts');
	}
	public function validate($input) {
		/*$valid = array();
		$valid['url_todo'] = sanitize_text_field($input['url_todo']);
		$valid['title_todo'] = sanitize_text_field($input['title_todo']);
		if (strlen($valid['url_todo']) == 0) {
			add_settings_error(
					'todo_url',                     // Setting title
					'todourl_texterror',            // Error ID
					'Please enter a valid URL',     // Error message
					'error'                         // Type of message
			);
			// Set it to the default value
			$valid['url_todo'] = $this->data['url_todo'];
		}
		if (strlen($valid['title_todo']) == 0) {
			add_settings_error(
					'todo_title',
					'todotitle_texterror',
					'Please enter a title',
					'error'
			);
			$valid['title_todo'] = $this->data['title_todo'];
		}
		return $valid;*/
	}
	
	
	static function update_options() {
		if (isset($_POST["update_settings"])) {
			var_dump($_POST);
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
				.diffver {
					background: #ffa;
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
			</style>
            <form method="post" action="">
            	<?php 
					$pluginoptions = get_option('registered_scripts');
				?>
                <input type="text" value="<?php var_dump($pluginoptions); ?>" name="registered_scripts" />
                
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
				$pluginoptions = get_option('registered_scripts');
				
                $registeredscripts = array();
                foreach($wp_scripts->registered as $key => $val) {
                    if (self::$cdn_scripts[$key]) :
                        if (!in_array($key, $registeredscripts)) $registeredscripts[] .= $key;
                        if ($val->deps) :
                            foreach ($val->deps as $val2) if (!in_array($val2, $registeredscripts)) $registeredscripts[] .= $val2;
                        endif;
                    endif;
                }
                foreach($registeredscripts as $val) {
					$scripts = $wp_scripts->registered[$val];
					preg_match('/\d+(\.\d+)+/', self::$cdn_scripts[$scripts->handle], $matches);
					$css = null;
					if (strpos($scripts->ver,$matches[0]) === 0 || strpos($matches[0],$scripts->ver) === 0) $css = 'diffver';
                    if (!array_key_exists($val,self::$cdn_scripts)) $css = 'nocdn';
                    if (preg_match('/^\/\//',$scripts->src)) $css = 'iscdn';
                    echo self::list_scripts($scripts, $css);
                }
                ?>
                </tbody>
                </table>
                
                <input type="hidden" name="update_settings" value="Y" />
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
                </p>
            </form>
		</div>
		<?php
	}
	
	static function list_scripts($scripts = array(),$class = null) {	
		$return = '';
		$return .= "<tr class='row $class'>";
		$return .= '<td>'.$scripts->handle.'</td>';
		$return .= '<td>'.$scripts->ver.'</td>';
		preg_match('/\d+(\.\d+)+/', self::$cdn_scripts[$scripts->handle], $matches);
		$return .= '<td>'.$matches[0].'</td>';
		$return .= '<td>';
			/*$return .= '<table>';
			$return .= '<tr><td><input type="radio" /><label>default src:</label></td><td>'.$scripts->src.'</td></tr>';
			$return .= '<tr><td><input type="radio" /><label>cdn src:</label></td><td>'.self::$cdn_scripts[$scripts->handle].'</td></tr>';
			$return .= '<tr><td><input type="radio" /><label>custom src:</label></td><td><input /></td></tr>';
			$return .= '</table>';*/
			
			$return .= '<label><input type="radio" name="'.$scripts->handle.'" value="native" />default src: '.$scripts->src.'</label><br />';
			$return .= '<label><input type="radio" name="'.$scripts->handle.'" value="cdn" />cdn src: '.self::$cdn_scripts[$scripts->handle].'</label><br />';
			$return .= '<label><input type="radio" name="'.$scripts->handle.'" value="custom" />custom src:</label><input name="custom"/>';
			
		$return .= '</td>';
		$return .= '<td class="">';
		if ($scripts->deps) foreach($scripts->deps as $val) $return .= "$val<br>";
		$return .= '</td>';
		$return .= '</tr>';
		return $return;
	}
		
	static function overwrite_srcs() {
		global $wp_scripts;
		foreach($wp_scripts->registered as $key => $val) {
			if (self::$cdn_scripts[$key]) {
				$val->src = self::$cdn_scripts[$key];
				$val->ver = null;
				wp_register_script($val);
			}
		}
	}
	
	static function get_cdn() {
		$xml = file_get_contents("http://cdnjs.com/");
		$haystack = substr($xml,strpos($xml,'<table'),strpos($xml,'</table>')-strpos($xml,'<table')+9);
		$needle = '<a itemprop="name" href="libraries/';
		$needle2 = '<p itemprop="downloadUrl" class="library-url" style="padding: 0; margin: 0">';
		
		$cdn_scripts = array();
		
		while (($lastPos = strpos($haystack, $needle, $lastPos))!== false) {
			$positions[] = $lastPos;
			$lastPos = $lastPos + strlen($needle);
			
			
			$key = substr($haystack,$lastPos,strpos($haystack,'"',$lastPos)-$lastPos);
			$keyPos = strpos($haystack,$needle2,$lastPos) + strlen($needle2);
			
			$val = substr($haystack,$keyPos,strpos($haystack,'</p>',$keyPos)-$keyPos);
			$cdn_scripts[$key] = $val;
		}
		
		return $cdn_scripts;
	}
	
}
add_action('init', array('load_cdn_scripts', 'init'));


















?>