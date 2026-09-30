<?php
/*
 * @Author: AmirhpCom <https://amirhp.com>
 * @Last modified by: AmirhpCom <its@amirhp.com>
 */

if (!defined("ABSPATH")) exit;
if (!class_exists("WC_Settings_Page") || class_exists("peproDev_UploadReceipt_Settings")) return;

class peproDev_UploadReceipt_Settings extends WC_Settings_Page {
  private $plugin;
  public function __construct($plugin) {
    $this->plugin = $plugin;
    $this->id     = "receipt_upload";
    $this->label  = __("Receipt Upload", "pepro-bacs-receipt-upload-for-woocommerce");
    parent::__construct();
    add_action("woocommerce_admin_field_peprodev_receipt_help", array($this, "output_help"));
  }
  protected function get_own_sections() {
    return array(
      ""         => __("General", "pepro-bacs-receipt-upload-for-woocommerce"),
      "statuses" => __("Order Status Automation", "pepro-bacs-receipt-upload-for-woocommerce"),
      "content"  => __("Upload Form", "pepro-bacs-receipt-upload-for-woocommerce"),
      "help"     => __("Help & Tools", "pepro-bacs-receipt-upload-for-woocommerce"),
    );
  }
  public function output() {
    global $current_section;
    if ("help" === $current_section) $GLOBALS["hide_save_button"] = true; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce core global.
    parent::output();
  }
  public function save() {
    global $current_section;
    if ("help" === $current_section) return;
    parent::save();
  }
  private function get_order_statuses() {
    return array_merge(array("none" => __("Disabled (do nothing)", "pepro-bacs-receipt-upload-for-woocommerce")), wc_get_order_statuses());
  }
  private function get_file_type_options() {
    $labels  = array(
      "image/jpeg"      => "JPG / JPEG",
      "image/png"       => "PNG",
      "image/webp"      => "WEBP",
      "image/gif"       => "GIF",
      "image/bmp"       => "BMP",
      "image/avif"      => "AVIF",
      "image/heic"      => "HEIC",
      "application/pdf" => "PDF",
    );
    $options = array();
    foreach ($this->plugin->get_safe_mimes() as $mime) {
      $options[$mime] = isset($labels[$mime]) ? $labels[$mime] : $mime;
    }
    return $options;
  }
  protected function get_settings_for_default_section() {
    return array(
      array(
        "type"  => "title",
        "id"    => "peprobacsru_general",
        "title" => __("Receipt Upload", "pepro-bacs-receipt-upload-for-woocommerce"),
        "desc"  => __("Choose which payment methods need a payment receipt, when customers can upload it and what files are accepted.", "pepro-bacs-receipt-upload-for-woocommerce"),
      ),
      array(
        "type"     => "multiselect",
        "id"       => "peprobacsru_allowed_gateways",
        "title"    => __("Payment methods", "pepro-bacs-receipt-upload-for-woocommerce"),
        "desc"     => __("Customers paying with these methods will see the receipt upload form.", "pepro-bacs-receipt-upload-for-woocommerce"),
        "default"  => array("bacs"),
        "class"    => "wc-enhanced-select",
        "css"      => "min-width: 400px;",
        "options"  => $this->plugin->get_wc_gateways(),
      ),
      array(
        "type"     => "multiselect",
        "id"       => "peprobacsru_show_on_statuses",
        "title"    => __("Show form on statuses", "pepro-bacs-receipt-upload-for-woocommerce"),
        "desc"     => __("The upload form is shown and accepted only while the order has one of these statuses.", "pepro-bacs-receipt-upload-for-woocommerce"),
        "default"  => $this->plugin->get_default_show_on_statuses(),
        "class"    => "wc-enhanced-select",
        "css"      => "min-width: 400px;",
        "options"  => wc_get_order_statuses(),
      ),
      array(
        "type"     => "multiselect",
        "id"       => "peprobacsru_allowed_file_types",
        "title"    => __("Allowed file types", "pepro-bacs-receipt-upload-for-woocommerce"),
        "desc"     => __("Files are checked by their real content, not only by extension.", "pepro-bacs-receipt-upload-for-woocommerce"),
        "default"  => array("image/jpeg", "image/png", "application/pdf"),
        "class"    => "wc-enhanced-select",
        "css"      => "min-width: 400px;",
        "options"  => $this->get_file_type_options(),
      ),
      array(
        "type"              => "number",
        "id"                => "peprobacsru_allowed_file_size",
        "title"             => __("Maximum file size (MB)", "pepro-bacs-receipt-upload-for-woocommerce"),
        // translators: %s: maximum upload size allowed by server
        "desc"              => sprintf(__("Your server accepts up to %s per upload.", "pepro-bacs-receipt-upload-for-woocommerce"), size_format(wp_max_upload_size())),
        "default"           => "4",
        "css"               => "width: 100px;",
        "custom_attributes" => array("min" => "1", "step" => "1", "dir" => "ltr"),
      ),
      array(
        "type"              => "url",
        "id"                => "peprobacsru_redirect_after_upload",
        "title"             => __("Redirect after upload", "pepro-bacs-receipt-upload-for-woocommerce"),
        "desc"              => __("Send the customer to this address after a successful upload. Leave empty to stay on the page.", "pepro-bacs-receipt-upload-for-woocommerce"),
        "default"           => "",
        "placeholder"       => "https://",
        "css"               => "min-width: 400px;",
        "custom_attributes" => array("dir" => "ltr"),
      ),
      array("type" => "sectionend", "id" => "peprobacsru_general"),
    );
  }
  protected function get_settings_for_statuses_section() {
    $statuses = $this->get_order_statuses();
    return array(
      array(
        "type"  => "title",
        "id"    => "peprobacsru_statuses",
        "title" => __("Order Status Automation", "pepro-bacs-receipt-upload-for-woocommerce"),
        "desc"  => __("Change the order status automatically on each receipt step. Choose Disabled to keep the order status untouched.", "pepro-bacs-receipt-upload-for-woocommerce"),
      ),
      array(
        "type"    => "select",
        "id"      => "peprobacsru_auto_change_status",
        "title"   => __("When order placed", "pepro-bacs-receipt-upload-for-woocommerce"),
        "desc"    => __("Overrides the default WooCommerce status for new orders of the selected payment methods.", "pepro-bacs-receipt-upload-for-woocommerce"),
        "default" => "none",
        "class"   => "wc-enhanced-select",
        "options" => $statuses,
      ),
      array(
        "type"    => "select",
        "id"      => "peprobacsru_status_on_receipt_awaiting_upload",
        "title"   => __("Receipt awaiting upload", "pepro-bacs-receipt-upload-for-woocommerce"),
        "desc"    => __("When a shop manager sets the receipt back to Awaiting Upload.", "pepro-bacs-receipt-upload-for-woocommerce"),
        "default" => "wc-receipt-upload",
        "class"   => "wc-enhanced-select",
        "options" => $statuses,
      ),
      array(
        "type"    => "select",
        "id"      => "peprobacsru_status_on_receipt_awaiting_approval",
        "title"   => __("Receipt uploaded", "pepro-bacs-receipt-upload-for-woocommerce"),
        "desc"    => __("When the customer uploads a receipt and it waits for approval.", "pepro-bacs-receipt-upload-for-woocommerce"),
        "default" => "wc-receipt-approval",
        "class"   => "wc-enhanced-select",
        "options" => $statuses,
      ),
      array(
        "type"    => "select",
        "id"      => "peprobacsru_status_on_receipt_approved",
        "title"   => __("Receipt approved", "pepro-bacs-receipt-upload-for-woocommerce"),
        "desc"    => __("When a shop manager approves the receipt.", "pepro-bacs-receipt-upload-for-woocommerce"),
        "default" => "none",
        "class"   => "wc-enhanced-select",
        "options" => $statuses,
      ),
      array(
        "type"    => "select",
        "id"      => "peprobacsru_status_on_receipt_rejected",
        "title"   => __("Receipt rejected", "pepro-bacs-receipt-upload-for-woocommerce"),
        "desc"    => __("When a shop manager rejects the receipt.", "pepro-bacs-receipt-upload-for-woocommerce"),
        "default" => "wc-receipt-rejected",
        "class"   => "wc-enhanced-select",
        "options" => $statuses,
      ),
      array("type" => "sectionend", "id" => "peprobacsru_statuses"),
    );
  }
  protected function get_settings_for_content_section() {
    return array(
      array(
        "type"  => "title",
        "id"    => "peprobacsru_content",
        "title" => __("Upload Form", "pepro-bacs-receipt-upload-for-woocommerce"),
        "desc"  => __("Customize what customers see around the upload form on the thank-you and order details pages.", "pepro-bacs-receipt-upload-for-woocommerce"),
      ),
      array(
        "type"        => "text",
        "id"          => "peprobacsru_form_title",
        "title"       => __("Form title", "pepro-bacs-receipt-upload-for-woocommerce"),
        "default"     => "",
        "placeholder" => __("Upload receipt", "pepro-bacs-receipt-upload-for-woocommerce"),
        "css"         => "min-width: 400px;",
      ),
      array(
        "type"              => "textarea",
        "id"                => "peprobacsru_html_before_form",
        "title"             => __("Content before form", "pepro-bacs-receipt-upload-for-woocommerce"),
        "desc"              => __("HTML and shortcodes are allowed, e.g. your bank account details.", "pepro-bacs-receipt-upload-for-woocommerce"),
        "default"           => "",
        "css"               => "width: 100%; max-width: 700px; min-height: 180px; font-family: monospace;",
        "custom_attributes" => array("dir" => "ltr"),
      ),
      array(
        "type"              => "textarea",
        "id"                => "peprobacsru_html_after_form",
        "title"             => __("Content after form", "pepro-bacs-receipt-upload-for-woocommerce"),
        "desc"              => __("HTML and shortcodes are allowed.", "pepro-bacs-receipt-upload-for-woocommerce"),
        "default"           => "",
        "css"               => "width: 100%; max-width: 700px; min-height: 180px; font-family: monospace;",
        "custom_attributes" => array("dir" => "ltr"),
      ),
      array("type" => "sectionend", "id" => "peprobacsru_content"),
    );
  }
  protected function get_settings_for_help_section() {
    return array(array("type" => "peprodev_receipt_help", "id" => "peprobacsru_help"));
  }
  public function output_help() {
    $protection = $this->plugin->get_protection_status(isset($_GET["recheck"]) && isset($_GET["_wpnonce"]) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET["_wpnonce"])), "peprobacsru_recheck"));
    $recheck    = wp_nonce_url(admin_url("admin.php?page=wc-settings&tab=receipt_upload&section=help&recheck=1"), "peprobacsru_recheck");
    $emails     = array(
      "wc_peprodev_uploadreceipt_customer"   => __("Uploaded Receipt to Customer", "pepro-bacs-receipt-upload-for-woocommerce"),
      "wc_peprodev_uploadreceipt_admin"      => __("Uploaded Receipt to Admin", "pepro-bacs-receipt-upload-for-woocommerce"),
      "wc_peprodev_approvedreceipt_customer" => __("Approved Receipt to Customer", "pepro-bacs-receipt-upload-for-woocommerce"),
      "wc_peprodev_approvedreceipt_admin"    => __("Approved Receipt to Admin", "pepro-bacs-receipt-upload-for-woocommerce"),
      "wc_peprodev_rejectedreceipt_customer" => __("Rejected Receipt to Customer", "pepro-bacs-receipt-upload-for-woocommerce"),
      "wc_peprodev_rejectedreceipt_admin"    => __("Rejected Receipt to Admin", "pepro-bacs-receipt-upload-for-woocommerce"),
    );
    ?>
    <tr><td colspan="2" class="peprodev-receipt-help">
      <div class="peprodev-receipt-card">
        <h3><?php echo esc_html__("Receipt storage protection", "pepro-bacs-receipt-upload-for-woocommerce"); ?></h3>
        <?php if ("protected" === $protection) { ?>
          <p class="peprodev-receipt-badge ok"><?php echo esc_html__("Protected: receipt files cannot be opened directly from the uploads folder.", "pepro-bacs-receipt-upload-for-woocommerce"); ?></p>
        <?php } elseif ("exposed" === $protection) { ?>
          <p class="peprodev-receipt-badge error"><?php echo esc_html__("Exposed: your web server ignores the .htaccess rules, receipt files can be opened directly. Add this rule to your Nginx server block:", "pepro-bacs-receipt-upload-for-woocommerce"); ?></p>
          <pre dir="ltr">location ~* ^/wp-content/uploads/<?php echo esc_html($this->plugin->get_folder_name()); ?>/ { deny all; return 403; }</pre>
        <?php } else { ?>
          <p class="peprodev-receipt-badge warn"><?php echo esc_html__("Could not verify, the loopback request to your site failed.", "pepro-bacs-receipt-upload-for-woocommerce"); ?></p>
        <?php } ?>
        <p><?php echo esc_html__("Receipts are always shown through signed links that only work for the related order.", "pepro-bacs-receipt-upload-for-woocommerce"); ?> <a href="<?php echo esc_url($recheck); ?>"><?php echo esc_html__("Check again", "pepro-bacs-receipt-upload-for-woocommerce"); ?></a></p>
      </div>
      <div class="peprodev-receipt-card">
        <h3><?php echo esc_html__("Email notifications", "pepro-bacs-receipt-upload-for-woocommerce"); ?></h3>
        <ul>
          <?php foreach ($emails as $section => $label) { ?>
            <li><a href="<?php echo esc_url(admin_url("admin.php?page=wc-settings&tab=email&section={$section}")); ?>"><?php echo esc_html($label); ?></a></li>
          <?php } ?>
        </ul>
      </div>
      <div class="peprodev-receipt-card">
        <h3><?php echo esc_html__("Shortcodes", "pepro-bacs-receipt-upload-for-woocommerce"); ?></h3>
        <table class="widefat striped">
          <tr><td><code>[receipt-form]</code></td><td><?php echo esc_html__("Upload form of the current order (thank-you and view order pages).", "pepro-bacs-receipt-upload-for-woocommerce"); ?></td></tr>
          <tr><td><code>[receipt-form order_id=15]</code></td><td><?php echo esc_html__("Upload form for order #15, shown only to its owner or shop managers.", "pepro-bacs-receipt-upload-for-woocommerce"); ?></td></tr>
          <tr><td><code>[receipt-preview order_id=15]</code></td><td><?php echo esc_html__("Receipt preview for order #15, shown only to its owner or shop managers.", "pepro-bacs-receipt-upload-for-woocommerce"); ?></td></tr>
        </table>
      </div>
      <div class="peprodev-receipt-card">
        <h3><?php echo esc_html__("Useful links", "pepro-bacs-receipt-upload-for-woocommerce"); ?></h3>
        <p>
          <a href="https://wordpress.org/support/plugin/pepro-bacs-receipt-upload-for-woocommerce/reviews/#new-post" target="_blank"><?php echo esc_html__("Rate 5-star", "pepro-bacs-receipt-upload-for-woocommerce"); ?></a> &middot;
          <a href="https://wordpress.org/plugins/pepro-bacs-receipt-upload-for-woocommerce/#developers" target="_blank"><?php echo esc_html__("Changelog", "pepro-bacs-receipt-upload-for-woocommerce"); ?></a> &middot;
          <a href="https://github.com/peprodev/wc-upload-reciept" target="_blank"><?php echo esc_html__("Contribute", "pepro-bacs-receipt-upload-for-woocommerce"); ?></a> &middot;
          <a href="https://wordpress.org/support/plugin/pepro-bacs-receipt-upload-for-woocommerce/" target="_blank"><?php echo esc_html__("Support", "pepro-bacs-receipt-upload-for-woocommerce"); ?></a> &middot;
          <a href="https://patchstack.com/database/vdp/pepro-bacs-receipt-upload-for-woocommerce" target="_blank"><?php echo esc_html__("Report a security issue", "pepro-bacs-receipt-upload-for-woocommerce"); ?></a>
        </p>
      </div>
    </td></tr>
    <?php
  }
}
