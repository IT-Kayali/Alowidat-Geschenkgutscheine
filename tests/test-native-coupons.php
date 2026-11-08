<?php
/**
 * Integration-nahe Tests der nativen WooCommerce-Coupon-Eingänge.
 * Aufruf: php tests/test-native-coupons.php <scenario>
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
$GLOBALS['scenario'] = $argv[1] ?? '';
$GLOBALS['notices'] = array();
$GLOBALS['transients'] = array();
$GLOBALS['currency'] = 'EUR';
$GLOBALS['is_checkout'] = false;
$GLOBALS['disable_field'] = false;
$GLOBALS['gift_only'] = false;
$GLOBALS['nonce_ok'] = true;
$GLOBALS['response'] = null;

function must( $value, $message ) { if ( ! $value ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
function wp_parse_args( $a, $b ) { return array_merge( $b, $a ); }
function get_option( $key, $fallback = false ) {
    if ( 'alw_gc_product_id' === $key ) { return 123; }
    if ( 'alw_gc_settings' === $key ) { return $GLOBALS['disable_field'] ? array( 'auto_redeem_field' => 'no' ) : array(); }
    return $fallback;
}
function absint( $v ) { return abs( (int) $v ); }
function wp_unslash( $v ) { return $v; }
function sanitize_text_field( $v ) { return trim( (string) $v ); }
function sanitize_key( $v ) { return strtolower( trim( $v ) ); }
function wp_nonce_field( $action, $name ) { echo '<input type="hidden" name="' . $name . '" value="valid-nonce">'; }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $v ) { return $v; }
function wp_kses_post( $v ) { return $v; }
function wc_coupons_enabled() { return true; }
function wc_get_cart_url() { return '/warenkorb/'; }
function wc_get_checkout_url() { return '/kasse/'; }
function get_woocommerce_currency() { return $GLOBALS['currency']; }
function wc_format_coupon_code( $v ) { return strtolower( trim( $v ) ); }
function wp_verify_nonce( $nonce, $action ) { return $GLOBALS['nonce_ok'] && 'valid-nonce' === $nonce && in_array( $action, array( 'woocommerce-cart', 'alw_gc_remove_native' ), true ); }
function check_ajax_referer( $action, $field ) { must( 'apply-coupon' === $action && 'security' === $field, 'Sicherheitskontext falsch' ); must( $GLOBALS['nonce_ok'] && 'valid-ajax' === ( $_POST[ $field ] ?? '' ), 'AJAX-Nonce wurde nicht geprüft' ); }
function wc_add_notice( $message, $type = 'success' ) { $GLOBALS['notices'][] = array( 'type' => $type, 'message' => $message ); }
function wc_print_notices() { $GLOBALS['response'] = 'html-notices'; }
function wc_nocache_headers() { $GLOBALS['response'] = 'nocache'; }
function wp_safe_redirect( $url ) { validate_test( 'redirect', $url ); }
function wp_die() { validate_test( 'ajax' ); }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $expiry ) { $GLOBALS['transients'][ $key ] = $value; return true; }
function wc_price( $v, $args = array() ) { return number_format( $v, 2, ',', '.' ) . ' €'; }
function is_order_received_page() { return false; }
function is_checkout() { return $GLOBALS['is_checkout']; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function wp_nonce_url( $url, $action ) { must( 'alw_gc_remove_native' === $action, 'falsche Nonce für Entfernen' ); return $url . '&_wpnonce=valid-nonce'; }
class FakeSession {
    private $data = array();
    public function get( $key, $default = null ) { return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : $default; }
    public function set( $key, $value ) { $this->data[ $key ] = $value; }
    public function __unset( $key ) { unset( $this->data[ $key ] ); }
}
class FakeCart {
    public $recalcs = 0;
    public $coupons = array();
    public function is_empty() { return false; }
    public function get_cart() { return $GLOBALS['gift_only'] ? array( array( 'product_id' => 123 ) ) : array( array( 'product_id' => 500 ) ); }
    public function get_applied_coupons() { return $this->coupons; }
    public function calculate_totals() {
        ++$this->recalcs;
        $code = WC()->session->get( 'alw_gc_code', '' );
        WC()->session->set( 'alw_gc_applied_cents', 'ALW-TEST' === $code && ! $GLOBALS['gift_only'] ? 2500 : 0 );
    }
}
class ALW_Gift_DB {
    public static function find_code( $code ) {
        if ( 'ALW-TEST' === $code ) { return (object) array( 'id' => 77, 'status' => 'active', 'balance_cents' => 2500 ); }
        if ( 'ALW-BLOCKED' === $code ) { return (object) array( 'id' => 77, 'status' => 'suspended', 'balance_cents' => 2500 ); }
        return null;
    }
    public static function active( $c ) { return $c && 'active' === $c->status && $c->balance_cents > 0; }
}
$GLOBALS['wc'] = (object) array( 'session' => new FakeSession(), 'cart' => new FakeCart() );
function WC() { return $GLOBALS['wc']; }
require dirname( __DIR__ ) . '/includes/class-alw-gift-cards.php';

function validate_test( $kind, $url = '' ) {
    $test = $GLOBALS['scenario'];
    $s = WC()->session;
    if ( 'native_cart_gift' === $test || 'native_ajax_gift' === $test || 'native_ajax_coupon_with_gift' === $test ) {
        must( 'ALW-TEST' === $s->get( 'alw_gc_code', '' ), 'Geschenkguthaben wurde nicht gespeichert' );
        must( 2500 === $s->get( 'alw_gc_applied_cents', 0 ), 'Gutscheinwert nicht angerechnet' );
        must( array( 'rabatt10' ) === WC()->cart->coupons || array() === WC()->cart->coupons, 'WooCommerce-Rabatte verändert' );
        must( count( $GLOBALS['notices'] ) === 1 && 'success' === $GLOBALS['notices'][0]['type'], 'Erfolgsnachricht fehlt' );
        must( WC()->cart->recalcs > 0, 'Warenkorbsumme nicht neu berechnet' );
    } elseif ( 'native_ajax_blocked' === $test ) {
        must( '' === $s->get( 'alw_gc_code', '' ), 'Gesperrter Code angewendet' );
        must( 'error' === $GLOBALS['notices'][0]['type'], 'Fehlermeldung fehlt' );
    } elseif ( 'native_cart_gift_only' === $test ) {
        must( '' === $s->get( 'alw_gc_code', '' ), 'Gutschein darf neuen Gutschein nicht bezahlen' );
        must( 'error' === $GLOBALS['notices'][0]['type'], 'Hinweis auf Guthabenausschluss fehlt' );
    } elseif ( 'native_remove' === $test ) {
        must( '' === $s->get( 'alw_gc_code', '' ), 'Einlösung nicht entfernt' );
        must( 0 === $s->get( 'alw_gc_applied_cents', 0 ), 'Guthaben nicht geleert' );
        must( array( 'rabatt10' ) === WC()->cart->coupons, 'Normaler Rabatt beim Entfernen gelöscht' );
        must( '/kasse/' === $url, 'Falsche Rückkehrseite' );
    } else { must( false, 'Unerwarteter Redirect oder AJAX-Abbruch' ); }
    must( ( 'ajax' === $kind ) === in_array( $test, array( 'native_ajax_gift', 'native_ajax_blocked', 'native_ajax_coupon_with_gift' ), true ), 'Falscher Eingabekanal' );
    echo "PASS: $test\n";
    exit( 0 );
}

$test = $GLOBALS['scenario'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
if ( 'native_cart_gift' === $test || 'native_cart_coupon' === $test || 'native_cart_nonce_invalid' === $test || 'native_cart_gift_only' === $test ) {
    if ( 'native_cart_nonce_invalid' === $test ) { $GLOBALS['nonce_ok'] = false; }
    if ( 'native_cart_gift_only' === $test ) { $GLOBALS['gift_only'] = true; }
    $_POST = array( 'apply_coupon' => 'Einlösen', 'coupon_code' => 'native_cart_coupon' === $test ? 'rabatt10' : 'ALW-TEST', 'woocommerce-cart-nonce' => 'valid-nonce' );
    ALW_Gift_Cards::intercept_native_cart_coupon();
    must( in_array( $test, array( 'native_cart_coupon', 'native_cart_nonce_invalid' ), true ), 'Native Gutscheineinlösung nicht abgefangen' );
    must( 0 === WC()->cart->recalcs && array() === $GLOBALS['notices'], 'Normalen oder nicht autorisierten Code verändert' );
} elseif ( in_array( $test, array( 'native_ajax_gift', 'native_ajax_coupon', 'native_ajax_blocked', 'native_ajax_coupon_with_gift' ), true ) ) {
    WC()->cart->coupons = 'native_ajax_coupon_with_gift' === $test ? array( 'rabatt10' ) : array();
    $_POST = array( 'security' => 'valid-ajax', 'coupon_code' => 'native_ajax_coupon' === $test ? 'rabatt10' : ( 'native_ajax_blocked' === $test ? 'ALW-BLOCKED' : 'ALW-TEST' ) );
    ALW_Gift_Cards::intercept_native_ajax_coupon();
    must( 'native_ajax_coupon' === $test, 'AJAX-Einlösung nicht abgefangen' );
    must( 0 === WC()->cart->recalcs && array() === $GLOBALS['notices'], 'Normalen Coupon verändert' );
} elseif ( 'native_remove' === $test ) {
    WC()->session->set( 'alw_gc_code', 'ALW-TEST' );
    WC()->session->set( 'alw_gc_applied_cents', 2500 );
    WC()->cart->coupons = array( 'rabatt10' );
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = array( 'alw_gc_remove' => '1', 'alw_gc_return' => 'checkout', '_wpnonce' => 'valid-nonce' );
    ALW_Gift_Cards::remove_native_gift_code();
} elseif ( 'native_optional_hidden' === $test ) {
    $GLOBALS['disable_field'] = true;
    ob_start(); ALW_Gift_Cards::cart_redeem_ui(); ALW_Gift_Cards::checkout_redeem_ui(); $html = ob_get_clean();
    must( '' === $html, 'Eigenes Feld wird trotz Deaktivierung angezeigt' );
    must( 'no' === ALW_Gift_Cards::settings()['auto_redeem_field'], 'Einstellung verloren' );
} elseif ( 'native_default_visible' === $test ) {
    ob_start(); ALW_Gift_Cards::cart_redeem_ui(); $html = ob_get_clean();
    must( false !== strpos( $html, 'alw-gc-redeem' ), 'Bestandsfeld ist standardmäßig nicht mehr sichtbar' );
} elseif ( 'native_credit_row' === $test ) {
    $GLOBALS['is_checkout'] = true;
    WC()->session->set( 'alw_gc_applied_cents', 2500 );
    ob_start(); ALW_Gift_Cards::credit_totals_row(); $html = ob_get_clean();
    must( false !== strpos( $html, '/kasse/?alw_gc_remove=1' ), 'Guthaben-Entfernen-Link fehlt' );
    must( false !== strpos( $html, 'alw_gc_return=checkout' ), 'Rückkehr zum Checkout fehlt' );
    must( false !== strpos( $html, '_wpnonce=valid-nonce' ), 'Entfernen ohne Nonce' );
} else { fwrite( STDERR, "Unknown scenario: $test\n" ); exit( 2 ); }
echo "PASS: $test\n";
