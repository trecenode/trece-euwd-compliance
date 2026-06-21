# Changelog

## 1.5.0
- **ALTCHA spam protection on the public withdrawal form:**
  Optional, self-hosted, GDPR-friendly proof-of-work CAPTCHA — no
  third-party calls, no cookies, no tracking. Disabled by default;
  enable under Withdrawals → Settings → Spam Protection. The challenge
  is generated server-side (signed with an HMAC secret auto-generated
  on first use) and verified before any other step-1 validation. The
  auto-withdraw fast path and step-2 review remain untouched — they
  already have stronger ownership checks. ALTCHA widget bundled in
  `assets/js/altcha.min.js` so the plugin makes no outbound network
  calls at runtime.

## 1.4.2
- **Small change to the gobal withdraw form:** 
  Added a header and a short description on how the form shall be used.

## 1.4.1
- **Bug fix and layout change:**
  Fix duplicate Withdrawals menu and consolidate the detail-page layout
    
  Trece_WDEU_Settings re-registered the top-level Withdrawals menu under
  the same slug Trece_WDEU_Admin_Log already owned. WP doesn't dedupe
  add_menu_page() by slug, so the sidebar showed two Withdrawals entries
  and a single page load fired both callbacks, rendering the request list
  twice. Settings now only registers its submenu against the existing
  parent and the orphan render_top_level_page helper is removed.
    
  On the request detail page, the right-column sidebar (width:35%) sat
  wider than WP's default 281px reservation on #post-body-content, so the
  Status / Audit Trail boxes overflowed leftward and didn't line up with
  the main-column boxes. Dropped the two-column layout in favour of a
  single full-width stack, and merged the Audit Trail content into the
  Status box (dropping duplicated Current Status / Admin Comment rows
  already shown in the status form) so the page isn't padded with sparse
  half-empty boxes.

## 1.4.0
- **Fix unauthenticated ownership-check bypass on public withdrawal form:** The 
  step-2 branch of render() reconstructed the review payload from raw  $_POST 
  and minted a transient token guarded only by isset() on the nonce,
  letting any unauthenticated POST disclose a WooCommerce order's line items
  by order number and forge withdrawal requests against arbitrary orders.
- **Single-source the plugin version on the plugin header:** TRECE_WDEU_VERSION 
  was hand-maintained alongside the plugin header's Version: line and the 
  README badge, so the three drifted out of sync (header 1.3.0 vs tag/CHANGELOG 1.3.1).
- **Add release tool and maintainer docs:** `npm run release X.Y.Z` orchestrates
  a local release: bump version, rebuild assets, commit, tag, and produce 
  dist/trece-withdrawal-eu-X.Y.Z.zip ready to attach to a GitHub release. 
  The zip is built from git archive of the freshly created tag (not the working 
  tree) and prunes dev-only meta (scripts/, package.json, .distignore, .gitignore,
  doc/) but keeps both minified and unminified assets so SCRIPT_DEBUG works on installs.

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
