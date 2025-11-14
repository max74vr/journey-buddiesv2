/**
 * Taxonomy Image Upload
 */
jQuery(document).ready(function($) {
    'use strict';

    var mediaUploader;

    // Upload image
    $(document).on('click', '.cdv-upload-taxonomy-image', function(e) {
        e.preventDefault();

        var button = $(this);
        var wrapper = button.closest('.cdv-taxonomy-image-wrapper').parent();
        var imagePreview = wrapper.find('.cdv-taxonomy-image-preview');
        var imageId = wrapper.find('.cdv-taxonomy-image-id');
        var removeButton = wrapper.find('.cdv-remove-taxonomy-image');

        // If the media uploader already exists, reopen it
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        // Create the media uploader
        mediaUploader = wp.media({
            title: 'Select or Upload Image',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });

        // When an image is selected
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();

            // Set the image preview
            imagePreview.attr('src', attachment.url).show();
            imageId.val(attachment.id);
            removeButton.show();
        });

        // Open the media uploader
        mediaUploader.open();
    });

    // Remove image
    $(document).on('click', '.cdv-remove-taxonomy-image', function(e) {
        e.preventDefault();

        var button = $(this);
        var wrapper = button.closest('.cdv-taxonomy-image-wrapper').parent();
        var imagePreview = wrapper.find('.cdv-taxonomy-image-preview');
        var imageId = wrapper.find('.cdv-taxonomy-image-id');

        // Clear the image
        imagePreview.attr('src', '').hide();
        imageId.val('');
        button.hide();
    });
});
