<?php
/*
Plugin Name: Paid Memberships Pro - Campus des Alvéoles
Plugin URI: https://www.paidmembershipspro.com/wp/pmpro-customizations/
Description: Code spécifique au fonctionnement du 🌱 campus des Alvéoles
Version: .1
Author: Paid Memberships Pro
Author URI: https://www.paidmembershipspro.com
*/

class PMPro_Alveoles {

    // key level_id, value durée engagement DateInterval format
    const COMMITMENT = [ '6' => 'P1Y' , '9' => 'P1Y'];

    /**
     * @param $level_id
     * @return PMPro_Membership_Level
     */
    static function getLevel($level_id){
        $level = new PMPro_Membership_Level();
        $level->get_membership_level($level_id);
        return $level;
    }

    static function getContractedMessage($date,$level_id = null){
        if ($level_id){
            $level = self::getLevel($level_id);
            return 'Vous &ecirc;tes engagé jusqu\'au ' . date_i18n(get_option('date_format'), $date->getTimestamp()) . ' sur la formule <b><i>' . $level->name . '</i></b>';
        }else{
            return 'Engagé jusqu\'au ' . date_i18n(get_option('date_format'), $date->getTimestamp());
        }
    }

    /**
     * @return bool
     */
    static function has_commitment_level(){
        foreach (array_keys(self::COMMITMENT) as $level_id){
            if (pmpro_hasMembershipLevel($level_id)){
                return true;
            }
        }
        return false;
    }

    /**
     * @param int $level_id
     * @return bool
     */
    static function is_with_commitment(int $level_id){
        return in_array($level_id,array_keys(self::COMMITMENT));
    }

    /**
     * @param int $level_id
     * @return false | DateTime
     * @throws Exception
     */
    static function contracted(int $level_id)
    {

        $level = self::getLevel($level_id);

        if (!self::is_with_commitment($level->ID)){
            return false;
        }
        $local_time = gmdate( 'Y-m-d H:i:s', $level->startdate );
        $timezone   = wp_timezone();
        $origin   = date_create( $local_time, $timezone );
        $delay = new DateInterval(self::COMMITMENT[$level->ID]);
        $now = new DateTime('now',$timezone);
        $to = $origin->add($delay);
        if ($now < $to){ //still engaged
            return $to;
        }
        return false;
    }
}

/**
 * Stop members from renewing/canceling their current membership level.
 * https://www.paidmembershipspro.com/how-to-stop-members-from-renewing-their-membership-level/
 * @throws Exception
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
        return true;
    }
    // Check if the user's current membership level is the same for checking out.
    if (pmpro_hasMembershipLevel($_REQUEST['level'])) { // Change level ID to a different level.
        pmpro_setMessage('This is your current membership level. Please select a different membership level.', 'pmpro_error');
        return false;
    }
    if (PMPro_Alveoles::has_commitment_level()) {
        $user_id = $current_user->ID;
        $membership_levels = pmpro_getMembershipLevelsForUser( $user_id );
        /** @var PMPro_Membership_Level $level */
        foreach ($membership_levels as $l) {
            if (PMPro_Alveoles::is_with_commitment($l->ID)) {
                if ($date = PMPro_Alveoles::contracted($l->ID)) { //still engaged
                    pmpro_setMessage(PMPro_Alveoles::getContractedMessage($date,$l->ID), 'pmpro_error');
                    return false;
                }
            }
        }
    }
    return true;
}
add_filter('pmpro_registration_checks', 'stop_members_from_renewing', 10, 1);

function before_change_membership_level( $level_id, $user_id, $old_levels, $cancel_level ) {
    if ($cancel_level && PMPro_Alveoles::is_with_commitment($cancel_level)){
        if (PMPro_Alveoles::contracted($cancel_level)){ //still engaged
            wp_redirect( pmpro_url("cancel",'levelstocancel='.$cancel_level.'&pmpro_error_contracted='.$cancel_level),302 );
            exit;
        }
    }
    return true;
}
add_action( 'pmpro_before_change_membership_level', 'before_change_membership_level', 10, 4 );

function pmpro_wp_after()
{
    if(!is_admin())
    {
        global $pmpro_pages;
        if(empty($pmpro_pages))
            return;
        if (isset($_GET['pmpro_error_engaged'])){
            if ($date = PMPro_Alveoles::is_with_commitment($_GET['pmpro_error_contracted'])) { //still engaged
                pmpro_setMessage(
                    PMPro_Alveoles::getContractedMessage($date,$_GET['pmpro_error_contracted']),
                    'pmpro_error',
                    true);
            }
        }
    }
}
add_action("wp", "pmpro_wp_after", 3);

/**
 * @param string $text
 * @param stdClass $level
 * @return string
 * @throws Exception
 */
function pmpro_aveoles_account_membership_expiration_text(string $text,stdClass $level)
{
    /** @var PMPro_Membership_Level $level */
    if ($date = PMPro_Alveoles::contracted($level->ID)){
        if ($text === "---"){
            $text = PMPro_Alveoles::getContractedMessage($date);
        }else{
            $text .= '<br />'.PMPro_Alveoles::getContractedMessage($date);
        }
    }
    return $text;
}
add_filter('pmpro_account_membership_expiration_text','pmpro_aveoles_account_membership_expiration_text',10,2);

function pmpro_aveoles_member_action_links($links)
{
    if (isset($links['cancel'])){
        $re = '/<a.*href="[^?]*\?levelstocancel=([0-9])\">/ixs';
        preg_match($re, $links['cancel'], $matches, PREG_OFFSET_CAPTURE, 0);
        if (count($matches)>1){
            $level_id = $matches[1][0];
            if (PMPro_Alveoles::contracted($level_id)){
                unset($links['cancel']);
                unset($links['change']);
            }
        }
    }
    return $links;
}
add_filter('pmpro_member_action_links','pmpro_aveoles_member_action_links',10,1);


function pmpro_alveoles_url($url, $page, $querystring, $scheme){
    if ($page == 'member_profile_edit' && function_exists('bp_loggedin_user_domain')){
        return bp_loggedin_user_domain() . bp_get_profile_slug() . '/edit';
    }
    return $url;
}
add_filter( 'pmpro_url', 'pmpro_alveoles_url', 10, 4 );