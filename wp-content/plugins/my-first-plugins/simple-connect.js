<?php
/*
Plugin Name: Simple Connect Plugin
Description: Adds a modern Connect button at the top of the homepage that generates a random URL and shows "Connected" after confirmation.
Version: 1.3
Author: Railijs Didzis Grieznis
*/

if (!defined('ABSPATH')) exit; // Prevent direct access

// --- Display Connect button on homepage ---
add_action('wp_head', function () {
?>
    <style>
        .connect-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 30px;
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 14px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05);
            max-width: 480px;
            margin: 50px auto;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            text-align: center;
        }

        .connect-btn,
        .ok-btn {
            display: inline-block;
            padding: 12px 32px;
            font-size: 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            letter-spacing: 0.3px;
            transition: all 0.25s ease-in-out;
            margin-top: 10px;
        }

        .connect-btn {
            background-color: #2563eb;
            color: #ffffff;
        }

        .connect-btn:hover {
            background-color: #1e4ed8;
            transform: translateY(-2px);
        }

        .ok-btn {
            background-color: #16a34a;
            color: #ffffff;
        }

        .ok-btn:hover {
            background-color: #15803d;
            transform: translateY(-2px);
        }

        .generated-url {
            margin-top: 18px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 15px;
            color: #374151;
            word-break: break-all;
        }

        .connected-msg {
            margin-top: 20px;
            color: #16a34a;
            font-weight: 600;
            font-size: 18px;
            animation: fadeIn 0.4s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .loading-text {
            color: #6b7280;
            font-size: 15px;
            margin-top: 10px;
        }
    </style>

    <div class="connect-container">
        <h2 style="margin-bottom: 10px; color:#111827;">Connection Setup</h2>
        <p style="color:#6b7280; margin-bottom: 20px;">Generate a secure connection link below.</p>
        <button class="connect-btn">Connect</button>
        <div class="generated-url"></div>
        <div class="connected-msg"></div>
    </div>

    <script>
        (function($) {
            $(document).ready(function() {
                const container = $('.connect-container');
                const connectBtn = container.find('.connect-btn');
                const urlDiv = container.find('.generated-url');
                const connectedDiv = container.find('.connected-msg');

                connectBtn.on('click', function() {
                    urlDiv.html('<p class="loading-text">Generating secure link...</p>');
                    connectedDiv.html('');

                    $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                        action: 'simple_connect_generate',
                        nonce: '<?php echo wp_create_nonce('simple_connect_nonce'); ?>'
                    }, function(response) {
                        if (response.success) {
                            const generatedUrl = response.data.url;
                            urlDiv.html(`
                                <p><strong>Generated URL:</strong><br><a href="${generatedUrl}" target="_blank" style="color:#2563eb; text-decoration:none;">${generatedUrl}</a></p>
                                <button class="ok-btn">Confirm</button>
                            `);

                            container.find('.ok-btn').on('click', function() {
                                connectedDiv.html('Connected successfully.');
                                connectBtn.fadeOut(300);
                                urlDiv.fadeOut(300);
                            });
                        } else {
                            urlDiv.html(`<p style="color:#dc2626;">${response.data.message}</p>`);
                        }
                    });
                });
            });
        })(jQuery);
    </script>
<?php
});

// --- AJAX handler to generate random URL ---
add_action('wp_ajax_simple_connect_generate', 'simple_connect_generate');
add_action('wp_ajax_nopriv_simple_connect_generate', 'simple_connect_generate');

function simple_connect_generate()
{
    check_ajax_referer('simple_connect_nonce', 'nonce');

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error(['message' => 'You must be logged in to use this feature.']);
    }

    // Generate a random URL
    $token = wp_generate_password(10, false);
    $generated_url = site_url("/connect?user={$user_id}&token={$token}");

    // Save the generated URL for the user
    update_user_meta($user_id, '_simple_connect_url', $generated_url);

    wp_send_json_success(['url' => $generated_url]);
}

// --- Shortcode to display the last generated URL ---
function simple_connect_last_url_shortcode()
{
    $user_id = get_current_user_id();
    if (!$user_id) return '<p>You must be logged in to view your last generated link.</p>';

    $url = get_user_meta($user_id, '_simple_connect_url', true);
    return $url
        ? "<p><strong>Last generated URL:</strong><br><a href='{$url}' target='_blank' style='color:#2563eb; text-decoration:none;'>{$url}</a></p>"
        : '<p>No connection link generated yet.</p>';
}
add_shortcode('simple_connect_last_url', 'simple_connect_last_url_shortcode');
