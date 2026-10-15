<?php

return [

    'custom' => [
        'email' => [
            'required' => 'Please enter your email address.',
            'email' => 'Please enter a valid email address (e.g., name@company.com).',
            'unique' => 'This email is already registered. Try logging in instead.',
        ],
        'password' => [
            'required' => 'Please enter a password.',
            'min' => 'Your password must be at least :min characters long.',
            'confirmed' => 'The passwords don\'t match. Please try again.',
        ],
        'name' => [
            'required' => 'Please enter your name.',
            'max' => 'Name cannot exceed :max characters.',
        ],
        'first_name' => [
            'required' => 'Please enter a first name.',
        ],
        'last_name' => [
            'required' => 'Please enter a last name.',
        ],
        'subject' => [
            'required' => 'Please enter a subject line.',
        ],
        'workspace_name' => [
            'required' => 'Please give your workspace a name.',
            'max' => 'Workspace name cannot exceed :max characters.',
        ],
    ],

    'attributes' => [
        'email' => 'email address',
        'password' => 'password',
        'password_confirmation' => 'password confirmation',
        'first_name' => 'first name',
        'last_name' => 'last name',
        'workspace_name' => 'workspace name',
        'body_html' => 'email content',
        'imap_host' => 'incoming mail server',
        'imap_port' => 'incoming port',
        'imap_username' => 'mail username',
        'imap_password' => 'mail password',
        'smtp_host' => 'outgoing mail server',
        'smtp_port' => 'outgoing port',
    ],

];
