<?php

namespace Src;

define('CREDENTIALS_CW', __DIR__ . '/../credentials/chatwork.json');

class Chatwork
{
    // URI to chatwork API
    const URI = 'https://api.chatwork.com/v1/rooms/%s/messages';
    // Token of chatwork API
    protected $token = null;
    // Message to be sent
    const MESSAGE = "[To:%s]\n%s\n";

    function __construct()
    {
        $credentials = json_decode(file_get_contents(CREDENTIALS_CW), true);
        $this->token = $credentials["access_token"];
    }

    function createMessage($timesheetResult, $codeToCWId)
    {
        $message = "[Timesheet]\n";
        foreach ($timesheetResult as $code => $value) {
            if (!isset($codeToCWId[strtoupper($code)])) continue;
            $ms = implode(" | ",  array_map(
                function ($v, $k) {
                    return $k . ":" . $v;
                },
                $value,
                array_keys($value)
            ));
            $message .= sprintf(self::MESSAGE, $codeToCWId[strtoupper($code)], $ms);
            var_dump($message);
        }
        return $message;
    }

    /**
     * Send message to chatwork
     * @param  $roomId  Room id
     * @param  $message Message
     */
    function sendMessage($roomId, $message)
    {
        $params = array(
            'body' => $message,
        );

        // Init cURL session
        $ch = curl_init();
        // Set Options on the cURL session
        // Set the URL to fetch
        curl_setopt($ch, CURLOPT_URL, sprintf(self::URI, $roomId));
        // Set HTTP header
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-ChatWorkToken: '. $this->token));
        // Set method to POST
        curl_setopt($ch, CURLOPT_POST, 1);
        // Set data to post
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params, '', '&'));
        // Set return the transfer as a string  of the return value of curl_exec()
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Perform cURL session
        $response = curl_exec($ch);
        // Close cURL session
        curl_close($ch);

        return $response;
    }
}
