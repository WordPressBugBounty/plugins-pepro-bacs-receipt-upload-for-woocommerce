<?php
/*
 * @Author: AmirhpCom <https://amirhp.com>
 * @Last modified by: AmirhpCom <its@amirhp.com>
 */

if (!defined("ABSPATH")) exit;
if (!class_exists("WC_peproDev_Receipt_Email")) return;

class WC_peproDev_ApprovedReceipt_Customer extends WC_peproDev_Receipt_Email {
  public function __construct() {
    $this->id             = "wc_peprodev_customer_receipt_approved";
    $this->customer_email = true;
    $this->title          = __("Approved Receipt to Customer", "pepro-bacs-receipt-upload-for-woocommerce");
    $this->description    = __("An email sent to the customer when a receipt is approved.", "pepro-bacs-receipt-upload-for-woocommerce");
    $this->template_html  = "customer-approved-receipt-template.php";
    $this->template_plain = "customer-approved-receipt-template-plain.php";
    parent::__construct();
  }
  public function get_default_heading() {
    return __("Receipt Approved", "pepro-bacs-receipt-upload-for-woocommerce");
  }
  public function get_default_subject() {
    // translators: %s: site title placeholder
    return sprintf(_x("[%s] Receipt Approved", "receipt-approved-customer-subject", "pepro-bacs-receipt-upload-for-woocommerce"), "{blogname}");
  }
}
