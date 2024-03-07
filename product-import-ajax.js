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
                // Hiển thị thông báo hoặc tiến trình loading
                $('#upload_status').html('<i class="fas fa-spinner fa-spin"></i> Uploading...');
                $('#import_result').html('');
                $('#import_errors').html('');
            },
            success: function(response) {
                // Xử lý kết quả sau khi upload thành công
                $('#upload_status').html('<i class="fas fa-check-circle"></i> Import completed successfully.');
                //console.log(response); // Log response để kiểm tra trong console
                
                if (response.success) {
                    // Xử lý khi thành công
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
                    // Hiển thị lỗi chi tiết
                    $('#upload_status').html('<i class="fas fa-exclamation-circle"></i>Error: '+ response.data);
                }
            },
            error: function(xhr, status, error) {
                // Xử lý lỗi
                $('#upload_status').html('<i class="fas fa-exclamation-circle"></i> Error: ' + xhr.responseText);
                console.log(xhr.responseText);
            }
        });
    });
});