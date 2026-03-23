<?php

return [

    'pinecone' => [
        'api_key' => env('PINECONE_API_KEY'),
        'host'    => env('PINECONE_HOST'),
        'index'   => env('PINECONE_INDEX', 'open-rag'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'sarvam' => [
        'api_key' => env('SARVAM_API_KEY'),
    ],

];
