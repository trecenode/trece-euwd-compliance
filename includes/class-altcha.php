<?php
/**
 * ALTCHA challenge generation and verification.
 *
 * Self-hosted, GDPR-friendly proof-of-work CAPTCHA. The plugin generates
 * a challenge signed with a server-side HMAC secret (so we can later
 * verify the solution came from a challenge we issued) and the browser
 * solves a small SHA-256 puzzle whose answer is included in the form
 * submission.
 *
 * Protocol reference: https://altcha.org
 *
 * @package Trece_Withdrawal_EU
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Trece_WDEU_Altcha
 *
 * Stateless helpers around the ALTCHA protocol — no per-challenge state
 * is stored on the server. The HMAC secret is auto-generated on first
 * use and stored in wp_options.
 *
 * @since 1.5.0
 */
class Trece_WDEU_Altcha {

	/**
	 * Option key holding the HMAC secret.
	 *
	 * @var string
	 */
	const SECRET_OPTION = 'trece_wdeu_altcha_secret';

	/**
	 * How long a freshly generated challenge stays valid, in seconds.
	 *
	 * Embedded in the salt suffix and re-checked at verify time, so a stale
	 * form left open in a tab can't be replayed indefinitely.
	 *
	 * @var int
	 */
	const CHALLENGE_TTL = 30 * MINUTE_IN_SECONDS;

	/**
	 * Proof-of-work search space. ~100k iterations of SHA-256 is roughly
	 * 100-300 ms on a modern device — invisible to humans, painful at scale
	 * to a bot trying thousands of submissions.
	 *
	 * @var int
	 */
	const MAX_PUZZLE_NUMBER = 100000;

	/**
	 * Is ALTCHA enabled in plugin settings?
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$settings = Trece_WDEU_Plugin::instance()->get_settings();
		return ! empty( $settings['spam_protection_altcha'] );
	}

	/**
	 * Return the HMAC secret, generating one on first use.
	 *
	 * @return string
	 */
	private static function get_secret() {
		$secret = get_option( self::SECRET_OPTION, '' );

		if ( ! is_string( $secret ) || strlen( $secret ) < 32 ) {
			$secret = wp_generate_password( 64, false );
			update_option( self::SECRET_OPTION, $secret, false );
		}

		return $secret;
	}

	/**
	 * Build a fresh challenge for the widget.
	 *
	 * @return array{algorithm:string,challenge:string,maxnumber:int,salt:string,signature:string}
	 */
	public static function create_challenge() {
		$max     = self::MAX_PUZZLE_NUMBER;
		$number  = random_int( 0, $max );
		$expires = time() + self::CHALLENGE_TTL;
		$salt    = bin2hex( random_bytes( 12 ) ) . '?expires=' . $expires;

		$challenge = hash( 'sha256', $salt . $number );
		$signature = hash_hmac( 'sha256', $challenge, self::get_secret() );

		return array(
			'algorithm' => 'SHA-256',
			'challenge' => $challenge,
			'maxnumber' => $max,
			'salt'      => $salt,
			'signature' => $signature,
		);
	}

	/**
	 * Verify a base64-encoded JSON payload submitted by the widget.
	 *
	 * @param string $payload Raw `altcha` field from the POST body.
	 *
	 * @return bool True if the solution is valid and the challenge has not
	 *              expired.
	 */
	public static function verify_solution( $payload ) {

		if ( ! is_string( $payload ) || '' === $payload ) {
			return false;
		}

		$decoded = base64_decode( $payload, true );
		if ( false === $decoded ) {
			return false;
		}

		$data = json_decode( $decoded, true );
		if ( ! is_array( $data ) ) {
			return false;
		}

		foreach ( array( 'algorithm', 'challenge', 'number', 'salt', 'signature' ) as $k ) {
			if ( ! isset( $data[ $k ] ) ) {
				return false;
			}
		}

		if ( 'SHA-256' !== $data['algorithm'] ) {
			return false;
		}

		// Signature must come from our own secret (proves we issued this
		// challenge, not an attacker forging their own easy puzzle).
		$expected_sig = hash_hmac( 'sha256', (string) $data['challenge'], self::get_secret() );
		if ( ! hash_equals( $expected_sig, (string) $data['signature'] ) ) {
			return false;
		}

		// Optional expiry baked into the salt suffix (?expires=<unix-ts>).
		if ( preg_match( '/\?(?:.*&)?expires=(\d+)/', (string) $data['salt'], $m ) ) {
			if ( time() > (int) $m[1] ) {
				return false;
			}
		}

		// Proof-of-work: the number really does hash to the challenge.
		$expected_challenge = hash( 'sha256', $data['salt'] . $data['number'] );
		if ( ! hash_equals( $expected_challenge, (string) $data['challenge'] ) ) {
			return false;
		}

		return true;
	}
}
