/**
 * Withdrawal EU Law — Checkout Consent UX Enhancement
 *
 * Adds visual feedback to the WooCommerce checkout consent checkboxes
 * rendered by the plugin. The actual validation (blocking checkout) is
 * handled server‑side via `woocommerce_checkout_process`.
 *
 * This script:
 *  1. Highlights unchecked required consent checkboxes with an error class.
 *  2. Clears the highlight as soon as the user checks the box.
 *  3. Re‑binds listeners after WooCommerce refreshes the checkout
 *     fragments (AJAX `updated_checkout` event).
 *
 * Compatible with both jQuery‑based WC checkouts and non‑jQuery
 * environments (MutationObserver fallback).
 *
 * @package TreceWithdrawalEU
 * @since   1.0.0
 */
( function () {
	'use strict';

	/* ------------------------------------------------------------------ */
	/*  Constants                                                         */
	/* ------------------------------------------------------------------ */

	/** CSS class prefix used throughout the plugin. */
	var PREFIX = 'trece-wdeu-';

	/** Wrapper class for digital‑content consent fields. */
	var CONSENT_SELECTOR = '.' + PREFIX + 'consent-digital';

	/** Class added to the wrapper when the checkbox is required but unchecked. */
	var CONSENT_ERROR_CLASS = PREFIX + 'consent-error';

	/** The actual checkbox inside the consent wrapper. */
	var CHECKBOX_SELECTOR = 'input[type="checkbox"]';

	/** WooCommerce checkout form selector (used for MutationObserver scope). */
	var WC_CHECKOUT_SELECTOR = 'form.checkout, .woocommerce-checkout';

	/* ------------------------------------------------------------------ */
	/*  Helpers                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Query all matching elements inside a parent.
	 *
	 * @param {string}  selector CSS selector.
	 * @param {Element} [parent] Defaults to document.
	 * @return {NodeList}
	 */
	function qsa( selector, parent ) {
		return ( parent || document ).querySelectorAll( selector );
	}

	/* ------------------------------------------------------------------ */
	/*  Consent checkbox visual feedback                                  */
	/* ------------------------------------------------------------------ */

	/**
	 * Apply or remove the error class on a consent wrapper based on the
	 * checkbox state.
	 *
	 * @param {Element} wrapper The `.trece-wdeu-consent-digital` element.
	 * @param {Element} checkbox The checkbox input inside the wrapper.
	 */
	function updateConsentState( wrapper, checkbox ) {
		// Only flag required checkboxes that are unchecked.
		if ( checkbox.required && ! checkbox.checked ) {
			wrapper.classList.add( CONSENT_ERROR_CLASS );
		} else {
			wrapper.classList.remove( CONSENT_ERROR_CLASS );
		}
	}

	/**
	 * Bind change listeners on all consent checkboxes currently in the DOM.
	 *
	 * Each checkbox gets a data attribute so we don't double‑bind after an
	 * AJAX checkout refresh.
	 */
	function bindConsentListeners() {
		var wrappers = qsa( CONSENT_SELECTOR );

		if ( ! wrappers.length ) {
			return;
		}

		wrappers.forEach( function ( wrapper ) {
			var checkbox = wrapper.querySelector( CHECKBOX_SELECTOR );

			if ( ! checkbox ) {
				return;
			}

			// Avoid binding twice (WC may fire `updated_checkout` multiple times).
			if ( checkbox.dataset.treceWdeuBound ) {
				return;
			}
			checkbox.dataset.treceWdeuBound = '1';

			// Listen for user interaction.
			checkbox.addEventListener( 'change', function () {
				updateConsentState( wrapper, checkbox );
			} );

			// Also validate when the checkout form is submitted so the
			// visual cue appears right away alongside WC's own notices.
			var form = checkbox.closest( 'form' );
			if ( form && ! form.dataset.treceWdeuConsentSubmit ) {
				form.dataset.treceWdeuConsentSubmit = '1';
				form.addEventListener( 'submit', function () {
					// Re‑evaluate all consent checkboxes on submit.
					bindConsentListeners(); // ensure latest DOM is covered
					qsa( CONSENT_SELECTOR ).forEach( function ( w ) {
						var cb = w.querySelector( CHECKBOX_SELECTOR );
						if ( cb ) {
							updateConsentState( w, cb );
						}
					} );
				} );
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/*  WooCommerce AJAX checkout integration                             */
	/* ------------------------------------------------------------------ */

	/**
	 * After WooCommerce refreshes checkout fragments via AJAX, our consent
	 * markup may have been replaced.  We need to re‑bind listeners.
	 *
	 * Strategy:
	 *  - If jQuery is available (WC checkout loads it), hook into the
	 *    `updated_checkout` event emitted by WC's checkout.js.
	 *  - Otherwise, fall back to a MutationObserver on the checkout form.
	 */
	function observeCheckoutUpdates() {

		// ---- jQuery path (preferred — matches WC's own eventing) ----
		if ( typeof jQuery !== 'undefined' ) {
			jQuery( document.body ).on( 'updated_checkout', function () {
				bindConsentListeners();
			} );

			// WC also fires this after payment method changes.
			jQuery( document.body ).on( 'payment_method_selected', function () {
				bindConsentListeners();
			} );

			return; // jQuery path is sufficient; skip MutationObserver.
		}

		// ---- MutationObserver fallback ----
		var checkoutForm = document.querySelector( WC_CHECKOUT_SELECTOR );

		if ( ! checkoutForm ) {
			return;
		}

		var observer = new MutationObserver( function ( mutations ) {
			// Only re‑bind if child nodes were actually added/removed.
			var dominated = mutations.some( function ( m ) {
				return m.type === 'childList' && m.addedNodes.length > 0;
			} );

			if ( dominated ) {
				bindConsentListeners();
			}
		} );

		observer.observe( checkoutForm, {
			childList: true,
			subtree:   true,
		} );
	}

	/* ------------------------------------------------------------------ */
	/*  Bootstrap                                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * Main initialisation.
	 */
	function init() {
		bindConsentListeners();
		observeCheckoutUpdates();
	}

	// Kick off when the DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
