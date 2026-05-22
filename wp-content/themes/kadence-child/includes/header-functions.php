<?php

/**
 * Header Functionality
 * Login/logout/register shortcode for use in Kadence header HTML element.
 * Usage: [login_buttons]
 */

function my_login_register_buttons()
{
    if (is_user_logged_in()) {

        return '<a style="margin-inline: .5em;" class="login-button" href="' . esc_url(wp_logout_url(home_url())) . '">Log Out</a>
        <a class="login-button" href="' . esc_url(home_url('/my-account')) . '"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></a>';
    } else {
        return '<a style="margin-inline: .5em;" class="login-button" href="' . esc_url(wp_login_url(get_permalink())) . '">Log In</a> ' .
            '<a style="margin-inline: .5em;" class="login-button" href="' . esc_url(home_url('/wholesale-register/')) . '">Register</a>';
    }
}

add_shortcode('login_buttons', 'my_login_register_buttons');
