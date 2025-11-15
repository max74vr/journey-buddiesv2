<?php
/**
 * Template Name: Create Trip
 * Description: Frontend form to publish a new trip
 */

// Check if user is logged in
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

// Check if user has capability to create travels
$user_id = get_current_user_id();
if (!current_user_can('create_viaggi')) {
    wp_redirect(home_url('/dashboard'));
    exit;
}

get_header();
?>

<main class="site-main">
    <div class="create-travel-page">
        <div class="container">
            <div class="create-travel-wrapper">
                <div class="page-header">
                    <h1>Create a New Trip</h1>
                    <p>Fill in the form to pitch your adventure and find the perfect travel companions.</p>
                </div>

                <form id="create-travel-form" class="travel-form">
                    <div class="form-section">
                        <h3>General Information</h3>

                        <div class="form-group">
                            <label for="travel_title">Trip Title <span class="required">*</span></label>
                            <input type="text" id="travel_title" name="travel_title" required placeholder="Ex: Weekend in Venice, Tuscany Road Trip">
                        </div>

                        <div class="form-group">
                            <label for="travel_description">Description <span class="required">*</span></label>
                            <textarea id="travel_description" name="travel_description" rows="6" required placeholder="Describe destinations, planned activities, mood, pace, and what makes this experience special."></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Destination</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="travel_destination">Destination <span class="required">*</span></label>
                                <input type="text" id="travel_destination" name="travel_destination" required placeholder="Ex: Venice, Amalfi Coast, Patagonia">
                            </div>

                            <div class="form-group">
                                <label for="travel_country_select">Country <span class="required">*</span></label>
                                <select id="travel_country_select" name="travel_country_select" required>
                                    <option value="">Select a country</option>

                                    <optgroup label="🇪🇺 Europe">
                                        <option value="Italy">Italy</option>
                                        <option value="France">France</option>
                                        <option value="Spain">Spain</option>
                                        <option value="Germany">Germany</option>
                                        <option value="United Kingdom">United Kingdom</option>
                                        <option value="Portugal">Portugal</option>
                                        <option value="Greece">Greece</option>
                                        <option value="Netherlands">Netherlands</option>
                                        <option value="Switzerland">Switzerland</option>
                                        <option value="Austria">Austria</option>
                                        <option value="Croatia">Croatia</option>
                                        <option value="Ireland">Ireland</option>
                                        <option value="Iceland">Iceland</option>
                                        <option value="Norway">Norway</option>
                                        <option value="Sweden">Sweden</option>
                                        <option value="Denmark">Denmark</option>
                                        <option value="Poland">Poland</option>
                                        <option value="Czech Republic">Czech Republic</option>
                                        <option value="Hungary">Hungary</option>
                                        <option value="Romania">Romania</option>
                                        <option value="Bulgaria">Bulgaria</option>
                                        <option value="Slovenia">Slovenia</option>
                                        <option value="Montenegro">Montenegro</option>
                                        <option value="Albania">Albania</option>
                                        <option value="Serbia">Serbia</option>
                                        <option value="Bosnia and Herzegovina">Bosnia and Herzegovina</option>
                                        <option value="North Macedonia">North Macedonia</option>
                                        <option value="Belgium">Belgium</option>
                                        <option value="Luxembourg">Luxembourg</option>
                                        <option value="Finland">Finland</option>
                                        <option value="Estonia">Estonia</option>
                                        <option value="Latvia">Latvia</option>
                                        <option value="Lithuania">Lithuania</option>
                                        <option value="Slovakia">Slovakia</option>
                                        <option value="Malta">Malta</option>
                                        <option value="Cyprus">Cyprus</option>
                                    </optgroup>

                                    <optgroup label="🌍 Africa">
                                        <option value="Morocco">Morocco</option>
                                        <option value="Egypt">Egypt</option>
                                        <option value="Tunisia">Tunisia</option>
                                        <option value="South Africa">South Africa</option>
                                        <option value="Kenya">Kenya</option>
                                        <option value="Tanzania">Tanzania</option>
                                        <option value="Madagascar">Madagascar</option>
                                        <option value="Namibia">Namibia</option>
                                        <option value="Botswana">Botswana</option>
                                        <option value="Zanzibar">Zanzibar</option>
                                        <option value="Mauritius">Mauritius</option>
                                        <option value="Seychelles">Seychelles</option>
                                        <option value="Senegal">Senegal</option>
                                        <option value="Ethiopia">Ethiopia</option>
                                    </optgroup>

                                    <optgroup label="🌏 Asia">
                                        <option value="Japan">Japan</option>
                                        <option value="Thailand">Thailand</option>
                                        <option value="Vietnam">Vietnam</option>
                                        <option value="China">China</option>
                                        <option value="India">India</option>
                                        <option value="Indonesia">Indonesia</option>
                                        <option value="Maldives">Maldives</option>
                                        <option value="Sri Lanka">Sri Lanka</option>
                                        <option value="United Arab Emirates">United Arab Emirates</option>
                                        <option value="Jordan">Jordan</option>
                                        <option value="Israel">Israel</option>
                                        <option value="Turkey">Turkey</option>
                                        <option value="Cambodia">Cambodia</option>
                                        <option value="Malaysia">Malaysia</option>
                                        <option value="Singapore">Singapore</option>
                                        <option value="Philippines">Philippines</option>
                                        <option value="Nepal">Nepal</option>
                                        <option value="South Korea">South Korea</option>
                                        <option value="Oman">Oman</option>
                                        <option value="Qatar">Qatar</option>
                                        <option value="Bali">Bali</option>
                                    </optgroup>

                                    <optgroup label="🌎 Americas">
                                        <option value="United States">United States</option>
                                        <option value="Canada">Canada</option>
                                        <option value="Mexico">Mexico</option>
                                        <option value="Brazil">Brazil</option>
                                        <option value="Argentina">Argentina</option>
                                        <option value="Peru">Peru</option>
                                        <option value="Chile">Chile</option>
                                        <option value="Colombia">Colombia</option>
                                        <option value="Costa Rica">Costa Rica</option>
                                        <option value="Cuba">Cuba</option>
                                        <option value="Dominican Republic">Dominican Republic</option>
                                        <option value="Ecuador">Ecuador</option>
                                        <option value="Bolivia">Bolivia</option>
                                        <option value="Uruguay">Uruguay</option>
                                        <option value="Panama">Panama</option>
                                        <option value="Guatemala">Guatemala</option>
                                        <option value="Nicaragua">Nicaragua</option>
                                    </optgroup>

                                    <optgroup label="🌏 Oceania">
                                        <option value="Australia">Australia</option>
                                        <option value="New Zealand">New Zealand</option>
                                        <option value="French Polynesia">French Polynesia</option>
                                        <option value="Fiji">Fiji</option>
                                    </optgroup>

                                    <option value="other">📝 Other (specify)</option>
                                </select>

                                <!-- Campo "Other" che appare quando selezionato -->
                                <input type="text" id="travel_country_other" name="travel_country_other" style="display: none; margin-top: 10px;" placeholder="Specify the country">

                                <!-- Hidden field that stores the final value -->
                                <input type="hidden" id="travel_country" name="travel_country">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>When to Travel</h3>

                        <div class="form-group">
                            <label>Date Type <span class="required">*</span></label>
                            <div class="radio-group" style="display: flex; gap: calc(var(--spacing-unit) * 3); margin-bottom: calc(var(--spacing-unit) * 2);">
                                <label style="display: flex; align-items: center; gap: calc(var(--spacing-unit) * 1); cursor: pointer;">
                                    <input type="radio" name="date_type" value="precise" checked>
                                    <span>Specific dates</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: calc(var(--spacing-unit) * 1); cursor: pointer;">
                                    <input type="radio" name="date_type" value="month">
                                    <span>Month only (flexible)</span>
                                </label>
                            </div>
                        </div>

                        <div id="precise-dates-container">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="travel_start_date">Start Date <span class="required">*</span></label>
                                    <input type="date" id="travel_start_date" name="travel_start_date" min="<?php echo date('Y-m-d'); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="travel_end_date">End Date <span class="required">*</span></label>
                                    <input type="date" id="travel_end_date" name="travel_end_date" min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div id="month-container" style="display: none;">
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

                                    // Mostra mesi dell'anno corrente (da questo mese in poi)
                                    for ($i = $current_month; $i <= 12; $i++) {
                                        $month_num = str_pad($i, 2, '0', STR_PAD_LEFT);
                                        echo '<option value="' . $current_year . '-' . $month_num . '">' . $months[$month_num] . ' ' . $current_year . '</option>';
                                    }

                                    // Mostra tutti i mesi del prossimo anno
                                    $next_year = $current_year + 1;
                                    foreach ($months as $num => $name) {
                                        echo '<option value="' . $next_year . '-' . $num . '">' . $name . ' ' . $next_year . '</option>';
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
                                <input type="number" id="travel_budget" name="travel_budget" min="0" required placeholder="500">
                            </div>

                            <div class="form-group">
                                <label for="travel_max_participants">Max Participants <span class="required">*</span></label>
                                <input type="number" id="travel_max_participants" name="travel_max_participants" min="2" max="50" value="5" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Travel Type(s)</h3>
                        <div class="checkbox-group">
                            <?php
                            $travel_types = get_terms(array(
                                'taxonomy' => 'tipo_viaggio',
                                'hide_empty' => false,
                            ));
                            if (!empty($travel_types) && !is_wp_error($travel_types)) :
                                foreach ($travel_types as $type) :
                            ?>
                                <label>
                                    <input type="checkbox" name="travel_types[]" value="<?php echo esc_attr($type->term_id); ?>">
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
                                    <label>
                                        <input type="checkbox" name="travel_transport[]" value="aereo">
                                        ✈️ Plane
                                    </label>
                                    <label>
                                        <input type="checkbox" name="travel_transport[]" value="treno">
                                        🚂 Train
                                    </label>
                                    <label>
                                        <input type="checkbox" name="travel_transport[]" value="bus">
                                        🚌 Bus
                                    </label>
                                    <label>
                                        <input type="checkbox" name="travel_transport[]" value="auto_propria">
                                        🚗 Own car
                                    </label>
                                    <label>
                                        <input type="checkbox" name="travel_transport[]" value="auto_noleggio">
                                        🚙 Rental car
                                    </label>
                                    <label>
                                        <input type="checkbox" name="travel_transport[]" value="nave">
                                        🚢 Boat/Ferry
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="travel_accommodation">🏨 Accommodation Type</label>
                                <select id="travel_accommodation" name="travel_accommodation">
                                    <option value="">Not specified</option>
                                    <option value="hotel">Hotel</option>
                                    <option value="ostello">Hostel</option>
                                    <option value="bb">B&B</option>
                                    <option value="airbnb">Airbnb/Vacation rental</option>
                                    <option value="camping">Camping/Tent</option>
                                    <option value="rifugio">Mountain hut</option>
                                    <option value="misto">Mixed</option>
                                    <option value="altro">Other</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="travel_difficulty">📈 Difficulty Level</label>
                                <select id="travel_difficulty" name="travel_difficulty">
                                    <option value="">Not specified</option>
                                    <option value="facile">Easy – suitable for everyone</option>
                                    <option value="moderato">Moderate – basic preparation required</option>
                                    <option value="impegnativo">Challenging – good fitness required</option>
                                    <option value="molto_impegnativo">Very challenging – for experienced travelers</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="travel_meals">🍽️ Meals</label>
                                <select id="travel_meals" name="travel_meals">
                                    <option value="">Not specified</option>
                                    <option value="non_inclusi">Not included</option>
                                    <option value="colazione">Breakfast only</option>
                                    <option value="mezza_pensione">Half board</option>
                                    <option value="pensione_completa">Full board</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="travel_guide_type">👥 Organization</label>
                                <select id="travel_guide_type" name="travel_guide_type">
                                    <option value="">Not specified</option>
                                    <option value="autonomo">Self-guided trip</option>
                                    <option value="guida_locale">With local guide</option>
                                    <option value="tour_organizzato">Fully organized tour</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="travel_requirements">📝 Requirements & Special Notes</label>
                            <textarea id="travel_requirements" name="travel_requirements" rows="4" placeholder="Ex: Required documents (visa, passport), recommended vaccinations, special gear, fitness requirements..."></textarea>
                            <small style="display: block; margin-top: 8px; color: #666;">Share any additional requirements, documents, or important info travelers should know.</small>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="disclaimer-box" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                            <h4 style="margin-top: 0; color: #856404;">⚠️ Important Notice</h4>
                            <p style="margin-bottom: 15px;">By publishing this trip, you confirm that:</p>
                            <ul style="margin: 0; padding-left: 20px;">
                                <li style="margin-bottom: 8px;">The platform <strong>only connects travelers</strong> and does not organize trips on their behalf.</li>
                                <li style="margin-bottom: 8px;">You are <strong>fully responsible</strong> for planning, safety, and logistics.</li>
                                <li style="margin-bottom: 8px;">You must <strong>personally verify</strong> every participant's identity and reliability.</li>
                                <li style="margin-bottom: 8px;">The platform <strong>is not responsible</strong> for behavior, damages, cancellations, or disputes between travelers.</li>
                                <li style="margin-bottom: 8px;">All <strong>payments and logistics</strong> are handled directly between you and the participants.</li>
                                <li style="margin-bottom: 8px;">You must comply with all <strong>local and international laws</strong> related to this trip.</li>
                            </ul>
                            <div style="margin-top: 15px;">
                                <label style="display: flex; align-items: start; gap: 10px; cursor: pointer;">
                                    <input type="checkbox" id="accept_travel_disclaimer" name="accept_travel_disclaimer" required style="margin-top: 4px;">
                                    <span>I have read and accept the notice. I understand I am solely responsible for this trip and release the platform from any liability.</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="<?php echo esc_url(home_url('/dashboard')); ?>" class="btn-secondary">Cancel</a>
                        <button type="submit" class="btn-primary btn-large">Publish Trip 🚀</button>
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

        if (selectedValue === 'altro') {
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
    $('input[name="date_type"]:checked').trigger('change');

    $('#create-travel-form').on('submit', function(e) {
        e.preventDefault();

        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        const $messages = $('#form-messages');

        const dateType = $('input[name="date_type"]:checked').val();
        let dataToSend = {
            action: 'cdv_create_travel',
            nonce: cdvAjax.nonce,
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
                $messages.html('<div class="error-message">Select a departure month.</div>');
                return;
            }

            dataToSend.travel_month = monthValue;
            dataToSend.date_type = 'month';
        }

        // Disable submit button and show validation message
        $submitBtn.prop('disabled', true).text('Validating address...');
        $messages.html('<div class="info-message">🔍 Checking whether the destination exists...</div>');

        // First, validate the address with geocoding
        $.ajax({
            url: cdvAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'cdv_validate_address',
                nonce: cdvAjax.nonce,
                destination: dataToSend.destination,
                country: dataToSend.country
            },
            success: function(validationResponse) {
                if (validationResponse.success) {
                    // Address is valid, proceed with travel creation
                    $submitBtn.text('Submitting...');
                    $messages.html('<div class="success-message">✅ Destination validated! Creating the trip...</div>');

                    $.ajax({
                        url: cdvAjax.ajaxurl,
                        type: 'POST',
                        data: dataToSend,
                        success: function(response) {
                            if (response.success) {
                                $messages.html('<div class="success-message">' + response.data.message + '</div>');

                                // Redirect to the travel page after 1.5 seconds
                                setTimeout(function() {
                                    window.location.href = response.data.redirect_url;
                                }, 1500);
                            } else {
                                $messages.html('<div class="error-message">' + response.data.message + '</div>');
                                $submitBtn.prop('disabled', false).text('Publish Trip 🚀');
                            }
                        },
                        error: function() {
                            $messages.html('<div class="error-message">An error occurred. Please try again later.</div>');
                            $submitBtn.prop('disabled', false).text('Publish Trip 🚀');
                        }
                    });
                } else {
                    // Address validation failed
                    $messages.html('<div class="error-message">❌ ' + validationResponse.data.message + '</div>');
                    $submitBtn.prop('disabled', false).text('Publish Trip 🚀');
                }
            },
            error: function() {
                // Validation request failed, but allow creation anyway (fallback)
                console.warn('Address validation failed, proceeding anyway');
                $submitBtn.text('Submitting...');
                $messages.html('<div class="warning-message">⚠️ Unable to validate the destination, proceeding anyway...</div>');

                $.ajax({
                    url: cdvAjax.ajaxurl,
                    type: 'POST',
                    data: dataToSend,
                    success: function(response) {
                        if (response.success) {
                            $messages.html('<div class="success-message">' + response.data.message + '</div>');

                            // Redirect to the travel page after 1.5 seconds
                            setTimeout(function() {
                                window.location.href = response.data.redirect_url;
                            }, 1500);
                        } else {
                            $messages.html('<div class="error-message">' + response.data.message + '</div>');
                            $submitBtn.prop('disabled', false).text('Publish Trip 🚀');
                        }
                    },
                    error: function() {
                        $messages.html('<div class="error-message">An error occurred. Please try again later.</div>');
                        $submitBtn.prop('disabled', false).text('Publish Trip 🚀');
                    }
                });
            }
        });
    });
});
</script>

<?php
get_footer();
