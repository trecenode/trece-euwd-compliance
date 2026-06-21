# Withdrawal EU Law Wordpress Plugin

**Version:** 1.4.0
**Author:** [13Node](https://13node.com)
**License:** GPL v2 or later
**Text Domain:** `trece-withdrawal-eu`
**Requires:** PHP 7.4+ · WordPress 6.0+ · WooCommerce 7.0+ *(optional)*

> Comprehensive WordPress plugin implementing EU consumer withdrawal rights under Directive 2011/83/EU (as amended by Directive (EU) 2019/2161) and upcoming June 2026 requirements. Provides a mandatory "withdraw from contract" button, two-step confirmation process, SHA-256 receipt hashing, Annex I.B model withdrawal form, checkout consent checkboxes for digital content (Art. 16(m)) and services (Art. 14(4)(a)), product exclusion notices under Art. 16, GDPR-compliant data handling, and a full admin management panel.

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
  - [General Settings](#general-settings)
  - [Email Notifications](#email-notifications)
  - [Form Page](#form-page)
  - [Order Number Requirement](#order-number-requirement)
  - [Privacy Policy](#privacy-policy)
  - [Trader Information](#trader-information)
  - [Checkout Consents](#checkout-consents)
  - [Per-Product Withdrawal Status](#per-product-withdrawal-status)
  - [Product Exclusions](#product-exclusions)
- [Shortcodes](#shortcodes)
  - [\[trece\_withdrawal\_form\]](#trece_withdrawal_form)
  - [\[trece\_withdrawal\_link\]](#trece_withdrawal_link)
- [Hooks & Filters Reference](#hooks--filters-reference)
  - [Filters](#filters)
  - [Actions](#actions)
- [Database](#database)
- [CSS Classes](#css-classes)
- [Legal Disclaimer](#legal-disclaimer)
- [Changelog](#changelog)

---

## Requirements

| Dependency   | Minimum Version | Notes                                      |
| ------------ | --------------- | ------------------------------------------ |
| PHP          | 7.4+            | 8.0+ recommended                           |
| WordPress    | 6.0+            |                                            |
| WooCommerce  | 7.0+            | Optional — required for checkout integration, order linking, and product exclusions |

The plugin operates in two modes:

- **WooCommerce mode** — Full integration with checkout consent checkboxes, order linking, product exclusion notices, My Account tab, and HPOS compatibility.
- **Standalone mode** — Works without WooCommerce for simple sites that only need the withdrawal form and admin management.

---

## Installation

1. **Upload** the plugin folder `trece-withdrawal-eu` to `/wp-content/plugins/`, or install via **Plugins → Add New → Upload Plugin** using the ZIP file.
2. **Activate** the plugin through the **Plugins** menu in WordPress.
3. **Navigate** to **Withdrawals → Settings** in the WordPress admin.
4. **Configure** the required settings:
   - Set your trader information (company name, address, email).
   - Select or create a page for the withdrawal form.
   - Review the withdrawal deadline (default: 14 days).
   - Configure email notification recipients.
5. **Add the form** to your chosen page using the `[trece_withdrawal_form]` shortcode.
6. **Add the withdrawal link** to relevant locations using the `[trece_withdrawal_link]` shortcode or the automatic placement options in settings.

---

## Configuration

All settings are available under **Withdrawals → Settings** in the WordPress admin panel.

### General Settings

| Setting                | Description                                                                 | Default |
| ---------------------- | --------------------------------------------------------------------------- | ------- |
| Withdrawal Deadline    | Number of days from delivery within which customers may exercise withdrawal rights. EU directive mandates a minimum of 14 days. | `14`    |
| Enable Standalone Mode | Allow submissions without a WooCommerce order number.                       | `No`    |

### Email Notifications

| Setting                         | Description                                                        | Default          |
| ------------------------------- | ------------------------------------------------------------------ | ---------------- |
| Admin Notification Email        | Email address(es) that receive notifications on new withdrawal requests. Comma-separated. | Site admin email |
| Send Customer Confirmation      | Whether to email the customer a confirmation with SHA-256 receipt hash upon successful submission. | `Yes`            |
| Email "From" Name               | Name used in the From header of outgoing emails.                   | Site name        |

### Form Page

| Setting            | Description                                                                                 |
| ------------------ | ------------------------------------------------------------------------------------------- |
| Withdrawal Form Page | Select the WordPress page where the `[trece_withdrawal_form]` shortcode is placed. Used for generating links automatically. |

### Order Number Requirement

| Setting                  | Description                                                                                   | Default |
| ------------------------ | --------------------------------------------------------------------------------------------- | ------- |
| Require Order Number     | When enabled, the withdrawal form requires a valid order number. Disable for standalone mode. | `Yes`   |
| Order Number Validation  | Validate that the submitted order number corresponds to an existing WooCommerce order.        | `Yes`   |

### Privacy Policy

| Setting              | Description                                                                                  |
| -------------------- | -------------------------------------------------------------------------------------------- |
| Privacy Policy Page  | Select the page containing your privacy policy. A link is displayed on the withdrawal form.  |
| Consent Text         | Text displayed alongside the privacy policy checkbox on the form.                            |

### Trader Information

These fields populate the **Annex I.B model withdrawal form** as required by the directive.

| Setting           | Description                                              |
| ----------------- | -------------------------------------------------------- |
| Company Name      | Legal name of the trader/business.                       |
| Address           | Full postal address of the trader.                       |
| Email Address     | Contact email address for withdrawal communications.     |
| Phone Number      | Optional contact phone number.                           |
| Fax Number        | Optional fax number (still referenced in some EU member state implementations). |

### Checkout Consents

These settings control the consent checkboxes displayed during WooCommerce checkout. They are only active when WooCommerce is installed and active.

| Setting                        | Description                                                                                                 | Default |
| ------------------------------ | ----------------------------------------------------------------------------------------------------------- | ------- |
| Enable Digital Content Consent | Display a checkbox for Art. 16(m) digital content consent (loss of right of withdrawal upon performance).   | `Yes`   |
| Digital Content Consent Text   | The label text for the digital content consent checkbox.                                                    | See plugin defaults |
| Enable Service Consent         | Display a checkbox for Art. 14(4)(a) service consent (acknowledgement that withdrawal right is lost after full performance). | `Yes`   |
| Service Consent Text           | The label text for the service consent checkbox.                                                            | See plugin defaults |

### Per-Product Withdrawal Status

Every WooCommerce product has a **Withdrawal Status** select on the **General** tab of the product edit screen (`_trece_wdeu_withdrawal_status`). It tells the plugin how that specific product is treated under the right of withdrawal — whether the customer keeps the full 14-day right, whether a checkout consent checkbox is required, and whether an exclusion notice is shown on the product page.

| Option (value)                                            | Legal basis                                                                                                                    | Effect in the store                                                                                                                                                                                                                                                                                       |
| ---------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Standard (14-day right of withdrawal)** (`standard`)      | Default withdrawal right under Directive 2011/83/EU — the customer has 14 days to withdraw without giving any reason.            | No consent checkbox at checkout, no exclusion notice on the product page. The customer keeps the full right of withdrawal and can use `[trece_withdrawal_form]` / "Withdraw from contract" as normal.                                                                                                  |
| **Digital content (Art. 16(m))** (`digital_content`)        | Digital content not supplied on a physical medium loses its right of withdrawal once the customer explicitly consents to immediate performance before the order is placed (Art. 16(m)). | A **mandatory** consent checkbox is shown at checkout ("I consent to the immediate supply of digital content and acknowledge that I will lose my right of withdrawal."). The order cannot be placed without checking it. The consent (with timestamp, IP, and user agent) is stored as evidence. If the customer accepts, the right of withdrawal is lost for that item; declining is not possible for this product type. No notice is shown on the product page — the checkout checkbox is the only place this is communicated. |
| **Service started early (Art. 14(4)(a))** (`service_early`) | A service the customer asks to start during the withdrawal period: once fully performed, the right of withdrawal is lost, and if withdrawn before completion the customer owes the proportionate cost of the service already provided (Art. 14(4)(a)). | An **optional** consent checkbox is shown at checkout ("I request that the service begins during the withdrawal period and acknowledge that, should I withdraw, I will be liable for the proportionate cost of the service already provided."). It does not block placing the order. If accepted, the customer acknowledges the proportionate-cost liability and loses the right of withdrawal once the service is fully performed. If declined, the customer keeps the right of withdrawal for that item, and eligibility is recalculated accordingly in the My Account withdrawal tab. |
| **Other Article 16 exception** (`other_article16`)          | Catch-all for other exclusions under Art. 16 of the directive (e.g. sealed goods that cannot be returned for hygiene reasons once unsealed, custom or personalised goods, etc.). | No consent checkbox is shown. If **Show Exclusion Notice on Product Page** is enabled (see [Product Exclusions](#product-exclusions)), the product page displays an exclusion notice ("No right of withdrawal" / configurable text). The withdrawal form/link is not offered for that item. |

**In short:**

- **`standard`** — always withdrawable within the deadline.
- **`digital_content`** — withdrawable only until the mandatory consent is accepted at checkout; afterwards it is not.
- **`service_early`** — withdrawable unless the optional consent was accepted and the service has been fully performed; if accepted, the customer may still owe the proportionate cost of work already done.
- **`other_article16`** — never withdrawable, regardless of consent.

See [Checkout Consents](#checkout-consents) to customise the consent checkbox texts, and [Product Exclusions](#product-exclusions) to configure exclusions and notices at the category level.

### Product Exclusions

Configure which products or product categories are excluded from withdrawal rights under Art. 16 of the directive.

| Setting                              | Description                                                                                              | Default |
| -------------------------------------- | -------------------------------------------------------------------------------------------------------- | ------- |
| Show Exclusion Notice on Product Page  | When enabled, products with Withdrawal Status set to **Other Article 16 exception** display an exclusion notice on the single product page. Has no effect on `digital_content` or `service_early` products, whose consent is handled at checkout instead. | `Yes`   |
| Other Exclusion Notice Title           | Title text for the Article 16 exclusion notice shown on the product page.                                | `No right of withdrawal` |
| Other Exclusion Notice Body            | Body text for the Article 16 exclusion notice shown on the product page.                                 | See plugin defaults |

---

## Shortcodes

### `[trece_withdrawal_form]`

Renders the full two-step withdrawal form based on the **Annex I.B model withdrawal form**.

**Attributes:** None.

**Usage:** Place this shortcode on a dedicated WordPress page, then select that page in **Withdrawals → Settings → Form Page**.

```html
[trece_withdrawal_form]
```

**Behaviour:**

1. **Step 1** — The customer fills in the form fields (name, address, order number if required, goods/services description, date of receipt, and optionally their email).
2. **Step 2** — A confirmation screen summarises the withdrawal request and requires explicit confirmation per Art. 11a(3) two-step process.
3. **Success** — A confirmation page displays the SHA-256 receipt hash, withdrawal ID, and timestamp. If email confirmation is enabled, the customer also receives this by email.

### `[trece_withdrawal_link]`

Renders the **"Withdraw from contract here"** button/link as required by the directive's mandatory withdrawal mechanism.

**Attributes:**

| Attribute  | Type   | Description                                                        | Default   |
| ---------- | ------ | ------------------------------------------------------------------ | --------- |
| `order_id` | int    | Optional. Pre-fills the order number field when the form is loaded. | —         |
| `class`    | string | Optional. Additional CSS class(es) for the link element.           | —         |

**Usage:**

```html
<!-- Basic usage -->
[trece_withdrawal_link]

<!-- With order ID pre-filled -->
[trece_withdrawal_link order_id="12345"]

<!-- With custom CSS class -->
[trece_withdrawal_link class="my-custom-button"]
```

---

## Hooks & Filters Reference

The plugin provides **7 filters** and **3 actions** for developers to customise behaviour.

### Filters

---

#### `trece_wdeu_withdrawal_deadline_days`

Modify the withdrawal deadline in days. The EU directive mandates a minimum of 14 days, but member states or the trader may offer more.

**Parameters:**

| Parameter | Type  | Description                         |
| --------- | ----- | ----------------------------------- |
| `$days`   | `int` | The withdrawal deadline in days. Default: `14`. |

**Returns:** `int` — The modified deadline in days.

**Example:**

```php
<?php
/**
 * Extend the withdrawal deadline to 30 days.
 */
add_filter( 'trece_wdeu_withdrawal_deadline_days', function ( int $days ): int {
    return 30;
} );
```

---

#### `trece_wdeu_consent_digital_text`

Modify the text displayed for the digital content consent checkbox at checkout (Art. 16(m)).

**Parameters:**

| Parameter | Type     | Description                                    |
| --------- | -------- | ---------------------------------------------- |
| `$text`   | `string` | The default consent checkbox label text.       |

**Returns:** `string` — The modified text.

**Example:**

```php
<?php
/**
 * Customise the digital content consent text.
 */
add_filter( 'trece_wdeu_consent_digital_text', function ( string $text ): string {
    return 'I expressly consent to the immediate provision of the digital content and acknowledge '
         . 'that I thereby lose my right of withdrawal.';
} );
```

---

#### `trece_wdeu_consent_service_text`

Modify the text displayed for the service consent checkbox at checkout (Art. 14(4)(a)).

**Parameters:**

| Parameter | Type     | Description                               |
| --------- | -------- | ----------------------------------------- |
| `$text`   | `string` | The default consent checkbox label text.  |

**Returns:** `string` — The modified text.

**Example:**

```php
<?php
/**
 * Customise the service consent text.
 */
add_filter( 'trece_wdeu_consent_service_text', function ( string $text ): string {
    return 'I acknowledge that once the service has been fully performed, '
         . 'I will no longer be entitled to withdraw from the contract.';
} );
```

---

#### `trece_wdeu_excluded_notice_html`

Modify the HTML output of the exclusion notice displayed on product pages for products excluded from withdrawal rights under Art. 16.

**Parameters:**

| Parameter  | Type          | Description                                                      |
| ---------- | ------------- | ---------------------------------------------------------------- |
| `$html`    | `string`      | The default exclusion notice HTML.                               |
| `$product` | `WC_Product`  | The WooCommerce product object.                                  |
| `$reason`  | `string`      | The exclusion reason key (e.g., `sealed_goods`, `personalised`). |

**Returns:** `string` — The modified HTML.

**Example:**

```php
<?php
/**
 * Add a custom icon to the exclusion notice.
 */
add_filter( 'trece_wdeu_excluded_notice_html', function ( string $html, WC_Product $product, string $reason ): string {
    $icon = '<span class="dashicons dashicons-warning"></span> ';
    return '<div class="trece-wdeu-exclusion-notice trece-wdeu-exclusion-notice--custom">'
         . $icon . esc_html__( 'This product is excluded from the right of withdrawal.', 'trece-withdrawal-eu' )
         . '</div>';
}, 10, 3 );
```

---

#### `trece_wdeu_annex_trader_data`

Modify the trader data string displayed in the Annex I.B model withdrawal form. Useful for multi-store setups or dynamic trader information.

**Parameters:**

| Parameter     | Type     | Description                                               |
| ------------- | -------- | --------------------------------------------------------- |
| `$trader_data`| `string` | The formatted trader data (name, address, email, etc.).   |

**Returns:** `string` — The modified trader data string.

**Example:**

```php
<?php
/**
 * Append a VAT number to the trader information.
 */
add_filter( 'trece_wdeu_annex_trader_data', function ( string $trader_data ): string {
    return $trader_data . "\n" . 'VAT: EU123456789';
} );
```

---

#### `trece_wdeu_resolve_order_number`

Implement custom logic to resolve an order number to a `WC_Order` object. Useful when using custom order number plugins (e.g., WooCommerce Sequential Order Numbers).

**Parameters:**

| Parameter       | Type               | Description                                            |
| --------------- | ------------------ | ------------------------------------------------------ |
| `$order`        | `WC_Order\|null`   | The resolved order, or `null` if not yet resolved.     |
| `$order_number` | `string`           | The order number submitted by the customer.            |

**Returns:** `WC_Order|null` — The resolved order, or `null` if not found.

**Example:**

```php
<?php
/**
 * Resolve order numbers from WooCommerce Sequential Order Numbers Pro.
 */
add_filter( 'trece_wdeu_resolve_order_number', function ( ?WC_Order $order, string $order_number ): ?WC_Order {
    if ( $order !== null ) {
        return $order;
    }

    // Query by custom order number meta.
    $orders = wc_get_orders( [
        'meta_key'   => '_order_number',
        'meta_value' => sanitize_text_field( $order_number ),
        'limit'      => 1,
    ] );

    return ! empty( $orders ) ? $orders[0] : null;
}, 10, 2 );
```

---

#### `trece_wdeu_receipt_email_content`

Modify the content of the confirmation email sent to the customer after a withdrawal request is successfully submitted.

**Parameters:**

| Parameter     | Type     | Description                                                    |
| ------------- | -------- | -------------------------------------------------------------- |
| `$content`    | `string` | The default email body content (HTML).                         |
| `$withdrawal` | `object` | The withdrawal data object containing all submission details.  |

**Returns:** `string` — The modified email content.

**Example:**

```php
<?php
/**
 * Add a custom footer to the withdrawal confirmation email.
 */
add_filter( 'trece_wdeu_receipt_email_content', function ( string $content, object $withdrawal ): string {
    $footer = '<hr><p style="font-size: 12px; color: #666;">'
            . 'If you have any questions, please contact us at support@example.com.'
            . '</p>';

    return $content . $footer;
}, 10, 2 );
```

---

### Actions

---

#### `trece_wdeu_withdrawal_created`

Fired immediately after a new withdrawal request is created and stored in the database.

**Parameters:**

| Parameter        | Type    | Description                                                                                     |
| ---------------- | ------- | ----------------------------------------------------------------------------------------------- |
| `$withdrawal_id` | `int`   | The ID of the newly created withdrawal record.                                                  |
| `$data`          | `array` | Associative array of submission data (`customer_name`, `customer_email`, `order_id`, `reason`, `hash`, etc.). |

**Example:**

```php
<?php
/**
 * Log new withdrawal requests to an external system.
 */
add_action( 'trece_wdeu_withdrawal_created', function ( int $withdrawal_id, array $data ): void {
    // Send to external CRM or logging service.
    wp_remote_post( 'https://api.example.com/withdrawals', [
        'body' => wp_json_encode( [
            'withdrawal_id' => $withdrawal_id,
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'order_id'       => $data['order_id'] ?? null,
            'created_at'     => current_time( 'mysql' ),
        ] ),
        'headers' => [ 'Content-Type' => 'application/json' ],
    ] );
}, 10, 2 );
```

---

#### `trece_wdeu_status_changed`

Fired after the status of a withdrawal request is changed (e.g., from `pending` to `approved` or `rejected`).

**Parameters:**

| Parameter        | Type     | Description                                            |
| ---------------- | -------- | ------------------------------------------------------ |
| `$withdrawal_id` | `int`    | The ID of the withdrawal record.                       |
| `$old_status`    | `string` | The previous status (e.g., `pending`, `approved`, `rejected`, `completed`). |
| `$new_status`    | `string` | The new status.                                        |

**Example:**

```php
<?php
/**
 * Send a notification when a withdrawal is approved.
 */
add_action( 'trece_wdeu_status_changed', function ( int $withdrawal_id, string $old_status, string $new_status ): void {
    if ( $new_status !== 'approved' ) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'trece_wdeu_withdrawals';
    $withdrawal = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $withdrawal_id ) );

    if ( $withdrawal && $withdrawal->customer_email ) {
        wp_mail(
            $withdrawal->customer_email,
            __( 'Your withdrawal request has been approved', 'trece-withdrawal-eu' ),
            sprintf(
                __( 'Dear %s, your withdrawal request #%d has been approved. We will process your refund shortly.', 'trece-withdrawal-eu' ),
                $withdrawal->customer_name,
                $withdrawal_id
            )
        );
    }
}, 10, 3 );
```

---

#### `trece_wdeu_consent_captured`

Fired when a checkout consent checkbox (digital content or service) is captured during order processing.

**Parameters:**

| Parameter       | Type     | Description                                                         |
| --------------- | -------- | ------------------------------------------------------------------- |
| `$order_id`     | `int`    | The WooCommerce order ID.                                           |
| `$consent_type` | `string` | The type of consent: `digital_content` or `service`.                |
| `$accepted`     | `bool`   | Whether the consent was accepted (`true`) or not (`false`).         |

**Example:**

```php
<?php
/**
 * Log consent events for compliance auditing.
 */
add_action( 'trece_wdeu_consent_captured', function ( int $order_id, string $consent_type, bool $accepted ): void {
    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        return;
    }

    $log_entry = sprintf(
        '[%s] Order #%d — Consent "%s": %s (IP: %s)',
        current_time( 'mysql' ),
        $order_id,
        $consent_type,
        $accepted ? 'ACCEPTED' : 'DECLINED',
        $order->get_customer_ip_address()
    );

    error_log( $log_entry );

    // Also store as order meta for auditing.
    $order->update_meta_data( "_trece_wdeu_consent_{$consent_type}", $accepted ? 'yes' : 'no' );
    $order->update_meta_data( "_trece_wdeu_consent_{$consent_type}_date", current_time( 'mysql' ) );
    $order->save();
}, 10, 3 );
```

---

## Database

The plugin creates a single custom table during activation.

### Table: `{$wpdb->prefix}trece_wdeu_withdrawals`

Default name: `wp_trece_wdeu_withdrawals`

| Column              | Type                 | Description                                                                 |
| ------------------- | -------------------- | --------------------------------------------------------------------------- |
| `id`                | `BIGINT(20) UNSIGNED`| Primary key, auto-increment.                                                |
| `order_id`          | `BIGINT(20) UNSIGNED`| WooCommerce order ID. `NULL` for standalone submissions.                    |
| `customer_name`     | `VARCHAR(255)`       | Full name of the customer.                                                  |
| `customer_email`    | `VARCHAR(255)`       | Email address of the customer.                                              |
| `customer_address`  | `TEXT`               | Postal address of the customer.                                             |
| `goods_description` | `TEXT`               | Description of the goods/services subject to withdrawal.                    |
| `order_date`        | `DATE`               | Date the order was placed or the contract concluded.                        |
| `receipt_date`      | `DATE`               | Date the goods were received (for goods contracts).                         |
| `reason`            | `TEXT`               | Optional reason for withdrawal (not required by directive, but can be collected). |
| `status`            | `VARCHAR(20)`        | Current status: `pending`, `approved`, `rejected`, `completed`. Default: `pending`. |
| `hash`              | `VARCHAR(64)`        | SHA-256 hash of the submission data, serving as a tamper-proof receipt.      |
| `ip_address`        | `VARCHAR(45)`        | IP address of the submitter (stored for legal evidence, GDPR considerations apply). |
| `created_at`        | `DATETIME`           | Timestamp of submission (UTC).                                              |
| `updated_at`        | `DATETIME`           | Timestamp of last status update (UTC).                                      |

**Indexes:**

- `PRIMARY KEY` on `id`
- Index on `order_id`
- Index on `hash`
- Index on `status`
- Index on `customer_email`

> **Note:** The table is created using `dbDelta()` during plugin activation and respects the site's configured table prefix and charset/collation.

---

## CSS Classes

All CSS classes are prefixed with `trece-wdeu-` to avoid conflicts. Key classes include:

| Class                                    | Element                                     |
| ---------------------------------------- | ------------------------------------------- |
| `.trece-wdeu-form`                       | Main withdrawal form wrapper.               |
| `.trece-wdeu-form__step`                 | Individual step container.                   |
| `.trece-wdeu-form__step--active`         | Currently visible step.                      |
| `.trece-wdeu-form__field`                | Form field wrapper.                          |
| `.trece-wdeu-form__input`                | Text inputs.                                 |
| `.trece-wdeu-form__textarea`             | Textarea inputs.                             |
| `.trece-wdeu-form__submit`               | Submit/confirm button.                       |
| `.trece-wdeu-form__back`                 | Back button (step 2 → step 1).              |
| `.trece-wdeu-confirmation`               | Step 2 confirmation summary.                 |
| `.trece-wdeu-success`                    | Success page wrapper.                        |
| `.trece-wdeu-success__hash`              | SHA-256 hash display.                        |
| `.trece-wdeu-link`                       | "Withdraw from contract" link/button.        |
| `.trece-wdeu-exclusion-notice`           | Product exclusion notice.                    |
| `.trece-wdeu-consent`                    | Checkout consent checkbox wrapper.           |
| `.trece-wdeu-consent--digital`           | Digital content consent checkbox.            |
| `.trece-wdeu-consent--service`           | Service consent checkbox.                    |
| `.trece-wdeu-annex`                      | Annex I.B trader data block.                 |

---

## Legal Disclaimer

> **This plugin provides tools to help implement EU consumer withdrawal rights as defined in Directive 2011/83/EU and its amendments. It does not constitute legal advice. The plugin authors and contributors are not liable for any legal consequences arising from its use. EU regulations may vary by member state and are subject to change. Always consult a qualified legal professional to ensure full compliance with applicable laws in your jurisdiction.**

---

## Changelog

Read all the changes in [CHANGELOG.md](CHANGELOG.md)

# License

This plugin is licensed under the [GNU General Public License v2.0 or later](LICENSE).