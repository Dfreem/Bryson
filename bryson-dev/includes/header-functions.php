<?php

/**
 * Header Functionality
 * Login/logout/register shortcode for use in Kadence header HTML element.
 * Usage: [login_buttons]
 */

function my_login_register_buttons()
{
    if (is_user_logged_in()) {

        return '<a style="margin-inline: .5em;" class="login-button" href="' . esc_url(wp_logout_url(home_url())) . '">Log Out</a>';
    } else {
        return '<a style="margin-inline: .5em;" class="login-button" href="' . esc_url(wp_login_url(get_permalink())) . '">Log In</a> ' .
            '<a style="margin-inline: .5em;" class="login-button" href="' . esc_url(home_url('/wholesale-register/')) . '">Register</a>';
    }
}

add_shortcode('login_buttons', 'my_login_register_buttons');
