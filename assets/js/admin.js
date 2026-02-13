/**
 * Dead Link Checker Pro - Admin JavaScript
 * Handles all interactive functionality
 */

(function ($) {
    'use strict';

    const BLC = {
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
            var $wrap = $('.blc-wrap');
            var $header = $wrap.find('.blc-header');
            if (!$wrap.length || !$header.length) return;

            // Create a container for notices right after the header
            var $noticeContainer = $('<div class="blc-notices-container"></div>');
            $header.after($noticeContainer);

            // Move all notice elements from .blc-wrap into the container
            $wrap.children('.notice, .updated, .error, .update-nag, div[class*="notice"]').not('.blc-notices-container').each(function () {
                $noticeContainer.append($(this));
            });

            // Remove container if empty
            if ($noticeContainer.children().length === 0) {
                $noticeContainer.remove();
            }
        },


        bindEvents: function () {
            // Scan button
            $('#blc-scan-btn').on('click', this.startScan.bind(this));

            // Stop scan button
            $('#blc-stop-btn').on('click', this.stopScan.bind(this));

            // Fresh Scan button
            $('#blc-fresh-scan-btn').on('click', this.freshScan.bind(this));

            // Force Stop button
            $('#blc-force-stop-btn').on('click', this.forceStopScan.bind(this));

            // Reset & Maintenance buttons (Help/Settings page)
            $(document).on('click', '#blc-reset-settings-btn', this.resetSettings.bind(this));
            $(document).on('click', '#blc-clear-history-btn', this.clearScanHistory.bind(this));
            $(document).on('click', '#blc-full-reset-btn', this.fullReset.bind(this));
            $(document).on('click', '#blc-cleanup-exports-btn', this.cleanupExports.bind(this));

            // Select all checkbox
            $('#blc-select-all').on('change', function () {
                $('.blc-link-checkbox').prop('checked', $(this).prop('checked'));
            });

            // Individual actions
            $(document).on('click', '.blc-recheck', this.recheckLink.bind(this));
            $(document).on('click', '.blc-dismiss', this.dismissLink.bind(this));
            $(document).on('click', '.blc-undismiss', this.undismissLink.bind(this));
            $(document).on('click', '.blc-delete', this.deleteLink.bind(this));
            $(document).on('click', '.blc-edit', this.openEditModal.bind(this));
            $(document).on('click', '.blc-redirect', this.openRedirectModal.bind(this));

            // Bulk action
            $('#blc-bulk-apply').on('click', this.bulkAction.bind(this));

            // Export dropdown
            $('#blc-export-btn').on('click', this.toggleExportMenu.bind(this));
            $(document).on('click', '.blc-export-option', this.exportLinks.bind(this));
            $(document).on('click', function (e) {
                if (!$(e.target).closest('.blc-export-dropdown').length) {
                    $('.blc-export-menu').hide();
                }
            });

            // Modal
            $(document).on('click', '.blc-modal-close, .blc-modal-cancel', this.closeModal.bind(this));
            $('#blc-edit-save').on('click', this.saveEdit.bind(this));
            $('#blc-remove-link').on('click', this.removeLink.bind(this));
            $('#blc-redirect-save').on('click', this.saveRedirect.bind(this));

            // Close modal on outside click
            $(document).on('click', '.blc-modal', function (e) {
                if ($(e.target).hasClass('blc-modal')) {
                    BLC.closeModal();
                }
            });

            // Close modal on ESC
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    BLC.closeModal();
                }
            });
        },

        initTabs: function () {
            $('.blc-tabs-nav a').on('click', function (e) {
                e.preventDefault();
                const target = $(this).attr('href');

                $('.blc-tabs-nav a').removeClass('active');
                $(this).addClass('active');

                $('.blc-tab-panel').removeClass('active');
                $(target).addClass('active');
            });
        },

        startScan: function (e) {
            e.preventDefault();
            const $btn = $('#blc-scan-btn');
            const $stopBtn = $('#blc-stop-btn');

            $btn.hide();
            $stopBtn.show();
            $('#blc-scan-progress').show();

            $.ajax({
                url: blcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'blc_start_scan',
                    nonce: blcAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        BLC.pollProgress();
                    } else {
                        BLC.showToast(response.data || blcAdmin.strings.scanFailed, 'error');
                        BLC.resetScanButton();
                    }
                },
                error: function () {
                    BLC.showToast(blcAdmin.strings.scanFailed, 'error');
                    BLC.resetScanButton();
                }
            });
        },

        stopScan: function (e) {
            e.preventDefault();
            const $stopBtn = $('#blc-stop-btn');

            if (!confirm(blcAdmin.strings.confirmStop)) {
                return;
            }

            $stopBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + blcAdmin.strings.stopping);

            $.ajax({
                url: blcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'blc_stop_scan',
                    nonce: blcAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        BLC.showToast(blcAdmin.strings.scanStopped, 'success');
                        BLC.resetScanButton();
                        $('#blc-scan-progress').fadeOut();
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        BLC.showToast(response.data || blcAdmin.strings.error, 'error');
                        $stopBtn.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> ' + blcAdmin.strings.stopScan);
                    }
                },
                error: function () {
                    BLC.showToast(blcAdmin.strings.error, 'error');
                    $stopBtn.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> ' + blcAdmin.strings.stopScan);
                }
            });
        },

        freshScan: function (e) {
            e.preventDefault();

            // Show confirmation dialog
            if (!confirm(blcAdmin.strings.confirmFreshScan)) {
                return;
            }

            const $btn = $('#blc-fresh-scan-btn');
            const $scanBtn = $('#blc-scan-btn');
            const $stopBtn = $('#blc-stop-btn');

            // Disable buttons and show loading state
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + blcAdmin.strings.clearing);
            $scanBtn.hide();

            $.ajax({
                url: blcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'blc_fresh_scan',
                    nonce: blcAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        BLC.showToast(blcAdmin.strings.freshScanStarted, 'success');
                        $btn.hide();
                        $stopBtn.show();
                        $('#blc-scan-progress').show();
                        BLC.pollProgress();
                    } else {
                        BLC.showToast(response.data || blcAdmin.strings.error, 'error');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> ' + blcAdmin.strings.freshScan);
                        $scanBtn.show();
                    }
                },
                error: function () {
                    BLC.showToast(blcAdmin.strings.error, 'error');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> ' + blcAdmin.strings.freshScan);
                    $scanBtn.show();
                }
            });
        },

        pollProgress: function () {
            $.ajax({
                url: blcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'blc_get_scan_progress',
                    nonce: blcAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        const data = response.data;

                        if (data.status === 'completed' || data.status === 'idle' || data.status === 'cancelled') {
                            BLC.showToast(blcAdmin.strings.scanComplete, 'success');
                            BLC.resetScanButton();
                            $('#blc-scan-progress').fadeOut();
                            setTimeout(function () { location.reload(); }, 1500);
                        } else {
                            // Update progress bar
                            $('.blc-progress-fill').css('width', data.percent + '%');
                            $('.blc-progress-text').text(
                                blcAdmin.strings.progressText
                                    .replace('%1$s', data.checked)
                                    .replace('%2$s', data.total)
                                    .replace('%3$s', data.percent)
                                    .replace('%4$s', data.broken)
                                    .replace('%5$s', data.warnings)
                            );

                            // Poll again
                            setTimeout(BLC.pollProgress, 2000);
                        }
                    } else {
                        setTimeout(BLC.pollProgress, 3000);
                    }
                },
                error: function () {
                    setTimeout(BLC.pollProgress, 5000);
                }
            });
        },

        checkScanStatus: function () {
            // Check if scan is running on page load
            $.ajax({
                url: blcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'blc_get_scan_progress',
                    nonce: blcAdmin.nonce
                },
                success: function (response) {
                    if (response.success && response.data.status === 'running') {
                        $('#blc-scan-btn').hide();
                        $('#blc-stop-btn').show();
                        $('#blc-scan-progress').show();
                        $('.blc-progress-fill').css('width', response.data.percent + '%');
                        BLC.pollProgress();
                    }
                }
            });
        },

        resetScanButton: function () {
            $('#blc-scan-btn').show();
            $('#blc-stop-btn').hide().prop('disabled', false).html('<span class="dashicons dashicons-no"></span> ' + blcAdmin.strings.stopScan);
        },

        recheckLink: function (e) {
            const $btn = $(e.currentTarget);
            const $row = $btn.closest('tr');
            const linkId = $btn.data('id');

            $btn.find('.dashicons').addClass('spin');

            $.ajax({
                url: blcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'blc_recheck_link',
                    nonce: blcAdmin.nonce,
                    link_id: linkId
                },
                success: function (response) {
                    $btn.find('.dashicons').removeClass('spin');
                    if (response.success) {
                        BLC.showToast(response.data.message, 'success');
                        if (response.data.removed) {
                            // Link was fixed/removed from source — fade out the row
                            $row.fadeOut(400, function () {
                                $(this).remove();
                            });
                        } else {
                            setTimeout(function () { location.reload(); }, 1000);
                        }
                    } else {
                        BLC.showToast(response.data || blcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    $btn.find('.dashicons').removeClass('spin');
                    BLC.showToast(blcAdmin.strings.error, 'error');
                }
            });
        },

        dismissLink: function (e) {
            const $btn = $(e.currentTarget);
            const $row = $btn.closest('tr');
            const linkId = $btn.data('id');

            $.ajax({
                url: blcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'blc_dismiss_link',
                    nonce: blcAdmin.nonce,
                    link_id: linkId
                },
                success: function (response) {
                    if (response.success) {
                        $row.fadeOut(300, function () { $(this).remove(); });
                        BLC.showToast(response.data, 'success');
                    } else {
                        BLC.showToast(response.data || blcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    BLC.showToast(blcAdmin.strings.error, 'error');
                }
            });
        },

        undismissLink: function (e) {
            const $btn = $(e.currentTarget);
            const $row = $btn.closest('tr');
            const linkId = $btn.data('id');

            $.ajax({
                url: blcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'blc_undismiss_link',
                    nonce: blcAdmin.nonce,
                    link_id: linkId
                },
                success: function (response) {
                    if (response.success) {
                        $row.fadeOut(300, function () { $(this).remove(); });
                        BLC.showToast(response.data, 'success');
                    } else {
                        BLC.showToast(response.data || blcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    BLC.showToast(blcAdmin.strings.error, 'error');
                }
            });
        },

        deleteLink: function (e) {
            const $btn = $(e.currentTarget);
            const $row = $btn.closest('tr');
            const linkId = $btn.data('id');

            if (!confirm(blcAdmin.strings.confirmDelete)) return;

            $.ajax({
                url: blcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'blc_delete_link',
                    nonce: blcAdmin.nonce,
                    link_id: linkId
                },
                success: function (response) {
                    if (response.success) {
                        $row.fadeOut(300, function () { $(this).remove(); });
                        BLC.showToast(response.data, 'success');
                    } else {
                        BLC.showToast(response.data || blcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    BLC.showToast(blcAdmin.strings.error, 'error');
                }
            });
        },

        openEditModal: function (e) {
            const $btn = $(e.currentTarget);
            const linkId = $btn.data('id');
            const url = $btn.data('url');
            const anchor = $btn.data('anchor') || '';

            $('#blc-edit-link-id').val(linkId);
            $('#blc-edit-old-url').val(url);
            $('#blc-edit-new-url').val('');
            $('#blc-edit-anchor-text').val(anchor);
            $('#blc-edit-modal').fadeIn(200);
            $('#blc-edit-new-url').focus();
        },

        closeModal: function () {
            $('.blc-modal').fadeOut(200);
        },

        saveEdit: function () {
            const linkId = $('#blc-edit-link-id').val();
            const newUrl = $('#blc-edit-new-url').val();
            const newAnchor = $('#blc-edit-anchor-text').val();

            if (!newUrl && !newAnchor) {
                BLC.showToast(blcAdmin.strings.enterUrlOrAnchor, 'error');
                return;
            }

            $('#blc-edit-save').prop('disabled', true).text(blcAdmin.strings.processing);

            $.ajax({
                url: blcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'blc_edit_link',
                    nonce: blcAdmin.nonce,
                    link_id: linkId,
                    new_url: newUrl,
                    new_anchor_text: newAnchor
                },
                success: function (response) {
                    $('#blc-edit-save').prop('disabled', false).text(blcAdmin.strings.updateLink);
                    if (response.success) {
                        BLC.closeModal();
                        BLC.showToast(response.data, 'success');
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        BLC.showToast(response.data || blcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    $('#blc-edit-save').prop('disabled', false).text(blcAdmin.strings.updateLink);
                    BLC.showToast(blcAdmin.strings.error, 'error');
                }
            });
        },

        removeLink: function () {
            if (!confirm(blcAdmin.strings.confirmRemoveLink)) {
                return;
            }

            const linkId = $('#blc-edit-link-id').val();
            $('#blc-remove-link').prop('disabled', true).text(blcAdmin.strings.processing);

            $.ajax({
                url: blcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'blc_remove_link',
                    nonce: blcAdmin.nonce,
                    link_id: linkId
                },
                success: function (response) {
                    $('#blc-remove-link').prop('disabled', false).text(blcAdmin.strings.removeLink);
                    if (response.success) {
                        BLC.closeModal();
                        BLC.showToast(response.data, 'success');
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        BLC.showToast(response.data || blcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    $('#blc-remove-link').prop('disabled', false).text(blcAdmin.strings.removeLink);
                    BLC.showToast(blcAdmin.strings.error, 'error');
                }
            });
        },

        openRedirectModal: function (e) {
            const $btn = $(e.currentTarget);
            const linkId = $btn.data('id');
            const url = $btn.data('url');

            $('#blc-redirect-link-id').val(linkId);
            $('#blc-redirect-source-url').val(url);
            $('#blc-redirect-target-url').val('').focus();
            $('#blc-redirect-type').val('301');
            $('#blc-redirect-modal').fadeIn(200);
        },

        saveRedirect: function () {
            const linkId = $('#blc-redirect-link-id').val();
            const sourceUrl = $('#blc-redirect-source-url').val();
            const targetUrl = $('#blc-redirect-target-url').val();
            const redirectType = $('#blc-redirect-type').val();

            if (!targetUrl) {
                BLC.showToast(blcAdmin.strings.enterTargetUrl, 'error');
                return;
            }

            $('#blc-redirect-save').prop('disabled', true).text(blcAdmin.strings.processing);

            $.ajax({
                url: blcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'blc_create_redirect',
                    nonce: blcAdmin.nonce,
                    link_id: linkId,
                    source_url: sourceUrl,
                    target_url: targetUrl,
                    redirect_type: redirectType
                },
                success: function (response) {
                    $('#blc-redirect-save').prop('disabled', false).text(blcAdmin.strings.createRedirect);
                    if (response.success) {
                        BLC.closeModal();
                        BLC.showToast(response.data.message || blcAdmin.strings.redirectSuccess, 'success');
                    } else {
                        BLC.showToast(response.data || blcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    $('#blc-redirect-save').prop('disabled', false).text(blcAdmin.strings.createRedirect);
                    BLC.showToast(blcAdmin.strings.error, 'error');
                }
            });
        },

        bulkAction: function () {
            const action = $('#blc-bulk-action').val();
            const linkIds = [];

            $('.blc-link-checkbox:checked').each(function () {
                linkIds.push($(this).val());
            });

            if (!action) {
                BLC.showToast(blcAdmin.strings.selectAction, 'error');
                return;
            }

            if (linkIds.length === 0) {
                BLC.showToast(blcAdmin.strings.selectLink, 'error');
                return;
            }

            if (action === 'delete' && !confirm(blcAdmin.strings.confirmDelete)) {
                return;
            }

            $('#blc-bulk-apply').prop('disabled', true).text(blcAdmin.strings.processing);

            $.ajax({
                url: blcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'blc_bulk_action',
                    nonce: blcAdmin.nonce,
                    bulk_action: action,
                    link_ids: linkIds
                },
                success: function (response) {
                    $('#blc-bulk-apply').prop('disabled', false).text(blcAdmin.strings.apply);
                    if (response.success) {
                        BLC.showToast(response.data.message, 'success');
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        BLC.showToast(response.data || blcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    $('#blc-bulk-apply').prop('disabled', false).text(blcAdmin.strings.apply);
                    BLC.showToast(blcAdmin.strings.error, 'error');
                }
            });
        },

        toggleExportMenu: function (e) {
            e.preventDefault();
            e.stopPropagation();
            $('.blc-export-menu').toggle();
        },

        exportLinks: function (e) {
            e.preventDefault();
            const format = $(e.currentTarget).data('format');
            const $btn = $('#blc-export-btn');
            const originalText = $btn.html();

            $('.blc-export-menu').hide();
            $btn.html('<span class="dashicons dashicons-update spin"></span> ' + blcAdmin.strings.exporting);
            $btn.prop('disabled', true);

            $.ajax({
                url: blcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'blc_export_links',
                    nonce: blcAdmin.nonce,
                    format: format,
                    status: new URLSearchParams(window.location.search).get('status') || 'all'
                },
                success: function (response) {
                    if (response.success && response.data && response.data.download_url) {
                        BLC.showToast(blcAdmin.strings.exportSuccess, 'success');
                        var downloadUrl = response.data.download_url;

                        if (format === 'json') {
                            // Open JSON in a new tab for easy viewing
                            window.open(downloadUrl, '_blank');
                        } else {
                            // Download CSV without leaving the page
                            var fileName = downloadUrl.split('/').pop();
                            fetch(downloadUrl)
                                .then(function (resp) { return resp.blob(); })
                                .then(function (blob) {
                                    var blobUrl = window.URL.createObjectURL(blob);
                                    var $downloadLink = $('<a>')
                                        .attr('href', blobUrl)
                                        .attr('download', fileName)
                                        .css('display', 'none')
                                        .appendTo('body');
                                    $downloadLink[0].click();
                                    $downloadLink.remove();
                                    window.URL.revokeObjectURL(blobUrl);
                                });
                        }
                    } else {
                        // Handle error message properly
                        var errorMsg = blcAdmin.strings.exportFailed;
                        if (response.data) {
                            if (typeof response.data === 'string') {
                                errorMsg = response.data;
                            } else if (response.data.message) {
                                errorMsg = response.data.message;
                            }
                        }
                        BLC.showToast(errorMsg, 'error');
                    }
                },
                error: function () {
                    BLC.showToast(blcAdmin.strings.exportFailed, 'error');
                },
                complete: function () {
                    $btn.html(originalText);
                    $btn.prop('disabled', false);
                }
            });
        },

        showToast: function (message, type) {
            type = type || 'success';

            // Remove existing toasts
            $('.blc-toast').remove();

            const $toast = $('<div class="blc-toast ' + type + '">' + message + '</div>');
            $('body').append($toast);

            setTimeout(function () {
                $toast.fadeOut(300, function () { $(this).remove(); });
            }, 3000);
        },

        /**
         * Force Stop Scan
         */
        forceStopScan: function (e) {
            e.preventDefault();
            if (!confirm(blcAdmin.strings.confirmForceStop)) {
                return;
            }
            const $btn = $('#blc-force-stop-btn');
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + blcAdmin.strings.forceStopping);
            $.ajax({
                url: blcAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'blc_force_stop_scan', nonce: blcAdmin.nonce },
                success: function (response) {
                    if (response.success) {
                        BLC.showToast(response.data || blcAdmin.strings.allScansForceStopped, 'success');
                        setTimeout(function () { location.reload(); }, 1500);
                    } else {
                        BLC.showToast(response.data || blcAdmin.strings.failedForceStop, 'error');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-dismiss"></span> ' + blcAdmin.strings.forceStop);
                    }
                },
                error: function () {
                    BLC.showToast(blcAdmin.strings.error, 'error');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-dismiss"></span> ' + blcAdmin.strings.forceStop);
                }
            });
        },

        /**
         * Reset Settings to Defaults
         */
        resetSettings: function (e) {
            e.preventDefault();
            if (!confirm(blcAdmin.strings.confirmResetSettings)) {
                return;
            }
            const $btn = $('#blc-reset-settings-btn');
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + blcAdmin.strings.resetting);
            $.ajax({
                url: blcAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'blc_reset_settings', nonce: blcAdmin.nonce },
                success: function (response) {
                    if (response.success) {
                        BLC.showToast(response.data || blcAdmin.strings.settingsResetDefaults, 'success');
                        setTimeout(function () { location.reload(); }, 1500);
                    } else {
                        BLC.showToast(response.data || blcAdmin.strings.failedResetSettings, 'error');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-undo"></span> ' + blcAdmin.strings.resetSettings);
                    }
                },
                error: function () {
                    BLC.showToast(blcAdmin.strings.error, 'error');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-undo"></span> ' + blcAdmin.strings.resetSettings);
                }
            });
        },

        /**
         * Clear Scan History
         */
        clearScanHistory: function (e) {
            e.preventDefault();
            if (!confirm(blcAdmin.strings.confirmClearHistory)) {
                return;
            }
            const $btn = $('#blc-clear-history-btn');
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + blcAdmin.strings.clearing);
            $.ajax({
                url: blcAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'blc_clear_scan_history', nonce: blcAdmin.nonce },
                success: function (response) {
                    if (response.success) {
                        BLC.showToast(response.data || blcAdmin.strings.scanHistoryCleared, 'success');
                        setTimeout(function () { location.reload(); }, 1500);
                    } else {
                        BLC.showToast(response.data || blcAdmin.strings.failedClearHistory, 'error');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + blcAdmin.strings.clearHistory);
                    }
                },
                error: function () {
                    BLC.showToast(blcAdmin.strings.error, 'error');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + blcAdmin.strings.clearHistory);
                }
            });
        },

        /**
         * Full Plugin Reset
         */
        fullReset: function (e) {
            e.preventDefault();
            if (!confirm(blcAdmin.strings.confirmFullReset)) {
                return;
            }
            // Double confirmation for destructive action
            if (!confirm(blcAdmin.strings.confirmFullResetDouble)) {
                return;
            }
            const $btn = $('#blc-full-reset-btn');
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + blcAdmin.strings.resettingEverything);
            $.ajax({
                url: blcAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'blc_full_reset', nonce: blcAdmin.nonce },
                success: function (response) {
                    if (response.success) {
                        BLC.showToast(response.data || blcAdmin.strings.pluginFullyReset, 'success');
                        setTimeout(function () { location.reload(); }, 2000);
                    } else {
                        BLC.showToast(response.data || blcAdmin.strings.failedResetPlugin, 'error');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-warning"></span> ' + blcAdmin.strings.fullReset);
                    }
                },
                error: function () {
                    BLC.showToast(blcAdmin.strings.error, 'error');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-warning"></span> ' + blcAdmin.strings.fullReset);
                }
            });
        },

        /**
         * Cleanup Export Files
         */
        cleanupExports: function (e) {
            e.preventDefault();
            if (!confirm(blcAdmin.strings.confirmCleanupExports)) {
                return;
            }
            const $btn = $('#blc-cleanup-exports-btn');
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + blcAdmin.strings.cleaning);
            $.ajax({
                url: blcAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'blc_cleanup_exports', nonce: blcAdmin.nonce },
                success: function (response) {
                    if (response.success) {
                        BLC.showToast(response.data || blcAdmin.strings.exportFilesCleaned, 'success');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + blcAdmin.strings.cleanupExports);
                    } else {
                        BLC.showToast(response.data || blcAdmin.strings.failedCleanupExports, 'error');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + blcAdmin.strings.cleanupExports);
                    }
                },
                error: function () {
                    BLC.showToast(blcAdmin.strings.error, 'error');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + blcAdmin.strings.cleanupExports);
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        BLC.init();
    });

    // Add spin animation
    $('<style>.spin { animation: blc-spin 1s linear infinite; } @keyframes blc-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>').appendTo('head');

})(jQuery);
