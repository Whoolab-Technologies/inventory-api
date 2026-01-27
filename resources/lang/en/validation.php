<?php

return [
    'required' => 'The :attribute field is required.',
    'string' => 'The :attribute must be a string.',
    'integer' => 'The :attribute must be an integer.',
    'numeric' => 'The :attribute must be a number.',
    'boolean' => 'The :attribute field must be true or false.',
    'email' => 'The :attribute must be a valid email address.',
    'date' => 'The :attribute is not a valid date.',
    'array' => 'The :attribute must be an array.',
    'file' => 'The :attribute must be a file.',
    'image' => 'The :attribute must be an image.',
    'url' => 'The :attribute format is invalid.',
    'confirmed' => 'The :attribute confirmation does not match.',
    'unique' => 'The :attribute has already been taken.',
    'exists' => 'The selected :attribute is invalid.',
    'same' => 'The :attribute and :other must match.',
    'different' => 'The :attribute and :other must be different.',
    'min' => [
        'string' => 'The :attribute must be at least :min characters.',
        'numeric' => 'The :attribute must be at least :min.',
        'array' => 'The :attribute must have at least :min items.',
        'file' => 'The :attribute must be at least :min kilobytes.',
    ],

    'max' => [
        'string' => 'The :attribute must not be greater than :max characters.',
        'numeric' => 'The :attribute must not be greater than :max.',
        'array' => 'The :attribute must not have more than :max items.',
        'file' => 'The :attribute must not be greater than :max kilobytes.',
    ],

    'size' => [
        'string' => 'The :attribute must be :size characters.',
        'numeric' => 'The :attribute must be :size.',
        'array' => 'The :attribute must contain :size items.',
        'file' => 'The :attribute must be :size kilobytes.',
    ],

    'in' => 'The selected :attribute is invalid.',
    'not_in' => 'The selected :attribute is invalid.',
    'regex' => 'The :attribute format is invalid.',
    'distinct' => 'The :attribute field has a duplicate value.',
    'accepted' => 'The :attribute must be accepted.',
];
