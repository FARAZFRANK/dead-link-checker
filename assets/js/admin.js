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
            $(document).on('click', '#frankdlc-cleanup-exports-btn', this.cleanupExports.bind(this));

            // Select all checkbox
            $('#frankdlc-select-all').on('change', function () {
                $('.frankdlc-link-checkbox').prop('checked', $(this).prop('checked'));
            });

            // Individual actions
            $(document).on('click', '.frankdlc-recheck', this.recheckLink.bind(this));
            $(document).on('click', '.frankdlc-dismiss', this.dismissLink.bind(this));
            $(document).on('click', '.frankdlc-undismiss', this.undismissLink.bind(this));
            $(document).on('click', '.frankdlc-delete', this.deleteLink.bind(this));
            $(document).on('click', '.frankdlc-edit', this.openEditModal.bind(this));
            $(document).on('click', '.frankdlc-redirect', this.openRedirectModal.bind(this));

            // Bulk action
            $('#frankdlc-bulk-apply').on('click', this.bulkAction.bind(this));

            // Export dropdown
            $('#frankdlc-export-btn').on('click', this.toggleExportMenu.bind(this));
            $(document).on('click', '.frankdlc-export-option', this.exportLinks.bind(this));
            $(document).on('click', function (e) {
                if (!$(e.target).closest('.frankdlc-export-dropdown').length) {
                    $('.frankdlc-export-menu').hide();
                }
            });

            // Modal
            $(document).on('click', '.frankdlc-modal-close, .frankdlc-modal-cancel', this.closeModal.bind(this));
            $('#frankdlc-edit-save').on('click', this.saveEdit.bind(this));
            $('#frankdlc-remove-link').on('click', this.removeLink.bind(this));
            $('#frankdlc-redirect-save').on('click', this.saveRedirect.bind(this));

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
                    action: 'frankdlc_start_scan',
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

            if (!confirm(frankdlcAdmin.strings.confirmStop)) {
                return;
            }

            $stopBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + frankdlcAdmin.strings.stopping);

            $.ajax({
                url: frankdlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'frankdlc_stop_scan',
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
        },

        freshScan: function (e) {
            e.preventDefault();

            // Show confirmation dialog
            if (!confirm(frankdlcAdmin.strings.confirmFreshScan)) {
                return;
            }

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
                    action: 'frankdlc_fresh_scan',
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
        },

        pollProgress: function () {
            $.ajax({
                url: frankdlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'frankdlc_get_scan_progress',
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
                    action: 'frankdlc_get_scan_progress',
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

        recheckLink: function (e) {
            const $btn = $(e.currentTarget);
            const $row = $btn.closest('tr');
            const linkId = $btn.data('id');

            $btn.find('.dashicons').addClass('spin');

            $.ajax({
                url: frankdlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'frankdlc_recheck_link',
                    nonce: frankdlcAdmin.nonce,
                    link_id: linkId
                },
                success: function (response) {
                    $btn.find('.dashicons').removeClass('spin');
                    if (response.success) {
                        FRANKDLC.showToast(response.data.message, 'success');
                        if (response.data.removed) {
                            // Link was fixed/removed from source — fade out the row
                            $row.fadeOut(400, function () {
                                $(this).remove();
                            });
                        } else {
                            setTimeout(function () { location.reload(); }, 1000);
                        }
                    } else {
                        FRANKDLC.showToast(response.data || frankdlcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    $btn.find('.dashicons').removeClass('spin');
                    FRANKDLC.showToast(frankdlcAdmin.strings.error, 'error');
                }
            });
        },

        dismissLink: function (e) {
            const $btn = $(e.currentTarget);
            const $row = $btn.closest('tr');
            const linkId = $btn.data('id');

            $.ajax({
                url: frankdlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'frankdlc_dismiss_link',
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
                    action: 'frankdlc_undismiss_link',
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

            if (!confirm(frankdlcAdmin.strings.confirmDelete)) return;

            $.ajax({
                url: frankdlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'frankdlc_delete_link',
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

        openEditModal: function (e) {
            const $btn = $(e.currentTarget);
            const linkId = $btn.data('id');
            const url = $btn.data('url');
            const anchor = $btn.data('anchor') || '';

            $('#frankdlc-edit-link-id').val(linkId);
            $('#frankdlc-edit-old-url').val(url);
            $('#frankdlc-edit-new-url').val('');
            $('#frankdlc-edit-anchor-text').val(anchor);
            $('#frankdlc-edit-modal').fadeIn(200);
            $('#frankdlc-edit-new-url').focus();
        },

        closeModal: function () {
            $('.frankdlc-modal').fadeOut(200);
        },

        saveEdit: function () {
            const linkId = $('#frankdlc-edit-link-id').val();
            const newUrl = $('#frankdlc-edit-new-url').val();
            const newAnchor = $('#frankdlc-edit-anchor-text').val();

            if (!newUrl && !newAnchor) {
                FRANKDLC.showToast(frankdlcAdmin.strings.enterUrlOrAnchor, 'error');
                return;
            }

            $('#frankdlc-edit-save').prop('disabled', true).text(frankdlcAdmin.strings.processing);

            $.ajax({
                url: frankdlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'frankdlc_edit_link',
                    nonce: frankdlcAdmin.nonce,
                    link_id: linkId,
                    new_url: newUrl,
                    new_anchor_text: newAnchor
                },
                success: function (response) {
                    $('#frankdlc-edit-save').prop('disabled', false).text(frankdlcAdmin.strings.updateLink);
                    if (response.success) {
                        FRANKDLC.closeModal();
                        FRANKDLC.showToast(response.data, 'success');
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        FRANKDLC.showToast(response.data || frankdlcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    $('#frankdlc-edit-save').prop('disabled', false).text(frankdlcAdmin.strings.updateLink);
                    FRANKDLC.showToast(frankdlcAdmin.strings.error, 'error');
                }
            });
        },

        removeLink: function () {
            if (!confirm(frankdlcAdmin.strings.confirmRemoveLink)) {
                return;
            }

            const linkId = $('#frankdlc-edit-link-id').val();
            $('#frankdlc-remove-link').prop('disabled', true).text(frankdlcAdmin.strings.processing);

            $.ajax({
                url: frankdlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'frankdlc_remove_link',
                    nonce: frankdlcAdmin.nonce,
                    link_id: linkId
                },
                success: function (response) {
                    $('#frankdlc-remove-link').prop('disabled', false).text(frankdlcAdmin.strings.removeLink);
                    if (response.success) {
                        FRANKDLC.closeModal();
                        FRANKDLC.showToast(response.data, 'success');
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        FRANKDLC.showToast(response.data || frankdlcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    $('#frankdlc-remove-link').prop('disabled', false).text(frankdlcAdmin.strings.removeLink);
                    FRANKDLC.showToast(frankdlcAdmin.strings.error, 'error');
                }
            });
        },

        openRedirectModal: function (e) {
            const $btn = $(e.currentTarget);
            const linkId = $btn.data('id');
            const url = $btn.data('url');

            $('#frankdlc-redirect-link-id').val(linkId);
            $('#frankdlc-redirect-source-url').val(url);
            $('#frankdlc-redirect-target-url').val('').focus();
            $('#frankdlc-redirect-type').val('301');
            $('#frankdlc-redirect-modal').fadeIn(200);
        },

        saveRedirect: function () {
            const linkId = $('#frankdlc-redirect-link-id').val();
            const sourceUrl = $('#frankdlc-redirect-source-url').val();
            const targetUrl = $('#frankdlc-redirect-target-url').val();
            const redirectType = $('#frankdlc-redirect-type').val();

            if (!targetUrl) {
                FRANKDLC.showToast(frankdlcAdmin.strings.enterTargetUrl, 'error');
                return;
            }

            $('#frankdlc-redirect-save').prop('disabled', true).text(frankdlcAdmin.strings.processing);

            $.ajax({
                url: frankdlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'frankdlc_create_redirect',
                    nonce: frankdlcAdmin.nonce,
                    link_id: linkId,
                    source_url: sourceUrl,
                    target_url: targetUrl,
                    redirect_type: redirectType
                },
                success: function (response) {
                    $('#frankdlc-redirect-save').prop('disabled', false).text(frankdlcAdmin.strings.createRedirect);
                    if (response.success) {
                        FRANKDLC.closeModal();
                        FRANKDLC.showToast(response.data.message || frankdlcAdmin.strings.redirectSuccess, 'success');
                    } else {
                        FRANKDLC.showToast(response.data || frankdlcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    $('#frankdlc-redirect-save').prop('disabled', false).text(frankdlcAdmin.strings.createRedirect);
                    FRANKDLC.showToast(frankdlcAdmin.strings.error, 'error');
                }
            });
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

            if (action === 'delete' && !confirm(frankdlcAdmin.strings.confirmDelete)) {
                return;
            }

            $('#frankdlc-bulk-apply').prop('disabled', true).text(frankdlcAdmin.strings.processing);

            $.ajax({
                url: frankdlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'frankdlc_bulk_action',
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

        toggleExportMenu: function (e) {
            e.preventDefault();
            e.stopPropagation();
            $('.frankdlc-export-menu').toggle();
        },

        exportLinks: function (e) {
            e.preventDefault();
            const format = $(e.currentTarget).data('format');
            const $btn = $('#frankdlc-export-btn');
            const originalText = $btn.html();

            $('.frankdlc-export-menu').hide();
            $btn.html('<span class="dashicons dashicons-update spin"></span> ' + frankdlcAdmin.strings.exporting);
            $btn.prop('disabled', true);

            $.ajax({
                url: frankdlcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'frankdlc_export_links',
                    nonce: frankdlcAdmin.nonce,
                    format: format,
                    status: new URLSearchParams(window.location.search).get('status') || 'all'
                },
                success: function (response) {
                    if (response.success && response.data && response.data.download_url) {
                        FRANKDLC.showToast(frankdlcAdmin.strings.exportSuccess, 'success');
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
                        var errorMsg = frankdlcAdmin.strings.exportFailed;
                        if (response.data) {
                            if (typeof response.data === 'string') {
                                errorMsg = response.data;
                            } else if (response.data.message) {
                                errorMsg = response.data.message;
                            }
                        }
                        FRANKDLC.showToast(errorMsg, 'error');
                    }
                },
                error: function () {
                    FRANKDLC.showToast(frankdlcAdmin.strings.exportFailed, 'error');
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
            $('.frankdlc-toast').remove();

            const $toast = $('<div class="frankdlc-toast ' + type + '">' + message + '</div>');
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
            if (!confirm(frankdlcAdmin.strings.confirmForceStop)) {
                return;
            }
            const $btn = $('#frankdlc-force-stop-btn');
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + frankdlcAdmin.strings.forceStopping);
            $.ajax({
                url: frankdlcAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'frankdlc_force_stop_scan', nonce: frankdlcAdmin.nonce },
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
        },

        /**
         * Reset Settings to Defaults
         */
        resetSettings: function (e) {
            e.preventDefault();
            if (!confirm(frankdlcAdmin.strings.confirmResetSettings)) {
                return;
            }
            const $btn = $('#frankdlc-reset-settings-btn');
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + frankdlcAdmin.strings.resetting);
            $.ajax({
                url: frankdlcAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'frankdlc_reset_settings', nonce: frankdlcAdmin.nonce },
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
        },

        /**
         * Clear Scan History
         */
        clearScanHistory: function (e) {
            e.preventDefault();
            if (!confirm(frankdlcAdmin.strings.confirmClearHistory)) {
                return;
            }
            const $btn = $('#frankdlc-clear-history-btn');
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + frankdlcAdmin.strings.clearing);
            $.ajax({
                url: frankdlcAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'frankdlc_clear_scan_history', nonce: frankdlcAdmin.nonce },
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
        },

        /**
         * Full Plugin Reset
         */
        fullReset: function (e) {
            e.preventDefault();
            if (!confirm(frankdlcAdmin.strings.confirmFullReset)) {
                return;
            }
            // Double confirmation for destructive action
            if (!confirm(frankdlcAdmin.strings.confirmFullResetDouble)) {
                return;
            }
            const $btn = $('#frankdlc-full-reset-btn');
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + frankdlcAdmin.strings.resettingEverything);
            $.ajax({
                url: frankdlcAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'frankdlc_full_reset', nonce: frankdlcAdmin.nonce },
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
        },

        /**
         * Cleanup Export Files
         */
        cleanupExports: function (e) {
            e.preventDefault();
            if (!confirm(frankdlcAdmin.strings.confirmCleanupExports)) {
                return;
            }
            const $btn = $('#frankdlc-cleanup-exports-btn');
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + frankdlcAdmin.strings.cleaning);
            $.ajax({
                url: frankdlcAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'frankdlc_cleanup_exports', nonce: frankdlcAdmin.nonce },
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
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        FRANKDLC.init();
    });

    // Add spin animation
    $('<style>.spin { animation: frankdlc-spin 1s linear infinite; } @keyframes frankdlc-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>').appendTo('head');

})(jQuery);
