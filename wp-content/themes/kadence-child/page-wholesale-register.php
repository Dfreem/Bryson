<?php

/**
 * Template Name: Wholesale Register
 *
 * Drop this file into your child theme folder.
 * Create a WordPress page, set its slug to "wholesale-register",
 * and choose "Wholesale Register" as the page template.
 */

if (is_user_logged_in()) {
    wp_redirect(wc_get_account_endpoint_url('dashboard'));
    exit;
}

get_header();

$errors   = [];
$success  = false;
$old      = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['whr_nonce'])) {

    if (! wp_verify_nonce($_POST['whr_nonce'], 'wholesale_register')) {
        $errors[] = 'Security check failed. Please refresh and try again.';
    } else {

        // Sanitise every field
        $fields = [
            'first_name'   => sanitize_text_field($_POST['first_name']   ?? ''),
            'last_name'    => sanitize_text_field($_POST['last_name']    ?? ''),
            'email'        => sanitize_email($_POST['email']        ?? ''),
            'company'      => sanitize_text_field($_POST['company']      ?? ''),
            'address_1'    => sanitize_text_field($_POST['address_1']    ?? ''),
            'address_2'    => sanitize_text_field($_POST['address_2']    ?? ''),
            'city'         => sanitize_text_field($_POST['city']         ?? ''),
            'state'        => sanitize_text_field($_POST['state']        ?? ''),
            'postcode'     => sanitize_text_field($_POST['postcode']     ?? ''),
            'country'      => sanitize_text_field($_POST['country']      ?? ''),
            'phone'        => sanitize_text_field($_POST['phone']        ?? ''),
            'username'     => sanitize_user($_POST['username']     ?? ''),
            'password'     => $_POST['password']     ?? '',
            'password2'    => $_POST['password2']    ?? '',
        ];
        $old = $fields;

        // Validate
        if (empty($fields['first_name']))  $errors[] = 'First name is required.';
        if (empty($fields['last_name']))   $errors[] = 'Last name is required.';
        if (! is_email($fields['email']))  $errors[] = 'A valid email address is required.';
        if (email_exists($fields['email'])) $errors[] = 'That email address is already registered.';
        if (empty($fields['company']))     $errors[] = 'Company name is required.';
        if (empty($fields['address_1']))   $errors[] = 'Address Line 1 is required.';
        if (empty($fields['city']))        $errors[] = 'City is required.';
        if (empty($fields['state']))       $errors[] = 'State / Province is required.';
        if (empty($fields['postcode']))    $errors[] = 'ZIP / Postcode is required.';
        if (empty($fields['country']))     $errors[] = 'Country is required.';
        if (empty($fields['phone']))       $errors[] = 'Phone number is required.';
        if (empty($fields['username']))    $errors[] = 'A login / username is required.';
        if (username_exists($fields['username'])) $errors[] = 'That username is already taken.';
        if (strlen($fields['password']) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($fields['password'] !== $fields['password2']) $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            $user_id = wp_insert_user([
                'user_login'   => $fields['username'],
                'user_email'   => $fields['email'],
                'user_pass'    => $fields['password'],
                'first_name'   => $fields['first_name'],
                'last_name'    => $fields['last_name'],
                'display_name' => $fields['first_name'] . ' ' . $fields['last_name'],
                'role'         => 'pending_wholesale', // custom role — see functions.php
            ]);

            if (is_wp_error($user_id)) {
                $errors[] = $user_id->get_error_message();
            } else {
                // Save extra meta
                $meta_map = [
                    'billing_company'   => $fields['company'],
                    'billing_address_1' => $fields['address_1'],
                    'billing_address_2' => $fields['address_2'],
                    'billing_city'      => $fields['city'],
                    'billing_state'     => $fields['state'],
                    'billing_postcode'  => $fields['postcode'],
                    'billing_country'   => $fields['country'],
                    'billing_phone'     => $fields['phone'],
                    'billing_first_name' => $fields['first_name'],
                    'billing_last_name' => $fields['last_name'],
                    'billing_email'     => $fields['email'],
                    'whr_pending'       => '1',
                ];
                foreach ($meta_map as $key => $value) {
                    update_user_meta($user_id, $key, $value);
                }

                // DEBUG: confirm billing name meta survived registration
                error_log(sprintf(
                    '[WHR DEBUG] post-registration user %d — billing_first_name="%s" billing_last_name="%s"',
                    $user_id,
                    get_user_meta($user_id, 'billing_first_name', true),
                    get_user_meta($user_id, 'billing_last_name', true)
                ));

                // Notify admin
                // whr_notify_admin_new_application($user_id, $fields);

                $success = true;
                $old     = [];
            }
        }
    }
}
?>

<main id="whr-wrap">

    <div class="whr-card">

        <div class="whr-header">
            <!-- <div class="whr-logo-mark">
                <?php
                if (has_custom_logo()) {
                    the_custom_logo();
                } else {
                    echo '<span class="whr-site-name">' . esc_html(get_bloginfo('name')) . '</span>';
                }
                ?>
            </div> -->
            <h1>Wholesale Account<br><em>Application</em></h1>
            <p class="whr-sub">Fill in the form below. Our team will review your application and be in touch shortly.</p>
        </div>

        <?php if ($success) : ?>

            <div class="whr-success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M8 12l3 3 5-5" />
                </svg>
                <h2>Application Received</h2>
                <p>Thank you! We've notified our team and will email you once your account is approved.</p>
            </div>

        <?php else : ?>

            <?php if ($errors) : ?>
                <div class="whr-errors" role="alert">
                    <strong>Please fix the following:</strong>
                    <ul>
                        <?php foreach ($errors as $e) : ?>
                            <li><?php echo esc_html($e); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" class="whr-form" novalidate>
                <?php wp_nonce_field('wholesale_register', 'whr_nonce'); ?>

                <fieldset>
                    <legend>Contact Information</legend>
                    <div class="whr-row whr-col2">
                        <div class="whr-field">
                            <label for="first_name">First Name <span>*</span></label>
                            <input type="text" id="first_name" name="first_name" required
                                value="<?php echo esc_attr($old['first_name'] ?? ''); ?>">
                        </div>
                        <div class="whr-field">
                            <label for="last_name">Last Name <span>*</span></label>
                            <input type="text" id="last_name" name="last_name" required
                                value="<?php echo esc_attr($old['last_name'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="whr-row whr-col2">
                        <div class="whr-field">
                            <label for="email">Email Address <span>*</span></label>
                            <input type="email" id="email" name="email" required
                                value="<?php echo esc_attr($old['email'] ?? ''); ?>">
                        </div>
                        <div class="whr-field">
                            <label for="phone">Phone <span>*</span></label>
                            <input type="tel" id="phone" name="phone" required
                                value="<?php echo esc_attr($old['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="whr-field">
                        <label for="company">Company <span>*</span></label>
                        <input type="text" id="company" name="company" required
                            value="<?php echo esc_attr($old['company'] ?? ''); ?>">
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Business Address</legend>
                    <div class="whr-field">
                        <label for="address_1">Address Line 1 <span>*</span></label>
                        <input type="text" id="address_1" name="address_1" required
                            value="<?php echo esc_attr($old['address_1'] ?? ''); ?>">
                    </div>
                    <div class="whr-field">
                        <label for="address_2">Address Line 2</label>
                        <input type="text" id="address_2" name="address_2"
                            value="<?php echo esc_attr($old['address_2'] ?? ''); ?>">
                    </div>
                    <div class="whr-row whr-col2">
                        <div class="whr-field">
                            <label for="city">City <span>*</span></label>
                            <input type="text" id="city" name="city" required
                                value="<?php echo esc_attr($old['city'] ?? ''); ?>">
                        </div>
                        <div class="whr-field">
                            <label for="state">State / Province <span>*</span></label>
                            <input type="text" id="state" name="state" required
                                value="<?php echo esc_attr($old['state'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="whr-row whr-col2">
                        <div class="whr-field">
                            <label for="postcode">ZIP / Postcode <span>*</span></label>
                            <input type="text" id="postcode" name="postcode" required
                                value="<?php echo esc_attr($old['postcode'] ?? ''); ?>">
                        </div>
                        <div class="whr-field">
                            <label for="country">Country <span>*</span></label>
                            <input type="text" id="country" name="country" required
                                value="<?php echo esc_attr($old['country'] ?? ''); ?>">
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Login Credentials</legend>
                    <div class="whr-field">
                        <label for="username">Username / Login <span>*</span></label>
                        <input type="text" id="username" name="username" required autocomplete="username"
                            value="<?php echo esc_attr($old['username'] ?? ''); ?>">
                    </div>
                    <div class="whr-row whr-col2">
                        <div class="whr-field">
                            <label for="password">Password <span>*</span></label>
                            <input type="password" id="password" name="password" required
                                autocomplete="new-password" minlength="8">
                            <small>Minimum 8 characters</small>
                        </div>
                        <div class="whr-field">
                            <label for="password2">Confirm Password <span>*</span></label>
                            <input type="password" id="password2" name="password2" required
                                autocomplete="new-password">
                        </div>
                    </div>
                </fieldset>

                <button type="submit" class="whr-submit">
                    <span>Submit Application</span>
                    <svg viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l5.5 5.25a.75.75 0 010 1.08l-5.5 5.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd" />
                    </svg>
                </button>

                <p class="whr-login-link">Already have an account? <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">Sign in</a></p>
            </form>

        <?php endif; ?>

    </div><!-- .whr-card -->

</main>

<style>
    /* ─── Reset / Base ─────────────────────────────────────────── */
    #whr-wrap {
        --brand: #1a3a2a;
        --brand-mid: #2d6a4f;
        --accent: #52b788;
        --accent-lt: #d8f3dc;
        --surface: #ffffff;
        --border: #d0ddd6;
        --text: #1c2b22;
        --muted: #6b7f74;
        --error-bg: #fff0f0;
        --error-bd: #f5c2c2;
        --error-tx: #8b1a1a;
        --radius: 10px;
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, .07), 0 12px 40px -8px rgba(26, 58, 42, .12);

        font-family: 'Georgia', 'Times New Roman', serif;
        min-height: 100vh;
        background: linear-gradient(135deg, #e8f5e9 0%, #f1f8f4 50%, #e0f2e9 100%);
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding: 48px 16px 80px;
    }

    /* ─── Card ─────────────────────────────────────────────────── */
    .whr-card {
        background: var(--surface);
        border-radius: 18px;
        box-shadow: var(--shadow);
        width: 100%;
        max-width: 640px;
        overflow: hidden;
    }

    /* ─── Header ───────────────────────────────────────────────── */
    .whr-header {
        background: var(--brand);
        color: #fff;
        padding: 40px 48px 36px;
        border-bottom: 4px solid var(--accent);
    }

    .whr-logo-mark {
        margin-bottom: 20px;
    }

    .whr-logo-mark img {
        max-height: 48px;
        width: auto;
    }

    .whr-site-name {
        font-size: 14px;
        font-weight: 600;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: var(--accent);
    }

    .whr-header h1 {
        font-size: clamp(26px, 5vw, 34px);
        font-weight: 400;
        line-height: 1.15;
        letter-spacing: -.02em;
        color: #fff;
    }

    .whr-header h1 em {
        font-style: italic;
        color: var(--accent);
    }

    .whr-sub {
        margin-top: 12px;
        font-size: 14px;
        line-height: 1.6;
        color: #a8c4b2;
        font-family: system-ui, sans-serif;
    }

    /* ─── Form body ─────────────────────────────────────────────── */
    .whr-form {
        padding: 40px 48px;
    }

    fieldset {
        border: none;
        margin-bottom: 36px;
    }

    fieldset+fieldset {
        border-top: 1px solid var(--border);
        padding-top: 32px;
    }

    legend {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: var(--brand-mid);
        font-family: system-ui, sans-serif;
        margin-bottom: 20px;
        padding-bottom: 8px;
        border-bottom: 2px solid var(--accent-lt);
        width: 100%;
    }

    /* ─── Field layout ─────────────────────────────────────────── */
    .whr-row {
        display: flex;
        gap: 16px;
    }

    .whr-col2>.whr-field {
        flex: 1;
        min-width: 0;
    }

    .whr-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin-bottom: 16px;
    }

    .whr-field:last-child {
        margin-bottom: 0;
    }

    label {
        font-size: 13px;
        font-weight: 600;
        color: var(--text);
        font-family: system-ui, sans-serif;
        letter-spacing: .01em;
    }

    label span {
        color: var(--accent);
    }

    input[type="text"],
    input[type="email"],
    input[type="tel"],
    input[type="password"] {
        width: 100%;
        padding: 11px 14px;
        border: 1.5px solid var(--border);
        border-radius: var(--radius);
        font-size: 15px;
        font-family: system-ui, sans-serif;
        color: var(--text);
        background: #fafcfb;
        transition: border-color .18s, box-shadow .18s, background .18s;
        outline: none;
        -webkit-appearance: none;
    }

    input:focus {
        border-color: var(--accent);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(82, 183, 136, .18);
    }

    small {
        font-size: 11.5px;
        color: var(--muted);
        font-family: system-ui, sans-serif;
    }

    /* ─── Errors ────────────────────────────────────────────────── */
    .whr-errors {
        margin: 0 48px 28px;
        padding: 16px 20px;
        background: var(--error-bg);
        border: 1.5px solid var(--error-bd);
        border-radius: var(--radius);
        color: var(--error-tx);
        font-family: system-ui, sans-serif;
        font-size: 13.5px;
    }

    .whr-errors strong {
        display: block;
        margin-bottom: 8px;
    }

    .whr-errors ul {
        padding-left: 18px;
        line-height: 1.7;
    }

    /* ─── Submit ─────────────────────────────────────────────────── */
    .whr-submit {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        width: 100%;
        padding: 15px 24px;
        background: var(--brand);
        color: #fff;
        border: none;
        border-radius: var(--radius);
        font-size: 15px;
        font-weight: 600;
        font-family: system-ui, sans-serif;
        letter-spacing: .02em;
        cursor: pointer;
        transition: background .2s, transform .15s, box-shadow .2s;
        margin-top: 8px;
    }

    .whr-submit svg {
        width: 18px;
        height: 18px;
        transition: transform .2s;
    }

    .whr-submit:hover {
        background: var(--brand-mid);
        box-shadow: 0 4px 16px rgba(26, 58, 42, .25);
    }

    .whr-submit:hover svg {
        transform: translateX(4px);
    }

    .whr-submit:active {
        transform: scale(.98);
    }

    .whr-login-link {
        text-align: center;
        margin-top: 20px;
        font-size: 13.5px;
        font-family: system-ui, sans-serif;
        color: var(--muted);
    }

    .whr-login-link a {
        color: var(--brand-mid);
        font-weight: 600;
        text-decoration: none;
    }

    .whr-login-link a:hover {
        text-decoration: underline;
    }

    /* ─── Success ────────────────────────────────────────────────── */
    .whr-success {
        padding: 60px 48px;
        text-align: center;
        font-family: system-ui, sans-serif;
    }

    .whr-success svg {
        width: 56px;
        height: 56px;
        stroke: var(--accent);
        margin-bottom: 20px;
    }

    .whr-success h2 {
        font-size: 24px;
        font-family: Georgia, serif;
        color: var(--brand);
        margin-bottom: 12px;
    }

    .whr-success p {
        color: var(--muted);
        line-height: 1.65;
        font-size: 15px;
    }

    /* ─── Responsive ─────────────────────────────────────────────── */
    @media (max-width: 520px) {
        .whr-header {
            padding: 28px 24px 24px;
        }

        .whr-form {
            padding: 28px 24px;
        }

        .whr-errors {
            margin-inline: 24px;
        }

        .whr-success {
            padding: 40px 24px;
        }

        .whr-row {
            flex-direction: column;
            gap: 0;
        }
    }
</style>

<?php get_footer(); ?>