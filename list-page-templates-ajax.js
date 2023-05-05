(function ($) {
    $(document).ready(function () {
        $('#list_page_templates_form').on('submit', function (e) {
            e.preventDefault();

            const inputFile = $('#list_page_templates_csv')[0].files[0];
            if (!inputFile) {
                alert('Please choose a CSV file to upload.');
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                const csvData = e.target.result;
                const rows = csvData.split('\n');
                const nonce = $('#list_page_templates_nonce').val();
                let processedCount = 0;

                const processNextUrl = function () {
                    if (processedCount >= rows.length) {
                        $('#list_page_templates_output table').append('</table>');
                        return;
                    }

                    const url = rows[processedCount].trim();
                    processedCount++;

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'list_page_templates_process_url',
                            url: url,
                            nonce: nonce
                        },
                        success: function (response) {
                            if (response.success) {
                                $('#list_page_templates_output table').append(response.data);
                                processNextUrl();
                            } else {
                                alert(response.data);
                            }
                        },
                        error: function () {
                            alert('An error occurred. Please try again.');
                        }
                    });
                };

                $('#list_page_templates_output').html(list_page_templates_vars.table_open + list_page_templates_vars.table_close);
                processNextUrl();
            };

            reader.readAsText(inputFile);
        });
    });
})(jQuery);
