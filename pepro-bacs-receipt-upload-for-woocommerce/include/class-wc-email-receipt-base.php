<?php
/*
 * @Author: AmirhpCom <https://amirhp.com>
 * @Last modified by: AmirhpCom <its@amirhp.com>
 */

if (!defined("ABSPATH")) exit;
if (!class_exists("WC_Email") || class_exists("WC_peproDev_Receipt_Email")) return;

abstract class WC_peproDev_Receipt_Email extends WC_Email {
  public function __construct() {
    $this->template_base = PEPRODEV_RECEIPT_UPLOAD_EMAIL_PATH . "templates/";
    $this->placeholders  = array_merge(array("{order_number}" => "", "{order_date}" => ""), $this->placeholders);
    parent::__construct();
    if (!$this->customer_email) {
      $this->recipient = $this->get_option("recipient", get_option("admin_email"));
    }
  }
  public function trigger($order_id) {
    $this->setup_locale();
    $this->object = wc_get_order($order_id);
    if ($this->object) {
      $this->placeholders["{order_number}"] = $this->object->get_order_number();
      $this->placeholders["{order_date}"]   = wc_format_datetime($this->object->get_date_created());
      if ($this->customer_email) $this->recipient = $this->object->get_billing_email();
    }
    if ($this->object && $this->is_enabled() && $this->get_recipient()) {
      $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
    }
    $this->restore_locale();
  }
  public function get_content_html() {
    return wc_get_template_html($this->template_html, $this->get_template_args(false), "", $this->template_base);
  }
  public function get_content_plain() {
    return wc_get_template_html($this->template_plain, $this->get_template_args(true), "", $this->template_base);
  }
  protected function get_template_args($plain_text) {
    return array(
      "order"              => $this->object,
      "additional_content" => $this->get_additional_content(),
      "email_heading"      => $this->get_heading(),
      "sent_to_admin"      => !$this->customer_email,
      "plain_text"         => $plain_text,
      "email"              => $this,
    );
  }
  public function init_form_fields() {
    parent::init_form_fields();
    if ($this->customer_email) return;
    $fields = array();
    foreach ($this->form_fields as $key => $field) {
      $fields[$key] = $field;
      if ("enabled" === $key) {
        $fields["recipient"] = array(
          "title"       => __("Recipient(s)", "pepro-bacs-receipt-upload-for-woocommerce"),
          "type"        => "text",
          /* translators: %s: WP admin email */
          "description" => sprintf(__("Enter recipients (comma separated) for this email. Defaults to %s.", "pepro-bacs-receipt-upload-for-woocommerce"), "<code>" . esc_attr(get_option("admin_email")) . "</code>"),
          "placeholder" => "",
          "default"     => "",
          "desc_tip"    => true,
        );
      }
    }
    $this->form_fields = $fields;
  }
}
