<?php
return array (
  'capps_setting' => 
  array (
    'setting_uid' => 'varchar(36) NOT NULL',
    'agent_uid' => 'varchar(36)',
    'relation' => 'varchar(64) NOT NULL',
    'type' => 'varchar(32)',
    'name' => 'varchar(255)',
    'description' => 'mediumtext',
    'date_created' => 'datetime',
    'date_updated' => 'datetime',
    'data' => 'mediumtext NOT NULL',
    'media' => 'mediumtext NOT NULL',
    'active' => 'int(1)',
  ),
);
