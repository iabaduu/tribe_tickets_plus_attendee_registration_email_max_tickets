<?php
/**
 * Event Tickets Integration
 * Customizations for The Events Calendar RSVP/Tickets
 *
 * @package IABADUU_Custom
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Verifica che la classe non esista già
if ( ! class_exists( 'IABADUU_Event_Tickets_Integration' ) ) :

class IABADUU_Event_Tickets_Integration {
    
    /**
     * Slug del campo email personalizzato
     */
    const EMAIL_FIELD_SLUG = 'tribe-tickets-plus-iac-email';
    
    /**
     * Inizializzazione della classe
     */
    public static function init() {
        // 1. Rimuove il numero di partecipanti dal template RSVP
        add_filter( 'tribe_template_done', array( __CLASS__, 'remove_attendance_number' ), 10, 2 );
        
        // 2. Limita il numero di biglietti selezionabili in un singolo ordine (es. max 3 alla volta)
        add_filter( 'tribe_tickets_get_ticket_max_purchase', array( __CLASS__, 'limit_max_purchase_quantity' ) );
        
        // 3. Sposta il campo email in prima posizione nel form
        add_filter( 'event_tickets_plus_meta_fields_by_ticket', array( __CLASS__, 'move_email_field_first' ), 20 );
        
        // 4. Limita a 1 RSVP per email - intercetta AJAX
        add_action( 'wp_ajax_tribe_tickets_rsvp_handle', array( __CLASS__, 'check_duplicate_email' ), 1 );
        add_action( 'wp_ajax_nopriv_tribe_tickets_rsvp_handle', array( __CLASS__, 'check_duplicate_email' ), 1 );
    }
    
    /**
     * Controlla se l'email è già registrata per l'evento e blocca i duplicati
     */
    public static function check_duplicate_email() {
        $step = isset( $_POST['step'] ) ? sanitize_text_field( $_POST['step'] ) : '';
        
        // Procedi solo nello step finale
        if ( $step !== 'success' ) {
            return;
        }
        
        $ticket_id = isset( $_POST['ticket_id'] ) ? intval( $_POST['ticket_id'] ) : 0;
        
        if ( empty( $ticket_id ) ) {
            return;
        }
        
        // Recupera l'event_id dal ticket
        $event_id = get_post_meta( $ticket_id, '_tribe_rsvp_for_event', true );
        
        if ( empty( $event_id ) ) {
            $event_id = get_post_meta( $ticket_id, '_tribe_tickets_event', true );
        }
        
        if ( empty( $event_id ) ) {
            $ticket_post = get_post( $ticket_id );
            $event_id = $ticket_post ? $ticket_post->post_parent : 0;
        }
        
        if ( empty( $event_id ) ) {
            return;
        }
        
        // Estrai l'email dalla struttura POST
        $email = '';
        if ( isset( $_POST['tribe_tickets'][ $ticket_id ]['attendees'][0]['email'] ) ) {
            $email = sanitize_email( $_POST['tribe_tickets'][ $ticket_id ]['attendees'][0]['email'] );
        }
        
        if ( empty( $email ) ) {
            return;
        }
        
        // Controlla duplicati nel database
        global $wpdb;
        
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
            WHERE pm.meta_key = '_tribe_rsvp_email'
            AND LOWER(pm.meta_value) = LOWER(%s)
            AND pm2.meta_key = '_tribe_rsvp_event'
            AND pm2.meta_value = %d
            AND p.post_status != 'trash'",
            $email,
            $event_id
        ) );
        
        if ( $exists > 0 ) {
            wp_send_json( array(
                'success' => false,
                'data' => array(
                    'html' => '<div class="tribe-tickets-notice tribe-tickets-notice--error" style="padding: 20px; background: #ffebee; border-left: 4px solid #c62828; margin: 15px 0; border-radius: 4px;">
                        <strong style="color: #c62828;">⚠️ Registrazione non consentita</strong><br><br>
                        L\'indirizzo email <strong>' . esc_html( $email ) . '</strong> è già registrato per questo evento.<br>
                        È consentita una sola registrazione per indirizzo email.
                    </div>'
                )
            ) );
            exit;
        }
    }
    
    /**
     * Rimuove il numero di partecipanti (Attendance) dal template
     */
    public static function remove_attendance_number( $show, $name ) {
        if ( $name === 'v2/rsvp/details/attendance' ) {
            return '';
        }
        return $show;
    }
    
    /**
     * Limita il numero massimo di biglietti acquistabili in una singola transazione
     */
    public static function limit_max_purchase_quantity( $available_at_a_time ) {
        if ( function_exists( 'iabaduu_get_setting' ) ) {
            $max_tickets = iabaduu_get_setting( 'max_tickets', 3 );
        } else {
            $max_tickets = 3; 
        }
        return min( intval( $max_tickets ), $available_at_a_time );
    }
    
    /**
     * Sposta il campo email in prima posizione
     */
    public static function move_email_field_first( $fields ) {
        $email_index = array_search( self::EMAIL_FIELD_SLUG, array_column( $fields, 'slug' ) );
        
        if ( $email_index !== false ) {
            $email_field = $fields[ $email_index ];
            array_splice( $fields, $email_index, 1 );
            array_splice( $fields, 0, 0, array( $email_field ) );
        }
        return $fields;
    }
}

// AVVIO DELLA CLASSE
add_action( 'plugins_loaded', array( 'IABADUU_Event_Tickets_Integration', 'init' ) );

endif;
