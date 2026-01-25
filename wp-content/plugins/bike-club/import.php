<?php
function bk_ride_paces($str, $pacemap)
{
	$paceids = explode(',', $str);
	$pace_array = array();
    foreach ($paceids as $paceid) {
		if (ord($paceid) >= 64) {
	    	$pid = (ord($paceid)-64);
	    	$pace_array[] = $pacemap[$pid];
		}
    }
	return $pace_array;
}
function bk_update_privacy($user)
{
	$arr = array();
    $arr[1] = "public"; // Name
    $arr[2] = "loggedin"; // gender
    $arr[9] = "loggedin"; // birthday
    $arr[16] = "adminsonly";
    $arr[18] = "adminsonly";
    $arr[20] = "adminsonly";
    $arr[39] = "adminsonly";
    $arr[98] = "adminsonly";
    $arr[99] = "adminsonly";
    $arr[100] = "adminsonly";
    $arr[101] = "adminsonly";
    $arr[102] = "adminsonly";
	if (!empty($user) && array_key_exists('home_phone_show', $user))
        $arr[103] = $user['home_phone_show'] == 'yes' ? "loggedin" : "adminsonly";
	if (!empty($user) && array_key_exists('work_phone_show', $user))
        $arr[104] = $user['work_phone_show'] == 'yes' ? "loggedin" : "adminsonly";
	if (!empty($user) && array_key_exists('cell_phone_show', $user))
        $arr[105] = $user['cell_phone_show'] == 'yes' ? "loggedin" : "adminsonly";
	if (!empty($user) && array_key_exists('fax_phone_show', $user))
        $arr[106] = $user['fax_phone_show'] == 'yes' ? "loggedin" : "adminsonly";
	if (!empty($user) && array_key_exists('emergency_phone_show', $user))
        $arr[107] = $user['emergency_phone_show'] == 'yes' ? "loggedin" : "adminsonly";
    $arr[108] = "loggedin";
    $arr[113] = "adminsonly";
    $arr[114] = "adminsonly";
    $arr[115] = "adminsonly";
    $arr[117] = "adminsonly";
    $arr[136] = "loggedin"; // normally ride at
    $arr[173] = "loggedin"; // help with social events
    $arr[176] = "loggedin"; // help with ride planning
    $arr[179] = "loggedin"; // Off Road
    $arr[180] = "loggedin"; // i ride atb
	if (!empty($user) && array_key_exists('email_show', $user))
        $arr[247] = $user['email_show'] == 'yes' ? "loggedin" : "adminsonly";
    $arr[248] = "adminsonly"; // married
    $arr[250] = "adminsonly"; // comments
    update_user_meta($user['ID'],'bp_xprofile_visibility_levels', $arr);
}
function bk_update_roles($user)
{
	$wpuser = new WP_User($user['ID']);
	if ($user['enddate'] >= time()) {
        $roleList = '"';
		$roleList .=  'active';
	    if (!$wpuser->has_cap('active'))
            $wpuser->add_role('active');
	    if (!$wpuser->has_cap('subscriber'))
            $wpuser->add_role('subscriber');
		$ride_leader_added = 0;
        if (!empty($user['role']))
          foreach ($user['role'] as $role) {
            // echo "role: " . $role . '<br />';
		    switch ($role) {
			    case 2:
					if (!$wpuser->has_cap('membercoordinator'))
					    $wpuser->add_role('membercoordinator');
		            $roleList .=  ',membercoordinator';
				    break;
			    case 3:
					if (!$wpuser->has_cap('rideleader'))
					    $wpuser->add_role('rideleader');
                    if (!$ride_leader_added) {
                        $ride_leader_added = 1;
                        if (!strpos($roleList, "rideleader"))
		                    $roleList .=  ',rideleader';
                    }
				    break;
			    case 4:
					if (!$wpuser->has_cap('ridecoordinator'))
					    $wpuser->add_role('ridecoordinator');
		            $roleList .=  ',ridecoordinator';
				    break;
			    case 5:
					if (!$wpuser->has_cap('ride_planner'))
					    $wpuser->add_role('ride_planner');
		            $roleList .=  ',rideplanner';
				    break;
			    case 6:
					if (!$wpuser->has_cap('safetycoordinator'))
					    $wpuser->add_role('safetycoordinator');
		            $roleList .=  ',safetycoordinator';
				    break;
			    case 7:
					if (!$wpuser->has_cap('newslettereditor'))
					    $wpuser->add_role('newslettereditor');
		            $roleList .=  ',newslettereditor';
				    break;
			    case 8:
					if (!$wpuser->has_cap('treasurer'))
					    $wpuser->add_role('treasurer');
		            $roleList .=  ',treasurer';
				    break;
			    case 9:
					if (!$wpuser->has_cap('president'))
					    $wpuser->add_role('president');
		            $roleList .=  ',president';
				    break;
			    case 10:
					if (!$wpuser->has_cap('rpc'))
					    $wpuser->add_role('rpc');
		            $roleList .=  ',rpc';
				    break;
			    case 11:
					if (!$wpuser->has_cap('librarian'))
					    $wpuser->add_role('librarian');
		            $roleList .=  ',librarian';
				    break;
			}
          }
        $roleList .= '"';
        update_user_meta($user['ID'], "Roles", $roleList);
    }
    else {
        $wpuser->remove_role('active');
        $wpuser->remove_role('librarian');
        $wpuser->remove_role('membercoordinator');
        $wpuser->remove_role('newslettereditor');
        $wpuser->remove_role('ride_coordinator_backup');
        $wpuser->remove_role('ride_planner');
        $wpuser->remove_role('ridecoordinator');
        $wpuser->remove_role('rideleader');
        $wpuser->remove_role('rpc');
        $wpuser->remove_role('safetycoordinator');
        $wpuser->remove_role('treasurer');
    }
}
function bk_create_or_update_user($user, $pacemap)
{
    global $wpdb;
    if (empty($_GET['group']) && $user['ID'] == 0) {
        $user['ID'] = wp_create_user($user['username'], "test", $user['email']);
        if ( is_wp_error($user['ID'])) {
             error_log($user['ID']->get_error_message() . " username:" . $user['username']);
             return;
        }
        $result = $wpdb->update($wpdb->users,
                      array('user_pass' => $user['user_pass']),
                      array('ID' => $user['ID']) );
    }
    $userdata = array('ID' => $user['ID'], 'user_login' => $user['username'], 'user_nicename' => strtolower($user['username']), 'user_email' => $user['email'], 'display_name' => $user['display_name'], 'nickname' => $user['display_name'], 'first_name' => $user['firstname'], 'last_name' => $user['lastname'], 'description' => $user['comments'], 'rich_editing' => 'true', 'user_registered' => $user['date_created'] . ' 00:00:00');
    wp_update_user($userdata);
    // echo "USER:" . $user['ID'] . '<br />';
	$wpuser = new WP_User($user['ID']);
    $wpuser->remove_role('active');
    $wpuser->remove_role('librarian');
    $wpuser->remove_role('membercoordinator');
    $wpuser->remove_role('newslettereditor');
    $wpuser->remove_role('ride_coordinator_backup');
    $wpuser->remove_role('ride_planner');
    $wpuser->remove_role('ridecoordinator');
    $wpuser->remove_role('rideleader');
    $wpuser->remove_role('rpc');
    $wpuser->remove_role('safetycoordinator');
    $wpuser->remove_role('treasurer');
    xprofile_set_field_data(115, $user['ID'], 0);
    update_user_meta($user['ID'], "membership_initial_payment", $user['dues_paid']);
    update_user_meta($user['ID'], "membership_start_date", $user['date_paid']);
    update_user_meta($user['ID'], "membership_enddate",  $user['date_expires']);
    update_user_meta($user['ID'], "membership_status", $user['status']);
    update_user_meta($user['ID'], "membership_id", $user['membertype']);
    update_user_meta($user['ID'], "pending", "1");
    update_user_meta($user['ID'], "dismissed_wp_pointers", "wp496_privacy");
    update_user_meta($user['ID'], "aiowps_account_status", "approved");
    update_user_meta($user['ID'], "dbem_phone", "");
    update_user_meta($user['ID'], "email_users_accept_mass_emails", "true");
    update_user_meta($user['ID'], "email_users_accept_notifications", "true");
    update_user_meta($user['ID'], $wpdb->prefix . "user_level", "0");
    update_user_meta($user['ID'], "Dual Member", "0");
    $active = $user['status'] == 'active' ? 1 : 0;
    // $param = array( 'subscriber' => '1', 'active' => $active, 'rideleader' => $user['ride_leader'], 'bbp_participant' => '1' );
    $param = array();
    if ($active)  {
        $param['active'] = 1;
        $param['subscriber'] = 1;
        $param['bbd_participant'] = 1;
        if ($user['ride_leader'] == 'yes')
            $param['rideleader'] = 1;
    }
    else {
        $param['subscriber'] = 1;
    }
    update_user_meta($user['ID'], $wpdb->prefix . "capabilities", $param);
    update_user_meta($user['ID'], "locale", "");
    // $show_admin_bar = $user['ID'] == "1" || $user['ID'] == "3160" ? "true" : "false";
    update_user_meta($user['ID'], "show_admin_bar_front", "true");
    update_user_meta($user['ID'], "use_ssl", "0");
    update_user_meta($user['ID'], "admin_color", "fresh");
    update_user_meta($user['ID'], "comment_shortcuts", "false");
    update_user_meta($user['ID'], "syntax_highlighting", "true");
    update_user_meta($user['ID'], "rich_editing", "true");
    update_user_meta($user['ID'], "Dual Member",  $user['dual_member']);
    update_user_meta($user['ID'], "old_user_id",  $user['memberID']);
    update_user_meta($user['ID'], "DNS Email",  $user['dns_email'] == 'yes' ? 1 : 0);
    update_user_meta($user['ID'], "session_tokens",  array());
    xprofile_set_field_data(247, $user['ID'], $user['email']);
    xprofile_set_field_data(20, $user['ID'], bk_ride_paces($user['ride_cancel_email'], $pacemap));
    xprofile_set_field_data(9, $user['ID'], $user['date_birth']);
    xprofile_set_field_data(248, $user['ID'], $user['married'] == 'yes' ? array('Yes') : array());
    xprofile_set_field_data(2, $user['ID'], $user['gender']);
    xprofile_set_field_data(1, $user['ID'], $user['display_name']);
    xprofile_set_field_data(173, $user['ID'], $user['social_events'] == 'yes' ? array("I will help with social events") : array());
    xprofile_set_field_data(176, $user['ID'], $user['ride_planning'] == 'yes' ? array("I will help with ride planning") : array());
    xprofile_set_field_data(179, $user['ID'], $user['atb_rider'] == 'yes' ? "Yes" : "No");
    xprofile_set_field_data(136, $user['ID'], $user['ride_pace']);
    xprofile_set_field_data(115, $user['ID'], $user['ride_leader']);
    xprofile_set_field_data(117, $user['ID'], bk_ride_paces($user['rideleader_pace'], $pacemap));
    xprofile_set_field_data(113, $user['ID'], $user['car_license_plate']);
    xprofile_set_field_data(114, $user['ID'], $user['car_license_plate2']);
    xprofile_set_field_data(107, $user['ID'], $user['emergency_phone']);
    xprofile_set_field_data(103, $user['ID'], $user['home_phone']);
    xprofile_set_field_data(104, $user['ID'], $user['work_phone']);
    xprofile_set_field_data(105, $user['ID'], $user['cell_phone']);
    xprofile_set_field_data(106, $user['ID'], $user['fax_phone']);
    xprofile_set_field_data(102, $user['ID'], $user['zip']);
    xprofile_set_field_data(100, $user['ID'], $user['city']);
    xprofile_set_field_data(101, $user['ID'], $user['state']);
    xprofile_set_field_data(98, $user['ID'], $user['address1']);
    xprofile_set_field_data(99, $user['ID'], $user['address2']);
    xprofile_set_field_data(16, $user['ID'], $user['dns_email'] == 'yes' ? array("Do NOT send me ANY email, regardless of my settings below") : array());
    xprofile_set_field_data(18, $user['ID'], $user['news_email'] == 'yes' ? array("Send me news, events, other notifications") : array());
    xprofile_set_field_data(39, $user['ID'], bk_ride_paces($user['adhoc_email'], $pacemap));
    xprofile_set_field_data(108, $user['ID'], $user['preferred_phone']);
    xprofile_set_field_data(250, $user['ID'], "");
    xprofile_set_field_data(315, $user['ID'], "");
    $custom_level = array(
         'cycle_period' => '',
         'initial_payment' => '',
         'cycle_number' => '',
         'billing_limit' => '',
         'user_id' => $user['ID'],
         'membership_id' => $user['membertype'],
         'code_id' => '0',
         'billing_amount' => '0',
         'trial_amount' => '0',
         'trial_limit' => '0',
         'status' => $user['status'],
         'startdate' => $user['date_paid'],
         'enddate' => $user['date_expires'],
         'modified' => date('Y-m-d H:i:s', strtotime("now")));
    pmpro_changeMembershipLevel($custom_level, $user['ID']);
    bk_update_roles($user);
    bk_update_privacy($user);
}
function bk_import_users()
{
    global $wpdb;
    $mysqli = new mysqli("localhost", DB_USER, DB_PASSWORD, DB_NAME);
    if ($mysqli->connect_errno) {
        echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
    }
    $query = 'select paceID, pace_name from club_pace_list';
    $paces = $mysqli->query($query);
    if ($paces) {
        while ($pace = $paces->fetch_object()) {
            $pacemap[$pace->paceID] = $pace->pace_name;
        }
        $paces->close();
    }
    // roles: 1: CM, 2: MC, 3: RL, 4: RC, 5: RP, 6: SC, 7: NE, 8: T
    //        9: PR  10: RPC, 11: L
    //   CM=Club Member, MC = Membership Coordinator, RL=Ride Leader
    //   RC=Ride Coordinator RP=Ride Planner, SC=Safety Coordinator
    //   NE=Newletter Editor L=Librarian T=Treasuref PR=President
    $query = 'select club_role2member_xref.roleID as roleID, role_name, memberID from club_role2member_xref LEFT JOIN club_role_list ON club_role_list.roleID = club_role2member_xref.roleID';
    $members = $mysqli->query($query);
    if ($members) {
        while ($member = $members->fetch_object()) {
            if ($member->roleID > 1) {
                if (empty($rolemap[$member->memberID]))
                    $rolemap[$member->memberID] = array();
                $rolemap[$member->memberID][] = $member->roleID;
            }
        }
        $members->close();
    }
    // echo 'pacemap:<pre>'; print_r($pacemap); echo '</pre>';
    if (empty($_GET['group']))
        $rng = "club_member.memberID >= 10002158";
    else if (intval($_GET['group']) == 1)
        $rng = "club_member.memberID < 10000388";
    else if (intval($_GET['group']) == 2)
        $rng = "club_member.memberID >= 10000388 AND club_member.memberID < 10001035";
    else if (intval($_GET['group']) == 3)
        $rng = "club_member.memberID >= 10001035 AND club_member.memberID < 10001602";
    else if (intval($_GET['group']) == 4)
        $rng = "club_member.memberID >= 10001602 AND club_member.memberID < 10002158";
    $query = 'SELECT club_member.memberID as memberID, club_member.address1 as address1,
    club_member.date_created as date_created,
    club_member.date_updated as date_updated,
    club_member.address2 as address2,
    club_member.adhoc_email as adhoc_email,
    club_member.news_email as news_email,
    club_member.atb_rider as atb_rider,
    club_member.car_license_plate as car_license_plate,
    club_member.car_license_plate2 as car_license_plate2,
    club_member.city as city,
    club_member.comments as comments,
    club_member.date_birth as date_birth,
    club_member.date_expires as date_expires,
    club_member.date_joined as date_joined,
    club_member.date_paid as date_paid,
    club_member.dues_paid as dues_paid,
    club_member.dns_email as dns_email,
    club_member.dual_member as dual_member,
    club_member.email as email,
    club_member.emergency_phone as emergency_phone,
    club_member.cell_phone as cell_phone,
    club_member.fax_phone as fax_phone,
    club_member.firstname as firstname,
    club_member.gender as gender,
    club_member.home_phone as home_phone,
    club_member.lastname as lastname,
    club_member.marital_status as married,
    club_member.memberID as memberID,
    club_member.preferred_phone as preferred_phone,
    club_member.ride_cancel_email as ride_cancel_email,
    club_member.ride_leader as ride_leader,
    club_member.ride_paceID as ride_paceID,
    club_member.rideleader_pace as rideleader_pace,
    club_member.ride_planning as ride_planning,
    club_member.social_events as social_events,
    club_member.atb_rider as atb_rider,
    club_member.state as state,
    club_member.status as status,
    club_member.username as username,
    club_member.work_phone as work_phone,
    club_member.zip as zip,
    club_member.home_phone_show as home_phone_show,
    club_member.work_phone_show as work_phone_show,
    club_member.cell_phone_show as cell_phone_show,
    club_member.fax_phone_show as fax_phone_show,
    club_member.emergency_phone_show as emergency_phone_show,
    club_member.email_show as email_show,
    mos_users.password as user_pass 
    FROM club_member LEFT JOIN mos_users ON club_member.memberID = mos_users.id WHERE ' . $rng . ' ORDER BY club_member.memberID';
    // echo $query . '<br />';
    $users = $mysqli->query($query);
    if ($users) {
        while ($user = $users->fetch_assoc()) {
        
            // echo 'id: ' . $user['ID'] . ' dns_email: ' . $user['dns_email'] . '<br />';
            $user['ID'] = "0";
            $query = "SELECT user_id FROM " . $wpdb->prefix . "usermeta WHERE meta_key = 'old_user_id' AND meta_value = '" . $user['memberID'] . "' LIMIT 1";
            $result = $mysqli->query($query);
            if ($result && $result->num_rows == 1) {
                $row = $result->fetch_object();
                $user['ID'] = $row->user_id;
                $result->close();
            }
            if (!empty($user['dual_member']) && $user['dual_member'] != "0") {
                $query = "SELECT user_id FROM " . $wpdb->prefix . "usermeta WHERE meta_key = 'old_user_id' AND meta_value = '" . $user['dual_member'] . "' LIMIT 1";
                $result = $mysqli->query($query);
                if ($result && $result->num_rows == 1) {
                    $row = $result->fetch_object();
                    $user['dual_member'] = $row->user_id;
                    $result->close();
                }
            }
            $user['display_name'] = $user['firstname'] . ' ' . $user['lastname']; 
            $id = $user['ID'];
	        $user['enddate'] = strtotime($user['date_expires']);
            $user['startdate'] = strtotime($user['date_paid']);
            // $msg = 'Processing: ' . $user['display_name'] . ': ' . $user['memberID'] . ': ' . $user['ID']';
            // error_log( 'Processing: ' . $user['display_name'] . ': ' . $user['memberID'] . ': ' . $user['ID'] );
            $diff = round(($user['enddate'] - $user['startdate']) / 3600 / 24 /365);
            if ($diff > 3) $diff = 3;
            $user['membertype'] = $diff;
            if (!empty($user['dual_member'])) {
		        $user['membertype'] += 3;
            }
            if (!empty($user['ride_paceID']) && $user['ride_paceID'] != 0)
                $user['ride_pace'] = $pacemap[$user['ride_paceID']];
            else
                $user['ride_pace'] = '';
	        $user['birthdate'] = $user['date_birth'] . ' 00:00:00';
            if ($user['enddate'] < time()) {
                $user['ride_leader'] = array();
                $user['role'] = array();
                $user['status'] = 'inactive';
                $user['membertype'] = 0;
            }
            else {
                if (empty($rolemap[$user['memberID']]))
                    $user['role'] = array();
				else
				    $user['role'] = $rolemap[$user['memberID']];
                if ($user['ride_leader'] == 'yes')
                    $user['ride_leader'] = array("I am a Ride Leader");
                else
                    $user['ride_leader'] = array();
            }
            if ($id != 1 && $id != 24 && $id != 3151 && $user['email'] != "" && $user['email'] != "none")
               bk_create_or_update_user($user, $pacemap);
        }
        // $users->close();
        echo 'Import Done';
    }
    else
        echo 'No Users<br />';
}
add_shortcode('bike_import_users', 'bk_import_users');

function bk_upd_users_dismiss()
{
    global $wpdb;
    $mysqli = new mysqli("localhost", DB_USER, DB_PASSWORD, DB_NAME);
    if ($mysqli->connect_errno) {
        echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
    }
    $query = "SELECT ID FROM " . $wpdb->prefix . "users";
    $users = $mysqli->query($query);
    if ($users) {
        while ($user_id = $users->fetch_assoc()) {
            if (user_can($user_id['ID'], 'manage_options'))
                add_user_meta( $user_id['ID'], 'dud_exclude_user_addon_notice_dismissed', 'true', true );
                  // echo $user_id['ID'] . '<br />';
		}
    }
}

function bike_add_attendee($userid, $rideid, $status, $car_license, $emergency_phone)
{
    if ($status == "no")
        $status = "No";
    if ($status == "yes")
        $status = "Yes";
    if ($status == "maybe")
        $status = "Maybe";
    $cell_phone = xprofile_get_field_data('Mobile Phone', $userid);
    $pod = pods('ride', $rideid);
    $attendees = $pod->field('attendees');
    foreach ($attendees as $attendee) {
        $user = get_post_meta($attendee['ID'], 'rider', true);
    }
    $user = pods('user', $userid);
    $data = array();
    $data['title'] = $rideid . ' - ' . $user->field('display_name');
    $data['rider'] = $userid;
    $data['ride_attendee_status'] = $status;
    $data['car_license'] = empty($car_licenes) ? "" : $car_license;
    $data['emergency_phone'] = empty($emergency_phone) ? "" : $emergency_phone;
    $data['cell_phone'] = empty($cell_phone) ? "" : $cell_phone;
    if ($status == "Yes" || $status == "Maybe") {
        $apod = pods('ride-attendee');
        $aid = $apod->add($data);
        $pod->add_to('attendees', $aid); 
	    return "aid:" . $aid . " status:" . $status . " " . $data['title'] . "<br>";
    }
	return "";
}
function bk_get_attendees($mysqli, $oldrideid, $rideid, $usermap)
{
     $ret = "";
     $attendees = array();
     $query = "SELECT memberID, status, emergency_phone, car_license_plate FROM `club_ride_attendees` WHERE rideID = " . $oldrideid;
     $attendees = $mysqli->query($query);
     if ($attendees) {
         while ($attendee = $attendees->fetch_object()) {
             if ($attendee->memberID != 0 && array_key_exists($attendee->memberID, $usermap)) {
                 $userid = $usermap[$attendee->memberID];
                 if ($userid)
                     $ret .= "rideid:" . $rideid . " userid:" . $userid;
                     $ret .= bike_add_attendee($userid, $rideid, $attendee->status, $attendee->car_license_plate, $attendee->emergency_phone);
             }
         }
     }
     return $ret;
}
add_shortcode('bike_import_attendees', 'bk_import_attendees');
function bk_import_attendees()
{
    $ret = "";
    $mysqli = new mysqli("localhost", DB_USER, DB_PASSWORD, DB_NAME);
    if ($mysqli->connect_errno) {
        $ret .= "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
    }
    $query = "SELECT rideID, date_start FROM `club_ride_list` WHERE CAST(date_start AS DATE) >= '2020-03-01'";
    $rides = $mysqli->query($query);
    if ($rides) {
        $usermap = bk_get_usermap();
        while ($ride = $rides->fetch_object()) {
             $params = array( 'where' => 'old_ride_id.meta_value = ' . $ride->rideID);
             $rpod = pods('ride', $params);
             $rideID = $rpod->field('ID');
             $ret .= bk_get_attendees($mysqli, $ride->rideID, $rideID, $usermap);
        }
    }
	return $ret;
}
function bk_get_usermap()
{
    $params = array(
        'limit' => -1
    );
    $user = pods('user');
    $user->find($params);
    if ($user->total() > 0) {
        while ($user->fetch()) {
            $userid = intval($user->field('old_user_id'));
            if ($userid > 0) {
                $usermap[$userid] = $user->field('ID');
		    }
        }
    }
	$usermap[1480] = 5195;
    return $usermap;
}
add_shortcode('bike_import_rides', 'bk_import_rides');
// add_action('bike_import_rides', bk_import_rides, 10, 2);
function bk_import_rides($offset, $limit)
{
    if (!class_exists('Pods'))
        return;
    $ret = "";
    $params = array(
        'where' => 'CAST(ride_date.meta_value AS DATE) > "2019-12-01"',
        'orderby' => 'CAST(ride_date.meta_value AS DATE)',
        'limit' => -1
    );
    $pod = pods('ride');
    $pod->find($params);
    if ($pod->total() > 0) {
        while( $pod->fetch()) {
            remove_action('pods_api_post_save_pod_item_ride', 'bike_ride_post_save_function');
            $pod->delete();
            add_action('pods_api_post_save_pod_item_ride', 'bike_ride_post_save_function', 10, 3);
        }
    }
    $params = array(
        'limit' => -1
    );
    $pod = pods('tour');
    $pod->find($params);
    if ($pod->total() > 0) {
       while( $pod->fetch()) {
           $tourno = $pod->field('tour_number');
           $tournomap[$tourno] = $pod->field('ID');
       }
    }
    $pace = pods('pace');
    $pace->find($params);
    if ($pace->total() > 0) {
        while ($pace->fetch()) {
            $pacename = $pace->field('post_title');
            $pacemap[$pacename] = $pace->field('ID');
        }
    }
    $usermap = bk_get_usermap();
    $mysqli = new mysqli("localhost", DB_USER, DB_PASSWORD, DB_NAME);
    if ($mysqli->connect_errno) {
        $ret .= "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
    }
    $query = "SELECT club_ride_list.rideID as rideID, club_ride_list.status as status, club_tour_list.tourID as tourID, club_tour_list.tour_name as tour_name, club_pace_list.pace_name as pace_name, club_ride2leader_xref.memberID as leaderID, date_start, club_ride_list.created_byID as created_byID, club_ride_list.date_created as date_created, club_ride_list.date_updated as date_updated, substitute_requested, rider_count, substitute, substitute_requested, comments, ad_hoc, leader_pace, nosignup_cancel FROM `club_ride_list` LEFT JOIN club_tour_list ON club_ride_list.tourID = club_tour_list.tourID LEFT JOIN club_pace_list ON club_ride_list.paceID = club_pace_list.paceID LEFT JOIN club_ride2leader_xref ON club_ride2leader_xref.rideID = club_ride_list.rideID WHERE club_ride2leader_xref.memberID != 0 AND club_ride2leader_xref.memberID != 1480 AND date_start >= '2019-12-01'"; // club_ride_list.rideID >= 49838";// ORDER BY date_start DESC LIMIT 17500,500"; // . $offset . "," . $limit;
    $rides = $mysqli->query($query);
    $cnt = 0;
    if ($rides) {
        while ($ride = $rides->fetch_object()) {
             $cnt++;
             $args = array(
                  'post_type' => 'ride',
                  'meta_key' => 'old_ride_id',
                  'meta_query' => array( array(
                       'key' => 'old_ride_id',
                       'value' => $ride->rideID,
                       'compare' => '='
                  ) )
             );
             $mquery = new WP_Query( $args );
             if ($mquery->have_posts())
                 continue;
			 if ($ride->leaderID != 0 && array_key_exists($ride->leaderID, $usermap))
			     $rideleaderID = $usermap[$ride->leaderID];
		     else
			     $rideleaderID = 0;
			 if ($ride->created_byID != 0 && array_key_exists($ride->created_byID, $usermap))
			     $created_byID = $usermap[$ride->created_byID];
		     else
			     $created_byID = 0;
			 $tz = new DateTimeZone(wp_timezone_string());
			 $gmt = new DateTimeZone("UTC");
			 $datetime_obj = new DateTime($ride->date_start, $tz);
	         $rl = pods('user', $rideleaderID);
	         $username = $rl->field('display_name');
			 $datetime = $datetime_obj->format("D, M dS Y h:ia");
			 $post_title = $datetime . ' : ' . $ride->tourID . " " . $ride->tour_name . " " . $ride->pace_name . " " . $username;
			 $date = $datetime_obj->format("Y-m-d");
			 $time = $datetime_obj->format("H:i:s");
			 $cr_datetime_obj = new DateTime($ride->date_created, $tz);
			 $cr_date = $cr_datetime_obj->format("Y-m-d H:i:s");
			 $gmt_cr_datetime_obj = new DateTime($ride->date_created, $gmt);
			 $gmt_cr_date = $gmt_cr_datetime_obj->format("Y-m-d H:i:s");
			 $mod_datetime_obj = new DateTime($ride->date_created, $tz);
			 $mod_date = $mod_datetime_obj->format("Y-m-d H:i:s");
			 $gmt_mod_datetime_obj = new DateTime($ride->date_created, $gmt);
			 $gmt_mod_date = $gmt_mod_datetime_obj->format("Y-m-d H:i:s");
             if ($ride->status == "scheduled") {
                 if (stristr($ride->ride_comments, "canceled") ||
                         stristr($ride->ride_comments, "cancelled"))
                     $status = 2;
                 else
                     $status = 0;
             }
             else if ($ride->status == "proposed")
                 $status = 1;
             else if ($ride->status == "cancelled")
                 $status = 2;
             else if ($ride->status == "denied")
                 $status = 3;
             else if ($ride->status == "ridden")
                 $status = 4;
             $pod = pods('ride'); 
             $data = array(
                 'post_title' => $post_title,
                 'time' => $time,
                 'rider_count' => $ride->rider_count,
                 'substitute' => $ride->substitute == 'yes' ? 1 : 0,
                 'substitute_requested' => $ride->substitute == 'yes' ? 1 : 0,
                 'tour' =>  $tournomap[$ride->tourID],
                 'pace' => $pacemap[$ride->pace_name],
                 'ride_leader' => $rideleaderID,
                 'ride-status' => $status,
                 'ride_comments' => $ride->comments,
                 'ride_date' => $date,
                 'post_author' => $created_byID,
                 'post_date' => $cr_date,
                 'post_date_gmt' => $gmt_cr_date,
                 'post_modified' => $mod_date,
                 'post_modified_gmt' => $gmt_mod_date,
				 'old_ride_id' => $ride->rideID,
				 'ad_hoc' => $ride->ad_hoc == 'yes' ? 1 : 0
			 );
			 $rideid = $pod->add($data);
             $apod = pods('ride', $rideid);
             remove_action('pods_api_post_save_pod_item_ride', 'bike_ride_post_save_function');
             $apod->save('post_name', $rideid);
             add_action('pods_api_post_save_pod_item_ride', 'bike_ride_post_save_function', 10, 3);
			 $guid = get_site_url() . '/ride/' . $rideid . '/';
             global $wpdb;
			 $wpdb->update( $wpdb->posts, array('guid' => $guid, 'comment_status' => 'open'), array('ID' => $rideid));
             $ret .= "rideid:" . $rideid . ' title:' . $post_title . '<br>';
             // $ret .= bk_get_attendees($mysqli, $ride->rideID, $rideid, $usermap);
        }
        $rides->close();
        $ret .= "total:" . $cnt . '<br>';
    }
    else $ret .= "No Rides Found";
    return $ret;
}
add_shortcode('bike_update_rides', 'bk_update_rides');
function bk_update_rides()
{
    if (!class_exists('Pods'))
        return "no pods";
    $usermap = bk_get_usermap();
    $ret = "";
    $mysqli = new mysqli("localhost", DB_USER, DB_PASSWORD, DB_NAME);
    if ($mysqli->connect_errno) {
        $ret .= "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
    }
    $query = "SELECT club_ride_list.rideID as rideID, club_ride_list.status as status, date_start, club_ride_list.created_byID as created_byID, club_ride_list.date_created as date_created, club_ride_list.date_updated as date_updated, club_ride_list.comments as comments, club_ride_list.rider_count as rider_count FROM `club_ride_list` JOIN club_ride2leader_xref ON club_ride2leader_xref.rideID = club_ride_list.rideID WHERE club_ride2leader_xref.memberID != 0 AND club_ride2leader_xref.memberID != 1480 ORDER BY rideID ASC LIMIT 17500,500";
    $rides = $mysqli->query($query);
    $cnt = 0;
    if ($rides) {
        while ($ride = $rides->fetch_object()) {
             $cnt++;
			 /* $tz = new DateTimeZone(wp_timezone_string());
			 $gmt = new DateTimeZone("UTC");
			 $cr_datetime_obj = new DateTime($ride->date_created, $tz);
			 $cr_date = $cr_datetime_obj->format("Y-m-d H:i:s");
			 $gmt_cr_datetime_obj = new DateTime($ride->date_created, $gmt);
			 $gmt_cr_date = $gmt_cr_datetime_obj->format("Y-m-d H:i:s");
			 $mod_datetime_obj = new DateTime($ride->date_created, $tz);
			 $mod_date = $mod_datetime_obj->format("Y-m-d H:i:s");
			 $gmt_mod_datetime_obj = new DateTime($ride->date_created, $gmt);
			 $gmt_mod_date = $gmt_mod_datetime_obj->format("Y-m-d H:i:s");
			 if ($ride->created_byID != 0 && array_key_exists($ride->created_byID, $usermap))
			     $created_byID = $usermap[$ride->created_byID];
		     else
			     $created_byID = 0; */
             $params = array( 'where' => 'old_ride_id.meta_value = ' . $ride->rideID);
             if ($ride->status == "scheduled") {
                 if (stristr($ride->comments, "canceled") ||
                         stristr($ride->comments, "cancelled"))
                     $status = 2;
                 else if ($rider_count > 0)
                     $status = 4;
                 else
                     $status = 0;
             }
             else if ($ride->status == "proposed")
                 $status = 1;
             else if ($ride->status == "cancelled")
                 $status = 2;
             else if ($ride->status == "denied")
                 $status = 3;
             else if ($ride->status == "ridden")
                 $status = 4;
             $rpod = pods('ride', $params);
             /* $data = array(
                 'post_author' => $created_byID,
                 'post_date' => $cr_date,
                 'post_date_gmt' => $gmt_cr_date,
                 'post_modified' => $mod_date,
                 'post_modified_gmt' => $gmt_mod_date,
			 ); */
             $data = array(
                 'ride-status' => $status,
                 'rider_count' => $rider_count
             );
             if (0 < $rpod->total()) {
                 while ($rpod->fetch()) {
                     remove_action('pods_api_post_save_pod_item_ride', 'bike_ride_post_save_function');
                     $rpod->save($data);
                     add_action('pods_api_post_save_pod_item_ride', 'bike_ride_post_save_function', 10, 3);
                 }
             }
        }
        $ret .= "total:" . $cnt . '<br>';
    }
    else $ret .= "No Rides Found";
    return $ret;
}

add_shortcode('bike_set_roles', 'bk_set_roles');
function bk_set_roles()
{
    global $wpdb;
    $ret = "start<br>";
    $mysqli = new mysqli("localhost", DB_USER, DB_PASSWORD, DB_NAME);
    if ($mysqli->connect_errno) {
        $ret .= "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
    }
    $query = 'SELECT user_id, club_role2member_xref.roleID as roleID FROM ' . $wpdb->prefix . 'usermeta LEFT JOIN club_member ON club_member.memberID = ' . $wpdb->prefix . 'usermeta.meta_value LEFT JOIN club_role2member_xref ON club_role2member_xref.memberID = club_member.memberID WHERE ' . $wpdb->prefix . 'usermeta.meta_key = "old_user_id" AND club_member.status = "active" AND ( club_role2member_xref.roleID = 11 OR ( club_role2member_xref.roleID >= 2 AND club_role2member_xref.roleID < 10 ) )';
    $users = $mysqli->query($query);
    if ($users) {
        while ($user = $users->fetch_assoc()) {
            $ret .= "userid:" . $user['user_id'] . ' role:' . $user['roleID'] . '<br>';
	        $wpuser = new WP_User($user['user_id']);
		    switch ($user['roleID']) {
			    case 2:
					// if (!$wpuser->has_cap('membercoordinator'))
					    // $wpuser->add_role('membercoordinator');
				    break;
			    case 3:
					if (!$wpuser->has_cap('rideleader'))
					    $wpuser->add_role('rideleader');
				    break;
			    case 4:
					if (!$wpuser->has_cap('ridecoordinator'))
					    $wpuser->add_role('ridecoordinator');
				    break;
			    case 5:
					if (!$wpuser->has_cap('ride_planner'))
					    $wpuser->add_role('ride_planner');
				    break;
			    case 6:
					if (!$wpuser->has_cap('safetycoordinator'))
					    $wpuser->add_role('safetycoordinator');
				    break;
			    case 7:
					if (!$wpuser->has_cap('newslettereditor'))
					    $wpuser->add_role('newslettereditor');
				    break;
			    case 8:
					if (!$wpuser->has_cap('treasurer'))
					    $wpuser->add_role('treasurer');
				    break;
			    case 9:
					if (!$wpuser->has_cap('president'))
					    $wpuser->add_role('president');
				    break;
			    case 10:
					if (!$wpuser->has_cap('rpc'))
					    $wpuser->add_role('rpc');
				    break;
			    case 11:
					if (!$wpuser->has_cap('librarian'))
					    $wpuser->add_role('librarian');
				    break;
			}
        }
    }
    return $ret;
}
?>
