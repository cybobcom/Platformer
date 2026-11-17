<?php
return [
    'mode'            => 'off',  // off | auto | on
    'shed_percent'    => 50,     // nur bei 'on'
    'auto'            => ['load_threshold' => 4, 'max_percent' => 80],
    'retry_after'     => 60,
    'json_interfaces' => [],     // z. B. ['news/list', 'events/json']
    'message_title'   => 'Kurze Pause',
    'message_html'    => 'Aktuell sind sehr viele Nutzer online. Bitte in einigen Minuten erneut versuchen.',
];