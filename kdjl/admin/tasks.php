<?php
require_once(dirname(__FILE__) . '/_bootstrap.php');
require_once(dirname(__FILE__) . '/_layout.php');

function adminTaskColorLabels()
{
	global $_task;
	$labels = array();
	if (isset($_task['varytype']) && is_array($_task['varytype']))
	{
		foreach ($_task['varytype'] as $id => $label) $labels[intval($id)] = $label;
	}
	return $labels;
}

function adminTaskCategories($colors)
{
	$categories = array('limited' => '限时任务');
	foreach ($colors as $id => $label) $categories['c' . intval($id)] = $label;
	$categories['invalid'] = '未显示分类';
	return $categories;
}

function adminTaskCategoryKey($row, $colors)
{
	if (intval($row['flags']) !== 0) return 'limited';
	$color = intval($row['color']);
	return isset($colors[$color]) ? 'c' . $color : 'invalid';
}

function adminTaskUrl($view, $q, $extra)
{
	$params = array('view' => $view);
	if ($q !== '') $params['q'] = $q;
	foreach ($extra as $key => $value) $params[$key] = $value;
	return 'tasks.php?' . http_build_query($params);
}

function adminTaskParseFromNpc($value)
{
	$value = trim((string)$value);
	$result = array('first' => 0, 'suffix' => 0, 'has_suffix' => false);
	if ($value === '' || $value === '0') return $result;
	$parts = explode('|', $value, 2);
	if (isset($parts[0]) && preg_match('/^[0-9]+$/', trim($parts[0]))) $result['first'] = intval($parts[0]);
	if (isset($parts[1]) && preg_match('/^[0-9]+$/', trim($parts[1])))
	{
		$result['suffix'] = intval($parts[1]);
		$result['has_suffix'] = true;
	}
	return $result;
}

function adminTaskSplitTokens($value)
{
	$value = trim((string)$value);
	if ($value === '' || $value === '0') return array();
	$tokens = explode(',', $value);
	$result = array();
	foreach ($tokens as $token)
	{
		$token = trim($token);
		if ($token !== '' && $token !== '0') $result[] = $token;
	}
	return $result;
}

function adminTaskTokensFromText($value)
{
	$value = str_replace(array("\r\n", "\r"), "\n", (string)$value);
	$value = str_replace("\n", ',', $value);
	return adminTaskSplitTokens($value);
}

function adminTaskParseNeed($value)
{
	$result = array('items' => array(), 'kills' => array(), 'monself' => '', 'raw' => array());
	foreach (adminTaskSplitTokens($value) as $token)
	{
		if (preg_match('/^see:[0-9]+$/', $token)) continue;
		if (preg_match('/^giveitem:([0-9|]+):([0-9]+)$/', $token, $m))
		{
			$result['items'][] = $m[1] . ':' . $m[2];
			continue;
		}
		if (preg_match('/^killmon:([0-9|]+):([0-9]+)$/', $token, $m))
		{
			$result['kills'][] = $m[1] . ':' . $m[2];
			continue;
		}
		if (preg_match('/^monself:([0-9|]+)$/', $token, $m))
		{
			$result['monself'] = $result['monself'] === '' ? $m[1] : $result['monself'] . '|' . $m[1];
			continue;
		}
		$result['raw'][] = $token;
	}
	return $result;
}

function adminTaskParseLimit($value)
{
	$result = array('level_min' => '', 'level_max' => '', 'comself' => '', 'cishu_times' => '', 'cishu_days' => '', 'raw' => array());
	foreach (adminTaskSplitTokens($value) as $token)
	{
		if (preg_match('/^lv:([0-9]+)\|([0-9]*)$/', $token, $m))
		{
			$result['level_min'] = $m[1];
			$result['level_max'] = $m[2];
			continue;
		}
		if (preg_match('/^comself:([0-9|]+)$/', $token, $m))
		{
			$result['comself'] = $result['comself'] === '' ? $m[1] : $result['comself'] . '|' . $m[1];
			continue;
		}
		if (preg_match('/^cishu:([0-9]+):([0-9]+)$/', $token, $m))
		{
			$result['cishu_times'] = $m[1];
			$result['cishu_days'] = $m[2];
			continue;
		}
		$result['raw'][] = $token;
	}
	return $result;
}

function adminTaskParseResult($value)
{
	$result = array('exp' => '', 'props' => array(), 'raw' => array());
	foreach (adminTaskSplitTokens($value) as $token)
	{
		if (preg_match('/^exp:([0-9]+)$/', $token, $m))
		{
			$result['exp'] = $m[1];
			continue;
		}
		if (preg_match('/^props:([0-9]+):([0-9]+)$/', $token, $m))
		{
			$result['props'][] = $m[1] . ':' . $m[2];
			continue;
		}
		$result['raw'][] = $token;
	}
	return $result;
}

function adminTaskParseIdCountLines($text, $allowPipeIds)
{
	$text = str_replace(array("\r\n", "\r"), "\n", (string)$text);
	$lines = explode("\n", $text);
	$result = array();
	foreach ($lines as $line)
	{
		$line = trim($line);
		if ($line === '') continue;
		if (!preg_match('/^([0-9|]+):([0-9]+)$/', $line, $m)) return false;
		if (!$allowPipeIds && strpos($m[1], '|') !== false) return false;
		$ids = explode('|', $m[1]);
		$cleanIds = array();
		foreach ($ids as $id)
		{
			$id = intval($id);
			if ($id < 1) return false;
			$cleanIds[] = $id;
		}
		$count = intval($m[2]);
		if ($count < 1) return false;
		$result[] = implode('|', $cleanIds) . ':' . $count;
	}
	return $result;
}

function adminTaskNormalizeIdPipe($value)
{
	$value = trim((string)$value);
	if ($value === '') return '';
	if (!preg_match('/^[0-9|]+$/', $value)) return false;
	$ids = explode('|', $value);
	$clean = array();
	foreach ($ids as $id)
	{
		$id = intval($id);
		if ($id < 1) return false;
		$clean[$id] = $id;
	}
	return implode('|', array_values($clean));
}

function adminTaskBuildNeed($oknpc, $itemsText, $killsText, $monselfText, $rawText)
{
	$tokens = array('see:' . intval($oknpc));
	$items = adminTaskParseIdCountLines($itemsText, true);
	if ($items === false) return false;
	foreach ($items as $line) $tokens[] = 'giveitem:' . $line;
	$kills = adminTaskParseIdCountLines($killsText, true);
	if ($kills === false) return false;
	foreach ($kills as $line) $tokens[] = 'killmon:' . $line;
	$monself = adminTaskNormalizeIdPipe($monselfText);
	if ($monself === false) return false;
	if ($monself !== '') $tokens[] = 'monself:' . $monself;
	foreach (adminTaskTokensFromText($rawText) as $token)
	{
		if (preg_match('/^see:/', $token)) continue;
		$tokens[] = $token;
	}
	return implode(',', $tokens);
}

function adminTaskBuildLimit($minLevel, $maxLevel, $comselfText, $rawText, $completionMode, $cishuTimes, $cishuDays)
{
	$tokens = array();
	$minLevel = trim((string)$minLevel);
	$maxLevel = trim((string)$maxLevel);
	$completionMode = trim((string)$completionMode);
	if ($minLevel !== '' || $maxLevel !== '')
	{
		if ($minLevel === '') $minLevel = '1';
		if ($maxLevel === '') $maxLevel = '0';
		if (!preg_match('/^[0-9]+$/', $minLevel) || !preg_match('/^[0-9]+$/', $maxLevel)) return false;
		$tokens[] = 'lv:' . intval($minLevel) . '|' . intval($maxLevel);
	}
	$comself = adminTaskNormalizeIdPipe($comselfText);
	if ($comself === false) return false;
	if ($comself !== '') $tokens[] = 'comself:' . $comself;
	if ($completionMode === 'limited')
	{
		$cishuTimes = trim((string)$cishuTimes);
		$cishuDays = trim((string)$cishuDays);
		if (!preg_match('/^[0-9]+$/', $cishuTimes) || !preg_match('/^[0-9]+$/', $cishuDays) ||
			intval($cishuTimes) < 1 || intval($cishuDays) < 1) return false;
		$tokens[] = 'cishu:' . intval($cishuTimes) . ':' . intval($cishuDays);
	}
	foreach (adminTaskTokensFromText($rawText) as $token)
	{
		if (preg_match('/^cishu:/', $token)) continue;
		$tokens[] = $token;
	}
	return count($tokens) > 0 ? implode(',', $tokens) : '0';
}

function adminTaskBuildResult($exp, $propsText, $rawText)
{
	$tokens = array();
	$exp = trim((string)$exp);
	if ($exp !== '')
	{
		if (!preg_match('/^[0-9]+$/', $exp)) return false;
		if (intval($exp) > 0) $tokens[] = 'exp:' . intval($exp);
	}
	$props = adminTaskParseIdCountLines($propsText, false);
	if ($props === false) return false;
	foreach ($props as $line) $tokens[] = 'props:' . $line;
	foreach (adminTaskTokensFromText($rawText) as $token) $tokens[] = $token;
	return count($tokens) > 0 ? implode(',', $tokens) : '0';
}

function adminTaskRwlParts($cid)
{
	if (preg_match('/^rwl:([0-9]+)\|([0-9]*)$/', trim((string)$cid), $m))
	{
		return array('current' => intval($m[1]), 'next' => intval($m[2]));
	}
	return false;
}

function adminTaskMakeRwl($currentId, $nextId)
{
	return 'rwl:' . intval($currentId) . '|' . intval($nextId);
}

function adminTaskSuffixByXulie($tasks, $xulie, $excludeId)
{
	$xulie = intval($xulie);
	if ($xulie < 1) return 0;
	foreach ($tasks as $row)
	{
		if (intval($row['id']) === intval($excludeId) || intval($row['xulie']) !== $xulie) continue;
		$parsed = adminTaskParseFromNpc($row['fromnpc']);
		if ($parsed['has_suffix'] && $parsed['suffix'] > 0) return $parsed['suffix'];
	}
	return 0;
}

function adminTaskNextSuffix($tasks, $color, $excludeId)
{
	$used = array();
	foreach ($tasks as $row)
	{
		if (intval($row['id']) === intval($excludeId) || intval($row['color']) !== intval($color)) continue;
		$parsed = adminTaskParseFromNpc($row['fromnpc']);
		if ($parsed['has_suffix'] && $parsed['suffix'] > 0) $used[$parsed['suffix']] = true;
	}
	for ($i = 1; $i < 1000000; $i++) if (!isset($used[$i])) return $i;
	return false;
}

function adminTaskNextXulie($tasks)
{
	$max = 0;
	foreach ($tasks as $row) if (intval($row['xulie']) > $max) $max = intval($row['xulie']);
	return $max + 1;
}

function adminTaskPreviousId($tasks, $taskId)
{
	$taskId = intval($taskId);
	if ($taskId < 1) return 0;
	foreach ($tasks as $row)
	{
		$rowId = intval($row['id']);
		if ($rowId === $taskId) continue;
		$parts = adminTaskRwlParts($row['cid']);
		if ($parts !== false && intval($parts['next']) === $taskId) return $rowId;
	}
	return 0;
}

function adminTaskNextFlag($tasks, $timeRows)
{
	$max = 0;
	foreach ($tasks as $row) if (intval($row['flags']) > $max) $max = intval($row['flags']);
	if (is_array($timeRows))
	{
		foreach ($timeRows as $row)
		{
			if ($row['titles'] === 'task' && preg_match('/^[0-9]+$/', (string)$row['days']) && intval($row['days']) > $max) $max = intval($row['days']);
		}
	}
	return $max + 1;
}

function adminTaskDateToCompact($value)
{
	$value = trim((string)$value);
	if ($value === '') return '';
	if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})T([0-9]{2}):([0-9]{2})$/', $value, $m)) return false;
	if (!checkdate(intval($m[2]), intval($m[3]), intval($m[1])) || intval($m[4]) > 23 || intval($m[5]) > 59) return false;
	return $m[1] . $m[2] . $m[3] . $m[4] . $m[5] . '00';
}

function adminTaskDateInput($value)
{
	$value = trim((string)$value);
	if (!preg_match('/^([0-9]{4})([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})$/', $value, $m)) return '';
	return $m[1] . '-' . $m[2] . '-' . $m[3] . 'T' . $m[4] . ':' . $m[5];
}

function adminTaskSaveSchedule($db, $flag, $start, $end)
{
	$flag = intval($flag);
	$startSql = $db->escape($start);
	$endSql = $db->escape($end);
	$rows = $db->getRecords("SELECT Id FROM timeconfig WHERE titles='task' AND days='{$flag}' ORDER BY Id FOR UPDATE");
	if (is_array($rows) && count($rows) > 0)
	{
		foreach ($rows as $row)
		{
			if (!$db->query("UPDATE timeconfig SET starttime='{$startSql}',endtime='{$endSql}' WHERE Id=" . intval($row['Id']))) return false;
		}
		return true;
	}
	return $db->query("INSERT INTO timeconfig(titles,days,starttime,endtime) VALUES('task','{$flag}','{$startSql}','{$endSql}')") ? true : false;
}

function adminTaskFail($db, $message, $returnUrl, $rollback)
{
	if ($rollback) $db->query('ROLLBACK');
	adminSetFlash('error', $message);
	adminRedirect($returnUrl);
}

function adminTaskJoined($items)
{
	return is_array($items) ? implode("\n", $items) : '';
}

function adminTaskNames($ids, $map)
{
	$result = array();
	foreach (explode('|', (string)$ids) as $id)
	{
		$id = intval($id);
		if ($id < 1) continue;
		$result[] = isset($map[$id]) ? ('#' . $id . ' ' . $map[$id]['name']) : ('#' . $id . ' 不存在');
	}
	return implode(' / ', $result);
}

$colors = adminTaskColorLabels();
$categories = adminTaskCategories($colors);
$view = isset($_GET['view']) ? $_GET['view'] : 'limited';
if (!isset($categories[$view])) $view = 'limited';
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$returnUrl = adminTaskUrl($view, $q, array());

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST')
{
	$action = isset($_POST['action']) ? $_POST['action'] : '';
	$postView = isset($_POST['view']) ? $_POST['view'] : $view;
	if (!isset($categories[$postView])) $postView = 'limited';
	$postQ = isset($_POST['q']) ? trim((string)$_POST['q']) : '';
	$returnUrl = adminTaskUrl($postView, $postQ, array());

	if ($action === 'save_task')
	{
		$taskId = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
		$isNew = $taskId < 1;
		$adminDb->query('START TRANSACTION');
		$lockedTasks = $adminDb->getRecords('SELECT * FROM task ORDER BY id FOR UPDATE');
		if (!is_array($lockedTasks)) adminTaskFail($adminDb, '任务表读取失败：' . $adminDb->getError(), $returnUrl, true);
		$taskMap = array();
		foreach ($lockedTasks as $row) $taskMap[intval($row['id'])] = $row;
		$existing = $isNew ? false : (isset($taskMap[$taskId]) ? $taskMap[$taskId] : false);
		if (!$isNew && !is_array($existing)) adminTaskFail($adminDb, '没有找到要修改的任务。', $returnUrl, true);
		if ($isNew)
		{
			$taskId = adminNextFreeNumericId($lockedTasks, 'id');
			if ($taskId === false) adminTaskFail($adminDb, '无法生成新的任务 id。', $returnUrl, true);
			if (!$adminDb->query("INSERT INTO task(id,title,fromnpc,frommsg,okmsg,oknpc,okneed,result,cid,limitlv,hide,xulie,flags,color) VALUES({$taskId},'','0','','',0,'see:8','0','self','0',1,0,0,1)")) adminTaskFail($adminDb, '新增任务失败：' . $adminDb->getError(), $returnUrl, true);
			$existing = array('id' => $taskId, 'title' => '', 'fromnpc' => '0', 'frommsg' => '', 'okmsg' => '', 'oknpc' => 0, 'okneed' => 'see:8', 'result' => '0', 'cid' => 'self', 'limitlv' => '0', 'hide' => 1, 'xulie' => 0, 'flags' => 0, 'color' => 1);
			$lockedTasks[] = $existing;
			$taskMap[$taskId] = $existing;
		}

		$title = trim((string)(isset($_POST['title']) ? $_POST['title'] : ''));
		if ($title === '') adminTaskFail($adminDb, '任务标题不能为空。', $returnUrl, true);
		$frommsg = (string)(isset($_POST['frommsg']) ? $_POST['frommsg'] : '');
		$okmsg = (string)(isset($_POST['okmsg']) ? $_POST['okmsg'] : '');
		$color = isset($_POST['color']) ? intval($_POST['color']) : 0;
		$hide = isset($_POST['hide']) ? intval($_POST['hide']) : 1;
		if ($hide < 0 || $hide > 2) $hide = 1;

		$oknpc = $isNew ? 8 : intval($existing['oknpc']);
		if ($oknpc < 1) $oknpc = 8;
		$completionMode = isset($_POST['completion_mode']) ? trim((string)$_POST['completion_mode']) : 'unlimited';
		if ($completionMode !== 'once' && $completionMode !== 'limited') $completionMode = 'unlimited';
		$isSequence = isset($_POST['is_sequence']) ? true : false;
		if ($completionMode === 'once' && $isSequence) adminTaskFail($adminDb, '序列任务不能同时设置为 cid=0 的一次性任务。', $returnUrl, true);

		$need = adminTaskBuildNeed($oknpc, isset($_POST['need_items']) ? $_POST['need_items'] : '', isset($_POST['need_kills']) ? $_POST['need_kills'] : '', isset($_POST['need_monself']) ? $_POST['need_monself'] : '', isset($_POST['need_raw']) ? $_POST['need_raw'] : '');
		if ($need === false) adminTaskFail($adminDb, '所需物品、击杀怪物或交付主战宠物格式不正确。', $returnUrl, true);
		$limitlv = adminTaskBuildLimit(isset($_POST['level_min']) ? $_POST['level_min'] : '', isset($_POST['level_max']) ? $_POST['level_max'] : '', isset($_POST['accept_comself']) ? $_POST['accept_comself'] : '', isset($_POST['limit_raw']) ? $_POST['limit_raw'] : '', $completionMode, isset($_POST['cishu_times']) ? $_POST['cishu_times'] : '', isset($_POST['cishu_days']) ? $_POST['cishu_days'] : '');
		if ($limitlv === false) adminTaskFail($adminDb, '接取等级、出战宠物要求或完成次数限制格式不正确。', $returnUrl, true);
		$result = adminTaskBuildResult(isset($_POST['reward_exp']) ? $_POST['reward_exp'] : '', isset($_POST['reward_props']) ? $_POST['reward_props'] : '', isset($_POST['reward_raw']) ? $_POST['reward_raw'] : '');
		if ($result === false) adminTaskFail($adminDb, '奖励经验或奖励物品格式不正确。', $returnUrl, true);

		$oldRwl = adminTaskRwlParts($existing['cid']);
		$sequenceId = 0;
		$cid = 'self';
		$previousId = isset($_POST['previous_task_id']) ? intval($_POST['previous_task_id']) : 0;
		$postedXulie = isset($_POST['sequence_id']) ? intval($_POST['sequence_id']) : 0;

		if ($oldRwl !== false)
		{
			foreach ($lockedTasks as $row)
			{
				$rowId = intval($row['id']);
				if ($rowId === $taskId || $rowId === $previousId) continue;
				$parts = adminTaskRwlParts($row['cid']);
				if ($parts !== false && intval($parts['next']) === $taskId)
				{
					$newCid = adminTaskMakeRwl($parts['current'], $oldRwl['next']);
					if (!$adminDb->query("UPDATE task SET cid='" . $adminDb->escape($newCid) . "' WHERE id={$rowId}")) adminTaskFail($adminDb, '调整原序列前序任务失败：' . $adminDb->getError(), $returnUrl, true);
				}
			}
		}

		if ($isSequence)
		{
			if ($previousId > 0 && isset($taskMap[$previousId]) && $previousId !== $taskId)
			{
				$previous = $taskMap[$previousId];
				if ($postedXulie > 0 && intval($previous['xulie']) !== $postedXulie) adminTaskFail($adminDb, '前序任务必须属于选择的现有序列号。', $returnUrl, true);
				$sequenceId = intval($previous['xulie']);
				if ($sequenceId < 1) $sequenceId = $postedXulie > 0 ? $postedXulie : adminTaskNextXulie($lockedTasks);
				$prevParts = adminTaskRwlParts($previous['cid']);
				$nextId = $prevParts !== false ? intval($prevParts['next']) : 0;
				if ($nextId === $taskId) $nextId = $oldRwl !== false ? intval($oldRwl['next']) : 0;
				$prevCid = adminTaskMakeRwl($previousId, $taskId);
				if (!$adminDb->query("UPDATE task SET xulie={$sequenceId},cid='" . $adminDb->escape($prevCid) . "' WHERE id={$previousId}")) adminTaskFail($adminDb, '保存前序任务失败：' . $adminDb->getError(), $returnUrl, true);
				$cid = adminTaskMakeRwl($taskId, $nextId);
			}
			else
			{
				$sequenceId = $postedXulie > 0 ? $postedXulie : intval($existing['xulie']);
				if ($sequenceId < 1) $sequenceId = adminTaskNextXulie($lockedTasks);
				$nextId = $oldRwl !== false ? intval($oldRwl['next']) : 0;
				$cid = adminTaskMakeRwl($taskId, $nextId);
			}
			if ($sequenceId > 255) adminTaskFail($adminDb, '序列号超过 task.xulie 可保存范围。', $returnUrl, true);
		}
		else
		{
			$sequenceId = 0;
			$cid = $completionMode === 'once' ? '0' : (($oldRwl === false && trim((string)$existing['cid']) !== '' && trim((string)$existing['cid']) !== '0') ? $existing['cid'] : 'self');
		}

		$fromParsed = adminTaskParseFromNpc($existing['fromnpc']);
		$fromFirst = $isNew ? 8 : $fromParsed['first'];
		if ($fromFirst < 1) $fromFirst = 8;
		$fromSuffix = $fromParsed['has_suffix'] ? intval($fromParsed['suffix']) : 0;
		if ($fromSuffix < 1)
		{
			if ($isSequence && $sequenceId > 0) $fromSuffix = adminTaskSuffixByXulie($lockedTasks, $sequenceId, $taskId);
			if ($fromSuffix < 1) $fromSuffix = adminTaskNextSuffix($lockedTasks, $color, $taskId);
		}
		if ($fromSuffix === false || $fromSuffix < 1) adminTaskFail($adminDb, '无法生成 fromnpc 后半段。', $returnUrl, true);
		$fromnpc = $fromFirst . '|' . $fromSuffix;

		$flags = 0;
		$scheduleChanged = false;
		if (isset($_POST['is_limited']))
		{
			$flags = intval($existing['flags']);
			if ($flags < 1)
			{
				$timeRows = $adminDb->getRecords("SELECT * FROM timeconfig WHERE titles='task' ORDER BY Id FOR UPDATE");
				$flags = adminTaskNextFlag($lockedTasks, $timeRows);
			}
			if ($flags > 255) adminTaskFail($adminDb, '限时任务 flags 超过 task.flags 可保存范围。', $returnUrl, true);
			$start = adminTaskDateToCompact(isset($_POST['limit_start']) ? $_POST['limit_start'] : '');
			$end = adminTaskDateToCompact(isset($_POST['limit_end']) ? $_POST['limit_end'] : '');
			if ($start === false || $end === false || $start === '' || $end === '' || $start > $end) adminTaskFail($adminDb, '限时任务必须填写有效的开始和结束时间。', $returnUrl, true);
			if (!adminTaskSaveSchedule($adminDb, $flags, $start, $end)) adminTaskFail($adminDb, '保存限时任务时间失败：' . $adminDb->getError(), $returnUrl, true);
			$scheduleChanged = true;
		}

		if (strlen($fromnpc) > 10) adminTaskFail($adminDb, 'fromnpc 超过 10 字节，无法保存。', $returnUrl, true);
		if (strlen($need) > 255) adminTaskFail($adminDb, 'okneed 超过 255 字节，无法保存。', $returnUrl, true);
		if (strlen($result) > 255) adminTaskFail($adminDb, 'result 超过 255 字节，无法保存。', $returnUrl, true);
		if (strlen($cid) > 50) adminTaskFail($adminDb, 'cid 超过 50 字节，无法保存。', $returnUrl, true);
		if (strlen($limitlv) > 255) adminTaskFail($adminDb, 'limitlv 超过 255 字节，无法保存。', $returnUrl, true);

		$titleSql = $adminDb->escape($title);
		$frommsgSql = $adminDb->escape($frommsg);
		$okmsgSql = $adminDb->escape($okmsg);
		$fromnpcSql = $adminDb->escape($fromnpc);
		$needSql = $adminDb->escape($need);
		$resultSql = $adminDb->escape($result);
		$cidSql = $adminDb->escape($cid);
		$limitSql = $adminDb->escape($limitlv);
		$sql = "UPDATE task SET title='{$titleSql}',fromnpc='{$fromnpcSql}',frommsg='{$frommsgSql}',okmsg='{$okmsgSql}',oknpc={$oknpc},okneed='{$needSql}',result='{$resultSql}',cid='{$cidSql}',limitlv='{$limitSql}',hide={$hide},xulie={$sequenceId},flags={$flags},color={$color} WHERE id={$taskId}";
		if (!$adminDb->query($sql) || !$adminDb->query('COMMIT')) adminTaskFail($adminDb, '保存任务失败：' . $adminDb->getError(), $returnUrl, true);

		$cacheOk = adminRefreshTaskCache($adminDb, $adminMem);
		if ($scheduleChanged) $cacheOk = adminRefreshTimeConfigCache($adminDb, $adminMem) && $cacheOk;
		adminSetFlash($cacheOk ? 'success' : 'warning', '任务 #' . $taskId . ' 已保存' . ($cacheOk ? '。' : '，但任务或时间缓存刷新失败。'));
		adminRedirect(adminTaskUrl($postView, $postQ, array('edit' => $taskId)));
	}
}

$taskRows = $adminDb->getRecords('SELECT * FROM task ORDER BY id');
if (!is_array($taskRows)) $taskRows = array();
$taskMap = array();
$counts = array();
foreach ($categories as $key => $label) $counts[$key] = 0;
foreach ($taskRows as $row)
{
	$id = intval($row['id']);
	$taskMap[$id] = $row;
	$key = adminTaskCategoryKey($row, $colors);
	if (!isset($counts[$key])) $counts[$key] = 0;
	$counts[$key]++;
}

$displayRows = array();
foreach ($taskRows as $row)
{
	if (adminTaskCategoryKey($row, $colors) !== $view) continue;
	if ($q !== '' && strpos((string)$row['id'], $q) === false && stripos((string)$row['title'], $q) === false) continue;
	$displayRows[] = $row;
}

$timeRows = $adminDb->getRecords("SELECT * FROM timeconfig WHERE titles='task' ORDER BY CAST(days AS UNSIGNED),Id");
if (!is_array($timeRows)) $timeRows = array();
$timeByFlag = array();
foreach ($timeRows as $row)
{
	$flag = intval($row['days']);
	if ($flag > 0 && !isset($timeByFlag[$flag])) $timeByFlag[$flag] = $row;
}

$propsMap = adminPropsMap($adminDb);
$petMap = adminPetMap($adminDb);
$gpcRows = $adminDb->getRecords('SELECT id,name FROM gpc ORDER BY id');
$gpcMap = array();
if (is_array($gpcRows)) foreach ($gpcRows as $row) $gpcMap[intval($row['id'])] = $row;

$editId = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$showNew = isset($_GET['new']) ? true : false;
$editTask = false;
if ($editId > 0 && isset($taskMap[$editId])) $editTask = $taskMap[$editId];
if ($showNew)
{
	$editTask = array('id' => 0, 'title' => '', 'fromnpc' => '8', 'frommsg' => '', 'okmsg' => '', 'oknpc' => 8, 'okneed' => 'see:8', 'result' => '0', 'cid' => 'self', 'limitlv' => 'lv:1|0', 'hide' => 1, 'xulie' => 0, 'flags' => 0, 'color' => 1);
}

adminPageStart('任务管理', 'tasks');
?>
	<section class="band">
		<div class="section-head">
			<div><h2>任务分类</h2></div>
			<a class="btn primary" href="<?php echo adminH(adminTaskUrl($view, $q, array('new' => 1))); ?>">新增任务</a>
		</div>
		<div class="segmented task-tabs">
			<?php foreach ($categories as $key => $label) { ?><a<?php echo $view === $key ? ' class="active"' : ''; ?> href="<?php echo adminH(adminTaskUrl($key, '', array())); ?>"><?php echo adminH($label); ?><span><?php echo intval(isset($counts[$key]) ? $counts[$key] : 0); ?></span></a><?php } ?>
		</div>
		<form class="filters task-search" method="get">
			<input type="hidden" name="view" value="<?php echo adminH($view); ?>" />
			<input class="input" type="search" name="q" value="<?php echo adminH($q); ?>" placeholder="任务 id / 标题" />
			<button class="btn secondary" type="submit">查询</button>
			<?php if ($q !== '') { ?><a class="btn secondary" href="<?php echo adminH(adminTaskUrl($view, '', array())); ?>">清空</a><?php } ?>
		</form>
	</section>
	<section class="band">
		<div class="section-head"><div><h2><?php echo adminH($categories[$view]); ?></h2><div class="subtle">当前显示 <?php echo count($displayRows); ?> 条</div></div></div>
		<?php if (count($displayRows) === 0) { ?>
			<div class="empty">没有符合条件的任务。</div>
		<?php } else { ?>
			<div class="table-wrap">
				<table class="task-table">
					<thead><tr><th>ID</th><th>标题</th><th>分类</th><th>显示</th><th>NPC</th><th>完成条件</th><th>奖励</th><th>序列</th><th>限时</th><th>操作</th></tr></thead>
					<tbody>
					<?php foreach ($displayRows as $row) {
						$need = adminTaskParseNeed($row['okneed']);
						$result = adminTaskParseResult($row['result']);
						$from = adminTaskParseFromNpc($row['fromnpc']);
						$flag = intval($row['flags']);
					?>
						<tr>
							<td class="code"><?php echo intval($row['id']); ?></td>
							<td><strong><?php echo adminH($row['title']); ?></strong><div class="subtle">color=<?php echo intval($row['color']); ?> / flags=<?php echo $flag; ?></div></td>
							<td><?php echo isset($colors[intval($row['color'])]) ? adminH($colors[intval($row['color'])]) : '<span class="badge warning">未显示</span>'; ?></td>
							<td><?php echo intval($row['hide']) === 1 ? '<span class="badge success">显示</span>' : (intval($row['hide']) === 2 ? '<span class="badge muted">隐藏</span>' : '<span class="badge warning">' . intval($row['hide']) . '</span>'); ?></td>
							<td><div class="code">from=<?php echo adminH($row['fromnpc']); ?></div><div class="code">ok=<?php echo intval($row['oknpc']); ?> / see=<?php echo preg_match('/^see:([0-9]+)/', $row['okneed'], $m) ? intval($m[1]) : '缺失'; ?></div><?php if ($from['has_suffix']) { ?><div class="subtle">排序位 <?php echo intval($from['suffix']); ?></div><?php } ?></td>
							<td><div class="subtle">物品 <?php echo count($need['items']); ?> / 杀怪 <?php echo count($need['kills']); ?></div><?php if ($need['monself'] !== '') { ?><div class="subtle">交付主宠 <?php echo adminH(adminTaskNames($need['monself'], $petMap)); ?></div><?php } ?></td>
							<td><div class="subtle">经验 <?php echo $result['exp'] === '' ? 0 : intval($result['exp']); ?> / 道具 <?php echo count($result['props']); ?></div></td>
							<td><div class="code">xulie=<?php echo intval($row['xulie']); ?></div><div class="code"><?php echo adminH($row['cid']); ?></div></td>
							<td><?php if ($flag > 0) { $schedule = isset($timeByFlag[$flag]) ? $timeByFlag[$flag] : false; ?><span class="badge warning">限时</span><div class="code"><?php echo $schedule ? adminH($schedule['starttime'] . ' - ' . $schedule['endtime']) : '未配置时间'; ?></div><?php } else { ?><span class="badge muted">普通</span><?php } ?></td>
							<td><a class="btn secondary" href="<?php echo adminH(adminTaskUrl($view, $q, array('edit' => intval($row['id'])))); ?>">编辑</a></td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
		<?php } ?>
	</section>
<?php
if (is_array($editTask))
{
	$need = adminTaskParseNeed($editTask['okneed']);
	$limit = adminTaskParseLimit($editTask['limitlv']);
	$result = adminTaskParseResult($editTask['result']);
	$flag = intval($editTask['flags']);
	$schedule = $flag > 0 && isset($timeByFlag[$flag]) ? $timeByFlag[$flag] : false;
	$isSequence = intval($editTask['xulie']) > 0 || adminTaskRwlParts($editTask['cid']) !== false;
	$editCid = trim((string)$editTask['cid']);
	if ($limit['cishu_times'] !== '' || $limit['cishu_days'] !== '') $completionMode = 'limited';
	else if (!$isSequence && ($editCid === '' || $editCid === '0')) $completionMode = 'once';
	else $completionMode = 'unlimited';
	$selectedPreviousId = adminTaskPreviousId($taskRows, intval($editTask['id']));
?>
	<div class="task-modal">
		<div class="task-dialog">
			<div class="task-dialog-head">
				<h2><?php echo intval($editTask['id']) > 0 ? '编辑任务 #' . intval($editTask['id']) : '新增任务'; ?></h2>
				<a class="btn secondary" href="<?php echo adminH($returnUrl); ?>">关闭</a>
			</div>
			<form method="post" class="task-form">
				<input type="hidden" name="action" value="save_task" />
				<input type="hidden" name="task_id" value="<?php echo intval($editTask['id']); ?>" />
				<input type="hidden" name="view" value="<?php echo adminH($view); ?>" />
				<input type="hidden" name="q" value="<?php echo adminH($q); ?>" />
				<div class="task-grid">
					<div class="field task-wide"><label>任务标题</label><input class="input" name="title" value="<?php echo adminH($editTask['title']); ?>" required="required" /></div>
					<div class="field"><label>color 分类</label><select class="select" name="color"><option value="0"<?php echo intval($editTask['color']) === 0 ? ' selected="selected"' : ''; ?>>0 - 未显示</option><?php foreach ($colors as $id => $label) { ?><option value="<?php echo intval($id); ?>"<?php echo intval($editTask['color']) === intval($id) ? ' selected="selected"' : ''; ?>><?php echo intval($id); ?> - <?php echo adminH($label); ?></option><?php } ?></select></div>
					<div class="field"><label>hide</label><select class="select" name="hide"><option value="1"<?php echo intval($editTask['hide']) === 1 ? ' selected="selected"' : ''; ?>>1 - 显示</option><option value="2"<?php echo intval($editTask['hide']) === 2 ? ' selected="selected"' : ''; ?>>2 - 隐藏</option><option value="0"<?php echo intval($editTask['hide']) === 0 ? ' selected="selected"' : ''; ?>>0 - 其他</option></select></div>
					<div class="field task-wide"><label>接取信息 frommsg</label><textarea class="textarea" name="frommsg" rows="6"><?php echo adminH($editTask['frommsg']); ?></textarea></div>
					<div class="field task-wide"><label>完成信息 okmsg</label><textarea class="textarea" name="okmsg" rows="4"><?php echo adminH($editTask['okmsg']); ?></textarea></div>
					<div class="field"><label>所需物品 pid:数量</label><textarea class="textarea" name="need_items" rows="6"><?php echo adminH(adminTaskJoined($need['items'])); ?></textarea></div>
					<div class="field"><label>需击杀怪物 gpc_id:数量</label><textarea class="textarea" name="need_kills" rows="6"><?php echo adminH(adminTaskJoined($need['kills'])); ?></textarea></div>
					<div class="field"><label>交付主战宠物 bb_id</label><input class="input" name="need_monself" value="<?php echo adminH($need['monself']); ?>" /></div>
					<div class="field"><label>okneed 其他片段</label><textarea class="textarea" name="need_raw" rows="4"><?php echo adminH(adminTaskJoined($need['raw'])); ?></textarea></div>
					<div class="field"><label>接取最低等级</label><input class="input" name="level_min" value="<?php echo adminH($limit['level_min']); ?>" /></div>
					<div class="field"><label>接取最高等级</label><input class="input" name="level_max" value="<?php echo adminH($limit['level_max']); ?>" /></div>
					<div class="field"><label>接取出战宠物 bb_id</label><input class="input" name="accept_comself" value="<?php echo adminH($limit['comself']); ?>" /></div>
					<div class="field"><label>完成方式</label><select class="select" name="completion_mode"><option value="unlimited"<?php echo $completionMode === 'unlimited' ? ' selected="selected"' : ''; ?>>可重复/其他条件控制</option><option value="once"<?php echo $completionMode === 'once' ? ' selected="selected"' : ''; ?>>一次性任务(cid=0)</option><option value="limited"<?php echo $completionMode === 'limited' ? ' selected="selected"' : ''; ?>>按天数限制(cishu)</option></select></div>
					<div class="field"><label>可完成次数</label><input class="input" name="cishu_times" value="<?php echo adminH($limit['cishu_times']); ?>" /></div>
					<div class="field"><label>统计天数</label><input class="input" name="cishu_days" value="<?php echo adminH($limit['cishu_days']); ?>" /></div>
					<div class="field"><label>limitlv 其他片段</label><textarea class="textarea" name="limit_raw" rows="4"><?php echo adminH(adminTaskJoined($limit['raw'])); ?></textarea></div>
					<div class="field"><label>奖励经验</label><input class="input" name="reward_exp" value="<?php echo adminH($result['exp']); ?>" /></div>
					<div class="field"><label>奖励物品 pid:数量</label><textarea class="textarea" name="reward_props" rows="5"><?php echo adminH(adminTaskJoined($result['props'])); ?></textarea></div>
					<div class="field task-wide"><label>result 其他片段</label><textarea class="textarea" name="reward_raw" rows="4"><?php echo adminH(adminTaskJoined($result['raw'])); ?></textarea></div>
					<label class="task-check"><input type="checkbox" name="is_sequence" value="1"<?php echo $isSequence ? ' checked="checked"' : ''; ?> /> 序列任务</label>
					<div class="field"><label>现有序列号</label><select class="select" name="sequence_id" data-task-sequence-select="1"><option value="0">自动新序列</option><?php $seenXulie = array(); foreach ($taskRows as $row) { $x = intval($row['xulie']); if ($x < 1 || isset($seenXulie[$x])) continue; $seenXulie[$x] = true; ?><option value="<?php echo $x; ?>"<?php echo intval($editTask['xulie']) === $x ? ' selected="selected"' : ''; ?>><?php echo $x; ?></option><?php } ?></select></div>
					<div class="field task-wide"><label>前序任务</label><select class="select" name="previous_task_id" data-task-previous-select="1" data-task-initial-previous="<?php echo intval($selectedPreviousId); ?>"><option value="0" data-task-xulie="0">不选择</option><?php foreach ($taskRows as $row) { $rowId = intval($row['id']); if ($rowId === intval($editTask['id'])) continue; $rowXulie = intval($row['xulie']); ?><option value="<?php echo $rowId; ?>" data-task-xulie="<?php echo $rowXulie; ?>"<?php echo $selectedPreviousId === $rowId ? ' selected="selected"' : ''; ?>><?php echo $rowId; ?> - <?php echo adminH($row['title']); ?> (xulie=<?php echo $rowXulie; ?>, cid=<?php echo adminH($row['cid']); ?>)</option><?php } ?></select></div>
					<label class="task-check"><input type="checkbox" name="is_limited" value="1"<?php echo $flag > 0 ? ' checked="checked"' : ''; ?> /> 限时任务<?php echo $flag > 0 ? ' flags=' . $flag : ''; ?></label>
					<div class="field"><label>限时开始</label><input class="input" type="datetime-local" name="limit_start" value="<?php echo $schedule ? adminH(adminTaskDateInput($schedule['starttime'])) : ''; ?>" /></div>
					<div class="field"><label>限时结束</label><input class="input" type="datetime-local" name="limit_end" value="<?php echo $schedule ? adminH(adminTaskDateInput($schedule['endtime'])) : ''; ?>" /></div>
				</div>
				<div class="task-dialog-actions">
					<div class="subtle">保存时会自动保证 okneed 以 see:oknpc 开头；新增任务 fromnpc/oknpc 使用 8；一周一次可设为按天数限制 1 次/7 天。</div>
					<button class="btn primary" type="submit">保存任务</button>
				</div>
			</form>
		</div>
	</div>
<?php } ?>
<?php adminPageEnd(); ?>
