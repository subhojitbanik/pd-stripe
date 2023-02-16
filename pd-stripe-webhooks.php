<?php

add_action('init','stripe_task', 10);
function stripe_task() {

    if(!isset($_REQUEST['pd_stripe_webhook']))
    {
        return;
    }

    $stripe = new \Stripe\StripeClient(STRIPE_CLINET_KEY);

    // $endpoint_secret = 'whsec_EkBTaWhQqx2QGJfOrMP5c1tsp8SdxA3L'; // test mode 
    $endpoint_secret = 'whsec_i7d0KPS8TEugWgPt9gRYFXOaS6QJRyVx';

    $payload = @file_get_contents('php://input');
    $event = null;
    try {
        $event = \Stripe\Event::constructFrom(
            json_decode($payload, true)
        );
    } catch(\UnexpectedValueException $e) {
        // Invalid payload
        echo '⚠️ Webhook error while parsing basic request.';
        http_response_code(400);
        exit();
    }

    if ($endpoint_secret) {
        // Only verify the event if there is an endpoint secret defined
        // Otherwise use the basic decoded event
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        try {
          $event = \Stripe\Webhook::constructEvent(
            $payload, $sig_header, $endpoint_secret
          );
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
          // Invalid signature
          echo '⚠️  Webhook error while validating signature.';
          http_response_code(400);
          exit();
        }
    }

    switch ($event->type) {
        case 'payment_intent.succeeded':
          $paymentIntent = $event->data->object; // contains a \Stripe\PaymentIntent
          // Then define and call a method to handle the successful payment intent.
          // handlePaymentIntentSucceeded($paymentIntent);
          // Create post object
          $my_post = array(
            'post_title'    => get_the_title( $paymentIntent->metadata->request_id ),
            'post_status'   => 'publish',
            'post_author'   => $paymentIntent->metadata->student_id,
            'post_type' => 'pd_stripe_payments'
          );

          // Insert the post into the database
          $post_id = wp_insert_post( $my_post );
          if($post_id){
            update_post_meta($post_id,'tutoring_request', $paymentIntent->metadata->request_id );
            update_post_meta($post_id,'student', $paymentIntent->metadata->student_id );
            update_post_meta($post_id,'tutor', $paymentIntent->metadata->tutor_id );
            update_post_meta($post_id,'payment_id', $paymentIntent->id );
            update_post_meta($post_id,'payment_status', $paymentIntent->status );
            update_post_meta($post_id,'amount', $paymentIntent->amount );
          }
          break;
        case 'payment_method.attached':
          $paymentMethod = $event->data->object; // contains a \Stripe\PaymentMethod
          // Then define and call a method to handle the successful attachment of a PaymentMethod.
          // handlePaymentMethodAttached($paymentMethod);
          break;
        default:
          // Unexpected event type
          error_log('Received unknown event type');
      }

      http_response_code(200);
} 


