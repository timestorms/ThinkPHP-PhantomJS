<?php
namespace Home\Controller;
use Think\Controller;
use QL\QueryList;

class IndexController extends Controller {
    public function index(){

    	$regular = array(
    			'image'=>array('img','src')
    		);
        //采集某页面所有的图片
        //$data = QueryList::Query('http://cms.querylist.cc/bizhi/453.html',['image' => ['img','src']])->data;
        $data = QueryList::Query('http://cms.querylist.cc/bizhi/453.html',$regular)->data;
        //打印结果
        print_r($data);
    }
}