<?php

if (!defined('DS')) {
	define('DS', DIRECTORY_SEPARATOR);
}

use \WHMCS\Database\Capsule;

add_hook('AdminAreaFooterOutput', 1, function ($vars) {

	if ($vars['filename'] != 'invoices' && $_REQUEST['action'] != 'edit') {
		return;
	}

	$invoicePaymentMethod = Capsule::table('tblinvoices')
	                               ->select('paymentmethod', 'status')
	                               ->where('id', '=', $_REQUEST['id'])
	                               ->first();

	if ($invoicePaymentMethod->paymentmethod == 'twocheckoutconvertplus' && $invoicePaymentMethod->status == 'Paid') {
		$reasons = Capsule::table('tblpaymentgateways')
		                  ->select('value')
		                  ->where('gateway', '=', $invoicePaymentMethod->paymentmethod)
		                  ->where('setting', '=', 'refundReasons')
		                  ->first();

		$reasonsTable = explode("\n", $reasons->value);
		$selectOption = '';
		foreach ($reasonsTable as $option) {
			$optionToSelect = trim($option);
			if (empty($optionToSelect)) {
				continue;
			}
			$selectOption .= "<option value='$optionToSelect'>$optionToSelect</option>";
		}

		$html = "<tr><td class='fieldlabel'>Comment</td><td class='fieldarea'><input name='comment' id='comment' class='form-control' style='display: inline-block;'></input></td></tr>";
		$html .= "<tr><td class='fieldlabel'>Reason</td><td class='fieldarea'><select name='reason' id='reason' class='form-control select-inline' style='display: inline-block;'>$selectOption</select></td></tr>";
		return <<<SCRIPT
        <script>
          $(document).ready(function(){
            $("#refundtype").parent().parent().after("$html");
         });
    </script>
SCRIPT;
	}
});
