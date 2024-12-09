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

// prevent profile updates in WordPress
add_action( 'personal_options_update', 'lock_user_profile_updates', 10, 1 );
add_action( 'edit_user_profile_update', 'lock_user_profile_updates', 10, 1 );

function lock_user_profile_updates( $user_id ) {
    if ( current_user_can( 'manage_options' ) ) {
        return;
    }

    $current_user = get_userdata( $user_id );
    $submitted_email = $_POST['email'] ?? '';
    $submitted_first_name = $_POST['first_name'] ?? '';
    $submitted_last_name = $_POST['last_name'] ?? '';

    if ( $submitted_email !== $current_user->user_email ) {
        add_filter( 'user_profile_update_errors', function( $errors ) {
            $errors->add( 'email_change_error', __( 'You are not allowed to change your email address.', 'lock-user-profiles' ) );
        } );
    }

    if ( $submitted_first_name !== $current_user->first_name || $submitted_last_name !== $current_user->last_name ) {
        add_filter( 'user_profile_update_errors', function( $errors ) {
            $errors->add( 'name_change_error', __( 'You are not allowed to change your name.', 'lock-user-profiles' ) );
        } );
    }
}

// prevent account updates in WooCommerce
add_action( 'woocommerce_save_account_details_errors', 'lock_user_profile_woocommerce_updates', 10, 2 );

function lock_user_profile_woocommerce_updates( $errors, $current_user ) {
    if ( current_user_can( 'manage_options' ) ) {
        return;
    }

    $submitted_billing_email = $_POST['billing_email'] ?? '';
    $submitted_billing_first_name = $_POST['billing_first_name'] ?? '';
    $submitted_billing_last_name = $_POST['billing_last_name'] ?? '';

    if ( $submitted_billing_email && $submitted_billing_email !== get_user_meta( $current_user->ID, 'billing_email', true ) ) {
        $errors->add( 'email_change_error', __( 'You are not allowed to change your email address.', 'lock-user-profiles' ) );
    }

    if ( ( $submitted_billing_first_name && $submitted_billing_first_name !== get_user_meta( $current_user->ID, 'billing_first_name', true ) ) || 
         ( $submitted_billing_last_name && $submitted_billing_last_name !== get_user_meta( $current_user->ID, 'billing_last_name', true ) ) ) {
        $errors->add( 'name_change_error', __( 'You are not allowed to change your name.', 'lock-user-profiles' ) );
    }
}

// Ref: ChatGPT
