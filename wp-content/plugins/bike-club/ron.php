<?php
// error_reporting(2047);
// ini_set('display_errors', true);
// ini_set('display_errors', 1);

class RC_Report
{
    static $rideCounts;
    static $riderCounts;
    static $cnt, $rcnt;
    public static function get_ride_count($yr, $pace)
    {
        return self::$rideCounts[$yr][$pace];
    }
    public static function get_rider_count($yr, $pace)
    {
        return self::$riderCounts[$yr][$pace];
    }
    public static function get_total_ride_count($yr)
    {
        return self::$cnt[$yr];
    }
    public static function get_total_rider_count($yr)
    {
        return self::$rcnt[$yr];
    }
    public static function get_data($month_offset=0)
    {
		$args = array(
		    'post_type' => 'ride',
			'post_status' => 'publish',
			'posts_per_page' => -1,
		);
	    $paces = pods('pace');
		$params = array( 'limit' => -1 );
		$paces->find($params);
		$pacemap = array();
		$pacenames = array();
		while ($paces->fetch()) {
			$pacename = $paces->field('post_title');
		    $pacemap[$paces->field('ID')] = $pacename;
			$pacenames[] = $pacename;
		}
        for ($yearno = 0; $yearno < 5; ++$yearno) {
		    foreach ($pacenames as $pacename) {
                self::$rideCounts[$yearno][$pacename]    = 0;
                self::$riderCounts[$yearno][$pacename]    = 0;
		    }
            self::$rideCounts[$yearno]['ATB']    = 0;
            self::$riderCounts[$yearno]['ATB']    = 0;
            self::$cnt[$yearno]                = 0;
            self::$rcnt[$yearno]               = 0;
            $year          = date('Y') - 4 + $yearno;
            $month         = date('n');
            $months = $yearno * 12 + $month_offset;
            $pmonths = $yearno * 12 + $month_offset + 1;
            $year = date("Y", strtotime( "-" . $months . " month" ) );
            $month = date("n", strtotime( "-" . $months . " month" ) );
            $pyear = date("Y", strtotime( "-" . $pmonths . " month" ) );
            $pmonth = date("n", strtotime( "-" . $pmonths . " month" ) );

            $month_padded  = sprintf("%02d", $month);
            $pmonth_padded = sprintf("%02d", $pmonth);
            $strt          = strval($pyear) . "-" . $pmonth_padded . "-01";
            $end           = strval($year) . "-" . $month_padded . "-01";
			$args['meta_query'] = array(
			    'relation' => 'AND',
			    array(
				    'rider_count' => 1,
				    'compare' => '>=',
					'type' => 'numeric'
		        ),
				array(
					     'key' => 'ride_date',
						 'meta_type' => 'date',
						 'value' => $strt,
						 'compare' => '>='
				),
				array(
					     'key' => 'ride_date',
						 'meta_type' => 'date',
						 'value' => $end,
						 'compare' => '<'
				)
			);
			$query = new WP_Query($args);
			if ($query->have_posts())  {
			    while ($query->have_posts()) {
				    $post_id = get_the_ID();
					$paceid = get_post_meta($post_id, 'pace', true);
					if ($paceid) {
                		if (is_array($paceid))
							$paceid = $paceid['ID'];
					    $tour = get_post_meta($post_id, 'tour', true);
					    // if ($tour) {
					        // $tourid = $tour['ID'];
					        // $tourno = get_post_meta($tourid, 'tour_number', true);
					        // $miles = get_post_meta($tourid, 'miles', true);
					        // error_log("tour:" . $tourno . " miles:" . $miles);
					    // }
					    $ridercnt = intval(get_post_meta($post_id, 'rider_count', true));
					    $paceName = $pacemap[$paceid];
					    if ($paceName == 'MB' || $paceName == 'HB')
					        $paceName = 'ATB';
                        self::$rideCounts[$yearno][$paceName]++;
                        self::$riderCounts[$yearno][$paceName] += $ridercnt;
                        self::$cnt[$yearno]++;
                        self::$rcnt[$yearno] += $ridercnt;
					}
                    $query->the_post();
				}
			}
        }
    }
}
add_shortcode('ron_report', 'ronreport');
function ronreport()
{
if (isset($_GET['month'])) {
    $month_offset = intval($_GET['month']);
}
else
    $month_offset = 0;
$dmonth = $month_offset + 1;
$obj = new RC_report();
$obj->get_data($month_offset);
$month = date("n", strtotime( "-" . $dmonth . " month" ) ) - 1;
$year = date("Y", strtotime( "-" . $dmonth . " month" ) );
$months    = array(
    "January",
    "February",
    "March",
    "April",
    "May",
    "June",
    "July",
    "August",
    "September",
    "October",
    "November",
    "December"
);
$monthName = $months[$month];
$dmonth = $month_offset + 1;
$ret = '
<style>
table.center {
    margin-left:20px;
    margin-top:20px;
}
thead {
    background-color:green;
    color:white;
    border-right: 3px solid black;
    border-top: 3px solid black;
}
tfoot {
    border-bottom: 3px solid black;
}
.col1 {
    background-color:yellowgreen;
    color:white; width: 80px;
    border-right: 3px solid black;
    border-left: 3px solid black;
    border-bottom: 1px solid lightgray;
}
.subhead {background-color:olive; color:black;}

table, th, td {
    border: 1px solid black;
    border-collapse: collapse;
    text-align: center;
}

tr {
    line-height:25px;
}

th {
    line-height: 30px;
    width: 60px;
}
.rbold { border-right: 3px solid black; }
.lbold { border-left: 3px solid black; }

</style>';
if (isset($_GET['month'])) {
    $month_offset = intval($_GET['month']);
}
else
    $month_offset = 0;
$dmonth = $month_offset + 1;
$ret .= '<p><a href="/ronreport?month=' . $dmonth .'">Previous Month</a>';
$dmonth -= 2;
if ($dmonth >= 0)
   $ret .= '&nbsp; &nbsp;<a href="/ronreport?month=' . $dmonth . '">Next Month</a>';
$ret .= "</p>";
$ret .= '
<table class="center">
  <thead>
    <tr>
      <th class="rbold lbold">' . $monthName . '</th>
      <th colspan="2" class="rbold">' . $year . '</th>
      <th colspan="2" class="rbold">' . ($year - 1) . '</th>
      <th colspan="2" class="rbold">' . ($year - 2) . '</th>
      <th colspan="2" class="rbold">' . ($year - 3) . '</th>
      <th colspan="2" class="rbold">' . ($year - 4) . '</th>
    </tr>
  </thead>';
$ret .= '
  <tfoot>
     <tr>
       <td class="col1">Total</td>
       <td>' . $obj->get_total_ride_count(0) . '</td>
       <td class="rbold">' . $obj->get_total_rider_count(0) . '</td>
       <td>' . $obj->get_total_ride_count(1) . '</td>
       <td class="rbold">' . $obj->get_total_rider_count(1) . '</td>
       <td>' . $obj->get_total_ride_count(2) . '</td>
       <td class="rbold">' . $obj->get_total_rider_count(2) . '</td>
       <td>' . $obj->get_total_ride_count(3) . '</td>
       <td class="rbold">' . $obj->get_total_rider_count(3) . '</td>
       <td>' . $obj->get_total_ride_count(4) . '</td>
       <td class="rbold">' . $obj->get_total_rider_count(4) . '</td>
     </tr>
  </tfoot>';
$ret .= '
  <tbody>
    <tr>
      <th class="col1"></th>
      <th class="subhead">Rides</th>
      <th class="subhead rbold">Riders</th>
      <th class="subhead">Rides</th>
      <th class="subhead rbold">Riders</th>
      <th class="subhead">Rides</th>
      <th class="subhead rbold">Riders</th>
      <th class="subhead">Rides</th>
      <th class="subhead rbold">Riders</th>
      <th class="subhead">Rides</th>
      <th class="subhead rbold">Riders</th>
    </tr>
    <tr>
      <td class="col1">B+</td>
      <td>' . $obj->get_ride_count(0, 'B+') . '</td>
      <td class="rbold">' . $obj->get_rider_count(0, 'B+') . '</td>
      <td>' . $obj->get_ride_count(1, 'B+') . '</td>
      <td class="rbold">' . $obj->get_rider_count(1, 'B+') . '</td>
      <td>' . $obj->get_ride_count(2, 'B+') . '</td>
      <td class="rbold">' . $obj->get_rider_count(2, 'B+') . '</td>
      <td>' . $obj->get_ride_count(3, 'B+') . '</td>
      <td class="rbold">' . $obj->get_rider_count(3, 'B+') . '</td>
      <td>' . $obj->get_ride_count(4, 'B+') . '</td>
      <td class="rbold">' . $obj->get_rider_count(4, 'B+') . '</td>
    </tr>
    <tr>
      <td class="col1">B</td>
      <td>' . $obj->get_ride_count(0, 'B') . '</td>
      <td class="rbold">' . $obj->get_rider_count(0, 'B') . '</td>
      <td>' . $obj->get_ride_count(1, 'B') . '</td>
      <td class="rbold">' . $obj->get_rider_count(1, 'B') . '</td>
      <td>' . $obj->get_ride_count(2, 'B') . '</td>
      <td class="rbold">' . $obj->get_rider_count(2, 'B') . '</td>
      <td>' . $obj->get_ride_count(3, 'B') . '</td>
      <td class="rbold">' . $obj->get_rider_count(3, 'B') . '</td>
      <td>' . $obj->get_ride_count(4, 'B') . '</td>
      <td class="rbold">' . $obj->get_rider_count(4, 'B') . '</td>
    </tr>
    <tr>
      <td class="col1">C+</td>
      <td>' . $obj->get_ride_count(0, 'C+') . '</td>
      <td class="rbold">' . $obj->get_rider_count(0, 'C+') . '</td>
      <td>' . $obj->get_ride_count(1, 'C+') . '</td>
      <td class="rbold">' . $obj->get_rider_count(1, 'C+') . '</td>
      <td>' . $obj->get_ride_count(2, 'C+') . '</td>
      <td class="rbold">' . $obj->get_rider_count(2, 'C+') . '</td>
      <td>' . $obj->get_ride_count(3, 'C+') . '</td>
      <td class="rbold">' . $obj->get_rider_count(3, 'C+') . '</td>
      <td>' . $obj->get_ride_count(4, 'C+') . '</td>
      <td class="rbold">' . $obj->get_rider_count(4, 'C+') . '</td>
    </tr>
    <tr>
      <td class="col1">C</td>
      <td>' . $obj->get_ride_count(0, 'C') . '</td>
      <td class="rbold">' . $obj->get_rider_count(0, 'C') . '</td>
      <td>' . $obj->get_ride_count(1, 'C') . '</td>
      <td class="rbold">' . $obj->get_rider_count(1, 'C') . '</td>
      <td>' . $obj->get_ride_count(2, 'C') . '</td>
      <td class="rbold">' . $obj->get_rider_count(2, 'C') . '</td>
      <td>' . $obj->get_ride_count(3, 'C') . '</td>
      <td class="rbold">' . $obj->get_rider_count(3, 'C') . '</td>
      <td>' . $obj->get_ride_count(4, 'C') . '</td>
      <td class="rbold">' . $obj->get_rider_count(4, 'C') . '</td>
    </tr>
    <tr>
      <td class="col1">D+</td>
      <td>' . $obj->get_ride_count(0, 'D+') . '</td>
      <td class="rbold">' . $obj->get_rider_count(0, 'D+') . '</td>
      <td>' . $obj->get_ride_count(1, 'D+') . '</td>
      <td class="rbold">' . $obj->get_rider_count(1, 'D+') . '</td>
      <td>' . $obj->get_ride_count(2, 'D+') . '</td>
      <td class="rbold">' . $obj->get_rider_count(2, 'D+') . '</td>
      <td>' . $obj->get_ride_count(3, 'D+') . '</td>
      <td class="rbold">' . $obj->get_rider_count(3, 'D+') . '</td>
      <td>' . $obj->get_ride_count(4, 'D+') . '</td>
      <td class="rbold">' . $obj->get_rider_count(4, 'D+') . '</td>
    </tr>
    <tr>
      <td class="col1">D</td>
      <td>' . $obj->get_ride_count(0, 'D') . '</td>
      <td class="rbold">' . $obj->get_rider_count(0, 'D') . '</td>
      <td>' . $obj->get_ride_count(1, 'D') . '</td>
      <td class="rbold">' . $obj->get_rider_count(1, 'D') . '</td>
      <td>' . $obj->get_ride_count(2, 'D') . '</td>
      <td class="rbold">' . $obj->get_rider_count(2, 'D') . '</td>
      <td>' . $obj->get_ride_count(3, 'D') . '</td>
      <td class="rbold">' . $obj->get_rider_count(3, 'D') . '</td>
      <td>' . $obj->get_ride_count(4, 'D') . '</td>
      <td class="rbold">' . $obj->get_rider_count(4, 'D') . '</td>
    </tr>
    <tr>
      <td class="col1">CA</td>
      <td>' . $obj->get_ride_count(0, 'CA') . '</td>
      <td class="rbold">' . $obj->get_rider_count(0, 'CA') . '</td>
      <td>' . $obj->get_ride_count(1, 'CA') . '</td>
      <td class="rbold">' . $obj->get_rider_count(1, 'CA') . '</td>
      <td>' . $obj->get_ride_count(2, 'CA') . '</td>
      <td class="rbold">' . $obj->get_rider_count(2, 'CA') . '</td>
      <td>' . $obj->get_ride_count(3, 'CA') . '</td>
      <td class="rbold">' . $obj->get_rider_count(3, 'CA') . '</td>
      <td>' . $obj->get_ride_count(4, 'CA') . '</td>
      <td class="rbold">' . $obj->get_rider_count(4, 'CA') . '</td>
    </tr>
    <tr>
      <td class="col1">TA</td>
      <td>' . $obj->get_ride_count(0, 'TA') . '</td>
      <td class="rbold">' . $obj->get_rider_count(0, 'TA') . '</td>
      <td>' . $obj->get_ride_count(1, 'TA') . '</td>
      <td class="rbold">' . $obj->get_rider_count(1, 'TA') . '</td>
      <td>' . $obj->get_ride_count(2, 'TA') . '</td>
      <td class="rbold">' . $obj->get_rider_count(2, 'TA') . '</td>
      <td>' . $obj->get_ride_count(3, 'TA') . '</td>
      <td class="rbold">' . $obj->get_rider_count(3, 'TA') . '</td>
      <td>' . $obj->get_ride_count(4, 'TA') . '</td>
      <td class="rbold">' . $obj->get_rider_count(4, 'TA') . '</td>
    </tr>
    <tr>
      <td class="col1">TB</td>
      <td>' . $obj->get_ride_count(0, 'TB') . '</td>
      <td class="rbold">' . $obj->get_rider_count(0, 'TB') . '</td>
      <td>' . $obj->get_ride_count(1, 'TB') . '</td>
      <td class="rbold">' . $obj->get_rider_count(1, 'TB') . '</td>
      <td>' . $obj->get_ride_count(2, 'TB') . '</td>
      <td class="rbold">' . $obj->get_rider_count(2, 'TB') . '</td>
      <td>' . $obj->get_ride_count(3, 'TB') . '</td>
      <td class="rbold">' . $obj->get_rider_count(3, 'TB') . '</td>
      <td>' . $obj->get_ride_count(4, 'TB') . '</td>
      <td class="rbold">' . $obj->get_rider_count(4, 'TB') . '</td>
    </tr>
    <tr>
      <td class="col1">TC</td>
      <td>' . $obj->get_ride_count(0, 'TC') . '</td>
      <td class="rbold">' . $obj->get_rider_count(0, 'TC') . '</td>
      <td>' . $obj->get_ride_count(1, 'TC') . '</td>
      <td class="rbold">' . $obj->get_rider_count(1, 'TC') . '</td>
      <td>' . $obj->get_ride_count(2, 'TC') . '</td>
      <td class="rbold">' . $obj->get_rider_count(2, 'TC') . '</td>
      <td>' . $obj->get_ride_count(3, 'TC') . '</td>
      <td class="rbold">' . $obj->get_rider_count(3, 'TC') . '</td>
      <td>' . $obj->get_ride_count(4, 'TC') . '</td>
      <td class="rbold">' . $obj->get_rider_count(4, 'TC') . '</td>
    </tr>
    <tr>
      <td class="col1">TD</td>
      <td>' . $obj->get_ride_count(0, 'TD') . '</td>
      <td class="rbold">' . $obj->get_rider_count(0, 'TD') . '</td>
      <td>' . $obj->get_ride_count(1, 'TD') . '</td>
      <td class="rbold">' . $obj->get_rider_count(1, 'TD') . '</td>
      <td>' . $obj->get_ride_count(2, 'TD') . '</td>
      <td class="rbold">' . $obj->get_rider_count(2, 'TD') . '</td>
      <td>' . $obj->get_ride_count(3, 'TD') . '</td>
      <td class="rbold">' . $obj->get_rider_count(3, 'TD') . '</td>
      <td>' . $obj->get_ride_count(4, 'TD') . '</td>
      <td class="rbold">' . $obj->get_rider_count(4, 'TD') . '</td>
    </tr>
    <tr>
      <td class="col1">Off-Road</td>
      <td>' . $obj->get_ride_count(0, 'ATB') . '</td>
      <td class="rbold">' . $obj->get_rider_count(0, 'ATB') . '</td>
      <td>' . $obj->get_ride_count(1, 'ATB') . '</td>
      <td class="rbold">' . $obj->get_rider_count(1, 'ATB') . '</td>
      <td>' . $obj->get_ride_count(2, 'ATB') . '</td>
      <td class="rbold">' . $obj->get_rider_count(2, 'ATB') . '</td>
      <td>' . $obj->get_ride_count(3, 'ATB') . '</td>
      <td class="rbold">' . $obj->get_rider_count(3, 'ATB') . '</td>
      <td>' . $obj->get_ride_count(4, 'ATB') . '</td>
      <td class="rbold">' . $obj->get_rider_count(4, 'ATB') . '</td>
    </tr>';
$ret .= '</tbody></table>';
return $ret;
}
function bk_gen_csv()
{
	$ret = "";
	$args = array(
	    'post_type' => 'ride',
		'post_status' => 'publish',
		'posts_per_page' => -1,
	);
	$strt = $_GET['start'];
	$end = $_GET['end'];
	$ret = "start:" . $strt . " end:" . $end;
	if (empty($strt) || empty($end))
	    return $ret;
    $paces = pods('pace');
	$params = array( 'limit' => -1 );
	$paces->find($params);
	$pacemap = array();
	$pacenames = array();
	while ($paces->fetch()) {
		$pacename = $paces->field('post_title');
	    $pacemap[$paces->field('ID')] = $pacename;
		$pacenames[] = $pacename;
	}
	$args['meta_query'] = array(
	    'relation' => 'AND',
	    array(
		    'rider_count' => 1,
		    'compare' => '>=',
			'type' => 'numeric'
	    ),
		array(
			     'key' => 'ride_date',
				 'meta_type' => 'date',
				 'value' => $strt,
				 'compare' => '>='
		),
		array(
			     'key' => 'ride_date',
				 'meta_type' => 'date',
				 'value' => $end,
				 'compare' => '<'
		)
	);
	$query = new WP_Query($args);
	if ($query->have_posts())  {
	    $ret = "opening file";
		$file = fopen("rides.csv", "w");
		fputcsv($file, array('ride_date', 'tourno', 'pace', 'miles', 'ridercnt', 'miles_total'));
	    while ($query->have_posts()) {
		    $post_id = get_the_ID();
			$paceid = get_post_meta($post_id, 'pace', true);
			if ($paceid) {
                if (is_array($paceid))
					$paceid = $paceid['ID'];
			    $tourid = get_post_meta($post_id, 'tour', true);
			    if ($tourid) {
			        $tourno = get_post_meta($tourid, 'tour_number', true);
			        $miles = get_post_meta($tourid, 'miles', true);
			        $ride_date = get_post_meta($post_id, 'ride_date', true);
		            $ridercnt = intval(get_post_meta($post_id, 'rider_count', true));
					if ($ridercnt > 0) {
		                $paceName = $pacemap[$paceid];
		                if ($paceName == 'MB' || $paceName == 'HB')
		                    $paceName = 'ATB';
				        fputcsv($file, array($ride_date, $tourno, $paceName, $miles, $ridercnt, $miles * $ridercnt));
					}
		        }
		    }
            $query->the_post();
	    }
		fclose($file);
	    $ret .= "closing file";
	}
	return $ret;
}
add_shortcode('bk-gen-csv', 'bk_gen_csv');

?>
