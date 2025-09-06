<?php
/**
 * Plugin Name:       Elementor AI Assistant
 * Plugin URI:        https://yukdigitalz.com/
 * Description:       Sebuah addon Elementor untuk membuat desain website menggunakan AI berdasarkan prompt.
 * Version:           3.0.0
 * Author:            Shihela
 * Author URI:        https://yukdigitalz.com/
 * Text Domain:       elementor-ai-assistant
 * Elementor tested up to: 3.23.0
 */

if (!defined('ABSPATH')) {
    exit; // Mencegah akses file secara langsung
}

final class Elementor_AI_Assistant
{
    const VERSION = '3.0.0';
    const MINIMUM_ELEMENTOR_VERSION = '3.5.0';
    const MINIMUM_PHP_VERSION = '7.4';

    private static ?Elementor_AI_Assistant $_instance = null;

    public static function instance(): Elementor_AI_Assistant
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init(): void
    {
        if (!$this->is_dependencies_met()) {
            // Anda bisa menambahkan notifikasi admin di sini jika diperlukan
            return;
        }
        $this->register_hooks();
    }

    private function is_dependencies_met(): bool
    {
        return defined('ELEMENTOR_VERSION') &&
            version_compare(ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION, '>=') &&
            version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '>=');
    }

    private function register_hooks(): void
    {
        // Hooks untuk Elementor Editor
        add_action('elementor/elements/categories_registered', [$this, 'register_widget_categories']);
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'enqueue_editor_scripts']);
        add_action('elementor/editor/after_enqueue_styles', [$this, 'enqueue_editor_styles']);

        // Hooks untuk Halaman Admin & Pengaturan
        add_action('admin_menu', [$this, 'create_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        // Hooks untuk AJAX
        add_action('wp_ajax_eai_generate_design', [$this, 'handle_ajax_generate_design']);
        add_action('wp_ajax_eai_activate_license', [$this, 'handle_ajax_activate_license']);
        add_action('wp_ajax_eai_deactivate_license', [$this, 'handle_ajax_deactivate_license']);
    }
    
    //======================================================================
    // 1. FUNGSI UNTUK EDITOR ELEMENTOR
    //======================================================================

    public function register_widget_categories(\Elementor\Elements_Manager $elements_manager): void
    {
        $elements_manager->add_category(
            'ai-assistant-category',
            ['title' => esc_html__('AI Assistant', 'elementor-ai-assistant'), 'icon' => 'eicon-magic-wand']
        );
    }

    public function register_widgets(\Elementor\Widgets_Manager $widgets_manager): void
    {
        require_once plugin_dir_path(__FILE__) . 'includes/widgets/ai-generator-widget.php';
        $widgets_manager->register(new \Elementor_AI_Generator_Widget());
    }

    public function enqueue_editor_scripts(): void
    {
        wp_enqueue_script('eai-editor-js', plugin_dir_url(__FILE__) . 'assets/js/editor.js', ['elementor-editor', 'jquery'], self::VERSION, true);
        wp_localize_script('eai-editor-js', 'eai_ajax_object', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('eai_ajax_nonce')]);
    }

    public function enqueue_editor_styles(): void
    {
        wp_enqueue_style('eai-editor-css', plugin_dir_url(__FILE__) . 'assets/css/editor.css', [], self::VERSION);
    }

    //======================================================================
    // 2. FUNGSI UNTUK HALAMAN PENGATURAN & LISENSI
    //======================================================================

    public function create_admin_menu(): void
    {
        add_options_page('AI Assistant Settings', 'AI Assistant', 'manage_options', 'eai-settings-page', [$this, 'settings_page_html']);
    }
    
    public function settings_page_html(): void
    {
        if (!current_user_can('manage_options')) { return; }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p>Konfigurasi lisensi plugin dan koneksi ke API Artificial Intelligence.</p>

            <h2 class="nav-tab-wrapper eai-nav-tab-wrapper">
                <a href="#license" class="nav-tab">Plugin License</a>
                <a href="#api" class="nav-tab">AI API Settings</a>
            </h2>

            <form action="options.php" method="post">
                <?php settings_fields('eai_settings_group'); ?>

                <div id="tab-content-license" class="eai-settings-tab-content">
                    <?php do_settings_sections('eai-settings-license'); ?>
                </div>

                <div id="tab-content-api" class="eai-settings-tab-content">
                    <?php do_settings_sections('eai-settings-api'); ?>
                </div>
                
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }

    public function register_settings(): void
    {
        // Grup utama tetap sama
        register_setting('eai_settings_group', 'eai_gemini_api_key');
        register_setting('eai_settings_group', 'eai_license_key');
        register_setting('eai_settings_group', 'eai_customer_api_key');
        register_setting('eai_settings_group', 'eai_license_status');

        // Section dan Field untuk Tab Lisensi
        add_settings_section('eai_license_section', 'Plugin License Activation', null, 'eai-settings-license');
        add_settings_field('eai_license_key_field', 'License & API Key', [$this, 'license_key_field_html'], 'eai-settings-license', 'eai_license_section');

        // Section dan Field untuk Tab AI API
        add_settings_section('eai_api_section', 'AI API Configuration', null, 'eai-settings-api');
        add_settings_field('eai_gemini_api_key_field', 'Google AI (Gemini) API Key', [$this, 'api_key_field_html'], 'eai-settings-api', 'eai_api_section');
    }

    public function api_key_field_html(): void
    {
        $api_key = get_option('eai_gemini_api_key');
        ?>
        <input type="password" name="eai_gemini_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" style="width: 50em;">
        <p class="description">Enter your Google AI (Gemini) API key from Google AI Studio.</p>
        <?php
    }

    public function license_key_field_html(): void
    {
        $license_key = get_option('eai_license_key');
        $api_key     = get_option('eai_customer_api_key');
        $status      = get_option('eai_license_status');
        ?>
        <div style="margin-bottom: 15px;">
            <label for="eai_license_key" style="display:block; font-weight:bold;">License Key</label>
            <input type="text" id="eai_license_key" name="eai_license_key" value="<?php echo esc_attr($license_key); ?>" class="regular-text" <?php echo ($status === 'valid') ? 'disabled' : ''; ?>>
        </div>
        <div style="margin-bottom: 15px;">
            <label for="eai_customer_api_key" style="display:block; font-weight:bold;">Your API Key</label>
            <input type="text" id="eai_customer_api_key" name="eai_customer_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" <?php echo ($status === 'valid') ? 'disabled' : ''; ?>>
        </div>

        <?php if ($status === 'valid'): ?>
            <button type="button" class="button button-secondary" id="eai_deactivate_license_btn">Deactivate License</button>
        <?php else: ?>
            <button type="button" class="button button-primary" id="eai_activate_license_btn">Activate License</button>
        <?php endif; ?>
        
        <div id="eai_license_feedback" style="margin-top: 10px;">
            <?php if ($status === 'valid'): ?>
                <p style="color: green;">License is active.</p>
            <?php elseif (!empty($status)): ?>
                <p style="color: red;">License Error: <?php echo esc_html($status); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function enqueue_admin_scripts($hook_suffix): void
    {
        if ('settings_page_eai-settings-page' !== $hook_suffix) {
            return;
        }
        wp_enqueue_script('eai-admin-js', plugin_dir_url(__FILE__) . 'assets/js/admin.js', ['jquery'], self::VERSION, true);
        wp_enqueue_style('eai-admin-css', plugin_dir_url(__FILE__) . 'assets/css/admin.css', [], self::VERSION);
        wp_add_inline_script('eai-admin-js', 'const eai_nonce = "' . wp_create_nonce('eai_license_nonce') . '";', 'before');
    }
    
    //======================================================================
    // 3. FUNGSI HANDLER AJAX
    //======================================================================

    public function handle_ajax_generate_design(): void
    {
        if (get_option('eai_license_status') !== 'valid') {
            wp_send_json_error(['message' => 'Please activate your license to use this feature.']);
        }
        
        check_ajax_referer('eai_ajax_nonce', 'security');

        // Menerima riwayat percakapan sebagai string JSON
        $conversation_history_json = isset($_POST['conversation_history']) ? stripslashes($_POST['conversation_history']) : '';
        if (empty($conversation_history_json)) {
            wp_send_json_error(['message' => 'Error: Conversation history is empty.']);
        }
        
        // Decode string JSON menjadi array PHP
        $contents = json_decode($conversation_history_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => 'Error: Invalid conversation history format.']);
        }

        $api_key = get_option('eai_gemini_api_key');
        if (empty($api_key)) { wp_send_json_error(['message' => 'Error: Google AI API Key is not set.']); }

        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $api_key;
        $headers = ['Content-Type' => 'application/json'];
        
        // Body sekarang berisi seluruh riwayat percakapan
        $body = ['contents' => $contents];

        $response = wp_remote_post($api_url, ['headers' => $headers, 'body' => json_encode($body), 'timeout' => 30]);

        if (is_wp_error($response)) { wp_send_json_error(['message' => 'API Request Failed: ' . $response->get_error_message()]); }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_body['error'])) { wp_send_json_error(['message' => 'API Error: ' . $response_body['error']['message']]); }
        
        if (!isset($response_body['candidates'][0]['content']['parts'][0]['text'])) {
            error_log('Unexpected Gemini API response: ' . print_r($response_body, true));
            wp_send_json_error(['message' => 'Error: Received an unexpected format from the API.']);
        }
        
        $ai_message = $response_body['candidates'][0]['content']['parts'][0]['text'];
        wp_send_json_success(['message' => trim($ai_message)]);
    }

    public function handle_ajax_activate_license(): void
    {
        check_ajax_referer('eai_license_nonce', 'nonce');
    
        $license_key = sanitize_text_field($_POST['license_key']);
        $api_key     = sanitize_text_field($_POST['api_key']);

        if (empty($license_key) || empty($api_key)) {
            wp_send_json_error(['message' => 'License Key and API Key are required.']);
        }
    
        $api_url = 'https://project.yukdigitalz.com/marketplace/wp-json/wclm/v2/action';
        $headers = ['X-License-Key' => $license_key, 'X-API-Key' => $api_key];
        $body = ['action' => 'activate', 'site_url' => home_url()];
    
        $response = wp_remote_post($api_url, ['headers' => $headers, 'body' => $body, 'timeout' => 20]);
    
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Connection error: ' . $response->get_error_message()]);
        }
    
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code === 200 && isset($response_body['success']) && $response_body['success'] === true) {
            update_option('eai_license_status', 'valid');
            update_option('eai_license_key', $license_key);
            update_option('eai_customer_api_key', $api_key);
            wp_send_json_success(['message' => 'License activated successfully!']);
        } else {
            $error_message = $response_body['message'] ?? ($response_body['error'] ?? 'Failed to activate.');
            update_option('eai_license_status', $error_message);
            wp_send_json_error(['message' => esc_html($error_message)]);
        }
    }

    public function handle_ajax_deactivate_license(): void
    {
        check_ajax_referer('eai_license_nonce', 'nonce');
    
        $license_key = get_option('eai_license_key');
        $api_key     = get_option('eai_customer_api_key');
        
        if (empty($license_key) || empty($api_key)) {
            wp_send_json_error(['message' => 'No license found to deactivate.']);
        }
    
        $api_url = 'https://project.yukdigitalz.com/marketplace/wp-json/wclm/v2/action';
        $headers = ['X-License-Key' => $license_key, 'X-API-Key' => $api_key];
        $body = ['action' => 'deactivate', 'site_url' => home_url()];
    
        wp_remote_post($api_url, ['headers' => $headers, 'body' => $body, 'timeout' => 20]);
    
        delete_option('eai_license_status');
        delete_option('eai_license_key');
        delete_option('eai_customer_api_key');
    
        wp_send_json_success(['message' => 'License has been deactivated.']);
    }
}

Elementor_AI_Assistant::instance();