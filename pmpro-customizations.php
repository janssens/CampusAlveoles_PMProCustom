<?php
/*
Plugin Name: PMPro Customizations
Plugin URI: https://www.paidmembershipspro.com/wp/pmpro-customizations/
Description: Customizations for my Paid Memberships Pro Setup
Version: .1
Author: Paid Memberships Pro
Author URI: https://www.paidmembershipspro.com
*/
 
//Now start placing your customization code below this line

/**
 * Stop members from renewing their current membership level.
 * https://www.paidmembershipspro.com/how-to-stop-members-from-renewing-their-membership-level/
 */

function stop_members_from_renewing($okay)
{
    global $current_user;

    // If something else isn't okay, stop from running this code further.
    if (!$okay) {
        return $okay;
    }

    // If the user doesn't have a membership level carry on with checkout.
    if (!pmpro_hasMembershipLevel()) {
        return $okay;
    }

    // Check if the user's current membership level is the same for checking out.
    if (pmpro_hasMembershipLevel('6') && $_REQUEST['level'] == '6') { // Change level ID to a different level.
        $okay = false;
        pmpro_setMessage('This is your current membership level. Please select a different membership level.', 'pmpro_error');
    }

    $membership_level = pmpro_getMembershipLevelForUser($current_user->ID);
    if(!empty($membership_level) && pmpro_hasMembershipLevel('6')){
        if (empty($membership_level->enddate) || $membership_level->enddate == '0000-00-00 00:00:00'){
            return true;
        }else{

        }
    }

    // Check if the user is still under contract
    if (pmpro_hasMembershipLevel('6') && $_REQUEST['level'] == '6') { // Change level ID to a different level.
        $okay = false;
        pmpro_setMessage('This is your current membership level. Please select a different membership level.', 'pmpro_error');
    }


    return $okay;

}

add_filter('pmpro_registration_checks', 'stop_members_from_renewing', 10, 1);