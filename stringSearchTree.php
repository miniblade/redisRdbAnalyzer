<?php
/**
 * Created by PhpStorm.
 * User: blade
 * Date: 2019/6/20
 * Time: 上午9:51
 */
$logFile="tree.log";
const RESULT_CSV="result.csv";
const TEST_CSV="test.csv";
const TEST2_CSV="test2.csv";

const CL_LEVEL="level";
const CL_KEY_NUM="keyNum";
const CL_SIZE="size";
const CL_PREFIX="prefix";
const CL_RATE="rate";
file_put_contents($logFile,"begin");
$fileName=RESULT_CSV;

class Node{
    public $info=[];
    public $next=[];
    public function showLine($maxSize,$logFile){
       if(empty($this->info)){
           file_put_contents($logFile,"no Info",FILE_APPEND);
           return "";
       }
       $curSize=$this->info['size'];
       $spaceRate=$curSize/$maxSize;
        echo  $str= implode(',',$this->info).",$spaceRate".PHP_EOL;
       foreach($this->next as $node){
           echo $node->showLine($maxSize,$logFile);
       }
       return "";
   }
}
ini_set('memory_limit','4000M');

//基础单词查找树
//目标输出相似keys数,相似度策略 字符元素
//层级订成100,最多100

$time1=microtime(true);
const MAX_LEVEL=10;
$node=new Node();//TODO ["level"=>0,"c1"=>["level"=>1,"totalSize"=>size,"keyNum"=>num,prefix="prefix","c2"=>[]]]
//输出keys ,size_in_bytes
$lines =file($fileName,FILE_IGNORE_NEW_LINES);
static $count=0;

foreach($lines as $line){
   $array=explode(",",$line);
   $key=$array[0];
   $size=$array[1];
   decodeKeyInRet($node,$key,$size);
   $count++;
   if($count%100==0){
       file_put_contents($logFile,".",FILE_APPEND);
   }
    if($count%10000==0){
        file_put_contents($logFile,"\n\n!",FILE_APPEND);
    }
}

file_put_contents($logFile,"\n\nshowLines!",FILE_APPEND);
echo "showLines".PHP_EOL;
echo "info:".implode( ',',$node->info).";nextNodeNum:".count($node->next).PHP_EOL;
$node->showLine($node->info['size'],$logFile);

function showRet($node,$level=50){
    echo "end".PHP_EOL;
    var_dump($node);
    echo json_encode((array)$node,JSON_UNESCAPED_UNICODE).PHP_EOL;
}
$spendTime=(microtime(true)-$time1)*1000;
echo "sendTime:$spendTime ms".PHP_EOL;

function showRetI($node,$level,$curLevel){
    foreach($node as $info){

    }

}

/**向结果$ret中插入key,记录key size
 * -----------------
 * @param $node
 * @param $key
 * @param $size
 */
function decodeKeyInRet(&$node,$key,$size){
    //用递归的方式
    $len=strlen($key);
    $maxLen=min($len,MAX_LEVEL);
    if(empty($node->info)){
        $node->info=[
            CL_LEVEL=>0,
            CL_KEY_NUM=>1,
            CL_SIZE=>$size,
            CL_PREFIX=>"",
        ];
    }else{
        $oldSize=$node->info[CL_SIZE];
        $oldKeyNum=$node->info[CL_KEY_NUM];
        $node->info[CL_SIZE]=$oldSize+$size;
        $node->info[CL_KEY_NUM]=$oldKeyNum+1;
    }
    putKey($node,1,$maxLen,$key,$len,$size);
}

/**时间复杂度 key数量n,平均长度l= key*l
 * -------------------
 * @param $node   Node 插入到当前数组
 * @param $level   int 当前插入对应层级,start 1,也是当前字符串索引
 * @param $maxLevel int 最大层级
 * @param $key     string key
 * @param $keyLength  int key长度
 * @param $size    int  大小
 */
function putKey(&$node ,$level,$maxLevel,$key,$keyLength,$size){
        if($level>$maxLevel){
            return ;
        }
    //[info=>["level"=>1,"totalSize"=>size,"keyNum"=>num,prefix="prefix"],"c2"=>[]
        if($level>$keyLength){
            //只是保险
            throw new Exception("层级错误，key:$key,level:$level,len:$keyLength");
        }
        $c=$key[$level-1];
        //size
        if(empty($node->next[$c])){
            $newNode=new Node();
            $newNode->info=[
                CL_LEVEL=>$level,
                CL_KEY_NUM=>1,
                CL_SIZE=>$size,
                CL_PREFIX=>substr($key,0,$level)
            ];
            $node->next[$c]=$newNode;
            $curNode=$newNode;
        }else{
            $curNode=$node->next[$c];
            $oldSize=$curNode->info[CL_SIZE];
            $oldKeyNum=$curNode->info[CL_KEY_NUM];
            $curNode->info[CL_SIZE]=$oldSize+$size;
            $curNode->info[CL_KEY_NUM]=$oldKeyNum+1;
        }
        putKey($curNode,$level+1,$maxLevel,$key,$keyLength,$size);
}

