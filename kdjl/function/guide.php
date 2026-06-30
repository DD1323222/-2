<?php
require_once('../config/config.game.php');
secStart($_pm['mem']);
require_once('../sec/dblock_fun.php');
$a = getLock($_SESSION['id']);
if(!is_array($a)){
	realseLock();
	die('服务器繁忙，请稍候再试！');
}
$op = $_GET['op'];
$user = $_pm['user']->getUserById($_SESSION['id']);

if($op == 'ajax_guide'){
	$arr = $_pm['mysql'] -> getOneRecord("SELECT new_guide_step FROM player_ext WHERE uid=".$_SESSION['id']);
	echo $arr['new_guide_step'];
}else if($op == 'add_guide_step'){
	$client_step = isset($_GET['step']) ? intval($_GET['step']) : -2;
	if($client_step < 0 || $client_step >= 20){
		realseLock();
		die('ERROR:Invalid guide step');
	}
	$_pm['mysql'] -> query("UPDATE player_ext SET new_guide_step = new_guide_step+1 WHERE uid = {$_SESSION['id']} AND new_guide_step = {$client_step} AND new_guide_step < 20");
	if(mysql_affected_rows($_pm['mysql'] -> getConn()) != 1){
		$user1 = $_pm['mysql'] -> getOneRecord("SELECT new_guide_step FROM player_ext WHERE uid = {$_SESSION['id']}");
		if(is_array($user1) && isset($user1['new_guide_step'])){
			realseLock();
			die('SYNC:'.intval($user1['new_guide_step']));
		}
		realseLock();
		die('操作错误(1)');
	}
	$prize_arr=array(3=>'1308:10',4=>'1241:2',6=>'912:10',8=>'1039:1',14=>'1308:5,1992:5,2493:1',20=>'2047:1');
	$sql = "SELECT new_guide_step FROM player_ext WHERE uid = {$_SESSION['id']}";
	$user1 = $_pm['mysql'] -> getOneRecord($sql);
	if(!array_key_exists($user1['new_guide_step'],$prize_arr)){
		echo 'OK:'.intval($user1['new_guide_step']);
		realseLock();
		die();
	}
	$prize = $prize_arr[$user1['new_guide_step']];
	$arr = explode(',',$prize);
	$task = new task();
	foreach($arr as $v){
		$inarr = explode(':',$v);
		$task->saveGetPropsMore($inarr[0],$inarr[1]);
	}
	echo 'OK:'.intval($user1['new_guide_step']);
}else if($op == 'do_over'){
	$client_step = isset($_GET['step']) ? intval($_GET['step']) : -2;
	$sql = "SELECT new_guide_step FROM player_ext WHERE uid = {$_SESSION['id']}";
	$user1 = $_pm['mysql'] -> getOneRecord($sql);
	if($user1['new_guide_step'] >= 20 || $user1['new_guide_step'] < 0){
		realseLock();
		die('SYNC:'.intval($user1['new_guide_step']));
	}
	if($client_step !== intval($user1['new_guide_step'])){
		realseLock();
		die('SYNC:'.intval($user1['new_guide_step']));
	}
	$task = new task();
	$task->saveGetPropsMore(2047,1);

	$_pm['mysql'] -> query("UPDATE player_ext SET new_guide_step = -1 WHERE uid = {$_SESSION['id']}");
	realseLock();
	echo 'OK:';
	die('跳过新手引导，获得新手90级礼包。');
}
realseLock();
?>
