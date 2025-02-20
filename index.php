<?php
/*
Plugin Name: WP Easy GCalendar
Description: A plugin to display Google Calendar events in list or calendar layout.
Version: 1.3
Author: Alfe Caesar Lagas
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Enqueue necessary scripts and styles
function image_popup_enqueue_scripts() {
    wp_enqueue_style('wp-easy-calendar-style', plugins_url('/css/style.css', __FILE__), array(), rand(1000, 9999));
} 
add_action('wp_enqueue_scripts', 'image_popup_enqueue_scripts');

// Add settings page
function gcal_add_settings_page() {
    add_options_page('WP Easy GCalendar', 'WP Easy GCalendar', 'manage_options', 'gcal-settings', 'gcal_render_settings_page');
}
add_action('admin_menu', 'gcal_add_settings_page');

// Register settings
function gcal_register_settings() {
    register_setting('gcal_settings_group', 'gcal_api_key');
    register_setting('gcal_settings_group', 'gcal_calendar_id');
    register_setting('gcal_settings_group', 'gcal_list_layout'); 
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
                    <th>List Layout:</th>
                    <td>
                        <select name="gcal_list_layout">
                            <?php
                            $layouts = ['1', '2', '3', '4'];
                            $selected_layout = get_option('gcal_list_layout', '1');
                            foreach ($layouts as $layout) {
                                echo '<option value="' . esc_attr($layout) . '" ' . selected($selected_layout, $layout, false) . '>' . esc_html($layout) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
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
    $list_layout = get_option('gcal_list_layout');
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
         data-list-layout="<?php echo esc_attr($list_layout); ?>" 
         data-limit="<?php echo esc_attr($atts['limit']); ?>"
         data-timezone="<?php echo esc_attr($wp_timezone); ?>">
        <p>Loading events...</p>
    </div>
    <script>
        function formatDate(dateString) {
            const date = new Date(dateString);
            const month = date.toLocaleDateString('en-US', { month: 'short' }).toUpperCase();
            const day = String(date.getDate()).padStart(2, '0');
            return `${month} ${day}`;
        }

        const calendarSVG = `<svg class="calendar-icon" fill="#000000" version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" 
                width="800px" height="800px" viewBox="0 0 610.398 610.398"
                xml:space="preserve">
            <g>
                <g>
                    <path d="M159.567,0h-15.329c-1.956,0-3.811,0.411-5.608,0.995c-8.979,2.912-15.616,12.498-15.616,23.997v10.552v27.009v14.052
                        c0,2.611,0.435,5.078,1.066,7.44c2.702,10.146,10.653,17.552,20.158,17.552h15.329c11.724,0,21.224-11.188,21.224-24.992V62.553
                        V35.544V24.992C180.791,11.188,171.291,0,159.567,0z"/>
                    <path d="M461.288,0h-15.329c-11.724,0-21.224,11.188-21.224,24.992v10.552v27.009v14.052c0,13.804,9.5,24.992,21.224,24.992
                        h15.329c11.724,0,21.224-11.188,21.224-24.992V62.553V35.544V24.992C482.507,11.188,473.007,0,461.288,0z"/>
                    <path d="M539.586,62.553h-37.954v14.052c0,24.327-18.102,44.117-40.349,44.117h-15.329c-22.247,0-40.349-19.79-40.349-44.117
                        V62.553H199.916v14.052c0,24.327-18.102,44.117-40.349,44.117h-15.329c-22.248,0-40.349-19.79-40.349-44.117V62.553H70.818
                        c-21.066,0-38.15,16.017-38.15,35.764v476.318c0,19.784,17.083,35.764,38.15,35.764h468.763c21.085,0,38.149-15.984,38.149-35.764
                        V98.322C577.735,78.575,560.671,62.553,539.586,62.553z M527.757,557.9l-446.502-0.172V173.717h446.502V557.9z"/>
                    <path d="M353.017,266.258h117.428c10.193,0,18.437-10.179,18.437-22.759s-8.248-22.759-18.437-22.759H353.017
                        c-10.193,0-18.437,10.179-18.437,22.759C334.58,256.074,342.823,266.258,353.017,266.258z"/>
                    <path d="M353.017,348.467h117.428c10.193,0,18.437-10.179,18.437-22.759c0-12.579-8.248-22.758-18.437-22.758H353.017
                        c-10.193,0-18.437,10.179-18.437,22.758C334.58,338.288,342.823,348.467,353.017,348.467z"/>
                    <path d="M353.017,430.676h117.428c10.193,0,18.437-10.18,18.437-22.759s-8.248-22.759-18.437-22.759H353.017
                        c-10.193,0-18.437,10.18-18.437,22.759S342.823,430.676,353.017,430.676z"/>
                    <path d="M353.017,512.89h117.428c10.193,0,18.437-10.18,18.437-22.759c0-12.58-8.248-22.759-18.437-22.759H353.017
                        c-10.193,0-18.437,10.179-18.437,22.759C334.58,502.71,342.823,512.89,353.017,512.89z"/>
                    <path d="M145.032,266.258H262.46c10.193,0,18.436-10.179,18.436-22.759s-8.248-22.759-18.436-22.759H145.032
                        c-10.194,0-18.437,10.179-18.437,22.759C126.596,256.074,134.838,266.258,145.032,266.258z"/>
                    <path d="M145.032,348.467H262.46c10.193,0,18.436-10.179,18.436-22.759c0-12.579-8.248-22.758-18.436-22.758H145.032
                        c-10.194,0-18.437,10.179-18.437,22.758C126.596,338.288,134.838,348.467,145.032,348.467z"/>
                    <path d="M145.032,430.676H262.46c10.193,0,18.436-10.18,18.436-22.759s-8.248-22.759-18.436-22.759H145.032
                        c-10.194,0-18.437,10.18-18.437,22.759S134.838,430.676,145.032,430.676z"/>
                    <path d="M145.032,512.89H262.46c10.193,0,18.436-10.18,18.436-22.759c0-12.58-8.248-22.759-18.436-22.759H145.032
                        c-10.194,0-18.437,10.179-18.437,22.759C126.596,502.71,134.838,512.89,145.032,512.89z"/>
                </g>
            </g>
            </svg>`;

        const clockSVG = `<svg class="clock-icon" width="800px" height="800px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 7V12H15M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>`;
        
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('gcal-list');
            const apiKey = container.dataset.apiKey;
            const calendarId = container.dataset.calendarId;
            const listLayout = container.dataset.listLayout;
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
                    
                    let html = `<div class="gcal-list-container set-layout-${listLayout}">`;
                    events.forEach(event => {
                        let start = event.start.dateTime || event.start.date + 'T00:00:00';
                        let end = event.end.dateTime || event.end.date + 'T23:59:59';
                        let startDate = new Date(start).toLocaleString('en-US', { timeZone: timezone });
                        let endDate = new Date(end).toLocaleString('en-US', { timeZone: timezone });
                        let htmlLink = event.htmlLink;
                        let imageUrl = `https://placehold.co/600x400`;

                        if(event.attachments){
                            console.log('aasdasdasd')
                            let attachment = event.attachments[0].fileId 
                            imageUrl = `https://lh3.googleusercontent.com/d/${attachment}=w1000`;
                        }
 

                        let dateOnly = startDate.split(",")[0];
                        let timeOnly = startDate.split(",")[1];
                        let monthOnly = formatDate(dateOnly).split(" ")[0];
                        let dayOnly = formatDate(dateOnly).split(" ")[1];

                        if(listLayout == 1){
                            
                            html += `<div class="gcal-list__row">
                                        <div class="gcal-list__column date-col">
                                            <div class="gcal-list__column__month">${monthOnly}</div>
                                            <div class="gcal-list__column__day">${dayOnly}</div>
                                        </div>
                                        <div class="gcal-list__column title-col">
                                            <div class="gcal-list__column__title">${event.summary || 'No Title'}</div>
                                        </div>
                                        <div class="gcal-list__column link-col">
                                            <a href="${htmlLink}" class="arrow-btn" target="_blank"><svg fill="#000000" height="200px" width="200px" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 330 330" xml:space="preserve"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path id="XMLID_222_" d="M250.606,154.389l-150-149.996c-5.857-5.858-15.355-5.858-21.213,0.001 c-5.857,5.858-5.857,15.355,0.001,21.213l139.393,139.39L79.393,304.394c-5.857,5.858-5.857,15.355,0.001,21.213 C82.322,328.536,86.161,330,90,330s7.678-1.464,10.607-4.394l149.999-150.004c2.814-2.813,4.394-6.628,4.394-10.606 C255,161.018,253.42,157.202,250.606,154.389z"></path> </g></svg></a>
                                        </div>
                                    </div>`
                        }
                        else if(listLayout == 2){
                            html += `<div class="gcal-list__row">
                                        <div class="gcal-list__column date-col">${calendarSVG} ${monthOnly} ${dayOnly} - ${timeOnly}</div>
                                        <div class="gcal-list__column title-col">${event.summary || 'No Title'}</div>
                                        <div class="gcal-list__column link-col"><a href="${htmlLink}" target="_blank">➡️ View Details</a></div>
                                    </div><hr  />`;
                        }
                        else if(listLayout == 3){
                            html += `<div class="gcal-list__row">
                                        <div class="gcal-list__column img-col">
                                            <div class="gcal-list__column__img">
                                                <img src="${imageUrl}" />
                                            </div>
                                        </div>
                                        <div class="gcal-list__column content-col">
                                            <div class="gcal-list__column__dateTime">${calendarSVG} <span>${monthOnly} ${dayOnly}</span> ${clockSVG} <span>${timeOnly}</span></div>
                                            <div class="gcal-list__column__title"><a href="${htmlLink}" target="_blank">${event.summary || 'No Title'}</a></div>
                                        </div>
                                    </div>`
                        }
                        else if(listLayout == 4){
                            html += `<div><strong>${event.summary || 'No Title'}</strong> | ${startDate} - ${endDate}</div>`;
                        }
                        
                        //html += `<div><strong>${event.summary || 'No Title'}</strong> | ${startDate} - ${endDate}</div>`;
                    });
                    html += '</div>';
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
