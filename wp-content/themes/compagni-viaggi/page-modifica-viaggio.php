<?php
/**
 * Template Name: Edit Trip
 * Description: Frontend form to edit an existing trip
 */

// Check if user is logged in
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

// Get travel ID from URL
$travel_id = isset($_GET['travel_id']) ? intval($_GET['travel_id']) : 0;

if (!$travel_id) {
    wp_redirect(home_url('/dashboard'));
    exit;
}

// Get travel post
$travel = get_post($travel_id);

if (!$travel || $travel->post_type !== 'viaggio') {
    wp_redirect(home_url('/dashboard'));
    exit;
}

// Check if current user is the organizer
$user_id = get_current_user_id();
if ($travel->post_author != $user_id) {
    wp_redirect(home_url('/dashboard'));
    exit;
}

// Get travel meta data
$destination = get_post_meta($travel_id, 'cdv_destination', true);
$country = get_post_meta($travel_id, 'cdv_country', true);
$start_date = get_post_meta($travel_id, 'cdv_start_date', true);
$end_date = get_post_meta($travel_id, 'cdv_end_date', true);
$date_type = get_post_meta($travel_id, 'cdv_date_type', true) ?: 'precise';
$travel_month = get_post_meta($travel_id, 'cdv_travel_month', true);
$budget = get_post_meta($travel_id, 'cdv_budget', true);
$max_participants = get_post_meta($travel_id, 'cdv_max_participants', true);

// Optional fields
$transport = get_post_meta($travel_id, 'cdv_travel_transport', true);
$accommodation = get_post_meta($travel_id, 'cdv_travel_accommodation', true);
$difficulty = get_post_meta($travel_id, 'cdv_travel_difficulty', true);
$meals = get_post_meta($travel_id, 'cdv_travel_meals', true);
$guide_type = get_post_meta($travel_id, 'cdv_travel_guide_type', true);
$requirements = get_post_meta($travel_id, 'cdv_travel_requirements', true);

// Get travel types
$travel_types = wp_get_post_terms($travel_id, 'tipo_viaggio', array('fields' => 'ids'));

// Normalize country value to match the English selector labels
$country_translation_map = array(
    'Italia' => 'Italy',
    'Francia' => 'France',
    'Spagna' => 'Spain',
    'Germania' => 'Germany',
    'Regno Unito' => 'United Kingdom',
    'Portogallo' => 'Portugal',
    'Grecia' => 'Greece',
    'Paesi Bassi' => 'Netherlands',
    'Svizzera' => 'Switzerland',
    'Austria' => 'Austria',
    'Croazia' => 'Croatia',
    'Irlanda' => 'Ireland',
    'Islanda' => 'Iceland',
    'Norvegia' => 'Norway',
    'Svezia' => 'Sweden',
    'Danimarca' => 'Denmark',
    'Polonia' => 'Poland',
    'Repubblica Ceca' => 'Czech Republic',
    'Ungheria' => 'Hungary',
    'Romania' => 'Romania',
    'Bulgaria' => 'Bulgaria',
    'Slovenia' => 'Slovenia',
    'Montenegro' => 'Montenegro',
    'Albania' => 'Albania',
    'Serbia' => 'Serbia',
    'Bosnia ed Erzegovina' => 'Bosnia and Herzegovina',
    'Macedonia del Nord' => 'North Macedonia',
    'Belgio' => 'Belgium',
    'Lussemburgo' => 'Luxembourg',
    'Finlandia' => 'Finland',
    'Estonia' => 'Estonia',
    'Lettonia' => 'Latvia',
    'Lituania' => 'Lithuania',
    'Slovacchia' => 'Slovakia',
    'Malta' => 'Malta',
    'Cipro' => 'Cyprus',
    'Marocco' => 'Morocco',
    'Egitto' => 'Egypt',
    'Tunisia' => 'Tunisia',
    'Sudafrica' => 'South Africa',
    'Kenya' => 'Kenya',
    'Tanzania' => 'Tanzania',
    'Madagascar' => 'Madagascar',
    'Namibia' => 'Namibia',
    'Botswana' => 'Botswana',
    'Zanzibar' => 'Zanzibar',
    'Mauritius' => 'Mauritius',
    'Seychelles' => 'Seychelles',
    'Senegal' => 'Senegal',
    'Etiopia' => 'Ethiopia',
    'Giappone' => 'Japan',
    'Thailandia' => 'Thailand',
    'Vietnam' => 'Vietnam',
    'Cina' => 'China',
    'India' => 'India',
    'Indonesia' => 'Indonesia',
    'Maldive' => 'Maldives',
    'Sri Lanka' => 'Sri Lanka',
    'Emirati Arabi Uniti' => 'United Arab Emirates',
    'Giordania' => 'Jordan',
    'Israele' => 'Israel',
    'Turchia' => 'Turkey',
    'Cambogia' => 'Cambodia',
    'Malesia' => 'Malaysia',
    'Singapore' => 'Singapore',
    'Filippine' => 'Philippines',
    'Nepal' => 'Nepal',
    'Corea del Sud' => 'South Korea',
    'Oman' => 'Oman',
    'Qatar' => 'Qatar',
    'Bali' => 'Bali',
    'Stati Uniti' => 'United States',
    'Canada' => 'Canada',
    'Messico' => 'Mexico',
    'Brasile' => 'Brazil',
    'Argentina' => 'Argentina',
    'Per\u00f9' => 'Peru',
    'Cile' => 'Chile',
    'Colombia' => 'Colombia',
    'Costa Rica' => 'Costa Rica',
    'Cuba' => 'Cuba',
    'Repubblica Dominicana' => 'Dominican Republic',
    'Ecuador' => 'Ecuador',
    'Bolivia' => 'Bolivia',
    'Uruguay' => 'Uruguay',
    'Panama' => 'Panama',
    'Guatemala' => 'Guatemala',
    'Nicaragua' => 'Nicaragua',
    'Australia' => 'Australia',
    'Nuova Zelanda' => 'New Zealand',
    'Polinesia Francese' => 'French Polynesia',
    'Fiji' => 'Fiji',
    'altro' => 'other',
);
if (!empty($country) && isset($country_translation_map[$country])) {
    $country = $country_translation_map[$country];
}

$region_countries = array(
    '🇪🇺 Europe' => array('Italy', 'France', 'Spain', 'Germany', 'United Kingdom', 'Portugal', 'Greece', 'Netherlands', 'Switzerland', 'Austria', 'Croatia', 'Ireland', 'Iceland', 'Norway', 'Sweden', 'Denmark', 'Poland', 'Czech Republic', 'Hungary', 'Romania', 'Bulgaria', 'Slovenia', 'Montenegro', 'Albania', 'Serbia', 'Bosnia and Herzegovina', 'North Macedonia', 'Belgium', 'Luxembourg', 'Finland', 'Estonia', 'Latvia', 'Lithuania', 'Slovakia', 'Malta', 'Cyprus'),
    '🌍 Africa' => array('Morocco', 'Egypt', 'Tunisia', 'South Africa', 'Kenya', 'Tanzania', 'Madagascar', 'Namibia', 'Botswana', 'Zanzibar', 'Mauritius', 'Seychelles', 'Senegal', 'Ethiopia'),
    '🌏 Asia' => array('Japan', 'Thailand', 'Vietnam', 'China', 'India', 'Indonesia', 'Maldives', 'Sri Lanka', 'United Arab Emirates', 'Jordan', 'Israel', 'Turkey', 'Cambodia', 'Malaysia', 'Singapore', 'Philippines', 'Nepal', 'South Korea', 'Oman', 'Qatar', 'Bali'),
    '🌎 Americas' => array('United States', 'Canada', 'Mexico', 'Brazil', 'Argentina', 'Peru', 'Chile', 'Colombia', 'Costa Rica', 'Cuba', 'Dominican Republic', 'Ecuador', 'Bolivia', 'Uruguay', 'Panama', 'Guatemala', 'Nicaragua'),
    '🌏 Oceania' => array('Australia', 'New Zealand', 'French Polynesia', 'Fiji'),
);
$all_country_options = array();
foreach ($region_countries as $countries) {
    $all_country_options = array_merge($all_country_options, $countries);
}
if (empty($country)) {
    $country_select_value = '';
    $country_other_value = '';
} elseif (in_array($country, $all_country_options, true)) {
    $country_select_value = $country;
    $country_other_value = '';
} else {
    $country_select_value = 'other';
    $country_other_value = $country;
}

get_header();
?>

<main class="site-main">
    <div class="create-travel-page">
        <div class="container">
            <div class="create-travel-wrapper">
                <div class="page-header">
                    <h1>Edit Trip</h1>
                    <p>Update the details of your trip.</p>
                </div>

                <form id="edit-travel-form" class="travel-form">
                    <input type="hidden" id="travel_id" name="travel_id" value="<?php echo esc_attr($travel_id); ?>">

                    <div class="form-section">
                        <h3>General Information</h3>

                        <div class="form-group">
                            <label for="travel_title">Trip Title <span class="required">*</span></label>
                            <input type="text" id="travel_title" name="travel_title" required placeholder="Ex: Weekend in Venice, Tuscany Road Trip" value="<?php echo esc_attr($travel->post_title); ?>">
                        </div>

                        <div class="form-group">
                            <label for="travel_description">Description <span class="required">*</span></label>
                            <textarea id="travel_description" name="travel_description" rows="6" required placeholder="Describe the experience: destinations, activities, vibe, what makes it special..."><?php echo esc_textarea($travel->post_content); ?></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Destination</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="travel_destination">Destination <span class="required">*</span></label>
                                <input type="text" id="travel_destination" name="travel_destination" required placeholder="Ex: Venice, Tuscany" value="<?php echo esc_attr($destination); ?>">
                            </div>

                            <div class="form-group">
                                <label for="travel_country_select">Country <span class="required">*</span></label>
                                <select id="travel_country_select" name="travel_country_select" required>
                                    <option value=""><?php esc_html_e('Select a country', 'compagni-viaggi'); ?></option>
                                    <?php foreach ($region_countries as $region_label => $countries_list) : ?>
                                        <optgroup label="<?php echo esc_attr($region_label); ?>">
                                            <?php foreach ($countries_list as $country_name) : ?>
                                                <option value="<?php echo esc_attr($country_name); ?>" <?php selected($country_select_value, $country_name); ?>>
                                                    <?php echo esc_html($country_name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                    <option value="other" <?php selected($country_select_value, 'other'); ?>>📝 Other (specify)</option>
                                </select>

                                <input type="text"
                                       id="travel_country_other"
                                       name="travel_country_other"
                                       style="<?php echo $country_select_value === 'other' ? '' : 'display: none;'; ?> margin-top: 10px;"
                                       placeholder="Specify the country"
                                       value="<?php echo esc_attr($country_other_value); ?>">

                                <input type="hidden" id="travel_country" name="travel_country" value="<?php echo esc_attr($country); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>When to Travel</h3>

                        <div class="form-group">
                            <label>Date Type <span class="required">*</span></label>
                            <div class="radio-group" style="display: flex; gap: calc(var(--spacing-unit) * 3); margin-bottom: calc(var(--spacing-unit) * 2);">
                                <label style="display: flex; align-items: center; gap: calc(var(--spacing-unit) * 1); cursor: pointer;">
                                    <input type="radio" name="date_type" value="precise" <?php checked($date_type, 'precise'); ?>>
                                    <span>Specific dates</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: calc(var(--spacing-unit) * 1); cursor: pointer;">
                                    <input type="radio" name="date_type" value="month" <?php checked($date_type, 'month'); ?>>
                                    <span>Month only (flexible)</span>
                                </label>
                            </div>
                        </div>

                        <div id="precise-dates-container" style="<?php echo ($date_type === 'month') ? 'display: none;' : ''; ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="travel_start_date">Start Date <span class="required">*</span></label>
                                    <input type="date" id="travel_start_date" name="travel_start_date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo esc_attr($start_date); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="travel_end_date">End Date <span class="required">*</span></label>
                                    <input type="date" id="travel_end_date" name="travel_end_date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo esc_attr($end_date); ?>">
                                </div>
                            </div>
                        </div>

                        <div id="month-container" style="<?php echo ($date_type === 'month') ? '' : 'display: none;'; ?>">
                            <div class="form-group">
                                <label for="travel_month">Departure Month <span class="required">*</span></label>
                                <select id="travel_month" name="travel_month">
                                    <option value="">Select a month</option>
                                    <?php
                                    $months = array(
                                        '01' => 'January', '02' => 'February', '03' => 'March',
                                        '04' => 'April', '05' => 'May', '06' => 'June',
                                        '07' => 'July', '08' => 'August', '09' => 'September',
                                        '10' => 'October', '11' => 'November', '12' => 'December'
                                    );
                                    $current_month = (int)date('n');
                                    $current_year = (int)date('Y');

                                    // Show remaining months of the current year
                                    for ($i = $current_month; $i <= 12; $i++) {
                                        $month_num = str_pad($i, 2, '0', STR_PAD_LEFT);
                                        $value = $current_year . '-' . $month_num;
                                        $selected = ($travel_month === $value) ? 'selected' : '';
                                        echo '<option value="' . $value . '" ' . $selected . '>' . $months[$month_num] . ' ' . $current_year . '</option>';
                                    }

                                    // Show every month of the following year
                                    $next_year = $current_year + 1;
                                    foreach ($months as $num => $name) {
                                        $value = $next_year . '-' . $num;
                                        $selected = ($travel_month === $value) ? 'selected' : '';
                                        echo '<option value="' . $value . '" ' . $selected . '>' . $name . ' ' . $next_year . '</option>';
                                    }
                                    ?>
                                </select>
                                <small style="display: block; margin-top: calc(var(--spacing-unit) * 0.5); color: #666;">
                                    The trip will remain open for the entire selected month (flexible dates)
                                </small>
                            </div>
                        </div>

                        <h3 style="margin-top: calc(var(--spacing-unit) * 4);">Budget</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="travel_budget">Budget per Person (€) <span class="required">*</span></label>
                                <input type="number" id="travel_budget" name="travel_budget" min="0" required placeholder="500" value="<?php echo esc_attr($budget); ?>">
                            </div>

                            <div class="form-group">
                                <label for="travel_max_participants">Max Participants <span class="required">*</span></label>
                                <input type="number" id="travel_max_participants" name="travel_max_participants" min="2" max="50" required value="<?php echo esc_attr($max_participants); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Travel Type(s)</h3>
                        <div class="checkbox-group">
                            <?php
                            $all_travel_types = get_terms(array(
                                'taxonomy' => 'tipo_viaggio',
                                'hide_empty' => false,
                            ));
                            if (!empty($all_travel_types) && !is_wp_error($all_travel_types)) :
                                foreach ($all_travel_types as $type) :
                                    $checked = in_array($type->term_id, $travel_types) ? 'checked' : '';
                            ?>
                                <label>
                                    <input type="checkbox" name="travel_types[]" value="<?php echo esc_attr($type->term_id); ?>" <?php echo $checked; ?>>
                                    <?php echo esc_html($type->name); ?>
                                </label>
                            <?php
                                endforeach;
                            endif;
                            ?>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Additional Details <span style="font-weight: normal; font-size: 0.9rem; color: var(--text-medium);">(Optional)</span></h3>
                        <p style="color: var(--text-medium); margin-bottom: calc(var(--spacing-unit) * 3);">These details help travelers better understand the experience.</p>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="travel_transport">🚗 Transportation</label>
                                <div class="checkbox-group" style="grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));">
                                    <?php
                                    $transport_options = array(
                                        'aereo' => '✈️ Plane',
                                        'treno' => '🚂 Train',
                                        'bus' => '🚌 Bus',
                                        'auto_propria' => '🚗 Own car',
                                        'auto_noleggio' => '🚙 Rental car',
                                        'nave' => '🚢 Boat/Ferry'
                                    );
                                    $transport_array = is_array($transport) ? $transport : array();
                                    foreach ($transport_options as $value => $label) :
                                        $checked = in_array($value, $transport_array) ? 'checked' : '';
                                    ?>
                                    <label>
                                        <input type="checkbox" name="travel_transport[]" value="<?php echo esc_attr($value); ?>" <?php echo $checked; ?>>
                                        <?php echo esc_html($label); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="travel_accommodation">🏨 Accommodation Type</label>
                                <select id="travel_accommodation" name="travel_accommodation">
                                    <option value="">Not specified</option>
                                    <?php
                                    $accommodation_options = array(
                                        'hotel' => 'Hotel',
                                        'ostello' => 'Hostel',
                                        'bb' => 'B&B',
                                        'airbnb' => 'Airbnb/Vacation rental',
                                        'camping' => 'Camping/Tent',
                                        'rifugio' => 'Mountain hut',
                                        'misto' => 'Mixed',
                                        'altro' => 'Other'
                                    );
                                    foreach ($accommodation_options as $value => $label) {
                                        $selected = ($accommodation === $value) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="travel_difficulty">📈 Difficulty Level</label>
                                <select id="travel_difficulty" name="travel_difficulty">
                                    <option value="">Not specified</option>
                                    <?php
                                    $difficulty_options = array(
                                        'facile' => 'Easy – suitable for everyone',
                                        'moderato' => 'Moderate – basic preparation required',
                                        'impegnativo' => 'Challenging – good fitness required',
                                        'molto_impegnativo' => 'Very challenging – experienced travelers only'
                                    );
                                    foreach ($difficulty_options as $value => $label) {
                                        $selected = ($difficulty === $value) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="travel_meals">🍽️ Meals</label>
                                <select id="travel_meals" name="travel_meals">
                                    <option value="">Not specified</option>
                                    <?php
                                    $meals_options = array(
                                        'non_inclusi' => 'Not included',
                                        'colazione' => 'Breakfast only',
                                        'mezza_pensione' => 'Half board',
                                        'pensione_completa' => 'Full board'
                                    );
                                    foreach ($meals_options as $value => $label) {
                                        $selected = ($meals === $value) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="travel_guide_type">👥 Organization</label>
                                <select id="travel_guide_type" name="travel_guide_type">
                                    <option value="">Not specified</option>
                                    <?php
                                    $guide_options = array(
                                        'autonomo' => 'Self-guided trip',
                                        'guida_locale' => 'With local guide',
                                        'tour_organizzato' => 'Fully organized tour'
                                    );
                                    foreach ($guide_options as $value => $label) {
                                        $selected = ($guide_type === $value) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="travel_requirements">📝 Requirements & Special Notes</label>
                            <textarea id="travel_requirements" name="travel_requirements" rows="4" placeholder="Ex: Required documents (visa, passport), vaccinations, special gear, physical requirements..."><?php echo esc_textarea($requirements); ?></textarea>
                            <small style="display: block; margin-top: 8px; color: #666;">List any special requirements, required documents, or important info for travelers.</small>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="<?php echo esc_url(get_permalink($travel_id)); ?>" class="btn-secondary">Cancel</a>
                        <button type="submit" class="btn-primary btn-large">Save Changes 💾</button>
                    </div>

                    <div id="form-messages" style="margin-top: 20px;"></div>
                </form>
            </div>
        </div>
    </div>
</main>

<style>
.create-travel-page {
    padding: calc(var(--spacing-unit) * 6) 0;
    background: var(--bg-light);
    min-height: 80vh;
}

.create-travel-wrapper {
    max-width: 900px;
    margin: 0 auto;
    background: white;
    padding: calc(var(--spacing-unit) * 6);
    border-radius: 12px;
    box-shadow: 0 2px 20px rgba(0,0,0,0.08);
}

.page-header {
    text-align: center;
    margin-bottom: calc(var(--spacing-unit) * 6);
}

.page-header h1 {
    margin-bottom: calc(var(--spacing-unit) * 2);
    color: var(--primary-color);
}

.page-header p {
    font-size: 1.1rem;
    color: var(--text-medium);
}

.travel-form .form-section {
    margin-bottom: calc(var(--spacing-unit) * 5);
    padding-bottom: calc(var(--spacing-unit) * 5);
    border-bottom: 1px solid var(--border-color);
}

.travel-form .form-section:last-of-type {
    border-bottom: none;
    padding-bottom: 0;
}

.travel-form .form-section h3 {
    color: var(--text-dark);
    margin-bottom: calc(var(--spacing-unit) * 3);
    font-size: 1.3rem;
}

.required {
    color: var(--error-color);
}

.checkbox-group {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: calc(var(--spacing-unit) * 2);
}

.checkbox-group label {
    display: flex;
    align-items: center;
    gap: calc(var(--spacing-unit) * 1);
    padding: calc(var(--spacing-unit) * 1.5);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s;
}

.checkbox-group label:hover {
    border-color: var(--primary-color);
    background: rgba(var(--primary-rgb), 0.05);
}

.checkbox-group input[type="checkbox"] {
    cursor: pointer;
}

.form-actions {
    display: flex;
    gap: calc(var(--spacing-unit) * 2);
    justify-content: center;
    margin-top: calc(var(--spacing-unit) * 4);
}

.success-message {
    background: var(--success-color);
    color: white;
    padding: calc(var(--spacing-unit) * 3);
    border-radius: 8px;
    text-align: center;
}

.error-message {
    background: var(--error-color);
    color: white;
    padding: calc(var(--spacing-unit) * 3);
    border-radius: 8px;
    text-align: center;
}

.info-message {
    background: #3498db;
    color: white;
    padding: calc(var(--spacing-unit) * 3);
    border-radius: 8px;
    text-align: center;
}

.warning-message {
    background: #f39c12;
    color: white;
    padding: calc(var(--spacing-unit) * 3);
    border-radius: 8px;
    text-align: center;
}

@media (max-width: 768px) {
    .create-travel-wrapper {
        padding: calc(var(--spacing-unit) * 4);
    }

    .checkbox-group {
        grid-template-columns: 1fr;
    }

    .form-actions {
        flex-direction: column;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle between precise dates and month
    $('input[name="date_type"]').on('change', function() {
        const dateType = $(this).val();

        if (dateType === 'precise') {
            $('#precise-dates-container').show();
            $('#month-container').hide();
            $('#travel_start_date').prop('required', true);
            $('#travel_end_date').prop('required', true);
            $('#travel_month').prop('required', false);
        } else {
            $('#precise-dates-container').hide();
            $('#month-container').show();
            $('#travel_start_date').prop('required', false);
            $('#travel_end_date').prop('required', false);
            $('#travel_month').prop('required', true);
        }
    });

    // Update end date min when start date changes
    $('#travel_start_date').on('change', function() {
        const startDate = $(this).val();
        $('#travel_end_date').attr('min', startDate);
    });

    // Handle country select with "Other" option
    $('#travel_country_select').on('change', function() {
        const selectedValue = $(this).val();
        const $otherField = $('#travel_country_other');
        const $hiddenField = $('#travel_country');

        if (selectedValue === 'other') {
            // Show the "other" text field
            $otherField.show().prop('required', true).focus();
            $hiddenField.val(''); // Clear hidden field
        } else {
            // Hide the "other" text field and set hidden field value
            $otherField.hide().prop('required', false).val('');
            $hiddenField.val(selectedValue);
        }
    });

    // Update hidden field when "other" text field changes
    $('#travel_country_other').on('input', function() {
        $('#travel_country').val($(this).val());
    });

    // Initialize on page load
    $('#travel_country_select').trigger('change');

    $('#edit-travel-form').on('submit', function(e) {
        e.preventDefault();

        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        const $messages = $('#form-messages');

        const dateType = $('input[name="date_type"]:checked').val();
        let dataToSend = {
            action: 'cdv_update_travel',
            nonce: cdvAjax.nonce,
            travel_id: $('#travel_id').val(),
            title: $('#travel_title').val(),
            description: $('#travel_description').val(),
            destination: $('#travel_destination').val(),
            country: $('#travel_country').val(),
            budget: $('#travel_budget').val(),
            max_participants: $('#travel_max_participants').val(),
            travel_types: [],
            travel_transport: [],
            travel_accommodation: $('#travel_accommodation').val(),
            travel_difficulty: $('#travel_difficulty').val(),
            travel_meals: $('#travel_meals').val(),
            travel_guide_type: $('#travel_guide_type').val(),
            travel_requirements: $('#travel_requirements').val()
        };

        // Get travel types
        $('input[name="travel_types[]"]:checked').each(function() {
            dataToSend.travel_types.push($(this).val());
        });

        // Get travel transport methods
        $('input[name="travel_transport[]"]:checked').each(function() {
            dataToSend.travel_transport.push($(this).val());
        });

        // Add date info based on type
        if (dateType === 'precise') {
            const startDate = $('#travel_start_date').val();
            const endDate = $('#travel_end_date').val();

            if (!startDate || !endDate) {
                $messages.html('<div class="error-message">Please provide both start and end dates.</div>');
                return;
            }

            if (new Date(endDate) <= new Date(startDate)) {
                $messages.html('<div class="error-message">The end date must be later than the start date.</div>');
                return;
            }

            dataToSend.start_date = startDate;
            dataToSend.end_date = endDate;
            dataToSend.date_type = 'precise';
        } else {
            const monthValue = $('#travel_month').val();

            if (!monthValue) {
                $messages.html('<div class="error-message">Select a month di partenza.</div>');
                return;
            }

            dataToSend.travel_month = monthValue;
            dataToSend.date_type = 'month';
        }

        // Disable submit button
        $submitBtn.prop('disabled', true).text('Saving changes...');

        $.ajax({
            url: cdvAjax.ajaxurl,
            type: 'POST',
            data: dataToSend,
            success: function(response) {
                if (response.success) {
                    $messages.html('<div class="success-message">' + response.data.message + '</div>');

                    // Redirect to the travel page after 1 second
                    setTimeout(function() {
                        window.location.href = response.data.redirect_url;
                    }, 1000);
                } else {
                    $messages.html('<div class="error-message">' + response.data.message + '</div>');
                    $submitBtn.prop('disabled', false).text('Save Changes 💾');
                }
            },
            error: function() {
                $messages.html('<div class="error-message">An error occurred. Please try again later.</div>');
                $submitBtn.prop('disabled', false).text('Save Changes 💾');
            }
        });
    });
});
</script>

<?php
get_footer();
