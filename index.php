<?php
/*
Plugin Name: WP Easy GCalendar
Description: A plugin to display Google Calendar events in list or calendar layout.
Version: 1.2
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
                    //console.log("API Response:", data);
                    
                    if (!data.items || data.items.length === 0) {
                        container.innerHTML = '<p>No events found.</p>';
                        return;
                    }
                    
                    let events = data.items.filter(event => event.start);
                    
                    if (!isNaN(limit) && limit > 0) {
                        events = events.slice(0, limit);
                    }

                    console.log('list events',events);
                    
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
    <script src='https://cdn.jsdelivr.net/npm/rrule@2.6.4/dist/es5/rrule.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/rrule@6.1.15/index.global.min.js'></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('gcal-calendar');
            const apiKey = container.dataset.apiKey;
            const calendarId = container.dataset.calendarId;
            const timezone = container.dataset.timezone;
            const now = new Date();
            const startOfYear = new Date(now.getFullYear(), - 1, 0, 1).toISOString();
            const nextYear = new Date(now.getFullYear() + 1, 11, 31).toISOString();

            console.log(nextYear)
            
            async function fetchEvents() {
                const url = `https://www.googleapis.com/calendar/v3/calendars/${encodeURIComponent(calendarId)}/events?key=${apiKey}&timeMin=${startOfYear}&timeMax=${nextYear}&singleEvents=true&orderBy=startTime&maxResults=9000`;
                try {
                    const response = await fetch(url);
                    const data = await response.json();

                    console.log("API Response:", data); // Log the entire response

                    if (data.error) {
                        console.error("Google API Error:", data.error);
                        alert(`Google API Error: ${data.error.message}`); // Display error to the user
                        return [];
                    }

                    if (!data.items) {
                        throw new Error("No events found or API request failed.");
                    }

                    return data.items.map(event => {
                        let eventObject = {
                            title: event.summary,
                            start: event.start.dateTime || event.start.date,
                            end: event.end.dateTime || event.end.date,
                            allDay: !event.start.dateTime,
                            url: event.htmlLink
                        };

                        if (event.recurrence) {
                            eventObject.rrule = event.recurrence[0].replace("RRULE:", "");
                        }

                        return eventObject;
                    });
                } catch (error) {
                    console.error("Error fetching events:", error);
                    return [];
                }
            }

            
            fetchEvents().then(events => {
                let calendar = new FullCalendar.Calendar(container, {
                    timeZone: timezone,
                    headerToolbar: {
                        start: 'prev,next today',
                        center: 'title',
                        end: 'dayGridMonth,timeGridWeek,timeGridDay,listYear'
                    },
                    events: events,
                });
                
                calendar.render();
            });
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('gcal_calendar', 'gcal_render_calendar');


?>
