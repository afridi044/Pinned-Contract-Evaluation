<?php

declare(strict_types=1);

/**
 * Add New User network administration panel.
 *
 * @package WordPress
 * @subpackage Multisite
 * @since 3.1.0
 */

/** Load WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if (!is_multisite()) {
    wp_die(__('Multisite support is not enabled.'));
}

if (!current_user_can('create_users')) {
    wp_die(__('You do not have sufficient permissions to add users to this network.'));
}

get_current_screen()->add_help_tab([
    'id'      => 'overview',
    'title'   => __('Overview'),
    'content' => '<p>' . __('Add User will set up a new user account on the network and send that person an email with username and password.') . '</p>' .
                 '<p>' . __('Users who are signed up to the network without a site are added as subscribers to the main or primary dashboard site, giving them profile pages to manage their accounts. These users will only see Dashboard and My Sites in the main navigation until a site is created for them.') . '</p>',
]);

get_current_screen()->set_help_sidebar(
    '<p><strong>' . __('For more information:') . '</strong></p>' .
    '<p>' . __('<a href="https://codex.wordpress.org/Network_Admin_Users_Screen" target="_blank">Documentation on Network Users</a>') . '</p>' .
    '<p>' . __('<a href="https://wordpress.org/support/forum/multisite/" target="_blank">Support Forums</a>') . '</p>'
);

/** @var WP_Error|null $add_user_errors */
$add_user_errors = null;

if (($_REQUEST['action'] ?? '') === 'add-user') {
    check_admin_referer('add-user', '_wpnonce_add-user');

    if (!current_user_can('manage_network_users')) {
        wp_die(__('You do not have permission to access this page.'));
    }

    $user_data = $_POST['user'] ?? null;
    if (!is_array($user_data)) {
        wp_die(__('Cannot create an empty user.'));
    }

    $username = $user_data['username'] ?? '';
    $email    = $user_data['email'] ?? '';

    $validation_result = wpmu_validate_user_signup($username, $email);
    $validation_errors = $validation_result['errors'];

    if ($validation_errors->has_errors()) {
        $add_user_errors = $validation_errors;
    } else {
        $password = wp_generate_password(length: 12, special_chars: false);
        $user_id  = wpmu_create_user(
            username: strtolower($validation_result['user_name']),
            password: $password,
            email: $validation_result['user_email']
        );

        if (!$user_id || is_wp_error($user_id)) {
            $add_user_errors = is_wp_error($user_id)
                ? $user_id
                : new WP_Error('add_user_fail', __('Cannot add user.'));
        } else {
            wp_new_user_notification(user_id: $user_id, plaintext_pass: $password);
            wp_redirect(add_query_arg(['update' => 'added'], 'user-new.php'));
            exit;
        }
    }
}

$messages = [];
if (($_GET['update'] ?? '') === 'added') {
    $messages[] = __('User added.');
}

$title       = __('Add New User');
$parent_file = 'users.php';

require ABSPATH . 'wp-admin/admin-header.php';
?>

<div class="wrap">
<h1 id="add-new-user"><?php _e('Add New User'); ?></h1>
<?php
if (!empty($messages)) {
    foreach ($messages as $msg) {
        printf(
            '<div id="message" class="updated notice is-dismissible"><p>%s</p></div>',
            esc_html($msg)
        );
    }
}

if ($add_user_errors?->has_errors()) : ?>
    <div class="error">
        <?php foreach ($add_user_errors->get_error_messages() as $message) : ?>
            <p><?= esc_html($message) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

    <form action="<?php echo network_admin_url('user-new.php?action=add-user'); ?>" id="adduser" method="post" novalidate="novalidate">
    <table class="form-table" role="presentation">
        <tr class="form-field form-required">
            <th scope="row"><label for="username"><?php _e('Username'); ?></label></th>
            <td><input type="text" id="username" class="regular-text" name="user[username]" required aria-required="true" autocapitalize="none" autocorrect="off" maxlength="60" /></td>
        </tr>
        <tr class="form-field form-required">
            <th scope="row"><label for="email"><?php _e('Email'); ?></label></th>
            <td><input type="email" id="email" class="regular-text" name="user[email]" required aria-required="true" /></td>
        </tr>
        <tr class="form-field">
            <td colspan="2"><?php _e('A link to set a new password will be mailed to the user at the above email address.'); ?></td>
        </tr>
    </table>
    <?php wp_nonce_field('add-user', '_wpnonce_add-user'); ?>
    <?php submit_button(__('Add User'), 'primary', 'add-user'); ?>
    </form>
</div>
<?php
require ABSPATH . 'wp-admin/admin-footer.php';