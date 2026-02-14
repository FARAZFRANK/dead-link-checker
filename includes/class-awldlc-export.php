<?php
/**
 * Export Handler
 *
 * Exports links to CSV or JSON format.
 *
 * @package DeadLinkCheckerPro
 * @since 3.0.0
 */

defined('ABSPATH') || exit;

class AWLDLC_Export
{

    /**
     * Export links to file
     *
     * @param string $format Export format (csv or json)
     * @param array  $args   Query arguments for links
     * @return string|WP_Error File URL on success, WP_Error on failure
     */
    public function export($format = 'csv', $args = array())
    {
        $links = awldlc()->database->get_links(array_merge($args, array('per_page' => 10000)));

        if (empty($links)) {
            return new WP_Error('no_data', __('No links to export.', 'dead-link-checker'));
        }

        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/dlc-exports';

        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
            file_put_contents($export_dir . '/index.php', '<?php // Silence is golden');
        }

        $filename = 'dlc-export-' . gmdate('Y-m-d-His') . '.' . $format;
        $filepath = $export_dir . '/' . $filename;

        if ($format === 'json') {
            $result = $this->export_json($links, $filepath);
        } else {
            $result = $this->export_csv($links, $filepath);
        }

        if (!$result) {
            return new WP_Error('export_failed', __('Failed to create export file.', 'dead-link-checker'));
        }

        return $upload_dir['baseurl'] . '/dlc-exports/' . $filename;
    }

    /**
     * Export links to CSV format
     *
     * @param array  $links    Array of link objects
     * @param string $filepath Path to save the file
     * @return bool True on success, false on failure
     */
    private function export_csv($links, $filepath)
    {
        $handle = fopen($filepath, 'w');
        if (!$handle) {
            return false;
        }

        // UTF-8 BOM for Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        // Header row
        fputcsv($handle, array(
            'ID',
            'URL',
            'Status Code',
            'Status',
            'Is Broken',
            'Is Warning',
            'Link Type',
            'Source ID',
            'Source Type',
            'Source Title',
            'Anchor Text',
            'Redirect URL',
            'Redirect Count',
            'Response Time',
            'Last Check',
            'First Detected',
            'Error Message'
        ));

        foreach ($links as $link_data) {
            $link = new AWLDLC_Link($link_data);

            fputcsv($handle, array(
                $link->id,
                $link->url,
                $link->status_code,
                $link->status_text,
                $link->is_broken ? 'Yes' : 'No',
                $link->is_warning ? 'Yes' : 'No',
                $link->get_type_label(),
                $link->source_id,
                $link->source_type,
                $link->get_source_title(),
                $link->anchor_text,
                $link->redirect_url,
                $link->redirect_count,
                $link->response_time,
                $link->last_check,
                $link->first_detected,
                $link->error_message,
            ));
        }

        fclose($handle);
        return true;
    }

    /**
     * Export links to JSON format
     *
     * @param array  $links    Array of link objects
     * @param string $filepath Path to save the file
     * @return bool True on success, false on failure
     */
    private function export_json($links, $filepath)
    {
        $data = array();

        foreach ($links as $link_data) {
            $link = new AWLDLC_Link($link_data);

            $data[] = array(
                'id' => $link->id,
                'url' => $link->url,
                'status_code' => $link->status_code,
                'status_text' => $link->status_text,
                'is_broken' => $link->is_broken,
                'is_warning' => $link->is_warning,
                'link_type' => $link->link_type,
                'source_id' => $link->source_id,
                'source_type' => $link->source_type,
                'source_title' => $link->get_source_title(),
                'anchor_text' => $link->anchor_text,
                'redirect_url' => $link->redirect_url,
                'redirect_count' => $link->redirect_count,
                'response_time' => $link->response_time,
                'last_check' => $link->last_check,
                'first_detected' => $link->first_detected,
                'error_message' => $link->error_message,
            );
        }

        $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return file_put_contents($filepath, $json) !== false;
    }
}
