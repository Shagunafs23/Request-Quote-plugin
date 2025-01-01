<?php
/*
Plugin Name: Request Quote Popup
Plugin URI: https://yourwebsite.com
Description: Adds a "Request Quote" button with a popup form and stores submissions in the WordPress database.
Version: 1.1
Author: Shagun Mishra
Author URI: https://yourwebsite.com
License: GPL2
*/

// --- Activation Hook to Create Database Table ---
register_activation_hook(__FILE__, 'create_quote_requests_table');

function create_quote_requests_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'quote_requests';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT(11) NOT NULL AUTO_INCREMENT,
        full_name VARCHAR(255) NOT NULL,
        phone_number VARCHAR(20) NOT NULL,
        email VARCHAR(255) NOT NULL,
        product_name VARCHAR(255) NOT NULL,
        product_code VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        quantity INT(11) NOT NULL,
        submission_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// --- Add "Request Quote" Button on Product Pages ---
function add_request_quote_button()
{
    echo '<button id="request-quote-button" class="button alt" style="margin-top: 10px;">Request a Quote</button>';
}
add_action('woocommerce_after_add_to_cart_button', 'add_request_quote_button');

// --- Add Quote Form Modal on Product Pages ---
function add_quote_popup_to_product_page()
{
    ?>
    <div class="modal-overlay" ></div>
    <div id="quote-form-popup" style="display: none;">
        <div class="quote-form-content">
            <button id="close-quote-popup" style="float: right;">X</button>
            <h2>Request a Quote</h2>
            <form id="quotation-form">
                <div id="contact-info-step">
                    <label for="full-name">Full Name:</label>
                    <input type="text" id="full-name" name="full_name" required>
                    <label for="phone-number">Phone Number:</label>
                    <input type="text" id="phone-number" name="phone_number" required>
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                    <button type="button" id="next-step-1">Next</button>
                </div>
                <div id="quotation-form-step" style="display: none;">
                    <label for="product-name">Product Name:</label>
                    <input type="text" id="product-name" name="product_name" value="<?php echo esc_attr(get_the_title()); ?>" readonly>
                    <label for="product-code">Product Code:</label>
                    <input type="text" id="product-code" name="product_code" value="<?php echo esc_attr(get_post_meta(get_the_ID(), '_sku', true)); ?>">
                    <label for="message">Message:</label>
                    <textarea id="message" name="message"></textarea>
                    <label for="quantity">Quantity:</label>
                    <input type="number" id="quantity" name="quantity" min="1" value="1">
                    <button type="submit">Submit</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}
add_action('woocommerce_after_main_content', 'add_quote_popup_to_product_page');

// --- Handle Form Submission and Store Data in Database ---
function send_quote_request()
{
    global $wpdb;

    if (!empty($_POST['form_data'])) {
        parse_str($_POST['form_data'], $form_data);

        $table_name = $wpdb->prefix . 'quote_requests';
        $wpdb->insert(
            $table_name,
            [
                'full_name' => sanitize_text_field($form_data['full_name']),
                'phone_number' => sanitize_text_field($form_data['phone_number']),
                'email' => sanitize_email($form_data['email']),
                'product_name' => sanitize_text_field($form_data['product_name']),
                'product_code' => sanitize_text_field($form_data['product_code']),
                'message' => sanitize_textarea_field($form_data['message']),
                'quantity' => intval($form_data['quantity']),
            ]
        );

        wp_send_json_success('Your quote request has been submitted successfully!');
    } else {
        wp_send_json_error('Invalid form submission.');
    }

    wp_die();
}
add_action('wp_ajax_send_quote_request', 'send_quote_request');
add_action('wp_ajax_nopriv_send_quote_request', 'send_quote_request');

// --- Add Admin Menu for Quote Requests ---
function add_quote_requests_menu()
{
    add_menu_page(
        'Quote Requests',
        'Quote Requests',
        'manage_options',
        'quote-requests',
        'display_quote_requests',
        'dashicons-list-view',
        25
    );
}
add_action('admin_menu', 'add_quote_requests_menu');

// --- Handle Delete Request ---
function handle_quote_delete()
{
    if (isset($_GET['delete_quote']) && is_numeric($_GET['delete_quote'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'quote_requests';
        $quote_id = intval($_GET['delete_quote']);
        $wpdb->delete($table_name, ['id' => $quote_id]);
        wp_redirect(admin_url('admin.php?page=quote-requests'));
        exit;
    }
}
add_action('admin_init', 'handle_quote_delete');

// --- Display Stored Submissions in Admin Panel ---
function display_quote_requests()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'quote_requests';
    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY submission_date DESC");

    echo '<div class="wrap"><h1>Quote Requests</h1>';
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>
            <th>ID</th>
            <th>Full Name</th>
            <th>Phone Number</th>
            <th>Email</th>
            <th>Product Name</th>
            <th>Product Code</th>
            <th>Message</th>
            <th>Quantity</th>
            <th>Date</th>
            <th>Actions</th>
        </tr></thead>';
    echo '<tbody>';

    foreach ($results as $row) {
        $delete_url = esc_url(add_query_arg(['delete_quote' => $row->id], admin_url('admin.php?page=quote-requests')));
        $edit_url = esc_url(add_query_arg(['edit_quote' => $row->id], admin_url('admin.php?page=quote-requests')));
        echo '<tr>';
        echo '<td>' . esc_html($row->id) . '</td>';
        echo '<td>' . esc_html($row->full_name) . '</td>';
        echo '<td>' . esc_html($row->phone_number) . '</td>';
        echo '<td>' . esc_html($row->email) . '</td>';
        echo '<td>' . esc_html($row->product_name) . '</td>';
        echo '<td>' . esc_html($row->product_code) . '</td>';
        echo '<td>' . esc_html($row->message) . '</td>';
        echo '<td>' . esc_html($row->quantity) . '</td>';
        echo '<td>' . esc_html($row->submission_date) . '</td>';
        echo '<td>
                <a href="' . $delete_url . '" class="button button-secondary" onclick="return confirm(\'Are you sure you want to delete this quote?\')">Delete</a>
              </td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}

// --- Enqueue Scripts and Styles for Modal ---
function enqueue_custom_modal_scripts()
{
    wp_enqueue_script('jquery');
    wp_add_inline_script('jquery', '
        jQuery(document).ready(function ($) {
            // Open modal
            $("#request-quote-button").on("click", function (e) {
                e.preventDefault();
                $(".modal-overlay").fadeIn();
                $("#quote-form-popup").fadeIn();
            });

            // Close modal
            $("#close-quote-popup, .modal-overlay").on("click", function (e) {
                e.preventDefault();
                $(".modal-overlay").fadeOut();
                $("#quote-form-popup").fadeOut();
            });

            // Navigate to the next step
            $("#next-step-1").on("click", function (e) {
                e.preventDefault();
                $("#contact-info-step").hide();
                $("#quotation-form-step").fadeIn();
            });

            // Submit form via AJAX
            $("#quotation-form").on("submit", function (e) {
                e.preventDefault();
                let $submitButton = $(this).find("button[type=\'submit\']");
                $submitButton.prop("disabled", true).text("Submitting...");

                $.post(quoteAjax.ajaxurl, {
                    action: "send_quote_request",
                    form_data: $(this).serialize()
                }, function (response) {
                    alert(response.data);
                    $(".modal-overlay").fadeOut();
                    $("#quote-form-popup").fadeOut();
                    $submitButton.prop("disabled", false).text("Submit");
                }).fail(function () {
                    alert("Something went wrong. Please try again.");
                    $submitButton.prop("disabled", false).text("Submit");
                });
            });
        });
    ');
    wp_enqueue_style('quote-popup-style', plugin_dir_url(__FILE__) . 'css/quote-popup.css');
    wp_localize_script('jquery', 'quoteAjax', ['ajaxurl' => admin_url('admin-ajax.php')]);
}
add_action('wp_enqueue_scripts', 'enqueue_custom_modal_scripts');