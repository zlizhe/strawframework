<?php
namespace Strawframework\Protocol;

/**
 *  db 库所有实现方法
 */
interface Db{

    /**
     *  写入新数据
     * @param array $data
     *
     * @return mixed
     */
    public function insert($data);

    /**
     * 根据条件查找一条
     * @return array
     */
    public function getOne();


    /**
     * 查找所有符合条件的行
     *
     */
    public function getAll();

    /**
     * 统计文档（字段）数量
     * @return mixed
     */
    public function count();

    /**
     * 执行完整 sql
     */
    public function getQuery();

    /**
     *  更新数据
     * @param $data
     * @param $condition
     *
     * @return mixed
     */
    public function update($data, $condition);

    /*
     *  删除数据
     *  @param $condition 删除条件 array()
     * */
    public function delete($condition);

    /**
     * 获取最新的查询  sql
     * @return mixed
     */
    public function getLastSql();
}
