<?php
/*
Plugin Name: PeproDev Receipt Uploader for WooCommerce
Description: Upload Receipt for Any Payment method in WooCommerce. Customers will Upload the receipt (image/pdf) and Shop Managers will approve/reject it manually
Author: Pepro Dev. Group
Author URI: https://pepro.dev/
Developer: AmirhpCom
Developer URI: https://amirhp.com/
Plugin URI: https://pepro.dev/receipt-upload
Version: 2.15.0
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
WC requires at least: 7.0
WC tested up to: 11.1
Text Domain: pepro-bacs-receipt-upload-for-woocommerce
Domain Path: /languages
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

use Automattic\WooCommerce\Utilities\OrderUtil;

if (!defined("ABSPATH")) exit;

if (!class_exists("peproDev_UploadReceiptWC")) {
  class peproDev_UploadReceiptWC {
    public $version = "2.15.0";
    public $db_slug = "wcuploadrcp";
    public $url;
    public $title;
    public $title_w;
    private $plugin_dir;
    private $folder_name;
    private $assets_url;
    private $status_order_placed;
    private $status_receipt_awaiting_upload;
    private $status_receipt_awaiting_approval;
    private $status_receipt_rejected;
    private $status_receipt_approved;
    private $html_before;
    private $html_after;
    private $defaultImg;
    public function __construct() {
      $this->plugin_dir                       = plugin_dir_path(__FILE__);
      $this->assets_url                       = plugins_url("/assets/", __FILE__);
      $this->url                              = admin_url("admin.php?page=wc-settings&tab=receipt_upload");
      $this->title                            = "PeproDev Receipt Uploader";
      $this->title_w                          = sprintf('%2$s ver. %1$s', $this->version, $this->title);
      $this->folder_name                      = sanitize_file_name(apply_filters("peprodev_uploadreceipt_folder_name", apply_filters_deprecated("pepro_upload_receipt_folder_name", array("receipt_upload"), "2.12.0", "peprodev_uploadreceipt_folder_name")));
      $this->status_order_placed              = get_option("peprobacsru_auto_change_status", "none");
      $this->status_receipt_awaiting_upload   = get_option("peprobacsru_status_on_receipt_awaiting_upload", "none");
      $this->status_receipt_awaiting_approval = get_option("peprobacsru_status_on_receipt_awaiting_approval", "none");
      $this->status_receipt_rejected          = get_option("peprobacsru_status_on_receipt_rejected", "none");
      $this->status_receipt_approved          = get_option("peprobacsru_status_on_receipt_approved", "none");
      $this->html_before                      = get_option("peprobacsru_html_before_form", "");
      $this->html_after                       = get_option("peprobacsru_html_after_form", "");
      $this->defaultImg                       = "{$this->assets_url}backend/images/NoImageLarge.png";
      if (!defined("PEPRODEV_RECEIPT_UPLOAD_EMAIL_PATH")) define("PEPRODEV_RECEIPT_UPLOAD_EMAIL_PATH", plugin_dir_path(__FILE__));
      add_action("init", array($this, "init_plugin"));
      add_filter("woocommerce_email_classes", array($this, "register_email"), 1, 1);
      add_action("woocommerce_receipt_uploaded_notification", array($this, "trigger_receipt_uploaded_notification"));
      add_action("woocommerce_receipt_approved_notification", array($this, "trigger_receipt_approved_notification"));
      add_action("woocommerce_receipt_rejected_notification", array($this, "trigger_receipt_rejected_notification"));
      add_action("peprodev_uploadreceipt_email_receipt_preview", array($this, "email_receipt_preview"), 10, 4);
      add_filter("wc_order_statuses", array($this, "add_wc_order_statuses"), 10000, 1);
      add_filter("plugin_row_meta", array($this, "plugin_row_meta"), 10, 2);
      add_filter("plugin_action_links", array($this, "plugin_action_links"), 10, 2);
      add_action("before_woocommerce_init", function () {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
          \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility("custom_order_tables", __FILE__, true);
        }
      });
    }
    public function init_plugin() {
      $this->add_wc_prebuy_status();
      add_action("admin_init", array($this, "admin_init"));
      add_action("pre_get_posts", array($this, "media_custom_filter"));
      add_action("admin_enqueue_scripts", array($this, "enqueue_admin_script"));
      add_filter("manage_upload_columns", array($this, "add_column_upload_receipt"));
      add_action("manage_media_custom_column", array($this, "column_upload_receipt"), 10, 2);
      add_action("woocommerce_thankyou", array($this, "woocommerce_thankyou"), -1);
      add_action("woocommerce_order_details_before_order_table", array($this, "order_details_before_order_table"), -1000);
      add_action("add_meta_boxes", array($this, "receipt_upload_add_meta_box"));
      add_action("admin_menu", array($this, "admin_menu"), 1000);
      add_action("woocommerce_process_shop_order_meta", array($this, "receipt_upload_save"));
      if ($this->is_hpos_enabled()) {
        add_filter("manage_woocommerce_page_wc-orders_columns", array($this, "column_header"));
        add_action("manage_woocommerce_page_wc-orders_custom_column", array($this, "column_content"), 20, 2);
      } else {
        add_filter("manage_edit-shop_order_columns", array($this, "column_header"));
        add_action("manage_shop_order_posts_custom_column", array($this, "column_content"), 20, 2);
      }
      add_filter("woocommerce_get_settings_pages", array($this, "add_settings_page"));
      add_filter("woocommerce_admin_settings_sanitize_option_peprobacsru_redirect_after_upload", "esc_url_raw");
      add_filter("woocommerce_valid_order_statuses_for_payment", array($this, "valid_order_statuses_for_payment"), 10, 2);
      add_action("admin_enqueue_scripts", array($this, "admin_enqueue_scripts"));
      add_shortcode("receipt-preview", array($this, "receipt_preview_shortcode"));
      add_shortcode("receipt-form", array($this, "receipt_form_shortcode"));
      add_action("wp_ajax_upload-payment-receipt", array($this, "handel_ajax_req"));
      add_action("wp_ajax_nopriv_upload-payment-receipt", array($this, "handel_ajax_req"));
      add_filter("wp_get_attachment_url", array($this, "filter_receipt_attachment_url"), 99, 2);
      add_filter("image_downsize", array($this, "filter_receipt_image_downsize"), 99, 3);
      add_filter("wp_calculate_image_srcset", array($this, "filter_receipt_image_srcset"), 99, 5);
      add_action("template_redirect", array($this, "block_receipt_attachment_page"));
      $this->maybe_serve_receipt();
    }
    public function is_hpos_enabled() {
      return class_exists("\Automattic\WooCommerce\Utilities\OrderUtil") && OrderUtil::custom_orders_table_usage_is_enabled();
    }
    public function add_column_upload_receipt($columns) {
      $columns["upload_receipt"] = __("Attached Order", "pepro-bacs-receipt-upload-for-woocommerce");
      return $columns;
    }
    public function column_upload_receipt($column_name, $attachment_id) {
      if ("upload_receipt" !== $column_name) return;
      $file     = get_attached_file($attachment_id);
      $filesize = $file && file_exists($file) ? size_format(filesize($file), 2) : "";
      $meta     = absint(get_post_meta($attachment_id, "_attached_order", true));
      if ($meta) {
        $url = add_query_arg(array("receipt_attached" => $meta), admin_url("upload.php"));
        printf("<a href='%s' title='%s'>#%s</a> | %s", esc_url($url), esc_attr__("Filter Receipts uploaded to this Order", "pepro-bacs-receipt-upload-for-woocommerce"), esc_html($meta), esc_html($filesize));
      } else {
        echo esc_html($filesize);
      }
    }
    public function enqueue_admin_script($hook) {
      if ("upload.php" !== $hook) return;
      $url   = add_query_arg(array("receipt_attached" => "yes", "mode" => "list"), admin_url("upload.php"));
      $title = __("Filter Receipts", "pepro-bacs-receipt-upload-for-woocommerce");
      wp_register_script("upload_receipt-js", "", array("jquery"), $this->version, true);
      wp_enqueue_script("upload_receipt-js");
      wp_add_inline_script("upload_receipt-js", sprintf('(function($){$(function(){$(".filter-items .actions").first().append($("<a class=\"button button-secondary\"></a>").attr("href", "%s").text("%s"));});})(jQuery);', esc_js(esc_url_raw($url)), esc_js($title)));
    }
    public function media_custom_filter($query) {
      if (!is_admin() || !$query->is_main_query() || !current_user_can("upload_files")) return;
      if ("attachment" !== $query->get("post_type") || empty($_GET["receipt_attached"])) return; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
      $value = sanitize_text_field(wp_unslash($_GET["receipt_attached"])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
      if ("yes" === $value) {
        $meta_query = array(array("key" => "_attached_order", "compare" => "EXISTS"));
      } else {
        $meta_query = array(array("key" => "_attached_order", "value" => absint($value), "compare" => "="));
      }
      $query->set("meta_query", $meta_query);
      $query->set("post_status", array("inherit", "private"));
    }
    public function plugin_row_meta($links_array, $plugin_file_name) {
      if (plugin_basename(__FILE__) === $plugin_file_name) {
        $links_array[] = "<a href='" . esc_url("mailto:support+ReceiptUploader@pepro.dev?subject=PeproDev Receipt Uploader") . "'>" . esc_html__("Support", "pepro-bacs-receipt-upload-for-woocommerce") . "</a>";
      }
      return $links_array;
    }
    public function plugin_action_links($actions, $plugin_file) {
      if (plugin_basename(__FILE__) === $plugin_file) {
        $actions["{$this->db_slug}_1"] = "<a href='" . esc_url($this->url) . "'>" . esc_html__("Setting", "pepro-bacs-receipt-upload-for-woocommerce") . "</a>";
        $actions["{$this->db_slug}_2"] = "<a href='" . esc_url(admin_url("admin.php?page=wc-settings&tab=email")) . "'>" . esc_html__("WC Emails", "pepro-bacs-receipt-upload-for-woocommerce") . "</a>";
      }
      return $actions;
    }
    public function valid_order_statuses_for_payment($status) {
      $status[] = "receipt-upload";
      $status[] = "receipt-approval";
      $status[] = "receipt-rejected";
      return $status;
    }
    public function send_email($email_class, $order_id) {
      if (!function_exists("WC")) return;
      $emails = WC()->mailer()->get_emails();
      if (!empty($emails[$email_class]) && method_exists($emails[$email_class], "trigger")) {
        $emails[$email_class]->trigger($order_id);
      }
    }
    public function trigger_receipt_uploaded_notification($order_id) {
      $this->send_email("WC_peproDev_UploadReceipt_Customer", $order_id);
      $this->send_email("WC_peproDev_UploadReceipt_Admin", $order_id);
    }
    public function trigger_receipt_approved_notification($order_id) {
      $this->send_email("WC_peproDev_ApprovedReceipt_Customer", $order_id);
      $this->send_email("WC_peproDev_ApprovedReceipt_Admin", $order_id);
    }
    public function trigger_receipt_rejected_notification($order_id) {
      $this->send_email("WC_peproDev_RejectedReceipt_Customer", $order_id);
      $this->send_email("WC_peproDev_RejectedReceipt_Admin", $order_id);
    }
    public function get_receipt_attachment_id($order) {
      $attachment_id = $order->get_meta("receipt_uploaded_attachment_id", true);
      if (!$attachment_id) $attachment_id = $order->get_meta("receipt_uplaoded_attachment_id", true);
      return absint($attachment_id);
    }
    public function get_receipt_status($order) {
      $status = $order->get_meta("receipt_upload_status", true);
      return in_array($status, array("upload", "pending", "approved", "rejected"), true) ? $status : "";
    }
    public function email_receipt_preview($order, $sent_to_admin = false, $plain_text = false, $email = null) {
      if ($plain_text || !is_a($order, "WC_Order")) return;
      echo $this->render_receipt_preview($order, true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render_receipt_preview.
    }
    public function render_receipt_preview($order, $is_email = false) {
      if (!$this->is_payment_method_allowed($order->get_payment_method())) return "";
      $attachment_id = $this->get_receipt_attachment_id($order);
      $status        = $this->get_receipt_status($order) ?: "upload";
      $date_uploaded = $order->get_meta("receipt_upload_date_uploaded", true);
      $note          = $order->get_meta("receipt_upload_admin_note", true);
      $src           = $attachment_id ? $this->generate_secure_preview_src($attachment_id, $order) : $this->defaultImg;
      ob_start();
      if ($is_email) echo "<br>";
      echo "<p><img src='" . esc_url($src) . "' class='receipt-preview " . esc_attr($status) . "' alt='" . esc_attr__("Receipt", "pepro-bacs-receipt-upload-for-woocommerce") . "' style='max-width: 200px; height: auto;' /></p>";
      if ("approved" !== $status && "pending" !== $status) {
        echo "<p><a href='" . esc_url($order->get_view_order_url()) . "#upload_receipt' target='_blank'>" . esc_html__("View Order Details", "pepro-bacs-receipt-upload-for-woocommerce") . "</a></p>";
      }
      if (!empty($date_uploaded)) {
        echo "<p>" . esc_html__("Date Uploaded: ", "pepro-bacs-receipt-upload-for-woocommerce") . "<bdi dir='ltr'>" . esc_html($this->format_date($date_uploaded)) . "</bdi></p>";
      }
      if ($note && ("approved" === $status || "rejected" === $status)) {
        echo "<p>" . esc_html__("Admin Note: ", "pepro-bacs-receipt-upload-for-woocommerce") . "<span>" . nl2br(esc_html($note)) . "</span></p>";
      }
      if ($is_email) echo "<br>";
      return ob_get_clean();
    }
    public function format_date($date) {
      $timestamp = is_numeric($date) ? (int) $date : strtotime((string) $date);
      if (!$timestamp) return (string) $date;
      return date_i18n("Y-m-d l H:i:s", $timestamp);
    }
    public function get_shortcode_order($order_id) {
      global $post, $wp;
      $order_id = absint($order_id);
      if (!$order_id && isset($wp->query_vars["order-received"])) $order_id = absint($wp->query_vars["order-received"]);
      if (!$order_id && isset($wp->query_vars["view-order"])) $order_id = absint($wp->query_vars["view-order"]);
      if (!$order_id && $post) $order_id = absint($post->ID);
      return $order_id ? wc_get_order($order_id) : false;
    }
    public function receipt_preview_shortcode($atts = array(), $content = "") {
      $atts  = shortcode_atts(array("order_id" => "", "email" => ""), $atts, "receipt-preview");
      $order = $this->get_shortcode_order($atts["order_id"]);
      if (!$order || !$this->can_access_order($order, $this->get_request_order_key())) return "";
      return $this->render_receipt_preview($order, "yes" === strtolower((string) $atts["email"]));
    }
    public function receipt_form_shortcode($atts = array(), $content = "") {
      $atts      = shortcode_atts(array("order_id" => "", "email" => ""), $atts, "receipt-form");
      $is_email  = "yes" === strtolower((string) $atts["email"]);
      $order     = $this->get_shortcode_order($atts["order_id"]);
      $order_key = $this->get_request_order_key();
      if (!$order || !$this->can_access_order($order, $order_key)) return "";
      if (!$this->is_payment_method_allowed($order->get_payment_method())) return "";
      $attachment_id = $this->get_receipt_attachment_id($order);
      $status        = $this->get_receipt_status($order) ?: "upload";
      $status_text   = $this->get_status($status);
      $date_uploaded = $order->get_meta("receipt_upload_date_uploaded", true);
      $note          = $order->get_meta("receipt_upload_admin_note", true);
      $src           = $attachment_id ? $this->generate_secure_preview_src($attachment_id, $order) : $this->defaultImg;
      wp_enqueue_style("upload-receipt.css", "{$this->assets_url}frontend/css/wc-receipt.css", array(), $this->version);
      wp_register_script("upload-receipt.js", "{$this->assets_url}frontend/js/upload-receipt.js", array("jquery"), $this->version, true);
      wp_localize_script("upload-receipt.js", "_upload_receipt", array(
        "ajax_url"      => admin_url("admin-ajax.php"),
        "order_id"      => $order->get_id(),
        "order_key"     => $order_key,
        "max_size"      => $this->_allowed_file_size(),
        // translators: ## is file size in MB
        "max_alert"     => _x("Error! File size should be less than ## MB", "js-translate", "pepro-bacs-receipt-upload-for-woocommerce"),
        "loading"       => _x("Please wait ...", "js-translate", "pepro-bacs-receipt-upload-for-woocommerce"),
        // translators: ## is upload progress percentage
        "precent"       => _x("Please wait, Uploading ## % ...", "js-translate", "pepro-bacs-receipt-upload-for-woocommerce"),
        "done"          => _x("Uploading Done Successfully", "js-translate", "pepro-bacs-receipt-upload-for-woocommerce"),
        "select_file"   => _x("Error! You should choose a file first.", "js-translate", "pepro-bacs-receipt-upload-for-woocommerce"),
        "redirect_url"  => esc_url_raw(get_option("peprobacsru_redirect_after_upload", "")),
        "unknown_error" => _x("Unknown Server Error Occured! Try again.", "js-translate", "pepro-bacs-receipt-upload-for-woocommerce"),
      ));
      wp_enqueue_script("upload-receipt.js");
      ob_start();
      ?>
      <div class="peprodev_woocommerce_receipt_uploader shortcode_wrapper" id="upload_receipt">
        <h2 class="woocommerce-order-details__title upload_receipt"><?php echo esc_html($this->get_form_title()); ?></h2>
        <?php if (!$is_email) echo wp_kses_post(do_shortcode($this->html_before)); ?>
        <table class="woocommerce-table woocommerce-table--upload-receipt upload_receipt">
          <tbody>
            <tr>
              <th scope="row"><?php echo esc_html__("Current receipt: ", "pepro-bacs-receipt-upload-for-woocommerce"); ?></th>
              <td class="receipt-img-preview">
                <img src="<?php echo esc_url($src); ?>" title="<?php echo esc_attr($status_text); ?>" class="receipt-preview <?php echo esc_attr($status); ?>" alt="<?php echo esc_attr__("Receipt", "pepro-bacs-receipt-upload-for-woocommerce"); ?>" />
                <p class="receipt-status <?php echo esc_attr($status); ?>"><?php echo esc_html($status_text); ?></p>
              </td>
            </tr>
            <?php if ($this->can_upload_receipt($order)) { ?>
              <tr>
                <th scope="row"><?php echo esc_html__("Upload Receipt: ", "pepro-bacs-receipt-upload-for-woocommerce"); ?></th>
                <td class="receipt-img-upload">
                  <form id="uploadreceiptfileimage" enctype="multipart/form-data">
                    <?php wp_nonce_field("{$this->db_slug}_upload_{$order->get_id()}", "uniqnonce"); ?>
                    <div class="receipt-upload-fields">
                      <input type="file" id="receipt-file" name="upload" autocomplete="off" required accept="<?php echo esc_attr(implode(",", array_values(array_unique($this->get_allowed_upload_mimes())))); ?>" />
                      <button class="start-upload button" type="button"><?php echo esc_html__("Upload Receipt", "pepro-bacs-receipt-upload-for-woocommerce"); ?></button>
                    </div>
                  </form>
                </td>
              </tr>
            <?php } ?>
          </tbody>
          <tfoot>
            <tr class="date-uploaded <?php echo !empty($date_uploaded) ? "show" : "hide"; ?>">
              <th scope="row"><?php echo esc_html__("Date Uploaded: ", "pepro-bacs-receipt-upload-for-woocommerce"); ?></th>
              <td class="receipt-upload-date">
                <?php if (!empty($date_uploaded)) { ?>
                  <bdi dir="ltr"><?php echo esc_html($this->format_date($date_uploaded)); ?></bdi>
                <?php } ?>
              </td>
            </tr>
            <?php if ($note && ("approved" === $status || "rejected" === $status)) { ?>
              <tr>
                <th scope="row"><?php echo esc_html__("Admin Note: ", "pepro-bacs-receipt-upload-for-woocommerce"); ?></th>
                <td class="receipt-admin-note"><span><?php echo nl2br(esc_html($note)); ?></span></td>
              </tr>
            <?php } ?>
          </tfoot>
        </table>
        <?php if (!$is_email) echo wp_kses_post(do_shortcode($this->html_after)); ?>
      </div>
      <?php
      return ob_get_clean();
    }
    public function get_form_title() {
      $title = trim((string) get_option("peprobacsru_form_title", ""));
      return "" !== $title ? $title : __("Upload receipt", "pepro-bacs-receipt-upload-for-woocommerce");
    }
    public function generate_secure_preview_src($id = 0, $order = null, $email = false) {
      if (empty($id) || !$order || !is_a($order, "WC_Order")) return $this->defaultImg;
      return $this->get_receipt_url($id, $order->get_id(), "thumbnail");
    }
    public function get_receipt_token($attachment_id, $order_id) {
      return substr(hash_hmac("sha256", "receipt|" . absint($attachment_id) . "|" . absint($order_id), wp_salt("auth")), 0, 32);
    }
    public function get_receipt_url($attachment_id, $order_id, $size = "thumbnail") {
      return add_query_arg(array(
        "pepro_receipt" => absint($attachment_id),
        "receipt_order" => absint($order_id),
        "receipt_size"  => "full" === $size ? "full" : "thumbnail",
        "receipt_token" => $this->get_receipt_token($attachment_id, $order_id),
      ), home_url("/"));
    }
    public function current_user_can_manage_receipts() {
      return current_user_can("manage_woocommerce") || current_user_can("edit_shop_orders");
    }
    public function attachment_belongs_to_order($attachment_id, $order) {
      $attachment_id = absint($attachment_id);
      if (!$attachment_id || !$order) return false;
      if ((int) get_post_meta($attachment_id, "_attached_order", true) === (int) $order->get_id()) return true;
      return $this->get_receipt_attachment_id($order) === $attachment_id;
    }
    public function maybe_serve_receipt() {
      // phpcs:disable WordPress.Security.NonceVerification.Recommended
      if (empty($_GET["pepro_receipt"])) return;
      $attachment_id = absint($_GET["pepro_receipt"]);
      $order_id      = isset($_GET["receipt_order"]) ? absint($_GET["receipt_order"]) : 0;
      $token         = isset($_GET["receipt_token"]) ? sanitize_text_field(wp_unslash($_GET["receipt_token"])) : "";
      $size          = isset($_GET["receipt_size"]) && "full" === $_GET["receipt_size"] ? "full" : "thumbnail";
      // phpcs:enable WordPress.Security.NonceVerification.Recommended
      $order = $order_id ? wc_get_order($order_id) : false;
      if (!$attachment_id || !$order || !hash_equals($this->get_receipt_token($attachment_id, $order_id), $token) || !$this->attachment_belongs_to_order($attachment_id, $order)) {
        wp_die(esc_html__("Unauthorized Access!", "pepro-bacs-receipt-upload-for-woocommerce"), 403);
      }
      $file = get_attached_file($attachment_id);
      if ("thumbnail" === $size) {
        $thumb = image_get_intermediate_size($attachment_id, "thumbnail");
        if ($thumb && !empty($thumb["path"])) {
          $uploads = wp_get_upload_dir();
          $file    = path_join($uploads["basedir"], $thumb["path"]);
        } elseif (!wp_attachment_is_image($attachment_id)) {
          wp_safe_redirect($this->defaultImg);
          exit;
        }
      }
      $filetype = wp_check_filetype((string) $file);
      if (!$file || !file_exists($file) || !in_array($filetype["type"], $this->get_safe_mimes(), true)) {
        wp_die(esc_html__("File not found!", "pepro-bacs-receipt-upload-for-woocommerce"), 404);
      }
      $content = $this->filesystem()->get_contents($file);
      if (false === $content) wp_die(esc_html__("File not found!", "pepro-bacs-receipt-upload-for-woocommerce"), 404);
      nocache_headers();
      header("Content-Type: " . $filetype["type"]);
      header("Content-Length: " . strlen($content));
      header("Content-Disposition: inline; filename=\"" . sanitize_file_name(wp_basename($file)) . "\"");
      header("X-Content-Type-Options: nosniff");
      header("X-Robots-Tag: noindex, nofollow");
      echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary file output.
      exit;
    }
    public function filesystem() {
      require_once ABSPATH . "wp-admin/includes/class-wp-filesystem-base.php";
      require_once ABSPATH . "wp-admin/includes/class-wp-filesystem-direct.php";
      return new WP_Filesystem_Direct(null);
    }
    public function get_receipt_order_id($attachment_id) {
      return (int) get_post_meta($attachment_id, "_attached_order", true);
    }
    public function filter_receipt_attachment_url($url, $attachment_id) {
      $order_id = $this->get_receipt_order_id($attachment_id);
      if (!$order_id) return $url;
      return $this->current_user_can_manage_receipts() ? $this->get_receipt_url($attachment_id, $order_id, "full") : $this->defaultImg;
    }
    public function filter_receipt_image_downsize($downsize, $attachment_id, $size) {
      $order_id = $this->get_receipt_order_id($attachment_id);
      if (!$order_id) return $downsize;
      if (!$this->current_user_can_manage_receipts()) return array($this->defaultImg, 150, 150, false);
      $meta  = wp_get_attachment_metadata($attachment_id);
      $thumb = "full" !== $size ? image_get_intermediate_size($attachment_id, $size) : false;
      if ($thumb) return array($this->get_receipt_url($attachment_id, $order_id, "thumbnail"), $thumb["width"], $thumb["height"], true);
      $width  = !empty($meta["width"]) ? $meta["width"] : 150;
      $height = !empty($meta["height"]) ? $meta["height"] : 150;
      return array($this->get_receipt_url($attachment_id, $order_id, "full"), $width, $height, false);
    }
    public function filter_receipt_image_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
      return $this->get_receipt_order_id($attachment_id) ? false : $sources;
    }
    public function block_receipt_attachment_page() {
      if (!is_attachment() || !$this->get_receipt_order_id(get_queried_object_id()) || $this->current_user_can_manage_receipts()) return;
      global $wp_query;
      $wp_query->set_404();
      status_header(404);
      nocache_headers();
    }
    public function admin_menu() {
      add_submenu_page("woocommerce", $this->title, __("Receipt Upload", "pepro-bacs-receipt-upload-for-woocommerce"), "manage_woocommerce", $this->url);
      $v230 = get_option("peprobacsru_allowed_gatewawys", null);
      if (!empty($v230)) {
        update_option("peprobacsru_allowed_gateways", $v230);
        delete_option("peprobacsru_allowed_gatewawys");
      }
    }
    public function register_email($emails) {
      require_once "{$this->plugin_dir}include/class-wc-email-receipt-base.php";
      $classes = array(
        "WC_peproDev_UploadReceipt_Admin"      => "class-wc-email-admin-uploaded.php",
        "WC_peproDev_ApprovedReceipt_Admin"    => "class-wc-email-admin-approved.php",
        "WC_peproDev_RejectedReceipt_Admin"    => "class-wc-email-admin-rejected.php",
        "WC_peproDev_UploadReceipt_Customer"   => "class-wc-email-customer-uploaded.php",
        "WC_peproDev_ApprovedReceipt_Customer" => "class-wc-email-customer-approved.php",
        "WC_peproDev_RejectedReceipt_Customer" => "class-wc-email-customer-rejected.php",
      );
      foreach ($classes as $class => $file) {
        require_once "{$this->plugin_dir}include/{$file}";
        $emails[$class] = new $class();
      }
      return $emails;
    }
    public function get_wc_gateways() {
      $gateways = array();
      foreach (WC()->payment_gateways->payment_gateways() as $gateway_id => $gateway) {
        $gateways[$gateway_id] = wp_strip_all_tags($gateway->get_method_title());
      }
      return $gateways;
    }
    public function add_settings_page($pages) {
      require_once "{$this->plugin_dir}include/class-settings-page.php";
      $pages[] = new peproDev_UploadReceipt_Settings($this);
      return $pages;
    }
    public function get_folder_name() {
      return $this->folder_name;
    }
    public function get_protection_status($force = false) {
      $status = get_transient("peprobacsru_protection_status");
      if (!$force && false !== $status) return $status;
      $this->protect_receipt_dir();
      $uploads  = wp_get_upload_dir();
      $response = wp_remote_get($uploads["baseurl"] . "/" . $this->folder_name . "/protection-check.txt", array("timeout" => 10, "redirection" => 0));
      if (is_wp_error($response)) {
        $status = "unknown";
      } else {
        $status = 200 === (int) wp_remote_retrieve_response_code($response) ? "exposed" : "protected";
      }
      set_transient("peprobacsru_protection_status", $status, HOUR_IN_SECONDS);
      return $status;
    }
    public function admin_enqueue_scripts($hook) {
      $screen = function_exists("get_current_screen") ? get_current_screen() : null;
      $ids    = array("edit-shop_order", "shop_order", "woocommerce_page_wc-orders", "upload", "woocommerce_page_wc-settings");
      if ($screen && in_array($screen->id, $ids, true)) {
        wp_enqueue_style("wc-orders.css", "{$this->assets_url}backend/css/wc-orders.css", array(), $this->version);
      }
    }
    public function column_header($columns) {
      $new_columns = array();
      foreach ($columns as $column_name => $column_info) {
        $new_columns[$column_name] = $column_info;
        if ("order_status" === $column_name) {
          $new_columns["wcuploadrcp"] = __("Payment Receipt", "pepro-bacs-receipt-upload-for-woocommerce");
        }
      }
      if (!isset($new_columns["wcuploadrcp"])) {
        $new_columns["wcuploadrcp"] = __("Payment Receipt", "pepro-bacs-receipt-upload-for-woocommerce");
      }
      return $new_columns;
    }
    public function column_content($column, $order_id) {
      if ("wcuploadrcp" !== $column) return;
      $order = wc_get_order($order_id);
      if (!$order) return;
      if (!$this->is_payment_method_allowed($order->get_payment_method())) {
        echo esc_html($order->get_payment_method_title());
        return;
      }
      $attachment_id = $this->get_receipt_attachment_id($order);
      $status        = $this->get_receipt_status($order) ?: "upload";
      $status_text   = $this->get_status($status);
      if ($attachment_id) {
        echo "<a href='" . esc_url($this->get_receipt_url($attachment_id, $order->get_id(), "full")) . "' target='_blank'>
          <img src='" . esc_url($this->generate_secure_preview_src($attachment_id, $order)) . "' class='receipt-preview " . esc_attr($status) . "' alt='" . esc_attr($status_text) . "' title='" . esc_attr($status_text) . "' />
        </a>";
      } else {
        echo "<span class='receipt-awaiting-upload'>" . esc_html__("Awaiting Upload", "pepro-bacs-receipt-upload-for-woocommerce") . "</span>";
      }
    }
    public function _allowed_file_types($file_mime) {
      $allowed = in_array($file_mime, $this->_allowed_file_types_array(), true) && in_array($file_mime, $this->get_safe_mimes(), true);
      $allowed = apply_filters_deprecated("pepro_upload_receipt_allowed_file_mimes", array($allowed, $file_mime), "2.12.0", "peprodev_uploadreceipt_allowed_file_mimes");
      return apply_filters("peprodev_uploadreceipt_allowed_file_mimes", $allowed, $file_mime);
    }
    public function get_safe_mimes() {
      return apply_filters("peprodev_uploadreceipt_safe_mimes", array("image/jpeg", "image/png", "image/gif", "image/webp", "image/bmp", "image/avif", "image/heic", "application/pdf"));
    }
    public function get_allowed_upload_mimes() {
      $allowed = array_intersect($this->_allowed_file_types_array(), $this->get_safe_mimes());
      $mimes   = array();
      foreach (wp_get_mime_types() as $ext => $mime) {
        if (in_array($mime, $allowed, true)) $mimes[$ext] = $mime;
      }
      return $mimes;
    }
    public function _allowed_file_types_array() {
      $mimes = get_option("peprobacsru_allowed_file_types", "image/jpeg" . PHP_EOL . "image/png" . PHP_EOL . "application/pdf");
      $mimes = is_array($mimes) ? $mimes : explode("\n", (string) $mimes);
      return array_values(array_filter(array_map("trim", $mimes)));
    }
    public function is_payment_method_allowed($method) {
      return in_array($method, (array) get_option("peprobacsru_allowed_gateways", array("bacs")), true);
    }
    public function _allowed_file_size() {
      $size = apply_filters_deprecated("pepro_upload_receipt_max_upload_size", array(get_option("peprobacsru_allowed_file_size", 4)), "2.12.0", "peprodev_uploadreceipt_max_upload_size");
      return (float) apply_filters("peprodev_uploadreceipt_max_upload_size", $size);
    }
    public function get_meta($meta = "", $post_id = false) {
      global $post;
      if (!$post_id) $post_id = $post ? $post->ID : false;
      $order = wc_get_order($post_id);
      $field = $order ? $order->get_meta($meta, true) : get_post_meta($post_id, $meta, true);
      if (empty($field)) return false;
      return is_array($field) ? stripslashes_deep($field) : stripslashes(wp_kses_decode_entities($field));
    }
    public function receipt_upload_add_meta_box() {
      $screen = $this->is_hpos_enabled() && function_exists("wc_get_page_screen_id") ? wc_get_page_screen_id("shop-order") : "shop_order";
      add_meta_box("receipt_upload-receipt-upload", __("Upload Receipt", "pepro-bacs-receipt-upload-for-woocommerce"), array($this, "receipt_upload_html"), $screen, "side", "high");
    }
    public function get_order_receipts($order_id) {
      return get_posts(array(
        "post_type"      => "attachment",
        "post_status"    => array("inherit", "private"),
        "posts_per_page" => 20,
        "meta_key"       => "_attached_order", // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        "meta_value"     => absint($order_id), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        "orderby"        => "ID",
        "order"          => "DESC",
      ));
    }
    public function receipt_upload_html($post_or_order) {
      $order = is_a($post_or_order, "WC_Order") ? $post_or_order : wc_get_order(isset($post_or_order->ID) ? $post_or_order->ID : $post_or_order);
      if (!$order) return;
      $order_id = $order->get_id();
      wp_nonce_field("_receipt_upload_nonce", "receipt_upload_nonce");
      wp_enqueue_media();
      wp_enqueue_script("wc-orders.js", "{$this->assets_url}backend/js/wc-orders.js", array("jquery"), $this->version, true);
      $attachment_id = $this->get_receipt_attachment_id($order);
      $status        = $this->get_receipt_status($order) ?: "upload";
      $date_uploaded = $order->get_meta("receipt_upload_date_uploaded", true);
      $src           = $attachment_id ? $this->generate_secure_preview_src($attachment_id, $order) : $this->defaultImg;
      ?>
      <div class="receipt-preview-wrap">
        <img data-def="<?php echo esc_url($this->defaultImg); ?>" id="change_receipt_attachment_id" title="<?php echo esc_attr__("Click to change", "pepro-bacs-receipt-upload-for-woocommerce"); ?>" src="<?php echo esc_url($src); ?>" alt="" />
        <p class="hidden"><input title="<?php echo esc_attr__("Receipt Attachment ID", "pepro-bacs-receipt-upload-for-woocommerce"); ?>" type="text" name="receipt_uploaded_attachment_id" id="receipt_uploaded_attachment_id" value="<?php echo esc_attr($attachment_id ? $attachment_id : ""); ?>"></p>
      </div>
      <?php if ($attachment_id) { ?>
        <p><a href="<?php echo esc_url($this->get_receipt_url($attachment_id, $order_id, "full")); ?>" target="_blank" class="button button-secondary widebutton"><span class="dashicons dashicons-external"></span> <?php echo esc_html__("Open Full Receipt", "pepro-bacs-receipt-upload-for-woocommerce"); ?></a></p>
      <?php } ?>
      <p><span><?php echo esc_html__("Uploaded at:", "pepro-bacs-receipt-upload-for-woocommerce"); ?> <date><?php echo esc_html($date_uploaded); ?></date></span></p>
      <p>
        <a href="#" class="button button-secondary widebutton changefile"><span class="dashicons dashicons-format-image"></span> <?php echo esc_html__("Change Receipt Image", "pepro-bacs-receipt-upload-for-woocommerce"); ?></a>
        <a href="#" class="button button-secondary widebutton removefile"><span class="dashicons dashicons-editor-unlink"></span> <?php echo esc_html__("Unlink Receipt Image", "pepro-bacs-receipt-upload-for-woocommerce"); ?></a>
        <a href="#" class="button button-secondary widebutton changedate" id="receipt_upload_date_btn"><span class="dashicons dashicons-calendar-alt"></span> <?php echo esc_html__("Change Upload Date", "pepro-bacs-receipt-upload-for-woocommerce"); ?></a>
      </p>
      <p><input type="text" dir="ltr" style="display: none;" autocomplete="off" name="receipt_upload_date_uploaded" id="receipt_upload_date_uploaded" value="<?php echo esc_attr($date_uploaded); ?>"></p>
      <p>
        <label for="receipt_upload_status"><?php echo esc_html__("Receipt Approval Status", "pepro-bacs-receipt-upload-for-woocommerce"); ?></label>
        <select autocomplete="off" id="receipt_upload_status" name="receipt_upload_status" class="<?php echo esc_attr($status); ?>">
          <?php foreach (array("upload" => __("Awaiting Upload", "pepro-bacs-receipt-upload-for-woocommerce"), "pending" => __("Pending", "pepro-bacs-receipt-upload-for-woocommerce"), "approved" => __("Approved", "pepro-bacs-receipt-upload-for-woocommerce"), "rejected" => __("Rejected", "pepro-bacs-receipt-upload-for-woocommerce")) as $key => $label) { ?>
            <option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>><?php echo esc_html($label); ?></option>
          <?php } ?>
        </select>
      </p>
      <p>
        <label for="receipt_upload_admin_note"><?php echo esc_html__("Admin Note", "pepro-bacs-receipt-upload-for-woocommerce"); ?></label>
        <textarea rows="5" autocomplete="off" name="receipt_upload_admin_note" id="receipt_upload_admin_note"><?php echo esc_textarea($order->get_meta("receipt_upload_admin_note", true)); ?></textarea>
      </p>
      <?php
      $all_previous = $this->get_order_receipts($order_id);
      if (!empty($all_previous)) {
        echo "<hr><p>" . esc_html__("Previously Uploaded Receipts", "pepro-bacs-receipt-upload-for-woocommerce") . "</p><div class='prev-items-uploaded'>";
        foreach ($all_previous as $attached) {
          $thumb = $this->get_receipt_url($attached->ID, $order_id, "thumbnail");
          $full  = $this->get_receipt_url($attached->ID, $order_id, "full");
          echo "<div class='prev-uploaded-item'>
            <a href='" . esc_url(admin_url("upload.php?item={$attached->ID}")) . "' target='_blank'><img src='" . esc_url($thumb) . "' width='75' alt='' /></a>
            <a href='" . esc_url($full) . "' target='_blank' class='button button-small'><span class='dashicons dashicons-external'></span> " . esc_html__("View", "pepro-bacs-receipt-upload-for-woocommerce") . "</a>
          </div>";
        }
        echo "</div>";
      }
      ?>
      <p><small class="receipt-settings-link"><a target="_blank" href="<?php echo esc_url($this->url); ?>"><?php echo esc_html__("Change Upload Receipt Plugin Setting", "pepro-bacs-receipt-upload-for-woocommerce"); ?></a></small></p>
      <?php
    }
    public function receipt_upload_save($order_id) {
      if (!isset($_POST["receipt_upload_nonce"]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST["receipt_upload_nonce"])), "_receipt_upload_nonce")) return;
      if (!$this->current_user_can_manage_receipts() || (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE)) return;
      $order = wc_get_order($order_id);
      if (!$order) return;
      $prev = $this->get_receipt_status($order);
      $new  = $prev;
      if (isset($_POST["receipt_uploaded_attachment_id"])) {
        $attachment_id = absint($_POST["receipt_uploaded_attachment_id"]);
        $order->update_meta_data("receipt_uploaded_attachment_id", $attachment_id ? $attachment_id : "");
      }
      if (isset($_POST["receipt_upload_date_uploaded"])) {
        $order->update_meta_data("receipt_upload_date_uploaded", sanitize_text_field(wp_unslash($_POST["receipt_upload_date_uploaded"])));
      }
      if (isset($_POST["receipt_upload_admin_note"])) {
        $order->update_meta_data("receipt_upload_admin_note", sanitize_textarea_field(wp_unslash($_POST["receipt_upload_admin_note"])));
      }
      if (isset($_POST["receipt_upload_status"])) {
        $posted = sanitize_key(wp_unslash($_POST["receipt_upload_status"]));
        if (in_array($posted, array("upload", "pending", "approved", "rejected"), true)) $new = $posted;
      }
      $posted_data = map_deep(wp_unslash($_POST), "sanitize_textarea_field");
      do_action("peprodev_uploadreceipt_save_receipt", $order->get_id(), $order, $posted_data);
      do_action("peprodev_uploadreceipt_{$prev}_to_{$new}", $order->get_id(), $order, $posted_data);
      $order->update_meta_data("receipt_upload_status", $new);
      $note = "<strong>" . esc_html__("Order Status Changed by PeproDev Upload Receipt", "pepro-bacs-receipt-upload-for-woocommerce") . "</strong><br>";
      if ($new !== $prev) {
        $order->update_meta_data("receipt_upload_last_change", current_time("Y-m-d H:i:s"));
        $map = array(
          "approved" => $this->status_receipt_approved,
          "rejected" => $this->status_receipt_rejected,
          "upload"   => $this->status_receipt_awaiting_upload,
          "pending"  => $this->status_receipt_awaiting_approval,
        );
        if (!empty($map[$new]) && "none" !== $map[$new]) {
          $order->set_status($map[$new], $note, true);
        }
      }
      $order->save();
      if ($new !== $prev) {
        do_action("peprodev_uploadreceipt_receipt_status_changed", $order->get_id(), $order, $prev, $new);
        switch ($new) {
          case "approved":
            do_action("woocommerce_receipt_approved_notification", $order->get_id());
            do_action("peprodev_uploadreceipt_receipt_approved", $order->get_id(), $order, $prev, $new);
            break;
          case "rejected":
            do_action("woocommerce_receipt_rejected_notification", $order->get_id());
            do_action("peprodev_uploadreceipt_receipt_rejected", $order->get_id(), $order, $prev, $new);
            break;
          case "upload":
            do_action("woocommerce_receipt_await_upload_notification", $order->get_id());
            do_action("peprodev_uploadreceipt_receipt_awaiting_upload", $order->get_id(), $order, $prev, $new);
            break;
          case "pending":
            do_action("woocommerce_receipt_pending_approval_notification", $order->get_id());
            do_action("peprodev_uploadreceipt_receipt_awaiting_approval", $order->get_id(), $order, $prev, $new);
            break;
        }
      }
      if (isset($_POST["receipt_upload_admin_note"])) {
        do_action("peprodev_uploadreceipt_receipt_attached_note", $order->get_id(), $order, $prev, $new);
      }
    }
    public function get_status($status) {
      switch ($status) {
        case "upload":
          return __("Awaiting Upload", "pepro-bacs-receipt-upload-for-woocommerce");
        case "pending":
          return __("Pending Approval", "pepro-bacs-receipt-upload-for-woocommerce");
        case "approved":
          return __("Receipt Approved", "pepro-bacs-receipt-upload-for-woocommerce");
        case "rejected":
          return __("Receipt Rejected", "pepro-bacs-receipt-upload-for-woocommerce");
        default:
          return __("Unknown Status", "pepro-bacs-receipt-upload-for-woocommerce");
      }
    }
    public function woocommerce_thankyou($order_id) {
      $order = $order_id ? wc_get_order($order_id) : false;
      if (!$order || !$this->is_payment_method_allowed($order->get_payment_method())) return;
      if ($order->get_meta("receipt_upload_status", true)) return;
      if (!empty($this->status_order_placed) && "none" !== $this->status_order_placed) {
        $order->set_status($this->status_order_placed, "<strong>" . esc_html__("Order Status Changed by PeproDev Upload Receipt", "pepro-bacs-receipt-upload-for-woocommerce") . "</strong><br>");
      }
      $order->update_meta_data("receipt_upload_status", "upload");
      $order->update_meta_data("peprodev_uploadreceipt_action_run_once", "yes");
      $order->save();
      do_action("peprodev_uploadreceipt_order_placed", $order);
    }
    public function order_details_before_order_table($order) {
      $order = wc_get_order($order);
      if (!$order) return;
      if ($order->has_status($this->get_show_on_statuses())) {
        echo $this->receipt_form_shortcode(array("order_id" => $order->get_id())); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in receipt_form_shortcode.
      }
    }
    public function add_wc_prebuy_status() {
      register_post_status("wc-receipt-upload", array(
        "label"                     => __("Awaiting Upload", "pepro-bacs-receipt-upload-for-woocommerce"),
        "public"                    => false,
        "exclude_from_search"       => false,
        "show_in_admin_all_list"    => true,
        "show_in_admin_status_list" => true,
        // translators: %s: number of orders
        "label_count"               => _n_noop("Awaiting Receipt Upload (%s)", "Awaiting Receipts Upload (%s)", "pepro-bacs-receipt-upload-for-woocommerce"),
      ));
      register_post_status("wc-receipt-approval", array(
        "label"                     => __("Awaiting Approval", "pepro-bacs-receipt-upload-for-woocommerce"),
        "public"                    => false,
        "exclude_from_search"       => false,
        "show_in_admin_all_list"    => true,
        "show_in_admin_status_list" => true,
        // translators: %s: number of orders
        "label_count"               => _n_noop("Awaiting Receipt Approval (%s)", "Awaiting Receipts Approval (%s)", "pepro-bacs-receipt-upload-for-woocommerce"),
      ));
      register_post_status("wc-receipt-rejected", array(
        "label"                     => _x("Receipt Rejected", "pst", "pepro-bacs-receipt-upload-for-woocommerce"),
        "public"                    => false,
        "exclude_from_search"       => false,
        "show_in_admin_all_list"    => true,
        "show_in_admin_status_list" => true,
        // translators: %s: number of orders
        "label_count"               => _n_noop("Receipt Rejected (%s)", "Receipt Rejected (%s)", "pepro-bacs-receipt-upload-for-woocommerce"),
      ));
    }
    public function add_wc_order_statuses($order_statuses) {
      $new_order_statuses = array();
      foreach ($order_statuses as $key => $status) {
        $new_order_statuses[$key] = $status;
        if ("wc-pending" === $key) {
          $new_order_statuses["wc-receipt-upload"]   = _x("Awaiting Receipt Upload", "pst", "pepro-bacs-receipt-upload-for-woocommerce");
          $new_order_statuses["wc-receipt-approval"] = _x("Awaiting Receipt Approval", "pst", "pepro-bacs-receipt-upload-for-woocommerce");
          $new_order_statuses["wc-receipt-rejected"] = _x("Receipt Rejected", "pst", "pepro-bacs-receipt-upload-for-woocommerce");
        }
      }
      return $new_order_statuses;
    }
    public function get_request_order_key() {
      return isset($_GET["key"]) ? sanitize_text_field(wp_unslash($_GET["key"])) : ""; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }
    public function can_access_order($order, $order_key = "") {
      if (!$order || !is_a($order, "WC_Order")) return false;
      if ($this->current_user_can_manage_receipts()) return true;
      $customer_id = (int) $order->get_customer_id();
      if ($customer_id > 0) return is_user_logged_in() && get_current_user_id() === $customer_id;
      return !empty($order_key) && $order->key_is_valid($order_key);
    }
    public function get_default_show_on_statuses() {
      return array("wc-pending", "wc-on-hold", "wc-processing", "wc-receipt-upload", "wc-receipt-approval", "wc-receipt-rejected");
    }
    public function get_show_on_statuses() {
      $statuses = get_option("peprobacsru_show_on_statuses", $this->get_default_show_on_statuses());
      return array_map(function ($i) { return str_replace("wc-", "", $i); }, (array) $statuses);
    }
    public function can_upload_receipt($order) {
      if (!$this->is_payment_method_allowed($order->get_payment_method())) return false;
      if (in_array($this->get_receipt_status($order), array("approved", "pending"), true)) return false;
      return $order->has_status($this->get_show_on_statuses());
    }
    public function handel_ajax_req() {
      $order_id  = isset($_POST["order"]) ? absint($_POST["order"]) : 0;
      $order_key = isset($_POST["order_key"]) ? sanitize_text_field(wp_unslash($_POST["order_key"])) : "";
      $nonce     = isset($_POST["nonce"]) ? sanitize_text_field(wp_unslash($_POST["nonce"])) : "";
      if (!$order_id || !wp_verify_nonce($nonce, "{$this->db_slug}_upload_{$order_id}")) {
        wp_send_json_error(array("msg" => __("Unauthorized Access!", "pepro-bacs-receipt-upload-for-woocommerce")), 403);
      }
      $order = wc_get_order($order_id);
      if (!$order || !$this->can_access_order($order, $order_key)) {
        wp_send_json_error(array("msg" => __("Unauthorized Access!", "pepro-bacs-receipt-upload-for-woocommerce")), 403);
      }
      if (!$this->can_upload_receipt($order)) {
        wp_send_json_error(array("msg" => __("Uploading receipt is not allowed for this order.", "pepro-bacs-receipt-upload-for-woocommerce")), 403);
      }
      if (empty($_FILES["file"]["size"]) || empty($_FILES["file"]["tmp_name"]) || empty($_FILES["file"]["name"])) {
        wp_send_json_error(array("msg" => __("There was an error uploading your file.", "pepro-bacs-receipt-upload-for-woocommerce")));
      }
      require_once ABSPATH . "wp-admin/includes/image.php";
      require_once ABSPATH . "wp-admin/includes/file.php";
      require_once ABSPATH . "wp-admin/includes/media.php";
      $tmp_name  = $_FILES["file"]["tmp_name"]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- server generated temp path.
      $file_name = sanitize_file_name(wp_unslash($_FILES["file"]["name"]));
      $file_size = absint($_FILES["file"]["size"]);
      $mimes     = $this->get_allowed_upload_mimes();
      $filetype  = wp_check_filetype_and_ext($tmp_name, $file_name, $mimes);
      $real_mime = !empty($filetype["type"]) ? $filetype["type"] : "";
      if (!$real_mime || !is_uploaded_file($tmp_name) || !$this->_allowed_file_types($real_mime) || $file_size > $this->_allowed_file_size() * 1024 * 1024) {
        wp_send_json_error(array("msg" => __("There was an error uploading your file. Please check file type and size.", "pepro-bacs-receipt-upload-for-woocommerce")));
      }
      $_FILES["file"]["name"] = sprintf("receipt-%d-%s.%s", $order_id, wp_generate_password(24, false, false), !empty($filetype["ext"]) ? $filetype["ext"] : "jpg");
      $post_data = array(
        "post_status" => "private",
        // translators: %s: order number
        "post_title"  => sprintf(__("Receipt for order #%s", "pepro-bacs-receipt-upload-for-woocommerce"), $order->get_order_number()),
      );
      add_filter("upload_dir", array($this, "change_receipt_upload_dir"));
      $attachment_id = media_handle_upload("file", $this->is_hpos_enabled() ? 0 : $order_id, $post_data, array("test_form" => false, "mimes" => $mimes));
      remove_filter("upload_dir", array($this, "change_receipt_upload_dir"));
      if (is_wp_error($attachment_id) || !is_numeric($attachment_id)) {
        wp_send_json_error(array("msg" => is_wp_error($attachment_id) ? $attachment_id->get_error_message() : __("There was an error uploading your file.", "pepro-bacs-receipt-upload-for-woocommerce")));
      }
      $datetime = current_time("Y-m-d H:i:s");
      update_post_meta($attachment_id, "_attached_order", $order_id);
      $order->update_meta_data("receipt_uploaded_attachment_id", $attachment_id);
      $order->update_meta_data("receipt_upload_date_uploaded", $datetime);
      $order->update_meta_data("receipt_upload_status", "pending");
      if (!empty($this->status_receipt_awaiting_approval) && "none" !== $this->status_receipt_awaiting_approval) {
        $order->set_status($this->status_receipt_awaiting_approval, "<strong>" . esc_html__("Order Status Changed by PeproDev Upload Receipt", "pepro-bacs-receipt-upload-for-woocommerce") . "</strong><br>");
      }
      $order->add_order_note("<strong>" . esc_html($this->title) . "</strong><br>" . sprintf(
        // translators: %s: receipt image
        esc_html__("Customer uploaded payment receipt image. %s", "pepro-bacs-receipt-upload-for-woocommerce"),
        "<br><a target='_blank' href='" . esc_url($this->get_receipt_url($attachment_id, $order_id, "full")) . "'><img src='" . esc_url($this->get_receipt_url($attachment_id, $order_id, "thumbnail")) . "' style='height: 50px; min-width: 50px; border-radius: 4px; border: 1px solid #eee;' alt='' /></a>"
      ));
      $order->save();
      do_action("woocommerce_receipt_uploaded_notification", $order_id);
      do_action("peprodev_uploadreceipt_customer_uploaded_receipt", $order_id, $attachment_id);
      wp_send_json_success(array(
        "msg"      => __("Upload completed successfully.", "pepro-bacs-receipt-upload-for-woocommerce"),
        "date"     => $this->format_date($datetime),
        "status"   => "pending",
        "statustx" => $this->get_status("pending"),
        "url"      => $this->generate_secure_preview_src($attachment_id, $order),
      ));
    }
    public function change_receipt_upload_dir($param) {
      $param["subdir"] = "/" . $this->folder_name;
      $param["url"]    = $param["baseurl"] . "/" . $this->folder_name;
      $param["path"]   = $param["basedir"] . "/" . $this->folder_name;
      $this->protect_receipt_dir($param["path"]);
      return $param;
    }
    public function protect_receipt_dir($path = "") {
      if (empty($path)) {
        $uploads = wp_get_upload_dir();
        $path    = $uploads["basedir"] . "/" . $this->folder_name;
      }
      if (!wp_mkdir_p($path)) return false;
      $filesystem = $this->filesystem();
      $htaccess   = trailingslashit($path) . ".htaccess";
      $marker     = "peprodev-receipt-protect-v2";
      if (!$filesystem->exists($htaccess) || false === strpos((string) $filesystem->get_contents($htaccess), $marker)) {
        $filesystem->put_contents($htaccess, "# PeproDev Receipt Uploader ({$marker})\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n", 0644);
      }
      if (!$filesystem->exists(trailingslashit($path) . "index.php")) {
        $filesystem->put_contents(trailingslashit($path) . "index.php", "<?php\n// Silence is golden.\n", 0644);
      }
      if (!$filesystem->exists(trailingslashit($path) . "protection-check.txt")) {
        $filesystem->put_contents(trailingslashit($path) . "protection-check.txt", "If you can read this, receipt files are publicly accessible.\n", 0644);
      }
      return true;
    }
    public function admin_init() {
      if (get_option("peprobacsru_version") !== $this->version) {
        $this->protect_receipt_dir();
        $types = get_option("peprobacsru_allowed_file_types", null);
        if (is_string($types)) {
          update_option("peprobacsru_allowed_file_types", array_values(array_intersect($this->_allowed_file_types_array(), $this->get_safe_mimes())));
        }
        delete_transient("peprobacsru_protection_status");
        update_option("peprobacsru_version", $this->version);
      }
      // phpcs:ignore WordPress.Security.NonceVerification.Recommended
      if (isset($_GET["page"], $_GET["tab"], $_GET["section"]) && "wc-settings" === $_GET["page"] && "checkout" === $_GET["tab"] && "upload_receipt" === $_GET["section"]) {
        wp_safe_redirect($this->url);
        exit;
      }
    }
  }
  add_action("plugins_loaded", function () {
    if (!class_exists("WooCommerce")) {
      add_action("admin_notices", function () {
        printf(
          "<div class='notice notice-error'><p>%s</p></div>",
          sprintf(
            // translators: 1: this plugin name, 2: WooCommerce
            esc_html__('%1$s needs %2$s in order to function', "pepro-bacs-receipt-upload-for-woocommerce"),
            "<strong>" . esc_html__("PeproDev Receipt Uploader for WooCommerce", "pepro-bacs-receipt-upload-for-woocommerce") . "</strong>",
            "<a href='" . esc_url(admin_url("plugin-install.php?s=woocommerce&tab=search&type=term")) . "'><strong>WooCommerce</strong></a>"
          )
        );
      });
      return;
    }
    $GLOBALS["peprodev_uploadreceipt"] = new peproDev_UploadReceiptWC();
  });
}
