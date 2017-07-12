var page = require('webpage').create(),
system = require('system'),
url;


if(system.args.length === 1){
	phantomjs.exit(1);
}else{
	url = system.args[1];

	//开始渲染url
	page.open(url,function (status){
		if(status !== 'success'){
			phantom.exit();
		}else{
			var sc =page.evaluate(function (){
				return document.body.innerHTML;
			});
			window.setTimeout(function (){
				console.log(sc);
				phantom.exit();
			},1000)
		}

	});
}

//睡眠函数
function sleep(ms){
	console.log('start s:'+new Date()/1000);
	ms += new Date().getTime();
	while(new Date()<ms){
		//console.log('ms:'+new Date()/1000);
	}
	console.log('end s:'+new Date()/1000);
}