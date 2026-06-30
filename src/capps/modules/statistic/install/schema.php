<?php
return array (
  'capps_statistic' => 
  array (
    'statistic_id' => 'int(11) NOT NULL auto_increment',
    'date' => 'datetime',
    'content_id' => 'int(11)',
    'structure_id' => 'int(11)',
    'language_id' => 'int(11)',
    'tracking_id' => 'int(11)',
    'access_status' => 'varchar(16)',
    'description' => 'varchar(255)',
    'user_id' => 'int(11)',
    'ip_address' => 'varchar(32)',
    'ip_name' => 'varchar(255)',
    'url' => 'varchar(255)',
    'browser' => 'varchar(255)',
    'referer' => 'varchar(255)',
    'session' => 'varchar(255)',
    'data' => 'mediumtext',
    'private_device' => 'varchar(255)',
    'private_device_width' => 'varchar(255)',
    'private_cbin' => 'varchar(255)',
    'private_loading_time' => 'float(10,3)',
  ),
);
