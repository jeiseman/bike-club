<?php
class BikeClubPod {
    protected $pod;
    protected $pod_name;
    protected $id;

    public function __construct($id = null) {
        $this->id = $id;
        if (!empty($this->pod_name)) {
            $this->pod = pods($this->pod_name, $id);
        }
    }

    public function get_pod() {
        return $this->pod;
    }

    public function get($field, $display = false) {
        if ($display) {
            return $this->pod->display($field);
        }
        return $this->pod->field($field);
    }

    public function set($field, $value) {
        return $this->pod->save($field, $value);
    }

    public function add($data) {
        return $this->pod->add($data);
    }

    public function add_to($field, $value) {
        return $this->pod->add_to($field, $value);
    }

    public function remove_from($field, $value) {
        return $this->pod->remove_from($field, $value);
    }

    public function total() {
        return $this->pod->total();
    }

    public function fetch() {
        return $this->pod->fetch();
    }

    public function find($params) {
        return $this->pod->find($params);
    }

    public function form($fields = null, $label = 'Save', $return = '') {
        return $this->pod->form($fields, $label, $return);
    }

    public function __call($name, $arguments) {
        $prefix = substr($name, 0, 4);

        if ($prefix === 'get_') {
            $field = substr($name, 4);
            $display = isset($arguments[0]) ? $arguments[0] : false;
            return $this->get($field, $display);
        } elseif ($prefix === 'set_') {
            $field = substr($name, 4);
            return $this->set($field, $arguments[0]);
        }

        // Delegate to underlying pod
        return call_user_func_array([$this->pod, $name], $arguments);
    }

    public function __get($name) {
        return $this->pod->$name;
    }

    public function __set($name, $value) {
        $this->pod->$name = $value;
    }

    public function __isset($name) {
        return isset($this->pod->$name);
    }

    public function __unset($name) {
        unset($this->pod->$name);
    }
}
