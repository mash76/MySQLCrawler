<?
session_start();
include("ipcheck.php");//ip認証ログインチェック
$currentDB=$_SESSION['mysql_current_db'];

//jQueryのjqModal()プラグインを利用してモダルダイアログを出してる

//パラメータなければ終了
if (!$_REQUEST['TABLE_NAME']) exit(strRed("set table name"));

?>
<html>
<head><title><?=$_REQUEST['TABLE_NAME']?>:<?=$_SESSION['mysql_current_db']?></title>
<meta http-equiv="Content-Type" content="text/html; charset=shift_jis">
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
←<a href="MySQLCrawler.php"><?=$connStr1['server']; ?> <? echodarkred($currentDB); ?></a><hr/>
<? 
if ($_REQUEST['TABLE_NAME']) $_SESSION['mysql_his_table'][$_REQUEST['TABLE_NAME']]='';

//$connStr1=array('server'=>'127.0.0.1','db'=>'****','user'=>'****','pass'=>'*****');
//$_SESSION['mysql_conn_str']があること前提

//スキーマ指定あれば選択
if ($_REQUEST['TABLE_SCHEMA']) {
	$_SESSION['mysql_current_db']=$_REQUEST['TABLE_SCHEMA'];
	$_SESSION['mysql_his_db'][$_REQUEST['TABLE_SCHEMA']]="";
}

//$connStr1=$_SESSION['mysql_conn_str'];
$currentDB=$_SESSION['mysql_current_db'];
$link = @mysql_connect($connStr1['server'],$connStr1['user'],$connStr1['pass']); //mysql接続
sql2asc("use ".$currentDB,$link,false);
sql2asc('set names utf8',$link,false);//文字コード整え


//ワンタイムキーあれば更新SQL実行：reloadで繰り返される対策
if ($_REQUEST['onetimekey']==$_SESSION['onetimekey']){

    if ($_REQUEST['sqltext']) sql2asc(addslashesMQuote($_REQUEST['sqltext']),$link,true,false);//SQL実行 minmax distinct 

		//echo "<a href='?mode=add_partition&type=date_month'>part by month</a> ";
		//echo "<a href='?mode=add_partition&type=pk_hash'>part by PKhash</a> <br/>";
	if ($_REQUEST['mode']=='add_partition'){
		if ($_REQUEST['type']=='date_month'){
			//dateの列の最大最小を取る
			$a=array_shift(sql2asc("select min(purchased) as min,max(purchased) as max from ".$_REQUEST['TABLE_NAME'],$link));
			//svar_dump($a);
			$min=$a['min'];
			$max=$a['max'];
			//echo "min=$min max=$max";
			$min_u=strtotime(date("Y-m-01",strtotime($min)));
			$max_u=strtotime(date("Y-m-01",strtotime($max)));
			
			//月ごとパーティション
			for ($i=$min_u;$i<=$max_u;$i+=86400*28) $mons[date("Y-m",$i)]="1";
			$mons[date("Y-m",$max_u)]="1";//最新の月
			//var_dump($mons);
		
			foreach($mons as $key=>$month) $strPart[]="PARTITION p".preg_replace("/-/","",$key)." VALUES LESS THAN ( TO_DAYS('".date("Y-m-t",strtotime($key."-01"))." 23:59:59') ) COMMENT = '".$key."のdate'";
			$strPart[]="PARTITION pMAXVALUE VALUES LESS THAN ( MAXVALUE ) COMMENT = '残り全部'";
			$strp=implode(",\n",$strPart);
			//echo nl2br($strp);

			$sql="ALTER TABLE ".$_REQUEST['TABLE_NAME']." PARTITION BY RANGE ( to_days(purchased))
				(".$strp." ENGINE = InnoDB);";
			sql2asc( $sql,$link);
			//月ごとに区切る partition table をドロップしてゆく
			//最小=最小日の月　最大=最大日の月の最終日
		}
		if ($_REQUEST['type']=='hash'){
			sql2asc( "ALTER TABLE ".$_REQUEST['TABLE_NAME']." PARTITION BY HASH ( id ) PARTITIONS ".$_REQUEST['parts'],$link);
		}
	}

	//alter table
    if ($_REQUEST['mode']=='edit_table'){
    	if (isset($_REQUEST['TABLE_NAME_OLD'])) sql2asc("ALTER TABLE `".$_REQUEST['TABLE_NAME_OLD']."` RENAME TO `".$_REQUEST['TABLE_NAME']."`",$link);
    	if (isset($_REQUEST['TABLE_COMMENT'])) sql2asc("ALTER TABLE `".$_REQUEST['TABLE_NAME']."` comment '".addslashesMQuote($_REQUEST['TABLE_COMMENT'])."'",$link);
    }

    if ($_REQUEST['mode']=='drop_col') sql2asc("ALTER TABLE `".$_REQUEST['TABLE_NAME']."` drop column `".$_REQUEST['COLUMN_NAME']."`",$link);
    if ($_REQUEST['mode']=='add_col'){
    	sql2asc("ALTER TABLE `".$_REQUEST['TABLE_NAME']."` add column `tempcolumn_".date("YmdHis")."` varchar(100) null after `".$_REQUEST['AFTER']."`",$link);
    }
    if ($_REQUEST['mode']=='drop_index'){
    	sql2asc("drop index `".$_REQUEST['INDEX_NAME']."` on `".$_REQUEST['TABLE_NAME']."`",$link);
    }
    if ($_REQUEST['mode']=='add_pk'){
		$PKs=array();
		$aryCols=sql2asc("select COLUMN_NAME,COLUMN_KEY from information_schema.columns where TABLE_SCHEMA=database() and TABLE_NAME='".$_REQUEST['TABLE_NAME']."'",$link);
		foreach($aryCols as $row) if ($row['COLUMN_KEY']=="PRI") $PKs[$row['COLUMN_NAME']]="`".$row['COLUMN_NAME']."`";
		$PKs[$_REQUEST['COLUMN_NAME']]="`".$_REQUEST['COLUMN_NAME']."`";
    	sql2asc("ALTER TABLE `".$_REQUEST['TABLE_NAME']."` DROP PRIMARY KEY",$link);
    	sql2asc("ALTER TABLE `".$_REQUEST['TABLE_NAME']."` ADD PRIMARY KEY (".join(",",$PKs).")",$link);
    }
    if ($_REQUEST['mode']=='del_pk'){
		$PKs=array();
		$aryCols=sql2asc("select COLUMN_NAME,COLUMN_KEY,EXTRA from information_schema.columns where TABLE_SCHEMA=database() and TABLE_NAME='".$_REQUEST['TABLE_NAME']."'",$link);
		foreach($aryCols as $row) {
			if ($row['COLUMN_KEY']=="PRI") $PKs[$row['COLUMN_NAME']]="`".$row['COLUMN_NAME']."`";
			//autoincrement列ならまずaiはずすよう警告
			if ($row['EXTRA']=='auto_increment') exit(strRed("drop auto_increment before delete Primary Key"));
		}
		unset($PKs[$_REQUEST['COLUMN_NAME']]);

		
    	sql2asc("ALTER TABLE `".$_REQUEST['TABLE_NAME']."` DROP PRIMARY KEY",$link);
    	sql2asc("ALTER TABLE `".$_REQUEST['TABLE_NAME']."` ADD PRIMARY KEY (".join(",",$PKs).")",$link);
		//sql2asc("CREATE INDEX index_".$_REQUEST['TABLE_NAME']."_".$_REQUEST['COLUMN_NAME']." ON ".$_REQUEST['TABLE_NAME']." (".$_REQUEST['COLUMN_NAME']."(10)) ",$link);
    }

	if ($_REQUEST['mode']=='add_index'){
    	sql2asc("CREATE INDEX index_".$_REQUEST['TABLE_NAME']."_".$_REQUEST['COLUMN_NAME']." ON ".$_REQUEST['TABLE_NAME']." (".$_REQUEST['COLUMN_NAME'].") ",$link);
    	//sql2asc("CREATE INDEX index_".$_REQUEST['TABLE_NAME']."_".$_REQUEST['COLUMN_NAME']." ON ".$_REQUEST['TABLE_NAME']." (".$_REQUEST['COLUMN_NAME']."(10)) ",$link);
    }
	if ($_REQUEST['mode']=='add_unique'){
    	sql2asc("CREATE UNIQUE INDEX index_".$_REQUEST['TABLE_NAME']."_".$_REQUEST['COLUMN_NAME']." ON ".$_REQUEST['TABLE_NAME']." (".$_REQUEST['COLUMN_NAME'].") ",$link);
    	//sql2asc("CREATE INDEX index_".$_REQUEST['TABLE_NAME']."_".$_REQUEST['COLUMN_NAME']." ON ".$_REQUEST['TABLE_NAME']." (".$_REQUEST['COLUMN_NAME']."(10)) ",$link);
    }

    if ($_REQUEST['mode']=='edit_col_comp'){
        // yes/no > null/not-null
    	if (preg_match("/yes/i",$_REQUEST['rowvalues']['IS_NULLABLE']['value'])) $nullable='null';
    	if (preg_match("/no/i",$_REQUEST['rowvalues']['IS_NULLABLE']['value'])) $nullable='not null';

    	// 0が来てもセットされるようissetで確認
    	$default="";
    	if (isset($_REQUEST['rowvalues']['COLUMN_DEFAULT']['value']) and trim($_REQUEST['rowvalues']['COLUMN_DEFAULT']['value'])!="")  $default=" default " .$_REQUEST['rowvalues']['COLUMN_DEFAULT']['value'];
    	//var_dump($_REQUEST);
    	sql2asc("ALTER TABLE `".$_REQUEST['TABLE_NAME']."` change `".$_REQUEST['COLUMN_NAME_OLD']."` `".$_REQUEST['rowvalues']['COLUMN_NAME']['value']."` ".
    	$_REQUEST['rowvalues']['COLUMN_TYPE']['value']." ".$_REQUEST['rowvalues']['EXTRA']['value']." ".$nullable." ".$default." comment '".addslashesMQuote($_REQUEST['rowvalues']['COLUMN_COMMENT']['value'])."'",$link);
    }
}

//ワンタイムキーをセット
$_SESSION['onetimekey']='key'.rand(1,10000);

//コメント取得,件数
$info_table=array_shift(sql2asc("select TABLE_NAME,TABLE_COMMENT from information_schema.tables where table_schema=database() and table_name='".$_REQUEST['TABLE_NAME']."'", $link,false,false)); 

if (!$info_table['TABLE_COMMENT']) $table_comment='<span style="font-style:italic;color:gray;">no table comment</span>';
else $table_comment=htmlentities($info_table['TABLE_COMMENT'],ENT_QUOTES,$encode);

$recCount=array_shift(sql2asc("select count(1) as count from ".$_REQUEST['TABLE_NAME'], $link,false,false));

$tableSizeByte=array_shift(array_shift(sql2asc("select DATA_LENGTH from information_schema.tables where TABLE_SCHEMA=database() and TABLE_NAME='".$_REQUEST['TABLE_NAME']."'",$link,false,false)));
?>
	<span style='font-size:x-large;font-weight:bold; '>
		<a href="MySQL_Table.php?TABLE_NAME=<?=$_REQUEST['TABLE_NAME']?>">
			<?=$_REQUEST['TABLE_NAME']?>
		</a>
	</span>
	<span style='font-size:x-large;'><?=$recCount['count']?></span>
	<span id='TABLECOMMENT' realval='<?=htmlentities($info_table['TABLE_COMMENT'],ENT_QUOTES,$encode)?>' OnDblClick='tebleCommentEdit();' >
		<?=$table_comment ?>
	</span>

<!-- 飛び出すウィンドウ -->
<a href="#" class="jqModal">Edit</a>

<div class="jqmWindow" id="dialog">
	<a href="#" class="jqmClose">Close</a><hr/>
	
	<form name=f1 action="" method="post" >
		<input type=hidden name="mode" value="edit_table" />
        <input type=hidden name="onetimekey" value="<?=$_SESSION['onetimekey'] ?>">
		<input type=hidden name="TABLE_NAME_OLD" value="<?=$info_table['TABLE_NAME']?>">
		<table><tr><td>
			<span style="font-size:x-large">Tablename</span>
		</td><td>
			<input style="font-size:x-large" type='text' name='TABLE_NAME' size=38 value='<?=$info_table['TABLE_NAME']?>' >
		</td></tr><tr><td>
			<span style="font-size:x-large">Comment</span>
		</td><td>
			<input style="font-size:x-large" type='text' name='TABLE_COMMENT' size=38 value='<?=htmlentities(preg_replace('/;.*/','',$info_table['TABLE_COMMENT']),ENT_QUOTES,'utf-8') ?>' >
		</td></tr></table>
		<br/>
		<input type=submit value="update"/>
	</form>
</div>
	
<script type="text/javascript">
	$().ready(function() { 
		$('#dialog').jqm(); 
		$('#col_setumei').hide(); 
	});
</script>

<br/>
<?=round($tableSizeByte/1024/1024,1)."MB";?> &nbsp;<br/>
<?
//テーブル指定あり > テーブル詳細
$tableInfo=sql2asc("select TABLE_TYPE,ENGINE,ROW_FORMAT,CREATE_OPTIONS,TABLE_ROWS,AVG_ROW_LENGTH,DATA_LENGTH,MAX_DATA_LENGTH,INDEX_LENGTH,DATA_FREE,AUTO_INCREMENT,CREATE_TIME,UPDATE_TIME,CHECK_TIME,TABLE_COLLATION from information_schema.tables where TABLE_SCHEMA=database() and TABLE_NAME='".$_REQUEST['TABLE_NAME']."'",$link);
echo asc2html($tableInfo,false);
echo "<br/>";//SQL出さない分の改行


//テーブルヘッダ
$tableRow=array_shift($tableInfo);
?>
<form name="f11111" action="" method=get>
	<input type=hidden name="mode" value="edit_col_comp">
    <input type=hidden name="onetimekey" value="<?=$_SESSION['onetimekey'] ?>">
	<input type=hidden name="TABLE_NAME" value="<?=$_REQUEST['TABLE_NAME']?>">
	<?
	//テーブル詳細
	echo "<strong>COLUMN</strong> ";
	$aryCols=sql2asc("select COLUMN_NAME,COLUMN_COMMENT,COLUMN_KEY,EXTRA,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME ".
						"from information_schema.columns ".
						"where TABLE_SCHEMA=database() and TABLE_NAME='".$_REQUEST['TABLE_NAME']."'",$link);
	foreach ($aryCols as &$row) {
		$row['ACTION']=' &nbsp; <a safe="'.$safe.'" href="?mode=add_pk&onetimekey='.$_SESSION['onetimekey'].'&TABLE_NAME='.$_REQUEST['TABLE_NAME'].'&COLUMN_NAME='.$row['COLUMN_NAME'].'">PK</a> ';
		$row['ACTION'].=' &nbsp; <a safe="'.$safe.'" href="?mode=del_pk&onetimekey='.$_SESSION['onetimekey'].'&TABLE_NAME='.$_REQUEST['TABLE_NAME'].'&COLUMN_NAME='.$row['COLUMN_NAME'].'">delPK</a> ';
		$row['ACTION'].=' &nbsp; <a safe="'.$safe.'" href="?mode=add_index&onetimekey='.$_SESSION['onetimekey'].'&TABLE_NAME='.$_REQUEST['TABLE_NAME'].'&COLUMN_NAME='.$row['COLUMN_NAME'].'">Index</a> ';
		$row['ACTION'].=' &nbsp; <a safe="'.$safe.'" href="?mode=add_unique&onetimekey='.$_SESSION['onetimekey'].'&TABLE_NAME='.$_REQUEST['TABLE_NAME'].'&COLUMN_NAME='.$row['COLUMN_NAME'].'">Unique</a> ';
		$row['ACTION'].='&nbsp; <a safe="'.$safe.'" href="?mode=drop_col&onetimekey='.$_SESSION['onetimekey'].'&TABLE_NAME='.$_REQUEST['TABLE_NAME'].'&COLUMN_NAME='.$row['COLUMN_NAME'].'">drop</a>';
		$row['ACTION'].='&nbsp; <a safe="'.$safe.'" href="?mode=add_col&onetimekey='.$_SESSION['onetimekey'].'&TABLE_NAME='.$_REQUEST['TABLE_NAME'].'&AFTER='.$row['COLUMN_NAME'].'">↓add</a>';
	}
	echo "<div id='ALTER_TABLE'>";
	echo asc2html($aryCols,"ALTER_TABLE");
	echo "<div>";
	?>
	<input type=submit value="" style="visibility:hidden">
</form>

<div id="col_setumei" >
	COLUMN_TYPE<br/>
	tinyint smallint int bigint<br/>
	varchar() char()<br/>
	date datetime timestamp<br/>
	<br/>
	IS_NULLABLE - null / not null<br/>
	COLUMN_KEY - primary key<br/>
	EXTRA - auto increment<br/>
	DEFAULT - CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP<br/>
	<br/>
</div>
<br/>
<?
//インデックス情報
echo "<strong>INDEX</strong> ";
$aryIndes=sql2asc("SELECT INDEX_NAME,COLUMN_NAME,INDEX_TYPE,COMMENT,SEQ_IN_INDEX,NON_UNIQUE,CARDINALITY,COLLATION,SUB_PART,PACKED,NULLABLE FROM INFORMATION_SCHEMA.STATISTICS WHERE table_name = '".$_REQUEST['TABLE_NAME']."' AND table_schema =database() ",$link);
foreach($aryIndes as &$row) {
	$row['ACTION']="";
	if ($row['INDEX_NAME']!='PRIMARY') $row['ACTION']='<a href="?mode=drop_index&onetimekey='.$_SESSION['onetimekey'].'&TABLE_NAME='.$_REQUEST['TABLE_NAME'].'&INDEX_NAME='.$row['INDEX_NAME'].'">Del</a>';
}
echo asc2html($aryIndes,false);

if ($tableRow['TABLE_TYPE']=='BASE TABLE'){

    //PARTITION
    echo "<br/><strong>PARTITIONS</strong> ";
    $assocPart=sql2asc("select 
    PARTITION_NAME,PARTITION_METHOD,PARTITION_ORDINAL_POSITION,SUBPARTITION_NAME,SUBPARTITION_METHOD,SUBPARTITION_ORDINAL_POSITION,
    PARTITION_EXPRESSION,SUBPARTITION_EXPRESSION,PARTITION_DESCRIPTION,TABLE_ROWS,
    AVG_ROW_LENGTH,DATA_LENGTH,MAX_DATA_LENGTH,INDEX_LENGTH,DATA_FREE,
    CREATE_TIME,UPDATE_TIME,CHECK_TIME,CHECKSUM,
    PARTITION_COMMENT,NODEGROUP,TABLESPACE_NAME 
     from information_schema.PARTITIONS where TABLE_SCHEMA=database()  and TABLE_NAME='".$_REQUEST['TABLE_NAME']."'",$link);
     
    //trigger
    foreach($assocPart as &$row) {
    	$row['action']='<a href="?TABLE_NAME='.$_REQUEST['TABLE_NAME'].'&onetimekey='.$_SESSION['onetimekey'].'&sqltext='.urlencode("alter table `".$_REQUEST['TABLE_NAME']."` drop partition `".$row['PARTITION_NAME'].'`').';" >Del</a>';
    }
    echo asc2html($assocPart,false,false);

    echo "partition > ";
    echo "<a href='?mode=add_partition&type=date_month&TABLE_NAME=".$_REQUEST['TABLE_NAME']."&onetimekey=".$_SESSION['onetimekey']."'>range:month</a> ";
    echo "<a href='?mode=add_partition&type=hash&parts=5&TABLE_NAME=".$_REQUEST['TABLE_NAME']."&onetimekey=".$_SESSION['onetimekey']."'>hash5</a> ";
    echo "<a href='?mode=add_partition&type=hash&parts=10&TABLE_NAME=".$_REQUEST['TABLE_NAME']."&onetimekey=".$_SESSION['onetimekey']."'>hash10</a> ";
    echo "<br/>";

    //TRIGGER
    echo "<br/><strong>TRIGGER</strong> ";
    $assocTrig=sql2asc("select TRIGGER_NAME,ACTION_TIMING,EVENT_MANIPULATION,DEFINER,ACTION_STATEMENT from information_schema.TRIGGERS where TRIGGER_SCHEMA=database()  and EVENT_OBJECT_TABLE='".$_REQUEST['TABLE_NAME']."'",$link);
    foreach($assocTrig as &$row) {
    	$row['EVENT_MANIPULATION']=strRed($row['EVENT_MANIPULATION']);//タイミングinsert delete update を赤色強調
    	$row['action']='<a href="?TABLE_NAME='.$_REQUEST['TABLE_NAME'].'&onetimekey='.$_SESSION['onetimekey'].'&sqltext='.urlencode("drop trigger `".$row['TRIGGER_NAME'].'`').';" >Del</a>';
    }
    echo asc2html($assocTrig,false,false);

    $createTrigger="
    create trigger ".$_REQUEST['TABLE_NAME']."_**replace**
    on `".$_REQUEST['TABLE_NAME']."` for each row
    begin
      /* update TABLE003 set COL01='updated' where COL02=NEW.COL02; */ /* after */
      /* insert into LOGTABLE (value) values(OLD.COL02); */ /* before */
    end;
    ";
    ?>
    Trigger &nbsp;
    Before <a href="MySQLCrawler.php?mode1=sql&onetimekey=<?=$_SESSION['onetimekey']?>&sqltext=<?=urlencode(str_replace("**replace**","bef_ins before insert",$createTrigger))?>" target="_blank" >Insert</a> 
    <a href="MySQLCrawler.php?mode1=sql&onetimekey=<?=$_SESSION['onetimekey']?>&sqltext=<?=urlencode(str_replace("**replace**","bef_upd before update",$createTrigger))?>" target="_blank" >Update</a> 
    <a href="MySQLCrawler.php?mode1=sql&onetimekey=<?=$_SESSION['onetimekey']?>&sqltext=<?=urlencode(str_replace("**replace**","bef_del before delete",$createTrigger))?>" target="_blank" >Delete</a>  
    After <a href="MySQLCrawler.php?mode1=sql&onetimekey=<?=$_SESSION['onetimekey']?>&sqltext=<?=urlencode(str_replace("**replace**","aft_ins after insert",$createTrigger))?>" target="_blank" >Insert</a> 
    <a href="MySQLCrawler.php?mode1=sql&onetimekey=<?=$_SESSION['onetimekey']?>&sqltext=<?=urlencode(str_replace("**replace**","aft_upd after update",$createTrigger))?>" target="_blank" >Update</a> 
    <a href="MySQLCrawler.php?mode1=sql&onetimekey=<?=$_SESSION['onetimekey']?>&sqltext=<?=urlencode(str_replace("**replace**","aft_del after delete",$createTrigger))?>" target="_blank" >Delete</a> 
    <br/> <br/>
    <?
    //insert update statement 表示
    foreach ($aryCols as $row){
	    if ($row['EXTRA']=='auto_increment') $auto_increment_col=$row['COLUMN_NAME'];
    	$cols[$row['COLUMN_NAME']]="`".$row['COLUMN_NAME']."`";
    	$colVals[$row['COLUMN_NAME']]="'VAL_".$row['COLUMN_NAME']."'";
    	$colUpd[$row['COLUMN_NAME']]="`".$row['COLUMN_NAME']."`='VAL_".$row['COLUMN_NAME']."'";
    }

    echo nl2br("select * from ".$_REQUEST['TABLE_NAME'])."<br/><br/>";
    echo nl2br("select * ,(select count(*) from *** where ".$auto_increment_col."=".$_REQUEST['TABLE_NAME'].".".$auto_increment_col." ) as ct from ".$_REQUEST['TABLE_NAME']).strGray("-- 小テーブル統計<br/><br/>");
    echo nl2br("select ".implode(",",$cols)." from ".$_REQUEST['TABLE_NAME'])."<br/><br/>";

	$insert="insert into `".$_REQUEST['TABLE_NAME']."` (\n ".implode(",\n",$cols).") \n values( \n".implode(",\n",$colVals).")";
    echo nl2br(preg_replace("/\n/","",$insert))."<br/><br/>";
	//autoincrementなしinsert
	echo strRed("no auto_inc col"),"<br/>";
	$cols_no_ai_insert=$cols;
	unset($cols_no_ai_insert[$auto_increment_col]);
    $insert_no_ai="insert into `".$_REQUEST['TABLE_NAME']."` (\n ".implode(",\n",$cols_no_ai_insert).") \n values( \n".implode(",\n",$colVals).")";
    echo nl2br(preg_replace("/\n/","",$insert_no_ai))."<br/><br/>";


    $update="update `".$_REQUEST['TABLE_NAME']."` set  \n ".implode(",\n",$colUpd)."  \n where ...";	
    echo nl2br(preg_replace("/\n/","",$update))."<br/><br/>";

    echo nl2br($insert)."<br/><br/>";
    echo nl2br($update)."<br/>";

    //createTable
    echo "<br/><strong>CREATE TABLE</strong><hr/> ";
    $createTable=array_shift(sql2asc("show create table ".$_REQUEST['TABLE_NAME'],$link));
    echo "drop table `".$_REQUEST['TABLE_NAME']."`;<br/><br/>";

    echo nl2br(preg_replace("/ /","&nbsp;&nbsp;",htmlentities($createTable['Create Table'].";",ENT_QUOTES,$encode)));
}

//createView
echo "<br/><br/><strong>CREATE VIEW</strong><hr/> ";

if ($tableRow['TABLE_TYPE']=='VIEW'){

    $createView=array_shift(sql2asc("show create view ".$_REQUEST['TABLE_NAME'],$link));
    $cr_view=preg_replace("/(AS select)/","\n $1",$createView['Create View']);
    $cr_view=preg_replace("/( from )/","\n $1",$cr_view);
    $cr_view=preg_replace("/,/","\n  ",$cr_view);

    $create_view="-- drop view `".$_REQUEST['TABLE_NAME']."`; \n".
			 preg_replace("/.*DEFINER VIEW/","-- create view ",$cr_view);
    echo nl2br($create_view);

    echo '
    <form name="alter_view" action="MySQLCrawler.php">
      <input type="hidden" name="mode1" value="sql">
      <input type="hidden" name="TABLE_NAME" value="'.$_REQUEST['TABLE_NAME'].'">
      <textarea name="sqltext" cols=50 rows=10>'.$create_view.'</textarea>
      <input type="submit" />    
    </form>';
}
//SymfonyYML
echo "<br/><br/><strong>Symfony schema.yml</strong><hr/>";
echo $_REQUEST['TABLE_NAME'].": #".$tableComment."<br/>";

foreach ($aryCols as $row2){
	$type=" type : ".preg_replace("/(.*?)( |\()(.*)/i","$1",$row2['COLUMN_TYPE']);
    if (preg_match("/int/i",$type)) $type=" type : INTEGER ";
    if (preg_match("/date/i",$type)) $type=" type : timestamp ";
    if (preg_match("/varchar/i",$type)) $type.=" ,size:".preg_replace("/.*(\()(\d*)(\))/","$2",$row2['COLUMN_TYPE']);
    if (preg_match("/text/i",$type)) $type=" type : LONGVARCHAR ";
    
	//echo $row2['COLUMN_TYPE'];
	$primaryKey="";
	if ($row2['COLUMN_KEY']=='PRI') $primaryKey=" ,primaryKey: true ";
	$autoIncrement="";
	if ($row2['EXTRA']=='auto_increment') $autoIncrement=" ,autoIncrement: true ";
	$unsigned="";
	if (preg_match("/unsigned/i",$row2['COLUMN_TYPE'])) $unsigned=" ,unsigned: true ";
	$comment="";
	if ($row2['COLUMN_COMMENT']!="") $comment=" #".$row2['COLUMN_COMMENT'];
	$default="";
	if ($row2['COLUMN_DEFAULT']!="") $default=" ,default: ".$row2['COLUMN_DEFAULT'];
	$require=" ,required: false";
	if ($row2['IS_NULLABLE']=='NO') $require=" ,required: true";
	
	echo "&nbsp;&nbsp;".$row2['COLUMN_NAME'].": { ".$type.$primaryKey.$autoIncrement.$unsigned.$default.$require." } ".$comment."<br/>";	
}
//インデックス
    echo "    _indexes:<br/>";
    foreach($aryIndes as &$row) {
        if ($row['INDEX_NAME']!='PRIMARY' and $row['NON_UNIQUE']=='1') echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".$row['INDEX_NAME'].": [".$row['COLUMN_NAME']."]<br/>";
    }
    echo "    _uniques:<br/>";
    foreach($aryIndes as &$row) {
        if ($row['INDEX_NAME']!='PRIMARY' and $row['NON_UNIQUE']=='0') echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".$row['INDEX_NAME'].": [".$row['COLUMN_NAME']."]<br/>";
    }
mysql_close();

?>
<script type="text/javascript"> 
	//DB選択のリンクつけ
	var table='<? if (isset($_REQUEST['TABLE_NAME'])) echo $_REQUEST['TABLE_NAME']; ?>';
	var pk='<? if (isset($pk)) echo $pk;?>';

	//列を交互に配色 pk列とfk列を色変更
	$('tr[name!="header"]').each( function (ind,obj){ 
		if (ind % 2==1){$(obj).css("background-color","#F0F0F0");}//列を交互配色
	});
	//safeモードなら危険リンクをoffに styleも変える あの内側にspan入れてgrayに
    $('A[safe="true"]').each( function (ind,obj){ 
        $(obj).attr("href","javascript:alert('safe mode');");
        $(obj).html("<span style='color:silver'>"+$(obj).html()+"</span>");
	});
	
	//1テーブルselectなら
	$("#ALTER_TABLE tr").each( function (ind,obj){ 
		//列にダブルクリックイベント
		$(obj).dblclick(function(event){//onj=<tr>
			//alert(event.type);
			$("#col_setumei").slideToggle();
			
			//二重押し禁止
			if ($(obj).attr('clicked')=='true'){ 
				//tr内のinputを全部削除
				$(obj).find('input').remove();
                $(obj).find('br').remove();
				$(obj).removeClass('gray');
				$(obj).attr('clicked','false');
				return false;
			}
			$(obj).attr('clicked',"true");
			$(obj).addClass('gray');
			
			oldname=$(obj).find('td:first').html()
			//全tdをループしてテキストボックス追加
			$(obj).find('td').each(function(ind2,obj2){
				colname=$(this).attr('name');
				//alert(colname);
				if(colname!='ACTION' && colname!='COLLATION_NAME' && colname!='COLUMN_KEY' ){//idがACTION以外	

                    if ($(this).attr('isnull')=='true'){
                        $(this).html('<input type="text" name="rowvalues['+$(this).attr('name')+'][value]" value=""><br/>'+
                        $(this).html()+'<input type="hidden" name="rowvalues['+$(this).attr('name')+'][isnull]" value="'+$(this).attr('isnull')+'">');
                    }else{
                        $(this).html('<input type="text" name="rowvalues['+$(this).attr('name')+'][value]" value="'+encodeHTML($(this).attr("text"))+'"><br/ >'+
                        $(this).html()+'<input type="hidden" name="rowvalues['+$(this).attr('name')+'][isnull]" value="'+$(this).attr('isnull')+'">');
                    }

				//	if ($(this).attr('isnull')=='true'){//でnull項目
						//$(this).html('<input type="text" name="'+$(this).attr('name')+'" value=""/>'+$(this).html());
				//	}else{ //nullでない
						//$(this).html('<input type="text" name="'+$(this).attr('name')+'" value="'+$(this).html()+'"/>'+$(this).html());
				//	}
				}
			});
			$(obj).find('td:first').html('<input type=hidden name="COLUMN_NAME_OLD" value="'+oldname+'" />'+$(obj).find('td:first').html());
		});
	});
function encodeHTML(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); //"
}
</script> 
