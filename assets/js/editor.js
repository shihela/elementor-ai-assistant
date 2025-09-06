/**
 * Script untuk menangani interaksi widget AI Assistant.
 * @since 3.2.1 (Final Modal Listener Fix)
 */
(function ($) {
    'use strict';

    const aiAssistantEditor = {
        conversationHistory: {}, 

        init: function () {
            if (typeof elementor === 'undefined') { return; }
            elementor.channels.editor.on('eai_assistant:generate', this.handleGenerateClick);
            elementor.on('preview:loaded', () => this.attachIframeListeners());
        },

        attachIframeListeners: function() {
            const $previewIframe = $('#elementor-preview-iframe');
            if (!$previewIframe.length) return;

            const $iframeBody = $previewIframe.contents().find('body');
            $iframeBody.off('.aiAssistant');
            $iframeBody.on('submit.aiAssistant', '.eai-follow-up-form', this.handleFollowUpSubmit);
            $iframeBody.on('click.aiAssistant', '.eai-view-copy-button', this.openModal);
        },
        
        handleGenerateClick: function () {
            try {
                const widgetModel = elementor.getPanelView().getCurrentPageView().model;
                if (!widgetModel) { throw new Error('Could not get widget model.'); }
                const widgetId = widgetModel.get('id');
                const promptText = widgetModel.get('settings').get('eai_prompt');

                if (!aiAssistantEditor.isPromptValid(promptText)) {
                    aiAssistantEditor.showNotification('error', 'Initial prompt cannot be empty!');
                    return;
                }
                
                aiAssistantEditor.conversationHistory[widgetId] = [{'role': 'user', 'parts': [{'text': promptText}]}];
                const $widgetElement = aiAssistantEditor.getWidgetElement(widgetId);
                if (!$widgetElement) { return; }

                aiAssistantEditor.setLoadingState($widgetElement, true);
                aiAssistantEditor.sendRequestToBackend(widgetId, $widgetElement);
            } catch (error) {
                console.error("AI Assistant Error:", error);
                aiAssistantEditor.showNotification('error', 'A critical JavaScript error occurred.');
            }
        },

        handleFollowUpSubmit: function(event) {
            event.preventDefault();
            const $form = $(event.currentTarget);
            const $input = $form.find('.eai-follow-up-input');
            const promptText = $input.val();
            const widgetId = $form.data('widget-id');
            const $widgetElement = aiAssistantEditor.getWidgetElement(widgetId);

            if (!aiAssistantEditor.isPromptValid(promptText) || !$widgetElement) { return; }

            aiAssistantEditor.conversationHistory[widgetId].push({'role': 'user', 'parts': [{'text': promptText}]});
            $input.val('');
            aiAssistantEditor.setLoadingState($widgetElement, true);
            aiAssistantEditor.sendRequestToBackend(widgetId, $widgetElement);
        },

        sendRequestToBackend: function (widgetId, $widgetElement) {
            $.ajax({
                url: eai_ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'eai_generate_design',
                    conversation_history: JSON.stringify(aiAssistantEditor.conversationHistory[widgetId]),
                    security: eai_ajax_object.nonce
                },
                success: function (response) {
                    if (response.success) {
                        aiAssistantEditor.conversationHistory[widgetId].push({'role': 'model', 'parts': [{'text': response.data.message}]});
                        aiAssistantEditor.displayConversation(widgetId, $widgetElement);
                    } else {
                        aiAssistantEditor.displayError($widgetElement, response.data.message);
                    }
                },
                error: function (xhr) {
                    aiAssistantEditor.displayError($widgetElement, 'Failed to communicate with the server.');
                }
            });
        },
        
        displayConversation: function(widgetId, $widgetElement) {
            const history = aiAssistantEditor.conversationHistory[widgetId];
            let chatLogHTML = '';

            history.forEach(turn => {
                let contentHTML = $('<div/>').text(turn.parts[0].text).html();
                chatLogHTML += `<div class="eai-chat-turn ${turn.role}"><div class="role">${turn.role === 'user' ? 'Anda' : 'AI Assistant'}</div><div class="content">${contentHTML}</div></div>`;
            });
            
            const fullHTML = `
                <div class="eai-chat-container">
                    <div class="eai-chat-log">${chatLogHTML}</div>
                    <button class="eai-view-copy-button" data-widget-id="${widgetId}">Lihat & Salin Respons Terakhir</button>
                    <form class="eai-follow-up-form" data-widget-id="${widgetId}">
                        <input type="text" class="eai-follow-up-input" placeholder="Ketik prompt tambahan...">
                        <button type="submit" class="eai-follow-up-submit">Kirim</button>
                    </form>
                </div>
            `;
            
            $widgetElement.find('.eai-generator-widget-wrapper').html(fullHTML);
            const chatLog = $widgetElement.find('.eai-chat-log')[0];
            if(chatLog) { chatLog.scrollTop = chatLog.scrollHeight; }
        },

        openModal: function(event) {
            const widgetId = $(event.currentTarget).data('widget-id');
            const history = aiAssistantEditor.conversationHistory[widgetId];
            const lastAIResponse = history.filter(turn => turn.role === 'model').pop();
            
            if (!lastAIResponse) return;
            const fullResponseText = lastAIResponse.parts[0].text;

            $('.eai-modal-overlay').remove(); // Hapus modal lama jika ada
            const modalHTML = `
                <div class="eai-modal-overlay">
                    <div class="eai-modal-content">
                        <div class="eai-modal-header">
                            <h4>AI Generated Response</h4>
                            <button type="button" class="eai-modal-close">&times;</button>
                        </div>
                        <div class="eai-modal-body">
                            <pre>${fullResponseText}</pre>
                        </div>
                        <div class="eai-modal-footer">
                            <button type="button" class="eai-modal-copy-all">Salin Semua</button>
                        </div>
                    </div>
                </div>
            `;
            $('body').append(modalHTML);
        },

        closeModal: function() {
            $('.eai-modal-overlay').remove();
        },

        handleModalCopyClick: function(event) {
            const $button = $(event.currentTarget);
            // Ambil teks dari elemen <pre> di dalam modal yang sama
            const textToCopy = $button.closest('.eai-modal-content').find('.eai-modal-body pre').text();

            if (textToCopy && navigator.clipboard) {
                navigator.clipboard.writeText(textToCopy).then(() => {
                    $button.text('Tersalin!').addClass('copied');
                    setTimeout(() => { $button.text('Salin Semua').removeClass('copied'); }, 2000);
                });
            }
        },

        getWidgetElement: function(widgetId) { const $previewIframe = $('#elementor-preview-iframe').contents(); return $previewIframe.find(`[data-id="${widgetId}"]`); },
        isPromptValid: function (prompt) { return prompt && prompt.trim() !== ''; },
        setLoadingState: function ($widgetElement, isLoading) {
            const $wrapper = $widgetElement.find('.eai-generator-widget-wrapper');
            if (isLoading) {
                if ($wrapper.find('.eai-chat-container').length) {
                    $wrapper.find('.eai-follow-up-form').hide();
                    $wrapper.find('.eai-chat-log').append('<p class="eai-thinking" style="text-align:center; opacity:0.7;"><i>AI is thinking...</i></p>');
                } else {
                    $wrapper.html('<h3><span class="eicon-loading eicon-animation-spin"></span> Generating...</h3>');
                }
            }
        },
        displayError: function ($widgetElement, message) { $widgetElement.find('.eai-generator-widget-wrapper').html(`<p style="color: red;"><b>Error:</b> ${message}</p>`); },
        showNotification: function (type, message) { if (typeof elementor.notifications !== 'undefined') { elementor.notifications.showToast({ message: message }); } },
    };

    // --- PERBAIKAN UTAMA DI SINI ---
    // Kita pindahkan listener untuk modal ke sini, di dokumen utama.
    $(function() {
        aiAssistantEditor.init();

        // Event listener ini sekarang berada di lingkup yang benar.
        $('body').on('click', '.eai-modal-close, .eai-modal-overlay', function(e) {
            // Pastikan tidak menutup jika mengklik konten modalnya
            if ($(e.target).is('.eai-modal-content') || $(e.target).closest('.eai-modal-content').length > 0) {
                if (!$(e.target).is('.eai-modal-close')) {
                    return;
                }
            }
            aiAssistantEditor.closeModal();
        });

        $('body').on('click', '.eai-modal-copy-all', function(e) {
            aiAssistantEditor.handleModalCopyClick(e);
        });
    });

})(jQuery);