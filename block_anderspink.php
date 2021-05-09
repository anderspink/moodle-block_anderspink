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
 * Version details
 *
 * @package    block_anderspink
 * @copyright  2016 onwards Anders Pink Ltd <info@anderspink.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Unfortunatly due to a bug in moodle, filelib wasn't always being included, and we need it!
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/ap_cache.php');

require_once($CFG->libdir . '/filelib.php');

class block_anderspink extends block_base {

    function init() {
        $this->title = get_string('pluginname', 'block_anderspink');
        $this->cache = new ap_cache();
    }

    function render_article($article, $imageposition = 'side', $content_preview = false, $show_comments = false) {

        $side = $imageposition === 'side';

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
                    <div class='" . ($side ? "ap-article-image-container-side-inner" : "ap-article-image-container-top-inner") . "' style='background-image:url({$article['image']})'>
                    </div>
                </div>
            ";
        }

        $cutoff = 75;
        $title = strlen(trim($article['title'])) > $cutoff ? substr($article['title'], 0, $cutoff) . "..." : $article['title'];
        $content = $content_preview ? $article['content'] : '';

        $featured_comment = null;
        if ($show_comments && count($article['comments']) > 0) {
            foreach ($article['comments'] as $comment) {
                if (isset($comment['pinned']) && $comment['pinned']) {
                    $featured_comment = $comment;
                }
            }
            if (!$featured_comment) {
                $featured_comment = $article['comments'][count($article['comments']) - 1];
            }
        }
        if ($featured_comment) {
            // Render links from markdown
            $featured_comment['text'] = preg_replace('/\[(.*)\]\((.*)\)/', '<a target="_blank" href="$2">$1</a>', $featured_comment['text']);
        }

        return "
            <div class='ap-article'>
              <a class='ap-article-link' href='{$article['url']}' title='" . htmlspecialchars($article['title'], ENT_QUOTES) . "' target='_blank'>
                  {$image}
                  <div class='" . (($side && $article['image']) ? 'ap-margin-right' : '') . "'>
                      <div class='ap-article-title'>" . htmlspecialchars($title) . "</div>
                      <div class='ap-article-text-extra'>" . implode(' - ', $extra) . "</div>
                  </div>
              </a>
              <div>
                " . ($content ? "<div class='ap-article-content'>{$content}</div>" : "") . "
                " . ($featured_comment ? "<div class='ap-article-comment'>Our comment: <span class='ap-article-comment-text'>\"" . $featured_comment['text'] . "\"</span></div>" : "") . "
              </div>
            </div>
        ";
    }

    function get_content() {
        global $CFG, $OUTPUT;

        $apihost = "https://anderspink.com";

        if ($this->content !== null) {
            return $this->content;
        }

        if (empty($this->instance)) {
            $this->content = '';
            return $this->content;
        }

        if (!$this->config) {
            $this->config = new stdClass();
        }

        // defaults
        if (!isset($this->config->source) || !$this->config->source) {
            $this->config->source = 'briefing';
        }
        if (!isset($this->config->image) || !$this->config->image) {
            $this->config->image = 'side';
        }
        if (!isset($this->config->column) || !$this->config->column) {
            $this->config->column = 1;
        }
        if (!isset($this->config->limit) || !$this->config->limit) {
            $this->config->limit = 5;
        }
        $this->config->filter_imageless = isset($this->config->filter_imageless) && $this->config->filter_imageless === '1';
        $this->config->limit = max(min($this->config->limit, 30), 1); // Cap betwen 1-30
        $this->config->content_preview = isset($this->config->content_preview) && $this->config->content_preview === '1';
        $this->config->comment = isset($this->config->comment) && $this->config->comment === '1';

        if (isset($this->config->title) && $this->config->title) {
            $this->title = $this->config->title;
        }

        $this->content = new stdClass();
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

        $apikey = get_config('anderspink', 'key');

        if (!$apikey || strlen(trim($apikey)) === 0) {
            $this->content->text = 'Please set the API key in the global Anders Pink block settings.';
            return $this->content;
        }

        // Try and parse the key as JSON
        $apikeys = json_decode($apikey, true);
        if ($apikeys && count($apikeys) > 0) {
            // If we have a key ID, find the key itself
            if ($this->config->apikeyid) {
                $found = false;
                foreach ($apikeys as $apikeyrow) {
                    if ($apikeyrow['id'] === $this->config->apikeyid) {
                        $apikey = $apikeyrow['key'];
                        $found = true;
                    }
                }
                if (!$found) {
                    $this->content->text = 'Error: couldn\'t find key with id ' . $this->config->apikeyid;
                    return $this->content;
                }
            } else {
                // Use the first key by default
                $apikey = $apikeys[0]['key'];
            }
        }

        $date = new DateTime();
        $datenow = $date->format('Y-m-d\TH:i:s');

        $key = null;
        $dateofexpiry = null;
        $url = null;

        // Cache key is based on the config and api key, so that it's invalidated when the config changes
        $key = md5(json_encode($this->config)) . $apikey;

        // Seperate out the logic for briefings vs boards (different calls, and cache times)
        if ($this->config->source === 'briefing') {
            if (!isset($this->config->briefing) || !$this->config->briefing) {
                $this->content->text = 'Please configure this block and choose a briefing to show.';
                return $this->content;
            }
            $date = new DateTime();
            $dateofexpiry = $date->add(new DateInterval('PT1M'))->format('Y-m-d\TH:i:s'); // 1 minute
            $time = $this->config->briefing_time ? $this->config->briefing_time : 'auto';
            $url = $apihost . "/api/v2/briefings/{$this->config->briefing}?time={$time}&limit={$this->config->limit}" . ($this->config->filter_imageless ? "&filter_imageless" : "");
        } else {
            if (!isset($this->config->board) || !$this->config->board) {
                $this->content->text = 'Please configure this block and choose a board to show.';
                return $this->content;
            }
            $date = new DateTime();
            $dateofexpiry = $date->add(new DateInterval('PT5S'))->format('Y-m-d\TH:i:s'); // 5 seconds
            $url = $apihost . "/api/v2/boards/{$this->config->board}?limit={$this->config->limit}" . ($this->config->filter_imageless ? "&filter_imageless" : "");
        }

        // Check the cache first...
        $response = null;
        $stringresponse = $this->cache->get($key);
        if ($stringresponse) {
            $response = json_decode($stringresponse, true);
            if ($datenow > $response['ttl']) {
                $response = null;
            }
        }

        if (!$response) {
            // Do an API call to load the briefings...
            $fullresponse = download_file_content(
                $url,
                array('X-Api-Key' => $apikey),
                null,
                true
            );
            $response = json_decode($fullresponse->results, true);

            if ($response && $response['status'] === 'success') {
                $response['ttl'] = $dateofexpiry;
                $this->cache->set($key, json_encode($response));
            }
        }

        if (!$response) {
            $this->content->text = 'There was an issue loading the briefing/board: ' . $fullresponse->error;
            return $this->content;
        }

        if ($response['status'] !== 'success') {
            $this->content->text = 'There was an API error: ' . $response['message'];
            return $this->content;
        }

        // Get the html for the individual blocks
        $articlehtml = array();
        foreach (array_slice($response['data']['articles'], 0, $this->config->limit) as $article) {
            $articlehtml[] = $this->render_article($article, $this->config->image, $this->config->content_preview, $this->config->comment);
        }

        // Render the blocks in one or two columns
        if ($this->config->column === 1) {
            $this->content->text = implode("\n", $articlehtml);
        } else if ($this->config->column === 2) {
            $this->content->text =
                '<div class="ap-columns">' .
                implode("\n", array_map(function ($item) {
                    return '<div class="ap-two-column">' . $item . '</div>';
                }, $articlehtml)) .
                '</div>';
        }

        return $this->content;
    }

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
        // Not needed just yet
        return true;
    }

    private function time2str($ts) {
        if (!ctype_digit($ts)) {
            $ts = strtotime($ts);
        }
        $diff = time() - $ts;
        if ($diff == 0) {
            return 'now';
        } elseif ($diff > 0) {
            $day_diff = floor($diff / 86400);
            if ($day_diff == 0) {
                if ($diff < 60) return 'just now';
                if ($diff < 120) return '1m';
                if ($diff < 3600) return floor($diff / 60) . 'm';
                if ($diff < 7200) return '1h';
                if ($diff < 86400) return floor($diff / 3600) . 'h';
            }
            if ($day_diff == 1) {
                return '1d';
            }
            if ($day_diff < 7) {
                return $day_diff . 'd';
            }
            if ($day_diff < 31) {
                return ceil($day_diff / 7) . 'w';
            }
        }
        return date('F Y', $ts);
    }
}
