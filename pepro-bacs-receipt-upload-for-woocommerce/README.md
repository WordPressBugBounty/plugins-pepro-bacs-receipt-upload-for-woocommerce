# PeproDev Receipt Uploader for WooCommerce

Let customers upload a payment receipt (image or PDF) for any payment method, and approve or reject it from the WooCommerce order screen.

[WordPress.org](https://wordpress.org/plugins/pepro-bacs-receipt-upload-for-woocommerce/) · Current version: **2.15.0** · Requires WordPress 6.0+, WooCommerce 7.0+, PHP 7.4+ · Tested up to WordPress 7.1, WooCommerce 11.1, PHP 8.5

## Features

- Receipt upload for any payment method (BACS, cheque, COD or any custom gateway)
- Upload form on the thank-you page and in My Account order details
- Approve, reject or reset receipts from the order screen, with an admin note
- Automatic order status change on order placed, receipt uploaded, approved and rejected
- Extra order statuses: Awaiting Receipt Upload, Awaiting Receipt Approval, Receipt Rejected
- Six WooCommerce emails (uploaded / approved / rejected, for customer and admin)
- File type picker (JPG, PNG, WEBP, GIF, BMP, AVIF, HEIC, PDF) and size limit
- Custom content before/after the form, custom form title, redirect after upload
- Orders list receipt column, media library receipt filter
- Shortcodes: `[receipt-form]`, `[receipt-preview order_id=15]`
- HPOS compatible, RTL and translation ready

## Security

- Receipts are stored in `wp-content/uploads/receipt_upload` with random file names and deny rules
- Receipts are only served through signed links bound to their order
- Only the order owner (or a guest with the order key) and shop managers can upload or view receipts
- Files are validated by real content
- **Nginx**: `.htaccess` is ignored, add this to your server block (the Help & Tools tab checks it for you):

```nginx
location ~* ^/wp-content/uploads/receipt_upload/ { deny all; return 403; }
```

Report security issues through the [Patchstack VDP](https://patchstack.com/database/vdp/pepro-bacs-receipt-upload-for-woocommerce).

## Settings

WooCommerce → Settings → **Receipt Upload** (General, Order Status Automation, Upload Form, Help & Tools).

## Developer hooks

**Actions**: `peprodev_uploadreceipt_customer_uploaded_receipt`, `peprodev_uploadreceipt_receipt_status_changed`, `peprodev_uploadreceipt_receipt_approved`, `peprodev_uploadreceipt_receipt_rejected`, `peprodev_uploadreceipt_receipt_awaiting_upload`, `peprodev_uploadreceipt_receipt_awaiting_approval`, `peprodev_uploadreceipt_order_placed`, `peprodev_uploadreceipt_save_receipt`, `peprodev_uploadreceipt_email_receipt_preview`

**Filters**: `peprodev_uploadreceipt_folder_name`, `peprodev_uploadreceipt_allowed_file_mimes`, `peprodev_uploadreceipt_max_upload_size`, `peprodev_uploadreceipt_safe_mimes`

**jQuery events** on `document`: `peprodev_receipt_uploader_ajax_prevented`, `peprodev_receipt_uploader_ajax_success`, `peprodev_receipt_uploader_ajax_failed`, `peprodev_receipt_uploader_ajax_completed`

## Changelog

### 2.15.0 (2026-09-26)
- Fixed: receipt status showed as Unknown Status on the first thank-you page view with block themes

### 2.14.0 (2026-09-26)
- Security: fixed unauthenticated cross-order receipt tampering (IDOR), thanks to Lyris Vale
- Security: fixed unauthenticated disclosure of other customers' receipt images (IDOR), thanks to Shivamani Vastrala
- Tested up to WordPress 7.1, WooCommerce 11.1, PHP 8.1 – 8.5

### 2.13.0
- New settings tab with sections, file type picker, storage protection check, custom form title

### 2.12.0
- Renamed to *PeproDev Receipt Uploader for WooCommerce*, text domain `pepro-bacs-receipt-upload-for-woocommerce`, Plugin Check compliant

### 2.11.0
- Security hardening (access checks for shortcodes, escaping, capability checks) and bug fixes

### 2.10.0
- Signed receipt links and protected receipt storage

### 2.9.0
- Order ownership check for receipt uploads

Full history in [readme.txt](readme.txt).

## Credits

Developed at [BlackSwanDev](https://blackswandev.com/) and [Pepro Dev](https://pepro.dev/) · Lead Developer: [AmirhpCom](https://amirhp.com/)
