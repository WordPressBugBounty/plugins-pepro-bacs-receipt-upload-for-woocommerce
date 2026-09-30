<?php
/**
 * Admin Receipt Rejected (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/admin-rejected-receipt-template-plain.php
 *
 * @see https://woocommerce.com/document/template-structure/
 * @version 2.11.0
 */

defined("ABSPATH") || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading));
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
// translators: %s: order number
echo esc_html(sprintf(__("The order #%s uploaded receipt has been rejected.", "pepro-bacs-receipt-upload-for-woocommerce"), $order->get_order_number())) . "\n\n";
do_action("woocommerce_email_order_details", $order, $sent_to_admin, $plain_text, $email);
echo "\n----------------------------------------\n\n";
do_action("woocommerce_email_order_meta", $order, $sent_to_admin, $plain_text, $email);
do_action("woocommerce_email_customer_details", $order, $sent_to_admin, $plain_text, $email);
echo "\n\n----------------------------------------\n\n";
if ($additional_content) {
  echo esc_html(wp_strip_all_tags(wptexturize($additional_content)));
  echo "\n\n----------------------------------------\n\n";
}
echo wp_kses_post(apply_filters("woocommerce_email_footer_text", get_option("woocommerce_email_footer_text")));
