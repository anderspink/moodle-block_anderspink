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

        $apikeyoptions = array('' => 'Select an API key');

        if (!$apikey || strlen(trim($apikey)) === 0) {
            $errors[] = 'No API key is set for the block, please set this in the global block settings for Anders Pink';
        } else {

            // Try to parse the API key as JSON
            $apikeys = json_decode($apikey, true);
            if (!$apikeys) {
                // As it's failed to parse as JSON, lets assume it's a string key (old method)
                $apikeys = array(array("id" => 1, "key" => $apikey, "label" => "Default"));
            }

            foreach ($apikeys as $apikey) {
                $apikeyoptions[$apikey['id']] = $apikey['label'];
            }


            // Get the briefings/boards for each key
            foreach ($apikeys as $apikey) {
                $fullresponse1 = download_file_content(
                    'https://anderspink.com/api/v2/briefings',
                    array('X-Api-Key' => $apikey['key']),
                    null,
                    true
                );
                $fullresponse2 = download_file_content(
                    'https://anderspink.com/api/v2/boards',
                    array('X-Api-Key' => $apikey['key']),
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
                            $errors[] = $response1['message'] . ' (API key #' . $apikey['id'] . ')';
                        }
                        if ($response2['status'] !== 'success') {
                            $errors[] = $response2['message'] . ' (API key #' . $apikey['id'] . ')';
                        }
                    } else {
                        foreach ($response1['data']['owned_briefings'] as $briefing) {
                            $briefings[$briefing['id']] = $apikey['id'] . '_____' . $briefing['name'];
                        }
                        foreach ($response1['data']['subscribed_briefings'] as $briefing) {
                            $briefings[$briefing['id']] = $apikey['id'] . '_____' . $briefing['name'];
                        }
                        foreach ($response2['data']['owned_boards'] as $board) {
                            $boards[$board['id']] = $apikey['id'] . '_____' . $board['name'];
                        }
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

        $mform->addElement('select', 'config_apikeyid', 'API key', $apikeyoptions, array());

        $radioarray = array();
        $radioarray[] = $mform->createElement('radio', 'config_source', '', get_string('showbriefing', 'block_anderspink'), 'briefing');
        $radioarray[] = $mform->createElement('radio', 'config_source', '', get_string('showsavedboard', 'block_anderspink'), 'board');
        $mform->addGroup($radioarray, 'radioar', '', array(' '), false);
        $mform->setDefault('config_source', 'briefing');

        $mform->addElement('html', '<div id="source_section_briefing">');
        $mform->addElement('select', 'config_briefing', get_string('briefingselect', 'block_anderspink'), $briefings);
        $briefingTimes = array(
            'auto' => 'Auto (recommended)',
            '24-hours' => '24 Hours',
            '3-days' => '3 Days',
            '1-week' => '1 Week',
            '1-month' => '1 Month',
            '3-months' => '3 Months',
        );
        $mform->addElement('select', 'config_briefing_time', get_string('briefingselecttime', 'block_anderspink'), $briefingTimes, array());
        $mform->setDefault('config_briefing_time', 'auto');
        $mform->addHelpButton('config_briefing_time', 'briefingselecttime', 'block_anderspink');

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

        $mform->addElement('advcheckbox', 'config_filter_imageless', get_string('filterimagelessarticles', 'block_anderspink'), '', array('group' => 1), array(0, 1));
        $mform->setDefault('config_filter_imageless', 0);
        $mform->addHelpButton('config_filter_imageless', 'filterimagelessarticles', 'block_anderspink');

        $mform->addElement('advcheckbox', 'config_content_preview', get_string('showcontentpreview', 'block_anderspink'), '', array('group' => 1), array(0, 1));
        $mform->setDefault('config_content_preview', 0);
        $mform->addHelpButton('config_content_preview', 'showcontentpreview', 'block_anderspink');

        $mform->addElement('advcheckbox', 'config_comment', get_string('showcomment', 'block_anderspink'), '', array('group' => 1), array(0, 1));
        $mform->setDefault('config_comment', 0);
        $mform->addHelpButton('config_comment', 'showcomment', 'block_anderspink');

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

                    // listen to when api key select is changed
                    Y.all("select[name=config_apikeyid]").on("change", function (e) {
                        useApiKey(e.currentTarget.get("value"))
                    });


                    // store data-apikeyid attr on each option to allow for filtering later
                    for (var entity of ["briefing", "board"]) {
                        Y.one("select[name=config_"+entity+"]").all("option").each(function(n) {
                            if (n.getAttribute("value").length > 0) {
                                var parts = n.getHTML().split("_____")
                                n.setAttribute("data-apikeyid", parts[0])
                                n.setHTML(parts[1])
                            }
                        })
                    }

                    function useApiKey(keyId) {
                        for (var entity of ["briefing", "board"]) {
                            var node = Y.one("select[name=config_"+entity+"]")
                            var selectedValue = node.get("value")
                            var valueStillAvailable = false
                            node.all("option").hide().setAttribute("disabled")
                            if (keyId !== "") {
                                node.all("option[data-apikeyid=\'"+keyId+"\']").each(function(n) {
                                    n.show().removeAttribute("disabled")
                                    if (selectedValue === n.getAttribute("value")) {
                                        valueStillAvailable = true
                                    }
                                })
                            }
                            if (!valueStillAvailable) {
                                node.set("value", "")
                            }
                        }
                    }

                    // on load, filter the briefings/boards by the selected api key
                    useApiKey(Y.one("select[name=config_apikeyid]").get("value"))

                });
            </script>
        ');
    }
}
