<?php

namespace Src;

use Google_Client;
use Google_Service_Sheets;

define('APPLICATION_NAME', 'Framgia Timesheet Checking');
define('CREDENTIALS_PATH', __DIR__ . '/../credentials/sheets.googleapis.com.json');
define('CLIENT_SECRET_PATH', __DIR__ . '/../credentials/client_secret.json');
define('SCOPES', implode(' ', [Google_Service_Sheets::SPREADSHEETS_READONLY]));

class Google {

    public function __construct() {}

    /**
     * Returns an authorized API client.
     * @return Google_Client the authorized client object
     */
    function getClient() {
        $client = new Google_Client();
        $client->setApplicationName(APPLICATION_NAME);
        $client->setScopes(SCOPES);
        $client->setAuthConfig(CLIENT_SECRET_PATH);
        $client->setAccessType('offline');

        // Load previously authorized credentials from a file.
        if (file_exists(CREDENTIALS_PATH)) {
            $accessToken = json_decode(file_get_contents(CREDENTIALS_PATH), true);
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

            // Store the credentials to disk.
            if(!file_exists(dirname(CREDENTIALS_PATH))) {
                mkdir(dirname(CREDENTIALS_PATH), 0700, true);
            }
            file_put_contents(CREDENTIALS_PATH, json_encode($accessToken));
            printf("Credentials saved to %s\n", CREDENTIALS_PATH);
        }
        $client->setAccessToken($accessToken);

        // Refresh the token if it's expired.
        if ($client->isAccessTokenExpired()) {
            $refreshTokenSaved = $client->getRefreshToken();
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            $accessTokenUpdated = $client->getAccessToken();
            $accessTokenUpdated['refresh_token'] = $refreshTokenSaved;
            file_put_contents(CREDENTIALS_PATH, json_encode($accessTokenUpdated));
        }
        return $client;
    }

    /**
     * Returns Google Service Sheets.
     * @return Google_Service_Sheets object
     */
    public function getServiceSheets()
    {
        $client = $this->getClient();
        $service = new Google_Service_Sheets($client);
        return $service;
    }

}