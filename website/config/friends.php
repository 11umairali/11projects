<?php

/*
|--------------------------------------------------------------------------
| Friends & phone discovery
|--------------------------------------------------------------------------
| A phone number only counts after it was VERIFIED by a code sent to that number – otherwise anyone could type somebody
| else's number to find out who has that person saved. So SMS delivery must be configured for the feature to work.
|
|   FRIENDS_SMS_DRIVER=none     (default) phone verification is switched off, nobody can be discovered
|   FRIENDS_SMS_DRIVER=log      TESTING ONLY: the code is written to storage/logs/laravel.log instead of being sent
|   FRIENDS_SMS_DRIVER=twilio   real SMS through Twilio:
|       TWILIO_SID=ACxxxxxxxx   TWILIO_TOKEN=xxxxxxxx   TWILIO_FROM=+1555…  (a number or sender id owned by your Twilio account)
*/
return [
    'sms_driver' => env('FRIENDS_SMS_DRIVER', 'none'),
    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],
    'code_ttl_minutes' => 10,
    'max_suggestions' => 50,
];
