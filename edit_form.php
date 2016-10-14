<?php

class block_anderspink_edit_form extends block_edit_form {

    protected function specific_definition($mform) {
        
        $briefings = array('' => 'Select a briefing');
        $boards = array('' => 'Select a saved board');
        $errors = array();
        $apiKey = get_config('anderspink', 'key');
        
        if (!$apiKey || strlen(trim($apiKey)) === 0) {
            $errors[] = 'No API key is set for the block, please set this in the global block settings for Anders Pink';
        } else {
            $fullResponse = download_file_content(
                'https://anderspink.com/api/v1/users/current',
                array('X-Api-Key' => $apiKey),
                null,
                true
            );
            $response = json_decode($fullResponse->results, true);
            
            if (!$response) {
                $errors[] = 'Failed to do API call';
            } else {
                if ($response['status'] !== 'success') {
                    $errors[] = $response['message'];
                } else {
                    foreach ($response['data']['owned_briefings'] as $briefing) {
                        $briefings[$briefing['id']] = $briefing['name'];
                    }
                    foreach ($response['data']['subscribed_briefings'] as $briefing) {
                        $briefings[$briefing['id']] = $briefing['name'];
                    }
                    foreach ($response['data']['owned_boards'] as $board) {
                        $boards[$board['id']] = $board['name'];
                    }
                }
            }
        }
        
        // Section header title according to language file.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        // A sample string variable with a default value.
        $mform->addElement('text', 'config_title', get_string('blockstring', 'block_anderspink'));
        $mform->setType('config_title', PARAM_TEXT);
        
        $radioarray = array();
        $radioarray[] = $mform->createElement('radio', 'config_source', '', get_string('showbriefing', 'block_anderspink'), 'briefing', $attributes);
        $radioarray[] = $mform->createElement('radio', 'config_source', '', get_string('showsavedboard', 'block_anderspink'), 'board', $attributes);
        $mform->addGroup($radioarray, 'radioar', '', array(' '), false);
        $mform->setDefault('config_source', 'briefing');
  
        
        /*
        if (count($errors)) {
            foreach ($errors as $i => $error) {
                $mform->addElement('text', 'error_' . $i, 'There was an error getting the list of briefings from the API: ' . $error);
            }
        }
        */
        
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
