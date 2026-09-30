<?php
/**
 * Admin Receipt Approved
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/admin-approved-receipt-template.php
 *
 * @see https://woocommerce.com/document/template-structure/
 * @version 2.11.0
 */

defined("ABSPATH") || exit;

do_action("woocommerce_email_header", $email_heading, $email);
// translators: %s: order number
echo "<p>" . esc_html(sprintf(__("The order #%s uploaded receipt has been approved.", "pepro-bacs-receipt-upload-for-woocommerce"), $order->get_order_number())) . "</p>";
if ($additional_content) echo wp_kses_post(wpautop(wptexturize($additional_content)));
do_action("peprodev_uploadreceipt_email_receipt_preview", $order, $sent_to_admin, $plain_text, $email);
do_action("woocommerce_email_order_details", $order, $sent_to_admin, $plain_text, $email);
do_action("woocommerce_email_order_meta", $order, $sent_to_admin, $plain_text, $email);
do_action("woocommerce_email_customer_details", $order, $sent_to_admin, $plain_text, $email);
do_action("woocommerce_email_footer", $email);
