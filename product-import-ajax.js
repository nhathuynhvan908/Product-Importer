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
            },
            success: function(response) {
                // Xử lý kết quả sau khi upload thành công
                if (response.imports) {
                    if (response.success) {
                        $('#upload_status').html('<i class="fas fa-check-circle"></i> Import completed successfully.');
                    } else {
                        $('#upload_status').html('<i class="fas fa-exclamation-circle"></i> Error: ' + response.data);
                    }
                } else {
                    $('#upload_status').html('<i class="fas fa-exclamation-circle"></i> Error: ' + response.errors);
                }
                console.log(response);
            },
            error: function(xhr, status, error) {
                // Xử lý lỗi
                $('#upload_status').html('<i class="fas fa-exclamation-circle"></i> Error: ' + xhr.responseText);
                console.log(xhr.responseText);
            }
        });
    });
});