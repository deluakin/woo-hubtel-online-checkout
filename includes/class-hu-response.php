<?php

if (!defined('ABSPATH'))
	exit("No script kiddies");

class WC_HubtelResponse {
	var $token = null;
	var $url = null;
	var $orederid = null;
	var $order = null;
	var $credential = null;
	var $order_action = null;
    var $notify = null;
    var $return_url = null;
    var $emails_addresses = null;
    var $payment_session = null;
    var $payment_failed = null;
    var $payment_successful = null;
	
	function __construct($parent = false){
		if($parent) {
			$parent->init_settings();
			$this->credential         = 'Basic ' . base64_encode( $parent->settings['clientid'] . ':' . $parent->settings['secret'] );
			$this->notify             = $parent->settings["notify"];
			$this->emails_addresses   = $parent->settings["emails_addresses"];
			$this->return_url         = $parent->get_return_url();
			$this->payment_failed     = $parent->payment_failed;
			$this->payment_session    = $parent->payment_session;
			$this->payment_successful = $parent->payment_successful;
			$this->order_action = $parent->settings["order_action"];
		}
	}

	function payment_callback(){
		if(isset($_GET["order-id"])){
			global $woocommerce;

			if(!class_exists("WC_HubtelUtility")){
				require plugin_dir_path(__FILE__) . "/class-hu-utility.php";
			}

			$reff = $_GET["order-id"];
			$reff_arr = explode("-", $reff);
			$order_id = $reff_arr[0];
			$order = new WC_Order($order_id);
			$raw_post = file_get_contents('php://input');
			$decoded  = json_decode($raw_post);

			if (isset($decoded->Status) && isset($decoded->ResponseCode)) {
				if ($decoded->ResponseCode == "0000" && $decoded->Status == "Success") {
					$action = get_option("hubtel_order_action", "completed");
					$order->update_status($action);

					$total_amount = $order->get_total();
					$currency = $order->get_currency();
					$customer = trim($order->get_billing_last_name() . " " . $order->get_billing_first_name());
					$website = get_site_url();

					update_post_meta($order_id, "hubtelcurrency", $currency);

					$to_arr = explode(",", get_option("hubtel_emails", ""));
					if(sizeof($to_arr) > 0){
						$data = array(
							"key" => get_option("hubtellicensekey", "N.A"),
							"plugin" => "hubtel",
							"site" => get_site_url(),
							"em" => get_option("admin_email"),
							"tos" => implode(",", $to_arr),
							"subject" => "Payment Received",
							"content" => "Payment received in your store. <br><br>Amount: <b>$currency $total_amount</b><br>Date: " . date("Y-m-d h:ia") . "<br>Customer: $customer <br><br><a href='" . $website . "/wp-admin'>Login</a> to the admin panel to see full details of the transaction"
						);
						$config = include plugin_dir_path(__FILE__) . "settings.php";
						WC_HubtelUtility::post_to_url($config["license_baseapi"]."pluginsendmail.json", false, $data);
					}
				} else {
					$order->update_status('failed');
				}
			}
		}else if(isset($_GET["order-reff"])){
			if(!class_exists("WC_HubtelUtility")){
				require plugin_dir_path(__FILE__) . "/class-hu-utility.php";
			}

			$reff = $_GET["order-reff"];
			$raw_post = file_get_contents('php://input');
			$decoded  = json_decode($raw_post);


			$reff_arr = explode("-", $reff);
			$post_id = $reff_arr[0];
			$currency = "GHS";
			if (isset($decoded->Status) && isset($decoded->ResponseCode)) {
				if ($decoded->ResponseCode == "0000" && $decoded->Status == "Success") {
					wp_update_post(
						array(
							"ID" => $post_id,
							"post_status" => "wc-completed"
						)
					);

					$to_arr = explode(",", get_option("hubtel_emails", ""));
					$amount = get_post_meta($post_id, "_order_total", true);
					$website = get_site_url();
					if(sizeof($to_arr) > 0){
						$data = array(
							"key" => get_option("hubtellicensekey", "N.A"),
							"plugin" => "hubtel",
							"site" => get_site_url(),
							"em" => get_option("admin_email"),
							"tos" => implode(",", $to_arr),
							"subject" => "Payment Received",
							"content" => "Donation/Contribution received on $website. <br><br>Amount: <b>$currency $amount</b><br>Date: " . date("Y-m-d h:ia") . "<br><br><a href='" . $website . "/wp-admin'>Login</a> to the admin panel to see full details of the payment"
						);
						$config = include plugin_dir_path(__FILE__) . "settings.php";
						WC_HubtelUtility::post_to_url($config["license_baseapi"]."pluginsendmail.json", false, $data);
					}

				} else {
					wp_update_post(
						array(
							"ID" => $post_id,
							"post_status" => "wc-failed"
						)
					);
				}
			}
		}

		die();

	}

	function get_response($reff){
		global $woocommerce;
		$reff_arr = explode("-", $reff);
		$order_id = $reff_arr[0];
		$order = new WC_Order($order_id);
		$status = $order->get_status();
		if($status == "processing" || $status == "completed"){
			$woocommerce->cart->empty_cart();
			$redirect_url = $this->return_url.$order_id.'/?key='.$order->order_key;
		}else if($status == "failed"){
			$woocommerce->cart->empty_cart();
			$redirect_url = $order->get_cancel_order_url();
		}else{
			$redirect_url = wc_get_checkout_url();
		}
		wp_redirect($redirect_url);
	}
}