<?php
$adminDropConfig = array(
	'title' => '怪物掉落管理',
	'active' => 'monster_drops',
	'field' => 'droplist',
	'field_label' => '普通掉落',
	'page' => 'drops.php'
);
require(dirname(__FILE__) . '/_drop_manage.php');
