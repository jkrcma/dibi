<?php
 /**
 * dibi - Database Abstraction Layer according to dgx
 * --------------------------------------------------
 *
 * For PHP 5.0.3 or newer
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/dibi/
 * @copyright  Copyright (c) 2005-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE
 * @package    dibi
 * @category   Database
 * @version    0.6 $Revision: 6 $ $Date: 2006-06-07 17:50:32 +0200 (st, 07 VI 2006) $
 */define('DIBI','Version 0.6 $Revision: 6 $');if(version_compare(PHP_VERSION,'5.0.3','<'))die('dibi needs PHP 5.0.3 or newer');
 if(!defined('DIBI'))die();abstract class DibiDriver{protected$config;public$formats=array('NULL'=>"NULL",'TRUE'=>"1",'FALSE'=>"0",'date'=>"'Y-m-d'",'datetime'=>"'Y-m-d H:i:s'",);abstract static public function connect($config);protected function __construct($config){$this->config=$config;}public function getConfig(){return$this->config;}abstract public function query($sql);abstract public function affectedRows();abstract public function insertId();abstract public function begin();abstract public function commit();abstract public function rollback();abstract public function escape($value,$appendQuotes=FALSE);abstract public function quoteName($value);abstract public function getMetaData();} 
 if(!defined('DIBI'))die();if(!interface_exists('Countable',false)){interface Countable{function count();}}abstract class DibiResult implements IteratorAggregate,Countable{const FIELD_TEXT='s',FIELD_BINARY='b',FIELD_BOOL='l',FIELD_INTEGER='i',FIELD_FLOAT='f',FIELD_DATE='d',FIELD_DATETIME='t',FIELD_UNKNOWN='?',FIELD_COUNTER='c';protected$convert;abstract public function seek($row);abstract public function rowCount();abstract public function getFields();abstract public function getMetaData($field);abstract protected function detectTypes();abstract protected function free();abstract protected function doFetch();final public function fetch(){$rec=$this->doFetch();if(!is_array($rec))return FALSE;if($t=$this->convert){foreach($rec as$key=>$value){if(isset($t[$key]))$rec[$key]=$this->convert($value,$t[$key]);}}return$rec;}final function fetchSingle(){$rec=$this->doFetch();if(!is_array($rec))return FALSE;if($t=$this->convert){$value=reset($rec);$key=key($rec);return isset($t[$key])?$this->convert($value,$t[$key]):$value;}return reset($rec);}final function fetchAll(){@$this->seek(0);$rec=$this->fetch();if(!$rec)return array();$assocBy=func_get_args();$arr=array();if(!$assocBy){$value=count($rec)==1?key($rec):NULL;do{$arr[]=$value===NULL?$rec:$rec[$value];}while($rec=$this->fetch());return$arr;}do{foreach($assocBy as$n=>$assoc){$val[$n]=$rec[$assoc];unset($rec[$assoc]);}foreach($assocBy as$n=>$assoc){if($n==0)$tmp=&$arr[$val[$n]];else$tmp=&$tmp[$assoc][$val[$n]];if($tmp===NULL)$tmp=$rec;}}while($rec=$this->fetch());return$arr;}final function fetchPairs($key,$value){@$this->seek(0);$rec=$this->fetch();if(!$rec)return array();$arr=array();do{$arr[$rec[$key]]=$rec[$value];}while($rec=$this->fetch());return$arr;}public function __destruct(){@$this->free();}public function setType($field,$type=NULL){if($field===TRUE)$this->detectTypes();elseif(is_array($field))$this->convert=$field;else$this->convert[$field]=$type;}public function getType($field){return isset($this->convert[$field])?$this->convert[$field]:NULL;}public function convert($value,$type){if($value===NULL||$value===FALSE)return$value;static$conv=array(self::FIELD_TEXT=>'string',self::FIELD_BINARY=>'string',self::FIELD_BOOL=>'bool',self::FIELD_INTEGER=>'int',self::FIELD_FLOAT=>'float',self::FIELD_COUNTER=>'int',);if(isset($conv[$type])){settype($value,$conv[$type]);return$value;}if($type==self::FIELD_DATE)return strtotime($value);if($type==self::FIELD_DATETIME)return strtotime($value);return$value;}public function getIterator($offset=NULL,$count=NULL){return new DibiResultIterator($this,$offset,$count);}public function count(){return$this->rowCount();}}class DibiResultIterator implements Iterator{private$result,$offset,$count,$record,$row;public function __construct(DibiResult$result,$offset=NULL,$count=NULL){$this->result=$result;$this->offset=(int)$offset;$this->count=$count===NULL?2147483647:(int)$count;}public function rewind(){$this->row=0;@$this->result->seek($this->offset);$this->record=$this->result->fetch();}public function key(){return$this->row;}public function current(){return$this->record;}public function next(){$this->record=$this->result->fetch();$this->row++;}public function valid(){return is_array($this->record)&&($this->row<$this->count);}} 
 if(!defined('DIBI'))die();class DibiParser{private$modifier,$hasError,$driver;public function parse($driver,$args){$this->driver=$driver;$mod=&$this->modifier;$mod=false;$this->hasError=false;$command=null;static$condKeys=array('if'=>1,'else'=>1,'end'=>1);$cond=true;$conds=array();$sql=array();$count=count($args);$i=-1;while(++$i<$count){$arg=$args[$i];if(is_string($arg)&&!$mod){$toSkip=strcspn($arg,'`[\'"%');if($toSkip!=strlen($arg))$arg=substr($arg,0,$toSkip).preg_replace_callback('/
                           (?=`|\[|\'|"|%)              ## speed-up
                           (?:
                              `(.+?)`|                  ## 1) `identifier`
                              \[(.+?)\]|                ## 2) [identifier]
                              (\')((?:\'\'|[^\'])*)\'|  ## 3,4) string
                              (")((?:""|[^"])*)"|       ## 5,6) "string"
                              %([a-zA-Z]{1,4})$|             ## 7) right modifier
                              (\'|")                    ## 8) lone-quote
                           )/xs',array($this,'callback'),substr($arg,$toSkip));if($cond)$sql[]=$arg;if(isset($condKeys[$mod])){switch($mod){case'if':$conds[]=$cond;$cond=(bool)$args[++$i];break;case'else':if($conds){$cond=!$cond;break;}case'end':if($conds){$cond=array_pop($conds);break;}$this->hasError=true;$sql[]="**Unexpected condition $mod**";}$mod=false;}continue;}if(!$cond)continue;if(is_array($arg)&&!$mod&&is_string(key($arg))){if(!$command)$command=strtoupper(substr(ltrim($args[0]),0,6));$mod=($command=='INSERT'||$command=='REPLAC')?'V':'S';}$sql[]=$this->formatValue($arg,$mod);$mod=false;}$sql=implode(' ',$sql);if($this->hasError)return new DibiException('Errors during generating SQL',array('sql'=>$sql));return$sql;}private function formatValue($value,$modifier){if(is_array($value)){$vx=$kx=array();switch($modifier){case'S':foreach($value as$k=>$v){list($k,$mod)=explode('%',$k.'%',3);$vx[]=$this->driver->quoteName($k).'='.$this->formatValue($v,$mod);}return implode(', ',$vx);case'V':foreach($value as$k=>$v){list($k,$mod)=explode('%',$k.'%',3);$kx[]=$this->driver->quoteName($k);$vx[]=$this->formatValue($v,$mod);}return'('.implode(', ',$kx).') VALUES ('.implode(', ',$vx).')';default:foreach($value as$v)$vx[]=$this->formatValue($v,$modifier);return implode(', ',$vx);}}if($modifier){if($value instanceof IDibiVariable)return$value->toSql($this->driver,$modifier);if(!is_scalar($value)&&!is_null($value)){$this->hasError=true;return'**Unexpected '.gettype($value).'**';}switch($modifier){case"s":return$this->driver->escape($value,TRUE);case'b':return$value?$this->driver->formats['TRUE']:$this->driver->formats['FALSE'];case'i':case'u':case'd':return(string)(int)$value;case'f':return(string)(float)$value;case'D':return date($this->driver->formats['date'],is_string($value)?strtotime($value):$value);case'T':return date($this->driver->formats['datetime'],is_string($value)?strtotime($value):$value);case'n':return$this->driver->quoteName($value);case'p':return(string)$value;default:$this->hasError=true;return"**Unknown modifier %$modifier**";}}if(is_string($value))return$this->driver->escape($value,TRUE);if(is_int($value)||is_float($value))return(string)$value;if(is_bool($value))return$value?$this->driver->formats['TRUE']:$this->driver->formats['FALSE'];if(is_null($value))return$this->driver->formats['NULL'];if($value instanceof IDibiVariable)return$value->toSql($this->driver);$this->hasError=true;return'**Unexpected '.gettype($value).'**';}private function callback($matches){if($matches[1])return$this->driver->quoteName($matches[1]);if($matches[2])return$this->driver->quoteName($matches[2]);if($matches[3])return$this->driver->escape(strtr($matches[4],array("''"=>"'")),true);if($matches[5])return$this->driver->escape(strtr($matches[6],array('""'=>'"')),true);if($matches[7]){$this->modifier=$matches[7];return'';}if($matches[8]){return'**Alone quote**';$this->hasError=true;}die('this should be never executed');}} 
 if(!defined('DIBI'))die();class DibiException extends Exception{private$info;public function __construct($message,$info=NULL){$this->info=$info;if(isset($info['message']))$message="$message: $info[message]";parent::__construct($message);}public function getSql(){return@$this->info['sql'];}}function is_error($var){return($var===FALSE)||($var instanceof Exception);}  if(function_exists('date_default_timezone_set'))date_default_timezone_set('Europe/Prague');interface IDibiVariable{public function toSQL($driver,$modifier=NULL);}class dibi{static private$registry=array();static private$conn;static private$parser;static public$sql;static public$error;static public$logfile;static public$debug=false;static private$query=array();static public function connect($config,$name='def'){if(!self::$parser)self::$parser=new DibiParser();if(isset(self::$registry[$name]))return new DibiException("Connection named '$name' already exists.");if(empty($config['driver']))return new DibiException('Driver is not specified.');$className="Dibi$config[driver]Driver";  if(!class_exists($className))return new DibiException("Unable to create instance of dibi driver class '$className'.");$conn=call_user_func(array($className,'connect'),$config);if(self::$logfile!=NULL){if(is_error($conn))$msg="Can't connect to DB '$config[driver]': ".$conn->getMessage();else$msg="Successfully connected to DB '$config[driver]'";$f=fopen(self::$logfile,'a');fwrite($f,"$msg\r\n\r\n");fclose($f);}if(is_error($conn)){if(self::$debug)echo'[dibi error] '.$conn->getMessage();return$conn;}self::$conn=self::$registry[$name]=$conn;return TRUE;}static public function isConnected(){return(bool)self::$conn;}static public function getConnection($name=NULL){return$name===NULL?self::$conn:@self::$registry[$name];}static public function activate($name){if(!isset(self::$registry[$name]))return FALSE;self::$conn=self::$registry[$name];return TRUE;}static public function query(){if(!self::$conn)return new DibiException('Dibi is not connected to DB');$args=func_num_args()?func_get_args():self::$query;self::$query=array();self::$sql=self::$parser->parse(self::$conn,$args);if(is_error(self::$sql))return self::$sql;$timer=-microtime(true);$res=self::$conn->query(self::$sql);$timer+=microtime(true);if(is_error($res)){if(self::$debug){echo'[dibi error] '.$res->getMessage();self::dump(self::$sql);}self::$error=$res;}else{self::$error=FALSE;}if(self::$logfile!=NULL){if(is_error($res))$msg=$res->getMessage();elseif($res instanceof DibiResult)$msg='object('.get_class($res).') rows: '.$res->rowCount();else$msg='OK';$f=fopen(self::$logfile,'a');fwrite($f,self::$sql.";\r\n-- Result: $msg"."\r\n-- Takes: ".sprintf('%0.3f',$timer*1000).' ms'."\r\n\r\n");fclose($f);}return$res;}static public function test(){if(!self::$conn)return FALSE;$args=func_num_args()?func_get_args():self::$query;self::$query=array();$sql=self::$parser->parse(self::$conn,$args);if(is_error($sql)){self::dump($sql->getSql());return$sql->getSql();}else{self::dump($sql);return$sql;}}static public function insertId(){if(!self::$conn)return FALSE;return self::$conn->insertId();}static public function affectedRows(){if(!self::$conn)return FALSE;return self::$conn->affectedRows();}static public function dump($sql){static$highlight=array('ALL','DISTINCT','AS','ON','INTO','AND','OR','AS',);static$newline=array('SELECT','UPDATE','INSERT','DELETE','FROM','WHERE','HAVING','GROUP BY','ORDER BY','LIMIT','SET','VALUES','LEFT JOIN','INNER JOIN',);foreach($newline as$word)$sql=preg_replace('#\b'.$word.'\b#',"\n\$0",$sql);$sql=trim($sql);$sql=wordwrap($sql,100);$sql=htmlSpecialChars($sql);$sql=strtr($sql,array("\n"=>'<br />'));foreach($newline as$word)$sql=preg_replace('#\b'.$word.'\b#','<strong style="color:blue">$0</strong>',$sql);foreach($highlight as$word)$sql=preg_replace('#\b'.$word.'\b#','<strong style="color:green">$0</strong>',$sql);$sql=preg_replace('#\*\*.+?\*\*#','<strong style="color:red">$0</strong>',$sql);echo'<pre class="dibi">',$sql,'</pre>';}static public function dumpResult(DibiResult$res){echo'<table class="dump"><tr>';echo'<th>Row</th>';$fieldCount=$res->fieldCount();for($i=0;$i<$fieldCount;$i++){$info=$res->fieldMeta($i);echo'<th>'.htmlSpecialChars($info['name']).'</th>';}echo'</tr>';foreach($res as$row=>$fields){echo'<tr><th>',$row,'</th>';foreach($fields as$field){if(is_object($field))$field=$field->__toString();echo'<td>',htmlSpecialChars($field),'</td>';}echo'</tr>';}echo'</table>';}}
 if(!defined('DIBI'))die();class DibiMySqlDriver extends DibiDriver{private$conn,$insertId=FALSE,$affectedRows=FALSE;public$formats=array('NULL'=>"NULL",'TRUE'=>"1",'FALSE'=>"0",'date'=>"'Y-m-d'",'datetime'=>"'Y-m-d H:i:s'",);public static function connect($config){if(!extension_loaded('mysql'))return new DibiException("PHP extension 'mysql' is not loaded");if(empty($config['host']))$config['host']='localhost';if(@$config['protocol']==='unix')$host=':'.$config['host'];else$host=$config['host'].(empty($config['port'])?'':$config['port']);if(function_exists('ini_set'))$save=ini_set('track_errors',TRUE);$php_errormsg='';if(empty($config['persistent']))$conn=@mysql_connect($host,@$config['username'],@$config['password']);else$conn=@mysql_pconnect($host,@$config['username'],@$config['password']);if(function_exists('ini_set'))ini_set('track_errors',$save);if(!is_resource($conn))return new DibiException("Connecting error",array('message'=>mysql_error()?mysql_error():$php_errormsg,'code'=>mysql_errno(),));if(!empty($config['charset'])){$succ=@mysql_query('SET CHARACTER SET '.$config['charset'],$conn);}if(!empty($config['database'])){if(!@mysql_select_db($config['database'],$conn))return new DibiException("Connecting error",array('message'=>mysql_error($conn),'code'=>mysql_errno($conn),));}$obj=new self($config);$obj->conn=$conn;return$obj;}public function query($sql){$this->insertId=$this->affectedRows=FALSE;$res=@mysql_query($sql,$this->conn);if(is_resource($res))return new DibiMySqlResult($res);if($res===FALSE)return new DibiException("Query error",array('message'=>mysql_error($this->conn),'code'=>mysql_errno($this->conn),'sql'=>$sql,));$this->affectedRows=mysql_affected_rows($this->conn);if($this->affectedRows<0)$this->affectedRows=FALSE;$this->insertId=mysql_insert_id($this->conn);if($this->insertId<1)$this->insertId=FALSE;return TRUE;}public function affectedRows(){return$this->affectedRows;}public function insertId(){return$this->insertId;}public function begin(){return mysql_query('BEGIN',$this->conn);}public function commit(){return mysql_query('COMMIT',$this->conn);}public function rollback(){return mysql_query('ROLLBACK',$this->conn);}public function escape($value,$appendQuotes=FALSE){return$appendQuotes?"'".mysql_real_escape_string($value,$this->conn)."'":mysql_real_escape_string($value,$this->conn);}public function quoteName($value){return'`'.strtr($value,array('.'=>'`.`')).'`';}public function getMetaData(){trigger_error('Meta is not implemented yet.',E_USER_WARNING);}}class DibiMySqlResult extends DibiResult{private$resource,$meta;public function __construct($resource){$this->resource=$resource;}public function rowCount(){return mysql_num_rows($this->resource);}protected function doFetch(){return mysql_fetch_assoc($this->resource);}public function seek($row){return mysql_data_seek($this->resource,$row);}protected function free(){mysql_free_result($this->resource);}public function getFields(){if($this->meta===NULL)$this->createMeta();return array_keys($this->meta);}protected function detectTypes(){if($this->meta===NULL)$this->createMeta();}public function getMetaData($field){if($this->meta===NULL)$this->createMeta();return isset($this->meta[$field])?$this->meta[$field]:FALSE;}private function createMeta(){static$types=array('ENUM'=>self::FIELD_TEXT,'SET'=>self::FIELD_TEXT,'CHAR'=>self::FIELD_TEXT,'VARCHAR'=>self::FIELD_TEXT,'STRING'=>self::FIELD_TEXT,'TINYTEXT'=>self::FIELD_TEXT,'TEXT'=>self::FIELD_TEXT,'MEDIUMTEXT'=>self::FIELD_TEXT,'LONGTEXT'=>self::FIELD_TEXT,'BINARY'=>self::FIELD_BINARY,'VARBINARY'=>self::FIELD_BINARY,'TINYBLOB'=>self::FIELD_BINARY,'BLOB'=>self::FIELD_BINARY,'MEDIUMBLOB'=>self::FIELD_BINARY,'LONGBLOB'=>self::FIELD_BINARY,'DATE'=>self::FIELD_DATE,'DATETIME'=>self::FIELD_DATETIME,'TIMESTAMP'=>self::FIELD_DATETIME,'TIME'=>self::FIELD_DATETIME,'BIT'=>self::FIELD_BOOL,'YEAR'=>self::FIELD_INTEGER,'TINYINT'=>self::FIELD_INTEGER,'SMALLINT'=>self::FIELD_INTEGER,'MEDIUMINT'=>self::FIELD_INTEGER,'INT'=>self::FIELD_INTEGER,'INTEGER'=>self::FIELD_INTEGER,'BIGINT'=>self::FIELD_INTEGER,'FLOAT'=>self::FIELD_FLOAT,'DOUBLE'=>self::FIELD_FLOAT,'REAL'=>self::FIELD_FLOAT,'DECIMAL'=>self::FIELD_FLOAT,'NUMERIC'=>self::FIELD_FLOAT,);$count=mysql_num_fields($this->resource);$this->meta=$this->convert=array();for($index=0;$index<$count;$index++){$info['native']=$native=strtoupper(mysql_field_type($this->resource,$index));$info['flags']=explode(' ',mysql_field_flags($this->resource,$index));$info['length']=mysql_field_len($this->resource,$index);$info['table']=mysql_field_table($this->resource,$index);if(in_array('auto_increment',$info['flags']))$info['type']=self::FIELD_COUNTER;else{$info['type']=isset($types[$native])?$types[$native]:self::FIELD_UNKNOWN;}$name=mysql_field_name($this->resource,$index);$this->meta[$name]=$info;$this->convert[$name]=$info['type'];}}}
 if(!defined('DIBI'))die();class DibiMySqliDriver extends DibiDriver{private$conn,$insertId=FALSE,$affectedRows=FALSE;public$formats=array('NULL'=>"NULL",'TRUE'=>"1",'FALSE'=>"0",'date'=>"'Y-m-d'",'datetime'=>"'Y-m-d H:i:s'",);public static function connect($config){if(!extension_loaded('mysqli'))return new DibiException("PHP extension 'mysqli' is not loaded");if(empty($config['host']))$config['host']='localhost';$conn=@mysqli_connect($config['host'],@$config['username'],@$config['password'],@$config['database'],@$config['port']);if(!$conn)return new DibiException("Connecting error",array('message'=>mysqli_connect_error(),'code'=>mysqli_connect_errno(),));if(!empty($config['charset']))mysqli_query($conn,'SET CHARACTER SET '.$config['charset']);$obj=new self($config);$obj->conn=$conn;return$obj;}public function query($sql){$this->insertId=$this->affectedRows=FALSE;$res=@mysqli_query($this->conn,$sql);if(is_object($res))return new DibiMySqliResult($res);if($res===FALSE)return new DibiException("Query error",$this->errorInfo($sql));$this->affectedRows=mysqli_affected_rows($this->conn);if($this->affectedRows<0)$this->affectedRows=FALSE;$this->insertId=mysqli_insert_id($this->conn);if($this->insertId<1)$this->insertId=FALSE;return TRUE;}public function affectedRows(){return$this->affectedRows;}public function insertId(){return$this->insertId;}public function begin(){return mysqli_autocommit($this->conn,FALSE);}public function commit(){$ok=mysqli_commit($this->conn);mysqli_autocommit($this->conn,TRUE);return$ok;}public function rollback(){$ok=mysqli_rollback($this->conn);mysqli_autocommit($this->conn,TRUE);return$ok;}private function errorInfo($sql=NULL){return array('message'=>mysqli_error($this->conn),'code'=>mysqli_errno($this->conn),'sql'=>$sql,);}public function escape($value,$appendQuotes=FALSE){return$appendQuotes?"'".mysqli_real_escape_string($this->conn,$value)."'":mysqli_real_escape_string($this->conn,$value);}public function quoteName($value){return'`'.strtr($value,array('.'=>'`.`')).'`';}public function getMetaData(){trigger_error('Meta is not implemented yet.',E_USER_WARNING);}}class DibiMySqliResult extends DibiResult{private$resource,$meta;public function __construct($resource){$this->resource=$resource;}public function rowCount(){return mysqli_num_rows($this->resource);}protected function doFetch(){return mysqli_fetch_assoc($this->resource);}public function seek($row){return mysqli_data_seek($this->resource,$row);}protected function free(){mysqli_free_result($this->resource);}public function getFields(){if($this->meta===NULL)$this->createMeta();return array_keys($this->meta);}protected function detectTypes(){if($this->meta===NULL)$this->createMeta();}public function getMetaData($field){if($this->meta===NULL)$this->createMeta();return isset($this->meta[$field])?$this->meta[$field]:FALSE;}private function createMeta(){static$types=array(MYSQLI_TYPE_FLOAT=>self::FIELD_FLOAT,MYSQLI_TYPE_DOUBLE=>self::FIELD_FLOAT,MYSQLI_TYPE_DECIMAL=>self::FIELD_FLOAT,MYSQLI_TYPE_TINY=>self::FIELD_INTEGER,MYSQLI_TYPE_SHORT=>self::FIELD_INTEGER,MYSQLI_TYPE_LONG=>self::FIELD_INTEGER,MYSQLI_TYPE_LONGLONG=>self::FIELD_INTEGER,MYSQLI_TYPE_INT24=>self::FIELD_INTEGER,MYSQLI_TYPE_YEAR=>self::FIELD_INTEGER,MYSQLI_TYPE_GEOMETRY=>self::FIELD_INTEGER,MYSQLI_TYPE_DATE=>self::FIELD_DATE,MYSQLI_TYPE_NEWDATE=>self::FIELD_DATE,MYSQLI_TYPE_TIMESTAMP=>self::FIELD_DATETIME,MYSQLI_TYPE_TIME=>self::FIELD_DATETIME,MYSQLI_TYPE_DATETIME=>self::FIELD_DATETIME,MYSQLI_TYPE_ENUM=>self::FIELD_TEXT,MYSQLI_TYPE_SET=>self::FIELD_TEXT,MYSQLI_TYPE_STRING=>self::FIELD_TEXT,MYSQLI_TYPE_VAR_STRING=>self::FIELD_TEXT,MYSQLI_TYPE_TINY_BLOB=>self::FIELD_BINARY,MYSQLI_TYPE_MEDIUM_BLOB=>self::FIELD_BINARY,MYSQLI_TYPE_LONG_BLOB=>self::FIELD_BINARY,MYSQLI_TYPE_BLOB=>self::FIELD_BINARY,);$count=mysqli_num_fields($this->resource);$this->meta=$this->convert=array();for($index=0;$index<$count;$index++){$info=(array)mysqli_fetch_field_direct($this->resource,$index);$native=$info['native']=$info['type'];if($info['flags']&MYSQLI_AUTO_INCREMENT_FLAG)$info['type']=self::FIELD_COUNTER;else{$info['type']=isset($types[$native])?$types[$native]:self::FIELD_UNKNOWN;}$this->meta[$info['name']]=$info;$this->convert[$info['name']]=$info['type'];}}}
 if(!defined('DIBI'))die();class DibiOdbcDriver extends DibiDriver{private$conn,$affectedRows=FALSE;public$formats=array('NULL'=>"NULL",'TRUE'=>"-1",'FALSE'=>"0",'date'=>"#m/d/Y#",'datetime'=>"#m/d/Y H:i:s#",);public static function connect($config){if(!extension_loaded('odbc'))return new DibiException("PHP extension 'odbc' is not loaded");if(@$config['persistent'])$conn=@odbc_pconnect($config['database'],$config['username'],$config['password']);else$conn=@odbc_connect($config['database'],$config['username'],$config['password']);if(!is_resource($conn))return new DibiException("Connecting error",array('message'=>odbc_errormsg(),'code'=>odbc_error(),));$obj=new self($config);$obj->conn=$conn;return$obj;}public function query($sql){$this->affectedRows=FALSE;$res=@odbc_exec($this->conn,$sql);if(is_resource($res))return new DibiOdbcResult($res);if($res===FALSE)return new DibiException("Query error",$this->errorInfo($sql));$this->affectedRows=odbc_num_rows($this->conn);if($this->affectedRows<0)$this->affectedRows=FALSE;return TRUE;}public function affectedRows(){return$this->affectedRows;}public function insertId(){return FALSE;}public function begin(){return odbc_autocommit($this->conn,FALSE);}public function commit(){$ok=odbc_commit($this->conn);odbc_autocommit($this->conn,TRUE);return$ok;}public function rollback(){$ok=odbc_rollback($this->conn);odbc_autocommit($this->conn,TRUE);return$ok;}private function errorInfo($sql=NULL){return array('message'=>odbc_errormsg($this->conn),'code'=>odbc_error($this->conn),'sql'=>$sql,);}public function escape($value,$appendQuotes=FALSE){$value=str_replace("'","''",$value);return$appendQuotes?"'".$value."'":$value;}public function quoteName($value){return'['.strtr($value,array('.'=>'].[')).']';}public function getMetaData(){trigger_error('Meta is not implemented yet.',E_USER_WARNING);}}class DibiOdbcResult extends DibiResult{private$resource,$meta,$row=0;public function __construct($resource){$this->resource=$resource;}public function rowCount(){return odbc_num_rows($this->resource);}protected function doFetch(){return odbc_fetch_array($this->resource,$this->row++);}public function seek($row){$this->row=$row;}protected function free(){odbc_free_result($this->resource);}public function getFields(){if($this->meta===NULL)$this->createMeta();return array_keys($this->meta);}protected function detectTypes(){if($this->meta===NULL)$this->createMeta();}public function getMetaData($field){if($this->meta===NULL)$this->createMeta();return isset($this->meta[$field])?$this->meta[$field]:FALSE;}private function createMeta(){if($this->meta!==NULL)return$this->meta;static$types=array('CHAR'=>self::FIELD_TEXT,'COUNTER'=>self::FIELD_COUNTER,'VARCHAR'=>self::FIELD_TEXT,'LONGCHAR'=>self::FIELD_TEXT,'INTEGER'=>self::FIELD_INTEGER,'DATETIME'=>self::FIELD_DATETIME,'CURRENCY'=>self::FIELD_FLOAT,'BIT'=>self::FIELD_BOOL,'LONGBINARY'=>self::FIELD_BINARY,'SMALLINT'=>self::FIELD_INTEGER,'BYTE'=>self::FIELD_INTEGER,'BIGINT'=>self::FIELD_INTEGER,'INT'=>self::FIELD_INTEGER,'TINYINT'=>self::FIELD_INTEGER,'REAL'=>self::FIELD_FLOAT,'DOUBLE'=>self::FIELD_FLOAT,'DECIMAL'=>self::FIELD_FLOAT,'NUMERIC'=>self::FIELD_FLOAT,'MONEY'=>self::FIELD_FLOAT,'SMALLMONEY'=>self::FIELD_FLOAT,'FLOAT'=>self::FIELD_FLOAT,'YESNO'=>self::FIELD_BOOL,);$count=odbc_num_fields($this->resource);$this->meta=$this->convert=array();for($index=1;$index<=$count;$index++){$native=strtoupper(odbc_field_type($this->resource,$index));$name=odbc_field_name($this->resource,$index);$this->meta[$name]=array('type'=>isset($types[$native])?$types[$native]:self::FIELD_UNKNOWN,'native'=>$native,'length'=>odbc_field_len($this->resource,$index),'scale'=>odbc_field_scale($this->resource,$index),'precision'=>odbc_field_precision($this->resource,$index),);$this->convert[$name]=$this->meta[$name]['type'];}}}
 if(!defined('DIBI'))die();class DibiSqliteDriver extends DibiDriver{private$conn,$insertId=FALSE,$affectedRows=FALSE;public$formats=array('NULL'=>"NULL",'TRUE'=>"1",'FALSE'=>"0",'date'=>"'Y-m-d'",'datetime'=>"'Y-m-d H:i:s'",);public static function connect($config){if(!extension_loaded('sqlite'))return new DibiException("PHP extension 'sqlite' is not loaded");if(empty($config['database']))return new DibiException("Database must be specified");$errorMsg='';if(empty($config['persistent']))$conn=@sqlite_open($config['database'],@$config['mode'],$errorMsg);else$conn=@sqlite_popen($config['database'],@$config['mode'],$errorMsg);if(!$conn)return new DibiException("Connecting error",array('message'=>$errorMsg,));$obj=new self($config);$obj->conn=$conn;return$obj;}public function query($sql){$this->insertId=$this->affectedRows=FALSE;$errorMsg='';$res=@sqlite_query($this->conn,$sql,SQLITE_ASSOC,$errorMsg);if($res===FALSE)return new DibiException("Query error",array('message'=>$errorMsg,'sql'=>$sql,));if(is_resource($res))return new DibiSqliteResult($res);$this->affectedRows=sqlite_changes($this->conn);if($this->affectedRows<0)$this->affectedRows=FALSE;$this->insertId=sqlite_last_insert_rowid($this->conn);if($this->insertId<1)$this->insertId=FALSE;return TRUE;}public function affectedRows(){return$this->affectedRows;}public function insertId(){return$this->insertId;}public function begin(){return sqlite_query($this->conn,'BEGIN');}public function commit(){return sqlite_query($this->conn,'COMMIT');}public function rollback(){return sqlite_query($this->conn,'ROLLBACK');}public function escape($value,$appendQuotes=FALSE){return$appendQuotes?"'".sqlite_escape_string($value)."'":sqlite_escape_string($value);}public function quoteName($value){return$value;}public function getMetaData(){trigger_error('Meta is not implemented yet.',E_USER_WARNING);}}class DibiSqliteResult extends DibiResult{private$resource,$meta;public function __construct($resource){$this->resource=$resource;}public function rowCount(){return sqlite_num_rows($this->resource);}protected function doFetch(){return sqlite_fetch_array($this->resource,SQLITE_ASSOC);}public function seek($row){return sqlite_seek($this->resource,$row);}protected function free(){}public function getFields(){if($this->meta===NULL)$this->createMeta();return array_keys($this->meta);}protected function detectTypes(){if($this->meta===NULL)$this->createMeta();}public function getMetaData($field){if($this->meta===NULL)$this->createMeta();return isset($this->meta[$field])?$this->meta[$field]:FALSE;}private function createMeta(){$count=sqlite_num_fields($this->resource);$this->meta=$this->convert=array();for($index=0;$index<$count;$index++){$name=sqlite_field_name($this->resource,$index);$this->meta[$name]=array('type'=>self::FIELD_UNKNOWN);$this->convert[$name]=self::FIELD_UNKNOWN;}}}?>