<?php

add_shortcode('pd-stripe-payments', 'pd_stripe_payments' );
function pd_stripe_payments($atts){
    $atts = extract(shortcode_atts([
        'user_role' => ''
    ], $atts ));
    
    if(is_user_logged_in()){
        $current_user = wp_get_current_user();
        $roles = ( array ) $current_user->roles;

        if(in_array($user_role, $roles)){
            $args = array(
                'post_type' => 'pd_stripe_payments',
                'author' => get_current_user_id(),
                'orderby'       =>  'post_date',
                'order'         =>  'DESC',
                'posts_per_page' => -1
            );
            $payments = get_posts($args);

            ob_start();

            echo '<table>';
                echo '<tr>';
                echo '<th>Request ID</th>';
                echo '<th>Tutoring Request</th>';
                echo '<th>Tutor</th>';
                echo '<th>Amount Paid</th>';
                echo '<th>Payment Status</th>';
                echo '</tr>';
                foreach($payments as $payment){
                    echo '<tr>';
                    echo '<td>'.$payment->tutoring_request.'</td>';
                    echo '<td>'.get_the_title($payment->ID).'</td>';
                    echo '<td>'. pd_get_user_name(get_post_meta($payment->ID, 'tutor', true)) .'</td>';
                    echo '<td>$'. get_post_meta($payment->ID, 'amount', true)/100 .'</td>';
                    echo '<td>'. get_post_meta($payment->ID, 'payment_status', true) .'</td>';
                    echo '</tr>';
                }
            echo '</table>';

            // $user = get_user_by('id', $payment->ID );

            // print_r($user);

            return ob_get_clean();
        }
    }

}

function pd_get_user_name($user_id){

    $user = get_user_by('id', $user_id );
    return $user->first_name . ' ' . $user->last_name;

}

function pd_stripe_get_payment_record($request_id){

    $args = array(
        'post_type' => 'pd_stripe_payments',
        // 'author' => get_current_user_id(),
        'meta_key' => 'tutoring_request',
        'meta_value' => $request_id,
        'meta_compare' => '=',
        'posts_per_page' => 1
    );
    return get_posts($args);

}