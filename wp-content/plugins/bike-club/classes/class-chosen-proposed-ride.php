<?php
namespace BikeClub;

class ChosenProposedRide extends Pod {
    protected $pod_name = 'chosen_proposed_ride';

    public function get_post_title($display = false) { return $this->get_field('post_title', $display); }

    public function get_submittor($display = false) { return $this->get_field('submittor', $display); }
    public function set_submittor($value) { return $this->set_field('submittor', $value); }

    public function get_ride($display = false) { return $this->get_field('ride', $display); }
    public function set_ride($value) { return $this->set_field('ride', $value); }

    public function get_ride_leader($display = false) { return $this->get_field('ride_leader', $display); }
    public function set_ride_leader($value) { return $this->set_field('ride_leader', $value); }
}
