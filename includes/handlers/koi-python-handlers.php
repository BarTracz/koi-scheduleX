<?php

function koi_run_python_schedule_script($personalities_file, $calendar_file, $params = [])
{
    // Default return structure
    $result = [
        'success'      => false,
        'output'       => 'An unknown error occurred.',
        'download_url' => ''
    ];

    // --- 1. Handle File Uploads ---

    // WordPress upload overrides
    $upload_overrides = ['test_form' => false];

    // Get upload directory information
    $upload_dir = wp_upload_dir();
    $koi_dir = $upload_dir['basedir'] . '/koi-schedule-files';
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
    $output_filename = 'harmonogram.csv';
    $output_path = $koi_dir . '/' . $output_filename;
    $output_url = $upload_dir['baseurl'] . '/koi-schedule-files/' . $output_filename;

    // IMPORTANT: Full path to your Python executable
    $python_executable = 'python'; // e.g., 'C:\\Python39\\python.exe'
    $script_path = KOI_SCHEDULE_PATH . 'includes/python/schedule1.py';

    // Ensure the main script exists
    if (!file_exists($script_path)) {
        $result['output'] = "Error: The main Python script was not found.";
        return $result;
    }

    // Construct the command with arguments
    $command_parts = [
        escapeshellcmd($python_executable),
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
            $result['download_url'] = $output_url;
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
