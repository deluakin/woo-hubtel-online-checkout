<?php

if (!defined('ABSPATH'))
	exit("No script kiddies");

$plugin = plugin_basename(__FILE__);
if (!class_exists('WC_HubtelSetup')) {
    require plugin_dir_path(__FILE__) . "../includes/class-hu-setup.php";
}
$setup = new WC_HubtelSetup();
$config = $setup->read_config();

if(isset($_POST["woocommerce_wc_hubtelpayment_save"]) && $_POST["woocommerce_wc_hubtelpayment_save"] == "Save Changes"){
    $nonce = isset($_POST["_wpnonce"]) ? $_POST["_wpnonce"] : "";
    if(!wp_verify_nonce($nonce) || !current_user_can("administrator")){
	    exit("unauthorized");
    }
    $title = isset($_POST["woocommerce_wc_hubtelpayment_title"]) ? wp_filter_nohtml_kses($_POST["woocommerce_wc_hubtelpayment_title"]) : "";
    $description = isset($_POST["woocommerce_wc_hubtelpayment_description"]) ? wp_filter_nohtml_kses($_POST["woocommerce_wc_hubtelpayment_description"]) : "";
	$merchantnum = isset($_POST["woocommerce_wc_hubtelpayment_merchantnum"]) ? $_POST["woocommerce_wc_hubtelpayment_merchantnum"] : "";
    $clientid = isset($_POST["woocommerce_wc_hubtelpayment_clientid"]) ? $_POST["woocommerce_wc_hubtelpayment_clientid"] : "";
    $secret = isset($_POST["woocommerce_wc_hubtelpayment_secret"]) ? $_POST["woocommerce_wc_hubtelpayment_secret"] : "";
	$logo = isset($_POST["woocommerce_wc_hubtelpayment_logo"]) ? $_POST["woocommerce_wc_hubtelpayment_logo"] : "";
    $enabled = isset($_POST["woocommerce_wc_hubtelpayment_enabled"]) ? $_POST["woocommerce_wc_hubtelpayment_enabled"] : "0";
	$emails = isset($_POST["woocommerce_wc_hubtelpayment_emails"]) ? $_POST["woocommerce_wc_hubtelpayment_emails"] : "";
	$cconverter = isset($_POST["woocommerce_wc_hubtelpayment_cconverter"]) ? $_POST["woocommerce_wc_hubtelpayment_cconverter"] : "0";
	$order_action = isset($_POST["woocommerce_wc_hubtelpayment_order_action"]) ? $_POST["woocommerce_wc_hubtelpayment_order_action"] : "0";

	$emails_arr = explode(",", $emails);
	foreach ($emails_arr as $key => $em){
		$emails_arr[$key] = sanitize_email($em);
    }
	$emails = implode(",", $emails_arr);

    $config['title'] = $title;
    $config['description'] = $description;
	$config['merchantnum'] = $merchantnum;
    $config['clientid'] = $clientid;
    $config['secret'] = $secret;
	$config['logo'] = $logo;
    $config['enabled'] = $enabled;
	$config['emails'] = $emails;
	$config['cconverter'] = $cconverter;
	$config['order_action'] = $order_action;

    $setup->write_config($config);

}else{
    $title = wp_filter_nohtml_kses($config['title']);
    $description = wp_filter_nohtml_kses($config['description']);
	$merchantnum = $config['merchantnum'];
    $clientid = $config['clientid'];
    $secret = $config['secret'];
	$logo = $config['logo'];
    $enabled = $config['enabled'];
	$emails = $config['emails'];
	$cconverter = $config['cconverter'];
	$order_action = $config['order_action'];
}

?>

<?php if (class_exists('WC_Payment_Gateway') && class_exists('WC_HubtelPayment')): ?>
<div class="wrap">
	<?php require plugin_dir_path(__FILE__) . "/buynow-header.php"; ?>
    <h3>Hubtel Payment Gateway</h3>
    <p>Hubtel Payment is most popular payment gateway for online shopping in Ghana.</p>
    <?php echo (isset($_POST["woocommerce_wc_hubtelpayment_save"]) && $_POST["woocommerce_wc_hubtelpayment_save"] == "Save Changes") ? '<div class="notice notice-success is-dismissible"><p>Settings saved successfully</p></div>' : ""; ?>
    <form method="post">
        <table class="form-table">
            <?php
            class WC_HubtelPaymentSettings extends WC_Payment_Gateway{
                public function __construct() {
                    $plugin = plugin_basename(__FILE__);
                    if (!class_exists('WC_HubtelSetup')) {
                        require plugin_dir_path(__FILE__) . "../includes/class-hu-setup.php";
                    }
                    $setup = new WC_HubtelSetup();
                    $config = $setup->read_config();


                    $this->id = $config["id"];
                    $this->method_title = __($config["title"], 'woocommerce' );
                    $this->icon = $config["icon"];
                    $this->has_fields = false;

                    $this->form_fields = array(
                        'enabled' => array(
                            'title' => __('Enable/Disable', $config["id"]),
                            'type' => 'checkbox',
                            'label' => __('Add to WooCommerce Checkout Page', $config["id"]),
                            'default' => "no"),
                        'title' => array(
                            'title' => __('Title', $config["id"]),
                            'type' => 'text',
                            'description' => __('This controls the title which the user sees during checkout.', $config["id"]),
                            'default' => __($config["title"], $config["id"])),
                        'description' => array(
                            'title' => __('Description', $config["id"]),
                            'type' => 'textarea',
                            'description' => __('This controls the description which the user sees during checkout.', $config["id"]),
                            'default' => __($config["description"], $config["id"])),
                        'merchantnum' => array(
	                        'title' => __('M.A Number', $config["id"]),
	                        'type' => 'text',
	                        'description' => __('Your hubtel merchant account number', $config["id"]),
	                        'default' => __($config["merchantnum"], $config["id"])),
                        'clientid' => array(
                            'title' => __('Client Id', $config["id"]),
                            'type' => 'text',
                            'description' => __('', $config["id"]),
                            'default' => __($config["clientid"], $config["id"])),
                        'secret' => array(
                            'title' => __('Secret', $config["id"]),
                            'type' => 'text',
                            'description' => __('', $config["id"]),
                            'default' => __($config["secret"], $config["id"])),
                        'logo' => array(
	                        'title' => __('Website Logo Url', $config["id"]),
	                        'type' => 'text',
	                        'description' => __('The dimensions should ideally be a 100px X 100px', $config["id"]),
	                        'default' => __($config["logo"], $config["id"])),
                        'order_action' => array(
	                        'title' => __('Order Status <br/><small style="font-weight: 400;">After payment has been completed, change order status to the selected status</small>', $config["id"]),
	                        'type' => 'select',
	                        'options' => array(
		                        'completed' => 'Completed',
	                            'processing' => 'Processing'
                            ),
	                        'description' => __('', $config["id"]),
	                        'default' => __($config["order_action"], $config["id"])),
                        'cconverter' => array(
	                        'title' => __('Currency Conversion<br><small style="font-weight: 400;">Since Hubtel Payment supports only GHS, enable this option if your store/site currency is <b>USD, GBP, EUR or NGN</b></small>', $config["id"]),
	                        'type' => 'checkbox',
	                        'label' => __('Automatically convert to GHS before sending to hubtel', $config["id"]),
	                        'default' => "no"),
                        'emails' => array(
	                        'title' => __('Notification Email Addresses', $config["id"]),
	                        'type' => 'textarea',
	                        'description' => __('Send a mail to the email addresses provided above whenever payment is received. <br>Separate each email address with a comma.', $config["id"]),
	                        'default' => __($config["emails"], $config["id"])),
                        'save' => array(
                            'title' => __('', $config["id"]),
                            'class' => 'button-primary',
                            'type' => 'submit',
                            'description' => __('', $config["id"]),
                            'default' => __('Save Changes', $config["id"])),
                    );
                    $this->init_settings();

	                $this->settings["enabled"] = ($config['enabled']) ? "yes" : "no";
                    $this->settings["title"] = $config['title'];
                    $this->settings["description"] = $config['description'];
	                $this->settings["merchantnum"] = $config['merchantnum'];
                    $this->settings["clientid"] = $config['clientid'];
                    $this->settings["secret"] = $config['secret'];
	                $this->settings["logo"] = $config['logo'];
	                $this->settings["emails"] = $config['emails'];
	                $this->settings["cconverter"] = ($config['cconverter']) ? "yes" : "no";
	                $this->settings["order_action"] = $config['order_action'];

                    $this->generate_settings_html();
                }

                function process_admin_options(){
                    parent::process_admin_options();
                }
            }

            $obj = new WC_HubtelPaymentSettings();
            if(isset($_POST["woocommerce_wc_hubtelpayment_save"]) && $_POST["woocommerce_wc_hubtelpayment_save"] == "Save Changes"){
                $obj->process_admin_options();
            }
            ?>
        </table>
	    <?php wp_nonce_field() ?>
    </form>
</div>
<?php else: ?>
    <div class="wrap">
        <h3>Hubtel Payment Gateway</h3>
        <p>Hubtel Payment is most popular payment gateway for online shopping in Ghana.</p>
        <?php echo (isset($_POST["woocommerce_wc_hubtelpayment_save"]) && $_POST["woocommerce_wc_hubtelpayment_save"] == "Save Changes") ? '<div class="notice notice-success is-dismissible"><p>Settings saved successfully</p></div>' : ""; ?>
        <form method="post">
            <table class="form-table">
                <tbody>
                <tr>
                    <th scope="row">
                        <label for="woocommerce_wc_hubtelpayment_merchantnum">M.A Number</label>
                    </th>
                    <td class="forminp">
                        <input class="input-text" type="text" name="woocommerce_wc_hubtelpayment_merchantnum" value="<?php echo $merchantnum ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woocommerce_wc_hubtelpayment_clientid">Client Id</label>
                    </th>
                    <td class="forminp">
                        <input class="input-text" type="text" name="woocommerce_wc_hubtelpayment_clientid" value="<?php echo $clientid ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woocommerce_wc_hubtelpayment_secret">Secret</label>
                    </th>
                    <td class="forminp">
                        <input class="input-text" type="text" name="woocommerce_wc_hubtelpayment_secret" value="<?php echo $secret ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woocommerce_wc_hubtelpayment_logo">Website Logo Url</label>
                    </th>
                    <td class="forminp">
                        <input class="input-text" type="text" name="woocommerce_wc_hubtelpayment_logo" value="<?php echo $logo ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woocommerce_wc_hubtelpayment_cconverter">Currency Conversion<br/><small style="font-weight: 400;">Since Hubtel Payment supports only GHS, enable this option if your store/site currency is <b>USD, GBP, EUR or NGN</b></small></label>
                    </th>
                    <td class="forminp">
                        <fieldset>
                            <input type="checkbox" name="woocommerce_wc_hubtelpayment_cconverter" value="1" <?php echo ($cconverter <> "no") ? "checked" : "" ?> />
                            <label for="woocommerce_wc_hubtelpayment_cconverter">Automatically convert to GHS before sending to hubtel.</label>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woocommerce_wc_hubtelpayment_description">Notification Email Addresses</label>
                    </th>
                    <td class="forminp">
                        <fieldset>
                            <textarea type="textarea" rows="5" name="woocommerce_wc_hubtelpayment_emails"><?php echo $emails ?></textarea>
                            <p class="description">Send a mail to the email addresses provided above whenever payment is received. <br>Separate each email address with a comma.</p>
                        </fieldset>
                    </td>
                </tr>
                </tbody>
            </table>
            <p class="submit">
                <button type="submit" name="woocommerce_wc_hubtelpayment_save" class="button-primary" value="Save Changes"> Saved Changes</button>
            </p>
	        <?php wp_nonce_field() ?>
        </form>

    </div>

<?php endif; ?>
