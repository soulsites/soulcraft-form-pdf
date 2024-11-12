jQuery(document).ready(function(jQuery) {
    const DEBUG_LOG_REFRESH_INTERVAL = 5000; // 5 Sekunden
    let debugLogInterval = null;

    // Debug Mode Funktionalität
    function handleDebugMode() {
        const debugCheckbox = jQuery('input[name="carbon_fields_compact_input[_pdf_debug_enabled]"]');
        const debugEnabled = debugCheckbox.is(':checked');
        pdf_debug('Debug mode state changed', debugEnabled);

        if (debugEnabled) {
            if (jQuery('.debug-log-container').length === 0) {
                // Debug-Log Container erstellen wenn noch nicht vorhanden
                const debugContainer = jQuery(`
                   <div class="debug-log-container">
                       <div class="debug-log-header">
                           <h4>Debug Log</h4>
                           <button type="button" class="button refresh-debug-log">Aktualisieren</button>
                       </div>
                       <textarea id="debug_log_content" rows="15" readonly></textarea>
                   </div>
               `);
                jQuery('#debug_section').after(debugContainer);
            }
            jQuery('.debug-log-container').show();
            refreshDebugLog();
            startAutoRefresh();
        } else {
            jQuery('.debug-log-container').hide();
            stopAutoRefresh();
        }
    }

    // Auto-Refresh des Debug Logs
    function startAutoRefresh() {
        if (debugLogInterval === null) {
            debugLogInterval = setInterval(refreshDebugLog, DEBUG_LOG_REFRESH_INTERVAL);
            pdf_debug('Started auto refresh');
        }
    }

    function stopAutoRefresh() {
        if (debugLogInterval !== null) {
            clearInterval(debugLogInterval);
            debugLogInterval = null;
            pdf_debug('Stopped auto refresh');
        }
    }

    // Debug Log aktualisieren
    function refreshDebugLog() {
        pdf_debug('Refreshing debug log');
        jQuery.ajax({
            url: soulcraftPdfData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'refresh_debug_log',
                nonce: soulcraftPdfData.nonce
            },
            success: function(response) {
                if (response.success) {
                    const logContent = response.data.content;
                    jQuery('#debug_log_content').val(logContent);

                    // Scrolle zum Ende des Logs
                    const textarea = document.getElementById('debug_log_content');
                    if (textarea) {
                        textarea.scrollTop = textarea.scrollHeight;
                    }
                }
            },
            error: function(xhr, status, error) {
                pdf_debug('Debug log refresh error', {xhr, status, error});
            }
        });
    }

    // PDF Vorschau senden
    function sendPdfPreview(button) {
        const container = button.closest('.cf-complex__group');
        const formIdSelect = container.find('select[name*="[form_id]"]');
        const previewEmailField = container.find('input[name*="[pdf_preview_email]"]');

        const formId = formIdSelect.val();
        const previewEmail = previewEmailField.val();

        pdf_debug('Preview requested', {formId, previewEmail});

        if (!formId) {
            showNotice('error', 'Bitte wählen Sie ein Formular aus.');
            return;
        }

        if (!previewEmail) {
            showNotice('error', 'Bitte geben Sie eine E-Mail-Adresse für die Vorschau an.');
            return;
        }

        button.prop('disabled', true);
        showSpinner(button);

        jQuery.ajax({
            url: soulcraftPdfData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'send_pdf_preview',
                nonce: soulcraftPdfData.nonce,
                form_id: formId,
                preview_email: previewEmail
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', 'Die PDF wurde erfolgreich zur Vorschau gesendet.');
                } else {
                    showNotice('error', response.data || 'Die PDF konnte nicht gesendet werden.');
                }
            },
            error: function(xhr, status, error) {
                pdf_debug('Preview error', {xhr, status, error});
                showNotice('error', 'Ein Fehler ist bei der Übertragung aufgetreten.');
            },
            complete: function() {
                button.prop('disabled', false);
                hideSpinner(button);
            }
        });
    }

    // Google Font Installation
    function installGoogleFont() {
        const fontFamily = jQuery('input[name="carbon_fields_compact_input[_google_font_family]"]').val();

        if (!fontFamily) {
            showNotice('error', 'Bitte geben Sie eine Schriftart an.');
            return;
        }

        const button = jQuery('.install-google-font');
        button.prop('disabled', true);
        showSpinner(button);

        jQuery.ajax({
            url: soulcraftPdfData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'install_google_font',
                nonce: soulcraftPdfData.nonce,
                font_family: fontFamily
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);

                    // Aktualisiere Font-Dropdowns
                    updateFontDropdowns(response.data.fonts);
                } else {
                    showNotice('error', response.data || 'Die Schriftart konnte nicht installiert werden.');
                }
            },
            error: function(xhr, status, error) {
                pdf_debug('Font installation error', {xhr, status, error});
                showNotice('error', 'Ein Fehler ist bei der Installation aufgetreten.');
            },
            complete: function() {
                button.prop('disabled', false);
                hideSpinner(button);
            }
        });
    }

    // Helper Funktionen
    function showNotice(type, message) {
        const noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
        const notice = jQuery(`
           <div class="notice jQuery{noticeClass} is-dismissible">
               <p>jQuery{message}</p>
               <button type="button" class="notice-dismiss">
                   <span class="screen-reader-text">Dismiss this notice.</span>
               </button>
           </div>
       `);

        // Entferne alte Notices
        jQuery('.notice').remove();

        // Füge neue Notice hinzu
        jQuery('.wrap h1').after(notice);

        // Automatisch nach 5 Sekunden ausblenden
        setTimeout(() => {
            notice.fadeOut(() => notice.remove());
        }, 5000);
    }

    function showSpinner(button) {
        if (!button.find('.spinner').length) {
            button.after('<span class="spinner is-active"></span>');
        }
    }

    function hideSpinner(button) {
        button.next('.spinner').remove();
    }

    function updateFontDropdowns(fonts) {
        jQuery('select[name*="[font]"]').each(function() {
            const currentValue = jQuery(this).val();
            jQuery(this).empty();

            Object.entries(fonts).forEach(([value, label]) => {
                jQuery(this).append(jQuery('<option>', {
                    value: value,
                    text: label,
                    selected: value === currentValue
                }));
            });
        });
    }

    // Debug Helper
    function pdf_debug(message, data = null) {
        if (window.console && window.console.log) {
            const debugMessage = data ? `PDF Debug: jQuery{message} - jQuery{JSON.stringify(data)}` : `PDF Debug: jQuery{message}`;
            console.log(debugMessage);
        }
    }

    // Event Listeners
    jQuery('input[name="carbon_fields_compact_input[_pdf_debug_enabled]"]').on('change', handleDebugMode);
    jQuery(document).on('click', '.refresh-debug-log', refreshDebugLog);
    jQuery(document).on('click', '.send-pdf-preview', function() {
        sendPdfPreview(jQuery(this));
    });
    jQuery(document).on('click', '.install-google-font', installGoogleFont);

    // Initialisierung
    handleDebugMode();
});