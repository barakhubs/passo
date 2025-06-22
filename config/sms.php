<?php

// config/sms.php
return [
    'furaha_base_url' => env('FURAHA_SMS_BASE_URL', 'https://api.furahasms.com'),
    'furaha_username' => env('FURAHA_SMS_USERNAME'),
    'furaha_api_key' => env('FURAHA_SMS_API_KEY'),
];
