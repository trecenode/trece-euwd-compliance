/**
 * Withdrawal EU Law — Public Form Enhancement
 *
 * Vanilla JavaScript. Progressive enhancement only — the form works
 * perfectly without JS thanks to HTML5 validation and CSS :checked selectors.
 *
 * Responsibilities:
 *  1. Toggle the products textarea visibility via a CSS class when the
 *     withdrawal‑type radio changes between "full" and "partial".
 *  2. Smooth‑scroll to the first server‑side error message, if any.
 *  3. Add lightweight client‑side validation feedback (red border on blur
 *     for empty required fields).
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

	/** Class toggled on the products‑field wrapper when partial is selected. */
	var SHOW_PRODUCTS_CLASS = PREFIX + 'show-products';

	/** Class added to a required field that fails the blur check. */
	var FIELD_ERROR_CLASS = PREFIX + 'field-error';

	/** Selector for the Step 1 form container. */
	var STEP1_SELECTOR = '.' + PREFIX + 'step1';

	/** Selector for server‑side error notices rendered by WordPress / WC. */
	var ERROR_NOTICE_SELECTOR = '.' + PREFIX + 'notice--error, .woocommerce-error';

	/* ------------------------------------------------------------------ */
	/*  Helpers                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Safely query a single element inside a parent.
	 *
	 * @param {string}  selector CSS selector.
	 * @param {Element} [parent] Defaults to document.
	 * @return {Element|null}
	 */
	function qs( selector, parent ) {
		return ( parent || document ).querySelector( selector );
	}

	/**
	 * Safely query all matching elements inside a parent.
	 *
	 * @param {string}  selector CSS selector.
	 * @param {Element} [parent] Defaults to document.
	 * @return {NodeList}
	 */
	function qsa( selector, parent ) {
		return ( parent || document ).querySelectorAll( selector );
	}

	/* ------------------------------------------------------------------ */
	/*  Withdrawal‑type radio toggle                                      */
	/* ------------------------------------------------------------------ */

	/**
	 * Show or hide the products textarea based on the selected radio value.
	 *
	 * Instead of manipulating `display` directly we toggle a CSS class on the
	 * form wrapper so that styles can also be driven purely by CSS `:checked`.
	 *
	 * @param {Element} form The Step 1 <form> element (or its wrapper).
	 */
	function syncProductsVisibility( form ) {
		var selected = qs(
			'input[name="trece_wdeu_type"]:checked',
			form
		);

		if ( ! selected ) {
			return;
		}

		if ( selected.value === 'partial' ) {
			form.classList.add( SHOW_PRODUCTS_CLASS );
		} else {
			form.classList.remove( SHOW_PRODUCTS_CLASS );
		}
	}

	/**
	 * Bind change listeners on the withdrawal‑type radios.
	 *
	 * @param {Element} form The Step 1 wrapper / form element.
	 */
	function initTypeToggle( form ) {
		var radios = qsa( 'input[name="trece_wdeu_type"]', form );

		if ( ! radios.length ) {
			return;
		}

		// Set the initial state on page load.
		syncProductsVisibility( form );

		radios.forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				syncProductsVisibility( form );
			} );
		} );
	}

	/* ------------------------------------------------------------------ */
	/*  Smooth scroll to error notices                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * If the page contains a server‑side error notice, smoothly scroll it
	 * into view so the user doesn't miss it.
	 */
	function scrollToErrors() {
		var errorNotice = qs( ERROR_NOTICE_SELECTOR );

		if ( ! errorNotice ) {
			return;
		}

		// Use native smooth scrolling; works in all modern browsers.
		errorNotice.scrollIntoView( {
			behavior: 'smooth',
			block:    'center',
		} );
	}

	/* ------------------------------------------------------------------ */
	/*  Inline validation feedback                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Add a blur listener to every required field inside the form.
	 * When the field loses focus and is still empty we add a visual
	 * error class; the class is removed as soon as the user types.
	 *
	 * This does NOT prevent submission — HTML5 `required` handles that.
	 *
	 * @param {Element} form The Step 1 wrapper / form element.
	 */
	function initBlurValidation( form ) {
		var fields = qsa(
			'input[required], textarea[required], select[required]',
			form
		);

		if ( ! fields.length ) {
			return;
		}

		fields.forEach( function ( field ) {
			field.addEventListener( 'blur', function () {
				if ( ! field.value.trim() ) {
					field.classList.add( FIELD_ERROR_CLASS );
				} else {
					field.classList.remove( FIELD_ERROR_CLASS );
				}
			} );

			// Clear the error state as soon as the user starts typing.
			field.addEventListener( 'input', function () {
				if ( field.value.trim() ) {
					field.classList.remove( FIELD_ERROR_CLASS );
				}
			} );
		} );
	}

	/* ------------------------------------------------------------------ */
	/*  Bootstrap                                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * Main initialisation — called once the DOM is ready.
	 */
	function init() {
		var stepOne = qs( STEP1_SELECTOR );

		if ( ! stepOne ) {
			// The Step 1 form is not on this page — nothing to do.
			return;
		}

		// Find the actual <form> inside the wrapper, or fall back to the
		// wrapper itself if it IS the form.
		var form = stepOne.tagName === 'FORM'
			? stepOne
			: qs( 'form', stepOne ) || stepOne;

		initTypeToggle( form );
		initBlurValidation( form );
		scrollToErrors();
	}

	// Kick off when the DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		// DOM already parsed (e.g. script loaded with `defer`).
		init();
	}

} )();
