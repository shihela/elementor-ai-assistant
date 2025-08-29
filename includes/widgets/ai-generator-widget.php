<?php
if (!defined('ABSPATH')) { exit; }

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Elementor_AI_Generator_Widget extends Widget_Base
{
    public function get_name(): string { return 'ai-generator'; }
    public function get_title(): string { return esc_html__('AI Generator', 'elementor-ai-assistant'); }
    public function get_icon(): string { return 'eicon-ai'; }
    public function get_categories(): array { return ['ai-assistant-category']; }
    public function get_keywords(): array { return ['ai', 'generator', 'assistant', 'otomatis']; }

    protected function _register_controls(): void
    {
        $this->start_controls_section('eai_section_prompt', ['label' => esc_html__('AI Prompt', 'elementor-ai-assistant'), 'tab' => Controls_Manager::TAB_CONTENT]);
        $this->add_control('eai_prompt', ['label' => esc_html__('Your Prompt', 'elementor-ai-assistant'), 'type' => Controls_Manager::TEXTAREA, 'rows' => 10, 'label_block' => true]);
        $this->add_control('eai_generate_button', ['type' => Controls_Manager::BUTTON, 'text' => esc_html__('Generate Design', 'elementor-ai-assistant'), 'event' => 'eai_assistant:generate']);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        printf('<div class="eai-generator-widget-wrapper"><p>%s</p></div>', esc_html__('Enter your prompt and click "Generate Design".', 'elementor-ai-assistant'));
    }
}