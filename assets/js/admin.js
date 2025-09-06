jQuery(document).ready(function($) {
    
    // --- Logika untuk Tab ---
    function handleTabSwitching() {
        var $tabs = $('.eai-nav-tab-wrapper .nav-tab');
        var $tabContents = $('.eai-settings-tab-content');

        // Tentukan tab aktif saat halaman dimuat
        var activeTab = window.location.hash || $tabs.first().attr('href');
        
        // Fungsi untuk mengaktifkan tab
        function activateTab(tabHref) {
            $tabs.removeClass('nav-tab-active');
            $tabContents.removeClass('active').hide();

            $tabs.filter('[href="' + tabHref + '"]').addClass('nav-tab-active');
            $('#tab-content-' + tabHref.substring(1)).addClass('active').show();
        }
        
        // Aktifkan tab awal
        activateTab(activeTab);
        
        // Handler saat tab diklik
        $tabs.on('click', function(e) {
            e.preventDefault();
            var targetTab = $(this).attr('href');
            activateTab(targetTab);
            // Update URL hash untuk state (menyimpan tab aktif saat refresh)
            window.history.replaceState(null, null, ' ' + targetTab);
        });
    }
    
    handleTabSwitching();

    // --- Logika untuk Aksi Lisensi (Kode dari sebelumnya, tidak ada perubahan) ---
    function handleLicenseAction(action, button) {
        var feedbackDiv = $('#eai_license_feedback');
        var originalButtonText = button.text();
        
        button.text('Processing...').prop('disabled', true);
        feedbackDiv.html('');

        var data = {
            action: 'eai_' + action + '_license',
            nonce: eai_nonce // Pastikan nonce ini didefinisikan via wp_add_inline_script
        };

        if (action === 'activate') {
            data.license_key = $('#eai_license_key').val();
            data.api_key = $('#eai_customer_api_key').val();
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                var message = response.success ? `<p style="color: green;">${response.data.message}</p>` : `<p style="color: red;">${response.data.message}</p>`;
                feedbackDiv.html(message);
                if (response.success) {
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    button.text(originalButtonText).prop('disabled', false);
                }
            },
            error: function() {
                feedbackDiv.html('<p style="color: red;">An unknown error occurred.</p>');
                button.text(originalButtonText).prop('disabled', false);
            }
        });
    }

    $(document).on('click', '#eai_activate_license_btn', function() {
        handleLicenseAction('activate', $(this));
    });

    $(document).on('click', '#eai_deactivate_license_btn', function() {
        handleLicenseAction('deactivate', $(this));
    });
});