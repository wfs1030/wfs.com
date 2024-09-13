// 地址发布页 https://subaibai.vip/
// 搜索数字验证
var rule = {
		title: '素白白',
		// host:'https://www.subaibaiys.com',
		host: 'https://subaibai.vip',
		hostJs: 'print(HOST);let html=request(HOST,{headers:{"User-Agent":PC_UA}});let src = jsp.pdfh(html,".go:eq(0)&&a&&href");print(src);HOST=src', //网页域名根动态抓取js代码。通过HOST=赋值
		// url:'/fyclass/page/fypage',
		url: '/fyclassfyfilter',
		filterable: 1, //是否启用分类筛选,
		filter_url: '{{fl.area}}{{fl.year}}{{fl.class}}{{fl.cateId}}/page/fypage',
	},
	{
		"key": "year",
		"name": "年份",
		"value": [{
			"n": "全部",
			"v": ""
		}, {
			"v": "/year/2024",
			"n": "2024"
		}, {
			"v": "/year/2023",
			"n": "2023"
		}, {
			"v": "/year/2022",
			"n": "2022"
		}, {
			"v": "/year/2021",
			"n": "2021"
		}, {
			"v": "/year/2020",
			"n": "2020"
		}, {
			"v": "/year/2019",
			"n": "2019"
		}, {
			"v": "/year/2018",
			"n": "2018"
		}, {
			"v": "/year/2017",
			"n": "2017"
		}, {
			"v": "/year/2016",
			"n": "2016"
		}, {
			"v": "/year/2015",
			"n": "2015"
		}, {
			"v": "/year/2014",
			"n": "2014"
		}, {
			"v": "/year/2013",
			"n": "2013"
		}, {
			"v": "/year/2012",
			"n": "2012"
		}, {
			"v": "/year/2011",
			"n": "2011"
		}, {
			"v": "/year/2010",
			"n": "2010"
		}, {
			"v": "/year/2009",
			"n": "2009"
		}, {
			"v": "/year/2008",
			"n": "2008"
		}, {
			"v": "/year/2007",
			"n": "2007"
		}, {
			"v": "/year/2006",
			"n": "2006"
		}, {
			"v": "/year/2005",
			"n": "2005"
		}, {
			"v": "/year/2004",
			"n": "2004"
		}, {
			"v": "/year/2003",
			"n": "2003"
		}, {
			"v": "/year/2002",
			"n": "2002"
		}, {
			"v": "/year/2001",
			"n": "2001"
		}, {
			"v": "/year/2000",
			"n": "2000"
		}, {
			"v": "/year/1999",
			"n": "1999"
		}, {
			"v": "/year/1998",
			"n": "1998"
		}, {
			"v": "/year/1997",
			"n": "1997"
		}, {
			"v": "/year/1996",
			"n": "1996"
		}, {
			"v": "/year/1995",
			"n": "1995"
		}, {
			"v": "/year/1994",
			"n": "1994"
		}, {
			"v": "/year/1993",
			"n": "1993"
		}, {
			"v": "/year/1992",
			"n": "1992"
		}, {
			"v": "/year/1991",
			"n": "1991"
		}, {
			"v": "/year/1990",
			"n": "1990"
		}, {
			"v": "/year/1989",
			"n": "1989"
		}, {
			"v": "/year/1988",
			"n": "1988"
		}, {
			"v": "/year/1987",
			"n": "1987"
		}, {
			"v": "/year/1986",
			"n": "1986"
		}, {
			"v": "/year/1985",
			"n": "1985"
		}, {
			"v": "/year/1984",
			"n": "1984"
		}, {
			"v": "/year/1983",
			"n": "1983"
		}, {
			"v": "/year/1982",
			"n": "1982"
		}, {
			"v": "/year/1981",
			"n": "1981"
		}, {
			"v": "/year/1980",
			"n": "1980"
		}, {
			"v": "/year/1979",
			"n": "1979"
		}, {
			"v": "/year/1978",
			"n": "1978"
		}, {
			"v": "/year/1977",
			"n": "1977"
		}, {
			"v": "/year/1976",
			"n": "1976"
		}, {
			"v": "/year/1975",
			"n": "1975"
		}, {
			"v": "/year/1974",
			"n": "1974"
		}, {
			"v": "/year/1973",
			"n": "1973"
		}, {
			"v": "/year/1972",
			"n": "1972"
		}, {
			"v": "/year/1971",
			"n": "1971"
		}, {
			"v": "/year/1970",
			"n": "1970"
		}, {
			"v": "/year/1969",
			"n": "1969"
		}, {
			"v": "/year/1968",
			"n": "1968"
		}, {
			"v": "/year/1967",
			"n": "1967"
		}, {
			"v": "/year/1966",
			"n": "1966"
		}, {
			"v": "/year/1965",
			"n": "1965"
		}, {
			"v": "/year/1964",
			"n": "1964"
		}, {
			"v": "/year/1963",
			"n": "1963"
		}, {
			"v": "/year/1962",
			"n": "1962"
		}, {
			"v": "/year/1960",
			"n": "1960"
		}, {
			"v": "/year/1959",
			"n": "1959"
		}, {
			"v": "/year/1954",
			"n": "1954"
		}, {
			"v": "/year/1952",
			"n": "1952"
		}, {
			"v": "/year/1950",
			"n": "1950"
		}, {
			"v": "/year/1949",
			"n": "1949"
		}, {
			"v": "/year/1948",
			"n": "1948"
		}, {
			"v": "/year/1940",
			"n": "1940"
		}, {
			"v": "/year/1939",
			"n": "1939"
		}, {
			"v": "/year/1925",
			"n": "1925"
		}]
	}]
},
// searchUrl:'/search?q=**',
searchUrl: '/page/fypage?s=**',
	searchable: 2, //是否启用全局搜索,
	quickSearch: 0, //是否启用快速搜索,
	headers: {
		'User-Agent': 'UC_UA',
	},
	// class_parse:'.navlist&&li;a&&Text;a&&href;.*/(\\w+)',
	class_name: '影视筛选&电影&电视剧&热门电影&高分电影&动漫电影&香港经典电影&国产剧&美剧&韩剧&动漫剧&漫威宇宙电影系列&速度与激情电影系列&007系列(25部正传+2部外传)', //静态分类名称拼接
	class_url: 'movie_bt&new-movie&tv-drama&hot-month&high-movie&cartoon-movie&hongkong-movie&domestic-drama&american-drama&korean-drama&anime-drama&marvel-movies&fastfurious&zero-zero-seven', //静态分类标识拼接
	play_parse: true,
	// lazy:'',
	lazy: `js:
        pdfh = jsp.pdfh;
        var html = request(input);
        var ohtml = pdfh(html, '.videoplay&&Html');
        var url = pdfh(ohtml, "body&&iframe&&src");
        if (/Cloud/.test(url)) {
            var ifrwy = request(url);
            let code = ifrwy.match(/var url = '(.*?)'/)[1].split('').reverse().join('');
            let temp = '';
            for (let i = 0x0; i < code.length; i = i + 0x2) {
                temp += String.fromCharCode(parseInt(code[i] + code[i + 0x1], 0x10))
            }
            input = {
                jx: 0,
                url: temp.substring(0x0, (temp.length - 0x7) / 0x2) + temp.substring((temp.length - 0x7) / 0x2 + 0x7),
                parse: 0
            }
        } else if (/decrypted/.test(ohtml)) {
            var phtml = pdfh(ohtml, "body&&script:not([src])&&Html");
            eval(getCryptoJS());
            var scrpt = phtml.match(/var.*?\\)\\);/g)[0];
            var data = [];
            eval(scrpt.replace(/md5/g, 'CryptoJS').replace('eval', 'data = '));
            input = {
                jx: 0,
                url: data.match(/url:.*?[\\'\\"](.*?)[\\'\\"]/)[1],
                parse: 0
            }
        } else {
            input
        }
	`,
	limit: 6,
	推荐: '.leibox&&li;*;*;*;*',
	// double:true, // 推荐内容是否双层定位
	一级: '.mrb&&li;img&&alt;img&&data-original;.jidi&&Text;a&&href',
	二级: {
		"title": "h1&&Text;.moviedteail_list&&li:eq(0)&&Text",
		"img": ".dyimg&&img&&src",
		"desc": ".moviedteail_list&&li:eq(-1)&&Text;;;.moviedteail_list&&li:eq(7)&&Text;.moviedteail_list&&li:eq(5)&&Text",
		"content": ".yp_context&&p&&Text",
		"tabs": ".mi_paly_box .ypxingq_t",
		"lists": ".paly_list_btn:eq(#id) a"
	},
	搜索: '.search_list&&li;*;*;*;*',
}