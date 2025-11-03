<?php

function koi_python_runner_page()
{
    ?>
    <div class="wrap">
        <h1>Python Schedule Generator</h1>
        <p>Upload your files and run the script to generate the schedule.</p>
        
        <form method="post" action="" enctype="multipart/form-data">
            <input type="hidden" name="run_python_script" value="1">
            
            <h2>1. Upload Files</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Personalities File (.csv)</th>
                    <td><input type="file" name="personalities_file" accept=".csv" required></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Calendar File (.csv)</th>
                    <td><input type="file" name="calendar_file" accept=".csv" required></td>
                </tr>
            </table>

            <h2>2. Schedule Parameters</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="schedule_month">Month</label></th>
                    <td>
                        <select name="schedule_month" id="schedule_month" required>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php selected(date('n'), $m); ?>><?php echo date('F', mktime(0, 0, 0, $m, 10)); ?></option>
                            <?php endfor; ?>
                        </select>
                        <p class="description">Select the month for which you want to generate the schedule.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="schedule_year">Year</label></th>
                    <td>
                        <input type="number" name="schedule_year" id="schedule_year" value="<?php echo date('Y'); ?>" min="2020" max="2099" required>
                        <p class="description">Enter the year for which you want to generate the schedule.</p>
                    </td>
                </tr>
            </table>

            <h2>3. Scheduling Rules</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="stream_duration">Stream duration (in hours)</label></th>
                    <td><input type="number" name="stream_duration" id="stream_duration" value="3" min="1" required>
                        <p class="description">The default duration of a single stream.</p></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="max_concurrent_streams">Max concurrent streams</label></th>
                    <td><input type="number" name="max_concurrent_streams" id="max_concurrent_streams" value="2" min="1" required>
                        <p class="description">The maximum number of streamers who can broadcast in the same time slot.</p></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="min_streams_per_day">Min streams per day</label></th>
                    <td><input type="number" name="min_streams_per_day" id="min_streams_per_day" value="3" min="1" required>
                        <p class="description">The minimum total number of streams that should take place each day.</p></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="max_streams_per_streamer">Max streams per streamer per month</label></th>
                    <td><input type="number" name="max_streams_per_streamer" id="max_streams_per_streamer" value="16" min="1" required>
                        <p class="description">The maximum number of streams a single streamer can have in the generated monthly schedule.</p></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="min_streams_per_bucket">Min streams per "bucket"</label></th>
                    <td><input type="number" name="min_streams_per_bucket" id="min_streams_per_bucket" value="5" min="1" required>
                        <p class="description">The minimum number of streams each streamer should have in each time of day bucket (e.g., 5 morning, 5 afternoon, 5 evening).</p></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="min_streams_per_week">Min streams per week</label></th>
                    <td><input type="number" name="min_streams_per_week" id="min_streams_per_week" value="2" min="0" required>
                        <p class="description">The minimum number of streams each streamer should have in each week of the month. Set to 0 to disable.</p></td>
                </tr>
            </table>

            <?php submit_button('Generate Schedule'); ?>
        </form>
        
        <?php
        // Check if the form was submitted and files were uploaded
        if (isset($_POST['run_python_script']) && !empty($_FILES['personalities_file']['tmp_name']) && !empty($_FILES['calendar_file']['tmp_name'])) {
            // Call the handler function, passing the uploaded files and other parameters
            $params = array_intersect_key($_POST, array_flip(['schedule_month', 'schedule_year', 'stream_duration', 'max_concurrent_streams', 'min_streams_per_day', 'max_streams_per_streamer', 'min_streams_per_bucket', 'min_streams_per_week']));
            $result = koi_run_python_schedule_script($_FILES['personalities_file'], $_FILES['calendar_file'], $params);
            
            echo '<h2>Script Output:</h2>';
            echo '<pre>' . esc_textarea($result['output']) . '</pre>';

            // If the script was successful, show the download link
            if ($result['success']) {
                echo '<h2>Download Schedule</h2>';
                echo '<p><a href="' . esc_url($result['download_url']) . '" class="button button-primary">Download harmonogram.csv</a></p>';
            }
        } elseif (isset($_POST['run_python_script'])) {
            echo '<div class="error"><p>Please select both files before running the script.</p></div>';
        }
        ?>
    </div>
    <?php
}
