/**
 * Script untuk menangani interaksi widget AI Assistant di dalam editor Elementor.
 * @since 2.5.1 (Final Listener Fix)
 */
(function ($) {
    'use strict';

    const aiAssistantEditor = {
        /**
         * Inisialisasi script utama.
         */
        init: function () {
            if (typeof elementor === 'undefined') { return; }

            // Listener untuk tombol Generate, ini selalu aman.
            elementor.channels.editor.on('eai_assistant:generate', this.handleGenerateClick);

            // --- PERBAIKAN UTAMA: Memasang dan memasang ulang listener dengan cerdas ---
            // Saat pratinjau pertama kali dimuat
            elementor.on('preview:loaded', () => this.attachCopyListener());
            // Saat terjadi perubahan besar pada editor yang mungkin me-reload iframe
            elementor.channels.editor.on('change', () => this.attachCopyListener());
        },

        /**
         * Memasang listener untuk tombol Salin Teks di dalam iframe pratinjau.
         * Ini adalah fungsi kunci untuk mencegah listener ganda atau salah target.
         */
        attachCopyListener: function() {
            const $previewIframe = $('#elementor-preview-iframe');
            if ($previewIframe.length) {
                const $iframeContents = $previewIframe.contents();
                // Gunakan .off() dulu untuk menghapus listener lama sebelum memasang yang baru.
                // Ini mencegah penumpukan event listener yang bisa menyebabkan masalah.
                $iframeContents.off('click.aiAssistant').on('click.aiAssistant', '.eai-copy-button', this.handleCopyClick);
            }
        },

        /**
         * Menangani event klik pada tombol "Generate Desain".
         */
        handleGenerateClick: function () {
            try {
                const $textarea = $('.elementor-control-eai_prompt textarea');
                if (!$textarea.length) { throw new Error('Could not find prompt textarea.'); }
                const promptText = $textarea.val();

                if (!aiAssistantEditor.isPromptValid(promptText)) {
                    aiAssistantEditor.showNotification('error', 'Prompt cannot be empty!');
                    return;
                }
                
                const widgetModel = elementor.getPanelView().getCurrentPageView().model;
                if (!widgetModel) { throw new Error('Could not get widget model.'); }
                const widgetId = widgetModel.get('id');
                const $previewIframe = $('#elementor-preview-iframe').contents();
                const $widgetElement = $previewIframe.find(`[data-id="${widgetId}"]`);

                if (!$widgetElement.length) { throw new Error('Could not find widget element in canvas.'); }

                aiAssistantEditor.setLoadingState($widgetElement, true);
                aiAssistantEditor.sendRequestToBackend(promptText, $widgetElement);
            } catch (error) {
                console.error("AI Assistant Error:", error);
                aiAssistantEditor.showNotification('error', 'A critical JavaScript error occurred.');
            }
        },

        // --- Sisa fungsi (handleCopyClick, sendRequestToBackend, dll.) tidak ada perubahan ---
        handleCopyClick: function(event) {
            const $button = $(event.currentTarget);
            const $wrapper = $button.closest('.eai-response-wrapper');
            const textToCopy = $wrapper.find('pre').text();
            if (textToCopy && navigator.clipboard) {
                navigator.clipboard.writeText(textToCopy).then(() => {
                    const originalText = 'Salin Teks';
                    $button.text('Tersalin!').addClass('copied');
                    setTimeout(() => { $button.text(originalText).removeClass('copied'); }, 2000);
                });
            }
        },

        sendRequestToBackend: function (prompt, $widgetElement) {
            $.ajax({
                url: eai_ajax_object.ajax_url,
                type: 'POST',
                data: { action: 'eai_generate_design', prompt: prompt, security: eai_ajax_object.nonce },
                success: function (response) {
                    if (response.success) {
                        aiAssistantEditor.displayResult($widgetElement, response.data.message);
                    } else {
                        aiAssistantEditor.displayError($widgetElement, response.data.message);
                    }
                },
                error: function (xhr) {
                    aiAssistantEditor.displayError($widgetElement, 'Failed to communicate with the server.');
                }
            });
        },
        
        isPromptValid: function (prompt) { return prompt && prompt.trim() !== ''; },
        setLoadingState: function ($widgetElement, isLoading) {
            const $wrapper = $widgetElement.find('.eai-generator-widget-wrapper');
            if (isLoading) { $wrapper.html('<h3><span class="eicon-loading eicon-animation-spin"></span> Generating...</h3>'); }
        },
        displayResult: function ($widgetElement, result) {
            const $wrapper = $widgetElement.find('.eai-generator-widget-wrapper');
            const resultHTML = `<div class="eai-response-wrapper"><button class="eai-copy-button">Salin Teks</button><h4>AI Response:</h4><pre>${result}</pre></div>`;
            $wrapper.html(resultHTML);
        },
        displayError: function ($widgetElement, message) {
            const $wrapper = $widgetElement.find('.eai-generator-widget-wrapper');
            $wrapper.html(`<p style="color: red;"><b>Error:</b> ${message}</p>`);
        },
        showNotification: function (type, message) {
            if (typeof elementor.notifications !== 'undefined') {
                elementor.notifications.showToast({ message: message });
            }
        },
    };

    $(window).on('elementor:init', () => aiAssistantEditor.init());

})(jQuery);