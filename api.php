<?php
// 卡密系统API接口
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/KamiSystem.php';
$config = require __DIR__ . '/config.php';
$kami = new KamiSystem();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function checkAdmin($config) {
    $password = $_GET['password'] ?? $_POST['password'] ?? '';
    return $password === $config['admin_password'];
}

function output($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

switch ($action) {
    // 验证卡密（公开接口）
    case 'verify':
        $card = $_GET['card'] ?? $_POST['card'] ?? '';
        $deviceId = $_GET['device_id'] ?? $_POST['device_id'] ?? '';
        $deviceName = $_GET['device_name'] ?? $_POST['device_name'] ?? '';
        if (!$card) output(['success' => false, 'error' => '请输入卡密']);
        if (!$deviceId) output(['success' => false, 'error' => '缺少设备ID']);
        $result = $kami->verify($card, $deviceId, $deviceName);
        output($result);
        break;
    
    // 解绑设备（公开接口，需要解绑卡）
    case 'unbind':
        $card = $_GET['card'] ?? $_POST['card'] ?? '';
        $unbindCard = $_GET['unbind_card'] ?? $_POST['unbind_card'] ?? '';
        $newDeviceId = $_GET['new_device_id'] ?? $_POST['new_device_id'] ?? '';
        $newDeviceName = $_GET['new_device_name'] ?? $_POST['new_device_name'] ?? '';
        if (!$card) output(['success' => false, 'error' => '请输入卡密']);
        if (!$unbindCard) output(['success' => false, 'error' => '请输入解绑卡']);
        $result = $kami->unbind($card, $unbindCard, $newDeviceId, $newDeviceName);
        output($result);
        break;
    
    // 查询卡密信息（公开接口）
    case 'info':
        $card = $_GET['card'] ?? $_POST['card'] ?? '';
        if (!$card) output(['success' => false, 'error' => '请输入卡密']);
        $result = $kami->info($card);
        output($result);
        break;
    
    // 查询解绑卡信息（公开接口）
    case 'unbind_info':
        $card = $_GET['card'] ?? $_POST['card'] ?? '';
        if (!$card) output(['success' => false, 'error' => '请输入解绑卡']);
        $result = $kami->unbindInfo($card);
        output($result);
        break;
    
    // 生成卡密（需要管理员）
    case 'generate':
        if (!checkAdmin($config)) output(['success' => false, 'error' => '管理员密码错误']);
        $type = $_GET['type'] ?? $_POST['type'] ?? 'day';
        $count = intval($_GET['count'] ?? $_POST['count'] ?? 1);
        $remark = $_GET['remark'] ?? $_POST['remark'] ?? '';
        if ($count < 1 || $count > 1000) output(['success' => false, 'error' => '生成数量必须在1-1000之间']);
        $result = $kami->generate($type, $count, $remark);
        output($result);
        break;
    
    // 生成解绑卡（需要管理员）
    case 'generate_unbind':
        if (!checkAdmin($config)) output(['success' => false, 'error' => '管理员密码错误']);
        $count = intval($_GET['count'] ?? $_POST['count'] ?? 1);
        $remark = $_GET['remark'] ?? $_POST['remark'] ?? '';
        if ($count < 1 || $count > 1000) output(['success' => false, 'error' => '生成数量必须在1-1000之间']);
        $result = $kami->generateUnbindCard($count, $remark);
        output($result);
        break;
    
    // 获取卡密列表（需要管理员）
    case 'list':
        if (!checkAdmin($config)) output(['success' => false, 'error' => '管理员密码错误']);
        $page = intval($_GET['page'] ?? 1);
        $pageSize = intval($_GET['pageSize'] ?? 50);
        $result = $kami->listAll($page, $pageSize);
        output(['success' => true, 'data' => $result]);
        break;
    
    // 获取解绑卡列表（需要管理员）
    case 'list_unbind':
        if (!checkAdmin($config)) output(['success' => false, 'error' => '管理员密码错误']);
        $page = intval($_GET['page'] ?? 1);
        $pageSize = intval($_GET['pageSize'] ?? 50);
        $result = $kami->listUnbindCards($page, $pageSize);
        output(['success' => true, 'data' => $result]);
        break;
    
    // 删除卡密（需要管理员）
    case 'delete':
        if (!checkAdmin($config)) output(['success' => false, 'error' => '管理员密码错误']);
        $card = $_GET['card'] ?? $_POST['card'] ?? '';
        $result = $kami->delete($card);
        output($result);
        break;
    
    // 删除解绑卡（需要管理员）
    case 'delete_unbind':
        if (!checkAdmin($config)) output(['success' => false, 'error' => '管理员密码错误']);
        $card = $_GET['card'] ?? $_POST['card'] ?? '';
        $result = $kami->deleteUnbindCard($card);
        output($result);
        break;
    
    // 管理员强制解绑（需要管理员）
    case 'force_unbind':
        if (!checkAdmin($config)) output(['success' => false, 'error' => '管理员密码错误']);
        $card = $_GET['card'] ?? $_POST['card'] ?? '';
        if (!$card) output(['success' => false, 'error' => '请输入卡密']);
        $result = $kami->forceUnbind($card);
        output($result);
        break;
    
    // 禁用卡密（需要管理员）
    case 'ban':
        if (!checkAdmin($config)) output(['success' => false, 'error' => '管理员密码错误']);
        $card = $_GET['card'] ?? $_POST['card'] ?? '';
        $result = $kami->ban($card);
        output($result);
        break;
    
    // 解禁卡密（需要管理员）
    case 'unban':
        if (!checkAdmin($config)) output(['success' => false, 'error' => '管理员密码错误']);
        $card = $_GET['card'] ?? $_POST['card'] ?? '';
        $result = $kami->unban($card);
        output($result);
        break;
    
    // 禁用解绑卡（需要管理员）
    case 'ban_unbind':
        if (!checkAdmin($config)) output(['success' => false, 'error' => '管理员密码错误']);
        $card = $_GET['card'] ?? $_POST['card'] ?? '';
        $result = $kami->banUnbindCard($card);
        output($result);
        break;
    
    // 统计（需要管理员）
    case 'stats':
        if (!checkAdmin($config)) output(['success' => false, 'error' => '管理员密码错误']);
        $result = $kami->stats();
        output(['success' => true, 'data' => $result]);
        break;
    
    default:
        output([
            'success' => false,
            'error' => '无效的操作',
            'available_actions' => [
                '公开接口' => ['verify', 'unbind', 'info', 'unbind_info'],
                '管理员接口' => ['generate', 'generate_unbind', 'list', 'list_unbind', 'delete', 'delete_unbind', 'ban', 'unban', 'ban_unbind', 'stats'],
            ],
        ]);
}
