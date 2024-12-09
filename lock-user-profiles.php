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
    return [
        'email'           => get_userdata( $user_id )->user_email,
        'first_name'      => get_user_meta( $user_id, 'first_name', true ),
        'last_name'       => get_user_meta( $user_id, 'last_name', true ),
        'billing_email'   => get_user_meta( $user_id, 'billing_email', true ),
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
        'email'      => sanitize_email( $_POST['email'] ?? '' ),
        'first_name' => sanitize_text_field( $_POST['first_name'] ?? '' ),
        'last_name'  => sanitize_text_field( $_POST['last_name'] ?? '' ),
        'current'    => get_locked_user_data( $user_id ),
    ];
}

function validate_wp_profile_updates( $errors, $update, $user ) {
    global $submitted_wp_profile_data;

    if ( ! empty( $submitted_wp_profile_data ) ) {
        $submitted = $submitted_wp_profile_data;

        // prevent email changes
        if ( $submitted['email'] !== $submitted['current']['email'] ) {
            $errors->add( 'email_change_error', __( 'You are not allowed to change your email address.', 'lock-user-profiles' ) );
        }

        // prevent first or last name changes
        if ( $submitted['first_name'] !== $submitted['current']['first_name'] ||
             $submitted['last_name'] !== $submitted['current']['last_name'] ) {
            $errors->add( 'name_change_error', __( 'You are not allowed to change your name.', 'lock-user-profiles' ) );
        }
    }
}

// validate WooCommerce account updates
add_action( 'woocommerce_save_account_details_errors', 'validate_woocommerce_account_updates', 10, 2 );

function validate_woocommerce_account_updates( $errors, $user_id ) {
    // allow admins to make changes
    if ( current_user_can( 'manage_options' ) ) {
        return;
    }

    $current_data = get_locked_user_data( $user_id );

    // get submitted data
    $submitted_billing_email      = sanitize_email( $_POST['billing_email'] ?? '' );
    $submitted_billing_first_name = sanitize_text_field( $_POST['billing_first_name'] ?? '' );
    $submitted_billing_last_name  = sanitize_text_field( $_POST['billing_last_name'] ?? '' );

    // prevent billing email changes
    if ( $submitted_billing_email !== $current_data['billing_email'] ) {
        $errors->add( 'email_change_error', __( 'You are not allowed to change your billing email address.', 'lock-user-profiles' ) );
    }

    // prevent billing name changes
    if ( $submitted_billing_first_name !== $current_data['billing_first_name'] || $submitted_billing_last_name !== $current_data['billing_last_name'] ) {
        $errors->add( 'name_change_error', __( 'You are not allowed to change your billing name.', 'lock-user-profiles' ) );
    }
}

// prevent profile updates via rest api (including WooCommerce fields)
add_filter( 'rest_pre_dispatch', 'block_rest_api_profile_updates', 10, 3 );

function block_rest_api_profile_updates( $result, $server, $request ) {
    // block updates for the logged-in user's profile
    if ( preg_match( '#^/wp/v2/users/me$#', $request->get_route() ) && $request->get_method() === 'POST' ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            $submitted_data = $request->get_body_params();
            $current_data   = get_locked_user_data( get_current_user_id() );

            // prevent WordPress core field updates
            if ( isset( $submitted_data['email'] ) && $submitted_data['email'] !== $current_data['email'] ) {
                return new WP_Error( 'rest_forbidden', __( 'You are not allowed to update your email address.', 'lock-user-profiles' ) );
            }

            if ( isset( $submitted_data['first_name'] ) && $submitted_data['first_name'] !== $current_data['first_name'] ) {
                return new WP_Error( 'rest_forbidden', __( 'You are not allowed to update your first name.', 'lock-user-profiles' ) );
            }

            if ( isset( $submitted_data['last_name'] ) && $submitted_data['last_name'] !== $current_data['last_name'] ) {
                return new WP_Error( 'rest_forbidden', __( 'You are not allowed to update your last name.', 'lock-user-profiles' ) );
            }

            // prevent WooCommerce billing field updates
            if ( isset( $submitted_data['billing_email'] ) && $submitted_data['billing_email'] !== $current_data['billing_email'] ) {
                return new WP_Error( 'rest_forbidden', __( 'You are not allowed to update your billing email address.', 'lock-user-profiles' ) );
            }

            if ( isset( $submitted_data['billing_first_name'] ) && $submitted_data['billing_first_name'] !== $current_data['billing_first_name'] ) {
                return new WP_Error( 'rest_forbidden', __( 'You are not allowed to update your billing first name.', 'lock-user-profiles' ) );
            }

            if ( isset( $submitted_data['billing_last_name'] ) && $submitted_data['billing_last_name'] !== $current_data['billing_last_name'] ) {
                return new WP_Error( 'rest_forbidden', __( 'You are not allowed to update your billing last name.', 'lock-user-profiles' ) );
            }
        }
    }

    return $result;
}

// Ref: ChatGPT
