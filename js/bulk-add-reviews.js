jQuery(document).ready(function($) {
    // Save Batch Size
    $('#bar-batch-size-form').on('submit', function(e) {
        e.preventDefault();

        var data = {
            action: 'bar_save_batch_size',
            nonce: $('#bar_batch_size_nonce_field').val(),
            batch_size: $('#bar_batch_size').val(),
        };

        $.post(bulkAddReviews.ajax_url, data, function(response) {
            if (response.success) {
                alert(response.data.message);
            } else {
                alert(response.data.message);
            }
        });
    });

    // Save Reviewer
    $('#bar-add-reviewer-form').on('submit', function(e) {
        e.preventDefault();

        var data = {
            action: 'bar_save_reviewer',
            nonce: $('#bar_nonce').val(),
            name: $('#bar_reviewer_name').val(),
            email: $('#bar_reviewer_email').val(),
            message: $('#bar_review_message').val(),
        };

        $.post(bulkAddReviews.ajax_url, data, function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert(response.data.message);
            }
        });
    });

    // Delete Reviewer
    $('.bar-delete-reviewer').on('click', function(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to delete this reviewer?')) {
            return;
        }

        var index = $(this).data('index');

        var data = {
            action: 'bar_delete_reviewer',
            nonce: bulkAddReviews.nonce,
            index: index,
        };

        $.post(bulkAddReviews.ajax_url, data, function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert(response.data.message);
            }
        });
    });

    // Start Adding Reviews
    $('#start-process').on('click', function(e) {
        e.preventDefault();

        $(this).prop('disabled', true);
        $('#progress-container').show();

        var batchNumber = 0;

        function processBatch() {
            $.ajax({
                url: bulkAddReviews.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'process_reviews_batch',
                    nonce: bulkAddReviews.nonce,
                    batch_number: batchNumber
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.complete) {
                            $('#progress-fill').css('width', '100%');
                            $('#progress-text').text('100%');
                            $('#progress-container').hide();
                            $('#completion-message').show();
                        } else {
                            batchNumber = response.data.batch_number;
                            $('#progress-fill').css('width', response.data.percentage + '%');
                            $('#progress-text').text(response.data.percentage + '%');
                            processBatch();
                        }
                    } else {
                        alert(response.data.message);
                        console.log(response);
                        $('#start-process').prop('disabled', false);
                        $('#progress-container').hide();
                    }
                },
                error: function(xhr, status, error) {
                    alert('An AJAX error occurred: ' + error);
                    console.log(xhr, status, error);
                    $('#start-process').prop('disabled', false);
                    $('#progress-container').hide();
                }
            });
        }

        processBatch();
    });

    // Delete Reviews Added by Plugin
    $('#delete-reviews').on('click', function(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to delete all reviews added by the plugin?')) {
            return;
        }

        $(this).prop('disabled', true);
        $('#delete-progress').show();

        var data = {
            action: 'bar_delete_all_reviews',
            nonce: bulkAddReviews.nonce,
        };

        $.post(bulkAddReviews.ajax_url, data, function(response) {
            if (response.success) {
                $('#delete-progress').hide();
                $('#delete-completion-message').show();
                alert(response.data.message);
            } else {
                alert(response.data.message);
                $('#delete-reviews').prop('disabled', false);
                $('#delete-progress').hide();
            }
        });
    });

    // Generate Reviews
    $('#bar-generate-reviews-form').on('submit', function(e) {
        e.preventDefault();

        $(this).find('button').prop('disabled', true);
        $('#generate-progress-container').show();

        var numberOfReviews = $('#bar_number_of_reviews').val();

        $.ajax({
            url: bulkAddReviews.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'bar_generate_reviews',
                nonce: $('#bar_generate_reviews_nonce_field').val(),
                number_of_reviews: numberOfReviews
            },
            success: function(response) {
                if (response.success) {
                    $('#generate-progress-fill').css('width', '100%');
                    $('#generate-progress-text').text('100%');
                    $('#generate-progress-container').hide();
                    $('#generate-completion-message').show();
                    alert(response.data.message);
                    $('#bar-generate-reviews-form').find('button').prop('disabled', false);
                } else {
                    alert(response.data.message);
                    $('#bar-generate-reviews-form').find('button').prop('disabled', false);
                    $('#generate-progress-container').hide();
                }
            },
            error: function(xhr, status, error) {
                alert('An AJAX error occurred: ' + error);
                console.log(xhr, status, error);
                $('#bar-generate-reviews-form').find('button').prop('disabled', false);
                $('#generate-progress-container').hide();
            }
        });
    });

    // Delete Generated Test Reviews
    $('#delete-generated-reviews').on('click', function(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to delete all generated test reviews?')) {
            return;
        }

        $(this).prop('disabled', true);
        $('#delete-generated-progress').show();

        var data = {
            action: 'bar_delete_generated_reviews',
            nonce: bulkAddReviews.nonce,
        };

        $.post(bulkAddReviews.ajax_url, data, function(response) {
            if (response.success) {
                $('#delete-generated-progress').hide();
                $('#delete-generated-completion-message').show();
                alert(response.data.message);
            } else {
                alert(response.data.message);
                $('#delete-generated-reviews').prop('disabled', false);
                $('#delete-generated-progress').hide();
            }
        });
    });
});