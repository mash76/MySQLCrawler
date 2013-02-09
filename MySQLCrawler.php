<?
//ip認証ログインチェック
include("ipcheck.php");
include("MySQL_SQL.php");//大きなSQLセット

//テストparam記入
    //magic_quotes_gpc 動的変更不可

//初期セット履歴(任意)
//圧倒的なキーボード高速操作

//動作条件 200万行,テーブル300,column50 per table,procedure 100,view 20,trigger 20,index 100,event 10 
//次々と変更の入る短期拡張型運用、ソーシャルゲーム、ユーザーの反応をリアルタイムに見ながらやっていく。

$aryEncode=array("SJIS"=>array('mysql'=>'sjis','html'=>'shift_jis','php'=>'SJIS'),
                "UTF-8"=>array('mysql'=>'utf8','html'=>'utf-8','php'=>'UTF-8'),
                "EUC-JP"=>array('mysql'=>'ujis','html'=>'euc-jp','php'=>'EUCJP'));

if ($_REQUEST['mode1']=='logout') {
	unset($_SESSION['mysql_conn_str']);
	unset($_SESSION['mysql_current_db']);
}

//引数受取り
$param_mode1="";
if (isset($_REQUEST['mode1'])) $param_mode1=$_REQUEST['mode1'];
$param_mode2="";
if (isset($_REQUEST['mode2'])) $param_mode2=$_REQUEST['mode2'];
$param_searchtext="";
if ($_REQUEST['searchtext']) $param_searchtext=stripslashMQuote($_REQUEST['searchtext']);

if (isset($_REQUEST['encode'])) $_SESSION['encode']=$_REQUEST['encode'];
if (!isset($_SESSION['encode'])) $_SESSION['encode']='UTF-8';//文字エンコード sjis UTF-8

$http_content_type='Content-Type: text/html;charset='.$_SESSION['encode'];
header($http_content_type);//ヘッダ書き換え

//画面からの接続文字列セット
if ($_REQUEST['conn']) {
	unset($_SESSION['mysql_current_db']);
	$_SESSION['mysql_conn_str']=$_REQUEST['conn'];
}
//履歴session登録：検索条件 選択テーブル 選択DB
if ($param_mode2=='delhistory' or !$_SESSION['mysql_his_searchtext'])     $_SESSION['mysql_his_searchtext']=array();
if ($param_mode2=='delhistory' or !$_SESSION['mysql_his_db'])             $_SESSION['mysql_his_db']=array();
if ($param_mode2=='delhistory' or !$_SESSION['mysql_his_table'])          $_SESSION['mysql_his_table']=array();

//スキーマ指定あれば選択
if ($dbLock) $_SESSION['mysql_current_db']=$dbLock;//ロック値入ってればdb変更不可
elseif ($_REQUEST['TABLE_SCHEMA'] ) $_SESSION['mysql_current_db']=$_REQUEST['TABLE_SCHEMA'];

$_SESSION['mysql_his_db'][$_SESSION['mysql_current_db']]="";//history追加

if (isset($_REQUEST['limit'])) $_SESSION['mysql_limit']=$_REQUEST['limit'];//正常値で0も来るからissetで確認
if (isset($_REQUEST['trim'])) $_SESSION['mysql_trim']=$_REQUEST['trim'];

//初期値
if (!$_SESSION['mysql_current_db']) $_SESSION['mysql_current_db']=$_SESSION['mysql_conn_str']['db'];//DB指定なければデフォルトDBセット 0もあるからissetで確認
if (!isset($_SESSION['mysql_limit'])) $_SESSION['mysql_limit']=200;
if (!isset($_SESSION['mysql_trim'])) $_SESSION['mysql_trim']=100;
//履歴追加 7件以上は消す
if ($param_mode1=='search') $_SESSION['mysql_his_searchtext'][$param_searchtext]="";
if ($param_mode1=='table')     $_SESSION['mysql_his_table'][$_REQUEST['TABLE_NAME']]='';
if (count($_SESSION['mysql_his_searchtext'])>7 ) array_shift($_SESSION['mysql_his_searchtext']);
if (count($_SESSION['mysql_his_table'])>7      ) array_shift($_SESSION['mysql_his_table']);

if ($_REQUEST['sqltext']) 	$param_sqltext=stripslashMQuote($_REQUEST['sqltext']);

//DB接続 接続文字列あれば  DB名違えば終了
if ($_SESSION['mysql_conn_str']){
	$link = @mysql_connect($_SESSION['mysql_conn_str']['server'],$_SESSION['mysql_conn_str']['user'],$_SESSION['mysql_conn_str']['pass']) ; //mysql接続

	if ($link){
		//DB選択、文字コードセット
		sql2asc('use '.$_SESSION['mysql_current_db'],$link,false,false);
		if (mysql_errno()) {
			unset($_SESSION['mysql_conn_str']);
			unset($_SESSION['mysql_current_db']);
		    exit();
		}
		sql2asc('set names '.$aryEncode[$_SESSION['encode']]['mysql'],$link,false,false);
		if ($param_mode1=='tablelistout')  {
			tableListOut($_SESSION['mysql_current_db'],$link,$_REQUEST['outtype'],$_REQUEST['filter'],$_REQUEST['createTableOnly']);
			exit();
		}

		//事前実行insert update delete
		if ($_REQUEST['sql'] and $_REQUEST['onetimekey']==$_SESSION['onetimekey']) {
			sql2asc(stripslashMQuote($_REQUEST['sql']),$link);
		}

		//ダンプ出力系HTML始まる前に
		//CSV TSV出力
		if (preg_match("/(csv|csvquote|tsv|insertout)/",$param_mode2)) {

			if ($param_mode2=='csv' or $param_mode2=='csvquote')    $filename=date("Ymd_His").".csv";
			if ($param_mode2=='tsv')                                $filename=date("Ymd_His").".tsv";
			if ($param_mode2=='insertout')                          $filename=date("Ymd_His").".sql";

			//テキスト形式httpヘッダを出す
			header("Content-Type: text/plain;charset=".$_SESSION['encode']); 
			//header("Content-disposition: attachment; filename=$filename"); 
			sqlDump(explode(";",$param_sqltext),$link,$param_mode2);
			exit();
		}
        
		//viewermode
		if (preg_match("/(viewer)/",$param_mode2)) {
            ?>
            <style>
            body{font-size:small;font-size:small;font-family:arial,helvetica,sans-serif;}
			</style>
			<?
			echo strHTML("MySQLCrawler Viewer Mode ","span bold #555555 large").strGray(date("Y-m-d H:i:s"))."<br/>";
			echo strHTML($_SESSION['mysql_conn_str']['server']." ".strDarkred($_SESSION['mysql_conn_str']['db']),"x-large bold span")."<hr/>";
			sqlDump(explode(";",$param_sqltext),$link,$param_mode2);
			?>
			<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js"></script>
			<script type="text/javascript"> 
				//列を交互に配色 pk列とfk列を色変更
				$('tr[name!="header"]').each( function (ind,obj){ ;
					if (ind % 2==1 ){$(obj).css("background-color","#F0F0F0");}//列を交互配色
				});
			</script> 
			<?
			exit();
		}
	}else{
		//session_destroy();
		echo strRed("DB connect error <br/>");
		unset($_SESSION['mysql_conn_str']);
	}
}
?>
<? echo '<?xml version="1.0" encoding="'.$encode.'" ?>'; ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd"> 
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ja"> 
<head> 
<meta http-equiv="Content-Type" content="text/html; charset=$encode">
<meta name="robots" content="none"> 
<title>MySQL:<?=$_SESSION['mysql_current_db']?></title>
<?
$css="styleMac.css";
if (preg_match("/Windows/i",$_SERVER['HTTP_USER_AGENT'])) $css="style.css";
?>
<link href='<?=$css?>' rel='stylesheet' type='text/css' />
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js"></script> 
<script type="text/javascript" src="js/jqModal.js"></script> 
<link rel="stylesheet" type="text/css" href="js/jqModal.css">
</head>
<body>
<?
//MYsql接続済みでなければDBサーバー選択 
if (!$link) { ?>
    <style>
	  body {font-size:x-large;}
	  td {font-size:x-large;}
	  input {font-size:x-large;}
	</style>
    Login MySQL<hr/>
		  <form name='formServerSel' method='POST' action='?' target='_self' > 
            <table>
                <tr><td>server</td><td><input type='text' name='conn[server]' size=40 value='<?=$_REQUEST['conn']['server']?>'> &nbsp;</td></tr> 
                <tr><td>db    </td><td><input type='text' name='conn[db]'     size=40 value='<?=$_REQUEST['conn']['db']?>'> &nbsp;</td></tr> 
                <tr><td>user  </td><td><input type='text' name='conn[user]'   size=40 value='<?=$_REQUEST['conn']['user']?>'> &nbsp;</td></tr> 
                <tr><td>pass  </td><td><input type='password' name='conn[pass]' size=40 value='<?=$_REQUEST['conn']['pass']?>'> &nbsp;</td></tr> 
            </table> 
            <input type='submit' value='connect'> 
          </form> 
<?
exit();
}




//デバッグ用サーバー情報 memory magicquote os
if ($_REQUEST['mode1']=='serverinfo') {
	echo "Web Server OS<hr/>";
	echo php_uname('s')." &nbsp;".php_uname('r')." &nbsp;".php_uname('v')." &nbsp;".php_uname("m")."<br/><br/>";
	echo "PHP Config<hr/>";
	foreach(ini_get_all() as $key=>$value){
	    if (preg_match("/(memory_limit|magic_quotes_gpc)/",$key)) echo $key."=".$value['local_value']."<br/>";
	}
	echo "<br/>";
}



//ワンタイムキーをセット
$_SESSION['onetimekey']='key'.rand(1,10000);

//キークエリならSQL作る  直指定タイプ or MySQLセッション変数指定
if (isset($_REQUEST['keyselect'])) {
	if ($_REQUEST['mysql_variable']==''){
		$value1="'".$_REQUEST['value']."'";
	}else{
		$param_sqltext="SET @val01 = '".$_REQUEST['value']."' ; \n";
		$value1=" @val01 ";
	}
	
    $sql="select TABLE_NAME from information_schema.columns 
        where table_schema='".$_SESSION['mysql_current_db']."' and COLUMN_NAME='".$_REQUEST['column']."'";
    
    if ($_REQUEST['table_name_filter']) $sql.=" and TABLE_NAME like '%".$_REQUEST['table_name_filter']."%'";
    
    $aryTables=sql2asc($sql, $link,false,false);//TableData
    foreach($aryTables as $key=>$value){
        $sql=$_REQUEST['prefix'].$value['TABLE_NAME']." where ".$_REQUEST['column']."=".$value1 ." ".$_REQUEST['suffix'];
        $param_sqltext.=$sql.";\n";
    }
}

if ($param_mode2=='indent') $param_sqltext=sqlSeikei($param_sqltext);//インデントモード整形

//include 'inc_menu.php';?>
<!--HTML開始 server version user --> 
<?
    //権限のSQL
    $user=array_shift(array_shift(sql2asc("select CURRENT_USER",$link,false)));
    $user_forsql="'".str_replace("@","'@'",$user."'");
    $privSQL=
        "select * from information_schema.USER_PRIVILEGES where grantee = '".mysql_real_escape_string($user_forsql)."'; \n".
        "select * from information_schema.SCHEMA_PRIVILEGES where grantee = '".mysql_real_escape_string($user_forsql)."'; \n".
        "select * from information_schema.TABLE_PRIVILEGES where grantee = '".mysql_real_escape_string($user_forsql)."'; \n".
        "select * from information_schema.COLUMN_PRIVILEGES where grantee = '".mysql_real_escape_string($user_forsql)."';";
?>

<!-- quickMenu-->
<div class="jqmWindow" id="quickMenu">
    Close<hr/>
    <div id="rootMenuText" style='font-size:x-large;color:Silver;font-weight:bold;'></div>
    <input type='button' value="" id='quickMenuButton' />
    <div id="infoText" style="color:crimson"></div>
</div>

<script type="text/javascript">
    var words="";
    var urls = { 
                  SQL:"?mode1=sql&sqltext=",
                  Table:"?searchtext=sd&mode1=search&searchItem=TABLE",
                  Trigger:"?searchtext=&mode1=search&searchItem=TRIGGER",
                  Event:"?searchtext=&mode1=search&searchItem=EVENT",
                  Routines:"?searchtext=&mode1=search&searchItem=ROUTINE",
				  Database:"?mode1=dblist", 
                  Search:"?TABLE_SCHEMA=<?=$_SESSION['mysql_current_db']?>",
                  Variables:"?mode1=sql&sqltext=show+variables+where+variable_name+like%27%25%25%27+%0A%2F%2A+log+buffer+relay+report+slave+slow+time+cache+version+query+ssl+sql+net+init+have+inno+max+isam+char+%2A%2F" , 
                  Status:"?mode1=sql&sqltext=show+status+where+variable_name+like%27%25%25%27+%0A%2F%2A+Aborted+Binlog+Com+Com_Alter+Com_create+Com_delete+Com_drop+Com_show+Com_stmt+Created+Delayed+Handler+Innodb+Innodb_buffer+Innodb_data+Key+Qcache+Threads+Sort+%2A%2F" , 
                  Process:"?mode1=sql&sqltext=show%20processlist" , 
                  Summary:"?mode1=dbsummary",
                  KeySelect:"?mode1=keyselects",
                  Colstat:"task",
                  Describe:"?mode1=tablelistout&outtype=html"};
    $().ready(function() { $('#quickMenu').jqm(); });
    
    //メニュー一覧を色変えて表示
    for (key in urls){
        var re=new RegExp("^("+words+")",'i');
        $("#rootMenuText").html(
            $("#rootMenuText").html() + " " + key.replace(re,"<span style='color:crimson;'>$1</span>")
        );
    }

    //ctrl+mでメニュー
    $("body").keydown( function ( event ){        
        if( event.ctrlKey === true && event.which === 77 ){
                words="";
                $('#quickMenu').jqmShow();
        }
    });
    
    //メニュー内キーダウン 文字がたまってゆく escかdelでclose
    $("#quickMenuButton").keydown( function ( event ){        
         if( event.which === 13 ) {
           re = new RegExp("^"+words,"i");
           for (key in urls){
              if (key.match(re)) location.href=urls[key];
           }
        }
        words+=String.fromCharCode(event.which);
        //words+=event.which;
        
        //メニュー項目を色変えて表示
        $("#rootMenuText").text("");
        var re=new RegExp("^("+words+")",'i');
        for (key in urls){
            $("#rootMenuText").html(
                $("#rootMenuText").html() + " " + key.replace(re,"<span style='color:crimson;'>$1</span>")
            );
        }
        $('#infoText').text(words);
        
        if( event.which === 8 || event.which===27 ){ //esc del
            words="";
            $('#quickMenu').jqmHide();
            $('#infoText').text("");
            $("#quickMenuButton").blur()//フォーカスを元に
        }
    });
</script>

<div style='display:-webkit-box;display:-moz-box;' >
  <div style='width:50%;'>
    <a href="?mode1=dblist" title='Current Server'>
        <span class="strbold" style='color:black'><?=$_SESSION['mysql_conn_str']['server']?></span>
    </a>
    <? $verTime=array_Shift(sql2asc("select version() as ver,now() as now", $link,false)) ?>
	<?=strGray($verTime['ver'])?>
    <a href="?mode1=sql&sqltext=<?=urlencode($privSQL)?>"><?=$user?></a>
   <br/>
    <a href='?searchtext=&mode1=search&searchItem=TABLE&TABLE_SCHEMA=<?=$_SESSION['mysql_current_db'] ?>' title='Current Database' >
        <span class="strbold" style='color:crimson;'><?=$_SESSION['mysql_current_db'] ?></span>
    </a> 
	<? 
	if ($dbLock) echo strRed(strBold("DBLOCK "));
	if ($safe) echo strRed(strBold("SAFE "));
	?>&nbsp;
    <span style='font-size:medium;font-weight:bold;'>
        <a href='?mode1=sql&sqltext=' title='Execute SQL'>SQL</a>
        <a href='?searchtext=&mode1=search&searchItem=TABLE' title='Show Tables'>TABLES</a>
		<a href='?mode1=dbsummary'>Summary</a>
	</span>

  </div><div style='text-align:right;vertical-align:bottom;width:50%;font-size:medium;'>
  		<?=strGray($verTime['now'])?>
        <a href='?mode1=serverinfo' ><?=strBold("ServerInfo")?></a>
		<a href='?mode1=logout' ><?=strBold("logout")?></a>
		<br/>
        <a href='<? echo "?mode1=sql&sqltext=".urlencode("show variables where variable_name like'%%' \n/* log buffer relay report slave slow time cache version query ssl sql net init have inno max isam char */"); ?>'>Variables</a>
        <a href='?mode1=sql&sqltext=<?=urlencode("show status where variable_name like'%%' \n/* Aborted Binlog Com Com_Alter Com_create Com_delete Com_drop Com_show Com_stmt Created Delayed Handler Innodb Innodb_buffer Innodb_data Key Qcache Threads Sort */") ?>'>Status</a>
        
        <a href='?mode1=sql&sqltext=<?=urlencode($privSQL)?>'>Priv</a>
        <a href='?mode1=sql&sqltext=show processlist'>Process</a>
   <br/>
        <a href='?mode1=csvcreate'>CSVUp&Create</a>
        <a href='?mode1=keyselects'>Keyselects</a>
        <a href='?mode1=sql&sqltext=<?=urlencode("select count(1) as COUNT,COLUMN_TYPE \nfrom information_schema.columns \nwhere TABLE_SCHEMA='".$_SESSION['mysql_current_db']."' \ngroup by COLUMN_TYPE") ?>' target='_blank'>ColStat</a>
        <a href='?mode1=sql&sqltext=<?=urlencode("select count(1) as COUNT,COLUMN_TYPE,COLUMN_DEFAULT,IS_NULLABLE,COLUMN_KEY,EXTRA \nfrom information_schema.columns \nwhere TABLE_SCHEMA=database() \ngroup by COLUMN_TYPE,COLUMN_DEFAULT,IS_NULLABLE,COLUMN_KEY,EXTRA") ?>' target='_blank'>ColStat2</a>
        <a href='?mode2=delhistory'>ClearHistory</a>
        <a href='?mode1=tablelistout&outtype=html' target='_blank'>TableList</a>
        (<a href='?mode1=tablelistout&outtype=text' target='_blank'>TSV</a>) 
  </div>
</div>
<?
//DB一覧
if ($param_mode1=='dblist')  {
    
    //DBの数
    $databases=sql2asc("select SCHEMA_NAME,DEFAULT_CHARACTER_SET_NAME as CHARSET from information_schema.SCHEMATA", $link,false,false);
    foreach ($databases as &$row) {
        $row['Action']="<a href='?mode1=sql&sqltext=".urlencode("-- drop database ".$row['SCHEMA_NAME'])."'>#Drop</a>";
        $row['SCHEMA_NAME']="<a href='?searchtext=&mode1=search&searchItem=TABLE&TABLE_SCHEMA=".$row['SCHEMA_NAME']."' >".$row['SCHEMA_NAME']."</a>";
    }
    echo asc2html($databases,"db",false);
    ?>
    <br/>
    <script type="text/javascript">
        $("td[id^=db_SCHEMA_NAME]").css("font-size","large");//idがdb_SCHEMA_NAME〜のobj(=schema_name)の文字を大きく
        $("td[id^=db_SCHEMA_NAME]").css("font-weight","bold");
    </script>
    
    <!-- 飛び出すウィンドウ create Database -->
    <a href="javascript:$('#dialog').jqmShow();" style='font-size:large:font-weight:bold;'>Create</a>
    <div class="jqmWindow" id="dialog">
        <a href="#" class="jqmClose">Close</a><hr/>
        <form name='fCreateDB' >
            <input style="font-size:x-large" type=text name='newTABLE_SCHEMA' value='' size='20' /> 
            <input style="font-size:x-large" type=button value='CreateDatabase' 
            onClick="window.location.href='?mode1=sql&sqltext=<?=urlencode("create database ")?>' + document.fCreateDB.newTABLE_SCHEMA.value; " /> 
        </form >
    </div>
    <script type="text/javascript">
        $().ready(function() { $('#dialog').jqm(); });
    </script>
    <?
    exit();
}
?>
<hr/>

<!-- 検索欄TABLE COLUMN -->
    <form id='iform1' name='nform1' action='' method='get' target='_self'> 
          <input id='searchtext' type='text' name='searchtext' title='Search from Tables,Columns,Comments,ServerVariables,ServerStatus' value="<? echo htmlspecialchars($param_searchtext,ENT_QUOTES, $aryEncode[$_SESSION['encode']]['php']);?>" />
          <input id='sdfs' type='hidden' name='mode1' value='search' /> 
          <input id='searchItem' type='hidden' name='searchItem' value='TABLE,COLUMN,ROUTINE,TRIGGER,EVENT,INDEX,VARIABLES,STATUS' /> 
		  <input id='alschema' type=hidden name="allschema" value="false" /> 
          <input id='iinput3_submit' type='submit' name='ninput3_submit' value='Search' title='Search from Tables,Columns,Comments,ServerVariables,ServerStatus' /> 
          <a href="javascript:document.nform1.allschema.value='true';document.nform1.submit();">AllSchema</a> 

          ...<? if ($_SESSION['mysql_his_searchtext']) foreach ($_SESSION['mysql_his_searchtext'] as $key=>$value) echo "<a href='javascript:document.nform1.searchtext.value=\"".$key."\";nform1.submit();'>".$key."</a> &nbsp;";?>
    </form>
	...<? 
    if (is_array($_SESSION['mysql_his_table'])) {
    	foreach ($_SESSION['mysql_his_table'] as $key=>$value) echo "<a href='MySQL_Table.php?TABLE_SCHEMA=".$_SESSION['mysql_current_db'].
                                                                "&mode1=table&mode2=data&TABLE_NAME=".$key."'>".$key."</a> &nbsp;";
                                                             
		echo "<br/>";
	}

if ($param_mode1=='logshow')    echo asc2html(sql2asc("select * from mysql.general_log ;", $link,true));
if ($param_mode1=='dbsummary') {
    
    //count of TABLE,COLUMN,INDEX,TRIGGER,ROUTINE,EVENT
    echo "<br/><strong>Database Objects</strong> ";
	echo asc2html(sql2asc("
            select 
            (select count(1) as TABLES from information_schema.TABLES where TABLE_SCHEMA=database()) as TABLES,
            (select count(1) as COLUMNS from information_schema.COLUMNS where TABLE_SCHEMA=database()) as COLUMNS,
            (select count(1) as INDEXES from information_schema.STATISTICS where TABLE_SCHEMA=database()) as INDEXES,
            (select count(1) as ROUTINES from information_schema.ROUTINES where ROUTINE_SCHEMA=database()) as ROUTINES,
            (select count(1) as TRIGGERS from information_schema.TRIGGERS where TRIGGER_SCHEMA=database()) as TRIGGERS,
            (select count(1) as EVENTS from information_schema.EVENTS where EVENT_SCHEMA=database()) as EVENTS",$link));

    echo "<br/><strong>TABLE / INDEX Storage Size</strong> ";
	$dataSize=sql2asc("select sum(DATA_LENGTH) as DATA_LENGTH_SUM,sum(INDEX_LENGTH) as INDEX_LENGTH_SUM from information_schema.TABLES where TABLE_SCHEMA=database()", $link,true);
	$dataSize[0]['DATA_LENGTH_SUM'].=" (".round($dataSize[0]['DATA_LENGTH_SUM']/1024/1024,1)."MB)";
	$dataSize[0]['INDEX_LENGTH_SUM'].=" (".round($dataSize[0]['INDEX_LENGTH_SUM']/1024/1024,1)."MB)";
	echo asc2html($dataSize);


    echo "<br/><strong>TABLE Storage Engines</strong> (null = view) ";
    echo asc2html(sql2asc("select ENGINE,count(1) as count from information_schema.TABLES 
	                                    where TABLE_SCHEMA=database() group by ENGINE", $link,true));


    echo "<br/><strong>COLUMN Rowtypes </strong> (null = view) ";
    echo asc2html(sql2asc("select count(1) as COUNT,COLUMN_TYPE from information_schema.columns 
										where TABLE_SCHEMA=database() group by COLUMN_TYPE ", $link,true));

    echo "<br/><strong>TABLE Recent Edit</strong> ";
    echo asc2html(sql2asc("select TABLE_NAME,CREATE_TIME,UPDATE_TIME from information_schema.TABLES where TABLE_SCHEMA=database() ".
                        "and ( CREATE_TIME > date_add(now(), interval -2 day) or UPDATE_TIME > date_add(now(), interval -2 day)) order by UPDATE_TIME desc", $link,true));

    echo "<br/><strong>ROUTINE Recent Edit</strong> ";
    echo asc2html(sql2asc("select ROUTINE_NAME,ROUTINE_TYPE,DEFINER,CREATED,LAST_ALTERED,ROUTINE_DEFINITION from information_schema.ROUTINES ".
                                " where ROUTINE_SCHEMA=database() ".
                                "and ( CREATED > date_add(now(), interval -2 day) or LAST_ALTERED > date_add(now(), interval -2 day)) order by LAST_ALTERED desc", $link,true));

    echo "<br/><strong>TRIGGER Recent Edit</strong> ";
    echo asc2html(sql2asc("select TRIGGER_NAME,EVENT_MANIPULATION,EVENT_OBJECT_TABLE,ACTION_TIMING,ACTION_STATEMENT from information_schema.TRIGGERS ".
                                " where TRIGGER_SCHEMA=database() ".
                                "and CREATED > date_add(now(), interval -2 day) ", $link,true));

    echo asc2html(sql2asc("show variables like '%event%'",$link));
	
	
	
	echo "<br/><strong>EVENT Recent</strong> ";
    echo asc2html(sql2asc("select EVENT_NAME,EVENT_TYPE,EXECUTE_AT,DEFINER,CREATED,LAST_ALTERED,EVENT_DEFINITION from information_schema.EVENTS ".
                                " where EVENT_SCHEMA=database() ".
                                "and ( CREATED > date_add(now(), interval -2 day) or LAST_ALTERED > date_add(now(), interval -2 day)) order by LAST_ALTERED desc", $link,true));

	//Onetime Event
    echo "<br/><strong>EVENT ONETIME</strong> ";
    $eventOnetime=sql2asc("select EVENT_NAME,EVENT_COMMENT,EVENT_TYPE,EXECUTE_AT,STATUS,EVENT_DEFINITION from information_schema.EVENTS ".
                                " where EVENT_SCHEMA=database() and EVENT_TYPE='ONE TIME' ", $link,true);
	//del enable disable
	foreach ($eventOnetime as &$row) {
		$row['action']="<a href='?mode1=dbsummary&onetimekey=".$_SESSION['onetimekey']."&sql=".urlencode("alter event ".$row['EVENT_NAME']." enable")."'>enable</a> ";
		$row['action'].="<a href='?mode1=dbsummary&onetimekey=".$_SESSION['onetimekey']."&sql=".urlencode("alter event ".$row['EVENT_NAME']." disable ")."'>disable</a> ";
		$row['action'].="<a href='?mode1=dbsummary&onetimekey=".$_SESSION['onetimekey']."&sql=".urlencode("drop event ".$row['EVENT_NAME'])."'>del</a> ";
		if (strtotime($row['EXECUTE_AT']) < time()) $row['EXECUTE_AT']=strRed($row['EXECUTE_AT']);//過去イベント、DISABLEは赤
		if ($row['STATUS']=="DISABLED") $row['STATUS']=strRed($row['STATUS']);
	}
	echo asc2html($eventOnetime,false,false,false);
	
	//Recurring Event
    echo "<br/><strong>EVENT RECURRING</strong> ";
    $eventRecurring=sql2asc("select EVENT_NAME,EVENT_COMMENT,EVENT_TYPE,STARTS,ENDS,INTERVAL_VALUE,INTERVAL_FIELD,STATUS,EVENT_DEFINITION from information_schema.EVENTS ".
                                " where EVENT_SCHEMA=database() and EVENT_TYPE='RECURRING' ".
                                " and ( CREATED > date_add(now(), interval -2 day) or LAST_ALTERED > date_add(now(), interval -2 day)) order by LAST_ALTERED desc", $link,true);
	foreach ($eventRecurring as &$row) {
		$row['action']="<a href='?mode1=dbsummary&onetimekey=".$_SESSION['onetimekey']."&sql=".urlencode("alter event ".$row['EVENT_NAME']." enable")."'>enable</a> ";
		$row['action'].="<a href='?mode1=dbsummary&onetimekey=".$_SESSION['onetimekey']."&sql=".urlencode("alter event ".$row['EVENT_NAME']." disable ")."'>disable</a> ";
		$row['action'].="<a href='?mode1=dbsummary&onetimekey=".$_SESSION['onetimekey']."&sql=".urlencode("drop event ".$row['EVENT_NAME'])."'>del</a> ";
		if (strtotime($row['ENDS']) < time()) $row['ENDS']=strRed($row['ENDS']);//過去イベント、DISABLEは赤
		if ($row['STATUS']=="DISABLED") $row['STATUS']=strRed($row['STATUS']);
	}
	echo asc2html($eventRecurring,false,false,false);

    echo "<br/><strong>MASTER SLAVE</strong> ";
    echo asc2html(sql2asc("show variables like '%bin%'",$link));
	echo asc2html(sql2asc("show variables like '%master%'",$link));
    echo asc2html(sql2asc("show status like '%master%'",$link));
    echo asc2html(sql2asc("show variables like '%slave%'",$link));
    echo asc2html(sql2asc("show status like '%slave%'",$link));
    echo asc2html(sql2asc("show slave status",$link));
}

//SQL実行
if ($param_mode1=='sql') {
        //SQL行数取得
        $rowsize=count(explode("\n",$param_sqltext));
        if ($rowsize<6) $rowsize=6;
        ?> 
        <table width="100%"><tr><td nowrap valign=top width=70%> 
            <form id='formsql' name='formsql' action='' method='post' target='_self'>
              <input type='hidden' name='issqlblank' id='issqlblank' value='' /> 
              <textarea rows="<? echo $rowsize;?>" cols="100" id="sqltext" name="sqltext"><? echo $param_sqltext;?></textarea> 
            <br/> 
              <input type='hidden' name='mode1' value='sql' /> 
              <input type='hidden' name='mode2' value='execute' /> 
              <input type='hidden' name='mode2value' value='' /> 
              <input type='hidden' name='ignoreDelimiter' value='false' />
              <input type='hidden' name='limit' value="<?=$_SESSION['mysql_limit']?>" /> 
              <input type='hidden' name='trim' value="<?=$_SESSION['mysql_trim']?>" /> 
              <input type='submit' name='submit2' value='Execute' onclick='document.formsql.mode2.value="execute";' />
              <input type='submit' name='submit3' value='ignoreDelimiter' onclick='document.formsql.ignoreDelimiter.value="true";' />

    <a href="javascript:$('#sqlTemplate').jqmShow();" style='font-size:large:font-weight:bold;'>SQLTemplate</a>
	<div class="jqmWindow" id="sqlTemplate">
        SQLTemplate<hr/>
        <? foreach($sqls as $key=>$val){
            echo preg_replace("/(.*?) (.*)/","<strong>$1</strong> <span style='color:gray'>$2</span>",$key);
            echo " <a href='?mode1=sql&sqltext=".urlencode($val)."'>link</a>";
            echo "<br/>";
        }?>
    </div>
    <script type="text/javascript">
        $().ready(function() { $('#sqlTemplate').jqm(); });
    </script>
    
            <script type="text/javascript">
                //入力例
                if ($("#sqltext").val()=="") {
                    $("#issqlblank").val('true');
                    $("#sqltext").val("select now(); \nselect version(); \nshow variables where variable_name like '%log%'; \nshow status where variable_name like '%cache%';");
                    $("#sqltext").css('color','#999999');
                }
                //フォーカス入ったら空白に
                $("#sqltext").click(function(event){
                    if ($("#issqlblank").val()=='true'){
                        $("#sqltext").val('');
                        $("#sqltext").css('color','#000000');
                        $("#issqlblank").val('false');
                    }
                });
                //hidden書き換えfunc
                formLimit=function(limitVal){document.formsql.limit.value=limitVal; document.formsql.submit();};
                formTrim =function(trimVal) {document.formsql.trim.value=trimVal;   document.formsql.submit();};
                formMode2=function(trimVal) {document.formsql.mode2.value=trimVal;  document.formsql.submit();};
            </script>
             <br/>
             limit <?=$_SESSION['mysql_limit']?>&nbsp;
             <a href='javascript:formLimit(100);'>100</a>
             <a href='javascript:formLimit(200);'>200</a>
              <a href='javascript:formLimit(0);'>ALL</a>

              &nbsp;
             trim <?=$_SESSION['mysql_trim']?>&nbsp;
                <a href='javascript:formTrim(10);'>10</a>
                <a href='javascript:formTrim(100);'>100</a>
                <a href='javascript:formTrim(200);'>200</a>
                 <a href='javascript:formTrim(0);'>ALL</a>
            <br/>
                <a href='javascript:formMode2("explain");'>Explain</a>&nbsp; 
                <a href='javascript:formMode2("profile");'>Profile</a>&nbsp;
                <a href='javascript:document.formsql.target="_self";formMode2("indent");'>Indent</a>&nbsp;
                <a href='javascript:formMode2("tabcontain");'>TabCRLFin?</a>&nbsp;
                <a href='javascript:formMode2("hexdump");'>HexDump</a>&nbsp;
                <a href='javascript:document.formsql.target="_blank";formMode2("csv");'>CSV</a>&nbsp;
                <a href='javascript:document.formsql.target="_blank";formMode2("csvquote");'>"CSV"</a>&nbsp;
                <a href='javascript:document.formsql.target="_blank";formMode2("tsv");'>TSV</a>&nbsp;
				<a href="?mode=sql&mode2=viewer&sqltext=<?=urlencode($param_sqltext)?>">Viewer</a>		
			  </form> 
        </td><td valign=top width=30%> 
        </td></tr></table>
        <br/>
    <? 
    //ignoreDelimiterでなければSQL分解
    if ($_REQUEST['ignoreDelimiter']!='true') $param_sqltext=explode(";",$param_sqltext);

    sqlDump($param_sqltext,$link,$param_mode2);
}

//検索
if ($param_mode1=='search' or !$param_mode1){

	//検索対象
	foreach (explode(",",$_REQUEST['searchItem']) as $value) if ($value) $searchItems[$value]="true";
    $aryKeywords=trimArray(preg_split('/\s/',mysqlEsacpeMQuote($param_searchtext)));
    $sqlWhereSchema=" where TABLE_SCHEMA=database() ";
    if ($_REQUEST['allschema']=="true") $sqlWhereSchema=" where 1=1 ";

    //TABLE
	if ($searchItems['TABLE'] or !$searchItems){
		echo "<br/><strong>TABLE</strong> ";
		if ($_REQUEST['allschema']=="true") echo " AllSchema";
		$sqlWhere="";
		$sqlTABLE_SCHEMA= " 1=1 ";
		if ($_REQUEST['allschema']!='true') $sqlTABLE_SCHEMA= " table_schema=database() ";
		if ($aryKeywords){
			foreach ($aryKeywords as $value) {$sqlWhere.=" and (table_name like '%".$value."%' or TABLE_COMMENT like '%".$value."%' or ENGINE like '%".$value."%' 
													or TABLE_COLLATION like '%".$value."%' ) ";}
		}
		$sqlTables="select TABLE_NAME,TABLE_COMMENT,CREATE_OPTIONS,TABLE_TYPE,ENGINE,DATA_LENGTH,round(DATA_LENGTH/1024/1024,1) as MB ,TABLE_SCHEMA,
                     CREATE_TIME,UPDATE_TIME,'' as ACTION from information_schema.tables as a where ".$sqlTABLE_SCHEMA.$sqlWhere;
    	$tables=sql2asc($sqlTables, $link,true,false);

        //mysqldump文字列
        $mysqldump="mysqldump -h ".$_SESSION['mysql_conn_str']['server']." ".$_SESSION['mysql_current_db']." ";
        foreach ($tables as $row) $mysqldump.=" ".$row['TABLE_NAME'];

        //Drop,ワードを色付け
        foreach ($tables as &$row){
            $tableName=$row['TABLE_NAME'];
    		$tableType=$row['TABLE_TYPE'];
    		$row['TABLE_COMMENT']=htmlentities($row['TABLE_COMMENT'],ENT_QUOTES,$encode);
    		foreach ($row as &$val) {
                foreach ($aryKeywords as $value) $val=markRed($val,$value);
            } 
            if ($tableType=='BASE TABLE') $row['ACTION'].="<a href='?mode1=sql&sqltext=".urlencode('-- drop table '.$tableName)."' target='_blank'>#Drop</a>";
            if ($tableType=='VIEW') $row['ACTION'].="<a href='?mode1=sql&sqltext=".urlencode('-- drop view '.$tableName)."' target='_blank'>#Drop</a>";
    		$row['TABLE_NAME']="<a href='MySQL_Table.php?TABLE_SCHEMA=".$_SESSION['mysql_current_db']."&TABLE_NAME=".$tableName."'>".$row['TABLE_NAME']."</a>";
        }
        echo asc2html($tables,"TABLES",false) ;
        
        echo "絞込み結果を<br/>";
        echo "<br/><strong>バックアップ用MySQLDump</strong><br/> ".strGray($mysqldump);
        echo "<br/><a href='?mode1=tablelistout&outtype=html&filter=".$_REQUEST['searchtext']."&createTableOnly=true'>CreateTables</a><br/> ";

        if ($tables) echo "<br/>";

?>
    <br/>
    <!-- createTable form -->
    <form name='formCT' method=post>
        name<input type='text' name='TABLE_NAME' size='30' /> <br/>
        engine<input type='text' name='engine' value='InnoDB' /> 
		<?
		$engines=sql2asc("select * from information_schema.engines",$link,false,false);
		foreach($engines as $row) echo '<a href="javascript:formCT.engine.value=\''.$row['ENGINE'].'\'">'.$row['ENGINE'].'</a> ';
		?>
		<input type='hidden' name='partition' value='' /> <br/>
		<input type='hidden' name='mode1' value='sql' /> 
		<input type='hidden' name='mode2' value='createtable' /> 
		<input type='hidden' name='onetimekey' value='<?=$_SESSION['onetimekey']?>' /> 
		<input type='hidden' name='sqltext' />
        <input type='submit' value='createTable'  
            onClick=" 
            document.formCT.sqltext.value='CREATE TABLE `'+document.formCT.TABLE_NAME.value+
            '` \n ( `id` int(11) NOT NULL AUTO_INCREMENT,  \n PRIMARY KEY (`id`)) \n'+
            'ENGINE='+formCT.engine.value+' DEFAULT CHARSET=utf8';"/>
    </form>
<?
	}
	
    //COLUMNS
	if ($searchItems['COLUMN']){
		echo "<strong>COLUMNS</strong> ";
		if ($_REQUEST['allschema']=="true") echo " AllSchema";
	
		$sqlWhere2="";
		foreach ($aryKeywords as $value) {$sqlWhere2.=" and ( COLUMN_NAME like '%".$value."%' or COLUMN_COMMENT like '%".$value."%' or COLUMN_TYPE like '%".$value."%' 
													or COLUMN_KEY like '%".$value."%' or EXTRA like '%".$value."%' ) ";}
		$strTABLE_SCHEMA= " 1=1 ";
		if ($_REQUEST['allschema']!="true")  $strTABLE_SCHEMA= " table_schema=database() ";
		$columns=sql2asc("select COLUMN_NAME,COLUMN_COMMENT,TABLE_NAME,TABLE_SCHEMA,IS_NULLABLE,COLLATION_NAME,COLUMN_TYPE,COLUMN_KEY,EXTRA from information_schema.columns 
                          where ".$strTABLE_SCHEMA.$sqlWhere2, $link,true,false);

        //ワードを色付け
        foreach ($columns as &$row){
			$table_name=$row['TABLE_NAME'];
            foreach ($row as &$val) {
                foreach ($aryKeywords as $value) $val=markRed($val,$value);
            }
            $row['TABLE_NAME']="<a href='MySQL_Table.php?TABLE_SCHEMA=".$_SESSION['mysql_current_db']."&TABLE_NAME=".$table_name."'>".$row['TABLE_NAME']."</a>";
        }
        echo asc2html($columns,"COLUMNS",false,false);
        if ($columns) echo "<br/>";
	}

    //INDEX
	if ($searchItems['INDEX']){
        echo "<strong>INDEX</strong> ";
        foreach ($aryKeywords as $value) $sqlWhereInd.="and ( INDEX_NAME like '%".$value."%' or INDEX_TYPE like '%".$value."%' or COLUMN_NAME like '%".$value."%' or COMMENT like '%".$value."%' ) ";
    	$strTABLE_SCHEMA= " 1=1 ";
    	if ($_REQUEST['allschema']!="true")  $strTABLE_SCHEMA= " table_schema=database() ";

    	$aryIndes=sql2asc("SELECT INDEX_NAME,TABLE_NAME,COLUMN_NAME,INDEX_TYPE,COMMENT,SEQ_IN_INDEX,NON_UNIQUE,CARDINALITY,COLLATION,SUB_PART,PACKED,NULLABLE FROM INFORMATION_SCHEMA.STATISTICS WHERE ".$strTABLE_SCHEMA.$sqlWhereInd,$link,true,false);
        foreach ($aryKeywords as $value) $aryIndes=assocMarkRed($aryIndes,$value);
        echo asc2html($aryIndes,"index",false);
        if ($aryIndes) echo "<br/>";

        //view,procedure,trigger
        echo "<strong>VIEWS SELECT STATEMENT</strong> ";
        $sqlWhereView="";
        foreach ($aryKeywords as $value) $sqlWhereView.=" and (TABLE_NAME like '%".$value."%' or VIEW_DEFINITION like '%".$value."%' ) ";
        $assocView=sql2asc("select TABLE_NAME,TABLE_SCHEMA,VIEW_DEFINITION,CHECK_OPTION,IS_UPDATABLE,DEFINER,SECURITY_TYPE,CHARACTER_SET_CLIENT,COLLATION_CONNECTION from information_schema.VIEWS ".$sqlWhereSchema.$sqlWhereView,$link,true,false);
        foreach ($aryKeywords as $value) $assocView=assocMarkRed($assocView,$value);
        echo asc2html($assocView,"VIEWS",false);//TableData
        if ($assocView) echo "<br/>";
    }
    
	if ($searchItems['ROUTINE']){
        echo "<strong>PROCEDURE,FUNCTION</strong> ";
        $sqlWhereProc="";
    	$ROUTINE_NAME=$row['ROUTINE_NAME'];

    	$strROUTINE_SCHEMA= " 1=1 ";
    	if ($_REQUEST['allschema']!="true")  $strROUTINE_SCHEMA= " ROUTINE_SCHEMA=database() ";

    	foreach ($aryKeywords as $value) $sqlWhereProc.=" and  (ROUTINE_NAME like '%".$value."%' or ROUTINE_DEFINITION like '%".$value."%' ) ";
        $assocProcedure=sql2asc("select * from information_schema.ROUTINES where ".$strROUTINE_SCHEMA.$sqlWhereProc, $link);
    	foreach ($assocProcedure as &$row) {
    		foreach ($row as &$colval){
    			$ROUTINE_NAME=$row['ROUTINE_NAME'];
    			foreach ($aryKeywords as $value) {
    				$colval=MarkRed($colval,$value);
    			}
    		}
    		if ($row['ROUTINE_TYPE']=='FUNCTION') $row['ACTION']="<a href='?mode1=sql&sqltext=".urlencode('-- DROP PROCEDURE `'.$ROUTINE_NAME."`")."' target='_blank'>#DropProc</a>";
    		if ($row['ROUTINE_TYPE']=='PROCEDURE')  $row['ACTION']="<a href='?mode1=sql&sqltext=".urlencode('-- DROP FUNCTION `'.$ROUTINE_NAME."`")."' target='_blank'>#DropFunc</a>";
    		//*task functionも削除
    	}
        echo asc2html($assocProcedure,"ROUTINES",false);//TableData
        if ($assocProcedure) echo "<br/>";
    }
	if ($searchItems['TRIGGER']){
        echo "<strong>TRIGGER</strong> ";
    	$strTRIGGER_SCHEMA= " 1=1 ";
    	if ($_REQUEST['allschema']!="true")  $strTRIGGER_SCHEMA= " TRIGGER_SCHEMA=database() ";
		$sqlWhereTrigger="";
        foreach ($aryKeywords as $value) $sqlWhereTrigger.=" and (TRIGGER_NAME like '%".$value."%' or ACTION_STATEMENT like '%".$value."%' or EVENT_OBJECT_TABLE like '%".$value."%' ) ";
        $assocTrigger=sql2asc("select TRIGGER_NAME,EVENT_OBJECT_TABLE,ACTION_TIMING,EVENT_MANIPULATION,ACTION_STATEMENT,ACTION_ORIENTATION,DEFINER,TRIGGER_SCHEMA from information_schema.TRIGGERS where ".$strTRIGGER_SCHEMA.$sqlWhereTrigger, $link);
        foreach ($aryKeywords as $value) $assocTrigger=assocMarkRed($assocTrigger,$value);
        echo asc2html($assocTrigger,"TRIGGER",false);//TableData
    }
	if ($searchItems['EVENT']){
        if ($assocTrigger) echo "<br/>";
        echo "<strong>EVENT</strong> ";
    	$strEVENT_SCHEMA= " 1=1 ";
    	if ($_REQUEST['allschema']!="true")  $strEVENT_SCHEMA= " EVENT_SCHEMA=database() ";
		$sqlWhereEvent="";
        foreach ($aryKeywords as $value) $sqlWhereEvent.=" and (EVENT_NAME like '%".$value."%' or EVENT_DEFINITION like '%".$value."%' ) ";
        $assocTrigger=sql2asc("select EVENT_NAME,EXECUTE_AT,EVENT_TYPE,STATUS,EVENT_DEFINITION,DEFINER,EVENT_SCHEMA,CREATED,LAST_ALTERED from information_schema.EVENTS where ".$strEVENT_SCHEMA.$sqlWhereEvent, $link);
        foreach ($aryKeywords as $value) $assocTrigger=assocMarkRed($assocTrigger,$value);
        echo asc2html($assocTrigger,"EVENT",false);//TableData
        if ($assocTrigger) echo "<br/>";
    }

    //variable status
	if ($aryKeywords){
	    foreach ($aryKeywords as $value) $sqlWhereVar[]=" ( variable_name like '%".$value."%' or value like '%".$value."%' ) ";
	    $sqlWhereVar=" and ".join(" and ",$sqlWhereVar);//最後のorをとる
	}

	if ($searchItems['VARIABLES']){
        echo "<strong>VARIABLES</strong> ";
        $assocVar=sql2asc("show variables where 1=1 ".$sqlWhereVar, $link);
        foreach ($aryKeywords as $value) $assocVar=assocMarkRed($assocVar,$value);
        echo asc2html($assocVar,"VARIABLE",false);
        if ($assocVar) echo "<br/>";
    }
	if ($searchItems['STATUS']){
        echo "<strong>STATUS</strong> ";
        $assocSta=sql2asc("show status where 1=1 ".$sqlWhereVar, $link);
        foreach ($aryKeywords as $value) $assocSta=assocMarkRed($assocSta,$value);
        echo asc2html($assocSta,"STATUS",false);
        if ($assocSta) echo "<br/>";
    }
}

if ($param_mode1=='csvcreateup') {

	$csvlines=explode("\n",$_REQUEST['csv']);
	$line1=$csvlines[0];
	
	//列に分解
	if ($_REQUEST['updtype']=='csv') $separater=",";
	if ($_REQUEST['updtype']=='tsv') $separater="\t";
	$cols=explode($separater,$line1);
	$csvtable="csvup_table_".date("YmdHis");
	$create_table="CREATE TABLE `".$csvtable."` (";
	foreach ($cols as $key=>$col) {
		$colname[$key]="`column_".($key+1)."`";
		$colDefs[$key]=$colname[$key]." varchar(200) DEFAULT NULL ";
	}
	
	//create table 生成 
	if ($_REQUEST['createAI']){
		$create_table.=' `column_ai` integer(11) NOT NULL AUTO_INCREMENT , '.implode(",",$colDefs).
						" , PRIMARY KEY (`column_ai`)) ENGINE=MyIsam DEFAULT CHARSET=utf8 COMMENT='comment01' ";
	}else{
		$create_table.=implode(",",$colDefs)." , PRIMARY KEY (`column_1`)) ENGINE=MyIsam DEFAULT CHARSET=utf8 COMMENT='comment01' ";
	}
	sql2asc($create_table,$link);

	//insert
	foreach ($csvlines as $line){
		if (!$line) continue;
		$colvals=explode($separater,$line);
		$insVal=array();
		foreach ($colvals as $val) $insVal[]="'".$val."'";
		$insert="insert into `".$csvtable."` (".implode(",",$colname).") values(".implode(",",$insVal).");";
		sql2asc($insert,$link);
	}

	//integer化
	//foreach ($cols as $key=>$col) sql2asc("alter table `".$csvtable."` modify `column_".($key+1)."` integer DEFAULT NULL ",$link); //動いた
}


if ($param_mode1=='csvcreate') {
	echo "<br/>";
	echo "<form method='post'>";
	echo "<input type='hidden' name='mode1' value='csvcreateup' >";
	echo "CSV TSV Upload & CREATE TABLE<br/><textarea name='csv' cols=80 rows=50 ></textarea><br/>";
	echo "<input type='checkbox' name='createAI' value='1' />add AutoIncrement column<br/>";
	echo "<input type='submit' name='updtype' value='csv'/>";
	echo "<input type='submit' name='updtype' value='tsv'/>";
	echo "</form>";

}

//列名でテーブル横断絞込み
if ($param_mode1=='keyselects') {
        $arySuffix=array(' order by updated_at desc',' and created_at > date_add(now(), interval -5 minute)');
        foreach ($arySuffix as $key=>$value)
            {$htmlSuffix.="<a href='javascript:void(0);' onclick='document.formkey.suffix.value=\"$value\"'>$value</a><br/>";}
 
        $aryPrefix=array(' select * from ','-- delete from ');
        foreach ($aryPrefix as $key=>$value)
            {$htmlPrefix.="<a href='javascript:void(0);' onclick='document.formkey.prefix.value=\"$value\"'>$value</a><br/>";}
        ?>
        <form id='formkey' name='formkey' action='' method='get' target='_self'>
			<input type='checkbox' name='mysql_variable' value="1" />use MySQLVariable	<?=strGray("set @val01=1; se;lect * from table01 where col_id=@val");?>
            <br/>
            tablename filter<input type='text' name='table_name_filter' value="" />
            
            <table><tr><td valign=top> 

                  prefix<br/><input type='text' size='30' id='prefix' name='prefix' value='select * from ' /><br/><?=$htmlPrefix ?><br/>
             </td><td valign=top> 
					<br/>
					(TABLES) WHERE 			
             </td><td valign=top> 
			 		col<br/>
				  <input type='text' id='column' name='column' value='' /> =
				  <br/><br/>
				  <?
				  $sql="select * from (
        			select COLUMN_NAME,count(1) as count 
					from information_schema.columns where table_schema='".$_SESSION['mysql_current_db']."' 
        			group by COLUMN_NAME order by count desc ) as A where count >1";
    $KeyList=sql2asc($sql, $link,false,false);
    foreach ($KeyList as $key=>&$row) $row['COLUMN_NAME']="<a href='javascript:document.formkey.column.value=\"".$row['COLUMN_NAME']."\";$(\"#value\").focus();'>".$row['COLUMN_NAME']."</a>"; 

    echo asc2html($KeyList,"KEYSELECT",false);//TableData
				  
				  ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
             </td><td valign=top> 
			 		value<br/>
				  <input type='text' id='value' name='value' value='' /> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			 </td><td valign=top> 
                  suffix<br/><input type='text' size='60' id='suffix' name='suffix' value='' /><br/><?=$htmlSuffix ?><br/>
                  <input type='hidden' id='mode1' name='mode1' value='sql' /> 
                  <input type='hidden' id='keyselect' name='keyselect' value='true' /> 
            </td></tr></table> 
            <input type='submit' name='submit3' value='KeyQuery' /> 
         </form>
         <br/>
        <?
}
mysql_close($link) or die("切断失敗");//db切断
?>
<script type="text/javascript"> 
    //列を交互に配色 pk列とfk列を色変更
    $('tr[name!="header"]').each( function (ind,obj){ ;
        if (ind % 2==1 ){$(obj).css("background-color","#F0F0F0");}//列を交互配色
    });
</script> 
<hr/>
<!-- フッター --> 
<a href='<?="?mode1=sql&mode2=author&sqltext=".urlencode("SHOW AUTHORS ;\nSHOW CONTRIBUTORS/* after ver5.1.3 */ ;"); ?>'>MySQLAuthors</a>
    <br/><br/> 
    <?
    echo '$_REQUEST <pre>';var_dump($_REQUEST);echo '</pre>';
    echo "magic_quote=".get_magic_quotes_gpc()." (0=off 1=on) runtimeval=".get_magic_quotes_runtime()."<br/>";
    echo "Log<br/>".asc2html($GLOBALS['LOG'],'LOG');
    ?>
</body> 
</html> 

<? //functions
function explodeSchWord($in_word){
    $keywords=preg_split('/\s/',$in_word);
    foreach ($keywords as $key=>$value) {if ($value=="") unset($keywords[$key]);}
    if (count($keywords)==0) $keywords=array("");
    return $keywords;
}


//sqlexplode
function sqlexplode($in_str,$arySeparaters){
    //降順にソート
    krsort($arySeparaters);
    foreach ($arySeparaters as $keyPosi=>$eventVal){
        //echo "$keyPosi - $eventVal"."<br/>";
        if ($eventVal=='event sql_separater'){
            $return[]=mb_substr($in_str,$keyPosi+1,mb_strlen($in_str)-$keyPosi-1);
            $in_str=mb_substr($in_str,0,$keyPosi+1);
        }
    }
    $return[]=$in_str;
    //セパレータがa文字目なら、をはさんでa+1文字目から最後までと(0文字目からa文字)に分ける
    krsort($return);
    return $return;
}

//table一覧 text or html    createTableOnly = table なら createtable だけ出す falseなら定義表も
function tableListOut($TABLE_SCHEMA,$link,$outtype='text',$filter="",$createTableOnly=false){ //filter=テーブル名絞込み条件 

    mysql_query("set names sjis",$link);

    if ($outtype=='text')   {
        header("Content-Type: text/plain charset=shift_jis");
        echo assocToText(sql2asc("select version(),now()", $link,false))."\n";//DB概要
    }else{
        header("Content-Type: text/html charset=shift_jis");
        echo asc2html(sql2asc("select version(),now()", $link,false))."<br/>";//DB概要
    }

    //tables
    $sql="select TABLE_NAME,TABLE_COMMENT,TABLE_ROWS,ENGINE,TABLE_COLLATION from information_schema.tables where table_schema=database() ";
    if ($filter) $sql.=" and TABLE_NAME like '%".$filter."%' ";
    $tables=sql2asc($sql,$link,false,false);//Table Difinition
    
    //テーブル数
    if (count($tables)==0) exit('no tables');
    
    if ($filter) $strTables.="table name filter:".$filter."\n";
    $strTables.="".count($tables)." Tables \n";
    $strTables.="\n\n";
    
    if ($outtype=='html')    $strTables=nl2br($strTables);
    echo $strTables;

    if ($outtype=='text')    echo assocToText($tables);
    else            echo asc2html($tables);

    $strCols= "Column / CreateTable \n\n";
    if ($outtype=='html')    $strCols=nl2br($strCols);
    echo $strCols;

    //TableColumns
    foreach ($tables as $key=>$row){
        if ($outtype=='html') echo nl2br("-- ".$row['TABLE_NAME']."  -  ".$row['TABLE_COMMENT']."\n\n");
        else echo "-- ".$row['TABLE_NAME']."  -  ".$row['TABLE_COMMENT']."\n\n";
        $sqlTableDifinition=    "select TABLE_SCHEMA,TABLE_NAME,COLUMN_NAME,COLUMN_COMMENT,COLUMN_TYPE,COLUMN_KEY,EXTRA,COLLATION_NAME 
                    from information_schema.columns where TABLE_SCHEMA=database() and TABLE_NAME='".$row['TABLE_NAME']."'";

        //テーブル定義表
        if (!$createTableOnly){
            $assoc=sql2asc($sqlTableDifinition,$link,false,false);
            if ($outtype=='text')    echo assocToText($assoc);
            else                    echo asc2html($assoc);
            
            echo "\n";
            
            //sample 3 rows select
            if (isset($param_mode2) and $param_mode2=='samplerow'){
                echo "sample 3 rows";
                $TableDifinition="select * from ".$row['TABLE_NAME']." limit 3";
                echo assocToText(sql2asc($sqlTableDifinition, $link,false,false));
            }
        }
        
        //create statement
        $sqlCreateTable="show create table ".$row['TABLE_NAME'];
        $assoc=array_shift(sql2asc($sqlCreateTable, $link,false));
        $createtable=$assoc['Create Table'].";\n\n\n";
        
        if ($outtype=='html'){
            $createtable=preg_replace("/ /","&nbsp;",$createtable);
            $createtable=nl2br($createtable);
        }
        echo $createtable;
    }
}


//ダンプ出力系処理 csv,tsv,insert sqlの束を受け取る
function sqlDump($arySQL,$link,$outType){

    $arySQL=(Array)$arySQL;//配列でなければ配列化
    //検索結果
    foreach ($arySQL as $key=>$value){

        if (!trim($value)) continue;

        //通常SQL実行
        if ($outType=='execute' or $outType=='author' or !$outType) {
            $array=assocTrimChar(sql2asc(trim($value),$link,true,$_SESSION['mysql_limit']),$_SESSION['mysql_trim']);
            echo asc2html($array,"SQL");
            echo "<br/>";
        }
        if ($outType=='indent') {
            //echo "indent";
        }
        //distinct
        if ($outType=='distinct') echo asc2html(sql2asc($value, $link,true,$_SESSION['mysql_limit']),"DISTINCT");
        //CSV出力
        if ($outType=='csv')    echo assocToText(stripTabCrLf(addHeaderToAssoc(sql2asc($value, $link,false,false))),",");//*task
        if ($outType=='csvquote') {//*task 禁止文字来たら警告
            $assocCSV=sql2asc($value, $link,false,false);
            foreach($assocCSV as $key=>$value){
                $rowQuoted=null;
                foreach($value as $key2=>$value2)    $rowQuoted[$key2]='"'.$value2.'"';
                $assocCSVQuoted[$key]=$rowQuoted;
            }
            echo assocToText(addHeaderToAssoc($assocCSVQuoted,","));
        }
		
		//TSV INSERT EXPLAIN PROFILE
        if ($outType=='tsv')         echo assocToText(stripTabCrLf(addHeaderToAssoc(sql2asc($value, $link,false,false))),"\t");//*task
        if ($outType=='insertout')   echo assocToInsert(sql2asc($value,$link,false,false),$_REQUEST['TABLE_NAME']);
        if ($outType=='hexdump')     echo asc2html(HexDumpAssoc(sql2asc($value, $link,true,$limit)),"SQL");
        if ($outType=='tabcontain')  echo asc2html(checkCTRLContain(sql2asc($value, $link,true,$limit)),"SQL");
        if ($outType=='explain')     echo asc2html(sql2asc('explain '.$value, $link),'EXPLAIN');
        if ($outType=='viewer')     {
			$records=sql2asc($value, $link);
			if ($_REQUEST['mark']) $records=assocMarkRed($records,$_REQUEST['mark']);
			echo asc2html($records,'viewer',false)."<br/>";
		}
		if ($outType=='profile') {
            sql2asc('set profiling=1', $link);
            sql2asc($value, $link);
            echo asc2html(sql2asc("show profile;", $link),'PROFILE');
        }
    }
}

/*

■mode1
search
table
  data
tablelistout  
dbsummary 
serverinfo 
sql
  execute
  author
tables
dblist
logout

  createtable

*/

?>