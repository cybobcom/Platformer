<?php
return array (
  'capps_content' => 
  array (
    'content_id' => 'int(11) NOT NULL auto_increment',
    'address_id' => 'int(11)',
    'language_id' => 'int(11) NOT NULL',
    'language_reference_id' => 'int(11)',
    'structure_id' => 'int(11)',
    'template' => 'varchar(255)',
    'previous_id' => 'int(11)',
    'sorting' => 'int(11)',
    'type' => 'varchar(32)',
    'name' => 'varchar(255)',
    'teaser' => 'longtext',
    'content' => 'longtext',
    'data' => 'longtext',
    'media' => 'longtext',
    'active' => 'int(1)',
    'author' => 'varchar(64)',
    'addressgroups' => 'varchar(255)',
    'date_created' => 'datetime',
    'date_updated' => 'datetime',
    'date_start' => 'datetime',
    'date_end' => 'datetime',
  ),
);
