<?php
/*
Plugin Name: WP Easy GCalendar
Description: A plugin to display Google Calendar events in list or calendar layout.
Version: 1.1
Author: Alfe Caesar Lagas
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Add settings page
function gcal_add_settings_page() {
    add_options_page('WP Easy GCalendar', 'WP Easy GCalendar', 'manage_options', 'gcal-settings', 'gcal_render_settings_page');
}
add_action('admin_menu', 'gcal_add_settings_page');

// Register settings
function gcal_register_settings() {
    register_setting('gcal_settings_group', 'gcal_api_key');
    register_setting('gcal_settings_group', 'gcal_calendar_id');
}
add_action('admin_init', 'gcal_register_settings');

// Render settings page
function gcal_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>WP Easy GCalendar Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('gcal_settings_group'); ?>
            <?php do_settings_sections('gcal_settings_group'); ?>
            <table class="form-table">
                <tr>
                    <th>Google API Key:</th>
                    <td><input type="text" name="gcal_api_key" value="<?php echo esc_attr(get_option('gcal_api_key')); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th>Calendar ID:</th>
                    <td><input type="text" name="gcal_calendar_id" value="<?php echo esc_attr(get_option('gcal_calendar_id')); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <td colspan="2"><hr /></td>
                </tr>
                <tr>
                    <th>List View Shortcode:</th>
                    <td>
                        [gcal_list] - parameters: 'show' : 'all' OR 'upcoming'  || 'limit' : integer <br /> 
                        [gcal_list show="all" limit="5"] || [gcal_list show="upcoming" limit="3"]
                    </td>
                </tr>
                <tr>
                    <th>Calendar View Shortcode:</th>
                    <td>[gcal_calendar]</td>
                </tr>
                <tr>
                    <td colspan="2"><hr /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}


// Get WordPress Timezone
function gcal_get_wp_timezone() {
    $wp_timezone = get_option('timezone_string');
    if (!$wp_timezone) {
        $gmt_offset = get_option('gmt_offset');
        $wp_timezone = timezone_name_from_abbr('', $gmt_offset * 3600, 0);
    }
    return $wp_timezone;
}

// Shortcode for rendering events in list format
function gcal_render_list($atts) {
    $atts = shortcode_atts([
        'show' => 'all', // Options: 'all', 'upcoming'
        'limit' => 10 // Default limit of events
    ], $atts);
    
    $api_key = get_option('gcal_api_key');
    $calendar_id = get_option('gcal_calendar_id');
    $wp_timezone = gcal_get_wp_timezone();
    
    if (!$api_key || !$calendar_id) {
        return '<p>Please configure the API Key and Calendar ID in the settings.</p>';
    }
    
    ob_start();
    ?>
    <div id="gcal-list" 
         data-api-key="<?php echo esc_attr($api_key); ?>" 
         data-calendar-id="<?php echo esc_attr($calendar_id); ?>" 
         data-show="<?php echo esc_attr($atts['show']); ?>" 
         data-limit="<?php echo esc_attr($atts['limit']); ?>"
         data-timezone="<?php echo esc_attr($wp_timezone); ?>">
        <p>Loading events...</p>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('gcal-list');
            const apiKey = container.dataset.apiKey;
            const calendarId = container.dataset.calendarId;
            const show = container.dataset.show;
            const limit = parseInt(container.dataset.limit, 10);
            const timezone = container.dataset.timezone;
            const now = new Date().toISOString();
            
            let apiUrl = `https://www.googleapis.com/calendar/v3/calendars/${calendarId}/events?key=${apiKey}&orderBy=startTime&singleEvents=true`;
            
            if (show === 'upcoming') {
                apiUrl += `&timeMin=${encodeURIComponent(now)}`;
            }
            
            fetch(apiUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP Error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log("API Response:", data);
                    
                    if (!data.items || data.items.length === 0) {
                        container.innerHTML = '<p>No events found.</p>';
                        return;
                    }
                    
                    let events = data.items.filter(event => event.start);
                    
                    if (!isNaN(limit) && limit > 0) {
                        events = events.slice(0, limit);
                    }
                    
                    let html = '<ul>';
                    events.forEach(event => {
                        let start = event.start.dateTime || event.start.date + 'T00:00:00';
                        let end = event.end.dateTime || event.end.date + 'T23:59:59';
                        let startDate = new Date(start).toLocaleString('en-US', { timeZone: timezone });
                        let endDate = new Date(end).toLocaleString('en-US', { timeZone: timezone });
                        
                        html += `<li><strong>${event.summary || 'No Title'}</strong> | ${startDate} - ${endDate}</li>`;
                    });
                    html += '</ul>';
                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error("Fetch Error:", error.message);
                    container.innerHTML = `<p>Error fetching events: ${error.message}</p>`;
                });
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('gcal_list', 'gcal_render_list');


// Shortcode for rendering events in calendar format
function gcal_render_calendar() {
    $api_key = get_option('gcal_api_key');
    $calendar_id = get_option('gcal_calendar_id');
    $wp_timezone = gcal_get_wp_timezone();
    
    if (!$api_key || !$calendar_id) {
        return '<p>Please configure the API Key and Calendar ID in the settings.</p>';
    }
    
    ob_start();
    ?>
    <div id="gcal-calendar" data-api-key="<?php echo esc_attr($api_key); ?>" data-calendar-id="<?php echo esc_attr($calendar_id); ?>" data-timezone="<?php echo esc_attr($wp_timezone); ?>"></div>
    <script type="text/javascript" src="https://apis.google.com/js/api.js" id="google-api-js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js" id="moment-js-js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.js" id="fullcalendar-js-js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/@fullcalendar/google-calendar@6.1.9/index.global.min.js" id="fullcalendargcal-js-js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('gcal-calendar');
            const apiKey = container.dataset.apiKey;
            const calendarId = container.dataset.calendarId;
            const timezone = container.dataset.timezone;
            
            fetch(`https://www.googleapis.com/calendar/v3/calendars/${calendarId}/events?key=${apiKey}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP Error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log("API Response:", data);
                    
                    if (!data.items || data.items.length === 0) {
                        container.innerHTML = '<p>No events found.</p>';
                        return;
                    }
                    
                    let events = data.items.filter(event => event.start).map(event => ({
                        title: event.summary || "No Title",
                        start: event.start.dateTime || event.start.date
                    }));
                    
                    let calendar = new FullCalendar.Calendar(container, {
                        timeZone: timezone,
                        googleCalendarApiKey: apiKey, 
                        headerToolbar: {
                            start: 'prev,next today',
                            center: 'title',
                            end: 'dayGridMonth,timeGridWeek,timeGridDay,listYear'
                        },
                        events: calendarId
                    });
                    calendar.render();
                })
                .catch(error => {
                    console.error("Fetch Error:", error.message);
                    container.innerHTML = `<p>Error fetching events: ${error.message}</p>`;
                });
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('gcal_calendar', 'gcal_render_calendar');
?>
