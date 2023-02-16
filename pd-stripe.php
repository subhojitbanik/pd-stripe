<?php
/*
* Plugin Name:       PD Stripe
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           1.0.0
 * Author:            Knovatek
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       pd-stripe
 * Domain Path:       /languages
 */

 // If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


 //define( 'STRIPE_CLINET_KEY', '' ); // test mode key

require plugin_dir_path( __FILE__ ). 'vendor/autoload.php';
require plugin_dir_path( __FILE__ ). 'pd-stripe-connect.php';
require plugin_dir_path( __FILE__ ). 'pd-stripe-webhooks.php';
require plugin_dir_path( __FILE__ ). 'pd-stripe-payments.php';
require plugin_dir_path( __FILE__ ). 'pd-stripe-refund.php';
require plugin_dir_path( __FILE__ ). 'pd-stripe-payouts.php';
$pdStripeConnect = new PdStripeConnect();


add_action( 'wp_enqueue_scripts', 'fg_pd_enqueue_script' );
function fg_pd_enqueue_script() {

    wp_enqueue_style( 'fg-pd-stripe', plugin_dir_url( __FILE__ ). 'style.css');


    // wp_enqueue_script( 'tui-calendar-code-snippet', 'https://uicdn.toast.com/tui.code-snippet/v1.5.2/tui-code-snippet.min.js', array('jquery'), '', true );
}


/**
 *  connected ID : acct_1LORtK2RlxQI9P4q
 */

add_action( 'wp_head', 'add_stripe' );

function add_stripe(){
    echo '<script src="https://js.stripe.com/v3/"></script>';
}

add_shortcode( 'create_tutor_account', function(){

    $stripe = new \Stripe\StripeClient(STRIPE_CLINET_KEY);
    $current_user = wp_get_current_user();
    
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
                    'student_id' =>  get_current_user_id(),
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
                var stripe = Stripe('pk_live_I0IHoA2GqhAVPunk5Yjh0JtA00o9S744Ep');
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

                                var paymentIntent_id = document.getElementById('paymentIntent_id');
                                paymentIntent_id.value = result.paymentIntent.id;
                                
                                const form = document.getElementById('payment_success');
                                form.submit();
                                
                            }
                        }
                    });
                });
            </script>
        <?php
    }
    return ob_get_clean();
} );

add_action( 'wp_ajax_stripe_post_payment', 'stripe_post_payment' );
add_action( 'wp_ajax_nopriv_stripe_post_payment', 'stripe_post_payment' );

function stripe_post_payment(){
    check_ajax_referer( 'stripe-post-payment', 'security' );
    $result = $_POST['payment'];
    echo $result['amount'];
    die;
}

add_shortcode( 'payment-success', 'payment_success' );
function payment_success(){
    
    $stripe = new \Stripe\StripeClient(STRIPE_CLINET_KEY);
    $payment_intent = $stripe->paymentIntents->retrieve(
        $_POST['paymentIntent_id']
    );
    $transfer = $stripe->transfers->retrieve(
        $payment_intent->charges->data[0]->transfer
    );
    $student_profile_id = get_user_meta( get_current_user_id(), 'profile_id', true );
    $student_name = get_post_meta( $student_profile_id, 'first_name', true ) . ' ' . get_post_meta( $student_profile_id, 'last_name', true );
    $description = get_the_title( $_POST['request_id'] ). ' by ' .$student_name. ' for Request ID : '.$_POST['request_id'];

    $stripe->charges->update(
        $transfer->destination_payment,
        [
            'description' => 'Paid for '. $description,
            'metadata' => [ 
                'booking_id' => $_POST['booking_id'], 
                'student_id' => get_current_user_id(), 
                'request_id' => $_POST['request_id'] 
            ]
        ],
        ['stripe_account' => get_user_meta( $_POST['tutor_id'] , 'stripe_acc_id', true )]
    );

    $result = apply_filters( 'fg_payment_success', $_POST );

    // return $result;
    if($result && $result > 0){
        do_action( 'after_payment_success', $result );
        return 'Thank you, your payment is successful';

    }else{
        return 'Error while confrming the payment';
    }
}
