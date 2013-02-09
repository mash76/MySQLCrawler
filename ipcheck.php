<?
//PHP設定
ini_set('display_errors', '1');
ini_set('error_reporting', E_ERROR );

error_reporting(E_ALL ^ E_NOTICE);
ini_set( 'display_errors', 1 );//エラーを画面に出す
session_start();

//ip制限 loginチェック
if (!isset($_SESSION['login'])) {	?>
	no login. <a id='login' href='index.php'>login</a>
	<script type="text/javascript"> document.getElementById("login").focus(); </script>
	<?
	exit();
}

//サイト共通
$connStr1= array('server'=>'*******','user'=>'*******',
					'pass'=>'*******','db'=>'*******','charset'=>'utf-8');

//define('URL_BASE','/var/www/html/penguin/');

//MySQLCrawler 初期値
$_SESSION['mysql_conn_str']=$connStr1;
$encode='utf-8';
$safe="";//$safe="true" or "";
$dbLock="*******";  //DB変更不可

//include HTTPheader
include("inc_functions.php");
header("Content-type: text/html; charset=utf-8;");//shift_JIS  utf-8

?>