<?php
defined('ABSPATH') or die();
class WA_API {
    /**
     * @var string
     * The base URL for the Wise Agent API
     */
    public $api_base_url = 'https://sync.thewiseagent.com';

    /**
     * @var string
     * The base URL for the Wise Agent website
     */
    public $wa_base_url = "https://thewiseagent.com";

    /**
     * @var int
     * The timestamp of the last time the forms cache was updated
     */
    public $last_cache = null;

    /**
     * @var string
     * OAuth2 client ID
     */
    private $client_id = '16a6726f-dbb6-46ed-af75-ed39cbf279dd';

    /**
     * @var string
     * OAuth2 access token
     */
    private $access_token = '';

    /**
     * @var string
     * OAuth2 refresh token
     */
    private $refresh_token = '';

    /**
     * @var string
     * OAuth2 token expiration date
     */
    private $token_expires = '';

    /**
     * @var bool
     * Whether or not the user is logged in
     */
    private $logged_in = false;

    /**
     * @var array
     * The Wise Agent user object
     */
    private $user = null;

    /**
     * @var array
     * Cached Wise Agent forms
     */
    private $cached_forms = [];

    /**
     * @var string
     * The OAuth2 code verifier
     */
    private $code_verifier = '';

    /**
     * @var array
     * The last error returned by the API, if any
     * 
     * Standard format is ['error' => 'error message', 'error_description' => 'error description']
     */
    private $last_error = null;

    /**
     * @var string
     * The hCaptcha secret key
     */
    private $hCaptcha_secret = '';

    /**
     * @var string
     * The hCaptcha site key
     */
    private $hCaptcha_site_key = '';

    /**
     * @var bool
     * Whether or not hCaptcha is enabled
     */
    private $hCaptcha_enabled = false;

    /**
     * @var string
     * The hCaptcha secret key
     */
    private $reCaptcha_secret = '';

    /**
     * @var string
     * The hCaptcha site key
     */
    private $reCaptcha_site_key = '';

    /**
     * @var bool
     * Whether or not hCaptcha is enabled
     */
    private $reCaptcha_enabled = false;

    /**
     * Construct the Wise Agent API object
     * @param array $wa_options   The wiseagent_options array from the database
     */
    public function __construct($wa_options, $h_captcha_options, $re_captcha_options)
    {
        $this->set_wa_opts($wa_options, $h_captcha_options, $re_captcha_options);
        $this->logged_in = false;
    }

    /**
     * Format the last error as HTML
     * @return string
     */
    public function get_last_error_html() {
        if($this->last_error == null) {
            return '';
        }
        $html = '<div class="notice notice-error"><p><strong>Wise Agent Error:</strong> ' . $this->last_error['error'] . '</p><p>' . $this->last_error['error_description'] . '</p></div>';
        return $html;
    }

    /**
     * Get the last error as an array
     * @return array|null
     */
    public function get_last_error_raw() {
        return $this->last_error;
    }

    /**
     * Get Wise Agent user from the API or from the cache
     * @return mixed|null
     * 
     */
    public function wiseagent_get_user() {
        if($this->logged_in && $this->user != null) {
            return $this->user;
        }
        $result = $this->api_get('/http/webconnect.asp?requestType=user');
        if($result != null) {
            $this->user = $result;
            $this->logged_in = true;
        }
        return $result;
    }

    public function get_hCaptcha_settings () {
        return ['site_key' => $this->hCaptcha_site_key, 'secret' => $this->hCaptcha_secret, 'enabled' => $this->hCaptcha_enabled];
    }

    public function get_re_captcha_settings() {
        return ['site_key' => $this->reCaptcha_site_key, 'secret' => $this->reCaptcha_secret, 'enabled' => $this->reCaptcha_enabled];
    }

    /**
     * Get a login URL for Wise Agent
     * @return string
     */
    public function get_sso_url() {
        $state = md5(uniqid(rand(), true));
        $code_challenge = $this->generate_code_challenge();
        $url = $this->api_base_url . '/WiseAuth/auth?client_id=' . $this->client_id . '&response_type=code&state=' .$state . '&redirect_uri=' . urlencode(admin_url('admin-post.php?action=wa_oauth2')) . '&scope=profile%20contacts%20marketing&code_challenge=' . $code_challenge . '&code_challenge_method=S256';
        return $url;
    }
    

    /**
     * Get a list of capture forms from Wise Agent, either from the cache or from the API
     * @return mixed|null
     */
    public function get_wa_capture_forms() {
        if($this->cached_forms != null && count($this->cached_forms) > 0) {
            return $this->cached_forms;
        }
        return $this->api_get('/http/webconnect.asp?requestType=captureForms');
    }

    /**
     * Get a single capture form from Wise Agent, either from the cache or from the API
     * @param $form_id
     * @return mixed|null
     */
    public function get_wa_capture_form($form_id) {
        if($this->cached_forms != null && count($this->cached_forms) > 0) {
            foreach($this->cached_forms as $form) {
                if($form->userFormID == $form_id) {
                    return $form;
                }
            }
        }
        if(count($this->cached_forms) == 0) {
            $this->update_forms_cache();
        }
        return $this->api_get('/http/webconnect.asp?requestType=captureForm&formid=' . $form_id);
    }


    /**
     * Check if the Wise Agent user is authenticated.
     * @return bool
     */
    public function is_logged_in() {
        if($this->access_token == '' || $this->refresh_token == '' || $this->token_expires == '') {
            return false;
        }
        if($this->logged_in) {
            return true;
        } else {
            $usr = $this->wiseagent_get_user();
            if($usr != null) {
                $this->logged_in = true;
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * Submit form data to Wise Agent API and return the response
     * @param $data
     * @return mixed|null
     */
    public function send_form_data($data) {
        $this_website = get_site_url();
        $comma_delimited_fields = '';
        $ignore_fields = '_wp_http_referer,userFormID,wa_capture_form_nonce,responsePage';

        # Look through $data and find additional fields that are not in the ignore list
        foreach($data as $key => $value) {
            if(strpos($ignore_fields, $key) === false) {
                $comma_delimited_fields .= $key . ',';
            }
        }
        $comma_delimited_fields = rtrim($comma_delimited_fields, ',');
        
        $path = $this->api_base_url . '/http/webconnect.asp?requestType=webcontact&CommaDelimitedFormFields=' . urlencode($comma_delimited_fields) . '&wa_website=' . urlencode($this_website) . "&" . http_build_query($data);
        return $this->api_post_form($path);
    }

    /**
     * Generate a code challenge for the OAuth2 flow
     * @return string
     */
    public function generate_code_challenge() {
        $code_verifier = $this->base64url_encode(random_bytes(32));
        $code_challenge = $this->base64url_encode(hash('sha256', $code_verifier, true));

        // Store the code verifier in the options
        $this->code_verifier = $code_verifier;
        $this->save_opts();

        return $code_challenge;
    }

    /**
     * Exchange an authorization code for an access token from WiseAuth
     * @param $authCode
     * @param $code_verifier
     * @return bool
     */
    public function exchange_auth_code($authCode, $code_verifier) {
        $url = $this->api_base_url . '/WiseAuth/token';
        $args = array(
            'body' => array(
                'grant_type' => 'authorization_code',
                'code' => $authCode,
                'code_verifier' => $code_verifier,
                'client_id' => $this->client_id
            )
        );
        $response = wp_remote_post($url, $args);
        if (is_wp_error($response)) {
            $this->last_error = ['error' => $response->get_error_message(), 'error_description' => 'Something went wrong. Please try again.'];
            return false;
        }

        if($response['response']['code'] != 200) {
            error_log('exchange_auth_code: ' . $response['response']['code'] . ' ' . $response['response']['message'], 3, WA_LOG_FILE);

            $err = json_decode($response['body'], true);

            if(isset($err['error'])) {
                $this->last_error = array('error' => $err['error'], 'error_description' => $err['error_description'] ? $err['error_description'] : '');
                error_log("Setting error array to: " . print_r($this->last_error, true) . "\n",3,WA_LOG_FILE);
            } else {
                $this->last_error = array('error' => $response['response']['code'], 'error_description' => $response['response']['message'] ? $response['response']['message'] : '');
            }
            return false;
        }
        $response = json_decode($response['body']);
        $this->access_token = $response->access_token;
        $this->refresh_token = $response->refresh_token;
        $this->token_expires = $response->expires_at;
        $this->save_opts();


        $this->last_error = null;
        // On first connect, update the forms cache
        $this->update_forms_cache();

        return true;
    }


    /**
     * Revokes the current access token, effectively logging the user out of Wise Agent
     * @return bool
     */
    public function revoke_token() {
        $url = $this->api_base_url . '/WiseAuth/revoke';
        $args = [
            'body' => [
                'token' => $this->access_token,
                'token_type_hint' => 'access_token'
            ],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ];
        $response = wp_remote_post($url,$args);
        if (is_wp_error($response)) {
            error_log('revoke_token: ' . $response->get_error_message(), 3, WA_LOG_FILE);
            $this->last_error = ['error' => $response->get_error_message(), 'error_description' => 'Something went wrong. Please try again.'];
            return false;
        }
        if (wp_remote_retrieve_response_code($response) != 200) {
            error_log('revoke_token ERROR: ' . wp_remote_retrieve_response_message($response), 3, WA_LOG_FILE);

            $err = json_decode($response['body'], true);

            if(isset($err['error'])) {
                $this->last_error = array('error' => $err['error'], 'error_description' => $err['error_description'] ? $err['error_description'] : '');
                error_log("Setting error array to: " . print_r($this->last_error, true) . "\n",3,WA_LOG_FILE);
            } else {
                $this->last_error = array('error' => $response['response']['code'], 'error_description' => $response['response']['message'] ? $response['response']['message'] : '');
            }
            return false;
        }
        $this->access_token = '';
        $this->refresh_token = '';
        $this->token_expires = '';
        $this->cached_forms = [];
        $this->last_cache = null;
        $this->last_error = null;
        $this->save_opts();

        return true;
    }


    /**
     * Update local cache of forms from Wise Agent API
     * @return void
     */
    public function update_forms_cache() {
        // if we have an access token, get the forms from the API
        if (strlen($this->access_token) > 0) {
            $forms = $this->api_get('/http/webconnect.asp?requestType=captureForms');
            if ($forms != null) {
                $this->cached_forms = $forms;
                $this->save_opts(true);
            }
        }
    }

    /**
     * Get the saved code_verifier for use in the OAuth2 flow
     * @return string
     */
    public function get_code_verifier () {
        return $this->code_verifier;
    }

    public function validate_h_captcha ($hCaptcha_response) {
        $data = [
            'secret' => $this->hCaptcha_secret,
            'response' => $hCaptcha_response
        ];

        $args = [
            'body' => $data,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ];

        $response = wp_remote_post('https://hcaptcha.com/siteverify',$args);

        if ($response['response']['code'] != 200) {
            error_log('validate_h_captcha: ' . $response['response']['message'] . "\n", 3, WA_LOG_FILE);
            return false;
        }
        if (is_wp_error($response)) {
            error_log('validate_h_captcha: ' . $response->get_error_message(), 3, WA_LOG_FILE);
            $this->last_error = ['error' => $response->get_error_message(), 'error_description' => 'Something went wrong. Please try again.'];
            return false;
        }
        $serialized_response = json_decode($response['body']);
        if($serialized_response->success) {
            return true;
        } else {
            return false;
        }
    }

    public function validate_re_captcha ($re_captcha_response) {
        $data = [
            'secret' => $this->reCaptcha_secret,
            'response' => $re_captcha_response
        ];
        
        $args = [
            'body' => $data,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ];

        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify',$args);

        if ($response['response']['code'] != 200) {
            error_log('validate_re_captcha: ' . $response['response']['message'] . "\n", 3, WA_LOG_FILE);
            return false;
        }
        if (is_wp_error($response)) {
            error_log('validate_re_captcha: ' . $response->get_error_message(), 3, WA_LOG_FILE);
            $this->last_error = ['error' => $response->get_error_message(), 'error_description' => 'Something went wrong. Please try again.'];
            return false;
        }
        $serialized_response = json_decode($response['body']);
        // log response
        error_log('validate_re_captcha: ' . print_r($serialized_response, true) . "\n", 3, WA_LOG_FILE);
        if($serialized_response->success) {
            // check action matches
            if($serialized_response->action != 'submit') {
                return false;
            }
            // check hostname matches
            if($serialized_response->hostname != $_SERVER['SERVER_NAME']) {
                return false;
            }
            // check score
            if($serialized_response->score < 0.4) {
                return false;
            }
            return true;
        } else {
            return false;
        }
    }

    // Private methods

    /**
     * Generalized method for making a GET request to the Wise Agent API
     * @param string $path  The relative path to the API endpoint
     * @param bool $include_client  Whether or not to include the X-Client-Id header
     * @return mixed|null
     */
    private function api_get($path, $include_client = false){

        if($this->is_token_expired()){
            $this->refresh_access_token();
        }
        //Add access_token as Bearer token to header
        $headers = array(
            'Authorization' => 'Bearer ' . $this->access_token,
        );
        if($include_client){
            $headers['X-Client-Id'] = $this->client_id;
        }
        $url = $this->api_base_url . $path;
        $response = wp_remote_get($url, array('headers' => $headers));
        // Check for error
        if (is_wp_error($response)) {
            error_log('Something went wrong: ' . $response->get_error_message(),3,WA_LOG_FILE);
            $this->last_error = ['error' => $response->get_error_message(), 'error_description' => 'Something went wrong. Please try again.'];
            return;
        }
        if ($response['response']['code'] != 200) {
            error_log("Error while requesting $url: " . $response['response']['message'] . "\n",3,WA_LOG_FILE);
            error_log("Response body: " . $response['body'] . "\n",3,WA_LOG_FILE);
            $err = json_decode($response['body'], true);

            if(isset($err['error'])) {
                $this->last_error = array('error' => $err['error'], 'error_description' => $err['error_description'] ? $err['error_description'] : '');
                error_log("Setting error array to: " . print_r($this->last_error, true) . "\n",3,WA_LOG_FILE);
            } else {
                $this->last_error = array('error' => $response['response']['code'], 'error_description' => $response['response']['message'] ? $response['response']['message'] : '');
            }
            return;
        }
        $data = json_decode($response['body']);
        $this->last_error = null;
        return $data;
    }

    /**
     * Generalized method for making a POST request to the Wise Agent API
     * @param $url  The full URL to the API endpoint
     * @return mixed|null
     */
    private function api_post_form($url) {
        if($this->is_token_expired()){
            $this->refresh_access_token();
        }
        //Add access_token as Bearer token to header
        $headers = array(
            'Authorization' => 'Bearer ' . $this->access_token,
            'Content-Type' => 'application/x-www-form-urlencoded'
        );
        $response = wp_remote_post($url, array('headers' => $headers));
        // Check for error
        if (is_wp_error($response)) {
            error_log('Something went wrong: ' . $response->get_error_message(),3,WA_LOG_FILE);
            $this->last_error = ['error' => $response->get_error_message(), 'error_description' => 'Something went wrong. Please try again.'];
            return;
        }
        if ($response['response']['code'] != 200) {
            error_log("Error while requesting $url: " . $response['response']['message'] . "\n",3,WA_LOG_FILE);
            // Log body for debugging
            error_log("Error while requesting $url: " . $response['body'] . "\n",3,WA_LOG_FILE);
            $err = json_decode($response['body'], true);

            if(isset($err['error'])) {
                $this->last_error = array('error' => $err['error'], 'error_description' => $err['error_description'] ? $err['error_description'] : '');
                error_log("Setting error array to: " . print_r($this->last_error, true) . "\n",3,WA_LOG_FILE);
            } else {
                $this->last_error = array('error' => $response['response']['code'], 'error_description' => $response['response']['message'] ? $response['response']['message'] : '');
            }
            return;
        }
        $this->last_error = null;
    }

    /**
     * Check if the current access token has expired
     * @return bool
     */
    private function is_token_expired() {
        $dtexpired = new DateTime($this->token_expires);
        return $dtexpired->getTimestamp() < time();
    }

    /**
     * Refresh the access token using the refresh token
     * @return void
     */
    private function refresh_access_token(){
        $path = '/WiseAuth/token';
        $url = $this->api_base_url . $path;
        $args = array(
            'body' => array(
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->refresh_token,
                'client_id' => $this->client_id
            )
        );
        $response = wp_remote_post($url, $args);
        if (is_wp_error($response)) {
            $this->last_error = ['error' => $response->get_error_message(), 'error_description' => 'Something went wrong. Please try again.'];
            error_log('refresh_access_token: ' . $response->get_error_message(), 3, WA_LOG_FILE);
            return;
        }
        if($response['response']['code'] != 200){
            error_log('refresh_access_token: ' . $response['response']['message'], 3, WA_LOG_FILE);

            $err = json_decode($response['body'], true);

            if(isset($err['error'])) {
                $this->last_error = array('error' => $err['error'], 'error_description' => $err['error_description'] ? $err['error_description'] : '');
                error_log("Setting error array to: " . print_r($this->last_error, true) . "\n",3,WA_LOG_FILE);
            } else {
                $this->last_error = array('error' => $response['response']['code'], 'error_description' => $response['response']['message'] ? $response['response']['message'] : '');
            }
            return;
        }
        $token_resp = json_decode($response['body']);

        // Update wiseagent options

        $options = get_option('wiseagent_options');
        $options['access_token'] = $token_resp->access_token;
        $options['refresh_token'] = $token_resp->refresh_token;
        $options['token_expires'] = $token_resp->expires_at;
        update_option('wiseagent_options', $options);

        $h_captcha_options = get_option('wiseagent_hcaptcha_options');
        $re_captcha_options = get_option('wiseagent_recaptcha_options');

        // Update local variables
        $this->set_wa_opts($options, $h_captcha_options, $re_captcha_options);

        $this->last_error = null;
    }

    /**
     * Set the local variables for the Wise Agent options
     * @param $wa_options
     * @return void
     */
    private function set_wa_opts($wa_options, $h_captcha_options, $re_captcha_options) {
        $this->access_token = isset($wa_options['access_token']) ? $wa_options['access_token'] : '';
        $this->refresh_token = isset($wa_options['refresh_token']) ? $wa_options['refresh_token'] : '';
        $this->token_expires = isset($wa_options['token_expires']) ? $wa_options['token_expires'] : '';
        $this->last_cache = isset($wa_options['last_cache']) ? $wa_options['last_cache'] : null;
        $this->code_verifier = isset($wa_options['code_verifier']) ? $wa_options['code_verifier'] : '';
        $this->hCaptcha_secret = isset($h_captcha_options['hCaptcha_secret']) ? $h_captcha_options['hCaptcha_secret'] : '';
        $this->hCaptcha_site_key = isset($h_captcha_options['hCaptcha_site_key']) ? $h_captcha_options['hCaptcha_site_key'] : '';
        $this->hCaptcha_enabled = isset($h_captcha_options['hCaptcha_enabled']) ? $h_captcha_options['hCaptcha_enabled'] : false;
        $this->reCaptcha_enabled = isset($re_captcha_options['reCaptcha_enabled']) ? $re_captcha_options['reCaptcha_enabled'] : false;
        $this->reCaptcha_secret = isset($re_captcha_options['reCaptcha_secret']) ? $re_captcha_options['reCaptcha_secret'] : '';
        $this->reCaptcha_site_key = isset($re_captcha_options['reCaptcha_site_key']) ? $re_captcha_options['reCaptcha_site_key'] : '';

        if(isset($wa_options['cached_forms'])) {
            $this->cached_forms = json_decode($wa_options['cached_forms']);
        }
    }

    /**
     * Save the Wise Agent options to the database
     * @param bool $updateCacheDate Whether to update the last_cache date
     * @return void
     */
    private function save_opts($updateCacheDate = false) {
        $options = get_option('wiseagent_options');
        $h_captcha_options = get_option('wiseagent_hcaptcha_options');
        $re_captcha_options = get_option('wiseagent_recaptcha_options');
        $options['access_token'] = $this->access_token;
        $options['refresh_token'] = $this->refresh_token;
        $options['token_expires'] = $this->token_expires;
        $options['code_verifier'] = $this->code_verifier;
        $options['cached_forms'] = json_encode($this->cached_forms);
        $h_captcha_options['hCaptcha_secret'] = $this->hCaptcha_secret;
        $h_captcha_options['hCaptcha_site_key'] = $this->hCaptcha_site_key;
        $h_captcha_options['hCaptcha_enabled'] = $this->hCaptcha_enabled;
        $re_captcha_options['reCaptcha_secret'] = $this->reCaptcha_secret;
        $re_captcha_options['reCaptcha_site_key'] = $this->reCaptcha_site_key;
        $re_captcha_options['reCaptcha_enabled'] = $this->reCaptcha_enabled;

        if($updateCacheDate) {
            $options['last_cache'] = time();
            $this->last_cache = $options['last_cache'];
        }
        update_option('wiseagent_options', $options);
        update_option('wiseagent_hcaptcha_options', $h_captcha_options);
        update_option('wiseagent_recaptcha_options', $re_captcha_options);
    }


    /**
     * Base64 URL encode a string for use in a URL
     * @param $data
     * @return string
     */
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

}