<?php

if (!defined('ABSPATH')) 
    exit("No script kiddies");

function hubtel_payment_button_init() {

    $plugin = plugin_basename(__FILE__);
    if (!class_exists('WC_HubtelSetup')) {
        require plugin_dir_path(__FILE__) . "/includes/class-hu-setup.php";
    }

	if (!class_exists('WC_HubtelResponse')) {
		require plugin_dir_path(__FILE__) . "/includes/class-hu-response.php";
	}

    $setup = new WC_HubtelSetup();
    $setup->__initialize($plugin);

    class WC_HubtelPaymentButton {
        var $config = null;

        public function __construct() {
            if (!class_exists('WC_HubtelSetup')) {
                require plugin_dir_path(__FILE__) . "/includes/class-hu-setup.php";
            }
            $setup = new WC_HubtelSetup();
            $this->config = $setup->read_config();

            add_shortcode('HubtelPaymentButton', array(&$this, 'HubtelPaymentButton'));
            add_action('wp_ajax_delhubtelbutton', array('WC_HubtelPaymentButton', 'DeleteButton'));
            add_action('wp_ajax_nopriv_hubtelinitpayment', array('WC_HubtelPaymentButton', 'InitHubtelPayment'));
            add_action('wp_ajax_hubtelinitpayment', array('WC_HubtelPaymentButton', 'InitHubtelPayment'));
	        add_action('wp_ajax_new-hubtel-button', array('WC_HubtelPaymentButton', 'NewHubtelButton'));
        }

        function NewHubtelButton(){
	        $config = include plugin_dir_path(__FILE__) . "includes/settings.php";
	        $site = "site=" . get_option("siteurl", "");
			$newbtn_api = str_replace("####", get_option("hubtellicensekey", "N.A"), $config["license_baseapi"]."newbutton?plugin=hubtel&key=####");
			$newbtn_api .= "&" . $site;

	        if (!class_exists('WC_HubtelUtility')) {
		        require plugin_dir_path(__FILE__) . "/includes/class-hu-utility.php";
	        }

	        $data = isset($_POST["submit"]) ? $_POST : false;
	        $response = WC_HubtelUtility::post_to_url($newbtn_api, false, $data);
	        if($response){
		        echo $response;
	        }
        	exit;
        }

        function InitHubtelPayment(){
	        $s_currency_arr = explode(",", get_option("hubtel_supported_currency", ""));
	        $code = isset($_POST["code"]) ? intval(filter_var($_POST["code"], FILTER_VALIDATE_INT)) : "";
            $name = isset($_POST["name"]) ? filter_var($_POST["name"], FILTER_SANITIZE_STRING) : "";
            $email = isset($_POST["email"]) ? sanitize_email(filter_var($_POST["email"], FILTER_SANITIZE_EMAIL)) : "";
            $mobile = isset($_POST["mobile"]) ? filter_var($_POST["mobile"], FILTER_SANITIZE_STRING) : "";
	        $currency = isset($_POST["currency"]) ? filter_var($_POST["currency"], FILTER_SANITIZE_STRING) : "GHS";
	        $redirect_url = isset($_POST["p"]) ? esc_url_raw(filter_var($_POST["p"], FILTER_SANITIZE_URL)) : get_site_url();
            $amt = isset($_POST["amount"]) ? floatval($_POST["amount"]) : 0;
            $customer = array(
            	            "name" => $name,
	                        "email" => $email,
	                        "mobile" => $mobile
                        );


            $url_arr = explode("?", $redirect_url);
	        $redirect_url = $url_arr[0];
            $endpoint = get_option("hubtel_payment_endpoint");
	        $callback = get_option("hubtel_callback_baseapi");
            $credential = 'Basic ' . base64_encode(get_option("hubtel_clientid", "") . ":" . get_option("hubtel_secret", ""));

	        if(!class_exists("WC_HubtelUtility"))
		        require plugin_dir_path(__FILE__) . "/includes/class-hu-utility.php";

	        $ex_rate = 1;
	        if($s_currency_arr && in_array($currency, $s_currency_arr) && isset($s_currency_arr[0]) && $s_currency_arr[0] <> $currency){
		        $data = array(
			        "key" => get_option("hubtellicensekey", "N.A"),
			        "plugin" => "hubtel",
			        "site" => get_site_url(),
			        "em" => get_option("admin_email"),
			        "curr" => $currency
		        );
		        $config = include plugin_dir_path(__FILE__) . "includes/settings.php";
		        $response = WC_HubtelUtility::post_to_url($config["license_baseapi"]."currencyrate.json", false, $data);
		        if($response) {
			        $ex_rate = json_decode( $response );
		        }
		        $amt = $amt / $ex_rate;
	        }


	        $postarr = array(
		        "post_author" => 1,
		        "post_type" => "hubtelbutton",
		        "post_status" => "wc-pending",
		        "post_date_gmt" => date("Y-m-d h:i:s"),
	        );
	        $post_id = wp_insert_post($postarr);

	        $logo_url = WC_HubtelUtility::get_store_logo();
	        $reff = WC_HubtelUtility::generate_payment_reff($post_id);

            #payment payload
	        $amt = number_format($amt, 2);

            $payload = array(
                "items" => array(array(
	                "name" => "Donation/Contribution",
	                "quantity" => 1,
	                "unitPrice" => $amt
                )),
                "totalAmount" => $amt,
                "description" => "Payment for a Donation/Contribution",
                "returnUrl" => $redirect_url . "?order-reff=" . $reff,
                "cancellationUrl" => $redirect_url . "?order-reff=" . $reff,
                "callbackUrl" => $callback . "&order-reff=" . $reff,
                "merchantBusinessLogoUrl" => $logo_url,
                "merchantAccountNumber" => get_option('hubtel_merchantnum', ''),
                "clientReference" => $reff,
            );

            #post payload
            $data = array(
	            "key" => get_option("hubtellicensekey", "N.A"),
	            "code" => $code,
	            "plugin" => "hubtel"
            );
	        $config = include plugin_dir_path(__FILE__) . "includes/settings.php";
	        WC_HubtelUtility::post_to_url($config["license_baseapi"]."clickbutton.json", false, $data);
	        $response = WC_HubtelUtility::post_to_url($endpoint, $credential, $payload);

	        if($response){
		        $response_decoded = json_decode($response);
		        if (isset($response_decoded->status) && $response_decoded->status == "Success") {
			        $response_data = $response_decoded->data;

			        update_post_meta($post_id, "HubtelToken", $response_data->checkoutId);
			        update_post_meta($post_id, "_donation_customer", $customer);
			        update_post_meta($post_id, "_order_total", $amt);
			        update_post_meta($post_id, "_hubteltotal", $amt);

			        echo $response_data->checkoutUrl;
		        }
	        }
	        exit;
        }

        function DeleteButton(){
            require_once plugin_dir_path(__FILE__) . "includes/class-hu-utility.php";
            $data = array(
                "key" => get_option("hubtellicensekey", "N.A"),
                "plugin" => "hubtel",
                "code" => $_GET["code"]
            );
	        $config = include plugin_dir_path(__FILE__) . "includes/settings.php";
            $response = WC_HubtelUtility::post_to_url($config["license_baseapi"]."delbutton.json", false, $data);
            echo "1";
            exit;
        }

        function VerifyPayment($reff){
	        $reff_arr = explode("-", $reff);
	        $post_id = $reff_arr[0];
	        $status = get_post_status($post_id);
	        if ($status == "wc-completed") {
		        echo "<div style='background-color: #46c9b6; color: #ffffff; text-align: center;padding: 20px;'>Your contribution was successfully recieved, thank you.</div><br>";
	        }else{
		        echo "<div style='background-color: #f2dede;text-align: center;padding: 20px;'>Payment request could not be completed.</div><br>";
	        }
        }

        function HubtelPaymentButton($atts){
	        $reff = isset($_GET["order-reff"]) ? $_GET["order-reff"] : "";
        	if(trim($reff) <> ""){
        		$this->VerifyPayment($reff);
	        }
            $code = "";
            $icon = plugin_dir_url(__FILE__) . "/assets/images/button.png";
            $atts = shortcode_atts(array(
                'code' => $code,
            ), $atts);

            $code = $atts["code"];
            $plugin = "hubtel";
            $key = get_option("hubtellicensekey", "N.A");

            require_once plugin_dir_path(__FILE__) . "includes/class-hu-utility.php";
            $data = array(
              "code" => $code,
              "plugin" => "hubtel",
              "key" => $key
            );
	        $config = include plugin_dir_path(__FILE__) . "includes/settings.php";
            $response = WC_HubtelUtility::post_to_url($config["license_baseapi"]."getbutton.json", false, $data);

            $card_logos = plugin_dir_url(__FILE__) . "/assets/images/logo.png";
            $api_url = admin_url('admin-ajax.php') . '?action=hubtelinitpayment';
            $response = str_replace("XXXXX", $card_logos, $response);
            $response = str_replace("#####", $api_url, $response);

            $str = <<<HTML
    $response
HTML;
            return $str;
        }
    }

    new WC_HubtelPaymentButton();
}