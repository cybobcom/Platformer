<?php
return [
    'mode'            => 'off',  // off | auto | on
    'shed_percent'    => 50,     // only for 'on'
    'auto'            => ['load_threshold' => 4, 'max_percent' => 80],
    'retry_after'     => 60,
    'json_interfaces' => [],     // e.g. ['news/list', 'events/json']
    'message_title'   => 'Kurze Pause',
    'message_html'    => 'Aktuell sind sehr viele Nutzer online. Bitte in einigen Minuten erneut versuchen.',
];