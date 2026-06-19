# Changelog

## 1.3.1
- Spanish String Translations fix

## 1.3.0

*Country applicability, audit trail, and admin UX.*

- **Country applicability:** new "Applicability (Country)" settings — restrict
  the withdrawal flow to selected billing countries (defaults to the EU
  countries enabled in WooCommerce). Gating applies to checkout consent
  (classic + block), order emails, the My Account flow, and the public form.
  Disabled by default, so existing behaviour is unchanged until enabled.
- **Per-request activity log:** withdrawal requests now keep an audit trail
  (submission, status changes, with actor and IP) shown in a new "Activity Log"
  metabox on the request detail screen.
- **Admin UX:** pending-withdrawal count on the WooCommerce dashboard status
  widget, and a "Review request" notice on the order edit screen when a request
  is pending.
- Fixed: the GDPR personal-data exporter and eraser iterated request IDs as if
  they were arrays, so they exported/erased nothing; they now resolve each
  request correctly and redact IPs stored in the activity log.
- **WPML compatibility:** `wpml-config.xml` registers the consent/notice texts
  as translatable admin texts, copies the product and category withdrawal
  status to translations, and marks the internal request CPT as
  non-translatable.

## 1.2.0

*Feature-parity gaps from competitive review (block checkout, guest flow, download eligibility).*

- **Block-based checkout (Store API) support:** consent checkboxes now render on
  the WooCommerce block checkout via `woocommerce_register_additional_checkout_field`,
  shown only when the cart contains a matching digital_content / service_early
  product. The classic checkout path is unchanged.
- **Guest withdrawal flow:** customer order emails for guest orders now include a
  one-click, tokenized "Submit a withdrawal request" link (validated with
  `hash_equals`) that takes the guest straight to the review step without
  re-entering order details.
- **Download-based eligibility (Art. 16(m)):** a downloadable digital product is
  excluded from withdrawal only once consent was given **and** the file was
  actually downloaded; consented-but-not-downloaded items keep the right.
  Non-downloadable digital content is still governed by consent alone.
- Shared consent-persistence logic between classic and block checkout; cached
  per-status product index busted on product/category changes.

## 1.1.0

*Mixed-order (digital + physical) differentiation.*

- Withdrawal requests now capture the actual order line items, classified into
  withdrawable vs. excluded under Art. 16, instead of free text.
- The review step shows per-line checkboxes for withdrawable items and lists the
  items excluded from the right of withdrawal; the selection is validated server
  side so excluded items can never be smuggled back in.
- `scope` (full/partial) and the excluded-items list are now derived
  automatically from the order and stored on the request.
- New `Trece_WDEU_WC_Product::classify_order_items()` helper, reused by the
  My Account eligibility check and the public form.
- Fixed: free-text "Products affected" was discarded when saving a request.
- Fixed: confirmation/notification emails rendered empty fields due to a
  meta-key prefix mismatch in `get_withdrawal()`.
- Fixed: status-change email crashed on send (`send_status_change` method name
  mismatch).
- Fixed: the public two-step form did not advance to the review step after a
  valid step 1.
- Excluded items are now decoded for display in the admin detail view and the
  CSV export.

## 1.0.0

*Initial release.*

- Two-step withdrawal form with Annex I.B model form.
- SHA-256 receipt hashing for tamper-proof confirmation.
- Checkout consent checkboxes for digital content (Art. 16(m)) and services (Art. 14(4)(a)).
- Product exclusion system with category inheritance under Art. 16.
- Full admin management panel with withdrawal request list and detail views.
- Email notifications to admin and customer.
- WooCommerce and standalone mode support.
- HPOS (High-Performance Order Storage) compatible.
- GDPR-compliant data handling.
- Spanish (es_ES) translation included.
- 7 filters and 3 actions for developer customisation.