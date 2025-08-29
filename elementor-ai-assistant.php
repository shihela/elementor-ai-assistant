<?php
/**
 * Plugin Name:       Elementor AI Assistant
 * Description:       Sebuah addon Elementor untuk membuat desain website menggunakan AI berdasarkan prompt.
 * Version:           1.0.0
 * Author:            Shihela
 * Text Domain:       elementor-ai-assistant
 * Elementor tested up to: 3.23.0
 */

if (!defined('ABSPATH')) {
    exit; // Mencegah akses file secara langsung
}

final class Elementor_AI_Assistant
{
    const VERSION = '2.0.1';
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
        add_action('elementor/elements/categories_registered', [$this, 'register_widget_categories']);
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'enqueue_editor_scripts']);
        add_action('wp_ajax_eai_generate_design', [$this, 'handle_ajax_generate_design']);
        add_action('elementor/editor/after_enqueue_styles', [$this, 'enqueue_editor_styles']);
        
        // Hooks untuk halaman pengaturan
        add_action('admin_menu', [$this, 'create_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

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
        wp_enqueue_script('ai-assistant-editor-js', plugin_dir_url(__FILE__) . 'assets/js/editor.js', ['elementor-editor', 'jquery'], self::VERSION, true);
        wp_localize_script('ai-assistant-editor-js', 'eai_ajax_object', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('eai_ajax_nonce')]);
    }

    // Letakkan fungsi ini di dalam kelas
    public function enqueue_editor_styles(): void
    {
        wp_enqueue_style(
            'ai-assistant-editor-css',
            plugin_dir_url(__FILE__) . 'assets/css/editor.css',
            [], // Tidak ada dependensi
            self::VERSION
        );
    }

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
            <form action="options.php" method="post">
                <?php
                settings_fields('eai_settings_group');
                do_settings_sections('eai-settings-page');
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }

    public function register_settings(): void
    {
        register_setting('eai_settings_group', 'eai_gemini_api_key', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '']);
        add_settings_section('eai_settings_section', 'API Configuration', null, 'eai-settings-page');
        add_settings_field('eai_gemini_api_key_field', 'Google AI (Gemini) API Key', [$this, 'api_key_field_html'], 'eai-settings-page', 'eai_settings_section');
    }

    public function api_key_field_html(): void
    {
        $api_key = get_option('eai_gemini_api_key');
        ?>
        <input type="password" name="eai_gemini_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" style="width: 50em;">
        <p class="description">Enter your Google AI (Gemini) API key from Google AI Studio.</p>
        <?php
    }

    public function handle_ajax_generate_design(): void
    {
        check_ajax_referer('eai_ajax_nonce', 'security');

        $prompt = isset($_POST['prompt']) ? sanitize_textarea_field($_POST['prompt']) : '';
        if (empty($prompt)) { wp_send_json_error(['message' => 'Error: Prompt is empty.']); }

        $api_key = get_option('eai_gemini_api_key');
        if (empty($api_key)) { wp_send_json_error(['message' => 'Error: Google AI API Key is not set in Settings > AI Assistant.']); }

        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $api_key;
        $headers = ['Content-Type' => 'application/json'];
        $body = ['contents' => [['parts' => [['text' => 'You are a helpful website design assistant. Provide a concise and creative answer for: ' . $prompt]]]]];

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
}

Elementor_AI_Assistant::instance();