<?php
session_start();
require_once __DIR__ . '/KamiSystem.php';
$config = require __DIR__ . '/config.php';
$kami = new KamiSystem();

$error = '';
$success = '';

// 处理登录
if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    // 管理员登录：用户名从配置读取 + 管理员密码
    if ($username === $config['admin_username'] && $password === $config['admin_password']) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['agent_logged_in'] = false;
    } else {
        // 代理登录
        $result = $kami->agentLogin($username, $password);
        if ($result['success']) {
            $_SESSION['agent_logged_in'] = true;
            $_SESSION['agent_username'] = $username;
            $_SESSION['admin_logged_in'] = false;
        } else {
            $error = $result['error'];
        }
    }
}

// 处理登出
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$isAdmin = $_SESSION['admin_logged_in'] ?? false;
$isAgent = $_SESSION['agent_logged_in'] ?? false;
$agentUsername = $_SESSION['agent_username'] ?? '';

// 代理状态检查：被禁用/拒绝后强制踢出去
if ($isAgent && $agentUsername) {
    $agentStatus = $kami->getAgentStatus($agentUsername);
    if ($agentStatus !== 'active') {
        $kickMsg = ($agentStatus === 'banned') ? '您的账号已被管理员禁用，请联系管理员' : '您的账号已被拒绝，请联系管理员';
        $_SESSION['kick_msg'] = $kickMsg;
        session_destroy();
        session_start();
        $_SESSION['kick_msg'] = $kickMsg;
        header('Location: index.php');
        exit;
    }
}

// 管理员审批代理
if (isset($_GET['approve_agent']) && $isAdmin) {
    $result = $kami->approveAgent($_GET['approve_agent']);
    if ($result['success']) $_SESSION['success_msg'] = '代理已通过审批';
}

// 管理员拒绝代理
if (isset($_GET['reject_agent']) && $isAdmin) {
    $result = $kami->rejectAgent($_GET['reject_agent']);
    if ($result['success']) $_SESSION['success_msg'] = '代理已被拒绝';
}

// 管理员创建代理
if (isset($_POST['create_agent']) && $isAdmin) {
    $username = trim($_POST['agent_username'] ?? '');
    $password = $_POST['agent_password'] ?? '';
    $remark = $_POST['agent_remark'] ?? '';
    $result = $kami->createAgent($username, $password, $remark);
    if ($result['success']) {
        $_SESSION['success_msg'] = "代理 {$username} 创建成功";
    } else {
        $error = $result['error'];
    }
}

// 管理员删除代理
if (isset($_GET['delete_agent']) && $isAdmin) {
    $result = $kami->deleteAgent($_GET['delete_agent']);
    if ($result['success']) $_SESSION['success_msg'] = '代理已删除';
}

// 管理员禁用/启用代理
if (isset($_GET['toggle_agent']) && $isAdmin) {
    $result = $kami->toggleAgentStatus($_GET['toggle_agent']);
    if ($result['success']) $_SESSION['success_msg'] = '代理状态已更新';
}

// 管理员审批卡密
if (isset($_GET['approve']) && $isAdmin) {
    $result = $kami->approveCard($_GET['approve']);
    if ($result['success']) $_SESSION['success_msg'] = '卡密审批通过';
}

// 管理员拒绝卡密
if (isset($_GET['reject']) && $isAdmin) {
    $result = $kami->rejectCard($_GET['reject']);
    if ($result['success']) $_SESSION['success_msg'] = '卡密已拒绝';
}

// 一键清空所有待审批卡密
if (isset($_GET['clear_pending']) && $isAdmin) {
    $result = $kami->clearPendingCards();
    if ($result['success']) $_SESSION['success_msg'] = "已清空 {$result['count']} 张待审批卡密";
    header('Location: ?tab=pending');
    exit;
}

// 清空指定代理的待审批卡密
if (isset($_GET['clear_agent_pending']) && $isAdmin) {
    $agent = $_GET['clear_agent_pending'];
    $result = $kami->clearAgentPendingCards($agent);
    if ($result['success']) $_SESSION['success_msg'] = "已清空代理 {$agent} 的 {$result['count']} 张待审批卡密";
    header('Location: ?tab=pending');
    exit;
}

// 代理生成卡密（待审批）
if (isset($_POST['agent_generate']) && $isAgent) {
    $type = $_POST['type'] ?? 'day';
    $count = intval($_POST['count'] ?? 1);
    $remark = $_POST['remark'] ?? '';
    $result = $kami->agentGenerateCard($agentUsername, $type, $count, $remark);
    if ($result['success']) {
        $_SESSION['success_msg'] = $result['message'];
        header('Location: ?tab=cards');
        exit;
    } else {
        $error = $result['error'];
    }
}

// 处理生成卡密
if (isset($_POST['generate']) && $isAdmin) {
    $type = $_POST['type'] ?? 'day';
    $count = intval($_POST['count'] ?? 1);
    $remark = $_POST['remark'] ?? '';
    $result = $kami->generate($type, $count, $remark);
    if ($result['success']) {
        $_SESSION['generated_cards'] = $result['cards'];
        $_SESSION['success_msg'] = "成功生成 {$count} 张卡密";
        header('Location: ?tab=cards');
        exit;
    } else {
        $error = $result['error'];
    }
}

// 处理生成解绑卡
if (isset($_POST['generate_unbind']) && $isAdmin) {
    $count = intval($_POST['count'] ?? 1);
    $remark = $_POST['remark'] ?? '';
    $result = $kami->generateUnbindCard($count, $remark);
    if ($result['success']) {
        $success = "成功生成 {$count} 张解绑卡";
        $_SESSION['generated_unbind_cards'] = $result['cards'];
    } else {
        $error = $result['error'];
    }
}

// 处理后台解绑
if (isset($_POST['admin_unbind']) && $isAdmin) {
    $card = $_POST['card'] ?? '';
    $result = $kami->forceUnbind($card);
    if ($result['success']) {
        $success = "卡密 {$card} 已强制解绑";
    } else {
        $error = $result['error'];
    }
}

// 处理删除
if (isset($_GET['delete']) && $isAdmin) {
    $kami->delete($_GET['delete']);
    header('Location: index.php');
    exit;
}
if (isset($_GET['delete_unbind']) && $isAdmin) {
    $kami->deleteUnbindCard($_GET['delete_unbind']);
    header('Location: index.php?tab=unbind');
    exit;
}

// 处理禁用/解禁
if (isset($_GET['ban']) && $isAdmin) {
    $kami->ban($_GET['ban']);
    header('Location: index.php');
    exit;
}
if (isset($_GET['unban']) && $isAdmin) {
    $kami->unban($_GET['unban']);
    header('Location: index.php');
    exit;
}
if (isset($_GET['ban_unbind']) && $isAdmin) {
    $kami->banUnbindCard($_GET['ban_unbind']);
    header('Location: index.php?tab=unbind');
    exit;
}

$stats = $kami->stats();
$page = intval($_GET['page'] ?? 1);
$tab = $_GET['tab'] ?? ($isAgent ? 'agent_cards' : 'cards');
$cardList = $kami->listAll($page, 30);
$unbindList = $kami->listUnbindCards($page, 30);
$generatedCards = $_SESSION['generated_cards'] ?? [];
$generatedUnbindCards = $_SESSION['generated_unbind_cards'] ?? [];
if (isset($_SESSION['success_msg'])) {
    $success = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
unset($_SESSION['generated_cards'], $_SESSION['generated_unbind_cards']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>卡密管理系统</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f0f2f5; color: #333; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .header a { color: white; text-decoration: none; opacity: 0.9; }
        .login-box { max-width: 400px; margin: 100px auto; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .login-box h2 { text-align: center; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .btn { display: inline-block; padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; text-decoration: none; }
        .btn:hover { background: #5568d3; }
        .btn-danger { background: #f56c6c; }
        .btn-danger:hover { background: #e45656; }
        .btn-success { background: #67c23a; }
        .btn-success:hover { background: #529b2e; }
        .btn-warning { background: #e6a23c; }
        .btn-warning:hover { background: #cf9236; }
        .btn-info { background: #909399; }
        .btn-info:hover { background: #73767a; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-card .number { font-size: 28px; font-weight: bold; color: #667eea; }
        .stat-card .label { color: #999; margin-top: 5px; font-size: 13px; }
        .panel { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .panel h3 { margin-bottom: 15px; }
        .generate-form { display: flex; gap: 15px; align-items: end; flex-wrap: wrap; }
        .generate-form .form-group { margin-bottom: 0; flex: 1; min-width: 120px; }
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab { padding: 10px 20px; background: white; border-radius: 5px; cursor: pointer; text-decoration: none; color: #333; border: 1px solid #ddd; }
        .tab.active { background: #667eea; color: white; border-color: #667eea; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #fafafa; font-weight: 600; }
        .status-active { color: #67c23a; }
        .status-used { color: #e6a23c; }
        .status-expired { color: #909399; }
        .status-banned { color: #f56c6c; }
        .card-text { font-family: monospace; background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
        .alert { padding: 10px 15px; border-radius: 5px; margin-bottom: 15px; }
        .alert-error { background: #fef0f0; color: #f56c6c; border: 1px solid #fde2e2; }
        .alert-success { background: #f0f9eb; color: #67c23a; border: 1px solid #e1f3d8; }
        .generated-cards { background: #f5f7fa; padding: 15px; border-radius: 5px; margin-top: 15px; max-height: 300px; overflow-y: auto; }
        .generated-cards .card-item { font-family: monospace; padding: 5px 0; border-bottom: 1px solid #e4e7ed; display: flex; justify-content: space-between; }
        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 20px; }
        .pagination a, .pagination span { padding: 5px 10px; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333; }
        .pagination .current { background: #667eea; color: white; border-color: #667eea; }
        .api-info { background: #f5f7fa; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px; line-height: 1.8; }
        .device-info { font-size: 11px; color: #999; }
        .unbind-badge { background: #e6a23c; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
    </style>
</head>
<body>
<div class="container">
    <?php if (!$isAdmin && !$isAgent): ?>
    <div class="login-box">
        <h2>黯れ·卡密管理系统</h2>
        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        <?php if (!empty($_SESSION['kick_msg'])): ?><div class="alert alert-error"><?= $_SESSION['kick_msg'] ?></div><?php unset($_SESSION['kick_msg']); ?><?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" name="login" class="btn" style="width:100%">登录</button>
        </form>
        <p style="text-align:center;color:#999;font-size:12px;margin-top:10px">黯れ卡密系统</p>
    </div>
    <?php else: ?>
    <div class="header">
        <h1>黯れ·卡密管理系统 <?= $isAgent ? '（代理：'.htmlspecialchars($agentUsername).'）' : '' ?></h1>
        <a href="?logout">退出登录</a>
    </div>
    
    <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
    
    <?php if ($isAdmin): ?>
    <div class="stats">
        <div class="stat-card"><div class="number"><?= $stats['total'] ?></div><div class="label">总卡密</div></div>
        <div class="stat-card"><div class="number"><?= $stats['unused'] ?></div><div class="label">未使用</div></div>
        <div class="stat-card"><div class="number"><?= $stats['bound'] ?></div><div class="label">已绑定设备</div></div>
        <div class="stat-card"><div class="number"><?= $stats['active'] ?></div><div class="label">有效中</div></div>
        <div class="stat-card"><div class="number"><?= $stats['expired'] ?></div><div class="label">已过期</div></div>
        <div class="stat-card"><div class="number"><?= $stats['banned'] ?></div><div class="label">已禁用</div></div>
        <div class="stat-card"><div class="number"><?= $stats['unbind_unused'] ?></div><div class="label">可用解绑卡</div></div>
    </div>
    <?php else: ?>
    <div class="stats">
        <?php
        $myCards = $kami->listAgentCards($agentUsername, 1, 1000);
        $myTotal = $myCards['total'];
        $myPending = 0;
        $myActive = 0;
        $myUsed = 0;
        foreach ($myCards['list'] as $c) {
            if ($c['status'] === 'pending') $myPending++;
            elseif ($c['status'] === 'active' && !$c['used']) $myActive++;
            elseif ($c['used']) $myUsed++;
        }
        ?>
        <div class="stat-card"><div class="number"><?= $myTotal ?></div><div class="label">我的卡密</div></div>
        <div class="stat-card"><div class="number"><?= $myPending ?></div><div class="label">待审批</div></div>
        <div class="stat-card"><div class="number"><?= $myActive ?></div><div class="label">未使用</div></div>
        <div class="stat-card"><div class="number"><?= $myUsed ?></div><div class="label">已使用</div></div>
    </div>
    <?php endif; ?>
    
    <div class="tabs">
        <?php if ($isAdmin): ?>
        <a href="?tab=cards" class="tab <?= $tab === 'cards' ? 'active' : '' ?>">卡密管理</a>
        <a href="?tab=pending" class="tab <?= $tab === 'pending' ? 'active' : '' ?>">待审批</a>
        <a href="?tab=agents" class="tab <?= $tab === 'agents' ? 'active' : '' ?>">代理管理</a>
        <a href="?tab=unbind" class="tab <?= $tab === 'unbind' ? 'active' : '' ?>">解绑卡管理</a>
        <a href="?tab=api" class="tab <?= $tab === 'api' ? 'active' : '' ?>">API说明</a>
        <?php else: ?>
        <a href="?tab=cards" class="tab <?= $tab === 'cards' ? 'active' : '' ?>">卡密管理</a>
        <a href="?tab=unbind" class="tab <?= $tab === 'unbind' ? 'active' : '' ?>">解绑卡管理</a>
        <a href="?tab=api" class="tab <?= $tab === 'api' ? 'active' : '' ?>">API说明</a>
        <?php endif; ?>
    </div>
    
    <?php if ($tab === 'cards'): ?>
    <div class="panel">
        <h3><?= $isAdmin ? '生成卡密' : '申请卡密（需管理员审批）' ?></h3>
        <form method="post" class="generate-form">
            <div class="form-group">
                <label>卡密类型</label>
                <select name="type">
                    <?php foreach ($config['card_types'] as $key => $type): ?>
                    <option value="<?= $key ?>"><?= $type['name'] ?> (<?= $type['duration'] > 0 ? $type['duration'].'天' : '永久' ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><?= $isAdmin ? '生成数量' : '申请数量' ?></label>
                <input type="number" name="count" value="1" min="1" max="<?= $isAdmin ? 1000 : 100 ?>">
            </div>
            <div class="form-group">
                <label>备注</label>
                <input type="text" name="remark" placeholder="可选">
            </div>
            <button type="submit" name="<?= $isAdmin ? 'generate' : 'agent_generate' ?>" class="btn btn-success"><?= $isAdmin ? '生成卡密' : '提交申请' ?></button>
        </form>
        <?php if ($generatedCards && $isAdmin): ?>
        <div class="generated-cards">
            <strong>新生成的卡密（一机一码，绑定后不可更换设备）：</strong>
            <?php foreach ($generatedCards as $card): ?>
            <div class="card-item">
                <span><?= $card ?></span>
                <button onclick="copyText('<?= $card ?>')" class="btn" style="padding:2px 8px;font-size:12px">复制</button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="panel">
        <h3><?= $isAdmin ? '卡密列表' : '我的卡密' ?></h3>
        <?php
        if ($isAgent) {
            $cardList = $kami->listAgentCards($agentUsername, $page, 50);
        }
        ?>
        <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>卡密</th>
                    <?php if ($isAdmin): ?><th>操作</th><?php endif; ?>
                    <th>类型</th>
                    <th>状态</th>
                    <th>创建时间</th>
                    <th>过期时间</th>
                    <th>绑定设备</th>
                    <?php if ($isAdmin): ?><th>备注</th><?php endif; ?>
                    <?php if ($isAgent): ?><th>操作</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cardList['list'] as $card): ?>
                <tr>
                    <td><span class="card-text"><?= $card['card'] ?></span></td>
                    <?php if ($isAdmin): ?>
                    <td style="white-space:nowrap">
                        <?php
                        $status = $card['status'];
                        if ($status === 'active' && $card['used'] && $card['expires_at'] && strtotime($card['expires_at']) < time()) {
                            $status = 'expired';
                        }
                        ?>
                        <?php if ($status === 'banned'): ?>
                        <a href="?unban=<?= urlencode($card['card']) ?>&tab=cards" class="btn btn-success" style="padding:3px 8px;font-size:12px">解禁</a>
                        <?php else: ?>
                        <a href="?ban=<?= urlencode($card['card']) ?>&tab=cards" class="btn btn-warning" style="padding:3px 8px;font-size:12px">禁用</a>
                        <?php endif; ?>
                        <a href="?delete=<?= urlencode($card['card']) ?>&tab=cards" class="btn btn-danger" style="padding:3px 8px;font-size:12px" onclick="return confirm('确定删除？')">删除</a>
                    </td>
                    <?php endif; ?>
                    <td><?= $card['type_name'] ?></td>
                    <td>
                        <?php
                        $status = $card['status'];
                        if ($status === 'active' && $card['used'] && $card['expires_at'] && strtotime($card['expires_at']) < time()) {
                            $status = 'expired';
                        }
                        $statusText = ['active' => '有效', 'pending' => '待审批', 'used' => '已使用', 'expired' => '已过期', 'banned' => '已禁用', 'rejected' => '已拒绝'];
                        echo "<span class='status-{$status}'>" . ($statusText[$status] ?? $status) . "</span>";
                        ?>
                    </td>
                    <td><?= $card['created_at'] ?></td>
                    <td><?= $card['expires_at'] ?: '永久' ?></td>
                    <td>
                        <?php if ($card['device_id']): ?>
                        <div class="device-info"><?= htmlspecialchars($card['device_name'] ?: substr($card['device_id'], 0, 12).'...') ?></div>
                        <?php else: ?>
                        <span style="color:#999">未绑定</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($isAdmin): ?><td><?= htmlspecialchars($card['remark'] ?? '') ?></td><?php endif; ?>
                    <?php if ($isAgent): ?>
                    <td>
                        <?php if (!$card['used']): ?>
                        <a href="?agent_delete=<?= urlencode($card['card']) ?>&tab=cards" class="btn btn-danger" style="padding:3px 8px;font-size:12px" onclick="return confirm('确定删除？')">删除</a>
                        <?php else: ?>
                        <span style="color:#999">已使用</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if ($isAdmin): ?>
        <div class="pagination">
            <?php
            $totalPages = ceil($cardList['total'] / $cardList['pageSize']);
            for ($i = 1; $i <= $totalPages; $i++):
            ?>
            <?php if ($i == $page): ?>
            <span class="current"><?= $i ?></span>
            <?php else: ?>
            <a href="?page=<?= $i ?>&tab=cards"><?= $i ?></a>
            <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php elseif ($tab === 'unbind'): ?>
    <?php if ($isAdmin): ?>
    <div class="panel">
        <h3>待审批解绑卡（代理申请）</h3>
        <?php $pendingUnbind = $kami->listPendingUnbindCards(); ?>
        <?php if (empty($pendingUnbind)): ?>
        <p style="color:#999">暂无待审批解绑卡</p>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table>
            <thead>
                <tr><th>解绑卡</th><th>代理</th><th>申请时间</th><th>备注</th><th>操作</th></tr>
            </thead>
            <tbody>
                <?php foreach ($pendingUnbind as $card): ?>
                <tr>
                    <td><span class="card-text"><?= $card['card'] ?></span></td>
                    <td><?= htmlspecialchars($card['agent_id'] ?? '') ?></td>
                    <td><?= $card['created_at'] ?></td>
                    <td><?= htmlspecialchars($card['remark'] ?? '') ?></td>
                    <td style="white-space:nowrap">
                        <a href="?approve_unbind=<?= urlencode($card['card']) ?>&tab=unbind" class="btn btn-success" style="padding:3px 8px;font-size:12px">通过</a>
                        <a href="?reject_unbind=<?= urlencode($card['card']) ?>&tab=unbind" class="btn btn-danger" style="padding:3px 8px;font-size:12px" onclick="return confirm('确定拒绝？')">拒绝</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
    <div class="panel">
        <h3>生成解绑卡</h3>
        <form method="post" class="generate-form">
            <div class="form-group">
                <label>生成数量</label>
                <input type="number" name="count" value="1" min="1" max="1000">
            </div>
            <div class="form-group">
                <label>备注</label>
                <input type="text" name="remark" placeholder="可选">
            </div>
            <button type="submit" name="generate_unbind" class="btn btn-warning">生成解绑卡</button>
        </form>
        <?php if ($generatedUnbindCards): ?>
        <div class="generated-cards">
            <strong>新生成的解绑卡（每张只能用一次）：</strong>
            <?php foreach ($generatedUnbindCards as $card): ?>
            <div class="card-item">
                <span><?= $card ?></span>
                <button onclick="copyText('<?= $card ?>')" class="btn" style="padding:2px 8px;font-size:12px">复制</button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="panel">
        <h3>后台强制解绑（不需要解绑卡）</h3>
        <form method="post" class="generate-form">
            <div class="form-group" style="flex:2">
                <label>输入要解绑的卡密</label>
                <input type="text" name="card" placeholder="XXXX-XXXX-XXXX-XXXX" required>
            </div>
            <button type="submit" name="admin_unbind" class="btn btn-danger" onclick="return confirm('确定强制解绑？此操作会清除设备绑定')">强制解绑</button>
        </form>
    </div>
    <?php else: ?>
    <div class="panel">
        <h3>申请解绑卡（需管理员审批）</h3>
        <form method="post" class="generate-form">
            <div class="form-group">
                <label>申请数量</label>
                <input type="number" name="count" value="1" min="1" max="50">
            </div>
            <div class="form-group">
                <label>备注</label>
                <input type="text" name="remark" placeholder="可选">
            </div>
            <button type="submit" name="agent_generate_unbind" class="btn btn-warning">提交申请</button>
        </form>
    </div>
    <?php endif; ?>
    
    <div class="panel">
        <h3><?= $isAdmin ? '解绑卡列表' : '我的解绑卡' ?></h3>
        <?php
        if ($isAgent) {
            $unbindList = $kami->listAgentUnbindCards($agentUsername, $page, 50);
        }
        ?>
        <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>解绑卡</th>
                    <th>状态</th>
                    <th>创建时间</th>
                    <th>使用时间</th>
                    <th>用于卡密</th>
                    <th>备注</th>
                    <?php if ($isAdmin || $isAgent): ?><th>操作</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($unbindList['list'] as $card): ?>
                <tr>
                    <td><span class="card-text"><?= $card['card'] ?></span></td>
                    <td>
                        <?php
                        $ubStatus = $card['status'] ?? 'active';
                        if ($ubStatus === 'pending') {
                            echo '<span style="color:#f39c12">待审批</span>';
                        } elseif ($ubStatus === 'rejected') {
                            echo '<span style="color:red">已拒绝</span>';
                        } elseif ($ubStatus === 'banned') {
                            echo '<span class="status-banned">已禁用</span>';
                        } elseif ($card['used']) {
                            echo '<span class="status-used">已使用</span>';
                        } else {
                            echo '<span class="status-active">未使用</span>';
                        }
                        ?>
                    </td>
                    <td><?= $card['created_at'] ?></td>
                    <td><?= $card['used_at'] ?: '-' ?></td>
                    <td><?= $card['used_for_card'] ? '<span class="card-text">'.$card['used_for_card'].'</span>' : '-' ?></td>
                    <td><?= htmlspecialchars($card['remark'] ?? '') ?></td>
                    <?php if ($isAdmin): ?>
                    <td>
                        <?php if (!$card['used']): ?>
                        <a href="?ban_unbind=<?= urlencode($card['card']) ?>&tab=unbind" class="btn btn-warning" style="padding:3px 8px;font-size:12px">禁用</a>
                        <?php endif; ?>
                        <a href="?delete_unbind=<?= urlencode($card['card']) ?>&tab=unbind" class="btn btn-danger" style="padding:3px 8px;font-size:12px" onclick="return confirm('确定删除？')">删除</a>
                    </td>
                    <?php elseif ($isAgent): ?>
                    <td>
                        <?php if (!$card['used']): ?>
                        <a href="?agent_delete_unbind=<?= urlencode($card['card']) ?>&tab=unbind" class="btn btn-danger" style="padding:3px 8px;font-size:12px" onclick="return confirm('确定删除？')">删除</a>
                        <?php else: ?>
                        <span style="color:#999">已使用</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php elseif ($tab === 'api'): ?>
    <div class="panel">
        <h3>API接口说明</h3>
        <div class="api-info">
            <strong>验证卡密：</strong> api.php?action=verify&card=卡密&device_id=设备ID&device_name=设备名<br>
            <strong>解绑设备：</strong> api.php?action=unbind&card=卡密&unbind_card=解绑卡&new_device_id=新设备ID<br>
            <strong>查询卡密：</strong> api.php?action=info&card=卡密<br>
            <strong>查询解绑卡：</strong> api.php?action=unbind_info&card=解绑卡<br>
            <strong>生成卡密：</strong> api.php?action=generate&type=month&count=10&password=管理员密码<br>
            <strong>生成解绑卡：</strong> api.php?action=generate_unbind&count=10&password=管理员密码<br>
            <strong>卡密列表：</strong> api.php?action=list&password=管理员密码<br>
            <strong>解绑卡列表：</strong> api.php?action=list_unbind&password=管理员密码<br>
            <strong>禁用卡密：</strong> api.php?action=ban&card=卡密&password=管理员密码<br>
            <strong>删除卡密：</strong> api.php?action=delete&card=卡密&password=管理员密码<br><br>
            <strong>一机一码规则：</strong><br>
            1. 卡密首次使用自动绑定设备<br>
            2. 绑定后只能在该设备使用，换设备会提示"已绑定其他设备"<br>
            3. 更换设备必须使用解绑卡（UB开头）<br>
            4. 解绑卡只能使用一次，使用后失效<br>
            5. 后台可以强制解绑（不需要解绑卡）
        </div>
    </div>
    
    <?php elseif ($tab === 'pending'): ?>
    <div class="panel">
        <?php $pendingCards = $kami->listPendingCards(); ?>
        <h3>待审批卡密（代理申请）
            <?php if (!empty($pendingCards)): ?>
            <a href="?clear_pending=1" class="btn btn-danger" style="float:right;padding:5px 15px;font-size:13px" onclick="return confirm('确定清空所有待审批卡密？此操作不可恢复！')">一键清空全部</a>
            <?php endif; ?>
        </h3>
        <?php if (empty($pendingCards)): ?>
        <p style="color:#999">暂无待审批卡密</p>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>卡密</th>
                    <th>代理</th>
                    <th>类型</th>
                    <th>申请时间</th>
                    <th>备注</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingCards as $card): ?>
                <tr>
                    <td><span class="card-text"><?= $card['card'] ?></span></td>
                    <td><?= htmlspecialchars($card['agent_id'] ?? '') ?></td>
                    <td><?= $card['type_name'] ?></td>
                    <td><?= $card['created_at'] ?></td>
                    <td><?= htmlspecialchars($card['remark'] ?? '') ?></td>
                    <td style="white-space:nowrap">
                        <a href="?approve=<?= urlencode($card['card']) ?>&tab=pending" class="btn btn-success" style="padding:3px 8px;font-size:12px">通过</a>
                        <a href="?reject=<?= urlencode($card['card']) ?>&tab=pending" class="btn btn-danger" style="padding:3px 8px;font-size:12px" onclick="return confirm('确定拒绝？')">拒绝</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
    
    <?php elseif ($tab === 'agents'): ?>
    <div class="panel">
        <h3>待审批代理</h3>
        <?php $pendingAgents = $kami->listPendingAgents(); ?>
        <?php if (empty($pendingAgents)): ?>
        <p style="color:#999">暂无待审批代理</p>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>用户名</th>
                    <th>注册时间</th>
                    <th>备注</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingAgents as $agent): ?>
                <tr>
                    <td><?= htmlspecialchars($agent['username']) ?></td>
                    <td><?= $agent['created_at'] ?></td>
                    <td><?= htmlspecialchars($agent['remark'] ?? '') ?></td>
                    <td style="white-space:nowrap">
                        <a href="?approve_agent=<?= urlencode($agent['username']) ?>&tab=agents" class="btn btn-success" style="padding:3px 8px;font-size:12px">通过</a>
                        <a href="?reject_agent=<?= urlencode($agent['username']) ?>&tab=agents" class="btn btn-danger" style="padding:3px 8px;font-size:12px" onclick="return confirm('确定拒绝？')">拒绝</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
    <div class="panel">
        <h3>创建代理（直接创建，无需审批）</h3>
        <form method="post" class="generate-form">
            <div class="form-group">
                <label>代理用户名</label>
                <input type="text" name="agent_username" required>
            </div>
            <div class="form-group">
                <label>代理密码</label>
                <input type="text" name="agent_password" required>
            </div>
            <div class="form-group">
                <label>备注</label>
                <input type="text" name="agent_remark" placeholder="可选">
            </div>
            <button type="submit" name="create_agent" class="btn btn-success">创建代理</button>
        </form>
    </div>
    <div class="panel">
        <h3>代理列表</h3>
        <?php $agentList = $kami->listAgents(); ?>
        <?php if (empty($agentList)): ?>
        <p style="color:#999">暂无代理</p>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>用户名</th>
                    <th>状态</th>
                    <th>创建时间</th>
                    <th>总卡密数</th>
                    <th>备注</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($agentList as $agent): ?>
                <tr>
                    <td><?= htmlspecialchars($agent['username']) ?></td>
                    <td>
                        <?php
                        $statusMap = ['active' => ['正常', 'green'], 'pending' => ['待审批', '#f39c12'], 'banned' => ['已禁用', 'red'], 'rejected' => ['已拒绝', 'red']];
                        $st = $statusMap[$agent['status']] ?? [$agent['status'], '#999'];
                        echo "<span style='color:{$st[1]}'>{$st[0]}</span>";
                        ?>
                    </td>
                    <td><?= $agent['created_at'] ?></td>
                    <td><?= $agent['total_cards'] ?></td>
                    <td><?= htmlspecialchars($agent['remark'] ?? '') ?></td>
                    <td style="white-space:nowrap">
                        <a href="?toggle_agent=<?= urlencode($agent['username']) ?>&tab=agents" class="btn btn-warning" style="padding:3px 8px;font-size:12px"><?= $agent['status'] === 'active' ? '禁用' : '启用' ?></a>
                        <a href="?delete_agent=<?= urlencode($agent['username']) ?>&tab=agents" class="btn btn-danger" style="padding:3px 8px;font-size:12px" onclick="return confirm('确定删除代理？')">删除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
    
    <?php endif; ?>
    <?php endif; ?>
</div>
<script>
function copyText(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('已复制：' + text);
    });
}
</script>
</body>
</html>
