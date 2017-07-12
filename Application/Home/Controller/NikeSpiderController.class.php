<?php
namespace Home\Controller;
use Think\Controller;
use QL\QueryList;
/*
    描述：抓取一个品牌下的所有商品的信息，例：nike旗舰店
    (一级类目是配置里面的，二级类目是抓取的然后写入数据库，但是要带入父类目)

    1.首先每个品牌的顶级类目都是图片表示，不好固定，所以可以直接配置品牌的顶级类目，比如：nike下面有男子，女子，儿童

    2.然后分别获取顶级类目下对应的二级类目，并且将类目的url和catId写入到数据表，如：女子/鞋类,上衣，裤装，

    3.然后在for循环中再次抓取二级类目的所有分类(女子/鞋类/下面所有的商品-其中所有的分页-其中渲染一页并且解析大概需要5秒)

    4.每抓取完一个二级类目所有商品后，将商品写入一次
    
    (需要注意在profile.js中渲染数据的时候将所有数据渲染成功才能显示，不然要重新渲染)
    耗时：
    一级类目：3个 (男子，女子，儿童)
    二级类目：8-25个不等  (男子-鞋类、滑板  女子-鞋类、上衣  儿童-鞋类)
    二级类目分页：10个(一个分页大概5秒)
    
    大概每个一级类目：10*10*5=500秒（时间太长可能会断，断了需要重连，然后最好记录下更新时间）



    //------ 获取二级类目的商品列表(所有页) ----------------
    $dataList = $this->getAllGoodsList($this->thirdUrl);

    //------ 获取二级类目的商品列表(单页)--------------------
    $dataList = $this->getGoodsList($this->thirdUrl);
    $dataList = $this->handleGoodsList($dataList);
 */

    
class NikeSpiderController extends Controller {

    //http scheme
    const HTTP_SCHEME = 'https:';

    //http 头部
    const HTTP_HEADER = 'https://';

    //每个页面共有40个商品
    const GOODS_NUM_ONE_PAGE = 40;

    //获取商品列表页的时候，最大重试次数
    const MAX_RENDER_NUM = 5;

    //默认的商品封面图(1个像素)
    const DEFAULT_IMAGE = 'https://assets.alicdn.com/s.gif';

    //默认的首焦图(1像素)
    const DEFAULT_HOMEPAGE_IMAGE = 'https://assets.alicdn.com/s.gif';

    //品牌id
    private $_brandId;

    //品牌名
    private $_brandName;

    //配置信息
    private $_categoryConfig;
    
    //首焦图地址
    private $_homepageUrl = 'https://nike.tmall.com/view_shop.htm';

    //一级类目URL
    private $_firstCategoryId;

    /**
     * 初始化数据
     */
    public function __construct(){
        parent::__construct();

        //获取品牌的配置信息
        $this->_categoryConfig = C('brand_config.nike');
        $this->_brandId = $this->_categoryConfig['brand_id'];
        $this->_homepageUrl = $this->_categoryConfig['homepage_url'];
    }
    



    public function index(){
//var_dump('sdfsdfsd');exit();
        ini_set("max_execution_time", 82400);
        
        log_message('begin to crawle nike goods info!',LOG_INFO);        

        if(empty($this->_categoryConfig)){
            log_message('empty brand configs!',LOG_ERR);
            return;
        }

        htmlDebug($this->_categoryConfig);
        
        //首焦图信息更新
        $homageInfo = $this->getHomepageInfo($this->_homepageUrl);
        $homageInfo = $this->handleHomepageInfo($homageInfo);
        $result = M('brand','db_','MYSQLCONF')->where(array('brand_id'=>$this->_brandId))->save($homageInfo);
        log_message('update homepage result:'.json_encode($result),LOG_INFO);   
        if($result === false){
            log_message('update homepage info error!info:'.json_encode($homageInfo),LOG_ERR);
        }



        //类目和商品信息更新
        foreach($this->_categoryConfig['top_category'] as $category){
            $this->_firstCategoryId = $category['category_id'];

            //-------获取品牌的一级类目下所有二级类目信息     //女子下面的所有二级类目 (鞋类，上衣，夹克)
            $secondCategoryList = $this->getSecondCategory($category['url']);

            //secondCategoryUrl = $secondCategoryList[0]['second_category_url'];
            //入口
            if(empty($secondCategoryList)){
                log_message('get empty category!url:'.json_encode($category),LOG_ERR);
            }else{
                //解析出category_id并且将二级类目信息入库
                $secondCategoryInfo = $this->handleCategoryInfo($secondCategoryList);

                log_message('secondCategoryInfo:'.json_encode($secondCategoryInfo),LOG_DEBUG);

                
                if(empty($secondCategoryInfo)){
                    log_message('empty secondCategoryInfo!',LOG_ERR);
                    return;
                }

                //将二级类目信息入库
                $result = M('category','db_','MYSQLCONF')->addAll($secondCategoryInfo,array(),true);

                log_message('result:'.json_encode($result),LOG_DEBUG);
                if($result === false){
                    log_message('add second category info error!info:'.json_encode($secondCategoryInfo),LOG_ERR);
                    return false;
                }
            
                //exit;
                //获取对应二级类目下所有的商品信息
                foreach($secondCategoryList as $secondCategory){
                    if(isset($secondCategory['second_category_url'])){

                        //从二级类目url中获取类目id
                        $secondCategoryId = $this->getCategoryIdFromSecondCategoryUrl($secondCategory['second_category_url']);
                        if(empty($secondCategoryId)){
                            log_message('empty secondCategoryId!info:'.json_encode($secondCategory),LOG_ERR);
                        }
                        //获取一个类目下所有信息,如：女子-鞋类 下的所有商品信息
                        $goodsList = $this->getAllGoodsList($secondCategory['second_category_url']);

                        if(empty($goodsList)){
                            log_message('get empty goods_info in second category!info:'.json_encode($secondCategory),LOG_ERR);
                            continue;
                        }

                        //对商品数据进行处理
                        $goodsList = $this->handleGoodsList($goodsList,$secondCategoryId);
                        log_message('goodsList:'.json_encode($goodsList),LOG_DEBUG);

                        //数据入库
                        $result = M('goods_lite','db_','MYSQLCONF')->addAll($goodsList,'',true);
                        if($result === false){
                            log_message('add goods info error!info:'.json_encode($secondCategory['second_category_url']),LOG_ERR);
                            continue;
                        }
                    }else{
                        log_message('empty second category!info:'.json_encode($secondCategory),LOG_ERR);
                        continue;
                    }
                }
                
            }
        }


        log_message('finish crawle goods info!',LOG_INFO);
    }


    

    //nike - 获取某个品牌的的一级类目下的二级类目()
    public function getSecondCategory($url){
        if(empty($url)){
            log_message('getSecondCategory empty url!url:'.json_encode($url),LOG_ERR);
            return false;
        }

        //解析规则
        $regular = array(            
            'name'=>array('.J_TAttrNav .attrs .cateAttrs .attrValues li a','text'),         //类目名称
            'second_category_url'=>array('.J_TAttrNav .attrs .cateAttrs .attrValues li a','href'),
            'callback' => array($this,'attributeHandle')
        );

        $html = $this->renderHtml($url);
        $data = QueryList::Query($html, $regular)->data;

        if(empty($data)){
            return false;
        }

        return $data;
    }


    //获取一个二级类目下的所有信息（可能有多个分页）getAllGoodsList
    public function getAllGoodsList($url){
        

        //使用循环获取多个分页的数据
        $hasNextPagination = true;
        $allData = array();
        $lastUrl = '';
        while($hasNextPagination){

            log_message('getAllGoodsList single url:'.$url,LOG_DEBUG);
            //获取一个分页中的所有商品信息
            $data = $this->getGoodsList($url);
            //log_message("single count:".count($data).',url:'.$url,LOG_DEBUG);
            
            if(!empty($data)){
                //$lastUrl = 
                $allData = array_merge($allData,$data);
            }

            //可以添加重试逻辑
            
            if(empty($data)){
                log_message('get empty goods data from one page!url:'.$url,LOG_ERR);
                continue;
            }

            //判断是否有下一页，如果有的话继续抓取
            if(!empty($data[0]['next_pagination'])){
                log_message('next pagination_0:'.$data[0]['next_pagination'],LOG_INFO);
                $url = $this->handlePaginationUrl($data[0]['next_pagination']);     //下一页的url地址
                log_message('next pagination_1:'.$url,LOG_INFO);
            }else{
                $hasNextPagination = false;
                log_message('last pagination!',LOG_INFO);
            }
        }
        log_message('all data count:'.count($allData),LOG_DEBUG);
        return $allData;
    }


    //获取一个分页的所有商品信息 getGoodsList
    public function getGoodsList($url){

        if(empty($url)){
            log_message('empty param url!info:'.$url,LOG_ERR);
            return false;
        }
        
        $regular = array(
            'image'=>array('.item4line1 .item dt.photo img','src'),                         //封面图
            'image_lazy'=>array('.item4line1 .item dt.photo img','data-ks-lazyload'),   //封面图-慢加载(替代封面图无法加载的情况)
            'thumb'=>array('.item4line1 .item dd.thumb .thumb-inner img','src'),            //缩略图
            'name' =>array('.item4line1 .item dd.detail a.item-name','text'),               //商品名称
            'detail_url' =>array('.item4line1 .item dd.detail a.item-name','href'),               //商品详情链接
            'price' =>array('.item4line1 .item dd.detail .attribute .c-price','text'),      //价钱
            'next_pagination' =>array('.pagination a.next','href'),
            'callback' => array($this,'goodsListCallback')
        );

        $html = $this->renderHtml($url);
        $data = QueryList::Query($html, $regular)->data;

        if(empty($data)){
            log_message('getGoodsDetail empty data!url:',LOG_ERR);
        }

        log_message('data_count:'.count($data),LOG_DEBUG);

        return $data;
    }

    //使用phantomjs将url的内容先获取到然后渲染,并且保存起来
    //默认是js引擎(phantomjs)渲染操作
    public function renderHtml($url,$type='render'){
        if(empty($url)){
            log_message('empty url!',LOG_ERR);
            return false;
        }

        $output = '';

        if($type!='render'){
            $output = file_get_contents($url);
        }else{
            //渲染操作
            $cmd = 'phantomjs '.JS_PATH.'profile.js '.$url;

            log_message('cmd:'.$cmd,LOG_INFO);

            exec($cmd,$output,$ret);
            if($ret != 0){
                log_message("exec error cmd:{$cmd}, output:" . json_encode($output), LOG_ERR);
                return false;
            }
        }
        

        log_message('exec len:'.strlen(json_encode($output)),LOG_DEBUG);

        //将渲染后的数据保存在临时目录
        $htmlPath = RENDER_HTML_PATH.date('Ymd',time());
        if (! is_dir ( $htmlPath )) {
            mkdir ( $htmlPath, 0755, TRUE );
        }
        $filePath = $htmlPath.DS.md5($url).'.html';

        log_message('path_static_2:'.$filePath,LOG_DEBUG);  

        file_put_contents($filePath,$output);
        $html = file_get_contents($filePath);

        log_message('file_get_contents len:'.strlen($html),LOG_DEBUG);
        
        return $html;        
    }


    //作回调的操作，比如补齐url，过滤字符串，或者替换操作
    public function attributeHandle($str,$type){

        //处理分页的url格式
        if($type == 'next_pagination'){
            $str = $this->handlePaginationUrl($str);
        }

        //处理分级类目的url
        if($type == 'second_category_url'){
            $str = $this->handleCategoryUrl($str);
        }

        //首焦图处理
        if($type == 'homepage_image' || $type == 'homepage_image_lazy'){
            $str = self::HTTP_SCHEME.$str;
        }

        return $str;
    }

    //商品列表的的属性回调处理
    public function goodsListCallback($str,$type){
        if($type == 'image' || $type == 'image_lazy' || $type == 'thumb' || $type == 'detail_url'){
            $str = self::HTTP_SCHEME.$str;
        }

        return $str;
    }


    //处理分页的url
    public function handlePaginationUrl($url){
        if(empty($url)){
            return false;
        }

        $arr = parse_url($url);

        $tempArray = explode('&',$arr['query']);
        $pageNo = end($tempArray);

        $tempArray = explode('&',$arr['query']);
        $pageNo = end($tempArray);

        return self::HTTP_HEADER.$arr['host'].$arr['path'].'?'.$pageNo;
    }


    //处理分级类目的url
    public function handleCategoryUrl($url){
        if(empty($url)){
            return false;
        }

        $arr = parse_url($url);

        return self::HTTP_HEADER.$arr['host'].$arr['path'];
    }

    //补全类目的其他信息
    public function handleCategoryInfo($categoryList){
        if(empty($categoryList)){
            log_message('empty categoryList params!',LOG_ERR);
            return false;
        }

        foreach($categoryList as $key=>&$category){

            //$url = '//nike.tmall.com/category-1260521728.htm?spm=a1z10.5-b-s.w4011-14234872789.46.tFCfpy&search=y&search=y#TmshopSrchNav';
            if(!isset($category['second_category_url'])){
                log_message("un isset category sencond_category_url!info:".json_encode($category),LOG_ERR);
                unset($categoryList[$key]);
                continue;
            }

            $secondCategoryId = $this->getCategoryIdFromSecondCategoryUrl($category['second_category_url']);
            if(empty($secondCategoryId)){
                log_message('empty second_category_id!info:'.json_encode($category),LOG_ERR);
                unset($categoryList[$key]);
                continue;
            }

            $category['second_category_id'] = $secondCategoryId;
            $category['first_category_id'] = $this->_firstCategoryId;
            $category['brand_id'] = $this->_brandId;
            $category['create_time'] = time();
        }

        return $categoryList;
    }


    //获取一个商品详细信息(商品详情页)
    public function getGoodsDetail($url){

        if(empty($url)){
            log_message('empty param url!info:'.$url,LOG_ERR);
            return false;
        }

        $regular = array(            
            'image'=>array('.item4line1 .item dt.photo img','src'),                         //封面图
            'thumb'=>array('.item4line1 .item dd.thumb .thumb-inner img','src'),            //缩略图
            'name' =>array('.item4line1 .item dd.detail a.item-name','text'),               //商品名称
            'detail_url' =>array('.item4line1 .item dd.detail a.item-name','href'),               //商品详情链接
            'price' =>array('.item4line1 .item dd.detail .attribute .c-price','text'),      //价钱
            'next_pagination' =>array('.pagination a.next','href'),
            'callback' => array($this,'attributeHandle')
        );

        $data = QueryList::Query($html, $regular)->data;

        if(empty($data)){
            log_message('getGoodsDetail empty data!url:'.$url,LOG_ERR);
        }
        log_message('data_count:'.count($data),LOG_DEBUG);

        return $data;
    }


    //获取首焦图
    public function getHomepageInfo($url){
        if(empty($url)){
            log_message('getHomepageInfo empty param url!info:'.$url,LOG_ERR);
            return false;
        }

        $regular = array(            
            'homepage_image'=>array('.left a img','src'),        //大图
            'homepage_image_lazy'=>array('.left a img','data-ks-lazyload'),        //大图-慢加载
            'homepage_image_url'=>array('.left a','href'),       //大图链接url
            'callback' => array($this,'attributeHandle')
        );

        $html = $this->renderHtml($url,'file_get');
        $data = QueryList::Query($html, $regular)->data;

        if(empty($data)){
            log_message('getHomepageInfo empty data!url:'.$url,LOG_ERR);
        }
        log_message('data_count:'.count($data),LOG_DEBUG);

        return $data;
    }


    //处理一页中的商品
    public function handleGoodsList($goodsList,$secondCategoryId){
        if(empty($goodsList) || empty($secondCategoryId)){
            log_message('goodsList empty!',LOG_ERR);
            return false;
        }

        //记录商品数，将一页中多余的商品去掉
        $count = 1;

        foreach($goodsList as $key=>&$goodsInfo){

            $goodsInfo['create_time'] = time();
            $goodsInfo['brand_id'] = $this->_brandId;
            $goodsInfo['first_category_id'] = $this->_firstCategoryId;
            $goodsInfo['second_category_id'] = $secondCategoryId;

            //封面图未正常加载
            if(isset($goodsInfo['image']) && $goodsInfo['image'] == self::DEFAULT_IMAGE && isset($goodsInfo['image_lazy'])){
                log_message("image:{$goodsInfo['image']},image_lazy:{$goodsInfo['image_lazy']}",LOG_INFO);
                $goodsInfo['image'] = $goodsInfo['image_lazy'];
            }

            //去掉多余的商品(推荐展示的)
            if(!isset($goodsInfo['image']) || $count>self::GOODS_NUM_ONE_PAGE){
                log_message('surplus goods!info:'.json_encode($goodsInfo),LOG_INFO);
                unset($goodsList[$key]);
            }

            //取出goods_id
            $goodsId = $this->getGoodsidFromUrl($goodsInfo['detail_url']);
            if(empty($goodsId)){
                log_message('get no goodsid from url!info:'.$goodsInfo['detail_url'],LOG_ERR);
                unset($goodsList[$key]);
            }else{
                $goodsInfo['goods_id'] = $goodsId;
            }

            if(isset($goodsInfo['image_lazy'])){
                unset($goodsInfo['image_lazy']);
            }

            $count++;
        }

        return $goodsList;
    }

    //处理首焦图信息
    public function handleHomepageInfo($dataList){
        if(empty($dataList)){
            log_message('handleHomepageInfo empty params!info:'.json_encode($dataList),LOG_ERR);
            return false;
        }
        $homepageInfo = $dataList[0];

        if(isset($homepageInfo['homepage_image']) && $homepageInfo['homepage_image'] == self::DEFAULT_HOMEPAGE_IMAGE && isset($homepageInfo['homepage_image_lazy'])){
            log_message('default homepage image!homepageInfo:'.json_encode($homepageInfo),LOG_INFO);
            $homepageInfo['homepage_image'] = $homepageInfo['homepage_image_lazy'];
        }

        $homepageInfo['update_time'] =time();
        unset($homepageInfo['homepage_image_lazy'] );

        return $homepageInfo;
    }

    //从商品详情页中获取goods_id
    public function getGoodsidFromUrl($url){
        $arr = parse_url($url);
        
        $goodsId='';
        $tmpArray = explode("&", $arr['query']);
        foreach($tmpArray as $key=>$value){
            if(false!==strpos($value,'id=')){
                $tmpArr = explode('=',$value);
                $goodsId = $tmpArr[1];
            }
        }
        return $goodsId;
    }


    //从二级类目的url中获取二级类目id
    public function getCategoryIdFromSecondCategoryUrl($url){
        //https://nike.tmall.com/category-1260524552.htm?spm=a1z10.5-b-s.w4011-14234872789.46.4Ye2tV&search=y&scene=taobao_shop#TmshopSrchNav
        $arr = parse_url($url);

        $tmpArr1 = explode('-',$arr['path']);

        $tmpArr2 = explode('.',$tmpArr1[1]);
        return $tmpArr2[0];
    }
}
?>