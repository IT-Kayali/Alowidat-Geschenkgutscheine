<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ALW_Gift_Cards {
    private static $allow_add = false;

    public static function settings() {
        return wp_parse_args( get_option( 'alw_gc_settings', array() ), array(
            'amounts' => '25,50,100', 'minimum' => 20, 'maximum' => 500,
            'gold' => '#d4b45c', 'dark' => '#212121', 
        ) );
    }

    public static function gift_product_id() {
        return absint( get_option( 'alw_gc_product_id', 0 ) );
    }

    public static function cents( $value ) {
        return (int) round( (float) wc_format_decimal( $value, 2 ) * 100 );
    }

    public static function money( $cents ) {
        return wc_price( (int) $cents / 100, array( 'currency' => 'EUR' ) );
    }

    public static function is_gift_product( $product_id ) {
        return self::gift_product_id() && absint( $product_id ) === self::gift_product_id();
    }

    public static function init() {
        add_action( 'init', array( __CLASS__, 'ensure_product' ), 30 );
        add_shortcode( 'alowidat_gutscheine', array( __CLASS__, 'purchase_shortcode' ) );
        add_shortcode( 'alowidat_guthaben', array( __CLASS__, 'balance_shortcode' ) );
        add_shortcode( 'alowidat_gutschein_einloesen', array( __CLASS__, 'redeem_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_action( 'template_redirect', array( __CLASS__, 'handle_forms' ), 20 );
        add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'protect_product' ), 10, 3 );
        add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'restore_cart_item' ), 20, 2 );
        add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'set_prices' ), 20 );
        // Digitale Geschenkgutscheine dürfen niemals Versandbedarf verursachen.
        add_filter( 'woocommerce_product_needs_shipping', array( __CLASS__, 'gift_needs_no_shipping' ), 20, 2 );
        add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'cart_item_display' ), 20, 2 );
        add_filter( 'woocommerce_cart_item_quantity', array( __CLASS__, 'lock_quantity' ), 10, 3 );
        add_filter( 'woocommerce_update_cart_validation', array( __CLASS__, 'validate_cart_quantity' ), 10, 4 );
        add_filter( 'woocommerce_coupon_is_valid_for_product', array( __CLASS__, 'exclude_gift_purchase_from_discounts' ), 20, 4 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item_meta' ), 20, 4 );
        add_action( 'woocommerce_review_order_before_submit', array( __CLASS__, 'checkout_purchase_policy' ), 15 );
        add_filter( 'woocommerce_calculated_total', array( __CLASS__, 'apply_credit_to_total' ), 999, 2 );
        add_action( 'woocommerce_before_cart_totals', array( __CLASS__, 'cart_redeem_ui' ) );
        add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'checkout_redeem_ui' ), 11 );
        add_action( 'woocommerce_cart_totals_before_order_total', array( __CLASS__, 'credit_totals_row' ) );
        add_action( 'woocommerce_review_order_before_order_total', array( __CLASS__, 'credit_totals_row' ) );
        add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'add_order_credit' ), 30, 2 );
        add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'reserve_order_credit' ), 10 );
        add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'restore_order_credit' ), 10 );
        add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'restore_order_credit' ), 10 );
        add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'refunded_order' ), 10 );
        add_action( 'woocommerce_order_refunded', array( __CLASS__, 'partial_refund_notice' ), 10, 2 );
        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'issue_from_order' ), 10 );
        add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'issue_from_order' ), 10 );
        add_action( 'woocommerce_payment_complete', array( __CLASS__, 'issue_from_order' ), 15 );
        add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'clear_applied_session' ), 30 );
        add_action( 'woocommerce_before_thankyou', array( __CLASS__, 'clear_applied_session' ), 10 );
        add_action( 'admin_notices', array( __CLASS__, 'setup_notices' ) );
    }

    public static function setup_notices() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
        if ( 'EUR' !== get_woocommerce_currency() ) {
            echo '<div class="notice notice-error"><p>Alowidat Gutscheine benötigt für die aktuelle Version EUR als Shop-Währung.</p></div>';
        }
        if ( ! function_exists( 'openssl_encrypt' ) ) {
            echo '<div class="notice notice-error"><p>Alowidat Gutscheine benötigt PHP OpenSSL. Bitte auf dem Server aktivieren.</p></div>';
        }
    }

    public static function ensure_product() {
        if ( ! class_exists( 'WC_Product_Simple' ) ) { return; }
        $id = self::gift_product_id();
        $existing = $id ? wc_get_product( $id ) : false;
        if ( $existing && $existing->is_type( 'simple' ) ) {
            // Bereits vorhandene Gutscheinprodukte bei Updates sicher als rein digital
            // konfigurieren. Keine neue Produkt-ID und keine Änderung bestehender Karten.
            $changed = false;
            if ( ! $existing->get_virtual() ) {
                $existing->set_virtual( true );
                $changed = true;
            }
            if ( 'none' !== $existing->get_tax_status() ) {
                $existing->set_tax_status( 'none' );
                $changed = true;
            }
            if ( 'hidden' !== $existing->get_catalog_visibility() ) {
                $existing->set_catalog_visibility( 'hidden' );
                $changed = true;
            }
            if ( $changed ) { $existing->save(); }
            return;
        }
        $product = new WC_Product_Simple();
        $product->set_name( 'Alowidat Digitaler Geschenkgutschein' );
        $product->set_status( 'publish' );
        $product->set_catalog_visibility( 'hidden' );
        $product->set_virtual( true );
        $product->set_sold_individually( false );
        $product->set_tax_status( 'none' );
        $product->set_regular_price( '0' );
        $product->set_price( '0' );
        $product->set_description( 'Digitaler Alowidat Geschenkgutschein. Kauf ausschließlich über die Gutscheinseite.' );
        $product->set_manage_stock( false );
        $product->save();
        update_option( 'alw_gc_product_id', $product->get_id(), false );
    }

    public static function assets() {
        wp_register_style( 'alw-gc-front', ALW_GC_URL . 'assets/css/front.css', array(), ALW_GC_VERSION );
        wp_register_script( 'alw-gc-front', ALW_GC_URL . 'assets/js/front.js', array(), ALW_GC_VERSION, true );
        $settings = self::settings();
        wp_add_inline_style( 'alw-gc-front', ':root{--alw-gc-gold:' . esc_attr( $settings['gold'] ) . ';--alw-gc-dark:' . esc_attr( $settings['dark'] ) . ';}' );
        wp_enqueue_style( 'alw-gc-front' );
        wp_enqueue_script( 'alw-gc-front' );
    }

    private static function valid_amounts() {
        $settings = self::settings();
        $amounts = array_unique( array_filter( array_map( 'absint', explode( ',', $settings['amounts'] ) ) ) );
        sort( $amounts );
        return $amounts;
    }

    public static function purchase_shortcode() {
        if ( ! function_exists( 'WC' ) || 'EUR' !== get_woocommerce_currency() ) {
            return '<div class="alw-gc-error">Gutscheine sind derzeit nicht verfügbar.</div>';
        }
        $s = self::settings();
        $amounts = self::valid_amounts();
        ob_start();
        ?>
        <section class="alw-gc-wrap" aria-label="Alowidat Geschenkgutscheine">
            <div class="alw-gc-intro"><span class="alw-gc-eyebrow">ALOWIDAT PARFÜMERIE</span><h2>Ein Geschenk, das begeistert.</h2><p>Verschenke die Freiheit, den eigenen Lieblingsduft zu entdecken. Digital, persönlich und voller Eleganz.</p></div>
            <div class="alw-gc-layout">
                <div class="alw-gc-visual" aria-label="Vorschau des Geschenkgutscheins">
                    <div class="alw-gc-visual-top"><span class="alw-gc-wordmark">ALOWIDAT</span><span class="alw-gc-small">PARFÜMERIE</span></div>
                    <div class="alw-gc-ornament" aria-hidden="true">✦</div>
                    <div class="alw-gc-visual-bottom"><span>GESCHENKGUTSCHEIN</span><strong class="alw-gc-preview-value"><?php echo esc_html( number_format_i18n( $amounts[0] ?? 25, 0 ) ); ?> €</strong><small>Ein besonderes Geschenk. Ein unvergesslicher Duft.</small></div>
                </div>
                <form class="alw-gc-buy" method="post" action="">
                    <h3>Deinen Gutschein gestalten</h3>
                    <p class="alw-gc-muted">Wähle den Gutscheinwert und füge auf Wunsch eine persönliche Nachricht hinzu.</p>
                    <?php wp_nonce_field( 'alw_gc_purchase', 'alw_gc_nonce' ); ?>
                    <input type="hidden" name="alw_gc_action" value="purchase">
                    <span class="alw-gc-label">Gutscheinwert</span>
                    <div class="alw-gc-amounts" role="group" aria-label="Gutscheinwert auswählen">
                        <?php foreach ( $amounts as $index => $amount ) : ?>
                            <label class="alw-gc-amount"><input type="radio" name="alw_gc_amount_choice" value="<?php echo esc_attr( $amount ); ?>" <?php checked( 0, $index ); ?>><span><?php echo esc_html( $amount ); ?> €</span></label>
                        <?php endforeach; ?>
                        <label class="alw-gc-amount"><input type="radio" name="alw_gc_amount_choice" value="custom"><span>Wunschbetrag</span></label>
                    </div>
                    <div class="alw-gc-custom" hidden>
                        <label for="alw-gc-custom-amount">Individueller Betrag (€)</label>
                        <input id="alw-gc-custom-amount" type="number" name="alw_gc_custom_amount" min="<?php echo esc_attr( $s['minimum'] ); ?>" <?php if ( '' !== (string) $s['maximum'] ) : ?>max="<?php echo esc_attr( $s['maximum'] ); ?>" <?php endif; ?>step="0.01" inputmode="decimal" value="<?php echo esc_attr( $s['minimum'] ); ?>">
                        <small><?php
                            $min_label = number_format_i18n( (float) $s['minimum'], floor( (float) $s['minimum'] ) === (float) $s['minimum'] ? 0 : 2 );
                            if ( '' === (string) $s['maximum'] ) {
                                echo 'Ab ' . esc_html( $min_label ) . ' €';
                            } else {
                                $max_label = number_format_i18n( (float) $s['maximum'], floor( (float) $s['maximum'] ) === (float) $s['maximum'] ? 0 : 2 );
                                echo 'Zwischen ' . esc_html( $min_label ) . ' € und ' . esc_html( $max_label ) . ' €';
                            }
                        ?></small>
                    </div>
                    <span class="alw-gc-label">Für wen ist der Gutschein?</span>
                    <div class="alw-gc-targets"><label><input name="alw_gc_target" type="radio" value="self" checked> Für mich</label><label><input name="alw_gc_target" type="radio" value="friend"> Zum Verschenken</label></div>
                    <div class="alw-gc-recipient" hidden>
                        <div class="alw-gc-field"><label for="alw-gc-recipient-name">Name des Empfängers <small>(optional)</small></label><input id="alw-gc-recipient-name" type="text" maxlength="120" name="alw_gc_recipient_name" placeholder="Für wen ist das Geschenk?"></div>
                        <div class="alw-gc-field"><label for="alw-gc-recipient-email">E-Mail des Empfängers *</label><input id="alw-gc-recipient-email" type="email" name="alw_gc_recipient_email" placeholder="name@beispiel.de"></div>
                        <div class="alw-gc-field"><label for="alw-gc-sender">Dein Name <small>(optional)</small></label><input id="alw-gc-sender" type="text" maxlength="120" name="alw_gc_sender_name" placeholder="Von ..."></div>
                        <div class="alw-gc-field"><label for="alw-gc-message">Persönliche Nachricht <small>(optional)</small></label><textarea id="alw-gc-message" name="alw_gc_message" maxlength="500" rows="3" placeholder="Ich wünsche dir viel Freude beim Entdecken deines Lieblingsdufts!"></textarea></div>
                    </div>
                    <button class="alw-gc-primary" type="submit"><span>Jetzt Gutschein kaufen</span><span class="alw-gc-button-value"><?php echo esc_html( $amounts[0] ?? 25 ); ?> €</span></button>
                    <p class="alw-gc-help">✦ Digitaler Versand nach Zahlung · Nur online bei Alowidat einlösbar · Auch mit einem anderen Kundenkonto oder als Gast nutzbar · Restguthaben bleibt erhalten · Mit Rabattaktionen kombinierbar</p>
                    <?php echo self::purchase_policy_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Statisches, kontrolliertes HTML. ?>
                </form>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * Verbraucherfreundlicher Hinweis statt eines unzulässigen pauschalen
     * Widerrufsausschlusses. Online-Wertgutscheine unterliegen grundsätzlich
     * dem gesetzlichen Fernabsatzwiderruf, auch bei digitalem Versand.
     * Die ausführliche Widerrufsbelehrung ist im Shop separat bereitzustellen.
     */
    private static function purchase_policy_html( $checkout = false ) {
        $class = $checkout ? ' alw-gc-policy-checkout' : '';
        return '<aside class="alw-gc-policy' . $class . '" aria-label="Rückgabe und Widerruf">'
            . '<strong>Rückgabe und Widerruf</strong>'
            . '<p>Beim Onlinekauf von Geschenkgutscheinen gilt grundsätzlich das gesetzliche 14-tägige Widerrufsrecht. '
            . 'Darüber hinaus bieten wir keine freiwillige Rücknahme oder einen Umtausch an. '
            . 'Eine Barauszahlung des Gutschein- oder Restguthabens ist, außer bei gesetzlichen Ansprüchen, ausgeschlossen.</p>'
            . '</aside>';
    }

    /** Zeigt den Hinweis erneut, aber nur wenn Gutscheine gekauft werden. */
    public static function checkout_purchase_policy() {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) { return; }
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( self::is_gift_product( $item['product_id'] ?? 0 ) ) {
                echo wp_kses_post( self::purchase_policy_html( true ) );
                return;
            }
        }
    }

    public static function balance_shortcode() {
        $result = null;
        if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['alw_gc_action'] ) && 'balance' === sanitize_key( wp_unslash( $_POST['alw_gc_action'] ) ) ) {
            if ( isset( $_POST['alw_gc_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['alw_gc_nonce'] ) ), 'alw_gc_balance' ) && self::limit_requests( 'balance', 10 ) ) {
                $code = sanitize_text_field( wp_unslash( $_POST['alw_gc_code'] ?? '' ) );
                $card = ALW_Gift_DB::find_code( $code );
                if ( $card ) {
                    $result = '<div class="alw-gc-balance-result">Verfügbares Guthaben: <strong>' . self::money( 'active' === $card->status && $card->expires_at > current_time( 'mysql', true ) ? $card->balance_cents : 0 ) . '</strong><small>Gültig bis: ' . esc_html( wp_date( 'd.m.Y', strtotime( $card->expires_at . ' UTC' ) ) ) . ' · Status: ' . esc_html( self::status_label( $card ) ) . '</small></div>';
                } else { $result = '<p class="alw-gc-error">Gutscheincode nicht gefunden.</p>'; }
            } else { $result = '<p class="alw-gc-error">Anfrage nicht möglich. Bitte später erneut versuchen.</p>'; }
        }
        ob_start(); ?>
        <div class="alw-gc-simple"><h3>Guthaben abfragen</h3><p>Prüfe den aktuellen Wert deines Alowidat Gutscheins.</p><form method="post"><input type="hidden" name="alw_gc_action" value="balance"><?php wp_nonce_field( 'alw_gc_balance', 'alw_gc_nonce' ); ?><label for="alw-gc-check-code">Gutscheincode</label><div class="alw-gc-inline"><input id="alw-gc-check-code" name="alw_gc_code" type="text" autocomplete="off" required placeholder="ALW-XXXXXX-XXXXXX-XXXXXX-XXXXXX"><button type="submit">Prüfen</button></div></form><?php echo $result ? wp_kses_post( $result ) : ''; ?></div>
        <?php return ob_get_clean();
    }

    public static function status_label( $card ) {
        if ( 'active' !== $card->status ) { return 'Gesperrt'; }
        if ( $card->expires_at <= current_time( 'mysql', true ) ) { return 'Abgelaufen'; }
        if ( (int) $card->balance_cents <= 0 ) { return 'Aufgebraucht'; }
        return 'Aktiv';
    }

    private static function limit_requests( $purpose, $max ) {
        $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
        $key = 'alw_gc_rl_' . md5( $purpose . $ip );
        $count = (int) get_transient( $key );
        if ( $count >= $max ) { return false; }
        set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
        return true;
    }

    public static function handle_forms() {
        if ( is_admin() || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['alw_gc_action'] ) ) { return; }
        $action = sanitize_key( wp_unslash( $_POST['alw_gc_action'] ) );
        if ( ! in_array( $action, array( 'purchase', 'apply', 'remove' ), true ) ) { return; }
        if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->session ) { return; }
        $nonce = sanitize_text_field( wp_unslash( $_POST['alw_gc_nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'alw_gc_' . $action ) ) {
            wc_add_notice( 'Sicherheitsprüfung fehlgeschlagen. Bitte erneut versuchen.', 'error' );
            return;
        }
        if ( 'purchase' === $action ) {
            if ( ! self::limit_requests( 'purchase', 30 ) ) { wc_add_notice( 'Zu viele Anfragen. Bitte kurz warten.', 'error' ); return; }
            self::handle_purchase();
            return;
        }
        if ( 'remove' === $action ) {
            WC()->session->__unset( 'alw_gc_code' );
            WC()->session->__unset( 'alw_gc_applied_cents' );
            WC()->cart->calculate_totals();
            wc_add_notice( 'Gutschein entfernt.', 'success' );
        } else {
            if ( ! self::limit_requests( 'apply', 15 ) ) { wc_add_notice( 'Zu viele Versuche. Bitte später erneut versuchen.', 'error' ); return; }
            $code = sanitize_text_field( wp_unslash( $_POST['alw_gc_code'] ?? '' ) );
            $card = ALW_Gift_DB::find_code( $code );
            if ( ! ALW_Gift_DB::active( $card ) || 'EUR' !== get_woocommerce_currency() ) {
                wc_add_notice( 'Gutscheincode ungültig, abgelaufen oder ohne Guthaben.', 'error' );
                return;
            }
            WC()->session->set( 'alw_gc_code', $code );
            WC()->cart->calculate_totals();
            $applied = (int) WC()->session->get( 'alw_gc_applied_cents', 0 );
            if ( $applied <= 0 ) {
                WC()->session->__unset( 'alw_gc_code' );
                wc_add_notice( 'Dieser Gutschein kann nicht für den Kauf weiterer Gutscheine verwendet werden.', 'error' );
                return;
            }
            wc_add_notice( 'Alowidat Geschenkgutschein erfolgreich angewendet.', 'success' );
        }
        wp_safe_redirect( wp_get_referer() ?: wc_get_cart_url() );
        exit;
    }

    private static function handle_purchase() {
        $s = self::settings();
        if ( 'EUR' !== get_woocommerce_currency() || ! function_exists( 'openssl_encrypt' ) ) {
            wc_add_notice( 'Gutscheinkauf derzeit nicht verfügbar.', 'error' ); return;
        }
        $choice = sanitize_text_field( wp_unslash( $_POST['alw_gc_amount_choice'] ?? '' ) );
        $amount = 'custom' === $choice ? wc_format_decimal( wp_unslash( $_POST['alw_gc_custom_amount'] ?? '' ), 2 ) : ( in_array( (int) $choice, self::valid_amounts(), true ) && ctype_digit( $choice ) ? $choice : '0' );
        $cents = self::cents( $amount );
        // Bei leerem Maximum lediglich Mindestwert und technisches Sicherheitslimit prüfen.
        $max_set = '' !== (string) $s['maximum'];
        if ( 'custom' === $choice && ( $cents < self::cents( $s['minimum'] ) || ( $max_set && $cents > self::cents( $s['maximum'] ) ) ) ) {
            wc_add_notice( 'Bitte einen gültigen Wunschbetrag auswählen.', 'error' ); return;
        }
        if ( $cents < 1 || $cents > 1000000 ) { wc_add_notice( 'Ungültiger Gutscheinwert.', 'error' ); return; }
        $target = sanitize_key( wp_unslash( $_POST['alw_gc_target'] ?? 'self' ) );
        $email = 'friend' === $target ? sanitize_email( wp_unslash( $_POST['alw_gc_recipient_email'] ?? '' ) ) : '';
        if ( 'friend' === $target && ! is_email( $email ) ) { wc_add_notice( 'Bitte eine gültige Empfänger-E-Mail-Adresse angeben.', 'error' ); return; }
        $product_id = self::gift_product_id();
        if ( ! $product_id || ! wc_get_product( $product_id ) ) { wc_add_notice( 'Das Gutscheinprodukt ist nicht verfügbar.', 'error' ); return; }
        $data = array(
            'alw_gc_amount_cents' => $cents,
            'alw_gc_recipient_email' => $email,
            'alw_gc_recipient_name' => 'friend' === $target ? sanitize_text_field( wp_unslash( $_POST['alw_gc_recipient_name'] ?? '' ) ) : '',
            'alw_gc_sender_name' => 'friend' === $target ? sanitize_text_field( wp_unslash( $_POST['alw_gc_sender_name'] ?? '' ) ) : '',
            'alw_gc_gift_message' => 'friend' === $target ? sanitize_textarea_field( wp_unslash( $_POST['alw_gc_message'] ?? '' ) ) : '',
            'alw_gc_unique' => wp_generate_uuid4(),
        );
        self::$allow_add = true;
        $key = WC()->cart->add_to_cart( $product_id, 1, 0, array(), $data );
        self::$allow_add = false;
        if ( $key ) {
            wc_add_notice( 'Geschenkgutschein wurde zum Warenkorb hinzugefügt.', 'success' );
            wp_safe_redirect( wc_get_cart_url() ); exit;
        }
        wc_add_notice( 'Der Geschenkgutschein konnte nicht hinzugefügt werden.', 'error' );
    }

    public static function protect_product( $valid, $product_id, $quantity ) {
        if ( self::is_gift_product( $product_id ) && ! self::$allow_add ) {
            wc_add_notice( 'Bitte verwende die Alowidat Gutscheinseite zum Kauf.', 'error' );
            return false;
        }
        return $valid;
    }

    public static function restore_cart_item( $cart_item, $values ) {
        foreach ( array( 'alw_gc_amount_cents', 'alw_gc_recipient_email', 'alw_gc_recipient_name', 'alw_gc_sender_name', 'alw_gc_gift_message', 'alw_gc_unique' ) as $key ) {
            if ( isset( $values[ $key ] ) ) { $cart_item[ $key ] = $values[ $key ]; }
        }
        return $cart_item;
    }

    public static function set_prices( $cart ) {
        if ( ! $cart || ( is_admin() && ! wp_doing_ajax() ) ) { return; }
        foreach ( $cart->get_cart() as $item ) {
            if ( self::is_gift_product( $item['product_id'] ) && isset( $item['alw_gc_amount_cents'] ) ) {
                $item['data']->set_price( (int) $item['alw_gc_amount_cents'] / 100 );
                $item['data']->set_tax_status( 'none' );
                $item['data']->set_virtual( true );
            }
        }
    }

    public static function gift_needs_no_shipping( $needs_shipping, $product ) {
        if ( $product instanceof WC_Product && self::is_gift_product( $product->get_id() ) ) {
            return false;
        }
        return $needs_shipping;
    }

    public static function cart_item_display( $item_data, $item ) {
        if ( ! self::is_gift_product( $item['product_id'] ?? 0 ) ) { return $item_data; }
        if ( ! empty( $item['alw_gc_recipient_email'] ) ) {
            $item_data[] = array( 'key' => 'Empfänger', 'value' => esc_html( $item['alw_gc_recipient_email'] ) );
        } else { $item_data[] = array( 'key' => 'Digitale Zustellung', 'value' => 'Per E-Mail an die Bestelladresse' ); }
        return $item_data;
    }

    public static function lock_quantity( $html, $cart_item_key, $cart_item ) {
        return self::is_gift_product( $cart_item['product_id'] ?? 0 ) ? '<span class="alw-gc-qty">1</span><input type="hidden" name="cart[' . esc_attr( $cart_item_key ) . '][qty]" value="1">' : $html;
    }

    public static function validate_cart_quantity( $valid, $cart_item_key, $values, $quantity ) {
        if ( self::is_gift_product( $values['product_id'] ?? 0 ) && 1 !== (int) $quantity ) { return false; }
        return $valid;
    }

    public static function exclude_gift_purchase_from_discounts( $valid, $product, $coupon, $values ) {
        return self::is_gift_product( $product->get_id() ) ? false : $valid;
    }

    public static function order_item_meta( $order_item, $cart_item_key, $values, $order ) {
        if ( ! self::is_gift_product( $values['product_id'] ?? 0 ) ) { return; }
        $order_item->add_meta_data( '_alw_gc_amount_cents', (int) ( $values['alw_gc_amount_cents'] ?? 0 ), true );
        $order_item->add_meta_data( '_alw_gc_recipient_email', sanitize_email( $values['alw_gc_recipient_email'] ?? '' ), true );
        $order_item->add_meta_data( '_alw_gc_recipient_name', sanitize_text_field( $values['alw_gc_recipient_name'] ?? '' ), true );
        $order_item->add_meta_data( '_alw_gc_sender_name', sanitize_text_field( $values['alw_gc_sender_name'] ?? '' ), true );
        $order_item->add_meta_data( '_alw_gc_gift_message', sanitize_textarea_field( $values['alw_gc_gift_message'] ?? '' ), true );
    }

    private static function eligible_credit_cents( $total, $cart ) {
        $gift_cents = 0;
        foreach ( $cart->get_cart() as $item ) {
            if ( self::is_gift_product( $item['product_id'] ?? 0 ) ) {
                $gift_cents += self::cents( (float) ( $item['line_total'] ?? 0 ) + (float) ( $item['line_tax'] ?? 0 ) );
            }
        }
        return max( 0, self::cents( $total ) - $gift_cents );
    }

    public static function apply_credit_to_total( $total, $cart ) {
        if ( ! WC()->session || 'EUR' !== get_woocommerce_currency() ) { return $total; }
        WC()->session->set( 'alw_gc_applied_cents', 0 );
        WC()->session->__unset( 'alw_gc_card_id' );
        $code = WC()->session->get( 'alw_gc_code', '' );
        if ( ! is_string( $code ) || '' === $code ) { return $total; }
        $card = ALW_Gift_DB::find_code( $code );
        if ( ! ALW_Gift_DB::active( $card ) ) { return $total; }
        $credit = min( (int) $card->balance_cents, self::eligible_credit_cents( $total, $cart ) );
        if ( $credit <= 0 ) { return $total; }
        WC()->session->set( 'alw_gc_card_id', (int) $card->id );
        WC()->session->set( 'alw_gc_applied_cents', $credit );
        // Ausschließlich Zahlbetrag reduzieren; Positionspreise, Rabatt und Steuer bleiben unverändert.
        return max( 0, (float) $total - ( $credit / 100 ) );
    }

    public static function credit_totals_row() {
        $credit = WC()->session ? (int) WC()->session->get( 'alw_gc_applied_cents', 0 ) : 0;
        if ( $credit > 0 ) { echo '<tr class="alw-gc-credit-row"><th>Alowidat Guthaben</th><td data-title="Guthaben">−' . wp_kses_post( self::money( $credit ) ) . '</td></tr>'; }
    }

    public static function redeem_shortcode() { ob_start(); self::redeem_widget(); return ob_get_clean(); }
    public static function cart_redeem_ui() { self::redeem_widget(); }
    public static function checkout_redeem_ui() { if ( ! is_order_received_page() ) { self::redeem_widget(); } }

    private static function redeem_widget() {
        if ( ! WC()->cart || WC()->cart->is_empty() ) { return; }
        $code = WC()->session ? WC()->session->get( 'alw_gc_code', '' ) : '';
        $card = $code ? ALW_Gift_DB::find_code( $code ) : null;
        ?>
        <div class="alw-gc-redeem"><div><strong>Hast du einen Alowidat Geschenkgutschein?</strong><p>Mit allen Sale-Artikeln und Rabattcodes kombinierbar.</p></div>
            <?php if ( $code && ALW_Gift_DB::active( $card ) ) : ?>
                <form method="post" class="alw-gc-inline alw-gc-active"><span>Aktiv: •••• <?php echo esc_html( $card->code_last4 ); ?> · Verfügbar <?php echo wp_kses_post( self::money( $card->balance_cents ) ); ?></span><input type="hidden" name="alw_gc_action" value="remove"><?php wp_nonce_field( 'alw_gc_remove', 'alw_gc_nonce' ); ?><button type="submit">Entfernen</button></form>
            <?php else : ?>
                <form method="post" class="alw-gc-inline"><input type="hidden" name="alw_gc_action" value="apply"><?php wp_nonce_field( 'alw_gc_apply', 'alw_gc_nonce' ); ?><input aria-label="Geschenkgutscheincode" type="text" name="alw_gc_code" autocomplete="off" required placeholder="Gutscheincode eingeben"><button type="submit">Einlösen</button></form>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function add_order_credit( $order, $data ) {
        if ( ! WC()->session ) { return; }
        $credit = (int) WC()->session->get( 'alw_gc_applied_cents', 0 );
        $card_id = (int) WC()->session->get( 'alw_gc_card_id', 0 );
        if ( $credit <= 0 || $card_id <= 0 ) { return; }
        if ( $order->get_meta( '_alw_gc_card_id', true ) ) { return; }
        $card = ALW_Gift_DB::get( $card_id );
        if ( ! ALW_Gift_DB::active( $card ) || (int) $card->balance_cents < $credit ) {
            throw new Exception( 'Gutscheinguthaben hat sich geändert. Bitte die Bestellung aktualisieren.' );
        }
        $fee = new WC_Order_Item_Fee();
        $fee->set_name( 'Alowidat Geschenkgutschein (Guthaben)' );
        $fee->set_amount( -$credit / 100 );
        $fee->set_total( -$credit / 100 );
        $fee->set_tax_status( 'none' );
        $fee->add_meta_data( '_alw_gc_payment', 'yes', true );
        $order->add_item( $fee );
        $order->update_meta_data( '_alw_gc_card_id', $card_id );
        $order->update_meta_data( '_alw_gc_credit_cents', $credit );
        $order->update_meta_data( '_alw_gc_reserved', 'no' );
        // Kartentotal wurde in woocommerce_calculated_total bereits um diesen Betrag reduziert.
    }

    public static function reserve_order_credit( $order ) {
        $card_id = (int) $order->get_meta( '_alw_gc_card_id', true );
        $credit = (int) $order->get_meta( '_alw_gc_credit_cents', true );
        if ( ! $card_id || $credit <= 0 || 'yes' === $order->get_meta( '_alw_gc_reserved', true ) ) { return; }
        $ok = ALW_Gift_DB::change_balance( $card_id, -$credit, 'reserve-order-' . $order->get_id(), 'redeem', $order->get_id(), 'Bei Bestellung reserviert/eingelöst' );
        if ( ! $ok ) {
            $order->update_status( 'failed', 'Gutscheinguthaben nicht mehr verfügbar.' );
            throw new Exception( 'Gutscheinguthaben ist nicht mehr verfügbar. Bitte den Warenkorb aktualisieren.' );
        }
        $order->update_meta_data( '_alw_gc_reserved', 'yes' );
        $order->save();
    }

    public static function restore_order_credit( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || 'yes' !== $order->get_meta( '_alw_gc_reserved', true ) || 'yes' === $order->get_meta( '_alw_gc_released', true ) ) { return; }
        $card_id = (int) $order->get_meta( '_alw_gc_card_id', true );
        $credit = (int) $order->get_meta( '_alw_gc_credit_cents', true );
        if ( $card_id && $credit && ALW_Gift_DB::change_balance( $card_id, $credit, 'release-order-' . $order_id, 'restore', $order_id, 'Bestellung storniert/erstattet' ) ) {
            $order->update_meta_data( '_alw_gc_released', 'yes' );
            $order->save();
            $order->add_order_note( 'Alowidat Gutscheinguthaben wurde erstattet.' );
        }
    }

    public static function refunded_order( $order_id ) {
        self::restore_order_credit( $order_id );
        self::suspend_purchased_cards( $order_id );
    }

    private static function suspend_purchased_cards( $order_id, $item_ids = null ) {
        global $wpdb;
        if ( is_array( $item_ids ) && ! $item_ids ) { return; }
        if ( null === $item_ids ) {
            $wpdb->update( ALW_Gift_DB::cards_table(), array( 'status' => 'suspended' ), array( 'order_id' => absint( $order_id ) ) );
        } else {
            foreach ( $item_ids as $item_id ) {
                $wpdb->update( ALW_Gift_DB::cards_table(), array( 'status' => 'suspended' ), array( 'order_item_id' => absint( $item_id ) ) );
            }
        }
    }

    public static function partial_refund_notice( $order_id, $refund_id ) {
        $refund = wc_get_order( $refund_id );
        if ( ! $refund ) { return; }
        $gift_item_ids = array();
        foreach ( $refund->get_items( 'line_item' ) as $item ) {
            $item_id = absint( $item->get_meta( '_refunded_item_id', true ) );
            if ( ! $item_id ) { continue; }
            $original = wc_get_order_item_meta( $item_id, '_alw_gc_amount_cents', true );
            if ( $original ) { $gift_item_ids[] = $item_id; }
        }
        if ( $gift_item_ids ) {
            self::suspend_purchased_cards( $order_id, $gift_item_ids );
            $order = wc_get_order( $order_id );
            if ( $order ) { $order->add_order_note( 'Alowidat: erstattete Geschenkgutscheine gesperrt. Bereits eingelöste Beträge und Auszahlung bitte manuell prüfen.' ); }
        }
        $order = wc_get_order( $order_id );
        if ( $order && $order->get_meta( '_alw_gc_card_id', true ) ) {
            $order->add_order_note( 'Alowidat: Teilrückerstattung bei mit Gutschein bezahlter Bestellung. Gutschrift des Guthabenanteils bitte manuell prüfen.' );
        }
    }

    public static function issue_from_order( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || ! $order->is_paid() ) { return; }
        foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
            if ( ! self::is_gift_product( $item->get_product_id() ) ) { continue; }
            $cents = (int) $item->get_meta( '_alw_gc_amount_cents', true );
            if ( $cents <= 0 ) { continue; }
            $email = sanitize_email( $item->get_meta( '_alw_gc_recipient_email', true ) );
            if ( ! $email ) { $email = $order->get_billing_email(); }
            if ( ! is_email( $email ) ) { $order->add_order_note( 'Gutschein konnte nicht versendet werden: ungültige Empfänger-E-Mail.' ); continue; }
            $id = ALW_Gift_DB::create( array(
                'amount_cents' => $cents,
                'order_id' => $order_id,
                'order_item_id' => $item_id,
                'recipient_name' => $item->get_meta( '_alw_gc_recipient_name', true ),
                'recipient_email' => $email,
                'sender_name' => $item->get_meta( '_alw_gc_sender_name', true ),
                'purchaser_email' => $order->get_billing_email(),
                'gift_message' => $item->get_meta( '_alw_gc_gift_message', true ),
            ) );
            if ( is_wp_error( $id ) ) { $order->add_order_note( 'Gutschein konnte nicht erstellt werden: ' . $id->get_error_message() ); continue; }
            $card = ALW_Gift_DB::get( $id );
            if ( $card && ! $card->delivered_at && self::send_gift_email( $card ) ) {
                $order->add_order_note( 'Alowidat Gutschein #' . $id . ' an ' . $email . ' verschickt.' );
            }
        }
    }

    public static function send_gift_email( $card ) {
        global $wpdb;
        if ( ! $card || ! is_email( $card->recipient_email ) ) { return false; }
        $code = ALW_Gift_DB::decrypt( $card->code_encrypted );
        if ( ! $code ) { return false; }
        $recipient = trim( $card->recipient_name ) ?: 'Du';
        $from = trim( $card->sender_name );
        $message = trim( $card->gift_message );
        $body = '<!doctype html><html><body style="margin:0;background:#f5f2ec;font-family:Arial,sans-serif;color:#24221f"><div style="max-width:600px;margin:30px auto;background:#fff;border:1px solid #e7dfd2;border-radius:16px;overflow:hidden"><div style="padding:36px;background:#171715;color:#fff;text-align:center"><div style="letter-spacing:5px;font-size:28px;font-weight:bold">ALOWIDAT</div><div style="font-size:12px;letter-spacing:3px;color:#b59b65;margin-top:8px">PARFÜMERIE</div><div style="font-size:14px;letter-spacing:3px;margin:45px 0 10px;color:#d8c8a8">GESCHENKGUTSCHEIN</div><div style="font-size:52px;font-weight:bold;color:#f2e1bb">' . self::money( $card->amount_cents ) . '</div></div><div style="padding:34px;text-align:center"><h2 style="font-size:25px;margin:0 0 10px">Ein Duft. Ein besonderer Moment.</h2><p>Hallo ' . esc_html( $recipient ) . ',</p><p>du hast einen digitalen Alowidat Geschenkgutschein erhalten.</p>';
        if ( $from ) { $body .= '<p>Von <strong>' . esc_html( $from ) . '</strong></p>'; }
        if ( $message ) { $body .= '<p style="padding:15px;background:#f8f6f2;border-radius:10px;font-style:italic">' . nl2br( esc_html( $message ) ) . '</p>'; }
        $body .= '<div style="margin:28px 0;padding:18px;background:#f8f6f2;border:1px dashed #b59b65;border-radius:8px;font-size:19px;letter-spacing:1px;font-weight:bold">' . esc_html( $code ) . '</div><p>Gib diesen Code beim Bezahlen im Onlineshop im Feld „Alowidat Geschenkgutschein“ ein. Er ist auch mit Rabattcodes und Sale-Angeboten kombinierbar. Restguthaben bleibt erhalten.</p><p><a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '" style="display:inline-block;padding:14px 28px;background:#171715;color:#fff;text-decoration:none;border-radius:6px">Jetzt Düfte entdecken</a></p><p style="color:#746f68;font-size:13px">Online einlösbar auf ' . esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ) . ' · Gültig bis ' . esc_html( wp_date( 'd.m.Y', strtotime( $card->expires_at . ' UTC' ) ) ) . '</p></div></div></body></html>';
        $subject = 'Dein Alowidat Geschenkgutschein über ' . wp_strip_all_tags( self::money( $card->amount_cents ) );
        $sent = wp_mail( $card->recipient_email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
        if ( $sent ) { $wpdb->update( ALW_Gift_DB::cards_table(), array( 'delivered_at' => current_time( 'mysql', true ) ), array( 'id' => $card->id ) ); }
        return $sent;
    }

    public static function clear_applied_session() {
        if ( function_exists( 'WC' ) && WC()->session ) {
            WC()->session->__unset( 'alw_gc_code' );
            WC()->session->__unset( 'alw_gc_card_id' );
            WC()->session->__unset( 'alw_gc_applied_cents' );
        }
    }
}