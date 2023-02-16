<?php

// Hook into that action that'll fire every twelve hours
add_action( 'pd_stripe_every_twelve_hours', 'pd_stripe_every_twelve_hours_func' );
function pd_stripe_every_twelve_hours_func() {

    $stripe = new \Stripe\StripeClient(STRIPE_CLINET_KEY);

    //5066
    // update_post_meta(5066,'tutor_payment_status', date('h:i:s A') );

    $meetings = sb_video_get_all_meetings();
    foreach($meetings as $meeting){

        if( ( $meeting->remarks == 3 || $meeting->remarks == 2 ) && $meeting->id > 196 ){

            $payment = get_payment_by_reqid_tutor_id($meeting->request_id, $meeting->tutuor_id);
            $stripe_acc_id = get_user_meta($meeting->tutuor_id,'stripe_acc_id',true);
            
            if(empty(get_post_meta($payment[0]->ID,'tutor_payment_status',true))){

                $amount = get_post_meta($payment[0]->ID,'amount',true )*0.70;

                $balance = $stripe->balance->retrieve(
                    [],
                    ['stripe_account' => $stripe_acc_id]
                );

                if(!empty($payment) && $balance->available[0]->amount >= $amount){
                    

                    $description = 'Payout for '. get_the_title( $meeting->request_id ) . ' Request ID: ' . $meeting->request_id;

                
                    $payout = $stripe->payouts->create(
                        [
                            'amount' => $amount,
                            'currency' => 'cad',
                            'description' => $description
                        ], 
                        [
                            'stripe_account' => $stripe_acc_id
                        ]
                    );

                    if($payout->status == 'pending'){
                        update_post_meta($payment[0]->ID,'tutor_payment_status', $payout->status);
                    }

                }

            }

        }elseif($meeting->tutor_join_status == 0 && $meeting->student_join_status == 0 && $meeting->remarks == 0 && get_unattended_meeting_status($meeting->student_id, $meeting->meeting_date) && $meeting->id > 196){
            $refund = pd_stripe_process_refund($meeting->request_id);

            if($refund === 'succeeded' || $refund === 'refunded'){
                do_action('after_unattended_refund_success', $meeting );
            }
        }
    }

}
// } );

add_shortcode('manual_cron', 'manual_payout_refund_cron' );
function manual_payout_refund_cron(){

    $is_verified = 0;
    $output = '';
    if(!empty( get_user_meta( get_current_user_id() , 'stripe_acc_id', true ) )){
        $stripe = new \Stripe\StripeClient(STRIPE_CLINET_KEY);
        $account_update = $stripe->accounts->retrieve( get_user_meta( get_current_user_id(), 'stripe_acc_id', true ) );

        if( $account_update->charges_enabled && $account_update->payouts_enabled ){
            $is_verified = 1;
        }
    }

    ob_start();
    if($is_verified){
        ?>
            <form action="" method="post" style="margin-top: 20px;">
                <input type="submit" id="manual_payout_refund" name="manual_payout_refund_cron" value="Update and receive your payouts">
            </form>
            <script>
                const btn = document.getElementById('manual_payout_refund');
                btn.addEventListener('click', function handleClick() {
                    btn.value = 'Please wait...';
                });
            </script>
        <?php
    }
    
    if(isset($_POST['manual_payout_refund_cron'])){
        // do_action('after_unattended_refund_success', $meeting );
        pd_stripe_every_twelve_hours_func();
        echo '<p style="background: green; color: #fff;display: inline-block;padding: 5px 27px;margin-top: 10px;">Payouts updated successfully</p>';
    }
    
    return ob_get_clean();
}

function get_unattended_meeting_status($student_id, $meeting_date){

    $arr = false;
    
    // $mEnd = strtotime($start) + 3600; $mEndDate->modify('+ 1 hour');
    $mEnd = new DateTime($meeting_date);
    // $mEnd = $mEnd->modify('+ 1 hour');
    $mEnd = $mEnd->format('Y-m-d H:i:s');
    $mEnd_string = strtotime($mEnd);

    $student_timezone = get_post_meta(get_user_profile_id($student_id), 'student_timezone', true);

    if(!empty($student_timezone)){
        // $today_now = '2022-09-28 09:30';
        $uTimezone = new DateTimeZone( $student_timezone );
        $today_now = new DateTime( "now", $uTimezone ); //new DateTime(  $today_now );//
        $today_now = $today_now->format('Y-m-d H:i:s');
        $today_string = strtotime($today_now);

        
        if($mEnd_string < $today_string){
            $arr = true;
        }
    }

    return $arr;
    
}

function get_payment_by_reqid_tutor_id($request_id, $tutor_id){

    $query_args = array(
        'post_type'  => 'pd_stripe_payments',
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key'   => 'tutoring_request',
                'value' => $request_id,
                'compare' => '='
            ),
            array(
                'key'   => 'tutor',
                'value' => $tutor_id,
                'compare' => '='
            ),
        )
    );
    return $query = get_posts( $query_args );

}

add_shortcode('test-payout','pd_stripe_payout' );
function pd_stripe_payout(){

    $meetings = sb_video_get_all_meetings();
    ob_start();
    foreach($meetings as $meeting){

        // if( $meeting->remarks == 3 ){

        //     $payment = get_payment_by_reqid_tutor_id($meeting->request_id, $meeting->tutuor_id);
        //     $stripe_acc_id = get_user_meta($meeting->tutuor_id,'stripe_acc_id',true);
        //     $amount = get_post_meta($payment[0]->ID,'amount',true );

        //     // \Stripe\Stripe::setApiKey(STRIPE_CLINET_KEY);

        //     // $balance = \Stripe\Balance::retrieve(
        //     // ['stripe_account' => $stripe_acc_id]
        //     // );

        //     $stripe = new \Stripe\StripeClient(STRIPE_CLINET_KEY);
        //     $balance = $stripe->balance->retrieve(
        //         [],
        //         ['stripe_account' => $stripe_acc_id]
        //     );
        //     echo $stripe_acc_id;
        //     echo '<br/><pre>';
        //     print_r($balance->available[0]->amount);
        //     echo '</pre><br/>';

        //     if(!empty($payment) && empty(get_post_meta($payment[0]->ID,'tutor_payment_status',true)) && $balance->available[0]->amount >= $amount ){

                
                

        //         $description = 'Payout for '. get_the_title( $meeting->request_id ) . ' Request ID: ' . $meeting->request_id;

        //         echo '<br/>';
        //         echo $description;
        //         echo '<br/>';
        //         // $payout = $stripe->payouts->create(
        //         //     [
        //         //         'amount' => $amount*0.70,
        //         //         'currency' => 'cad',
        //         //         'description' => $description
        //         //     ], 
        //         //     [
        //         //         'stripe_account' => $stripe_acc_id
        //         //     ]
        //         // );

        //         // if($payout->status == 'pending'){
        //         //     update_post_meta($payment[0]->ID,'tutor_payment_status', $payout->status);
        //         // }

        //     }

        // }else
        
        if($meeting->remarks == 0 && get_unattended_meeting_status($meeting->student_id, $meeting->meeting_date)){ //&& get_unattended_meeting_status($meeting->student_id, $meeting->meeting_date)
            // pd_stripe_process_refund($meeting->request_id);
            echo $meeting->request_id.' : '.get_the_title($meeting->request_id);
            echo '<br>';
        }
    }

    return ob_get_clean();
}