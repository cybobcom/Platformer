<?php
return array (
  'capps_structure' => 
  array (
    'structure_id' => 'int(11) NOT NULL auto_increment',
    'address_id' => 'int(11)',
    'language_id' => 'int(11) NOT NULL',
    'language_reference_id' => 'int(11)',
    'template' => 'varchar(255)',
    'parent_id' => 'int(11) NOT NULL',
    'previous_id' => 'int(11) NOT NULL',
    'sorting' => 'int(11)',
    'name' => 'varchar(255)',
    'type' => 'varchar(64) NOT NULL',
    'visible' => 'int(1)',
    'active' => 'int(1)',
    'author' => 'varchar(64)',
    'addressgroups' => 'varchar(255)',
    'data' => 'longtext',
    'media' => 'longtext',
    'date_created' => 'datetime',
    'date_updated' => 'datetime',
    'date_start' => 'datetime',
    'date_end' => 'datetime',
  ),
);
