<?php
if (!defined('ABSPATH'))
	exit("No script kiddies");

if(!class_exists("WC_HubtelUtility")){
    require plugin_dir_path(__FILE__) . "../includes/class-hu-utility.php";
}

$data = array(
    "key" => get_option("hubtellicensekey", "N.A"),
    "plugin" => "hubtel",
);
$config = include plugin_dir_path(__FILE__) . "../includes/settings.php";
$response = WC_HubtelUtility::post_to_url($config["license_baseapi"]."getbuttons.json", false, $data);
if($response != "-1"){
    $response = json_decode($response);
}
$newbtn_api = admin_url("admin-ajax.php?action=new-hubtel-button");
?>

<link rel='stylesheet' href="<?php echo plugin_dir_url(__FILE__) . "../assets/css/tippy.css" ?>" type='text/css' media='all' />
<script src="<?php echo plugin_dir_url(__FILE__) . "../assets/js/tippy.min.js" ?>"></script>

<div class="wrap">
    <?php require plugin_dir_path(__FILE__) . "/buynow-header.php"; ?>
    <h1 class="wp-heading-inline">Hubtel Payment Buttons</h1>
    <a href="#new-button" id="newhubtelbutton" class="page-title-action">New Button</a>
    <br/><br/>
    <table class="wp-list-table widefat fixed striped posts">
        <thead>
        <tr>
            <th scope="col" class="manage-column column-order_status">
                Date
            </th>
            <th scope="col" class="manage-column column-order_title">
                Currency
            </th>
            <th scope="col" class="manage-column column-order_title">
                Amount
            </th>
            <th scope="col" class="manage-column column-order_total">
                Shortcode <span id="ic_help" href="#" title="Add this shortcode to any post"><img src="<?php echo plugin_dir_url(__FILE__) . "../assets/images/ic_help.png" ?>" style="position: relative;top: 3px;" /></span>
            </th>
            <th scope="col" class="manage-column column-order_total">
                Button Text
            </th>
            <th scope="col" class="manage-column column-order_actions">
                Clicks
            </th>
            <th></th>
        </tr>
        </thead>

        <tbody id="the-list">
        <?php if(is_array($response)): ?>
            <?php if(sizeof($response) > 0): ?>
                <?php foreach($response as $d): ?>
                    <tr>
                        <td><?php echo $d->created_at ?></td>
                        <td><?php echo $d->currency ?></td>
                        <td><?php echo ($d->amount <= 0) ? "<b title='Contributors will specify thier contribution'>Open</b>" : $d->amount ?></td>
                        <td>[HubtelPaymentButton code="<?php echo $d->btn_code ?>"]</td>
                        <td><?php echo esc_html($d->btn_text) ?></td>
                        <td><?php echo $d->clicks ?></td>
                        <td>
                            <button title="Delete" href="#del-button" class="button delhubtelbutton" data-url="<?php echo admin_url('admin-ajax.php?action=delhubtelbutton&code=' . $d->btn_code); ?>">X</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr class="no-items">
                    <td class="colspanchange" colspan="6">
                        You have not created any payment button
                    </td>
                </tr>
            <?php endif; ?>
        <?php else: ?>
            <tr class="no-items">
                <td class="colspanchange" colspan="6">
                    License verification failed
                </td>
            </tr>
        <?php endif; ?>
        </tbody>

    </table>


    <div id="new-button-modal" class="hubtel-modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <iframe id="" src="<?php echo $newbtn_api ?>"  height="550px"></iframe>
                <script>
                    window.addEventListener( "message",
                      function (e) {
                            var message = e.data;
                            if(message == "<?php echo $data["key"] ?>") {
                                jQuery("#new-button-modal .close").trigger("click");
                                location.reload();
                            }
                      },
                      false);
                </script>
            </div>
        </div>
    </div>
</div>

<script>
    tippy(document.querySelector('#ic_help'));
    tippy(document.querySelectorAll('.delhubtelbutton'));
</script>