<?php
/**
 * Plugin Name: Ayaoriko Basic Auth for Free
 * Description: 非ログインユーザーを対象にBasic認証を設定します。管理画面でID/Passの変更や認証失敗時のメッセージの変更ができます。
 * Plugin URI: https://ayaoriko.com/coding/wordpress/ayaoriko-basic-auth/
 * Author: あやおり子@ayaoriko
 * Author URI: https://ayaoriko.com/
 * Version: 1.0.1
 */

// 管理画面に設定ページを追加
function basic_auth_add_settings_menu() {
    add_management_page(
        'Basic認証設定',
        'Basic認証設定',
        'manage_options',
        'basic-auth-settings',
        'basic_auth_settings_page'
    );
}
add_action('admin_menu', 'basic_auth_add_settings_menu');

// 設定ページの表示
function basic_auth_settings_page() {
    ?>
    <div class="wrap">
        <h1>Basic認証設定</h1>
        <?php
        // 保存後のメッセージ表示
        if (isset($_GET['settings-updated'])) {
            if ($_GET['settings-updated'] == 'true') {
                // 保存成功時のメッセージを追加
                add_settings_error('basic_auth_messages', 'basic_auth_message', '設定を保存しました。', 'updated');
            } else {
                // 保存失敗時のメッセージを追加
                add_settings_error('basic_auth_messages', 'basic_auth_message', '設定の保存に失敗しました。', 'error');
            }
        }

        // メッセージを表示
        settings_errors('basic_auth_messages');
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('basic-auth-settings-group');
            do_settings_sections('basic-auth-settings-group');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">ユーザー名</th>
                    <td>
                        <input type="text" id="basic_auth_username" name="basic_auth_username" value="<?php echo esc_attr(get_option('basic_auth_username')); ?>" />
                    </td>
                </tr>
                
                <tr valign="top">
                    <th scope="row">パスワード</th>
                    <td>
                        <input type="password" id="basic_auth_password" name="basic_auth_password" value="<?php echo esc_attr(get_option('basic_auth_password')); ?>" />
                        <button type="button" onclick="basic_auth_toggle_password_visibility()">表示/非表示</button>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">認証失敗時のメッセージ</th>
                    <td>
                        <textarea name="basic_auth_failure_message" rows="5" cols="50"><?php echo esc_textarea(get_option('basic_auth_failure_message', 'ログインに失敗しました。')); ?></textarea>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>

        <style id="basic-auth-admin">
            /* ここにCSSを追記してください */
        </style>
    </div>

    <script>
    function basic_auth_toggle_password_visibility() {
        var passwordField = document.getElementById("basic_auth_password");
        if (passwordField.type === "password") {
            passwordField.type = "text";
        } else {
            passwordField.type = "password";
        }
    }
    // エンターキーでのフォーム送信を無効化
    document.getElementById("basic_auth_form").addEventListener("keydown", function(event) {
        if (event.key === "Enter") {
            event.preventDefault();
        }
    });
    
        // 保存成功/失敗後にアラートを表示
    document.addEventListener('DOMContentLoaded', function() {
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('settings-updated')) {
            if (urlParams.get('settings-updated') === 'true') {
                alert('設定が保存されました');
            } else {
                alert('設定の保存に失敗しました');
            }
        }
    });
    </script>
    <?php
}

// 設定を登録
function basic_auth_register_settings() {
    register_setting('basic-auth-settings-group', 'basic_auth_username');
    register_setting('basic-auth-settings-group', 'basic_auth_password');
    register_setting('basic-auth-settings-group', 'basic_auth_failure_message');
}
add_action('admin_init', 'basic_auth_register_settings');

// Basic認証の無効化ロジック
function basic_auth_disable_for_logged_in_users() {
    // wp-login.phpや管理画面へのアクセスを除外
    if (strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false || strpos($_SERVER['REQUEST_URI'], '/wp-admin/') !== false) {
        return;
    }

    // ユーザーがログインしているかどうかを確認
    if (is_user_logged_in()) {
        return; // ユーザーがログインしている場合はBasic認証をスキップ
    }

    // Basic認証のユーザー名とパスワードを取得
    $username = get_option('basic_auth_username');
    $password = get_option('basic_auth_password');

    // ユーザー名またはパスワードが空の場合はBasic認証をスキップ
    if (empty($username) || empty($password)) {
        return; // ユーザー名とパスワードが空の場合は認証をスキップ
    }

    // 認証失敗時のカスタムメッセージを取得
    $failure_message = get_option('basic_auth_failure_message', 'ログインに失敗しました。');

    // ヘッダーから認証情報を取得
    if (!isset($_SERVER['PHP_AUTH_USER']) || 
        $_SERVER['PHP_AUTH_USER'] !== $username || 
        $_SERVER['PHP_AUTH_PW'] !== $password) {
        
        // 認証失敗時にヘッダーを設定
        header('WWW-Authenticate: Basic realm="Restricted Area"');
        header('HTTP/1.0 401 Unauthorized');
        echo $failure_message; // カスタムメッセージを表示
        exit;
    }
}
add_action('init', 'basic_auth_disable_for_logged_in_users');

// 設定ページにクラスを追加（保存ボタン非表示用）
function basic_auth_add_settings_page_class($classes) {
    $screen = get_current_screen();
    if ($screen->id === 'settings_page_basic-auth-settings') {
        $classes .= ' basic-auth-settings-page';
    }
    return $classes;
}
add_filter('admin_body_class', 'basic_auth_add_settings_page_class');

// 更新確認ロジック
require_once plugin_dir_path(__FILE__) . 'updater/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://ayaoriko.com/plugin-update-file/ayaoriko-basic-auth-for-free/version.json',
    __FILE__,
    'ayaoriko-basic-auth-for-free'
);
