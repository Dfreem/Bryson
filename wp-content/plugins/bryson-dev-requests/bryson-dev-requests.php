<?php

/**
 * Plugin Name: Bryson Dev Requests
 * Description: Kanban board for submitting and tracking development requests.
 * Version: 1.1.0
 * Author: Devin Freeman
 */

defined('ABSPATH') || exit;

define('BDR_VERSION', '1.1.0');
define('BDR_DIR', plugin_dir_path(__FILE__));
define('BDR_URL', plugin_dir_url(__FILE__));

// ── Activation: create tables ─────────────────────────────
register_activation_hook(__FILE__, 'bdr_activate');
function bdr_activate()
{
    global $wpdb;
    $table    = $wpdb->prefix . 'dev_requests';
    $comments = $wpdb->prefix . 'dev_request_comments';
    $charset  = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE $table (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        title       VARCHAR(255)    NOT NULL,
        description LONGTEXT        NOT NULL,
        status      VARCHAR(50)     NOT NULL DEFAULT 'requested',
        priority    VARCHAR(20)     NOT NULL DEFAULT 'normal',
        created_by  BIGINT UNSIGNED NOT NULL,
        created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;");
    dbDelta("CREATE TABLE $comments (
        id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        request_id BIGINT UNSIGNED NOT NULL,
        user_id    BIGINT UNSIGNED NOT NULL,
        comment    LONGTEXT        NOT NULL,
        is_admin   TINYINT(1)      NOT NULL DEFAULT 0,
        is_read    TINYINT(1)      NOT NULL DEFAULT 0,
        created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY request_id (request_id)
    ) $charset;");
}

// ── Email settings helper ─────────────────────────────────
function bdr_email_enabled($key)
{
    $opts = get_option('bdr_email_settings', []);
    // All off by default
    return !empty($opts[$key]);
}

// ── Admin menu ────────────────────────────────────────────
add_action('admin_menu', 'bdr_menu');
function bdr_menu()
{
    $unread = bdr_unread_count();
    $bubble = $unread ? ' <span class="awaiting-mod">' . $unread . '</span>' : '';

    add_menu_page('Dev Requests', 'Dev Requests' . $bubble, 'read', 'bdr-board', 'bdr_board_page', 'dashicons-editor-ul', 30);

    add_submenu_page('bdr-board', 'Email Settings', 'Email Settings', 'manage_options', 'bdr-settings', 'bdr_settings_page');
}

function bdr_unread_count()
{
    global $wpdb;
    $comments       = $wpdb->prefix . 'dev_request_comments';
    $requests_table = $wpdb->prefix . 'dev_requests';
    $user_id        = get_current_user_id();
    $is_admin       = current_user_can('administrator') || current_user_can('shop_manager');
    if ($is_admin) return 0;
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $comments c
         JOIN $requests_table r ON r.id = c.request_id
         WHERE r.created_by = %d AND c.is_admin = 1 AND c.is_read = 0",
        $user_id
    ));
}

// ── Email settings page ───────────────────────────────────
function bdr_settings_page()
{
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['bdr_save_settings'])) {
        check_admin_referer('bdr_settings');
        $opts = [
            'new_request_to_admin'    => isset($_POST['new_request_to_admin']),
            'status_change_to_staff'  => isset($_POST['status_change_to_staff']),
            'admin_comment_to_staff'  => isset($_POST['admin_comment_to_staff']),
            'staff_comment_to_admin'  => isset($_POST['staff_comment_to_admin']),
        ];
        update_option('bdr_email_settings', $opts);
        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }

    $opts = get_option('bdr_email_settings', []);
?>
    <div class="wrap">
        <h1 style="font-family:sans-serif;">Dev Requests — Email Settings</h1>
        <p style="color:#666; font-size:13px;">All notifications are off by default. Enable only what you need.</p>
        <form method="post">
            <?php wp_nonce_field('bdr_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">New request submitted</th>
                    <td>
                        <label>
                            <input type="checkbox" name="new_request_to_admin" <?php checked(!empty($opts['new_request_to_admin'])); ?> />
                            Email me (admin) when staff submit a new request
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Card moved</th>
                    <td>
                        <label>
                            <input type="checkbox" name="status_change_to_staff" <?php checked(!empty($opts['status_change_to_staff'])); ?> />
                            Email the requester when their card moves to a new column
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Admin posts a comment</th>
                    <td>
                        <label>
                            <input type="checkbox" name="admin_comment_to_staff" <?php checked(!empty($opts['admin_comment_to_staff'])); ?> />
                            Email the requester when I add a comment or question
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Staff replies to a comment</th>
                    <td>
                        <label>
                            <input type="checkbox" name="staff_comment_to_admin" <?php checked(!empty($opts['staff_comment_to_admin'])); ?> />
                            Email me (admin) when staff reply to a comment
                        </label>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Settings', 'primary', 'bdr_save_settings'); ?>
        </form>
    </div>
<?php
}

// ── Scripts & styles ──────────────────────────────────────
add_action('admin_enqueue_scripts', 'bdr_assets');
function bdr_assets($hook)
{
    if (strpos($hook, 'bdr-board') === false) return;
    wp_enqueue_style('bdr-style', BDR_URL . 'board.css', [], BDR_VERSION);
    wp_enqueue_script('bdr-script', BDR_URL . 'board.js', ['jquery'], BDR_VERSION, true);
    wp_localize_script('bdr-script', 'bdrData', [
        'ajax'    => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('bdr_nonce'),
        'isAdmin' => current_user_can('administrator') || current_user_can('shop_manager'),
        'userId'  => get_current_user_id(),
    ]);
}

// ── Main board page ───────────────────────────────────────
function bdr_board_page()
{
    global $wpdb;
    $table    = $wpdb->prefix . 'dev_requests';
    $comments = $wpdb->prefix . 'dev_request_comments';
    $is_admin = current_user_can('administrator') || current_user_can('shop_manager');
    $user_id  = get_current_user_id();

    $statuses = ['requested' => 'Requested', 'in-progress' => 'In Progress', 'complete' => 'Complete'];

    $where    = $is_admin ? '' : $wpdb->prepare('WHERE created_by = %d', $user_id);
    $requests = $wpdb->get_results("SELECT * FROM $table $where ORDER BY created_at DESC");

    $unread_map = $admin_unread_map = [];
    if (!empty($requests)) {
        $ids = implode(',', array_map('intval', array_column($requests, 'id')));
        if (!$is_admin) {
            $rows = $wpdb->get_results("SELECT request_id, COUNT(*) as cnt FROM $comments WHERE request_id IN ($ids) AND is_admin = 1 AND is_read = 0 GROUP BY request_id");
            foreach ($rows as $row) $unread_map[$row->request_id] = (int)$row->cnt;
        } else {
            $rows = $wpdb->get_results("SELECT request_id, COUNT(*) as cnt FROM $comments WHERE request_id IN ($ids) AND is_admin = 0 AND is_read = 0 GROUP BY request_id");
            foreach ($rows as $row) $admin_unread_map[$row->request_id] = (int)$row->cnt;
        }
    }

    $grouped = array_fill_keys(array_keys($statuses), []);
    foreach ($requests as $r) {
        if (isset($grouped[$r->status])) $grouped[$r->status][] = $r;
    }

    $priority_labels = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
?>
    <div class="bdr-wrap">
        <div class="bdr-topbar">
            <h1 class="bdr-title">Development Requests</h1>
            <button class="bdr-btn-new" id="bdr-open-modal">+ New Request</button>
        </div>
        <div class="bdr-board">
            <?php foreach ($statuses as $slug => $label) :
                $col_cards = $grouped[$slug];
                $count = count($col_cards); ?>
                <div class="bdr-column" data-status="<?php echo esc_attr($slug); ?>">
                    <div class="bdr-col-header">
                        <span class="bdr-col-title"><?php echo esc_html($label); ?></span>
                        <span class="bdr-col-count"><?php echo $count; ?></span>
                    </div>
                    <div class="bdr-cards">
                        <?php foreach ($col_cards as $req) :
                            $unread = $is_admin ? ($admin_unread_map[$req->id] ?? 0) : ($unread_map[$req->id] ?? 0);
                            $author = get_userdata($req->created_by);
                            $author_name = $author ? $author->display_name : 'Unknown';
                        ?>
                            <div class="bdr-card <?php echo $unread ? 'has-unread' : ''; ?>"
                                data-id="<?php echo esc_attr($req->id); ?>"
                                data-status="<?php echo esc_attr($req->status); ?>">
                                <?php if ($unread) : ?>
                                    <div class="bdr-unread-badge" title="<?php echo esc_attr($unread . ' unread comment' . ($unread > 1 ? 's' : '')); ?>">
                                        <?php echo $unread; ?> <?php echo $unread === 1 ? 'new reply' : 'new replies'; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="bdr-card-priority bdr-priority-<?php echo esc_attr($req->priority); ?>">
                                    <?php echo esc_html($priority_labels[$req->priority] ?? 'Normal'); ?>
                                </div>
                                <div class="bdr-card-title"><?php echo esc_html($req->title); ?></div>
                                <div class="bdr-card-meta">
                                    <?php if ($is_admin) : ?><span>By <?php echo esc_html($author_name); ?></span> · <?php endif; ?>
                                    <span><?php echo date('M j', strtotime($req->created_at)); ?></span>
                                </div>
                                <div class="bdr-card-actions">
                                    <button class="bdr-btn-view" data-id="<?php echo esc_attr($req->id); ?>">View</button>
                                    <?php if ($is_admin) : ?>
                                        <div class="bdr-move-btns">
                                            <?php foreach ($statuses as $s => $l) : ?>
                                                <?php if ($s !== $req->status) : ?>
                                                    <button class="bdr-btn-move" data-id="<?php echo esc_attr($req->id); ?>" data-status="<?php echo esc_attr($s); ?>">→ <?php echo esc_html($l); ?></button>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($col_cards)) : ?><div class="bdr-empty">No requests</div><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- New Request Modal -->
    <div class="bdr-modal-overlay" id="bdr-modal" style="display:none;">
        <div class="bdr-modal">
            <div class="bdr-modal-header">
                <h2>New Development Request</h2>
                <button class="bdr-modal-close" id="bdr-close-modal">✕</button>
            </div>
            <div class="bdr-modal-body">
                <label>Title <span class="req">*</span></label>
                <input type="text" id="bdr-title" placeholder="Brief description of what you need" maxlength="255" />
                <label>Priority</label>
                <select id="bdr-priority">
                    <option value="low">Low — whenever you get to it</option>
                    <option value="normal" selected>Normal</option>
                    <option value="high">High — this week if possible</option>
                    <option value="urgent">Urgent — ASAP</option>
                </select>
                <label>Details <span class="req">*</span></label>
                <textarea id="bdr-description" rows="6" placeholder="Describe what you need, why, and any relevant context..."></textarea>
                <div class="bdr-modal-footer">
                    <button class="bdr-btn-cancel" id="bdr-cancel">Cancel</button>
                    <button class="bdr-btn-submit" id="bdr-submit">Submit Request</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    <div class="bdr-modal-overlay" id="bdr-detail-modal" style="display:none;">
        <div class="bdr-modal bdr-modal-wide">
            <div class="bdr-modal-header">
                <h2 id="bdr-detail-title"></h2>
                <button class="bdr-modal-close" id="bdr-close-detail">✕</button>
            </div>
            <div class="bdr-modal-body">
                <div id="bdr-detail-meta" class="bdr-detail-meta"></div>
                <div id="bdr-detail-description" class="bdr-detail-description"></div>
                <div class="bdr-comments-section">
                    <h3>Comments</h3>
                    <div id="bdr-comments-list"></div>
                    <div class="bdr-comment-form">
                        <textarea id="bdr-new-comment" rows="3" placeholder="Add a comment or question..."></textarea>
                        <button class="bdr-btn-submit" id="bdr-post-comment">Post Comment</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
}

// ── AJAX: Submit new request ──────────────────────────────
add_action('wp_ajax_bdr_submit', 'bdr_ajax_submit');
function bdr_ajax_submit()
{
    check_ajax_referer('bdr_nonce', 'nonce');
    global $wpdb;

    $title       = sanitize_text_field($_POST['title'] ?? '');
    $description = sanitize_textarea_field($_POST['description'] ?? '');
    $priority    = in_array($_POST['priority'] ?? '', ['low', 'normal', 'high', 'urgent']) ? $_POST['priority'] : 'normal';

    if (!$title || !$description) wp_send_json_error('Missing fields.');

    $wpdb->insert($wpdb->prefix . 'dev_requests', [
        'title'       => $title,
        'description' => $description,
        'priority'    => $priority,
        'status'      => 'requested',
        'created_by'  => get_current_user_id(),
        'created_at'  => current_time('mysql'),
        'updated_at'  => current_time('mysql'),
    ]);

    if (bdr_email_enabled('new_request_to_admin')) {
        $submitter = wp_get_current_user();
        $admins    = get_users(['role__in' => ['administrator', 'shop_manager'], 'fields' => ['user_email']]);
        foreach ($admins as $admin) {
            wp_mail(
                $admin->user_email,
                'New Dev Request: ' . $title,
                "A new development request was submitted by {$submitter->display_name}.\n\nTitle: {$title}\n\nDetails:\n{$description}\n\nView it in the WordPress admin under Dev Requests."
            );
        }
    }

    wp_send_json_success(['id' => $wpdb->insert_id]);
}

// ── AJAX: Move card ───────────────────────────────────────
add_action('wp_ajax_bdr_move', 'bdr_ajax_move');
function bdr_ajax_move()
{
    check_ajax_referer('bdr_nonce', 'nonce');
    if (!current_user_can('administrator') && !current_user_can('shop_manager')) wp_send_json_error('Unauthorized.');

    global $wpdb;
    $id     = intval($_POST['id'] ?? 0);
    $status = sanitize_text_field($_POST['status'] ?? '');
    $valid  = ['requested', 'in-progress', 'complete'];

    if (!$id || !in_array($status, $valid)) wp_send_json_error('Invalid data.');

    $wpdb->update($wpdb->prefix . 'dev_requests', ['status' => $status, 'updated_at' => current_time('mysql')], ['id' => $id]);

    if (bdr_email_enabled('status_change_to_staff')) {
        $req    = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dev_requests WHERE id = %d", $id));
        $labels = ['requested' => 'Requested', 'in-progress' => 'In Progress', 'complete' => 'Complete'];
        if ($req) {
            $owner = get_userdata($req->created_by);
            if ($owner) {
                wp_mail(
                    $owner->user_email,
                    'Dev Request Update: ' . $req->title,
                    "Your development request \"{$req->title}\" has been moved to: {$labels[$status]}.\n\nLog in to the site admin to view details."
                );
            }
        }
    }

    wp_send_json_success();
}

// ── AJAX: Get request detail ──────────────────────────────
add_action('wp_ajax_bdr_get', 'bdr_ajax_get');
function bdr_ajax_get()
{
    check_ajax_referer('bdr_nonce', 'nonce');
    global $wpdb;

    $id       = intval($_POST['id'] ?? 0);
    $req      = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dev_requests WHERE id = %d", $id));
    if (!$req) wp_send_json_error('Not found.');

    $is_admin = current_user_can('administrator') || current_user_can('shop_manager');
    $user_id  = get_current_user_id();

    if (!$is_admin && (int)$req->created_by !== $user_id) wp_send_json_error('Unauthorized.');

    if ($is_admin) {
        $wpdb->update($wpdb->prefix . 'dev_request_comments', ['is_read' => 1], ['request_id' => $id, 'is_admin' => 0]);
    } else {
        $wpdb->update($wpdb->prefix . 'dev_request_comments', ['is_read' => 1], ['request_id' => $id, 'is_admin' => 1]);
    }

    $comments_raw = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}dev_request_comments WHERE request_id = %d ORDER BY created_at ASC",
        $id
    ));

    $comments = [];
    foreach ($comments_raw as $c) {
        $author     = get_userdata($c->user_id);
        $comments[] = [
            'id'         => $c->id,
            'comment'    => nl2br(esc_html($c->comment)),
            'author'     => $author ? $author->display_name : 'Unknown',
            'is_admin'   => (bool)$c->is_admin,
            'created_at' => date('M j, Y g:i a', strtotime($c->created_at)),
        ];
    }

    $author = get_userdata($req->created_by);
    wp_send_json_success([
        'id'          => $req->id,
        'title'       => $req->title,
        'description' => nl2br(esc_html($req->description)),
        'status'      => $req->status,
        'priority'    => $req->priority,
        'created_by'  => $author ? $author->display_name : 'Unknown',
        'created_at'  => date('M j, Y', strtotime($req->created_at)),
        'comments'    => $comments,
        'is_admin'    => $is_admin,
    ]);
}

// ── AJAX: Post comment ────────────────────────────────────
add_action('wp_ajax_bdr_comment', 'bdr_ajax_comment');
function bdr_ajax_comment()
{
    check_ajax_referer('bdr_nonce', 'nonce');
    global $wpdb;

    $id       = intval($_POST['request_id'] ?? 0);
    $comment  = sanitize_textarea_field($_POST['comment'] ?? '');
    $user_id  = get_current_user_id();
    $is_admin = (current_user_can('administrator') || current_user_can('shop_manager')) ? 1 : 0;

    if (!$id || !$comment) wp_send_json_error('Missing data.');

    $req = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dev_requests WHERE id = %d", $id));
    if (!$req) wp_send_json_error('Not found.');
    if (!$is_admin && (int)$req->created_by !== $user_id) wp_send_json_error('Unauthorized.');

    $wpdb->insert($wpdb->prefix . 'dev_request_comments', [
        'request_id' => $id,
        'user_id'    => $user_id,
        'comment'    => $comment,
        'is_admin'   => $is_admin,
        'is_read'    => 0,
        'created_at' => current_time('mysql'),
    ]);

    $commenter = wp_get_current_user();
    if ($is_admin && bdr_email_enabled('admin_comment_to_staff')) {
        $owner = get_userdata($req->created_by);
        if ($owner) {
            wp_mail(
                $owner->user_email,
                'Question on your Dev Request: ' . $req->title,
                "{$commenter->display_name} has a question about your request \"{$req->title}\":\n\n{$comment}\n\nLog in to the WP admin under Dev Requests to reply."
            );
        }
    } elseif (!$is_admin && bdr_email_enabled('staff_comment_to_admin')) {
        $admins = get_users(['role__in' => ['administrator', 'shop_manager'], 'fields' => ['user_email']]);
        foreach ($admins as $admin) {
            wp_mail(
                $admin->user_email,
                'Reply on Dev Request: ' . $req->title,
                "{$commenter->display_name} replied to the request \"{$req->title}\":\n\n{$comment}"
            );
        }
    }

    $author = get_userdata($user_id);
    wp_send_json_success([
        'comment'    => nl2br(esc_html($comment)),
        'author'     => $author ? $author->display_name : 'Unknown',
        'is_admin'   => (bool)$is_admin,
        'created_at' => date('M j, Y g:i a', strtotime(current_time('mysql'))),
    ]);
}
