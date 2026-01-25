<?php
namespace BikeClub;

class Role extends Pod {
    protected $pod_name = 'role';

    public function get_member($display = false) { return $this->get_field('member', $display); }
    public function get_post_title($display = false) { return $this->get_field('post_title', $display); }
    public function get_index($display = false) { return $this->get_field('index', $display); }
    public function get_telephone($display = false) { return $this->get_field('telephone', $display); }
    public function get_email($display = false) { return $this->get_field('email', $display); }
}
