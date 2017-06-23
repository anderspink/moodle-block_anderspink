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

class block_anderspink_edit_form extends block_edit_form {

    protected function specific_definition($mform) {

        $briefings = array('' => 'Select a briefing');
        $boards = array('' => 'Select a saved board');
        $errors = array();
        $apikey = get_config('anderspink', 'key');

        if (!$apikey || strlen(trim($apikey)) === 0) {
            $errors[] = 'No API key is set for the block, please set this in the global block settings for Anders Pink';
        } else {
            $fullresponse1 = download_file_content(
                'https://anderspink.com/api/v2/briefings',
                array('X-Api-Key' => $apikey),
                null,
                true
            );
            $fullresponse2 = download_file_content(
                'https://anderspink.com/api/v2/boards',
                array('X-Api-Key' => $apikey),
                null,
                true
            );
            $response1 = json_decode($fullresponse1->results, true);
            $response2 = json_decode($fullresponse2->results, true);

            if (!$response1 || !$response2) {
                if (!$response1) {
                    $errors[] = 'Failed to do API call: ' . $fullresponse1->error;
                }
                if (!$response2) {
                    $errors[] = 'Failed to do API call: ' . $fullresponse2->error;
                }
            } else {
                if ($response1['status'] !== 'success' || $response2['status'] !== 'success') {
                    if ($response1['status'] !== 'success') { 
                        $errors[] = $response1['message'];
                    }
                    if ($response2['status'] !== 'success') { 
                        $errors[] = $response2['message'];
                    }
                } else {
                    foreach ($response1['data']['owned_briefings'] as $briefing) {
                        $briefings[$briefing['id']] = $briefing['name'];
                    }
                    foreach ($response1['data']['subscribed_briefings'] as $briefing) {
                        $briefings[$briefing['id']] = $briefing['name'];
                    }
                    foreach ($response2['data']['owned_boards'] as $board) {
                        $boards[$board['id']] = $board['name'];
                    }
                }
            }
        }

        // Section header title according to language file.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        if (count($errors)) {
            foreach ($errors as $i => $error) {
                $mform->addElement('html', '<p style="background-color:#ffc7c7;padding:10px;">' . $error . '</p>');
            }
        }

        // A sample string variable with a default value.
        $mform->addElement('text', 'config_title', get_string('blockstring', 'block_anderspink'));
        $mform->setType('config_title', PARAM_TEXT);

        $radioarray = array();
        $radioarray[] = $mform->createElement('radio', 'config_source', '', get_string('showbriefing', 'block_anderspink'), 'briefing');
        $radioarray[] = $mform->createElement('radio', 'config_source', '', get_string('showsavedboard', 'block_anderspink'), 'board');
        $mform->addGroup($radioarray, 'radioar', '', array(' '), false);
        $mform->setDefault('config_source', 'briefing');

        $mform->addElement('html', '<div id="source_section_briefing">');
        $mform->addElement('select', 'config_briefing', get_string('briefingselect', 'block_anderspink'), $briefings, array());
        $mform->addElement('html', '</div>');

        $mform->addElement('html', '<div id="source_section_board">');
        $mform->addElement('select', 'config_board', get_string('boardselect', 'block_anderspink'), $boards, array());
        $mform->addElement('html', '</div>');

        $radioarray = array();
        $radioarray[] = $mform->createElement('radio', 'config_image', '', get_string('sideimage', 'block_anderspink'), 'side');
        $radioarray[] = $mform->createElement('radio', 'config_image', '', get_string('topimage', 'block_anderspink'), 'top');
        $mform->addGroup($radioarray, 'radioar', 'Article image position', array(' '), false);
        $mform->setDefault('config_image', 'side');

        $radioarray = array();
        $radioarray[] = $mform->createElement('radio', 'config_column', '', get_string('onecolumn', 'block_anderspink'), 1);
        $radioarray[] = $mform->createElement('radio', 'config_column', '', get_string('twocolumns', 'block_anderspink'), 2);
        $mform->addGroup($radioarray, 'radioar', 'Number of columns', array(' '), false);
        $mform->setDefault('config_column', 1);
        $mform->setType('config_column', PARAM_INT);

        $mform->addElement('text', 'config_limit', get_string('numberofarticles', 'block_anderspink'));
        $mform->setDefault('config_limit', 5);
        $mform->setType('config_limit', PARAM_INT);

        $mform->addElement('html', '
            <script type="text/javascript">
                YUI().use("node", function (Y) {

                    function handleSourceVisibility(source) {
                        console.log("here", source);
                        if (source === "briefing") {
                            Y.one("#source_section_briefing").show();
                            Y.one("#source_section_board").hide();
                        } else {
                            Y.one("#source_section_briefing").hide();
                            Y.one("#source_section_board").show();
                        }
                    }

                    // handle the visibility on load
                    var selectedValue = Y.one("input[name=config_source]:checked").get("value");
                    if (!selectedValue) {
                        selectedValue = "briefing";
                    }
                    handleSourceVisibility(selectedValue);

                    // listen to when the radio buttons are toggled
                    Y.all("input[name=config_source]").on("change", function (e) {
                        handleSourceVisibility(e.currentTarget.get("value"));
                    });
                });
            </script>
        ');
    }
}
