jQuery(document).ready(function($) {
    $('#product_import_form').on('submit', function(event) {
        event.preventDefault();
        var form_data = new FormData($(this)[0]);
        form_data.append('product_import_nonce', $('#product_import_nonce').val());
        form_data.append('action', 'handle_csv_import');
        
        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: form_data,
            contentType: false,
            processData: false,
            beforeSend: function() {
                // Show notifications or loading progress
                $('#upload_status').html('<i class="fas fa-spinner fa-spin"></i> Uploading...');
                $('#import_result').html('');
                $('#import_errors').html('');
            },
            success: function(response) {
                // Process results after successful upload
                $('#upload_status').html('<i class="fas fa-check-circle"></i> Import completed successfully.');
                //console.log(response); // Log response to check in console
                
                if (response.success) {
                    // Process when successful
                    var data = response.data;
                    var success_count = data.success_count;
                    var errors = data.errors;

                    if (success_count > 0) {
                        $('#import_result').html('Imported ' + success_count + ' products successfully.');
                    }
                    
                    if (errors.length > 0) {
                        var errorHtml = '<ul>';
                        $.each(errors, function(index, error) {
                            $.each(error, function(index_line, error_line) {
                                errorHtml += '<li>' + error_line + '</li>';
                            });
                        });
                        errorHtml += '</ul>';
                        $('#import_errors').html(errorHtml);
                    }
                } else {
                    // Show detailed errors
                    $('#upload_status').html('<i class="fas fa-exclamation-circle"></i>Error: '+ response.data);
                }
            },
            error: function(xhr, status, error) {
                // Error handling
                $('#upload_status').html('<i class="fas fa-exclamation-circle"></i> Error: ' + xhr.responseText);
                console.log(xhr.responseText);
            }
        });
    });
});