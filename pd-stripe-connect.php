<?php

class PdStripeConnect
{

    protected $stripe;
    public $is_connect_verified;
    public $stripe_acc_id;

    public function __construct()
    {
        $this->stripe = new \Stripe\StripeClient(STRIPE_CLINET_KEY);
        $this->is_connect_verified = false;
        $this->stripe_acc_id = get_user_meta( get_current_user_id() , 'stripe_acc_id', true );
        
        add_shortcode( 'create_connect_account', array( $this, 'create_connect_account') );
        add_action( 'template_redirect', array( $this, 'payment_account_redirect'), 11 );
    }

    public function create_connect_account($atts)
    {
        if( is_user_logged_in() ){

            $is_verified = 0;
            if(!empty( get_user_meta( get_current_user_id() , 'stripe_acc_id', true ) )){

                $account_update = $this->stripe->accounts->retrieve( get_user_meta( get_current_user_id(), 'stripe_acc_id', true ) );
    
                if( $account_update->charges_enabled && $account_update->payouts_enabled ){
                    $is_verified = 1;
                }
            }

            if( empty( get_user_meta( get_current_user_id() , 'stripe_acc_id', true ) ) && !isset( $_POST['create_connect_account'] ) ){
                
                ob_start();
                    ?>

                        <p>It is mandatory to complete the creation and verification of your payment account to get paid and continue to use the system. The following information is required. Please have them ready.</p>
                        <ul>
                            <li>Email Address</li>
                            <li>Mobile number that will be used to verify and give access to your express dashboard</li>
                            <li>Legal Name: First name and Last name</li>
                            <li>Date Of Birth</li>
                            <li>Home address</li>
                            <li>Bank account information for payout</li>
                            <li>Government-issued ID</li>
                        </ul>
                        <br>
                        <form action="<?php echo get_the_permalink(); ?>" method="POST">	
                            <?php wp_nonce_field('create_connect_account', 'create_connect_account'); ?>
                            <input type="submit" class="fgpd-stripe-btn btn-blue" name="connect_account_submit" value="Create account to get paid">
                        </form>

                    <?php

                return ob_get_clean();

            }
            
            if(empty( get_user_meta( get_current_user_id() , 'stripe_acc_id', true ) ) && isset( $_POST['connect_account_submit'] ) ){ //&& wp_verify_nonce($_POST['create_connect_account'], 'create_connect_account')

                $current_user = wp_get_current_user();
                $connect_account = $this->stripe->accounts->create([
                    'type' => 'express',
                    'country' => 'CA',
                    'email' => $current_user->user_email,
                    'capabilities' => [
                        'card_payments' => ['requested' => true],
                        'transfers' => ['requested' => true],
                    ],
                    'business_type' => 'individual',
                    'settings' => [
                        'payouts' => [
                            'schedule' => [
                                'interval' => 'manual'
                            ]
                        ]
                    ]
                ]);

                if(!empty( $connect_account->id) ){

                    update_user_meta( get_current_user_id(), 'stripe_acc_id', $connect_account->id );

                    $verify_account = $this->stripe->accountLinks->create(
                        [
                            'account' => $connect_account->id,
                            'refresh_url' => get_the_permalink(),
                            'return_url' => get_the_permalink(),
                            'type' => 'account_onboarding',
                            'collect' => 'eventually_due',
                        ]
                    );
    
                    ob_start();
    
                    ?>
                        <h2>Your account has been created successfully</h2>
                        <h4>Please complete the verification process to get paid.</h4>
                        <a href="<?php echo $verify_account->url; ?>" style="color: #fff" class="fgpd-stripe-btn btn-yellow">Complete Verification Process</a>
                        
                    <?php
                    
                    return ob_get_clean();

                }else{

                    ob_start();
    
                    ?>
                        <h2>There is an error creating account</h2>
                        <h4>Please try after some time.</h4>
                        <form action="<?php echo get_the_permalink(); ?>" method="POST">	
                            <?php wp_nonce_field('create_connect_account', 'create_connect_account'); ?>
                            <input type="submit" name="create_connect_account" value="Create account to get paid">
                        </form>
                        
                    <?php
                    
                    return ob_get_clean();

                }

            }
            
            if($is_verified){

                $express_dashboard_link = $this->stripe->accounts->createLoginLink(
                    get_user_meta( get_current_user_id() , 'stripe_acc_id', true ),
                  );

                ob_start();

                    echo get_user_meta( get_current_user_id() , 'stripe_acc_id', true );
                    // echo 'Is verified : ' . $this->is_connect_verified;

                    // $is_verified = $this->stripe->accounts->retrieve( get_user_meta( get_current_user_id(), 'stripe_acc_id', true ) );
                    // echo 'charges_enabled : ' . $is_verified->charges_enabled . ' | payouts_enabled : ' . $is_verified->payouts_enabled;
                    // echo '<br/>'. ( $is_verified->charges_enabled && $is_verified->payouts_enabled );
                    ?>
                        <h2>Your account has been verified successfully</h2>
                        <h4>You can start submitting the response to tutoring requests now.</h4>
                        <a href="<?php echo $express_dashboard_link->url; ?>" style="color: #fff" class="fgpd-stripe-btn btn-yellow" target="blank">View Stripe account</a>
                    <?php

                    // print_r($is_verified);
                return ob_get_clean();

            }else{

                $verify_account = $this->stripe->accountLinks->create(
                    [
                        'account' => get_user_meta( get_current_user_id() , 'stripe_acc_id', true ),
                        'refresh_url' => get_the_permalink(),
                        'return_url' => get_the_permalink(),
                        'type' => 'account_onboarding',
                        'collect' => 'eventually_due',
                    ]
                );

                ob_start();

                ?>

                    <h4>Please complete the verification process to get paid.</h4>
                    <a href="<?php echo $verify_account->url; ?>" style="color: #fff" class="fgpd-stripe-btn btn-yellow">Complete Verification Process</a>
                    
                <?php
                
                return ob_get_clean();
            }

            

        }else{
            return 'Please login to continue';
        }
    }

    // public function create_connect_account_redirect()
    // {
    //     //// isset( $_POST['create_connect_account'] ) &&
    //     if( empty( get_user_meta( get_current_user_id() , 'stripe_acc_id', true ) ) && wp_verify_nonce($_POST['create_connect_account'], 'create_connect_account') ){

    //         $current_user = wp_get_current_user();
    //         $connect_account = $this->stripe->accounts->create([
    //             'type' => 'custom',
    //             'country' => 'US',
    //             'email' => 'developerpaddy4@gmail.com',//$current_user->user_email,
    //             'capabilities' => [
    //                 'card_payments' => ['requested' => true],
    //                 'transfers' => ['requested' => true],
    //             ],
    //             'settings' => [
    //                 'payouts' => [
    //                     'schedule' => [
    //                         'interval' => 'manual'
    //                     ]
    //                 ]
    //             ]
    //         ]);

    //         // print_r($connect_account);
    //         // exit();

    //         if(!empty( $connect_account->id) ){
    //             update_user_meta( get_current_user_id(), 'stripe_acc_id', $connect_account->id );
    //         }

    //     }elseif( !empty( get_user_meta( get_current_user_id() , 'stripe_acc_id', true ) ) && wp_verify_nonce($_POST['start_onboarding'], 'start_onboarding') ){

    //         // isset( $_POST['start_onboarding'] ) && 

    //         $verify_account = $this->stripe->accountLinks->create(
    //             [
    //               'account' => get_user_meta( get_current_user_id() , 'stripe_acc_id', true ),
    //               'refresh_url' => get_the_permalink(),
    //               'return_url' => get_the_permalink(),
    //               'type' => 'account_onboarding',
    //               'collect' => 'eventually_due',
    //             ]
    //         );

    //         wp_redirect( $verify_account->url );
    //         exit;

    //     }
    // }

    public function payment_account_redirect()
    {
        $profile_complete = get_post_meta(get_profile_id(),'profile_complete',true );
        global $current_user;
        global $post;
        $user_roles = $current_user->roles;
        $is_verified = 0;
        if(!empty( get_user_meta( get_current_user_id() , 'stripe_acc_id', true ) ) && in_array('tutor', $user_roles) && ( 330 == $post->post_parent || 8 == $post->post_parent) ){

            $account_update = $this->stripe->accounts->retrieve( get_user_meta( get_current_user_id(), 'stripe_acc_id', true ) );

            if( $account_update->charges_enabled && $account_update->payouts_enabled ){
                $is_verified = 1;
            }
        }
        //136 == $current_user->id &&
        if(  is_user_logged_in() && !$is_verified && $profile_complete && ( 330 == $post->post_parent || 8 == $post->post_parent) && !is_page('payments') ){
            if(in_array('tutor', $user_roles)){
                wp_redirect(home_url('/tutor-dashboard/payments/'));
                exit();
            }
        }

    }
}
?>