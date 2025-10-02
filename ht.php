<?php
/*
 * 本文件为修改后台登录地址专用文件，将该文件夹放在网站根目录，修改applicaiton/lys文件夹，改为你想要的文件夹名，
 * 第一次改后台地址时，xxx.com/ht.php运行该文件按需填写即可，
 * 如果改了一次还想再改后台，则需要将操作部分'lys'修改为你上一次修改后的地址
 */
header('content-type:text/html;charset=utf-8');
set_time_limit(0);

define('APP_DEBUG', 0);
define('LYSPHP_PATH', dirname(__FILE__) . DIRECTORY_SEPARATOR);
require LYSPHP_PATH . 'lysphp' . DIRECTORY_SEPARATOR . 'lysphp.php';

lys_base::load_sys_class("application", "", 0);
lys_base::load_sys_class("debug", "", 0);
lys_base::load_sys_class("db_factory", "", 0);
$db = D('admin');

$style = <<<STYLE
<title>修改后台登录地址</title>
<style>
    .formbox {
        width: 520px;
        margin: 60px auto 0;
        border: 1px solid #2196F3;
        padding: 10px;
    }
    .formbox h4 {
        margin-bottom: 20px;
        text-align: center;
        color: #03A9F4;
        border-bottom: 1px solid #dfdfdf;
        padding-bottom: 5px;
        padding-top: 5px;
    }
    .btn {
        display: flex;
    }
    .btn button {
        display: block;
        text-align: center;
        width: 200px;
        background-color: #2196F3;
        color: #fff;
        border: none;
        outline: 0;
        padding: 10px 0;
        font-size: 16px;
        cursor: pointer;
        margin-left: 113px;
    }
    .formbox .li {
        display: flex;
        margin-bottom: 8px;
    }
    .formbox .li input[type="text"] {
        border: 1px solid #383838;
        height: 26px;
        flex-grow: 0.4;
    }
    .formbox .li span {
        width: 113px;
    }
    *, *::after, *::before {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    input[type="text"]:focus {
        outline: none;
        box-shadow: 0px 0px 2px rgba(0,188,212,0.6);
    }
    .ok-res {
        text-align: center;
        padding-top: 50px;
        font-size: 16px;
        color: #F44336;
    }
    .formbox .p1 {
        font-size: 14px;
        margin-bottom: 10px;
        color: #607D8B;
    }
</style>
STYLE;

echo $style;

if (isset($_POST['dosubmit'])) {
    $db = D('admin');
    $backend = isset($_POST['backend']) ? $_POST['backend'] : 'lys';  // 修改后的路径
    $oldbackend = isset($_POST['oldbackend']) ? $_POST['oldbackend'] : 'admin';  // 修改前的路径
    
    if ($backend == '' || $oldbackend == '') {
        exit('原路径和修改路径不能为空！');
    }

    $sql = "UPDATE `lys_menu` SET `m`='{$backend}' WHERE `m` ='{$oldbackend}';";
    $sql = rtrim(trim($sql), ';');
    $sqls = array(0 => $sql);
    
    array_walk($sqls, function($sqls) use(&$db) {
        $db->query($sqls);
    });

    $f_path = LYSPHP_PATH . 'lysphp' . DIRECTORY_SEPARATOR . 'lysphp.php';
    $stra = "define('LYS_ADMIN', 'lys')";//操作部分
    $strb = "define('LYS_ADMIN', '".$backend."')";
    file_put_contents($f_path, str_replace($stra, $strb, file_get_contents($f_path)));
    
    echo '<div class="ok-res">后台路径修改成功！新的后台登录地址为：xxx.com/'.$backend.'</div>
          <div style="text-align:center;margin-top:10px;">请记得删除本文件！</div>';
}
?>

<div class="formbox">
    <h4>星怀蓝梦修改后台登录地址</h4>
    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
        <p class="p1">首先将application/目录下后台模块文件夹修改</p>
        <input type="hidden" value="1" name="dosubmit">
        <div class="li">
            <span>原后台路径</span>
            <input type="text" name="oldbackend">
        </div>
        <div class="li">
            <span>新后台路径</span>
            <input type="text" name="backend">
        </div>
        <div class="li btn">
            <button type="submit">确定</button>
        </div>
    </form>
</div>