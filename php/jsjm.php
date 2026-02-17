<?php
error_reporting(0);
ini_set('display_errors',0);
ini_set('memory_limit','256M');
ini_set('max_execution_time',120);
header('Content-Type: text/html; charset=utf-8');

if(!function_exists('startsWith')){
function startsWith($h,$n){
return $n===''||(strpos($h,$n)===0&&is_string($h));
}
}

if(!function_exists('endsWith')){
function endsWith($h,$n){
return $n===''||(substr($h,-strlen($n))===$n&&is_string($h));
}
}

function cleanUtf8($s){
if(!is_string($s))return '';
$s=@mb_convert_encoding($s,'UTF-8','UTF-8');
$s=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u','',$s);
return str_replace("\xef\xbb\xbf",'',$s);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
ob_clean();
$resp=['status'=>'error','msg'=>'','data'=>''];
try{
$opt=$_POST['opt']??'';
if($opt==='rsa'){
$action=$_POST['rsa_action']??'';
$key=trim($_POST['rsa_key']??'');
$data=trim($_POST['rsa_data']??'');
$signature=trim($_POST['rsa_signature']??'');
$alg=$_POST['rsa_alg']??'sha256';
if($action===''||$key===''||$data=='')throw new Exception('参数不完整');
if(!extension_loaded('openssl')) throw new Exception('PHP未开启openssl扩展');
if($action==='public_encrypt'){
$pub=openssl_pkey_get_public($key);
if(!$pub)throw new Exception('公钥格式错误');
$enc='';
openssl_public_encrypt($data,$enc,$pub);
openssl_free_key($pub);
$resp['status']='success';
$resp['msg']='公钥加密成功';
$resp['data']=base64_encode($enc);
}else if($action==='private_decrypt'){
$pri=openssl_pkey_get_private($key);
if(!$pri)throw new Exception('私钥格式错误');
$dec='';
openssl_private_decrypt(base64_decode($data),$dec,$pri);
openssl_free_key($pri);
$resp['status']='success';
$resp['msg']='私钥解密成功';
$resp['data']=$dec;
}else if($action==='private_sign'){
$pri=openssl_pkey_get_private($key);
if(!$pri)throw new Exception('私钥格式错误');
$sig='';
openssl_sign($data,$sig,$pri,$alg);
openssl_free_key($pri);
$resp['status']='success';
$resp['msg']='私钥签名成功';
$resp['data']=base64_encode($sig);
}else if($action==='public_verify'){
$pub=openssl_pkey_get_public($key);
if(!$pub)throw new Exception('公钥格式错误');
$ok=openssl_verify($data,base64_decode($signature),$pub,$alg);
openssl_free_key($pub);
if($ok===1){
$resp['status']='success';
$resp['msg']='验签通过';
$resp['data']='验签结果：有效✅';
}else{
$resp['status']='error';
$resp['msg']='验签失败';
$resp['data']='验签结果：无效❌';
}
}else{
throw new Exception('不支持的操作');
}
}else if($opt==='gbk_encode'){
$str=trim($_POST['str']??'');
if($str==='')throw new Exception('字符串不能为空');
if(!extension_loaded('mbstring'))throw new Exception('PHP未开启mbstring扩展');
$gbkStr=mb_convert_encoding($str,'GBK','UTF-8');
$bytes=[];
for($i=0;$i<strlen($gbkStr);$i++){
$bytes[]=ord($gbkStr[$i]);
}
$resp['status']='success';
$resp['msg']='GBK编码成功';
$resp['data']=$bytes;
}else{
throw new Exception('非法操作');
}
}catch(Exception $e){
$resp['msg']=$e->getMessage();
}
echo json_encode($resp,JSON_UNESCAPED_UNICODE);
exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>🔐逆向综合工具箱</title>
<script src="https://cdn.bootcdn.net/ajax/libs/crypto-js/4.2.0/crypto-js.min.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:system-ui,sans-serif}
body{background:#f5f7fa;padding:10px}
.container{max-width:1000px;margin:0 auto}
.header{text-align:center;margin-bottom:15px}
.header h1{color:#2196F3;font-size:20px}
.btn-clear-all{padding:8px 16px;background:#ccc;color:#333;border:none;border-radius:4px;cursor:pointer;margin:0 auto 10px;display:block;width:180px}
.tabs{display:flex;gap:4px;margin-bottom:10px;overflow-x:auto;padding-bottom:5px}
.tab-btn{flex:1;min-width:80px;padding:8px;text-align:center;border:none;border-radius:4px;cursor:pointer;background:#e0e0e0;color:#333;font-weight:bold;font-size:12px}
.tab-btn.active{background:#2196F3;color:#fff}
.panel{background:#fff;padding:12px;border-radius:6px;box-shadow:0 1px 4px rgba(0,0,0,0.05);margin-bottom:10px;display:none}
.panel.active{display:block}
.form-group{margin-bottom:10px}
.form-group label{display:block;margin-bottom:4px;font-weight:bold;color:#333;font-size:13px}
input,textarea,select{width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;font-size:12px;margin-bottom:6px}
textarea{height:180px;font-family:Consolas,Monaco,monospace;overflow-y:auto;resize:none}
.textarea-sm{height:60px!important}
.btn{padding:8px 12px;border:none;border-radius:4px;cursor:pointer;margin-right:6px;margin-bottom:6px;background:#4CAF50;color:#fff;font-weight:bold;font-size:12px}
.btn-clear{background:#ff9800}
.btn-upload{background:#2196F3}
.tip{padding:8px;border-radius:4px;margin:8px 0;font-size:12px;font-weight:bold}
.tip-success{background:#e8f5e9;color:#2E7D32}
.tip-warn{background:#fff3e0;color:#f57c00}
.tip-error{background:#ffebee;color:#d32f2f}
.search-bar{display:flex;gap:6px;margin:8px 0;align-items:center}
.search-bar select,.search-bar input{flex:1;margin-bottom:0}
.search-bar .btn{flex:0 0 60px;padding:6px;margin:0}
.config-row{display:flex;gap:6px;margin-bottom:6px;flex-wrap:wrap;align-items:center}
.config-item{flex:1;min-width:100px}
.log-area{font-size:12px;color:#666;margin:6px 0;padding:6px;background:#f9f9f9;border-radius:4px}
.inline-tip{font-size:12px;margin:4px 0;display:none}
.inline-tip-success{color:#2E7D32}
.inline-tip-error{color:#d32f2f}
.popup-tip{position:fixed;top:20px;left:50%;transform:translateX(-50%);padding:12px 20px;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,0.1);z-index:9999;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:50%;transition:opacity 0.3s ease}
.popup-tip.success{background:#f0f9eb;color:#52c41a;border-left:4px solid #52c41a}
.popup-tip.error{background:#fff1f0;color:#f5222d;border-left:4px solid #f5222d}
.popup-tip.warn{background:#fffbe6;color:#faad14;border-left:4px solid #faad14}
</style>
</head>
<body>
<div class="container">
<div class="header">
<h1>🔐逆向综合工具箱</h1>
</div>
<button class="btn-clear-all" onclick="clearAllTools()">🗑️一键清空所有</button>
<div class="tabs">
<button class="tab-btn active" onclick="switchTab('aes')">AES加解密</button>
<button class="tab-btn" onclick="switchTab('rsa')">RSA/ECDSA</button>
<button class="tab-btn" onclick="switchTab('sha')">SHA哈希</button>
<button class="tab-btn" onclick="switchTab('hmac')">HMAC签名</button>
<button class="tab-btn" onclick="switchTab('md5')">MD5编码</button>
<button class="tab-btn" onclick="switchTab('base64')">Base64</button>
<button class="tab-btn" onclick="switchTab('url')">URL编码</button>
<button class="tab-btn" onclick="switchTab('unicode')">Unicode</button>
<button class="tab-btn" onclick="switchTab('hex')">进制互转</button>
<button class="tab-btn" onclick="switchTab('timestamp')">时间戳</button>
</div>

<div class="panel active" id="aes">
<div class="form-group">
<label>AES加解密</label>
<textarea id="aes_input" placeholder="输入待加密/解密内容"></textarea>
<button class="btn btn-upload" onclick="uploadFile('aes_input')">📤上传</button>
<div class="config-row">
<div class="config-item">
<label>输入格式</label>
<select id="aes_in_format">
<option value="text" selected>文本(UTF-8)</option>
<option value="hex">十六进制(Hex)</option>
</select>
</div>
<div class="config-item">
<label>输入编码</label>
<select id="aes_input_enc">
<option value="UTF8" selected>UTF-8</option>
<option value="GBK">GBK</option>
<option value="GB2312">GB2312</option>
<option value="UTF16LE">UTF-16LE</option>
<option value="UTF16BE">UTF-16BE</option>
<option value="ISO88591">ISO-8859-1</option>
<option value="ASCII">ASCII</option>
</select>
</div>
</div>
<div class="config-row">
<div class="config-item">
<label>密文格式</label>
<select id="aes_cipher_format">
<option value="Base64" selected>Base64</option>
<option value="Hex">十六进制(Hex)</option>
<option value="Base64URL">Base64URL</option>
</select>
</div>
</div>
<div class="config-row">
<div class="config-item">
<label>输出格式</label>
<select id="aes_out_format">
<option value="Base64" selected>Base64</option>
<option value="Base64URL">Base64URL</option>
<option value="hex_lower">十六进制(Hex)-小写</option>
<option value="hex_upper">十六进制(Hex)-大写</option>
</select>
</div>
<div class="config-item">
<label>输出编码</label>
<select id="aes_output_enc">
<option value="UTF8" selected>UTF-8</option>
<option value="GBK">GBK</option>
<option value="GB2312">GB2312</option>
<option value="UTF16LE">UTF-16LE</option>
<option value="UTF16BE">UTF-16BE</option>
<option value="ISO88591">ISO-8859-1</option>
<option value="ASCII">ASCII</option>
</select>
</div>
</div>
<div class="config-row">
<div class="config-item">
<label>模式</label>
<select id="aes_mode">
<option value="ECB">ECB</option>
<option value="CBC" selected>CBC</option>
<option value="OFB">OFB</option>
<option value="CFB">CFB</option>
<option value="CTS">CTS</option>
<option value="CTR">CTR</option>
<option value="GCM">GCM</option>
</select>
</div>
<div class="config-item">
<label>填充</label>
<select id="aes_padding">
<option value="None">None</option>
<option value="Pkcs7" selected>PKCS7</option>
<option value="ZeroPadding">ZeroPadding</option>
<option value="AnsiX923">ANSIX923</option>
<option value="Iso10126">ISO10126</option>
</select>
</div>
<div class="config-item">
<label>密钥长度</label>
<select id="aes_key_length">
<option value="128" selected>128bit</option>
<option value="192">192bit</option>
<option value="256">256bit</option>
</select>
</div>
</div>
<div class="config-row">
<div class="config-item">
<label>密钥格式</label>
<select id="aes_key_format">
<option value="text" selected>Text</option>
<option value="hex">Hex</option>
</select>
</div>
<div class="config-item">
<label>偏移量格式</label>
<select id="aes_iv_format">
<option value="text" selected>Text</option>
<option value="hex">Hex</option>
</select>
</div>
</div>
<input type="text" id="aes_key" placeholder="密钥Key">
<div id="aes_key_tip" class="inline-tip"></div>
<input type="text" id="aes_iv" placeholder="偏移量IV">
<div id="aes_iv_tip" class="inline-tip"></div>
</div>
<button class="btn" onclick="aesEncrypt()">🔒加密</button>
<button class="btn" onclick="aesDecrypt()">🔓解密</button>
<button class="btn btn-clear" onclick="clearField('aes_input,aes_key,aes_iv,aes_output,aes_key_tip,aes_iv_tip')">🗑️清空</button>
<button class="btn" onclick="rollback('aes_output','aes_input')">结果回滚输入</button>
<div class="form-group">
<label>处理结果</label>
<textarea id="aes_output" readonly></textarea>
<button class="btn" onclick="copyResult('aes_output')">📋复制</button>
<button class="btn" onclick="downloadOutput('aes_output','aes_result')">💾下载</button>
<button class="btn btn-clear" onclick="clearOutput('aes_output')">🗑️清空输出</button>
</div>
</div>

<div class="panel" id="rsa">
<div class="form-group">
<label>RSA/ECDSA加解密/签名验签</label>
<textarea id="rsa_data" placeholder="输入明文/密文/待签名字符串"></textarea>
<button class="btn btn-upload" onclick="uploadFile('rsa_data')">📤上传</button>
<div class="config-row">
<div class="config-item">
<label>输入编码</label>
<select id="rsa_input_enc">
<option value="UTF8" selected>UTF-8</option>
<option value="GBK">GBK</option>
<option value="GB2312">GB2312</option>
<option value="UTF16LE">UTF-16LE</option>
<option value="UTF16BE">UTF-16BE</option>
<option value="ISO88591">ISO-8859-1</option>
<option value="ASCII">ASCII</option>
</select>
</div>
<div class="config-item">
<label>操作类型</label>
<select id="rsa_action">
<option value="public_encrypt">公钥加密</option>
<option value="private_decrypt">私钥解密</option>
<option value="private_sign">私钥签名</option>
<option value="public_verify">公钥验签</option>
</select>
</div>
<div class="config-item">
<label>算法</label>
<select id="rsa_alg">
<option value="sha256">SHA256</option>
<option value="sha1">SHA1</option>
<option value="md5">MD5</option>
</select>
</div>
</div>
<input type="text" id="rsa_key" placeholder="公钥/私钥（PEM格式）">
<input type="text" id="rsa_signature" placeholder="验签时填写签名（Base64）">
<div id="rsa_tip" class="inline-tip"></div>
</div>
<button class="btn" onclick="rsaOperate()">🚀执行</button>
<button class="btn btn-clear" onclick="clearField('rsa_data,rsa_key,rsa_signature,rsa_output,rsa_tip')">🗑️清空</button>
<button class="btn" onclick="rollback('rsa_output','rsa_data')">结果回滚输入</button>
<div class="form-group">
<label>处理结果</label>
<textarea id="rsa_output" readonly></textarea>
<button class="btn" onclick="copyResult('rsa_output')">📋复制</button>
<button class="btn" onclick="downloadOutput('rsa_output','rsa_result')">💾下载</button>
<button class="btn btn-clear" onclick="clearOutput('rsa_output')">🗑️清空输出</button>
</div>
</div>

<div class="panel" id="sha">
<div class="form-group">
<label>SHA哈希（SHA1/SHA256/SHA512）</label>
<div class="config-row">
<div class="config-item">
<label>输入格式</label>
<select id="sha_in_format">
<option value="text" selected>文本(UTF-8)</option>
<option value="hex">十六进制(Hex)</option>
</select>
</div>
<div class="config-item">
<label>输入编码</label>
<select id="sha_input_enc">
<option value="UTF8" selected>UTF-8</option>
<option value="GBK">GBK</option>
<option value="GB2312">GB2312</option>
<option value="UTF16LE">UTF-16LE</option>
<option value="UTF16BE">UTF-16BE</option>
<option value="ISO88591">ISO-8859-1</option>
<option value="ASCII">ASCII</option>
</select>
</div>
</div>
<div class="config-row">
<div class="config-item">
<label>哈希算法</label>
<select id="sha_type">
<option value="SHA1">SHA1</option>
<option value="SHA256" selected>SHA256</option>
<option value="SHA512">SHA512</option>
</select>
</div>
<div class="config-item">
<label>输出格式</label>
<select id="sha_out_format">
<option value="hex_lower" selected>十六进制(Hex)-小写</option>
<option value="hex_upper">十六进制(Hex)-大写</option>
</select>
</div>
</div>
<textarea id="sha_input" placeholder="输入待哈希内容"></textarea>
<button class="btn btn-upload" onclick="uploadFile('sha_input')">📤上传</button>
<button class="btn" onclick="shaEncode()">🔐生成哈希</button>
<button class="btn btn-clear" onclick="clearField('sha_input,sha_output')">🗑️清空</button>
</div>
<div class="form-group">
<label>哈希结果</label>
<textarea id="sha_output" readonly></textarea>
<button class="btn" onclick="copyResult('sha_output')">📋复制</button>
<button class="btn" onclick="downloadOutput('sha_output','sha_result')">💾下载</button>
<button class="btn btn-clear" onclick="clearOutput('sha_output')">🗑️清空输出</button>
</div>
</div>

<div class="panel" id="hmac">
<div class="form-group">
<label>HMAC签名（带密钥）</label>
<div class="config-row">
<div class="config-item">
<label>输入格式</label>
<select id="hmac_in_format">
<option value="text" selected>文本(UTF-8)</option>
<option value="hex">十六进制(Hex)</option>
</select>
</div>
<div class="config-item">
<label>输入编码</label>
<select id="hmac_input_enc">
<option value="UTF8" selected>UTF-8</option>
<option value="GBK">GBK</option>
<option value="GB2312">GB2312</option>
<option value="UTF16LE">UTF-16LE</option>
<option value="UTF16BE">UTF-16BE</option>
<option value="ISO88591">ISO-8859-1</option>
<option value="ASCII">ASCII</option>
</select>
</div>
</div>
<div class="config-row">
<div class="config-item">
<label>签名算法</label>
<select id="hmac_type">
<option value="MD5">HMAC-MD5</option>
<option value="SHA1">HMAC-SHA1</option>
<option value="SHA256" selected>HMAC-SHA256</option>
</select>
</div>
<div class="config-item">
<label>输出格式</label>
<select id="hmac_out_format">
<option value="hex_lower" selected>十六进制(Hex)-小写</option>
<option value="hex_upper">十六进制(Hex)-大写</option>
<option value="base64">Base64</option>
</select>
</div>
</div>
<input type="text" id="hmac_key" placeholder="签名密钥Key">
<textarea id="hmac_input" placeholder="输入待签名字符串"></textarea>
<button class="btn btn-upload" onclick="uploadFile('hmac_input')">📤上传</button>
<button class="btn" onclick="hmacSign()">🔏生成签名</button>
<button class="btn btn-clear" onclick="clearField('hmac_input,hmac_key,hmac_output')">🗑️清空</button>
</div>
<div class="form-group">
<label>签名结果</label>
<textarea id="hmac_output" readonly></textarea>
<button class="btn" onclick="copyResult('hmac_output')">📋复制</button>
<button class="btn" onclick="downloadOutput('hmac_output','hmac_result')">💾下载</button>
<button class="btn btn-clear" onclick="clearOutput('hmac_output')">🗑️清空输出</button>
</div>
</div>

<div class="panel" id="md5">
<div class="form-group">
<label>MD5编码（不可逆）</label>
<textarea id="md5_input" placeholder="输入待编码内容"></textarea>
<button class="btn btn-upload" onclick="uploadFile('md5_input')">📤上传</button>
<div class="config-row">
<div class="config-item">
<label>输入格式</label>
<select id="md5_in_format">
<option value="text" selected>文本(UTF-8)</option>
<option value="hex">十六进制(Hex)</option>
</select>
</div>
<div class="config-item">
<label>输入编码</label>
<select id="md5_input_enc">
<option value="UTF8" selected>UTF-8</option>
<option value="GBK">GBK</option>
<option value="GB2312">GB2312</option>
<option value="UTF16LE">UTF-16LE</option>
<option value="UTF16BE">UTF-16BE</option>
<option value="ISO88591">ISO-8859-1</option>
<option value="ASCII">ASCII</option>
</select>
</div>
</div>
<div class="config-row">
<div class="config-item">
<label>输出格式</label>
<select id="md5_out_format">
<option value="hex_lower" selected>十六进制(Hex)-小写</option>
<option value="hex_upper">十六进制(Hex)-大写</option>
</select>
</div>
</div>
<button class="btn" onclick="md5Encode()">🔼生成MD5</button>
<button class="btn btn-clear" onclick="clearField('md5_input,md5_output')">🗑️清空</button>
</div>
<div class="form-group">
<label>MD5结果（32位）</label>
<textarea id="md5_output" readonly></textarea>
<button class="btn" onclick="copyResult('md5_output')">📋复制</button>
<button class="btn" onclick="downloadOutput('md5_output','md5_result')">💾下载</button>
<button class="btn btn-clear" onclick="clearOutput('md5_output')">🗑️清空输出</button>
</div>
</div>

<div class="panel" id="base64">
<div class="form-group">
<label>Base64编码/解码</label>
<textarea id="base64_input" placeholder="输入待处理内容"></textarea>
<button class="btn btn-upload" onclick="uploadFile('base64_input')">📤上传</button>
<div class="config-row">
<div class="config-item">
<label>输入格式</label>
<select id="base64_in_format">
<option value="text" selected>文本(UTF-8)</option>
<option value="hex">十六进制(Hex)</option>
</select>
</div>
<div class="config-item">
<label>输入编码</label>
<select id="base64_input_enc">
<option value="UTF8" selected>UTF-8</option>
<option value="GBK">GBK</option>
<option value="GB2312">GB2312</option>
<option value="UTF16LE">UTF-16LE</option>
<option value="UTF16BE">UTF-16BE</option>
<option value="ISO88591">ISO-8859-1</option>
<option value="ASCII">ASCII</option>
</select>
</div>
</div>
<div class="config-row">
<div class="config-item">
<label>输出格式</label>
<select id="base64_out_format">
<option value="text" selected>文本(UTF-8)</option>
<option value="hex_lower">十六进制(Hex)-小写</option>
<option value="hex_upper">十六进制(Hex)-大写</option>
</select>
</div>
<div class="config-item">
<label>输出编码</label>
<select id="base64_output_enc">
<option value="UTF8" selected>UTF-8</option>
<option value="GBK">GBK</option>
<option value="GB2312">GB2312</option>
<option value="UTF16LE">UTF-16LE</option>
<option value="UTF16BE">UTF-16BE</option>
<option value="ISO88591">ISO-8859-1</option>
<option value="ASCII">ASCII</option>
</select>
</div>
</div>
<button class="btn" onclick="base64Encode()">🔼编码</button>
<button class="btn" onclick="base64Decode()">🔽解码</button>
<button class="btn btn-clear" onclick="clearField('base64_input,base64_output')">🗑️清空</button>
</div>
<div class="form-group">
<label>处理结果</label>
<textarea id="base64_output" readonly></textarea>
<button class="btn" onclick="copyResult('base64_output')">📋复制</button>
<button class="btn" onclick="downloadOutput('base64_output','base64_result')">💾下载</button>
<button class="btn" onclick="rollback('base64_output','base64_input')">结果回滚输入</button>
<button class="btn btn-clear" onclick="clearOutput('base64_output')">🗑️清空输出</button>
</div>
</div>

<div class="panel" id="url">
<div class="form-group">
<label>URL编码/解码</label>
<textarea id="url_input" placeholder="输入待处理内容"></textarea>
<button class="btn btn-upload" onclick="uploadFile('url_input')">📤上传</button>
<div class="config-row">
<div class="config-item">
<label>输入编码</label>
<select id="url_input_enc">
<option value="UTF8" selected>UTF-8</option>
<option value="GBK">GBK</option>
<option value="GB2312">GB2312</option>
<option value="UTF16LE">UTF-16LE</option>
<option value="UTF16BE">UTF-16BE</option>
<option value="ISO88591">ISO-8859-1</option>
<option value="ASCII">ASCII</option>
</select>
</div>
<div class="config-item">
<label>输出编码</label>
<select id="url_output_enc">
<option value="UTF8" selected>UTF-8</option>
<option value="GBK">GBK</option>
<option value="GB2312">GB2312</option>
<option value="UTF16LE">UTF-16LE</option>
<option value="UTF16BE">UTF-16BE</option>
<option value="ISO88591">ISO-8859-1</option>
<option value="ASCII">ASCII</option>
</select>
</div>
</div>
<button class="btn" onclick="urlEncode()">🔼编码</button>
<button class="btn" onclick="urlDecode()">🔽解码</button>
<button class="btn btn-clear" onclick="clearField('url_input,url_output')">🗑️清空</button>
</div>
<div class="form-group">
<label>处理结果</label>
<textarea id="url_output" readonly></textarea>
<button class="btn" onclick="copyResult('url_output')">📋复制</button>
<button class="btn" onclick="downloadOutput('url_output','url_result')">💾下载</button>
<button class="btn" onclick="rollback('url_output','url_input')">结果回滚输入</button>
<button class="btn btn-clear" onclick="clearOutput('url_output')">🗑️清空输出</button>
</div>
</div>

<div class="panel" id="unicode">
<div class="form-group">
<label>Unicode编码/解码</label>
<textarea id="unicode_input" placeholder="输入待处理内容"></textarea>
<button class="btn btn-upload" onclick="uploadFile('unicode_input')">📤上传</button>
<div class="config-row">
<div class="config-item">
<label>输入编码</label>
<select id="unicode_input_enc">
<option value="UTF8" selected>UTF-8</option>
<option value="GBK">GBK</option>
<option value="GB2312">GB2312</option>
<option value="UTF16LE">UTF-16LE</option>
<option value="UTF16BE">UTF-16BE</option>
<option value="ISO88591">ISO-8859-1</option>
<option value="ASCII">ASCII</option>
</select>
</div>
<div class="config-item">
<label>输出编码</label>
<select id="unicode_output_enc">
<option value="UTF8" selected>UTF-8</option>
<option value="GBK">GBK</option>
<option value="GB2312">GB2312</option>
<option value="UTF16LE">UTF-16LE</option>
<option value="UTF16BE">UTF-16BE</option>
<option value="ISO88591">ISO-8859-1</option>
<option value="ASCII">ASCII</option>
</select>
</div>
</div>
<button class="btn" onclick="unicodeEncode()">🔼编码</button>
<button class="btn" onclick="unicodeDecode()">🔽解码</button>
<button class="btn btn-clear" onclick="clearField('unicode_input,unicode_output')">🗑️清空</button>
</div>
<div class="form-group">
<label>处理结果</label>
<textarea id="unicode_output" readonly></textarea>
<button class="btn" onclick="copyResult('unicode_output')">📋复制</button>
<button class="btn" onclick="downloadOutput('unicode_output','unicode_result')">💾下载</button>
<button class="btn" onclick="rollback('unicode_output','unicode_input')">结果回滚输入</button>
<button class="btn btn-clear" onclick="clearOutput('unicode_output')">🗑️清空输出</button>
</div>
</div>

<div class="panel" id="hex">
<div class="form-group">
<label style="white-space: nowrap; display: block; overflow: hidden; text-overflow: ellipsis;">进制互转（Bin/Dec/Hex/文本）</label>
<div class="config-row">
<div class="config-item">
<label>Hex输出格式</label>
<select id="hex_case">
<option value="lower" selected>小写</option>
<option value="upper">大写</option>
</select>
</div>
<div class="config-item">
<label>文本编码</label>
<select id="hex_str_enc">
<option value="UTF8" selected>UTF-8</option>
<option value="GBK">GBK</option>
<option value="GB2312">GB2312</option>
<option value="UTF16LE">UTF-16LE</option>
<option value="UTF16BE">UTF-16BE</option>
<option value="ISO88591">ISO-8859-1</option>
<option value="ASCII">ASCII</option>
</select>
</div>
</div>
<textarea id="hex_input" placeholder="输入数字/文本"></textarea>
<button class="btn btn-upload" onclick="uploadFile('hex_input')">📤上传</button>
<button class="btn" onclick="strToHex()">文本→Hex</button>
<button class="btn" onclick="hexToStr()">Hex→文本</button>
<button class="btn" onclick="decToHex()">10进制→Hex</button>
<button class="btn" onclick="hexToDec()">Hex→10进制</button>
<button class="btn" onclick="binToDec()">2进制→10进制</button>
<button class="btn" onclick="decToBin()">10进制→2进制</button>
<button class="btn" onclick="strToUtf8Bytes()">字符串→UTF-8字节</button>
<button class="btn" onclick="strToGbkBytes()">字符串→GBK字节</button>
<button class="btn btn-clear" onclick="clearField('hex_input,hex_output')">🗑️清空</button>
</div>
<div class="form-group">
<label>处理结果</label>
<textarea id="hex_output" readonly class="textarea-sm" style="height:180px!important;"></textarea>
<button class="btn" onclick="copyResult('hex_output')">📋复制</button>
<button class="btn" onclick="downloadOutput('hex_output','hex_result')">💾下载</button>
<button class="btn" onclick="rollback('hex_output','hex_input')">结果回滚输入</button>
<button class="btn btn-clear" onclick="clearOutput('hex_output')">🗑️清空输出</button>
</div>
</div>

<div class="panel" id="timestamp">
<div class="form-group">
<label>时间戳↔标准时间</label>
<textarea id="ts_input" placeholder="输入时间戳/日期字符串"></textarea>
<div id="ts_tip" class="inline-tip"></div>
<button class="btn btn-upload" onclick="uploadFile('ts_input')">📤上传</button>
<button class="btn" onclick="tsToDate()">时间戳→日期</button>
<button class="btn" onclick="dateToTs()">日期→时间戳</button>
<button class="btn" onclick="nowTs()">获取当前时间戳</button>
<button class="btn btn-clear" onclick="clearField('ts_input,ts_output,ts_tip')">🗑️清空</button>
</div>
<div class="form-group">
<label>处理结果</label>
<textarea id="ts_output" readonly class="textarea-sm" style="height:180px!important;"></textarea>
<button class="btn" onclick="copyResult('ts_output')">📋复制</button>
<button class="btn" onclick="downloadOutput('ts_output','ts_result')">💾下载</button>
<button class="btn" onclick="rollback('ts_output','ts_input')">结果回滚输入</button>
<button class="btn btn-clear" onclick="clearOutput('ts_output')">🗑️清空输出</button>
</div>
</div>

</div>
<script>
function downloadOutput(e,n,t='text/plain'){
const c=document.getElementById(e).value.trim();
if(!c){
showPopup('无内容可下载','error');
return;
}
const b=new Blob([c],{type:t});
const a=document.createElement('a');
a.href=URL.createObjectURL(b);
const o=t.split('/')[1]||'txt';
a.download=`${n}.${o}`;
a.click();
URL.revokeObjectURL(a.href);
}
function copyResult(e){
const n=document.getElementById(e);
if(!n||!n.value.trim()){
showPopup('无内容可复制','error');
return;
}
n.select();
try{
document.execCommand('copy');
showPopup('复制成功');
}catch(t){
showPopup('复制失败，请手动复制','error');
}
}
function uploadFile(e){
const n=document.createElement('input');
n.type='file';
n.accept='*';
n.onchange=function(t){
const c=t.target.files[0];
if(!c)return;
const b=new FileReader();
b.onload=function(r){
const o=document.getElementById(e);
o&&(o.value=r.target.result);
showPopup(`已上传：${c.name}`);
};
b.readAsText(c,'UTF-8');
};
n.click();
}
function clearField(e){
e.split(',').forEach(n=>{
const t=document.getElementById(n);
if(t){
t.value='';
if(t.classList.contains('inline-tip')){
t.style.display='none';
t.innerText='';
t.className='inline-tip';
}
}
});
}
function showPopup(msg,type='success'){
const existingPopup=document.querySelector('.popup-tip');
if(existingPopup)existingPopup.remove();
const popup=document.createElement('div');
popup.className=`popup-tip ${type}`;
const icon=type==='success'?'✅':type==='error'?'❌':'⚠️';
popup.innerHTML=`<span>${icon}</span>${msg}`;
document.body.appendChild(popup);
setTimeout(()=>{
popup.style.opacity='0';
setTimeout(()=>popup.remove(),300);
},2800);
}
function showInlineTip(id,msg,type='error'){
const el=document.getElementById(id);
if(el){
clearTimeout(el.timer);
el.innerText=msg;
el.className=`inline-tip inline-tip-${type}`;
el.style.display='block';
el.timer=setTimeout(()=>{
el.style.display='none';
el.innerText='';
el.className='inline-tip';
},3000);
}
}
function rollback(outId,inId){
const out=document.getElementById(outId),inEl=document.getElementById(inId);
if(out&&inEl&&out.value.trim()){
inEl.value=out.value.trim();
showPopup('结果已回滚到输入框');
}else{
showPopup('无结果可回滚','error');
}
}
function clearAllTools(){
const allInputs=document.querySelectorAll('input,textarea');
allInputs.forEach(i=>i.value='');
const allTips=document.querySelectorAll('.tip,.inline-tip');
allTips.forEach(t=>{
t.innerHTML='';
t.style.display='none';
t.className='inline-tip';
});
const logs=document.querySelectorAll('.log-area');
logs.forEach(l=>l.style.display='none');
showPopup('已清空所有工具内容');
}
function clearOutput(id){
const el=document.getElementById(id);
if(el)el.value='';
}
function switchTab(tabId){
document.querySelectorAll('.tab-btn').forEach(btn=>btn.classList.remove('active'));
document.querySelectorAll('.panel').forEach(panel=>panel.classList.remove('active'));
const activeBtn=document.querySelector(`.tab-btn[onclick="switchTab('${tabId}')"]`);
const activePanel=document.getElementById(tabId);
if(activeBtn)activeBtn.classList.add('active');
if(activePanel)activePanel.classList.add('active');
}
function getEncoder(enc){
switch(enc){
case 'GBK':case 'GB2312':return CryptoJS.enc.Latin1;
case 'UTF16LE':return CryptoJS.enc.Utf16LE;
case 'UTF16BE':return CryptoJS.enc.Utf16BE;
case 'ISO88591':case 'ASCII':return CryptoJS.enc.Latin1;
default:return CryptoJS.enc.Utf8;
}
}
function parseKeyValue(val,fmt){
val=val.trim();
if(!val)return null;
return fmt==='hex'?CryptoJS.enc.Hex.parse(val):CryptoJS.enc.Utf8.parse(val);
}
function formatCiphertext(ciphertext,format){
if(!ciphertext)return '';
switch(format){
case 'Hex':return ciphertext.ciphertext.toString(CryptoJS.enc.Hex);
case 'Base64URL':return ciphertext.toString().replace(/\+/g,'-').replace(/\//g,'_').replace(/=/g,'');
default:return ciphertext.toString();
}
}
function parseCiphertext(ciphertext,format){
if(!ciphertext.trim())return null;
ciphertext=ciphertext.trim().replace(/\s+/g,'');
switch(format){
case 'Hex':return CryptoJS.enc.Hex.parse(ciphertext.toLowerCase());
case 'Base64URL':
ciphertext=ciphertext.replace(/-/g,'+').replace(/_/g,'/');
while(ciphertext.length%4)ciphertext+='=';
return CryptoJS.enc.Base64.parse(ciphertext);
default:return CryptoJS.enc.Base64.parse(ciphertext);
}
}
function validateAesKey(key,keyLength){
if(!key)return false;
const expectedLen=parseInt(keyLength)/8;
return key.length===expectedLen;
}
function validateAesIv(iv,mode){
if(['ECB'].includes(mode))return true;
return iv&&iv.length===16;
}
function aesEncrypt(){
showInlineTip('aes_key_tip','','error');
showInlineTip('aes_iv_tip','','error');
const input=document.getElementById('aes_input').value.trim();
const inFmt=document.getElementById('aes_in_format').value;
const inEnc=document.getElementById('aes_input_enc').value;
const key=document.getElementById('aes_key').value.trim();
const keyFmt=document.getElementById('aes_key_format').value;
const iv=document.getElementById('aes_iv').value.trim();
const ivFmt=document.getElementById('aes_iv_format').value;
const mode=document.getElementById('aes_mode').value;
const padding=document.getElementById('aes_padding').value;
const keyLength=document.getElementById('aes_key_length').value;
const outFormat=document.getElementById('aes_out_format').value;
const output=document.getElementById('aes_output');
if(!input||!key){
showInlineTip('aes_key_tip','请输入内容和密钥','error');
return;
}
if(!validateAesKey(key,keyLength)){
showInlineTip('aes_key_tip',`密钥长度错误：当前${key.length}位，${keyLength}bit需${keyLength/8}字节`,'error');
return;
}
if(!validateAesIv(iv,mode)){
showInlineTip('aes_iv_tip','CBC模式需16位IV','error');
return;
}
try{
let pt=inFmt==='hex'?CryptoJS.enc.Hex.parse(input):getEncoder(inEnc).parse(input);
const keyParsed=parseKeyValue(key,keyFmt);
const ivParsed=iv?parseKeyValue(iv,ivFmt):null;
const config={mode:CryptoJS.mode[mode],padding:CryptoJS.pad[padding]};
if(ivParsed&&mode!=='ECB')config.iv=ivParsed;
const encrypted=CryptoJS.AES.encrypt(pt,keyParsed,config);
let res='';
switch(outFormat){
case 'hex_lower':res=encrypted.ciphertext.toString(CryptoJS.enc.Hex);break;
case 'hex_upper':res=encrypted.ciphertext.toString(CryptoJS.enc.Hex).toUpperCase();break;
case 'Base64URL':res=encrypted.toString().replace(/\+/g,'-').replace(/\//g,'_').replace(/=/g,'');break;
default:res=encrypted.toString();
}
output.value=res;
showInlineTip('aes_key_tip','AES加密成功','success');
}catch(err){
showInlineTip('aes_key_tip','加密失败：'+err.message,'error');
output.value='';
}
}
function aesDecrypt(){
showInlineTip('aes_key_tip','','error');
showInlineTip('aes_iv_tip','','error');
const input=document.getElementById('aes_input').value.trim();
const inFmt=document.getElementById('aes_in_format').value;
const key=document.getElementById('aes_key').value.trim();
const keyFmt=document.getElementById('aes_key_format').value;
const iv=document.getElementById('aes_iv').value.trim();
const ivFmt=document.getElementById('aes_iv_format').value;
const mode=document.getElementById('aes_mode').value;
const padding=document.getElementById('aes_padding').value;
const keyLength=document.getElementById('aes_key_length').value;
const cipherFormat=document.getElementById('aes_cipher_format').value;
const outEnc=document.getElementById('aes_output_enc').value;
const output=document.getElementById('aes_output');
if(!input||!key){
showInlineTip('aes_key_tip','请输入内容和密钥','error');
return;
}
if(!validateAesKey(key,keyLength)){
showInlineTip('aes_key_tip',`密钥长度错误：当前${key.length}位，${keyLength}bit需${keyLength/8}字节`,'error');
return;
}
if(!validateAesIv(iv,mode)){
showInlineTip('aes_iv_tip','CBC模式需16位IV','error');
return;
}
try{
let ct=inFmt==='hex'?CryptoJS.enc.Hex.parse(input):parseCiphertext(input,cipherFormat);
const keyParsed=parseKeyValue(key,keyFmt);
const ivParsed=iv?parseKeyValue(iv,ivFmt):null;
const config={mode:CryptoJS.mode[mode],padding:CryptoJS.pad[padding]};
if(ivParsed&&mode!=='ECB')config.iv=ivParsed;
const decrypted=CryptoJS.AES.decrypt({ciphertext:ct},keyParsed,config);
output.value=decrypted.toString(getEncoder(outEnc));
showInlineTip('aes_key_tip','AES解密成功','success');
}catch(err){
let errMsg='解密失败：';
if(err.message.includes('Malformed UTF-8 data'))errMsg+='参数不匹配';
else if(err.message.includes('unpad'))errMsg+='填充错误';
else errMsg+=err.message;
showInlineTip('aes_key_tip',errMsg,'error');
output.value='';
}
}
async function rsaOperate(){
const action=document.getElementById('rsa_action').value;
const key=document.getElementById('rsa_key').value.trim();
const data=document.getElementById('rsa_data').value.trim();
const signature=document.getElementById('rsa_signature').value.trim();
const alg=document.getElementById('rsa_alg').value;
const output=document.getElementById('rsa_output');
const tip=document.getElementById('rsa_tip');
if(!key||!data){
showInlineTip('rsa_tip','密钥和内容不能为空','error');
return;
}
if(action==='public_verify'&&!signature){
showInlineTip('rsa_tip','验签必须填写签名','error');
return;
}
try{
const fd=new FormData();
fd.append('opt','rsa');
fd.append('rsa_action',action);
fd.append('rsa_key',key);
fd.append('rsa_data',data);
fd.append('rsa_signature',signature);
fd.append('rsa_alg',alg);
const res=await fetch('',{method:'POST',body:fd});
const json=await res.json();
if(json.status==='success'){
output.value=json.data;
showInlineTip('rsa_tip',json.msg,'success');
}else{
output.value='';
showInlineTip('rsa_tip',json.msg,'error');
}
}catch(e){
showInlineTip('rsa_tip','请求失败','error');
output.value='';
}
}
function shaEncode(){
const input=document.getElementById('sha_input').value.trim();
const inFmt=document.getElementById('sha_in_format').value;
const inEnc=document.getElementById('sha_input_enc').value;
const type=document.getElementById('sha_type').value;
const of=document.getElementById('sha_out_format').value;
const output=document.getElementById('sha_output');
if(!input){showPopup('请输入内容','error');return;}
try{
let d=inFmt==='hex'?CryptoJS.enc.Hex.parse(input):getEncoder(inEnc).parse(input);
let h;
switch(type){
case 'SHA1':h=CryptoJS.SHA1(d);break;
case 'SHA512':h=CryptoJS.SHA512(d);break;
default:h=CryptoJS.SHA256(d);
}
output.value=of==='hex_upper'?h.toString().toUpperCase():h.toString();
showPopup('SHA哈希生成成功');
}catch(e){showPopup('哈希失败','error');}
}
function hmacSign(){
const input=document.getElementById('hmac_input').value.trim();
const inFmt=document.getElementById('hmac_in_format').value;
const inEnc=document.getElementById('hmac_input_enc').value;
const key=document.getElementById('hmac_key').value.trim();
const type=document.getElementById('hmac_type').value;
const of=document.getElementById('hmac_out_format').value;
const output=document.getElementById('hmac_output');
if(!input||!key){showPopup('请输入内容和密钥','error');return;}
try{
const kp=CryptoJS.enc.Utf8.parse(key);
let d=inFmt==='hex'?CryptoJS.enc.Hex.parse(input):getEncoder(inEnc).parse(input);
let s;
switch(type){
case 'MD5':s=CryptoJS.HmacMD5(d,kp);break;
case 'SHA1':s=CryptoJS.HmacSHA1(d,kp);break;
default:s=CryptoJS.HmacSHA256(d,kp);
}
output.value=of==='base64'?s.toString(CryptoJS.enc.Base64):(of==='hex_upper'?s.toString().toUpperCase():s.toString());
showPopup('HMAC签名生成成功');
}catch(e){showPopup('签名失败','error');}
}
function base64Encode(){
const ipt=document.getElementById('base64_input').value.trim();
const inf=document.getElementById('base64_in_format').value;
const enc=document.getElementById('base64_input_enc').value;
const of=document.getElementById('base64_out_format').value;
const out=document.getElementById('base64_output');
if(!ipt){showPopup('请输入内容','error');return;}
try{
let d=inf==='hex'?CryptoJS.enc.Hex.parse(ipt):getEncoder(enc).parse(ipt);
const b=CryptoJS.enc.Base64.stringify(d);
if(of==='text'){out.value=b;
}else{
let hex=[];
for(let i=0;i<b.length;i++)hex.push(b.charCodeAt(i).toString(16).padStart(2,'0'));
out.value=of==='hex_upper'?hex.join('').toUpperCase():hex.join('');
}
showPopup('Base64编码成功');
}catch(e){showPopup('编码失败','error');}
}
function base64Decode(){
const ipt=document.getElementById('base64_input').value.trim();
const of=document.getElementById('base64_out_format').value;
const enc=document.getElementById('base64_output_enc').value;
const out=document.getElementById('base64_output');
if(!ipt){showPopup('请输入内容','error');return;}
try{
const b=CryptoJS.enc.Base64.parse(ipt);
out.value=of.startsWith('hex')?(of==='hex_upper'?b.toString(CryptoJS.enc.Hex).toUpperCase():b.toString(CryptoJS.enc.Hex)):b.toString(getEncoder(enc));
showPopup('Base64解码成功');
}catch(e){showPopup('解码失败','error');}
}
function urlEncode(){
const ipt=document.getElementById('url_input').value.trim();
const out=document.getElementById('url_output');
if(!ipt){showPopup('请输入内容','error');return;}
out.value=encodeURIComponent(ipt);
showPopup('URL编码成功');
}
function urlDecode(){
const ipt=document.getElementById('url_input').value.trim();
const out=document.getElementById('url_output');
if(!ipt){showPopup('请输入内容','error');return;}
out.value=decodeURIComponent(ipt);
showPopup('URL解码成功');
}
function unicodeEncode(){
const ipt=document.getElementById('unicode_input').value.trim();
const out=document.getElementById('unicode_output');
if(!ipt){showPopup('请输入内容','error');return;}
out.value=ipt.split('').map(c=>'\\u'+c.charCodeAt(0).toString(16).padStart(4,'0')).join('');
showPopup('Unicode编码成功');
}
function unicodeDecode(){
const ipt=document.getElementById('unicode_input').value.trim();
const out=document.getElementById('unicode_output');
if(!ipt){showPopup('请输入内容','error');return;}
out.value=ipt.replace(/\\u([0-9a-fA-F]{4})/g,(m,c)=>String.fromCharCode(parseInt(c,16)));
showPopup('Unicode解码成功');
}
function md5Encode(){
const ipt=document.getElementById('md5_input').value.trim();
const inf=document.getElementById('md5_in_format').value;
const enc=document.getElementById('md5_input_enc').value;
const of=document.getElementById('md5_out_format').value;
const out=document.getElementById('md5_output');
if(!ipt){showPopup('请输入内容','error');return;}
let d=inf==='hex'?CryptoJS.enc.Hex.parse(ipt):getEncoder(enc).parse(ipt);
let r=CryptoJS.MD5(d).toString();
out.value=of==='hex_upper'?r.toUpperCase():r;
showPopup('MD5生成成功');
}
function strToHex(){
const ipt=document.getElementById('hex_input').value.trim();
const enc=document.getElementById('hex_str_enc').value;
const cs=document.getElementById('hex_case').value;
if(!ipt){showPopup('请输入文本','error');return;}
const b=getEncoder(enc).parse(ipt);
document.getElementById('hex_output').value=cs==='upper'?b.toString(CryptoJS.enc.Hex).toUpperCase():b.toString(CryptoJS.enc.Hex);
showPopup('转换成功');
}
function hexToStr(){
const ipt=document.getElementById('hex_input').value.trim().replace(/\s+/g,'');
const enc=document.getElementById('hex_str_enc').value;
if(!ipt||!/^[0-9a-fA-F]+$/.test(ipt)||ipt.length%2!==0){showPopup('Hex格式错误','error');return;}
document.getElementById('hex_output').value=CryptoJS.enc.Hex.parse(ipt).toString(getEncoder(enc));
showPopup('转换成功');
}
function decToHex(){
const ipt=document.getElementById('hex_input').value.trim();
const cs=document.getElementById('hex_case').value;
if(!/^\d+$/.test(ipt)){showPopup('请输入10进制数','error');return;}
document.getElementById('hex_output').value=cs==='upper'?BigInt(ipt).toString(16).toUpperCase():BigInt(ipt).toString(16);
showPopup('转换成功');
}
function hexToDec(){
const ipt=document.getElementById('hex_input').value.trim().replace(/\s+/g,'');
if(!ipt||!/^[0-9a-fA-F]+$/.test(ipt)){showPopup('Hex格式错误','error');return;}
document.getElementById('hex_output').value=BigInt('0x'+ipt).toString();
showPopup('转换成功');
}
function binToDec(){
const ipt=document.getElementById('hex_input').value.trim().replace(/\s+/g,'');
if(!ipt||!/^[01]+$/.test(ipt)){showPopup('2进制格式错误','error');return;}
document.getElementById('hex_output').value=BigInt('0b'+ipt).toString();
showPopup('转换成功');
}
function decToBin(){
const ipt=document.getElementById('hex_input').value.trim();
if(!/^\d+$/.test(ipt)){showPopup('请输入10进制数','error');return;}
document.getElementById('hex_output').value=BigInt(ipt).toString(2);
showPopup('转换成功');
}
function strToUtf8Bytes(){
const ipt=document.getElementById('hex_input').value.trim();
if(!ipt){showPopup('请输入文本','error');return;}
document.getElementById('hex_output').value=JSON.stringify(Array.from(new TextEncoder().encode(ipt)));
showPopup('转换成功');
}
async function strToGbkBytes(){
const ipt=document.getElementById('hex_input').value.trim();
if(!ipt){showPopup('请输入文本','error');return;}
const fd=new FormData();fd.append('opt','gbk_encode');fd.append('str',ipt);
const r=await fetch('',{method:'POST',body:fd});
const j=await r.json();
if(j.status==='success'){
document.getElementById('hex_output').value=JSON.stringify(j.data);
showPopup('GBK转换成功');
}else{
showPopup(j.msg||'失败','error');
}
}
function tsToDate(){
const ipt=document.getElementById('ts_input').value.trim();
if(!/^\d+$/.test(ipt)){showInlineTip('ts_tip','时间戳错误','error');return;}
const ts=ipt.length===10?parseInt(ipt)*1000:parseInt(ipt);
const d=new Date(ts);
if(isNaN(d.getTime())){showInlineTip('ts_tip','时间戳无效','error');return;}
document.getElementById('ts_output').value=`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}:${String(d.getSeconds()).padStart(2,'0')}`;
showPopup('转换成功');
}
function dateToTs(){
const ipt=document.getElementById('ts_input').value.trim();
const ts=new Date(ipt).getTime();
if(isNaN(ts)){showInlineTip('ts_tip','日期错误','error');return;}
document.getElementById('ts_output').value=`${ts}（毫秒）\n${Math.floor(ts/1000)}（秒）`;
showPopup('转换成功');
}
function nowTs(){
const ts=Date.now();const d=new Date(ts);
document.getElementById('ts_input').value=ts;
document.getElementById('ts_output').value=`${ts}（毫秒）\n${Math.floor(ts/1000)}（秒）\n${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}:${String(d.getSeconds()).padStart(2,'0')}`;
showInlineTip('ts_tip','获取成功','success');
}
</script>
</body>
</html>
