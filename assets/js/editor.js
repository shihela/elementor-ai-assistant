/**
 * Script untuk menangani interaksi widget AI Assistant di dalam editor Elementor.
 * @since 2.0.0 (Final Production Script)
 */
(function ($) {
    'use strict';
    const aiAssistantEditor = {
        init: function () {
            if (typeof elementor === 'undefined') { return; }
            elementor.channels.editor.on('eai_assistant:generate', this.handleGenerateClick);
        },
        handleGenerateClick: function () {
            try {
                const widgetModel = elementor.getPanelView().getCurrentPageView().model;
                if (!widgetModel) { throw new Error('Could not get widget model.'); }
                const promptText = widgetModel.get('settings').get('eai_prompt');
                if (!promptText || promptText.trim() === '') {
                    aiAssistantEditor.showNotification('error', 'Prompt cannot be empty!');
                    return;
                }
                const widgetId = widgetModel.get('id');
                const $previewIframe = $('#elementor-preview-iframe').contents();
                const $widgetElement = $previewIframe.find(`[data-id="${widgetId}"]`);
                if (!$widgetElement.length) { throw new Error('Could not find widget element.'); }
                aiAssistantEditor.setLoadingState($widgetElement, true);
                aiAssistantEditor.sendRequestToBackend(promptText, $widgetElement);
            } catch (error) {
                console.error('AI Assistant critical error:', error);
                aiAssistantEditor.showNotification('error', 'A critical error occurred. Please check console.');
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
                    aiAssistantEditor.displayError($widgetElement, 'Failed to communicate with the server. Status: ' + xhr.status);
                }
            });
        },
        setLoadingState: function ($widgetElement, isLoading) {
            const $wrapper = $widgetElement.find('.eai-generator-widget-wrapper');
            if (isLoading) { $wrapper.html('<h3><span class="eicon-loading eicon-animation-spin"></span> Generating...</h3>'); }
        },
        displayResult: function ($widgetElement, result) {
            const $wrapper = $widgetElement.find('.eai-generator-widget-wrapper');
            $wrapper.html('<h4>AI Response:</h4><pre style="white-space: pre-wrap; word-wrap: break-word;">' + result + '</pre>');
        },
        displayError: function ($widgetElement, message) {
            const $wrapper = $widgetElement.find('.eai-generator-widget-wrapper');
            $wrapper.html('<p style="color: red;"><b>Error:</b> ' + message + '</p>');
        },
        showNotification: function (type, message) {
            if (typeof elementor.notifications !== 'undefined') {
                elementor.notifications.showToast({ message: message });
            }
        },
    };
    $(function () { aiAssistantEditor.init(); });
})(jQuery);