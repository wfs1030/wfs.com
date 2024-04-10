// 筛选页功能关闭中
muban.mxone5.二级.desc = '.video-info-items:eq(6)&&Text;;;.video-info-actor:eq(1)&&Text;.video-info-actor:eq(0)&&Text';
var rule = {
	title: '子子影视',
	模板: 'mxone5',
	host: 'https://www.ziziys.com',
	url: '/list/fyclass/page/fypage.html',
	class_name: '动漫片&恐怖片&历史传记片&战争片&武侠古装&记录片&灾难片&音乐歌舞&国产剧&美剧&韩剧&泰剧&国漫&日漫&动漫',
	class_url: '23&24&25&26&28&29&30&31&13&14&15&16&20&21&22',
	class_parse: '',
	lazy: `js:
		var html = JSON.parse(request(input).match(/r player_.*?=(.*?)</)[1]);
		var url = html.url;
		if (html.encrypt == "1") {
			url = unescape(url)
		} else if (html.encrypt == "2") {
			url = unescape(base64Decode(url))
		}
		if (/m3u8|mp4/.test(url)) {
			input = url
		} else {
			input
		}
	`,
	// searchUrl:'/vsearch/**--fypage.html',
	searchUrl: '/index.php/ajax/suggest?mid=1&wd=**&limit=50',
	detailUrl: '/vdetail/fyid.html', //非必填,二级详情拼接链接
	搜索: 'json:list;name;pic;;id',
}