<?php
namespace BikeClub;

class User extends Pod {
    protected $pod_name = 'user';

    public function get_display_name($display = false) { return $this->get_field('display_name', $display); }
    public function set_display_name($value) { return $this->set_field('display_name', $value); }

    public function get_user_email($display = false) { return $this->get_field('user_email', $display); }
    public function set_user_email($value) { return $this->set_field('user_email', $value); }

    public function get_rides($display = false) { return $this->get_field('rides', $display); }
    public function add_to_rides($value) { return $this->add_to('rides', $value); }
    public function remove_from_rides($value) { return $this->remove_from('rides', $value); }

    public function get_user_nicename($display = false) { return $this->get_field('user_nicename', $display); }

    public function get_ride_leader_email($display = false) { return $this->get_field('ride_leader_email', $display); }
    public function set_ride_leader_email($value) { return $this->set_field('ride_leader_email', $value); }

    public function get_dual_member($display = false) { return $this->get_field('dual_member', $display); }
    public function set_dual_member($value) { return $this->set_field('dual_member', $value); }
}
