/**
 * Frank Dead Link Checker - Admin JavaScript
 * Handles all interactive functionality
 */

(function ($) {
    'use strict';

    const FRANKDLC = {
        init: function () {
            this.relocateNotices();
            this.bindEvents();
            this.initTabs();
            this.checkScanStatus();
        },

        /**
         * Move third-party admin notices out of our layout
         * WordPress injects notices inside .wrap, which breaks our flexbox header
         */
        relocateNotices: function () {
            var $wrap = $('.frankdlc-wrap');
            var $header = $wrap.find('.frankdlc-header');
            if (!$wrap.length || !$header.length) return;

            // Create a container for notices right after the header
            var $noticeContainer = $('<div class="frankdlc-notices-container"></div>');
            $header.after($noticeContainer);

            // Move all notice elements from .frankdlc-wrap into the container
            $wrap.children('.notice, .updated, .error, .update-nag, div[class*="notice"]').not('.frankdlc-notices-container').each(function () {
                $noticeContainer.append($(this));
            });

            // Remove container if empty
            if ($noticeContainer.children().length === 0) {
                $noticeContainer.remove();
            }
        },


        bindEvents: function () {
            // Scan button
            $('#frankdlc-scan-btn').on('click', this.startScan.bind(this));

            // Stop scan button
            $('#frankdlc-stop-btn').on('click', this.stopScan.bind(this));

            // Fresh Scan button
            $('#frankdlc-fresh-scan-btn').on('click', this.freshScan.bind(this));

            // Force Stop button
            $('#frankdlc-force-stop-btn').on('click', this.forceStopScan.bind(this));

            // Reset & Maintenance buttons (Help/Settings page)
            $(document).on('click', '#frankdlc-reset-settings-btn', this.resetSettings.bind(this));
            $(document).on('click', '#frankdlc-clear-history-btn', this.clearScanHistory.bind(this));
            $(document).on('click', '#frankdlc-full-reset-btn', this.fullReset.bind(this));


            // Select all checkbox
            $('#frankdlc-select-all').on('change', function () {
                $('.frankdlc-link-checkbox').prop('checked', $(this).prop('checked'));
            });

            // Individual actions

            $(document).on('click', '.frankdlc-dismiss', this.dismissLink.bind(this));
            $(document).on('click', '.frankdlc-undismiss', this.undismissLink.bind(this));
            $(document).on('click', '.frankdlc-delete', this.deleteLink.bind(this));


            // Bulk action
            $('#frankdlc-bulk-apply').on('click', this.bulkAction.bind(this));



            // Modal
            $(document).on('click', '.frankdlc-modal-close, .frankdlc-modal-cancel', this.closeModal.bind(this));


            // Close modal on outside click
            $(document).on('click', '.frankdlc-modal', function (e) {
                if ($(e.target).hasClass('frankdlc-modal')) {
                    FRANKDLC.closeModal();
                }
            });

            // Close modal on ESC
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    FRANKDLC.closeModal();
                }
            });
        },

        initTabs: function () {
            $('.frankdlc-tabs-nav a').on('click', function (e) {
                e.preventDefault();
                const target = $(this).attr('href');

                $('.frankdlc-tabs-nav a').removeClass('active');
                $(this).addClass('active');

                $('.frankdlc-tab-panel').removeClass('active');
                $(target).addClass('active');

                // Persist active tab in URL hash
                if (history.replaceState) {
                    history.replaceState(null, null, target);
                } else {
                    window.location.hash = target;
                }

                // Also save to localStorage for form-submit persistence
                try { localStorage.setItem('frankdlc_active_tab', target); } catch (e) { }
            });

            // Restore active tab from URL hash or localStorage on page load
            var hash = window.location.hash;
            if (!hash || !$(hash).length || !$(hash).hasClass('frankdlc-tab-panel')) {
                try { hash = localStorage.getItem('frankdlc_active_tab'); } catch (e) { }
            }
            if (hash && $(hash).length && $(hash).hasClass('frankdlc-tab-panel')) {
                $('.frankdlc-tabs-nav a').removeClass('active');
                $('.frankdlc-tabs-nav a[href="' + hash + '"]').addClass('active');
                $('.frankdlc-tab-panel').removeClass('active');
                $(hash).addClass('active');
            }

            // Append hash to form action before submit so WordPress redirects back with tab hash
            $('.frankdlc-settings-page form').on('submit', function () {
                var activeTab = $('.frankdlc-tabs-nav a.active').attr('href') || '#general';
                var $form = $(this);
                var action = $form.attr('action') || '';
                action = action.replace(/#.*$/, '') + activeTab;
                $form.attr('action', action);
            });
        },

        startScan: function (e) {
            e.preventDefault();
            const $btn = $('#frankdlc-scan-btn');
            const $stopBtn = $('#frankdlc-stop-btn');

            $btn.hide();
            $stopBtn.show();
            $('#frankdlc-scan-progress').show();

            $.ajax({
                url: frankdlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'FRANKDLC_start_scan',
                    nonce: frankdlcAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        FRANKDLC.pollProgress();
                    } else {
                        FRANKDLC.showToast(response.data || frankdlcAdmin.strings.scanFailed, 'error');
                        FRANKDLC.resetScanButton();
                    }
                },
                error: function () {
                    FRANKDLC.showToast(frankdlcAdmin.strings.scanFailed, 'error');
                    FRANKDLC.resetScanButton();
                }
            });
        },

        stopScan: function (e) {
            e.preventDefault();
            const $stopBtn = $('#frankdlc-stop-btn');

            FRANKDLC.confirmModal(frankdlcAdmin.strings.confirmStop, function() {
                $stopBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + frankdlcAdmin.strings.stopping);

                $.ajax({
                    url: frankdlcAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'FRANKDLC_stop_scan',
                        nonce: frankdlcAdmin.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            FRANKDLC.showToast(frankdlcAdmin.strings.scanStopped, 'success');
                            FRANKDLC.resetScanButton();
                            $('#frankdlc-scan-progress').fadeOut();
                            setTimeout(function () { location.reload(); }, 1000);
                        } else {
                            FRANKDLC.showToast(response.data || frankdlcAdmin.strings.error, 'error');
                            $stopBtn.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> ' + frankdlcAdmin.strings.stopScan);
                        }
                    },
                    error: function () {
                        FRANKDLC.showToast(frankdlcAdmin.strings.error, 'error');
                        $stopBtn.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> ' + frankdlcAdmin.strings.stopScan);
                    }
                });
            });
        },

        freshScan: function (e) {
            e.preventDefault();

            FRANKDLC.confirmModal(frankdlcAdmin.strings.confirmFreshScan, function() {
                const $btn = $('#frankdlc-fresh-scan-btn');
                const $scanBtn = $('#frankdlc-scan-btn');
                const $stopBtn = $('#frankdlc-stop-btn');

                // Disable buttons and show loading state
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + frankdlcAdmin.strings.clearing);
                $scanBtn.hide();

                $.ajax({
                    url: frankdlcAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'FRANKDLC_fresh_scan',
                        nonce: frankdlcAdmin.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            FRANKDLC.showToast(frankdlcAdmin.strings.freshScanStarted, 'success');
                            $btn.hide();
                            $stopBtn.show();
                            $('#frankdlc-scan-progress').show();
                            FRANKDLC.pollProgress();
                        } else {
                            FRANKDLC.showToast(response.data || frankdlcAdmin.strings.error, 'error');
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> ' + frankdlcAdmin.strings.freshScan);
                            $scanBtn.show();
                        }
                    },
                    error: function () {
                        FRANKDLC.showToast(frankdlcAdmin.strings.error, 'error');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> ' + frankdlcAdmin.strings.freshScan);
                        $scanBtn.show();
                    }
                });
            });
        },

        pollProgress: function () {
            $.ajax({
                url: frankdlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'FRANKDLC_get_scan_progress',
                    nonce: frankdlcAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        const data = response.data;

                        if (data.status === 'completed' || data.status === 'idle' || data.status === 'cancelled') {
                            FRANKDLC.showToast(frankdlcAdmin.strings.scanComplete, 'success');
                            FRANKDLC.resetScanButton();
                            $('#frankdlc-scan-progress').fadeOut();
                            setTimeout(function () { location.reload(); }, 1500);
                        } else {
                            // Update progress bar
                            $('.frankdlc-progress-fill').css('width', data.percent + '%');
                            $('.frankdlc-progress-text').text(
                                frankdlcAdmin.strings.progressText
                                    .replace('%1$s', data.checked)
                                    .replace('%2$s', data.total)
                                    .replace('%3$s', data.percent)
                                    .replace('%4$s', data.broken)
                                    .replace('%5$s', data.warnings)
                            );

                            // Poll again
                            setTimeout(FRANKDLC.pollProgress, 2000);
                        }
                    } else {
                        setTimeout(FRANKDLC.pollProgress, 3000);
                    }
                },
                error: function () {
                    setTimeout(FRANKDLC.pollProgress, 5000);
                }
            });
        },

        checkScanStatus: function () {
            // Check if scan is running on page load
            $.ajax({
                url: frankdlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'FRANKDLC_get_scan_progress',
                    nonce: frankdlcAdmin.nonce
                },
                success: function (response) {
                    if (response.success && response.data.status === 'running') {
                        $('#frankdlc-scan-btn').hide();
                        $('#frankdlc-stop-btn').show();
                        $('#frankdlc-scan-progress').show();
                        $('.frankdlc-progress-fill').css('width', response.data.percent + '%');
                        FRANKDLC.pollProgress();
                    }
                }
            });
        },

        resetScanButton: function () {
            $('#frankdlc-scan-btn').show();
            $('#frankdlc-stop-btn').hide().prop('disabled', false).html('<span class="dashicons dashicons-no"></span> ' + frankdlcAdmin.strings.stopScan);
        },



        dismissLink: function (e) {
            const $btn = $(e.currentTarget);
            const $row = $btn.closest('tr');
            const linkId = $btn.data('id');

            $.ajax({
                url: frankdlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'FRANKDLC_dismiss_link',
                    nonce: frankdlcAdmin.nonce,
                    link_id: linkId
                },
                success: function (response) {
                    if (response.success) {
                        $row.fadeOut(300, function () { $(this).remove(); });
                        FRANKDLC.showToast(response.data, 'success');
                    } else {
                        FRANKDLC.showToast(response.data || frankdlcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    FRANKDLC.showToast(frankdlcAdmin.strings.error, 'error');
                }
            });
        },

        undismissLink: function (e) {
            const $btn = $(e.currentTarget);
            const $row = $btn.closest('tr');
            const linkId = $btn.data('id');

            $.ajax({
                url: frankdlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'FRANKDLC_undismiss_link',
                    nonce: frankdlcAdmin.nonce,
                    link_id: linkId
                },
                success: function (response) {
                    if (response.success) {
                        $row.fadeOut(300, function () { $(this).remove(); });
                        FRANKDLC.showToast(response.data, 'success');
                    } else {
                        FRANKDLC.showToast(response.data || frankdlcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    FRANKDLC.showToast(frankdlcAdmin.strings.error, 'error');
                }
            });
        },

        deleteLink: function (e) {
            const $btn = $(e.currentTarget);
            const $row = $btn.closest('tr');
            const linkId = $btn.data('id');

            FRANKDLC.confirmModal(frankdlcAdmin.strings.confirmDelete, function() {
                $.ajax({
                    url: frankdlcAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'FRANKDLC_delete_link',
                        nonce: frankdlcAdmin.nonce,
                        link_id: linkId
                    },
                    success: function (response) {
                        if (response.success) {
                            $row.fadeOut(300, function () { $(this).remove(); });
                            FRANKDLC.showToast(response.data, 'success');
                        } else {
                            FRANKDLC.showToast(response.data || frankdlcAdmin.strings.error, 'error');
                        }
                    },
                    error: function () {
                        FRANKDLC.showToast(frankdlcAdmin.strings.error, 'error');
                    }
                });
            });
        },



        closeModal: function () {
            $('.frankdlc-modal').fadeOut(200);
        },

        bulkAction: function () {
            const action = $('#frankdlc-bulk-action').val();
            const linkIds = [];

            $('.frankdlc-link-checkbox:checked').each(function () {
                linkIds.push($(this).val());
            });

            if (!action) {
                FRANKDLC.showToast(frankdlcAdmin.strings.selectAction, 'error');
                return;
            }

            if (linkIds.length === 0) {
                FRANKDLC.showToast(frankdlcAdmin.strings.selectLink, 'error');
                return;
            }

            if (action === 'delete') {
                FRANKDLC.confirmModal(frankdlcAdmin.strings.confirmDelete, function() {
                    FRANKDLC.processBulkAction(action, linkIds);
                });
            } else {
                FRANKDLC.processBulkAction(action, linkIds);
            }
        },

        processBulkAction: function(action, linkIds) {
            $('#frankdlc-bulk-apply').prop('disabled', true).text(frankdlcAdmin.strings.processing);

            $.ajax({
                url: frankdlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'FRANKDLC_bulk_action',
                    nonce: frankdlcAdmin.nonce,
                    bulk_action: action,
                    link_ids: linkIds
                },
                success: function (response) {
                    $('#frankdlc-bulk-apply').prop('disabled', false).text(frankdlcAdmin.strings.apply);
                    if (response.success) {
                        FRANKDLC.showToast(response.data.message, 'success');
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        FRANKDLC.showToast(response.data || frankdlcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    $('#frankdlc-bulk-apply').prop('disabled', false).text(frankdlcAdmin.strings.apply);
                    FRANKDLC.showToast(frankdlcAdmin.strings.error, 'error');
                }
            });
        },



        showToast: function (message, type) {
            type = type || 'success';

            // Remove existing toasts
            $('.frankdlc-toast').remove();

            const $toast = $('<div class="frankdlc-toast ' + type + '">' + message + '</div>');
            $('body').append($toast);

            setTimeout(function () {
                $toast.fadeOut(300, function () { $(this).remove(); });
            }, 3000);
        },

        confirmModal: function (message, onConfirm) {
            $('.frankdlc-confirm-modal').remove();
            const modalHtml = `
                <div class="frankdlc-modal frankdlc-confirm-modal" style="display:flex; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:99999; align-items:center; justify-content:center;">
                    <div style="background:#fff; border-radius:8px; width:400px; max-width:90%; padding:24px; box-shadow:0 4px 15px rgba(0,0,0,0.2);">
                        <h3 style="margin-top:0; margin-bottom:15px; font-size:18px; color:#1d2327;">${frankdlcAdmin.strings.confirmTitle || 'Please Confirm'}</h3>
                        <p style="font-size:14px; color:#3c434a; margin-bottom:24px; line-height:1.5;">${message}</p>
                        <div style="text-align:right;">
                            <button type="button" class="button frankdlc-modal-cancel" style="margin-right:8px;">${frankdlcAdmin.strings.cancel || 'Cancel'}</button>
                            <button type="button" class="button button-primary frankdlc-modal-confirm">${frankdlcAdmin.strings.ok || 'OK'}</button>
                        </div>
                    </div>
                </div>
            `;
            $('body').append(modalHtml);
            
            $('.frankdlc-modal-cancel').on('click', function() {
                $('.frankdlc-confirm-modal').fadeOut(200, function() { $(this).remove(); });
            });
            
            $('.frankdlc-modal-confirm').on('click', function() {
                $('.frankdlc-confirm-modal').fadeOut(200, function() { $(this).remove(); });
                if (typeof onConfirm === 'function') {
                    onConfirm();
                }
            });
        },

        /**
         * Force Stop Scan
         */
        forceStopScan: function (e) {
            e.preventDefault();
            FRANKDLC.confirmModal(frankdlcAdmin.strings.confirmForceStop, function() {
                const $btn = $('#frankdlc-force-stop-btn');
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + frankdlcAdmin.strings.forceStopping);
                $.ajax({
                    url: frankdlcAdmin.ajaxUrl,
                    type: 'POST',
                    data: { action: 'FRANKDLC_force_stop_scan', nonce: frankdlcAdmin.nonce },
                    success: function (response) {
                        if (response.success) {
                            FRANKDLC.showToast(response.data || frankdlcAdmin.strings.allScansForceStopped, 'success');
                            setTimeout(function () { location.reload(); }, 1500);
                        } else {
                            FRANKDLC.showToast(response.data || frankdlcAdmin.strings.failedForceStop, 'error');
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-dismiss"></span> ' + frankdlcAdmin.strings.forceStop);
                        }
                    },
                    error: function () {
                        FRANKDLC.showToast(frankdlcAdmin.strings.error, 'error');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-dismiss"></span> ' + frankdlcAdmin.strings.forceStop);
                    }
                });
            });
        },

        /**
         * Reset Settings to Defaults
         */
        resetSettings: function (e) {
            e.preventDefault();
            FRANKDLC.confirmModal(frankdlcAdmin.strings.confirmResetSettings, function() {
                const $btn = $('#frankdlc-reset-settings-btn');
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + frankdlcAdmin.strings.resetting);
                $.ajax({
                    url: frankdlcAdmin.ajaxUrl,
                    type: 'POST',
                    data: { action: 'FRANKDLC_reset_settings', nonce: frankdlcAdmin.nonce },
                    success: function (response) {
                        if (response.success) {
                            FRANKDLC.showToast(response.data || frankdlcAdmin.strings.settingsResetDefaults, 'success');
                            setTimeout(function () { location.reload(); }, 1500);
                        } else {
                            FRANKDLC.showToast(response.data || frankdlcAdmin.strings.failedResetSettings, 'error');
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-undo"></span> ' + frankdlcAdmin.strings.resetSettings);
                        }
                    },
                    error: function () {
                        FRANKDLC.showToast(frankdlcAdmin.strings.error, 'error');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-undo"></span> ' + frankdlcAdmin.strings.resetSettings);
                    }
                });
            });
        },

        /**
         * Clear Scan History
         */
        clearScanHistory: function (e) {
            e.preventDefault();
            FRANKDLC.confirmModal(frankdlcAdmin.strings.confirmClearHistory, function() {
                const $btn = $('#frankdlc-clear-history-btn');
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + frankdlcAdmin.strings.clearing);
                $.ajax({
                    url: frankdlcAdmin.ajaxUrl,
                    type: 'POST',
                    data: { action: 'FRANKDLC_clear_scan_history', nonce: frankdlcAdmin.nonce },
                    success: function (response) {
                        if (response.success) {
                            FRANKDLC.showToast(response.data || frankdlcAdmin.strings.scanHistoryCleared, 'success');
                            setTimeout(function () { location.reload(); }, 1500);
                        } else {
                            FRANKDLC.showToast(response.data || frankdlcAdmin.strings.failedClearHistory, 'error');
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + frankdlcAdmin.strings.clearHistory);
                        }
                    },
                    error: function () {
                        FRANKDLC.showToast(frankdlcAdmin.strings.error, 'error');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + frankdlcAdmin.strings.clearHistory);
                    }
                });
            });
        },

        /**
         * Full Plugin Reset
         */
        fullReset: function (e) {
            e.preventDefault();
            FRANKDLC.confirmModal(frankdlcAdmin.strings.confirmFullReset, function() {
                // Double confirmation for destructive action
                FRANKDLC.confirmModal(frankdlcAdmin.strings.confirmFullResetDouble, function() {
                    const $btn = $('#frankdlc-full-reset-btn');
                    $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + frankdlcAdmin.strings.resettingEverything);
                    $.ajax({
                        url: frankdlcAdmin.ajaxUrl,
                        type: 'POST',
                        data: { action: 'FRANKDLC_full_reset', nonce: frankdlcAdmin.nonce },
                        success: function (response) {
                            if (response.success) {
                                FRANKDLC.showToast(response.data || frankdlcAdmin.strings.pluginFullyReset, 'success');
                                setTimeout(function () { location.reload(); }, 2000);
                            } else {
                                FRANKDLC.showToast(response.data || frankdlcAdmin.strings.failedResetPlugin, 'error');
                                $btn.prop('disabled', false).html('<span class="dashicons dashicons-warning"></span> ' + frankdlcAdmin.strings.fullReset);
                            }
                        },
                        error: function () {
                            FRANKDLC.showToast(frankdlcAdmin.strings.error, 'error');
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-warning"></span> ' + frankdlcAdmin.strings.fullReset);
                        }
                    });
                });
            });
        },

        /**
         * Cleanup Export Files
         */
        cleanupExports: function (e) {
            e.preventDefault();
            FRANKDLC.confirmModal(frankdlcAdmin.strings.confirmCleanupExports, function() {
                const $btn = $('#frankdlc-cleanup-exports-btn');
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + frankdlcAdmin.strings.cleaning);
                $.ajax({
                    url: frankdlcAdmin.ajaxUrl,
                    type: 'POST',
                    data: { action: 'FRANKDLC_cleanup_exports', nonce: frankdlcAdmin.nonce },
                    success: function (response) {
                        if (response.success) {
                            FRANKDLC.showToast(response.data || frankdlcAdmin.strings.exportFilesCleaned, 'success');
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + frankdlcAdmin.strings.cleanupExports);
                        } else {
                            FRANKDLC.showToast(response.data || frankdlcAdmin.strings.failedCleanupExports, 'error');
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + frankdlcAdmin.strings.cleanupExports);
                        }
                    },
                    error: function () {
                        FRANKDLC.showToast(frankdlcAdmin.strings.error, 'error');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + frankdlcAdmin.strings.cleanupExports);
                    }
                });
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        FRANKDLC.init();
    });

    // Add spin animation
    $('<style>.spin { animation: frankdlc-spin 1s linear infinite; } @keyframes frankdlc-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>').appendTo('head');

})(jQuery);
