<?php
// 设置中国时区
date_default_timezone_set('Asia/Shanghai');

// 验证登录状态
function checkLogin() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
}

// 登录处理
// 在session_start()后添加验证码生成和验证函数
session_start();

// 生成验证码
function generateCaptcha() {
    $chars = '123456789abcdefghijklmnpqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ';
    $captcha = substr(str_shuffle($chars), 0, 4);
    $_SESSION['captcha'] = $captcha;
    return $captcha;
}

// 验证验证码
function verifyCaptcha($input) {
    return isset($_SESSION['captcha']) && strtolower($_SESSION['captcha']) === strtolower($input);
}

// 修改登录处理部分
if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $captcha = $_POST['captcha'] ?? '';
    
    if ($username === 'lys' && $password === '198234' && verifyCaptcha($captcha)) {
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        $_SESSION['username'] = $username;
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } elseif (!verifyCaptcha($captcha)) {
        $login_error = "验证码错误";
    } else {
        $login_error = "用户名或密码错误";
    }
}

// 处理退出登录
if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// 会话安全检查
function check_session() {
    if (!isset($_SESSION['logged_in']) || 
        $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR'] ||
        $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT'] ||
        (time() - $_SESSION['last_activity']) > 1800) {
        session_unset();
        session_destroy();
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// 如果是已登录状态，检查会话
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    check_session();
}

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_USER', 'lys2025');
define('DB_PASS', 'lc20110610');
define('DB_NAME', 'lys2025');

// 命令行执行功能
if (isset($_POST['command']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $command = trim($_POST['command']);
    $command_result = '';
    
    if (!empty($command)) {
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
                throw new Exception("数据库连接失败: " . $conn->connect_error);
            }
            
            $start_time = microtime(true); // 记录开始时间
            $command_result .= "> 开始执行命令...\n";
            
            // 执行SQL命令
            if ($conn->multi_query($command)) {
                do {
                    if ($result = $conn->store_result()) {
                        // 处理查询结果
                        $row_count = 0;
                        while ($row = $result->fetch_assoc()) {
                            $command_result .= print_r($row, true) . "\n";
                            $row_count++;
                        }
                        $command_result .= "> 返回了 {$row_count} 行数据\n";
                        $result->free();
                    }
                } while ($conn->next_result());
                
                $execution_time = round((microtime(true) - $start_time) * 1000, 2); // 计算执行时间(毫秒)
                $command_result .= "\n> 命令执行成功! (耗时: {$execution_time}ms)\n";
            } else {
                throw new Exception("执行错误: " . $conn->error);
            }
            
            $conn->close();
        } catch (Exception $e) {
            $command_result .= "\n> 命令执行失败: " . $e->getMessage() . "\n";
        }
    }
}

// 确保备份目录存在且可写
define('BACKUP_DIR', $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'databack');
if (!file_exists(BACKUP_DIR)) {
    if (!mkdir(BACKUP_DIR, 0755, true)) {
        die("无法创建备份目录: " . BACKUP_DIR);
    }
} elseif (!is_writable(BACKUP_DIR)) {
    die("备份目录不可写: " . BACKUP_DIR);
}

// 在备份处理前添加调试信息
if (isset($_POST['backup'])) {
    echo "<pre>调试信息：\n";
    echo "网站根目录: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
    echo "备份目录: " . BACKUP_DIR . "\n";
    echo "目录是否存在: " . (file_exists(BACKUP_DIR) ? '是' : '否') . "\n";
    echo "目录可写: " . (is_writable(BACKUP_DIR) ? '是' : '否') . "\n";
    echo "</pre>";
    
    $backup_file = BACKUP_DIR . DIRECTORY_SEPARATOR . 'pppabc-' . date('Ymd-His') . '-1.sql';
    
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            throw new Exception("数据库连接失败: " . $conn->connect_error);
        }
        
        // 获取所有表
        $tables = array();
        $result = $conn->query("SHOW TABLES");
        while($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        
        $sqlScript = "";
        foreach ($tables as $table) {
            // 获取表结构
            $result = $conn->query("SHOW CREATE TABLE $table");
            $row = $result->fetch_row();
            $sqlScript .= "\n\n" . $row[1] . ";\n\n";
            
            // 获取表数据
            $result = $conn->query("SELECT * FROM $table");
            $columns = $conn->query("SHOW COLUMNS FROM $table")->fetch_all(MYSQLI_ASSOC);
            $column_names = array_column($columns, 'Field');
            
            while($row = $result->fetch_assoc()) {
                $sqlScript .= "INSERT INTO $table (`" . implode('`,`', $column_names) . "`) VALUES(";
                $values = array();
                foreach($column_names as $col) {
                    $values[] = "'" . $conn->real_escape_string($row[$col] ?? '') . "'";
                }
                $sqlScript .= implode(',', $values) . ");\n";
            }
        }
        
        // 写入备份文件
        if (file_put_contents($backup_file, $sqlScript)) {
            $message = "备份成功: " . basename($backup_file);
        } else {
            throw new Exception("无法写入备份文件");
        }
    } catch (Exception $e) {
        $message = "备份失败: " . $e->getMessage();
    }
}

// 处理还原请求 - 使用PHP实现
if (isset($_POST['restore'])) {
    if (!empty($_POST['existing_backup'])) {
        $backup_file = BACKUP_DIR . DIRECTORY_SEPARATOR . $_POST['existing_backup'];
        if (file_exists($backup_file)) {
            $backup_source = $backup_file;
        }
    }
    
    if (empty($backup_source)) {
        $message = "错误: 请选择有效的备份文件";
    } else {
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
                throw new Exception("数据库连接失败: " . $conn->connect_error);
            }
            
            // 1. 先删除所有表
            $result = $conn->query("SHOW TABLES");
            while($row = $result->fetch_row()) {
                $conn->query("DROP TABLE IF EXISTS " . $row[0]);
            }
            
            // 2. 读取备份文件内容
            $sqlScript = file_get_contents($backup_source);
            if (!$sqlScript) {
                throw new Exception("无法读取备份文件");
            }
            
            // 3. 执行SQL语句
            $conn->multi_query($sqlScript);
            while ($conn->next_result()) {
                // 清除所有结果集
                if ($result = $conn->store_result()) {
                    $result->free();
                }
                if ($conn->errno) {
                    throw new Exception("执行SQL错误: " . $conn->error);
                }
            }
            
            $message = "还原成功";
        } catch (Exception $e) {
            $message = "还原失败: " . $e->getMessage();
        }
    }
}

// 处理删除备份请求
if (isset($_POST['delete_backup'])) {
    if (!empty($_POST['existing_backup'])) {
        $backup_file = BACKUP_DIR . DIRECTORY_SEPARATOR . $_POST['existing_backup'];
        if (file_exists($backup_file)) {
            if (unlink($backup_file)) {
                $message = "删除备份文件成功: " . $_POST['existing_backup'];
            } else {
                $message = "删除备份文件失败";
            }
        } else {
            $message = "备份文件不存在";
        }
    } else {
        $message = "请选择要删除的备份文件";
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统管理面板</title>
    <style>
        body {
            font-family: 'Segoe UI', 'Microsoft YaHei', sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
            padding: 20px;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .header {
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #212529;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }
        
        .btn {
            background: #4361ee;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #3a56d4;
            transform: translateY(-1px);
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        /* 优化后的终端样式 */
        .terminal {
            background: #1e1e1e;
            color: #e0e0e0;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Consolas', 'Courier New', monospace;
            margin-bottom: 15px;
            box-shadow: inset 0 0 10px rgba(0,0,0,0.5);
        }
        
        .terminal-output {
            height: 200px;
            overflow-y: auto;
            margin-bottom: 10px;
            white-space: pre-wrap;
            line-height: 1.4;
            font-size: 13px;
            background: #252526;
            padding: 10px;
            border-radius: 3px;
        }
        
        /* 修改终端输入框样式 */
        .terminal-input {
            width: 95%;
            background: #2d2d2d;
            color: #e0e0e0;
            border: 1px solid #3c3c3c;
            padding: 12px;
            border-radius: 3px;
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 13px;
            min-height: 100px;
            resize: vertical;
            white-space: pre;
            overflow-x: auto;
        }
        
        .terminal-input:focus {
            outline: none;
            border-color: #4361ee;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.25);
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            margin-bottom: 15px;
            transition: border-color 0.15s;
        }
        
        .form-control:focus {
            border-color: #4361ee;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid transparent;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            border-color: #4caf50;
            color: #2e7d32;
        }
        
        .alert-danger {
            background-color: #ffebee;
            border-color: #f44336;
            color: #c62828;
        }
        
        /* 登录表单样式 */
        .login-form {
            max-width: 400px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .login-form h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #4361ee;
        }
        
        .login-form input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .login-form button {
            width: 100%;
            padding: 12px;
        }
        
        /* 响应式调整 */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .header {
                padding: 15px;
            }
            
            .terminal-output {
                height: 150px;
            }
        }
    </style>
</head>
<body>
    <?php if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true): ?>
        <div class="login-form">
            <h2>系统登录</h2>
            <?php if (isset($login_error)): ?>
                <div class="alert alert-danger"><?php echo $login_error; ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="text" name="username" placeholder="用户名" required>
                <input type="password" name="password" placeholder="密码" required>
                <div style="display:flex; margin-bottom:15px;">
                    <input type="text" name="captcha" placeholder="验证码" required style="flex:1; margin-right:10px;">
                    <div style="background:#f0f0f0; padding:10px; border-radius:4px; font-family:monospace; letter-spacing:2px;">
                        <?php echo generateCaptcha(); ?>
                    </div>
                </div>
                <button type="submit" name="login" class="btn">登录</button>
            </form>
        </div>
    <?php else: ?>
        <div class="header">
            <h1>系统管理面板</h1>
            <form method="post">
                <button type="submit" name="logout" class="btn btn-danger" style="padding: 8px 15px;">退出登录</button>
            </form>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert <?php echo (strpos($message, '成功') !== false) ? 'alert-success' : 'alert-danger'; ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        
        <!-- 优化后的命令行终端 -->
        <div class="card">
            <div class="card-title">命令行终端</div>
            <div class="terminal">
                <div class="terminal-output">
                    <?php if (isset($command_result)): ?>
                        <div><span style="color:#4CAF50;">$ <?php echo htmlspecialchars($command, ENT_QUOTES, 'UTF-8'); ?></span></div>
                        <div><?php echo $command_result; ?></div>
                    <?php endif; ?>
                </div>
                <form method="post">
                    <textarea name="command" class="terminal-input" placeholder="输入命令..." rows="5" style="resize: vertical;"></textarea>
                    <button type="submit" class="btn" style="margin-top:10px;">执行</button>
                </form>
            </div>
        </div>
        
        <!-- 数据库备份还原功能 -->
        <div class="card">
            <div class="card-title">数据库备份</div>
            <form method="post">
                <button type="submit" name="backup" class="btn">立即备份</button>
            </form>
        </div>
        
        <div class="card">
            <div class="card-title">数据库还原</div>
            <form method="post">
                <select name="existing_backup" class="form-control" required>
                    <option value="">选择已有备份文件...</option>
                    <?php 
                    if (file_exists(BACKUP_DIR)) {
                        $backup_files = scandir(BACKUP_DIR);
                        usort($backup_files, function($a, $b) {
                            if ($a === '.' || $a === '..') return 1;
                            if ($b === '.' || $b === '..') return -1;
                            return filemtime(BACKUP_DIR . '/' . $b) - filemtime(BACKUP_DIR . '/' . $a);
                        });
                        
                        $latest_file = null;
                        // 备份文件列表中的日期显示
                        foreach ($backup_files as $file): 
                            if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql'):
                                if (!$latest_file) $latest_file = $file;
                        ?>
                                <option value="<?php echo $file; ?>" 
                                    <?php echo (isset($_POST['existing_backup']) && $_POST['existing_backup'] === $file) || 
                                              (!isset($_POST['existing_backup']) && $file === $latest_file) ? 'selected' : ''; ?>>
                                    <?php echo $file; ?> 
                                    (<?php echo date('Y-m-d H:i:s', filemtime(BACKUP_DIR . '/' . $file)); ?>)
                                </option>
                        <?php 
                            endif;
                        endforeach; 
                    }
                    ?>
                </select>
                <button type="submit" name="restore" class="btn">开始还原</button>
                <button type="submit" name="delete_backup" class="btn btn-danger" style="margin-top:10px;">删除备份</button>
            </form>
        </div>
    <?php endif; ?>
</body>
</html>