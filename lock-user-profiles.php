<?php
/*
Plugin Name: Lock User Profiles
Plugin URI: https://www.littlebizzy.com/plugins/lock-user-profiles
Description: Prevents users, including WooCommerce users, from changing their name or email address.
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

// hook into profile update to prevent name or email change
add_action( 'personal_options_update', 'lock_user_profiles_prevent_name_email_change', 10, 1 );
add_action( 'edit_user_profile_update', 'lock_user_profiles_prevent_name_email_change', 10, 1 );

function lock_user_profiles_prevent_name_email_change( $user_id ) {
    // get current user data
    $current_user = get_userdata( $user_id );

    // get the submitted data
    $submitted_email = $_POST['email'] ?? '';
    $submitted_first_name = $_POST['first_name'] ?? '';
    $submitted_last_name = $_POST['last_name'] ?? '';

    // check if email has changed
    if ( $submitted_email !== $current_user->user_email ) {
        add_filter( 'user_profile_update_errors', function( $errors ) {
            $errors->add( 'email_change_error', __( 'You are not allowed to change your email address.', 'lock-user-profiles' ) );
        } );
    }

    // check if first or last name has changed
    if ( $submitted_first_name !== $current_user->first_name || $submitted_last_name !== $current_user->last_name ) {
        add_filter( 'user_profile_update_errors', function( $errors ) {
            $errors->add( 'name_change_error', __( 'You are not allowed to change your name.', 'lock-user-profiles' ) );
        } );
    }
}

// Ref: ChatGPT
