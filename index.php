<?php
/**
 * index.php 文件单一入口
 *
 * @author           李依朔  
 * @license          http://www.pppabc.com
 * @lastmodify       2018-05-12
 */

 
//调试模式：开发阶段设为开启true，部署阶段设为关闭false。
define('APP_DEBUG', true);

//URL模式: 0=>mca兼容模式，1=>s兼容模式，2=>REWRITE模式，3=>SEO模式，4=>兼容性PATHINFO模式。
define('URL_MODEL', '3');

//lysphp根路径
define('LYSPHP_PATH', dirname(__FILE__).DIRECTORY_SEPARATOR); 

//加载lysphp框架的入口文件      
require(LYSPHP_PATH.'lysphp'.DIRECTORY_SEPARATOR.'lysphp.php'); 

//创建应用
lys_base::creat_app();