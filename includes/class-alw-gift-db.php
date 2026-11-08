<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ALW_Gift_DB {
    public static function cards_table() {
        global $wpdb;
        return $wpdb->prefix . 'alw_gift_cards';
    }

    public static function ledger_table() {
        global $wpdb;
        return $wpdb->prefix . 'alw_gift_ledger';
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $cards = self::cards_table();
        $ledger = self::ledger_table();
        dbDelta( "CREATE TABLE {$cards} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code_hash char(64) NOT NULL,
            code_encrypted text NOT NULL,
            code_last4 varchar(4) NOT NULL,
            amount_cents bigint(20) NOT NULL,
            balance_cents bigint(20) NOT NULL,
            currency char(3) NOT NULL DEFAULT 'EUR',
            status varchar(20) NOT NULL DEFAULT 'active',
            recipient_name varchar(190) NOT NULL DEFAULT '',
            recipient_email varchar(190) NOT NULL DEFAULT '',
            sender_name varchar(190) NOT NULL DEFAULT '',
            purchaser_email varchar(190) NOT NULL DEFAULT '',
            gift_message text NOT NULL,
            order_id bigint(20) unsigned NOT NULL DEFAULT 0,
            order_item_id bigint(20) unsigned DEFAULT NULL,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            delivered_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code_hash (code_hash),
            UNIQUE KEY order_item_id (order_item_id),
            KEY recipient_email (recipient_email),
            KEY order_id (order_id),
            KEY status (status)
        ) ENGINE=InnoDB {$charset};" );
        dbDelta( "CREATE TABLE {$ledger} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            gift_card_id bigint(20) unsigned NOT NULL,
            event_key varchar(100) NOT NULL,
            event_type varchar(30) NOT NULL,
            amount_cents bigint(20) NOT NULL,
            order_id bigint(20) unsigned NOT NULL DEFAULT 0,
            note text NOT NULL,
            actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY event_key (event_key),
            KEY gift_card_id (gift_card_id),
            KEY order_id (order_id)
        ) ENGINE=InnoDB {$charset};" );
        add_option( 'alw_gc_settings', array(
            'amounts' => '25,50,100',
            'minimum' => 20,
            'maximum' => 500,
            'gold' => '#d4b45c',
            'dark' => '#212121',
        ), '', false );
        update_option( 'alw_gc_db_version', ALW_GC_VERSION );
    }

    public static function hash_code( $code ) {
        return hash_hmac( 'sha256', self::normalize_code( $code ), wp_salt( 'auth' ) );
    }

    public static function normalize_code( $code ) {
        return preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $code ) );
    }

    public static function encrypt( $plaintext ) {
        if ( ! function_exists( 'openssl_encrypt' ) ) { return false; }
        $iv = random_bytes( 12 );
        $tag = '';
        $cipher = openssl_encrypt( $plaintext, 'aes-256-gcm', hash( 'sha256', wp_salt( 'secure_auth' ), true ), OPENSSL_RAW_DATA, $iv, $tag );
        return false === $cipher ? false : base64_encode( $iv . $tag . $cipher );
    }

    public static function decrypt( $encrypted ) {
        if ( ! function_exists( 'openssl_decrypt' ) ) { return false; }
        $binary = base64_decode( (string) $encrypted, true );
        if ( false === $binary || strlen( $binary ) < 29 ) { return false; }
        return openssl_decrypt( substr( $binary, 28 ), 'aes-256-gcm', hash( 'sha256', wp_salt( 'secure_auth' ), true ), OPENSSL_RAW_DATA, substr( $binary, 0, 12 ), substr( $binary, 12, 16 ) );
    }

    public static function new_code() {
        $raw = strtoupper( bin2hex( random_bytes( 12 ) ) );
        return 'ALW-' . implode( '-', str_split( $raw, 6 ) );
    }

    public static function find_code( $code ) {
        global $wpdb;
        $normalized = self::normalize_code( $code );
        if ( ! preg_match( '/^ALW[A-F0-9]{24}$/', $normalized ) ) { return null; }
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::cards_table() . ' WHERE code_hash = %s', self::hash_code( $code ) ) );
    }

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::cards_table() . ' WHERE id = %d', (int) $id ) );
    }

    public static function active( $card ) {
        return $card && 'active' === $card->status && 'EUR' === $card->currency
            && (int) $card->balance_cents > 0 && $card->expires_at > current_time( 'mysql', true );
    }

    public static function expires_at() {
        // Ende des dritten auf das Kaufjahr folgenden Kalenderjahres (UTC).
        return ( (int) wp_date( 'Y' ) + 3 ) . '-12-31 23:59:59';
    }

    public static function create( $args ) {
        global $wpdb;
        if ( ! function_exists( 'openssl_encrypt' ) ) { return new WP_Error( 'encryption', 'OpenSSL ist für die sichere Speicherung erforderlich.' ); }
        $cents = absint( $args['amount_cents'] ?? 0 );
        if ( $cents < 1 ) { return new WP_Error( 'amount', 'Ungültiger Gutscheinwert.' ); }
        $order_item_id = ! empty( $args['order_item_id'] ) ? absint( $args['order_item_id'] ) : null;
        if ( $order_item_id ) {
            $existing = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::cards_table() . ' WHERE order_item_id = %d', $order_item_id ) );
            if ( $existing ) { return (int) $existing; }
        }
        for ( $try = 0; $try < 5; $try++ ) {
            $code = self::new_code();
            $encrypted = self::encrypt( $code );
            if ( ! $encrypted ) { return new WP_Error( 'encryption', 'Gutscheincode kann nicht sicher verschlüsselt werden.' ); }
            $ok = $wpdb->insert( self::cards_table(), array(
                'code_hash' => self::hash_code( $code ),
                'code_encrypted' => $encrypted,
                'code_last4' => substr( self::normalize_code( $code ), -4 ),
                'amount_cents' => $cents,
                'balance_cents' => $cents,
                'currency' => 'EUR',
                'status' => 'active',
                'recipient_name' => sanitize_text_field( $args['recipient_name'] ?? '' ),
                'recipient_email' => sanitize_email( $args['recipient_email'] ?? '' ),
                'sender_name' => sanitize_text_field( $args['sender_name'] ?? '' ),
                'purchaser_email' => sanitize_email( $args['purchaser_email'] ?? '' ),
                'gift_message' => sanitize_textarea_field( $args['gift_message'] ?? '' ),
                'order_id' => absint( $args['order_id'] ?? 0 ),
                'order_item_id' => $order_item_id,
                'created_by' => absint( $args['created_by'] ?? 0 ),
                'created_at' => current_time( 'mysql', true ),
                'expires_at' => self::expires_at(),
            ) );
            if ( $ok ) {
                $id = (int) $wpdb->insert_id;
                $wpdb->insert( self::ledger_table(), array(
                    'gift_card_id' => $id,
                    'event_key' => 'issue-' . $id,
                    'event_type' => 'issue',
                    'amount_cents' => $cents,
                    'order_id' => absint( $args['order_id'] ?? 0 ),
                    'note' => 'Gutschein ausgestellt',
                    'actor_id' => absint( $args['created_by'] ?? 0 ),
                    'created_at' => current_time( 'mysql', true ),
                ) );
                return $id;
            }
            // Bei wiederholten Shop-/Gateway-Events nur eine Karte je Bestellposition.
            if ( $order_item_id ) {
                $existing = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::cards_table() . ' WHERE order_item_id = %d', $order_item_id ) );
                if ( $existing ) { return (int) $existing; }
            }
        }
        return new WP_Error( 'database', 'Der Gutschein konnte nicht angelegt werden.' );
    }

    /** Atomic reservation / release with idempotent ledger event. InnoDB required. */
    public static function change_balance( $card_id, $amount_cents, $event_key, $event_type, $order_id, $note = '' ) {
        global $wpdb;
        $card_id = absint( $card_id );
        $amount_cents = (int) $amount_cents;
        if ( ! $card_id || 0 === $amount_cents ) { return false; }
        $wpdb->query( 'START TRANSACTION' );
        try {
            $card = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::cards_table() . ' WHERE id = %d FOR UPDATE', $card_id ) );
            $exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::ledger_table() . ' WHERE event_key = %s', $event_key ) );
            if ( $exists ) { $wpdb->query( 'COMMIT' ); return true; }
            if ( ! $card || ( $amount_cents < 0 && ( ! self::active( $card ) || (int) $card->balance_cents < abs( $amount_cents ) ) ) ) {
                $wpdb->query( 'ROLLBACK' );
                return false;
            }
            $insert = $wpdb->insert( self::ledger_table(), array(
                'gift_card_id' => $card_id,
                'event_key' => $event_key,
                'event_type' => sanitize_key( $event_type ),
                'amount_cents' => $amount_cents,
                'order_id' => absint( $order_id ),
                'note' => sanitize_text_field( $note ),
                'actor_id' => get_current_user_id(),
                'created_at' => current_time( 'mysql', true ),
            ) );
            if ( ! $insert ) { $wpdb->query( 'ROLLBACK' ); return false; }
            $updated = $wpdb->query( $wpdb->prepare(
                'UPDATE ' . self::cards_table() . ' SET balance_cents = balance_cents + %d WHERE id = %d',
                $amount_cents, $card_id
            ) );
            if ( 1 !== $updated ) { $wpdb->query( 'ROLLBACK' ); return false; }
            $wpdb->query( 'COMMIT' );
            return true;
        } catch ( Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            return false;
        }
    }
}