<?php

if (!defined('ABSPATH')) 
    exit("No script kiddies");

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))){
	add_action('admin_notices', 'ShowNoWooCommerceMsg');
    return;
}

function ShowNoWooCommerceMsg(){
	$screen = get_current_screen();
	if(isset($screen->id) && $screen->id == "plugins"){
		//echo "<div class='error notice is-dismissible'><p>Woocommerce has to be installed and active to use the <b>Hubtel WooCommerce Payment Gateway</b> plugin</p></div>";
	}
}
function hubtel_payment_init() {

    if (!class_exists('WC_Payment_Gateway')){
	    add_action('admin_notices', 'ShowNoWooCommerceMsg');
        return;
    }

    $plugin = plugin_basename(__FILE__);
    if (!class_exists('WC_HubtelSetup')) {
        require plugin_dir_path(__FILE__) . "/includes/class-hu-setup.php";
    }

	if (!class_exists('WC_HubtelResponse')) {
		require plugin_dir_path(__FILE__) . "/includes/class-hu-response.php";
	}

    $setup = new WC_HubtelSetup();
    $setup->__initialize($plugin);

    class WC_HubtelPayment extends WC_Payment_Gateway {
        var $config = null;
        var $setup = null;

        public function __construct() {
            $this->setup = new WC_HubtelSetup();
            $this->config = $this->setup->read_config();

            $this->id = $this->config["id"];
            $this->method_title = __($this->config["title"], 'woocommerce' );
            $this->icon = $this->config["icon"];
            $this->has_fields = false;

            $this->setup->init_form_fields($this);
            $this->init_settings();

            $this->title = $this->config['title'];
            $this->description = $this->config['description'];
	        $this->merchantnum = $this->config['merchantnum'];
            $this->clientid = $this->config['clientid'];
            $this->secret = $this->config['secret'];

            $this->settings["title"] = $this->config['title'];
            $this->settings["description"] = $this->config['description'];
	        $this->settings["merchantnum"] = $this->config['merchantnum'];
            $this->settings["clientid"] = $this->config['clientid'];
            $this->settings["secret"] = $this->config['secret'];

            $this->posturl = $this->config['payment_endpoint'];
            $this->payment_successful = $this->config['payment_successful'];
            $this->payment_failed = $this->config['payment_failed'];
            $this->payment_session = $this->config['payment_session'];
            $this->email_notification = $this->config['email_notification'];

            if (isset($_REQUEST["checkoutid"])) {
	            $reff = WC()->session->get('hubtel_wc_hash_key');
                if(!class_exists("WC_HubtelResponse")){
                    require plugin_dir_path(__FILE__) . "/includes/class-hu-response.php";
                }

                $resp_obj = new WC_HubtelResponse($this);
                $resp_obj->get_response($reff);
            }

            if (isset($_REQUEST["hubtel"])) {
                wc_add_notice($_REQUEST["hubtel"], "error");
            }

            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            }
        }

        public function admin_options() {
            #Generate the HTML For the settings form.
            echo '<h3>' . __('Hubtel Payment Gateway', 'hubtelpayment') . '</h3>';
            echo '<p>' . __('Hubtel Payment is most popular payment gateway for online shopping in Ghana.') . '</p>';
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }

        function process_admin_options(){
            parent::process_admin_options();
            $settings = $this->get_post_data();
            $this->config["title"] = $settings["woocommerce_" . $this->id . "_title"];
            $this->config["description"] = $settings["woocommerce_" . $this->id . "_description"];
            $this->config["clientid"] = $settings["woocommerce_" . $this->id . "_clientid"];
            $this->config["secret"] = $settings["woocommerce_" . $this->id . "_secret"];
	        $this->config["merchantnum"] = $settings["woocommerce_" . $this->id . "_merchantnum"];
            $this->config["enabled"] = $settings["woocommerce_" . $this->id . "_enabled"];
            $this->setup->write_config($this->config);
        }

        protected function get_payment_args($order) {
            global $woocommerce;

            $redirect_url = wc_get_checkout_url();
	        $reff = WC_HubtelUtility::generate_payment_reff($order->get_id());
            WC()->session->set('hubtel_wc_hash_key', $reff);
            $items = $woocommerce->cart->get_cart();
            $hubtel_items = array();
			$s_currency_arr = explode(",", $this->config['supported_currency']);
			$currency = $order->get_currency();
            foreach ($items as $item) {
	            $_product =  wc_get_product( $item['data']->get_id());
	            $price = get_post_meta($item['product_id'] , '_price', true);

	            $hubtel_items[] = array(
                    "name" => $_product->get_title(),
                    "quantity" => $item["quantity"],
                    "unitPrice" => $price / (($item["quantity"] == 0) ? 1 : $item["quantity"])
                );
            }
			

            $order_shipping_total = $order->get_total_shipping();
            if($order_shipping_total > 0){
                $hubtel_items[] = array(
                    "name" => "Shipping Fee",
                    "quantity" => "1",
                    "unitPrice" => $order_shipping_total
                );
            }

	        $ex_rate = 1;
	        if(in_array($currency, $s_currency_arr) && $s_currency_arr[0] <> $currency){
		        $data = array(
			        "key" => get_option("hubtellicensekey", "N.A"),
			        "plugin" => "hubtel",
			        "site" => get_site_url(),
			        "em" => get_option("admin_email"),
			        "curr" => strtolower($currency)
		        );
		        $config = include plugin_dir_path(__FILE__) . "includes/settings.php";
		        $response = WC_HubtelUtility::post_to_url($config["license_baseapi"] . "currencyrate.json", false, $data);
		        if($response) {
			        $ex_rate = json_decode( $response );
		        }
		        foreach ($hubtel_items as $key => $item){
			        $hubtel_items[$key]["unitPrice"] = number_format($hubtel_items[$key]["unitPrice"] / $ex_rate, 2);
		        }
	        }


	        $logo_url = WC_HubtelUtility::get_store_logo();

            $hubtelpayment_args = array(
	            "items" => $hubtel_items,
	            "totalAmount" => $order->get_total(),
	            "description" => "Payment of GHs" . $order->get_total() . " for item(s) bought on " . get_bloginfo("name"),
	            "returnUrl" => $redirect_url,
	            "cancellationUrl" => $redirect_url,
	            "callbackUrl" => $this->config['callback_baseapi'] . "&order-id=" . $reff,
	            "merchantBusinessLogoUrl" => $logo_url,
	            "merchantAccountNumber" => $this->settings['merchantnum'],
	            "clientReference" => $reff,
            );
	        $hubtelpayment_args["totalAmount"] = number_format($hubtelpayment_args["totalAmount"] / $ex_rate, 2);
	        update_post_meta($order->get_id(), "_hubteltotal", $hubtelpayment_args["totalAmount"]);

            apply_filters('woocommerce_hubtelpayment_args', $hubtelpayment_args, $order);
            return $hubtelpayment_args;
        }

        function process_payment($order_id) {
            if(!class_exists("WC_HubtelUtility"))
                require plugin_dir_path(__FILE__) . "/includes/class-hu-utility.php";
            $url = "";
            $this->init_settings();
            $order = new WC_Order($order_id);
            $credential =  'Basic ' . base64_encode($this->settings['clientid'] . ':' . $this->settings['secret']);
            WC()->session->set('hubtel_wc_order_id', $order_id);
            $response = WC_HubtelUtility::post_to_url($this->posturl, $credential, $this->get_payment_args($order));
            if($response){
                $response_decoded = json_decode($response);
                if (isset($response_decoded->status) && $response_decoded->status == "Success") {
	                $response_data = $response_decoded->data;
                    update_post_meta($order_id, "HubtelToken", $response_data->checkoutId);
                    $url = $response_data->checkoutUrl;
                } else {
                    global $woocommerce;
                    $url = wc_get_checkout_url();
                    $err_msg = isset($response_decoded->response_text) ? $response_decoded->response_text : "Request could not be completed";
                    if (strstr($url, "?")) {
                        $url .= "&hubtel=" . $err_msg;
                    } else {
                        $url .= "?hubtel=" . $err_msg;
                    }
                }
            }else{
                $url .= "?hubtel=Request could not be completed. Please try again later.";
            }

            return array(
                'result' => 'success',
                'redirect' => $url
            );
        }
    }
}

add_action('wp_ajax_nopriv_hubtelpaymentcallback', array("WC_HubtelResponse", "payment_callback"));
add_action('wp_ajax_hubtelpaymentcallback', array("WC_HubtelResponse", "payment_callback"));