
function check() { 
	// 在这里写你自已的标准 
	return document.getElementById('username'); 
} 
function waitForReady() { 
	if (!check()) { 
		return setTimeout(waitForReady, 50); // 每50毫秒检查一下 
	}else { 
		// phantom与页面的通信推荐用alert来做 
		alert('ready'); 
	} 
} 

waitForReady(); 

var page = new WebPage(); 
page.onAlert = function (message) { 
	if (message === 'ready') { 
		takeScreenshot(page); // 截图 
	} 
} 
page.open(url, function () { console.log('page loaded but not ready'); });