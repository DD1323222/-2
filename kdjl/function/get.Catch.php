<?php
/**
*@Version: %version%
*@Copyright: %copyright%
*@Author: %author%

*@Write Date: 2008.05.19
*@Update Date: 2008.10.28
*@Usage: study skill of user bb.
*@Memo:
  0: 数据错误
  捕捉功能方法修改。
*/

require_once('../config/config.game.php');
if(empty($_SESSION['id'])) die('0');
define('MEM_FIGHT_KEY', $_SESSION['id'] . 'fight');

$arrobj = new arrays();
secStart($_pm['mem']);

$bid = isset($_REQUEST['pid']) && !is_array($_REQUEST['pid']) ? intval($_REQUEST['pid']) : 0; // bag props id.table:userbag.使用道具ID（精灵球ID）

if($bid < 1) die('0');

require_once('../sec/dblock_fun.php');
$a = getLock(intval($_SESSION['id']));
if(!is_array($a)){
	realseLock();
	die('服务器繁忙，请稍候再试！');
}

function catchUseBall($bid)
{
	global $_pm;
	$uid = intval($_SESSION['id']);
	$bid = intval($bid);
	$sql = "UPDATE userbag SET sums=sums-1 WHERE id=$bid AND uid=$uid AND sums>0";
	if(!$_pm['mysql']->query($sql) || mysql_affected_rows($_pm['mysql']->getConn()) != 1){
		$_pm['mysql']->query('ROLLBACK');
		die('20');
	}
}

function catchAbort($message)
{
	global $_pm;
	$_pm['mysql']->query('ROLLBACK');
	die($message);
}

function catchCommit($gid)
{
	global $_pm;
	if(!$_pm['mysql']->query('COMMIT')){
		$_pm['mysql']->query('ROLLBACK');
		die('服务器繁忙，请稍候再试！');
	}
	$_SESSION['catch_gw_info'] = intval($gid);
}

function catchFinish($gid,$message)
{
	catchCommit($gid);
	die($message);
}

function catchChanceHit($chance)
{
	$chance = max(0,min(1,floatval($chance)));
	if($chance <= 0) return false;
	if($chance >= 1) return true;
	return rand(1,10000) <= intval(round($chance*10000));
}

$user	 = $_pm['user']->getUserById($_SESSION['id']);//用户信息
$sp	     = $_pm['user']->getUserItemById($_SESSION['id'],$bid);//用户包裹信息
$allbb   = $_pm['user']->getUserPetById($_SESSION['id']);//用户宠物信息
$memgpcid = unserialize($_pm['mem']->get('db_gpcid'));
$mempropsid = unserialize($_pm['mem']->get('db_propsid'));

$lockedBall = $_pm['mysql']->getOneRecord("SELECT id,pid,sums FROM userbag WHERE id=$bid AND uid=".intval($_SESSION['id'])." AND sums>0 FOR UPDATE");
if(!is_array($sp) || !is_array($lockedBall) || intval($sp['pid']) != intval($lockedBall['pid'])){
	catchAbort('20');
}
$sp['sums'] = intval($lockedBall['sums']);

$fightKey = 'fight'.$_SESSION['id'];
$test = isset($_SESSION[$fightKey]) ? $_SESSION[$fightKey] : false;
if(!is_array($test) || !isset($test['gid']) || intval($test['gid']) < 1){
	catchAbort('-1');
}
$fightGid = intval($test['gid']);

if(isset($_SESSION['catch_gw_info']) && intval($_SESSION['catch_gw_info']) == $fightGid)
{
	realseLock();
	stopUser2(52);//,true
	die('0');
}

$gs = is_array($memgpcid) && isset($memgpcid[$fightGid]) ? $memgpcid[$fightGid] : false;
/*$gs = $_pm['mem']->dataGet(array('k'	=>	MEM_GPC_KEY,
			 		    'v'	=>  "if(\$rs['id'] == '{$test['gid']}') \$ret=\$rs;"
				 ));*/
				 //当前所打的怪物数据
$bb = $test;
if (!is_array($bb) || !is_array($gs)) catchAbort('-1');
else
{
	$bbrs = $arrobj->dataGet(array('k'	=>	MEM_BB_KEY,
			 		    	  'v'	=>  "if(\$rs['uid'] == '{$_SESSION['id']}' && \$rs['id']=='{$bb['bid']}') \$ret=\$rs;"
					 			),//当前所打怪的宠物数据
							$allbb
						   );
	if (!is_array($bbrs)) $bbrs['level']=0;
}

if (is_array($sp))
{
	
	$prs = $sp;//包裹信息。
	
	// 捕捉道具 和要被捕捉的怪物信息都正确，开始计算。
	if (is_array($prs) && is_array($gs))
	{
	
		if($prs['sums'] < 1)
		{
			catchAbort("20");
		}
		if($bid != $prs['id'])
		{
			catchAbort("20");
		}
		// Start count...
		// 实际捕捉率=[怪物捕捉值/（100－玩家宠物与怪物等级之差）]*（1－怪物当前HP值/怪物最大HP值）*100%+捕捉道具附加捕捉率
		
		//实际捕捉率＝（怪物捕捉值/100）*（1－怪物当前HP值/怪物最大HP值）*100%+捕捉道具附加捕捉率 
		
		// 结果格式：

		$pv = explode(':', $prs['effect']);
		
		if(strtolower($pv[0])=='getitems')//获取装备
		{
			$params = explode(",",$pv[1]);
			$theGPCs = explode("|",$params[0]);
			/*if(!in_array($_SESSION['fight'.$_SESSION['id']]['gid'],$theGPCs))
			{
				die("12");
			}*/
			
			
			
			
			$monsterMaxHp = max(1,floatval($gs['hp']));
			$monsterHp = max(0,min(floatval($bb['hp']),$monsterMaxHp));
			$pzl = max(0,min(1,($gs['catchv']/100)*(1-$monsterHp/$monsterMaxHp)));
			
			if(catchChanceHit($pzl))
			{
				$msg = "";
				$strarr = explode(",",$prs['effect']);
				$items = explode("|",$strarr[1]);
				foreach($items as $v)
				{
					$proparr = explode(":",$v);
					if(count($proparr) < 3 || intval($proparr[0]) < 1 || intval($proparr[1]) < 1 || intval($proparr[2]) < 1) continue;
					$randnum = rand(1,$proparr[1]);
					if($randnum == 1)
					{
						
						$prs = $mempropsid[$proparr[0]];
						/*$prs = $_pm['mem']->dataGet(array('k' => MEM_PROPS_KEY, 
													 'v' => "if(\$rs['id'] == '{$proparr[0]}') \$ret=\$rs;"
										  ));*/
										 
						
						
						$task = new task();
						$giveResult = $task->saveGetPropsMore($proparr[0],$proparr[2]);
						if($giveResult !== true){
							$_pm['mysql']->query('ROLLBACK');
							die($giveResult === '200' ? '背包空间不足！' : '捕捉奖励发放失败！');
						}
						catchUseBall($bid);
						if(isset($proparr[3]) && $proparr[3] == "2")
						{
							$task->saveGword("在 {$gs['name']} 身上成功的发现了 {$prs['name']} {$proparr[2]} 个。");
						}
						$newstr = "恭喜您得到 {$prs['name']} {$proparr[2]} 个。";
						catchFinish($test['gid'],$newstr);
						break;
					}
				}
				catchUseBall($bid);
				catchFinish($test['gid'],'0');
			}
			else{
				catchUseBall($bid);
				catchFinish($test['gid'],'0');
			}
		}
		else if(strtolower($pv[0])=='get')//获取装备
		{
			$theGPCs = explode("|",$pv[1]);			
			
			if(!in_array($_SESSION['fight'.$_SESSION['id']]['gid'],$theGPCs))
			{
				catchAbort("12");
			}
			
			$pvv = str_replace('%','',$pv[2])/100;
			$monsterMaxHp = max(1,floatval($gs['hp']));
			$monsterHp = max(0,min(floatval($bb['hp']),$monsterMaxHp));
			$pzl = max(0,min(1,($gs['catchv']/100)*(1-$monsterHp/$monsterMaxHp)+$pvv));
			
			if(catchChanceHit($pzl)) // Catch ok.
			{
				//掉落物品获取。格式：道具ID：机率范围。
				$prpid = intval($pv[4]);
				$okidlist = $drop = "";
				if ($prpid === false || $prpid == 0 || $prpid == '') $drop = '无';
				else
				{
					$rarr = array($prpid);
					foreach ($rarr as $k => $v)
					{
						
						/*$prs = $_pm['mem']->dataGet(array('k' => MEM_PROPS_KEY, 
												 'v' => "if(\$rs['id'] == '{$v}') \$ret=\$rs;"
									  ));*/

						$prs = $mempropsid[$v];
						if( is_array($prs) )
						{
							$drop .= $prs['name'].',';
							$okidlist .= $v.',';
						} 
					}// end foreach.
					$drop = substr($drop, 0, -1);
					$okidlist = substr($okidlist, 0, -1);
					$task = new task();
					$giveResult = $task->saveGetPropsMore($prpid,1);
					if($giveResult !== true){
						$_pm['mysql']->query('ROLLBACK');
						die($giveResult === '200' ? '背包空间不足！' : '捕捉奖励发放失败！');
					}
				}
				
				//发公告			
				if($pv['3'] == 2)
				{
					$task = new task();
					$task->saveGword("成功的获取了: ".$drop."，太爽了！");
				}
				
				catchUseBall($bid);
				catchFinish($test['gid'],'15');
			}else{
				catchUseBall($bid);
				catchFinish($test['gid'],'13');
			}
		}
		else if(strtolower($pv[0])=='catch')
		{
			if ($gs['catchid'] == 0) catchAbort('3'); // 此怪不能捕捉
			$pvv = str_replace('%','',$pv[2])/100;
			$gwidarr = explode("|",$pv[1]);
			if(!in_array($gs['id'],$gwidarr))
			{
				catchAbort("7");//不能捕捉此宝宝
			}
			$carriedPets = $_pm['mysql']->getRecords("SELECT id FROM userbb WHERE uid=".intval($_SESSION['id'])." AND muchang=0 FOR UPDATE");
			if($carriedPets === false && mysql_errno($_pm['mysql']->getConn()) != 0){
				catchAbort('服务器繁忙，请稍候再试！');
			}
			$carriedCount = is_array($carriedPets) ? count($carriedPets) : 0;
			if($carriedCount >= 3){
				catchAbort('6');
			}
			
			
			
			$monsterMaxHp = max(1,floatval($gs['hp']));
			$monsterHp = max(0,min(floatval($bb['hp']),$monsterMaxHp));
			$pzl = max(0,min(1,($gs['catchv']/100)*(1-$monsterHp/$monsterMaxHp)+$pvv));
			
			if(catchChanceHit($pzl)) // Catch ok.
			{
				$newpetsid = intval($gs['catchid']);
				// Get new bb info.
						$membbid = unserialize($_pm['mem']->get('db_bbid'));
						$bb = is_array($membbid) && isset($membbid[$newpetsid]) ? $membbid[$newpetsid] : false;
						/*$bb = $_pm['mem']->dataGet(array('k'	=>	MEM_BB_KEY,
						'v'	=>  "if(\$rs['id'] == '{$newpetsid}') \$ret=\$rs;"
						),
						$allbb
				 );*/
				if (!is_array($bb) || intval($bb['id']) != $newpetsid || trim($bb['name']) == '' || trim($bb['name']) == '0'
					|| intval($gs['wx']) != intval($bb['wx'])){
					$_pm['mysql']->query('ROLLBACK');
					die('2');
				}
				$czl = getCzl($bb['czl']);
				if($czl === false || $czl <= 0){
					$_pm['mysql']->query('ROLLBACK');
					die('捕捉宠物成长配置错误！');
				}
				
				// insert into userbb.
				//$bbid= $newid = mem_get_autoid($m, MEM_ORDER_KEY, 'userbb');
				
				$uinfo = $user;
				$petName = $_pm['mysql']->escape($bb['name']);
				$petUsername = $_pm['mysql']->escape(isset($uinfo['nickname']) ? $uinfo['nickname'] : '');
				$petSkillList = $_pm['mysql']->escape(isset($bb['skillist']) ? $bb['skillist'] : '');
				$petRemakeLevel = $_pm['mysql']->escape(isset($bb['remakelevel']) ? $bb['remakelevel'] : '');
				$petRemakeId = $_pm['mysql']->escape(isset($bb['remakeid']) ? $bb['remakeid'] : '');
				$petRemakePid = $_pm['mysql']->escape(isset($bb['remakepid']) ? $bb['remakepid'] : '');
				$petInserted = $_pm['mysql']->query("INSERT INTO userbb(name,uid,username,level,wx,ac,mc,srchp,hp,srcmp,mp,skillist,stime,nowexp,
						lexp,imgstand,imgack,imgdie,hits,miss,speed,kx,remakelevel,remakeid,remakepid,czl,headimg,cardimg,effectimg)
				VALUES('{$petName}','".intval($uinfo['id'])."','{$petUsername}','1','".intval($bb['wx'])."',
				   '".floatval($bb['ac'])."','".floatval($bb['mc'])."','".floatval($bb['hp'])."','".floatval($bb['hp'])."','".floatval($bb['mp'])."','".floatval($bb['mp'])."','{$petSkillList}',unix_timestamp(),
				  '{$bb['nowexp']}','100','{$bb['imgstand']}','{$bb['imgack']}','{$bb['imgdie']}',
				   '".floatval($bb['hits'])."','".floatval($bb['miss'])."','".floatval($bb['speed'])."','{$bb['kx']}','{$petRemakeLevel}',
				   '{$petRemakeId}','{$petRemakePid}','".floatval($czl)."','{$bb['headimg']}','{$bb['cardimg']}','{$bb['effectimg']}')
				");
				if(!$petInserted || mysql_affected_rows($_pm['mysql']->getConn()) != 1){
					$_pm['mysql']->query('ROLLBACK');
					die('捕捉宠物保存失败，请稍候再试！');
				}
				$bbid = intval($_pm['mysql']->last_id());
				if($bbid < 1){
					$_pm['mysql']->query('ROLLBACK');
					die('捕捉宠物保存失败，请稍候再试！');
				}
				
				//修复只能有一种技能的bug技能，和吸血技能
				$arr = explode(",", $bb['skillist']);
				$memskillsysid = unserialize($_pm['mem']->get('db_skillsysid'));
				foreach($arr as $av)
				{
					if($av === '' || $av === '0')
					{
						continue;
					}
					$newarr = explode(":",$av);
					if(count($newarr) != 2 || !ctype_digit($newarr[0]) || !ctype_digit($newarr[1])
						|| intval($newarr[0]) < 1 || intval($newarr[1]) < 1)
					{
						$_pm['mysql']->query('ROLLBACK');
						die('捕捉宠物技能配置错误！');
					}
					$jn = is_array($memskillsysid) && isset($memskillsysid[$newarr[0]]) ? $memskillsysid[$newarr[0]] : false;
					/*$jn = $_pm['mem']->dataGet(array('k'	=>	MEM_SKILLSYS_KEY,
						'v'	=>  "if(\$rs['id'] == '{$newarr[0]}') \$ret=\$rs;"
					));*/
					if(!is_array($jn)){
						$_pm['mysql']->query('ROLLBACK');
						die('捕捉宠物技能配置错误！');
					}
					$skillLevel = intval($newarr[1]);
					$skillIndex = $skillLevel-1;
					$ack  = explode(",", isset($jn['ackvalue']) ? $jn['ackvalue'] : '');
					$plus = explode(",", isset($jn['plus']) ? $jn['plus'] : '');
					$uhp  = explode(",", isset($jn['uhp']) ? $jn['uhp'] : '');
					$ump  = explode(",", isset($jn['ump']) ? $jn['ump'] : '');
					$img = explode(",",isset($jn['imgeft']) ? $jn['imgeft'] : '');
					if(!isset($ack[$skillIndex]) || !isset($uhp[$skillIndex]) || !isset($ump[$skillIndex])){
						$_pm['mysql']->query('ROLLBACK');
						die('捕捉宠物技能等级配置错误！');
					}
					$skillName = $_pm['mysql']->escape(isset($jn['name']) ? $jn['name'] : '');
					$skillVary = $_pm['mysql']->escape(isset($jn['vary']) ? $jn['vary'] : '');
					$skillValue = $_pm['mysql']->escape($ack[$skillIndex]);
					$skillPlus = $_pm['mysql']->escape(isset($plus[$skillIndex]) ? $plus[$skillIndex] : '');
					$skillImg = $_pm['mysql']->escape(isset($img[$skillIndex]) ? $img[$skillIndex] : '');
					$skillInserted = $_pm['mysql']->query("INSERT INTO skill(bid,name,level,vary,wx,value,plus,img,uhp,ump,sid)
					VALUES({$bbid}, '{$skillName}','{$skillLevel}','{$skillVary}','".intval($jn['wx'])."','{$skillValue}','{$skillPlus}','{$skillImg}',".intval($uhp[$skillIndex]).",".intval($ump[$skillIndex]).",".intval($jn['id']).")
					");
					if(!$skillInserted || mysql_affected_rows($_pm['mysql']->getConn()) != 1){
						$_pm['mysql']->query('ROLLBACK');
						die('捕捉宠物技能保存失败，请稍候再试！');
					}
				}
				// Get jn info.
				/*$jn = $_pm['mem']->dataGet(array('k'	=>	MEM_SKILLSYS_KEY,
						'v'	=>  "if(\$rs['id'] == '{$arr[0]}') \$ret=\$rs;"
				));
				$ack  = split(",", $jn['ackvalue']);
				$plus = split(",", $jn['plus']);
				$uhp  = split(",", $jn['uhp']);
				$ump  = split(",", $jn['ump']);
				获取刚插入宠物ID。
				$newbb = $_pm['mysql']->getOneRecord("SELECT id 
							  FROM userbb
							 WHERE uid={$_SESSION['id']}
							 ORDER BY stime DESC
							 LIMIT 0,1			                                         
						  ");
				$bbid = $newbb['id'];
				
				// Insert userbb jn.	
				//$newid = mem_get_autoid($m, MEM_ORDER_KEY,'skill');
				echo "INSERT INTO skill(bid,name,level,vary,wx,value,plus,img,uhp,ump,sid)
				VALUES({$bbid}, '{$jn['name']}','{$arr['1']}','{$jn['vary']}','{$jn['wx']}','{$ack['0']}','{$plus['0']}','{$jn['img']}',{$uhp['0']},{$ump['0']},{$jn['id']})
				";exit;
				$_pm['mysql']->query("INSERT INTO skill(bid,name,level,vary,wx,value,plus,img,uhp,ump,sid)
				VALUES({$bbid}, '{$jn['name']}','{$arr['1']}','{$jn['vary']}','{$jn['wx']}','{$ack['0']}','{$plus['0']}','{$jn['img']}',{$uhp['0']},{$ump['0']},{$jn['id']})
				");*/
				//减去精灵球
				catchUseBall($bid);
				catchCommit($test['gid']);
				if(isset($pv['3']) && $pv['3'] == 2){
					$task = new task();
					$task->saveGword("成功的捕捉到了 {$bb['name']} ，太有才了！");
				}
				//$_pm['user']->updateMemUserbb($_SESSION['id']);
				//$_pm['user']->updateMemUsersk($_SESSION['id']);
				die('10');
			}
			else
			{ // Clear props.
				catchUseBall($bid);
				catchFinish($test['gid'],'0');
				//$_pm['user']->updateMemUserbag($_SESSION['id']);
			} // 捕捉机率太低。	 
		}
	}
}
$_pm['mysql']->query('ROLLBACK');
$_pm['mem']->memClose();
echo "0";


/**
* @Usage: 存储用户得到的道具到用户包裹.
* @Param: String, format: 1,2,3
* @Logic: 
  如果用户包裹有此物品，如果可以折叠，直接累加，否则插入新纪录。
  >>增加物品说明字段
*/
function saveGetProps($idlist)
{
	if ($idlist == '' or $idlist == 0) return false;
	global $_pm,$_bag,$user;
	$arrobj = new arrays();

	$l=0;
	if (is_array($_bag))
	{
		foreach ($_bag as $x => $y)
		{
			if ($y['sums']>0 && $y['zbing']==0) $l++;
		}
	}
	if ($l >= $user['maxbag']) return false;	
	
	$arr = split(',', $idlist);
	foreach ($arr as $k => $v)
	{
		$rs = $arrobj->dataGet(array('k' => MEM_USERBAG_KEY, 
									 'v' => "if(\$rs['uid']=='{$_SESSION['id']}' && \$rs['pid']=='{$v}') \$ret=\$rs;"
									 ),
								   $_bag
							  ); 
		
		//$rs = $_pm['mysql']->getOneRecord("SELECT * FROM userbag WHERE uid={$_SESSION['id']} and pid={$v}");
		if (is_array($rs))
		{
			if ($rs['vary'] == 1) // 可折叠道具.
			{
				$_pm['mysql']->query("UPDATE userbag
							   SET sums=sums+1
							 WHERE id={$rs['id']}
						  ");
			}
			else
			{
				//$newid = mem_get_autoid($m, MEM_ORDER_KEY, 'userbag');
				$_pm['mysql']->query("INSERT INTO userbag(uid,pid,sell,vary,sums,stime)
							VALUES(
								   {$user['id']},
								   {$v},
								   {$rs['sell']},
								   2,
								   1,
								   unix_timestamp()
								  );
						  ");
				 $l++;
			}
		}
		else{
			$mempropsid = unserialize($_pm['mem']->get('db_propsid'));
			$rs = $mempropsid[$v];
			/*$rs = $_pm['mem']->dataGet(array('k' => MEM_PROPS_KEY, 
								    'v' => "if(\$rs['id'] == '{$v}') \$ret=\$rs;"
								  ));*/
			if (is_array($rs))
			{
				//$newid = mem_get_autoid($m, MEM_ORDER_KEY, 'userbag');
				$_pm['mysql']->query("INSERT INTO userbag(uid,pid,sell,vary,sums,stime)
							VALUES(
								   {$user['id']},
								   {$v},
								   {$rs['sell']},
								   {$rs['vary']},
								   1,
								   unix_timestamp()
								  );
						  ");
				 $l++;
			}	
		}		
		unset($rs);
		// 检测是否超出包裹，
		if ($l >= $user['maxbag']) return false;
	}	
}
?>
