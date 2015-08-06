<?
//ip認証ログインチェック
include("ipcheck.php");
$_SESSION['setting']=$setting;

$aryEncode=array("SJIS"=>array('mysql'=>'sjis','html'=>'shift_jis','php'=>'SJIS'),
                "UTF-8"=>array('mysql'=>'utf8','html'=>'utf-8','php'=>'UTF-8'),
                "EUC-JP"=>array('mysql'=>'ujis','html'=>'euc-jp','php'=>'EUCJP'));

if ($_REQUEST['mode1']=='logout') {
	unset($_SESSION['mysql_conn_str']);
	unset($_SESSION['mysql_current_db']);
}
if ($_REQUEST['mode1']=='debug') {
	if ($_SESSION['mysqlcrawler_debug'])  	unset($_SESSION['mysqlcrawler_debug']);
	else									$_SESSION['mysqlcrawler_debug']="true";
}

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
if ($setting['dbLock']) $_SESSION['mysql_current_db']=$setting['dbLock'];//ロック値入ってればdb変更不可
elseif ($_REQUEST['TABLE_SCHEMA'] ) $_SESSION['mysql_current_db']=$_REQUEST['TABLE_SCHEMA'];


//初期値
if (!$_SESSION['mysql_current_db']) $_SESSION['mysql_current_db']=$_SESSION['mysql_conn_str']['db'];//DB指定なければデフォルトDB

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
    }  
}
?>
<? htmlHeader("MySQL:".$_SESSION['mysql_current_db'],$setting['encode']);?>
<body>
<?
//ワンタイムキーをセット
$_SESSION['onetimekey']='key'.rand(1,10000);
?>
<div style='display:-webkit-box;display:-moz-box;' >
  <div style='width:50%;'>
    <a href="?mode1=dblist" title='Current Server'>
        <span class="strbold" style='color:black'><?=$_SESSION['mysql_conn_str']['server']?></span>
    <span style='color:gray;font-size:150%;'><?=$_SESSION['mysql_conn_str']['server_desc']?></span>
    </a>
    <? $verTime=array_Shift(sql2asc("select version() as ver,now() as now", $link,false)) ?>
	<?=strHTML("&nbsp;".$verTime['ver'],"bold gray span")?>
    <a href="?mode1=sql&sqltext=<?=urlencode($privSQL)?>"><?=strBold($user)?></a>
   <br/>
    <a href='MySQLCrawler.php?searchtext=&mode1=search&searchItem=TABLE&TABLE_SCHEMA=<?=$_SESSION['mysql_current_db'] ?>' title='Current Database' >
        <span class="strbold" style='color:crimson;'><?=$_SESSION['mysql_current_db'] ?></span>
    </a> 
    <span style='font-size:medium;font-weight:bold;'>

		<?
		$reader="read_".$_SESSION['mysql_current_db'];
		$readOnlySQL="CREATE USER ".$reader.";\n".
					"SET PASSWORD FOR ".$reader." = PASSWORD ('pass01'); \n".
					"GRANT SELECT ON * TO ".$reader.";";
		?>
	</span>
	<? 
	if ($setting['dbLock']) echo strRed(strBold("DBLock "));
	if ($setting['safe']) echo strRed(strBold("Safe "));
	
	?>&nbsp;
  </div><div style='text-align:right;vertical-align:bottom;width:50%;font-size:medium;'>
  		<?=strGray($verTime['now'])?>
        <a href='MySQLCrawler.php?mode1=debug' title="dump $_REQUEST (get/post) parameters"><?
            if ($_SESSION['mysqlcrawler_debug']) echo strRed(strBold("debug"));
            else								 echo strBold("debug");
        ?></a>
        <a href='MySQLCrawler.php?mode1=serverinfo' ><?=strBold("serverInfo")?></a>
		<a href='MySQLCrawler.php?mode1=logout' ><?=strBold("logout")?></a>
		<br/>
        <a href='<? echo "?mode1=sql&sqltext=".urlencode("show variables where variable_name like'%%' \n/* log buffer relay report slave slow time cache version query ssl sql net init have inno max isam char */"); ?>'>Variables</a>
        <a href='?mode1=sql&sqltext=<?=urlencode("show status where variable_name like'%%' \n/* Aborted Binlog Com Com_Alter Com_create Com_delete Com_drop Com_show Com_stmt Created Delayed Handler Innodb Innodb_buffer Innodb_data Key Qcache Threads Sort */") ?>'>Status</a>
        
        <a href='?mode1=sql&sqltext=show processlist'>Process</a>
   <br/>

        <a href='?mode1=sql&sqltext=<?=urlencode("select count(1) as COUNT,COLUMN_TYPE \nfrom information_schema.columns \nwhere TABLE_SCHEMA='".$_SESSION['mysql_current_db']."' \ngroup by COLUMN_TYPE") ?>' target='_blank'>ColStat</a>
        <a href='?mode1=sql&sqltext=<?=urlencode("select count(1) as COUNT,COLUMN_TYPE,COLUMN_DEFAULT,IS_NULLABLE,COLUMN_KEY,EXTRA \nfrom information_schema.columns \nwhere TABLE_SCHEMA=database() \ngroup by COLUMN_TYPE,COLUMN_DEFAULT,IS_NULLABLE,COLUMN_KEY,EXTRA") ?>' target='_blank'>ColStat2</a>
        <a href='?mode1=tablelistout&outtype=html' target='_blank'>TableList</a>
        (<a href='?mode1=tablelistout&outtype=text' target='_blank'>TSV</a>) 
  </div>
</div>

<hr/>
	<?  
	if ($_REQUEST["sqltext"]) echo asc2html(sql2asc(stripslashMQuote($_REQUEST["sqltext"]),$link))."<hr/>";

	echo strBold("Memory<br/>");
	$dataSize=sql2asc("select sum(DATA_LENGTH) as DATA_LENGTH_SUM,sum(INDEX_LENGTH) as INDEX_LENGTH_SUM from information_schema.TABLES where TABLE_SCHEMA=database()", $link,true);
	echo "<blockquote>";
?>
	
	
<pre>	
Global
  cache
    query_cache
    thread_cache
    table_cache

  innoDB
    innodb_buffer_pool
    innodb_log buffer
    innodb_additional_mem_pool
  MyIsam
    key_buffer
  Heap

Per USER
  join_buffer
  read buffer
  sort_buffer
  binlog_cache
  max_allowed_packet
</pre>
<?

	echo strBold("buffer");
    echo asc2html(sql2asc("show variables like '%buffer%'",$link));
    echo asc2html(sql2asc("show status like '%buffer%'",$link));
	echo strBold("innodb");
    echo asc2html(sql2asc("show variables like '%innodb%'",$link));
    echo asc2html(sql2asc("show status like '%innodb%'",$link));
	echo strBold("myisam");
    echo asc2html(sql2asc("show variables like '%myisam%'",$link));
    echo asc2html(sql2asc("show status like '%myisam%'",$link));
	echo strBold("cache");
    echo asc2html(sql2asc("show variables like '%cache%'",$link));
    echo asc2html(sql2asc("show status like '%cache%'",$link));
	echo "</blockquote>";


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
    $engines=sql2asc("select ENGINE,count(1) as count,sum(DATA_LENGTH) as DATA_LENGTH_SUM,sum(INDEX_LENGTH) as INDEX_LENGTH_SUM from information_schema.TABLES 
	                                    where TABLE_SCHEMA=database() group by ENGINE", $link,true);
	foreach($engines as &$row) {
		$row["ENGINE"]='<a href="?sqltext='.urlencode("select * from information_schema.TABLES ".
							"where TABLE_SCHEMA=database() and ENGINE='".$row["ENGINE"]."'").'">'.$row["ENGINE"].'</a>';
		$row['DATA_LENGTH_SUM'].=" (".round($row['DATA_LENGTH_SUM']/1024/1024,1)."MB)";
		$row['INDEX_LENGTH_SUM'].=" (".round($row['INDEX_LENGTH_SUM']/1024/1024,1)."MB)";    
	    
    
    }
    echo asc2html($engines,false,false);


    echo "<br/><strong>COLUMN Rowtypes </strong> (null = view) ";
    $rowtypes=sql2asc("select count(1) as COUNT,COLUMN_TYPE from information_schema.columns 
										where TABLE_SCHEMA=database() group by COLUMN_TYPE ", $link,true);
	foreach($rowtypes as &$row) $row["COLUMN_TYPE"]='<a href="?sqltext='.urlencode("select * from information_schema.columns ".
							"where TABLE_SCHEMA=database() and COLUMN_TYPE='".$row["COLUMN_TYPE"]."'").'">'.$row["COLUMN_TYPE"].'</a>';
    echo asc2html($rowtypes,false,false);

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
    echo asc2html(sql2asc("show variables like '%relay_log%'",$link));
    echo asc2html(sql2asc("show status like '%relay_log%'",$link));
    echo asc2html(sql2asc("show status like '%slave%'",$link));
    echo asc2html(sql2asc("show slave status",$link));
    
mysql_close($link) or die("切断失敗");//db切断
?>
<script type="text/javascript"> 
    //列を交互に配色 pk列とfk列を色変更
    $('tr[name!="header"]').each( function (ind,obj){ ;
        if (ind % 2==1 ){$(obj).css("background-color","#F0F0F0");}//列を交互配色
    });
</script> 
<hr/>

    <?
if ($_SESSION['mysqlcrawler_debug']) {
	htmlFooterDebug();
	echo "<br/>".strBold('$setting');
    echo assocDump($setting);
    echo strBold('$_SESSION["mysql_his_table"]');
	echo assocDump($_SESSION["mysql_his_table"]);
	
	echo strBold('$_SESSION["mysql_his_searchtext"]');
    echo assocDump($_SESSION["mysql_his_searchtext"]);
    echo "sqlHistory<br/>";
    var_dump( $_SESSION["sql_his"]);
    
    echo strBold("<br/>Log<br/>").asc2html($GLOBALS['LOG'],'LOG');
}

function htmlFooterDebug(){
    echo strBold('$_REQUEST');
    echo assocDump($_REQUEST);
    
    echo strBold('RAW_POST<br/>');
    echo file_get_contents("php://input");

    echo '$_SESSION<br/>'."<pre>".print_r($_SESSION,true)."</pre>";
}
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
            if ($outtype=='text')   echo assocToText($assoc);
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


?>