<?php
if (!is_user_logged_in() || !current_user_can('active')) {
    wp_redirect(home_url());
    die();
}
$postid = get_the_ID();
if (!empty($_GET['touredit']) && $_GET['touredit'] == "Edit" && (current_user_can("ridecoordinator") || current_user_can("administrator"))) {
    echo '<h1>Update Tour</h1>';
    gravity_form(19, false, false, false, array('touredit' => 'Edit'));
	return;
}
$post = get_post($postid);
$tpod = pods('tour', $postid);
$start = $tpod->field('start_point');
$tourno = $tpod->field('tour_number');
$tourname = $tourno . " - " . $post->post_title;
$creator = $tpod->field('creator');
$active = $tpod->field('active') ? "Yes" : "No";
if (function_exists('bk_tour_description'))
    $description = bk_tour_description($tpod);
else
    $description = $tpod->field('tour_description');
$comments = $tpod->field('tour_comments');
$terrain = $tpod->field('tour-terrain');
$tour_type = $tpod->field('tour_type');
if (!empty($terrain)) {
    $ptpage = '<a href="#" class="pace-terrain">';
    // if ($tour_type == 0)
	    // $ptpage = "pace-terrain-information-road-day-evening";
    // else
	    // $ptpage = "pace-terrain-info-path-trail-rides";
    // $ptpage = "/pace-terrain-information/#pace/";
    // $ptpage = "%23elementor-action%3Aaction%3Dpopup%3Aopen%26settings%3DeyJpZCI6IjMzOTM2IiwidG9nZ2xlIjpmYWxzZX0%3D";
    $terrain_name = $terrain['post_title'];
    // $terrain_link = '<a href="' . $ptpage . '">' . $terrain_name . '</a>';
    $terrain_link = $ptpage . $terrain_name . '</a>';
}
else {
    $terrain_link = "";
}
if ( $start ) {
    $url = get_site_url() . '/start_point/' . $start['post_name'] . '/';
    $startlink = !empty($start) ? '<a href="' . esc_url($url) . '">' . $start['post_title'] . '</a>' : '';
}
else {
    $startlink = '';
}
$miles = $tpod->field('miles');
$climb = $tpod->field('climb');
$tourtype = $tpod->field('tour_type') ? "ATB" : "Road";
$mapurl = $tpod->field('tour_map');
$cue = $tpod->field('cue_sheet_number'); ?>
<dl>
   <dt>Tour</dt>
   <dd><?php echo $tourname;?></dd>
   <?php if (!empty($creator)) { ?>
   <dt>Tour by</dt>
   <dd><?php echo $creator;?></dd>
   <?php } ?>
   <dt>Active</dt>
   <dd><?php echo $active;?></dd>
   <dt>Description</dt>
   <dd><?php echo $description;?></dd>
   <dt>Notes</dt>
   <dd><?php echo $comments;?></dd>
   <dt>Terrain</dt>
   <dd><?php echo $terrain_link;?></dd>
   <dt>Start</dt>
   <dd><?php echo $startlink;?></dd>
   <dt>Miles</dt>
   <dd><?php echo $miles;?></dd>
   <dt>Climb</dt>
   <dd><?php echo $climb;?></dd>
   <dt>Tour Type</dt>
   <dd><?php echo $tourtype;?></dd>
   <dt>Cue</dt>
   <dd><?php echo bike_cue_links($cue, $tourno, $mapurl); ?></dd>
</dl>
<?php
$ret = road_hazards($postid);
if (!empty($ret))
    echo $ret;
if (current_user_can('ridecoordinator')) {
?>
<p></p>
<a href="<?php echo get_site_url(); ?>/tour/?p=<?php echo $postid;?>&touredit=Edit" class="button">Edit Tour</a>
<?php
}
?>
