<?php
/**
 * Logged-in WooCommerce customer wallet for digital Alowidat gift cards.
 * Gift cards remain bearer instruments: saving a code in an account does not
 * transfer the legal entitlement or prevent someone else using that code.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ALW_Gift_Account {
    const ENDPOINT = 'geschenkguthaben';
    const META_KEY = '_alw_gc_saved_card_ids';

    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_endpoint' ), 9 );
        add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu_items' ), 35 );
        add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render' ) );
        add_action( 'template_redirect', array( __CLASS__, 'handle_save_code' ), 15 );
        add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'dashboard_link' ), 30 );
        add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'query_vars' ) );
    }

    public static function register_endpoint() {
        add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
        if ( get_option( 'alw_gc_account_rewrite_version' ) !== ALW_GC_VERSION ) {
            // On plugin update flush just once; do not flush on each page request.
            flush_rewrite_rules( false );
            update_option( 'alw_gc_account_rewrite_version', ALW_GC_VERSION, false );
        }
    }

    public static function query_vars( $vars ) {
        $vars[ self::ENDPOINT ] = self::ENDPOINT;
        return $vars;
    }

    public static function menu_items( $items ) {
        $result = array();
        $added  = false;
        foreach ( $items as $slug => $label ) {
            if ( self::ENDPOINT === $slug ) { continue; }
            if ( ! $added && 'edit-account' === $slug ) {
                $result[ self::ENDPOINT ] = 'Mein Guthaben';
                $added = true;
            }
            $result[ $slug ] = $label;
            if ( ! $added && 'payment-methods' === $slug ) {
                $result[ self::ENDPOINT ] = 'Mein Guthaben';
                $added = true;
            }
        }
        if ( ! $added ) {
            $result[ self::ENDPOINT ] = 'Mein Guthaben';
        }
        return $result;
    }

    private static function saved_ids( $user_id ) {
        $saved = get_user_meta( $user_id, self::META_KEY, true );
        if ( ! is_array( $saved ) ) { return array(); }
        return array_slice( array_values( array_unique( array_filter( array_map( 'absint', $saved ) ) ) ), 0, 200 );
    }

    private static function customer_cards( $user_id ) {
        global $wpdb;
        $user = get_userdata( $user_id );
        if ( ! $user ) { return array(); }
        $table = ALW_Gift_DB::cards_table();
        $email = sanitize_email( $user->user_email );
        $ids = self::saved_ids( $user_id );
        $params = array( $email );
        $query = "SELECT * FROM {$table} WHERE recipient_email = %s";
        if ( $ids ) {
            $query .= ' OR id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
            $params = array_merge( $params, $ids );
        }
        $query .= ' ORDER BY created_at DESC, id DESC LIMIT 200';
        return (array) $wpdb->get_results( $wpdb->prepare( $query, $params ) );
    }

    private static function transactions( $ids ) {
        global $wpdb;
        if ( ! $ids ) { return array(); }
        $ids = array_map( 'absint', $ids );
        $sql = 'SELECT gift_card_id, amount_cents, event_type, created_at FROM ' . ALW_Gift_DB::ledger_table()
            . ' WHERE gift_card_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')'
            . ' ORDER BY created_at DESC, id DESC LIMIT 15';
        return (array) $wpdb->get_results( $wpdb->prepare( $sql, $ids ) );
    }

    public static function handle_save_code() {
        if ( empty( $_POST['alw_gc_wallet_action'] ) || 'save' !== sanitize_key( wp_unslash( $_POST['alw_gc_wallet_action'] ) ) ) { return; }
        if ( ! is_user_logged_in() || ! function_exists( 'wc_get_account_endpoint_url' ) ) { return; }
        $link = wc_get_account_endpoint_url( self::ENDPOINT );
        $nonce = sanitize_text_field( wp_unslash( $_POST['alw_gc_wallet_nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'alw_gc_wallet_save' ) ) {
            wc_add_notice( 'Sicherheitsprüfung fehlgeschlagen. Bitte erneut versuchen.', 'error' );
            wp_safe_redirect( $link ); exit;
        }
        $user_id = get_current_user_id();
        $rate_key = 'alw_gc_wallet_rate_' . $user_id;
        $attempts = (int) get_transient( $rate_key );
        if ( $attempts >= 10 ) {
            wc_add_notice( 'Zu viele Eingaben. Bitte in einer Minute erneut versuchen.', 'error' );
            wp_safe_redirect( $link ); exit;
        }
        set_transient( $rate_key, $attempts + 1, MINUTE_IN_SECONDS );
        $code = sanitize_text_field( wp_unslash( $_POST['alw_gc_wallet_code'] ?? '' ) );
        $card = ALW_Gift_DB::find_code( $code );
        if ( ! ALW_Gift_DB::active( $card ) ) {
            wc_add_notice( 'Der Gutscheincode ist ungültig, gesperrt, abgelaufen oder bereits aufgebraucht.', 'error' );
            wp_safe_redirect( $link ); exit;
        }
        $ids = self::saved_ids( $user_id );
        if ( in_array( (int) $card->id, $ids, true ) ) {
            wc_add_notice( 'Dieser Geschenkgutschein ist bereits in deinem Konto hinterlegt.', 'notice' );
        } elseif ( count( $ids ) >= 200 ) {
            wc_add_notice( 'Es können maximal 200 Gutscheine gespeichert werden.', 'error' );
        } else {
            $ids[] = (int) $card->id;
            update_user_meta( $user_id, self::META_KEY, $ids );
            wc_add_notice( 'Geschenkgutschein wurde zu deiner Guthabenübersicht hinzugefügt.', 'success' );
        }
        wp_safe_redirect( $link ); exit;
    }

    private static function card_status( $card ) {
        return ALW_Gift_Cards::status_label( $card );
    }

    public static function render() {
        if ( ! is_user_logged_in() ) {
            echo '<p>Bitte melde dich an, um deine Gutscheine zu sehen.</p>';
            return;
        }
        $user_id = get_current_user_id();
        $cards = self::customer_cards( $user_id );
        $total_cents = 0;
        $active_count = 0;
        foreach ( $cards as $card ) {
            if ( ALW_Gift_DB::active( $card ) ) {
                $total_cents += (int) $card->balance_cents;
                $active_count++;
            }
        }
        ?>
        <section class="alw-gc-account" aria-label="Mein Gutscheinguthaben">
            <div class="alw-gc-wallet-head">
                <div>
                    <span class="alw-gc-wallet-eyebrow">ALOWIDAT · MEIN KONTO</span>
                    <h2>Mein Guthaben</h2>
                    <p>Deine digitalen Geschenkgutscheine auf einen Blick.</p>
                </div>
                <span class="alw-gc-wallet-head-icon" aria-hidden="true">✦</span>
            </div>
            <div class="alw-gc-wallet-hero">
                <div class="alw-gc-wallet-hero-top"><span>ALOWIDAT</span><small>PARFÜMERIE</small></div>
                <div class="alw-gc-wallet-hero-content">
                    <span>Aktuell verfügbares Geschenkguthaben</span>
                    <strong><?php echo wp_kses_post( ALW_Gift_Cards::money( $total_cents ) ); ?></strong>
                    <small><?php echo esc_html( sprintf( _n( '%d aktiver Gutschein', '%d aktive Gutscheine', $active_count, 'alowidat-geschenkgutscheine' ), $active_count ) ); ?> · Nur online einlösbar</small>
                </div>
                <div class="alw-gc-wallet-hero-mark" aria-hidden="true">✦</div>
            </div>
            <div class="alw-gc-wallet-columns">
                <div class="alw-gc-wallet-card alw-gc-wallet-save">
                    <div class="alw-gc-wallet-section-head"><h3>Gutschein hinzufügen</h3><p>Du hast einen Gutschein geschenkt bekommen? Speichere ihn in deiner Übersicht.</p></div>
                    <form method="post" action="<?php echo esc_url( wc_get_account_endpoint_url( self::ENDPOINT ) ); ?>">
                        <?php wp_nonce_field( 'alw_gc_wallet_save', 'alw_gc_wallet_nonce' ); ?>
                        <input type="hidden" name="alw_gc_wallet_action" value="save">
                        <label for="alw-gc-wallet-code">Gutscheincode</label>
                        <div class="alw-gc-wallet-save-fields">
                            <input id="alw-gc-wallet-code" type="text" name="alw_gc_wallet_code" autocomplete="off" required maxlength="64" placeholder="ALW-XXXXXX-XXXXXX-XXXXXX-XXXXXX">
                            <button type="submit">Hinzufügen</button>
                        </div>
                    </form>
                    <p class="alw-gc-wallet-help">Geschenkgutscheine, die an deine Konto-E-Mail-Adresse gesendet wurden, erscheinen automatisch. Das Speichern ändert nichts daran, wer den Code einlösen kann.</p>
                </div>
                <div class="alw-gc-wallet-card alw-gc-wallet-action">
                    <span class="alw-gc-wallet-sparkle" aria-hidden="true">✧</span>
                    <h3>Freude verschenken</h3>
                    <p>Überrasche jemanden mit einem besonderen Duftmoment.</p>
                    <a href="<?php echo esc_url( self::purchase_page_url() ); ?>">Gutschein kaufen <span aria-hidden="true">→</span></a>
                </div>
            </div>
            <div class="alw-gc-wallet-list">
                <div class="alw-gc-wallet-section-head"><h3>Meine Gutscheine</h3><p>Gültigkeit, Guthaben und Gutscheincode im Überblick.</p></div>
                <?php if ( ! $cards ) : ?>
                    <div class="alw-gc-wallet-empty"><span aria-hidden="true">✦</span><strong>Noch keine Gutscheine vorhanden</strong><p>Du kannst hier jederzeit deinen ersten Alowidat Geschenkgutschein hinzufügen.</p></div>
                <?php else : ?>
                    <div class="alw-gc-wallet-cards">
                        <?php foreach ( $cards as $card ) :
                            $status = self::card_status( $card );
                            $code = ALW_Gift_DB::decrypt( $card->code_encrypted );
                            ?>
                            <article class="alw-gc-wallet-voucher">
                                <div class="alw-gc-wallet-voucher-main">
                                    <span class="alw-gc-wallet-voucher-symbol" aria-hidden="true">✦</span>
                                    <div><strong>Geschenkgutschein #<?php echo esc_html( $card->id ); ?></strong><span>ALW-••••-<?php echo esc_html( $card->code_last4 ); ?> · Gültig bis <?php echo esc_html( wp_date( 'd.m.Y', strtotime( $card->expires_at . ' UTC' ) ) ); ?></span></div>
                                </div>
                                <div class="alw-gc-wallet-voucher-side"><strong><?php echo wp_kses_post( ALW_Gift_Cards::money( ALW_Gift_DB::active( $card ) ? $card->balance_cents : 0 ) ); ?></strong><span class="alw-gc-wallet-badge <?php echo 'Aktiv' === $status ? 'is-active' : ''; ?>"><?php echo esc_html( $status ); ?></span></div>
                                <?php if ( $code && 'Aktiv' === $status ) : ?>
                                    <details class="alw-gc-wallet-code-reveal"><summary>Gutscheincode anzeigen</summary><code><?php echo esc_html( $code ); ?></code><span>Den Code kannst du im Warenkorb oder Checkout einlösen.</span></details>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php $events = self::transactions( wp_list_pluck( $cards, 'id' ) ); ?>
            <?php if ( $events ) : ?>
                <div class="alw-gc-wallet-history">
                    <div class="alw-gc-wallet-section-head"><h3>Letzte Guthabenbewegungen</h3><p>Die 15 neuesten Einträge deiner angezeigten Gutscheine.</p></div>
                    <div class="alw-gc-wallet-activity">
                        <?php foreach ( $events as $event ) :
                            $desc = array( 'issue' => 'Gutschein erhalten', 'redeem' => 'Im Shop eingelöst', 'restore' => 'Guthaben zurückgebucht', 'suspend' => 'Gutschein gesperrt', 'activate' => 'Gutschein aktiviert' );
                            $event_label = $desc[ $event->event_type ] ?? 'Guthabenänderung';
                            ?>
                            <div class="alw-gc-wallet-activity-row"><div><strong><?php echo esc_html( $event_label ); ?></strong><small><?php echo esc_html( wp_date( 'd.m.Y', strtotime( $event->created_at . ' UTC' ) ) ); ?> · Gutschein #<?php echo esc_html( $event->gift_card_id ); ?></small></div><span class="<?php echo (int) $event->amount_cents < 0 ? 'is-debit' : ''; ?>"><?php echo (int) $event->amount_cents > 0 ? '+' : ''; ?><?php echo wp_kses_post( ALW_Gift_Cards::money( $event->amount_cents ) ); ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <p class="alw-gc-wallet-disclaimer">Guthaben ist kein Rabatt: Es wird nach den regulären Preisen, Aktionen und Steuern auf den Bestellbetrag angerechnet. Der Gutscheincode ist wie Bargeld zu behandeln und darf nicht an Unbefugte weitergegeben werden.</p>
        </section>
        <?php
    }

    private static function purchase_page_url() {
        $page_id = absint( get_option( 'alw_gc_purchase_page_id', 0 ) );
        if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
            return get_permalink( $page_id );
        }
        // Conservative fallback: does not assume a page slug exists.
        $shop_url = wc_get_page_permalink( 'shop' );
        return $shop_url ? $shop_url : home_url( '/' );
    }

    public static function dashboard_link() {
        if ( ! is_user_logged_in() ) { return; }
        $cards = self::customer_cards( get_current_user_id() );
        $total = 0;
        foreach ( $cards as $card ) { if ( ALW_Gift_DB::active( $card ) ) { $total += (int) $card->balance_cents; } }
        echo '<div class="alw-gc-wallet-dashboard-shortcut"><div><span>DEIN ALOWIDAT GUTHABEN</span><strong>' . wp_kses_post( ALW_Gift_Cards::money( $total ) ) . '</strong><small>Digitale Geschenkgutscheine</small></div><a href="' . esc_url( wc_get_account_endpoint_url( self::ENDPOINT ) ) . '">Mein Guthaben ansehen <span aria-hidden="true">→</span></a></div>';
    }
}