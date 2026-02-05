<?php
/*
Plugin Name: Bike Club
Plugin URI: 
Description: Add bike club specific features
Version: 0.1
Author: Jonathan Eiseman
Author URI: 
License: 
License URI: 
*/

include( plugin_dir_path( __FILE__ ) . 'ron.php');
include( plugin_dir_path( __FILE__ ) . 'rwgps.php');

require 'vendor/autoload.php';

use Dompdf\Dompdf;

const PROPOSED_RIDE_LEADER_USERID = 5658;

/* 
global $pmprosm_sponsored_account_levels;
$pmprosm_sponsored_account_levels = array (
    4 => array(
        'main-level-id' => 4,
        'sponsored_level_id' => 1,
        'seats' => 1,
        'seat_cost' => 0,
        'add_code_to_conformation_email' => true,
        'discount_code' => array(
            'expiration_number' => '1',
            'expiration_period' => "'Year'"
        ),
        'children_get_name' => true,
        'children_hide_username' => false,
        'children_hide_email' => false,
        'children_hide_password' => false
    ),
    5 => array(
        'main-level-id' => 5,
        'sponsored_level_id' => 2,
        'seats' => 1,
        'seat_cost' => 0,
        'discount_code' => array(
            'expiration_number' => '2',
            'expiration_period' => "'Year'"
        ),
        'children_get_name' => true,
        'children_hide_username' => false,
        'children_hide_email' => false,
        'children_hide_password' => false
    ),
    6 => array(
        'main-level-id' => 6,
        'sponsored_level_id' => 3,
        'seats' => 1,
        'seat_cost' => 0,
        'discount_code' => array(
            'expiration_number' => '3',
            'expiration_period' => "'Year'"
        ),
        'children_get_name' => true,
        'children_hide_username' => false,
        'children_hide_email' => false,
        'children_hide_password' => false
    )
);
*/

function homepage_template_redirect()
{
    // if logged in then redirect to the welcome page
    if( is_front_page() && is_user_logged_in() && (!defined('ELEMENTOR_PATH') || !class_exists('Elementor\Plugin') || ! \Elementor\Plugin::$instance->preview->is_preview_mode() ))
    {
        wp_redirect(home_url('/welcome/'));
        exit();
    }
}

function bk_login_logo() { ?>
    <style type="text/css">
        #login h1 a, .login h1 a {
             display: none;
        }
    </style>
<?php }
// add_action( 'login_enqueue_scripts', 'bk_login_logo' );

// add_action( 'template_redirect', 'homepage_template_redirect' );
function bpfr_remove_visibility_level( $levels ) {
	global $bp;

		// if ( isset( $levels['loggedin'] ) ) {
			// unset( $levels['loggedin'] );
		// }

		if ( isset( $levels['public'] ) ) {
			unset( $levels['public'] );
		}
$levels['adminsonly']['label'] =  _x( 'Only visible for me', 'Visibility level setting', 'buddypress' );
	return $levels;
}
add_filter( 'simple_email_queue_max', function () { return 50; });
add_filter( 'wp_lazy_loading_enabled', '__return_false' );

add_filter('generate_leave_comment', function($txt) {
    if (!empty($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], "/ride/") !== false )
        $txt .= " for other riders and ride leader";
    return $txt;
});

add_filter( 'bp_xprofile_get_visibility_levels', 'bpfr_remove_visibility_level' );

add_action('bp_setup_nav', 'bike_remove_forums_func', 50);
function bike_remove_forums_func()
{
    if (!is_user_logged_in() || !current_user_can('active'))
        bp_core_remove_nav_item('forums');
}

// add_action('after_setup_theme', 'remove_admin_bar');
function remove_admin_bar() {
    if (!current_user_can('manage_options') && !is_admin()) {
      show_admin_bar(false);
    }
}

function bike_scripts(){
    //file where AJAX code will be found 
	if ( is_front_page() )
        wp_enqueue_script( 'bk-slider-handle', plugins_url('js/slider.js', __FILE__), array('jquery'), "3.10" );
    wp_enqueue_script( 'bk-script-handle', plugins_url('js/bike_script_file.js', __FILE__), array('jquery'), "3.27" );

   //passing variables to the javascript file
   wp_localize_script('bk-script-handle', 'frontEndAjax', array(
    'ajaxurl' => admin_url( 'admin-ajax.php' ),
    'nonce' => wp_create_nonce('ajax_nonce')
    ));
}
add_action( 'wp_enqueue_scripts', 'bike_scripts' );

function convertDateFormat($date, $informat, $outformat)
{
	$tz = new DateTimeZone(wp_timezone_string());
    $d = DateTime::createFromFormat($informat, $date, $tz);
	if ($d == false)
	    return false;
	return $d->format($outformat);
}

function bk_move_from_waitlist($rpod, $wlapod) {
    $tz = new DateTimeZone(wp_timezone_string());
    $ride_date = $rpod->display('ride_date');
    $start_time = $rpod->display('time');
	$ridestart = new DateTime($ride_date . ' ' . $start_time, $tz);
	$mintime = new DateTime("now", $tz);
	if ($ridestart < $mintime)
	    return;
    $wlapod->save('wait_list_number', 0);
    $rider = $wlapod->field('rider');
    if ( ! $rider )
		return;
	$rideid = $rpod->field('ID');
    bk_attendee_list_change( $rpod, $wlapod, "No", 0, true );
    bk_remove_from_other_rides($rider['ID'], $rideid);
    $tourfield = $rpod->field('tour');
    $tourid = $tourfield['ID'];
    $tpod = pods('tour', $tourid);
    $start = $tpod->display('start_point');
    $rl = $rpod->field('ride_leader');
    $rlid = $rl['ID'];
    $rlpod = pods('user', $rlid);
    $dow_obj = new DateTime($ride_date, $tz);
    $dow = $dow_obj->format("l");
    $leader = $rlpod->field('display_name');
    $user_info = get_userdata($rider['ID']);
    $to_email = $user_info->user_email;
    $rc = pods('role', 13256);
	$rc_user = $rc->field('member');
    $fromname = $rc_user['display_name'];
    // $from = 'ridecoordinator@email.mafw.org';
    $from = 'ridecoordinator@mafw.org';
	$subject = "MAF: You are signed up for a ride";
	$msg = 'You are now signed up for a ride that you were previously waitlisted on. The ride is from ' . $start . ' starting at ' . $start_time . ' on ' . $dow . ', ' .  $ride_date . '. You can see the ride <a href="' . get_site_url() . '/ride/' . $rideid . '">here</a>';
	$headers = array('Content-Type: text/html; charset=UTF-8', 'From: ' . $fromname . ' <' . $from . '>');
    wp_mail($to_email, $subject, $msg, $headers);
}
function bk_find_wl_apod($rpod)
{
	$wlapod = 0;
	$attendees = $rpod->field('attendees');
	$min_wlnum = 10000;
    if (!empty($attendees)) {
		$userid_arr = array();
		sort($attendees);
        foreach ($attendees as $attendee) {
			if (!is_array($attendee))
		        $attendeeid = $attendee;
			else
		        $attendeeid = $attendee['ID'];
			$userid = get_post_field('post_author', $attendeeid);
			if (empty($userid) || $userid == 0)
			    continue;
			if (!array_key_exists($userid, $userid_arr))
			    $userid_arr[$userid] = $attendeeid;
			else
			    continue;
            $user = get_userdata($userid);
			if (empty($user))
			    continue;
            $apod = pods('ride-attendee', $attendeeid);
			$ra_status = $apod->field('ride_attendee_status');
			if ($ra_status != "No") {
			    $wlnum = $apod->field('wait_list_number');
				if (empty($wlnum))
				    $wlnum = 0;
				if ($wlnum != 0) {
				    if ($wlnum < $min_wlnum) {
					    $min_wlnum = $wlnum;
						$wlapod = $apod;
				    }
			    }
			}
        }
    }
	return $wlapod;
}

function bk_attendee_list_change( $rpod, $aid, $status = "No", $remain = 0, $from_wl = false, $ride_attendees = null, $waitlist = null )
{
	$do_notification = $rpod->field("ride_attendee_notification"); 
	$send_text = $rpod->field("send_text_message"); 
	if ( empty($do_notification) && empty($send_text) ) {
		return;
	}
	$msg = "";
	if ( $ride_attendees === null )  {
		$count = ride_attendees($rpod, $ride_attendees, $waitlist);
	}
	$rl = $rpod->field('ride_leader');
	if ( $rl !== false ) {
	    $rlid = $rl['ID'];
	    $rlpod = pods("user", $rlid);
        $apod = pods('ride-attendee', $aid);
		$rider = $apod->field('rider');
		if ( $rider ) {
			$user_info = get_userdata($rider['ID']);
		}
		else {
			$userid = get_post_field('post_author', $aid);
    		$user_info = get_userdata($userid);
		}
		$rider_name = $user_info ? $user_info->display_name : "";
		$msg = $rider_name;
		if ( $from_wl ) {
			$msg .= " added to ride from waitlist on your ride: ";
		}
		else if ( $status == "No" ) {
			$wlnum = $apod->field('wait_list_number');
			if ( !empty($wlnum) && $wlnum > 0 ) {
				$msg .= " removed from waitlist on your ride: ";
			}
			else {
				$msg .= " removed from your ride: ";
			}
		}
		else {
			$wlnum = $apod->field('wait_list_number');
			if ( !empty($wlnum) && $wlnum > 0 ) {
				$msg .= " added to waitlist on your ride: ";
			}
			else {
				$msg .= " added to your ride: ";
			}
		}
    	$ride_date = $rpod->display('ride_date');
    	$tz = new DateTimeZone(wp_timezone_string());
    	$dow_obj = new DateTime($ride_date, $tz);
    	$dow = $dow_obj->format("l");
		$ride_date = $dow . ", " . $ride_date;
    	$start_time = $rpod->display('time');
		$txtmsg = $msg;
		if ( $do_notification ) {
        	$rl_email = $rlpod->field('user_email');
			$msg .= "<br />Ride Attendees: " . $ride_attendees;
			if (!empty($waitlist)) {
		    	$msg .= "<br />Waitlist: " . $waitlist;
			}
			$subject =  "Attendee list changed for your ride scheduled for: " . $dow . ' ' . $rpod->display('ride_date') . ' ' . $start_time;
    		$rc = pods('role', 13256);
			$rc_user = $rc->field('member');
    		$fromname = $rc_user['display_name'];
    		// $from = 'ridecoordinator@email.mafw.org';
    		$from = 'ridecoordinator@mafw.org';
	    	$headers = array('Content-Type: text/html; charset=UTF-8', 'From: ' . $fromname . ' <' . $from . '>');
        	wp_mail($rl_email, $subject, $msg, $headers);
		}
		if ( $send_text ) {
			$cell_phone = strip_tags(xprofile_get_field_data('Mobile Phone', $rlid));
			if (!empty($cell_phone)) {
				$cell_phones =  [ bk_fix_num($cell_phone) ];
				$txtmsg .=  $dow . ' ' . $rpod->display('ride_date') . ' ' . $start_time;
				if (function_exists('wp_sms_send')) {
                	wp_sms_send($cell_phones, $txtmsg, false);
				}
			}
		}
	}
}

function bikeride_mystatus_function() { 
    if (!class_exists('Pods'))
        return;
    //checking the nonce. will die if it is no good.
    check_ajax_referer('ajax_nonce', 'nonce');

    // now we can get the data we passed via $_REQUEST[].
    // make sure it isn't empty first.
    if(! empty( $_REQUEST['mystatus'] ) ){
        $status = esc_html($_REQUEST['mystatus']);
    }
    else
        return;
    if (!empty($_POST['rideID']) && function_exists('xprofile_get_field_data'))
        $rideid = absint($_POST['rideID']);
    else
        return;
    // $msg = "status=" . $status;
    // trigger_error($msg);
	$my_ra_status = "No";
    if ($rideid != 0) {
        $current_userid = get_current_user_id();
	    $upod = pods('user', $current_userid);
        $rpod = pods('ride', $rideid);
		$rl = $rpod->field('ride_leader');
	    $attendees = $rpod->field('attendees');
		$limit = $rpod->field('maximum_signups');
		if (empty($limit))
		    $limit = 0;
		if (array_key_exists('car_license', $_POST)) {
            $car_license = sanitize_text_field($_POST['car_license']);
            if (null == xprofile_get_field_data('Vehicle License', $current_userid))
                xprofile_set_field_data('Vehicle License', $current_userid, $car_license);
		}
		else
		    $car_license = "";
		if (array_key_exists('emergency_phone', $_POST)) {
            $emergency_phone = strip_tags(sanitize_text_field($_POST['emergency_phone']));
            if (null == xprofile_get_field_data('Emergency Number', $current_userid))
                xprofile_set_field_data('Emergency Number', $current_userid, $emergency_phone);
		}
		else
            $emergency_phone = "";
		if (array_key_exists('cell_phone', $_POST)) {
            $cell_phone = strip_tags(sanitize_text_field($_POST['cell_phone']));
            if (null == xprofile_get_field_data('Mobile Phone', $current_userid))
                xprofile_set_field_data('Mobile Phone', $current_userid, $cell_phone);
		}
		else
            $cell_phone = "";
        $found = 0;
        $user = pods('user', $current_userid);
        $data = array(
                    'title' => $rideid . ' - ' . $user->field('display_name'),
                    'ride_attendee_status' => $status,
                    'car_license' => $car_license,
                    'emergency_phone' => $emergency_phone,
                    'cell_phone' => $cell_phone
                );
		$ridercount = 1; // 1 for the ride leader
		$max_wlnum = 0;
		$min_wlnum = 10000;
        if (!empty($attendees)) {
			$userid_arr = array();
		    sort($attendees);
            foreach ($attendees as $attendee) {
				if (!is_array($attendee))
			        $attendeeid = $attendee;
				else
			        $attendeeid = $attendee['ID'];
			    $userid = get_post_field('post_author', $attendeeid);
			    if (empty($userid) || $userid == 0)
			        continue;
                $user = get_userdata($userid);
				if (empty($user))
				    continue;
				if (!array_key_exists($userid, $userid_arr))
				    $userid_arr[$userid] = $attendeeid;
				else
				    continue;
				$is_rl = ($rl['ID'] == $userid);
                $apod = pods('ride-attendee', $attendeeid);
				$ra_status = $apod->field('ride_attendee_status');
                if ($found == 0 && $current_userid == $userid) {
                    $found = 1;
					$mypod = $apod;
					$aid = $attendeeid;
					if (empty($mywlnum))
					    $mywlnum = 0;
				    $mywlnum = $apod->field('wait_list_number');
					$my_ra_status = $ra_status;
                    if ($apod->field('car_license') != $car_license) {
						if (empty($car_license)) {
							if ($ra_status == "Yes")
							    $status = "Yes";
						    continue;
						}
                        $apod->save('car_license', $car_license);
					}
                    if ($apod->field('emergency_phone') != $emergency_phone) {
						if (empty($emergency_phone)) {
							if ($ra_status == "Yes")
							    $status = "Yes";
						    continue;
						}
                        $apod->save('emergency_phone', $emergency_phone);
					}
                    if ($apod->field('cell_phone') != $cell_phone) {
						if (empty($cell_phone)) {
							if ($ra_status == "Yes")
							    $status = "Yes";
						    continue;
						}
                        $apod->save('cell_phone', $cell_phone);
					}
                }
				if ($ra_status != "No") {
				    $wlnum = $apod->field('wait_list_number');
					if (empty($wlnum))
					    $wlnum = 0;
					if ($wlnum == 0) {
				        // don't double count the ride leader
				        if ($rl['ID'] != $userid)
				            $ridercount++;
					}
				    else {
					    if ($wlnum > $max_wlnum)
				            $max_wlnum = $wlnum;
					    if ($wlnum < $min_wlnum) {
						    $min_wlnum = $wlnum;
							$wlapod = $apod;
					    }
				    }
				}
            }
        }
		$set_waitlist = false;
        $remain = $limit > 0 ? $limit - $ridercount : 0;
        if ($found == 0 && $status != "No") {
            $pod = pods('ride-attendee');
            $aid = $pod->add($data);
            if (!empty($aid) && is_int($aid) && $aid > 0)
                update_post_meta($aid, '_members_access_role', 'active');
            $rpod->add_to('attendees', $aid); 
			$apod = pods('ride-attendee', $aid);
			$apod->add_to('rider', $current_userid);
	        $upod->add_to('rides', $aid); 
			$my_ra_status = "No";
			$mypod = $apod;
			$mywlnum = 0;
        }
		if (!empty($mypod)) {
		    if ($remain <= 0 && !$is_rl) {
				if ($status != "No") {
					if ($my_ra_status == "No")
		                $mypod->save('wait_list_number', ++$max_wlnum);
						$mywlnum = $max_wlnum;
				}
			    else {
		            $mypod->save('wait_list_number', 0);
		            if (!empty($wlapod) && $limit > 0 && $limit == $ridercount && $mywlnum == 0 && $my_ra_status != "No")
						bk_move_from_waitlist($rpod, $wlapod);
						$mywlnum = 0;
			    }
		    }
			$is_rl = ($rl['ID'] == $current_userid);
			if ($status != "No")  {
                $mypod->save('ride_attendee_status', $status);
				if ($remain > 0 && !$is_rl) {
				    if ($my_ra_status == "No" && $mywlnum == 0) {
				        $ridercount++;
                        $remain = $limit > 0 ? $limit - $ridercount : 0;
				    }
                    bk_remove_from_other_rides($current_userid, $rideid);
			    }
			}
		    elseif (!$is_rl) {
				if ($my_ra_status != "No" && $mywlnum == 0) {
				    $ridercount--;
                    $remain = $limit > 0 ? $limit - $ridercount : 0;
				}
			    $aid = $mypod->field('ID');
				$upod->remove_from('rides', $aid);
                $apod = pods('ride-attendee', $aid);
                $apod->save('ride_attendee_status', "No");
			}
	    }
    }
	else
	    return;
	$ride_attendees = array();
	$waitlist = array();
	$count = ride_attendees($rpod, $ride_attendees, $waitlist);
    $remain = $limit > 0 ? $limit - $count : 0;
	if ( $my_ra_status != $status && !empty($aid) && $aid > 0 ) {
        bk_attendee_list_change( $rpod, $aid, $status, $remain, false, $ride_attendees, $waitlist );
	}
    $ajax_response = array('data_from_backend' => $ride_attendees,
						   'waitlisted' => $waitlist,
	                       'remaining' => $remain
	                      );

    bk_clear_cache();
    echo json_encode( $ajax_response );  //always echo an array encoded in json 
    die();
}
add_action( 'wp_mystatus_function', 'bikeride_mystatus_function' );
add_action( 'wp_ajax_bikeride_mystatus_function', 'bikeride_mystatus_function' );

function bike_display_DOW($dayNum, $date_obj, $sday)
{
    if ($sday == '')
        $sday = 'day0';
    $result = '<a ';
    if ($sday == $dayNum)
       $result .= 'class="form_row_label_sel"';
    else
       $result .= 'href="' . get_site_url() . '/7-day-ride-schedule-2/?from=' . $dayNum . '"';
    if ($dayNum == 'day0')
         $result .= '">Today ' . $date_obj->format("n/j") . ' </a> - ';
    elseif ($dayNum == 'day6')
         $result .= '">' . $date_obj->format("D n/j") . ' </a>';
    else
         $result .= '">' . $date_obj->format("D n/j") . ' </a> - ';
    return $result;
}
function bk_posts_where( $where, $query ) {
    global $wpdb;

    $ends_with = esc_sql( $query->get( 'ends_with' ) );

    if ( $ends_with ) {
        $where .= " AND $wpdb->posts.post_title LIKE '%$ends_with'";
    }

    return $where;
}
add_filter( 'posts_where', 'bk_posts_where', 10, 2 );

function bk_find_attendee($attendees, $post_id)
{
    foreach ($attendees as $attendee) {
        if (!is_array($attendee))
            $aid = $attendee;
        else {
            $aid = bk_find_attendee($attendee, $post_id);
            if ($aid != false)
                return $aid;
        }
        if ($aid == $post_id) {
            $apod = pods('ride-attendee', $aid);
            $status = $apod->field('ride_attendee_status');
            $wait_listed = $apod->field('wait_list_number');
            // error_log("aid:" . $aid . " status:" . print_r($status, true) . " wl:" . $wait_listed);
            if ($status != "No" && empty($wait_listed))
                return $aid;
        }
    }
    return false;
}
function bk_get_start_and_end_date( $addone = false )
{
    if (!empty($_GET['start_date'])) {
        $start_date = $_GET['start_date'];
    }
    else
        $start_date = "";

	$tz = new DateTimeZone(wp_timezone_string());
	if (!empty($_GET['start_date']))
	    $start_date = convertDateFormat($_GET['start_date'], 'm/d/Y', 'Y-m-d');
	if (empty($start_date) || $start_date == false) {
        $curdate_obj = new DateTime("now", $tz);
        $start_date = $curdate_obj->format('Y-m-d');
    }
    if (!empty($_GET['end_date'])) {
	    $end_date = convertDateFormat($_GET['end_date'], 'm/d/Y', 'Y-m-d');
		if ($end_date != false && $addone ) {
	        $end_date_obj = new DateTime($end_date . " + 1 day", $tz);
            $end_date = $end_date_obj->format("Y-m-d");
	    }
	}
    if (!empty($_GET['daterange']) && $_GET['daterange'] == "default") {
        $curdate = new DateTime("now", $tz);
        $enddate = new DateTime("now + 3 months", $tz);
        $start_date = $curdate->format('Y-m-d');
        $end_date = $enddate->format('Y-m-d');
	}
    if (empty($end_date) || $end_date == false) {
        $end_date_obj = new DateTime("now + 8 days", $tz);
        $end_date = $end_date_obj->format("Y-m-d");
	}
    return [ 'start_date' => $start_date, 'end_date' => $end_date ];
}

add_action( 'generate_before_404', 'bk_before_404' );
function bk_before_404()
{
	bk_clear_object_cache();
}

add_shortcode('clear-object-cache', 'bk_clear_object_cache');
function bk_clear_object_cache()
{
    global $wpdb, $wp_object_cache, $nginx_purger;

    if (!is_user_logged_in() || !current_user_can('active'))
        return;
	bk_clear_cache();
	if ( function_exists( 'pods_api' ) )
		pods_api()->cache_flush_pods();

	flush_rewrite_rules( false );

	if (!empty($nginx_purger) && is_object($nginx_purger)) {
		$nginx_purger->purge_all();
	}

    $wpdb->queries = [];

    if ( ! is_object( $wp_object_cache ) ) {
        return;
    }

    // The following are Memcached (Redux) plugin specific (see https://core.trac.wordpress.org/ticket/31463).
    if ( isset( $wp_object_cache->group_ops ) ) {
        $wp_object_cache->group_ops = [];
    }
    if ( isset( $wp_object_cache->stats ) ) {
        $wp_object_cache->stats = [];
    }
    if ( isset( $wp_object_cache->memcache_debug ) ) {
        $wp_object_cache->memcache_debug = [];
    }
    // Used by `WP_Object_Cache` also.
    if ( isset( $wp_object_cache->cache ) ) {
        $wp_object_cache->cache = [];
    }
	return "done";
}

add_shortcode('bkridesirode', 'bk_rides_i_rode_table');
function bk_rides_i_rode_table()
{
    $ret = "";
    $current_user = wp_get_current_user();
    $params = array(
        'limit' => -1,
        'where' => "t.post_title LIKE '%" . esc_sql($current_user->display_name) . "'",
    );
    $pod = pods('ride-attendee', $params);

    $ridelist = [];
    while ($pod->fetch()) {
        $post_id = $pod->id();
        $post_title = $pod->field('post_title');
		$rpod = null;
        $arr = preg_split('/ - /', $post_title);
        if ($arr && is_array($arr) && count($arr) > 0 ) {
			$rideid = $arr[0];
            $rpod = pods('ride', $rideid);
        }
        if ($rpod && $rpod->field('ride-status') == 4 ) {
            $attendees = $rpod->field('attendees');
            if (!empty($attendees)) {
                $aid = bk_find_attendee($attendees, $post_id);
                if ($aid != false) {
                    $tz = new DateTimeZone(wp_timezone_string());
                    $ride_date = $rpod->display('ride_date');
                    $start_time = $rpod->display('time');
                    $ridestart = new DateTime($ride_date . ' ' . $start_time, $tz);
		            $ptpage = '<a href="#" class="pace-terrain">';
		            $pace = $ptpage . $rpod->display('pace') . '</a>';
		            $rl = $rpod->field('ride_leader');
		            $rlpod = pods("user", $rl['ID']);
		            $rlname = $rlpod->field('display_name');
		    $ride_leader = '<a href="' . get_site_url() . '/members/' . $rlname . '/profile/">' . $rlname . '</a>';
                    $tourfield = $rpod->field('tour');
                    $tpod = pods('tour', $tourfield['ID']);
        			$miles = intval($tpod->field('miles'));
        			$climb = intval($tpod->field('climb'));
                    $start = $tpod->field('start_point');
                    $url = get_site_url() . '/start_point/' . $start['post_name'] . '/';
                    $startloc = '<a href="' . esc_url($url) . '">' . $start['post_title'] . '</a>';
                    $tour = '<a href="' . get_site_url() . '/ride/' . $rideid . '">' . $tpod->field('tour_number') . ' - ' . $tpod->field('post_title') . '</a>';
                    $ride_entry = [ 'ride_date' => $ride_date, 'start_time' => $start_time, 'ridestart' => $ridestart, 'pace' => $pace, 'miles' => $miles, 'climb' => $climb, 'rideleader' => $ride_leader, 'startloc' => $startloc, 'detail' => $tour ];
                    // error_log(print_r($ride_entry, true));
                    $ridelist[] = $ride_entry;
                }
            }
        }
    }
    if (!empty($ridelist)) {
        usort($ridelist, function($a, $b) {
            return $b['ridestart'] < $a['ridestart'] ? -1 : 1;
        });
        $ret = '<table id="my-signups" class="display ride_table" style="width:100%"><thead>
            <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Pace</th>
                <th>Distance</th>
                <th>Climb</th>
                <th>Starting Point</th>
                <th>Ride Leader</th>
                <th>Ride Detail</th>
            </tr>
        </thead>
        <tbody>';
        foreach ($ridelist as $ride) {
            $ret .= '<tr>';
            $ret .= '<td>' . $ride['ride_date'] . '</td>';
            $ret .= '<td>' . $ride['start_time'] . '</td>';
            $ret .= '<td>' . $ride['pace'] . '</td>';
            $ret .= '<td>' . $ride['miles'] . '</td>';
            $ret .= '<td>' . $ride['climb'] . '</td>';
            $ret .= '<td>' . $ride['startloc'] . '</td>';
            $ret .= '<td>' . $ride['rideleader'] . '</td>';
            $ret .= '<td>' . $ride['detail'] . '</td>';
            $ret .= '</tr>';
        }
        $ret .= '</tbody></table>';
    }
    return $ret;
}
function bk_user_attended_rides($user)
{
    $ride_count = 0;
    $params = array(
        'limit' => -1,
        'where' => "t.post_title LIKE '%" . esc_sql($user->display_name) . "'",
    );
    $pod = pods('ride-attendee', $params);

    $ridelist = [];
    while ($pod->fetch()) {
        $post_id = $pod->id();
        $post_title = $pod->field('post_title');
		$rpod = null;
        $arr = preg_split('/ - /', $post_title);
        if ($arr && is_array($arr) && count($arr) > 0 ) {
			$rideid = $arr[0];
            $rpod = pods('ride', $rideid);
        	if ($rpod && $rpod->field('ride-status') == 4 ) {
				$ride_count++;
        	}
		}
    }
    return $ride_count;
}
function bk_has_attended_rides($userid)
{
    $ret = "";
    $user = get_userdata($userid);
    $params = array(
        'limit' => 1,
        'where' => "t.post_title LIKE '%" . esc_sql($user->display_name) . "' AND t.post_date_gmt > DATE_SUB(NOW(), INTERVAL 1 YEAR)",
    );
    $pod = pods('ride-attendee', $params);

    if  ($pod->fetch()) {
        $post_id = $pod->id();
        $post_title = $pod->field('post_title');
        return "Yes";
    }
    return "No";
}
add_shortcode('bk-tour-search-text', 'bk_tour_search_text');
function bk_tour_search_text()
{
    $ret = "";
    if (current_user_can('ridecoordinator') || current_user_can('rideleader') || current_user_can('manage_options')) {
        $ret .= 'To schedule a ride: click on "+".';
    }
    else
        $ret .= 'To see more detail: click on "+".';
    return $ret . ' Click on any asterisks to see tour hazard information. Times Ridden is the rolling 12 month count.';
}
add_shortcode('bk_missing_vl', 'bk_missing_vl_func');
function bk_missing_vl_func()
{
$args = array(
    'meta_query' => array(
        array(
            'key' => 'bp_xprofile_visibility_levels',
            'value' => '',
            'compare' => 'NOT EXISTS',
        ),
    )
);
$ret = "";
$users = get_users( $args );
    foreach ($users as $user) {
            if (user_can($user->ID, 'active')) {
	    $user_info = get_userdata($user->ID);
	    $first_name = get_user_meta($user->ID, 'first_name', true);
	    $last_name = get_user_meta($user->ID, 'last_name', true);
            $email = $user_info->user_email;
            $ret .= $first_name . " " . $last_name . " " . $email . "<br />";
            }
    }
return $ret;
}
// add_shortcode('mafw_about', 'mafw_about_func');
function mafw_about_func()
{
    return '<a id="about_mafw" class="elementor-button elementor-slide-button elementor-size-md">About</a>';
}
add_shortcode('bike_rides_update', 'bike_rides_need_updating');
function bike_rides_need_updating()
{
    $msg = "";
    if (current_user_can('rideleader') || current_user_can('manage_options')) {
	    $tz = new DateTimeZone(wp_timezone_string());
        $curdt = new DateTime("now", $tz);
	    $curdate = $curdt->format('Y-m-d');
        $params = array(
            'limit' => 1,
            'where' => "ride_leader.ID = " . get_current_user_id() . " AND `ride-status`.meta_value = 0 AND CAST(ride_date.meta_value AS date) < '$curdate' AND CAST(ride_date.meta_value AS date) >= '2023-03-01'",
        );
        $pod = pods('ride', $params);
        if ($pod->total() > 0) {
             $msg = '<p class="aligncenter redfont"><a href="' . get_site_url() . '/my-scheduled-rides/">Please update your RIDES! Click here to update.</a></p>';
        }
    }
    return $msg;
}
add_shortcode('bike_ride_updated_message', 'bike_ride_updated_func');
function bike_ride_updated_func()
{
    if(! empty( $_REQUEST['rideupdated'] ) ) {
        return '<font color="red"><p class="aligncenter">Ride Updated</p></font>';
    }
    else
        return "";
}
add_shortcode('seven-day-ride-schedule', 'bike_seven_day_ride_schedule');
function bike_seven_day_ride_schedule()
{
    if (!empty($_GET['from']))
	    $sday = $_GET['from'];
    else
	    $sday = "";
    if ($sday == '')
       $sday = 'day0';
    $result = '<p class="form_row_label_unsel" align="center">';
	$tz = new DateTimeZone(wp_timezone_string());
    $dateobj = new DateTime("now", $tz);
    for ($i = 0; $i < 7; $i++) {
        $result .= bike_display_DOW('day' . $i, $dateobj, $sday);
        $dateobj->modify('+1 day');
    }
    $result .= '</p>';
    $day = substr($sday, 3);
	$dtobj = new DateTime("now +" . $day . "day", $tz);
    $dt = $dtobj->format('Y-m-d');
    $result .= ride_table($dt, null, "member", '0', 0, 1, 0, 1, 0);
    return $result;
}

function bike_ride_list_func($atts)
{
    $atts = shortcode_atts( array(
            'role' => 'guest',
			'show_date' => '0',
            'small' => '0',
            'scheduled' => '1',
            'statusfirst' => '0',
			'sepcols' => '0'
			), $atts, 'bike_ride_list');
    $result = "";
    $daterange = bk_get_start_and_end_date(true);
    $result = ride_table($daterange['start_date'], $daterange['end_date'], $atts['role'], $atts['show_date'], $atts['small'], $atts['scheduled'], $atts['statusfirst'], 0, $atts['sepcols']);
    return $result;
}
add_shortcode('bike_ride_list', 'bike_ride_list_func');

function bk_tour_description($tpod)
{
    // error_log("in bk_tour_description");
    $desc = "";
    $tour_description = $tpod->field('tour_description');
    $vimeo = $tpod->field('vimeo');
    if (!empty($tour_description))
        $desc .= $tour_description;
    if (!empty($vimeo) && (current_user_can('active') || current_user_can('manage_options'))) {
        $desc .= '<p><iframe src="' . $vimeo . '" width="640" height="360" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></p>';
    }
    return $desc;
}

function bike_new_tourno()
{
    if (!class_exists('Pods')) {
        return;
	}
    $pod = pods('tour');
    $params = array(
        'orderby' => 'CAST(tour_number.meta_value AS unsigned) DESC',
        'limit' => 1
    );
    $pod->find($params);
    if ($pod->total() > 0) {
       $pod->fetch();
       $tourno = $pod->field('tour_number') + 1;
    }
    else
       $tourno = 1;
    return $tourno;
}

function ride_table($start_date, $end_date, $role, $show_date, $small = 0, $scheduled = 1, $statusfirst = 0, $oldschedule = 0, $sepcols = 0) {
      if (!class_exists('Pods'))
        return;
      global $wp;
      $current_slug = add_query_arg( array(), $wp->request );
      $my_scheduled_rides = ($current_slug == "my-scheduled-rides") ? 1 : 0; 
	  $tz = new DateTimeZone(wp_timezone_string());
	  if (empty($role) || $role == 'member')
	      if (current_user_can('active'))
		      $role = 'member';
	      else
	          $role = 'guest';
	  if ($role == 'ride_coordinator' && !current_user_can('ridecoordinator') && !current_user_can('manage_options'))
	      if (current_user_can('active'))
		      $role = 'member';
	      else
	          $role = 'guest';
	  if (($role == 'ride_leader' || $role == 'ride_leader_ro') && !current_user_can('rideleader') && !current_user_can('manage_options'))
	      if (current_user_can('active'))
	          $role = 'member';
	      else
	          $role = 'guest';
	  $key = null; $date_column = xprofile_get_field_data("Add Date Column to Ride Schedule", get_current_user_id(), 'comma');
	  // if ($my_scheduled_rides || $statusfirst || !empty($date_column))
	      $showonlyday = 0;
	  // else
	      // $showonlyday = 1;
      if ($role == 'guest' || $role == 'member') {
          $statusfirst = 0;
		  if ($my_scheduled_rides == 0) {
			  $rlkey = current_user_can('ridecoordinator') || current_user_can('rideleader') ? 'rlkey' : $role;
              $key = "bk_ride_table_text__" . $start_date . "__" . $end_date . "__" . $rlkey . "__" . $show_date . "__" . $small . "__" . $scheduled . "__" . $statusfirst . "__" . $oldschedule;
              $ride_table_text = get_transient($key);
              if (!empty($ride_table_text)) {
                  return $ride_table_text;
              }
		  }
	  }
	  if (empty($start_date) || null ===  $start_date)
          $curdate_obj = new DateTime("now", $tz);
      else
          $curdate_obj = new DateTime($start_date, $tz);
      $start_date = $curdate_obj->format("Y-m-d");
      $week_obj = new DateTime($start_date, $tz);
      if ($role == 'ride_coordinator' && (empty($end_date) || null !== $end_date)) {
		  $end_date_obj = new DateTime('now +1 day', $tz);
      }
	  if (empty($end_date) || null === $end_date)
		  $end_date_obj = new DateTime($start_date . ' +1 day', $tz);
      else
		  $end_date_obj = new DateTime($end_date, $tz);
      $end_date = $end_date_obj->format("Y-m-d");	
	  $start_year = $curdate_obj->format("Y");
	  $end_year = $end_date_obj->format("Y");
      global $post;
      $slug = empty($post) ? 'ride-list' : $post->post_name;
      $result = '<table id="' . $slug . '" class="display ride_table" style="width:100%">
        <thead>
            <tr>';
	        if ($statusfirst == 1)
			    $result .= '<th>Status</th>';
	        if ($show_date == '1') {
				if ($showonlyday == 0)
				    $result .= '<th>Date</th>';
				$result .= '<th>Day</th>';
			}
	        $result .= '
                <th>Start</th>';
            if ($small != 1 && $my_scheduled_rides == 0) {
				if ($sepcols == 0) {
                    $result .= '
	                    <th>Pace/Terrain/<br>Miles/Climb(ft)</th>';
			    }
				else {
                    $result .= '
	                    <th>Pace</th>
	                    <th>Terrain</th>
	                    <th>Miles</th>
	                    <th>Climb</th>';
				}
            }
			else {
                $result .= '
	            <th>Pace</th>';
			}
	        $result .= '<th>Starting Point</th>';
			if ($small != 1 && ($role == "guest" || $role != 'ride_leader' ||
			        $scheduled != 1))
			    $result .= '<th>Leader</th>';
			if ($role == 'ride_leader' && $small == 1 && $scheduled == 0 && 
			        $show_date == 1 && $statusfirst == 1)
                $result .= '<th>Tour/Riders</th>';
			else
                $result .= '<th>Tour/Signup</th>';
            if ($small != 1) {
	            if ($role != 'guest') {
				    $result .= '<th>Links</th>';
                    if ($scheduled == 1 && ($role == 'ride_leader' || $role == 'ride_coordinator'))
                       $result .= '<th>Sign-in Sheet</th>';
	                if ($role == 'ride_coordinator' || $role == 'ride_leader' || $role == 'ride_leader_ro') {
				        $result .= '<th>Changes?</th>';
			        }
                }
	            $result .= '<th class="none">Average Climb (ft/mile)</th>';
				$result .= '<th class="none">Leader Comments</th>';
				$result .= '<th class="none">Tour Description</th>
					<th class="none">Tour Notes</th>
	                <th class="none">Attendees</th>';
                if ($statusfirst != 1)
	                $result .= '<th class="none">Status</th>';
	            if ($role == 'ride_coordinator' || $role == 'ride_leader' || $role == 'ride_leader_ro') {
					    $result .= '<th class="none">Rider Count</th>';
			    }
            }
	        $result .= '
            </tr>
        </thead>
        <tbody>';
	  $curr_userid = get_current_user_id();
	  if ($role == 'ride_leader' && !empty($curr_userid) && $curr_userid > 0)  {
		  $filter = 'ride_leader.ID = ' . $curr_userid;
          if ($scheduled == 0) { // ridden, canceled (for my led rides)
              $filter .= ' AND ( ride-status.meta_value = 2 OR ride-status.meta_value = 4 ) ';
              $filter .= " AND CAST(ride_date.meta_value AS date) >= '" . $start_date .
                "' AND CAST(ride_date.meta_value AS date) < '" . $end_date . "'";
          }
          else if ($scheduled == 1) // scheduled (for my scheduled rides)
              $filter .= ' AND ( ride-status.meta_value = 0 ) AND CAST(ride_date.meta_value AS date) >= "2019-03-01"';
      }
      else if ($role == 'ride_leader_ro' && !empty($curr_userid) && $curr_userid > 0) {
		  $filter = 'ride_leader.ID = ' . $curr_userid;
          $filter .= " AND CAST(ride_date.meta_value AS date) >= '" . $start_date .
                "' AND CAST(ride_date.meta_value AS date) < '" . $end_date . "'";
      }
      else {
          $filter = "CAST(ride_date.meta_value AS date) >= '" . $start_date .
                "' AND CAST(ride_date.meta_value AS date) < '" . $end_date . "'";
      }
      if ($oldschedule != 0 || ($role != 'ride_coordinator' && $scheduled != 1))
          $filter .= ' AND ride-status.meta_value != 3 ';
      $rpod = pods('ride');
      $params = array(
            'orderby' => 'CAST(ride_date.meta_value AS date), CAST(time.meta_value AS time), pace.index.meta_value',
	         'limit' => -1,
             'where' => $filter
        );
      $rpod->find($params);
      if ($rpod->total() > 0)
        $curdate_obj = new DateTime("now", $tz);
      while( $rpod->fetch()) {
        $weekchange = 0;
	    $date_str = $rpod->display('ride_date') . ' ' . $rpod->display('time');
		$date_obj = new DateTime($date_str, $tz);
		$ride_date_past = 0;
		if ($date_obj->format('Y') < $curdate_obj->format('Y'))
            $ride_date_past = 1;
		else if ($date_obj->format('Y') == $curdate_obj->format('Y'))  {
			if ($date_obj->format('m') < $curdate_obj->format('m'))
                $ride_date_past = 1;
			else if ($date_obj->format('m') == $curdate_obj->format('m')) {
				if ($date_obj->format('d') < $curdate_obj->format('d'))
                    $ride_date_past = 1;
				else if ($date_obj->format('d') == $curdate_obj->format('d')) {
				    if ($date_obj->format('H') < $curdate_obj->format('H'))
                        $ride_date_past = 1;
				}
			}
		}
 		$day = $date_obj->format("D");
		$date = $date_obj->format('Y-m-d');
		if (!empty($prevdate) && $date != $prevdate &&
		         ($role != "ride_leader" || $small != 1)) {
		    $daychange = 1;
            $newdate_obj = new DateTime($date, $tz);
            $interval = $newdate_obj->diff($week_obj);
            $days = $interval->format('%a');
            if ($days >= 7) {
                 $week_obj = $newdate_obj;
                 $weekchange = 1;
            }
        }
		else
		    $daychange = 0;
		$prevdate = $date;
		if ($end_year != $start_year)
		    $ridedate = $date_obj->format('m/d/y');
		else
		    $ridedate = $date_obj->format('m/d');
		// $time = $oldschedule == 0 ? $rpod->display('time') : $rpod->field('time');
		$time = $rpod->display('time');
		$tourfield = $rpod->field('tour');
		if ( $tourfield == false )
			continue;
        $tourid = $tourfield['ID'];
        $rideid = $rpod->field('ID');
		$tpod = pods('tour', $tourid);
	    // $min_time = new DateTime('now + 2 hours', $tz);
	    $min_time = new DateTime('now', $tz);
		$start_time = new DateTime($date . " " . $time, $tz);
		$count = 0;
		$aid = 0;
		$signups = array();
		$waitlist = array();
	    $count = bk_get_signup_list($rpod, $signups, $waitlist, $aid);
        $remaining = 0;
        if ($start_time >= $min_time && $rpod->field('ride-status') != 2) {
            $max_signups = $rpod->field('maximum_signups');
            if (!empty($max_signups)) {
                $remaining = $max_signups - $count;
				if ($remaining < 0)
				    $remaining = 0;
            }
        }
		$rl = $rpod->field('ride_leader');
		if ( $rl !== false ) {
		    $rlid = $rl['ID'];
		    $rlpod = pods("user", $rl['ID']);
            $rl_email = $rlpod->field('user_email');
		    $rluname = $rlpod->field('user_nicename');
		    $rlname = $rlpod->field('display_name');
		}
		else {
			$rlid = 0;
			$rl_email = "";
			$rluname = "";
			$rlname = "";
		}
        // Proposed rides only shown to ride leaders and ride coordinator
		if ( $rlid == PROPOSED_RIDE_LEADER_USERID && !current_user_can('ridecoordinator') && !current_user_can('rideleader') ) {
			continue;
		}
        $hazards = empty($tpod->field('road_closures')) ? "" : "*";
        if ($role != "guest") {
            if ($remaining < 40 && $role != 'ride_coordinator') {
		        $tour = '<a href="' . get_site_url() . '/ride/' . $rideid . '">' . $tpod->field('tour_number') . ' - ' . $tpod->field('post_title') . $hazards . '(' . $remaining . ')</a>';
            }
            else {
		        $tour = '<a href="' . get_site_url() . '/ride/' . $rideid . '">' . $tpod->field('tour_number') . ' - ' . $tpod->field('post_title') . $hazards . '</a>';
            }
        }
        else {
            if ($rpod->field('ride-status') != 2 && $ride_date_past != 1 && $remaining > 0)
		        $tour = '<a href="' . get_site_url() . '/guest-ride-signup/?rlid=' . $rlid . '&rideid=' . $rideid . '">' . $tpod->field('tour_number') . ' - ' . $tpod->field('post_title') . '</a>';
            else
		        $tour = $tpod->field('tour_number') . ' - ' . $tpod->field('post_title');
        }
        if ($scheduled == 2 || $oldschedule != 0) {
            if (/* $rpod->field('ride_canceled') == 1 || */ $rpod->field('ride-status') == 2)
                $tour .= ' <b><font color="red">**CANCELED**</font></b>';
            else if ($rpod->field('ride_change') == 1 && $ride_date_past != 1)
                $tour .= '<br /><b><font color="red">**Note Changes - Click + for details**</font></b>';
        }
		// if ($tpod->field('tour_type') == 0)
			// $ptpage = "pace-terrain-information-road-day-evening";
		// else
			// $ptpage = "pace-terrain-info-path-trail-rides";
		// $ptpage = "/pace-terrain-information/";
		// $ptpage = "%23elementor-action%3Aaction%3Dpopup%3Aopen%26settings%3DeyJpZCI6IjMzOTM2IiwidG9nZ2xlIjpmYWxzZX0%3D";
		$ptpage = '<a href="#" class="pace-terrain">';
		$tour_description = bk_tour_description($tpod);
		$tour_comments = $tpod->field('tour_comments');
		if (!empty($tour_comments)) {
		    $tour_comments = str_replace(["<li>", "</li>", "<p>", "</p>","<br>"], ["", "<br>", "", "", ""], $tour_comments);
		}
				// $pace = '<a href="' . $ptpage . '">' . $rpod->display('pace') . '</a>';
				$pace = $ptpage . $rpod->display('pace') . '</a>';
				if ($rpod->field('ride_canceled') == 'Yes') {
					$rideid = $rpod->field('ID');
					pods('ride', $rideid)->save('ride-status', 2);
				}
				$status = $rpod->display('ride-status');
				$ride_comments = $rpod->field('ride_comments');
				if ($role != "guest")
					$ride_leader = '<a href="' . get_site_url() . '/members/' . $rluname . '/profile/">' . $rlname . '</a>';
        else
		    $ride_leader = $rlname;
		$terrain_field = $tpod->field('tour-terrain');
		if (!empty($terrain_field) && !empty($terrain_field['post_title'])) {
		    $terrain_name = $terrain_field['post_title'];
            // $terrain = '<a href="' . $ptpage . '">' . $terrain_name . '</a>';
            $terrain = $ptpage . $terrain_name . '</a>';
		}
		else
			$terrain = "";
        $start = $tpod->field('start_point');
		if ($start) {
            $url = get_site_url() . '/start_point/' . $start['post_name'] . '/';
            $startloc = '<a href="' . esc_url($url) . '">' . $start['post_title'] . '</a>';
		}
		else
			$startloc = "";

        $climb = intval($tpod->field('climb'));
        $miles = intval($tpod->field('miles'));
		$rider_count = $rpod->display('rider_count');
		$average_climb = $miles > 0 ? round($climb / $miles) : 0;
	    $signup_list = implode(", ", $signups);
        $cue = bike_cue_links($tpod->field('cue_sheet_number'), $tpod->field('tour_number'), $tpod->field('tour_map') );
		// $url = '/edit-ride?rideid=' . $rideid;
        $url = '/ride/' . $rideid . '/riderole=';
        $bk_form_nonce = wp_nonce_field('bk_signinsheet_action', 'bk_signinsheet_nonce', true, false);
        $downloadform = '<form action="' . admin_url( "admin-ajax.php" ) .  '" method="post">
                         <input type="hidden" name="action" value="bk_signinsheet_action">' . $bk_form_nonce .
                         '<input type="hidden" name="rideid" value="' . $rideid . '">
                         <input type="submit" value="Download">
                         </form>';
		if ($role == 'ride_coordinator') {
            $url = get_site_url() . '/ride/' . $rideid . '/?riderole=2';
            $download = $downloadform;
		    $edit = '<a href="' . $url . '">Update Ride</a>';
        }
        else if ($role == 'ride_leader') {
            $url = get_site_url() . '/ride/' . $rideid . '/?riderole=1';
		    $edit = '<a href="' . $url . '">Update Ride</a>';
            $download = $downloadform;
        }
        else if ($role == 'ride_leader_ro') {
            $url = get_site_url() . '/ride/' . $rideid . '/?riderole=1';
		    $edit = '';
            $download = '';
        }
        else {
            $edit = '';
            $download = '';
        }
		if ($daychange == 1) {
         if ($weekchange == 1)
             $result .= '
              <tr class="weekchange">';
         else
             $result .= '
              <tr class="daychange">';
        }
		else
         $result .= '
          <tr>';
	      if ($statusfirst == 1)
			$result .= '<td>' . $status . '</td>';
		  if ($show_date == '1') {
            if ($role == 'ride_leader' && $ride_date_past == 1 && $small != 1)
                $style = ' style="color:red"';
            else
                $style = "";
		    // $result .= '<td ' . $style . '>' . $date . ' ct: ' . $curdate . ' sd: ' . $st_time . ' ds: ' . $date_str . ' cd: ' . $curdate_str . '</td>';
			if ($showonlyday == 0) {
		         $result .= '<td ' . $style . '>' . $ridedate . '</td>';
			     $result .= '<td>' . $day  . '</td>';
			}
			else
			    $result .= '<td><div class="tooltip">' . $day  . '<span class="tooltiptext">' . $ridedate . '</span></div></td>';
		  }
		  $result .= '
		    <td>' . $time . '</td>';
          if ($small != 1 && $my_scheduled_rides == 0) {
			  if ($sepcols == 0) {
                $result .= 
			        '<td>' . $pace . '/' . 
			        $terrain . '/' .
                    $miles . '/' .
                    $climb . '</td>';
		      }
			  else {
                $result .= 
			        '<td>' . $pace . '</td>' .
			        '<td>' . $terrain . '</td>' .
                    '<td>' . $miles . '</td>' .
                    '<td>' . $climb . '</td>';
			  }
          }
		  else {
		    $result .= '
		      <td>' . $pace . '</td>';
		  }
          $result .= '<td>' . $startloc . '</td>';
		  if ($small != 1 && ($role == "guest" || $role != 'ride_leader' ||
			        $scheduled != 1))
		      $result .= '<td>' . $ride_leader . '</td>';
          $result .= '<td>' . $tour . '</td>';
		  if ($role != 'guest' && $small != 1)
              $result .= '<td>' . $cue . '</td>';
          if ($small != 1) {
                if ($scheduled == 1 && ($role == "ride_leader" || $role == "ride_coordinator"))
		            $result .= '<td>' . $download . '</td>'; // signin sheet
		        if ($role == 'ride_coordinator' || $role == 'ride_leader'
                       || $role == 'ride_leader_ro') {
				    $result .= '<td>' . $edit . '</td>';
			    }
			    $result .= '<td>' . $average_climb . '</td>
                <td>' . $ride_comments . '</td>';
				    $result .= '<td>' . $tour_description . '</td>
				    <td>' . $tour_comments . '</td>
				    <td>' . $count . ': ' .$signup_list . '</td>';
                if ($statusfirst != 1)
			         $result .= '<td>' . $status . '</td>';
		        if ($role == 'ride_coordinator' || $role == 'ride_leader'
                       || $role == 'ride_leader_ro') {
		            $result .= '<td>' . $rider_count . '</td>';
			    }
          }
        $result .= '</tr>';
     } // end while
    $result .= '</tbody></table>';
    if ($rpod->total() <= 0) {
       global $wp;
       $current_slug = add_query_arg( array(), $wp->request );
       if ($current_slug == "my-scheduled-rides") {
           $result .= '<center>You have no scheduled rides.</center>';
       }
       else {
           $result .= '<center>No rides scheduled for ';
           if ($show_date == '1')
               $result .= 'the date range selected';
           else {
               $start_date_obj = new DateTime($start_date, $tz);
               $result .= $start_date_obj->format('l, M dS');
           }
	       $result .= '</center>';
       }
    }
	if (!empty($key))
           set_transient($key, $result, 14400);
	return $result;
}
function bk_get_signup_id_list($rpod)
{
    $signups = array();
	if (empty($rpod) || !is_object($rpod))
		return $signups;
	$attendees = $rpod->field('attendees');
	if (!empty($attendees)) {
		foreach ($attendees as $attendee) {
            $attendeeid = $attendee;
			$userid = get_post_field('post_author', $attendeeid);
			if (empty($userid) || $userid == 0 || $userid == PROPOSED_RIDE_LEADER_USERID)
			    continue;
			$apod = pods('ride-attendee', $attendeeid);
			$status = $apod->field('ride_attendee_status');
			$wait_listed = $apod->field('wait_list_number');
			if (empty($wait_listed) || $wait_listed == 0 && $status == "Yes" && !array_key_exits($userid, $signups))
		        $signups[] = $userid;
		}
	}
	return $signups;
}
function bk_get_signup_list($rpod, &$signups, &$waitlist, &$curuser_aid)
{
	$curuser_aid = 0;
    $signups = array();
	$waitlist = array();
	if (empty($rpod) || !is_object($rpod) ) {
		return 0;
	}
    else {
        $ride_status = $rpod->field('ride-status');
        if ($ride_status != 0 && $ride_status != 4)
		    return 0;
    }
	$count = 1;
	$attendees = $rpod->field('attendees');
    $rl = $rpod->field('ride_leader');
	if (!empty($attendees)) {
		$now = new DateTime();
        $six_months_ago = $now->modify('-6 months');
		$curruserid = get_current_user_id();
		$userid_arr = array();
	    sort($attendees);
		foreach ($attendees as $attendee) {
            $attendeeid = $attendee;
			$userid = get_post_field('post_author', $attendeeid);
			if (empty($userid) || $userid == 0)
			    continue;
			if (!array_key_exists($userid, $userid_arr))
			    $userid_arr[$userid] = $attendeeid;
			else
			    continue;
            $user = get_userdata($userid);
			$date_registered = preg_replace("!([^ ]*) .*!", "$1", $user->user_registered);
			if ($user->ID == $curruserid)
			    $curuser_aid = $attendeeid;
            $apod = pods('ride-attendee', $attendeeid);
			$status = $apod->field('ride_attendee_status');
			$wait_listed = $apod->field('wait_list_number');
			if (empty($wait_listed))
			    $wait_listed = 0;
			// if ($rpod->field('ID') == 152302)
			    // error_log("aid:" . $attendeeid . " " . $user->ID . ":" . $user->user_nicename . ": status:" . $status);
			if ($status != "No") {
			    $userpace = xprofile_get_field_data("I normally ride at", $user->ID, 'array');
				$new_member =  ( $date_registered > $six_months_ago ) ? '*' : '';
				if ( $date_registered > $six_months_ago && bk_user_attended_rides( $user ) < 5 ) {
					$new_member = '*';
                }
				else {
					$new_member = '';
				}
		        $name = '<a href="' . get_site_url() . '/members/' . $user->user_nicename . '/profile/">' . $new_member . $user->display_name;
                // $name = '<a href="' . get_site_url() . '/members/' . $user->user_nicename . '/profile/">' . $user->display_name;
                if (!empty($userpace)) {
                    $name .= '(' . $userpace . ')';
                }
                $name .= '</a>';
				if ($status === "Maybe")
				    $name .= '?';
			    if ($wait_listed > 0) {
			        $waitlist[] = $name;
				}
			    else {
			        $signups[] = $name;
				    if ($rl['ID'] != $user->ID)
				        $count++;
			    }
			}
		}
	}
    $guests = $rpod->field('guests');
	if (!empty($guests)) {
	    foreach ($guests as $guest) {
            $post = get_post($guest['ID']);
            $name = '<a href="' . get_site_url() . '/guest/' . $post->post_name . '/">' . pods('guest', $guest['ID'])->field('guests_name') . '</a>';;
			$signups[] = $name;
            $count++;
        }
    }
	return $count;
}
function ride_attendees($rpod, &$signup_list, &$wait_list)
{
	if (empty($rpod) || !is_object($rpod))
		return "";
	$aid = 0;
	$signups = array();
	$waitlist = array();
	$count = bk_get_signup_list($rpod, $signups, $waitlist, $aid);
	$signup_list = implode(", ", $signups);
	$wait_list = implode(", ", $waitlist);
	return $count;
}

add_shortcode('bk_add_tours_to_hazards', 'bk_add_tours_to_hazards');
function bk_add_tours_to_hazards()
{
    $ret = "";
    $pod = pods('road_closureswarning', 233501 );
    $newtours = array( 50, 78, 80, 635, 678, 697, 701, 798, 868, 873, 906, 1025, 1052, 1221, 1226, 1470 );

    foreach ($newtours as $tour) {
        $params = array( 'where' => 'tour_number.meta_value = ' . $tour );
        $tpods = pods('tour', $params);
        if (0 < $tpods->total()) {
            if ($tpods->fetch()) {
                $tid = $tpods->field('ID');
                $pod->add_to('tour', $tid);
                $ret .= "Added tour " . $tour . " tid:" . $tid . '<br />';
            }
        }
    }
    return $ret;
}
add_shortcode('food-stops-last-modified', 'bk_food_stops_last_modified');
function bk_food_stops_last_modified()
{
    global $wpdb;
	$query = 'SELECT post_modified FROM ' . $wpdb->prefix . 'posts WHERE post_type LIKE "food_stops" ORDER BY post_modified DESC LIMIT 1';
	$dt = "now";
    $results = $wpdb->get_results($query);
	if ($results) {
        foreach ($results as $result) {
		    $dt = $result->post_modified;
	    }
	}
	$tz = new DateTimeZone(wp_timezone_string());
    $mdt = new DateTime($dt, $tz);
    return $mdt->format('m/d/Y');
}

add_shortcode('food-stops-table', 'bk_food_stops_table');
function bk_food_stops_table()
{
     $ret = "";
     $mintime_gap = get_option('club-settings_min_time_between_rides') - 1;
	 $params = array( 'limit' => -1);
	 $pod = pods('food_stops', $params);
	 $foodstops = array();
	 while ($pod->fetch()) {
         $foodstops[] = array(
			    'name' => $pod->field('post_title'),
		        'mapurl' => $pod->field('map'),
                                'post_name' => $pod->field('post_name'),
				'town' => $pod->field('town'),
				'open' => $pod->field('open'),
				'hours' => $pod->field('hours'),
				'notes' => $pod->field('notes'),
				'phone' => $pod->field('phone'),
				'indoor_seating' => $pod->field('indoor_seating'),
				'address' => $pod->field('address')
              );
	 }
	 usort($foodstops, function($a, $b) {
	     return $a['town'] < $b['town'] ? 1 : -1;
	 });
    $ret .= '<table class="display food-stops-table" style="width:100%">';
    $ret .= '
        <thead>
            <tr>
                <th style="width:5%">Town</th>
                <th style="width:15%">Deli</th>
                <th style="width:5%">Open</th>
                <th style="width:15%">Hours</th>
                <th style="width:20%">Notes</th>
                <th style="width:5%">Indoor Seating Limit</th>
                <th style="width:25%">Address</th>
                <th style="width:15%">Phone</th>
            </tr>
        </thead>';
    $ret .= '<tbody>';
     foreach ($foodstops as $foodstop) {
         $ret .= '<tr>';
         $ret .= '<td>' . $foodstop['town'] . '</td>';
         $ret .= '<td><a href="' . $foodstop['post_name'] . '">' . $foodstop['name'] . '</a></td>';
         $ret .= '<td>' . $foodstop['open'] . '</td>';
         $ret .= '<td>' . $foodstop['hours'] . '</td>';
         $ret .= '<td>' . $foodstop['notes'] . '</td>';
         $ret .= '<td>' . $foodstop['indoor_seating'] . '</td>';
		 if (empty($foodstop['mapurl']))
             $ret .= '<td>' . $foodstop['address'] . '</td>';
		 else
             $ret .= '<td><a href="' . $foodstop['mapurl'] . '" target="_blank">' . $foodstop['address'] . '</a></td>';
         $ret .= '<td><a href="tel:' . $foodstop['phone'] . '">' . $foodstop['phone'] . '</a></td>';
		 $ret .= '</tr>';
     }
    $ret .= '</tbody>';
    $ret .= '</table>';
    return $ret;
}
add_shortcode('bk-schedule-block-table', 'bk_schedule_blocks');
function bk_schedule_blocks()
{
	$can_edit = current_user_can('ridecoordinator' || current_user_can('manager_options'));
	$blocked_entries = [];
	$ret = "";
	$tz = new DateTimeZone(wp_timezone_string());
	$date_obj = new DateTime("now", $tz);
	$datestr = $date_obj->format('Y-m-d');
    $params = array(
        'limit' => -1,
        'where' => "CAST(end_date.meta_value AS date) >= '$datestr'",
    );
    $pod = pods('locationdateblock', $params);

    if ($pod->total() > 0) {
        while ($pod->fetch()) {
		$post_id = $pod->id();
			if ($can_edit) {
				$link = get_site_url() . '/locationdateblock/' . $pod->field('post_name') . '/?blockedit=Edit';
			    $title = '<a href="' . $link . '">' . $pod->field('post_title') . '</a>';
			}
			else {
			    $title = $pod->field('post_title');
			}
		    $blocked_entries[] = [
			    'title' => $title,
				'start' => $pod->field("start_date"),
				'end' => $pod->field("end_date"),
				'Days of Week' => $pod->field("days_of_week"),
				'location' => $pod->display("start_location"),
			    'message' => strip_tags($pod->field("post_content"))
			];
		}
        $ret .= '<table class="display blocked-dates-table" style="width:100%">
          <thead>
            <tr>
                <th>Title</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Days of Week</th>
                <th>Start Location</th>
                <th>Message</th>
            </tr>
          </thead>
          <tbody>';
     	foreach ($blocked_entries as $entry) {
			$title = $entry['title'];
        	$start = $entry['start'];
        	$end = $entry['end'];
        	$dow = $entry['Days of Week'];
        	$location = $entry['location'];
        	$message = $entry['message'];
        	$ret .= '<tr>';
        	$ret .= '<td>' . $title . '</td>';
        	$ret .= '<td>' . $start . '</td>';
        	$ret .= '<td>' . $end . '</td>';
        	$ret .= '<td>' . $dow . '</td>';
        	$ret .= '<td>' . $location . '</td>';
        	$ret .= '<td>' . $message . '</td>';
        	$ret .= '</tr>';
    	}
     	$ret .= '</tbody></table>';
    	return $ret;
	}
	else {
	    return "No entries";
	}
}
add_shortcode('road-hazards-table', 'bk_road_hazards_table');
function bk_road_hazards_table()
{
	 $tz = new DateTimeZone(wp_timezone_string());
     $dt = new DateTime('1970-01-01 00:00:00', $tz);
	 $params = array( 'limit' => -1 );
	 $pod = pods('road_closureswarning', $params);
	 $hazards = array();
	 while ($pod->fetch()) {
		 $end = $pod->field('end_date');
		 if (empty($end) || $end == "0000-00-00")
		     $end = "";
		 $tours = $pod->field('tour');
		 // error_log(print_r($tours, true));
		 $description = $pod->field('description');
		 $comments = $pod->field('closure_comments');
		 $location = $pod->field('road_closure_location');
		 $start = $pod->field('start_date');
         if ( !empty( $tours ) && is_array( $tours ) )
		   foreach ($tours as $tour) {
			  $tid = $tour['ID'];
			  $tpod = pods('tour', $tid);
			  $tourno = $tpod->field('tour_number');
              $link = '<a href="' . get_site_url() . '/tour/?p=' . $tid . '">' . $tourno . '</a>';
		      $hazards[] = array(
			    'tour' => $link,
		        'description' => $description,
				'comments' => $comments,
				'start' => $start,
				'end' => $end,
				'location' => $location);
		   }
		 $modified = $pod->field('post_modified');
		 $postdt = new DateTime($modified, $tz);
		 if ($postdt > $dt)
		     $dt = $postdt;
	 }
	 usort($hazards, function($a, $b) {
	     return $a['tour'] < $b['tour'] ? -1 : 1;
	 });
     $ret = '<p class="roadclosure-title">Known Road Closures and Warnings. Last checked (to the best of my ability) on ' . $dt->format('m/d/Y') . '</p>';
     $ret .= '* Not all closures/warnings may be listed - always use caution.<br />';
     $ret .= '* If you encounter a closure not listed, please contact ridecoordinator@mafw.org<br /><br />';
     $ret .= '<table class="display road-hazards-table" style="width:100%">
        <thead>
            <tr>
                <th>Tour#</th>
                <th>Description</th>
                <th>Comments</th>
                <th>Start</th>
                <th>End</th>
                <th>Location</th>
            </tr>
        </thead>
        <tbody>';
     $ret .= '<h2>Road Closures/Hazards</h2>';
     foreach ($hazards as $hazard) {
		 $tourno = $hazard['tour'];
         $location = $hazard['location'];
         $description = $hazard['description'];
         $comments = $hazard['comments'];
         $startdate = $hazard['start'];
         $enddate = $hazard['end'];
         $location = $hazard['location'];
		 if (empty($location) || is_array($location))
		     $map = "";
		 else
             $map = '<a href="' . $location . '" target=_blank>MAP</a>';
         $ret .= '<tr>';
         $ret .= '<td>' . $tourno . '</td>';
         $ret .= '<td>' . $description . '</td>';
         $ret .= '<td>' . $comments . '</td>';
         $ret .= '<td>' . $startdate . '</td>';
         $ret .= '<td>' . $enddate . '</td>';
         $ret .= '<td>' . $map . '</td>';
         $ret .= '</tr>';
     }
     $ret .= '</tbody></table>';
     return $ret;
}

function road_hazards($tourid)
{
     $tpod = pods('tour', $tourid);
     $hazards = $tpod->field('road_closures');
     if (empty($hazards) || !is_array($hazards))
         return "";
     $ret = '<table class="display road-hazards-table" style="width:100%">
        <thead>
            <tr>
                <th>Description</th>
                <th>Comments</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Location</th>
            </tr>
        </thead>
        <tbody>';
     $ret .= '<h2>Road Closures/Hazards</h2>';
     foreach ($hazards as $hazard) {
		 if (is_array($hazard))
             $hazid = $hazard['ID'];
		 else
             $hazid = $hazard;
         $hpod = pods('road_closureswarning', $hazid);
         $description = $hpod->field('description');
         $comments = $hpod->field('closure_comments');
         $startdate = $hpod->field('start_date');
         $enddate = $hpod->field('end_date');
         if (empty($enddate))
             $enddate = "";
         $location = $hpod->field('road_closure_location');
		 if (empty($location) || is_array($location))
		     $map = "";
		 else
             $map = '<a href="' . $location . '" target=_blank>MAP</a>';
         $ret .= '<tr>';
         $ret .= '<td>' . $description . '</td>';
         $ret .= '<td>' . $comments . '</td>';
         $ret .= '<td>' . $startdate . '</td>';
         $ret .= '<td>' . $enddate . '</td>';
         $ret .= '<td>' . $map . '</td>';
         $ret .= '</tr>';
     }
     $ret .= '</tbody></table>';
     return $ret;
}

// add_filter( 'dud_set_user_email', 'bk_user_emails', 10, 2);
function bk_user_emails($email, $userid)
{
    error_log("email:" . $email . " userid:" . $userid);
	$user_info = get_userdata($userid);
    $email = $user_info->user_email;
    error_log("uemail:" . $email);
}

// update '1' to the ID of your form
add_filter( 'gform_pre_render_16', 'add_readonly_script' );
function add_readonly_script( $form ) {
    ?>
 
    <script type="text/javascript">
        jQuery(document).ready(function(){
            /* apply only to input or select with a class of gf_readonly */
            jQuery(".disabled select").attr("disabled","disabled");
        });
    </script>
 
    <?php
    return $form;
}
add_filter( 'gform_validation_26', 'bk_guest_validation' );
function bk_guest_validation($validation_result)
{
	global $wpdb;
    $msg = "";
    $form = $validation_result['form'];
    $rider_email = rgpost('input_10');
    if (empty($rider_email) || !filter_var($rider_email, FILTER_VALIDATE_EMAIL))
        $msg = "A valid email address is required.";
    else {
        $user = get_user_by('email', $rider_email);
        if ($user) {
			if  (user_can($user->ID, 'active')) {
                $msg = "Club members need to log-in to sign up for a ride.";
			}
			else {
                $enddate = pmpro_get_expiration_date( $user->ID );
				if ( $enddate !== null ) {
	    		    $tz = new DateTimeZone(wp_timezone_string());
        		    $yearago = new DateTime(null, $tz);
					$yearago->modify('-1 year');
					if($enddate > $yearago) {
						// don't allow recently expired members to sign up as a guest
                	    $msg = "Guest signup is just for new members. Former club members need to log-in and then renew your membership in order to sign up for a ride.";
					}
				}
			}
        }
		if ( empty($msg) ) {
			$query = 'SELECT id from ' . $wpdb->prefix . 'gf_entry_meta WHERE form_id = 26 AND meta_key LIKE 10 AND meta_value LIKE "' . $rider_email . '"';
			$results = $wpdb->get_results($query);
			if ($results && is_array($results) && count($results) > 1) {
				$url = home_url( '/membership-levels/' );
                $msg = 'You have exceeded the maximum number of guest signups. Please ' . '<a href="' . $url . '">join the club</a> so you can continue to enjoy the rides.';
			}
		}
    }
    if (!empty($msg)) {
        $validation_result['is_valid'] = false;
        foreach ($form['fields'] as &$field ) {
            if ($field->id == '10' && rgpost('input_10')) { // rider count field
		        $field->failed_validation = true;
		        $field->validation_message = $msg;
            }
        }
    }
    return $validation_result;
}
add_filter( 'gform_notification_26', 'bk_guest_rider_form_modify_to', 10, 3 );
function bk_guest_rider_form_modify_to( $notification, $form, $entry ) {
    $rlid = rgar($entry, 11);
    if ($notification['name'] == 'Send to Ride Leader' && !empty($rlid) && $rlid > 0) {
	    $user_info = get_userdata($rlid);
        $notification['toType'] = 'email';
        $notification['to'] = $user_info->user_email;
    }
    return $notification;
}

function pmpro_get_expiration_date( $userid )  {

	if ( ! empty( $user_id ) ) {
		//get the user's level
		$level = pmpro_getMembershipLevelForUser($user_id);
	}

	return (!empty($level) && !empty($level->enddate)) ? $level->enddate : null;
}
function bk_days_until_membership_expires() {
    // Check if the Paid Memberships Pro functions exist.
    if ( ! function_exists( 'pmpro_getMembershipLevelForUser' ) ) {
        return 999999;
    }

    $user_id = get_current_user_id();

    // Return if there's no logged-in user.
    if ( $user_id == 0 ) {
        return 999999;
    }

    $level = pmpro_getMembershipLevelForUser( $user_id );

    if ( ! empty( $level ) && ! empty( $level->enddate ) ) {

		$expiration_timestamp = $level->enddate;

        // Get the current timestamp.
        $current_timestamp = time();

        // If the expiration date is in the past, return 0.
        if ( $expiration_timestamp < $current_timestamp ) {
            return 0;
        }

        // Calculate the difference in seconds.
        $difference_in_seconds = $expiration_timestamp - $current_timestamp;

        // Convert seconds to days and round down.
        $days_left = floor( $difference_in_seconds / ( 60 * 60 * 24 ) );

        return (int) $days_left;
    }

    // Return large number if the user has no membership or their membership doesn't expire.
    return 999999;
}

/*
	Shortcode to show a member's expiration date.
	
	Add this code to your active theme's functions.php or a custom plugin.
	
	Then add the shortcode [pmpro_expiration_date] where you want the current user's
	expiration date to appear.
	
	If the user is logged out or doesn't have an expiration date, then --- is shown.
*/
function pmpro_expiration_date_shortcode( $atts ) {
	//make sure PMPro is active
	if(!function_exists('pmpro_getMembershipLevelForUser'))
		return;
	
	//get attributes
	$a = shortcode_atts( array(
	    'user' => '',
	), $atts );
	
	//find user
	if(!empty($a['user']) && is_numeric($a['user'])) {
		$user_id = $a['user'];
	} elseif(!empty($a['user']) && strpos($a['user'], '@') !== false) {
		$user = get_user_by('email', $a['user']);
		$user_id = $user->ID;
	} elseif(!empty($a['user'])) {
		$user = get_user_by('login', $a['user']);
		$user_id = $user->ID;
	} else {
		$user_id = false;
	}
	
	//no user ID? bail
	if(!isset($user_id))
		return;

    $enddate = pmpro_get_expiration_date( $user_id );

	if(!empty($enddate)) {
	    $tz = new DateTimeZone(wp_timezone_string());
        $enddate_obj = new DateTime($level->enddate, $tz);
		$content = $enddate_obj->format(get_option('date_format'));
    }
	else
		$content = "---";

	return $content;
}
add_shortcode('pmpro_expiration_date', 'pmpro_expiration_date_shortcode');


// add_filter('pods_api_pre_save_pod_item_tour', 'bike_pre_save_tour', 10, 2);
function bike_pre_save_tour($pieces, $is_new_item) {
    if ($is_new_item) {
        $num = $pieces['fields']['tour_number']['value'];
        $pod = pods('tour');
        $params = array(
            'where' => 'CAST(tour_number.meta_value AS unsigned) = ' .  $num
        );
        $pod->find($params);
        if ($pod->total() > 0)
           $pieces['fields']['tour_number']['value'] = bike_new_tourno();
    }
    return $pieces;
}

add_filter( 'gform_pre_render_19', 'bike_add_tour' );
add_filter( 'gform_pre_validation_19', 'bike_add_tour' );
add_filter( 'gform_pre_submission_filter_19', 'bike_add_tour' );
add_filter( 'gform_admin_pre_render_19', 'bike_add_tour' );
function bike_add_tour( $form ) {
    foreach ($form['fields'] as &$field) {
         if ($field->id == 11)
             $field->defaultValue = bike_new_tourno();
    }
    return $form;
}

add_action( 'xprofile_updated_profile', 'bk_clear_cache' ); 
add_action( 'pods_api_post_save_pod_item_road_closurewarning', 'bk_clear_cache' ); 

add_action( 'pods_api_post_save_pod_item_start_point', 'bk_clear_cache', 10, 3 ); 

add_action( 'pods_api_post_save_pod_item_tour', 'bike_custom_pods_update_terms_on_save', 10, 3 ); 

/** 
 * Update post terms on save for another associated taxonomy. 
 * 
 * @param array   $pieces      List of data. 
 * @param boolean $is_new_item Whether the item is new. 
 * @param int     $id          Item ID. 
 */ 
function bike_custom_pods_update_terms_on_save( $pieces, $is_new_item, $id ) { 
    remove_action( 'pods_api_post_save_pod_item_tour', 'bike_custom_pods_update_terms_on_save', 10, 3 ); 
    global $wpdb;
    $pod = pods('tour', $id);
	$mapurl = $pod->field('tour_map');
	if (!empty($mapurl) && strpos($mapurl, "http:") !== false)
	    $pod->save('tour_map', str_replace("http:", "https:", $mapurl));
    $cue = $pod->field('cue_sheet');
    $cueid = $cue ? $cue['ID'] : 0;
    $tourno = $pod->field('tour_number');
    $cue_sheet_number = $pod->field('cue_sheet_number');
	if (empty($tourno) || $tourno == 0) {
        $query = 'SELECT meta_value FROM ' . $wpdb->prefix . 'postmeta WHERE meta_key = "tour_number" ORDER BY CAST(meta_value AS unsigned) DESC LIMIT 1';
        $results = $wpdb->get_results($query);
		if ($results) {
            foreach ($results as $result)
			    $tourno = $result->meta_value + 1;
		}
		else
		    $tourno = 1;
        $pod->save('tour_number', $tourno);
	}
    if ($cueid > 0) {
        $filepath = get_post_meta($cueid, '_wp_attached_file', true);
        $new_cuenum = 0;
        if (!empty($filepath)) {
            if ($cue_sheet_number == 0) {
                $query = 'SELECT meta_value FROM ' . $wpdb->prefix . 'postmeta WHERE meta_key = "cue_sheet_number" ORDER BY CAST(meta_value AS unsigned) DESC LIMIT 1';
                $results = $wpdb->get_results($query);
		        if ($results) {
			        $cue_sheet_number = $results[0]->meta_value + 1;
                }
                else
		            $cue_sheet_number = 1;
                $new_cuenum = 1;
            }
			$upload_path = ABSPATH . 'wp-content/uploads/';
            if ($tourno < 100)
                $tourno = sprintf("%03d", $tourno);
            $new_filename = $cue_sheet_number . '-tour_' . $tourno . '.pdf';
			$new_path = 'cuesheet/' . $new_filename;
            if ($filepath != $new_path && file_exists($upload_path . $filepath))
		        rename($upload_path . $filepath, $upload_path . $new_path);
            if ($new_cuenum == 1) {
                update_post_meta($cueid, '_wp_attached_file', $new_path);
			    $guid = get_site_url() . '/wp-content/uploads/' . $new_path;
			    $wpdb->update( $wpdb->posts, array('guid' => $guid, 'post_name' => $new_filename, 'post_title' => $new_filename), array('ID' => $cueid));
	            $pod->save( 'cue_sheet_number', $cue_sheet_number);
            }
        }
    }
    add_action( 'pods_api_post_save_pod_item_tour', 'bike_custom_pods_update_terms_on_save', 10, 3 ); 
	bk_clear_cache();
}

add_action('wp_logout', 'bike_logout');
function bike_logout() {
    wp_redirect(home_url());
    exit();
}


function getgpsfile($mapfile, $isgpx)
{
    if (strstr($mapfile, "privacy_code") === false) {
        if ($isgpx) {
            return preg_replace("!routes/([^?]*)!", "routes/$1.gpx?sub_format=track&poi_as_wpt=true", $mapfile);
        }
        else {
            return preg_replace("!routes/([^?]*)!", "routes/$1.tcx", $mapfile);
        }
    }
    else {
        if ($isgpx) {
            return preg_replace("!routes/([^?]*)\?(.*)!", "routes/$1.gpx?$2&sub_format=track&poi_as_wpt=true", $mapfile);
        }
        else {
            return preg_replace("!routes/([^?]*)\?(.*)!", "routes/$1.tcx?$2", $mapfile);
        }
    }
    return $ret;
}
function bike_cue_links($cue_num, $tourno, $mapfile)
{
      $ret = "";
      if (!empty($cue_num) && is_array($cue_num))
          $cue_num = $cue_num[0];
      if (!empty($tourno) && is_array($tourno))
          $tourno = $tourno[0];
      if (!empty($cue_num) && $cue_num > 0 && !empty($tourno) && $tourno > 0)
	      $ret .= '<a href="' . get_site_url() . '/downloadCue.php?cuenum=' . $cue_num . '&tournum=' . $tourno . '">CUE</a> ';
      if ($mapfile) {
		  $ret .= '<a href="' . esc_url($mapfile) . '" target="_blank">MAP</a> ';
          $ret .= '<a href="' . esc_url(getgpsfile($mapfile, true)) . '">GPX</a> ';
          $ret .= '<a href="' . esc_url(getgpsfile($mapfile, false)) . '">TCX</a> ';
      }
      return $ret;
}
function bike_add_query_vars($aVars) {
$aVars[] = "tourid";
$aVars[] = "startid";
$aVars[] = "role";
$aVars[] = "riderole";
$aVars[] = "ridecoordinator";
$aVars[] = "socialcoordinator";
$aVars[] = "itcoordinator";
$aVars[] = "rideid";
$aVars[] = "start_date";
$aVars[] = "end_date";
$aVars[] = "status";
$aVars[] = "from";
return $aVars;
}
// hook add_query_vars function into query_vars
add_filter('query_vars', 'bike_add_query_vars');

add_filter( 'bp_get_the_profile_field_value', 'bp_filter_birthdate', 9, 3 );
function bp_filter_birthdate($value, $type, $id)
{
    if ($id == 9 && !empty($value)) {
	    $tz = new DateTimeZone(wp_timezone_string());
        $date_obj = DateTime::createFromFormat("m/d/Y", $value, $tz);
        if ($date_obj == false || empty($date_obj))
            $value = "";
        else
            $value = $date_obj->format('F jS');
    }
    return $value;
}

// add_filter('pre_user_email', 'skip_email_exist');
// function skip_email_exist($user_email){
    // define( 'WP_IMPORTING', 'SKIP_EMAIL_EXIST' );
    // return $user_email;
// }

add_filter('enable_loading_advanced_cache_dropin', 'bike_disable_advanced_cache');
function bike_disable_advanced_cache()
{
    return false;
}

add_filter('wp_get_nav_menu_items', 'bike_get_nav_menu_items', 11, 3);
function bike_get_nav_menu_items($items, $menu, $args)
{
    $str = "";
    if (!is_admin()) {
	    if ($menu->slug == 'ride-leader-pages') {
		    foreach ($items as $item) {
                if (strpos($item->title, 'Schedule a Ride') !== false) {
                    $item->url .= '?ride_coordinator=0';
				}
                else if (strpos($item->title, 'My Led Rides') !== false) {
	                $tz = new DateTimeZone(wp_timezone_string());
                    $date_obj = new DateTime("now", $tz);
                    $yr = $date_obj->format('Y');
                    $mn = $date_obj->format('m');
                    if ($mn < 10)
                        $yr -= 1;
					if (strpos($item->url, "?start_date=") !== false) {
						$item->url = preg_replace("/\?start_date=.*/", "", $item->url);
					}
                    $item->url .= "?start_date=10/1/" . $yr . "&end_date=" . $date_obj->format("m/d/Y");
                }
			}
		}
	}
    return $items;
}
// add_filter( 'gform_entry_id_pre_save_lead_6', 'bike_update_entry_on_form_submission', 10, 2 );

function bk_add_ride_leader_to_ride($rideid, $rpod) {
  	$pod = pods('ride-attendee');
   	$status = 'Yes';
   	$current_userid = get_current_user_id();
   	$user = pods('user', $current_userid);
   	$emergency_phone = strip_tags(xprofile_get_field_data('Emergency Number', $current_userid));
   	$car_license = xprofile_get_field_data('Vehicle License', $current_userid);
   	$cell_phone = strip_tags(xprofile_get_field_data('Mobile Phone', $current_userid));
   	$data = array(
        'title' => $rideid . ' - ' . $user->field('display_name'),
        'rider' => $current_userid,
        'ride_attendee_status' => $status,
        'car_license' => $car_license,
        'emergency_phone' => $emergency_phone,
        'cell_phone' => $cell_phone
    );
   	$aid = $pod->add($data);
   	$rpod->add_to('attendees', $aid);
   	$apod = pods('ride-attendee', $aid);
   	$upod = pods('user', $current_userid);
    $upod->add_to('rides', $aid);
    $apod->save('ride_attendee_status', $status);
    bk_remove_from_other_rides($current_userid, $rideid, 1);
    bk_clear_cache();
}
function bike_update_entry_on_form_submission( $entry_id, $form ) {
    $update_entry_id = get_user_meta(get_current_user_id(), 'search_tours_entry_id', true);
    return $update_entry_id ? $update_entry_id : $entry_id;
}

add_action('gform_after_submission_32', 'bk_proposed_ride_signup');
function bk_proposed_ride_signup($entry) {
    $rideid = rgar($entry, 'post_id');
	if ($rideid > 0 && current_user_can('rideleader')) {
        $rpod = pods('ride', $rideid);
        $rpod->save('ride-status', 0);
        $rpod->save('email_sent', 0);
		$userid = get_current_user_id();
		$author = get_post_field('post_author', $rideid);
	    $author_upod = pods('user', $author);
	    $leader_upod = pods('user', $userid);
		$rpod->save('ride_leader', $userid);
		$title = "ProposedRide-" . $rideid;
    	$data = array(
                    'post_title' => $title,
                    'post_status' => "publish",
                    'submittor' => $author_upod,
                    'ride' => $rideid,
                    'ride_leader' => $leader_upod
                );
    	$pod = pods('chosen_proposed_ride');
    	$postid = $pod->add($data);
		pods('chosen_proposed_ride', $postid)->save('ride', $rideid);
		pods('chosen_proposed_ride', $postid)->save('submittor', $author);
		pods('chosen_proposed_ride', $postid)->save('ride_leader', $userid);
        wp_update_post([ 'ID' => $rideid, 'post_author' => $userid ]);
        bk_add_ride_leader_to_ride($rideid, $rpod);
        bk_send_ride_email_if_needed($rpod, $rideid, $userid);
		bk_clear_cache();
    }
}
add_shortcode('bk-proposed-rides', 'bk_add_previous_proposed_rides_records');
function bk_add_previous_proposed_rides_records()
{
    // set_transient('added_records', 'yes');
    // $val = get_transient("added_records");
    // if ($val !== false && $val == "yes") {
	    // return "Already Added Records";
	// }
	pods('chosen_proposed_ride', 232051)->save('ride', 229379);
	pods('chosen_proposed_ride', 232051)->save('submittor', 4895);
	pods('chosen_proposed_ride', 232051)->save('ride_leader', 3160);
	pods('chosen_proposed_ride', 232052)->save('ride', 229376);
	pods('chosen_proposed_ride', 232052)->save('submittor', 4895);
	pods('chosen_proposed_ride', 232052)->save('ride_leader', 5522);
	pods('chosen_proposed_ride', 232053)->save('ride', 229185);
	pods('chosen_proposed_ride', 232053)->save('submittor', 3240);
	pods('chosen_proposed_ride', 232053)->save('ride_leader', 3160);
	pods('chosen_proposed_ride', 232054)->save('ride', 229376);
	pods('chosen_proposed_ride', 232054)->save('submittor', 4895);
	pods('chosen_proposed_ride', 232054)->save('ride_leader', 3128);
	// $rideid = 229379;
    // $pod = pods('chosen_proposed_ride');
	// $title = "ProposedRide-" . $rideid;
   	// $data = array(
                // 'post_title' => $title,
                // 'post_status' => "publish",
                // 'submittor' => pods('user', 4895),
                // 'ride' => $rideid,
                // 'ride_leader' => pods('user', 3160)
            // );
   	// $pod->add($data);
	// $rideid = 229376;
	// $title = "ProposedRide-" . $rideid;
	// $data['post_title'] = $title;
	// $data['ride'] = $rideid;
	// $data['submittor'] = pods('user', 4895);
	// $data['ride_leader'] = pods('user', 5522);
   	// $pod->add($data);
	// $rideid = 229185;
	// $title = "ProposedRide-" . $rideid;
	// $data['post_title'] = $title;
	// $data['ride'] = $rideid;
	// $data['submittor'] = pods('user', 3240);
	// $data['ride_leader'] = pods('user', 3160);
   	// $pod->add($data);
	// $rideid = 229010;
	////  $title = "ProposedRide-" . $rideid;
	// $data['post_title'] = $title;
	// $data['ride'] = $rideid;
	// $data['submittor'] = pods('user', 4895);
	// $data['ride_leader'] = pods('user', 3128);
   	// $pod->add($data);
	return "Done";
}
add_action('gform_after_submission_7', 'bk_add_ride');
// add ride leader to the attendee list for their ride
function bk_add_ride($entry)
{
    if ( !empty( $_GET['riderole'] ) && $_GET['riderole'] == 2 && current_user_can( "ridecoordinator" ) ) {
    	$rideid = rgar($entry, 'post_id');
       	$rpod = pods('ride', $rideid);
		$status = rgar($entry, 28);
		$rl = $rpod->field('ride_leader');
		if ($rl['ID'] == PROPOSED_RIDE_LEADER_USERID) {
		    $rpod->save('email_sent', 0);
		}
		else if ($status == 1) {
			$rpod->save('ride_leader', PROPOSED_RIDE_LEADER_USERID); // Proposed Ride
		    $rpod->save('email_sent', 0);
        }
		// if ($rl['ID'] != PROPOSED_RIDE_LEADER_USERID && $rl['ID'] == get_current_user_id()) {
			// $rpod->save('ride_leader', PROPOSED_RIDE_LEADER_USERID); // Proposed Ride
		// }
		// if ($status == 0) { // if Scheduled
		    // $status = 1; // Proposed
		// }
        $rpod->save('ride-status', $status);
	}
    else if (!empty($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], "add-ride") && !empty($_GET['riderole']) && $_GET['riderole'] == 1 && current_user_can("rideleader")) {
       	$rideid = rgar($entry, 'post_id');
       	$rpod = pods('ride', $rideid);
        $rpod->save('ride-status', 0);
        bk_add_ride_leader_to_ride($rideid, $rpod);
	}
}

add_action('gform_after_submission_26', 'bk_guest_signup');
function bk_guest_signup($entry)
{
    $name = rgar($entry, 1);
    $rideid = rgar($entry, 12);
    $title = $name . " - " . $rideid;
    $data = array(
                    'post_title' => $title,
                    'post_status' => "publish",
                    'guests_name' => $name,
                    'email' => rgar($entry, 10),
                    'car_license_plate' => rgar($entry, 5),
                    'emergency_number' => rgar($entry, 4),
                    'cell_phone' => rgar($entry, 3),
                    'ride_id' => rgar($entry, 13),
                );
    $pod = pods('guest');
    $gid = $pod->add($data);
    $rpod = pods('ride', $rideid);
    $rpod->add_to( 'guests', $gid );
    pods('guest', $gid)->save('_members_access_role', 'active');
    bk_clear_cache();
}
add_action('gform_after_submission_21', 'bk_rl_update_ride');
function bk_rl_update_ride($entry)
{
	$rpod = pods('ride', get_the_ID());
	$rideid = $rpod->field('ID');
    $rl = $rpod->field('ride_leader');
	$post_userid = get_post_field('post_author', $rideid);
	if ( $post_userid != $rl['ID']) {
    	wp_update_post([ 'ID' => $rideid, 'post_author' => $rl['ID']]);
		$attendees = $rpod->field('attendees');
	    if (!empty($attendees)) {
            $oldleader_apod = null;
            $newleader_apod = null;
		    foreach ($attendees as $attendee) {
			    if (!is_array($attendee))
				    $aid = $attendee;
			    else
				    $aid = $attendee['ID'];
			    $apod = pods('ride-attendee', $aid);
				$rider = $apod->field('rider');
				if ( $rider == $rl['ID'] ) {
            		$oldleader_apod = $apod;
				}
				else if ( $rider == $post_userid ) {
            		$newleader_apod = $apod;
				}
		    }
			if ($oldleader_apod !== null) {
	            $upod = pods('user', $post_userid);
			    $upod->remove_from('rides', $oldleader_aid);
			    $oldleader_apod->save('ride_attendee_status', "No");
			}
			if ($newleader_apod === null) {
        		bk_add_ride_leader_to_ride($rideid, $rpod);
			}
	    }
	}
	$canceled = rgar($entry, 35);
	$num_riders = rgar($entry, 14);
	$current_status = $rpod->field("ride-status");
	if (empty($current_status))
		$current_status = -1;
	$new_status = 0;
	if ($canceled == 1)
		$new_status = 2;
	else if ($num_riders > 0)
		$new_status = 4;
    if ($current_status != $new_status) {
        $rpod->save('ride-status', $new_status);
	}
	$limit = $rpod->field('maximum_signups');
	if (empty($limit))
	    $limit = 0;
	$signups = array();
	$waitlist = array();
	$aid = 0;
	$signup_cnt = bk_get_signup_list($rpod, $signups, $waitlist, $aid);
	$waitlist_cnt = count($waitlist);
	if ($waitlist_cnt > 0 && $signup_cnt < $limit) {
	    $num_to_move = $limit - $signup_cnt;
		if ($num_to_move > $waitlist_cnt)
		    $num_to_move = $waitlist_cnt;
		if ($num_to_move == 0)
		    return;
	    $attendees = $rpod->field('attendees');
        if (!empty($attendees)) {
			$userid_arr = array();
            sort($attendees);
            foreach ($attendees as $attendee) {
				if (!is_array($attendee))
			        $attendeeid = $attendee;
				else
			        $attendeeid = $attendee['ID'];
			    $userid = get_post_field('post_author', $attendeeid);
			    if (empty($userid) || $userid == 0)
			        continue;
				if (!array_key_exists($userid, $userid_arr))
				    $userid_arr[$userid] = $attendeeid;
				else
				    continue;
                $apod = pods('ride-attendee', $attendeeid);
			    $ra_status = $apod->field('ride_attendee_status');
			    if ($ra_status != "No") {
			        $wlnum = $apod->field('wait_list_number');
					if ($wlnum > 0) {
						bk_move_from_waitlist($rpod, $apod);
						if (--$num_to_move == 0)
						    break;
				    }
			    }
			}
		}
	}
	bk_clear_cache();
}
add_action('gform_after_submission_6', 'bike_search_tour_form_entry');
function bike_search_tour_form_entry( $entry )
{
	// echo "after entry_id:"; print_r($entry); die();
    update_user_meta(get_current_user_id(), 'search_tours_entry_id', rgar($entry, 'id'));
}

add_filter( 'gform_pre_render', 'bk_conditionally_show_field_based_on_time' );

// show the time field in the RL update ride form to allow ride leaders
// to update the time of the ride (up to 3 hours before the ride start)
function bk_conditionally_show_field_based_on_time( $form ) {
    if ( $form['id'] != 21 ) { 
        return $form;
    }
    if ( ! empty($_SERVER['REQUEST_URI']) ) {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $rideid = basename($path);
        $pod = pods('ride',$rideid);
        $ride_date = $pod->display('ride_date');
        $start_time = $pod->display('time');
        $tz = new DateTimeZone(wp_timezone_string());
        $ridestart = new DateTime($ride_date . ' ' . $start_time, $tz);
        $min_time = new DateTime('now + 3 hours', $tz);
        $visibility = ( $ridestart > $min_time ) ? 'visible' : 'hidden';
    }
    else {
        $visibility = 'hidden';
    }
    foreach ( $form['fields'] as &$field ) {
        if ( $field->id == 6 ) {
            $field->visibility = $visibility; 
            break;
        }
    }
    return $form;
}
  
function bk_send_cancellation_email($rideid = null)
{
    if (null === $rideid)
        return;
    $pod = pods('ride', $rideid);
    if (empty($pod))
        return;
    $ride_date = $pod->display('ride_date');
    $start_time = $pod->display('time');
    $tz = new DateTimeZone(wp_timezone_string());
	$ridestart = new DateTime($ride_date . ' ' . $start_time, $tz);
	$mintime = new DateTime("now", $tz);
	if ($ridestart < $mintime)
	    return;
	$tourfield = $pod->field('tour');
	$tourid = $tourfield['ID'];
    $tpod = pods('tour', $tourid);
    $rl = $pod->field('ride_leader');
	$rlid = $rl['ID'];
    $rlpod = pods('user', $rlid);
	$pace = $pod->display('pace');
    $template = get_option('club-settings_ride_cancellation_email');
    $dow_obj = new DateTime($ride_date, $tz);
    $dow = $dow_obj->format("l");
    $subject = "MAF: " . $pace . ' Ride Canceled: ' . $dow . ' ' . $ride_date;
    $leader_email = $rlpod->field('user_email');
    $leader = $rlpod->field('display_name');
    $rc = pods('role', 13256);
	$rc_user = $rc->field('member');
    $fromname = $rc_user['display_name'];
    // $from = 'ridecoordinator@email.mafw.org';
    $from = 'ridecoordinator@mafw.org';
    $replyto = 'Reply-To: ' . $leader . ' <' . $leader_email . '>';
    $preferred_phone = xprofile_get_field("Preferred Contact Phone", $rlpod->field('ID'), 'comma');
    if ($preferred_phone == 'Home')
        $rl_phone = strip_tags(xprofile_get_field_data("Home Phone", $rlid));
    else
        $rl_phone = strip_tags(xprofile_get_field_data('Mobile Phone', $rlid));
    $tourno = $tpod->field('tour_number');
    $tourname = $tpod->field('post_title');
    $startpoint = $tpod->display('start_point');
    $miles = $tpod->display('miles');
    $terrain = $tpod->display('tour-terrain');
    $comments = $pod->display('ride_comments');
    $signup = '<a href="' . get_site_url() . '/ride/' . $rideid . '/">Ride Details</a>';
	$long_ride_date = $dow . ", " . $ride_date;
    $macros = array('_LEADER_NAME_', '_LEADER_PHONE_', '_LEADER_EMAIL_', '_RIDE_DATE_START_', '_START_TIME_', '_TOUR_NAME_', '_TOUR_ID_', '_START_POINT_NAME_', '_PACE_', '_TOUR_MILES_', '_TERRAIN_', '_COMMENTS_', '_SIGNUP_');
    $replacements = array($leader, $rl_phone, $leader_email, $long_ride_date, $start_time, $tourname, $tourno, $startpoint, $pace, $miles, $terrain, $comments, $signup);
    $msg = str_replace($macros, $replacements, $template);
    bk_broadcast_email($fromname, $subject, $msg, $from, $replyto, 4, $pace, $rideid);
}
add_shortcode('bk-list-riders', 'bk_list_riders');
function bk_list_riders()
{
	$user_query = new WP_User_Query(array('role' =>  'active'));
    $users = $user_query->get_results();
    $bcclist = "";
    if (!empty($users)) {
      foreach($users as $user) {
        $dns = xprofile_get_field_data("Email Setting", $user->ID, 'comma');
		if (empty($dns)) {
			$dosend = true;
			$result = xprofile_get_field_data("I normally ride at", $user->ID, 'array');
			$user_info = get_userdata($user->ID);
            if ($result == "D" || $result == "D+")
                $bcclist .= $user_info->user_email . "<br />";
		}
      }
    }
    return print_r($bcclist, true);
}
/*
add_shortcode('bk-send-missing-adhocs', 'bk_send_missing_adhocs');
function bk_send_missing_adhocs()
{
    $val = get_transient("sent_emails");
    if ($val !== false && $val == "yes")
        return "not sent";
    set_transient("sent_emails", "yes", 3600);
    bk_send_adhoc_email(192716);
    return "sent";
}
add_shortcode('bk-send-ride-info', 'bk_send_me_ride_info');
function bk_send_me_ride_info()
{
    bk_send_adhoc_email(199902);
    return "sent";
}
*/
function bk_send_adhoc_email($rideid = null)
{
    if (null === $rideid)
        return;
    $pod = pods('ride', $rideid);
    if (empty($pod))
        return;
    if ( !empty( $_GET['riderole'] ) && $_GET['riderole'] == 2 && current_user_can( "ridecoordinator" ) ) {
		return;
	}
	$tourfield = $pod->field('tour');
    if (!empty($tourfield)) {
	    $tourid = $tourfield['ID'];
        $tpod = pods('tour', $tourid);
    }
    else {
		$tpod = null;
	}
    $rl = $pod->field('ride_leader');
	$rlid = $rl['ID'];
	if ($rlid == PROPOSED_RIDE_LEADER_USERID) {
		return;
	}
    $rlpod = pods('user', $rlid);
	$pace = $pod->display('pace');
    $template = get_option('club-settings_ride_schedule_notification_email');
    $ride_date = $pod->display('ride_date');
    $tz = new DateTimeZone(wp_timezone_string());
    $dow_obj = new DateTime($ride_date, $tz);
    $dow = $dow_obj->format("l");
	$ride_date = $dow . ", " . $ride_date;
    $start_time = $pod->display('time');
    $subject = "MAF: " . $pace . ' Ride Scheduled for ' . $dow . ' ' . $pod->display('ride_date');
    $leader_email = $rlpod->field('user_email');
    $leader = $rlpod->field('display_name');
    $rc = pods('role', 13256);
	$rc_user = $rc->field('member');
    $fromname = $rc_user['display_name'];
    // $from = 'ridecoordinator@email.mafw.org';
    $from = 'ridecoordinator@mafw.org';
    $replyto = 'Reply-To: ' . $leader . ' <' . $leader_email . '>';
    $preferred_phone = xprofile_get_field("Preferred Contact Phone", $rlpod->field('ID'), 'comma');
    if ($preferred_phone == 'Home')
        $rl_phone = strip_tags(xprofile_get_field_data("Home Phone", $rlid));
    else
        $rl_phone = strip_tags(xprofile_get_field_data('Mobile Phone', $rlid));
    if ($tpod !== null) {
        $tourno = $tpod->field('tour_number');
        $tourname = $tpod->field('post_title');
    }
	else {
		$tourno = "";
		$tourname = "";
	}
    $startpoint = $tpod->display('start_point');
    $miles = $tpod->display('miles');
    $terrain = $tpod->display('tour-terrain');
    $comments = $pod->display('ride_comments');
    $signup = '<p><a href="' . get_site_url() . '/ride/' . $rideid . '/">Sign Up</a></p>';
    $macros = array('_LEADER_NAME_', '_LEADER_PHONE_', '_LEADER_EMAIL_', '_RIDE_DATE_START_', '_START_TIME_', '_TOUR_NAME_', '_TOUR_ID_', '_START_POINT_NAME_', '_PACE_', '_TOUR_MILES_', '_TERRAIN_', '_COMMENTS_', '_SIGNUP_');
    $replacements = array($leader, $rl_phone, $leader_email, $ride_date, $start_time, $tourname, $tourno, $startpoint, $pace, $miles, $terrain, $comments, $signup);
    $msg = str_replace($macros, $replacements, $template);
    bk_broadcast_email($fromname, $subject, $msg, $from, $replyto, 3, $pace);
}

add_action('wp_mail_failed', 'bk_wpmail_failed', 10, 1);
function bk_wpmail_failed( $wp_error ) {
    error_log("email failed:" . print_r($wp_error, true));
}
function bk_fix_num($number)
{
    $number = str_replace(['+', ' ', '(', ')', '-'], "", $number);
    if ($number[0] != '1')
        $number = "1" . $number;
    return $number;
}
function bk_broadcast_email($fromname, $subject, $msg, $from, $replyto, $sendto, $pace = "", $ridenum = 0, $sendtome = 0, $smsmsg = "")
{
	$headers = array("Content-Type: text/html; charset=UTF-8");
    $headers[] = 'From: ' . $fromname . ' <' . $from . '>';
    $to_email = 'it_coordinator@mafw.org';
    if (!empty($replyto))
        $headers[] = $replyto;
	// sendto: 0=self, 1=all, 2=rideleaders, 3=ridebroadcast, 4=rideattendees
	if ($sendto == 0) {
		$user_info = get_userdata(get_current_user_id());
        $headers[] = 'BCC: ' . $user_info->user_email;
        // if ($to_email == "newsletter@mafw.org")
            // $to_email = "freewheelpat@gmail.com";
        // error_log("sending email to " . $to_email);
        wp_mail($to_email, $subject, $msg, $headers);
        // simple_email_queue_add($user_info->user_email, $subject, $msg, $headers);
		return;
	}
    $count = 0;
	if ($sendto == 4) { // ride attendees
        if ($ridenum > 0) {
            $cell_phones = array();
            $rpod = pods('ride', $ridenum);
            $rl = $rpod->field('ride_leader');
            if ($rl['ID'] == get_current_user_id()) {
                if ($sendtome == 1) {
		            $rl_info = get_userdata($rl['ID']);
                    wp_mail($rl_info->user_email, $subject, $msg, $headers);
                }
    			$guests = $rpod->field('guests');
				if (!empty($guests)) {
	    			foreach ($guests as $guest) {
				$email = pods('guest', $guest['ID'])->field('email');
						if (!empty($email) && is_email($email)) {
							$count++;
                        	$headers[] = 'BCC: ' . $email;
						}
        			}
    			}
	            $attendees = $rpod->field('attendees');
	            if (!empty($attendees)) {
		            foreach ($attendees as $attendee) {
						if (!is_array($attendee))
			                $attendeeid = $attendee;
						else
			                $attendeeid = $attendee['ID'];
			            $userid = get_post_field('post_author', $attendeeid);
			            if (empty($userid) || $userid == 0)
			                continue;
			            $apod = pods('ride-attendee', $attendeeid);
			            $status = $apod->field('ride_attendee_status');
			            $wait_listed = $apod->field('wait_list_number');
						if (empty($wait_listed))
						    $wait_listed = 0;
                        if ($status == "No" || $wait_listed > 0)
                            continue;
				        $user_info = get_userdata($userid);
						$count++;
                        $headers[] = 'BCC: ' . $user_info->user_email;
                        if (!empty($smsmsg)) {
                            $notext = xprofile_get_field_data("Disable Text Messages", $userid, 'comma');
							if (empty($notext)) {
                                $cell_phone = strip_tags(xprofile_get_field_data('Mobile Phone', $userid));
                                if (!empty($cell_phone))
                                    $cell_phones[] =  bk_fix_num($cell_phone);
							}
                        }
                    }
                }
	            if ( $count > 0 ) {
                    wp_mail($to_email, $subject, $msg,  $headers);
                }
            }
            if (count($cell_phones) > 0) {
                $first_name = get_user_meta($rl['ID'], 'first_name', true);
                $txtmsg = "RL:" . $first_name . " " . $smsmsg;
                wp_sms_send($cell_phones, $txtmsg, false);
            }
        }
		return;
	}
	$user_query = new WP_User_Query(array('role' => $sendto == 2 ? 'rideleader' : 'active'));
    $users = $user_query->get_results();
    if (!empty($users)) {
      foreach($users as $user) {
        $dns = xprofile_get_field_data("Email Setting", $user->ID, 'comma');
		if (empty($dns)) {
			$dosend = true;
			if ($sendto == 3) { // ride broadcast
			    $result = xprofile_get_field_data("Ride Announcements for:", $user->ID, 'array');
                if (empty($result) || !is_array($result) || !in_array($pace, $result))
                    $dosend = false;
			}
			else if ($sendto == 1) { // All
				$result = xprofile_get_field_data("Notifications", $user->ID, 'comma');
				if (empty($result))
					$dosend = false;
			}
			if ($dosend == true) {
				$user_info = get_userdata($user->ID);
                $headers[] = 'BCC: ' . $user_info->user_email;
                $count++;
			}
		}
      }
	  if ( $count > 0 ) {
          wp_mail($to_email, $subject, $msg,  $headers);
      }
    }
}
function bk_create_post($subject, $msg, $cat_id, $user_id)
{
    // Create post object
    $my_post = array(
      'post_title'    => strip_tags($subject),
      'post_content'  => strip_tags($msg, '<p><a><h1><h2><h3><h4><h5><h6><li><ol><ul><table><tr><th><td><img>'),
      'post_status'   => 'publish',
      'post_author'   => $user_id,
      'post_category' => array( $cat_id )
    );
 
    // Insert the post into the database
    $post_id = wp_insert_post( $my_post ); 
    // if ($post_id)
		// update_post_meta($post_id,  "_members_access_role", 'active');
}

add_action('gform_after_submission_12', 'bk_process_broadcast_email', 10, 2);
function bk_process_broadcast_email($entry, $form)
{
	if (!function_exists('bk_broadcast_email'))
	    return;
    // 5 = subject
    // 3 = message
    // 7 = send to
    // 9 = role
	$ridenum = 0;
	$subject = rgar($entry, 5);
	$msg = rgar($entry, 3);
    $to = rgar($entry, 7);
    $role = rgar($entry, 9);
    $current_user = wp_get_current_user();
    $replyto = 'Reply-To: ' . $current_user->display_name . ' <' . $current_user->user_email . '>';
    if ($role == "president" && (current_user_can("president") || current_user_can("manage_options"))) {
        $frominfo = pods('role', 13254); // president
        $from = 'president@mafw.org';
	    $from_info = $frominfo->field('member');
        $fromname = $from_info['display_name'];
        $replyto = 'Reply-To: ' . $fromname . ' <president@mafw.org>';
        if ($to != "Just Me") {
            $sender = pods('role', 13254);
	    $member = $sender->field('member');
            $cat_id = get_cat_ID( 'From the President' );
            bk_create_post($subject, $msg, $cat_id, $member['ID']);
        }
    }
    else if ($role == "presidentelect" && (current_user_can("president_elect") || current_user_can("manage_options"))) {
        $frominfo = pods('role', 225594); // president elect
        $from = 'president_elect@mafw.org';
	    $from_info = $frominfo->field('member');
        $fromname = $from_info['display_name'];
        $replyto = 'Reply-To: ' . $fromname . ' <president_elect@mafw.org>';
        if ($to != "Just Me") {
            $sender = pods('role', 13254);
	    $member = $sender->field('member');
            $cat_id = get_cat_ID( 'From the President Elect' );
            bk_create_post($subject, $msg, $cat_id, $member['ID']);
        }
    }
    else if ($role == "ridecoordinator" && (current_user_can("ridecoordinator") || current_user_can("manage_options"))) {
        $frominfo = pods('role', 13256); // ride coordinator
        // $from = 'ridecoordinator@email.mafw.org';
        $from = 'ridecoordinator@mafw.org';
	    $from_info = $frominfo->field('member');
        $fromname = $from_info['display_name'];
        $replyto = 'Reply-To: ' . $fromname . ' <ridecoordinator@mafw.org>';
        if ($to != "Just Me") {
            $sender = pods('role', 13256);
	    $member = $sender->field('member');
            $cat_id = get_cat_ID( 'From the Ride Coordinator' );
            bk_create_post($subject, $msg, $cat_id, $member['ID']);
        }
    }
    else if ($role == "safetycoordinator" && current_user_can("safetycoordinator") ) {
        $frominfo = pods('role', 13260); // safety coordinator
        $from = 'safety@mafw.org';
	    $from_info = $frominfo->field('member');
        $fromname = $from_info['display_name'];
        $replyto = 'Reply-To: ' . $fromname . ' <safety@mafw.org>';
        if ($to != "Just Me") {
            $sender = pods('role', 13260);
	    $member = $sender->field('member');
            $cat_id = get_cat_ID( 'From the Safety Coordinator' );
            bk_create_post($subject, $msg, $cat_id, $member['ID']);
        }
    }
    else if ($role == "socialcoordinator" && current_user_can("socialcoordinator") ) {
        $frominfo = pods('role', 13265); // social coordinator
        $from = 'social@mafw.org';
	    $from_info = $frominfo->field('member');
        $fromname = $from_info['display_name'];
        $replyto = 'Reply-To: ' . $fromname . ' <social@mafw.org>';
        if ($to != "Just Me") {
            $sender = pods('role', 13265);
	    $member = $sender->field('member');
            $cat_id = get_cat_ID( 'From the Social Coordinator' );
            bk_create_post($subject, $msg, $cat_id, $member['ID']);
        }
    }
    else if ($role == "itcoordinator" && current_user_can("manage_options") ) {
        $frominfo = pods('role', 13262); // IT coordinator
        // $from = 'it_coordinator@email.mafw.org';
        $from = 'it_coordinator@mafw.org';
	    $from_info = $frominfo->field('member');
        $fromname = $from_info['display_name'];
        $replyto = 'Reply-To: ' . $fromname . ' <it_coordinator@mafw.org>';
        if ($to != "Just Me") {
            $sender = pods('role', 13262);
	        $member = $sender->field('member');
            $cat_id = get_cat_ID( 'From the IT Coordinator' );
            bk_create_post($subject, $msg, $cat_id, $member['ID']);
        }
    }
    else if ($role == "newslettereditor" && (current_user_can("newslettereditor") || current_user_can("manage_options"))) {
        $frominfo = pods('role', 13261); // newsletter editor
        $from = 'newsletter@mafw.org';
	    $from_info = $frominfo->field('member');
        $fromname = $from_info['display_name'];
        $replyto = 'Reply-To: ' . $fromname . ' <newsletter@mafw.org>';
        if ($to != "Just Me") {
            $sender = pods('role', 13261);
	        $member = $sender->field('member');
            $cat_id = get_cat_ID( 'Newsletter Editor' );
            bk_create_post($subject, $msg, $cat_id, $member['ID']);
            if ($subject) {
		        $strs = [ " now available", " Available" ];
		        $subj = str_replace($strs, "", $subject); 
                set_transient('bk_nl_announcement_subject', $subj);
            }
        }
    }
    else if ($role == "membershipcoordinator" && (current_user_can("membercoordinator") || current_user_can("manage_options"))) {
        $frominfo = pods('role', 13258);
        $from = 'membership@mafw.org';
	    $from_info = $frominfo->field('member');
        $fromname = $from_info['display_name'];
        $replyto = 'Reply-To: ' . $fromname . ' <membership@mafw.org>';
    }
    else if ($role == "rideleader" && (current_user_can("rideleader") || current_user_can("manage_options"))) {
        // $from = 'ridecoordinator@email.mafw.org';
        $from = 'ridecoordinator@mafw.org';
        $fromname = $current_user->display_name;
        $to = "Ride Attendees";
        $ridenum = rgar($entry, 10);
        $replyto = 'Reply-To: ' . $fromname . ' <' . $current_user->user_email . '>';
    }
    else if (current_user_can("manage_options")){
		$to = "Just Me";
        $frominfo = pods('role', 13262); // it coordinator
        // $from = 'it_coordinator@email.mafw.org';
        $from = 'it_coordinator@mafw.org';
	    $from_info = $frominfo->field('member');
        $fromname = $from_info['display_name'];
        $replyto = 'Reply-To: ' . $fromname . ' <it_coordinator@mafw.org>';
    }
    else
        return;
	// (0=self, 1=all, 2=rideleaders, 3=ridebroadcast)
    $sendtome = 0;
    $sendastext = 0;
    $sendto = -1;
    $smsmsg = "";
    switch ($to) {
        case 'Just Me':  $sendto = 0; break;
        case 'All Members':  $sendto = 1; break;
        case 'Ride Leaders':  $sendto = 2; break;
        case 'Ride Attendees':
			if ($ridenum > 0) {
                $sendto = 4;
                // $stm = $entry["11.1"];
                // if ($stm == 'Yes')
                    // $sendtome = 1;
                $smsmsg = $entry["16"];
			}
			else
			    $sendto = -1;
            break;
    }
    if ($sendto >= 0)
        bk_broadcast_email($fromname, $subject, $msg, $from, $replyto, $sendto, "", $ridenum, $sendtome, $smsmsg);
}

function bike_tour_pick_data($data, $name, $value, $options){
    if ($name == "pods_field_tour-terrain" || $name == "pods_field_start_point") {
		$newdata = array();
		if ($name == "pods_field_tour-terrain")
			$podname = "terrain";
		else
			$podname = "start_point";
		foreach ($data as $id => $value) {
			if ($id) {
				$p = pods($podname, $id);
            	if ($p && $p->field('active') == 1) {
			    	$newdata[$id] = $value;
				}
			}
			else
				$newdata[$id] = $value;
        }
		return $newdata;
    }
    return $data;
}
add_filter('pods_field_pick_data', 'bike_tour_pick_data', 1, 4);

function tour_counts()
{
    $tourlast = array();
    $tourcnt = array();
	$tz = new DateTimeZone(wp_timezone_string());
    $sd = new DateTime("now -1 year", $tz);
	$ed = new DateTime("now", $tz);
    $start_date = $sd->format('Y-m-d');
    $end_date = $ed->format('Y-m-d');
    $params = array(
        'limit' => -1,
        'where' => "`ride-status`.meta_value = 4 AND CAST(ride_date.meta_value AS date) >= '$start_date' AND CAST(ride_date.meta_value AS date) < '$end_date'",
        'orderby' => 'CAST(ride_date.meta_value AS date) ASC',
    );
    $pod = pods('ride', $params);
    while ($pod->fetch()) {
         $post_id = $pod->id();
         $tournum_arr = $pod->field('tour');
         if (is_array($tournum_arr))
			 $tid = $tournum_arr['ID'];
	     else
		     $tid = $tournum_arr;
		 if (!empty($tid) && $tid > 0) {
             if (!array_key_exists($tid, $tourcnt))
                 $tourcnt[$tid] = 1;
             else
                 $tourcnt[$tid]++;
             $tourlast[$tid] = $post_id;
         }
    }
    set_transient('tourlast_arr', $tourlast, 12 * 3600);
    set_transient('tourcnt_arr', $tourcnt, 12 * 3600);
}

add_shortcode('bike_add_tours_to_rc', 'bike_add_tour_to_hazard');
function bike_add_tour_to_hazard($hazard, $tourid)
{
    $params = array('limit' => -1);
    $tpod = pods('tour', $params);
    while ($tpod->fetch()) {
         $post_id = $tpod->id();
         $tournum = $tpod->field('tour_number');
         $map[$tournum] = $post_id;
         $hazids = array();
         $hazards = $tpod->field('road_closures');
         if (is_array($hazards))
             foreach ($hazards as $hazard) {
                 $hazids[] = $hazard['ID'];
             }
         $tourmap[$post_id] = $hazids;
    }
	$roadclosures[] = array( 'id' => 211455, 'tournums' => array ( 78, 80, 112, 201, 649, 700, 736, 1126, 1131, 1140, 1350, 78, 80, 112, 201, 649, 700, 736, 1126, 1131, 1140, 1350
    )
	);
	$ret = "";
	foreach ($roadclosures as $roadclosure) {
	    $rcid = $roadclosure['id'];
		$tournums = $roadclosure['tournums'];
		foreach ($tournums as $tournum) {
            if (array_key_exists($tournum, $map)) {
		        $tourid = $map[$tournum];
                $hazids = $tourmap[$tourid];
                if (!array_key_exists($rcid, $hazids)) {
                    $pod = pods('road_closureswarning', $rcid); // upper creek
                    $pod->add_to('tour', $tourid);
				    $ret .= "adding tour " . $tournum . '(' . $tourid . ') to ' . $pod->field('title') . '<br>';
                }
            }
            else
                error_log("Missing map for " . $tournum);
		}
	}
	return $ret;
}
add_shortcode('bk-guest-test', 'bk_guest_test');
function bk_guest_test()
{
    $ret = "";
    $rpod = pods('ride', 200433);
    $guests = $rpod->field('guests');
	if (!empty($guests)) {
	    foreach ($guests as $guest) {
            $name = pods('guest', $guest['ID'])->field('guests_name');
            $ret .= $name . '<br>';
        }
    }
    return $ret;
}

function bk_user_on_ride($rideid, $riderid)
{
	$pod = pods('ride', $rideid);
    $rl = $pod->field('ride_leader');
	if ($rl['ID'] == $riderid)
	    return true;
	$attendees = $pod->field('attendees');
	if (!empty($attendees)) {
		$userid_arr = array();
	    foreach ($attendees as $attendee) {
			if (!is_array($attendee))
			    $attendeeid = $attendee;
			else
			    $attendeeid = $attendee['ID'];
			$userid = get_post_field('post_author', $attendeeid);
			if (empty($userid) || $userid == 0)
			    continue;
			if (!array_key_exists($userid, $userid_arr))
			    $userid_arr[$userid] = $attendeeid;
			else
			    continue;
			$status = pods('ride-attendee', $attendeeid)->field('ride_attendee_status');
			if ($status != "No" && $userid == $riderid)
			    return true;
	    }
	}
	return false;
}

// check if rider is already signed up (or is RL) on another ride 
// returns rideid of other ride or 0 means they are not signed up
//  on another ride
function rider_signupcheck($start_time, $rideid)
{
	$rpod = pods('ride', $rideid);
    $ride_date = $rpod->display('ride_date');
	$current_userid = get_current_user_id();
    $attendees = bk_get_attended_rides($current_userid);
	$min_time = clone $start_time;
	$max_time = clone $start_time;
	$interval = new DateInterval('PT3H');
	$min_time->sub($interval);
	$max_time->add($interval);
	if (!empty($attendees)) {
	    foreach ($attendees as $attendee) {
		    if (!is_array($attendee))
			    $attendeeid = $attendee;
			else
			    $attendeeid = $attendee['ID'];
			$userid = get_post_field('post_author', $attendeeid);
			if (empty($userid) || $userid == 0)
			    continue;
			$apod = pods('ride-attendee', $attendeeid);
            if ($apod->field('ride_attendee_status') == "No")
			    continue;
			$wlnum = $apod->field('wait_list_number');
			$ride = $apod->field('ride');
			if (!empty($ride) && $ride['ID'] > 0) {
				// if alreay signed up on this ride then allow them to change
				// status by treating them as not signed up on another ride
			    $rpod = pods('ride', $ride['ID']);
				if ($ride['ID'] == $rideid)
				    return 0;
                if ($rpod->field('ride-status') == 0) {
                    $ride_date = $rpod->display('ride_date');
                    $st_time = $rpod->display('time');
	                $tz = new DateTimeZone(wp_timezone_string());
				    $datestr = $ride_date . ' ' . $st_time;
				    $start_time = DateTime::createFromFormat("m/d/Y h:i a", $datestr, $tz);
				    // allow waitlisting on more than one ride
				    if ($start_time >= $min_time && $start_time <= $max_time) {
				        return  $ride['ID'] * ($wlnum > 0 ? -1 : 1);
				    }
                }
			}
		}
    }
    // $rideid = ride_leadercheck($ride_date);
	// if ($rideid > 0)
	    // return $rideid;
	return 0;
}
function bk_ride_date_loc_prohibited($date, $tourid)
{
	$tz = new DateTimeZone(wp_timezone_string());
    $ride_date = DateTime::createFromFormat("m/d/Y", $date, $tz);
	$datestr = $ride_date->format("Y-m-d");
    $ride_date_minus_one = $ride_date->modify('-1 day');
	$datestr_minus_one = $ride_date_minus_one->format("Y-m-d");
	$tpod = pods('tour', $tourid);
    $start = $tpod->field('start_point');
	$startid = $start ? $start['ID'] : 0;
    $params = array(
        'limit' => -1,
        'where' => "CAST(end_date.meta_value AS date) <= '$datestr' AND CAST(start_date.meta_value AS date) > '$datestr_minus_one'",
    );
    $pod = pods('locationdateblock', $params);

    if ($pod->total() > 0) {
        while ($pod->fetch()) {
			$blockid = $pod->id();
		    $start_loc = $pod->field('start_location');
		    $start_date = $pod->field('start_date');
		    $end_date = $pod->field('end_date');
    		$dow = $pod->field('days_of_week');
    		if ( $dow == "All Days" ||
                ($dow == "Weekends" && $ride_date->format('N') >= 6) ||
        		($dow == "Weekdays" && $ride_date->format('N') < 6))
    		{
		    	if (empty($start_loc) || $start_loc['ID'] == $startid) {
			    	return strip_tags($pod->field('post_content'));
        		}
			}
    	}
	}
    return null;
}
function rideleader_signupcheck($start_time, $tourid)
{
	if (empty($tourid) || $tourid == 0 || empty($start_time) || !is_object($start_time))
	    return 0;
	// error_log("starttime:" . $start_time->format("Y-m-d"));
	// error_log("tourid:" . $tourid);
    $startid = pods('tour', $tourid)->field('start_point');
    if (is_array($startid))
	    $startid = $startid['ID'];

    $mintime_gap = intval(get_option('club-settings_min_time_between_rides')) - 1;
    if ($mintime_gap < 0)
        $mintime_gap = 0;
	// error_log("startid:" . $startid);
	$min_time = clone $start_time;
	$max_time = clone $start_time;
	$interval = new DateInterval('PT'.$mintime_gap.'M');
	$min_time->sub($interval);
	$max_time->add($interval);
    $params = array(
        'limit' => -1,
        'where' => "`ride-status`.meta_value = 0 AND CAST(ride_date.meta_value AS date) = '" . $start_time->format("Y-m-d") . "' AND CAST(time.meta_value AS time) >= '" . $min_time->format("H:i") . "' AND CAST(time.meta_value AS time) <= '" . $max_time->format("H:i") . "'",
    );
    // error_log(print_r($params, true));
    $pod = pods('ride', $params);
    // error_log(print_r($query->request, true));
    if ($pod->total() > 0) {
        while ($pod->fetch()) {
			$rideid = $pod->id();
            $tourid = $pod->field('tour');
            if (is_array($tourid))
                $tourid = $tourid['ID'];
			if (!empty($tourid)) {
			    // error_log("tourid:" . $tourid);
			    $start = pods('tour', $tourid)->field('start_point');
                if (is_array($start))
                    $start = $start['ID'];
				if (!empty($start)) {
			        // error_log("startid:" . $start);
                    if ($start == $startid) {
			            return $rideid;
			        }
				}
			}
        }
	}
    return 0;
}
function ride_leadercheck($ridestart, $userid = 0)
{
	if ($userid == 0)
	    $userid = get_current_user_id();
    $sd = clone $ridestart;
    $ed = clone $ridestart;
    $interval = new DateInterval('PT3H');
    $sd->sub($interval);
    $ed->add($interval);
	$ride_date = $sd->format('Y-m-d');
	$start_time = $sd->format('H:i');
	$end_time = $ed->format('H:i');
    $params = array(
        'limit' => -1,
        'where' => "ride_leader.ID = $userid AND `ride-status`.meta_value = 0 AND CAST(ride_date.meta_value AS date) = '$ride_date' AND CAST(time.meta_value AS time) >= '$start_time' AND CAST(time.meta_value AS time) <= '$end_time'",
    );
    $pod = pods('ride', $params);
	if ($pod->total() > 0) {
        $pod->fetch();
        return $pod->id();
	}
	return 0;
}

add_filter( 'gform_validation_11', 'bikeride_date_range_validation' );
add_filter( 'gform_validation_15', 'bikeride_date_range_validation' );

function bikeride_date_range_validation( $validation_result )
{
    global $wp;
    $current_slug = add_query_arg( array(), $wp->request );
    if ($current_slug == "my-lead-rides")
        return $validation_result;
    $form = $validation_result['form'];
	$tz = new DateTimeZone(wp_timezone_string());
	$start_date = convertDateFormat(rgpost('input_1'), 'm/d/Y', 'Y-m-d');
	$end_date = convertDateFormat(rgpost('input_2'), 'm/d/Y', 'Y-m-d');
    // if ($current_slug == "ride-leader-report")
        $max_days = 10000; // 545;
    // else
        // $max_days = 100;
	if (false == $start_date)
        $max_obj = new DateTime( 'now + ' . $max_days . ' days', $tz);
	else
        $max_obj = new DateTime( $start_date . ' + ' . $max_days . ' days', $tz);
	if (false == $end_date)
        $end_obj = new DateTime( 'now', $tz);
	else
        $end_obj = new DateTime( $end_date, $tz);
    if ($end_obj > $max_obj) {
        foreach ($form['fields'] as &$field ) {
            if ($field->id == '2') { // end date field
			    $field->failed_validation = true;
		        $field->validation_message = 'You cannot specify a date range greater than ' . $max_days . ' days.';
	            $validation_result['is_valid'] = false;
				break;
		    }
        }
    }
	return $validation_result;
}
add_shortcode('bk-election-results', 'bk_election_results');
function bk_election_results()
{
    $candidates = [
		"Joe Reo" => "President",
		"Michael Chenkin" => "President",
		"Jim Anderson" => "Vice President",
		"Jeff Sperling" => "Vice President",
		"Kim Tulloch" => "Secretary",
		"Merritt Peterson" => "Treasurer",
		"Manny Coelho" => "Ride Coordinator",
		"Lisa Gentile" => "Membership Coordinator",
		"Drew Thraen" => "Safety Coordinator",
		"Jon Eiseman" => "IT Coordinator",
		"Mark Jay" => "Member at Large",
		"Barry Seip" => "Member at Large",
    ];
    $counts = [];
    foreach ($candidates as $name => $position) {
		$counts[$name] = 0;
    }
    $numvotes = 0;
    $msg = "";
    $arr = get_option("election_2024");
    if (!empty($arr) && is_array($arr)) {
        foreach ($arr as $id => $entry) {
            $numvotes++;
		    for ($idx = 2; $idx < 11; $idx++) {
				if ( array_key_exists( $idx, $entry ) ) {
					if ( array_key_exists( $entry[ $idx ], $counts ) ) {
			            $counts[$entry[$idx]]++;
					}
				}
			}
		}
    }
	$msg .= "<table><tr><td><strong>Person/Position</strong></td><td><strong>Votes</strong></td></tr>";
    foreach ($candidates as $name => $position) {
	    $msg .= "<tr><td>" . $name . " - " . $position . "</td><td>" . $counts[$name] . "</td></tr>";
	}
    $msg .= "</table>";
    $msg .= "<p>Total Ballots Cast:" . $numvotes . "</p>";
    /* $arr = get_option("election_2024");
    if (!empty($arr) && is_array($arr)) {
        foreach ($arr as $id => $entry) {
			$user_info = get_userdata($id);
			$msg .= $user_info->display_name . "," . $user_info->user_email . "<br />";
		}
	} */
	return $msg;
}
add_action('gform_after_submission_31', 'bk_add_vote');
function bk_add_vote($entry)
{
    $id = get_current_user_id();
    $arr = get_option("election_2024");
    if (empty($arr) || !is_array($arr) || !array_key_exists($id, $arr)) {
        $arr[$id] = $entry;
        update_option("election_2024", $arr);
    }
}
add_filter( 'gform_validation_31', 'bk_election_validate' );
function bk_election_validate( $validation_result ) {
    $id = get_current_user_id();
    $arr = get_option("election_2024");
    if (!empty($arr) && is_array($arr) && array_key_exists($id, $arr)) {
		$validation_message = 'You have already placed your vote.';
		$validation_result['is_valid'] = false;
        $form = $validation_result['form'];
        foreach ($form['fields'] as &$field ) {
            if ($field->id == '2') {
			    $field->failed_validation = true;
				$field->validation_message = $validation_message;
				break;
		    }
        }
    }
	return $validation_result;
}
add_filter( 'gform_validation_21', 'bikeride_rl_validate' );
function bikeride_rl_validate( $validation_result ) {
    $form = $validation_result['form'];
	$rpod = pods('ride', get_the_ID());
    foreach ($form['fields'] as &$field ) {
        if ($field->id == '14' && rgpost('input_14') > 0) { // rider count field
            $tz = new DateTimeZone(wp_timezone_string());
            $ridedate = new DateTime($rpod->display('ride_date'), $tz);
            $curdate = new DateTime("now", $tz);
            if ($ridedate > $curdate && rgpost('input_14') > 0) {
	            $validation_result['is_valid'] = false;
		        $field->failed_validation = true;
			    $field->validation_message = 'You cannot update the rider count before the ride occurs.';
			    $validation_result['is_valid'] = false;
				break;
		    }
	    }
		else if ($field->id == 32) { // max signups
            $count = bk_get_signup_list($rpod, $signups, $waitlist, $aid);
	        $max_signups = rgpost('input_32');
			// error_log("count:" . $count . " max_signups:" . $max_signups);
			if ($count > $max_signups) {
		        $field->failed_validation = true;
			    $field->validation_message = 'You cannot reduce the maximum signups below the number of people already signed up (which is ' . $count . ')';
			    $validation_result['is_valid'] = false;
			    break;
			}
		}
    }
    $validation_result['form'] = $form;
	return $validation_result;
}
// this is for adding a new ride
add_filter( 'gform_validation_7', 'bikeride_validation' );
function bikeride_validation( $validation_result ) {
	$indoors = (rgpost('input_3') == "I") ? true : false;
	// only need to check if this is a ride leader
	// the ride coordinator can do whatever he/she likes
	$rideloc_disallowed = false;
    // if (rgpost('input_13') != 2) {
	$status = rgpost('input_28');
	if (get_current_user_id() != 3964 && get_current_user_id() != 1 && $status != 2 && $status != 3) {
		$valid = 1;
        $date = rgpost('input_5');
        $tm = rgpost('input_6');
	    $tz = new DateTimeZone(wp_timezone_string());
        if (empty($date) || empty($tm[0]) || empty($tm[1])) {
            $valid = 0;
			$validation_message = 'You must specify a valid time and date for the ride.';
        }
        else {
		    $datestr = $date . ' ' . $tm[0] . ':' . $tm[1] . ' ' . $tm[2];
            $start_time = DateTime::createFromFormat("m/d/Y h:i a", $datestr, $tz);
            if ($start_time == false) {
                 $valid = 0;
			     $validation_message = 'You must specify a valid time and date for the ride.';
            }
            else {
		        $min_time = new DateTime('now + 3 hours', $tz);
		        if ($valid == 1 && $start_time < $min_time) {
                    $valid = 0;
			        $validation_message = 'You need to schedule the ride at least three hours before the ride start time!';
		        }
            }
        }
		$tourid = rgpost('input_21');
        if ( $valid == 1 ) {
		    $msg = bk_ride_date_loc_prohibited($date, $tourid);
            if ($msg) {
			    $valid = 0;
			    $rideloc_disallowed = true;
			    $validation_message = $msg;
		    }
		}
        if ($valid == 1 && ride_leadercheck($start_time) != 0) {
            $valid = 0;
			$validation_message = 'You are already leading a ride within a 3 hour window of this ride.';
        }
		else if ($valid == 1 && ($rideid = rideleader_signupcheck($start_time, $tourid)) != 0 && $indoors == false) {
            $valid = 0;
			$link = '<a href="' . get_site_url() . '/ride/' . $rideid . '"> Existing ride</a>';
                        $mintime_gap = intval(get_option('club-settings_min_time_between_rides'));
			$validation_message = 'There is already a ride scheduled at the same start point within ' . $mintime_gap . ' minutes of this proposed ride.' . $link;
		}
		else if ($valid == 1 && $indoors == false) {
	        $dt = new DateTime($datestr, $tz);
	        $date_tm = $dt->getTimeStamp();
            $dtz = date_default_timezone_get();
            date_default_timezone_set("America/New_York");
            $suninfo = date_sun_info($date_tm, 40.774, -74.466);
            $sunrise = new DateTime($date . ' ' . date("h:ia", $suninfo['sunrise']), $tz);
            $sunset = new DateTime($date . ' ' . date("h:ia", $suninfo['sunset']), $tz);
            date_default_timezone_set($dtz);
		    if ($start_time < $sunrise) {
			    $valid = 0;
				$validation_message = 'The scheduled start time is earlier than sunrise.';
			}
			else if ($start_time > $sunset) {
			    $valid = 0;
			    $validation_message = 'The scheduled start time is later than sunset. Did you select "PM" instead of "AM"?';

			} else {
		        $tourid = rgpost('input_21');
				$paceid = rgpost('input_3');
				$tpod = pods('tour', $tourid);
				$miles = $tpod->field('miles');
				$pace = pods('pace', $paceid);
				$minspeed = $pace->field('minspeed');
                if ($minspeed == 0)
                    $minspeed = 9;
				$interval = $sunset->diff($start_time);
				$diff = $interval->h + $interval->i / 60;
				$maxdist = round($diff * $minspeed);
				if ($miles > $maxdist) {
				    $valid = 0;
					$validation_message = "The ride is too long given the start time and the pace (it would end after sunset). Try selecting a shorter ride or an earlier start time. The maximum distance for this start time and pace is " . $maxdist . " miles.";
				}
			}
		}
		if ($valid == 0) {
			$validation_result['is_valid'] = false;
            $form = $validation_result['form'];
			$fieldid = $rideloc_disallowed ? '5' : '6';
            foreach ($form['fields'] as &$field ) {
                if ($field->id == $fieldid) { // time (or date) field
				    $field->failed_validation = true;
					$field->validation_message = $validation_message;
					break;
			    }
            }
		}
    }
	return $validation_result;
}

add_filter( 'gform_pre_render_7', 'bikeride_hide_status' );
add_filter( 'gform_pre_validation_7', 'bikeride_hide_status' );
add_filter( 'gform_pre_submission_filter_7', 'bikeride_hide_status' );
add_filter( 'gform_admin_pre_render_7', 'bikeride_hide_status' );
function bikeride_hide_status( $form ) {
    foreach ($form['fields'] as &$field ) {
        $fieldid = rgar($field, 'id');
        if ($fieldid == 15 && (empty($_GET['riderole']) || $_GET['riderole'] != 1))
             $field->type = 'hidden';
    }
    return $form;
}

add_action( 'gform_pre_submission_7', function( $form ) {
	$time_field = GFAPI::get_field($form, '6');
	$value = $time_field->get_value_submission( array() );

    $mintime_gap = intval(get_option('club-settings_min_time_between_rides'));
    if ($mintime_gap < 1)
        $mintime_gap = 1;
    $value[1] = (round(($value[1] / $mintime_gap)) * $mintime_gap);
	if ($value[1] == 60) {
	    $value[1] = 0;
		$value[0]++;
		if ($value[0] == 12) {
			$value[2] = "PM";
		}
		else if ($value[0] == 13) {
	        $value[0] = 1;
			$value[2] = "PM";
		}
	}
	$_POST['input_6'] = $value;
} );

add_filter( 'gform_pre_render_21', 'bikeupdride_hide_status' );
add_filter( 'gform_pre_validation_21', 'bikeupdride_hide_status' );
add_filter( 'gform_pre_submission_filter_21', 'bikeupdride_hide_status' );
add_filter( 'gform_admin_pre_render_21', 'bikeupdride_hide_status' );
function bikeupdride_hide_status( $form ) {
    $rpod = pods('ride', get_the_ID());
	$tz = new DateTimeZone(wp_timezone_string());
	$ridedate = new DateTime($rpod->display('ride_date'), $tz);
    $curdate = new DateTime("now", $tz);
	if ($ridedate < $curdate) {
        foreach ($form['fields'] as &$field ) {
            $fieldid = rgar($field, 'id');
            if ($fieldid == 22 || $fieldid == 26)
                 $field->type = 'hidden';
        }
    }
    return $form;
}

add_filter( 'gform_field_value_start_date', 'bk_populate_start_date');
function bk_populate_start_date($value)
{
    if (!empty($_GET['daterange']) && $_GET['daterange'] == "10days") {
	    $tz = new DateTimeZone(wp_timezone_string());
        $curdate = new DateTime("now", $tz);
        return $curdate->format('m/d/Y');
	}
	else
	    return $value;
}
add_filter( 'gform_field_value_end_date', 'bk_populate_end_date');
function bk_populate_end_date($value)
{
    if (!empty($_GET['daterange']) && $_GET['daterange'] == "default") {
	    $tz = new DateTimeZone(wp_timezone_string());
        $enddate = new DateTime("now + 3 months", $tz);
        return $enddate->format('m/d/Y');
	}
	else
        return $value;
}
add_filter( 'gform_field_choice_markup_pre_render_6_8', 'biketour_populate_posts_pulldown', 10, 4 );
function biketour_populate_posts_pulldown( $choice_markup, $choice, $field, $value ) {
    $entryid = get_user_meta(get_current_user_id(), 'search_tours_entry_id', true);
    if ($entryid) {
        $entry = GFAPI::get_entry($entryid);
        $val = rgar($entry, 8);
        if (!empty($val)) {
            $oldstr = "value='" . $val . "'";
            if (strpos($choice_markup, $val) != false) {
                $newstr = $oldstr . ' selected="selected"';
                $choice_markup = str_replace($oldstr, $newstr, $choice_markup);
            }
        }
    }
    return $choice_markup;
}

// add_filter( 'gform__6', 'biketour_populate_posts' );
add_filter( 'gform_pre_render_6', 'biketour_populate_posts' );
// add_filter( 'gform_pre_validation_6', 'biketour_populate_posts' );
// add_filter( 'gform_pre_submission_filter_6', 'biketour_populate_posts' );
add_filter( 'gform_admin_pre_render_6', 'biketour_populate_posts' );
function biketour_populate_posts( $form ) {
    $entryid = get_user_meta(get_current_user_id(), 'search_tours_entry_id', true);
    if ($entryid) {
        $entry = GFAPI::get_entry($entryid);
    }
    foreach ($form['fields'] as &$field ) {
        $fieldid = rgar($field, 'id');
        if ($entryid) {
            $val = rgar($entry, $fieldid);
            if ($fieldid == 8) {
                $field->choices = bike_start_choices($val);
            }
            else if ($fieldid == 29)
                $val = 1;
			else if ($fieldid == 11)
			    $val = 0;
            else if (!empty($field->choices)) {
                foreach ($field->choices as &$choice ) {
				    if (array_key_exists('value', $choice))
					    $fieldval = $choice['value'];
					else if (array_key_exists('text', $choice))
					    $fieldval = $choice['text'];
                    else
					    $fieldval = "";
					if (!empty($fieldval)) {
				        $choice['isSelected'] = ($fieldval == $val) ? true : false;
				    }
                }
            }
            else
                $field->defaultValue = $val;
        }
    }
    return $form;
}
function bike_start_choices($val)
{
    $startmap = array();
    $choices = array();
    $pod = pods('start_point');
    $params = array(
        'orderby' => 't.post_title' ,
        'limit' => -1,
        'where' => 'active.meta_value = 1'
    );
    $pod->find($params);
    while( $pod->fetch()) {
        $title = $pod->field('post_title');
        if (!array_key_exists($title, $startmap) && !empty($title)) {
            $startmap[$title] = $title;
	        $choices[] = array(
			            'text' => $title,
					    'value' => $title,
						'isSelected' => ($title == $val) ? true : false
					);
        }
    }
    return $choices;
}
add_filter( 'pods_gf_dynamic_choices_7_21', 'bike_tour_choices' );
function bike_tour_choices($choices)
{
	$active = 1;
	// $entryid = get_user_meta(get_current_user_id(), 'search_tours_entry_id', true);
    // if ($entryid) {
        // $entry = GFAPI::get_entry($entryid);
        // if (rgar($entry, 29) != 1)
			// $active = 0;
    // }	
	$arr = array();
	foreach ($choices as $choice) {
		if (array_key_exists('value', $choice) && is_numeric($choice['value'])) {
		    $tpod = pods('tour', $choice['value']);
			if ($tpod->field('active') == $active)
	            $arr[] = array('tourno' => $tpod->field('tour_number'), 'title' => $choice['text'], 'ID' => $choice['value']);
		}
	}
	$choices = array();
	$choices[] = array('text' => '-- Select One --', 'value' => '-- Select One --');
	foreach ($arr as $a) {
	    $text = $a['tourno'] . ' ' . $a['title'];
		$choices[] = array('text' => $text, 'value' => $a['ID']);
	}
	return $choices;
}
function bk_do_ride_cancellation($id) {
	bk_send_cancellation_email($id);
	$pod = pods('ride', $id);
	$attendees = $pod->field('attendees');
	if (!empty($attendees)) {
		foreach ($attendees as $attendee) {
			if (!is_array($attendee))
				$aid = $attendee;
			else
				$aid = $attendee['ID'];
			$apod = pods('ride-attendee', $aid);
			if (!empty($apod))
				$apod->save('ride_attendee_status', "No");
		}
	}
}
function bk_send_ride_email_if_needed($pod, $id, $userid) {
    // check if the time of the ride is in the past
    $tz = new DateTimeZone(wp_timezone_string());
    $ridedate = new DateTime($pod->display('ride_date'), $tz);
    $curdate = new DateTime("now", $tz);
	$email_sent = $pod->field('email_sent');
	$rl = $pod->field('ride_leader');
    $status = $pod->field("ride-status");
	$rlid = $rl['ID'];
    if ($status == 1) {
		if ($rlid != PROPOSED_RIDE_LEADER_USERID)
			$pod->save('ride_leader', PROPOSED_RIDE_LEADER_USERID);
    }
	else if ($rlid == PROPOSED_RIDE_LEADER_USERID) {
		$pod->save('ride-status', 1);
        $status = 1;
    }
    if ($status == 0 && (empty($email_sent) || $email_sent == 0) && $ridedate >= $curdate) {
        bk_send_adhoc_email($id);
		$pod->save('email_sent', 1);
    }
}
add_action('pods_api_post_save_pod_item_ride', 'bike_ride_post_save_function', 10, 3); 
function bike_ride_post_save_function($pieces, $is_new_item, $id) { 
    global $wpdb;
    if (!current_user_can('manage_options')
            && !current_user_can('ridecoordinator')
            && !current_user_can('rideleader'))
        return; 
	$pod = pods('ride', $id);
	$date = $pod->display("ride_date");
	$pacename = $pod->display("pace");
	$tz = new DateTimeZone(wp_timezone_string());
	$current_status = $pod->field("ride-status");
	$rcount = $pod->display("rider_count");
	$canceled = $pod->display("ride_canceled");
	$time = $pod->field("time");
	$pieces = explode(':', $time);
    remove_action('pods_api_post_save_pod_item_ride', 'bike_ride_post_save_function');
	if ( $pieces[0] == "00" ) {
	    $time = "12:" . $pieces[1] . ":00";
		$pod->save('time', $time);
	}
	$datetime_obj = new DateTime($date . ' ' . $time, $tz);
	$datetime = $datetime_obj->format("D, M dS, Y h:ia");
	$wpdb->update( $wpdb->posts, array('comment_status' => 'open'), array('ID' => $id));
	$userfield = $pod->field('ride_leader');
    if (is_array($userfield)) {
        $userid = $userfield['ID'];
	    $username = $pod->display('ride_leader');
    }
    else {
		$userid = get_current_user_id();
		$user = wp_get_current_user();
		$username = $user->display_name;
		$pod->add_to('ride_leader', $user->ID);
	}
    wp_update_post([ 'ID' => $id, 'post_author' => $userid ]);
    $rideleadername = $username;
	$tpodid = $pod->field("tour");
	$title = "";
	if (!empty($tpodid) && !$tpodid > 0) {
	    $tpod = pods("tour", $tpodid);
	    if (!empty($tpod)) {
	        $tourno = $tour->field('tour_number');
	        $tourname = $tour->field('post_title');
            $title = $datetime . ' : ' . $tourno . " " . $tourname . " " . $pacename . " " . $username;
        }
	}
    if (empty($title))
        $title = $datetime . ' : ' . $pacename . " " . $username;
	$pod->save( 'post_title', $title);
    // ride-status: 0=scheduled 1=proposed 2=canceled 3=denied 4=ridden
    // if ride leader fills in number of riders and the status is scheduled
    // then change the status to ridden
    if ($canceled == "Yes") {
        if ($current_status == 0 || empty($current_status) ) {
            update_post_meta($id, 'ride-status', 2);
			$current_status = 2;
			bk_do_ride_cancellation($id);
	    }
    }
	else if ( $current_status == 2 || $current_status == 1 ) {
		bk_do_ride_cancellation($id);
	}
    else if ($rcount > 0 && ( empty($current_status) || $current_status == 0)) {
        bk_send_ride_email_if_needed($pod, $id, $userid);
    }
    else if (empty($current_status) || $current_status == 0) {
			if ("" !== $rideleadername && stripos($rideleadername, "needs") == false  && stripos($rideleadername, "proposed" ) == false ) {
            update_post_meta($id, 'ride-status', 0);
			$current_status =  0;
            // check if the time of the ride is in the past
	        $tz = new DateTimeZone(wp_timezone_string());
	        $ridedate = new DateTime($pod->display('ride_date'), $tz);
            $curdate = new DateTime("now", $tz);
			$email_sent = $pod->field('email_sent');
            if ((empty($email_sent) || $email_sent == 0) && $ridedate >= $curdate) {
                bk_send_adhoc_email($id);
				update_post_meta($id, 'email_sent', 1);
	            bk_remove_from_other_rides($userid, $id, 1);
		    }
	    }
    }
	if (empty($current_status)) {
        update_post_meta($id, 'ride-status', 0);
    }
    add_action('pods_api_post_save_pod_item_ride', 'bike_ride_post_save_function', 10, 3);
    bk_clear_cache();
} 
add_action( 'wp_enqueue_scripts', 'tu_load_font_awesome' );
/** 
 * Enqueue Font Awesome. 
 */
function tu_load_font_awesome() {
    wp_enqueue_style( 'font-awesome', '//use.fontawesome.com/releases/v5.8.1/css/all.css', array(), '5.8.1' );
}

// add_action('wp_enqueue_scripts', function() {
    // wp_enqueue_style( 'bk-fe-style', plugins_dir_url(__FILE__) . 'css/editor.css', [], '5.4.3', 'all' );
// });
// make sure users stay logged in
add_filter('auth_cookie_expiration', 'auth_cookie_expiration_filter_bike', 10, 3);
function auth_cookie_expiration_filter_bike($expiration, $user_id, $remember) {
    if ($remember && !user_can($user_id, 'edit_others_posts')) {
        return YEAR_IN_SECONDS;
    }
    // default
    return $expiration;
}
// Adding a reset button to Gravity form
add_filter( 'gform_submit_button_6', 'bike_form_submit_button', 10, 2 );
function bike_form_submit_button( $button, $form ) {
    $button .= '<input type="button" id="reset" value="Clear Fields">';
    return $button;
}
if (function_exists('bp_is_active'))
    add_action( "init", "myplugin_load_textdomain" );
function myplugin_load_textdomain() {
    load_plugin_textdomain( "buddypress", false, basename( dirname( __FILE__ ) ) . "/languages" );
}

// when user changes name make sure it's just the first and last name
function bike_validate_name($data) {
    if ( $data->field_id == 1 && !empty($data->value)) { 
       $arr = explode(" ", $data->value);
       $cnt = count($arr);
	   if ($cnt < 2) {
           $data->field_id = 0;
           if (function_exists('bp_core_add_message'))
               bp_core_add_message(__('Must specify a first and last name', 'buddypress'), 'error');
	   }
	   else {
	       $data->value = $arr[0] . ' ' . $arr[$cnt - 1];
	   }
	}
}

// redirect from group 1 to group 2. Group 1 is for base which is not used
function bike_change_default_profile_group() {

	if ( !function_exists('bp_is_user_profile_edit') || ! bp_is_user_profile_edit() /* || ! bp_is_my_profile() */ ) {
		return;
	}

	$group_id = 2;
	if ( bp_get_current_profile_group_id()==1 ) {
			bp_core_redirect(bp_displayed_user_domain()."profile/edit/group/2/");
	}
}
add_action( 'get_header', 'bike_change_default_profile_group', 1 );


// disable the user personal data export page
add_filter( 'bp_settings_show_user_data_page', 'bike_remove_data_page' );
function bike_remove_data_page($filter) {
    return false;
}

/*
** Gravity Forms - Disable Autocomplete
*/
add_filter( 'gform_form_tag', 'gform_form_tag_autocomplete', 11, 2 );
function gform_form_tag_autocomplete( $form_tag, $form )
{
    // if ( is_admin() ) return $form_tag;
    // if ( GFFormsModel::is_html5_enabled() )
    // {
        $form_tag = str_replace( '>', ' autocomplete="off">', $form_tag );
    // }
    return $form_tag;
}
add_filter( 'gform_field_content', 'gform_form_input_autocomplete', 11, 5 );
function gform_form_input_autocomplete( $input, $field, $value, $lead_id, $form_id )
{
    // if ( is_admin() ) return $input;
    // if ( GFFormsModel::is_html5_enabled() )
    // {
        $input = preg_replace( '/<(input|textarea)/', '<${1} autocomplete="off" ', $input );
    // }
    return $input;
}
add_filter( 'gform_include_thousands_sep_pre_format_number', function ( $include_seperator, $field ) {
    // return $field->formId == 2 && $field->id == 5 ? false : $include_seperator;
    return false;
}, 10, 2 );
add_filter( 'wp_get_nav_menu_items','bike_nav_items', 11, 3 );

function bike_nav_items( $items, $menu, $args ) 
{
    foreach( $items as $item ) 
    {
        if( 15785 == $item->ID ) {
	        $tz = new DateTimeZone(wp_timezone_string());
			$date_obj = new DateTime("now", $tz);
            $date = $date_obj->format('m/d/Y');
			$enddate_obj = new DateTime("now + 3 weeks", $tz);
            $end_date = $enddate_obj->format('m/d/Y');
			if (strpos($item->url, "?start_date=") !== false) {
				$item->url = preg_replace("/\?start_date=.*/", "", $item->url);
			}
            $item->url .= '?start_date=' . $date . '&end_date=' . $end_date;
        }
		else if (46075 == $item->ID || 213328 == $item->ID || 227089 == $item->ID) {
	        $tz = new DateTimeZone(wp_timezone_string());
			$date_obj = new DateTime("now", $tz);
            $year = $date_obj->format('Y');
			$year--;
			$date = "10/01/" . $year;
			if (strpos($item->url, "?start_date=") !== false) {
				$item->url = preg_replace("/\?start_date=.*/", "", $item->url);
			}
			$item->url .= '?start_date=' . $date;
		}
        else if (135812 == $item->ID && !is_admin() && current_user_can('active')) {
			if (strpos($item->url, "&logged_in=1") !== false) {
            	$item->url .= '&logged_in=1';
			}
        }
    }
    return $items;
}


add_shortcode('bk_ride_list_url', 'bk_ride_list_url_func');
function bk_ride_list_url_func()
{
	// $tz = new DateTimeZone(wp_timezone_string());
	// $date_obj = new DateTime("now", $tz);
    // $date = $date_obj->format('m/d/Y');
	// $enddate_obj = new DateTime("now + 1 week", $tz);
    // $end_date = $enddate_obj->format('m/d/Y');
    // return '/ride-list/?start_date=' . $date . '&end_date=' . $end_date;
    return get_site_url() . '/ride-list/?daterange=default';
}
add_shortcode('bike_ride_list_url', 'bike_ride_list_url_func');
function bike_ride_list_url_func()
{
    return '<a href="' . bk_ride_list_url_func() . '">Ride Schedule</a>';
}
add_filter( 'members_check_parent_post_permission', '__return_false' );

function bike_tour_list_func($atts)
{
    $atts = shortcode_atts( array(
            'role' => '0'
			), $atts, 'bike_tour_list');
    if (!class_exists('Pods') || !current_user_can('active')) {
	    wp_redirect(home_url());
	    die();
    }
    if (!isset($_GET['ride_coordinator'])) {
        return "";
    }
    $entryid = get_user_meta(get_current_user_id(), 'search_tours_entry_id', true);
    $type = "1";
    $is_ride_coordinator = 0;
    $can_edit = current_user_can('ridecoordinator') || current_user_can('rc_cap') || current_user_can('manage_options'); 
    $can_add = $can_edit || current_user_can('rideleader');
    if (!empty($_GET['ride_coordinator']) && $_GET['ride_coordinator'] == '1' && $can_edit)
	    $is_ride_coordinator = 1;
    if ($entryid) {
       $entry = GFAPI::get_entry($entryid);
       $tourno = rgar($entry, 1);
       $tourname = addslashes(sanitize_text_field(rgar($entry, 2)));
       $active = rgar($entry, 29);
       $minlength = intval(rgar($entry, 4));
       $maxlength = intval(rgar($entry, 5));
       $terrain = addslashes(sanitize_text_field(rgar($entry, 9)));
       $startpt = addslashes(rgar($entry, 8));
       $tournotes = addslashes(sanitize_text_field(rgar($entry, 10)));
       $creator = addslashes(sanitize_text_field(rgar($entry, 46)));
       $type = rgar($entry, 11);
       if (empty($type))
           $type = 0;
    }
    if ($is_ride_coordinator == 1) {
        if ($active == 0)
            $active = "%0%";
        else if ($active == 1)
            $active = "%1%";
    }
    else
        $active = "%1%";
    $ret = '
      <table class="display tour_table" style="width:100%">
        <thead>
            <tr>
                <th style="width:5%">Tour #</th>
                <th style="width:25%">Name</th>
                <th class="tablet desktop" style="width:25%">Starting Point</th>
                <th class="tablet desktop" style="width:5%">Miles</th>
                <th class="tablet desktop" style="width:5%">Terrain</th>
                <th class="tablet desktop" style="width:5%">Climb</th>
                <th class="tablet desktop" style="width:5%">Last Ridden</th>
                <th class="tablet desktop" style="width:5%">Times Ridden</th>
                <th class="desktop" style="width:5%">Type</th>
                <th class="desktop" style="width:15%">Links</th>';
                if ($can_edit) {
                    $ret .= '<th class="none"></th>';
                }
                if ($can_add) {
                    $ret .= '<th class="none"></th>';
                }
                $ret .= '<th class="none">Description</th>
                <th class="none">Notes</th>
                <th class="none">Average Climb (feet/mile)</th>
                <th class="none">Tour by</th>
            </tr>
        </thead>
        <tbody>';
    $tourlast = get_transient('tourlast_arr');
    $tourcnt = get_transient('tourcnt_arr');
    if ($tourlast === false || $tourcnt === false) {
        tour_counts();
        $tourlast = get_transient('tourlast_arr');
        $tourcnt = get_transient('tourcnt_arr');
    }
    $filter = 'active.meta_value LIKE "' . $active . '" AND tour_type.meta_value = ' . $type;
    if (!empty($tourno))
        $filter .= ' AND CAST(tour_number.meta_value AS unsigned) = ' . $tourno;
    if (!empty($tourname))
        $filter .= ' AND t.post_title LIKE "%' . $tourname . '%"';
    if (!empty($minlength))
        $filter .= ' AND miles.meta_value >= ' . $minlength;
    if (!empty($maxlength))
        $filter .= ' AND miles.meta_value <= ' . $maxlength;
    if (!empty($terrain))
        $filter .= ' AND tour-terrain.post_title = "' . $terrain . '"';
    if (!empty($startpt))
        $filter .= ' AND start_point.post_title LIKE "%' . $startpt . '%"';
    if (!empty($tournotes))
        $filter .= ' AND tour_comments.meta_value LIKE "%' . $tournotes . '%"';
    if (!empty($creator))
        $filter .= ' AND creator.meta_value LIKE "%' . $creator . '%"';
    $tpod = pods('tour');
    $params = array(
            'orderby' => 'CAST(tour_number.meta_value AS unsigned)' ,
	         'limit' => -1,
             'where' => $filter
        );
    $tpod->find($params);
    while( $tpod->fetch()) {
        $tourno = $tpod->field('tour_number');
        $tid = $tpod->field('ID');
        $hazards = empty($tpod->field('road_closures')) ? "" : "*";
	    $tourid = '';
        $schedride = '';
        if ($can_edit) {
            $url = get_site_url() . '/tour/?p=' . $tid . "&touredit=Edit";
            $tourid .= ' <a href="' . esc_url($url) . '" class="button ride-button">Edit Tour</a>';
        }
        if ($can_add) {
		    $url = get_site_url() . '/add-ride/?pods_gf_field_21=' . $tid . '&riderole=';
            if ($can_edit) {
		        $url .= $is_ride_coordinator ? '2' : '1';
            }
            else
		        $url .= '1';
			$schedride .= ' <a href="' . esc_url($url) .
			   '" class="button ride-button">Schedule Ride</a>';
		}
		$ptpage = '<a href="#" class="pace-terrain">';
        $terrain = $tpod->field('tour-terrain');
        if ($terrain)
            $terrain_link = $ptpage . $terrain['post_title'] . '</a>';
        else
            $terrain_link = "";
        $start = $tpod->field('start_point');
        if ($start) {
            $url = get_site_url() . '/start-point/' . $start['post_name'] . '/';
            $startloc = '<a href="' . esc_url($url) . '">' . $start['post_title'] . '</a>';
        }
        else {
            $startloc = "";
        }
        $ttype = $tpod->field('tour_type');
        $climb = intval($tpod->field('climb'));
        $miles = intval($tpod->field('miles'));
	if ($miles <= 0)
	    $miles = 1; // avoid divide by zero
        $avg_climb =  $miles >= 0 ? round($climb/$miles) : 0;
        $tour_creator = $tpod->field('creator');
        $description = bk_tour_description($tpod);
        $comments = $tpod->field('tour_comments');
        if (is_array($tourlast) && array_key_exists($tid, $tourlast)) {
            $last_time_postid = $tourlast[$tid];
            $last_time = pods('ride', $last_time_postid)->field('ride_date');
            $last_time_link = '<a href="' . get_site_url() . '/ride/' . $last_time_postid . '/">' . $last_time . '</a>';
        }
        else {
            $last_time = "";
	    $last_time_link = "";
	}
        if (is_array($tourcnt) && array_key_exists($tid, $tourcnt))
            $ridden_count = $tourcnt[$tid];
        else
            $ridden_count = 0;
        $cue = bike_cue_links($tpod->field('cue_sheet_number'), $tpod->field('tour_number'), $tpod->field('tour_map') );
        if ($ttype == 0)
            $tour_type =  'Road';
        else
            $tour_type =  'ATB';
        $ret .= '
          <tr>
            <td>' . $tourno . '</td>
            <td><a href="' . get_site_url() . '/tour/?p=' . $tid . '">' . $tpod->display('post_title') . $hazards . '</a></td>
            <td>' . $startloc . '</td>
            <td>' . $miles . '</td>
            <td>' . $terrain_link . '</td>
            <td>' . $climb . '</td>
            <td>' . $last_time_link . '</td>
            <td>' . $ridden_count . '</td>
            <td>' . $tour_type . '</td>
            <td>' . $cue . '</td>';
            if ($can_edit) {
                $ret .= '<td>' . $tourid . '</td>';
            }
            if ($can_add) {
                $ret .= '<td>' . $schedride . '</td>';
            }
            $ret .= '
            <td>' . $description . '</td>
            <td>' . $comments . '</td>
            <td>' . $avg_climb . '</td>
            <td>' . $tour_creator . '</td>
        </tr>';
    } // end while
    $ret .= '</tbody></table>';
    return $ret;
}
add_shortcode('bike_tour_list', 'bike_tour_list_func');

function bk_gforms_confirmation_dynamic_redirect_six( $confirmation, $form, $entry, $ajax ) {
    if (!empty($_GET['ride_coordinator']) && $_GET['ride_coordinator'] == 1 ) {
        $confirmation = array( 'redirect' => get_site_url( null, '/add-ride-edit-tour/?ride_coordinator=1', 'https' ) );
    }
    return $confirmation;
}
function bk_gforms_confirmation_dynamic_redirect_seven( $confirmation, $form, $entry, $ajax ) {
    if (intval(rgar($entry, 13)) == '2' )
         $confirmation = array( 'redirect' => get_site_url( null, '/ride-coordinator-ride-list/?daterange=default', 'https' ) );
    return $confirmation;
}
function bk_gforms_confirmation_dynamic_redirect_eleven( $confirmation, $form, $entry, $ajax ) {
    if (!empty($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], "my-lead-rides")) {
        $start = rgar($entry, 1);
        $end = rgar($entry, 2);
        $start = convertDateFormat($start, 'Y-m-d', 'm/d/Y');
	$end = convertDateFormat($end, 'Y-m-d', 'm/d/Y');
        $confirmation = array( 'redirect' => get_site_url( null, '/my-lead-rides/?start_date=' . $start . '&end_date=' . $end, 'https' ) );
    }
    return $confirmation;
}
add_filter( 'gform_confirmation_6', 'bk_gforms_confirmation_dynamic_redirect_six', 10, 4 );
add_filter( 'gform_confirmation_7', 'bk_gforms_confirmation_dynamic_redirect_seven', 10, 4 );
add_filter( 'gform_confirmation_11', 'bk_gforms_confirmation_dynamic_redirect_eleven', 10, 4 );
function bk_gforms_confirmation_dynamic_redirect( $confirmation )
{
	$url = strtok($confirmation['redirect'], '?');
    $confirmation['redirect'] = $url;
    return $confirmation;
}
add_filter( 'gform_confirmation_28', 'bk_gforms_confirmation_dynamic_redirect' );

function bike_pmpro_level_cost_text($text, $level) {
    // is livel is free do nothing
    if (pmpro_isLevelFree($level)) {
        return "";
        // else is different to free do this 
    } else {
        //the full string is : The price for membership is $0.00 now.
        $restituisci = str_replace("now.","",$text); // in this case you can remove "now." in the full string
        return $restituisci;
    }
}
add_filter("pmpro_level_cost_text", "bike_pmpro_level_cost_text", 10, 2);
add_filter( 'wpmu_signup_user_notification', '__return_false' ); 

add_filter( 'comment_form_defaults', 'bike_rich_text_comment_form' );
function bike_rich_text_comment_form( $args ) {
	ob_start();
	wp_editor( '', 'comment', array(
		'media_buttons' => true, // show insert/upload button(s) to users with permission
		'textarea_rows' => '10', // re-size text area
		'dfw' => false, // replace the default full screen with DFW (WordPress 3.4+)
		'tinymce' => array(
        	'theme_advanced_buttons1' => 'bold,italic,underline,strikethrough,bullist,numlist,code,blockquote,link,unlink,outdent,indent,|,undo,redo,fullscreen',
	        'theme_advanced_buttons2' => '', // 2nd row, if needed
        	'theme_advanced_buttons3' => '', // 3rd row, if needed
        	'theme_advanced_buttons4' => '' // 4th row, if needed
  	  	),
		'quicktags' => array(
 	       'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,close'
	    )
	) );
	$args['comment_field'] = ob_get_clean();
	return $args;
}


function bike_show_email_fields()
{
   $ret = "";
   $dns = xprofile_get_field_data("Email Setting", 1, 'comma');
   if (!empty($dns)) {
   $ret .= 'dns<pre>'; print_r($dns, true) . '</pre>';
   } else $ret .= 'empty dns';
   $result = xprofile_get_field_data("Notifications", 1, 'comma');
   if (!empty($result)) {
   $ret .= 'notifications<pre>' . print_r($result, true) . '</pre>';
   } else $ret .= 'empty note';
   return $ret;
}
add_shortcode('bike_email_info', 'bike_show_email_fields');

function bk_remove_from_admin_bar($wp_admin_bar) {
    if ( ! is_admin() ) {
        $wp_admin_bar->remove_node('search');
        $wp_admin_bar->remove_menu( 'wp-logo' );
    }
}
add_action('admin_bar_menu', 'bk_remove_from_admin_bar', 999);
function bk_test_getusers()
{
	$members = [];
	$user_query = new WP_User_Query(array('role' => 'active'));
    $users = $user_query->get_results();

    $res = "First Name, Last Name, Email<br />";
    if (!empty($users)) {
      foreach($users as $user) {
        $dns = xprofile_get_field_data("Email Setting", $user->ID);
		if (empty($dns)) {
			$dosend = true;
			$result = xprofile_get_field_data("Notifications", $user->ID);
			if (empty($result) || $result == 0) {
				$dosend = false;
			}
			// $result = xprofile_get_field_data("Social Events", $user->ID);
			// if (empty($result) || $result == 1 || $result == "Yes" ) {
				// $dosend = false;
			// }
            if ($dosend == true) {
	            $first_name = get_user_meta($user->ID, 'first_name', true);
	            $last_name = get_user_meta($user->ID, 'last_name', true);
				$user_info = get_userdata($user->ID);
	            $members[] = array($first_name, $last_name, $user_info->user_email);
                    $res .= '"' . $first_name . '","' . $last_name . '","' . $user_info->user_email . '"<br />';
            }
		}
      }
    }
    return $res;
	$data = array('users' => $members);
    $url = "https://mafw.org/downloadcsv.php";
	$options  = array(
	    'http' => array(
		    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
			'method' => 'POST',
			'content' => http_build_query($data)
		)
	);
	$context = stream_context_create($options);
	$result = file_get_contents($url, false, $context);
    return $result;
} 
add_shortcode('bk_test_getusers', 'bk_test_getusers');

function truncate($string, $length)
{
	 return (strlen($string) > $length) ? substr($string, 0, $length) : $string;
}
add_action('wp_ajax_bk_signinsheet_action', 'bk_signinsheet_func');
function bk_signinsheet_func()
{
    if (!class_exists('Pods') ||
     (!current_user_can('rideleader') && !current_user_can('manage_options')) ){
	    wp_redirect(home_url());
	    die();
    }
    if (!isset($_POST['rideid']) || !wp_verify_nonce( $_POST['bk_signinsheet_nonce'], 'bk_signinsheet_action' )) {
        print 'Sorry, your nonce did not verify.';
        exit;
    }
    $rideid = intval($_POST['rideid']);
    if ($rideid <= 0) {
	wp_redirect(home_url());
	die();
    }
    $rpod = pods('ride', $rideid);
    if (empty($rpod)) {
	wp_redirect(home_url());
	die();
    }
    $idx = 0;
    for ($i = 0; $i < 56; $i++) {
        $linesin[$idx] = "{MEMBER_STATUS_$i}";
        $linesout[$idx++] = "____";
        $linesin[$idx] = "{MEMBER_NAME_$i}";
        $linesout[$idx++] = "___________________________________________________";
        $linesin[$idx] = "{MEMBER_EMRG_FONE_$i}";
        $linesout[$idx++] = "____________________________";
        $linesin[$idx] = "{MEMBER_CAR_LIC_$i}";
        $linesout[$idx++] = "___________________";
        $linesin[$idx] = "{MEMBER_CELL_FONE_$i}";
        $linesout[$idx++] = "____________________________";
    }
    $pod = pods('club-settings');
    $signinsheet = $pod->field('signin_sheet');
	$tourfield = $rpod->field('tour');
    $tourid = $tourfield['ID'];
	$tpod = pods('tour', $tourid);
	$ridename = $tpod->field('post_title');
	$ridemiles = $tpod->field('miles');
	$tourid = $tpod->field('tour_number');
	$tour_climb = $tpod->field('climb');
    $startpoint = $tpod->display('start_point');
	$pace = $rpod->display('pace');
	$tz = new DateTimeZone(wp_timezone_string());
	$date_obj = new DateTime($rpod->display('ride_date') . ' ' . $rpod->display('time'), $tz);
	$date = $date_obj->format("m/d/Y");
	$time = $date_obj->format("h:i a");
    $rl = $rpod->field('ride_leader');
    $safetypod = pods('role', 13260); // it coordinator
    $safety_coord = $safetypod->field('member');
    $safetyid = $safety_coord['ID'];
    $safety_info = get_user_meta($safetyid);
    $safetycoord['first_name'] =  $safety_info['first_name'][0];
    $safetycoord['last_name'] =  $safety_info['last_name'][0];
    $safetycoord['address1'] = xprofile_get_field_data('Home Address 1', $safetyid);
    $safetycoord['city'] = xprofile_get_field_data('City', $safetyid);
    $safetycoord['state'] = xprofile_get_field_data('State', $safetyid);
    $safetycoord['zip'] = xprofile_get_field_data('ZIP', $safetyid);
    $rlid = $rl['ID'];
    $rl_info = get_user_meta($rlid);
    $rideleader['first_name'] =  $rl_info['first_name'][0];
    $rideleader['last_name'] =  $rl_info['last_name'][0];
    $rideleader['cell_fone'] = strip_tags(xprofile_get_field_data('Mobile Phone', $rlid));
    $rideleader['emergency_phone'] = strip_tags(xprofile_get_field_data('Emergency Number', $rlid));
    $rideleader['car_license'] = xprofile_get_field_data('Vehicle License', $rlid);
	$attendees = $rpod->field('attendees');
	$idx = 0;
	$i = 0;
	if (!empty($attendees)) {
		$userid_arr = array();
        sort($attendees);
		foreach ($attendees as $attendee) {
			if (!is_array($attendee))
			    $attendeeid = $attendee;
			else
			    $attendeeid = $attendee['ID'];
			$userid = get_post_field('post_author', $attendeeid);
			if (empty($userid) || $userid == 0)
			    continue;
			if (!array_key_exists($userid,  $userid_arr))
			    $userid_arr[$userid] = $attendeeid;
			else
			    continue;
            $apod = pods('ride-attendee', $attendeeid);
			$status = $apod->field('ride_attendee_status');
			$wait_listed = $apod->field('wait_list_number');
			if (empty($wait_listed))
			    $wait_listed = 0;
            if ($status == "No" || $wait_listed > 0)
                continue;
            $user = pods('user', $userid);
			$car_license = $apod->field('car_license');
			$emergency_phone = $apod->field('emergency_phone');
			$cell_phone = $apod->field('cell_phone');
			if (empty($cell_phone))
                $cell_phone = strip_tags(xprofile_get_field_data('Mobile Phone', $userid));
			if (empty($car_license))
                $car_license = xprofile_get_field_data('Vehicle License', $userid);
			if (empty($emergency_phone))
                $emergency_phone = strip_tags(xprofile_get_field_data('Emergency Number', $userid));
			if ($userid == $rlid) {
                $rideleader['cell_fone'] = empty($cell_phone) ? "" : $cell_phone;
				$rideleader['car_license'] = empty($car_license) ? "______________" : "<u>$car_license</u>";
				$rideleader['emergency_phone'] = empty($emergency_phone) ? "" : "<u>$emergency_phone</u>";
			}
			else {
                // Member Status
			    $linesout[$idx++] = '<u>M</u>';
                // Member Name
				$linesout[$idx] = $user->field('display_name');
				if ($status === "Maybe")
				    $linesout[$idx] .= '?';
			    $idx++;
                // Member Emergency Name
				if (!empty($emergency_phone)) {
					$emergency_phone = truncate($emergency_phone, 21);
					$linesout[$idx] = "<u>$emergency_phone</u>";
				}
				$idx++;
                // Member Car License
				if (!empty($car_license)) {
				    $car_license = truncate($car_license, 10);
					$linesout[$idx] = "<u>$car_license</u>";
				}
				$idx++;
                // Member Cell Phone
				if (!empty($cell_phone))
					$linesout[$idx] = "<u>$cell_phone</u>";
				$idx++;
				$i++;
			}
		}
	}
    $guests = $rpod->field('guests');
	if (!empty($guests)) {
	    foreach ($guests as $guest) {
            $guestid = $guest['ID'];
            $gpod = pods('guest', $guestid);
            // Member Status
	        $linesout[$idx++] = '<u>G</u>';
            // Member Name
            $name = $gpod->field('guests_name');
			$linesout[$idx] = $name; $idx++;
            // emergency phone
            $emergency_phone = $gpod->field('emergency_number');
			if (!empty($emergency_phone))
			    $linesout[$idx] = $emergency_phone;
		    $idx++;
            // Member Car License
            $car_license = $gpod->field('car_license_plate');
			if (!empty($car_license)) {
			    $car_license = truncate($car_license, 10);
				$linesout[$idx] = "<u>$car_license</u>";
			}
			$idx++;
            // Member Cell Phone
            $cell_phone = $gpod->field('cell_phone');
			if (!empty($cell_phone)) {
				$linesout[$idx] = "<u>$cell_phone</u>";
            }
			$idx++;
			$i++;
        }
    }
	$commodins = array("{RIDE_NUMBER}","{TOUR_NAME}","{TOUR_NUMBER}","{TOUR_CLIMB}","{START_POINT_NAME}","{TOUR_MILES}","{PACE}","{DATE_START}","{TIME_START}","{LEADER_FIRSTNAME}","{LEADER_LASTNAME}","{LEADER_EMRG_FONE}", "{LEADER_CELL_FONE}","{LEADER_CAR_LIC}", "{SC_FIRSTNAME}","{SC_LASTNAME}","{SC_STREET}","{SC_CITY}","{SC_STATE}","{SC_ZIP}");
	$values = array($rideid, $ridename, $tourid, $tour_climb, $startpoint, $ridemiles, $pace, $date, $time, $rideleader['first_name'], $rideleader['last_name'], $rideleader['emergency_phone'], $rideleader['cell_fone'], $rideleader['car_license'], $safetycoord['first_name'], $safetycoord['last_name'], $safetycoord['address1'], $safetycoord['city'], $safetycoord['state'], $safetycoord['zip']);
	$signinsheet = str_replace($commodins, $values, $signinsheet);
	$signinsheet = str_replace($linesin, $linesout, $signinsheet);
	$dompdf = new Dompdf();
    $dompdf->set_option('enable_remote', TRUE);
	$dompdf->loadHtml($signinsheet);
	$dompdf->setPaper('A4', 'landscape');
	$dompdf->render();
    $file_to_save = get_home_path() . "wp-content/signin.pdf";
    file_put_contents($file_to_save, $dompdf->output());
    header('Content-type: application/pdf');
	// if (stripos($_SERVER['HTTP_USER_AGENT'], "iPad") === false) {
        // header('Content-Disposition: inline; filename="signin.pdf"');
        // header('Content-Length: ' . filesize($file_to_save));
    // }
    header('Content-Transfer-Encoding: binary');
    header('Accept-Ranges: bytes');
    readfile($file_to_save);
	// $dompdf->stream( 'signin.pdf' );
    // $file = fopen("test.html", "w");
    // fwrite($file, $signinsheet);
    // fclose($file);
}
add_shortcode('bk-cue-generate', 'bk_test_cue_generation', 10, 0);
function bk_test_cue_generation()
{
    $contents = file_get_contents("https://mafw.org/wp-content/uploads/tour_127.htm");
	$dompdf = new Dompdf();
    $dompdf->set_option('enable_remote', TRUE);
	$dompdf->loadHtml($contents);
	$dompdf->setPaper('A4', 'portrait');
    $dompdf->setBasePath(realpath('../../uploads')); 
	$dompdf->render();
    $file_to_save = get_home_path() . "wp-content/cue127.pdf";
    file_put_contents($file_to_save, $dompdf->output());
    // header('Content-type: application/pdf');
    // header('Content-Transfer-Encoding: binary');
    // header('Accept-Ranges: bytes');
    // readfile($file_to_save);
    return "";
}
add_filter('pdfemb_override_send_to_editor', 'bk_pdfemb_override_send_to_editor', 10, 4);

function bk_pdfemb_override_send_to_editor($shortcode, $html, $id, $attachment) {
    return '<a href="' . get_site_url() . '/?attachment_id=' . $id . '">' . $html . '</a>';
}

// add_filter( "pmpro_send_expiration_email", "bike_send_expiration_email", 10, 2);
// add_filter( "pmpro_send_expiration_warning_email", "bike_send_expiration_warning_email", 10, 2);
add_filter( "pmpro_send_credit_card_expiring_email", "bike_send_credit_card_expiring_email", 10, 2);
add_filter( "pmpro_send_trial_ending_email", "bike_send_trial_ending_email", 10, 2);
function bike_send_expiration_email($val, $userid)
{
     return false;
}
function bike_send_expiration_warning_email($val, $userid)
{
     return false;
}
function bike_send_credit_card_expiring_email($val, $userid)
{
     return false;
}
function bike_send_trial_ending_email($val, $userid)
{
     return false;
}
// add_filter( 'password_change_email', '__return_false' );

// add_filter( 'send_email_change_email', '__return_false' );

// add_filter ( 'storm_social_icons_hide_text', '__return_false' );

remove_filter('the_content', 'wpautop');

function bk_remove_from_other_rides($userid, $rideid, $wl_move = 0)
{
	// error_log("In bk_remove_from_other_rides rideid:" . $rideid . " userid:" . $userid);
	$attendees = bk_get_attended_rides($userid, $rideid);
	if (!empty($attendees)) {
	    foreach ($attendees as $attendee) {
		    if (!is_array($attendee))
			    $aid = $attendee;
			else
			    $aid = $attendee['ID'];
			$userid = get_post_field('post_author', $aid);
			if (empty($userid) || $userid == 0)
			    continue;
			$apod = pods('ride-attendee', $aid);
			$ra_status = $apod->field('ride_attendee_status');
			$ride = $apod->field('ride');
			$cur_ride_id = !empty($ride) ? $ride['ID'] : 0;
			// error_log("aid:" . $aid . " cur_rideid:" . $cur_ride_id . " rideid:" . $rideid);
			if ($cur_ride_id > 0 && $rideid != $cur_ride_id) {
		        $rpod = pods('ride', $cur_ride_id);
	            $upod = pods('user', get_current_user_id());
				// error_log("removing aid:" . $aid);
		        $upod->remove_from('rides', $aid);
				if ($ra_status != "No") {
			        $wlnum = $apod->field('wait_list_number');
					if ($wlnum > 0) {
                        $apod->save('wait_list_number', 0);
					}
					else if ($wl_move != 0) {
                        $wlapod = bk_find_wl_apod($rpod);
				        if ($wlapod) {
					        bk_move_from_waitlist($rpod, $wlapod);
                            bk_attendee_list_change( $rpod, $wlapod->field('ID') );
						}
					}
                    $apod->save('ride_attendee_status', "No");
				}
			}
		}
    }
	bk_clear_cache();
}
add_shortcode('bike-my-attended-rides', 'bk_attended_ride_table', 10, 0);
function bk_attended_ride_table($userid = 0)
{
	if (empty($userid) || $userid == 0)
	    $userid = get_current_user_id();
    $ret = '<table id="my-signups" class="display ride_table" style="width:100%"><thead>
            <tr>
				 <th>Date</th>
				 <th>Time</th>
				 <th>Pace</th>
				 <th>Starting Point</th>
				 <th>Ride Leader</th>
				 <th>Ride Detail</th>
			</tr>
		</thead>';
    $attendees = bk_get_attended_rides($userid);
	if (!empty($attendees)) {
		usort($attendees, function($a, $b) {
		    return $a['ridestart'] < $b['ridestart'] ? -1 : 1;
		});
		$ret .= '<tbody>';
	    foreach ($attendees as $attendee) {
		    if (!is_array($attendee))
			    $aid = $attendee;
			else
			    $aid = $attendee['ID'];
			$userid = get_post_field('post_author', $aid);
			if (empty($userid) || $userid == 0)
			    continue;
		    $apod = pods('ride-attendee', $aid);
		    $ride = $apod->field('ride');
		    if (!empty($ride) && $ride['ID'] > 0) {
				$rideid = $ride['ID'];
		        $rpod = pods('ride', $rideid);
                if ($rpod->field('ride-status') == 0) {
			        $wlnum = $apod->field('wait_list_number');
					if (!empty($wlnum) && $wlnum > 0)
					    $note = "*";
					else
					    $note = "";
                    $tz = new DateTimeZone(wp_timezone_string());
					$ret .= '<tr>';
                    $ride_date = $rpod->display('ride_date');
		            $ptpage = '<a href="#" class="pace-terrain">';
		            $pace = $ptpage . $rpod->display('pace') . '</a>';
                    $dow_obj = new DateTime($ride_date, $tz);
                    $dow = $dow_obj->format("l");
	                $ride_date = $dow . ", " . $ride_date;
                    $start_time = $rpod->display('time');
                    $tourfield = $rpod->field('tour');
					if (null !== $tourfield) {
                        $tourid = $tourfield['ID'];
                        $tpod = pods('tour', $tourid);
		                $tour = '<a href="' . get_site_url() . '/ride/' . $rideid . '">' . $tpod->field('tour_number') . ' - ' . $tpod->field('post_title') . '</a>';
					}
					else {
						$tour = "";
					}
                    $start = $tpod->field('start_point');
                    $rl = $rpod->field('ride_leader');
                    $rlid = $rl['ID'];
                    $rlpod = pods('user', $rlid);
                    $leader = $rlpod->field('display_name');
		            $rluname = $rlpod->field('user_nicename');
		            $rlname = $rlpod->field('display_name');
		            $ride_leader = '<a href="' . get_site_url() . '/members/' . $rluname . '/profile/">' . $rlname . '</a>';
					if ( $start ) {
                    	$url = get_site_url() . '/start_point/' . $start['post_name'] . '/';
                    	$startloc = '<a href="' . esc_url($url) . '">' . $start['post_title'] . '</a>';
					}
					else {
						$startloc = "";
					}
					$ret .= '<td>' . $ride_date . '</td>';
					$ret .= '<td>' . $start_time . '</td>';
					$ret .= '<td>' . $pace . '</td>';
					$ret .= '<td>' . $startloc . '</td>';
					$ret .= '<td>' . $ride_leader . '</td>';
					$ret .= '<td>' . $tour . $note . '</td>';
					$ret .= '</tr>';
				}
		    }
		}
	}
	$ret .= '</tbody></table>';
	if (empty($attendees))
       $ret .= '<center>You are not currently signed up for any rides.</center>';
    return $ret;
}
function bk_get_attended_rides($userid, $cur_ride_id = 0)
{
	if ($cur_ride_id > 0) {
	    $rpod = pods('ride', $cur_ride_id);
        $ride_date = $rpod->display('ride_date');
        $start_time = $rpod->display('time');
	    $tz = new DateTimeZone(wp_timezone_string());
	    $ridestart = new DateTime($ride_date . ' ' . $start_time, $tz);
	    $min_time = clone $ridestart;
	    $max_time = clone $ridestart;
	    $interval = new DateInterval('PT3H');
	    $min_time->sub($interval);
	    $max_time->add($interval);
	}
	$ret = array();
	$upod = pods('user', $userid);
	$attendees = $upod->field('rides');
	// error_log("cur_ride_id:" . $cur_ride_id);
	if (!empty($attendees)) {
	    foreach ($attendees as $attendee) {
		    if (!is_array($attendee))
			    $aid = $attendee;
			else
			    $aid = $attendee['ID'];
            $auserid = get_post_field('post_author', $aid);
			if (empty($userid) || $userid == 0 || $auserid != $userid)
			    continue;
			$apod = pods('ride-attendee', $aid);
			$ra_status = $apod->field('ride_attendee_status');
			$ride = $apod->field('ride');
			$rideid = !empty($ride) ? $ride['ID'] : 0;
			// error_log("rideid:" . $rideid . " ra_status:" . $ra_status);
			if ($rideid > 0 && $rideid != $cur_ride_id) {
		        $rpod = pods('ride', $rideid);
			    if ($ra_status != "No") {
                    $ride_date = $rpod->display('ride_date');
                    $start_time = $rpod->display('time');
	                $tz = new DateTimeZone(wp_timezone_string());
				    $ridestart = new DateTime($ride_date . ' ' . $start_time, $tz);
	                // $mintime = new DateTime("now + 2 hours", $tz);
	                $mintime = new DateTime("now", $tz);
				    if ($ridestart > $mintime) {
				        if ($cur_ride_id == 0 ||
						        ($cur_ride_id > 0 &&
						        $ridestart >= $min_time &&
								$ridestart <= $max_time)) {
				            $ret[] = array( 'ID' => $aid, 'ridestart' => $ridestart);
				        }
				    }
				    else {
				        $upod->remove_from('rides', $aid);
				    }
			    }
			    else {
				    $upod->remove_from('rides', $aid);
                    // $apod->save('ride_attendee_status', "No");
			    }
			}
		}
    }
	return $ret;
}

add_shortcode('bike-attended-rides', 'bk_attended_rides', 10, 1);
function bk_attended_rides($atts)
{
	$a = shortcode_atts( array(
	    'user' => '',
	), $atts );
	$ret = "";
	if(!empty($atts['user']) && is_numeric($atts['user'])) {
		$userid = !empty($atts['user']) ? $atts['user'] : get_current_user_id();
		$pod = pods('user', $userid);
	    $attendees = bk_get_attended_rides($userid);
		if (!empty($attendees)) {
		    foreach ($attendees as $attendee) {
		        if (!is_array($attendee))
			        $aid = $attendee;
			    else
			        $aid = $attendee['ID'];
			    $userid = get_post_field('post_author', $aid);
			    if (empty($userid) || $userid == 0)
			        continue;
			    $apod = pods('ride-attendee', $aid);
			    $ride = $apod->field('ride');
			    if (!empty($ride) && $ride['ID'] > 0) {
			        $rpod = pods('ride', $ride['ID']);
                    $ride_date = $rpod->display('ride_date');
                    $start_time = $rpod->display('time');
	                $tz = new DateTimeZone(wp_timezone_string());
			        $ret .= '<a href="' . get_site_url() . '/ride/' . $ride['ID'] . '">' . $ride_date . ' ' . $start_time . '</a><br />';
			    }
			}
		}
    }
	return $ret;
}
add_shortcode('safety-coordinator', 'mafw_safety_coordinator');
function mafw_safety_coordinator()
{
    $rpod = pods('ride', 17234 );
    $rl = $rpod->field('ride_leader');
    $rlid = $rl['ID'];
    $rl_info = get_user_meta($rlid);
    $rideleader['first_name'] =  $rl_info['first_name'][0];
    $rideleader['last_name'] =  $rl_info['last_name'][0];
    $rideleader['cell_fone'] = strip_tags(xprofile_get_field_data('Mobile Phone', $rlid));
    return print_r($rl, 1);
}
add_shortcode('bk_display_contacts', 'bk_display_contacts_func');
function bk_display_contacts_func()
{
    $ret = '<table class="display contacts_table" width="100%">
        <thead>
        <tr>
        <th><strong>Position</strong></th>
        <th><strong>Name</strong></th>';
    if (current_user_can("active")) {
        $ret .= '<th><strong>Phone</strong></th>';
    }
    $ret .= '<th><strong>Email</strong></th>
    </tr>
    </thead>
    </tbody>';
    $pod = pods('role');
    $params = array(
       'orderby' => 'index.meta_value',
       'limit' => -1
    );
    $pod->find($params);
    if (0 < $pod->total()) {
        while ($pod->fetch()) {
           $title = $pod->field('post_title');
           if (!current_user_can('active') ) {
                     if ( $title != "President" && FALSE === strpos($title, "Membership Coordinator") && $title != "Ride Coordinator" )
                              continue;
           }
           $ret .= '<tr>';
           $ret .= '<td>' . $title . '</td>';
	       $user = $pod->field('member');
	       if (current_user_can('active')) {
		       $ret .= '<td><a href="' . get_site_url() . '/members/' . $user['user_nicename'] . '/profile/">' . $user['display_name'] . '</a></td>';
               $ret .= '<td><a href="tel:' . $pod->field('telephone') . '">' . $pod->field('telephone') . '</a></td>';
	       }
	       else
		       $ret .= '<td>' . $user['display_name'] . '</td>';
	       $ret .= '<td><a href="mailto:' . $pod->field('email') . '" target="_blank">' . $pod->field('email') . '</a></td>';
           $ret .= '</tr>';
        }
    }
    $ret .= '</tbody>
        </table>';
    return $ret;
}

function bike_theme_name_scripts() {
    global $post;
	if (!is_front_page()) {
		// wp_register_script( 'bk_events_manager_script',
		  // plugins_url('/js/events-manager.js' , __FILE__), array( 'events-manager' ), '1.1' );
		// wp_enqueue_script( 'bk_events_manager_script' );
        wp_enqueue_style( 'data_tables_style', plugins_url('/datatables/datatables.min.css', __FILE__), false, "1.10.26" );
        wp_enqueue_script( 'data_tables_script', plugins_url('/datatables/datatables.min.js', __FILE__), array(), '1.10.27' );
        wp_enqueue_script( 'biketables', plugins_url('/js/datatables.js', __FILE__), array('data_tables_script'), '1.10.76' );
	}
	if ( is_front_page() )
        wp_enqueue_style( 'bk_slider_css', plugins_url('/css/slider.min.css', __FILE__), false, "6.0.11" );
    if (is_page("about_maf"))
        wp_enqueue_style( 'about_css', plugins_url('/css/style_about.min.css', __FILE__), false, "6.0.1" );
}
add_action( 'wp_enqueue_scripts', 'bike_theme_name_scripts' );

// add_action('after_setup_theme', 'bc_remove_title');
function bc_remove_title() {
    add_filter('generate_show_title', '__return_false');
}

add_action('generate_show_title', 'bike_hide_ride_title');
function bike_hide_ride_title()
{
    return get_post_type() == 'ride' ? false : true;
}

// add_action('wp_head', 'bk_hook_header', 100);
function bk_hook_header()
{
    if (!current_user_can('access_backend')) {
        echo '<style type="text/css">';
		echo '#wp-admin-bar-elementor_edit_page { display:none }';
        echo '</style>';
    }
}

add_action('admin_head', 'bike_custom_admin_css');
function bike_custom_admin_css() {
  echo '<style>
     .inside .bp-profile-field .checkbox-options label {
        display: inline;
        margin-left: 15px;
     }
  </style>';
}
// replace WordPress Howdy in WordPress 3.3
function replace_howdy( $wp_admin_bar ) {
    $my_account=$wp_admin_bar->get_node('my-account');
	if ( is_object( $my_account ) ) {
    	$newtitle = str_replace( 'Howdy,', 'Logged in as', $my_account->title );            
    	$wp_admin_bar->add_node( array(
        	'id' => 'my-account',
        	'title' => $newtitle,
    	) );
	}
}
add_filter( 'admin_bar_menu', 'replace_howdy',25 );

// add_action( 'wp_before_admin_bar_render', 'bk_remove_tribe_events', 100 );
// function bk_remove_tribe_events() {
    // global $wp_admin_bar;
    // $wp_admin_bar->remove_node( 'promoter-admin-bar' );
// }
function bk_disable_feed() {
wp_die( __('No feed available,please visit our <a href="'. get_bloginfo('url') .'">homepage</a>!') );
}
add_action('do_feed', 'bk_disable_feed', 1);
add_action('do_feed_rdf', 'bk_disable_feed', 1);
add_action('do_feed_rss', 'bk_disable_feed', 1);
add_action('do_feed_rss2', 'bk_disable_feed', 1);
add_action('do_feed_atom', 'bk_disable_feed', 1);
add_action('do_feed_rss2_comments', 'bk_disable_feed', 1);
add_action('do_feed_atom_comments', 'bk_disable_feed', 1);

add_filter( 'gppc_replace_merge_tags_in_labels', '__return_true' );

//turn on media library in gravity forms rich text editor
function bk_show_media_button( $editor_settings, $field_object, $form, $entry ) {
    if ( current_user_can("edit_others_pages") )
        $editor_settings['media_buttons'] = true;
	$editor_settings['wpautop'] = false;
	$editor_settings['remove_linebreaks'] = false;
	$editor_settings['convert_newlines_to_brs'] = false;
    return $editor_settings;
}
add_filter( 'gform_rich_text_editor_options', 'bk_show_media_button', 10, 4 );

function bk_add_the_mce_plugins( $plugins ) {
    $plugins['table'] = content_url() . '/tinymceplugins/table/plugin.min.js';
    $plugins['code'] = content_url() . '/tinymceplugins/code/plugin.min.js';
    // $plugins['searchreplace'] = content_url() . '/tinymceplugins/searchreplace/plugin.min.js';
    // $plugins['media'] = content_url() . '/tinymceplugins/media/plugin.min.js';
    // $plugins['print'] = content_url() . '/tinymceplugins/print/plugin.min.js';
    // $plugins['fullscreen'] = content_url() . '/tinymceplugins/fullscreen/plugin.min.js';
    $plugins['emoticons'] = content_url() . '/tinymceplugins/emoticons/plugin.min.js';
    // $plugins['image'] = content_url() . '/tinymceplugins/image/plugin.min.js';
    // $plugins['visualblocks'] = content_url() . '/tinymceplugins/visualblocks/plugin.min.js';
    return $plugins;
}
add_filter( 'mce_external_plugins', 'bk_add_the_mce_plugins' );

function bk_mce_buttons_2( $buttons ) {
    $buttons[] = "emoticons";
    $buttons[] = "table";
    $buttons[] = "code";
	return $buttons;
}
add_filter( 'mce_buttons_2', 'bk_mce_buttons_2' );
 
//loads script to do the oembed stuff
function bk_alt_lab_front_end_scripts(){
    wp_enqueue_editor();        
    wp_enqueue_script( 'mce-view', '', array('tiny_mce') ); 
}
// add_action( 'wp_enqueue_scripts', 'bk_alt_lab_front_end_scripts' );

// add_action('plugins_loaded', 'bk_disable_apm_plugi_nag_for_users', 10, 1);

// function bk_disable_apm_plugi_nag_for_users( $user_id ) {
    // $user_id = absint( $user_id );
            
    // if ( $user_id && intval( $user_id ) > 0  && current_user_can('install_plugins')) {
        // update_user_meta( $user_id, '_tribe_apm_plugin_nag', true );
    // }
// }
function defer_parsing_of_js( $url ) {
    if ( is_user_logged_in() ) return $url; //don't break WP Admin
    if ( FALSE === strpos( $url, '.js' ) ) return $url;
    if ( strpos( $url, 'jquery.js' ) ) return $url;
    return str_replace( ' src', ' defer src', $url );
}
add_filter( 'script_loader_tag', 'defer_parsing_of_js', 10 );

add_action('remove_user_role', 'bike_remove_user_role', 10, 2);
function bike_remove_user_role($user_id, $role)
{
    if ($role == 'rideleader')
	    xprofile_set_field_data(115, $user_id, "");
    bk_clear_cache();
}
add_action('add_user_role', 'bike_add_user_role', 10, 2);
function bike_add_user_role($user_id, $role)
{
    if ($role == 'rideleader')
	    xprofile_set_field_data(115, $user_id, "I am a Ride Leader");
    bk_clear_cache();
}

add_action('profile_update', 'bk_change_membership_end_date');
function bk_change_membership_end_date($user_id)
{
	if (!function_exists('pmpro_getMembershipLevelForUser')) return;
	$level = pmpro_getMembershipLevelForUser($user_id);
	if (!empty($level)) {
	    if (!empty($level->enddate) && $level->enddate > 0) {
		    $enddate = date('Y-m-d', $level->enddate);
            $tz = new DateTimeZone(wp_timezone_string());
            $dt = DateTime::createFromFormat("Y-m-d", $enddate, $tz);
            $curdt = new DateTime("now", $tz);
			if (false != $dt && $dt > $curdt) {
                $user = get_userdata($user_id);
                $user->add_role('active');
			}
	    }
		// bike_after_checkout($user_id);
	}
}
// add_shortcode('bk-leader-members', 'bk_rideleader_members');
function bk_rideleader_members()
{
    $leaders = [ 
1,
2893,
2896,
2897,
2898,
2907,
2927,
2933,
2937,
2939,
2956,
2959,
2960,
2968,
2969,
3000,
3046,
3055,
3072,
3081,
3086,
3097,
3128,
3151,
3156,
3157,
3160,
3163,
3164,
3168,
3169,
3193,
3195,
3198,
3200,
3227,
3240,
3260,
3374,
3405,
3410,
3417,
3425,
3454,
3478,
3501,
3511,
3548,
3572,
3604,
3605,
3671,
3675,
3680,
3708,
3725,
3767,
3813,
3825,
3949,
3964,
3969,
3971,
3995,
4011,
4098,
4127,
4145,
4174,
4186,
4203,
4266,
4358,
4361,
4384,
4395,
4466,
4469,
4488,
4501,
4512,
4588,
4668,
4677,
4738,
4750,
4765,
4780,
4895,
4902,
4978,
5004,
5068,
5128,
5133,
5134,
5149,
5177,
5181,
5213,
5246,
5275,
5317,
5353,
5391,
5447,
5472,
5525,
5549,
5578,
5581,
5631,
5632,
5658
    ];
    $msg = "";
	foreach ($leaders as $user_id) {
        $user_info = get_userdata($user_id);
	    $upod = pods('user', $user_id);
	    $email = $upod->field('ride_leader_email');
		$upod->save('ride_leader_email', $user_info->user_email);
        $msg .= $user_info->user_email . "<br />";
	}
	return $msg;
}

add_shortcode('bk-dual-members', 'bk_dual_members');
function bk_dual_members()
{
	global $wpdb;
$members = [ 
3055,
3250,
3316,
3403,
3510,
3527,
3920,
3924,
3927,
4145,
4306,
4565,
4620,
4690,
4883,
5148,
5312,
5331,
5333,
5369,
5436,
5438,
5443,
5489,
5501,
5509,
5522,
3097,
3718,
3995,
4003,
4246,
4771,
4855,
2927,
2944,
3106,
3156,
3157,
3226,
3309,
3418,
3971,
4011,
4242,
4266,
4510,
4733,
4776,
4880,
4895,
4973,
5269,
5456
];
	$msg = "";
	foreach ($members as $user_id) {
	    $upod = pods('user', $user_id);
	    $dual_members = $upod->field('dual_member');
		$enddate = "0";
		$user = false;
		$dual_member_uid = 0;;
		$dual_enddate = 0;
        if (!empty($dual_members)) {
		    $dual_member_uid = $dual_members['ID'];
	        $query = "SELECT enddate FROM {$wpdb->pmpro_memberships_users} WHERE user_id = " . $dual_member_uid . " AND status = 'active'";
            $results = $wpdb->get_results($query);
			if ($results) {
                $user = get_userdata($user_id);
				$dual_enddate = $results[0]->enddate;
		    }
		}
		$msg .= $dual_member_uid . "," . $dual_enddate . ",";
		if ($user)
			 $msg .= $user->user_email;
	    $msg .= "<br>";
	}
	return $msg;
}
function bk_expire_dual_member($user_id)
{
	global $wpdb;
	$upod = pods('user', $user_id);
	$dual_members = $upod->field('dual_member');
    if (!empty($dual_members)) {
	    $dual_member_uid = $dual_members['ID'];
		if ($dual_member_uid > 0) {
            $user = get_userdata($dual_member_uid);
		    if ($user) {
                $user->remove_role('active');
				$query = "SELECT id FROM {$wpdb->pmpro_memberships_users} WHERE user_id = " . $dual_member_uid . " AND status = 'active'";
        		$results = $wpdb->get_results($query);
	    		if ($results) {
		    		$uid = $results[0]->id;
		            $sqlQuery = "UPDATE {$wpdb->pmpro_memberships_users} SET enddate = current_date(), status = 'inactive', membership_id = 0 WHERE id = " . $uid;
				}
			}
		}
    }
}
// add_action('pmpro_after_checkout', 'bike_after_checkout', 10, 1);
function bike_after_checkout($user_id)
{
	global $wpdb;
	$level = pmpro_getMembershipLevelForUser($user_id);
	if (pmpro_hasMembershipLevel(array('4','5','6'), $user_id ) ) {
	    $upod = pods('user', $user_id);
	    $dual_members = $upod->field('dual_member');
        if (!empty($dual_members)) {
		    $dual_member_uid = $dual_members['ID'];
	        $query = "SELECT enddate, membership_id FROM {$wpdb->pmpro_memberships_users} WHERE user_id = " . $user_id . " AND status = 'active'";
            $results = $wpdb->get_results($query);
	        if ($results)
			    $result = $results[0];
			$membership_level = pmpro_getMembershipLevelForUser($user_id);
			// pmpro_changeMembershipLevel($membership_level, $dual_member_uid, 'active');
	        $query = "SELECT id FROM {$wpdb->pmpro_memberships_users} WHERE user_id = " . $dual_member_uid . " AND status = 'active'";
            $results = $wpdb->get_results($query);
	        if ($results)
			    $uid = $results[0]->id;
			else {
	            $query = "SELECT id FROM {$wpdb->pmpro_memberships_users} WHERE user_id = " . $dual_member_uid . " AND status = 'expired'";
                $results = $wpdb->get_results($query);
	            if ($results)
				    $uid = $results[0]->id;
			    else
				    return;
			}
		    $sqlQuery = "UPDATE {$wpdb->pmpro_memberships_users} SET enddate = '" . $result->enddate . "', billing_amount = 0, status = 'active', membership_id = " . $result->membership_id . " WHERE id = " . $uid;
			$wpdb->query($sqlQuery);
	        $dm_upod = pods('user', $dual_member_uid);
			$dm_upod->save('dual_member', $user_id);
		}
	}
}
add_action('pmpro_after_change_membership_level', 'bike_change_membership_level', 10, 3);
function bike_change_membership_level($level_id, $user_id, $old_level)
{
    $user = get_userdata($user_id);
	if (!$user || $user_id == 1)
	    return;
	// if (!empty($old_level) &&  $old_level > 3 && (empty($level_id) || false === $level_id || $level_id < 3))
	    // bk_expire_dual_member($user_id);
    if (false !== $level_id && $level_id > 0) {
	    $user->add_role('active');
        $isrl = xprofile_get_field_data(115, $user->ID, 'comma');
		if (!empty($isrl) && strpos($isrl,  "I am a Ride Leader") !== false )
	        $user->add_role('rideleader');
		// if ($level_id > 3)
            // bike_after_checkout($user_id);
    }
    else {
        $user->remove_role('active');
        $user->remove_role('librarian');
        $user->remove_role('membercoordinator');
        $user->remove_role('newslettereditor');
        $user->remove_role('ride_coordinator_backup');
        $user->remove_role('ride_planner');
        $user->remove_role('ridecoordinator');
        $user->remove_role('rpc');
        $user->remove_role('safetycoordinator');
        $user->remove_role('treasurer');
        $user->remove_role('president');
        $user->remove_role('president_elect');
        remove_action('remove_user_role', 'bike_remove_user_role', 10);
        $user->remove_role('rideleader');
        add_action('remove_user_role', 'bike_remove_user_role', 10, 2);
    }
    bk_clear_cache();
}

/**
 * Start Buffering the members directory content.
 */
function bk_start_members_dir_buffering() {
    ob_start();
}

add_action( 'bp_before_directory_members', 'bk_start_members_dir_buffering', - 1 );

/**
 * Discard the members directory content.
 */
function bk_end_members_dir_buffering() {
    ob_end_clean(); /// discard.
}

add_action( 'bp_after_directory_members', 'bk_end_members_dir_buffering', 100001 );

function bk_hide_profile_edit( $retval ) {	
    $retval['exclude_fields'] = '20';
	if (bp_is_user_profile_edit())
        $retval['exclude_fields'] = '115';
	// if (bp_is_profile_edit())
        // $retval['exclude_fields'] = '115';
	return $retval;	
}
add_filter( 'bp_after_has_profile_parse_args', 'bk_hide_profile_edit' );

add_action('admin_bar_menu', 'bk_add_toolbar_items', 100);
function bk_add_toolbar_items($admin_bar){
    $title = ( bk_days_until_membership_expires() <= 7 ) ? "Renew" : "My Account";
    $admin_bar->add_menu( array(
		'parent'    => 'top-secondary',
        'id'    => 'membership-account-item',
        'title' => $title,
        'href'  => get_site_url() . '/membership-account',
        'meta'  => array(
            'title' => __('My Account'),            
        ),
    ));
    $admin_bar->add_menu( array(
		'parent'    => 'user-actions',
        'id'    => 'root-membership-account-item',
        'title' => 'My Account',
        'href'  => get_site_url() . '/membership-account',
        'meta'  => array(
            'title' => __('My Account'),            
        ),
    ));
    $admin_bar->add_menu( array(
		'parent'    => 'user-actions',
        'id'    => 'help-item',
        'title' => 'Help',
        'href'  => '/website-user-guides',
        'meta'  => array(
            'title' => __('Help'),            
        ),
    ));
}
// function bk_set_link_website ( $link, $postId ) {
    // if (tribe_event_in_category( "other-organization-events", $postId)) {
	    // $website_url = tribe_get_event_website_url( $postId );
	    // // Only swaps link if set
	    // if ( !empty( $website_url ) ) {
		    // $link = $website_url;
	    // }
    // // }
	// return $link;
// }
// add_filter( 'tribe_get_event_link', 'bk_set_link_website', 100, 2 );

// add_filter('tribe_get_event_website_link_target', 'bk_set_website_link_target', 100, 0);
// function bk_set_website_link_target()
// {
    // return '_blank';
// }

add_filter( 'gform_shortcode_form', 'bk_gform_shortcode_form', 10, 2 );
function bk_gform_shortcode_form($shortcode_string, $attributes)
{
    if ($attributes['id'] == 7 &&
          !empty($_GET['riderole']) && $_GET['riderole'] == 2 &&
          !(current_user_can('rc_cap') || current_user_can('ridecoordinator')
            || current_user_can('manage_options'))) {
        wp_redirect(home_url());
        die();
    }
    return $shortcode_string;
}
if(!class_exists('RemoveEnviraGalleryPluginNotice')) :

class RemoveEnviraGalleryPluginNotice {
    public function __construct() {
        add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
    }
    public function plugins_loaded() {
        if ( !get_transient('eg_n_warning-invalid-license-key' ) ) {
            set_transient('eg_n_warning-invalid-license-key', time(), 365 * DAY_IN_SECONDS);
        }
    }
}
$GLOBALS['wc_removeenviragalleryluginnotice'] = new RemoveEnviraGalleryPluginNotice();
endif;

add_shortcode('club-member-addresses', 'bk_club_member_addresses');
function bk_club_member_addresses()
{
	$ret = "";
    if(!current_user_can('manage_options') && !current_user_can('treasurer'))
	    return $ret;
	
      $ret = '<table class="display club-members-table" style="width:100%">
        <thead>
            <tr>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Address 1</th>
                <th>Address 2</th>
                <th>City</th>
                <th>State</th>
                <th>Zip</th>
            </tr>
        </thead>
        <tbody>';
    $users      = get_users( array( 'fields' => array('ID' ), 'role' => 'active' ) );
    foreach ($users as $user) {
	    $first_name = get_user_meta($user->ID, 'first_name', true);
	    $last_name = get_user_meta($user->ID, 'last_name', true);
        $address1 = xprofile_get_field_data( 98, $user->ID );
        $address2 = xprofile_get_field_data( 99, $user->ID );
        $city = xprofile_get_field_data( 100, $user->ID );
        $state = xprofile_get_field_data( 101, $user->ID );
        $zip = xprofile_get_field_data( 102, $user->ID );
        $ret .= '<tr>';
        $ret .= '<td>' . $first_name . '</td>';
        $ret .= '<td>' . $last_name . '</td>';
        $ret .= '<td>' . $address1 . '</td>';
        $ret .= '<td>' . $address2 . '</td>';
        $ret .= '<td>' . $city . '</td>';
        $ret .= '<td>' . $state . '</td>';
        $ret .= '<td>' . $zip . '</td>';
        $ret .= '</tr>';
    }
    $ret .= '</tbody></table>';
    return $ret;
}
add_shortcode('club-member-report', 'bk_club_member_report');
function bk_club_member_report()
{
	$ret = "";
	if (!function_exists('pmpro_getMembershipLevelForUser'))
	    return $ret;
    if(!current_user_can('manage_options') && !current_user_can('safetycoordinator') && !current_user_can('president') && !current_user_can('president_elect') && !current_user_can('ridecoordinator') && !current_user_can('membercoordinator') && !current_user_can('treasurer'))
	    return $ret;
	
      $ret = '<table class="display club-members-table" style="width:100%">
        <thead>
            <tr>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Address 1</th>
                <th>Address 2</th>
                <th>City</th>
                <th>State</th>
                <th>Zip</th>
				<th>Email</th>
				<th>Gender</th>
				<th>date_birth</th>
				<th>date_start</th>
				<th>date_expires</th>
				<th>dues_paid</th>
				<th>date_joined</th>
				<th>home_phone</th>
				<th>work_phone</th>
				<th>fax_phone</th>
				<th>cell_phone</th>
				<th>emergency_phone</th>
				<th>second emergency_phone</th>
				<th>car_license_plate</th>
				<th>car2_license_plate</th>
				<th>ride_leader</th>
				<th>rider_pace</th>
				<th>profession</th>
				<th>social_activities</th>
            </tr>
        </thead>
        <tbody>';
    $users      = get_users( array( 'fields' => array('ID' ), 'role' => 'active' ) );
    foreach ($users as $user) {
	    $first_name = get_user_meta($user->ID, 'first_name', true);
	    $last_name = get_user_meta($user->ID, 'last_name', true);
        $address1 = xprofile_get_field_data( 98, $user->ID );
        $address2 = xprofile_get_field_data( 99, $user->ID );
        $city = xprofile_get_field_data( 100, $user->ID );
        $state = xprofile_get_field_data( 101, $user->ID );
        $zip = xprofile_get_field_data( 102, $user->ID );
		$user_info = get_userdata($user->ID);
        $email = $user_info->user_email;
		$gender = xprofile_get_field_data(2, $user->ID );
		$date_birth = xprofile_get_field_data(9, $user->ID );
		$home_phone = xprofile_get_field_data(103, $user->ID );
		$work_phone = xprofile_get_field_data(104, $user->ID );
		$cell_phone = xprofile_get_field_data(105, $user->ID );
		$fax_phone = xprofile_get_field_data(106, $user->ID );
		$emergency_phone = xprofile_get_field_data(107, $user->ID );
		$secondary_emergency = xprofile_get_field_data(356, $user->ID );
		$vehicle_license = xprofile_get_field_data(113, $user->ID );
		$vehicle2_license = xprofile_get_field_data(114, $user->ID );
		if (empty($vehicle2_license))
		    $vehicle2_license = "";
		$ride_leader = xprofile_get_field_data(115, $user->ID );
		if (empty($ride_leader))
		    $ride_leader = "No";
		else
		    $ride_leader = "Yes";
		$date_registered = $user_info->user_registered;
		$userpace = xprofile_get_field_data("I normally ride at", $user->ID, 'array');
        if (empty($userpace) )
            $userpace = "";
		$profession = xprofile_get_field_data(315, $user->ID );
		if (empty($profession))
			$profession = "";
		$social = xprofile_get_field_data(173, $user->ID );
		$social = empty($social) ? "" : "Yes";
		if (!empty($date_registered)) {
			$date_registered = preg_replace("!([^ ]*) .*!", "$1", $date_registered);
	        $tz = new DateTimeZone(wp_timezone_string());
            $dt = DateTime::createFromFormat("Y-m-d", $date_registered, $tz);
		    $date_joined = $dt->format('Y-m-d');
		}
		else {
		    $date_joined = "";
		}
		$level = pmpro_getMembershipLevelForUser($user->ID);
		$startdate = "";
		$enddate = "";
		$initial_payment = 0;
		if (!empty($level)) {
		    if (!empty($level->startdate) && $level->startdate > 0)
			    $startdate = date('Y-m-d', $level->startdate);
		    if (!empty($level->enddate) && $level->enddate > 0)
			    $enddate = date('Y-m-d', $level->enddate);
		    if (!empty($level->initial_payment))
			    $initial_payment = $level->initial_payment;
		}
        $ret .= '<tr>';
        $ret .= '<td>' . $first_name . '</td>';
        $ret .= '<td>' . $last_name . '</td>';
        $ret .= '<td>' . $address1 . '</td>';
        $ret .= '<td>' . $address2 . '</td>';
        $ret .= '<td>' . $city . '</td>';
        $ret .= '<td>' . $state . '</td>';
        $ret .= '<td>' . $zip . '</td>';
        $ret .= '<td>' . $email . '</td>';
        $ret .= '<td>' . $gender . '</td>';
        $ret .= '<td>' . $date_birth . '</td>';
        $ret .= '<td>' . $startdate . '</td>';
        $ret .= '<td>' . $enddate . '</td>';
        $ret .= '<td>' . $initial_payment . '</td>';
        $ret .= '<td>' . $date_joined . '</td>';
        $ret .= '<td>' . $home_phone . '</td>';
        $ret .= '<td>' . $work_phone . '</td>';
        $ret .= '<td>' . $cell_phone . '</td>';
        $ret .= '<td>' . $fax_phone . '</td>';
        $ret .= '<td>' . $emergency_phone . '</td>';
        $ret .= '<td>' . $secondary_emergency . '</td>';
        $ret .= '<td>' . $vehicle_license . '</td>';
        $ret .= '<td>' . $vehicle2_license . '</td>';
        $ret .= '<td>' . $ride_leader . '</td>';
        $ret .= '<td>' . $userpace . '</td>';
        $ret .= '<td>' . $profession . '</td>';
        $ret .= '<td>' . $social . '</td>';
        $ret .= '</tr>';
    }
    $ret .= '</tbody></table>';
    return $ret;
}

add_shortcode('member_ride_report', 'bk_member_ride_report');
function bk_member_ride_report()
{
	global $wp_query;
    $ret = '<table class="display member_ride_table" style="width:100%"> <thead>
        <tr>
	        <th>Rider</th>
	        <th>Email</th>
			<th>Total Rides</th>
			<th>Total Miles</th>
	        <th>Total Climb</th>
        </tr>
        </thead>
        </tbody>';

    $daterange = bk_get_start_and_end_date(true);
	$args = array(
		'post_type' => 'ride',
        'post_status' => 'publish',
		'no_found_rows' => true,
        'posts_per_page' => -1,
		'meta_query' => array(
			'relation' => 'AND',
            array(
                'relation' => 'OR',
			    array (
			        'key' => 'ride-status',
			        'value' => 2,
			    ),
			    array (
			        'key' => 'ride-status',
			        'value' => 4,
			    ),
            ),
			array (
				'key' => 'ride_date',
				'meta_type' => 'date',
				'value' => $daterange['start_date'],
				'compare' => '>=',
			),
			array (
				'key' => 'ride_date',
				'meta_type' => 'date',
				'value' => $daterange['end_date'],
				'compare' => '<',
			),
		)
	);
    $totalmileage = [];
    $totalclimb = [];
	$totalrides = [];
    $users      = get_users( array( 'fields' => array('ID' ), 'role' => 'active' ) );
    foreach ($users as $user) {
		$totalmileage[$user->ID] = 0;
		$totalclimb[$user->ID] = 0;
		$totalrides[$user->ID] = 0;
	}
    $params = array(
        'limit' => -1,
        'where' => "(`ride-status`.meta_value = 2 OR `ride-status`.meta_value = 4) AND CAST(ride_date.meta_value AS date) >= '" . $daterange['start_date'] . "' AND CAST(ride_date.meta_value AS date) < '" . $daterange['end_date'] . "'",
    );
    $pod = pods('ride', $params);
    if ($pod->total() > 0) {
        while ($pod->fetch()) {
            $post_id = $pod->id();
            $ridestatus = $pod->field('ride-status');
            $tourfield = $pod->field('tour');
            if ($tourfield) {
                $tourid = $tourfield['ID'];
                $tpod = pods('tour', $tourid);
                $miles = intval($tpod->field('miles'));
                $climb = intval($tpod->field('climb'));
			}
		    if ($ridestatus == 4 && $miles > 0)  {
	            $riders = bk_get_signup_id_list($pod);
				foreach ($riders as $riderId) {
					if (array_key_exists($riderId, $totalmileage)) {
			           	$totalmileage[$riderId] += $miles;
			           	$totalclimb[$riderId] += $climb;
						$totalrides[$riderId]++;
					}
					// else {
			           	// $totalmileage[$riderId] = $miles;
			           	// $totalclimb[$riderId] = $climb;
						// $totalrides[$riderId] = 1;
					// }
				}
			}
        }
        if (!empty($totalmileage)) {
            krsort($totalmileage, 1);
            foreach ($totalmileage as $key => $value) {
                $user_info = get_userdata($key);
	            $riderlink = '<a href="' . get_site_url() . '/members/' . $user_info->user_nicename . '/profile/">' . $user_info->display_name . '</a>';
                $ret .= '<tr>';
                $ret .= '<td>' . $riderlink . '</td>';
                $ret .= '<td>' . $user_info->user_email . '</td>';
                $ret .= '<td>' . $totalrides[$key] . '</td>';
	            $ret .= '<td>' . $totalmileage[$key] . '</td>';
	            $ret .= '<td>' . $totalclimb[$key] . '</td>';
                $ret .= '</tr>';
            }
        }
    }
    $ret .= '</tbody></table>';
    return $ret;
}
add_shortcode('proposed-ride-signup-report', 'bk_proposed_ride_signup_report');
function bk_proposed_ride_signup_report()
{
    global $post;
    $ret = '<table class="display proposed-ride-signup-table" style="width:100%">
        <thead>
        <tr>
	        <th>Leader</th>
	        <th>Create Date</th>
	        <th>Ride Date</th>
	        <th>Ride Link</th>
        </tr>
        </thead>
		<tbody>';

    $params = array( 'limit' => -1 );
    $pod = pods('chosen_proposed_ride', $params);
    if ($pod->total() > 0) {
        while ($pod->fetch()) {
            $post_id = $pod->id();
			$author_pid = $pod->field('submittor');
			$rlid = $pod->field('ride_leader');
			$rideid = $pod->field('ride');
            if (is_array($author_pid)) $author_pid = $author_pid['ID'];
            if (is_array($rlid)) $rlid = $rlid['ID'];
            if (is_array($rideid)) $rideid = $rideid['ID'];
            $user_info = get_userdata($author_pid);
		    $authorname = $user_info->display_name;
            $user_info = get_userdata($rlid);
		    $rlname = $user_info->display_name;
			$rpod = pods('ride', $rideid);
			$postdate = $rpod->display("post_date");
			$ridedate = $rpod->field("ride_date");
	        $tz = new DateTimeZone(wp_timezone_string());
	        $ridestart = new DateTime($ridedate, $tz);
            $url = get_site_url() . '/ride/' . $rideid . '/';
	        $ridelink = '<a href="' . $url . '/">' . $url . '</a>';
			if ($rpod->field('ride-status') == 4) {
            	$ret .= '<tr>';
            	$ret .= '<td>' . $rlname . '</td>';
            	$ret .= '<td>' . $postdate . '</td>';
            	$ret .= '<td>' . $ridedate . '</td>';
            	$ret .= '<td>' . $ridelink . '</td>';
	        	$ret .= '</tr>';
			}
        }
    }
    $ret .= '</tbody></table>';
    return $ret;
}
add_shortcode('ride_leader_report', 'bk_ride_leader_report');
function bk_ride_leader_report()
{
    $ret = '<table class="display leader_table" style="width:100%">
        <thead>
        <tr>
	        <th>Leader</th>
	        <th>Led Rides</th>
	        <th>Canceled Rides</th>
			<th>Total Miles</th>
			<th>Total Riders</th>
	        <th>Email</th>
        </tr>
        </thead>
        </tbody>';

    $daterange = bk_get_start_and_end_date(true);
    $mileage = [];
	$ridercnt = [];
    $params = array(
        'limit' => -1,
        'where' => "(`ride-status`.meta_value = 2 OR `ride-status`.meta_value = 4) AND CAST(ride_date.meta_value AS date) >= '" . $daterange['start_date'] . "' AND CAST(ride_date.meta_value AS date) < '" . $daterange['end_date'] . "'",
    );
    $pod = pods('ride', $params);
	// error_log(print_r($query, true));
    if ($pod->total() > 0) {
        while ($pod->fetch()) {
            $post_id = $pod->id();
            $leaderId = $pod->field('ride_leader');
	        $post_author = get_post_field('post_author', $post_id);
			$ridercount = 0;
            if (is_array($leaderId))
                $leaderId = $leaderId['ID'];
            if (!empty($leaderId) && $leaderId != 0) {
                $ridestatus = $pod->field('ride-status');
                $ridercount = intval($pod->field('rider_count'));
    			$tourfield = $pod->field('tour');
				// if ($leaderId != $post_author && $ridestatus == 4) {
    				// $user_info = get_userdata($post_author);
					// error_log("rideid:" . $post_id . " author:" . $user_info->user_nicename);
				// }
				if ($tourfield) {
    			    $tourid = $tourfield['ID'];
    			    $tpod = pods('tour', $tourid);
					$miles = intval($tpod->field('miles'));
				}
				else
					$miles = 0;
                // $datestr = $pod->display('ride_date') . ' ' . $pod->display('time');
                // $tz = new DateTimeZone(wp_timezone_string());
                // $start_time = DateTime::createFromFormat("m/d/Y h:i a", $datestr, $tz);
				// if ($ridestatus != 2 || ride_leadercheck($start_time, $leaderId) == 0) {
	                if (!isset($table[$leaderId])) {
		                $table[$leaderId] = array();
		                $table[$leaderId][2] = 0;
		                $table[$leaderId][4] = 0;
						$mileage[$leaderId] = 0;
						$ridercnt[$leaderId] = 0;
	                }
	                $table[$leaderId][$ridestatus]++;
					if ($ridestatus == 4) {
					    $mileage[$leaderId] += $miles;
						$ridercnt[$leaderId] += $ridercount;
					}
				// }
            }
        }
        if (!empty($table)) {
            krsort($table, 1);
            foreach ($table as $key => $value) {
                $user_info = get_userdata($key);
                if ($user_info->display_name != "Needs Leader") {
	                $leaderlink = '<a href="' . get_site_url() . '/members/' . $user_info->user_nicename . '/profile/">' . $user_info->display_name . '</a>';
                    $ret .= '<tr>';
                    $ret .= '<td>' . $leaderlink . '</td>';
	                $ret .= '<td>' . $value[4] . '</td>';
	                $ret .= '<td>' . $value[2] . '</td>';
	                $ret .= '<td>' . $mileage[$key] . '</td>';
	                $ret .= '<td>' . $ridercnt[$key] . '</td>';
	                $ret .= '<td>' . $user_info->user_email . '</td>';
	                $ret .= '</tr>';
					// if ( ! user_can( $key, "ride_leader" ) ) {
                         // wp_mail("joneiseman@gmail.com", "missing rl role", $user_info->user_nicename);
					// }
                }
            }
        }
    }
    $ret .= '</tbody></table>';
    return $ret;
}
function bk_bottom_admin_bar()
{
    echo '<style>
        div#wpadminbar {
            top: auto;
            bottom: 0;
            position: fixed;
        }
        .ab-sub-wrapper {
            bottom: 32px;
        }
        html[lang] {
            margin-top: 0 !important;
            margin-bottom: 32px !important;
        }
        @media screen and (max-width: 782px){
            .ab-sub-wrapper {
                bottom: 46px;
            }
            html[lang] {
                margin-bottom: 46px !important;
            }
        }
    </style>';
}
function bk_check_admin()
{
    if(!is_admin() && current_user_can('active'))
        add_action('wp_head', 'bk_bottom_admin_bar', 100);
}
// add_action('init', 'bk_check_admin');

add_filter( 'bbp_bypass_check_for_moderation', '__return_true' );
/**
 * Modify/change the default allowed tags for bbPress.
 *
 * The default list (below) is in bbpress/includes/common/formatting.php, L24-66.
 * Adjust below as needed. This should go in your theme's functions.php file (or equivilant).
 */
function bk_filter_bbpress_allowed_tags() {
	return array(

		// Links
		'a' => array(
			'href'     => array(),
			'title'    => array(),
			'rel'      => array()
		),

		// Quotes
		'blockquote'   => array(
			'cite'     => array()
		),

		// Code
		'code'         => array(),
		'pre'          => array(),

                // paragraphs
		'p'           => array( 'style' => array() ),
		'span'           => array( 'style' => array() ),

		// Formatting
		'br'           => array(),
		'em'           => array(),
		'strong'       => array(),
		'del'          => array(
			'datetime' => true,
		),

		// Lists
		'ul'           => array(),
		'ol'           => array(
			'start'    => true,
		),
		'li'           => array(),

		// Images
		'img'          => array(
			'src'      => true,
			'border'   => true,
			'alt'      => true,
			'height'   => true,
			'width'    => true,
		)
	);
}
add_filter( 'bbp_kses_allowed_tags', 'bk_filter_bbpress_allowed_tags' );

add_filter('user_has_cap', 'bk_user_has_cap', 100, 3);

function bk_user_has_cap($allcaps, $cap, $args)
{
    if ( !empty($cap[0]) && 'edit_user' == $args[0] && current_user_can('edit_users'))
        $allcaps[$cap[0]] = true;
    return $allcaps;
}

function bk_add_custom_field() {
    if (current_user_can('active')) {
		echo '<div class="bp-widget ' . bp_the_profile_group_slug() . '"><table class="profile-fields"><tbody>
			<tr class="my_mail">

				<td class="label">Contact me</td>

				<td class="data"><a href="mailto:' . bp_displayed_user_email() . '">' . bp_displayed_user_email() . '</a></td>

			</tr>
		</tbody></table>
	</div>';
    }
}

add_action('template_redirect', 'bk_redir_one_week_ride_schedule');
function bk_redir_one_week_ride_schedule()
{
    global $post;
    if (!empty($post) && $post->post_name == "1weekrideschedule") {
	$tz = new DateTimeZone(wp_timezone_string());
	$date_obj = new DateTime("now", $tz);
        $date = $date_obj->format('m/d/Y');
	$enddate_obj = new DateTime("now + 3 weeks", $tz);
        $end_date = $enddate_obj->format('m/d/Y');
        $url = '/ride-list/?start_date=' . $date . '&end_date=' . $end_date;
        wp_redirect($url);
    }
}

// add_action('save_post', 'bk_post_published', 5, 1);
function bk_post_published($post_id)
{
    global $wpdb;
	$key = "_members_access_role";
	$post_type = get_post_type($post_id);
	$roles = get_post_meta($post_id, $key);
	// error_log("id:" . $post_id . " roles:" . print_r($roles, true));
	if (empty($roles) && ($post_type == "post" || $post_type == "page")) {
		$ret = update_post_meta($post_id, $key, 'active');
		// error_log("retval:" . print_r($ret, true));
	}
}

add_shortcode('my_event_list', 'event_list_shortcode');
function event_list_shortcode()
{
    $datetime = new DateTime('tomorrow');
    $start = $datetime->format('Y-m-d');
    $datetime->modify('+1 year');
    $end = $datetime->format('Y-m-d');
    return do_shortcode('[events_list scope="' . $start . ',' . $end . '"]');
}

add_shortcode("bike-display-newsletter", "bk_display_newsletter");
function bk_display_newsletter()
{
    return do_shortcode('[pdf-embedder url="' . get_site_url() . '/wp-content/' . bk_latest_newsletter_path() . '" title="Current.nl"]');
}
function bk_latest_newsletter_path()
{
    $mydir = ABSPATH . "/wp-content/uploads/simple-file-list/";
    $pattern = $mydir . '/*.pdf';
    $files = glob($pattern);
    usort($files, function($a, $b) {
        return filemtime($a) < filemtime($b);
    });
    $newest_file = basename($files[0]);
    return "/uploads/simple-file-list/" . $newest_file;
}
// add_shortcode("bk-calendar-header", 'bk_cal_header');
function bk_cal_header()
{
    $url = $_SERVER['REQUEST_URI'];
    if (str_contains($url, "club-events")) {
        return do_shortcode('[elementor-template id="195899"]');
    }
    else if (str_contains($url, "bike-adventures")) {
        return do_shortcode('[elementor-template id="195902"]');
    }
    else if (str_contains($url, "other-organization-events") && current_user_can("active")) {
        return do_shortcode('[elementor-template id="195905"]');
    }
    else
        return "";
}

function bk_sort_wait_list(&$attendees)
{
    uasort($attendees, function ($a, $b) {
		if (!array_key_exists('wait_list_number', $a))
		    $a['wait_list_number'] = 0;
		if (!array_key_exists('wait_list_number', $b))
		    $b['wait_list_number'] = 0;
        return $a['wait_list_number'] - $b['wait_list_number'];
    });
}


add_shortcode("bike-display-nl-archive", "bk_display_nl_archive");
function bk_display_nl_archive()
{
	$ret = "";
    if (current_user_can("newslettereditor") || current_user_can("manage_options")) {
	    // $ret = do_shortcode('[file_manager_advanced login="yes" roles="newslettereditor,administrator" path="wp-content/uploads/simple-file-list" operations="upload,download,rm" view="list" theme="light" lang ="en" hide="index.html"]');
	    $ret = do_shortcode('[wp_file_manager id="c05a6e690ed5dea4a0b542f646b1a5c1" title="Newsletter Archive Updates"]');
	}
	else if (current_user_can("active")) {
	    // $ret = do_shortcode('[file_manager_advanced login="yes" roles="active" path="wp-content/uploads/simple-file-list" operations="download" view="list" theme="light" lang ="en" hide="index.html" ]');
	    $ret = do_shortcode('[wp_file_manager id="675b84155b54c9d3ff12b3fe4dfd2059" title="Newsletter Archive"]');
	}
	return $ret;
}
/**
 * Redirect members on login to a specific page based on their level.
 *
 * You can add this recipe to your site by creating a custom plugin
 * or using the Code Snippets plugin available for free in the WordPress repository.
 * Read this companion article for step-by-step directions on either method.
 * https://www.paidmembershipspro.com/create-a-plugin-for-pmpro-customizations/
 *
 */
function bk_login_redirect_per_membership_level( $redirect_to, $request, $user ) {
	if ( ! empty( $user->ID ) && function_exists('pmpro_getMembershipLevelForUser') ) {
		$level = pmpro_getMembershipLevelForUser($user->ID);
	    if (!pmpro_hasMembershipLevel(array('1','2','3','4','5','6','7','8','9'), $user->ID ) ) {
		    return home_url( '/membership-account/membership-levels/');
	    }
	}
	return $redirect_to;
}
add_filter( 'login_redirect', 'bk_login_redirect_per_membership_level', 10, 3 );

add_filter('bbp_get_do_not_reply_address','bk_bbp_no_reply_email');
function bk_bbp_no_reply_email(){
    $email = 'no_reply@mafw.org';
    return $email;
}
add_filter( 'bp_email_set_reply_to', 'bk_bp_email_set_reply_to', 10, 2);

function bk_bp_email_set_reply_to( $retval, $email_address ) {
    if ( bp_get_option( 'admin_email' ) === $email_address ) {
	$retval = new BP_Email_Recipient( 'no_reply@mafw.org' );
    }
    return $retval;
}

/* Only allow active members to access the bp activity page */
function bk_nonreg_visitor_redirect() {
    global $bp;
    if ( bp_is_activity_component() || bp_is_groups_component() || bp_is_page( BP_MEMBERS_SLUG ) ) {
        if (!is_user_logged_in() || !current_user_can('active')) {
			// redirect to login to access the page
            wp_redirect( get_option('siteurl') . '/wp-login.php' );
        }
	}
}

// add_filter('get_header','bk_nonreg_visitor_redirect',1);
function bk_render_block($block_content, $block)
{
    if ($block['blockName'] === 'core/file' && str_contains($block_content, 'Newsletter')) {
         $patterns = '!uploads/[0-9][0-9][0-9][0-9]/[0-9][0-9]/.*\.pdf!';
         $block_content = preg_replace($patterns, bk_latest_newsletter_path(), $block_content);
    }
    return $block_content;
}
// add_filter('render_block', 'bk_render_block', 10, 2);

add_filter( 'pmpro_member_action_links', 'bk_filter_pmpro_member_action_links' );
function bk_filter_pmpro_member_action_links($links)
{
    if (array_key_exists('cancel', $links)) {
	    unset($links['cancel']);
	}
	return $links;
}
function bk_pmpro_membership_card_member_links_top()
{
    ?>
        <li><a href="<?php echo get_site_url(); ?>/membership-card/membership-card-2019/" target="_blank"><?php _e("View and Print Membership Card", "pmpro"); ?></a></li>
    <?php
}
add_action("pmpro_member_links_top", "bk_pmpro_membership_card_member_links_top");
function bk_check_leaders()
{
	$ret = "";
	$user_query = new WP_User_Query(array('role' => 'rideleader'));
    $users = $user_query->get_results();
    if (!empty($users))
      foreach($users as $user) {
	        $ret .= $user->display_name . "<br />";
        // $isrl = xprofile_get_field_data("Ride Leader", $user->ID, 'comma');
		// if (!empty($isrl)) {
	        // $ret .= $user->display_name . ":" . print_r($isrl, true) . "<br />";
	        // xprofile_set_field_data(115, $user->ID, "");
	    // }
		// else {
		    // $ret .= $user->display_name . ":" . print_r($isrl, true) . "<br />";
		// }
      }
    return $ret;
}

// add_action('plugins_loaded', 'my_remove_dcfe_action', 11); 
// function my_remove_dcfe_action() {
    // remove_action('admin_notices', 'dynamic_content_for_elementor_promo');
// }

add_shortcode('bk-check-leaders', 'bk_check_leaders');
// add_action('bp_before_member_header_meta', 'bk_add_custom_field');
add_action('template_redirect', 'remove_gp_bbpress_css');
function remove_gp_bbpress_css()
{
    remove_action( 'wp_enqueue_scripts', 'generate_bbpress_css');
}

add_action('bp_after_group_home_content', 'bk_after_group_home_content');
function bk_after_group_home_content()
{
     ?><a href="https://www.lifewire.com/what-is-an-rss-feed-4684568" target="_blank">About RSS feeds</a><?php
}

function bk_clear_cache()
{
	global $wpdb;
    if (!is_user_logged_in() || !current_user_can('active'))
        return;
    $wpdb->query( "DELETE FROM `$wpdb->options` WHERE `option_name` LIKE ('_transient_bk_ride_table_text__%')" );
}
add_action('generate_inside_comments', function() {
	$uri = $_SERVER['REQUEST_URI'];
    $ridenum = intval(preg_replace("!/ride/([0-9]*)/!", "$1", $uri));
	if (!empty($ridenum) && $ridenum > 0) {
	    $post = get_post($ridenum);
	    $GLOBALS['post'] = $post;
	    $args = array( 'post_id' => $ridenum);
	    global $wp_query;
	    $comments = get_comments($args);
		if (!empty($comments)) {
	        $wp_query->comments = $comments;
		    $wp_query->comment_count = count($comments);
		}
	}
});
function bk_deactivate() {
    wp_clear_scheduled_hook('bk_cron');
}
function bk_do_midnight_tasks()
{
	tour_counts();
    bk_clear_cache();
}
add_action('init', function() {
     add_action('bk_cron', 'bk_do_midnight_tasks');
	 register_deactivation_hook( __FILE__, 'bk_deactivate');
	 if (! wp_next_scheduled('bk_cron')) {
	     wp_schedule_event( strtotime('today midnight'), 'daily', 'bk_cron');
	 }
});

function bk_deactivate_monthly() {
    wp_clear_scheduled_hook('bk_cron_monthly');
}
function bk_do_monthly_tasks()
{
    global $wpdb;
    $sql = 'UPDATE ' . $wpdb->prefix . 'comments SET comment_approved = 0 WHERE comment_post_ID = 11259 AND comment_date <= (NOW() - INTERVAL 60 DAY)';
    $wpdb->query($sql);
}
add_action('init', function() {
     add_action('bk_cron_monthly', 'bk_do_monthly_tasks');
	 register_deactivation_hook( __FILE__, 'bk_deactivate_monthly');
	 if (! wp_next_scheduled('bk_cron_monthly')) {
	     wp_schedule_event( time(), 'monthly', 'bk_cron_monthly');
	 }
});

add_action('admin_init', 'add_roles_to_membership_manager', 22);
function add_roles_to_membership_manager() {
    $manager = get_role('pmpro_membership_manager');
    if ($manager){
        $manager->add_cap('pmpro_emailtemplates');
    }
}

add_shortcode('bk-nl-link', 'bk_nl_link_func');
function bk_nl_link_func()
{
    $params = array(
        'orderby' => 't.post_date DESC',
        'limit' => 1,
        'tax_query' => array(
         array(
            'taxonomy' => 'category', 
            'field'    => 'id',
            'terms'    => 342,
        ),
       ),
    );
    $pod = pods('post', $params);
    if ($pod->fetch()) {
         $post_id = $pod->id();
    	 $ret = '<a href="' . get_permalink($post_id) . '">';
         $str = get_transient('bk_nl_announcement_subject');

         $str = "Click here to see the " . $str . " Announcement";
    	 $ret .= $str . '</a>';
         return $ret;
    }
    return "";
}
add_shortcode('bk-edit-ride', 'bk_edit_ride');
function bk_edit_ride()
{
    $rideid = get_query_var('rideid', null);
    $ridepod = pods( 'ride', $rideid );
    $userid = $ridepod->field('ride_leader');
    if ($userid == get_current_user_id() || user_can(get_current_user_id(), 'edit_ride_schedule')) {
        $fields = array( 'ride-status', 'ride_date', 'time', 'tour', 'pace', 'ride_leader', 'ride_comments', 'rider_count', 'ride_leader_pace');
        return $ridepod->form( $fields );
    }
	else
	    return "";
}
add_shortcode('bk-edit-start-point', 'bk_edit_start_point');
function bk_edit_start_point()
{
    if (!empty($_GET['startid']))
	    $startid = intval($_GET['startid']);
    else
	    return "";
    $stpod = pods( 'start_point', $startid );
    if (empty($stpod))
        return "";
    $fields = array( 'post_title', 'start-county', 'state', 'longitude', 'latitude', 'directions', 'active');
    return $stpod->form($fields);    
}
add_shortcode('bk-edit-tour', 'bk_edit_tour');
function bk_edit_tour()
{
    if (!empty($_GET['tourid']))
	    $tourid = $_GET['tourid'];
    else
	    return "";
    $tourpod = pods( 'tour', $tourid );
    if ($tourid <= 0 || empty($tourpod))
	return "";
    $fields = array( 'tour_number', 'post_title', 'tour_description', 'vimeo', 'tour_comments', 'tour-terrain', 'start_point', 'miles', 'climb', 'tour_type', 'tour_map', 'cue_sheet', 'active');
    return $tourpod->form( $fields ); 
}
add_shortcode('bk-add-start-point', 'bk_add_start_point');
function bk_add_start_point()
{
    $startptpod = pods( 'start_point' );
    $fields = array('post_title', 'start-county', 'state', 'longitude', 'latitude', 'active' => array( 'default' => 1 ), 'directions');
    return $startptpod->form( $fields ); 
}
add_shortcode('bk-add-tour', 'bk_add_tour');
function bk_add_tour()
{
    $tourpod = pods( 'tour' );
    $fields = array( 'post_title', 'active' => array( 'default' => 1 ), 'tour_description', 'vimeo', 'tour_comments', 'tour-terrain', 'start_point', 'miles', 'climb', 'tour_type', 'tour_map', 'cue_sheet');
    return $tourpod->form( $fields, 'Add Tour', '/?p=X_ID_X'); 
}

add_shortcode('club-sponsors', 'bk_sponsors');
function bk_sponsors()
{
    $ret = "";
    // Gets every town and then loop through the sponsors in that town
    $terms = get_terms(['taxonomy' => 'sponsor_town', 'hide_empty' => false]);

    foreach( $terms as $term ) {

        $ret .= '<strong>' . $term->name . '</strong><br />';
        $params = array(
             'orderby' => 't.post_title',
             'limit' => -1,
             'where' => 'sponsor-town.slug = "' . $term->slug . '"'
        );
        $pod = pods('club-sponsor');
		$sponsors = $pod->find($params);
		$i = 0;
        while( $sponsors->fetch()) {
			$loc = $sponsors->field('location');
			$address = $sponsors->field('address');
			$phone = $sponsors->field('phone');
            $ret .= '<strong><a href="' . $sponsors->field('website') . '">' . $sponsors->field('post_title') . '</a></strong><br />';
			if (!empty($loc))
                $ret .= '<a href="' . $loc . '" target="_blank">' . $address . '</a><br />';
			else
                $ret .= $address . '<br />';
            // $ret .= $phone . '<br />'; 
            $ret .= '<a href="tel:' . $phone . '">' . $phone . '</a><br />';
		}
        $ret .= '<br />';
    }
    return $ret;
}

add_shortcode('bk-profile-page', 'bk_get_profile_page');
function bk_get_profile_page()
{
    if (!is_user_logged_in())
        return '';
    $current_user = wp_get_current_user();
    return '<a href="' . get_site_url() . '/members/' . $current_user->user_login . '/profile/">Profile</a>';
}
add_shortcode('bk-sp-weather', 'bk_sp_weather_func');
function bk_sp_weather_func()
{
    $ret = "";
    $ret = '<br><h2 class="entry-title">Open Weather Map</h2>';
    $postid = get_the_ID();
    if (!$postid)
        return $ret;
    $rpod = pods('ride', $postid);
    if (empty($rpod))
        return $ret;
    $tourfield = $rpod->field('tour');
    if (empty($tourfield))
        return $ret;
    $tourid = $tourfield['ID'];
    $tpod = pods('tour', $tourid);
    if (empty($tpod))
        return $ret;
    $start = $tpod->field('start_point');
    if (empty($start))
        return $ret;
    $startid = $start['ID'];
    $stpod = pods('start_point', $startid);
    if (empty($stpod))
        return $ret;
    $arr = $stpod->field('start-county');
    if (empty($arr))
        return $ret;
    $termid = $arr['term_id'];
    if (!$termid)
        return $ret;
    $cpod = pods('county', $termid);
    if (empty($cpod))
        return $ret;
    $weather_code = $cpod->display('weather');
    if ($weather_code != 0) {
        $ret = '<h2 class="entry-title">Open Weather Map for ' . $stpod->field('post_title') . '</h2>' .
             do_shortcode('[location-weather id="' . $weather_code . '"]');
    }
    return $ret;
}

add_shortcode('bk-update-tours', 'bk_update_tours');
function bk_update_tours()
{ 
$msg = "done";
    return $msg;
}
add_shortcode('bk-start-points', 'bk_start_points');
function bk_start_points()
{
    $can_edit = current_user_can('ridecoordinator') || current_user_can('rc_cap');
    ob_start();
?>
	<table class="display start_table" style="width:100%">
        <thead>
            <tr>
                <th style="width:35%">Name</th>
                <th class="tablet desktop" style="width:13%">County</th>
                <th class="tablet desktop" style="width:13%">State</th>
                <th class="tablet desktop" style="width:13%">Longitude</th>
                <th class="tablet desktop" style="width:13%">Latitude</th>
		<?php if ($can_edit) { ?>
                <th class="tablet desktop" style="width:13%">Active</th>
                <?php } ?>
                <th class="none">Directions</th>
                <?php if ($can_edit) { ?>
                <th class="none">Edit</th>
                <?php } else { ?>
                <th class="none">View</th>
                <?php } ?>
            </tr>
        </thead>
        </tbody><?php
    $fields = array('post_title', 'start-county', 'state', 'longitude', 'latitude', 'active' => array( 'default' => 1 ), 'directions');
    $stpod = pods("start_point");
    $params = array(
            'orderby' => 't.post_title',
		    'limit' => -1
        );
    $stpod->find($params);
    while( $stpod->fetch()) { ?>
          <?php
              $active_sp = $stpod->display('active');
              if ($active_sp == "No" && !$can_edit) continue;
          ?>
          <tr>
            <td><?php
		          $url = get_site_url() . '/start-point/' . $stpod->display('post_name') . '/';
		          echo '<a href="' . esc_url($url) . '">'  . $stpod->display('post_title') . '</a>';
			?> </td>
            <td><?php echo $stpod->display('start-county'); ?> </td>
            <td><?php echo $stpod->display('state'); ?> </td>
            <td><?php echo $stpod->display('longitude'); ?> </td>
            <td><?php echo $stpod->display('latitude'); ?> </td>
	        <?php if ($can_edit) { ?>
            <td><?php echo $active_sp; ?> </td>
			<?php } ?>
            <td><?php echo $stpod->display('directions	'); ?> </td>
            <?php if ($can_edit) {
              $url = get_site_url() . '/edit-start-point/?startid=' . $stpod->field('ID');
              echo '<td><a href="' . esc_url($url) . '">Edit</a></td>';
            }
			else {
				$url = get_site_url() . '/start-point/' . $stpod->display('post_name') . '/';
				echo '<td><a href="' . esc_url($url) . '">View</a></td>';
			}
			?>
        </tr><?php
    } // end while
    ?>
    </tbody></table>
<?php
	return ob_get_clean();
}

// Add a custom endpoint "calendar"
function bk_add_calendar_feed(){
	add_feed('ical', 'bk_export_ics');
    // Only uncomment these 2 lines the first time you load this script, to update WP rewrite rules, or in case you see a 404
    // global $wp_rewrite;
    // $wp_rewrite->flush_rules( false );
}
add_action('init', 'bk_add_calendar_feed');

function bk_export_ics(){

    /*  For a better understanding of ics requirements and time formats
        please check https://gist.github.com/jakebellacera/635416   */

	if ( !array_key_exists('id', $_REQUEST ) ) {
		exit();
	}
    // Query the event
    $pod = pods('ride', $_REQUEST['id']);
    if($pod->exists()) :
    
        // Escapes a string of characters
        function escapeString($string) {
              return preg_replace('/([\,;])/','\\\$1', $string);
        }
    
        // while($the_event->have_posts()) : $the_event->the_post();
	
		$postid = $pod->id();
        // $pod = pods('ride', $postid);
        $ridestatus = $pod->field('ride-status');
        $datestr = $pod->display('ride_date') . ' ' . $pod->display('time');
        $tz = new DateTimeZone(wp_timezone_string());
        $start_time = DateTime::createFromFormat("m/d/Y h:i a", $datestr, $tz);
        $sttime = $start_time->getTimeStamp();
		$start_date = wp_date("Ymd\THis", $sttime);
        $tourfield = $pod->field('tour');
        $tourid = $tourfield['ID'];
        $tpod = pods('tour', $tourid);
        $tourno = $tpod->field('tour_number');
        $start = $tpod->field('start_point');
        $startid = $start['ID'];
        $stpod = pods( 'start_point', $startid );
        $lat = $stpod->display('latitude');
        $lon = $stpod->display('longitude');
        $pace = $pod->display('pace');
        $created_date = get_post_time('Ymd\THis\Z', true, $postid);
		$timestamp = date_i18n('Ymd\THis\Z', time(), true);
		$organiser = $pod->display('ride_leader');
        $summary = 'MAF' . $tourno . ' Bike Ride: ' . $pace . " " . $organiser;
        $url = 'https://mafw.org/ride/' . $postid . '/';
        $address = $lat . "," . $lon;
        $content = '<a href="' . get_site_url() . '/ride/' . $postid . '/">Ride Detail</a>';
        $title = 'MAF' . $tourno . ' Bike Ride: ' . $pace . " " . $organiser;

        //Give the iCal export a filename
        $filename = urlencode( 'ride' . $postid . '-ical-' . date('Y-m-d') . '.ics' );
        $eol = "\r\n";

        //Collect output
        ob_start();

        // Set the correct headers for this file
        header("Content-Description: File Transfer");
        header("Content-Disposition: attachment; filename=".$filename);
        header('Content-type: text/calendar; charset=utf-8');
        header("Pragma: 0");
        header("Expires: 0");

// The below ics structure MUST NOT have spaces before each line
// Credit for the .ics structure goes to https://gist.github.com/jakebellacera/635416
?>
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//<?php echo get_bloginfo('name'); ?> //NONSGML Events //EN
CALSCALE:GREGORIAN
X-WR-CALNAME:<?php echo get_bloginfo('name').$eol;?>
BEGIN:VEVENT
CREATED:<?php echo $created_date.$eol;?>
UID:<?php echo $postid.$eol;?>
DTEND;VALUE=DATE:<?php echo $start_date.$eol; ?>
DTSTART;VALUE=DATE:<?php echo $start_date.$eol; ?>
DTSTAMP:<?php echo $timestamp.$eol; ?>
LOCATION:<?php echo escapeString($address).$eol; ?>
DESCRIPTION:<?php echo $content.$eol; ?>
SUMMARY:<?php echo $summary.$eol; ?>
ORGANIZER:<?php echo escapeString($organiser).$eol;?>
URL;VALUE=URI:<?php echo escapeString($url).$eol; ?>
TRANSP:OPAQUE
END:VEVENT
<?php
        // escapeString($address).$eol;
        // endwhile;
?>
END:VCALENDAR
<?php
        //Collect output and echo
        $eventsical = ob_get_contents();
        ob_end_clean();
        echo $eventsical;
        exit();

    endif;
}
add_filter('content_save_pre', 'bk_strip_shortcodes' );
function bk_strip_shortcodes( $content ) {
    if ( ! current_user_can( 'manage_options' ) && false !== strpos( $content, '[' )  ) {
        $content = preg_replace( '/\[file_manager_advanced.*\]/s', '', $content);
    }
    return $content;
}
add_action( 'init', 'mpp_custom_bridge_resize_after_upload_func' );
 
function mpp_custom_bridge_resize_after_upload_func() { 
    if( function_exists( 'jr_uploadresize_resize' ) )
        add_action( 'mpp_handle_upload', 'jr_uploadresize_resize' );
}

add_action('init', 'bk_remove_retrieve_password_filter');
function bk_remove_retrieve_password_filter()
{
    remove_filter( 'retrieve_password_message', 'pmpro_retrieve_password_message', 10, 1 );
}

add_action( 'pmpro_membership_post_membership_expiry', 'bk_membership_expires' );
function bk_membership_expires( $userid ) {
    // get all sessions for user with ID $userid
    $sessions = WP_Session_Tokens::get_instance( $userid );

    // we have got the sessions, destroy them all!
    $sessions->destroy_all();

    delete_user_meta( $userid, 'persistent_login_remember_me', 'true' );
}


// add_shortcode('bk-logout-users', 'bk_users_logout');
function bk_users_logout()
{
    bk_membership_expires(5510);
    bk_membership_expires(4649);
    bk_membership_expires(5262);
    return "done";
}

function bk_disable_voting_page()
{
    global $wpdb;
    $p = $wpdb->prefix . "posts";
    $sql = 'UPDATE ' . $p . ' SET ' . $p . '.post_status = "draft" WHERE ' . $p . '.ID =  223796';
    $wpdb->query($sql);
}
// add_shortcode('disable-voting-page', 'bk_func_disable_voting_page');
function bk_func_disable_voting_page() {
	$tz = new DateTimeZone(wp_timezone_string());
    $dow_obj = new DateTime("2024-11-07 00:00:01", $tz);
    wp_schedule_single_event( $dow_obj->getTimeStamp(), 'bk_disable_voting_page'); 
}

add_action('pmpro_add_member_added', 'bk_add_xprofile_visibility', 30, 1);
function bk_add_xprofile_visibility($userid)
{
    // set default visibility to the email address to be all members
    // this will add a usermeta with a key of "bp_xprofile_visibility_levels"
    // this is needed for the default visibility levels to be honored
    if (function_exists('xprofile_set_field_visibility_level'))
        xprofile_set_field_visibility_level(247, $userid, "loggedin");
}
/*
function bk_set_event_page_query($query, $term)
{
    $tax_query = $query->get('tax_query');
    if (!$tax_query) {
        $tax_query = [];
    }
    $tax_query[] = [
        'taxonomy' => 'tribe_events_cat',
        'field' => 'slug',
        'terms' => $term,
    ];
    $meta_query = $query->get('meta_query');
    if (!$meta_query) {
        $meta_query = [];
    }
    $meta_query[] = [
        'key' => '_EventStartDate',
        'value' => date("Y-m-d"),
        'compare' => '>=',
        'type' => 'DATE'
    ];
    $query->set('meta_query', $meta_query);
    $query->set('tax_query', $tax_query);
    $query->set('orderby', [ 'meta_value' ]);
    $query->set('post_type', [ 'tribe_events' ] );
}
*/
// add_action ('elementor/query/bk_club_events', function($query) {
    // bk_set_event_page_query($query, 'club-events');
// });
// add_action ('elementor/query/bk_other_org_events', function($query) {
    // bk_set_event_page_query($query, 'other-organization-events');
// });
// add_action ('elementor/query/bk_adventure_events', function($query) {
    // bk_set_event_page_query($query, 'bike-adventures');
// });

/*
  Only let level 7 members sign up if they use a discount code.
*/
function bk_pmpro_registration_checks_require_code_to_register($pmpro_continue_registration)
{
  //only bother if things are okay so far
	if(!$pmpro_continue_registration)
		return $pmpro_continue_registration;

	global $pmpro_level, $discount_code;
	if ($pmpro_level != 7)
		return $pmpro_continue_registration;

    if (function_exists('pmpro_groupcodes_getGroupCode')) {
        $couponcode = trim(strtoupper($discount_code));
        $group_code = pmpro_groupcodes_getGroupCode($couponcode);
	    if (!empty($group_code) && $group_code->order_id == 0) {
		    global $wpdb;
	        $code_parent = $wpdb->get_var("SELECT code FROM $wpdb->pmpro_discount_codes WHERE id = '" . $group_code->code_parent . "' LIMIT 1");
		    if (!empty($code_parent) && $code_parent->code == "04ECDA9961") {
			    return $pmpro_continue_registration;
		    }
	    }
    }
    pmpro_setMessage("You must use a valid discount code to register for the free trial.", "pmpro_error");
	return false;
}
// add_filter("pmpro_registration_checks", "bk_pmpro_registration_checks_require_code_to_register");
function bk_pmpro_disable_member_emails($recipient, $email)
{
	if ($recipient == "pkurey@optonline.net" || $recipient == "bobgeddis2@aol.com")
  		$recipient = NULL;	
	
	return $recipient;
}
add_filter("pmpro_email_recipient", "bk_pmpro_disable_member_emails", 10, 2);

// add_action('pmpro_add_member_added', 'bk_registration', 30, 1);
function bk_registration($userid)
{
    $fields = array();
    $fields[] = array( 'name' => 'name', 'num' => 1 );
    $fields[] = array( 'name' => 'gender', 'num' => 2 );
    $fields[] = array( 'name' => 'home_phone', 'num' => 103 );
    $fields[] = array( 'name' => 'cell_phone', 'num' => 105 );
    $fields[] = array( 'name' => 'emergency_phone', 'num' => 107 );
    $fields[] = array( 'name' => 'car_license', 'num' => 113 );
    $fields[] = array( 'name' => 'address', 'num' => 98 );
    $fields[] = array( 'name' => 'city', 'num' => 100 );
    $fields[] = array( 'name' => 'state', 'num' => 101 );
    $fields[] = array( 'name' => 'zip', 'num' => 102 );
    foreach ($fields as $field) {
        $val = get_user_meta( $userid, $field->name, true ); 
        if (!empty($val))
            xprofile_set_field_data( $field->num, $userid, $val );
    }
}

// setcookie(TEST_COOKIE, 'WP Cookie check', 0, COOKIEPATH, COOKIE_DOMAIN);
// if ( SITECOOKIEPATH != COOKIEPATH ) setcookie(TEST_COOKIE, 'WP Cookie check', 0, SITECOOKIEPATH, COOKIE_DOMAIN);

/**
 * Allow expiring members to extend their membership on renewal or level change
 *
 * Extend the membership expiration date for a member with remaining days on their current level when they complete checkout for ANY other level that has an expiration date. Always add remaining days to the enddate.
 *
 * title: Allow expiring members to extend their membership on renewal or level change
 * layout: snippet
 * collection: checkout
 * category: renewals
 *
 * You can add this recipe to your site by creating a custom plugin
 * or using the Code Snippets plugin available for free in the WordPress repository.
 * Read this companion article for step-by-step directions on either method.
 * https://www.paidmembershipspro.com/create-a-plugin-for-pmpro-customizations/
 */


function bk_pmpro_checkout_level_extend_memberships( $level ) {

	global $pmpro_msg, $pmpro_msgt, $current_user;

	// does this level expire? are they an existing members with an expiration date?
	if ( ! empty( $level ) && ! empty( $level->expiration_number ) && pmpro_hasMembershipLevel() && ! empty( $current_user->membership_level->enddate ) ) {

		// get the current enddate of their membership
		$expiration_date = $current_user->membership_level->enddate;

		// calculate days left
		$todays_date = time();
		$time_left   = $expiration_date - $todays_date;

		// time left?
		if ( $time_left > 0 ) {

			// convert to days and add to the expiration date (assumes expiration was 1 year)
			$days_left = floor( $time_left / ( 60 * 60 * 24 ) );

			// figure out days based on period
			if ( $level->expiration_period == 'Day' ) {
				$total_days = $days_left + $level->expiration_number;
			} elseif ( $level->expiration_period == 'Week' ) {
				$total_days = $days_left + $level->expiration_number * 7;
			} elseif ( $level->expiration_period == 'Month' ) {
				$total_days = $days_left + $level->expiration_number * 30;
			} elseif ( $level->expiration_period == 'Year' ) {
					$total_days = $days_left + $level->expiration_number * 365;
			}

			// update number and period
			$level->expiration_number = $total_days;
			$level->expiration_period = 'Day';
		}
	}

	return $level;
}
add_filter( 'pmpro_checkout_level', 'bk_pmpro_checkout_level_extend_memberships' );

// Notice admin_init - will only run in backend
// add_action('admin_init', function() {
// 	require_once(ABSPATH.'/wp-admin/includes/upgrade.php');
// 	global $wpdb;
// 	$mytables=$wpdb->get_results("SHOW TABLES");
// 	foreach ($mytables as $mytable)
// 	{
// 	    foreach ($mytable as $t) 
// 	    {       
// 	        maybe_convert_table_to_utf8mb4( $t );
// 	    }
// 	}
// });

add_action('pre_get_posts', 'bk_make_search_exact', 10);
function bk_make_search_exact($query) {
    if (!is_admin() && $query->is_main_query() && $query->is_search) {
        $query->set('sentence', true);
    }
}
add_filter( 'wp_mail_from', 'bk_mail_from_address' );
function bk_mail_from_address( $email ) {
    if (empty($email) || $email == "wordpress@mafw.org")
        $email = 'it_coordinator@mafw.org';
    return $email;
}
add_filter( 'wp_mail_from_name', 'bk_mail_from_name' );
function bk_mail_from_name( $from_name ) {
    if (empty($from_name) || $from_name == "WordPress")
        $from_name = 'IT Coordinator';
    return $from_name;
}
add_filter('pods_evaluate_tag', 'bk_pod_evaluate_tag', 10, 2);
function bk_pod_evaluate_tag($value,  $tag ) {
    if ( $tag[0] == 'user' && $tag[1] == 'ID' && (empty($value) || !is_int($value ) ) )
        return 0;
    return $value;
}
add_filter('em_event_output_placeholder','bk_em_styles_placeholders',1,3);
function bk_em_styles_placeholders($replace, $EM_Event, $result){
    if( preg_match( '/#_EVENTIMAGE.*/', $result ) ) {
		$url = get_post_meta($EM_Event->post_id, 'EVENTURL', true);
		if ( empty( $url ) ) {
 		    $url = esc_url($EM_Event->get_permalink());
		    $replace = '<a href="' . $url . '">' . $replace . '</a>';
		}
        else {
		    $replace = '<a href="' . $url . '" target="_blank">' . $replace . '</a>';
        }
    }
    else if( preg_match( '/#_EVENTLINK.*/', $result ) ) {
		$url = get_post_meta($EM_Event->post_id, 'EVENTURL', true);
		if ( !empty( $url ) ) {
			$replace = preg_replace('/(<a href=")[^"]*">([^<]*)<\/a>/', '${1}' . $url . '">' . '$2' . '</a>', $replace);
		}
    }
    else if( preg_match( '/#_EVENTURL.*/', $result ) ) {
		$url = get_post_meta($EM_Event->post_id, 'EVENTURL', true);
		if ( !empty( $url ) ) {
			$replace = $url;
		}
    }
    return $replace;
}
function bk_gutenberg_categories_fix($args) {
// FIX MISSING CATEGORIES AND TAGS in Event Manager edit mode. 
    $args['show_in_rest'] = true;
    return $args;
}

add_filter('em_cpt_categories','bk_gutenberg_categories_fix');
add_filter('em_cpt_tags','bk_gutenberg_categories_fix');

// add_filter( 'em_get_search_views', 'bk_reorder_search_views' );
/* function bk_reorder_search_views( $search_views ) {
	error_log('search_views:' . print_r($search_views, true));
	$new_sviews = [];
	$new_sviews['grid'] = $search_views['grid'];
    foreach ($search_views as $key => $value) {
		if ( $key != 'grid' ) {
			$new_sviews[$key] = $value;
		}
	}
	error_log('new_sviews:' . print_r($new_sviews, true));
    return $new_sviews;
} */
function bk_get_events_grid_shortcode($args, $format='') {
	$args = (array) $args;
	$args['ajax'] = isset($args['ajax']) ? $args['ajax']:(!defined('EM_AJAX') || EM_AJAX );
	$args['format'] = ($format != '' || empty($args['format'])) ? $format : $args['format']; 
	$args['format'] = html_entity_decode($args['format']); //shortcode doesn't accept html
	$args['limit'] = isset($args['limit']) ? $args['limit'] : get_option('dbem_events_default_limit');
	if( !empty($args['id']) ) $args['id'] = rand();
	if( empty($args['format']) && empty($args['format_header']) && empty($args['format_footer']) ){
		ob_start();
		em_locate_template('templates/events-grid.php', true, array('args'=>$args));
		$return = ob_get_clean();
	}else{
		$args['ajax'] = false;
		$pno = ( !empty($args['pagination']) && !empty($_GET['pno']) && is_numeric($_GET['pno']) )? $_GET['pno'] : 1;
		$args['page'] = ( !empty($args['pagination']) && !empty($args['page']) && is_numeric($args['page']) )? $args['page'] : $pno;
		$return = EM_Events::output( $args );
	}
	return $return;
}
add_shortcode('events_grid', 'bk_get_events_grid_shortcode');

// add_filter('doing_it_wrong_trigger_error', 'bk_debug_backtrace', 10, 3);
function bk_debug_backtrace($func, $msg, $ver) {
	error_log($func . ":\n" . print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true));
	return $msg;
}

/* add_action(
	'doing_it_wrong_run',
	static function ( $function_name ) {
		if ( '_load_textdomain_just_in_time' === $function_name ) {
			error_log(print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true));
		}
	}
); */

add_shortcode('bk-update-users', 'bk_update_users');
function bk_update_users()
{
	$ret = "";
	$path = plugin_dir_path( __DIR__ ) . "bike-club";
    if (($handle = fopen($path."/memberupdates.csv", "r")) !== FALSE) {

		$header = fgetcsv($handle, 1000, ",");
		$num = count($header);
		if ($header !== FALSE) {
			while (($data = fgetcsv($handle, 1000, ",")) !== FALSE ) {
				$userid = $data[0];
				$address = $data[3];
				$town = $data[4];
				$state = $data[5];
				$zip = $data[6];
    			xprofile_set_field_data(102, $userid, $zip);
    			xprofile_set_field_data(100, $userid, $town);
    			xprofile_set_field_data(101, $userid, $state);
    			xprofile_set_field_data(98, $userid, $address);
				$ret .= $userid . "<br />\n";
        	}
		}
		fclose($handle);
	}
	return $ret;
}

add_shortcode ( 'events_list_searchform', 'em_get_events_list_search_shortcode' );
function em_get_events_list_search_shortcode($args = []) {
	if( !array_key_exists('id', $args) ) $args['id'] = rand();
    $selector = 'form#em-search-form-' . $args['id'] . ' button.em-search-submit';
    $args = em_get_search_form_defaults($args);
    $args['search_action'] = 'search_events';
    $args['search_url'] = get_option('dbem_events_page') ? get_permalink(get_option('dbem_events_page')):EM_URI;
    $args['css_classes'][] = 'em-events-search';
    $args['css_classes_advanced'][] = 'em-events-search-advanced';
    if (!empty($args['scope']['name'])) $args['scope'] = $args['scope']['name'];
    $script = '<script>
    jQuery(document).ready( function($){
        $(window).on("load", function() {
            $("' . $selector . '").click();
        });
    })</script>';
    ob_start();
    em_locate_template('templates/search.php', true, array('args'=>$args));
    return ob_get_clean() . $script;
}


add_shortcode('bk-patriots', function ( ) {
	return '<script async
  src="https://js.stripe.com/v3/buy-button.js">
</script>

<stripe-buy-button
  buy-button-id="buy_btn_1PQB1pGZUVtUMLUxRdT8dnoT"
  publishable-key="pk_live_cy8AOKlCF24RwLcajr6F2Y4m00dOLtivIN"
>
</stripe-buy-button>';
} );


// add_filter('wp_mail', 'bk_wp_mail', 10, 1);
function bk_wp_mail( $args) {
    return $args;
}

/*
 * This solution should be the accepted answer in this forum thread.
 * 
 * https://generatepress.com/forums/topic/gpgbpopup-maker-issue/#post-2088153
 */
add_filter( 'generateblocks_do_content', function( $content ) {
    $post_id = 199426; // popup ID for the newsletter announcement

    if ( has_blocks( $post_id ) ) {
        $block_element = get_post( $post_id );

	// Where 'popup' is the custom post type for Popup Maker popups.
	// https://docs.generateblocks.com/article/adding-content-sources-for-dynamic-css-generation/
        if ( ! $block_element || 'popup' !== $block_element->post_type ) {
            return $content;
        }

        if ( 'publish' !== $block_element->post_status || ! empty( $block_element->post_password ) ) {
            return $content;
        }

        $content .= $block_element->post_content;
    }

    return $content;
} );

// add_filter( 'send_auth_cookies', 'bk_send_auth_cookies' );
function bk_send_auth_cookies( $send ) {
	error_log(print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true));
    return $send;
}

// add_filter('add_post_metadata', 'bk_post_metadata', 999, 3);
function bk_post_metadata($check, $object_id, $meta_key)
{
    if ($meta_key == 'ride-status')
        return null;
    return $check;
}

add_filter('em_object_get_pagination_links', 'bk_object_output_pagination');
function bk_object_output_pagination($return)
{
	if (str_contains($return, '#s')) {
       return str_replace(['&lt;', '&gt;', '#s', '/h2'], ['%3C', '%3E', '%23s', '%2Fh2'], $return);
    }
    return $return;
}

// add_filter('wpseo_frontend_presentation', 'bk_frontend_presentation', 20, 2);
function bk_frontend_presentation($presentation, $context) {
    error_log("presentation:" . print_r($presentation, true));
    error_log("context:" . print_r($context, true));
    return $presentation;
}

add_filter( 'wpseo_title', 'bk_content_wpseo_title' );
function bk_content_wpseo_title($title){
	return bk_get_document_title($title);
}
add_filter('pre_get_document_title', 'bk_get_document_title');
function bk_get_document_title($title)
{
    if (!is_admin() && get_the_ID() !== false) {
		$content = get_the_content();
       if (has_shortcode($content, 'ride_leader_report')) {
           $daterange = bk_get_start_and_end_date(false);
           $start_date = convertDateFormat($daterange['start_date'], 'Y-m-d', 'm/d/Y');
	       $end_date = convertDateFormat($daterange['end_date'], 'Y-m-d', 'm/d/Y');
           $title = "Ride Leader Report - Morris Area Freewheelers " . $start_date . " - " . $end_date;
       }
    }
    return $title;
}

// add_filter( 'em_calendar_get_default_search', function( $atts ) {
    // $new_atts = $atts;
    // unset( $new_atts['calendar_size'] );
    // return $new_atts;
// } );

// add_filter('em_calendar_get_args', function( $args ) {
    // $args['scope'] = 'future';
    // return $args;
// });

add_filter( 'fmb_post_types', function( $post_types ) {
    unset( $post_types['attendee'] );
    unset( $post_types['email_broadcast'] );
    unset( $post_types['food_stops'] );
    unset( $post_types['guest'] );
    unset( $post_types['membership_card'] );
    unset( $post_types['pace'] );
    unset( $post_types['ride-attendee'] );
    unset( $post_types['ride'] );
    unset( $post_types['road_closurewarnings'] );
    unset( $post_types['role'] );
    unset( $post_types['start_point'] );
    unset( $post_types['terrain'] );
    unset( $post_types['tour'] );
    return $post_types;
} ); 

function multineedle_stripos($haystack, $needles, $offset=0) {
    foreach($needles as $needle) {
        if (stripos($haystack, $needle, $offset) !== false) return true;
    }
    return false;
}

add_filter('haet_mail_footer', function( $footer ){
    return '<p><span style="color: #363636;">© ' . date( 'Y' ) . ' Morris Area Freewhelers<br /><strong>Morris Area Freewheelers</strong><br /><a href="http://mafw.org/">www.mafw.org</a></span></p>';
});

add_filter( 'haet_mail_use_template', 'customize_template_usage', 10, 2 );
function customize_template_usage( $use_template, $mail ){
	$needle = [ "newsletter", "wordfence", "password" ];
	if ( multineedle_stripos($mail['subject'], $needle) !== false ) {
        return false;
	}
    return $use_template;
}

add_filter('em_rss_template_args', function($args) {
    if ( !empty($_GET['category']) ) {
        $args['category'] = sanitize_text_field($_GET['category']);
    }
    return $args;
});

/**
 * Add 'buddypress' field attribute to existing User Fields.
 * 
 * title: Add buddypress to existing User Fields
 * layout: snippet-example
 * collection: pmpro-buddypress
 * category: custom-fields, buddypress, buddyboss, xprofile
 * 
 * You can add this recipe to your site by creating a custom plugin
 * or using the Code Snippets plugin available for free in the WordPress repository.
 * Read this companion article for step-by-step directions on either method.
 * https://www.paidmembershipspro.com/create-a-plugin-for-pmpro-customizations/
 */
 function bk_pmpro_add_buddypress_to_existing_fields( $field, $where ) {
	// PMPro User Field => BuddyPress XProfile Field. Adjust this array for each field you need to map.
	$buddypress_mapping = array(
		'gender' => 'Gender',
		'birthday' => 'Birthday',
        'home_address' => 'Home Address 1',
        'city' => 'City',
        'state' => 'State',
        'zip_code' => 'Zip',
        'mobile_phone', 'Mobile Phone',
        'emergency_contact_name' => 'Emergency Contact Name',
        'emergency_contact_number' => 'Emergency Number'
	);
 
	// Loop through the above array and add the buddypress field to the existing PMPro User Field.
	foreach( $buddypress_mapping as $user_field => $buddypress_field ) {
		if ( $field->name === $user_field ) {
			$field->buddypress = $buddypress_field;
		}
	}
 
	return $field;
}
add_filter( 'pmpro_add_user_field', 'bk_pmpro_add_buddypress_to_existing_fields', 10, 2 );

add_filter('members_post_error_message', 'bk_login_redirect', 10, 1);
function bk_login_redirect( $error_msg ) {
    if (!is_user_logged_in()) {
        $current_url = home_url( add_query_arg( [], $GLOBALS['wp']->request ) );
		$url = esc_url( wp_login_url( $current_url ) ); 
        return '<div class="members-access-error"><b>Please <a href="' . $url . '">Log in</a> to access this page.</b></div>';
    }
    return $error_msg;
}
function my_em_custom_booking_form_cols_tickets($value, $col){
    if( $col != 'actions' && $col != 'event_name' && $col != 'user_email' ){
		$value = wp_strip_all_tags( $value );
	}
    return $value;
}
add_filter('em_bookings_table_rows_col','my_em_custom_booking_form_cols_tickets', 100, 2);

function bk_pmpro_set_application_fee_percentage( $application_fee_percentage ) {
	return 0; // Remove the application fee.
	// return 2.5; // Increase application fee to 2.5%
}
// add_filter( 'pmpro_set_application_fee_percentage', 'bk_pmpro_set_application_fee_percentage' );

add_shortcode('output_pending_events', function() {
    return EM_Events::output(array('status'=>1, 'scope'=>'all'));
});

function bk_become_ride_leader() {
    check_ajax_referer('ajax_nonce', 'nonce');
	if (isset($_POST['link']) ) {
		$link = isset($_POST['link']) ? $_POST['link'] : "";
		$link = preg_replace('/\?.*/', '', $link);
    	$rideid = absint(basename($link));
	}
	else {
		$rideid = 0;
	}
	if ($rideid > 0 && current_user_can('rideleader')) {
        $rpod = pods('ride', $rideid);
		$userid = get_current_user_id();
		$post_arr = [ 'ID' => $rideid, 'post_author' => $userid ];
		wp_update_post($post_arr);
		$rpod->save('ride-status', 0);
		// $rpod->save('ID', $userid);
		$rpod->save('ride_leader', $userid);
		$rl = $rpod->field('ride_leader');
		// $rpod->save('ride-status', 0);
        wp_update_post([ 'ID' => $rideid, 'post_author' => $userid ]);
		$rpod->save('ride_leader', $userid);
		// update_post_meta($rideid, '_pods_ride_leader', [ $userid ]);
		$rpod->save('ride-status', 0);
        bk_add_ride_leader_to_ride($rideid, $rpod);
        bk_send_ride_email_if_needed($rpod, $rideid, $userid);
		bk_clear_cache();
		$status = "You are now the ride leader!";
    }
	else {
		$status = 'error';
    }
	echo $status;
	exit;
}
add_action('wp_ajax_bk_become_ride_leader', 'bk_become_ride_leader');

function bk_become_leader_shortcode() {
    // Send request to admin-post.php
	// $form_action = esc_url(admin_url('admin-post.php'));
    // $link = esc_url($_SERVER['REQUEST_URI']);
	// $link = preg_replace('/\?.*/', '', $link);
    // $rideid = absint(basename($link));
    // ob_start(); // Start output buffering
    //
    // <! <form class='form-submit'>
        // <button type="submit" class="become-leader">Become the Ride Leader</button>
    // /form> !>
    // <?php
    // return ob_get_clean(); // Return the form HTML
	return '<div><form><input type="button" class="become-leader" id="becomeleader" name="become leader" value="Become the Ride Leader"></div><br /><div class="result_area"></form><p>Click on the button above to become the ride leader for this ride. Once you click on the button you will be the ride leader and the email broadcast will be sent out immediately.</p></div>';
}
add_shortcode('bk-become-leader', 'bk_become_leader_shortcode');

add_filter('em_event_output_placeholder', function( $replace, $EM_Event, $result ) {
    if ( preg_match( '/#_EVENTIMAGE.*/', $result ) ) {
        $replace = preg_replace( "/alt='[^']*'/", "", $replace );
    }
    return $replace;
}, 100, 3);


// add_shortcode('add-attendee', function() {
	// add_attendee(227292, 4677);
// });
function add_attendee( $rideid, $current_userid) {
	$key = "attend_" . $rideid . '_' . $current_userid;
	if (!get_option($key)) {
		add_option($key, true);
	}
	else {
		return;
	}
	$upod = pods('user', $current_userid);
    $rpod = pods('ride', $rideid);
	$rl = $rpod->field('ride_leader');
    $car_license = xprofile_get_field_data('Vehicle License', $current_userid);
    $emergency_phone = xprofile_get_field_data('Emergency Number', $current_userid);
    $cell_phone = xprofile_get_field_data('Mobile Phone', $current_userid);
    $user = pods('user', $current_userid);
    $data = array(
              'title' => $rideid . ' - ' . $user->field('display_name'),
              'ride_attendee_status' => "Yes",
              'car_license' => $car_license,
              'emergency_phone' => $emergency_phone,
              'cell_phone' => $cell_phone
            );
    $pod = pods('ride-attendee');
    $aid = $pod->add($data);
    if (!empty($aid) && is_int($aid) && $aid > 0) {
        pods('ride-attendee', $aid)->save('_members_access_role', 'active');
        $rpod->add_to('attendees', $aid); 
		$apod = pods('ride-attendee', $aid);
		$apod->add_to('rider', $current_userid);
	    $upod->add_to('rides', $aid); 
	}
    bk_clear_cache();
}


add_shortcode('bk-remove-emergency-contact-tags', 'bk_remove_emergency_contact_tags');
function bk_remove_emergency_contact_tags() {
    $ret = "";
    // Create a new WP_User_Query instance
    $args = array(
        'meta_key'   => 'emergency_contact_number',
        'meta_compare' => 'EXISTS', // Ensures only users with this meta key are returned
        'fields'     => array('ID') // We only need the user ID to update meta
    );

    $user_query = new WP_User_Query($args);

    // Get the results
    $users = $user_query->get_results();

    if (!empty($users)) {
        foreach ($users as $user) {
            $user_id = $user->ID;
            $emergency_contact_number = get_user_meta($user_id, 'emergency_contact_number', true);

            // Check if the meta value contains HTML tags
            if ($emergency_contact_number != strip_tags($emergency_contact_number)) {
                // Strip the tags
                $stripped_number = strip_tags($emergency_contact_number);

                // Update the usermeta
                update_user_meta($user_id, 'emergency_contact_number', $stripped_number);

                $ret .= "Updated user ID {$user_id}: original value '{$emergency_contact_number}' to '{$stripped_number}'<br>";
            }
        }
    } else {
        $ret .= "No users found with 'emergency_contact_number' meta key.";
    }
    return $ret;
}

add_action( 'wp_enqueue_scripts', 'bk_ride_enqueue_func' );
function bk_ride_enqueue_func() {   
    // --- SECTION 1: RIDES ---
    if ( is_singular( 'ride' ) ) {
        if ( isset( $_GET['riderole'] ) ) {
            $role = $_GET['riderole'];
            if ( is_numeric( $role ) ) {
                
                // Role 2: Coordinator
                if ( $role == 2 ) {
                    if ( current_user_can( 'ridecoordinator' ) ) {
                        gravity_form_enqueue_scripts( 7, true );
                    }
                } 
                // Role 1: Ride Leader
                elseif ( $role == 1 ) {
                    if ( current_user_can( 'rideleader' ) ) { 
                        gravity_form_enqueue_scripts( 21, true );
                        gravity_form_enqueue_scripts( 32, true );
                    }
                }
            }
        }
    }
    // --- SECTION 2: TOURS ---
    else if ( is_singular( 'tour' ) ) { 
        if ( isset( $_GET['touredit'] ) ) {
            if ( $_GET['touredit'] == "Edit" && ( current_user_can( "ridecoordinator" ) || current_user_can( "administrator" ) ) ) {
                gravity_form_enqueue_scripts( 19, true );
            }
        }
    }
    // --- SECTION 3: LOCATION BLOCKS ---
    else if ( is_singular( 'locationdateblock' ) ) {
        if ( isset( $_GET['blockedit'] ) ) {
            if ( $_GET['blockedit'] == "Edit" && ( current_user_can( "ridecoordinator" ) || current_user_can( "administrator" ) ) ) {
                gravity_form_enqueue_scripts( 28, true );
            }
        }
    }
}

function custom_prefix_admin_enqueue_scripts($hook_suffix = '') {
	if( $hook_suffix == 'post.php' || $hook_suffix === true || (!empty($_GET['page']) && substr($_GET['page'],0,14) == 'events-manager') || (!empty($_GET['post_type']) && in_array($_GET['post_type'], array(EM_POST_TYPE_EVENT,EM_POST_TYPE_LOCATION,'event-recurring'))) ){
		wp_enqueue_script('custom-prefix-events-editor', plugins_url('js/custom-prefix-events-editor.js', __FILE__), array(), '1.0.0');
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'migrate-pod', function( $args, $assoc_args ) {
        
        $recognized_flags = ['reverse', 'dry-run', 'verbose', 'index-fields', 'force'];
        $usage = "Usage: wp migrate-pod <pod_name> [--reverse] [--dry-run] [--verbose] [--index-fields=f1,f2] [--force]";

        if ( empty( $args ) ) {
            WP_CLI::error( "Missing Pod name.\n" . $usage );
            return;
        }
        
        $pod_name = $args[0];
        $api      = pods_api( $pod_name );

        if ( ! $api ) {
            WP_CLI::error( "Pod '{$pod_name}' does not exist.\n" . $usage );
            return;
        }

        foreach ( $assoc_args as $flag => $value ) {
            if ( ! in_array( $flag, $recognized_flags ) ) {
                WP_CLI::error( "Invalid argument '--$flag'.\n" . $usage );
                return;
            }
        }

        $dry_run  = isset( $assoc_args['dry-run'] );
        $verbose  = isset( $assoc_args['verbose'] );
        $reverse  = isset( $assoc_args['reverse'] );
        $force    = isset( $assoc_args['force'] );
        $manual_fields = isset( $assoc_args['index-fields'] ) ? explode(',', $assoc_args['index-fields']) : [];

        $pod_data = $api->load_pod( [ 'name' => $pod_name ] );
        $pod_id   = $pod_data['id']; 
        $current_storage = isset( $pod_data['storage'] ) ? $pod_data['storage'] : 'meta';
        $target_storage  = $reverse ? 'meta' : 'table';

        if ( $current_storage === $target_storage && ! $force ) {
            WP_CLI::warning( "Pod '$pod_name' is already using '$current_storage' storage." );
            return;
        }

        WP_CLI::log( WP_CLI::colorize( "Migration: %Y$current_storage%n -> %B$target_storage%n" ) );

        if ( ! $dry_run ) {
            $api->save_pod( [ 'id' => $pod_id, 'name' => $pod_name, 'storage' => $target_storage ] );
        }

        // --- START DEEP SYNC LOGIC ---
        $items = pods( $pod_name, [ 'limit' => -1 ] );
        $total = $items->total();
        
        if ( $total > 0 && ! $dry_run ) {
            $progress = \WP_CLI\Utils\make_progress_bar( "Deep Syncing $total items", $total );
            
            // Collect all field names for this Pod
            $field_names = array_column($pod_data['fields'], 'name');

            while ( $items->fetch() ) {
                $item_id = $items->id();
                $data_payload = [];

                foreach ( $field_names as $field_name ) {
                    // Force-pull from meta to bypass empty table columns
                    $val = get_post_meta( $item_id, $field_name, true );
                    if ( $val !== '' ) {
                        $data_payload[$field_name] = $val;
                    }
                }

                // Explicitly save the meta data into the table columns
                $api->save_pod_item( [ 
                    'pod'  => $pod_name, 
                    'id'   => $item_id, 
                    'data' => $data_payload 
                ] );
                
                $progress->tick();
            }
            $progress->finish();
        }

        // Index Management
        global $wpdb;
        $table_name = $wpdb->prefix . 'pods_' . $pod_name;
        $summary = ['indexed' => 0, 'removed' => 0];

        foreach ( $pod_data['fields'] as $field ) {
            $name = $field['name'];
            $type = $field['type'];
            $is_eligible = in_array($type, ['pick', 'relationship', 'number', 'float', 'decimal', 'currency']) || in_array($name, $manual_fields);
            $index_name = "idx_" . str_replace('-', '_', $name);

            if ( $reverse ) {
                if ( ! $dry_run ) {
                    $index_exists = $wpdb->get_results( "SHOW INDEX FROM $table_name WHERE Key_name = '$index_name'" );
                    if ( !empty($index_exists) ) {
                        $wpdb->query( "ALTER TABLE $table_name DROP INDEX `$index_name`" );
                        $summary['removed']++;
                    }
                }
            } else if ( $is_eligible ) {
                if ( ! $dry_run ) {
                    $index_exists = $wpdb->get_results( "SHOW INDEX FROM $table_name WHERE Key_name = '$index_name'" );
                    if ( empty( $index_exists ) ) {
                        $wpdb->query( "ALTER TABLE $table_name ADD INDEX `$index_name` (`$name`)" );
                        $summary['indexed']++;
                    }
                } else { $summary['indexed']++; }
            }
        }

        $table_output = [
            (object) [ 'Metric' => 'Pod Name', 'Status' => $pod_name ],
            (object) [ 'Metric' => 'Items Synced', 'Status' => $total ],
            (object) [ 'Metric' => 'Final Storage', 'Status' => $target_storage ],
            (object) [ 'Metric' => 'Indices', 'Status' => $reverse ? "-{$summary['removed']}" : "+{$summary['indexed']}" ]
        ];
        
        \WP_CLI\Utils\format_items( 'table', $table_output, [ 'Metric', 'Status' ] );
        wp_cache_flush();
        WP_CLI::success( $dry_run ? "Simulation finished." : "Deep Migration complete!" );
    } );
WP_CLI::add_command( 'turbo-migrate-pod', function( $args, $assoc_args ) {
        global $wpdb;

        if ( empty( $args ) ) {
            WP_CLI::error( "Usage: wp turbo-migrate-pod <pod_name> [--cleanup]" );
        }

        $pod_name = $args[0];
        $cleanup  = isset( $assoc_args['cleanup'] );
        $api      = pods_api( $pod_name );
        $pod_data = $api->load_pod( [ 'name' => $pod_name ] );

        if ( ! $pod_data ) {
            WP_CLI::error( "Pod '$pod_name' not found." );
        }

        $table_name = $wpdb->prefix . 'pods_' . $pod_name;
        $pod_id     = $pod_data['id'];

        WP_CLI::log( "--- Starting Unified Turbo Sync for $pod_name ---" );

        // 1. Ensure Table Structure & IDs
        if ( ( $pod_data['storage'] ?? 'meta' ) !== 'table' ) {
            $api->save_pod( [ 'id' => $pod_id, 'name' => $pod_name, 'storage' => 'table' ] );
        }
        $wpdb->query( "INSERT IGNORE INTO `$table_name` (id) SELECT ID FROM {$wpdb->posts} WHERE post_type = '$pod_name'" );

        // 2. Process Fields
        foreach ( $pod_data['fields'] as $field ) {
            $f_name   = $field['name'];
            $f_id     = $field['id'];
            $type     = $field['type'];
            $is_multi = ( ( $type === 'pick' || $type === 'relationship' ) && ( $field['options']['pick_format_type'] ?? '' ) === 'multi' );

            if ( $is_multi ) {
                WP_CLI::log( "Syncing Multi-select: $f_name..." );
                
                // Determine the correct junction table
                $rel_table = $wpdb->prefix . 'pods_rel_' . $f_name;
                $is_table_based = (bool) $wpdb->get_var( "SHOW TABLES LIKE '$rel_table'" );
                
                if ( $is_table_based ) {
                    // Custom Junction Table Sync
                    $wpdb->query( $wpdb->prepare( "
                        INSERT IGNORE INTO `$rel_table` (item_id, related_item_id)
                        SELECT post_id, meta_value FROM {$wpdb->postmeta} 
                        WHERE meta_key = %s AND meta_value != ''", $f_name ) );
                } else {
                    // Global Relationship Table Sync
                    $wpdb->query( $wpdb->prepare( "
                        INSERT IGNORE INTO `{$wpdb->prefix}pods_rel` (pod_id, item_id, related_item_id, field_id)
                        SELECT %d, post_id, meta_value, %d FROM {$wpdb->postmeta} 
                        WHERE meta_key = %s AND meta_value != ''", $pod_id, $f_id, $f_name ) );
                }
            } else {
                WP_CLI::log( "Syncing Standard Column: $f_name..." );
                
                // Standard SQL Update
                $wpdb->query( $wpdb->prepare( "
                    UPDATE `$table_name` AS p
                    JOIN {$wpdb->postmeta} AS m ON p.id = m.post_id
                    SET p.`$f_name` = m.meta_value
                    WHERE m.meta_key = %s", $f_name ) );

                // Auto-Index
                if ( in_array($type, ['pick', 'relationship', 'number', 'float', 'decimal', 'currency', 'date']) ) {
                    $index_name = "idx_" . str_replace('-', '_', $f_name);
                    $wpdb->query( "ALTER TABLE `$table_name` ADD INDEX IF NOT EXISTS `$index_name` (`$f_name`)" );
                }
            }
        }

        // 3. Optional Cleanup
        if ( $cleanup ) {
            WP_CLI::confirm( "Delete redundant meta for $pod_name?", $assoc_args );
            $field_names = array_column( $pod_data['fields'], 'name' );
            $meta_keys = implode( "','", array_map( 'esc_sql', $field_names ) );
            $wpdb->query( $wpdb->prepare( "DELETE m FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON m.post_id = p.ID WHERE p.post_type = %s AND m.meta_key IN ('$meta_keys')", $pod_name ) );
        }

        wp_cache_flush();
        WP_CLI::success( "Unified Migration complete!" );
    });
WP_CLI::add_command( 'mafw-rel-integrity', function( $args ) {
        global $wpdb;

        WP_CLI::log( "--- Running Relationship Integrity Report ---" );

        // 1. Check Global Relationship Table (wp_pods_rel)
        $global_orphans = $wpdb->get_results( "
            SELECT r.item_id, r.related_item_id, f.name as field_name, f.type as field_type
            FROM {$wpdb->prefix}pods_rel r
            JOIN {$wpdb->prefix}pods_fields f ON r.field_id = f.id
            LEFT JOIN {$wpdb->posts} p_item ON r.item_id = p_item.ID
            LEFT JOIN {$wpdb->posts} p_rel ON r.related_item_id = p_rel.ID
            WHERE p_item.ID IS NULL OR p_rel.ID IS NULL
        " );

        if ( ! empty( $global_orphans ) ) {
            WP_CLI::warning( "Found " . count( $global_orphans ) . " broken links in the global relationship table." );
            $report = [];
            foreach ( $global_orphans as $orphan ) {
                $report[] = [
                    'Field' => $orphan->field_name,
                    'Ride/Tour ID' => $orphan->item_id,
                    'Broken Target ID' => $orphan->related_item_id,
                    'Type' => $orphan->field_type
                ];
            }
            \WP_CLI\Utils\format_items( 'table', $report, ['Field', 'Ride/Tour ID', 'Broken Target ID', 'Type'] );
        } else {
            WP_CLI::success( "Global relationship table is clean!" );
        }

        // 2. Check Single-Select Integrity in Custom Tables
        // Example: Checking if a 'ride' points to a 'tour' that exists
        $ride_table = $wpdb->prefix . 'pods_ride';
        $broken_tours = $wpdb->get_results( "
            SELECT r.id as ride_id, r.tour as tour_id
            FROM $ride_table r
            LEFT JOIN {$wpdb->posts} p ON r.tour = p.ID
            WHERE r.tour IS NOT NULL AND r.tour != 0 AND p.ID IS NULL
        " );

        if ( ! empty( $broken_tours ) ) {
            WP_CLI::warning( "Found " . count( $broken_tours ) . " rides pointing to non-existent tours." );
        } else {
            WP_CLI::success( "Ride-to-Tour integrity is solid!" );
        }
    });
WP_CLI::add_command( 'mafw-db-purge-orphans', function() {
        global $wpdb;

        // 1. Count orphans first
        $count = $wpdb->get_var("
            SELECT COUNT(pm.meta_id) 
            FROM {$wpdb->postmeta} pm 
            LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id 
            WHERE p.ID IS NULL
        ");

        if ( $count == 0 ) {
            WP_CLI::success( "No orphaned metadata found!" );
            return;
        }

        WP_CLI::log( "Found $count orphaned metadata rows." );
        WP_CLI::confirm( "Do you want to delete these $count orphaned rows?" );

        // 2. The Purge
        $deleted = $wpdb->query("
            DELETE pm FROM {$wpdb->postmeta} pm 
            LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id 
            WHERE p.ID IS NULL
        ");

        WP_CLI::success( "Successfully purged $deleted orphaned metadata rows." );
    });
}
/**
 * Add MAFW Database Health Widget to WordPress Dashboard
 */
add_action('wp_dashboard_setup', 'mafw_register_health_widget');

function mafw_register_health_widget() {
    wp_add_dashboard_widget(
        'mafw_db_health_widget',
        'MAFW System Health: Rides & Tours',
        'mafw_display_health_widget'
    );
}

function mafw_display_health_widget() {
    global $wpdb;
    
    // Define our custom tables
    $tables = [
        'tour' => $wpdb->prefix . 'pods_tour',
        'ride' => $wpdb->prefix . 'pods_ride'
    ];

    echo '<table class="widefat fixed" style="border:none;">';
    echo '<thead><tr><th>Data Type</th><th>Rows</th><th>Size</th><th>Indices</th></tr></thead>';
    echo '<tbody>';

    foreach ($tables as $key => $table) {
        // 1. Get row count and data size
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT TABLE_ROWS, 
            ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024, 2) AS SIZE_KB 
            FROM information_schema.TABLES 
            WHERE TABLE_NAME = %s", $table));

        // 2. Count indices for this table
        $index_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT INDEX_NAME) 
            FROM information_schema.STATISTICS 
            WHERE TABLE_NAME = %s AND INDEX_NAME != 'PRIMARY'", $table));

        $label = ucfirst($key) . ' Table';
        $rows  = $stats ? $stats->TABLE_ROWS : 'N/A';
        $size  = $stats ? $stats->SIZE_KB . ' KB' : 'N/A';

        echo "<tr>
                <td><strong>$label</strong></td>
                <td>$rows</td>
                <td>$size</td>
                <td><span class='dashicons dashicons-database-export' style='color:#46b450;'></span> $index_count</td>
              </tr>";
    }

    echo '</tbody></table>';
    echo '<p style="margin-top:10px; font-style:italic; font-size:11px; color:#666;">';
    echo 'Storage: <strong>Table-Based</strong> | Optimization: <strong>Active</strong>';
    echo '</p>';
}

// add_action('admin_enqueue_scripts', 'custom_prefix_admin_enqueue_scripts');

// add_filter('em_object_get_default_search', function( $defaults ) {
    // $defaults['scope'] = 'all';
    // return $defaults; 
// });
// add_filter('em_content_categories_args', function($args) {
    // $args['scope'] = 'all';
    // return $args;
// });

/* add_filter( 'bp_enqueue_assets_in_bp_pages_only', '__return_true' );

$pmprogl_gift_levels = array(
    4 => array ( 
	    'level_id' => 1,
		'initial_payment' => 0,
		'billing_amount' => 0,
		'cycle_number' => 0,
		'cycle_period' => '',
		'billing_limit' => 0,
		'trial_amount' => 0,
		'trial_limit' => 0,
		'expiration_number' => 1,
		'expiration_period' => 'Year'
	),
	5 => array (
	    'level_id' => 2,
		'initial_payment' => 0,
		'billing_amount' => 0,
		'cycle_number' => 0,
		'cycle_period' => '',
		'billing_limit' => 0,
		'trial_amount' => 0,
		'trial_limit' => 0,
		'expiration_number' => 2,
		'expiration_period' => 'Year'
	),
	6 => array (
	    'level_id' => 3,
		'initial_payment' => 0,
		'billing_amount' => 0,
		'cycle_number' => 0,
		'cycle_period' => '',
		'billing_limit' => 0,
		'trial_amount' => 0,
		'trial_limit' => 0,
		'expiration_number' => 3,
		'expiration_period' => 'Year'
	)
);
*/
if ( ! defined( 'BP_AVATAR_THUMB_WIDTH' ) )
    define( 'BP_AVATAR_THUMB_WIDTH', 50 ); //change this with your desired thumb width

if ( ! defined( 'BP_AVATAR_THUMB_HEIGHT' ) )
    define( 'BP_AVATAR_THUMB_HEIGHT', 50 ); //change this with your desired thumb height

if ( ! defined( 'BP_AVATAR_FULL_WIDTH' ) )
    define( 'BP_AVATAR_FULL_WIDTH', 260 ); //change this with your desired full size,weel I changed it to 260 :)

if ( ! defined( 'BP_AVATAR_FULL_HEIGHT' ) )
    define( 'BP_AVATAR_FULL_HEIGHT', 260 ); //change this to default height for full avatar
?>
