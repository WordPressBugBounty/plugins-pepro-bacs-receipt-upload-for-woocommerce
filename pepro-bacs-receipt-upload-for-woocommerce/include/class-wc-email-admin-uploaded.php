<?php
/*
 * @Author: AmirhpCom <https://amirhp.com>
 * @Last modified by: AmirhpCom <its@amirhp.com>
 */

if (!defined("ABSPATH")) exit;
if (!class_exists("WC_peproDev_Receipt_Email")) return;

class WC_peproDev_UploadReceipt_Admin extends WC_peproDev_Receipt_Email {
  public function __construct() {
    $this->id             = "wc_peprodev_admin_receipt_uploaded";
    $this->customer_email = false;
    $this->title          = __("Uploaded Receipt to Admin", "pepro-bacs-receipt-upload-for-woocommerce");
    $this->description    = __("An email sent to the Admin when a receipt is uploaded.", "pepro-bacs-receipt-upload-for-woocommerce");
    $this->template_html  = "admin-uploaded-receipt-template.php";
    $this->template_plain = "admin-uploaded-receipt-template-plain.php";
    parent::__construct();
  }
  public function get_default_heading() {
    return __("Receipt Uploaded", "pepro-bacs-receipt-upload-for-woocommerce");
  }
  public function get_default_subject() {
    // translators: %s: site title placeholder
    return sprintf(_x("[%s] Receipt Uploaded", "receipt-uploaded-admin-subject", "pepro-bacs-receipt-upload-for-woocommerce"), "{blogname}");
  }
}
