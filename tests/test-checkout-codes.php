<?php
/**
 * Minimale Regressionstests der gemeinsamen Code-Eingabe ohne WP-Installation.
 * php tests/test-checkout-codes.php <scenario>
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
$GLOBALS['test_scenario'] = $argv[1] ?? '';
$GLOBALS['notices'] = array();
$GLOBALS['coupon_enabled'] = true;
$GLOBALS['transients'] = array();

function is_admin() { return false; }
function get_woocommerce_currency() { return 'EUR'; }
function get_option( $name, $default = false ) { return 'alw_gc_product_id' === $name ? 123 : $default; }
function absint( $v ) { return abs( (int) $v ); }
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function sanitize_key( $value ) { return strtolower( (string) $value ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function wp_kses_post( $value ) { return $value; }
function wc_coupons_enabled() { return $GLOBALS['coupon_enabled']; }
function wc_format_coupon_code( $value ) { return strtolower( trim( $value ) ); }
function wp_verify_nonce( $nonce, $action ) { return 'test-nonce' === $nonce; }
function wc_add_notice( $notice, $type ) { $GLOBALS['notices'][] = array( 'type' => $type, 'message' => $notice ); }
function wp_get_referer() { return '/kasse'; }
function wc_get_cart_url() { return '/warenkorb'; }
function wp_nonce_field( $action, $name ) { echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="test-nonce">'; }
function wc_price( $value, $args ) { return number_format( $value, 2, ',', '.' ) . ' €'; }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $expiry ) { $GLOBALS['transients'][ $key ] = $value; return true; }
function wp_safe_redirect( $url ) { validate_test( $url ); }

class FakeSession {
    private $data = array();
    public function get( $key, $default = null ) { return $this->data[ $key ] ?? $default; }
    public function set( $key, $value ) { $this->data[ $key ] = $value; }
    public function __unset( $key ) { unset( $this->data[ $key ] ); }
}
class FakeCart {
    public $codes = array();
    public $calls = array();
    public $recalcs = 0;
    public function is_empty() { return false; }
    public $items = array();
    public function get_cart() { return $this->items; }
    public function get_applied_coupons() { return $this->codes; }
    public function has_discount( $code ) { return in_array( $code, $this->codes, true ); }
    public function apply_coupon( $code ) {
        $this->calls[] = $code;
        if ( 'rabatt10' !== $code || in_array( $code, $this->codes, true ) ) {
            wc_add_notice( 'WooCommerce lehnt diesen Code ab.', 'error' );
            return false;
        }
        $this->codes[] = $code;
        wc_add_notice( 'WooCommerce Rabatt eingelöst.', 'success' );
        return true;
    }
    public function remove_coupon( $code ) {
        $this->codes = array_values( array_diff( $this->codes, array( $code ) ) );
    }
    public function calculate_totals() {
        ++$this->recalcs;
        $code = WC()->session->get( 'alw_gc_code', '' );
        WC()->session->set( 'alw_gc_applied_cents', 'ALW-TEST' === $code ? 2500 : 0 );
    }
}
class ALW_Gift_DB {
    public static function find_code( $code ) {
        if ( 'ALW-TEST' === $code ) {
            return (object) array( 'id' => 123, 'code_last4' => 'TEST', 'balance_cents' => 5000, 'status' => 'active' );
        }
        return null;
    }
    public static function active( $card ) { return $card && 'active' === $card->status; }
}
$GLOBALS['wc_fake'] = (object) array( 'session' => new FakeSession(), 'cart' => new FakeCart() );
function WC() { return $GLOBALS['wc_fake']; }
require dirname( __DIR__ ) . '/includes/class-alw-gift-cards.php';

$scenario = $GLOBALS['test_scenario'];
$valid_cases = array( 'gift', 'coupon', 'coupon_with_gift', 'remove_coupon_with_gift', 'remove_gift_with_coupon', 'invalid_coupon', 'render_both', 'fixed_cart_gift_protected', 'fixed_cart_normal_ok' );
if ( ! in_array( $scenario, $valid_cases, true ) ) { fwrite( STDERR, 'Unknown scenario\n' ); exit( 2 ); }
if ( in_array( $scenario, array( 'coupon_with_gift', 'remove_coupon_with_gift', 'remove_gift_with_coupon', 'render_both' ), true ) ) {
    WC()->session->set( 'alw_gc_code', 'ALW-TEST' );
    WC()->session->set( 'alw_gc_applied_cents', 2500 );
}
if ( in_array( $scenario, array( 'remove_coupon_with_gift', 'remove_gift_with_coupon', 'render_both' ), true ) ) {
    WC()->cart->codes = array( 'rabatt10' );
}
function must( $check, $message ) {
    if ( ! $check ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
}
function validate_test( $redirect = '' ) {
    $scenario = $GLOBALS['test_scenario'];
    $cart = WC()->cart;
    $session = WC()->session;
    if ( 'render_both' === $scenario ) {
        $r = new ReflectionMethod( 'ALW_Gift_Cards', 'redeem_widget' );
        ob_start();
        $r->invoke( null );
        $html = ob_get_clean();
        must( false !== strpos( $html, 'Gutschein- oder Rabattcode eingeben' ), 'Input bei aktivem Guthaben fehlt' );
        must( false !== strpos( $html, 'Geschenkguthaben: •••• TEST' ), 'Geschenkguthaben fehlt' );
        must( false !== strpos( $html, 'Rabattcode: rabatt10' ), 'Normaler Coupon fehlt' );
        must( false !== strpos( $html, 'value="remove_coupon"' ), 'Entfernen für Rabatt fehlt' );
        must( false !== strpos( $html, 'value="remove"' ), 'Entfernen für Guthaben fehlt' );
    } else {
        must( '/kasse' === $redirect, 'Fehlerhafte Weiterleitung' );
        if ( 'gift' === $scenario ) {
            must( 'ALW-TEST' === $session->get( 'alw_gc_code' ), 'Geschenkgutschein nicht gespeichert' );
            must( 2500 === $session->get( 'alw_gc_applied_cents' ), 'Guthaben nicht angerechnet' );
            must( array() === $cart->calls, 'Guthaben wurde als Woo-Coupon verarbeitet' );
        }
        if ( 'coupon' === $scenario || 'coupon_with_gift' === $scenario ) {
            must( array( 'rabatt10' ) === $cart->get_applied_coupons(), 'WooCommerce Coupon nicht angewendet' );
            must( array( 'rabatt10' ) === $cart->calls, 'Code nicht an WooCommerce übergeben' );
        }
        if ( 'coupon_with_gift' === $scenario || 'remove_coupon_with_gift' === $scenario ) {
            must( 'ALW-TEST' === $session->get( 'alw_gc_code' ), 'Bestehendes Guthaben gelöscht' );
        }
        if ( 'remove_coupon_with_gift' === $scenario ) {
            must( array() === $cart->get_applied_coupons(), 'Entfernter Coupon ist noch aktiv' );
        }
        if ( 'remove_gift_with_coupon' === $scenario ) {
            must( '' === $session->get( 'alw_gc_code', '' ), 'Guthaben nicht entfernt' );
            must( array( 'rabatt10' ) === $cart->get_applied_coupons(), 'Normaler Coupon unbeabsichtigt entfernt' );
        }
        if ( 'invalid_coupon' === $scenario ) {
            must( array() === $cart->get_applied_coupons(), 'Ungültigen Coupon akzeptiert' );
            must( 'error' === end( $GLOBALS['notices'] )['type'], 'WooCommerce-Fehler fehlt' );
        }
    }
    echo "PASS: $scenario\n";
    exit( 0 );
}
if ( 'render_both' === $scenario ) { validate_test(); }
if ( 'fixed_cart_gift_protected' === $scenario || 'fixed_cart_normal_ok' === $scenario ) {
    WC()->cart->items = array( array( 'product_id' => 'fixed_cart_gift_protected' === $scenario ? 123 : 987 ) );
    class FixedCartCoupon { public function get_discount_type() { return 'fixed_cart'; } }
    try {
        $valid = ALW_Gift_Cards::protect_gift_purchase_from_fixed_cart_coupons( true, new FixedCartCoupon() );
        must( 'fixed_cart_normal_ok' === $scenario && true === $valid, 'Fixed-cart Schutz fehlt oder trifft normale Artikel' );
    } catch ( Exception $e ) {
        must( 'fixed_cart_gift_protected' === $scenario, 'Normale Bestellung unnötig blockiert' );
    }
    echo "PASS: $scenario\n";
    exit( 0 );
}
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$action = 'apply';
$code = 'rabatt10';
if ( 'gift' === $scenario ) { $code = 'ALW-TEST'; }
if ( 'invalid_coupon' === $scenario ) { $code = 'ungueltig'; }
if ( 'remove_coupon_with_gift' === $scenario ) { $action = 'remove_coupon'; }
if ( 'remove_gift_with_coupon' === $scenario ) { $action = 'remove'; }
$_POST = array(
    'alw_gc_action' => $action,
    'alw_gc_nonce' => 'test-nonce',
    'alw_gc_code' => $code,
    'alw_gc_coupon_code' => 'rabatt10',
);
ALW_Gift_Cards::handle_forms();
fwrite( STDERR, "FAIL: Handler returned unexpectedly: $scenario\n" );
exit( 1 );