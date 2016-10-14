<?php

class block_anderspink_edit_form extends block_edit_form {

    protected function specific_definition($mform) {
        
        $briefings = array('' => 'Select a briefing');
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
                }
            }
        }
        
        // Section header title according to language file.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        // A sample string variable with a default value.
        $mform->addElement('text', 'config_title', get_string('blockstring', 'block_anderspink'));
        $mform->setType('config_title', PARAM_TEXT);        
        
        /*
        if (count($errors)) {
            foreach ($errors as $i => $error) {
                $mform->addElement('text', 'error_' . $i, 'There was an error getting the list of briefings from the API: ' . $error);
            }
        }
        */
        
        $mform->addElement('select', 'config_briefing', get_string('briefingselect', 'block_anderspink'), $briefings, array());

    }
}
