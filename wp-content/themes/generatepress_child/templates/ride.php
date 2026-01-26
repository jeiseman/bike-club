<?php
// function attendees_cmp_func($a, $b)
// {
    // return $a['ID'] - $b['ID'];
// }
//Call this with the shown parameters (make sure $time and $end are integers and in Unix timestamp format!)
//Get a link that will open a new event in Google Calendar with those details pre-filled
function bk_make_google_calendar_link($name, $begin, $end, $location, $details) {
	$tz = new DateTimeZone('UTC');
	$t = new DateTime('@' . $begin, $tz);
	$stdate = $t->format('Ymd\THis\Z');
	unset($t);
	$t = new DateTime('@' . $end, $tz);
	$enddate = $t->format('Ymd\THis\Z');
	unset($t);
	$url = 'https://www.google.com/calendar/render?action=TEMPLATE&text=' . urlencode($name);
	$url .= '&dates=' . $stdate . "/" . $enddate;
	if (!empty($location))
	    $url .= '&location=' . $location;
	$url .= '&details=' . urlencode($details);
	$url .= '&sf=true&output=xml';
    return $url;
}

if (!current_user_can('active') || !function_exists('xprofile_get_field_data')) {
   // echo '<h3>You need to <a href="#elementor-action%3Aaction%3Dpopup%3Aopen%26settings%3DeyJpZCI6IjE3NzA2IiwidG9nZ2xlIjpmYWxzZX0%3D">login</a> to view this page</h3>';
   $url =  "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
   $url = urlencode($url);
   echo '<h3>You need to <a href="https://mafw.org/wp-login.php?redirect_to=' . $url . '" class="member-login">login</a> to view this page</h3>';
   return;
}
if (!empty($_GET['riderole']))
    $role = $_GET['riderole'];
else
    $role = "";
if (!is_numeric($role) || $role < 0 || $role > 2)
    $role = 0;
if (!current_user_can('administrator'))
    if (($role == 1 && !current_user_can("rideleader")) ||
         ($role == 2 && !current_user_can("ridecoordinator")))
        $role = 0;
$postid = get_the_ID();
$pod = pods('ride', $postid);
$rl = $pod->field('ride_leader');
$rlpod = pods('user', $rl['ID']);
if ($role == 2) {
    // ride coordinator
    echo '<h1>Update Ride</h1>';
    gravity_form(7, false, false, false, array('role' => '2', 'rideedit' => 'Edit'));
    return;
}
else if ($rlpod->field("ID") == 5658 && current_user_can('rideleader')) {
    $tpod = $pod->field('tour');
    $tourfield = $pod->field('tour');
    if (null !== $tourfield) {
        $tourid = $tourfield['ID'];
        $tpod = pods('tour', $tourid);
        $tournum = $tpod->field('tour_number');
        $tourfieldval = $tournum . "-" . $tpod->field('post_name');
    }
    else
        $tourfieldval = "";
    // Proposed ride leader signup
    gravity_form(32, true, false, false, array('riderole' => 1, 'rideedit' => 'Edit', 'rideid' => $rl['ID'], 'tour' => $tourfieldval));
    return;
}
$ridestatus = $pod->field('ride-status');
$datestr = $pod->display('ride_date') . ' ' . $pod->display('time');
$tz = new DateTimeZone(wp_timezone_string());
$start_time = DateTime::createFromFormat("m/d/Y h:i a", $datestr, $tz);
$sttime = $start_time->getTimeStamp();
$heading = "<b>Starts:</b> ";
$heading = "<b>Starts:</b> ";
if ($ridestatus == 2) { // canceled
   $heading = "<b>Ride canceled, was scheduled for</b> ";
}
else if ($ridestatus == 4) {
   $heading = "<b>Ridden on</b> ";
}
$limit = $pod->field('maximum_signups');
$limited = 0;
if (empty($limit) || $limit < 1) {
    $limit = 10;
}
else
    $limited = 1;
$curr_userid = get_current_user_id();
$rl_phone = strip_tags(xprofile_get_field_data('Mobile Phone', $rl['ID']));
if ($role == 1 && $rlpod->field("ID") == $curr_userid) {
    // ride leader
    echo '<h1>Update Ride</h1>';
    echo '<h2>' . $pod->display('post_title') . '</h2>';
    echo '<p><a class="button" href="' . get_site_url() . '/ride-leader-email-broadcast/?ridenum=' . $postid . '">Send Email to Sign-ins</a></p>';
    gravity_form(21, false, false, false, array('role' => '1', 'rideedit' => "Edit"));
    return;
}
$tourfield = $pod->field('tour');
if (null !== $tourfield) {
    $tourid = $tourfield['ID'];
    $tpod = pods('tour', $tourid);
    $tournum = $tpod->field('tour_number');
    $start = $tpod->field('start_point');
    $terrain = $tpod->field('tour-terrain');
}
else {
	$tourid = "";
    $tournum = "";
    $start = false;
    $terrain = "";
}
$rideid = $pod->field('ID');
$license = "";
$emergency_phone = "";
$cell_phone = "";
$loc = "";
if ($start) {
    $startid = $start['ID'];
    $stpod = pods( 'start_point', $startid );
    $lon = $stpod->display('longitude');
    $lat = $stpod->display('latitude');
    $loc = $lat . "," . $lon;
}
$pace = $pod->display('pace');
$ridelink = '<a href="' . get_site_url() . '/ride/' . $postid . '/">Ride Detail</a>';
$gcalurl = bk_make_google_calendar_link('MAF' . $tournum . ' Bike Ride: ' . $pace . " " . $pod->display('ride_leader'), $sttime, $sttime, $loc, $ridelink);
$gcallink = '<a href="' . $gcalurl . '" target="_blank">' . 'Add to Google Calendar</a>';
$icallink = '<a href="' . get_feed_link('ical') . '?id=' . $postid . '"> Add to iCal (or Download ics)</a>';
if ($rlpod->field("ID") == $curr_userid && current_user_can('rideleader'))
    $broadcastlink = '<a target="_blank" class="button" href="' . get_site_url() . '/ride-leader-email-broadcast/?ridenum=' . $postid . '">Send Email to Sign-ins</a>';
if (!empty($terrain)) {
    // $ptpage = "#elementor-action%3Aaction%3Dpopup%3Aopen%20settings%3DeyJpZCI6IjMzOTM2IiwidG9nZ2xlIjpmYWxzZX0%3D";
    $ptpage = '<a href="#" class="pace-terrain">';
    $terrain_name = $terrain['post_title'];
    $terrain_link = $ptpage . $terrain_name . '</a>';
}
else {
    $terrain_link = "";
	$ptpage = "";
}
if ( !empty($pace)) {
    $pace_link = $ptpage . $pace . '</a>';
}
else {
    $pace_link = "";
}
$miles = intval($tpod->field('miles'));
$climb = intval($tpod->field('climb'));
$mystatus = "No";
$ras = "";
$hazards = $tpod->field('road_closures');
$ridercount = 0;

$aid = 0;
if (function_exists('bk_get_signup_list')) {
	$rideattendee_array = array();
	$wlattendees = array();
    $ridercount = bk_get_signup_list($pod, $rideattendee_array, $wlattendees, $aid);
	$remaining =  $limit - $ridercount;
	if ($remaining < 0)
	    $remaining = 0;
}
if (!empty($rideattendee_array))
	$ras = implode(", ", $rideattendee_array);
if ($aid) {
    $apod = pods('ride-attendee', $aid);
    $mystatus =  $apod->field('ride_attendee_status');
    $wlnum = $apod->field('wait_list_number');
    if (empty($wlnum) || $wlnum == 0)
	    $is_waitlisted = false;
    else
	    $is_waitlisted = true;
    $license = $apod->field('car_license');
    $emergency_phone = $apod->field('emergency_phone');
    $cell_phone = $apod->field('cell_phone');
}
$wlas = "";
if (!empty($wlattendees))
    $wlas = implode(", ", $wlattendees);
$pattern = "/^<p>(.*)<\/p>$/";
$ride_comments = $pod->display('ride_comments');
$ride_comments = preg_replace($pattern, '${1}', $ride_comments);
$tour_comments = $tpod->display('tour_comments');
$tour_comments = preg_replace($pattern, '${1}', $tour_comments);
if (function_exists('bk_tour_description'))
    $tour_description = bk_tour_description($tpod);
else
    $tour_description = $tpod->display('tour_description');
$tour_description = preg_replace($pattern, '${1}', $tour_description);
if ($start) {
   $url = get_site_url() . '/start-point/' . $start['post_name'] . '/';
   $startloc = '<a href="' . esc_url($url) . '">' . $start['post_title'] . '</a>';
}
else {
	$startloc = "";
}
$tourno = $tpod->field('tour_number');
$mapurl = $tpod->field('tour_map');
$cue = $tpod->field('cue_sheet_number');
if (empty($emergency_phone))
    $emergency_phone = strip_tags(xprofile_get_field_data('Emergency Number', $curr_userid));
if (empty($cell_phone))
    $cell_phone = strip_tags(xprofile_get_field_data('Mobile Phone', $curr_userid));
if (empty($license))
    $license = xprofile_get_field_data('Vehicle License', $curr_userid);
echo '<form><input type="hidden" id="rideID" name="rideID" value="' . $postid . '"></form>
';
?>
<div class="ridepage_content">
<div class="ride_description">
<h2>Ride Detail</h2>
   <ul style="list-style-type:none; margin-left: 5px;">
   <li><b>Tour: </b>
   <a href="<?php echo get_site_url(); ?>/tour/<?php echo $tpod->field('post_name'); ?>/"><?php echo $tpod->field('tour_number') . ' - ' . $tpod->field('post_title');?></a></li>
   <li><?php echo $heading . $pod->display('time') . " " . date("D", strtotime($pod->display('ride_date'))) . " " . $pod->display('ride_date'); ?></li>
   <li><b>From: </b><?php echo $startloc; ?></li>
   <li><b>Pace: </b><?php echo $pace_link;?> <b>Terrain: </b> <?php echo $terrain_link;?> <b>Miles: </b><?php echo $miles;?> <b>Climb: </b><?php echo $climb?>, <?php echo $miles > 0 ? round($climb/$miles) : 0; ?>ft/mi</li>
   <li><b>Links: </b><?php echo bike_cue_links($cue, $tourno, $mapurl); ?></li>
   <li><b>Leader: </b><a href="<?php echo get_site_url(); ?>/members/<?php echo $rlpod->field('user_nicename') . '/profile/';?>"><?php echo $pod->display('ride_leader');?></a> <b>Cell: </b><a href="tel:<?php echo $rl_phone . '">' . $rl_phone;?></a></li>
   <li><b>Riders <?php echo $ridercount; ?>: </b><span id="rideAttendees"><?php echo $ras; ?></span></li>
   <?php if (!empty($wlas)) { ?>
   <li><b>Wait list: </b><span id="waitList"><?php echo $wlas; ?></span></li>
   <?php } ?>
   <?php if ($remaining < 40) { ?>
   <li><b>Signups Remaining: </b><span id="signups-remaining"><?php echo $remaining; ?></span></li>
   <?php } ?>
   <?php if (!empty($ride_comments)) { ?>
   <li><b>Comments: </b><?php echo $ride_comments;?></li>
   <?php } ?>
   <li><b>Description: </b><?php echo $tour_description;?></li>
   <?php if (!empty($tour_comments)) { ?>
   <li><b>Notes: </b><?php echo $tour_comments;?></li>
   <?php } ?>
   <?php if (!empty($gcallink) && !empty($icallink)) {
       echo '<br />' . $gcallink . "<br />" . $icallink;
   } ?>
   <br /><br /><button class="bk-weather">Click here for the current weather</button>
   <?php if (!empty($broadcastlink)) {
       echo '<br />' . $broadcastlink;
   } ?>
   <!-- <li><b>Ride Number: </b><?php echo $postid; ?></li> -->
   </ul>
   </dl>
   </div>
   <div class="ride_attend">
   <?php
	   $ridefull = false;
	   // $min_time = new DateTime('now + 2 hours', $tz);
	   $min_time = new DateTime('now', $tz);
	   $allow_signup = true;
	   if ($start_time < $min_time) {
           // echo "<h2>No signups permitted less than 2 hours before the start of the ride.</h2>";
           echo "<h2>No signups permitted after the start of the ride.</h2>";
		   $allow_signup = false;
	   }
       else if ($rlpod->field("ID") == 5658) {
           echo "<h2>No signups permitted for proposed rides</h2>";
           // if ( current_user_can("rideleader") ) {
		       // echo do_shortcode('[bk-become-leader]');
		   // }
           $allow_signup = false;
       }
       else if (function_exists('rider_signupcheck') && ($other_postid = rider_signupcheck($start_time, $rideid)) != 0 && $other_postid > 0) {
         echo '<h2>You are already signed up for a ride within 3 hours of this ride: <a href="' . get_site_url() . '/ride/' . $other_postid . '">existing ride</a></h2>';
		 $allow_signup = false;
	   }
	   else if ($ridestatus == 2) { // canceled
         echo '<h2>Ride canceled, no signups allowed.</h2>';
		 $allow_signup = false;
	   }
       else {
	       if ($ridercount >= $limit && ($mystatus == "No" || $is_waitlisted))
		       $ridefull = true;
	   }
	if ($allow_signup) {
    ?>
   <h2>My information for this ride</h2>
   <?php if ($ridefull) { ?>
   <p><strong style="color:red">Ride is FULL. Signing up will put you on a waitlist. You will receive an email if you are signed up for the ride. Don't go to the ride unless you receive the email.</strong></p>
   <?php } ?>
   <dl>
   <dt>Planning to Attend?</dt>
   <dd>
     <select name="mystatus" id="mystatus">
       <option value="No" <?php if ($mystatus != 'Yes' && $mystatus != 'Maybe') echo 'selected'; ?> >No</option>
       <option value="Yes" <?php if ($mystatus == 'Yes') echo 'selected'; ?> >Yes</option>
       <!-- <option value="Maybe" <?php if ($mystatus == 'Maybe') echo 'selected'; ?> >Maybe</option> -->
     </select>
   </dd>
	 <dt>Cell Phone</dt><dd><input type="text" name="cell_fone" id="cell_fone" value="<?php echo $cell_phone;?>"></dd>
   <dt>Emergency Phone</dt><dd><input type="text" name="emrg_fone" id="emrg_fone" value="<?php echo $emergency_phone;?>"></dd>
	 <dt>Car License</dt><dd><input type="text" name="car_lic" id="car_lic" value="<?php echo $license;?>"></dd>
     <!-- <dt></dt><dd>Make sure you update the phone numbers and car license before selecting "Planning to Attend" or the changes will not be saved.</dd> -->
   </dl>
   <br>
   <button class="ride-update-button">Update</button>
   <textarea class="notification-area"></textarea>
   <div class="buttonHolder">
       <a href="<?php echo get_site_url(); ?>/Youth-Guest-Waiver.pdf" class="button" download>Youth Guest Waiver</a>
   </div>
<p>By signing up for this  ride, I attest that I have read. and agree to abide by the <a href="https://mafw.org/code-of-conduct/">MAFW Code of Conduct</a> on this ride and I also agree to the <a href="https://mafw.org/terms-of-service/">MAFW Terms of Service</a>.</p>
<?php } ?>
</div>
</div>
<?php
   $ret = road_hazards($tourid);
   if (!empty($ret))
      echo $ret;
?>
