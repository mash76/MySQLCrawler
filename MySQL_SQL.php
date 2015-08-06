<?
$ymdhis=date("Ymd_His");
$ymdhis_60=date("Ymd_His",time()+60);
$ymdhis_f_60=date("Y-m-d H:i:s",time()+60);

$sqls=array(
"DATE now date time current_timestamp current_date current_time microsecond <br/> unix_timestamp from_unixtime to_days date_add last_day <br/> 
 year month day hour minute second"
=>
<<<EOF
select now(),CURRENT_TIMESTAMP,CURRENT_DATE,CURRENT_TIME,MICROSECOND(now())  ;
select UNIX_TIMESTAMP(),FROM_UNIXTIME(UNIX_TIMESTAMP()),FROM_UNIXTIME(0);
select FROM_UNIXTIME(UNIX_TIMESTAMP()+1),FROM_UNIXTIME(UNIX_TIMESTAMP()-1);
select to_days(now());
select date_add(now(), interval +5 day),date_add(now(), interval -5 day);
select date_add(now(), interval +5 hour),date_add(now(), interval -5 hour);
select date_add(now(), interval +5 minute),date_add(now(), interval -5 minute);
select last_day(now());
select year(now()),month(now()),day(now()),hour(now()),minute(now()),second(now());
 
/* 日付関数は基本的に時刻を無視。時刻関数は日付を無視 
date_add,date_sub,adddate
	UTC_DATE()、UTC_TIME()、UTC_TIMESTAMP()
	DAYOFMONTH,
	curdate,curtime
	MAKETIME(12,15,30),makedate(1009,12,30),makedate(1009,12)
	YEARWEEK()  ,weekday,WEEKOFYEAR(date)
	DATE_FORMAT(date,format)
select adddate('2004-10-01',15), subdate('2004-10-01',15);
http://www.mysql.gr.jp/mysqlml/mysql/msg/10314
*/
EOF
,
"NUMBER format round truncate ceil floor abs mod sqrt power "
=>
<<<EOF
select 123+100,'123'+'100',concat(123,100),concat('123','100');
 
/* format */
    select format(12345678, 0),format(12345678, 2);
/* round,truncate  kiriage,kirisute*/
    select round(1234.56789,2),round(1234.56789,0),
           round(1234.56789,-2),round(1234.56789);
           /* Banker's Rounding 規則(原則四捨五入だが、5 の時は偶数側を選ぶ */
    select truncate(1234.56789, 2),truncate(1234.56789, 0),truncate(1234.56789, -2);
    select ceil(1234.56789),ceil(-1234.56789);
    select floor(1234.56789),floor(-1234.56789);
 
/* absolute */
    select abs(-12),abs(12),abs(0),abs(1234.5678) ,abs(-1234.5678)/* absolute */ ;
/* mod */
    select mod(10,3),mod(10,2),mod(10,1),mod(10,0);
/* exponent (SISUU) */
    select sqrt(1),sqrt(2),sqrt(3),sqrt(4),sqrt(0),sqrt(-1);
    select power(2,2),power(2,3),power(2,4),power(2,4.1),power(2,8),power(2,10),power(2,16),power(2,1),power(2,0),power(2,-1),power(2,-2),power(null,-2);
    select log10(100),log10(1000),log10(10000),log10(1),log10(0),log10(-1),log10(-10),log10(null);
    select log2(2),log2(4),log2(8),log2(16),log2(32),log2(256),log2(65536),log2(0),log2(-1),log2(2-2),log2(-4),log2(null);
EOF
,
"REGEXP "=>
<<<EOF
/* regexp */
    SELECT 'Ban' REGEXP '^Ba+n' , 'Bn' REGEXP '^Ba+n' /* マッチ=1 しなければ0*/;
/* or */
    SELECT 'hello' REGEXP '(hello|world)' , 'world' REGEXP '(hello|world)',
           'goodbye' REGEXP '(hello|world)';
 
/* control code */
    SELECT 'line1 \\n line2' REGEXP '\\n' , 'line1 \n line2' REGEXP '\\t';
    SELECT 'line1 \\t line2' REGEXP '\\n' , 'line1 \t line2' REGEXP '\\t';
    SELECT x'00' REGEXP '1'; ",
	
'HASH md5 sha'=>
"/* md5 sha1 */
    SELECT md5(1),sha(1);
    SELECT md5('1234567890abcdefghij1234567890abcdefghij1234567890abcdefghij') as md5sum_128bit,
           sha('1234567890abcdefghij1234567890abcdefghij1234567890abcdefghij') as shasum_160bit;
/* blank nullstr null */
    SELECT md5(''),sha('');
    SELECT md5(x'00'),sha(x'0a') /* nullstr LF */; 
    SELECT md5(null),sha(null);
 
/*    md5 hex32char=16byte=128bit  sha(sha1) hex40char=20byte=160bit  */;
EOF
,
"STRING "=>
<<<EOF
/*Like match */
select * from information_schema.COLUMNS where COLUMN_NAME LIKE '%character%';/* % _  escape( \% \_ ) not like...*/
SELECT 'abc' LIKE  'ABC';  /* true */
SELECT 'abc' LIKE BINARY 'ABC';  /* false */
SELECT STRCMP(1,2),STRCMP(2,1),STRCMP(2,2);

 select ASCII('A');
 SELECT BIN(12);
SELECT BIT_LENGTH('text');
SELECT CHAR(77,121,83,81,'76'); /* MySQL */

CHAR_LENGTH(str) /* multibyte as 1 char     synonym CHARACTER_LENGTH(str)

 CONCAT(str1,str2,...)
  CONCAT_WS(separator,str1,str2,...)  -- with separator  
  
   CONV(N,from_base,to_base)
   SELECT CONV('a',16,2);   -- 16 >2 
  ELT(N,str1,str2,str3,...)
  LOAD_FILE(file_name)
  LOCATE(substr,str), LOCATE(substr,str,pos)

  EXPORT_SET(bits,on,off[,separator[,number_of_bits]])
   SUBSTRING(str,pos), SUBSTRING(str FROM pos), SUBSTRING(str,pos,len), SUBSTRING(str FROM pos FOR len)
  FIELD(str,str1,str2,str3,...)
   LOWER(str)
   LTRIM(str)
    MAKE_SET(bits,str1,str2,...)
    MID(str,pos,len)   SUBSTRING SYNONYM
   OCT(N)
    SOUNDEX(str)
	SPACE(N)
	UCASE(str)

UCASE=UPPER

 POSITION(substr IN str) / LOCATE(substr,str) 
 FIND_IN_SET(str,strlist)
 CONCAT
 REPLACE(str,from_str,to_str)RTRIM(str)
 INSERT(str,pos,len,newstr)
 INSTR(str,substr)
 LCASE(str) UCASE(str)
 RIGHT(str,len)  LEFT(str,len)
 RPAD(str,len,padstr)    LPAD(str,len,padstr)
 REPLACE(str,from_str,to_str)

 UNHEX(str) <> HEX(int)

 QUOTE(str)
 OCTET_LENGTH(str   OCTET_LENGTH() / LENGTH(str)
 ORD(str)
 FORMAT(X,D)
 REPEAT(str,count)

 BIN,BIT_LENGTH
 CHAR,CHAR_LENGTH

EOF
,
"SERVER version "=>"select version(),",
"LOG,PROFILE,EXPLAIN "=>
<<<EOF
/* query routing plan*/
explain select * from information_schema.engines ;

/* profile query execute time */
set profiling=1;
select * from information_schema.engines ;
show profile;  
EOF
,
"TABLE create drop"=>"aaa",
"INDEX unique normal multi fulltext"=>"aaa",
"ROUTINE create drop "=>
"
CREATE PROCEDURE procedure_${ymdhis} (OUT param1 INT)
BEGIN
  SELECT COUNT(*) INTO param1 FROM information_schema.tables;
END;
/*
call procedure_${ymdhis}(@a);
select @a
*/
"
,
"FUNCTION "=>
"
CREATE FUNCTION func_${ymdhis}() RETURNS int RETURN 1 ;
CREATE FUNCTION funcp1_${ymdhis}(a integer) RETURNS integer RETURN a+a ;
"
,
"TRIGGER before/after   insert/update/delete"=>
"
create trigger TRIGGER001   before / after     insert/delete/update
on TABLE001 for each row
begin
  update TABLE003 set COL01='updated' where COL02=NEW.COL02;  /* after */
  /*insert into LOGTABLE (value) values(OLD.COL02); */ /* before */
end;
/*
OLD.user_id 更新前の値     NEW.user_id 更新後の値  update文の場合に使い分け
deleteはOLD insertはNEWのみ
bafore/afterはFK制約エラーを出さないため？
*/
"
,
"EVENT createEvent dropEvent"=>
"
/* onetime一回 */
create event EVENT_ONE_${ymdhis}
on schedule
   at '${ymdhis_f_60}' /* 指定時刻 */
   AT CURRENT_TIMESTAMP + INTERVAL 3 WEEK + INTERVAL 2 DAY  /* 3 週間と 2 日後 */
   COMMENT 'event comment'
do
   select now();
   /* insert ....*/


/* recurring繰り返し */
create event EVENT_REC_${ymdhis}
on schedule
   every 3 month starts current_timestamp + interval 3 week  /* 1 週間後に開始して 3 か月ごと */
   EVERY 12 HOUR STARTS CURRENT_TIMESTAMP + INTERVAL 30 MINUTE ENDS CURRENT_TIMESTAMP + INTERVAL 4 WEEK  /* 30 分後に開始し 4 週後まで 12 時間ごと */
   COMMENT 'event comment'
   /* endsなければ永久
      毎月n日n時とかは? */
do
   select now();
   /* insert ....*/
"
);

?>