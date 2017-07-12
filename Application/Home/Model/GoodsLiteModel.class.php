<?php
namespace Home\Model;

use Home\Utils\Util;
use Think\Model;

class GoodsLiteModel extends Model
{

    /**
     * @var
     */
    private $_goodsModel;

    /**
     * @var
     */
    private $_categoryModel;

    /**
     * 初始化数据
     */
    public function __construct()
    {
        $this->_goodsModel = M('goods_lite', 'mkf_', C('MYSQLCONF'));
        $this->_categoryModel = M('category', 'mkf_', C('MYSQLCONF'));
    }

    public function getDataList(){
        //model逻辑可以在这里

    }

}