<?php
// 卡密系统核心类
class KamiSystem {
    private $config;
    private $cards;
    private $unbindCards;
    private $agents;
    
    public function __construct() {
        $this->config = require __DIR__ . '/config.php';
        $this->loadCards();
        $this->loadUnbindCards();
        $this->loadAgents();
    }
    
    // 加载卡密数据
    private function loadCards() {
        if (file_exists($this->config['data_file'])) {
            $this->cards = json_decode(file_get_contents($this->config['data_file']), true);
            if (!is_array($this->cards)) $this->cards = [];
        } else {
            $this->cards = [];
        }
    }
    
    // 加载解绑卡数据
    private function loadUnbindCards() {
        if (file_exists($this->config['unbind_data_file'])) {
            $this->unbindCards = json_decode(file_get_contents($this->config['unbind_data_file']), true);
            if (!is_array($this->unbindCards)) $this->unbindCards = [];
        } else {
            $this->unbindCards = [];
        }
    }
    
    // 保存卡密数据
    private function saveCards() {
        file_put_contents($this->config['data_file'], json_encode($this->cards, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    // 保存解绑卡数据
    private function saveUnbindCards() {
        file_put_contents($this->config['unbind_data_file'], json_encode($this->unbindCards, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    // 生成随机卡密
    private function generateCardString() {
        $charset = $this->config['charset'];
        $length = $this->config['card_length'];
        $card = '';
        for ($i = 0; $i < $length; $i++) {
            $card .= $charset[rand(0, strlen($charset) - 1)];
        }
        if ($this->config['card_group']) {
            $card = implode('-', str_split($card, 4));
        }
        return $card;
    }
    
    // 生成卡密
    public function generate($type, $count = 1, $remark = '') {
        if (!isset($this->config['card_types'][$type])) {
            return ['success' => false, 'error' => '无效的卡密类型'];
        }
        $typeConfig = $this->config['card_types'][$type];
        $generated = [];
        for ($i = 0; $i < $count; $i++) {
            $card = $this->generateCardString();
            while (isset($this->cards[$card])) {
                $card = $this->generateCardString();
            }
            $this->cards[$card] = [
                'card' => $card,
                'type' => $type,
                'type_name' => $typeConfig['name'],
                'duration' => $typeConfig['duration'],
                'created_at' => date('Y-m-d H:i:s'),
                'expires_at' => null,
                'used' => false,
                'used_at' => null,
                'device_id' => null,
                'device_name' => '',
                'status' => 'active',
                'remark' => $remark,
                'unbind_history' => [],
            ];
            $generated[] = $card;
        }
        $this->saveCards();
        return ['success' => true, 'cards' => $generated];
    }
    
    // 代理申请解绑卡（待审批）
    public function agentGenerateUnbindCard($agentUsername, $count = 1, $remark = '') {
        if (!isset($this->agents[$agentUsername])) return ['success' => false, 'error' => '代理不存在'];
        $generated = [];
        for ($i = 0; $i < $count; $i++) {
            $card = 'UB' . $this->generateCardString();
            while (isset($this->unbindCards[$card])) {
                $card = 'UB' . $this->generateCardString();
            }
            $this->unbindCards[$card] = [
                'card' => $card,
                'created_at' => date('Y-m-d H:i:s'),
                'used' => false,
                'used_at' => null,
                'used_for_card' => '',
                'status' => 'pending',
                'agent_id' => $agentUsername,
                'remark' => $remark ?: '代理申请待审批',
            ];
            $generated[] = $card;
        }
        $this->saveUnbindCards();
        return ['success' => true, 'cards' => $generated, 'message' => '已提交审批，请等待管理员审批'];
    }
    
    // 审批代理解绑卡
    public function approveUnbindCard($card) {
        $card = strtoupper(trim($card));
        if (!isset($this->unbindCards[$card])) return ['success' => false, 'error' => '解绑卡不存在'];
        if ($this->unbindCards[$card]['status'] !== 'pending') return ['success' => false, 'error' => '该解绑卡无需审批'];
        $this->unbindCards[$card]['status'] = 'active';
        $this->saveUnbindCards();
        return ['success' => true, 'message' => '审批通过'];
    }
    
    // 拒绝代理解绑卡
    public function rejectUnbindCard($card) {
        $card = strtoupper(trim($card));
        if (!isset($this->unbindCards[$card])) return ['success' => false, 'error' => '解绑卡不存在'];
        $this->unbindCards[$card]['status'] = 'rejected';
        $this->saveUnbindCards();
        return ['success' => true, 'message' => '已拒绝'];
    }
    
    // 获取待审批解绑卡列表
    public function listPendingUnbindCards() {
        $pending = [];
        foreach ($this->unbindCards as $card => $data) {
            if (($data['status'] ?? '') === 'pending') {
                $pending[] = $data;
            }
        }
        usort($pending, function($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });
        return $pending;
    }
    
    // 获取代理的解绑卡列表
    public function listAgentUnbindCards($agentUsername, $page = 1, $pageSize = 50) {
        $all = [];
        foreach ($this->unbindCards as $card => $data) {
            if (($data['agent_id'] ?? '') === $agentUsername) {
                $all[] = $data;
            }
        }
        usort($all, function($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });
        $total = count($all);
        $start = ($page - 1) * $pageSize;
        $list = array_slice($all, $start, $pageSize);
        return ['total' => $total, 'page' => $page, 'pageSize' => $pageSize, 'list' => $list];
    }
    
    // 代理删除自己的解绑卡
    public function agentDeleteUnbindCard($agentUsername, $card) {
        $card = strtoupper(trim($card));
        if (!isset($this->unbindCards[$card])) return ['success' => false, 'error' => '解绑卡不存在'];
        if (($this->unbindCards[$card]['agent_id'] ?? '') !== $agentUsername) return ['success' => false, 'error' => '无权删除'];
        if ($this->unbindCards[$card]['used']) return ['success' => false, 'error' => '已使用的不能删除'];
        unset($this->unbindCards[$card]);
        $this->saveUnbindCards();
        return ['success' => true];
    }
    
    // 生成解绑卡
    public function generateUnbindCard($count = 1, $remark = '') {
        $generated = [];
        for ($i = 0; $i < $count; $i++) {
            $card = 'UB' . $this->generateCardString();
            while (isset($this->unbindCards[$card])) {
                $card = 'UB' . $this->generateCardString();
            }
            $this->unbindCards[$card] = [
                'card' => $card,
                'created_at' => date('Y-m-d H:i:s'),
                'used' => false,
                'used_at' => null,
                'used_for_card' => null,
                'status' => 'active',
                'remark' => $remark,
            ];
            $generated[] = $card;
        }
        $this->saveUnbindCards();
        return ['success' => true, 'cards' => $generated];
    }
    
    // 验证卡密（严格一机一码）
    public function verify($card, $deviceId = '', $deviceName = '') {
        $card = strtoupper(trim($card));
        if (!isset($this->cards[$card])) {
            $this->log($card, $deviceId, 'fail', '卡密不存在');
            return ['success' => false, 'error' => '卡密不存在'];
        }
        $data = $this->cards[$card];
        
        // 检查状态
        if ($data['status'] === 'banned') {
            $this->log($card, $deviceId, 'fail', '卡密已被禁用');
            return ['success' => false, 'error' => '卡密已被禁用'];
        }
        
        // 检查是否已过期
        if ($data['used'] && $data['expires_at']) {
            if (strtotime($data['expires_at']) < time()) {
                $data['status'] = 'expired';
                $this->cards[$card] = $data;
                $this->saveCards();
                $this->log($card, $deviceId, 'fail', '卡密已过期');
                return ['success' => false, 'error' => '卡密已过期'];
            }
        }
        
        // 首次使用，激活并绑定设备
        if (!$data['used']) {
            if (!$deviceId) {
                $this->log($card, $deviceId, 'fail', '缺少设备ID');
                return ['success' => false, 'error' => '缺少设备ID'];
            }
            $data['used'] = true;
            $data['used_at'] = date('Y-m-d H:i:s');
            $data['device_id'] = $deviceId;
            $data['device_name'] = $deviceName;
            if ($data['duration'] > 0) {
                $data['expires_at'] = date('Y-m-d H:i:s', time() + $data['duration'] * 86400);
            }
            $this->cards[$card] = $data;
            $this->saveCards();
            $this->log($card, $deviceId, 'success', '首次激活并绑定设备');
            return [
                'success' => true,
                'card' => $card,
                'type' => $data['type_name'],
                'expires_at' => $data['expires_at'] ?: '永久有效',
                'device_id' => $data['device_id'],
                'device_name' => $data['device_name'],
                'message' => '卡密已激活并绑定本设备',
            ];
        }
        
        // 已使用，验证设备是否匹配（严格一机一码）
        if ($data['device_id'] !== $deviceId) {
            $this->log($card, $deviceId, 'fail', '设备不匹配，卡密已绑定其他设备');
            return [
                'success' => false,
                'error' => '该卡密已绑定其他设备，如需更换设备请使用解绑卡',
                'bound_device' => $data['device_name'] ?: $data['device_id'],
            ];
        }
        
        $this->log($card, $deviceId, 'success', '验证成功');
        return [
            'success' => true,
            'card' => $card,
            'type' => $data['type_name'],
            'expires_at' => $data['expires_at'] ?: '永久有效',
            'device_id' => $data['device_id'],
            'device_name' => $data['device_name'],
        ];
    }
    
    // 使用解绑卡解绑设备
    public function unbind($card, $unbindCard, $newDeviceId = '', $newDeviceName = '') {
        $card = strtoupper(trim($card));
        $unbindCard = strtoupper(trim($unbindCard));
        
        // 检查卡密是否存在
        if (!isset($this->cards[$card])) {
            return ['success' => false, 'error' => '卡密不存在'];
        }
        $data = $this->cards[$card];
        
        // 检查解绑卡是否存在
        if (!isset($this->unbindCards[$unbindCard])) {
            return ['success' => false, 'error' => '解绑卡不存在'];
        }
        $ubData = $this->unbindCards[$unbindCard];
        
        // 检查解绑卡状态
        if ($ubData['status'] === 'banned') {
            return ['success' => false, 'error' => '解绑卡已被禁用'];
        }
        if ($ubData['used']) {
            return ['success' => false, 'error' => '解绑卡已使用过'];
        }
        
        // 检查卡密是否已绑定设备
        if (!$data['used'] || !$data['device_id']) {
            return ['success' => false, 'error' => '该卡密未绑定设备，无需解绑'];
        }
        
        // 记录解绑历史
        $data['unbind_history'][] = [
            'time' => date('Y-m-d H:i:s'),
            'old_device' => $data['device_id'],
            'old_device_name' => $data['device_name'],
            'unbind_card' => $unbindCard,
        ];
        
        // 解绑：清除设备绑定，卡密变为未使用状态
        $oldDevice = $data['device_name'] ?: $data['device_id'];
        $data['device_id'] = null;
        $data['device_name'] = '';
        $data['used'] = false;
        $data['used_at'] = null;
        // 保留过期时间（如果已激活过，有效期不重置）
        
        // 如果提供了新设备ID，直接绑定新设备
        if ($newDeviceId) {
            $data['used'] = true;
            $data['used_at'] = date('Y-m-d H:i:s');
            $data['device_id'] = $newDeviceId;
            $data['device_name'] = $newDeviceName;
        }
        
        $this->cards[$card] = $data;
        
        // 标记解绑卡已使用
        $ubData['used'] = true;
        $ubData['used_at'] = date('Y-m-d H:i:s');
        $ubData['used_for_card'] = $card;
        $this->unbindCards[$unbindCard] = $ubData;
        
        $this->saveCards();
        $this->saveUnbindCards();
        $this->log($card, $newDeviceId, 'unbind', "使用解绑卡{$unbindCard}解绑，原设备：{$oldDevice}");
        
        return [
            'success' => true,
            'message' => $newDeviceId ? '解绑成功并已绑定新设备' : '解绑成功，卡密现在可以在新设备上激活',
            'card' => $card,
            'unbind_card' => $unbindCard,
            'old_device' => $oldDevice,
            'new_device' => $newDeviceName ?: $newDeviceId,
        ];
    }
    
    // 管理员强制解绑（不需要解绑卡）
    public function forceUnbind($card) {
        $card = strtoupper(trim($card));
        if (!isset($this->cards[$card])) {
            return ['success' => false, 'error' => '卡密不存在'];
        }
        $data = $this->cards[$card];
        if (!$data['used'] || !$data['device_id']) {
            return ['success' => false, 'error' => '该卡密未绑定设备，无需解绑'];
        }
        $oldDevice = $data['device_name'] ?: $data['device_id'];
        $data['unbind_history'][] = [
            'time' => date('Y-m-d H:i:s'),
            'old_device' => $data['device_id'],
            'old_device_name' => $data['device_name'],
            'unbind_card' => 'ADMIN_FORCE',
        ];
        $data['device_id'] = null;
        $data['device_name'] = '';
        $data['used'] = false;
        $data['used_at'] = null;
        $this->cards[$card] = $data;
        $this->saveCards();
        $this->log($card, '', 'force_unbind', "管理员强制解绑，原设备：{$oldDevice}");
        return ['success' => true, 'message' => '强制解绑成功', 'card' => $card, 'old_device' => $oldDevice];
    }
    
    // 查询卡密信息
    public function info($card) {
        $card = strtoupper(trim($card));
        if (!isset($this->cards[$card])) {
            return ['success' => false, 'error' => '卡密不存在'];
        }
        return ['success' => true, 'data' => $this->cards[$card]];
    }
    
    // 查询解绑卡信息
    public function unbindInfo($card) {
        $card = strtoupper(trim($card));
        if (!isset($this->unbindCards[$card])) {
            return ['success' => false, 'error' => '解绑卡不存在'];
        }
        return ['success' => true, 'data' => $this->unbindCards[$card]];
    }
    
    // 获取所有卡密
    public function listAll($page = 1, $pageSize = 50) {
        $all = array_values($this->cards);
        usort($all, function($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });
        $total = count($all);
        $start = ($page - 1) * $pageSize;
        $list = array_slice($all, $start, $pageSize);
        return ['total' => $total, 'page' => $page, 'pageSize' => $pageSize, 'list' => $list];
    }
    
    // 获取所有解绑卡
    public function listUnbindCards($page = 1, $pageSize = 50) {
        $all = array_values($this->unbindCards);
        usort($all, function($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });
        $total = count($all);
        $start = ($page - 1) * $pageSize;
        $list = array_slice($all, $start, $pageSize);
        return ['total' => $total, 'page' => $page, 'pageSize' => $pageSize, 'list' => $list];
    }
    
    // 删除卡密
    public function delete($card) {
        $card = strtoupper(trim($card));
        if (!isset($this->cards[$card])) {
            return ['success' => false, 'error' => '卡密不存在'];
        }
        unset($this->cards[$card]);
        $this->saveCards();
        return ['success' => true];
    }
    
    // 删除解绑卡
    public function deleteUnbindCard($card) {
        $card = strtoupper(trim($card));
        if (!isset($this->unbindCards[$card])) {
            return ['success' => false, 'error' => '解绑卡不存在'];
        }
        unset($this->unbindCards[$card]);
        $this->saveUnbindCards();
        return ['success' => true];
    }
    
    // 禁用卡密
    public function ban($card) {
        $card = strtoupper(trim($card));
        if (!isset($this->cards[$card])) {
            return ['success' => false, 'error' => '卡密不存在'];
        }
        $this->cards[$card]['status'] = 'banned';
        $this->saveCards();
        return ['success' => true];
    }
    
    // 解禁卡密
    public function unban($card) {
        $card = strtoupper(trim($card));
        if (!isset($this->cards[$card])) {
            return ['success' => false, 'error' => '卡密不存在'];
        }
        $this->cards[$card]['status'] = 'active';
        $this->saveCards();
        return ['success' => true];
    }
    
    // 禁用解绑卡
    public function banUnbindCard($card) {
        $card = strtoupper(trim($card));
        if (!isset($this->unbindCards[$card])) {
            return ['success' => false, 'error' => '解绑卡不存在'];
        }
        $this->unbindCards[$card]['status'] = 'banned';
        $this->saveUnbindCards();
        return ['success' => true];
    }
    
    // 统计
    public function stats() {
        $total = count($this->cards);
        $used = 0;
        $active = 0;
        $expired = 0;
        $banned = 0;
        $bound = 0;
        foreach ($this->cards as $card) {
            if ($card['used']) $used++;
            if ($card['device_id']) $bound++;
            if ($card['status'] === 'active') $active++;
            if ($card['status'] === 'expired') $expired++;
            if ($card['status'] === 'banned') $banned++;
        }
        $unbindTotal = count($this->unbindCards);
        $unbindUsed = 0;
        foreach ($this->unbindCards as $uc) {
            if ($uc['used']) $unbindUsed++;
        }
        return [
            'total' => $total,
            'used' => $used,
            'unused' => $total - $used,
            'active' => $active,
            'expired' => $expired,
            'banned' => $banned,
            'bound' => $bound,
            'unbind_total' => $unbindTotal,
            'unbind_used' => $unbindUsed,
            'unbind_unused' => $unbindTotal - $unbindUsed,
        ];
    }
    

    // 加载代理数据
    private function loadAgents() {
        if (file_exists($this->config['agent_data_file'])) {
            $this->agents = json_decode(file_get_contents($this->config['agent_data_file']), true);
            if (!is_array($this->agents)) $this->agents = [];
        } else {
            $this->agents = [];
        }
    }
    
    // 保存代理数据
    private function saveAgents() {
        file_put_contents($this->config['agent_data_file'], json_encode($this->agents, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    // 创建代理账号（管理员直接创建，状态active）
    public function createAgent($username, $password, $remark = '') {
        $username = trim($username);
        if (!$username || !$password) return ['success' => false, 'error' => '用户名和密码不能为空'];
        if (isset($this->agents[$username])) return ['success' => false, 'error' => '代理已存在'];
        $this->agents[$username] = [
            'username' => $username,
            'password' => md5($password),
            'created_at' => date('Y-m-d H:i:s'),
            'remark' => $remark,
            'status' => 'active',
            'total_cards' => 0,
            'used_cards' => 0,
        ];
        $this->saveAgents();
        return ['success' => true, 'username' => $username];
    }
    
    // 代理自助注册（状态pending，需管理员审批）
    public function registerAgent($username, $password, $remark = '') {
        $username = trim($username);
        if (!$username || !$password) return ['success' => false, 'error' => '用户名和密码不能为空'];
        if (strlen($username) < 3) return ['success' => false, 'error' => '用户名至少3个字符'];
        if (strlen($password) < 6) return ['success' => false, 'error' => '密码至少6个字符'];
        if (isset($this->agents[$username])) return ['success' => false, 'error' => '用户名已存在'];
        $this->agents[$username] = [
            'username' => $username,
            'password' => md5($password),
            'created_at' => date('Y-m-d H:i:s'),
            'remark' => $remark,
            'status' => 'pending',
            'total_cards' => 0,
            'used_cards' => 0,
        ];
        $this->saveAgents();
        return ['success' => true, 'username' => $username, 'message' => '注册成功，请等待管理员审批'];
    }
    
    // 审批代理注册
    public function approveAgent($username) {
        if (!isset($this->agents[$username])) return ['success' => false, 'error' => '代理不存在'];
        $this->agents[$username]['status'] = 'active';
        $this->saveAgents();
        return ['success' => true, 'message' => '代理已通过审批'];
    }
    
    // 拒绝代理注册
    public function rejectAgent($username) {
        if (!isset($this->agents[$username])) return ['success' => false, 'error' => '代理不存在'];
        $this->agents[$username]['status'] = 'rejected';
        $this->saveAgents();
        return ['success' => true, 'message' => '代理已被拒绝'];
    }
    
    // 获取待审批代理列表
    public function listPendingAgents() {
        $pending = [];
        foreach ($this->agents as $username => $data) {
            if ($data['status'] === 'pending') {
                $pending[] = $data;
            }
        }
        return $pending;
    }
    
    // 代理登录
    public function agentLogin($username, $password) {
        $username = trim($username);
        if (!isset($this->agents[$username])) return ['success' => false, 'error' => '代理不存在'];
        $agent = $this->agents[$username];
        if ($agent['status'] === 'pending') return ['success' => false, 'error' => '账号待审批，请联系管理员'];
        if ($agent['status'] === 'rejected') return ['success' => false, 'error' => '账号已被拒绝'];
        if ($agent['status'] !== 'active') return ['success' => false, 'error' => '代理已被禁用'];
        if ($agent['password'] !== md5($password)) return ['success' => false, 'error' => '密码错误'];
        return ['success' => true, 'username' => $username];
    }
    
    // 代理生成卡密（待审批状态）
    public function agentGenerateCard($agentUsername, $type, $count = 1, $remark = '') {
        if (!isset($this->agents[$agentUsername])) return ['success' => false, 'error' => '代理不存在'];
        if (!isset($this->config['card_types'][$type])) return ['success' => false, 'error' => '无效的卡密类型'];
        $typeConfig = $this->config['card_types'][$type];
        $generated = [];
        for ($i = 0; $i < $count; $i++) {
            $card = $this->generateCardString();
            while (isset($this->cards[$card])) {
                $card = $this->generateCardString();
            }
            $this->cards[$card] = [
                'card' => $card,
                'type' => $type,
                'type_name' => $typeConfig['name'],
                'duration' => $typeConfig['duration'],
                'created_at' => date('Y-m-d H:i:s'),
                'expires_at' => null,
                'used' => false,
                'used_at' => null,
                'device_id' => null,
                'device_name' => '',
                'status' => 'pending',
                'agent_id' => $agentUsername,
                'remark' => $remark ?: '代理生成待审批',
                'unbind_history' => [],
            ];
            $generated[] = $card;
        }
        $this->agents[$agentUsername]['total_cards'] += $count;
        $this->saveAgents();
        $this->saveCards();
        return ['success' => true, 'cards' => $generated, 'message' => '已提交审批，请等待管理员审批'];
    }
    
    // 管理员审批代理卡密
    public function approveCard($card) {
        $card = strtoupper(trim($card));
        if (!isset($this->cards[$card])) return ['success' => false, 'error' => '卡密不存在'];
        if ($this->cards[$card]['status'] !== 'pending') return ['success' => false, 'error' => '该卡密无需审批'];
        $this->cards[$card]['status'] = 'active';
        $this->saveCards();
        return ['success' => true, 'message' => '审批通过'];
    }
    
    // 拒绝代理卡密
    public function rejectCard($card) {
        $card = strtoupper(trim($card));
        if (!isset($this->cards[$card])) return ['success' => false, 'error' => '卡密不存在'];
        $this->cards[$card]['status'] = 'rejected';
        $this->saveCards();
        return ['success' => true, 'message' => '已拒绝'];
    }
    
    // 获取待审批卡密列表
    public function listPendingCards() {
        $pending = [];
        foreach ($this->cards as $card => $data) {
            if ($data['status'] === 'pending') {
                $pending[] = $data;
            }
        }
        usort($pending, function($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });
        return $pending;
    }
    
    // 一键清空所有待审批卡密
    public function clearPendingCards() {
        $count = 0;
        foreach ($this->cards as $card => $data) {
            if ($data['status'] === 'pending') {
                unset($this->cards[$card]);
                $count++;
            }
        }
        $this->saveCards();
        return ['success' => true, 'count' => $count];
    }
    
    // 一键清空指定代理的待审批卡密
    public function clearAgentPendingCards($agentUsername) {
        $count = 0;
        foreach ($this->cards as $card => $data) {
            if ($data['status'] === 'pending' && ($data['agent_id'] ?? '') === $agentUsername) {
                unset($this->cards[$card]);
                $count++;
            }
        }
        $this->saveCards();
        return ['success' => true, 'count' => $count];
    }
    
    // 获取代理的卡密列表
    public function listAgentCards($agentUsername, $page = 1, $pageSize = 30) {
        $all = [];
        foreach ($this->cards as $card => $data) {
            if (($data['agent_id'] ?? '') === $agentUsername) {
                $all[] = $data;
            }
        }
        usort($all, function($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });
        $total = count($all);
        $start = ($page - 1) * $pageSize;
        $list = array_slice($all, $start, $pageSize);
        return ['total' => $total, 'page' => $page, 'pageSize' => $pageSize, 'list' => $list];
    }
    
    // 代理删除自己的卡密（只能删未使用/已拒绝的）
    public function agentDeleteCard($agentUsername, $card) {
        $card = strtoupper(trim($card));
        if (!isset($this->cards[$card])) return ['success' => false, 'error' => '卡密不存在'];
        if (($this->cards[$card]['agent_id'] ?? '') !== $agentUsername) return ['success' => false, 'error' => '无权删除此卡密'];
        if ($this->cards[$card]['used']) return ['success' => false, 'error' => '已使用的卡密不能删除'];
        unset($this->cards[$card]);
        $this->saveCards();
        return ['success' => true];
    }
    
    // 获取代理状态
    public function getAgentStatus($username) {
        if (!isset($this->agents[$username])) return 'none';
        return $this->agents[$username]['status'] ?? 'none';
    }
    
    // 获取所有代理列表
    public function listAgents() {
        return array_values($this->agents);
    }
    
    // 删除代理
    public function deleteAgent($username) {
        if (!isset($this->agents[$username])) return ['success' => false, 'error' => '代理不存在'];
        unset($this->agents[$username]);
        $this->saveAgents();
        return ['success' => true];
    }
    
    // 禁用/启用代理
    public function toggleAgentStatus($username) {
        if (!isset($this->agents[$username])) return ['success' => false, 'error' => '代理不存在'];
        $this->agents[$username]['status'] = ($this->agents[$username]['status'] === 'active') ? 'banned' : 'active';
        $this->saveAgents();
        return ['success' => true, 'status' => $this->agents[$username]['status']];
    }

    // 日志
    private function log($card, $deviceId, $status, $message) {
        if (!$this->config['enable_log']) return;
        $line = date('Y-m-d H:i:s') . " | {$status} | card={$card} | device={$deviceId} | {$message}\n";
        file_put_contents($this->config['log_file'], $line, FILE_APPEND);
    }
}
