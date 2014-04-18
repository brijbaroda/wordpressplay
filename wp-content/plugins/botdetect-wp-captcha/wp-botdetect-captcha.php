<?php

/*  Copyright 2013  Captcha Inc. (email : development@captcha.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


/*
Plugin Name: BotDetect CAPTCHA
Plugin URI: http://captcha.com/doc/php/wordpress-captcha.html?utm_source=plugin&amp;utm_medium=wp&amp;utm_campaign=3.0.Beta1.7
Description: Adds BotDetect CAPTCHA to WordPress comments, login, registration, and lost password.
Version: 3.0.Beta1.7
Author: BotDetect CAPTCHA
Author URI: http://captcha.com
*/

/**
 * WordPress DB defaults & options
 */
$LBD_WP_Defaults['generator'] = 'library';
$LBD_WP_Defaults['library_path'] = __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR;
$LBD_WP_Defaults['library_assets_url'] = plugin_dir_url( __FILE__ ) . 'lib/botdetect/public/';
$LBD_WP_Defaults['service_api_key'] = '';
$LBD_WP_Defaults['on_login'] = true;
$LBD_WP_Defaults['on_comments'] = true;
$LBD_WP_Defaults['on_lost_password'] = true;
$LBD_WP_Defaults['on_registration'] = true;
$LBD_WP_Defaults['audio'] = true;
$LBD_WP_Defaults['image_width'] = 235;
$LBD_WP_Defaults['image_height'] = 50;
$LBD_WP_Defaults['code_length'] = 4;
$LBD_WP_Defaults['helplink'] = 'image';
$LBD_WP_Defaults['remote'] = true;

$LBD_WP_Options = get_option('botdetect_options');
if(is_array($LBD_WP_Options)){
	$LBD_WP_Options = array_merge($LBD_WP_Defaults, $LBD_WP_Options);
}else{
	$LBD_WP_Options = $LBD_WP_Defaults;
}

/**
 * In case of a local library generator, include the required library files and route the request.
 */
if ($LBD_WP_Options['generator'] == 'library' && is_file($LBD_WP_Options['library_path'] . 'botdetect/CaptchaIncludes.php')) {
	define('LBD_INCLUDE_PATH', $LBD_WP_Options['library_path'] . 'botdetect/');
	define('LBD_URL_ROOT', $LBD_WP_Options['library_assets_url']);

	require_once($LBD_WP_Options['library_path'] . 'botdetect/CaptchaIncludes.php');
	require_once($LBD_WP_Options['library_path'] . 'botdetect/CaptchaConfig.php');

	// Configure Botdetect with WP settings
	$LBD_CaptchaConfig = CaptchaConfiguration::GetSettings();
	$LBD_CaptchaConfig->HandlerUrl = home_url( '/' ) . 'index.php?botdetect_request=1'; //handle trough the WP stack
	$LBD_CaptchaConfig->ReloadIconUrl = $LBD_WP_Options['library_assets_url'] . 'lbd_reload_icon.gif';
	$LBD_CaptchaConfig->SoundIconUrl = $LBD_WP_Options['library_assets_url'] . 'lbd_sound_icon.gif';
	$LBD_CaptchaConfig->LayoutStylesheetUrl = $LBD_WP_Options['library_assets_url'] . 'lbd_layout.css';
	$LBD_CaptchaConfig->ScriptIncludeUrl = $LBD_WP_Options['library_assets_url'] . 'lbd_scripts.js';

	$LBD_CaptchaConfig->CodeLength = $LBD_WP_Options['code_length'];
	$LBD_CaptchaConfig->ImageWidth = $LBD_WP_Options['image_width'];
	$LBD_CaptchaConfig->ImageHeight = $LBD_WP_Options['image_height'];

	$LBD_CaptchaConfig->SoundEnabled = $LBD_WP_Options['audio'];
	$LBD_CaptchaConfig->RemoteScriptEnabled = $LBD_WP_Options['remote'];  

	switch ($LBD_WP_Options['helplink']) {
		case 'image':
			$LBD_CaptchaConfig->HelpLinkMode = HelpLinkMode::Image;
			break;

		case 'text':
			$LBD_CaptchaConfig->HelpLinkMode = HelpLinkMode::Text;
			break;

		case 'off':
			$LBD_CaptchaConfig->HelpLinkEnabled = false;
			break;

		default:
			$LBD_CaptchaConfig->HelpLinkMode = HelpLinkMode::Image;
			break;
	}

	// Route the request
	if (isset($_GET['botdetect_request']) && $_GET['botdetect_request']) {
	  // direct access, proceed as Captcha handler (serving images and sounds), terminates on output.
	  require_once(LBD_INCLUDE_PATH . 'CaptchaHandler.php');
	} else {
	  // included in another file, proceed as Captcha class (form helper)
	  require_once(LBD_INCLUDE_PATH . 'CaptchaClass.php');
	}
}

class WP_Botdetect_Plugin{
	public static $instance;
	var $options = array();
	var $is_solved = false;

	/**
	 * Init & setup hooks
	 */
	public function __construct($options) {
		self::$instance = $this;
		register_activation_hook(__FILE__, array('WP_Botdetect_Plugin', 'add_defaults'));
		register_uninstall_hook(__FILE__, array('WP_Botdetect_Plugin', 'delete_options'));

		$this->hook('admin_init', 'wp_version_requirement');
		$this->hook('init', 'init_sessions');

		// We don't want the captcha to appear for logged in users. -- Mario: changed
	//	$this->hook('init', 'solve_if_logged_in');
		$this->hook('wp_logout', 'login_reset');

		// OPTIONS
		$this->options = $options;

		$this->hook('admin_menu', 'add_options_page');
		$this->hook('admin_init', 'register_setting');
		$this->hook('admin_footer', 'admin_scripts');

		add_filter( 'plugin_action_links', array($this, 'plugin_action_links'), 10, 2 );

		// GENERATOR NOTICES
		if($this->options['generator'] == 'library' && !class_exists('LBD_CaptchaBase')){
			$this->hook('admin_notices', 'captcha_library_missing_notice');
			return;
		}

		if($this->options['generator'] == 'service'){
			$this->hook('admin_notices', 'captcha_service_notice');
			return;
		}

		$this->hook('init', 'register_scripts');

		// USE ON
		if($this->options['on_login']){
			$this->hook('login_head', 'login_head');
			$this->hook('login_form', 'login_form');
			$this->hook('authenticate', 'login_validate', 1);
		}

		if($this->options['on_comments']){
			$this->hook('wp_enqueue_scripts', 'comment_head');
			$this->hook('comment_form_after_fields', 'comment_form');
			$this->hook('comment_form_logged_in_after', 'comment_form'); // Mario 20131004
			$this->hook('pre_comment_on_post', 'comment_validate', 1);
			$this->hook('comment_post', 'comment_reset');
		}

		if($this->options['on_lost_password']){
			$this->hook('login_head', 'login_head');
			$this->hook('lostpassword_form', 'lost_password_form');
			$this->hook('lostpassword_post', 'lost_password_validate');
		}

		if($this->options['on_registration']){
			$this->hook('login_head', 'login_head');
			$this->hook('register_form', 'register_form');
			$this->hook('registration_errors', 'register_validation');
		}

	}


	public function init_sessions() {
		if (!session_id()) {
				session_start();
		}
	}

	public function solve_if_logged_in(){
		// We don't want the captcha to appear for logged in users.
	
  // mario: always visible -- removed
  // $this->is_solved = is_user_logged_in();
	}

	public function register_scripts(){
		wp_register_style( 'botdetect-captcha-style', CaptchaUrls::LayoutStylesheetUrl());
	}

	/**
	 * Show Captcha on login form
	 */
	public function login_form(){
		$this->show_captcha_form('login_captcha', 'login_captcha_field');
	}

	public function login_validate($user){
		if ($_POST){
			$isHuman = $this->validate_captcha('login_captcha', 'login_captcha_field');
			if(!$isHuman){
				if (!is_wp_error($user)) {
					$user = new WP_Error();
				}

				$user->add('captcha_fail', __('<strong>ERROR</strong>: Please retype the letters under the CATPCHA image.'), 'BotDetect');
				remove_action('authenticate', 'wp_authenticate_username_password', 20);
				return $user;
			}
		}
	}

	public function login_reset(){
		$this->reset_captcha('login_captcha', 'login_captcha_field');
	}

	public function login_head(){
		wp_enqueue_style( 'botdetect-captcha-style' );
	}

	/**
	 * Show Captcha on comment form
	 */
	public function comment_form(){
		$this->show_captcha_form('comment_captcha', 'comment_captcha_field', array(
			'label' => __('Retype the characters', 'BotDetect'),
			'prepend' => '<p>',
			'append' => '</p>'
			));
	}
	public function comment_validate(){
		if ($_POST){
			$isHuman = $this->validate_captcha('comment_captcha', 'comment_captcha_field');
			if(!$isHuman){
				wp_die( __('<strong>ERROR</strong>: Please browser\'s back button and retype the letters under the CATPCHA image.'), 'BotDetect');
			}
		}

			// Possible alternative to wp_die();
			// $location = empty($_POST['redirect_to']) ? get_comment_link($comment_id) : $_POST['redirect_to'] . '#comment-' . $comment_id;
			// $location = apply_filters('comment_post_redirect', $location, $comment);

			// wp_safe_redirect( $location );
			// exit;
	}

	public function comment_head(){
		wp_enqueue_style( 'botdetect-captcha-style' );
	}

	public function comment_reset(){
		$this->reset_captcha('comment_captcha', 'comment_captcha_field');
	}

	/**
	 * Show Captcha on lost password form
	 */
	public function lost_password_form(){
		$this->show_captcha_form('lost_password_captcha', 'lost_password_captcha_field');
	}

	public function lost_password_validate(){
		if ($_POST){
			$isHuman = $this->validate_captcha('lost_password_captcha', 'lost_password_captcha_field');
			if(!$isHuman){
				wp_die( __('<strong>ERROR</strong>: Please browser\'s back button and retype the letters under the CATPCHA image.'), 'BotDetect');
			}else{
				$this->reset_captcha('lost_password_captcha', 'lost_password_captcha_field');
			}
		}
	}

	/**
	 * Show Captcha on register form
	 */
	public function register_form(){
		$this->show_captcha_form('register_captcha', 'register_captcha_field');
	}

	public function register_validation($error){
		if ($_POST){
			$isHuman = $this->validate_captcha('register_captcha', 'register_captcha_field');
			if(!$isHuman){
				if (!is_wp_error($error)) {
					$error = new WP_Error();
				}

				$error->add('captcha_fail', __('<strong>ERROR</strong>: Please retype the letters under the CATPCHA image.'), 'BotDetect');
				return $error;
			}else{
				$this->reset_captcha('register_captcha', 'register_captcha_field');
				return $error;
			}
		}
	}

	/**
	 * Captcha helpers
	 */
	public function validate_captcha($captcha_ID = 'BotDetectCaptcha', $UserInputId = 'CaptchaCode'){
		$captcha = &$this->init_captcha($captcha_ID, $UserInputId);

  /*  mario: always visible -- removed
		$this->is_solved = $captcha->IsSolved;
		if($captcha->IsSolved){
			$isHuman = true;
		}else{     
			$UserInput = $_POST[$UserInputId];
			$isHuman = $captcha->Validate($UserInput);
    }
    */
  
   // mario: always visible -- new
    $UserInput = $_POST[$UserInputId];
    $isHuman = $captcha->Validate($UserInput);
 
		return $isHuman;
	}

	/**
	 *
	 */
	public function get_capcha_form($captcha_ID = 'BotDetectCaptcha', $UserInputId = 'CaptchaCode'){
		$captcha = &$this->init_captcha($captcha_ID, $UserInputId);

	  /*  mario: always visible -- removed
    if (!$captcha->IsSolved && !$this->is_solved){
			$output = $captcha->Html();
			$output .= '<input name="' . $UserInputId . '" type="text" id="' . $UserInputId .'" />';
		}else{
			$output = '';
		}
    */
    
    // mario: always visible -- new
    $output = $captcha->Html();
    $output .= '<input name="' . $UserInputId . '" type="text" id="' . $UserInputId .'" />';

		return $output;
	}

	public function show_captcha_form($captcha_ID = 'BotDetectCaptcha', $UserInputId = 'CaptchaCode', $options = array()){
		$elements = array();
		$elements[] = $this->get_capcha_form($captcha_ID, $UserInputId);
		if(isset($options) && count($options) != 0 && isset($options[0])){
			if (array_key_exists('label', $options)){
				array_unshift($elements, '<label for="' . $UserInputId. '">' . $options['label']. '</label>');
			}
			if (array_key_exists('prepend', $options)){
				array_unshift($elements, $options['prepend']);
			}
			if (array_key_exists('append', $options)){
				$elements[] = $options['append'];
			}
		}
		echo implode('', $elements);
	}

	public function reset_captcha($captcha_ID = 'BotDetectCaptcha', $UserInputId = 'CaptchaCode'){
		$captcha = &$this->init_captcha($captcha_ID, $UserInputId);
		$captcha->Reset();
		$this->is_solved = false;
	}

	public function &init_captcha($captcha_ID = 'BotDetectCaptcha', $UserInputId = 'CaptchaCode'){
		$captcha = new Captcha($captcha_ID);
		$captcha->UserInputId = $UserInputId;

		return $captcha;
	}

	/**
	 * Admin notices
	 */
	function captcha_library_missing_notice(){
	  echo '<div class="error"><p>' . sprintf(__( '<b>You are almost done!</b> This Plugin requires you to deploy the BotDetect PHP Captcha library to your WordPress server.<br>
Please <a href="http://captcha.com/captcha-download.html?version=php&amp;utm_source=plugin&amp;utm_medium=wp&amp;utm_campaign=3.0.Beta1.7" title="Download the BotDetect PHP CAPTCHA Library">download</a> the BotDetect PHP Captcha Library from the <b>captcha.com</b> site, and deploy the \'lib\' folder from the downloaded archive to your WordPress server. That \'lib\' folder should end up at the following path: "%s" (=> note that path can also be customized in Plugin settings).', 'botdetect'), $this->options['library_path']) . '</p></div>';
	}
  

	function captcha_service_notice(){
	  echo '<div class="updated"><p>' . __( 'The BotDetect Captcha service is currently in a closed Alpha testing phase. Please contact us if you wish to participate in testing.', 'botdetect') . '</p></div>';
	}

	/**
	 * Minimum WP version
	 */
	public function wp_version_requirement() {
		global $wp_version;
		$plugin = plugin_basename( __FILE__ );
		$plugin_data = get_plugin_data( __FILE__, false );

		if ( version_compare($wp_version, "3.3", "<" ) ) {
			if( is_plugin_active($plugin) ) {
				deactivate_plugins( $plugin );
				wp_die( "'".$plugin_data['Name']."' requires WordPress 3.3 or higher, and has been deactivated! Please upgrade WordPress and try again.<br /><br />Back to <a href='".admin_url()."'>WordPress admin</a>." );
			}
		}
	}

	/**
	 * Add defaults on plugin activation
	 */
	public static function add_defaults() {
		global $LBD_WP_Defaults;

		$tmp = get_option('botdetect_options');
		if($tmp['chk_default_options_db'] == true || !is_array($tmp)) {
			delete_option('botdetect_options');
			update_option('botdetect_options', $LBD_WP_Defaults);
		}
	}

	/**
	 * Delete options on deactivation
	 */
	public static function delete_options() {
		delete_option('botdetect_options');
	}

	/**
	 * Add options page
	 */
	public function add_options_page() {
		add_options_page('BotDetect CAPTCHA WordPress Plugin Settings', 'BotDetect CAPTCHA', 'manage_options', __FILE__, array($this,'render_options_page'));
	}

	public function plugin_action_links( $links, $file ) {

		if ( $file == plugin_basename( __FILE__ ) ) {
			$action_link = '<a href="'.get_admin_url().'options-general.php?page=botdetect-wp-captcha/wp-botdetect-captcha.php">'.__('Settings').'</a>';
			// make the 'Settings' link appear first
			array_unshift( $links, $action_link );
		}

		return $links;
	}

	public function register_setting() {
		register_setting( 'botdetect_plugin_options', 'botdetect_options', array($this,'validate_options'));
	}

	/**
	 * Sanitize & Validate
	 */
	public function validate_options($input) {
		 // strip html from textboxes
		$input['image_width'] =  absint(wp_filter_nohtml_kses($input['image_width'])) ;
		$input['image_height'] =  absint(wp_filter_nohtml_kses($input['image_height']));
		$input['code_length'] =  absint(wp_filter_nohtml_kses($input['code_length']));

		$input['generator'] =  ($input['generator'] == 'library' || $input['generator'] == 'service')? $input['generator']: 'library';
		$input['library_path'] =  trailingslashit($input['library_path']);
		$input['library_assets_url'] =  trailingslashit(wp_filter_nohtml_kses($input['library_assets_url']));
		$input['service_api_key'] =  wp_filter_nohtml_kses($input['service_api_key']);

		$input['on_login'] =  (empty($input['on_login']))? false : true		;
		$input['on_comments'] =  (empty($input['on_comments']))? false : true;
		$input['on_lost_password'] =  (empty($input['on_lost_password']))? false : true;
		$input['on_registration'] =  (empty($input['on_registration']))? false : true;
		$input['audio'] =  (empty($input['audio']))? false : true;


		$input['helplink'] =  ($input['helplink'] == 'image' || $input['helplink'] == 'text' || $input['helplink'] == 'off')? $input['helplink'] : 'image';

		$input['chk_default_options_db'] =  (empty($input['chk_default_options_db']))? false : true;

		return $input;
	}

	/**
	 * Output the options page & form HTML
	 */
	public function render_options_page() {
		?>
		<div class="wrap">

			<div class="icon32" id="icon-options-general"><br></div>
			<h2><?php _e('BotDetect CAPTCHA WordPress Plugin (v3.0.Beta1.7)', 'botdetect');?></h2>
			<p></p>
      
			<form method="post" action="options.php">
				<?php settings_fields('botdetect_plugin_options'); ?>
				<?php $options = $this->options; ?>
        
				<table class="form-table">

					<tr valign="top" style="border-top:#dddddd 1px solid;">
            <td scope="row" colspan=2 ><?php _e('The license under which the BotDetect Captcha WordPress Plugin software is released is the <a href="http://www.gnu.org/licenses/gpl-2.0.txt">GPLv2</a> (or later) from the <a href="http://www.fsf.org/">Free Software Foundation</a>. Plugin source code is available for <a href="http://captcha.com/download/bd3/component/php/wordpress/wp-botdetect-captcha.zip">download here</a>.', 'botdetect'); ?></td>
          </tr>
          <tr valign="top" >            
						<th scope="row"><h3><?php _e('Plugin settings', 'botdetect'); ?></h3></th>
						<td>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e('Generate Captchas', 'botdetect'); ?></th>
						<td>
							<div id="botdetect_service_options_wrapper" class="botdetect_options_wrapper">
								<label style="color: #ccc;"><input class="botdetect_generator_select" name="botdetect_options[generator]" type="radio" value="service" disabled="disabled" /> <?php _e('Remotely', 'botdetect'); ?> <span style="color:#ccc;margin-left:5px;"><?php _e('(using the BotDetect CAPTCHA service -- not available since BotDetect Captcha service is currently in a private pre-Alpha testing phase)', 'botdetect'); ?></span></label><br />
								<div id="botdetect_service_options_wrapper" class="botdetect_options_wrapper" style="margin-left: 15px; margin-bottom: 20px;">
									<label style="color: #ccc;"><?php _e('Service API key:', 'botdetect'); ?> <br /><input type="text" size="50" name="botdetect_options[service_api_key]" value="" disabled="disabled" /></label><br />
									<label style="color: #ccc;"><input name="botdetect_options[service_redundancy]" type="checkbox" value="true" checked="checked" disabled="disabled"/> <?php _e('Use a local BotDetect CAPTCHA library as a fallback.', 'botdetect'); ?> </label><br />
								</div>
								<label><input class="botdetect_generator_select" name="botdetect_options[generator]" type="radio" value="library" checked="checked" disabled="disabled" /> <?php _e('Locally', 'botdetect'); ?> <span style="color:#666666;margin-left:5px;"><?php _e('(using a local BotDetect PHP CAPTCHA Library)', 'botdetect'); ?></span></label><br />
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e('Local BotDetect CAPTCHA Library', 'botdetect'); ?></th>
						<td>
							<div id="botdetect_library_options_wrapper" class="botdetect_options_wrapper">
								<label><?php _e('Filesystem path to the library folder:', 'botdetect'); ?> <br /><input type="text" size="50" name="botdetect_options[library_path]" value="<?php echo (isset($options['library_path']))? $options['library_path'] : ''; ?>" /><br /></label>
								<label><?php _e('URL to directory containing public resources:', 'botdetect'); ?> <br /><input type="text" size="50" name="botdetect_options[library_assets_url]" value="<?php echo (isset($options['library_assets_url']))? $options['library_assets_url'] : ''; ?>" /><br /></label>
							</div>
						</td>
					</tr>
					<tr><td colspan="2"><div style="margin-top:10px; border-top:#dddddd 1px solid;"></div></td></tr>
					<tr valign="top">
						<th scope="row"><?php _e('Use BotDetect CAPTCHA with', 'botdetect'); ?></th>
						<td>
							<label><input name="botdetect_options[on_login]" type="checkbox" value="true" <?php if (isset($options['on_login'])) { checked($options['on_login'], true); } ?> /> <?php _e('Login', 'botdetect'); ?> </label><br />
							<label><input name="botdetect_options[on_registration]" type="checkbox" value="true" <?php if (isset($options['on_registration'])) { checked($options['on_registration'], true); } ?> /> <?php _e('User Registration', 'botdetect'); ?> </label><br />
							<label><input name="botdetect_options[on_lost_password]" type="checkbox" value="true" <?php if (isset($options['on_lost_password'])) { checked($options['on_lost_password'], true); } ?> /> <?php _e('Lost Password', 'botdetect'); ?> </label><br />
							<label><input name="botdetect_options[on_comments]" type="checkbox" value="true" <?php if (isset($options['on_comments'])) { checked($options['on_comments'], true); } ?> /> <?php _e('Wordpress Comments', 'botdetect'); ?> </label><br />
						</td>
					</tr>

					<tr>
						<th scope="row"><?php _e('Captcha image width', 'botdetect'); ?></th>
						<td>
							<input type="text" size="3" name="botdetect_options[image_width]" value="<?php echo (isset($options['image_width']))? $options['image_width'] : ''; ?>" />
							<span style="color:#666666;">px</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e('Captcha image height', 'botdetect'); ?></th>
						<td>
							<input type="text" size="3" name="botdetect_options[image_height]" value="<?php echo (isset($options['image_height']))? $options['image_height'] : ''; ?>" />
							<span style="color:#666666;">px</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e('Number of characters', 'botdetect'); ?></th>
						<td>
							<input type="text" size="3" name="botdetect_options[code_length]" value="<?php echo (isset($options['code_length']))? $options['code_length'] : ''; ?>" />
						</td>
					</tr>

					<tr>
						<th scope="row"><?php _e('Sound', 'botdetect'); ?></th>
						<td>
							<label><input name="botdetect_options[audio]" type="checkbox" value="true" <?php if (isset($options['audio'])) { checked($options['audio'], true); } ?> /> <?php _e('Enable audio Captcha', 'botdetect'); ?></label>
						</td>
					</tr>
          
          <?php 
            $isFree = false; 
            if (class_exists('Captcha') && Captcha::IsFree()) $isFree = true; 
          ?>
           
					<tr>
						<th scope="row"><?php _e('Remote Include', 'botdetect'); ?></th>
						<td>
							<label><input name="botdetect_options[remote]" type="checkbox" value="true" 
              <?php if ($isFree) echo "disabled"; ?>
              <?php if (isset($options['remote'])) { checked($options['remote'], true); } ?> /> <?php _e('Enable Remote Include -- used for statistics collection and proof-of-work confirmation (still work in progress)','botdetect'); ?>
              <?php if ($isFree) { ?>
              <br>
              <?php _e('<i>Switching off is disabled with the Free version of BotDetect.', 'botdetect'); ?> <?php _e('<a href="http://captcha.com/doc/php/wordpress/paid-version.html?utm_source=plugin&amp;utm_medium=wp&amp;utm_campaign=3.0.Beta1.7">Upgrade</a> to enable.</i>', 'botdetect'); ?></span></label><br />              
              </label>
              <?php } ?>
						</td>
					</tr>
          
					<tr valign="top">
						<th scope="row"><?php _e('Help link', 'etc'); ?></th>
						<td>
							<label><input name="botdetect_options[helplink]" type="radio" value="image" <?php checked($options['helplink'], 'image'); ?> /> Image <span style="color:#666666;margin-left:42px;">Clicking the Captcha image opens the help page in a new browser tab.</span></label><br />
							<label><input name="botdetect_options[helplink]" type="radio" value="text" <?php checked($options['helplink'], 'text'); ?> /> Text <span style="color:#666666;margin-left:56px;">A text link to the help page is rendered in the bottom 10 px of the Captcha image.</span></label><br />
							<label><input name="botdetect_options[helplink]"
              <?php if ($isFree) echo "disabled"; ?>
              type="radio" value="off" <?php checked($options['helplink'], 'off'); ?> /> Off <span style="color:#666666;margin-left:63px;">
              <?php if ($isFree) { ?>
              <?php _e('<i>Not available with the Free version of BotDetect.', 'botdetect'); ?> <?php _e('<a href="http://captcha.com/doc/php/wordpress/paid-version.html?utm_source=plugin&amp;utm_medium=wp&amp;utm_campaign=3.0.Beta1.7">Upgrade</a> to enable.</i>', 'botdetect'); ?></span></label><br />
              <?php } else { ?>
              <?php _e('Help link is disabled.', 'botdetect'); ?></span></label><br />
              <?php } ?>
						</td>
					</tr>

					<tr>

						<td colspan = "2">
							<p><?php _e('Additionaly: Please note almost everything is customizable by editing BotDetect\'s <a href="http://captcha.com/doc/php/howto/captcha-configuration.html?utm_source=plugin&amp;utm_medium=wp&amp;utm_campaign=3.0.Beta1.7">configuration file</a>.', 'botdetect'); ?></p>
						</td>
					</tr>

					<tr><td colspan="2"><div style="margin-top:10px; border-top:#dddddd 1px solid;"></div></td></tr>
					<tr valign="top">
						<th scope="row"><?php _e('Misc Options', 'botdetect'); ?></th>
						<td>
							<label><input name="botdetect_options[chk_default_options_db]" type="checkbox" value="true" <?php if (isset($options['chk_default_options_db'])) { checked($options['chk_default_options_db'], true); } ?> /> <?php _e('Restore defaults upon plugin deactivation/reactivation', 'botdetect'); ?></label>
							<br /><span style="color:#666666;margin-left:2px;"><?php _e('Only check this if you want to reset plugin settings upon Plugin reactivation', 'botdetect'); ?></span>
						</td>
					</tr>
				</table>
				<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
				</p>
			</form>
		</div>
		<?php
	}

	function admin_scripts(){

	}

	/**
	 * Add action helper
	 */
	public function hook($hook){
		$priority = 10;
		$method = $this->sanitize_method($hook);
		$additional_args = func_get_args();
		unset($additional_args[0]);
		// set priority
		foreach((array)$additional_args as $a){
			if(is_int($a)){
				$priority = $a;
			}else{
				$method = $a;
			}
		}
		return add_action($hook,array($this,$method),$priority,999);
	}

	/**
	 * Sanitize hooks
	 */
	private function sanitize_method($m){
		return str_replace(array('.','-'),array('_DOT_','_DASH_'),$m);
	}

}
new WP_Botdetect_Plugin($LBD_WP_Options);
?>