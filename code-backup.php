<?php

add_shortcode( 'pd-stripe-payment', function(){

    $stripe = new \Stripe\StripeClient(STRIPE_CLINET_KEY);
    $current_user = wp_get_current_user();

    $_POST = [
        'amount' => 14,
        'student_id' =>  90,
        'tutor_id' => 91,
        'booking_id' =>  117,
        'request_id' =>  4620,
    ];
    
    if(isset($_POST['amount']) && !empty($_POST['amount'])){
        $amount = $_POST['amount']*100;
        $student_profile_id = get_user_meta( get_current_user_id(), 'profile_id', true );
        $student_name = get_post_meta( $student_profile_id, 'first_name', true ) . ' ' . get_post_meta( $student_profile_id, 'last_name', true );
        $description = get_the_title( $_POST['request_id'] ). ' by ' .$student_name; //'Student ID : '. get_current_user_id() . ' | Tutor ID : '. $_POST['tutor_id'] . ' | Booking ID : '. $_POST['booking_id'] . ' | Request ID : '. $_POST['request_id'];
        
        if(empty(get_user_meta( get_current_user_id(), 'stripe_acc_id', true ))){
            $customer = $stripe->customers->create([
                'name' => $student_name,
                'email' => $current_user->user_email,
                'metadata' => array(
                    'user_id' => get_current_user_id()
                )
            ]);

            if(!empty($customer->id)){
                update_user_meta(get_current_user_id(),'stripe_acc_id',$customer->id );
            }
        }
        
        $payment_intent = $stripe->paymentIntents->create(
            [
                'amount' => $amount,
                'currency' => 'cad',
                'customer' => get_user_meta( get_current_user_id(), 'stripe_acc_id', true ),
                'payment_method_types' => ['card'],
                'application_fee_amount' => ($amount*30)/100,
                'on_behalf_of' => get_user_meta( $_POST['tutor_id'] , 'stripe_acc_id', true ),
                'transfer_data' => [
                    'destination' => get_user_meta( $_POST['tutor_id'] , 'stripe_acc_id', true ), //'acct_1LMCP12RhbtCM1YJ',
                ],
                'statement_descriptor' => 'Fastgrades',
                'statement_descriptor_suffix' => 'Fastgrades',
                'description' => $description,
                'receipt_email' => $current_user->user_email,
                'metadata' => array(
                    'student_id' =>  $_POST['student_id'],
                    'tutor_id' =>  $_POST['tutor_id'],
                    'booking_id' =>  $_POST['booking_id'],
                    'request_id' =>  $_POST['request_id'],
                )
            ]
        );
    }else{
        echo '<p>Invalid action, please go back and process again</p>';
    }
    
    ob_start();
    if(!empty($payment_intent->client_secret)){
        
        ?>
            <h2 style="width: 40%; margin: auto; text-align: right;">$<?php echo $_POST['amount']; ?></h2>
            <form id="payment-form" class="cell example example4">
                <div id="card-element">
                    <!-- Elements will create input elements here -->
                </div>

                <!-- We'll put the error messages in this element -->
                <div id="card-errors" role="alert"></div>

                <button id="submit" data-secret="<?php echo $payment_intent->client_secret; ?>">Pay</button>
            </form>

            <form action="<?php echo home_url( 'payment-success' )?>" method="POST" id="payment_success">
                <input type="hidden" name="booking_id" id="booking_id" value="<?php echo $_POST['booking_id']; ?>">
                <input type="hidden" name="request_id" id="request_id" value="<?php echo $_POST['request_id']; ?>">
                <input type="hidden" name="tutor_id" id="tutor_id" value="<?php echo $_POST['tutor_id']; ?>">
                <input type="hidden" name="booking_status" id="booking_status" value="2">
                <input type="hidden" name="paymentIntent_id" id="paymentIntent_id">
            </form>

            <script>
                var stripe = Stripe('pk_test_YSv47ZcIl7EMeGiLzbNaCSAX00MT8nZqTt');
                var elements = stripe.elements({fonts: [
                    {
                        cssSrc: "https://rsms.me/inter/inter.css"
                    }
                ]});
                var style = {
                    base: {
                        color: "#32325D",
                        fontWeight: 500,
                        fontFamily: "Inter, Open Sans, Segoe UI, sans-serif",
                        fontSize: "16px",
                        fontSmoothing: "antialiased",
                        padding: "20px",

                        "::placeholder": {
                        color: "#CFD7DF"
                        }
                    },
                    invalid: {
                        color: "#E25950"
                    }
                };

                var card = elements.create("card", { classes: { base: 'fg-card'}, style: style });
                card.mount("#card-element");

                card.on('change', function(event) {
                    var displayError = document.getElementById('card-errors');
                    if (event.error) {
                        displayError.textContent = event.error.message;
                    } else {
                        displayError.textContent = '';
                    }
                });

                var form = document.getElementById('payment-form');

                form.addEventListener('submit', function(ev) {
                    ev.preventDefault();
                    var displayError = document.getElementById('card-errors');
                    displayError.textContent = 'Wait... We are processing the payment';
                    var secret = document.getElementById('submit');
                    stripe.confirmCardPayment(secret.getAttribute('data-secret'), {
                        payment_method: {
                        card: card,
                        billing_details: {
                            name: '<?php echo $student_name; ?>'
                        }
                        }
                    }).then(function(result) {
                        if (result.error) {
                        // Show error to your customer (for example, insufficient funds)
                        console.log(result.error.message);
                        } else {
                        // The payment has been processed!
                            if (result.paymentIntent.status === 'succeeded') {
                                displayError.textContent = 'Payment processed successfully';
                                // var paymentIntent_id = document.getElementById('paymentIntent_id');
                                // paymentIntent_id.value = result.paymentIntent.id;
                                
                                // const form = document.getElementById('payment_success');
                                // form.submit();
                                
                            }
                        }
                    });
                });
            </script>
        <?php
    }

    // echo $error;
    // echo '<pre>';
    // print_r($test_pr);
    // echo '</pre>';

    return ob_get_clean();
} );