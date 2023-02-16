<?php

function pd_stripe_process_refund($request_id){

    $payments = pd_stripe_get_payment_record($request_id);
    // return $payments;
    foreach($payments as $payment){
        $tutoring_request = get_post_meta($payment->ID,'tutoring_request', true );
        $request_payment_status = get_post_meta($payment->ID,'payment_status', true );
        $status = get_meeting_status($request_id, false);
        if($tutoring_request == $request_id && ($status != 2 || $status != 3) && $request_payment_status != 'refunded'){
            $stripe = new \Stripe\StripeClient(STRIPE_CLINET_KEY);
            $payment_intent = $stripe->paymentIntents->retrieve(
                get_post_meta($payment->ID,'payment_id', true )
            );

            $refund = $stripe->refunds->create([
                'charge' => $payment_intent->charges->data[0]->id,
                'reverse_transfer' => true,
                'refund_application_fee' => true,
                'metadata' => array(
                    'request' => get_the_title($tutoring_request), 
                    'request_id' => $tutoring_request,
                    'tutor_id' => get_post_meta($payment->ID,'tutor', true ),
                    'student_id' => get_post_meta($payment->ID,'student', true ),
                )
            ]);
            
            if($refund->status === 'succeeded'){
                update_post_meta($payment->ID,'payment_status','refunded' );
            }
            return $refund->status;
        }elseif($request_payment_status === 'refunded'){
            return $request_payment_status;
        }
    }

}

add_action('sb_video_after_update_remark','refund_if_tutor_failed_to_join', 11, 2 );
function refund_if_tutor_failed_to_join($remark, $request_id){
    if($remark == 1){
        pd_stripe_process_refund($request_id);
    }
}



