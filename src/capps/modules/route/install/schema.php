<?php
return array (
  'capps_route' => 
  array (
    'route_id' => 'int(11) NOT NULL auto_increment',
    'language_id' => 'int(11)',
    'structure_id' => 'int(11)',
    'content_id' => 'int(11)',
    'address_id' => 'int(11)',
    'route' => 'varchar(255)',
    'date_created' => 'datetime',
    'date_updated' => 'datetime',
    'data' => 'longtext',
  ),
);
