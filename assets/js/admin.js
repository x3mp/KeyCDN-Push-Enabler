/**
 * Admin JavaScript for KeyCDN Push Zone Addon
 */

(function($) {
    'use strict';

    // Progress update interval (in milliseconds)
    var progressUpdateInterval = 5000;
    
    // Track if the page is active/visible
    var isPageVisible = true;
    
    // Function to update progress
    function updateProgress() {
        // Only update if page is visible
        if (!isPageVisible) {
            return;
        }
        
        $.ajax({
            url: keycdnPushAddon.ajaxUrl,
            type: 'POST',
            data: {
                action: 'keycdn_push_enabler_get_progress',
                nonce: keycdnPushAddon.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    var progress = response.data;
                    
                    // Update progress bar
                    $('.keycdn-push-enabler-progress-bar').css('width', progress.percentage + '%');
                    
                    // Update progress text
                    var progressText = keycdnPushAddon.i18n.processed
                        .replace('%1$d', progress.processed)
                        .replace('%2$d', progress.total)
                        .replace('%3$d', progress.percentage);
                    
                    $('.keycdn-push-enabler-progress-info p:first').text(progressText);
                    
                    // Handle stalled state
                    if (progress.stalled) {
                        if ($('.keycdn-push-enabler-stalled').length === 0) {
                            $('.keycdn-push-enabler-progress-info p:first').after(
                                $('<p>').addClass('keycdn-push-enabler-stalled').text(keycdnPushAddon.i18n.stalled)
                            );
                        }
                    } else {
                        $('.keycdn-push-enabler-stalled').remove();
                    }
                    
                    // If process is complete, reload the page after a short delay
                    if (!progress.is_active && $('.keycdn-push-enabler-progress-wrapper').length > 0) {
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    }
                }
            },
            complete: function() {
                // Schedule next update if process is still active
                if ($('.keycdn-push-enabler-progress-wrapper').length > 0) {
                    setTimeout(updateProgress, progressUpdateInterval);
                }
            }
        });
    }
    
    // Document ready
    $(function() {
        // Initial progress update
        if ($('.keycdn-push-enabler-progress-wrapper').length > 0) {
            setTimeout(updateProgress, 1000);
        }
        
        // Track page visibility
        $(document).on('visibilitychange', function() {
            isPageVisible = document.visibilityState === 'visible';
            
            // If page becomes visible, update progress immediately
            if (isPageVisible && $('.keycdn-push-enabler-progress-wrapper').length > 0) {
                updateProgress();
            }
        });
    });
    
})(jQuery);