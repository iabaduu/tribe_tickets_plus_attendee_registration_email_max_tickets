/**
 * RSVP Limit - Un attendee per email
 * Versione 7 - Fix recupero event_id
 */

add_action('wp_ajax_tribe_tickets_rsvp_handle', 'rsvp_limit_check_duplicate_email', 1);
add_action('wp_ajax_nopriv_tribe_tickets_rsvp_handle', 'rsvp_limit_check_duplicate_email', 1);

function rsvp_limit_check_duplicate_email() {
    $step = isset($_POST['step']) ? sanitize_text_field($_POST['step']) : '';
    
    if ($step !== 'success') {
        return;
    }
    
    error_log('=== RSVP LIMIT v7: Step SUCCESS ===');
    
    $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
    
    if (empty($ticket_id)) {
        return;
    }
    
    // Recupera l'event_id - prova diversi metodi
    $event_id = get_post_meta($ticket_id, '_tribe_rsvp_for_event', true);
    error_log("RSVP LIMIT v7: _tribe_rsvp_for_event = $event_id");
    
    if (empty($event_id)) {
        $event_id = get_post_meta($ticket_id, '_tribe_tickets_event', true);
        error_log("RSVP LIMIT v7: _tribe_tickets_event = $event_id");
    }
    
    if (empty($event_id)) {
        $ticket_post = get_post($ticket_id);
        $event_id = $ticket_post ? $ticket_post->post_parent : 0;
        error_log("RSVP LIMIT v7: post_parent = $event_id");
    }
    
    // Ultimo tentativo: cerca nei meta del ticket
    if (empty($event_id)) {
        global $wpdb;
        $all_meta = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE '%%event%%'",
            $ticket_id
        ));
        error_log("RSVP LIMIT v7: All event meta for ticket $ticket_id: " . print_r($all_meta, true));
    }
    
    error_log("RSVP LIMIT v7: Ticket: $ticket_id, Event FINALE: $event_id");
    
    // Estrai l'email
    $email = '';
    if (isset($_POST['tribe_tickets'][$ticket_id]['attendees'][0]['email'])) {
        $email = sanitize_email($_POST['tribe_tickets'][$ticket_id]['attendees'][0]['email']);
    }
    
    error_log("RSVP LIMIT v7: Email: $email");
    
    if (empty($email) || empty($event_id)) {
        error_log('RSVP LIMIT v7: Dati mancanti - esco');
        return;
    }
    
    // Controlla duplicati
    global $wpdb;
    
    $exists = $wpdb->get_var($wpdb->prepare(
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
    ));
    
    error_log("RSVP LIMIT v7: Esistenti: $exists");
    
    if ($exists > 0) {
        error_log('=== RSVP LIMIT v7: BLOCCO ===');
        
        wp_send_json([
            'success' => false,
            'data' => [
                'html' => '<div style="padding:20px;background:#fee;border-left:4px solid #c00;margin:15px 0;">
                    <strong style="color:#c00;">⚠️ Registrazione non consentita</strong><br><br>
                    L\'email <strong>' . esc_html($email) . '</strong> è già registrata per questo evento.<br>
                    È consentita una sola registrazione per indirizzo email.
                </div>'
            ]
        ]);
        exit;
    }
    
    error_log('RSVP LIMIT v7: OK - procedo');
}
