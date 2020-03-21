<?php
if (!defined('ABSPATH'))
	exit("No script kiddies");

function formatDate($time) {
	if ($time >= strtotime("today 00:00")) {
		return date("g:i A", $time);
	} elseif ($time >= strtotime("yesterday 00:00")) {
		return "Yesterday at " . date("g:i A", $time);
	} elseif ($time >= strtotime("-6 day 00:00")) {
		return date("l \\a\\t g:i A", $time);
	} else {
		return date("M j, Y h:i:s", $time);
	}
}

if(!class_exists("WC_HubtelUtility")){
	require plugin_dir_path(__FILE__) . "../includes/class-hu-utility.php";
}
if(!class_exists("WC_HubtelResponse")){
	require_once plugin_dir_path(__FILE__) . "../includes/class-hu-response.php";
}
$respObj = new WC_HubtelResponse();

$data = array(
	"key" => get_option("hubtellicensekey", "N.A"),
	"plugin" => "hubtel",
    "site" => get_site_url(),
	"em" => get_option("admin_email", ""),
);
$config = include plugin_dir_path(__FILE__) . "../includes/settings.php";
$response = WC_HubtelUtility::post_to_url($config["license_baseapi"]."license.json", false, $data);
$response = (!$response || $response <= 0) ? 0 : $response;
global $wpdb;
$data = array();
$currency = "GHS";
$pstatus = isset($_GET["status"]) ? $_GET["status"] : "";
$daterange = isset($_GET["daterange-picker"]) ? $_GET["daterange-picker"] : "";
$limit = 20;
$page = (isset($_GET["paged"])) ? (($_GET["paged"] - 1) * $limit) : "0";
$sql = "SELECT pm.post_id, pm.meta_value AS 'token', p.post_date_gmt AS ddate, p.post_status AS pstatus 
        FROM {$wpdb->prefix}postmeta pm JOIN {$wpdb->prefix}posts p ON p.ID = pm.post_id
        WHERE $response AND pm.meta_key = 'hubteltoken'";

$sql_c = "SELECT count(*) AS total 
        FROM {$wpdb->prefix}postmeta pm JOIN {$wpdb->prefix}posts p ON p.ID = pm.post_id
        WHERE $response AND pm.meta_key = 'hubteltoken'";


if($pstatus <> "") {
	$sql .= " AND p.post_status = '$pstatus' ";
	$sql_c .= " AND p.post_status = '$pstatus' ";
}
if($daterange <> "") {
	$range = explode("-", $daterange);
	$datef = $range[0];
	$datet = $range[1];
	$sql .= " AND p.post_date_gmt >= '$datef' AND p.post_date_gmt <= '$datet'";
	$sql_c .= " AND p.post_date_gmt >= '$datef' AND p.post_date_gmt <= '$datet'";
}

$sql.= "ORDER BY p.post_date_gmt DESC ";

if(!isset($_GET["download"])){
	$sql.= " limit $page,$limit";
}

$total_x = $wpdb->get_row($sql_c);
$records_count = $total_x->total;
$pages = ceil($records_count/20);
$cur_page = (isset($_GET["paged"])) ? $_GET["paged"] : "1";
$next_page = $cur_page + 1;
$prev_page = $cur_page - 1;

$results = $wpdb->get_results($sql);
foreach($results as $key => $r) {
	$total = get_post_meta($r->post_id, "_hubteltotal", true);
	if(!$total){
		$total = get_post_meta($r->post_id, "_order_total", true);
    }
    $user = get_post_meta($r->post_id, "_customer_user", true);
    if($user && is_numeric($user)){
        $user_obj = get_user_by("ID", $user);
        if($user_obj){
            $mobileno = get_user_meta($user, "billing_phone", true);
            $email_addr = $user_obj->user_email;
            $r->customer = $user_obj->first_name . ' ' . $user_obj->last_name . 
                            "<br> <small><b>E:</b> " . $email_addr . "</small>" . 
                            "<br> <small><b>M:</b> " . $mobileno . "</small>";
        }else{
            $r->customer = "N.A";
        }
    }else{
	    $r->customer = "";
	    //if($user) {
		    $user = get_post_meta( $r->post_id, "_donation_customer", true );
		    $name = $email = $mobile = "";
			if(is_array($user)){
				foreach ( $user as $k => $u ) {
					if ( $k == "name" ) {
						$name        = ( $u == "" ) ? "N.A" : trim( $u );
						$r->customer .= $name;
					} else if ( $k == "email" ) {
						$email       = ( $u == "" ) ? "N.A" : trim( $u );
						$r->customer .= "<br> <small><b>E:</b> $email</small>";
					} else if ( $k == "mobile" ) {
						$mobile      = ( $u == "" ) ? "N.A" : trim( $u );
						$r->customer .= "<br> <small><b>M:</b> $mobile</small>";
					}
				}
			}
		    $r->customer = ( $name == "N.A" && $email == "N.A" && $mobile == "N.A" ) ? "Anonymous" : $r->customer;
	    //}
    }
    $r->total = $total;
	$r->pstatus = str_replace("wc-", "", $r->pstatus);
    $data[] = $r;
}

if(isset($_GET["download"])){
    #download as csv
	ob_end_clean();
	ob_start();
	header("Content-type: text/csv");
	header("Content-Disposition: attachment; filename=payment-logs.csv");
	header("Pragma: no-cache");
	header("Expires: 0");

	$content = "Date,Amount,Customer,Email Address,Mobile No,Token,Status\n";
	foreach ($data as $d){
	    $customer = explode("\n", preg_replace('/\<br(\s*)?\/?\>/i', "\n", $d->customer));
	    $cust = isset($customer[0]) ? $customer[0] : "";
		$email = isset($customer[1]) ? $customer[1] : "";
		$mobile = isset($customer[2]) ? $customer[2] : "";

		if($email <> "") {
			$email = str_replace( "<small>", "", $email );
			$email = str_replace( "<b>E:</b>", "", $email );
			$email = str_replace( "</small>", "", $email );
		}

		if($mobile <> "") {
			$mobile = str_replace( "<small>", "", $mobile );
			$mobile = str_replace( "<b>M:</b>", "", $mobile );
			$mobile = str_replace( "</small>", "", $mobile );
		}

	    $content .= $d->ddate . "," . number_format($d->total, 2) . "," . $cust . "," . $email . "," . $mobile . "," . $d->token . "," . $d->pstatus . "\n";
    }
	echo $content;
	ob_end_flush();
	die;
}

?>

<link rel='stylesheet' href="<?php echo plugin_dir_url(__FILE__) . "../assets/css/datepicker.css" ?>" type='text/css' media='all' />
<script src="<?php echo plugin_dir_url(__FILE__) . "../assets/js/fecha.min.js" ?>"></script>
<script src="<?php echo plugin_dir_url(__FILE__) . "../assets/js/datepicker.min.js" ?>"></script>
<div class="wrap">
	<?php require plugin_dir_path(__FILE__) . "/buynow-header.php"; ?>
    <form action="<?php echo admin_url("admin.php") ?>" method="get">
        <h1 class="wp-heading-inline">Hubtel Payment Logs</h1>
        <button type="submit" name="download" value="1" class="page-title-action">Download</button>
        <br/>

        <div class="tablenav top">
            <div class="alignleft actions">
                <div class="tablenav-pages">
                    <?php if ($records_count > 1): ?>
                        <span style="font-weight: bold"><?php echo $records_count  ?> transactions</span>
                    <?php else: ?>
                        <span style="font-weight: bold"><?php echo $records_count  ?> transaction</span>
                    <?php endif; ?>
                    <?php if ($pages > 1): ?>
                        <span class="pagination-links">
                                <?php if ($cur_page <= 1): ?>
                                    <span class="tablenav-pages-navspan" aria-hidden="true">‹</span>
                                <?php else: ?>
                                    <a class="next-page" href="<?php echo admin_url() ?>admin.php?page=hubtel_transactions&paged=<?php echo $prev_page ?>">
                                        <span class="screen-reader-text">Next page</span>
                                        <span aria-hidden="true">‹</span>
                                    </a>
                                <?php endif; ?>

                            <span class="paging-input">
                                    <label for="current-page-selector" class="screen-reader-text">
                                        Current Page
                                    </label>
                                    <span class="total-pages"><?php echo $cur_page ?></span>
                                    of <span class="total-pages"><?php echo $pages ?></span>
                                </span>
                            <?php if ($cur_page >= $pages): ?>
                                <span class="tablenav-pages-navspan" aria-hidden="true">›</span>
                            <?php else: ?>
                                <a class="next-page" href="<?php echo admin_url() ?>admin.php?page=hubtel_transactions&paged=<?php echo $next_page ?>">
                                        <span class="screen-reader-text">Next page</span>
                                        <span aria-hidden="true">›</span>
                                    </a>
                            <?php endif; ?>
                            </span>
                    <?php endif; ?>
                </div>
                <br class="clear">
            </div>

            <div class="alignright actions">
                    <input type="text" placeholder="Date" name="daterange-picker" id="daterange-picker"
                           value="<?php echo isset($_GET["daterange-picker"]) ? $_GET["daterange-picker"] : ""  ?>" />
                    <input type="submit" value="Filter" />
                    <select name="status">
                        <option value="">All</option>
                        <option value="wc-pending" <?php echo (isset($_GET["status"]) && $_GET["status"]=="wc-pending") ? "selected" : ""  ?>>Pending</option>
                        <option value="wc-cancelled" <?php echo (isset($_GET["status"]) && $_GET["status"]=="wc-cancelled") ? "selected" : ""  ?>>Cancelled</option>
                        <option value="wc-completed" <?php echo (isset($_GET["status"]) && $_GET["status"]=="wc-completed") ? "selected" : ""  ?>>Successful</option>
                    </select>
                    <input type="hidden" name="page" value="hubtel_transactions">
            </div>
        </div>
    </form>

    <table class="wp-list-table widefat fixed striped posts">
        <thead>
        <tr>
            <th scope="col" class="manage-column column-order_date">
                Date
            </th>
            <th scope="col" class="manage-column column-order_customer">
                Customer
            </th>
            <th scope="col" class="manage-column column-order_amount">
                Amount(GHS)
            </th>
            <th scope="col" class="manage-column column-order_status">
                Status
            </th>
            <th scope="col" class="manage-column column-order_token">
                Token
            </th>
        </tr>
        </thead>

        <tbody id="the-list">
        <?php if(sizeof($data) > 0): ?>
            <?php foreach($data as $d): ?>
                <tr>
                    <td><?php echo formatDate(strtotime($d->ddate)) ?></td>
                    <td><?php echo $d->customer ?></td>
                    <td><?php echo $d->total ?></td>
                    <td><?php echo ($d->pstatus == "completed") ? "Successful" : ucfirst($d->pstatus) ?></td>
                    <td><?php echo esc_html($d->token) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <?php if($response): ?>
                <tr class="no-items">
                    <td class="colspanchange" colspan="4">
                        No payment has been made yet
                    </td>
                </tr>
            <?php else: ?>
                <tr class="no-items">
                    <td class="colspanchange" colspan="6">
                        License verification failed
                    </td>
                </tr>
            <?php endif; ?>
        <?php endif; ?>
        </tbody>

    </table>
</div>
<script>
    var daterange = document.getElementById('daterange-picker');
    var datepicker = new HotelDatepicker(daterange, {
        endDate: new Date(),
        startDate: '2017-01-01',
        format: 'YYYY/MM/DD'
    });
</script>
