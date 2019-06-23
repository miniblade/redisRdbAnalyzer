# redisRdbAnalyzer
redis 内存分析工具和实战

##概述
    随着内存条价格的下降，内存使用会越来越多。了解系统的内存使用情况是重中之重。
    对于key:value形式的内存存储更是如此。本文使用单词查找树为基本数据结构和算法，
    来分析Redis一次内存快照中(即rdb文件内),所有key的使用情况和重点分布。
    当前已有的rdb分析工具主要有：开源的rdb-tool工具，能将rdb分解为类似redis_rdb.csv文件中格式：
    database,type,key,size_in_bytes,encoding,num_elements,len_largest_element,expiry;
    对于上百万key的redis分析哪种key占内存多少，仍然是个难以处理的过程。即便付费的GUI for Redis,也未能解决，如何定位这百万key中，
    代码中的哪个key占用内存最多。github中其它rdb工具也类似，偏重解析，而非分析。
    
    本文就来解决这个问题，精准定位代码中key占redis内存分布情况，工具化解决redis内存问题

##理论过程
   ###rdb文件：
    rdb文件时redis的(key:value)存储内存使用快照。
    一个 RDB 文件可以分为以下几个部分：
    
    +-------+-------------+-----------+-----------------+-----+-----------+
    | REDIS | RDB-VERSION | SELECT-DB | KEY-VALUE-PAIRS | EOF | CHECK-SUM |
    +-------+-------------+-----------+-----------------+-----+-----------+
    
                          |<-------- DB-DATA ---------->|
    其中
    REDIS:REDIS 五个字符，标识着一个 RDB 文件的开始
    RDB-VERSION:一个四字节长的以字符表示的整数，记录了该文件所使用的 RDB 版本号
    DB-DATA:这个部分在一个 RDB 文件中会出现任意多次，每个 DB-DATA 部分保存着服务器上一个非空数据库的所有数据。
    是我们分析基础依据
    SELECT-DB：这域保存着跟在后面的键值对所属的数据库号码
    KEY-VALUE-PAIRS：因为空的数据库不会被保存到 RDB 文件，所以这个部分至少会包含一个键值对的数据。
    每个键值对的数据使用以下结构来保存：
    
    +----------------------+---------------+-----+-------+
    | OPTIONAL-EXPIRE-TIME | TYPE-OF-VALUE | KEY | VALUE |
    +----------------------+---------------+-----+-------+
    OPTIONAL-EXPIRE-TIME 域是可选的，如果键没有设置过期时间，那么这个域就不会出现； 反之，如果这个域出现的话，那么它记录着键的过期时间，在当前版本的 RDB 中，过期时间是一个以毫秒为单位的 UNIX 时间戳。
    KEY 域保存着键，格式和 REDIS_ENCODING_RAW 编码的字符串对象一样。
    TYPE-OF-VALUE 域记录着 VALUE 域的值所使用的编码， 根据这个域的指示， 程序会使用不同的方式来保存和读取 VALUE 的值。
    VALUE：根据REDIS数据结构选择不同的编码方式，存储redis完整信息。
    EOF：标志着数据库内容的结尾（不是文件的结尾），值为 rdb.h/EDIS_RDB_OPCODE_EOF （255）。
    CHECK-SUM：RDB 文件所有内容的校验和， 一个 uint_64t 类型值。    
   ###rdb_tool解析后格式
    rdb_tool 解析后一般得到key,size_by_byte,expire为核心的格式化文件。
   ###解决思路
    我们依据这个事实：一般使用redis都是有不确定长度的前缀为key，来区分不同的key在代码中使用，类似:
    user:Activity1ByUidAndCityAndDate_%s_%s_%s
    我们定位到user:Activity1ByUidAndCityAndDate_前缀占用内存，即定位到了此key占用内存。
    系统黑盒的处理：
    使用单词查找树记录每个经过的节点的size总和、keyNum、前缀和size占总百分比。输出百分比的排序前n项，一般前100项，就能覆盖80%内存。
    据此就可以解决问题
   ####单词查找树
    https://algs4.cs.princeton.edu/50strings/
##实践
   ###导出rdb文件
    远程导出rdb文件到本地，cpu有消耗，大小有压缩有内存1/8大小，对网络消耗一般可接受。
    不确定可以监视着完成
    redis-cli -h -p -a --rdb dump.rdb
   ###解析二进制rdb文件到csv文件
    安装rdb_tool: 
        pip install rdbtools
    解析：
        rdb -c memory dump.rdb > memory.csv
        格式：database,type,key,size_in_bytes,encoding,num_elements,len_largest_element,expiry
        memory.csv 中keys可能有逗号，这样的key单独过滤掉。
    过滤 memory.csv 为 key,size_in_bytes格式
        cat memory.csv|awk -F, '{print $3 "," $4}'>test.csv
   ###stringSearchTree解析csv文件
        
      php stringSearchTree.php >result10.info 
      注10表示分析了key的前10个字符，若有需要可以分析更多的，不过时间消耗大致n*1.5倍
      格式：level,keyNum,size_by_bytes,prefix,rate
           前缀字符个数,此中统计key数量，总计内存大小，前缀，占总内存百分比
      输出结果：
           cat result10.info |grep -v 'E-'|awk -F, -f filter2.awk |sort -r |head -n 20>result_20
      输出格式：rate level keyNum size_in_bytes prefix rate 
   ###导入mysql中验证
      建表
      CREATE TABLE `redis_rdb` (
        `database` varchar(100) NOT NULL DEFAULT '',
        `type` varchar(255) NOT NULL DEFAULT '',
        `key` varchar(166) NOT NULL DEFAULT '',
        `size_in_bytes` int(11) NOT NULL,
        `encoding` varchar(255) NOT NULL DEFAULT '',
        `num_elements` int(11) NOT NULL,
        `len_largest_element` int(11) NOT NULL,
        `expiry` varchar(255) NOT NULL DEFAULT '',
        PRIMARY KEY (`key`),
        KEY `idx_size_in_bytes` (`size_in_bytes`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
      导入csv到数据库
       load data infile '/var/lib/mysql-files/memory.csv'
       into table redis_rdb
       fields terminated by ',' optionally enclosed by '\'' escaped by '"'
       ;
       统计验证
       #占用总内存
       select sum(size_in_bytes) from redis_rdb;//14 296 231 152
       //7 582 135 824 hot_feeds keys 50810 avg 149 225  5w 0.15M 占用 7G左右空间
       //0.22285222266809 9 67056 3185946888 cache_rec 0.22285222266809
       //3 185 946 888  cache_rec 67056   avg 47 511     6w 0.05M 占用 3G左右空间
       #其中第一大key占用总内存
       select sum(size_in_bytes) from redis_rdb where `key` like 'hot_feeds%';//7582135824
       #使用cat test.csv|grep -e '^hot_feeds'>hotf_feeds.csv,分析对应前缀的主要key
       //hot_feeds_hot_feeds_2019061706_recommend_104080534_pro
       //hot_feeds_hot_feeds_2019061706_recommend_104080534_pro_lock
       #其中第二大key占用总内存
       select sum(size_in_bytes) from redis_rdb where `key` like 'cache_rec%';//3185946888
       //cache_rec_data_0_000155d598d2166f3507ed2ad0b507de
       //cache_rec_data_100008910_6__pro
       //cache_rec_data_100008910_6__pro_lock
       //cache_rec_data_100011780_9_jingxuan_pro
       //cache_rec_data_100011780_9_jingxuan_pro_lock
       //cache_rec_data_100016297_11_tag_唱歌_pro
       //cache_rec_data_100016297_11_tag_唱歌_pro_lock

##总结
    只要是前缀能区别的key，借助此脚本，使用此流程都能分析出内存分布来。
    能观察才能修改，获取日志、快照等，能够观察永远是第一步。
    投入20%的时间在能提高10倍效率的事情上。
###引用
   [1]rdb文件结构 https://redisbook.readthedocs.io/en/latest/internal/rdb.html
   [2]开源的rdb_tool https://github.com/sripathikrishnan/redis-rdb-tools
   [3]付费的redis GUI https://rdbtools.com/
   [4]其它的rdb开源程序 https://github.com/search?q=rdb
   [5]算法第四版 第5章 https://algs4.cs.princeton.edu/50strings/