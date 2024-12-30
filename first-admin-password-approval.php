<?php
/*
Plugin Name: Change First Admin Password Approval
Description: Requires first administrator's approval before other administrators can change the Wordpress account password of the first administrator.
Version: 1.0
Author: Caleb P. Nwokocha
*/

// Hook to monitor when the first administrator's password is being updated
add_action('profile_update', function($user_id, $old_user_data) {
    $user = get_userdata($user_id);

    // Get all administrators
    $admins = get_users(['role' => 'administrator']);
    
    // Assume the first administrator is the primary one
    $first_admin = $admins[0];

    // Check if the user whose password is being changed is the first administrator
    if ($user_id === $first_admin->ID) {
        // Check if the password is being updated
        if ($user->user_pass !== $old_user_data->user_pass) {
            // Notify the first administrator that their password change is being requested
            require_approval_from_first_admin($user_id);
        }
    }
}, 10, 2);

// Function to notify the first administrator and lock the password change
function require_approval_from_first_admin($user_id) {
    // Get the first administrator
    $admins = get_users(['role' => 'administrator']);
    $first_admin = $admins[0]; // The first administrator in the list

    // Send an email to the first admin for approval
    wp_mail(
        $first_admin->user_email,
        'Password Change Approval Request',
        "An administrator is trying to change the password of the first administrator account. Please approve or deny this request."
    );

    // Lock the password change by marking the request as pending
    update_user_meta($user_id, 'password_change_pending', true);
}

// Block password changes until the first administrator approves the request
add_action('validate_password_reset', function($errors, $user) {
    // If the password change is pending approval, block the password reset
    if (get_user_meta($user->ID, 'password_change_pending', true)) {
        $errors->add('approval_required', 'Password change is pending approval from the first administrator.');
    }
}, 10, 2);

// Add an admin page for the first administrator to approve or deny password change requests
add_action('admin_menu', function() {
    add_menu_page(
        'Password Approvals', // Page title
        'Password Approvals', // Menu title
        'manage_options', // Capability required
        'password-approvals', // Menu slug
        'password_approval_page' // Callback function to display the page
    );
});

// Display the approval page in the WordPress admin panel
function password_approval_page() {
    ?>
    <h1>Password Change Requests</h1>
    <p>Here you can approve or deny password change requests for the first administrator account.</p>

    <?php
    // Get users with pending password change requests
    $admins = get_users(['role' => 'administrator']);
    foreach ($admins as $admin) {
        if (get_user_meta($admin->ID, 'password_change_pending', true)) {
            echo "<p>Pending password change request for " . $admin->user_login . "</p>";

            // Provide options to approve or deny the request
            ?>
            <form method="POST">
                <input type="hidden" name="approve_user_id" value="<?php echo $admin->ID; ?>" />
                <button type="submit" name="approve_request" class="button button-primary">Approve</button>
            </form>
            <?php
        }
    }
}

// Handle approval logic
if (isset($_POST['approve_request']) && isset($_POST['approve_user_id'])) {
    $user_id = $_POST['approve_user_id'];
    // Approve the password change request by removing the pending status
    update_user_meta($user_id, 'password_change_pending', false);

    // Optionally, send an email to the requesting admin notifying them of the approval
    wp_mail(
        get_userdata($user_id)->user_email,
        'Password Change Approved',
        'Your request to change the password of the first administrator has been approved.'
    );
}
?>
