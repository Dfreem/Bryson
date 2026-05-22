<?php

/**
 * ══════════════════════════════════════════════════════════════
 *  WHOLESALE REGISTRATION — paste into your child theme's
 *  functions.php (or a dedicated /includes/wholesale-register.php
 *  that you require_once from functions.php)
 * ══════════════════════════════════════════════════════════════
 */


/* ──────────────────────────────────────────────────────────────
 * 1. PENDING WHOLESALE ROLE
 *    Users land here on registration; admins promote them later.
 * ──────────────────────────────────────────────────────────────*/
add_action('init', function () {

    // Add the role if it doesn't exist yet
    if (! get_role('pending_wholesale')) {
        add_role(
            'pending_wholesale',
            'Pending Wholesale',
            ['read' => true]           // bare minimum — no shop access
        );
    }

    // Make sure the "wholesale_customer" role exists too
    // (skip if you already have one via another plugin)
    if (! get_role('wholesale_customer')) {
        $customer = get_role('customer');
        add_role(
            'wholesale_customer',
            'Wholesale Customer',
            $customer ? $customer->capabilities : ['read' => true]
        );
    }
});


/* ──────────────────────────────────────────────────────────────
 * 2. BLOCK PENDING USERS FROM LOGGING IN
 * ──────────────────────────────────────────────────────────────*/
add_filter('wp_authenticate_user', function ($user, $password) {

    if (is_wp_error($user)) {
        return $user;
    }

    if (in_array('pending_wholesale', (array) $user->roles, true)) {
        return new WP_Error(
            'pending_approval',
            __('Your wholesale account application is pending admin approval. You will receive an email once it has been reviewed.')
        );
    }

    return $user;
}, 10, 2);


// /* ──────────────────────────────────────────────────────────────
//  * 3. ADMIN NOTIFICATION EMAIL
//  *    Called from the page template after a successful insert.
//  * ──────────────────────────────────────────────────────────────*/
// function whr_notify_admin_new_application($user_id, $fields)
// {

//     $admin_email  = get_option('admin_email');
//     $site_name    = get_bloginfo('name');
//     $approve_url  = add_query_arg(
//         [
//             'action'  => 'whr_approve',
//             'user_id' => $user_id,
//             'nonce'   => wp_create_nonce('whr_approve_' . $user_id),
//         ],
//         admin_url('admin-post.php')
//     );
//     $user_edit_url = get_edit_user_link($user_id);

//     $subject = "[{$site_name}] New Wholesale Application — {$fields['first_name']} {$fields['last_name']}";

//     $message  = "A new wholesale account application has been submitted.\n\n";
//     $message .= "Name    : {$fields['first_name']} {$fields['last_name']}\n";
//     $message .= "Company : {$fields['company']}\n";
//     $message .= "Email   : {$fields['email']}\n";
//     $message .= "Phone   : {$fields['phone']}\n";
//     $message .= "Address : {$fields['address_1']}";
//     if ($fields['address_2']) $message .= ", {$fields['address_2']}";
//     $message .= "\n          {$fields['city']}, {$fields['state']} {$fields['postcode']}, {$fields['country']}\n";
//     $message .= "Username: {$fields['username']}\n\n";
//     $message .= "── One-click approve ──────────────────────────────\n";
//     $message .= $approve_url . "\n\n";
//     $message .= "── Edit user in WP Admin ──────────────────────────\n";
//     $message .= $user_edit_url . "\n";

//     wp_mail($admin_email, $subject, $message);
// }


/* ──────────────────────────────────────────────────────────────
 * 7. REDIRECT / PAGE RULES (keep your existing ones here)
 * ──────────────────────────────────────────────────────────────*/
add_action('template_redirect', function () {

    // Logged-in users off the register page
    if (is_user_logged_in() && is_page('wholesale-register')) {
        wp_redirect(wc_get_account_endpoint_url('dashboard'));
        exit;
    }

    // Logged-out users trying to reach the account dashboard
    if (! is_user_logged_in() && is_account_page()) {
        wp_redirect(wc_get_page_permalink('myaccount'));
        exit;
    }
});

// Point WooCommerce "Register" links to your custom page
add_filter('woocommerce_register_url', function () {
    return get_permalink(get_page_by_path('wholesale-register'));
});

// After any successful WC registration, send to dashboard
add_filter('woocommerce_registration_redirect', function () {
    return wc_get_account_endpoint_url('dashboard');
});
