<?php
return array (
  'capps_category' => 
  array (
    'category_id' => 'int(11) unsigned NOT NULL auto_increment',
    'language_id' => 'int(11)',
    'parent_id' => 'int(11)',
    'sorting' => 'int(11) NOT NULL',
    'name' => 'varchar(255)',
    'description' => 'longtext',
    'value' => 'varchar(32)',
    'entity' => 'varchar(32)',
    'template_id' => 'int(11)',
    'media' => 'mediumtext',
    'data' => 'longtext',
    'date_created' => 'datetime',
    'date_updated' => 'datetime',
    'active' => 'tinyint(4)',
    'flags' => 'varchar(255)',
    'tags' => 'varchar(255)',
  ),
);
