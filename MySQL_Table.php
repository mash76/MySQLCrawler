<?php
session_start();

//ip認証ログインチェック
include("ipcheck.php");

//$_SESSION['mysql_conn_str']があること前提

//スキーマ指定あれば選択
if ($_REQUEST['TABLE_SCHEMA']) {
    $_SESSION['mysql_current_db']=$_REQUEST['TABLE_SCHEMA'];
    $_SESSION['mysql_his_db'][$_REQUEST['TABLE_SCHEMA']]++;
}
$currentDB=$_SESSION['mysql_current_db'];

$aryEncode=array("SJIS"=>array('mysql'=>'sjis','html'=>'shift_jis','php'=>'SJIS'),
                "UTF-8"=>array('mysql'=>'utf8','html'=>'utf-8','php'=>'UTF-8'),
                "EUC-JP"=>array('mysql'=>'ujis','html'=>'euc-jp','php'=>'EUCJP')
            );

//接続を切り替え
if (isset($_REQUEST['conn_no'])) {
	unset($_SESSION['mysql_current_db']);
	$_SESSION['conn_no']=$_REQUEST['conn_no'];
	$_SESSION['mysql_conn_str']=$connStrAry[$_REQUEST['conn_no']];
	$_SESSION['mysql_current_db']=$_SESSION['mysql_conn_str']['db'];
}

if (isset($_SESSION['conn_no'])) $param_conn_no="&conn_no=".$_SESSION['conn_no'];
	
$encode='UTF-8';
$selectLimit=400;
$colCharMax=200;


$link = @mysql_connect($_SESSION['mysql_conn_str']['server'],$_SESSION['mysql_conn_str']['user'],$_SESSION['mysql_conn_str']['pass']) ; //mysql接続
mysql_set_charset("utf8"); //mysql_real_escape_String を文字化けさせない設定
sql2asc('use '.$currentDB,$link,false,false);//文字コード
sql2asc('set names utf8',$link,false,false);


if (preg_match('/export/',$_REQUEST['mode2'])) {

	//export: tsv csv insert
    header('Content-Type: text/plain;charset=utf8');//ヘッダ書き換え
	if ($_REQUEST['mode2']=='export_csv')   {
		echo assocToText(stripTabCrLf(sql2asc(stripslashMQuote($_REQUEST['sqltext']), $link,false,false)),",");
    }
    if ($_REQUEST['mode2']=='export_csvquote') {//*task
        $assocCSV=sql2asc($_REQUEST['sqltext'], $link,false,false);
        foreach($assocCSV as $key=>$value){
            $rowQuoted=null;
            foreach($value as $key2=>$value2)    $rowQuoted[$key2]='"'.$value2.'"';
            $assocCSVQuoted[$key]=$rowQuoted;
        }
		echo assocToText($assocCSVQuoted,",");
    }
	if ($_REQUEST['mode2']=='export_tsv'){
    	echo assocToText(stripTabCrLf(sql2asc(stripslashMQuote($_REQUEST['sqltext']), $link,false,false)),"\t");
	}
	if ($_REQUEST['mode2']=='export_insertout') {
		$assoc=sql2asc(stripslashMQuote($_REQUEST['sqltext']),$link,false,false);
		//noAI paramきたら AI列以外で構成
		if ($_REQUEST["noAI"]){
			//テーブル定義取得
			$descTableAssoc=sql2asc('describe '.$_REQUEST['TABLE_NAME'], $link,false,false);//TableData *task
			foreach($descTableAssoc as $row) if ($row['Extra']=="auto_increment") $aicol=$row['Field'];
			if ($aicol){
				foreach($assoc as &$row) unset($row[$aicol]); 
			}
		}
		echo assocToInsert($assoc,$_REQUEST['TABLE_NAME']);
    }
    exit();
}
header('Content-Type: text/html;charset=utf8');//ヘッダ書き換え



//ワンタイムキーあれば更新SQL実行：reloadで繰り返される対策
if (isset($_REQUEST['onetimekey']) && $_REQUEST['onetimekey']!=$_SESSION['onetimekey']) echo strRed("onetimekey fail<br/>");
if ($_REQUEST['onetimekey']==$_SESSION['onetimekey']){

    if ($_REQUEST['sqltext']) echo asc2html(sql2asc($_REQUEST['sqltext'],$link,true,false))."<br/>";//SQL実行 min_max distinct 

    //テーブルdescでpri列を探しwhere条件に
    $descTableAssoc=sql2asc('describe '.$_REQUEST['TABLE_NAME'], $link,false,false);//TableData *task
    $aryPKs=null;
    foreach($descTableAssoc as $key=>$value) if ($value['Key']=='PRI') $aryPKs[$value['Field']]=$value['Field'];

    //行更新ならDBに更新
    if (isset($_REQUEST['rowvalues'])){

        //update文作成、実行
        $aryUpd=null;
        $sqlUpd="";
        $aryWhere=null;
        $sqlWhere="";

         //pk列は更新前の値でwhere条件に、それ以外は更新
        foreach($_REQUEST['rowvalues'] as $key => $value){
            if (isset($aryPKs[$key]))  $aryWhere[]="`".$key."`='".addslashesMQuote($value['old_value'])."'";
            if ($value['isnull']=='true' and $value['value']=='')   $aryUpd[]="`".$key."`= null ";
            else  $aryUpd[]="`".$key."`='".addslashesMQuote($value['value'])."'";
        }
        $sqlUpd=join(",",$aryUpd);
        $sqlWhere=join(" and ",$aryWhere);
        $sql= 'update '.$_REQUEST['TABLE_NAME'].' set '.$sqlUpd." where ".$sqlWhere;
        sql2asc($sql,$link);
    }

    // 適当に1000レコード作る pk auto_increment 前提
    if ($_REQUEST['mode2']=='create1000' ){

		foreach($aryPKs as $col) $max_sql[]="max(`".$col."`) as ".$col;
		$aryMax=array_shift(sql2asc("select ".implode(",",$max_sql)." from ".$_REQUEST['TABLE_NAME'],$link));

		//1000行ごとの大insert文
		$recs=$_REQUEST['recs'];//作成行数
		echo count($recs)." / ";
		$sqlInsert=array();
		for ($j=0;$j<$recs;$j++){
			flush();//途中経過表示。通常は最後にまとめてブラウザに送信
			
			$cols=array();
			$vals=array();
			foreach ($descTableAssoc as $row){
				$valGenerate="";
				if ($row['Key']=='PRI')  {
					//auto_incrementなら自動発番にまかせ、autoでない数値型なら連番セット
					if ($row['Extra']!='auto_increment') {
						$cols[]="`".$row['Field']."`";
						if (preg_match ('/(int|float|double|decimal)/i',$row['Type'])) {
							$vals[$row['Field']]=$aryMax[$row['Field']]+$j+1;
						}else{
							//数値以外
							if (preg_match ('/(date|time)/i'       ,$row['Type'])) $valGenerate=date("'Y-m-d H:i:s'",time()+(rand(1,2000)-1000)*86400);//現在から少し揺らす		
						}
					}
				}else{
					$cols[]="`".$row['Field']."`";
					//形式ごとにダミーデータセット
					
					if (preg_match ('/(int|float|double|decimal)/i',$row['Type'])) $valGenerate=rand(1,50000);//揺らす
					if (preg_match ('/(tinyint|smallint)/i',$row['Type'])) $valGenerate=rand(1,127);//揺らす
					if (preg_match ('/(text|char)/i'       ,$row['Type'])) $valGenerate="'".substr(str_repeat("text567890",200),0,preg_replace("/(.*\()(\d*)(.*)/","$2",$row['Type']))."'";//
					if (preg_match ('/(date|time)/i'       ,$row['Type'])) $valGenerate=date("'Y-m-d H:i:s'",time()+(1000*86400)-rand(1,2000*86400));//現在から少し揺らす
				}
				if ($valGenerate) $vals[]=$valGenerate;
			}
			//5000行ごとに大きなinsert文で
			$sqlInsert[]=" (".join(",",$vals).")";
			//echo $j.":";
			if ($j%5000==0 or $j==$recs-1) {
				echo ($j+1)." ";
				$sqlInsertAll="insert into ".$_REQUEST['TABLE_NAME']." (".join(",",$cols)." )  values ".join(",",$sqlInsert);
				sql2asc($sqlInsertAll,$link,true,false);
				$sqlInsert=array();
			}
		}
	}
	
	//カスタムデータ生成 eval利用  
	if ($_REQUEST['generateCommit']){
		echo "カスタム生成<br/>";
		
//生成サンプル
$str=<<<DOC_END
ランダム数字 rand(1,20) rand(-20,300) rand(100,10000)/100 
ランダム文字 str_pad("",200,"*")  str_repeat("abc",10)  
独自変数  $n 行番号  $1 $2 $3 $4 n列めの値 現在列より前の列のみ参照可  
unix時刻 time()   日付 date("Y-m-d H:i:s")  
DOC_END;
		echo strGray(jsTrim($str,100))."<br/>";
		
		$sqlInsert="insert into ".$_REQUEST['TABLE_NAME']." (";
		for($i=1;$i<5;$i++){
			$sqlKeys=array();
			$sqlVals=array();
			$colnum=0;
			foreach ($_REQUEST['generate'] as $key=>$value)	{
				$colnum++;
				if ($value!=''){
					$value=str_replace('$n',$i,$value);//行番号置き換え
					//後方参照
					for($j=1;$j<$colnum;$j++){
						$value=str_replace('$'.$j,$sqlVals[$j-1],$value);
					}
					$eval='$a='.stripslashMQuote($value).";";
					if (eval($eval)===false) echoRed("evalErr".$eval);
					$sqlKeys[]="`".$key."`";
					$sqlVals[]=$a;
					
				}
			}
			foreach($sqlVals as &$value) $value="'".$value."'";
			echo strGray($sqlInsert.join(",",$sqlKeys).") values (".join(",",$sqlVals).");"."<br/>");
		}
	}
	
	//importTSVCSV upd 
	if ($_REQUEST['mode2']=='importtsvcsv_upd'){
		//文字コード判別 $str格納時のコードは、このphpファイルの保存文字コード
		$text=stripslashMQuote($_REQUEST['text']);
		$aryText=trimArray(explode("\n",$text));//配列の空白項目除去
		//cho "text=".$_REQUEST['text']."<br/>";			
		//echo "text=".$text."<br/>";
		
		$count=0;
		$success=0;
		$errors=0;
		$max=count($aryText);
		$descTableAssoc=sql2asc("describe ".$_REQUEST['TABLE_NAME'], $link,false,false);//pk取得
		$sql_set_ct=3000;
		if ($_REQUEST['smallsql']==1) $sql_set_ct=1;
		
		foreach ($aryText as $row_str){
			if (trim($row_str)=='') continue;//空白行ならスルー
			if ($_REQUEST['updtype']=='CSV')    $separateChar=',';
			else                                $separateChar="\t";

			//列取得、スルー列を除去
			$cols=array();
			//スルー対象でない列の値を取得
			foreach ($descTableAssoc as $field){
				if (!$_REQUEST['thru'][$field['Field']]) {
					$cols[]=$field['Field'];
				}
			}
			$strCols="(`".implode("`,`",$cols)."`)";
			//値
			$col_vals=explode($separateChar,$row_str);
			foreach($col_vals as &$val) {
				$val=trim($val);
				if ($_REQUEST["double_quote"]) $val=trim($val,'"');//通常除去＋ダブルクオートも
				$val=mysql_real_escape_string($val);
			}

			


			$strVals[]=" ('".join("','",$col_vals)."') ";//$rowをtrimせず。tsvで最後空白のとき空白を取得できないから
			//smallsqlがあれば毎回insert、なければ3000行ごと	
			$count++;
			if ( $count % $sql_set_ct==0 or $count==$max){
				$sqlInsert="insert into ".$_REQUEST['TABLE_NAME']." ".$strCols." values ".implode(",",$strVals).";";
				$ret=sql2asc($sqlInsert,$link);	
				
				$sqls++;
				if ($ret==false) $errors++;
				else 			 $success++;
				
				$strVals=array();
			}
		}
		echo "target column count ".count($cols)."<br/>";
		echo "send column count (last line) ".count($col_vals)."<br/>";

		echo "send lines ".$max."<br/>";
		echo "lines per sql ".$sql_set_ct."<br/>";
		echo "created sqls ".$sqls."<br/>";
		echo strGray(" success ").strBold($success);
		echo strGray(" <br/>fail:").strRed($errors)." <br/>";
	}
}


//ワンタイムキーをセット
$_SESSION['onetimekey']='key'.rand(1,10000);
if ($_REQUEST['schlink_clear']) unset($_SESSION['searchvals'][$_REQUEST['TABLE_NAME']]);
//検索履歴
if ($_REQUEST['searchvals']) $_SESSION['searchvals'][$_REQUEST['TABLE_NAME']][]=serialize($_REQUEST['searchvals']);	
if (!$_SESSION['searchvals'][$_REQUEST['TABLE_NAME']]) $_SESSION['searchvals'][$_REQUEST['TABLE_NAME']]=array();
?>
<? htmlHeader($_REQUEST['TABLE_NAME'].":".$_SESSION['mysql_current_db'],$setting['encode']);?>
<body>
←<a href="MySQLCrawler.php?mode=<?=$param_conn_no?>"><?=$_SESSION['mysql_conn_str']['server']; ?> <?=strDarkRed($currentDB); ?></a><br/>
<?
$ct=0;
foreach($_SESSION['searchvals'][$_REQUEST['TABLE_NAME']] as $key=>$searchvals_serial){
	$ct++;
	echo '<a href="?TABLE_NAME='.$_REQUEST['TABLE_NAME'].$param_conn_no.'&schlink='.$key.'"><span class="history" >filter'.$ct.'</span></a> ';
}
if ($_SESSION['searchvals'][$_REQUEST['TABLE_NAME']]) echo '<a style="font-style:italic;color:gray;" href="?TABLE_NAME='.$_REQUEST['TABLE_NAME'].'&schlink_clear=clear">clear</a>';
?>

<hr/>
<?
if ($_REQUEST['table_history']) $_SESSION['mysql_his_table'][$_REQUEST['TABLE_NAME']]++;

$descTableAssoc=sql2asc("describe ".$_REQUEST['TABLE_NAME'], $link,false,false);//pk取得
foreach($descTableAssoc as $key=>$row){    if ($row['Key']=='PRI') $pk[]=$row['Field'];}

//リンク表示用：テーブルコピー文生成
$newTable=$_REQUEST['TABLE_NAME'].date("_Ymd_His");
$assocCreateTable=sql2asc("show create table ".$_REQUEST['TABLE_NAME'], $link,false,false);//CreateTable
$sqlTableCopy="create table `".$newTable."` ( ".
              preg_replace('/^.*\(/',' ',$assocCreateTable[0]['Create Table'])." ; ".PHP_EOL.
              "insert into `".$newTable."` select * from ".$_REQUEST['TABLE_NAME'].";";

//コメント取得
$info_tables=array_shift(sql2asc("select * from information_schema.tables where table_schema=database() and table_name='".$_REQUEST['TABLE_NAME']."'", $link,false,false)); 
//件数
$recCount=array_shift(sql2asc("select count(1) as count from ".$_REQUEST['TABLE_NAME'], $link,false,false));
//テーブルコメント空ならno table commentと表示
if (!$info_tables['TABLE_COMMENT']) $table_comment='<span style="font-style:italic;color:gray;">no table comment</span>';
else                                $table_comment=htmlentities($info_tables['TABLE_COMMENT'],ENT_QUOTES,$encode);

	//検索 フォームに入れた場合と、そのショートカットから来た場合
	if (isset($_REQUEST['schlink'])) $searchvals=unserialize($_SESSION['searchvals'][$_REQUEST['TABLE_NAME']][$_REQUEST['schlink']]);
	if ($_REQUEST['searchvals']) $searchvals=$_REQUEST['searchvals'];
	$andor=$_REQUEST['andor'];
	$andor=" or ";
	

//DATA一覧
if ($_REQUEST['mode2']=='data' or !isset($_REQUEST['mode2'])){

	//検索条件の画面入力あれば where文作成 %あれば likeに $$あれば<>に ,あればinに
	$sqlWhere="";
	$arySQLWhere=array();
	$filterCount=$recCount['count'];
	
	if (isset($searchvals)){
		foreach($searchvals as $key => $value)    {//配列でaaa=bbをつくりandでexplodeし、結合
			$valueEscaped=mysqlEsacpeMQuote($value);
	
			if ($value!="") {
				if (strpos($value,"%")!==false) $arySQLWhere[$key]=$_REQUEST['TABLE_NAME'].".`".$key."` like '".$valueEscaped."'";
				elseif(strpos($value,"'")!==false) $arySQLWhere[$key]= $_REQUEST['TABLE_NAME'].".`".$key."` =".trim($value);
				elseif(strpos($value,"$$")!==false) $arySQLWhere[$key]= str_replace("$$",$_REQUEST['TABLE_NAME'].".`".$key."`",$valueEscaped);
				elseif(strpos($value,",")!==false) $arySQLWhere[$key]=$_REQUEST['TABLE_NAME'].".`".$key."` in (".$valueEscaped.")";
				elseif(strpos($value,"-")!==false) {
					list($w_from,$w_to)=explode("-",$valueEscaped);
					$arySQLWhere[$key]=$_REQUEST['TABLE_NAME'].".`".$key."` between '".$w_from."' and '".$w_to."'";
				}else{
					$arySQLWhere[$key]=$key." = '".$valueEscaped."'";
				}
			}
			$sqlWhere=implode(" ".$andor." ",$arySQLWhere);
			if (strlen($sqlWhere)!=0) $sqlWhere=" where ".$sqlWhere;
		}
	}


	//選択範囲内でいろいろdistinct mixmax,asc,desc   param=distinct_col
	if ($_REQUEST['distinct_col']){
		echo asc2html(sql2asc("select ".$_REQUEST['distinct_col'].",count(1) from ".$_REQUEST['TABLE_NAME']." group by ".$_REQUEST['distinct_col'] ,$link));
		echo asc2html(sql2asc("select ".$_REQUEST['distinct_col'].",count(1) from ".$_REQUEST['TABLE_NAME'].$sqlWhere." group by ".$_REQUEST['distinct_col'] ,$link));
	}
	if ($_REQUEST['minmax_col']){
		echo asc2html(sql2asc("select max(".$_REQUEST['minmax_col'].") ,min(".$_REQUEST['minmax_col'].")  from ".$_REQUEST['TABLE_NAME'] ,$link));
		echo asc2html(sql2asc("select max(".$_REQUEST['minmax_col'].") ,min(".$_REQUEST['minmax_col'].")  from ".$_REQUEST['TABLE_NAME'].$sqlWhere ,$link));
	}
	//sort "select * from ".$_REQUEST['TABLE_NAME'].$sqlWhere;
	if ($_REQUEST['sortcolumn']){
		$sqlWhere.=" order by ".$_REQUEST['TABLE_NAME'].".".$_REQUEST['sortcolumn']." ".$_REQUEST['sortorder'];
	}
	//リンク
	$sqlSelect="select * from ".$_REQUEST['TABLE_NAME'].$sqlWhere;
	
	$sqlNoLimit=$sqlSelect;
	if ($_REQUEST['nolimit']!='true') $sqlSelect.=" limit $selectLimit";//limitないと数万件で待たされる
	
	$tableData=sql2asc($sqlSelect, $link,false,false);//検索実行
	$filterCount=count($tableData);
}

?>
<span style='font-size:x-large;'>
        <span style='font-weight:bold;'>
        <a href="MySQL_TableEdit.php?TABLE_NAME=<?=$_REQUEST['TABLE_NAME'].$param_conn_no?>">
            <?=$_REQUEST['TABLE_NAME']?>
        </a>
    </span>
	<? if ($filterCount!=$recCount['count']) $filterCount=strHTML("filter ","span red")." ".$filterCount.strGray("/".$recCount['count']); ?>
    <?=$filterCount?>
</span>
<span id='TABLECOMMENT' realval='<?=htmlentities($info_tables['TABLE_COMMENT'],ENT_QUOTES,$encode)?>' OnDblClick='tebleCommentEdit();' >
    <?=$table_comment ?></span>

&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<a href="MySQLCrawler.php?mode1=sql<?=$param_conn_no?>&dbname=<?=$_SESSION['mysql_conn_str']['db']?>&sqltext=<?=$sqlTableCopy?>">BKCopy</a>&nbsp;
<a href="?mode2=importtsvcsv<?=$param_conn_no?>&TABLE_NAME=<?=$_REQUEST['TABLE_NAME']?>">ImportTSVCSV</a>&nbsp;

Generate 
<? foreach (array(1,5,50,100,1000,10000,100000) as $recs) { ?>
	<a safe="<?=$setting['safe']?>" href="?mode2=create1000<?=$param_conn_no?>&onetimekey=<?=$_SESSION['onetimekey']?>&recs=<?=$recs?>&TABLE_NAME=<?=$_REQUEST['TABLE_NAME']?>"><?=$recs?></a>&nbsp;   
<? } ?>

<a href="MySQLCrawler.php?mode1=sql&sqltext=<?=urlencode('-- truncate table `'.$_REQUEST['TABLE_NAME']."`") ?>" target='_blank'>#Truncate</a>&nbsp;&nbsp;
<a href="MySQLCrawler.php?mode1=sql&sqltext=<?=urlencode('-- drop table `'.$_REQUEST['TABLE_NAME']."`")?>" target='_blank'>#Drop</a> 


<?
/*
//join候補
$a=sql2asc("select * from information_schema.COLUMNS where TABLE_SCHEMA=database() and TABLE_NAME <> '".$_REQUEST['TABLE_NAME']."' and column_name in 
               (select column_name from information_schema.COLUMNS where TABLE_SCHEMA=database() and TABLE_NAME='".$_REQUEST['TABLE_NAME']."' and COLUMN_NAME like '%id%' )",$link,false);
foreach( $a as $row ) echo '<a href="MySQLCrawler.php?mode1=sql&sqltext='.
                            urlencode("select * from ".$_REQUEST['TABLE_NAME'].
                            " inner join ".$row['TABLE_NAME']." on ".$_REQUEST['TABLE_NAME'].".".$row['COLUMN_NAME']."=".$_REQUEST['TABLE_NAME'].".".$row['COLUMN_NAME'] ).'">'.
                            $row['TABLE_NAME'].":".$row['COLUMN_NAME'].'</a> ';
*/
?>

<br/>
<?
$sqlDescTable="select COLUMN_NAME,COLUMN_COMMENT,COLUMN_TYPE,
                        concat(NUMERIC_PRECISION,',',NUMERIC_SCALE) as NUMSIZE,
                        concat(CHARACTER_MAXIMUM_LENGTH,' (',CHARACTER_OCTET_LENGTH,')') as LENGTH_BYTE,
                        concat(CHARACTER_SET_NAME,' - ',COLLATION_NAME) as CHARSET_COLLATION,
                        IS_NULLABLE as NULLable,COLUMN_DEFAULT as DEFAULTVal,COLUMN_KEY ,EXTRA
                          from information_schema.columns 
                          where TABLE_SCHEMA=database() and TABLE_NAME='".$_REQUEST['TABLE_NAME']."'";

//importTSVCSV 入力欄
if ($_REQUEST['mode2']=='importtsvcsv' or $_REQUEST['mode2']=='importtsvcsv_upd'){ ?>
    <form name='upd' method="post" action="?" > 
    <input type=hidden name='mode2' value='<?=$_SESSION['onetimekey']?>'> 
    <input type=hidden name='TABLE_NAME' value='<?=$_REQUEST['TABLE_NAME']?>'> 
	<input type=hidden name='onetimekey' value='<?=$_SESSION['onetimekey']?>'> 
	<?
    echo strHTML("upload TSV CSV ","150% bold span");
	echo strGray("max_post_size=".ini_get("post_max_size"))."<br/>";
	
    $descTableAssoc=sql2asc("describe ".$_REQUEST['TABLE_NAME'], $link,false,false);//pk取得
	echo strBold(count($descTableAssoc))." columns<br/>"; 

    print "<table><tr name='header'>";
    foreach($descTableAssoc as $key=>$row){
      print "<td valign=top>".$row['Field']."&nbsp;&nbsp;&nbsp;<br/>".
	  		"<input type=checkbox name='thru[".$row['Field']."]' value='1' ".($_REQUEST['thru'][$row['Field']] ? "checked" : "")." />thru<br/>".
	  		strGray(
            $row['Key']."<br/>".
            $row['Type']."<br/>".
            $row['Null']."<br/>").
            strRed($row['Extra'])."</td>";

    }
    print "</tr></table>";
     ?>
     <!-- TSV CSV 入力欄 -->

		<textarea name='text' rows='30' cols='120'><?=stripslashMQuote($_REQUEST['text'])?></textarea> 
		<input type=hidden name='mode2' value='importtsvcsv_upd'> 
		<input type=hidden TABLE_NAME='<?=$_REQUEST['TABLE_NAME']?>'> 
		<br/>
		
		<input type=submit name='updtype' value='CSV'> 
		<input type=submit name='updtype' value='TSV'> 
		<input type=checkbox name='double_quote' value='1' <?=$_REQUEST['double_quote'] ? "checked" : "" ?> />"abcde"
		<input type=checkbox name='smallsql' value='1' <?=$_REQUEST['smallsql'] ? "checked" : "" ?> />SmallSQL
		<br/>
	</form>
<?
}

if (isset($tableData)) {

		echo strGray($sqlSelect).' <a href="MySQLCrawler.php?mode1=sql&sqltext='.urlencode($sqlSelect).'">#Select</a> ';
		//update文
		foreach($descTableAssoc as $col) $upd_1[]="\n`".$col["Field"]."`='VAL_".$col["Field"]."'";
		echo "<a href='MySQLCrawler.php?mode1=sql&sqltext=".
		urlencode('-- update '.$_REQUEST['TABLE_NAME']." set ".implode(",",$upd_1)." \n".trim($sqlWhere))."' target='_blank'>#Update</a> ";
		echo"<a href='MySQLCrawler.php?mode1=sql&sqltext=".urlencode('-- delete from `'.$_REQUEST['TABLE_NAME']."`".$sqlWhere)."' target='_blank'>#Delete</a>";
		
		echo " >> ";
	?>

		<?=strBold(count($tableData)).strGray("Rows");?> 
		<?=strGray("Export")?> 
			
		<a href="?mode2=export_csv&sqltext=<?=urlencode($sqlSelect)?>" target='_blank'>CSV</a> 
		<a href="?mode2=export_tsv&sqltext=<?=urlencode($sqlSelect)?>" target='_blank'>TSV</a> 

		<a href="?mode2=export_csv&sqltext=<?=urlencode($sqlNoLimit)?>" target='_blank'>CSVAll</a> 
		<a href="?mode2=export_tsv&sqltext=<?=urlencode($sqlNoLimit)?>" target='_blank'>TSVAll</a> 

		<a href="?mode2=export_insertout&TABLE_NAME=<?=$_REQUEST['TABLE_NAME']?>&sqltext=<?=urlencode($sqlSelect)?>" target='_blank'>InsertStmt</a>
				<a href="?mode2=export_insertout&noAI=true&TABLE_NAME=<?=$_REQUEST['TABLE_NAME']?>&sqltext=<?=urlencode($sqlSelect)?>" target='_blank'>(noAIcol)</a>  
		
		Edit 
		<? 

		//trim作る
		foreach ($tableData[0] as $colname=>$colval) $upd[]="`".$colname."`=trim('**val**' from `".$colname."`)";
		$upd_str="update ".$_REQUEST['TABLE_NAME']." set ".implode(",",$upd).";";

		echo "<a href='MySQLCrawler.php?mode1=sql&mode2=tabcontain&sqltext=".
		urlencode(' select * from `'.$_REQUEST['TABLE_NAME']."`; ".
			"\n".
			"\n -- ".preg_replace("/".preg_quote("**val**")."/"," ",$upd_str).
			"\n -- ".preg_replace("/".preg_quote("**val**")."/","\\r",$upd_str).
			"\n -- ".preg_replace("/".preg_quote("**val**")."/","\\n",$upd_str).
			"\n -- ".preg_replace("/".preg_quote("**val**")."/","\\t",$upd_str).
			"").
			"' target='_blank'>TrimTabCTRL</a>";
	
	foreach ($tableData as &$row) {
		$where_pks=array();
		foreach ($pk as $pk_colname) $where_pks[]="`".$pk_colname."` =\'".$row[$pk_colname]."\' ";
	 	$row['ACTION']='<a safe="'.$setting['safe'].'" href="javascript:document.frmTblSch.sqltext.value='."'delete from `".$_REQUEST['TABLE_NAME']."` ".
	 					"where ".implode(" and ",$where_pks)."';document.frmTblSch.submit();".'">Del</a>';
		
 	}
	
	echo asc2htmlSearch($tableData,$searchvals,"ONE_TABLE_DATA");//TableData
}
mysql_close($link) or die("切断失敗");//db切断
?>

<script type="text/javascript">
    //DB選択のリンクつけ
    var table='<?php if (isset($_REQUEST['TABLE_NAME'])) echo $_REQUEST['TABLE_NAME']; ?>';
    var pk='<?php if (isset($pk)) echo $pk;?>';

    //列を交互に配色 pk列とfk列を色変更
    $('tr[name!="header"]').each( function (ind,obj){ 
        if (ind % 2==0){$(obj).css("background-color","#F0F0F0");}//列を交互配色
    });
	//safeモードなら危険リンクをoffに styleも変える あの内側にspan入れてgrayに
    $('A[safe="true"]').each( function (ind,obj){ 
        $(obj).attr("href","javascript:alert('safe mode');");
        $(obj).html("<span style='color:silver'>"+$(obj).html()+"</span>");
	});

    //1テーブルselectなら
    $('tr[name!="header"]').each( function (ind,obj){ 
        //列にダブルクリックイベント
        $(obj).dblclick(function(event){ //obj=<tr>
            //alert(event.type);
            
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
            //テキストボックス追加
            $(obj).find('td').each(function(ind2,obj2){
                if($(this).attr('name')!='ACTION'){//idが0ACTION以外
                    if ($(this).attr('isnull')=='true'){
                        $(this).html('<input type="text" name="rowvalues['+$(this).attr('name')+'][value]" value=""><br/>'+
                        			$(this).html()+
									'<input type="hidden" name="rowvalues['+$(this).attr('name')+'][isnull]" value="'+$(this).attr('isnull')+'">');
                    }else{
                        $(this).html('<input type="text" name="rowvalues['+$(this).attr('name')+'][value]" value="'+encodeHTML($(this).attr("text"))+'"><br/ >'+
                        			'<input type="hidden" name="rowvalues['+$(this).attr('name')+'][old_value]" value="'+encodeHTML($(this).attr("text"))+'"><br/>'+
									$(this).html()+
									'<input type="hidden" name="rowvalues['+$(this).attr('name')+'][isnull]" value="'+$(this).attr('isnull')+'">');
                    }
                }
            });

            //formタグとsubmitボタン
            $(obj).find('td:first').html('<form name="'+$(obj).attr('id')+'" method="post" action="">'+
                                            '<input type=hidden name=mode2 value="data">'+
                                            '<input type=hidden name=TABLE_NAME value="'+table+'">'+
                                            $(obj).find('td:first').html()+
                                            '<input type=submit value=update>');
            $(obj).find('td:last').html($(obj).find('td:last').html()+'</form>');
        });
    });

function encodeHTML(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); //"
}
</script> 
 
<br/> 
<hr/> 
</body> 
</html> 
 
<?php //functions

function asc2htmlSearch($assoc,$searchvals,$htmlIdTag=null,$htmlEnc=true,$isTrim=false){ //クエリ結果連想配列

	ob_start();
    global $link,$colCharMax;
    $sortcolumn="";
    if (isset($_REQUEST['sortcolumn'])) $sortcolumn=$_REQUEST['sortcolumn'];
    $sortorder="";
    if (isset($_REQUEST['sortorder'])) $sortorder=$_REQUEST['sortorder'];  
    $TABLE_NAME="";
    if (isset($_REQUEST['TABLE_NAME'])) $TABLE_NAME=$_REQUEST['TABLE_NAME'];

	?>
    <div> 
        <form method='post' name='frmTblSch' id='frmTblSch' action='?'> 
              <input type=hidden name='mode2' value='data'> 
              <input type=hidden name='onetimekey' value='<?=$_SESSION['onetimekey']?>'> 

              <input type=hidden name='distinct_col' value=''> 
              <input type=hidden name='minmax_col' value=''>

			  <input type=hidden name='sqltext' value=''> 
			  
              <input type=hidden name='sortcolumn' value='<?=$sortcolumn?>'> 
              <input type=hidden name='sortorder' value='<?=$sortorder?>'>
			  
              <input type=hidden name='TABLE_NAME' value='<?=$TABLE_NAME ?>'> 
              <input type=hidden name='nolimit' value='false'> 
              <input type=hidden name='andor' id='andor' value='and'>
            <a href='javascript:document.frmTblSch.nolimit.value=true;document.frmTblSch.submit();'>noLimit</a>
            <input type='submit' name='submitfrmTblSch' value='Search' style='visibility:hidden;'> 
        <br/>
    <table id='<?=$htmlIdTag?>'><tr id='header' name='header'>
    
    <input type='text' name='allSearch' id='allSearch' placeholder="filterAllCols" size="12" value='' >

    <script type="text/javascript">
        //全列サーチ
        $("form").submit(function(){
            if ($("#allSearch").val()!="") {
                $(":input[type=text]").val('%'+$("#allSearch").val()+'%');
                $("#andor").val("or");
            }
        });
    </script>
    <br/><br/>
    <?
    //列名
    $aryTableDesc=sql2asc('describe '.$_REQUEST['TABLE_NAME'],$link,false,false);//TableData

    foreach($aryTableDesc as $key=>$row) $aryCols[$row['Field']]=$row;//列名をキーにして再生成
    foreach ($aryCols as $key => $value){
        $title=$value['Type']." Null=".$value['Null'];
        $colKey='';
        
        if ($value['Key']!='')  $title.="  Key=".$value['Key'];
        echo "    <th id='${key}' align='left' valign='top' style='border-bottom: 1px solid gray ;'><span title='$title' >${key}</span></th>";
    }
    echo "    <th style='border-bottom: 1px solid gray ;'>action</th>\n";
    echo "  </tr>\n";
    //ジェネレート欄
/*
	echo "  <tr>\n";
    foreach ($aryCols as $key => $value){
        echo "    <td id='${key}' align='left' valign='top' nowrap>";

        if (isset($_REQUEST['generate'])) {
            $generate=$_REQUEST['generate'];
            echo "<input type=text name='generate[".$key."]' value='".htmlentities(stripslashMQuote($generate[$key]),ENT_QUOTES,'UTF-8')."' />";//検索欄
        }else{
            echo "<input type='text' name='generate[".$key."]' value='' />";//検索欄
        }
        echo "&nbsp;</td>\n";
    }
    echo "    <td><input type='hidden' name='onetimekey' value='".$_SESSION['onetimekey']."' /><input type='submit' name='generateCommit' value='Generate' /></td>\n";
    echo "  </tr>\n";
*/

    //***task 空白があると$1,$2がずれる

    //検索欄 dist MM min max 
    echo "  <tr name='header'>";
    foreach ($aryCols as $key => $value){
        echo "    <td id='${key}' align='left' valign='top' nowrap>";

        //列タイプ表示
        $colKey=" ";
        if ($value['Key']=='PRI')  $colKey=strRed("PRI ");
        if ($value['Key']=='MUL')  $colKey="<span style='color:goldenrod;'>MUL </span>";

        $strNull='';
        if ($value['Null']=='YES') $strNull='Null';
        $extra="";
        if ($value["Extra"]) $extra=$value["Extra"];
        echo $colKey." ".$extra."<br/>".strGray($aryCols[$key]['Type']."<br/>".$strNull."<br/>");

        //dist MM desc asc
        $TABLE_NAME="";
        if (isset($_REQUEST['TABLE_NAME'])) $TABLE_NAME=$_REQUEST['TABLE_NAME'];
        ?>
		 
        <a title='distinct' href='javascript:document.frmTblSch.distinct_col.value="<?=$key?>";document.frmTblSch.submit();' target='_blank'>dist</a>&nbsp;
        <a title='minmax' href='javascript:document.frmTblSch.minmax_col.value="<?=$key?>";document.frmTblSch.submit();' target='_blank'>MM</a>&nbsp;
        
        <?
        $desc="desc";
        $asc="asc";
        if ($_REQUEST["sortcolumn"]==$key and $_REQUEST["sortorder"]=="asc") $asc=strHTML($asc,"bold crimson span");
        if ($_REQUEST["sortcolumn"]==$key and $_REQUEST["sortorder"]=="desc") $desc=strHTML($desc,"bold crimson span");        
        ?>
        <a href='#' title='order by desc' onClick='document.frmTblSch.sortcolumn.value="<?=$key?>"
            document.frmTblSch.sortorder.value="desc"; document.frmTblSch.submit();'><?=$desc?></a>&nbsp;
        <a href='#' title='order by asc' onClick='document.frmTblSch.sortcolumn.value="<?=$key?>";
            document.frmTblSch.sortorder.value="asc"; document.frmTblSch.submit();'><?=$asc?></a>
        &nbsp;&nbsp;<br/>
        <?
        //検索ワード
        if ($searchvals) {
            echo "<input type=text name='searchvals[${key}]' placeholder='filter' value='".htmlentities(stripslashMQuote($searchvals[$key]),ENT_QUOTES,'UTF-8')."'>";//検索欄
        }else{
            echo "<input type=text name='searchvals[${key}]' placeholder='filter' value=''>";//検索欄
        }
        echo "&nbsp;</td>\n";
    }
    echo "    <td>&nbsp;</td>\n  </tr>";

    //値
    if (count($assoc)>0) {
        foreach ($assoc as $row){
            echo "  <tr id='header".$count++."'>\n";
            foreach ($row as $key => $value){
                if ($value===null) {//nullと空白を区別
                    $value="<span style='color:gray;font-style:italic;'>null</span>";
                    $nullAttr="isnull=true";
                }else{
                    if ($htmlEnc) $nullAttr="isnull=false";
                    //長い文字省略 文字数計測 > HTMLエンコード > 省略通知html追加
                    if ($isTrim){
                    	$mbchar_count=mb_strlen($value,'UTF-8');
                    	$char_count=strlen($value);
                    	if ($mbchar_count>$colCharMax) $value=mb_substr($value,0,50,'UTF-8');
                    	if ($mbchar_count>$colCharMax) $value.="...".strRed($mbchar_count." char ".$char_count."byte");
					}
                   	if ($key!="ACTION") $value=htmlentities($value,ENT_COMPAT,'utf-8');
                }

                $tdValue=$value;
                if ($key=="ACTION") $tdValue="";
                echo '    <td id="'.$key.$count.'" name="'.$key.'" text="'.$tdValue.
                     '" style="border-bottom: 1px solid silver ;" '.$nullAttr.' align="left" valign="top">'.$value."</td>\n";
            }
            echo "  </tr>\n";
        }
    }
    echo "</table>\n<br/></form></div>";
    $return=ob_get_contents();
    ob_end_clean();
    return $return;
}

echo '$_REQUEST<br/>'."<pre>".print_r($_REQUEST,true)."</pre>";
echo '$_SESSION<br/>'."<pre>".print_r($_SESSION,true)."</pre>";

echo strBold('RAW_POST<br/>');
echo file_get_contents("php://input");

?>