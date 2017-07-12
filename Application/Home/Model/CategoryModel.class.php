<?php
namespace Home\Model;

use Home\Utils\Util;
use Think\Model;

class CategoryModel extends Model
{

    /**
     * @var
     */
    private $_brandModel;

    /**
     * @var
     */
    private $_categoryModel;

    /**
     * 初始化数据
     */
    public function __construct()
    {
        $this->_brandModel = M('brand', 'db_', C('MYSQLCONF'));
        $this->_categoryModel = M('category', 'db_', C('MYSQLCONF'));
    }

    public function getDataList(){
        //model逻辑可以在这里

    }

}