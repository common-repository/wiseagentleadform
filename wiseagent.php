<?php
/*  Plugin Name: Wise Agent Lead Forms
    Plugin URI: http://www.wiseagent.com
    Description: Wise Agent Lead Forms plugin for WordPress
    Version: 3.2.2
    Tags: CRM, forms, capture forms, contact management, Lead Capture Forms, Wise Agent, Lead Management Tool, Leads, Lead Capture, Landing page, WA forms, Wise Agent forms, Wise Agent Lead Forms
    License: GPLv2 or later
    License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

defined('ABSPATH') or die();
define('WA_LOG_FILE',WP_CONTENT_DIR . '/debug.log');
require_once('WA_API.php');

class wa_wp {
    private $wa_api;
    private $has_elementor = false;

    public function __construct()
    {

        $this->wa_api = new WA_API(get_option('wiseagent_options'), get_option('wiseagent_hcaptcha_options'), get_option('wiseagent_recaptcha_options'));
        add_action('admin_menu', array($this,'wiseagent_admin_menu'));
        // Register callback endpoint to handle Wise Agent OAuth2 callback
        add_action('admin_post_wa_oauth2', array($this,'wiseagent_oauth2_callback'));
        add_action('admin_post_wa_oauth2_disconnect', array($this,'wiseagent_oauth2_disconnect'));
        add_action('admin_post_wa_refresh_capture_forms', array($this,'wiseagent_refresh_capture_forms'));
        // Add JavaScript to admin page
        add_action('admin_enqueue_scripts', array($this,'wiseagent_admin_scripts'));
        // Add Wise Agent admin page settings
        add_action('admin_init', array($this,'wiseagent_admin_init'));
        add_action('init', array($this,'wiseagent_init'));
        // Remove wiseagent options on deactivation
        register_deactivation_hook(__FILE__, array($this,'wiseagent_deactivate'));

        // Add CSS for Capture Forms
        add_action('wp_enqueue_scripts', array($this,'wiseagent_capture_form_css'));
        add_action('admin_enqueue_scripts', array($this,'wiseagent_capture_form_css'));

        add_action('wp_enqueue_scripts', array($this,'wiseagent_capture_form_js'));

    }

    /**
     * Enqueue the captureForm.js script
     */
    public function wiseagent_capture_form_js() {
        wp_enqueue_script('wiseagent_capture_form_js', plugins_url('captureForm.js', __FILE__), array(), "1.0.2");
    }
    /**
     * Endpoint for form submission. Responds with thank you page and redirects to WACaptureForm::responsePage
     * 
     * Validates the nonce and redirects to the responsePage URL, if valid.
     * 
     * Sends data to Wise Agent API via WA_API::send_form_data()
     * 
     * Endpoint URL: /admin-ajax.php?action=wa_capture_form
     * 
     */
    public function wiseagent_capture_form() {
        $redirect_page = isset($_POST['responsePage']) ? $_POST['responsePage'] : '';
        $form_id = isset($_POST['userFormID']) ? $_POST['userFormID'] : '';

        // validate the wa_capture_form_nonce nonce
        if ( ! isset( $_POST["wa_capture_form_nonce"] ) || ! wp_verify_nonce( $_POST["wa_capture_form_nonce"], 'wa_capture_form' ) ) {
            error_log("wiseagent_capture_form: nonce failed for form_id $form_id\n", 3, WA_LOG_FILE);
            wp_nonce_ays("wa_capture_form_nonce");
        }
        // if not valid, use the referrer URL to take them back to the page they came from
        if ( ! filter_var($redirect_page, FILTER_VALIDATE_URL) ) {
            $redirect_page = $_POST['_wp_http_referer'];
            error_log("wiseagent_capture_form: responsePage is not a valid URL\n", 3, WA_LOG_FILE);
        }

        // Validate h-captcha-response
        $captcha_settings = $this->wa_api->get_hCaptcha_settings();
        if($captcha_settings['enabled'] == true) {
            $captcha_response = $_POST['h-captcha-response'];
            if($this->wa_api->validate_h_captcha($captcha_response) == false) {
                error_log("\nwiseagent_capture_form: h-captcha-response failed for form_id $form_id\n", 3, WA_LOG_FILE);
                wp_nonce_ays("wa_capture_form_nonce");
            } 
            // remove captcha response from POST data
            unset($_POST['h-captcha-response']);
            unset($_POST['g-recaptcha-response']);
        }

        // Validate re-captcha response
        $re_captcha_settings = $this->wa_api->get_re_captcha_settings();
        if($re_captcha_settings['enabled'] == true) {

            $re_captcha_response = $_POST['g-recaptcha-response'];
            if($this->wa_api->validate_re_captcha($re_captcha_response) == false) {
                error_log("\nwiseagent_capture_form: re-captcha-response failed for form_id $form_id\n", 3, WA_LOG_FILE);
                wp_nonce_ays("wa_capture_form_nonce");
            } 
            // remove captcha response from POST data
            unset($_POST['h-captcha-response']);
            unset($_POST['g-recaptcha-response']);
        }

        // Send form data to webconnect
        $this->wa_api->send_form_data($_POST);

        // Redirect to $redirect_page
        wp_redirect($redirect_page);
        exit();
    }


    /**
     * Endpoint for user action to refresh capture forms cache
     * 
     * Endpoint URL: /wp-admin/admin-post.php?action=wa_refresh_capture_forms
     */
    public function wiseagent_refresh_capture_forms () {
        $this->wa_api->update_forms_cache();
        wp_redirect(admin_url('admin.php?page=wiseagent-captureforms'));
        exit();
    }

    /**
     * Render capture form admin page.
     */
    public function wiseagent_capture_form_menu_page() {
        if( !current_user_can('manage_options') ) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        require_once('classes/captureFormTable.php');
        if($this->wa_api->is_logged_in()) {
            echo '<div id="wp-admin-alert" class="updated notice" style="display:none;">Copied to Clipboard!</div>';

            // Render the capture form table
            echo '<div class="wrap">';
            echo '<h1>Capture Forms</h1>';
            echo '<p>Use the following shortcode to display a capture form on any page or post.</p>';
            echo '<h3>Edit capture forms in <a href="' . esc_url($this->wa_api->wa_base_url) .  '/secure/user/formwizard.asp" target="_blank">Wise Agent</a></h3>';
            //Render link to blog post with instructions
            echo '<p><a href="https://wise-agent-crm.groovehq.com/help/what-is-capture-forms" target="_blank">Learn more about capture forms</a></p>';

            // Button to refresh the cached capture forms
            $last_updated = new DateTime();
            $tz = get_option('timezone_string');
            if($tz === false || strlen($tz) == 0)
                $tz = 'UTC';
            $url = admin_url('admin-post.php?action=wa_refresh_capture_forms');
            error_log("wiseagent_capture_form_menu_page: timezone_string: $tz\n", 3, WA_LOG_FILE);
            $last_updated->setTimezone(new \DateTimeZone($tz));
            if($this->wa_api->last_cache != null)
                $last_updated->setTimestamp($this->wa_api->last_cache);

            echo '<div class="wrap">';
            echo '<p>Last refreshed: ' . esc_html($last_updated->format('m/d/Y h:i:s A')) . '</p>';
            echo '<a id="refresh-capture-forms" class="button button-primary" href="'. esc_url($url) .'" ><span class="dashicons dashicons-update" style="display: inline-block; vertical-align: text-top; margin-right: 5px;"></span>Refresh Capture Forms</a>';
            echo '</div>';
            echo '<form id="form-filter" method="get">';
            echo '<input type="hidden" name="page" value="' . esc_html($_REQUEST['page']) . '" />';
            $table = new CaptureFormTable($this->wa_api);
            $table->prepare_items();
            $table->display();
            echo '</form>';
            echo '</div>';
        } else {
            echo '<div class="wrap">';
            echo '<h1>Wise Agent</h1>';
            $error_html = $this->wa_api->get_last_error_html();
            if($error_html != '') {
                echo wp_kses_post($error_html);
            }
            echo '<p>Wise Agent is a lead management tool that helps you capture leads, manage contacts, and track your marketing efforts.</p>';
            echo '<p>Visit <a href="https://wiseagent.com" target="_blank">www.wiseagent.com</a> to learn more.</p>';
            echo '</div>';
            echo '<div class="wrap">';
            echo '<h2>Connect to Wise Agent</h2>';
            echo '<p>Click the button below to connect to your Wise Agent account.</p>';
            // link to wiseagent-settings page
            echo '<a href="' . admin_url('admin.php?page=wiseagent-settings') . '" class="button button-primary">Connect to Wise Agent</a>';
            echo '</div>';
        }

    }

    /**
     * Add the settings page to the admin menu
     * 
     * Adds Wise Agent to the admin menu, with Submenu items for Settings and Capture Forms
     */ 
    public function wiseagent_admin_menu() {
        //Add a menu page
        add_menu_page( 'Capture Forms', 'Wise Agent', 'manage_options', 'wiseagent-captureforms', array($this,'wiseagent_capture_form_menu_page'), content_url("plugins/wiseagentleadform/images/logo_welcome.png"), 6 );
        add_submenu_page( 'wiseagent-captureforms', 'Capture Forms', 'Capture Forms', 'manage_options', 'wiseagent-captureforms', array($this,'wiseagent_capture_form_menu_page') );

        // Add submenu page
        add_submenu_page( 'wiseagent-captureforms', 'Settings', 'Settings', 'manage_options', 'wiseagent-settings', array($this,'wiseagent_settings_page') );
    }

    /**
     * Endpoint for OAuth 2.0 callback
     * 
     * This endpoint is called by Wise Agent after the user authorizes the app.
     * 
     * Endpoint URL: /wp-admin/admin-post.php?action=wa_oauth2
     */
    public function wiseagent_oauth2_callback() {
        $code = $_GET['code'];

        // code verified is stored in options
        $code_verifier = $this->wa_api->get_code_verifier();

        //exchange code for access token
        if(!$this->wa_api->exchange_auth_code($code, $code_verifier)) {
            // Show error message for 3 seconds
            echo '<script>setTimeout(function() { window.close(); },3000);</script>';
            echo '<div class="wrap">';
            echo '<h1>Wise Agent</h1>';
            echo '<p>There was an error connecting to Wise Agent. Please try again.</p>';
            echo esc_html($this->wa_api->get_last_error_html());
            echo '</div>';
            exit;
        }

        // close popup
        echo '<script>window.close();</script>';
        exit;
    }


    /**
     * Endpoint for OAuth 2.0 disconnect
     * 
     * This endpoint is called by Wise Agent after the user disconnects the app.
     * 
     * Endpoint URL: /wp-admin/admin-post.php?action=wa_oauth2_disconnect
     */
    public function wiseagent_oauth2_disconnect() {
        //Revoke our token
        if($this->wa_api->revoke_token()) {
            // Redirect to settings page
            wp_redirect(admin_url('admin.php?page=wiseagent-settings'));
            exit();
        } else {
            $err = $this->wa_api->get_last_error_raw();
            $url = admin_url('admin.php?page=wiseagent-settings');
            $url .= '&error=' . urlencode($err['error']);
            $url .= '&error_description=' . urlencode($err['error_description']);
            $url .= '&help=' . urlencode('If this problem persists, try disabling and re-enabling the plugin.');
            
            // Redirect to settings page w/ error
            wp_redirect($url);
            exit();
        }
    }

    /**
     * Render Wise Agent settings page
     */
    public function wiseagent_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        echo '<div class="wrap">';
        echo '<h2>Wise Agent</h2>';

        // Display errors from query string
        if(isset($_GET['error'])) {
            echo '<div class="notice notice-error notice-large">';
            echo '<h2>Error</h2>';
            echo '<p>Error: ' . esc_html($_GET['error']) . '</p>';
            echo '<p>Description: ' . esc_html($_GET['error_description']) . '</p>';
            if(isset($_GET['help'])) {
                echo '<strong>' . esc_html($_GET['help']) . '</strong>';
            }
            echo '</div>';
        }
        if(!$this->wa_api->is_logged_in()) {
            $error_html = $this->wa_api->get_last_error_html();
            if($error_html != '') {
                echo esc_html($error_html);
            }
            echo '<p>Click the button below to connect to your Wise Agent account.</p>';
            echo $this->wiseagent_login_btn();
        } else {
            // If elementor is not installed, display a message to the user
            if(!$this->has_elementor) {
                // display message notifying user to install Elementor
                echo '<div class="notice notice-info notice-large">';
                echo '<h2>Install Elementor</h2>';
                echo '<p>Elementor is a drag and drop page builder that allows you to create beautiful pages and posts.</p>';
                echo '<p>The Wise Agent plugin integrates with Elementor to drag and drop capture forms onto pages.</p>';
                echo '<p>Click the button below to install Elementor.</p>';
                echo '<a href="' . admin_url('plugin-install.php?s=elementor&tab=search&type=term') . '" class="button button-primary">Install Elementor</a>';
                echo '</div>';
            }
            // Display User Info
            $this->wiseagent_user();
            echo '<hr/>';

            // Display section for Google re-captcha setup
            $this->re_captcha_settings_form();

            // Display section for hCaptcha setup
            $this->h_captcha_settings_form();


        }
    }

    /**
     * Render the Google reCAPTCHA settings form
     */
    public function re_captcha_settings_form() {
        $re_captcha_settings = $this->wa_api->get_re_captcha_settings();
        $expanded_style = $re_captcha_settings['enabled'] ? 'display:block;' : 'display:none;';
        // Add dashicon chevron class
        $arrow_class = $re_captcha_settings['enabled'] ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2';

        echo '<div class="wa-collapsible-title" data-form-id="reCaptcha"><h3><a href="https://www.google.com/u/2/recaptcha/admin/create" target="_blank">Google reCAPTCHA (v3) Setup</a></h3> <i class="dashicons ' . $arrow_class . '" ></i></div>';
        echo '<div class="wiseagent-form-container">';
        echo '<div class="wiseagent-form-body wa-collapsible-content" data-form-id="reCaptcha" style="' . $expanded_style .'">';
        echo '<form method="post" action="options.php" class="wiseagent-form">';
        settings_fields('wiseagent_recaptcha_options');
        do_settings_sections('wiseagent_recaptcha_options');
    
        echo '<input type="checkbox" id="wiseagent_recaptcha_options[reCaptcha_enabled]" name="wiseagent_recaptcha_options[reCaptcha_enabled]" value="1" ' . ($re_captcha_settings['enabled'] ? 'checked' : '') . ' />';
        echo '<label for="wiseagent_recaptcha_options[reCaptcha_enabled]">Enable Google reCAPTCHA</label>';
        echo '<br/><br/>';
        echo '<label for="wiseagent_recaptcha_options[reCaptcha_site_key]">Google reCAPTCHA Site Key</label>';
        echo '<input style="width:450px;" placeholder="Your Google reCAPTCHA Site-Key" type="text" id="wiseagent_recaptcha_options[reCaptcha_site_key]" name="wiseagent_recaptcha_options[reCaptcha_site_key]" value="' . esc_html($re_captcha_settings['site_key']) . '" class="form-control"/>';
        echo '<br/>';
        echo '<label for="wiseagent_recaptcha_options[reCaptcha_secret]">Google reCAPTCHA Secret Key</label>';
        echo '<input style="width:450px;" placeholder="Your Google reCAPTCHA Secret" type="password" id="wiseagent_recaptcha_options[reCaptcha_secret]" name="wiseagent_recaptcha_options[reCaptcha_secret]" value="' . esc_html($re_captcha_settings['secret']) . '" class="form-control"/>';
        echo '<br/>';
        echo '<input type="submit" class="button button-primary" value="Save Settings" />';
        echo '</form>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render the hCaptcha settings form
     */
    public function h_captcha_settings_form() {
        $captcha_settings = $this->wa_api->get_hCaptcha_settings();
        $expanded_style = $captcha_settings['enabled'] ? 'display:block;' : 'display:none;';
        // Add dashicon chevron class
        $arrow_class = $captcha_settings['enabled'] ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2';
        echo '<hr/>';
        echo '<div class="wa-collapsible-title" data-form-id="hCaptcha"><h3><a href="https://www.hcaptcha.com/" target="_blank" >hCaptcha Setup</a></h3><i class="dashicons ' . $arrow_class . '" ></i></div>';
        echo '<div class="wiseagent-form-container">';
        echo '<div class="wiseagent-form-body wa-collapsible-content" data-form-id="hCaptcha" style="' . $expanded_style .'">';
        echo '<form method="post" action="options.php" class="wiseagent-form">';
        settings_fields('wiseagent_hcaptcha_options');
        do_settings_sections('wiseagent_hcaptcha_options');
    
        echo '<input type="checkbox" name="wiseagent_hcaptcha_options[hCaptcha_enabled]" id="wiseagent_hcaptcha_options[hCaptcha_enabled]" value="1" ' . ($captcha_settings['enabled'] ? 'checked' : '') . ' />';
        echo '<label for="wiseagent_hcaptcha_options[hCaptcha_enabled]">Enable hCaptcha</label>';
        echo '<br/><br/>';
        echo '<label for="wiseagent_hcaptcha_options[hCaptcha_site_key]">hCaptcha Site Key</label>';
        echo '<input style="width:450px;" placeholder="Your hCaptcha Site-Key" type="text" name="wiseagent_hcaptcha_options[hCaptcha_site_key]" id="wiseagent_hcaptcha_options[hCaptcha_site_key]" value="' . esc_html($captcha_settings['site_key']) . '" class="form-control"/>';
        echo '<br/>';
        echo '<label for="wiseagent_hcaptcha_options[hCaptcha_secret]">hCaptcha Secret Key</label>';
        echo '<input style="width:450px;" placeholder="Your hCaptcha Secret" type="password" name="wiseagent_hcaptcha_options[hCaptcha_secret]" id="wiseagent_hcaptcha_options[hCaptcha_secret]" value="' . esc_html($captcha_settings['secret']) . '" class="form-control"/>';
        echo '<br/>';
        echo '<input type="submit" class="button button-primary" value="Save Settings" />';
        echo '</form>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Enqueue the wiseagent-form.css stylesheet
     */
    public function wiseagent_capture_form_css() {
        wp_enqueue_style('wiseagent-form-css', plugin_dir_url(__FILE__) . 'css/wiseagent-form.css',array(),"1.1.1");
    }

    /**
     * Enqueue wiseagent.js for the admin page
     */
    public function wiseagent_admin_scripts() {
        wp_enqueue_script('wiseagent_admin', plugins_url('wiseagent.js', __FILE__), array(), "1.2.9");
    }

    /**
     * Register the wiseagent_options namespace for the settings page
     */
    public function wiseagent_admin_init() {
        register_setting('wiseagent_options', 'wiseagent_options');
        register_setting('wiseagent_hcaptcha_options', 'wiseagent_hcaptcha_options');
        register_setting('wiseagent_recaptcha_options', 'wiseagent_recaptcha_options');

        if (is_plugin_active( 'elementor/elementor.php' )) {
            $this->has_elementor = true;
        }
    }


    /**
     * Register shortcode and ajax actions
     */
    public function wiseagent_init() {
        //Register shortcode
        add_shortcode('wiseagent', array($this,'wiseagent_form_shortcode'));
        //actions for 
        add_action('wp_ajax_wa_capture_form', array($this,'wiseagent_capture_form'));
        add_action('wp_ajax_nopriv_wa_capture_form', array($this,'wiseagent_capture_form'));
        add_action( 'elementor/widgets/register', array($this,'register_new_widgets') );
    }

    /**
     * Render the Login to Wise Agent button.
     * 
     * Uses WA_API::get_sso_url() to get the URL for the button.
     */
    public function wiseagent_login_btn () {
        echo '<input id="loginBtn" type="button" class="button button-primary" value="Login to Wise Agent" onclick="javascript:wa_sso(\'' . $this->wa_api->get_sso_url() . '\')">';
    }

    /**
     * Render the currently logged in Wise Agent user.
     */
    public function wiseagent_user() {
        $user = $this->wa_api->wiseagent_get_user();
        $url = admin_url('admin-post.php?action=wa_oauth2_disconnect');
        if($user != null) {
            $imgUrl = $this->wa_api->wa_base_url . $user->image;
            $name = $user->name;
            $email = $user->email;
            echo "<div id='profileCard' class='card'>
           <div id='sessionProfileImageWrapper'>
               <img src='". esc_url($imgUrl). "' title='Profile Image' id='sessionProfileImage' onerror=\"this.onerror=null; this.src = 'https://thewiseagent.com/secure/team/images/photo.jpg'\">
           </div>
                <div class='userInfo'>
                    <strong id='sessionUser'>".esc_html($name)."</strong>
                    <small id='sessionEmail'>".esc_html($email)."</small>
                </div>
            </div>";
            echo '<div class="wrap">';
            echo '<a href="' . esc_url($url) . '" class="button button-disconnect">Disconnect</a>';
            echo '</div>';
        } else {
            echo '<strong>Authentication error: Invalid token</strong>';
        }
    }

    /**
     * Deactivation hook.
     * 
     * Revoke the access_token and delete the options.
     */
    public function wiseagent_deactivate() {
        $this->wa_api->revoke_token();
        delete_option('wiseagent_options');
        delete_option('wiseagent_hcaptcha_options');
        delete_option('wiseagent_recaptcha_options');
    }

    /**
     * Register the Wise Agent widget for Elementor
     */
    public function register_new_widgets( $widgets_manager ) {

        require_once( __DIR__ . '/wa_widget.php' );
    
        $widgets_manager->register( new \WA_Elementor_Widget() );
    
    }

    /**
     * Shortcode to render the Wise Agent capture form.
     */
    function wiseagent_form_shortcode($atts, $content = null, $tag = '') {
        $require_consent_checkbox = false;
        $fullatts = shortcode_atts(['form_id' => 0],$atts,$tag);
        $form = $this->wa_api->get_wa_capture_form($fullatts['form_id']);
        $sms_consent_content = wp_kses_post($form->topHtml);
        $mobile_required = false;
        if(strlen(trim($sms_consent_content)) == 0) {
            // Default SMS consent content
            $sms_consent_content = 'By checking off this box and submitting your information you are agreeing to the Terms and Privacy policy of this site, and are opting in to receive communications through SMS text messaging. Msg&data rates may apply. Text Help for info, and STOP to unsubscribe. Msg frequency varies.';
        }
        if($form == null) {
            return '<p>There was an error retrieving your forms. Please try again later.</p><br/><small>resp error</small><br/>';
        }

        $customFields = $form->customFields;
        if($form != null && $form->userFormID == 0) {
            return '<p>There was an error retrieving your forms. Please try again later.</p><br/><small>form userid 0</small><br/>';
        }


        $form_id = $form->userFormID;
        // Sanitize user input, remove unallowed html tags
        $source = wp_kses_post($form->Source);
        $response_page = wp_kses_post($form->responsePage);

        //CustomField schema: {"type":"text","id":"0","label":"TextField","name":"textfield","required":0,"maxChars":"20","charWidth":"1","value":""}

        $additional_fields_html = '';

        foreach($customFields as $c) {
            if($c == null) {
                continue;
            }
            $required = property_exists($c, 'required') ? $c->required == "1" : false;
            if($c->name == "Cell" || $c->name == "CCell") {
                $require_consent_checkbox = true;
                if($required)
                    $mobile_required = true;
            }
            $field_class = "wiseagent-form-field form-group";
            if(strtolower(trim($c->type)) == "checkbox") {
                $field_class .= " checkbox-field";
            } else if(strtolower(trim($c->type)) == "radio") {
                $field_class .= " radio-field";
            }
            $additional_fields_html .= '<div class="' . esc_html($field_class) . '">';
            if(strtolower(trim($c->type)) == "paragraph") {
                $additional_fields_html .= '<p id="wa_' . esc_html($c->name) . '">' . esc_html($c->text) . '</p>';
            }
            else {

                if((strtolower(trim($c->type)) == "select" || strtolower(trim($c->type)) == "radio") && count($c->optionList) > 0) {
                    $additional_fields_html .= '<label for="wa_' . esc_html($c->name) . '">' . esc_html($c->label) . '</label>';
                    if(strtolower(trim($c->type)) == "select") {

                        $additional_fields_html .= '<select ' . ($c->multi ? " multiple " : "") . ' id="wa_' . esc_html($c->name) . '" name="' . esc_html($c->name) . '" placeholder="' . esc_html($c->label) . '"' . ($required ? " required " : "") . ' class="form-control"/>';    

                        foreach($c->optionList as $o) {

                            $additional_fields_html .= '<option value="' . esc_html($o->value) . '">' . esc_html($o->text) . '</option>';
                        }
                        $additional_fields_html .= "</select>";

                    } else {
                        $i = 0;
                        $additional_fields_html .= '<div>';
                        foreach($c->optionList as $o) {
                            $additional_fields_html .= '<input value="'. esc_html($o->value) .'" type="' . esc_html($c->type) . '" id="wa_' . esc_html($c->name . $i) . '" name="' . esc_html($c->name) . '" placeholder="' . esc_html($o->text) . '" />';
                            $additional_fields_html .= '<label for="wa_' . esc_html($c->name . $i) . '">' . esc_html($o->text) . '</label>';
                            $i++;
                        }
                        $additional_fields_html .= '</div>';

                    }
                    
                } else if (strtolower(trim($c->type)) == "textarea") {
                    $additional_fields_html .= '<label for="wa_' . esc_html($c->name) . '">' . esc_html($c->label) . '</label>';
                    $additional_fields_html .= '<textarea id="wa_' . esc_html($c->name) . '" name="' . esc_html($c->name) . '" placeholder="' . esc_html($c->label) . ($required ? " required " : "") . '" maxlength="' . esc_html($c->max) . '" class="form-control"></textarea>';
                }
                else if(strtolower(trim($c->type)) == "checkbox") {
                    $additional_fields_html .= '<input type="checkbox" id="wa_' . esc_html($c->name) . '" name="' . esc_html($c->name) . '" placeholder="' . esc_html($c->label) . '" ' . ($required ? "required" : "") . '/>';
                    $additional_fields_html .= '<label for="wa_' . esc_html($c->name) . '">' . esc_html($c->label) . '</label>';
                }
                else {
                    $additional_fields_html .= '<label for="wa_' . esc_html($c->name) . '">' . esc_html($c->label) . '</label>';
                    $additional_fields_html .= '<input class="form-control" type="' . esc_html($c->type) . '" id="wa_' . esc_html($c->name) . '" name="' . esc_html($c->name) . '" placeholder="' . esc_html($c->label) . '" ' . ($required ? "required" : "") . ' maxlength="' . esc_html($c->max) . '"/>';
                }

            }
            
            $additional_fields_html .= '</div>';
        }

        // Add consent checkbox if required
        if($require_consent_checkbox) {
            $additional_fields_html .= '<div class="wiseagent-form-field form-group checkbox-field" style="display:flex;align-items:baseline;margin-top:30px;opacity:0.5;font-size:0.8em;">';
            $additional_fields_html .= '<input type="checkbox" id="wa_consent" name="wa_consent" placeholder="Consent" ' . ($mobile_required ? 'required' : '') . '/>';
            $additional_fields_html .= '<label for="wa_consent">' . $sms_consent_content . '</label>';
            $additional_fields_html .= '</div>';
        }

        //set action to the wp_ajax_nopriv_wa_user_form_submit action
        $action = admin_url('admin-ajax.php?action=wa_capture_form');
        $buttonText = $form->buttonText;
        if($buttonText == null || $buttonText == '') {
            $buttonText = 'Submit';
        }

        
        // Handle Captcha settings
        $captcha_settings = $this->wa_api->get_hCaptcha_settings();
        $re_captcha_settings = $this->wa_api->get_re_captcha_settings();
        $captcha_html = '';
        $recaptcha_html = '';
        if($captcha_settings['enabled'] == true) {
            $captcha_html = '<div class="h-captcha" data-sitekey="'. $captcha_settings['site_key'] . '"></div>';
            $captcha_html .= '<script src="https://js.hcaptcha.com/1/api.js" async defer></script>';
        }

        if($re_captcha_settings['enabled'] == true) {
            $recaptcha_html.= '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
            $data_callback = "onSubmitWAForm" . $form_id;
            // create the javascript 
            $recaptcha_html .= '<script>
            var formSubmitted' . $form_id . ' = false;
            function onSubmitWAForm' . $form_id . '(token) {
                var form = document.getElementById("wiseagent-form-' . $form_id . '");
                if(form.checkValidity()) {
                    if(formSubmitted' . $form_id . ' == false) {
                        formSubmitted' . $form_id . ' = true;
                        form.submit();
                    }
                }
                else
                    form.reportValidity();
            }
            </script>';
            $recaptcha_html .= '<input type="button" value="' . esc_html($buttonText) .  '" class="g-recaptcha" data-sitekey="'. $re_captcha_settings['site_key'] . '" data-callback="' . $data_callback . '" data-action="submit"/>';
        }

        wp_enqueue_script("wiseagent_capture_form_js");
        $html = '<div class="wiseagent-form-container">
                    <div class="wiseagent-form-body">
                        <form id="wiseagent-form-' . esc_html($form_id) . '" class="wiseagent-form" action="'.esc_html($action).'" method="post">
                            <input type="hidden" name="userFormID" value="' . esc_html($form_id) . '" />
                            <input type="hidden" name="Source" value="' . esc_html($source) . '" />
                            <input type="hidden" name="responsePage" value="' . esc_url($response_page) . '" />';
                            // Add nonce
                            $html .= wp_nonce_field("wa_capture_form", 'wa_capture_form_nonce', true, false);
                            $html .= $additional_fields_html;
                            $html .= $captcha_html;
                            $html .= '<div class="wiseagent-form-submit">';
                            $html .= $re_captcha_settings['enabled'] == false ? ('<input type="submit" value="' . esc_html($buttonText) . '" />') : $recaptcha_html;
                            $html .= '</div>
                        </form>
                    </div>
                </div>';
        return $html;

    }
}

$wiseagent = new wa_wp();