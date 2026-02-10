/**
 * Dead Link Checker Pro - Admin JavaScript
 * Handles all interactive functionality
 */

(function ($) {
    'use strict';

    const BLC = {
        init: function () {
            this.bindEvents();
            this.initTabs();
            this.checkScanStatus();
        },

        bindEvents: function () {
            // Scan button
            $('#blc-scan-btn').on('click', this.startScan.bind(this));

            // Stop scan button
            $('#blc-stop-btn').on('click', this.stopScan.bind(this));

            // Fresh Scan button
            $('#blc-fresh-scan-btn').on('click', this.freshScan.bind(this));

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

            if (!confirm(blcAdmin.strings.confirmStop || 'Are you sure you want to stop the scan?')) {
                return;
            }

            $stopBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Stopping...');

            $.ajax({
                url: blcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'blc_stop_scan',
                    nonce: blcAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        BLC.showToast(blcAdmin.strings.scanStopped || 'Scan stopped.', 'success');
                        BLC.resetScanButton();
                        $('#blc-scan-progress').fadeOut();
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        BLC.showToast(response.data || blcAdmin.strings.error, 'error');
                        $stopBtn.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> Stop Scan');
                    }
                },
                error: function () {
                    BLC.showToast(blcAdmin.strings.error, 'error');
                    $stopBtn.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> Stop Scan');
                }
            });
        },

        freshScan: function (e) {
            e.preventDefault();

            // Show confirmation dialog
            if (!confirm(blcAdmin.strings.confirmFreshScan || 'This will DELETE all existing link data and scan history, then start a fresh scan. Are you sure?')) {
                return;
            }

            const $btn = $('#blc-fresh-scan-btn');
            const $scanBtn = $('#blc-scan-btn');
            const $stopBtn = $('#blc-stop-btn');

            // Disable buttons and show loading state
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Clearing...');
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
                        BLC.showToast(blcAdmin.strings.freshScanStarted || 'All data cleared. Fresh scan started.', 'success');
                        $btn.hide();
                        $stopBtn.show();
                        $('#blc-scan-progress').show();
                        BLC.pollProgress();
                    } else {
                        BLC.showToast(response.data || blcAdmin.strings.error, 'error');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Fresh Scan');
                        $scanBtn.show();
                    }
                },
                error: function () {
                    BLC.showToast(blcAdmin.strings.error, 'error');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Fresh Scan');
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
                                'Checked ' + data.checked + ' of ' + data.total + ' links (' + data.percent + '%) - ' +
                                data.broken + ' broken, ' + data.warnings + ' warnings'
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
            $('#blc-stop-btn').hide().prop('disabled', false).html('<span class="dashicons dashicons-no"></span> Stop Scan');
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
                        setTimeout(function () { location.reload(); }, 1000);
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

            $('#blc-edit-link-id').val(linkId);
            $('#blc-edit-old-url').val(url);
            $('#blc-edit-new-url').val('').focus();
            $('#blc-edit-modal').fadeIn(200);
        },

        closeModal: function () {
            $('.blc-modal').fadeOut(200);
        },

        saveEdit: function () {
            const linkId = $('#blc-edit-link-id').val();
            const newUrl = $('#blc-edit-new-url').val();

            if (!newUrl) {
                BLC.showToast('Please enter a new URL', 'error');
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
                    new_url: newUrl
                },
                success: function (response) {
                    $('#blc-edit-save').prop('disabled', false).text('Update Link');
                    if (response.success) {
                        BLC.closeModal();
                        BLC.showToast(response.data, 'success');
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        BLC.showToast(response.data || blcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    $('#blc-edit-save').prop('disabled', false).text('Update Link');
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
                BLC.showToast('Please enter a target URL', 'error');
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
                    $('#blc-redirect-save').prop('disabled', false).text('Create Redirect');
                    if (response.success) {
                        BLC.closeModal();
                        BLC.showToast(response.data.message || 'Redirect created successfully!', 'success');
                    } else {
                        BLC.showToast(response.data || blcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    $('#blc-redirect-save').prop('disabled', false).text('Create Redirect');
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
                BLC.showToast('Please select an action', 'error');
                return;
            }

            if (linkIds.length === 0) {
                BLC.showToast('Please select at least one link', 'error');
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
                    $('#blc-bulk-apply').prop('disabled', false).text('Apply');
                    if (response.success) {
                        BLC.showToast(response.data.message, 'success');
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        BLC.showToast(response.data || blcAdmin.strings.error, 'error');
                    }
                },
                error: function () {
                    $('#blc-bulk-apply').prop('disabled', false).text('Apply');
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
                        BLC.showToast(blcAdmin.strings.exportSuccess || 'Export created successfully!', 'success');
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
                        var errorMsg = blcAdmin.strings.exportFailed || 'Export failed';
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
                    BLC.showToast(blcAdmin.strings.exportFailed || 'Export failed', 'error');
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
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        BLC.init();
    });

    // Add spin animation
    $('<style>.spin { animation: blc-spin 1s linear infinite; } @keyframes blc-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>').appendTo('head');

})(jQuery);
