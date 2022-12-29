<style>
    .data-loader {
        display: none;
    }

    .loader {
        display: flex;
        background: #fff;
        padding: 15px;
        text-align: center;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-direction: column;
        margin-top: 2rem;
        border: 1px solid #fefefe;
    }

    .loader span {
        display: block !important;
        width: 100%;
        margin-top: 15px;
    }

    .client_id_wrap {
        width: 341px;
        margin: auto;
        margin-top: 3rem;
        background: #fff;
        padding: 20px;
    }

    .client_id_wrap form {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .client_id_wrap form input {
        margin-bottom: 15px;
    }

    .data-loader.visible-loader {
        display: block !important;
    }

    .importData {
        display: none;
    }

    .book_import {
        padding: 0 15px;
        text-align: center;
    }
</style>
<?php
// $result = json_decode(wp_remote_retrieve_body(wp_remote_get('https://api.libsyn.com/post?show_id=18385')));

// $total_pai_page = (int)get_option('total_pai_page');

// if($total_pai_page > (int)$result->page_count){
//     update_option('total_pai_page', $result->page_count);
// }
// $total_page = isset($result->page_count) ? $result->page_count : $total_pai_page;
$total_page = 50;
?>
<div class="client_id_wrap">
    <form action="">
        <h2>Import Episode & Books From Libsyn API</h2>
        <label for="startPage">Start page</label>
        <input type="number" id="startPage" placeholder="Start Page min 1" min="1" required>
        <label for="endPage">End Page </label>
        <input type="number" id="endPage" max="<?php echo esc_attr($total_page); ?>" placeholder="End Page Max <= <?php echo esc_attr($total_page); ?>" required>
        <p>it will also import books from episode which episode content <code>Book: < Book Name> </code> in <code>Tags</code>.<br>
            In 1 page 20 episode listed so total End Page Number x 20 episode will import at a time. </p>
        <button id="client_id_btn" class="button button-primary">Import Episode & Books</button>
        <input type="hidden" id="max_page" value="<?php echo esc_attr($total_page); ?>">
    </form>
    <div class="importData">
        <h2>Episodes and Book Importing started, please don't reload the page! it will take few minute</h2>
        <p>Please check how many <a href="<?php echo esc_url(admin_url('edit.php?post_type=episodes')) ?>">Episodes</a> are Imported! </p>
    </div>

    <!-- <div class="data-loader">
        <div class="loader">
            Please Wait Episodes loading started!
            <span><img src="<?php echo plugin_dir_url(__FILE__) ?>assets/spinner.gif" alt=""></span>
        </div>
    </div> -->
</div>




<script>
    jQuery('#endPage').on('blur', function() {
        let max_page = parseInt(jQuery('#max_page').val())
        let end_page = parseInt(jQuery(this).val())
        if (max_page < end_page) {
            jQuery(this).val('')
        }
    })
    jQuery('#client_id_btn').on('click', function(e) {
        e.preventDefault();
        let startPage = jQuery('#startPage').val();
        let endPage = jQuery('#endPage').val();
        let client_id = jQuery('#client_id').val();
        let importText = jQuery('.importData');
        const max_page = jQuery('#max_page').val();

        if ((startPage == '' || endPage == '') || (startPage > endPage)) {
            alert('Please Enter valid start and end page number!');
            return false;
        }
        importText.show()
        setTimeout(() => {
            jQuery.post({
                url: "<?php echo admin_url('admin-ajax.php'); ?>",
                method: 'post',
                async: true,
                dataType: 'json',
                data: {
                    startPage: startPage,
                    endPage: endPage,
                    client_id: client_id,
                    action: 'savior_get_episodes_from_api'
                },
                success: function(response) {
                    if (response.success) {
                        setTimeout(() => {
                            window.location.assign("<?php echo admin_url('admin.php?page=savior-import-success') ?>")
                        }, 200);
                    }
                },
                error: function(error) {
                    if (response.success) {
                        setTimeout(() => {
                            window.location.assign("<?php echo admin_url('admin.php?page=savior-import-success') ?>")
                        }, 200);
                    }
                }
            })
        }, 200);
    })
</script>