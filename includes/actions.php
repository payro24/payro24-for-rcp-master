<?php
/**
 * Actions.
 *
 * @package RCP_payro24
 * @since 1.0
 */

/**
 * Creates a payment record remotely and redirects
 * the user to the proper page.
 *
 * @param array|object $subscription_data
 * @return void
 */
function rcp_payro24_create_payment( $subscription_data ) {

    global $rcp_options;

    $new_subscription_id = get_user_meta( $subscription_data['user_id'], 'rcp_subscription_level', true );
    if ( ! empty( $new_subscription_id ) ) {
        update_user_meta( $subscription_data['user_id'], 'rcp_subscription_level_new', $new_subscription_id );
    }

    $old_subscription_id = get_user_meta( $subscription_data['user_id'], 'rcp_subscription_level_old', true );
    update_user_meta( $subscription_data['user_id'], 'rcp_subscription_level', $old_subscription_id );

    // Start the output buffering.
    ob_start();

    $amount = str_replace( ',', '', $subscription_data['price'] );

    // Check if the currency is in Toman.
    if ( in_array( $rcp_options['currency'], array(
        'irt',
        'IRT',
        'تومان',
        __( 'تومان', 'rcp' ),
        __( 'تومان', 'payro24-for-rcp' )
    ) ) ) {
        $amount = $amount * 10;
    }

    // Send the request to payro24.
    $api_key = isset( $rcp_options['payro24_api_key'] ) ? $rcp_options['payro24_api_key'] : wp_die( __( 'payro24 API key is missing' ) );
    $sandbox = ( isset( $rcp_options['payro24_sandbox'] ) && $rcp_options['payro24_sandbox'] == 'yes' ) ? true : false;
    $callback = add_query_arg( 'gateway', 'payro24-for-rcp', $subscription_data['return_url'] );

    $data = array(
        'order_id'			=> $subscription_data['payment_id'],
        'amount'			=> intval( $amount ),
        'name'				=> $subscription_data['user_name'],
        'phone'				=> '',
        'mail'				=> $subscription_data['user_email'],
        'desc'				=> "{$subscription_data['subscription_name']} - {$subscription_data['key']}",
        'callback'			=> $callback,
    );

    $headers = array(
        'Content-Type'		=> 'application/json',
        'P-TOKEN'			=> $api_key,
        'X-SANDBOX'			=> $sandbox,
    );

    $args = array(
        'body'				=> json_encode( $data ),
        'headers'			=> $headers,
        'timeout'			=> 15,
    );

    $response = rcp_payro24_call_gateway_endpoint( 'https://api.payro24.ir/v1.1/payment', $args );
    if ( is_wp_error( $response ) ) {
        wp_die( sprintf( __( 'Unfortunately, the payment couldn\'t be processed due to the following reason: %s' ), $response->get_error_message() ) );
    }

    $http_status	= wp_remote_retrieve_response_code( $response );
    $result			= wp_remote_retrieve_body( $response );
    $result			= json_decode( $result );

    if ( 201 !== $http_status || empty( $result ) || empty( $result->link ) ) {
        wp_die( sprintf( __( 'Unfortunately, the payment couldn\'t be processed due to the following reason: %s' ), $result->error_message ) );
    }

    // Update transaction id into payment
    $rcp_payments = new RCP_Payments();
    $rcp_payments->update( $subscription_data['payment_id'], array( 'transaction_id' => $result->id ) );

    ob_end_clean();
    if ( headers_sent() ) {
        echo '<script type="text/javascript">window.onload = function() { top.location.href = "' . $result->link . '"; };</script>';
    } else {
        wp_redirect( $result->link );
    }

    exit;
}

add_action( 'rcp_gateway_payro24', 'rcp_payro24_create_payment' );

/**
 * Verify the payment when returning from the IPG.
 *
 * @return void
 */
function rcp_payro24_verify() {

    if ( ! isset( $_GET['gateway'] ) )
        return;

    if ( ! class_exists( 'RCP_Payments' ) )
        return;

    if ( 'payro24-for-rcp' !== sanitize_text_field( $_GET['gateway'] ) )
        return;

    global $rcp_options, $wpdb, $rcp_payments_db_name;

    $status    = !empty($_POST['status'])  ? sanitize_text_field($_POST['status'])   : (!empty($_GET['status'])  ? sanitize_text_field($_GET['status'])   : NULL);
    $track_id  = !empty($_POST['track_id'])? sanitize_text_field($_POST['track_id']) : (!empty($_GET['track_id'])? sanitize_text_field($_GET['track_id']) : NULL);
    $id        = !empty($_POST['id'])      ? sanitize_text_field($_POST['id'])       : (!empty($_GET['id'])      ? sanitize_text_field($_GET['id'])       : NULL);
    $order_id  = !empty($_POST['order_id'])? sanitize_text_field($_POST['order_id']) : (!empty($_GET['order_id'])? sanitize_text_field($_GET['order_id']) : NULL);
	$params    = !empty($_POST['id']) ? $_POST : $_GET;

    if( empty($order_id) || empty($id) ){
        return;
    }

    $rcp_payments = new RCP_Payments();
    $payment_data = $rcp_payments->get_payment($order_id);

    if ( empty( $payment_data ) )
        return;

    $user_id = intval( $payment_data->user_id );
    $subscription_name = $payment_data->subscription;

    if ( $payment_data->status != 'pending' ||  $payment_data->gateway != 'payro24' || $payment_data->transaction_id != $id || $payment_data->id != $order_id ) {
        return;
    }

    $api_key = isset( $rcp_options['payro24_api_key'] ) ? $rcp_options['payro24_api_key'] : wp_die( __( 'payro24 API key is missing' ) );
    $sandbox = ( isset( $rcp_options['payro24_sandbox'] ) && $rcp_options['payro24_sandbox'] == 'yes' ) ? true : false;

    if ( $status != 10 ) {
        $fault = rcp_payro24_fault_string( $status );
        $status = 'failed';
    }
    else {

        rcp_payro24_check_verification( $id );

        $data = array(
            'id'		=> $id,
            'order_id'	=> $order_id,
        );

        $headers = array(
            'Content-Type'		=> 'application/json',
            'P-TOKEN'			=> $api_key,
            'P-SANDBOX'			=> $sandbox,
        );

        $args = array(
            'body'				=> json_encode( $data ),
            'headers'			=> $headers,
            'timeout'			=> 15,
        );

        $response = rcp_payro24_call_gateway_endpoint( 'https://api.payro24.ir/v1.1/payment/verify', $args );
        if ( is_wp_error( $response ) ) {
            wp_die( sprintf( __( 'Unfortunately, the payment couldn\'t be processed due to the following reason: %s' ), $response->get_error_message() ) );
        }

        $http_status	= wp_remote_retrieve_response_code( $response );
        $result			= wp_remote_retrieve_body( $response );
        $result			= json_decode( $result );

        $status = '';
        $fault = '';

        if ( 200 !== $http_status ) {
            $status = 'failed';
            $fault = $result->error_message;
        }
        else {
            if ( $result->status >= 100 ) {
                $status = 'complete';

                $payment_data = array(
                    'date'				=> date( 'Y-m-d g:i:s' ),
                    'subscription'		=> $subscription_name,
                    'payment_type'		=> $payment_data->payment_method,
                    'subscription_key'	=> $payment_data->subscription_key,
                    'amount'			=> $result->amount,
                    'user_id'			=> $user_id,
                    'transaction_id'	=> $id,
                );

                $rcp_payments = new RCP_Payments();
                $payment_id = $rcp_payments->insert( $payment_data );
                $rcp_payments->update( $order_id, array( 'status' => 'complete' ) );
                rcp_payro24_set_verification( $payment_id, $id );

                $new_subscription_id = get_user_meta( $user_id, 'rcp_subscription_level_new', true );
                if ( ! empty( $new_subscription_id ) ) {
                    update_user_meta( $user_id, 'rcp_subscription_level', $new_subscription_id );
                }

                rcp_set_status( $user_id, 'active' );

                if ( version_compare( RCP_PLUGIN_VERSION, '2.1.0', '<' ) ) {
                    rcp_email_subscription_status( $user_id, 'active' );
                    if ( ! isset( $rcp_options['disable_new_user_notices'] ) ) {
                        wp_new_user_notification( $user_id );
                    }
                }

                update_user_meta( $user_id, 'rcp_payment_profile_id', $user_id );

                update_user_meta( $user_id, 'rcp_signup_method', 'live' );
                update_user_meta( $user_id, 'rcp_recurring', 'no' );

                $subscription          = rcp_get_subscription_details( rcp_get_subscription_id( $user_id ) );
                $member_new_expiration = date( 'Y-m-d H:i:s', strtotime( '+' . $subscription->duration . ' ' . $subscription->duration_unit . ' 23:59:59' ) );
                rcp_set_expiration_date( $user_id, $member_new_expiration );
                delete_user_meta( $user_id, '_rcp_expired_email_sent' );

                $log_data = array(
                    'post_title'   => __( 'Payment complete', 'payro24-for-rcp' ),
                    'post_content' => __( 'Transaction ID: ', 'payro24-for-rcp' ) . $id . __( ' / Payment method: ', 'payro24-for-rcp' ) . $payment_data->payment_method
						. ' Data: ' . print_r($result, true),
                    'post_parent'  => 0,
                    'log_type'     => 'gateway_error'
                );

                $log_meta = array(
                    'user_subscription' => $subscription_name,
                    'user_id'           => $user_id
                );

                WP_Logging::insert_log( $log_data, $log_meta );
            }
            else {
                $status = 'failed';
                $fault = rcp_payro24_fault_string( $result->status );
            }
        }
    }

    if ( 'failed' === $status ) {

        $rcp_payments = new RCP_Payments();
        $rcp_payments->update( $order_id, array( 'status' => $status ) );

        $log_data = array(
            'post_title'   => __( 'Payment failed', 'payro24-for-rcp' ),
            'post_content' => __( 'Transaction did not succeed due to following reason:', 'payro24-for-rcp' ) . $fault
				. __( ' / Payment method: ', 'payro24-for-rcp' ) . $payment_data->payment_method. ' Data: ' . print_r($params, true),
            'post_parent'  => 0,
            'log_type'     => 'gateway_error'
        );

        $log_meta = array(
            'user_subscription' => $subscription_name,
            'user_id'           => $user_id
        );

        WP_Logging::insert_log( $log_data, $log_meta );
    }

    add_filter( 'the_content', function( $content ) use( $status, $track_id, $fault ) {
        $message = '<style>
            .payro24-rcp-success {
                background: #4CAF50;
                padding: 15px;
                color: #fff;
                text-align: center;
            }
            .payro24-rcp-error {
                background: #F44336;
                padding: 15px;
                color: #fff;
                text-align: center;
            }
        </style>';

        if ( $status == 'complete' ) {
            $message .= '<br><div class="payro24-rcp-success">' . __( 'Payment was successful. Transaction tracking number is: ', 'payro24-for-rcp' ) . $track_id . '</div>';
        }
        if ( $status == 'failed' ) {
            $message .= '<br><div class="payro24-rcp-error">' . __( 'Payment failed due to the following reason: ', 'payro24-for-rcp' ) . $fault . '<br>' . __( 'Your transaction tracking number is: ', 'payro24-for-rcp' ) . $track_id . '</div>';
        }

        return $message . $content;
    } );
}

add_action( 'init', 'rcp_payro24_verify' );

/**
 * Change a user status to expired instead of cancelled.
 *
 * @param string $status
 * @param int $user_id
 * @return boolean
 */
function rcp_payro24_change_cancelled_to_expired( $status, $user_id ) {
    if ( 'cancelled' == $status ) {
        rcp_set_status( $user_id, 'expired' );
    }

    return true;
}

add_action( 'rcp_set_status', 'rcp_payro24_change_cancelled_to_expired', 10, 2 );
