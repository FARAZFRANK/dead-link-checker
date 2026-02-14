/**
 * Dead Link Checker - Admin JavaScript
 * Handles all interactive functionality
 */

(function ($) {
    'use strict';

    const AWLDLC = {
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
            var $wrap = $('.awldlc-wrap');
            var $header = $wrap.find('.awldlc-header');
            if (!$wrap.length || !$header.length) return;

            // Create a container for notices right after the header
            var $noticeContainer = $('<div class="awldlc-notices-container"></div>');
            $header.after($noticeContainer);

            // Move all notice elements from .awldlc-wrap into the container
            $wrap.children('.notice, .updated, .error, .update-nag, div[class*="notice"]').not('.awldlc-notices-container').each(function () {
                $noticeContainer.append($(this));
            });

            // Remove container if empty
            if ($noticeContainer.children().length === 0) {
                $noticeContainer.remove();
            }
        },


        bindEvents: function () {
            // Scan button
            $('#awldlc-scan-btn').on('click', this.startScan.bind(this));

            // Stop scan button
            $('#awldlc-stop-btn').on('click', this.stopScan.bind(this));

            // Fresh Scan button
            $('#awldlc-fresh-scan-btn').on('click', this.freshScan.bind(this));

            // Force Stop button
            $('#awldlc-force-stop-btn').on('click', this.forceStopScan.bind(this));

            // Reset & Maintenance buttons (Help/Settings page)
            $(document).on('click', '#awldlc-reset-settings-btn', this.resetSettings.bind(this));
            $(document).on('click', '#awldlc-clear-history-btn', this.clearScanHistory.bind(this));
            $(document).on('click', '#awldlc-full-reset-btn', this.fullReset.bind(this));
            $(document).on('click', '#awldlc-cleanup-exports-btn', this.cleanupExports.bind(this));

            // Select all checkbox
            $('#awldlc-select-all').on('change', function () {
                $('.awldlc-link-checkbox').prop('checked', $(this).prop('checked'));
            });

            // Individual actions
            $(document).on('click', '.awldlc-recheck', this.recheckLink.bind(this));
            $(document).on('click', '.awldlc-dismiss', this.dismissLink.bind(this));
            $(document).on('click', '.awldlc-undismiss', this.undismissLink.bind(this));
            $(document).on('click', '.awldlc-delete', this.deleteLink.bind(this));
            $(document).on('click', '.awldlc-edit', this.openEditModal.bind(this));
            $(document).on('click', '.awldlc-redirect', this.openRedirectModal.bind(this));

            // Bulk action
            $('#awldlc-bulk-apply').on('click', this.bulkAction.bind(this));

            // Export dropdown
            $('#awldlc-export-btn').on('click', this.toggleExportMenu.bind(this));
            $(document).on('click', '.awldlc-export-option', this.exportLinks.bind(this));
            $(document).on('click', function (e) {
                if (!$(e.target).closest('.awldlc-export-dropdown').length) {
                    $('.awldlc-export-menu').hide();
                }
            });

            // Modal
            $(document).on('click', '.awldlc-modal-close, .awldlc-modal-cancel', this.closeModal.bind(this));
            $('#awldlc-edit-save').on('click', this.saveEdit.bind(this));
            $('#awldlc-remove-link').on('click', this.removeLink.bind(this));
            $('#awldlc-redirect-save').on('click', this.saveRedirect.bind(this));

            // Close modal on outside click
            $(document).on('click', '.awldlc-modal', function (e) {
                if ($(e.target).hasClass('awldlc-modal')) {
                    AWLDLC.closeModal();
                }
            });

            // Close modal on ESC
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    AWLDLC.closeModal();
                }
            });
        },

        initTabs: function () {
            $('.awldlc-tabs-nav a').on('click', function (e) {
                e.preventDefault();
                const target = $(this).attr('href');

                $('.awldlc-tabs-nav a').removeClass('active');
                $(this).addClass('active');

                $('.awldlc-tab-panel').removeClass('active');
                $(target).addClass('active');
            });
        },

        startScan: function (e) {
            e.preventDefault();
            const $btn = $('#awldlc-scan-btn');
            const $stopBtn = $('#awldlc-stop-btn');

            $btn.hide();
            $stopBtn.show();
            $('#awldlc-scan-progress').show();

            $.ajax({
                url: awldlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'awldlc_start_scan',
                    nonce: awldlcAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        AWLDLC.pollProgress();
                    } else {
                        AWLDLC.showToast(response.data || awldlcAdmin.strings.scanFailed, 'error');
                        AWLDLC.resetScanButton();
                    }
                },
                error: function () {
                    AWLDLC.showToast(awldlcAdmin.strings.scanFailed, 'error');
                    AWLDLC.resetScanButton();
                }
            });
        },

        stopScan: function (e) {
            e.preventDefault();
            const $stopBtn = $('#awldlc-stop-btn');

            if (!confirm(awldlcAdmin.strings.confirmStop)) {
                return;
            }

            $stopBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + awldlcAdmin.strings.stopping);

            $.ajax({
                url: awldlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'awldlc_stop_scan',
                    nonce: awldlcAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        AWLDLC.showToast(awldlcAdmin.strings.scanStopped, 'success');
                        AWLDLC.resetScanButton();
                        $('#awldlc-scan-progress').fadeOut();
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        AWLDLC.showToast(response.data || awldlcAdmin.strings.error, 'error');
                        $stopBtn.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> ' + awldlcAdmin.strings.stopScan);
                    }
                },
                error: function () {
                    AWLDLC.showToast(awldlcAdmin.strings.error, 'error');
                    $stopBtn.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> ' + awldlcAdmin.strings.stopScan);
                }
            });
        },

        freshScan: function (e) {
            e.preventDefault();

            // Show confirmation dialog
            if (!confirm(awldlcAdmin.strings.confirmFreshScan)) {
                return;
            }

            const $btn = $('#awldlc-fresh-scan-btn');
            const $scanBtn = $('#awldlc-scan-btn');
            const $stopBtn = $('#awldlc-stop-btn');

            // Disable buttons and show loading state
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + awldlcAdmin.strings.clearing);
            $scanBtn.hide();

            $.ajax({
                url: awldlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'awldlc_fresh_scan',
                    nonce: awldlcAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        AWLDLC.showToast(awldlcAdmin.strings.freshScanStarted, 'success');
                        $btn.hide();
                        $stopBtn.show();
                        $('#awldlc-scan-progress').show();
                        AWLDLC.pollProgress();
                    } else {
                        AWLDLC.showToast(response.data || awldlcAdmin.strings.error, 'error');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> ' + awldlcAdmin.strings.freshScan);
                        $scanBtn.show();
                    }
                },
                error: function () {
                    AWLDLC.showToast(awldlcAdmin.strings.error, 'error');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> ' + awldlcAdmin.strings.freshScan);
                    $scanBtn.show();
                }
            });
        },

        pollProgress: function () {
            $.ajax({
                url: awldlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'awldlc_get_scan_progress',
                    nonce: awldlcAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        const data = response.data;

                        if (data.status === 'completed' || data.status === 'idle' || data.status === 'cancelled') {
                            AWLDLC.showToast(awldlcAdmin.strings.scanComplete, 'success');
                            AWLDLC.resetScanButton();
                            $('#awldlc-scan-progress').fadeOut();
                            setTimeout(function () { location.reload(); }, 1500);
                        } else {
                            // Update progress bar
                            $('.awldlc-progress-fill').css('width', data.percent + '%');
                            $('.awldlc-progress-text').text(
                                awldlcAdmin.strings.progressText
                                    .replace('%1$s', data.checked)
                                    .replace('%2$s', data.total)
                                    .replace('%3$s', data.percent)
                                    .replace('%4$s', data.broken)
                                    .replace('%5$s', data.warnings)
                            );

                            // Poll again
                            setTimeout(AWLDLC.pollProgress, 2000);
                        }
                    } else {
                        setTimeout(AWLDLC.pollProgress, 3000);
                    }
                },
                error: function () {
                    setTimeout(AWLDLC.pollProgress, 5000);
                }
            });
        },

        checkScanStatus: function () {
            // Check if scan is running on page load
            $.ajax({
                url: awldlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'awldlc_get_scan_progress',
                    nonce: awldlcAdmin.nonce
                },
                success: function (response) {
                    if (response.success && response.data.status === 'running') {
                        $('#awldlc-scan-btn').hide();
                        $('#awldlc-stop-btn').show();
                        $('#awldlc-scan-progress').show();
                        $('.awldlc-progress-fill').css('width', response.data.percent + '%');
                        AWLDLC.pollProgress();
                    }
                }
            });
        },

        resetScanButton: function () {
            $('#awldlc-scan-btn').show();
            $('#awldlc-stop-btn').hide().prop('disabled', false).html('<span class="dashicons dashicons-no"></span> ' + awldlcAdmin.strings.stopScan);
        },

        recheckLink: function (e) {
            const $btn = $(e.currentTarget);
            const $row = $btn.closest('tr');
            const linkId = $btn.data('id');

            $btn.find('.dashicons').addClass('spin');

            $.ajax({
                url: awldlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'awldlc_recheck_link',
                    nonce: awldlcAdmin.nonce,
                    link_id: linkId
                },
                success: function (response) {
                    $btn.find('.dashicons').removeClass('spin');
                    if (response.success) {
                        AWLDLC.showToast(response.data.message, 'success');
                        if (response.data.removed) {
                            // Link was fixed/removed from source — fade out the row
                            $row.fadeOut(400, function () {
                                $(this).remove();
                            });
                        } else {
                            setTimeout(function () { location.reload(); }, 1000);
                        }
                    } else {
                        AWLDLC.showToast(response.data || awldlcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    $btn.find('.dashicons').removeClass('spin');
                    AWLDLC.showToast(awldlcAdmin.strings.error, 'error');
                }
            });
        },

        dismissLink: function (e) {
            const $btn = $(e.currentTarget);
            const $row = $btn.closest('tr');
            const linkId = $btn.data('id');

            $.ajax({
                url: awldlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'awldlc_dismiss_link',
                    nonce: awldlcAdmin.nonce,
                    link_id: linkId
                },
                success: function (response) {
                    if (response.success) {
                        $row.fadeOut(300, function () { $(this).remove(); });
                        AWLDLC.showToast(response.data, 'success');
                    } else {
                        AWLDLC.showToast(response.data || awldlcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    AWLDLC.showToast(awldlcAdmin.strings.error, 'error');
                }
            });
        },

        undismissLink: function (e) {
            const $btn = $(e.currentTarget);
            const $row = $btn.closest('tr');
            const linkId = $btn.data('id');

            $.ajax({
                url: awldlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'awldlc_undismiss_link',
                    nonce: awldlcAdmin.nonce,
                    link_id: linkId
                },
                success: function (response) {
                    if (response.success) {
                        $row.fadeOut(300, function () { $(this).remove(); });
                        AWLDLC.showToast(response.data, 'success');
                    } else {
                        AWLDLC.showToast(response.data || awldlcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    AWLDLC.showToast(awldlcAdmin.strings.error, 'error');
                }
            });
        },

        deleteLink: function (e) {
            const $btn = $(e.currentTarget);
            const $row = $btn.closest('tr');
            const linkId = $btn.data('id');

            if (!confirm(awldlcAdmin.strings.confirmDelete)) return;

            $.ajax({
                url: awldlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'awldlc_delete_link',
                    nonce: awldlcAdmin.nonce,
                    link_id: linkId
                },
                success: function (response) {
                    if (response.success) {
                        $row.fadeOut(300, function () { $(this).remove(); });
                        AWLDLC.showToast(response.data, 'success');
                    } else {
                        AWLDLC.showToast(response.data || awldlcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    AWLDLC.showToast(awldlcAdmin.strings.error, 'error');
                }
            });
        },

        openEditModal: function (e) {
            const $btn = $(e.currentTarget);
            const linkId = $btn.data('id');
            const url = $btn.data('url');
            const anchor = $btn.data('anchor') || '';

            $('#awldlc-edit-link-id').val(linkId);
            $('#awldlc-edit-old-url').val(url);
            $('#awldlc-edit-new-url').val('');
            $('#awldlc-edit-anchor-text').val(anchor);
            $('#awldlc-edit-modal').fadeIn(200);
            $('#awldlc-edit-new-url').focus();
        },

        closeModal: function () {
            $('.awldlc-modal').fadeOut(200);
        },

        saveEdit: function () {
            const linkId = $('#awldlc-edit-link-id').val();
            const newUrl = $('#awldlc-edit-new-url').val();
            const newAnchor = $('#awldlc-edit-anchor-text').val();

            if (!newUrl && !newAnchor) {
                AWLDLC.showToast(awldlcAdmin.strings.enterUrlOrAnchor, 'error');
                return;
            }

            $('#awldlc-edit-save').prop('disabled', true).text(awldlcAdmin.strings.processing);

            $.ajax({
                url: awldlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'awldlc_edit_link',
                    nonce: awldlcAdmin.nonce,
                    link_id: linkId,
                    new_url: newUrl,
                    new_anchor_text: newAnchor
                },
                success: function (response) {
                    $('#awldlc-edit-save').prop('disabled', false).text(awldlcAdmin.strings.updateLink);
                    if (response.success) {
                        AWLDLC.closeModal();
                        AWLDLC.showToast(response.data, 'success');
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        AWLDLC.showToast(response.data || awldlcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    $('#awldlc-edit-save').prop('disabled', false).text(awldlcAdmin.strings.updateLink);
                    AWLDLC.showToast(awldlcAdmin.strings.error, 'error');
                }
            });
        },

        removeLink: function () {
            if (!confirm(awldlcAdmin.strings.confirmRemoveLink)) {
                return;
            }

            const linkId = $('#awldlc-edit-link-id').val();
            $('#awldlc-remove-link').prop('disabled', true).text(awldlcAdmin.strings.processing);

            $.ajax({
                url: awldlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'awldlc_remove_link',
                    nonce: awldlcAdmin.nonce,
                    link_id: linkId
                },
                success: function (response) {
                    $('#awldlc-remove-link').prop('disabled', false).text(awldlcAdmin.strings.removeLink);
                    if (response.success) {
                        AWLDLC.closeModal();
                        AWLDLC.showToast(response.data, 'success');
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        AWLDLC.showToast(response.data || awldlcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    $('#awldlc-remove-link').prop('disabled', false).text(awldlcAdmin.strings.removeLink);
                    AWLDLC.showToast(awldlcAdmin.strings.error, 'error');
                }
            });
        },

        openRedirectModal: function (e) {
            const $btn = $(e.currentTarget);
            const linkId = $btn.data('id');
            const url = $btn.data('url');

            $('#awldlc-redirect-link-id').val(linkId);
            $('#awldlc-redirect-source-url').val(url);
            $('#awldlc-redirect-target-url').val('').focus();
            $('#awldlc-redirect-type').val('301');
            $('#awldlc-redirect-modal').fadeIn(200);
        },

        saveRedirect: function () {
            const linkId = $('#awldlc-redirect-link-id').val();
            const sourceUrl = $('#awldlc-redirect-source-url').val();
            const targetUrl = $('#awldlc-redirect-target-url').val();
            const redirectType = $('#awldlc-redirect-type').val();

            if (!targetUrl) {
                AWLDLC.showToast(awldlcAdmin.strings.enterTargetUrl, 'error');
                return;
            }

            $('#awldlc-redirect-save').prop('disabled', true).text(awldlcAdmin.strings.processing);

            $.ajax({
                url: awldlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'awldlc_create_redirect',
                    nonce: awldlcAdmin.nonce,
                    link_id: linkId,
                    source_url: sourceUrl,
                    target_url: targetUrl,
                    redirect_type: redirectType
                },
                success: function (response) {
                    $('#awldlc-redirect-save').prop('disabled', false).text(awldlcAdmin.strings.createRedirect);
                    if (response.success) {
                        AWLDLC.closeModal();
                        AWLDLC.showToast(response.data.message || awldlcAdmin.strings.redirectSuccess, 'success');
                    } else {
                        AWLDLC.showToast(response.data || awldlcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    $('#awldlc-redirect-save').prop('disabled', false).text(awldlcAdmin.strings.createRedirect);
                    AWLDLC.showToast(awldlcAdmin.strings.error, 'error');
                }
            });
        },

        bulkAction: function () {
            const action = $('#awldlc-bulk-action').val();
            const linkIds = [];

            $('.awldlc-link-checkbox:checked').each(function () {
                linkIds.push($(this).val());
            });

            if (!action) {
                AWLDLC.showToast(awldlcAdmin.strings.selectAction, 'error');
                return;
            }

            if (linkIds.length === 0) {
                AWLDLC.showToast(awldlcAdmin.strings.selectLink, 'error');
                return;
            }

            if (action === 'delete' && !confirm(awldlcAdmin.strings.confirmDelete)) {
                return;
            }

            $('#awldlc-bulk-apply').prop('disabled', true).text(awldlcAdmin.strings.processing);

            $.ajax({
                url: awldlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'awldlc_bulk_action',
                    nonce: awldlcAdmin.nonce,
                    bulk_action: action,
                    link_ids: linkIds
                },
                success: function (response) {
                    $('#awldlc-bulk-apply').prop('disabled', false).text(awldlcAdmin.strings.apply);
                    if (response.success) {
                        AWLDLC.showToast(response.data.message, 'success');
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        AWLDLC.showToast(response.data || awldlcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    $('#awldlc-bulk-apply').prop('disabled', false).text(awldlcAdmin.strings.apply);
                    AWLDLC.showToast(awldlcAdmin.strings.error, 'error');
                }
            });
        },

        toggleExportMenu: function (e) {
            e.preventDefault();
            e.stopPropagation();
            $('.awldlc-export-menu').toggle();
        },

        exportLinks: function (e) {
            e.preventDefault();
            const format = $(e.currentTarget).data('format');
            const $btn = $('#awldlc-export-btn');
            const originalText = $btn.html();

            $('.awldlc-export-menu').hide();
            $btn.html('<span class="dashicons dashicons-update spin"></span> ' + awldlcAdmin.strings.exporting);
            $btn.prop('disabled', true);

            $.ajax({
                url: awldlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'awldlc_export_links',
                    nonce: awldlcAdmin.nonce,
                    format: format,
                    status: new URLSearchParams(window.location.search).get('status') || 'all'
                },
                success: function (response) {
                    if (response.success && response.data && response.data.download_url) {
                        AWLDLC.showToast(awldlcAdmin.strings.exportSuccess, 'success');
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
                        var errorMsg = awldlcAdmin.strings.exportFailed;
                        if (response.data) {
                            if (typeof response.data === 'string') {
                                errorMsg = response.data;
                            } else if (response.data.message) {
                                errorMsg = response.data.message;
                            }
                        }
                        AWLDLC.showToast(errorMsg, 'error');
                    }
                },
                error: function () {
                    AWLDLC.showToast(awldlcAdmin.strings.exportFailed, 'error');
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
            $('.awldlc-toast').remove();

            const $toast = $('<div class="awldlc-toast ' + type + '">' + message + '</div>');
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
            if (!confirm(awldlcAdmin.strings.confirmForceStop)) {
                return;
            }
            const $btn = $('#awldlc-force-stop-btn');
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + awldlcAdmin.strings.forceStopping);
            $.ajax({
                url: awldlcAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'awldlc_force_stop_scan', nonce: awldlcAdmin.nonce },
                success: function (response) {
                    if (response.success) {
                        AWLDLC.showToast(response.data || awldlcAdmin.strings.allScansForceStopped, 'success');
                        setTimeout(function () { location.reload(); }, 1500);
                    } else {
                        AWLDLC.showToast(response.data || awldlcAdmin.strings.failedForceStop, 'error');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-dismiss"></span> ' + awldlcAdmin.strings.forceStop);
                    }
                },
                error: function () {
                    AWLDLC.showToast(awldlcAdmin.strings.error, 'error');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-dismiss"></span> ' + awldlcAdmin.strings.forceStop);
                }
            });
        },

        /**
         * Reset Settings to Defaults
         */
        resetSettings: function (e) {
            e.preventDefault();
            if (!confirm(awldlcAdmin.strings.confirmResetSettings)) {
                return;
            }
            const $btn = $('#awldlc-reset-settings-btn');
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + awldlcAdmin.strings.resetting);
            $.ajax({
                url: awldlcAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'awldlc_reset_settings', nonce: awldlcAdmin.nonce },
                success: function (response) {
                    if (response.success) {
                        AWLDLC.showToast(response.data || awldlcAdmin.strings.settingsResetDefaults, 'success');
                        setTimeout(function () { location.reload(); }, 1500);
                    } else {
                        AWLDLC.showToast(response.data || awldlcAdmin.strings.failedResetSettings, 'error');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-undo"></span> ' + awldlcAdmin.strings.resetSettings);
                    }
                },
                error: function () {
                    AWLDLC.showToast(awldlcAdmin.strings.error, 'error');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-undo"></span> ' + awldlcAdmin.strings.resetSettings);
                }
            });
        },

        /**
         * Clear Scan History
         */
        clearScanHistory: function (e) {
            e.preventDefault();
            if (!confirm(awldlcAdmin.strings.confirmClearHistory)) {
                return;
            }
            const $btn = $('#awldlc-clear-history-btn');
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + awldlcAdmin.strings.clearing);
            $.ajax({
                url: awldlcAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'awldlc_clear_scan_history', nonce: awldlcAdmin.nonce },
                success: function (response) {
                    if (response.success) {
                        AWLDLC.showToast(response.data || awldlcAdmin.strings.scanHistoryCleared, 'success');
                        setTimeout(function () { location.reload(); }, 1500);
                    } else {
                        AWLDLC.showToast(response.data || awldlcAdmin.strings.failedClearHistory, 'error');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + awldlcAdmin.strings.clearHistory);
                    }
                },
                error: function () {
                    AWLDLC.showToast(awldlcAdmin.strings.error, 'error');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + awldlcAdmin.strings.clearHistory);
                }
            });
        },

        /**
         * Full Plugin Reset
         */
        fullReset: function (e) {
            e.preventDefault();
            if (!confirm(awldlcAdmin.strings.confirmFullReset)) {
                return;
            }
            // Double confirmation for destructive action
            if (!confirm(awldlcAdmin.strings.confirmFullResetDouble)) {
                return;
            }
            const $btn = $('#awldlc-full-reset-btn');
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + awldlcAdmin.strings.resettingEverything);
            $.ajax({
                url: awldlcAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'awldlc_full_reset', nonce: awldlcAdmin.nonce },
                success: function (response) {
                    if (response.success) {
                        AWLDLC.showToast(response.data || awldlcAdmin.strings.pluginFullyReset, 'success');
                        setTimeout(function () { location.reload(); }, 2000);
                    } else {
                        AWLDLC.showToast(response.data || awldlcAdmin.strings.failedResetPlugin, 'error');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-warning"></span> ' + awldlcAdmin.strings.fullReset);
                    }
                },
                error: function () {
                    AWLDLC.showToast(awldlcAdmin.strings.error, 'error');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-warning"></span> ' + awldlcAdmin.strings.fullReset);
                }
            });
        },

        /**
         * Cleanup Export Files
         */
        cleanupExports: function (e) {
            e.preventDefault();
            if (!confirm(awldlcAdmin.strings.confirmCleanupExports)) {
                return;
            }
            const $btn = $('#awldlc-cleanup-exports-btn');
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + awldlcAdmin.strings.cleaning);
            $.ajax({
                url: awldlcAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'awldlc_cleanup_exports', nonce: awldlcAdmin.nonce },
                success: function (response) {
                    if (response.success) {
                        AWLDLC.showToast(response.data || awldlcAdmin.strings.exportFilesCleaned, 'success');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + awldlcAdmin.strings.cleanupExports);
                    } else {
                        AWLDLC.showToast(response.data || awldlcAdmin.strings.failedCleanupExports, 'error');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + awldlcAdmin.strings.cleanupExports);
                    }
                },
                error: function () {
                    AWLDLC.showToast(awldlcAdmin.strings.error, 'error');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + awldlcAdmin.strings.cleanupExports);
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        AWLDLC.init();
    });

    // Add spin animation
    $('<style>.spin { animation: awldlc-spin 1s linear infinite; } @keyframes awldlc-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>').appendTo('head');

})(jQuery);
