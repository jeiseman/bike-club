<?php
namespace BikeClub;

class Ride extends Pod {
    protected $pod_name = 'ride';

    public function get_ride_date($display = false) { return $this->get_field('ride_date', $display); }
    public function set_ride_date($value) { return $this->set_field('ride_date', $value); }

    public function get_time($display = false) { return $this->get_field('time', $display); }
    public function set_time($value) { return $this->set_field('time', $value); }

    public function get_ride_leader($display = false) { return $this->get_field('ride_leader', $display); }
    public function set_ride_leader($value) { return $this->set_field('ride_leader', $value); }

    public function get_ride_status($display = false) { return $this->get_field('ride-status', $display); }
    public function set_ride_status($value) { return $this->set_field('ride-status', $value); }

    public function get_maximum_signups($display = false) { return $this->get_field('maximum_signups', $display); }
    public function set_maximum_signups($value) { return $this->set_field('maximum_signups', $value); }

    public function get_tour($display = false) { return $this->get_field('tour', $display); }
    public function set_tour($value) { return $this->set_field('tour', $value); }

    public function get_guests($display = false) { return $this->get_field('guests', $display); }
    public function add_to_guests($value) { return $this->add_to('guests', $value); }

    public function get_attendees($display = false) { return $this->get_field('attendees', $display); }
    public function add_to_attendees($value) { return $this->add_to('attendees', $value); }

    public function get_ride_attendee_notification($display = false) { return $this->get_field('ride_attendee_notification', $display); }

    public function get_send_text_message($display = false) { return $this->get_field('send_text_message', $display); }

    public function get_ride_canceled($display = false) { return $this->get_field('ride_canceled', $display); }

    public function get_ride_change($display = false) { return $this->get_field('ride_change', $display); }

    public function get_pace($display = false) { return $this->get_field('pace', $display); }

    public function get_ride_comments($display = false) { return $this->get_field('ride_comments', $display); }

    public function get_rider_count($display = false) { return $this->get_field('rider_count', $display); }

    public function get_ride_leader_pace($display = false) { return $this->get_field('ride_leader_pace', $display); }

    public function get_email_sent($display = false) { return $this->get_field('email_sent', $display); }
    public function set_email_sent($value) { return $this->set_field('email_sent', $value); }
}
