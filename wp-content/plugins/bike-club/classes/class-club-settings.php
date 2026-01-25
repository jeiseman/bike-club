<?php
namespace BikeClub;

class ClubSettings extends Pod {
    protected $pod_name = 'club-settings';

    public function get_signin_sheet($display = false) { return $this->get_field('signin_sheet', $display); }
    public function get_ride_cancellation_email($display = false) { return $this->get_field('ride_cancellation_email', $display); }
    public function get_ride_schedule_notification_email($display = false) { return $this->get_field('ride_schedule_notification_email', $display); }
    public function get_min_time_between_rides($display = false) { return $this->get_field('min_time_between_rides', $display); }
}
