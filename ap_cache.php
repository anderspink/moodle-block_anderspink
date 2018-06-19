<?php

class ap_cache {
    public function __construct() {
        $this->use_cache = intval(get_config('', 'version')) >= 2012120301;
        $this->cache = null;

        if ($this->use_cache) {
            $this->cache = cache::make('block_anderspink', 'apdata');
        } else {
            make_temp_directory('block_anderspink');
        }
    }
    public function get($key) {
        $value = @file_get_contents($this->path($key));
        return $value === false ? null : $value;
    }
    public function set($key, $value) {
        file_put_contents($this->path($key), $value);
    }
    private function path($key) {
        global $CFG;
        return $CFG->tempdir.'/block_anderspink/'.$key;
    }
}
