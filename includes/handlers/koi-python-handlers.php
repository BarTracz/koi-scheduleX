<?php

function koi_run_python_schedule_script($personalities_file, $calendar_file, $params = [])
{
    // --- 0. Security Checks ---
    if (!isset($_POST['run_python_script_nonce']) || !wp_verify_nonce($_POST['run_python_script_nonce'], 'run_python_script_action')) {
        return [
            'success' => false,
            'output'  => 'Security check failed (invalid nonce).',
        ];
    }

    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'koi-schedule'));
    }


    // Default return structure
    $result = [
        'success'      => false,
        'output'       => 'An unknown error occurred.',
        'download_url' => ''
    ];

    // --- 1. Handle File Uploads ---

    // WordPress upload overrides
    $upload_overrides = [
        'test_form' => false,
        'mimes'     => ['csv' => 'text/csv'],
    ];

    // Get upload directory information
    $upload_dir = wp_upload_dir();
    $koi_dir = $upload_dir['basedir'] . '/koi-schedule-files';

    // Secure the upload directory with an index.php and .htaccess file
    if (!file_exists($koi_dir . '/index.php')) {
        if (!is_dir($koi_dir)) {
            wp_mkdir_p($koi_dir);
        }
        file_put_contents($koi_dir . '/index.php', '<?php // Silence is golden.');
    }
    if (!file_exists($koi_dir . '/.htaccess')) {
        if (!is_dir($koi_dir)) {
            wp_mkdir_p($koi_dir);
        }
        file_put_contents($koi_dir . '/.htaccess', 'deny from all');
    }

    wp_mkdir_p($koi_dir); // Create the directory if it doesn't exist

    // Move personalities file
    $personalities_file_info = wp_handle_upload($personalities_file, $upload_overrides);
    if (empty($personalities_file_info['file'])) {
        $result['output'] = 'Error handling personalities file upload: ' . ($personalities_file_info['error'] ?? 'Unknown error');
        return $result;
    }
    $personalities_path = $personalities_file_info['file'];

    // Move calendar file
    $calendar_file_info = wp_handle_upload($calendar_file, $upload_overrides);
    if (empty($calendar_file_info['file'])) {
        // Clean up the first file if the second one fails
        unlink($personalities_path);
        $result['output'] = 'Error handling calendar file upload: ' . ($calendar_file_info['error'] ?? 'Unknown error');
        return $result;
    }
    $calendar_path = $calendar_file_info['file'];

    // --- 2. Prepare to Run Python Script ---

    // Define the output file path and URL
    $base_filename = 'harmonogram-' . date('Y-m-d') . '.csv';
    $output_filename = wp_unique_filename($koi_dir, $base_filename);
    $output_path = $koi_dir . '/' . $output_filename;

    // IMPORTANT: For better security and reliability, use the full, absolute path to your Python executable.
    $python_executable = 'python'; // e.g., 'C:\\Python39\\python.exe'
    $script_path = KOI_SCHEDULE_PATH . 'includes/python/schedule1.py';

    // Ensure the main script exists
    if (!file_exists($script_path)) {
        $result['output'] = "Error: The main Python script was not found.";
        // Clean up uploaded temp files before returning early
        if (file_exists($personalities_path)) {
            unlink($personalities_path);
        }
        if (file_exists($calendar_path)) {
            unlink($calendar_path);
        }
        return $result;
    }

    // Construct the command with arguments
    $command_parts = [
        $python_executable,
        escapeshellarg($script_path),
        '--personalities', escapeshellarg($personalities_path),
        '--calendar', escapeshellarg($calendar_path),
        '--output', escapeshellarg($output_path),
    ];

    // Map PHP param names to Python argument names
    $param_map = [
        'schedule_year' => 'year',
        'schedule_month' => 'month',
    ];

    foreach ($params as $key => $value) {
        $arg_name = $param_map[$key] ?? $key;
        $command_parts[] = '--' . $arg_name;
        $command_parts[] = escapeshellarg($value);
    }
    $command = implode(' ', $command_parts);

    // --- 3. Execute the Script ---

    $descriptorspec = [
       1 => ['pipe', 'w'], // stdout
       2 => ['pipe', 'w']  // stderr
    ];

    $process = proc_open($command, $descriptorspec, $pipes);

    if (is_resource($process)) {
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $return_value = proc_close($process);

        if ($return_value === 0) {
            $result['success'] = true;
            $result['output'] = "Script executed successfully:\n\n" . $stdout;
            $result['output_filename'] = $output_filename; // Add the filename to the result
        } else {
            $result['output'] = "Error executing script (return code: $return_value):\n\n" . $stderr;
        }
    } else {
        $result['output'] = "Error: Could not launch the Python script process.";
    }

    // --- 4. Clean up uploaded temp files ---
    unlink($personalities_path);
    unlink($calendar_path);

    return $result;
}

/**
 * Handles the secure download of a generated schedule file.
 *
 * This function checks for user permissions and a valid nonce before
 * streaming the file, preventing direct access to files in the protected
 * upload directory.
 *
 * @return void
 */
function koi_handle_schedule_download()
{
    // 1. Security Checks
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'koi_download_schedule_nonce')) {
        wp_die('Security check failed.');
    }

    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to download this file.');
    }

    // 2. File Validation
    if (empty($_GET['file'])) {
        wp_die('No file specified.');
    }

    $upload_dir = wp_upload_dir();
    $koi_dir = $upload_dir['basedir'] . '/koi-schedule-files';

    // Sanitize filename to prevent directory traversal
    $filename = sanitize_file_name(basename(urldecode($_GET['file'])));
    $filepath = $koi_dir . '/' . $filename;

    if (!file_exists($filepath)) {
        wp_die('File not found.');
    }

    // 3. Serve the file
    header('Content-Description: File Transfer');
    header('Content-Type: text/csv');
    // Use double quotes for the filename as per RFC 6266 and escape any double quotes within the name.
    $safe_filename = str_replace('"', '\\"', $filename);
    header('Content-Disposition: attachment; filename="' . $safe_filename . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}
add_action('admin_post_koi_download_schedule', 'koi_handle_schedule_download');
