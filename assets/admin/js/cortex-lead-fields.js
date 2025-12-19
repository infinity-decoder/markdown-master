jQuery(document).ready(function ($) {
    // --- Lead Fields Logic ---
    var leadFieldsWrapper = $('#cortex-lead-fields-builder');

    // Initial Load - Check if we have server data passed via localized script or global variable
    // For now, we assume global variable or empty
    var existingFields = (typeof cortex_quiz_data !== 'undefined' && cortex_quiz_data.lead_fields) ? cortex_quiz_data.lead_fields : [];

    // If empty and PHP passed it via data attribute? 
    // Let's assume we fetch it from a hidden input or similar if simpler.
    // Actually, best way is to localize script. Assuming we will modify admin enqueue to localize 'cortex_quiz_data'

    function renderLeadField(field) {
        var tmpl = wp.template('cortex-lead-field-item');
        if (!field.id) field.id = 'lf_' + Date.now() + Math.floor(Math.random() * 1000);
        if (!field.label) field.label = '';
        if (!field.name) field.name = '';
        if (!field.type) field.type = 'text';

        leadFieldsWrapper.append(tmpl(field));
    }

    if (existingFields && existingFields.length > 0) {
        _.each(existingFields, function (f) {
            renderLeadField(f);
        });
    }

    $('#cortex-btn-add-lead-field').on('click', function () {
        renderLeadField({ label: 'New Field', type: 'text', required: 0 });
    });

    leadFieldsWrapper.on('click', '.cortex-remove-lead-field', function () {
        if (confirm('Remove this field?')) {
            $(this).closest('.cortex-lead-field-item').remove();
        }
    });

    leadFieldsWrapper.on('keyup', '.cortex-update-preview', function () {
        var val = $(this).val();
        $(this).closest('.cortex-lead-field-item').find('.cortex-card-header strong').html('<span class="dashicons dashicons-menu"></span> ' + (val || 'New Field'));
    });

    // Sortable
    if ($.fn.sortable) {
        leadFieldsWrapper.sortable({
            handle: '.cortex-sortable-handle',
            placeholder: 'cortex-sortable-placeholder'
        });
    }
});
