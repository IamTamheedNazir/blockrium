<?php

use App\Models\ResUser;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use App\Notifications\PushNotification;
use Kreait\Firebase\Messaging\RegistrationToken;


if (!function_exists('format_date')) {
    function format_date($date)
    {
        return \Carbon\Carbon::parse($date)->format('F j, Y');
    }
}

if (!function_exists('commonCURLRequest')) {
        //
        function commonCURLRequest($url, $param = [])
        {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, isset($param["CURLOPT_HEADER"]) ? $param["CURLOPT_HEADER"] : TRUE);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, isset($param["CURLOPT_FOLLOWLOCATION"]) ? $param["CURLOPT_FOLLOWLOCATION"] : FALSE);
            curl_setopt($ch, CURLOPT_TIMEOUT, isset($param["TIMEOUT"]) ? $param["TIMEOUT"] : 5);
            //
            if (isset($param["AUTH_NAME"]) && isset($param["AUTH_PASS"])) {
                curl_setopt($ch, CURLOPT_USERPWD, $param["AUTH_NAME"] . ':' . $param["AUTH_PASS"]);
            };
            //
            if (isset($param["REQUEST_HEADER"]) && is_array($param["REQUEST_HEADER"])) {
                $curlHeaders = [];
                foreach ($param["REQUEST_HEADER"] as $k => $v) {
                    $curlHeaders[] = "$k: $v";
                };
                curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
            };
            //
            if (isset($param["USER_AGENT"])) {
                curl_setopt($ch, CURLOPT_USERAGENT, $param["USER_AGENT"]);
            };
            //
            if (isset($param["CURLOPT_IPRESOLVE"])) {
                curl_setopt($ch, CURLOPT_IPRESOLVE, $param["CURLOPT_IPRESOLVE"]);
            };
            //
            if (isset($param["POST"])) {
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $param["POST"]);
            };

            if (isset($param["PUT"])) {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, ($param["PUT"]));
            }
            if(isset($param["DELETE"])) {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            }
            //
            $response = curl_exec($ch);
            $responseErrors = [];
            if ($response === FALSE) {
                $responseErrors["ERROR"] = curl_error($ch);
                $responseErrors["CODE"] = curl_errno($ch);
            };
            $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            //
            curl_close($ch);
            return [$response, $responseCode, $responseErrors];
        }
        //
    }
if (!function_exists('sendPushNotification')) {
    function sendPushNotification($tokens, $title, $body)
    {
        // Firebase Cloud Messaging API Key (you should replace this with your actual FCM server key)
        $SERVER_API_KEY = env('FIREBASE_SERVER_KEY'); // Make sure you have set this in your .env file
        
        // Prepare the data to send via FCM
        $data = [
            "registration_ids" => $tokens, // This is an array of device tokens
            "notification" => [
                "title" => $title,
                "body" => $body,
            ],
        ];

        // Convert the data array to JSON
        $dataString = json_encode($data);

        // Set the headers for the request
        $headers = [
            'Authorization: key=' . $SERVER_API_KEY,
            'Content-Type: application/json',
        ];

        // Initialize cURL session
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);

        // Execute the cURL request
        $response = curl_exec($ch);

        // Check for cURL errors
        if ($response === false) {
            // Handle error
            $error = curl_error($ch);
            curl_close($ch);
            return response()->json(['status' => 'error', 'message' => $error]);
        }

        // Close cURL session
        curl_close($ch);

        // Return the response from Firebase
        return response()->json(['status' => 'success', 'response' => json_decode($response)]);
    }
}


if (!function_exists('sendPushNotificationNew')) {
    function sendPushNotificationNew($deviceToken, $subject, $body)
    {
        // Initialize Firebase with credentials
        $firebase = (new Factory)->withServiceAccount(env('FIREBASE_CREDENTIALS_PATH'));
        $messaging = $firebase->createMessaging();

        // Ensure the device token is not empty
        if (empty($deviceToken)) {
            return response()->json(['error' => 'Device token is missing'], 400);
        }

        // Create a notification message
       $message = CloudMessage::withTarget('token', $deviceToken)
            ->withNotification(['title' => $subject, 'body' => $body]);

        try {
            // Send the notification
            $response = $messaging->send($message);

            return response()->json(['status' => 200, 'message' => 'Notification sent successfully', 'response' => $response]);

        } catch (\Kreait\Firebase\Exception\Messaging\InvalidMessage $e) {
            return response()->json(['status' => 400, 'error' => 'Invalid message: ' . $e->getMessage()], 400);

        } catch (\Exception $e) {
            return response()->json(['status' => 500, 'error' => 'Error sending notification: ' . $e->getMessage()], 500);
        }
    }
}

