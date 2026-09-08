<?php
// 卡密系统配置文件
return [
    // 管理员账号（登录后台用）
    'admin_username' => '账号用户名',
    'admin_password' => '账号密码',
    
    // 卡密字符集
    'charset' => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',
    
    // 卡密长度
    'card_length' => 16,
    
    // 卡密分组（每4个字符加一个-）
    'card_group' => true,
    
    // 数据文件路径
    'data_file' => __DIR__ . '/cards.json',
    
    // 解绑卡数据文件
    'unbind_data_file' => __DIR__ . '/unbind_cards.json',
    
    // 代理数据文件
    'agent_data_file' => __DIR__ . '/agents.json',
    
    // 日志文件
    'log_file' => __DIR__ . '/verify.log',
    
    // 是否开启日志
    'enable_log' => true,
    
    // 卡密类型配置
    'card_types' => [
        'day' => ['name' => '天卡', 'duration' => 1],
        'week' => ['name' => '周卡', 'duration' => 7],
        'month' => ['name' => '月卡', 'duration' => 30],
        'quarter' => ['name' => '季卡', 'duration' => 90],
        'year' => ['name' => '年卡', 'duration' => 365],
        'forever' => ['name' => '永久卡', 'duration' => 0],
    ],
    
    // 是否绑定设备
    'bind_device' => true,
    
    // 同一卡密最多绑定设备数
    'max_devices' => 1,
];
