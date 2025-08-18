import sys
import os
sys.path.append("..")
import re
import hashlib
import hmac
import random
import string
from Crypto.Util.Padding
import unpad
from concurrent.futures
import ThreadPoolExecutor
from Crypto.PublicKey
import RSA
from Crypto.Cipher
import PKCS1_v1_5, AES
from base64
import b64encode, b64decode
import json
import time
from base.spider
import Spider

class Spider(Spider):

	def __init__(self):
	self.base_url = "http://dyxz.tv/"
self.headers = {
	"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"
}
self.categories = OrderedDict([
	("1", {
		"type_name": "首页推荐",
		"type_id": "1"
	}),
	("2", {
		"type_name": "电影",
		"type_id": "2"
	}),
	("3", {
		"type_name": "电视剧",
		"type_id": "3"
	}),
	("4", {
		"type_name": "动漫",
		"type_id": "4"
	}),
	("5", {
		"type_name": "综艺",
		"type_id": "5"
	})
])

def _fetch_html(self, url):
	req = urllib.request.Request(url, headers = self.headers)
response = urllib.request.urlopen(req)
return response.read().decode('utf-8')

def get_categories(self):
	return list(self.categories.values())

def get_videos_by_category(self, category_id, page = 1):
	if category_id == "1":
	url = self.base_url
else:
	category_name = self.categories[category_id]["type_name"]
url = f "{self.base_url}{category_name}/"
if page > 1:
	url += f "index_{page}.html"

html = self._fetch_html(url)
return self._parse_video_list(html)

def search(self, keyword):
	search_url = f "{self.base_url}index.php?m=vod-search&wd={urllib.parse.quote(keyword)}"
html = self._fetch_html(search_url)
return self._parse_video_list(html)

def get_video_detail(self, video_url):
	if not video_url.startswith('http'):
	video_url = urllib.parse.urljoin(self.base_url, video_url)

html = self._fetch_html(video_url)

# 解析详情
detail_pattern = re.compile(
	r '<h2>(.*?)</h2>.*?'
	r '<img src="(.*?)".*?'
	r '<span class="more">(.*?)</span>.*?'
	r '<div class="info">(.*?)</div>.*?'
	r '<div class="player">(.*?)</div>',
	re.S
)
match = detail_pattern.search(html)
if not match:
	return None

title, cover, remarks, info, play_list_html = match.groups()

# 解析播放源
play_sources = {}
play_source_pattern = re.compile(r '<h3>播放源(\d+)</h3>.*?<ul>(.*?)</ul>', re.S)
play_url_pattern = re.compile(r '<li><a href="(.*?)">(.*?)</a></li>')

for source in play_source_pattern.finditer(play_list_html):
	source_id = source.group(1)
play_sources[source_id] = []
for url_match in play_url_pattern.finditer(source.group(2)):
	play_url, episode = url_match.groups()
play_sources[source_id].append({
	"name": episode,
	"url": urllib.parse.urljoin(self.base_url, play_url)
})

# 构建详情数据
return {
	"vod_name": title,
	"vod_pic": urllib.parse.urljoin(self.base_url, cover),
	"vod_remarks": remarks.strip(),
	"vod_content": info.strip(),
	"vod_play_from": "$$$".join(play_sources.keys()),
	"vod_play_url": "$$$".join(
		"#".join(f "{ep['name']}${ep['url']}"
			for ep in source) for source in play_sources.values()
	)
}

def _parse_video_list(self, html):
	pattern = re.compile(
		r '<li><a href="(.*?)" title="(.*?)".*?<img src="(.*?)".*?<span class="tt">(.*?)</span>.*?</li>',
		re.S
	)
videos = []
for item in pattern.findall(html):
	video_url, title, cover, remarks = item
if not video_url.startswith('http'):
	video_url = urllib.parse.urljoin(self.base_url, video_url)
if not cover.startswith('http'):
	cover = urllib.parse.urljoin(self.base_url, cover)

videos.append({
	"vod_id": video_url,
	"vod_name": title,
	"vod_pic": cover,
	"vod_remarks": remarks
})
return videos

def get_play_url(self, play_page_url):
	# 实际播放地址需要进一步解析播放页
# 这里简化处理， 实际可能需要解析iframe或JavaScript生成的地址
return play_page_url

if __name__ == "__main__":
	spider = DyxzTVSpider()

# 测试分类
print("分类列表:")
print(json.dumps(spider.get_categories(), ensure_ascii = False, indent = 2))

# 测试首页视频
print("\n首页视频:")
print(json.dumps(spider.get_videos_by_category("1"), ensure_ascii = False, indent = 2))

# 测试搜索
print("\n搜索结果:")
print(json.dumps(spider.search("复仇者"), ensure_ascii = False, indent = 2))

# 测试详情
test_url = "/vod/12345.html"
# 替换为实际获取到的URL
print("\n视频详情:")
print(json.dumps(spider.get_video_detail(test_url), ensure_ascii = False, indent = 2))