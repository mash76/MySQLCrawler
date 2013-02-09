<?
//unix時刻可日付文字列を受け取り現在日と比べて  あとn日、ｎ日経過と返す
function timeAto($target_time,$now=null){

    if (!$now) $now=time();
    //文字列で来たらunixtimeに置き換え
    if (preg_match("/(:|-)/",$target_time)) $target_time=strtotime($target_time);

    if ($target_time > $now) { //未来
      $seconds=$target_time-$now;
      $ret="あと";
    }else{
      $seconds=abs($target_time-$now);
      $ret="経過";
    }

    if ($seconds>86400) $ret.=floor($seconds/86400)."日 ";
    if ($seconds>3600) $ret.=floor($seconds % 86400 /3600)."時間 ";
    if ($seconds>60) $ret.=floor($seconds % 3600 /60)."分 ";
    return $ret;
}

function schImages($path,$keyword,$filetype_re="",$showcount=50){

    if (!$filetype_re) $filetype_re='(gif$|jpg$|jpeg$|png$)';

    //imageファイルがなければ停止 
    $viewer_php=dirname(__FILE__)."/image.php";
    if (!file_exists($viewer_php)) exit(strRed("no ".$viewer_php));

    $counttmp=0;
    $shell="find ".$path." -type f | grep -E '".$filetype_re."'"; 
    //$shell="find ".$path." -type f"; 

    if (isset($keyword)) $shell.=" | grep '".$keyword."'";
    echo "<font color=gray>".$shell."</font><br/><br/>";
    $strImages=trim(`$shell`);
    $aryImages=explode("\n",$strImages);
    $count=count($aryImages);

    echo $count."件<br/>";

    //検索した画像一覧
    echo "<table border=0><tr>";
    foreach($aryImages as $path){
        echo "<td valign=top><hr/>";
        echo strGray($path)."<br/>";
        echo "<img src='image.php?path=".urlencode($path)."' /><br/>";
        echo "</td>";
        
        if ($counttmp % 5==0) echo "</tr><tr>";
        $counttmp++;
    }
    echo "</tr></table>";
}

function createInsert($record,$table_name){ //assocからinsert文ひとつ生成

	//colname
	foreach ($record as $colname =>$roc) $cols[]="`".$colname."`";
	//value
	$vals=array();
	foreach ($record as $value) $vals[]="'".addslashes($value)."'";
	$return="insert into `".$table_name."` (".implode(",",$cols).") values (".implode(",",$vals).");";
	return $return;
}
function createBigInsert($records,$table_name){ // assoc in assoc からinsert文の束を生成

	//colname
	foreach ($records[0] as $colname =>$roc) $cols[]="`".$colname."`";
	//value
	foreach ($records as $record) {
		$vals=array();
		foreach ($record as $value) $vals[]="'".addslashes($value)."'";
		$return.="insert into ".$table_name."(".implode(",",$cols).") values (".implode(",",$vals).");";
	}
	return $return;
}

function encolorLimitDate($strDate){ //2012-01-01 23:59:59 など日付受け取り、近いもの色付け 1時間以内=赤太字   本日中=赤

	$timeSecondPast=strtotime($strDate)-time();
	if ($timeSecondPast < 10)	return "<span style='background-color:red; color:white; font-weight:bold;'>".$strDate."</span>";
	if ($timeSecondPast < 3600)	return "<span style='color:red; font-weight:bold;'>".$strDate."</span>";
	if (date("Y-m-d",$timeSecondPast) == date("Y-m-d")) return "<span style='font-weight:bold;'>".$strDate."</span>";
	if ($timeSecondPast < 86400) return $strDate;
	return "<span style='color:dimgray;'>".$strDate."</span>";
}

function encolorRecentDate($strDate){ //2012-01-01 23:59:59 など日付受け取り、近いもの色付け 10分内=赤太字 60分内=赤 24H内=黄色

	$timeSecondPast=time()-strtotime($strDate);
	if ($timeSecondPast < 300)	return "<span style='color:red; font-weight:bold; text-decoration:underline;'>".$strDate."</span>";
	elseif ($timeSecondPast < 500)	return "<span style='color:red; font-weight:bold;'>".$strDate."</span>";
	elseif ($timeSecondPast < 1800)	return "<span style='color:red;'>".$strDate."</span>";
	elseif ($timeSecondPast < 3600*2)	return "<span style='font-weight:bold;'>".$strDate."</span>";
	elseif ($timeSecondPast < 86400)	return $strDate;
	return "<span style='color:dimgray;'>".$strDate."</span>";
}

function assocHTMLEnc($assoc,$encode='utf-8'){   //配列in配列をhtmlEncode
	foreach ($assoc as &$row) {
		foreach ($row as &$value) $value=htmlentities($value,ENT_QUOTES,$encode);
	}
	return $assoc;
}

function checkCTRLContain($assoc){ //ctrlコードが入っていたら表示,**task 文字列型の列だけ確認したい。日付文字列などが入っていると面倒。スキーマ渡すか。注意書きで対処
  $return=array();
	foreach ($assoc as &$row){
    $return[]=$row;//旧行
		foreach ($row as $key => &$value){
      $contains="";
      if (strpos($value," ")!==false)  $contains.="SPC ";   //SPC
      if (strpos($value,'\\')!==false) $contains.="5C=YEN ";//CR
      if (strpos($value,"'")!==false)  $contains.="['] ";   //CR
      if (strpos($value,'"')!==false)  $contains.='["] ';   //CR
      if (strpos($value,"\r")!==false) $contains.="CR ";    //CR
      if (strpos($value,"\n")!==false) $contains.="LF ";    //LF
      if (strpos($value,"\t")!==false) $contains.="TAB ";   //Tab
      $value=$contains;
    }
    $return[]=$row;//新行 チェック結果
  }
  return $return;
}

function HexDumpAssoc($assoc,$encode='utf-8'){   //16進ダンプ
  $return=array();
	foreach ($assoc as &$row){
    $return[]=$row;//旧行
		foreach ($row as $key => &$value){
      $value=bin2hex($value)." (Hex ".strlen($value)." byte  ".mb_strlen($value,$encode)." char)";
    }
    $return[]=$row;//新行
  }
  return $return;
}

function sqlSeikei($strSql){   //SQL整形 SQLテキストをselect update insert fromで見やすく改行
 
		//複数のスペースやタブならスペース1に変換してしまうか？
		$strSql=str_ireplace("SELECT ","\nSELECT ",$strSql);
		$strSql=str_ireplace("UPDATE ","\nUPDATE ",$strSql);
		$strSql=str_ireplace("INSERT ","\nINSERT ",$strSql);
 
		$strSql=preg_replace("/\s+WHERE\s+/i","\nWHERE ",$strSql);
		$strSql=preg_replace("/\s+AND\s+/i","\nAND ",$strSql);
		$strSql=preg_replace("/\s+FROM\s+/i","\nFROM ",$strSql);
		$strSql=preg_replace("/\s+ORDER\s+/i","\nORDER ",$strSql);
		$strSql=preg_replace("/\s+GROUP\s+/i","\nGROUP ",$strSql);
		$strSql=preg_replace("/,/",",\n  ",$strSql);
		return $strSql;
}

//ゼロ件なら即時エラー
function sql2val($sql,$link,$flgEcho=true,$limitCount=false,$encode='utf-8'){
	return array_shift(array_shift(sqlToAssoc($sql,$link,$flgEcho,$limitCount,$encode)));
}
function sql2asc($sql,$link,$flgEcho=true,$limitCount=false,$encode='utf-8'){
	return sqlToAssoc($sql,$link,$flgEcho,$limitCount,$encode);
}
function asc2html($assoc,$htmlIdTag=null,$doHtmlEncode=true){
	return assocToHtmlTable($assoc,$htmlIdTag,$doHtmlEncode);	
}
function zeroErr($assoc) { return zeroKenError($assoc);}

function zeroKenError($assoc){
	if (!$assoc) exit(strRed("sql:count 0 :".array_pop($GLOBALS['sql_his'])));
	return $assoc;
}


//SQLから配列:mysql
function sqlToAssoc($sql,$link,$flgEcho=true,$limitCount=false,$encode='utf-8'){ //SQL,DB接続,SQL表示フラグ

	//$linkきてなければエラー
	if (!is_resource($link)) exit(strRed('"'.__FUNCTION__.'" MySQL link erorr'));

    $assoc=array();

    $GLOBALS['sql_his'][]=$sql;
	$timeStart=microtime(true);
	$result = mysql_query($sql, $link) ;
	$timeUsed=round(microtime(true)-$timeStart,3);

	//SQLエラー処理
  	if ($result ===false) {
        echo strGray(jsTrim($sql))."<br/>".
             strRed(mysql_errno($link)."-".mysql_error($link))."<br/>";
		return array();
	}
	//insertUpdateDelete 成功
	if ($result ===true) {
    	if ($flgEcho) echo strGray(jsTrim($sql))." &nbsp; ".mysql_affected_rows($link)."&nbsp;".$timeUsed."&nbsp; <br/>";
		return array();
 	}

	//$result insertならtrue/false  select ならオブジェクト返す
    
	//select結果
	$msgLimitrow="";
	while($row=mysql_fetch_assoc($result)){
		$assoc[]=$row;
		//規定limit件数で配列化終了
		if ($limitCount==true and count($assoc)>=$limitCount){
			$msgLimitrow= strRed("limit");
			break;
		}
	}
	//SQL表示
	mysql_free_result($result);
	if ($flgEcho) echo strGray(jsTrim($sql))." &nbsp;".count($assoc)."$msgLimitrow &nbsp;".$timeUsed."<br/>";
    return $assoc;
}

//SQLから配列
function sqlLiteToAssoc($sql,$link,$flgEcho=true,$limitCount=false,$encode='utf-8'){ //SQL,DB接続,SQL表示フラグ

    $assoc=array();
	$timeStart=microtime(true);
	$result=@sqlite_query($link,$sql,SQLITE_BOTH,$error);
	$timeUsed=round(microtime(true)-$timeStart,3);
	//SQLエラー処理
	if ($error) {
		echo strGray($sql."<br/>");
		echo strRed($error)."<br/>";
		return array();
	}

	//insertUpdateDelete 成功
	if ($result ===true) {
    	if ($flgEcho) echo strGray(jsTrim($sql))." &nbsp;".$timeUsed."&nbsp; <br/>";//mysql_affected_rows($link)."&nbsp;"
		return array();
 	}
	if ($result ===false) {
    	if ($flgEcho) echo strGray(jsTrim($sql))." &nbsp; ".$timeUsed."&nbsp; <br/>";//mysql_affected_rows($link)."&nbsp;"
		echo strRed("false<br/>");
		return array();
 	}

	//select結果
	$msgLimitrow="";
	while($row = sqlite_fetch_array($result, SQLITE_ASSOC)){
		$assoc[]=$row;
		//規定limit件数で配列化終了
		if ($limitCount==true and count($assoc)>=$limitCount){
			$msgLimitrow= strRed("limit");
			break;
		}
	}
	//SQL表示
	//mysql_free_result($result);
	if ($flgEcho) echo strGray(jsTrim($sql))." &nbsp;".count($assoc)."$msgLimitrow &nbsp;".$timeUsed."<br/>";
    return $assoc;
}

//sqlを改行消してトリム クリックすると全文表示
function jsTrim($str,$count=140,$encode='utf-8'){
    //return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
	$trimed=substr($str,0,$count);
	$trimedEnc=htmlentities($trimed,ENT_QUOTES,$encode);
	
	if ($trimed!=$str) $trimedEnc=strRed("trim ").$trimedEnc."......";
	//クリックしたら改行を反映した元ソースを表示
	return "<span title='".htmlentities($str,ENT_QUOTES,$encode)."' onClick='".
	'if ($(this).attr("clicked")!="true") {
		$(this).text($(this).attr("title"));
		$(this).html($(this).html());} '.
		'$(this).attr("clicked","true");'.
	"'>".$trimedEnc."</span>";

}

//配列in配列のvalueをキーワード(ひとつ)で赤に
function assocMarkRed($array,$keyword,$colorName="crimson",$ignore_cols=array()){ //ignore..は 配列で来る

	//valueできたら配列に、配列はキーに
	$ignore_cols=(array)$ignore_cols;
	foreach($ignore_cols as $colname) $ignores[$colname]='ignore';
	var_dump($ignores);
	
	if (!$keyword) return $array;
	
	foreach ($array as &$row){
		foreach ($row as $key=>&$val){
			if (!$ignores[$key]) $val=markRed($val,$keyword,$colorName);
		}
	}
	return $array;
}

//arrayの空白やスペース要素を除去
function trimArray($in_array){
    $keywords=$in_array;
    foreach ($keywords as $key=>$value) {if (trim($value)=="") unset($in_array[$key]);}
    return $in_array;
}

//一項目をキーワード（ひとつ）で赤に
function markRed($val,$keyword,$colorName="crimson"){
	
	if (!$keyword) return $val;
	return preg_replace("/(".preg_quote($keyword).")/i","<span style='color:".$colorName."'>$1</span>",$val);
}

//配列in配列のvalueを文字数で切り詰める
function assocTrimChar($array,$trimcount){

	if (!$trimcount) return $array;

	foreach ($array as &$row){
		foreach ($row as &$val){
			if (strlen($val)>$trimcount){
				$val=substr($val,0,$trimcount)."...".strRed(strlen($val)."byte</span>");
			}
		}
	}
	return $array;
}

//変換：連想配列 > HTMLテーブル  //htmlencode するとtext attrはセットしない
function assocToHtmlTable($assoc,$htmlIdTag=null,$doHtmlEncode=true){ //クエリ結果連想配列

	$return="";
	$nullAttr="";
	if (count($assoc)==0) return;

	$return.= "\n<table name='${htmlIdTag}' id='${htmlIdTag}' >\n  <tr id='header' name='header' >";

	//ヘッダ表示
	foreach ($assoc as $row) {
		foreach ($row as $key => $value){//*task
			$return.= "    <th id='${key}' align='left' style='border-bottom: 2px solid silver;padding-right:10px;'>${key}</th>\n";
		}
		break;
	}
	$return.= "</tr>";
	$count=0;
	foreach ($assoc as $row){
		$return.= "    <tr id='header".$count++."'>\n";
		foreach ($row as $key => &$value){
			$nullAttr="isnull=false";
			if ($value===null) { //nullと空白を区別
				$value="<span style='color:gray;font-style:italic;'>null</span>";
				$nullAttr="isnull=true";
				$tdValue=$value;
			}else{
				$nowrap="";
    	        if ($key!="ACTION" and $doHtmlEncode) $value=htmlentities($value,ENT_COMPAT,'utf-8');
        	    if ($mbchar_count>$colCharMax) $value.="... <font color=red>".$mbchar_count." char ".$char_count."byte</font>";
				$tdValue=htmlentities($value,ENT_COMPAT,'utf-8');
   			}
			
			if ($key=="ACTION") $tdValue="";
			$return.= '        <td id="'.$htmlIdTag.'_'.$key.'_'.$count.'" name="'.$key.'" text="'.$tdValue.'" '.
					 ' style="border-bottom: 1px solid silver ; padding-right:5px;" '.$nullAttr.' align="left" valign="top" nowrap >'.$value."</td>\n";

		}
		$return.= "    </tr>\n";
	}
	$return.= "</table>\n";
	return $return;
}

function addHeaderToAssoc($in_assoc=array()){
    //0件から取る。
    if (!$in_assoc)             return $in_assoc;
    if (count($in_assoc)==0) return $in_assoc;
    
    foreach ($in_assoc as $key =>$row){
        $headerRow['header']=$row;
        foreach($row as $key2=>$row2)  $headerRow['header'][$key2]=$key2;
        break;
    }
    return $headerRow+$in_assoc;
}

function assocToText($in_assoc,$separater="\t"){  //連想配列をCSVやTSVテキスト化
	$returnStr="";
	if (count($in_assoc)==0) {return "";}
	foreach($in_assoc as $row){
		//foreach($row as $key=>$value){
			//要素にタブなどあれば確認
		//}
		$returnStr.=join($separater,$row)."\n";
	}
	return $returnStr."\n";
}

//連想配列をinsert文にレコードで取得
function assocToInsert($in_Records,$in_Tablename,$dbtype='mysql'){
  
    $returnStr="";
    if (count($in_Records)==0) {return "";}
    foreach($in_Records[0] as $key=>$value) {
		if ($dbtype=='mysql') $aryCols[]="`".$key."`";
    	elseif ($dbtype=='sqlite') $aryCols[]=$key;
	}
	$strCols=join(',',$aryCols);
    foreach($in_Records as $row){
        foreach($row as $key=>$value){
            if ($value===null)    {$aryVals[$key]='null';}
            else                {
				if ($dbtype=='mysql') $aryVals[$key]="'".mysql_real_escape_string($value)."'";
				elseif ($dbtype=='sqlite') $aryVals[$key]="'".sqlite_escape_string($value)."'";
			}
        }
        $returnStr.='insert into '.$in_Tablename.' ('.$strCols.') values('.join(',',$aryVals).');'."\n";
    }
    return $returnStr."\n";
}

function assocDump($assoc){  //連想配列をHTMLテーブルでダンプ1階層のみ
	echo "\n<table>";
	foreach ($assoc as $key=>$value)  {
		if (is_array($value)){
			echo "<tr><td style='border-bottom: 1px solid silver;'>".$key."</td><td style='border-bottom: 1px solid silver;'>";
			assocDump($value);
			echo "</td></tr>";
		}else{
			echo "<tr><td style='border-bottom: 1px solid silver;'>".$key."</td><td style='border-bottom: 1px solid silver;'>".$value."</td></tr>";
		}
	}
	echo "</table>";
}


//横棒グラフを追加 全行分の%を表示  引数 records配列 値の列名
function addChartCol($records,$countColName="count"){
	$sum=0;
	foreach ($records as &$row) $sum+=$row[$countColName];
	foreach ($records as &$row) {
		$percent=round($row[$countColName]/$sum,2);
		$pixel=$percent*100;
		$row['chart']="<img src='1dot_crimson.jpg' width=".$pixel." height=5 /><img src='1dot_gray.jpg' width=".(100-$pixel)." height=5 /> ".strGray($percent."%");
	}
	return $records;
}

//プラス・マイナスのグラフを追加   $records  priceColName=価格の元になる列名  maxval=100%の値
function add_chart_col_plus_minus($records,$priceColName,$max_val){
	$max=0;
	//maxの値のサイズ感を得る
	foreach ($records as &$row) if (abs($row[$priceColName]) > $max) $max=abs($row[$priceColName]);
	foreach ($records as &$row) {
		$pixel=round(abs($row[$priceColName])/$max,2)*100;
		//プラスとマイナスで描き分ける。プラス100px + minus 100px
		if ($row[$priceColName] < 0 ){
			
			$row['chart']="<img src='1dot_gray.jpg' width=".(100-$pixel)." height=5 /><img src='1dot_crimson.jpg' width=".($pixel)." height=5 /><img src='1dot_gray.jpg' width=100 height=5 />".strGray($pixel."%");
		}else{
			//グレーで100px ,値+残りグレー
			$row['chart']="<img src='1dot_gray.jpg' width=100 height=5 /><img src='1dot_crimson.jpg' width=".$pixel." height=5 /><img src='1dot_gray.jpg' width=".(100-$pixel)." height=5 /> ".strGray($pixel."% ".$max);
		}
	}
	return $records;
}

//配列の項目内のTABCrLf除去：参照渡し
function stripTabCrLf(&$in_assoc){
      foreach ($in_assoc as $key=>$row){
          foreach ($row as $key2=>$value){
              $value2=preg_replace("/(\t|\r|\n)/","",$value);
              $in_assoc[$key][$key2]=$value2;
          }
      }
      return $in_assoc;
}


function curl_request_simple($url,$showURL=false,$showResult=false){  //urlを受け通信してHTML返す

	if ($showURL) echo strRed("curl")." ".strGray($url)." ";
	$ch = curl_init($url);	//ページ 初期メソッドget
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);	//戻り値 0=画面 true=変数に出力
	curl_setopt($ch, CURLOPT_HEADER, true);	//1=戻り値でhttpヘッダも返す 0=返さない

	$start=microtime(true);
	$result = curl_exec($ch);

	$res_ary=explode("\n",trim($result));
	if ($showURL) echo round(microtime(true)-$start,2)." ".array_shift($res_ary)."  "." ".strRed(strlen($result)).strGray("byte<br/>");
	
	if ($showResult) echo nl2br(htmlentities($result));
	curl_close($ch);  
	return $result;   
}


function runShell($shell,$showDump=true){   //shell実行、コマンド出力

	if ($showDump) echo strGray(nl2br($shell))."<br/>";
	return `$shell`;
}

//自動クオートでなければクオート
function stripslashMQuote($str,$dbname="mysql"){  
	if (get_magic_quotes_gpc()) return stripslashes($str);
	return $str;
}
function addslashesMQuote($str,$dbname="mysql"){
	if ($dbname=="sqlite"){
		if (get_magic_quotes_gpc()) return sqlite_escape_string(stripslashes($str));
		return sqlite_escape_string($str);
	}
  	//mysql
	if (get_magic_quotes_gpc()) return $str;
	return addslashes($str);
}
function mysqlEsacpeMQuote($str){
  if (get_magic_quotes_gpc()) $str=stripslashes($str);
  return mysql_real_escape_string($str);
}

function echoBlack($str) { echo strBlack($str); }
function echoBlue($str)  { echo strBlue($str); }
function echoYellow($str)  { echo echoYellow($str); }
function echoRed($str)  { echo strRed($str); }
function echoGreen($str)  { echo strRed($str); }
function echoDarkred($str)  { echo strDarkred($str); }
function echoGray($str) { echo strGray($str); }
function echoBold($str) { echo strBold($str); }

function strTitle($str) { return "<span style='font-family:arial,helvetica,sans-serif;'>".$str."</span>";}

function str50($str) { return "<span style='font-size:50%;'>".$str."</span>";}
function str80($str) { return "<span style='font-size:80%;'>".$str."</span>";}
function str150($str) { return "<span style='font-size:150%;'>".$str."</span>";}
function str200($str) { return "<span style='font-size:200%;'>".$str."</span>";}
function str300($str) { return "<span style='font-size:300%;'>".$str."</span>";}

function strWhite($str) { return "<span style='color:#FFFFFF;'>".$str."</span>";}
function strPink($str) { return "<span style='color:pink;'>".$str."</span>";}
function strBlack($str) { return "<span style='color:#444444;'>".$str."</span>";}
function strBlue($str) { return "<span style='color:#4444DD;'>".$str."</span>";}
function strYellow($str) { return "<span style='color:yellow;'>".$str."</span>";}
function strRed($str)  { return "<span style='color:red;'>".$str."</span>";}
function strGreen($str) { return "<span style='color:DarkGreen;'>".$str."</span>";}
function strDarkred($str) { return "<span style='color:crimson;'>".$str."</span>";}
function strGray($str) { return "<span style='color:gray;'>".$str."</span>";}
function strOrange($str) { return "<span style='color:orange;'>".$str."</span>";}

function strBold($str) { return "<span style='font-weight:bold;'>".$str."</span>";}

function strCenter($str){ return "<div style='text-align:center;'>".$str."</div>";}
function strBlink($str){ return "<span style='text-decoration:blink;'>".$str."</span>";}

function strHTML($str,$decos){
	//短縮ワードとcssの対応リスト
	$deco_list=array("red"=>"color:red;","blue"=>"color:#4444DD;","crimson"=>"color:crimson;",
			"gray"=>"color:gray;","pink"=>"color:pink;",
			"orange"=>"color:orange;","green"=>"color:green;","purple"=>"color:purple;",
			"blink"=>"text-decoration:blink;","underline"=>"text-decoration:underline;",
			"left"=>"text-align:left;","center"=>"text-align:center;","right"=>"text-align:right;",
			"bold"=>"font-weight:bold;",
			"x-small"=>"font-size:x-small;","medium"=>"font-size:medium;",
			"large"=>"font-size:large;","x-large"=>"font-size:x-large;",
			"arial" =>"font-family:arial,helvetica,sans-serif;"
			);

	$deco_ary_in=explode(" ",$decos);
	$decoStr="";
	$tag='div';
	//css文字列作成  span指定なければdiv  #が交じる文字ならcolor:#nnnnnn
	foreach ($deco_ary_in as $deco) {
		if (trim($deco)!="" and $deco_list[$deco]) $decoStr.=$deco_list[$deco];
		if (trim($deco)=="span") $tag="span";
		//色
		if (strpos($deco,"#")!==false) $decoStr.="color:".trim($deco).";";//#つきなら色名
		//文字サイズ
		if (strpos($deco,"%")!==false) $decoStr.="font-size:".trim($deco).";";// % フォントサイズ
		if (strpos($deco,"px")!==false) $decoStr.="font-size:".trim($deco).";";// px フォントサイズ
	}
	return "<".$tag." style='".$decoStr."'>".$str."</".$tag.">";
}

function indent($str){ return "<blockquote>".$str."</blockquote>";}
?>