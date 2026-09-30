<?php
/*
 * @Author: AmirhpCom <https://amirhp.com>
 * @Last modified by: AmirhpCom <its@amirhp.com>
 */

if (!defined("ABSPATH")) exit;
if (!class_exists("WC_peproDev_Receipt_Email")) return;

class WC_peproDev_ApprovedReceipt_Admin extends WC_peproDev_Receipt_Email {
  public function __construct() {
    $this->id             = "wc_peprodev_admin_receipt_approved";
    $this->customer_email = false;
    $this->title          = __("Approved Receipt to Admin", "pepro-bacs-receipt-upload-for-woocommerce");
    $this->description    = __("An email sent to the Admin when a receipt is approved.", "pepro-bacs-receipt-upload-for-woocommerce");
    $this->template_html  = "admin-approved-receipt-template.php";
    $this->template_plain = "admin-approved-receipt-template-plain.php";
    parent::__construct();
  }
  public function get_default_heading() {
    return __("Receipt Approved", "pepro-bacs-receipt-upload-for-woocommerce");
  }
  public function get_default_subject() {
    // translators: %s: site title placeholder
    return sprintf(_x("[%s] Receipt Approved", "receipt-approved-admin-subject", "pepro-bacs-receipt-upload-for-woocommerce"), "{blogname}");
  }
}
