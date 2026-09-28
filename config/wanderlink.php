<?php

return [
    /*
    | Login screen media. Any Unsplash / Pexels URL works. The brand gradient is
    | painted underneath and the media is alpha-masked on top, so a blocked or
    | retired URL costs elegance, never function.
    */
    'login' => [
        'image' => env('LOGIN_IMAGE_URL') ?: 'https://images.unsplash.com/photo-1516426122078-c23e76319801?auto=format&fit=crop&w=1600&q=70',
        'video' => env('LOGIN_VIDEO_URL') ?: 'https://www.pexels.com/download/video/8865903/', // e.g. a Pexels .mp4 link; muted, looped, skipped for reduced-motion users
        'credit' => env('LOGIN_MEDIA_CREDIT') ?: 'Video via Pexels · photo via Unsplash',
    ],
];
