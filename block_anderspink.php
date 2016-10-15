<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * anderspink block caps.
 *
 * @package    block_anderspink
 * @copyright  Anders Pink Ltd <info@anderspink.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Unfortunatly due to a bug in moodle, filelib wasn't always being included, and we need it!
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir .'/filelib.php');

class block_anderspink extends block_base {

    function init() {
        $this->title = get_string('pluginname', 'block_anderspink');
    }
    
    function render_article($article, $image_position='side') {
        
        $side = $image_position === 'side';
        
        $extra = array();
        if ($article['domain']) {
            $extra[] = $article['domain'];
        }
        if ($article['date_published']) {
            $extra[] = $this->time2str($article['date_published']);
        }
        
        $image = "";
        if ($article['image']) {
            $image = "
                <div class='" . ($side ? "ap-article-image-container-side" : "ap-article-image-container-top") . "'>
                    <img class='ap-article-image' src='{$article['image']}' />
                </div>
            ";
        }
        
        
        return "
            <a class='ap-article' href='{$article['url']}'>
                {$image}
                <div class='" . (($side && $article['image']) ? 'ap-margin-right' : '') . "'>
                    <div>{$article['title']}</div>
                    <div class='ap-article-text-extra'>". implode(' - ', $extra) ."</div>
                </div>
            </a>
        ";
    }

    function get_content() {
        global $CFG, $OUTPUT, $PAGE;
        
        // defaults
        if (!$this->config->source) {
            $this->config->source = 'briefing';
        }
        if (!$this->config->image) {
            $this->config->image = 'side';
        }
        if (!$this->config->column) {
            $this->config->column = 1;
        }
        
        // load the javascript
        //$PAGE->requires->yui_module('moodle-block_anderspink', 'M.anderspink.init');
        
        if ($this->config->title) {
            $this->title = $this->config->title;
        }
        
        if ($this->content !== null) {
            return $this->content;
        }

        if (empty($this->instance)) {
            $this->content = '';
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

        
        $apiKey = get_config('anderspink', 'key');
        
        if (!$apiKey || strlen(trim($apiKey)) === 0) {
            $this->content->text = 'Please set the API key in the global Anders Pink block settings.';
            return $this->content;
        }
        
        
        $dateNow = (new DateTime())->format('Y-m-d\TH:i:s');
        $cache = cache::make('block_anderspink', 'apdata');
        
        $key = null;
        $dateOfExpiry = null;
        $url = null;
        
        if ($this->config->source === 'briefing') {
            if (!$this->config->briefing) {
                $this->content->text = 'Please configure this block and choose a briefing to show.';
                return $this->content;
            }
            $key = 'briefing_' . $this->config->briefing . '_' . $apiKey;
            $dateOfExpiry = (new DateTime())->add(new DateInterval('PT1M'))->format('Y-m-d\TH:i:s'); // 1 minute
            $url = 'https://anderspink.com/api/v1/briefings/' . $this->config->briefing;
        } else {
            if (!$this->config->board) {
                $this->content->text = 'Please configure this block and choose a board to show.';
                return $this->content;
            }
            $key = 'board_' . $this->config->board . '_' . $apiKey;
            $dateOfExpiry = (new DateTime())->add(new DateInterval('PT5S'))->format('Y-m-d\TH:i:s'); // 5 seconds
            $url = 'https://anderspink.com/api/v1/boards/' . $this->config->board;
        }
        
        // Check the cache first...
        $stringResponse = $cache->get($key);
        if ($stringResponse) {
            $response = json_decode($stringResponse, true);
            if ($dateNow > $response['ttl']) {
                $response = null;
            }
        }
        
        if (!$response) {
            // Do an API call to load the briefings...
            $fullResponse = download_file_content(
                $url,
                array('X-Api-Key' => $apiKey),
                null,
                true
            );
            $response = json_decode($fullResponse->results, true);
            $response['ttl'] = $dateOfExpiry;
            
            if ($response && $response['status'] === 'success') {
                $cache->set($key, json_encode($response));
            }
        }
        
        if (!$response) {
            $this->content->text = 'There was an unknown issue loading the briefing/board.';
            return $this->content;
        }
        
        if ($response['status'] !== 'success') {
            $this->content->text = 'There was an API error: ' . $response['message'];
            return $this->content;
        }
        
        $articleHtml = array();
        foreach (array_slice($response['data']['articles'],0,5) as $article) {
            $articleHtml[] = $this->render_article($article, $this->config->image);
        }
        
        // Linear..
        if ($this->config->column === 1) {
            $this->content->text = implode("\n", $articleHtml);
        } else if ($this->config->column === 2) {
            
            $rowHtml = array();
            $rows = array_chunk($articleHtml, 2);
            foreach ($rows as $row) {
                $rowHtml[] = "
                    <div class='ap-row'>
                        <div class='ap-column2'>
                            <div class='ap-column-inner-first'>
                                {$row[0]}
                            </div>
                        </div>
                        <div class='ap-column2'>
                            <div class='ap-column-inner-last'>
                                {$row[1]}
                            </div>
                        </div>
                    </div>
                ";
            }
            $this->content->text = implode("\n", $rowHtml);
        }
        
        return $this->content;
    }

    // my moodle can only have SITEID and it's redundant here, so take it away
    public function applicable_formats() {
        return array('all' => true);
    }

    public function instance_allow_multiple() {
          return true;
    }

    function has_config() {
        return true;
    }

    public function cron() {
        mtrace( "Hey, my cron script is running" );
        // do something
        return true;
    }
    
    private function time2str($ts) {
        if(!ctype_digit($ts)) {
            $ts = strtotime($ts);
        }
        $diff = time() - $ts;
        if($diff == 0) {
            return 'now';
        } elseif($diff > 0) {
            $day_diff = floor($diff / 86400);
            if($day_diff == 0) {
                if($diff < 60) return 'just now';
                if($diff < 120) return '1m';
                if($diff < 3600) return floor($diff / 60) . 'm';
                if($diff < 7200) return '1h';
                if($diff < 86400) return floor($diff / 3600) . 'h';
            }
            if($day_diff == 1) { return '1d'; }
            if($day_diff < 7) { return $day_diff . 'd'; }
            if($day_diff < 31) { return ceil($day_diff / 7) . 'w'; }
        }
        return date('F Y', $ts);
    } 
}
