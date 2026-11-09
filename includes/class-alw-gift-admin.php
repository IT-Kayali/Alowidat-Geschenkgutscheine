<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ALW_Gift_Admin {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ), 35 );
        add_action( 'admin_init', array( __CLASS__, 'actions' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( ALW_GC_FILE ), array( __CLASS__, 'plugin_links' ) );
    }

    public static function plugin_links( $links ) {
        array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=alw-gift-cards&tab=settings' ) ) . '">Einstellungen</a>' );
        return $links;
    }

    public static function menu() {
        add_submenu_page( 'woocommerce', 'Alowidat Gutscheine', 'Geschenkgutscheine', 'manage_woocommerce', 'alw-gift-cards', array( __CLASS__, 'page' ) );
    }

    public static function assets( $hook ) {
        if ( 'woocommerce_page_alw-gift-cards' === $hook ) {
            wp_enqueue_style( 'alw-gc-admin', ALW_GC_URL . 'assets/css/admin.css', array(), ALW_GC_VERSION );
        }
    }

    public static function actions() {
        if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_POST['alw_gc_admin_action'] ) ) { return; }
        $action = sanitize_key( wp_unslash( $_POST['alw_gc_admin_action'] ) );
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'alw_gc_admin_' . $action ) ) {
            wp_die( 'Sicherheitsüberprüfung fehlgeschlagen.' );
        }
        $redirect = admin_url( 'admin.php?page=alw-gift-cards' );
        if ( 'classic_checkout' === $action ) {
            $converted = 0;
            foreach ( array( 'cart' => array( 'woocommerce/cart', '[woocommerce_cart]' ), 'checkout' => array( 'woocommerce/checkout', '[woocommerce_checkout]' ) ) as $page_type => $config ) {
                $page_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( $page_type ) : 0;
                $page = $page_id > 0 ? get_post( $page_id ) : null;
                if ( ! $page || 'page' !== $page->post_type || ! current_user_can( 'edit_post', $page_id ) ) { continue; }
                // Fremde Layouts, Elementor und bestehende klassische Seiten NIE überschreiben.
                if ( ! has_block( $config[0], $page ) ) { continue; }
                // Original einmalig sichern; Rückweg ist im Seiteneditor oder per Revision möglich.
                if ( ! metadata_exists( 'post', $page_id, '_alw_gc_original_blocks' ) ) {
                    update_post_meta( $page_id, '_alw_gc_original_blocks', wp_slash( $page->post_content ) );
                }
                $result = wp_update_post( array(
                    'ID' => $page_id,
                    'post_content' => '<!-- wp:shortcode -->' . $config[1] . '<!-- /wp:shortcode -->',
                ), true );
                if ( ! is_wp_error( $result ) ) { $converted++; }
            }
            $redirect = add_query_arg( array( 'tab' => 'settings', 'alw_notice' => $converted ? 'classicdone' : 'classicnone' ), $redirect );
        } elseif ( 'settings' === $action ) {
            $old = ALW_Gift_Cards::settings();
            $raw_amounts = sanitize_text_field( wp_unslash( $_POST['amounts'] ?? '' ) );
            $amounts = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $raw_amounts ) ) ) ) );
            $amounts = array_filter( $amounts, static function( $v ) { return $v > 0 && $v <= 10000; } );
            // Ein leeres Maximum bewusst speichern: kein konfiguriertes Oberlimit.
            // Das technische Sicherheitslimit von 10.000 EUR je Gutschein bleibt bestehen.
            $min = min( 10000, max( 1, (float) wc_format_decimal( wp_unslash( $_POST['minimum'] ?? 20 ), 2 ) ) );
            $raw_max = isset( $_POST['maximum'] ) ? trim( (string) wp_unslash( $_POST['maximum'] ) ) : '';
            $max = '' === $raw_max ? '' : min( 10000, max( $min, (float) wc_format_decimal( $raw_max, 2 ) ) );
            $old['amounts'] = $amounts ? implode( ',', $amounts ) : '25,50,100';
            $old['minimum'] = $min;
            $old['maximum'] = $max;
            $old['gold'] = sanitize_hex_color( wp_unslash( $_POST['gold'] ?? '' ) ) ?: '#d4b45c';
            $old['dark'] = sanitize_hex_color( wp_unslash( $_POST['dark'] ?? '' ) ) ?: '#212121';
            $old['auto_redeem_field'] = isset( $_POST['auto_redeem_field'] ) ? 'yes' : 'no';
            $notice = isset( $_POST['important_notice'] ) && is_string( $_POST['important_notice'] ) ? sanitize_textarea_field( wp_unslash( $_POST['important_notice'] ) ) : '';
            $old['important_notice'] = function_exists( 'mb_substr' ) ? mb_substr( $notice, 0, 1500 ) : substr( $notice, 0, 1500 );
            update_option( 'alw_gc_settings', $old, false );
            update_option( 'alw_gc_purchase_page_id', absint( $_POST['purchase_page_id'] ?? 0 ), false );
            $redirect = add_query_arg( array( 'tab' => 'settings', 'alw_notice' => 'saved' ), $redirect );
        } elseif ( 'issue' === $action ) {
            $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
            $amount = ALW_Gift_Cards::cents( wp_unslash( $_POST['amount'] ?? 0 ) );
            if ( ! is_email( $email ) || $amount < 100 || $amount > 1000000 ) {
                $redirect = add_query_arg( array( 'tab' => 'issue', 'alw_notice' => 'invalid' ), $redirect );
            } else {
                $id = ALW_Gift_DB::create( array(
                    'amount_cents' => $amount,
                    'recipient_name' => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
                    'recipient_email' => $email,
                    'sender_name' => 'Alowidat',
                    'gift_message' => sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ),
                    'created_by' => get_current_user_id(),
                ) );
                if ( ! is_wp_error( $id ) ) {
                    $sent = ALW_Gift_Cards::send_gift_email( ALW_Gift_DB::get( $id ) );
                    $redirect = add_query_arg( array( 'view' => $id, 'alw_notice' => $sent ? 'issued' : 'emailfailed' ), $redirect );
                } else { $redirect = add_query_arg( array( 'tab' => 'issue', 'alw_notice' => 'error' ), $redirect ); }
            }
        } elseif ( in_array( $action, array( 'suspend', 'activate', 'resend' ), true ) ) {
            global $wpdb;
            $id = absint( $_POST['card_id'] ?? 0 );
            $card = ALW_Gift_DB::get( $id );
            if ( $card ) {
                if ( 'resend' === $action ) {
                    $success = ALW_Gift_Cards::send_gift_email( $card );
                    $notice = $success ? 'resent' : 'emailfailed';
                } else {
                    $status = 'suspend' === $action ? 'suspended' : 'active';
                    $wpdb->update( ALW_Gift_DB::cards_table(), array( 'status' => $status ), array( 'id' => $id ) );
                    $wpdb->insert( ALW_Gift_DB::ledger_table(), array(
                        'gift_card_id' => $id, 'event_key' => wp_generate_uuid4(), 'event_type' => $action,
                        'amount_cents' => 0, 'order_id' => 0, 'note' => 'Manueller Statuswechsel',
                        'actor_id' => get_current_user_id(), 'created_at' => current_time( 'mysql', true ),
                    ) );
                    $notice = $action;
                }
                $redirect = add_query_arg( array( 'view' => $id, 'alw_notice' => $notice ), $redirect );
            }
        }
        wp_safe_redirect( $redirect );
        exit;
    }

    public static function page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Keine Berechtigung.' ); }
        $tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'cards' ) );
        $view = absint( $_GET['view'] ?? 0 );
        ?>
        <div class="wrap alw-gc-admin">
            <div class="alw-gc-admin-head"><div><span class="alw-gc-admin-overline">ALOWIDAT · E-COMMERCE</span><h1>Geschenkgutscheine</h1><p>Digitale Geschenke verwalten. Guthaben kontrollieren. Premium-Erlebnisse schaffen.</p></div><a class="button button-primary alw-gc-admin-button" href="<?php echo esc_url( admin_url( 'admin.php?page=alw-gift-cards&tab=issue' ) ); ?>">+ Gutschein ausstellen</a></div>
            <?php self::notice(); ?>
            <nav class="alw-gc-admin-tabs">
                <a class="<?php echo ! $view && 'cards' === $tab ? 'active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=alw-gift-cards' ) ); ?>">Übersicht</a>
                <a class="<?php echo 'issue' === $tab ? 'active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=alw-gift-cards&tab=issue' ) ); ?>">Manuell ausstellen</a>
                <a class="<?php echo 'settings' === $tab ? 'active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=alw-gift-cards&tab=settings' ) ); ?>">Einstellungen & Shortcodes</a>
            </nav>
            <?php
            if ( $view ) { self::card_detail( $view ); }
            elseif ( 'issue' === $tab ) { self::issue_tab(); }
            elseif ( 'settings' === $tab ) { self::settings_tab(); }
            else { self::overview_tab(); }
            ?>
        </div>
        <?php
    }

    private static function notice() {
        $key = sanitize_key( wp_unslash( $_GET['alw_notice'] ?? '' ) );
        $texts = array(
            'saved' => 'Einstellungen gespeichert.', 'issued' => 'Gutschein erstellt und per E-Mail versendet.',
            'resent' => 'Gutschein-E-Mail erneut versendet.', 'suspend' => 'Gutschein gesperrt.',
            'activate' => 'Gutschein wieder aktiviert.', 'invalid' => 'Bitte gültige E-Mail und Gutscheinwert eingeben.',
            'emailfailed' => 'Gutschein gespeichert, aber E-Mail nicht versendet. Bitte SMTP und Server-Logs prüfen.',
            'error' => 'Der Gutschein konnte nicht erstellt werden.',
            'classicdone' => 'WooCommerce-Blockseiten wurden auf klassische Warenkorb-/Checkout-Shortcodes umgestellt. Originalinhalte sind gesichert. Bitte Layout und Testkauf prüfen.',
            'classicnone' => 'Keine unterstützten WooCommerce-Blockseiten erkannt oder keine Bearbeitungsrechte. Bestehende Layouts wurden nicht verändert.',
        );
        if ( isset( $texts[ $key ] ) ) {
            echo '<div class="notice ' . ( in_array( $key, array( 'invalid', 'error', 'emailfailed' ), true ) ? 'notice-error' : 'notice-success' ) . ' is-dismissible"><p>' . esc_html( $texts[ $key ] ) . '</p></div>';
        }
    }

    private static function overview_tab() {
        global $wpdb;
        $table = ALW_Gift_DB::cards_table();
        $stats = $wpdb->get_row( "SELECT COUNT(*) AS total_cards, COALESCE(SUM(amount_cents),0) AS issued_cents, COALESCE(SUM(CASE WHEN status='active' THEN balance_cents ELSE 0 END),0) AS open_cents FROM {$table}" );
        $count = (int) $stats->total_cards;
        $total = (int) $stats->issued_cents;
        $open = (int) $stats->open_cents;
        ?>
        <div class="alw-gc-admin-stats">
            <div><span>Gutscheine insgesamt</span><strong><?php echo esc_html( number_format_i18n( $count ) ); ?></strong><small>Digital ausgestellte Karten</small></div>
            <div><span>Ausgestellt</span><strong><?php echo wp_kses_post( ALW_Gift_Cards::money( $total ) ); ?></strong><small>Gesamter Nennwert</small></div>
            <div><span>Offenes Guthaben</span><strong><?php echo wp_kses_post( ALW_Gift_Cards::money( $open ) ); ?></strong><small>Aktive Karten inkl. ggf. abgelaufener Bestände*</small></div>
        </div>
        <?php
        $page = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $per_page = 25;
        $offset = ( $page - 1 ) * $per_page;
        $search = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );
        $where = '';
        $params = array();
        if ( $search ) {
            $where = ' WHERE code_last4 LIKE %s OR recipient_email LIKE %s OR recipient_name LIKE %s OR CAST(id AS CHAR) = %s ';
            $params = array( '%' . $wpdb->esc_like( $search ) . '%', '%' . $wpdb->esc_like( $search ) . '%', '%' . $wpdb->esc_like( $search ) . '%', $search );
        }
        $sql = "SELECT * FROM {$table}" . $where . ' ORDER BY id DESC LIMIT %d OFFSET %d';
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, array( $per_page, $offset ) ) ) );
        $filtered_count = $search ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table}" . $where, $params ) ) : $count;
        ?>
        <div class="alw-gc-admin-panel"><div class="alw-gc-admin-panel-title"><div><h2>Gutscheinverwaltung</h2><p>Codes werden aus Sicherheitsgründen nur maskiert angezeigt.</p></div><form method="get"><input type="hidden" name="page" value="alw-gift-cards"><input name="search" type="search" value="<?php echo esc_attr( $search ); ?>" placeholder="E-Mail, ID oder letzte 4 Zeichen"><button class="button" type="submit">Suchen</button></form></div>
            <div class="alw-gc-admin-table"><table class="widefat striped"><thead><tr><th>Gutschein</th><th>Empfänger</th><th>Nennwert</th><th>Guthaben</th><th>Status</th><th>Erstellt</th><th></th></tr></thead><tbody>
            <?php if ( ! $rows ) : ?><tr><td colspan="7">Noch keine Gutscheine vorhanden.</td></tr><?php endif; ?>
            <?php foreach ( $rows as $card ) : ?><tr>
                <td><strong>#<?php echo esc_html( $card->id ); ?></strong><br><small>ALW-••••-<?php echo esc_html( $card->code_last4 ); ?></small></td>
                <td><?php echo esc_html( $card->recipient_name ?: '–' ); ?><br><small><?php echo esc_html( $card->recipient_email ); ?></small></td>
                <td><?php echo wp_kses_post( ALW_Gift_Cards::money( $card->amount_cents ) ); ?></td>
                <td><strong><?php echo wp_kses_post( ALW_Gift_Cards::money( $card->balance_cents ) ); ?></strong></td>
                <td><span class="alw-gc-status <?php echo 'Aktiv' === ALW_Gift_Cards::status_label( $card ) ? 'ok' : ''; ?>"><?php echo esc_html( ALW_Gift_Cards::status_label( $card ) ); ?></span></td>
                <td><?php echo esc_html( wp_date( 'd.m.Y', strtotime( $card->created_at . ' UTC' ) ) ); ?></td>
                <td><a href="<?php echo esc_url( admin_url( 'admin.php?page=alw-gift-cards&view=' . $card->id ) ); ?>">Details</a></td>
            </tr><?php endforeach; ?>
            </tbody></table></div>
            <?php if ( $filtered_count > $per_page ) : ?>
                <div class="alw-gc-pagination"><?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $page, 'total' => ceil( $filtered_count / $per_page ) ) ) ); ?></div>
            <?php endif; ?>
        </div>
        <p class="description">* Übersicht enthält auch noch vorhandenes Guthaben auf abgelaufenen aktiven Karten; für Buchhaltung/Abgrenzung bitte eine separate Auswertung vornehmen. Fehlende E-Mails kannst du in den Kartendetails erneut versenden.</p>
        <?php
    }

    private static function card_detail( $id ) {
        global $wpdb;
        $card = ALW_Gift_DB::get( $id );
        if ( ! $card ) { echo '<p>Gutschein nicht gefunden.</p>'; return; }
        $ledger = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . ALW_Gift_DB::ledger_table() . ' WHERE gift_card_id = %d ORDER BY id DESC LIMIT 100', $id ) );
        ?>
        <div class="alw-gc-admin-panel"><p><a href="<?php echo esc_url( admin_url( 'admin.php?page=alw-gift-cards' ) ); ?>">← Zur Übersicht</a></p><h2>Gutschein #<?php echo esc_html( $id ); ?></h2>
        <div class="alw-gc-detail-grid"><div><label>Code</label><strong>ALW-••••-<?php echo esc_html( $card->code_last4 ); ?></strong><small>Vollständigen Code nur an die gespeicherte Empfängeradresse erneut senden.</small></div><div><label>Guthaben</label><strong><?php echo wp_kses_post( ALW_Gift_Cards::money( $card->balance_cents ) ); ?></strong></div><div><label>Ursprünglicher Wert</label><strong><?php echo wp_kses_post( ALW_Gift_Cards::money( $card->amount_cents ) ); ?></strong></div><div><label>Status</label><strong><?php echo esc_html( ALW_Gift_Cards::status_label( $card ) ); ?></strong></div><div><label>Empfänger</label><strong><?php echo esc_html( $card->recipient_email ); ?></strong></div><div><label>Ablauf</label><strong><?php echo esc_html( wp_date( 'd.m.Y', strtotime( $card->expires_at . ' UTC' ) ) ); ?></strong></div><div><label>Bestellung</label><strong><?php if ( $card->order_id ) : ?><a href="<?php $related_order = wc_get_order( $card->order_id ); echo esc_url( $related_order ? $related_order->get_edit_order_url() : admin_url( 'admin.php?page=wc-orders' ) ); ?>">#<?php echo esc_html( $card->order_id ); ?></a><?php else : ?>Manuell<?php endif; ?></strong></div><div><label>E-Mail</label><strong><?php echo $card->delivered_at ? 'Zuletzt versendet: ' . esc_html( wp_date( 'd.m.Y H:i', strtotime( $card->delivered_at . ' UTC' ) ) ) : 'Noch nicht erfolgreich versendet'; ?></strong></div></div>
        <div class="alw-gc-admin-actions">
        <?php foreach ( array( 'resend' => 'E-Mail erneut senden', 'suspend' => 'Gutschein sperren', 'activate' => 'Wieder aktivieren' ) as $action => $label ) :
            if ( 'suspend' === $action && 'suspended' === $card->status ) { continue; }
            if ( 'activate' === $action && 'active' === $card->status ) { continue; }
            ?><form method="post"><?php wp_nonce_field( 'alw_gc_admin_' . $action ); ?><input type="hidden" name="alw_gc_admin_action" value="<?php echo esc_attr( $action ); ?>"><input type="hidden" name="card_id" value="<?php echo esc_attr( $id ); ?>"><button type="submit" class="button" <?php echo 'suspend' === $action ? 'onclick="return confirm(\'Gutschein wirklich sperren?\')"' : ''; ?>><?php echo esc_html( $label ); ?></button></form>
        <?php endforeach; ?></div>
        </div>
        <div class="alw-gc-admin-panel"><h2>Transaktionen</h2><div class="alw-gc-admin-table"><table class="widefat striped"><thead><tr><th>Datum</th><th>Aktion</th><th>Betrag</th><th>Bestellung</th><th>Notiz</th></tr></thead><tbody>
        <?php foreach ( $ledger as $line ) : ?><tr><td><?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $line->created_at . ' UTC' ) ) ); ?></td><td><?php echo esc_html( $line->event_type ); ?></td><td><?php echo esc_html( $line->amount_cents > 0 ? '+' : '' ); ?><?php echo wp_kses_post( ALW_Gift_Cards::money( $line->amount_cents ) ); ?></td><td><?php echo $line->order_id ? '#' . esc_html( $line->order_id ) : '–'; ?></td><td><?php echo esc_html( $line->note ); ?></td></tr><?php endforeach; ?>
        </tbody></table></div></div>
        <?php
    }

    private static function issue_tab() { ?>
        <div class="alw-gc-admin-panel alw-gc-admin-form"><h2>Gutschein manuell ausstellen</h2><p>Für Service- und Kulanzfälle. Achtung: Der Gutschein wird ohne bezahlte WooCommerce-Bestellung erzeugt und sofort versendet.</p>
            <form method="post"><?php wp_nonce_field( 'alw_gc_admin_issue' ); ?><input type="hidden" name="alw_gc_admin_action" value="issue">
                <label for="alw-issue-email">Empfänger-E-Mail *</label><input type="email" id="alw-issue-email" name="email" required>
                <label for="alw-issue-name">Empfängername</label><input type="text" id="alw-issue-name" name="name" maxlength="120">
                <label for="alw-issue-amount">Betrag in EUR *</label><input type="number" id="alw-issue-amount" name="amount" min="1" max="10000" step="0.01" required>
                <label for="alw-issue-message">Nachricht (optional)</label><textarea id="alw-issue-message" name="message" rows="4" maxlength="500"></textarea>
                <p><button class="button button-primary" type="submit">Gutschein erstellen und versenden</button></p>
            </form></div>
        <?php }

    private static function settings_tab() {
        $s = ALW_Gift_Cards::settings(); ?>
        <div class="alw-gc-admin-panel alw-gc-admin-form"><h2>Shop-Einstellungen</h2>
        <form method="post"><?php wp_nonce_field( 'alw_gc_admin_settings' ); ?><input type="hidden" name="alw_gc_admin_action" value="settings">
            <label for="alw-amounts">Feste Gutscheinwerte in EUR</label><input type="text" id="alw-amounts" name="amounts" value="<?php echo esc_attr( $s['amounts'] ); ?>"><p class="description">Kommagetrennt, z. B. 25,50,100</p>
            <div class="alw-gc-admin-two"><div><label for="alw-min">Wunschbetrag mindestens (€)</label><input id="alw-min" type="number" min="1" max="10000" step="0.01" name="minimum" value="<?php echo esc_attr( $s['minimum'] ); ?>"></div><div><label for="alw-max">Wunschbetrag höchstens (€) <span style="font-weight:normal">(optional)</span></label><input id="alw-max" type="number" min="1" max="10000" step="0.01" name="maximum" value="<?php echo esc_attr( $s['maximum'] ); ?>" placeholder="Leer lassen für „Ab … €“"><p class="description">Leer lassen: Im Shop steht nur „Ab <?php echo esc_html( number_format_i18n( (float) $s['minimum'], floor( (float) $s['minimum'] ) === (float) $s['minimum'] ? 0 : 2 ) ); ?> €“. Technisches Sicherheitslimit: 10.000 € pro Gutschein.</p></div></div>
            <div class="alw-gc-admin-two"><div><label for="alw-gold">Akzentfarbe / Gold</label><input type="color" id="alw-gold" name="gold" value="<?php echo esc_attr( $s['gold'] ); ?>"></div><div><label for="alw-dark">Dunkle Hauptfarbe</label><input type="color" id="alw-dark" name="dark" value="<?php echo esc_attr( $s['dark'] ); ?>"></div></div>
            <label for="alw-purchase-page">Seite für Geschenkgutscheinkauf</label>
            <?php wp_dropdown_pages( array( 'name' => 'purchase_page_id', 'id' => 'alw-purchase-page', 'selected' => absint( get_option( 'alw_gc_purchase_page_id', 0 ) ), 'show_option_none' => '— Seite auswählen —', 'option_none_value' => '0' ) ); ?>
            <p class="description">Auf dieser Seite muss der Shortcode [alowidat_gutscheine] eingebunden sein. Der Link wird im Kundenkonto verwendet.</p>
            <p><label for="alw-auto-redeem"><input type="checkbox" id="alw-auto-redeem" name="auto_redeem_field" value="yes" <?php checked( 'yes', $s['auto_redeem_field'] ); ?>> Zusätzliches Alowidat-Gutschein-/Rabattfeld automatisch im Warenkorb und an der Kasse anzeigen</label></p>
            <p class="description">Deaktivieren: Sofern WooCommerce-Rabattcodes unter WooCommerce → Einstellungen → Allgemein aktiviert sind, bleiben die normalen WooCommerce-Gutscheinfelder sichtbar und akzeptieren weiterhin Alowidat-Geschenkguthaben sowie Rabattcodes. Auch ein angewendeter Geschenkgutschein kann in der Bestellsumme entfernt werden. Der Shortcode [alowidat_gutschein_einloesen] bleibt auf Wunsch manuell nutzbar.</p>
            <hr>
            <h3>Wichtige Hinweise auf der Gutscheinseite</h3>
            <label for="alw-important-notice">Hinweistext (manuell bearbeiten)</label>
            <textarea id="alw-important-notice" name="important_notice" rows="5" maxlength="1500" style="width:100%;max-width:760px" placeholder="Hinweistext für die Gutscheinseite …"><?php echo esc_textarea( $s['important_notice'] ); ?></textarea>
            <p class="description">Der Text steht auf der Kaufseite und bei Gutscheinkäufen an der Kasse zusammen mit dem gesetzlichen Widerrufshinweis in <strong>einem einzigen Hinweisfeld</strong>. Zeilenumbrüche bleiben erhalten. Leer lassen blendet nur den selbst verfassten Zusatztext aus; der gesetzliche Hinweis bleibt sichtbar. Rabattcodes können beim Kauf weiterer normaler Produkte verwendet werden, nie zur Preisreduzierung des Geschenkgutscheins.</p>
            <p><button class="button button-primary" type="submit">Einstellungen speichern</button></p>
        </form></div>
        <div class="alw-gc-admin-panel"><h2>Shortcodes</h2><p>Erstelle eine WordPress-Seite und füge die folgenden Shortcodes in einen Shortcode-Block oder in Elementor ein.</p>
            <div class="alw-gc-code-box"><code>[alowidat_gutscheine]</code><span>Gutscheinverkauf mit Auswahl und Design</span></div>
            <div class="alw-gc-code-box"><code>[alowidat_guthaben]</code><span>Guthabenabfrage über Gutscheincode</span></div>
            <div class="alw-gc-code-box"><code>[alowidat_gutschein_einloesen]</code><span>Einlösefeld (wird im klassischen Warenkorb/Checkout automatisch eingeblendet)</span></div>
        </div>
        <div class="alw-gc-admin-panel alw-gc-admin-form">
            <h2>Warenkorb- & Checkout-Kompatibilität</h2>
            <p>Die sichere Guthabeneinlösung dieses Plugins arbeitet mit dem <strong>klassischen WooCommerce-Warenkorb und Checkout</strong>. Ist auf einer WooCommerce-Seite ein Cart-/Checkout-Block eingebunden, erscheint deshalb für Administratoren eine Kompatibilitätswarnung. Eine bloße Änderung der Kompatibilitätserklärung würde den Checkout nicht funktionsfähig machen.</p>
            <p>Auf Wunsch können die <strong>zugewiesenen WooCommerce-Blockseiten</strong> mit einem Klick zu den offiziellen klassischen Shortcodes wechseln. Bestehende klassische Seiten und Seiten ohne die entsprechenden WooCommerce-Blöcke bleiben unverändert. Vorheriger Blockinhalt wird als Post-Metadatum gesichert; WordPress speichert außerdem eine Seitenrevision.</p>
            <form method="post">
                <?php wp_nonce_field( 'alw_gc_admin_classic_checkout' ); ?>
                <input type="hidden" name="alw_gc_admin_action" value="classic_checkout">
                <p><button type="submit" class="button button-secondary" onclick="return confirm('Wirklich die WooCommerce-Blockseiten auf die klassischen Shortcodes umstellen? Das kann das Layout verändern. Bitte zuvor ein Backup erstellen.')">Warenkorb und Kasse sicher auf klassische Ansicht umstellen</button></p>
            </form>
            <p class="description">Alternativ manuell in WordPress → Seiten → Warenkorb: <code>[woocommerce_cart]</code>, und Kasse: <code>[woocommerce_checkout]</code>. Nach der Umstellung einmal Warenkorb und Checkout testen und den Website-Cache leeren.</p>
        </div>
        <div class="alw-gc-admin-panel alw-gc-important"><h2>Vor dem Live-Einsatz prüfen</h2><p>Dieses Plugin unterstützt den <strong>klassischen WooCommerce-Warenkorb und Checkout</strong>, nicht die Cart/Checkout-Blocks. Geschenkgutscheine werden als Mehrzweckgutschein ohne Steuer beim Verkauf behandelt; die Einordnung als Mehrzweckgutschein bitte vor Produktivbetrieb steuerlich bestätigen. Tests mit PayPal, weiteren Zahlungsanbietern, Rabattcodes, Rechnungen, Steuerkonfiguration und Rückerstattungen durchführen. Automatische Erstattungen bei Teilretouren müssen gesondert geprüft werden.</p></div>
        <?php
    }
}
