<?php
/*
Plugin Name: Lock User Profiles
Plugin URI: https://www.littlebizzy.com/plugins/lock-user-profiles
Description: Prevents user profile changes 
Version: 1.0.0
Author: LittleBizzy
Author URI: https://www.littlebizzy.com
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
GitHub Plugin URI: littlebizzy/lock-user-profiles
Primary Branch: master
*/

// prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// disable wordpress.org updates for this plugin
add_filter( 'gu_override_dot_org', function( $overrides ) {
    $overrides[] = 'lock-user-profiles/lock-user-profiles.php';
    return $overrides;
}, 999 );

// helper function to fetch user data from wordpress and woocommerce
function get_locked_user_data( $user_id ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return [
            'email'              => '',
            'first_name'         => '',
            'last_name'          => '',
            'billing_email'      => '',
            'billing_first_name' => '',
            'billing_last_name'  => '',
        ];
    }
    return [
        'email'              => $user->user_email,
        'first_name'         => get_user_meta( $user_id, 'first_name', true ),
        'last_name'          => get_user_meta( $user_id, 'last_name', true ),
        'billing_email'      => get_user_meta( $user_id, 'billing_email', true ),
        'billing_first_name' => get_user_meta( $user_id, 'billing_first_name', true ),
        'billing_last_name'  => get_user_meta( $user_id, 'billing_last_name', true ),
    ];
}

// capture submitted wordpress profile changes before save
add_action( 'personal_options_update', 'collect_wp_profile_data', 10, 1 );
add_action( 'edit_user_profile_update', 'collect_wp_profile_data', 10, 1 );

// apply validation errors if restricted fields are changed
add_filter( 'user_profile_update_errors', 'validate_wp_profile_updates', 10, 3 );

// collect and sanitize wordpress core profile fields before validation
function collect_wp_profile_data( $user_id ) {
    // skip if current user is admin
    if ( current_user_can( 'manage_options' ) ) {
        return;
    }

    // store sanitized core fields and current data for validation
    global $submitted_wp_profile_data;
    $submitted_wp_profile_data = [
        'email'      => isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '',
        'first_name' => isset( $_POST['first_name'] ) ? sanitize_text_field( $_POST['first_name'] ) : '',
        'last_name'  => isset( $_POST['last_name'] ) ? sanitize_text_field( $_POST['last_name'] ) : '',
        'current'    => get_locked_user_data( $user_id ),
    ];
}

function validate_wp_profile_updates( $errors, $update, $user ) {
    global $submitted_wp_profile_data;
    if ( ! empty( $submitted_wp_profile_data ) ) {
        $submitted = $submitted_wp_profile_data;
        // Prevent email changes
        if ( $submitted['email'] !== $submitted['current']['email'] ) {
            $errors->add( 'email_change_error', __( 'You are not allowed to change your email address.', 'lock-user-profiles' ) );
        }
        // Prevent name changes
        if ( $submitted['first_name'] !== $submitted['current']['first_name'] ||
             $submitted['last_name'] !== $submitted['current']['last_name'] ) {
            $errors->add( 'name_change_error', __( 'You are not allowed to change your name.', 'lock-user-profiles' ) );
        }
    }
    return $errors;
}

// validate WooCommerce account updates
add_action( 'woocommerce_save_account_details_errors', 'validate_woocommerce_account_updates', 10, 2 );

function validate_woocommerce_account_updates( $errors, $user_id ) {
    // Allow admins to make changes
    if ( current_user_can( 'manage_options' ) ) {
        return;
    }

    $current_data = get_locked_user_data( $user_id );

    $submitted_billing_email      = isset( $_POST['billing_email'] ) ? sanitize_email( $_POST['billing_email'] ) : '';
    $submitted_billing_first_name = isset( $_POST['billing_first_name'] ) ? sanitize_text_field( $_POST['billing_first_name'] ) : '';
    $submitted_billing_last_name  = isset( $_POST['billing_last_name'] ) ? sanitize_text_field( $_POST['billing_last_name'] ) : '';

    // Prevent billing email changes
    if ( $submitted_billing_email !== $current_data['billing_email'] ) {
        $errors->add( 'email_change_error', __( 'You are not allowed to change your billing email address.', 'lock-user-profiles' ) );
    }
    // Prevent billing name changes
    if ( $submitted_billing_first_name !== $current_data['billing_first_name'] ||
         $submitted_billing_last_name !== $current_data['billing_last_name'] ) {
        $errors->add( 'name_change_error', __( 'You are not allowed to change your billing name.', 'lock-user-profiles' ) );
    }
}

// prevent profile updates via rest api (including WooCommerce fields)
add_filter( 'rest_pre_dispatch', 'block_rest_api_profile_updates', 10, 3 );

function block_rest_api_profile_updates( $result, $server, $request ) {
    // Check if this is the users/me endpoint and a POST request
    if ( preg_match( '#^/wp/v2/users/me$#', $request->get_route() ) && $request->get_method() === 'POST' ) {
        // Allow admin
        if ( current_user_can( 'manage_options' ) ) {
            return $result;
        }

        $current_user_id = get_current_user_id();
        if ( ! $current_user_id ) {
            return $result;
        }

        $submitted_data = $request->get_body_params();
        $current_data   = get_locked_user_data( $current_user_id );

        // Prevent changes to core fields
        if ( isset( $submitted_data['email'] ) && $submitted_data['email'] !== $current_data['email'] ) {
            return new WP_Error( 'rest_forbidden', __( 'You are not allowed to update your email address.', 'lock-user-profiles' ), [ 'status' => 403 ] );
        }
        if ( isset( $submitted_data['first_name'] ) && $submitted_data['first_name'] !== $current_data['first_name'] ) {
            return new WP_Error( 'rest_forbidden', __( 'You are not allowed to update your first name.', 'lock-user-profiles' ), [ 'status' => 403 ] );
        }
        if ( isset( $submitted_data['last_name'] ) && $submitted_data['last_name'] !== $current_data['last_name'] ) {
            return new WP_Error( 'rest_forbidden', __( 'You are not allowed to update your last name.', 'lock-user-profiles' ), [ 'status' => 403 ] );
        }

        // Prevent changes to WooCommerce fields
        if ( isset( $submitted_data['billing_email'] ) && $submitted_data['billing_email'] !== $current_data['billing_email'] ) {
            return new WP_Error( 'rest_forbidden', __( 'You are not allowed to update your billing email address.', 'lock-user-profiles' ), [ 'status' => 403 ] );
        }
        if ( isset( $submitted_data['billing_first_name'] ) && $submitted_data['billing_first_name'] !== $current_data['billing_first_name'] ) {
            return new WP_Error( 'rest_forbidden', __( 'You are not allowed to update your billing first name.', 'lock-user-profiles' ), [ 'status' => 403 ] );
        }
        if ( isset( $submitted_data['billing_last_name'] ) && $submitted_data['billing_last_name'] !== $current_data['billing_last_name'] ) {
            return new WP_Error( 'rest_forbidden', __( 'You are not allowed to update your billing last name.', 'lock-user-profiles' ), [ 'status' => 403 ] );
        }
    }

    return $result;
}

// Ref: ChatGPT
