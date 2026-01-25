<?php
namespace BikeClub;

class ClubSponsor extends Pod {
    protected $pod_name = 'club-sponsor';

    public function get_post_title($display = false) { return $this->get_field('post_title', $display); }
    public function get_website($display = false) { return $this->get_field('website', $display); }
    public function get_location($display = false) { return $this->get_field('location', $display); }
    public function get_address($display = false) { return $this->get_field('address', $display); }
    public function get_phone($display = false) { return $this->get_field('phone', $display); }
    public function get_sponsor_town($display = false) { return $this->get_field('sponsor-town', $display); }
}
