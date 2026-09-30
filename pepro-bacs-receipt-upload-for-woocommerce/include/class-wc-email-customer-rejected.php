<?php
/*
 * @Author: AmirhpCom <https://amirhp.com>
 * @Last modified by: AmirhpCom <its@amirhp.com>
 */

if (!defined("ABSPATH")) exit;
if (!class_exists("WC_peproDev_Receipt_Email")) return;

class WC_peproDev_RejectedReceipt_Customer extends WC_peproDev_Receipt_Email {
  public function __construct() {
    $this->id             = "wc_peprodev_customer_receipt_rejected";
    $this->customer_email = true;
    $this->title          = __("Rejected Receipt to Customer", "pepro-bacs-receipt-upload-for-woocommerce");
    $this->description    = __("An email sent to the customer when a receipt is rejected.", "pepro-bacs-receipt-upload-for-woocommerce");
    $this->template_html  = "customer-rejected-receipt-template.php";
    $this->template_plain = "customer-rejected-receipt-template-plain.php";
    parent::__construct();
  }
  public function get_default_heading() {
    return __("Receipt Rejected", "pepro-bacs-receipt-upload-for-woocommerce");
  }
  public function get_default_subject() {
    // translators: %s: site title placeholder
    return sprintf(_x("[%s] Receipt Rejected", "receipt-rejected-customer-subject", "pepro-bacs-receipt-upload-for-woocommerce"), "{blogname}");
  }
}
