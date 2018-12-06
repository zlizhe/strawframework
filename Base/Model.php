<?php
namespace Strawframework\Base;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\DeleteResult;
use MongoDB\InsertManyResult;
use MongoDB\InsertOneResult;
use MongoDB\UpdateResult;
use Strawframework\Db\Mongodb;
use Strawframework\Db\Mysql;
use Strawframework\Straw, Strawframework\Protocol\Db;

/**
 * 模型基类
 * 初始化数据库配置
 * Class Model
 * @package library
 */
class Model extends Straw implements Db{

    /**
     * 当前数据库对象
     * @var Mongodb | Mysql
     */
    protected $db;

    //数据库名
    protected $dbName;

    //数据库连接别名
    protected $dbTag;

    //当前操作表
    protected $table;

    //当前表前缀
    protected $pre;

    //当前配置 -> database
    protected static $dbArr = [];

    /**
     * 配置数据库 懒连接
     * Model constructor.
     *
     * @param null|string $dbTag
     */
    public function __construct(? string $dbTag = DEFAULT_DB) {
        parent::__construct();

        // 获取数据库操作对象
        if (!self::$dbArr[$dbTag]) {
            self::$dbArr[$dbTag] = Straw::$config['databases'][$dbTag];
        }

        $this->dbTag = $dbTag;
        //设置当前使用的db
        $this->dbName = Straw::$config['databases'][$dbTag]['DB_NAME'];

        //表前缀设置
        if (is_null($this->pre)) {
            $pre = self::$dbArr[$dbTag]['DB_PREFIX'] ?: '';
        }else{
            $pre = $this->pre;
        }

        //表名
        if (is_null($this->table)) {
            $this->table = strtolower(end(explode('\\', get_called_class())));
        }

        //设置最终表名
        if ($pre && strpos($this->table, $pre) !== 0) {
            $this->table = $pre . $this->table;
        }
    }

    //主从数据库
    private $dbArray = [];

    /**
     * 连接 数据库
     * @param null|string $type read | write
     *
     * @throws \Exception
     */
    private function _getConnect(?string $type = 'read'): void {

        //引入 对应 db 的库文件
        $dbClass = self::$dbArr[$this->dbTag]['DB_TYPE'];

        //驱动是否存在
        // db namespace
        $dbClass = '\\Strawframework\\Db\\' . $dbClass;

        if (FALSE === class_exists($dbClass))
            throw new \Exception(sprintf('Database driver %s not found.', $dbClass));

        /**
         * 支持分布式数据库配置
         * 1台读服务器 + 多台写服务器配置
         */
        $hostArray = explode(',', self::$dbArr[$this->dbTag]['DB_HOST']);
        //超过1个数据库集
        if (count($hostArray) > 1) {
            $portArray = explode(',', self::$dbArr[$this->dbTag]['DB_PORT']);
            $userArray = explode(',', self::$dbArr[$this->dbTag]['DB_USER']);
            $pwdArray = explode(',', self::$dbArr[$this->dbTag]['DB_PWD']);
            foreach ($hostArray as $key => $value) {
                $this->dbArray[] = [
                    'host'     => $value,
                    'port'     => $portArray[$key] ?: $portArray[0],
                    'username' => $userArray[$key] ?: $userArray[0],
                    'password' => $pwdArray[$key] ?: $pwdArray[0],
                    'dbname'   => $this->dbName,
                    'charset'  => self::$dbArr[$this->dbTag]['DB_CHARSET'] ?? 'UTF8',
                    'prefix'  => self::$dbArr[$this->dbTag]['DB_PREFIX'] ?? ''
                ];
            }
            unset($portArray, $userArray, $pwdArray);

            //读写分离
            if (TRUE == self::$dbArr[$this->dbTag]['WRITE_MASTER']) {

                //第一台数据库为 写 其他为读
                if ('write' == $type) {
                    $this->db = new $dbClass($this->dbArray[0]);
                } else {
                    $readArray = $this->dbArray;
                    //去除写数据库
                    unset($readArray[0]);
                    //打乱所有读数据库集
                    shuffle($readArray);
                    $this->db = new $dbClass($readArray[0]);
                }
            } else {

                //随机读写
                $randDb = shuffle($this->dbArray);
                $this->db = new $dbClass($randDb[0]);
            }


        } else {

            //单个数据库集
            //db obj
            $this->db = new $dbClass([
                                         'host'     => self::$dbArr[$this->dbTag]['DB_HOST'],
                                         'port'     => self::$dbArr[$this->dbTag]['DB_PORT'],
                                         'username' => self::$dbArr[$this->dbTag]['DB_USER'],
                                         'password' => self::$dbArr[$this->dbTag]['DB_PWD'],
                                         'dbname'   => $this->dbName,
                                         'charset'  => self::$dbArr[$this->dbTag]['DB_CHARSET'] ?? 'UTF8',
                                         'prefix'  => self::$dbArr[$this->dbTag]['DB_PREFIX'] ?? ''
                                     ]);
        }
        //选择待操作表
        $this->db->setTable($this->table);
    }

    //查询条件使用 连贯操作
    //连贯操作 数组
    private $_modelData = [];

    // 连贯操作 where 语句查询
    // * @param string|array|object $query  查询条件
    public function query($where = []): Model {

        $this->_modelData['query'] = $where;

        return $this;
    }

    // 连贯操作 绑定数据
    // 开始绑定属性至 :propName
    public function data(array $data): Model {

        if (!empty($data)) {
            $this->_modelData['data'] = $data;
        }

        return $this;
    }

    // 连贯操作 需要查询的字段
    // * @param string|array $field  需要查询的字段,字符串或者数组
    public function field($field = ''): Model {

        $this->_modelData['field'] = $field;

        return $this;
    }

    // 连贯操作 order 条件
    // * @param string|array $order  排序条件
    public function order($order): Model {

        if ($order) {
            $this->_modelData['order'] = $order;
        }

        return $this;
    }

    // 连贯操作  offset 量
    // * @param int          $offset 数据偏移量
    public function offset(int $offset): Model {
        if ($offset) {
            $this->_modelData['offset'] = $offset;
        }

        return $this;
    }

    // 连贯操作  limit 量
    public function limit(int $limit): Model {
        if ($limit) {
            $this->_modelData['limit'] = $limit;
        }

        return $this;
    }

    //添加其他选项
    public function options(array $options): Model{
        if (!empty($options))
            $this->_modelData['options'] = $options;
        return $this;
    }

    // 连贯操作  cache key
    public function cache($cacheKey, ?int $exp = DEFAULT_CACHEEXPIRE ?? null): Model {

        //缓存时间为 0 或没有缓存 key 时不缓存内容
        if ($exp) {

            $this->_modelData['exp'] = (int)$exp;
            if ($cacheKey) {
                $this->_modelData['cacheKey'] = $cacheKey;
            }
        }

        return $this;
    }


    //为可空字段赋值
    private function _setCanEmpty(array $data): void {

        foreach ($data as $key => $value) {
            if (!isset($this->_modelData[$key])) {
                $this->_modelData[$key] = $value ?? '';
            }
        }
    }

    /**
     * 写入新数据 $data $dvo or [$dvo,$dvo2]
     * @param array|object $data
     *
     * @return bool|mixed|InsertManyResult|InsertOneResult
     */
    public function insert($data) {
        $this->_getConnect('write');
        $this->_setCanEmpty(['options' => []]);

        $result = $this->db->insert($data, $this->_modelData['options']);
        //取到数据 清空条件
        $this->_modelData = [];

        return $result;
    }

    /**
     * 根据条件查找一条
     * @return array|mixed|object|null
     */
    public function getOne() {
        $this->_getConnect('read');

        $this->_setCanEmpty(['query' => [], 'data' => [], 'options' => [], 'field' => '', 'cacheKey' => '', 'exp' => DEFAULT_CACHEEXPIRE ?? null]);

        if (!$this->_modelData['query'])
            throw new \Exception('Query is empty.');

        //自动生成 cachekey
        if (TRUE === $this->_modelData['cacheKey']) {
            $this->_modelData['cacheKey'] = md5($this->table . __METHOD__ . json_encode($this->_modelData));
        }
        if ($this->_modelData['cacheKey']) {
            //有缓存 数据优先使用
            $cacheRes = json_decode(Cache::get($this->_modelData['cacheKey']), TRUE);
            if ($cacheRes) {
                //取到数据 清空条件
                $this->_modelData = [];

                return $cacheRes;
            }
        }

        $result = $this->db->getOne($this->_modelData['query'], $this->_modelData['field'], $this->_modelData['data'], $this->_modelData['options']);

        if ($this->_modelData['cacheKey']) {
            Cache::set($this->_modelData['cacheKey'], json_encode($result), $this->_modelData['exp']);
        }

        //取到数据 清空条件
        $this->_modelData = [];

        return $result;
    }

    /**
     * 查询全部
     * @var Mongodb | Mysql
     */
    public function getAll() {
        $this->_getConnect('read');

        $this->_setCanEmpty(['query' => [], 'data' => [], 'options' => [], 'field' => '', 'order' => '', 'offset' => NULL, 'limit' => NULL, 'cacheKey' => '', 'exp' => DEFAULT_CACHEEXPIRE ?? null]);

        //自动生成 cachekey
        if (TRUE === $this->_modelData['cacheKey']) {
            $this->_modelData['cacheKey'] = md5($this->table . __METHOD__ . json_encode($this->_modelData));
        }
        if ($this->_modelData['cacheKey']) {
            //有缓存 数据优先使用
            $cacheRes = json_decode(Cache::get($this->_modelData['cacheKey']), TRUE);
            if ($cacheRes) {
                //取到数据 清空条件
                $this->_modelData = [];

                return $cacheRes;
            }
        }

        $result = $this->db->getAll($this->_modelData['query'], $this->_modelData['field'], $this->_modelData['order'], $this->_modelData['offset'], $this->_modelData['limit'], $this->_modelData['data'], $this->_modelData['options']);

        if ($this->_modelData['cacheKey']) {
            Cache::set($this->_modelData['cacheKey'], json_encode($result), $this->_modelData['exp']);
        }

        //取到数据 清空条件
        $this->_modelData = [];

        return $result;
    }

    /**
     * 取条数
     * @var Mongodb | Mysql
     * @param string $countField
     *
     * @return int
     */
    public function count($countField = '*') {
        $this->_getConnect('read');

        $this->_setCanEmpty(['query' => [], 'data' => [], 'options' => [], 'cacheKey' => '', 'exp' => DEFAULT_CACHEEXPIRE ?? null]);

        //自动生成 cachekey
        if (TRUE === $this->_modelData['cacheKey']) {
            $this->_modelData['cacheKey'] = md5($this->table . __METHOD__ . json_encode($this->_modelData['query']) . json_encode($countField) . json_encode($this->_modelData['data']) . json_encode($this->_modelData['options']));
        }
        if ($this->_modelData['cacheKey']) {
            //有缓存 数据优先使用
            $cacheRes = json_decode(Cache::get($this->_modelData['cacheKey']), TRUE);
            if ($cacheRes) {
                //取到数据 清空条件
                $this->_modelData = [];

                return $cacheRes;
            }
        }

        $result = $this->db->count($this->_modelData['query'], $countField, $this->_modelData['data'], $this->_modelData['options']);

        if ($this->_modelData['cacheKey']) {
            Cache::set($this->_modelData['cacheKey'], json_encode($result), $this->_modelData['exp']);
        }

        //取到数据 清空条件
        $this->_modelData = [];

        return $result;
    }



    /**
     * 更新数据
     * @param array|object $setData 待更新数据
     * @param array|object $criteria 更新条件
     * @param string $cacheKey 需要删除的缓存 key
     * @var Mongodb | Mysql
     *
     * @return UpdateResult
     */
    public function update($setData, $criteria, $cacheKey = '') {
        $this->_getConnect('write');

        $this->_setCanEmpty(['data' => [], 'options' => []]);

        //有缓存 要删除
        if ($cacheKey) {
            Cache::del($cacheKey);
        }

        $result = $this->db->update($setData, $criteria, $this->_modelData['data'], $this->_modelData['options']);
        //清空
        $this->_modelData = [];

        return $result;
    }

    /**
     *  删除数据
     * @var Mongodb | Mysql
     * @param        $criteria
     * @param string $cacheKey 需要删除的缓存 key
     *
     * @return int
     */
    public function delete($criteria, $cacheKey = '') {
        $this->_getConnect('write');

        $this->_setCanEmpty(['data' => [], 'options' => []]);
        //有缓存 要删除
        if ($cacheKey) {
            Cache::del($cacheKey);
        }

        $result = $this->db->delete($criteria, $this->_modelData['data'], $this->_modelData['options']);
        //清空
        $this->_modelData = [];

        return $result;
    }



    /**
     * 执行 SQL
     * @param          $query
     * @param          $data
     * @param string   $cacheKey
     * @param int|null $exp
     *
     * @return mixed
     */
    public function getQuery() {
        $this->_getConnect('read');

        $this->_setCanEmpty(['query' => [], 'data' => [], 'options' => [], 'cacheKey' => '', 'exp' => DEFAULT_CACHEEXPIRE ?? null]);

        //自动生成 cachekey
        if (TRUE === $this->_modelData['cacheKey']) {
            $cacheKey = md5($this->table . __METHOD__ . json_encode($this->_modelData));
        }
        if ($cacheKey) {
            //有缓存 数据优先使用
            $cacheRes = json_decode(Cache::get($cacheKey), TRUE);
            if ($cacheRes) {
                return $cacheRes;
            }
        }

        $result = $this->db->getQuery($this->_modelData['query'], $this->_modelData['data'], $this->_modelData['options']);

        if ($cacheKey) {
            Cache::set($cacheKey, json_encode($result), $this->_modelData['exp']);
        }

        return $result;
    }

    /**
     * 获取本表中的所有字段
     *
     * @param string $table
     *
     * @return mixed
     */
    protected function getAllField($table = '') {

        if (!$table) {
            $table = $this->table;
        }

        // filed 专用前缀
        $cacheKey = '_Field_table_' . $table;

        //有缓存 数据优先使用
        $cacheRes = json_decode(Cache::get($cacheKey), TRUE);
        if ($cacheRes) {
            return $cacheRes;
        }

        $result = $this->db->getAllField($table);

        //APP_DEBUG 关闭后  设置永久缓存
        //@todo 写入缓存至 php 文件
        if (FALSE == APP_DEBUG) {
            Cache::set($cacheKey, json_encode($result), 60 * 60 * 24);
        }

        return $result;
    }


    //获取查询语句调试
    public function getLastSql() {
        return $this->db->getLastSql();
    }

    /**
     * @param $obj
     */
    public static function toArray($obj){
        if (!is_array($obj) && !is_object($obj))
            return $obj;

        $data = [];

        //if (!($obj instanceof BSONDocument)){
        //
        //
        //}

        //$list = (array)$obj;
        foreach ($obj as $key => $value) {
            if (is_object($value)){
                switch ($value){
                    case $value instanceof ObjectId:
                        $data[$key] = (string)$value;
                        break;
                    //@todo 配置 时间项目
                    case $value instanceof UTCDateTime:
                        $data[$key] = $value->toDateTime()->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
                        break;
                    default:
                        $data[$key] = self::toArray($value);
                        break;
                }
            }else{
                $data[$key] = $value;
            }
        }
        return $data;
    }

}
