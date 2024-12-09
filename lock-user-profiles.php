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

// prevent profile updates in wordpress
add_action( 'personal_options_update', 'lock_user_profile_updates', 10, 1 );
add_action( 'edit_user_profile_update', 'lock_user_profile_updates', 10, 1 );

function lock_user_profile_updates( $user_id ) {
    // allow admins to make changes
    if ( current_user_can( 'manage_options' ) ) {
        return;
    }

    // get the current user data
    $current_user = get_userdata( $user_id );

    // get the submitted data
    $submitted_email      = $_POST['email'] ?? '';
    $submitted_first_name = $_POST['first_name'] ?? '';
    $submitted_last_name  = $_POST['last_name'] ?? '';

    // prevent email changes
    if ( $submitted_email !== $current_user->user_email ) {
        add_filter( 'user_profile_update_errors', function( $errors ) {
            $errors->add( 'email_change_error', __( 'You are not allowed to change your email address.', 'lock-user-profiles' ) );
        } );
    }

    // prevent first name changes
    if ( $submitted_first_name !== $current_user->first_name ) {
        add_filter( 'user_profile_update_errors', function( $errors ) {
            $errors->add( 'first_name_change_error', __( 'You are not allowed to change your first name.', 'lock-user-profiles' ) );
        } );
    }

    // prevent last name changes
    if ( $submitted_last_name !== $current_user->last_name ) {
        add_filter( 'user_profile_update_errors', function( $errors ) {
            $errors->add( 'last_name_change_error', __( 'You are not allowed to change your last name.', 'lock-user-profiles' ) );
        } );
    }
}

// prevent profile updates via rest api
add_filter( 'rest_pre_dispatch', function( $result, $server, $request ) {
    // only target user update requests
    if ( $request->get_route() === '/wp/v2/users/me' && $request->get_method() === 'POST' ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', __( 'You are not allowed to update your profile.', 'lock-user-profiles' ) );
        }
    }

    return $result;
}, 10, 3 );

// prevent account updates in WooCommerce
add_action( 'woocommerce_save_account_details_errors', 'lock_user_profile_woocommerce_updates', 10, 2 );

function lock_user_profile_woocommerce_updates( $errors, $current_user ) {
    // allow admins to make changes
    if ( current_user_can( 'manage_options' ) ) {
        return;
    }

    // get submitted data
    $submitted_billing_email      = sanitize_email( $_POST['billing_email'] ?? '' );
    $submitted_billing_first_name = sanitize_text_field( $_POST['billing_first_name'] ?? '' );
    $submitted_billing_last_name  = sanitize_text_field( $_POST['billing_last_name'] ?? '' );

    // prevent billing email changes
    if ( $submitted_billing_email !== get_user_meta( $current_user->ID, 'billing_email', true ) ) {
        $errors->add( 'email_change_error', __( 'You are not allowed to change your email address.', 'lock-user-profiles' ) );
    }

    // prevent billing name changes
    if ( $submitted_billing_first_name !== get_user_meta( $current_user->ID, 'billing_first_name', true ) || 
         $submitted_billing_last_name !== get_user_meta( $current_user->ID, 'billing_last_name', true ) ) {
        $errors->add( 'name_change_error', __( 'You are not allowed to change your name.', 'lock-user-profiles' ) );
    }
}

// Ref: ChatGPT
