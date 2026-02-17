<?php
// 1.基础环境配置模块：统一时区适配跨境接口、强制输出JSON格式、兼容TVBox解析协议
date_default_timezone_set('Asia/Hong_Kong');
header('Content-Type: application/json; charset=utf-8');
// 初始化全局耗时统计变量（脚本总启动时间戳，微秒级）
$script_start_time = microtime(true);

// 2.低版本PHP兼容函数补全模块：补齐PHP7.0+缺失的原生函数，保证跨服务器环境稳定运行
if (!function_exists('json_validate')) {
    // 兼容函数：校验字符串是否为合法JSON
    function json_validate(string $json): bool {
        json_decode($json);
        return trim($json) !== '' && json_last_error() === JSON_ERROR_NONE;
    }
}
if (!function_exists('str_starts_with')) {
    // 兼容函数：判断字符串是否以指定前缀开头
    function str_starts_with(string $haystack, string $needle): bool {
        return strlen($needle) === 0 || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

// 3.核心配置中心模块（适配ppl.sszzyy.com解析流程：无加密、无密钥、纯接口转发）
$config = [
    // 3.1 请求核心配置：对接ppl前端页+授权接口+解析接口
    "request" => [
        "method"       => "GET", // 固定为GET，ppl全套接口均为GET请求
        "parseApi"     => "https://ppl.sszzyy.com/?url=", // ✅ 改成你要求的正确前端接口地址
        "authApi"      => "/api/auth.php", // 域名授权校验接口（必请求）
        "resolveApi"   => "/api/resolve.php", // 最终播放地址解析接口
        "auth_host"    => "www.sotvla.cc", // 授权接口必填的host参数
        "paramKeys"    => ["url", "video_url", "vurl"], // 接收视频地址的参数名，兼容多版本TVBox
        "videoField"   => "url", // 接口返回JSON里，播放地址对应的字段名
        "followRedirect" => true, // 是否自动跟随301/302重定向
        "timeout"      => 15, // 整个请求最大超时时间（秒）
        "connect_timeout" => 5, // 连接服务器超时时间（秒）
        "request_delay"=> [200, 500], // 请求前随机延迟：最小200ms，最大500ms，防频繁封IP
        "debug"        => false, // 调试日志开关：true开启，false关闭
        // 请求头适配ppl.sszzyy.com，防拦截
        "requestHeaders" => [
            "Host: ppl.sszzyy.com",
            "Connection: keep-alive",
            "sec-ch-ua: \"Not/A)Brand\";v=\"8\", \"Chromium\";v=\"126\", Android WebView\";v=\"126\"",
            "Accept: */*",
            "sec-ch-ua-mobile: ?1",
            "sec-ch-ua-platform: \"Android\"",
            "X-Requested-With: com.mmbox.xbrowser",
            "Sec-Fetch-Site: same-origin",
            "Sec-Fetch-Mode: cors",
            "Sec-Fetch-Dest: empty",
            "Accept-Encoding: gzip, deflate, br, zstd",
            "Accept-Language: zh-CN,zh;q=0.9,en-US,q=0.8,en;q=0.7"
        ],
        "userAgents" => [ // 安卓设备UA池，每次随机抽取，防止UA固定被封
            "Mozilla/5.0 (Linux; Android 15; V2171A Build/AP3A.240905.015.A2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.6478.71 Mobile Safari/537.36",
            "Mozilla/5.0 (Linux; Android 14; Pixel 8 Pro Build/UQ1A.240205.004) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.6167.184 Mobile Safari/537.36",
            "Mozilla/5.0 (Linux; Android 13; MI 13 Build/TKQ1.221114.001) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Mobile Safari/537.36",
            "Mozilla/5.0 (Linux; Android 12; Redmi K50 Pro Build/SKQ1.211006.001) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Mobile Safari/537.36",
            "Mozilla/5.0 (Linux; Android 14; Xiaomi 14 Build/UKQ1.230804.001) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Mobile Safari/537.36",
            "Mozilla/5.0 (Linux; Android 13; vivo X100 Build/TP1A.220624.014) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Mobile Safari/537.36",
            "Mozilla/5.0 (Linux; Android 12; OPPO Find X6 Build/SKQ1.220303.001) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36",
            "Mozilla/5.0 (Linux; Android 14; Samsung S24 Build/UP1A.231005.007) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36"
        ]
    ],
    // 3.4 错误响应配置：✅ 原有内容一个不删，只在后面新增容错提示
    "errorConfig" => [
        -1 => [
            "msg" => "未接收到视频地址!",
            "troubleshoot" => "1.检查TVBox解析链接是否携带url参数；2.确认paramKeys配置与盒子传参键名一致；3.检查参数是否被URL编码截断"
        ],
        -2 => [
            "msg" => "解析接口请求失败!",
            "troubleshoot" => "1.浏览器直接访问parseApi测试连通性；2.核对请求头Host/Referer/UA与抓包一致；3.检查服务器外网连通性；4.确认接口未被IP封禁"
        ],
        -3 => [
            "msg" => "播放地址无效/空!",
            "troubleshoot" => "1.核对videoField字段名大小写/嵌套格式（如data.play.url）；2.接口返回code非200或无播放字段；3.接口开启域名白名单，当前服务器域名未授权；4.接口返回空字符串/非法地址；5.解密开关未关闭导致明文地址被误解密；6.接口已下线或更换返回结构；7.未成功提取apiToken或授权接口未通过"
        ],
        -5 => [
            "msg" => "参数格式错误!",
            "troubleshoot" => "1.视频地址必须以http://或https://开头；2.地址存在特殊字符未正确URL编码；3.参数包含非法控制字符"
        ],
        -6 => [
            "msg" => "JSON解析失败!",
            "troubleshoot" => "1.接口返回非标准JSON格式；2.接口返回404/500错误页而非JSON；3.响应内容包含HTML/广告代码"
        ]
    ]
];

// 4.终极通用CURL调试日志函数（全接口通用，支持所有请求类型）
function universalCurlDebugLog($ch, $response, $requestInfo = [], $logFile = 'debug.log') {
    $time = date('Y-m-d H:i:s');
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

    $log = "[" . $time . "] =========================================\n";
    $log .= "[" . $time . "] 【请求信息】\n";
    $log .= "  方法: " . ($requestInfo['method'] ?? 'UNKNOWN') . "\n";
    $log .= "  URL: " . ($requestInfo['url'] ?? $effectiveUrl) . "\n";
    
    if (!empty($requestInfo['token'])) $log .= "  Token: " . $requestInfo['token'] . "\n";
    if (!empty($requestInfo['headers']) && is_array($requestInfo['headers'])) {
        $log .= "  请求头:\n";
        foreach ($requestInfo['headers'] as $header) $log .= "    - " . $header . "\n";
    }
    if (!empty($requestInfo['params']) && is_array($requestInfo['params'])) {
        $log .= "  请求参数:\n";
        foreach ($requestInfo['params'] as $key => $value) $log .= "    " . $key . " = " . $value . "\n";
    }
    
    $log .= "\n[" . $time . "] 【响应信息】\n";
    $log .= "  HTTP状态码: " . $httpCode . "\n";
    $log .= "  原始响应: " . $response . "\n";
    $log .= "-----------------------------------------\n\n";

    file_put_contents($logFile, $log, FILE_APPEND | LOCK_EX);
}

// 6.嵌套JSON字段提取函数模块：支持多级嵌套字段提取，适配复杂接口返回结构
function getNestedField($data, $field) {
    foreach (explode(".", $field) as $f) {
        if (!isset($data[$f]) || !is_array($data)) return "";
        $data = $data[$f];
    }
    return is_string($data) ? trim($data) : "";
}

// 8.标准化错误响应函数模块：统一错误输出格式，兼容TVBox，终止脚本执行
function buildErrorResponse($config, $code) {
    global $script_start_time;
    $error = $config["errorConfig"][$code] ?? ["msg" => "未知错误", "troubleshoot" => "无排查指引"];
    
    // 耗时改为：秒，2位小数
    $jb_hs = round(microtime(true) - $script_start_time, 2);
    
    echo json_encode([
        "code" => $code,
        "msg" => $error["msg"],
        "url" => "",
        "type" => "mp4",
        "troubleshoot" => $error["troubleshoot"],
        "jb_hs" => $jb_hs,
        "jk_hs" => 0,
        "jb_zhs" => $jb_hs, // 错误时总耗时=脚本耗时
        "server_time" => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    die();
}

// 9.主业务逻辑核心函数模块（适配ppl解析流程：无加密、纯转发、三步请求）
function main($config) {
    global $script_start_time;
    // Cookie临时存储文件（保持会话，必用）
    $cookie_file = __DIR__ . '/ppl_cookie.txt';

    // ===================== 主链路工作流程（ppl专用版） =====================
    // 1. 接收并兼容多版本TVBox传入的视频地址参数（原有逻辑，完全不变）
    $sourceUrl = "";
    $allParamKeys = $config["request"]["paramKeys"];
    foreach ($allParamKeys as $paramKey) {
        if (isset($_GET[$paramKey]) && !empty(trim($_GET[$paramKey]))) {
            $sourceUrl = trim($_GET[$paramKey]);
            break;
        }
    }

    // 2. 基础参数合法性校验（非空、协议头校验，原有逻辑不变）
    if (empty($sourceUrl)) buildErrorResponse($config, -1);
    if (!str_starts_with($sourceUrl, "http://") && !str_starts_with($sourceUrl, "https://")) buildErrorResponse($config, -5);
    
    // 3. 从UA池随机抽取UA，替换请求头中的UA字段（原有防封逻辑不变）
    $userAgents = $config["request"]["userAgents"];
    $randomUa = $userAgents[mt_rand(0, count($userAgents) - 1)];
    
    // 4. 执行请求前随机延迟，防频繁请求封IP（原有逻辑不变）
    $delay_min = $config["request"]["request_delay"][0];
    $delay_max = $config["request"]["request_delay"][1];
    $delay_ms = mt_rand($delay_min, $delay_max);
    usleep($delay_ms * 1000);

    // ===================== 核心流程1：请求前端页面，提取apiToken（明文获取，无加密） =====================
    $api_start_time = microtime(true);
    // ✅ 直接拼接：parseApi(https://ppl.sszzyy.com/?url=) + 用户传的视频地址
    $page_url = $config["request"]["parseApi"] . urlencode($sourceUrl);
    $ch_page = curl_init($page_url);
    curl_setopt_array($ch_page, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $config["request"]["followRedirect"],
        CURLOPT_USERAGENT => $randomUa,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HEADER => true, // 开启响应头，获取Cookie
        CURLOPT_COOKIEJAR => $cookie_file, // 保存服务器Cookie
        CURLOPT_COOKIEFILE => $cookie_file,
        CURLOPT_TIMEOUT => $config["request"]["timeout"],
        CURLOPT_CONNECTTIMEOUT => $config["request"]["connect_timeout"],
    ]);
    $page_response = curl_exec($ch_page);
    // 调试日志输出
    if ($config["request"]["debug"]) {
        universalCurlDebugLog($ch_page, $page_response, ["method" => "GET", "url" => $page_url]);
    }
    curl_close($ch_page);

    // 正则提取页面中的apiToken（固定匹配规则：apiToken: "xxx"）
    preg_match('/apiToken:\s*"([^"]+)"/i', $page_response, $token_match);
    $api_token = $token_match[1] ?? '';
    // 未提取到token，直接报错
    if (empty($api_token)) buildErrorResponse($config, -3);

    // ===================== 核心流程2：请求授权接口（必走！校验域名，防止拦截） =====================
    $auth_url = "https://ppl.sszzyy.com" . $config["request"]["authApi"] . "?host=" . $config["request"]["auth_host"];
    $ch_auth = curl_init($auth_url);
    curl_setopt_array($ch_auth, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => $randomUa,
        CURLOPT_REFERER => $page_url, // 带上来源页，校验通过
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_COOKIEJAR => $cookie_file,
        CURLOPT_COOKIEFILE => $cookie_file,
        CURLOPT_TIMEOUT => 5,
    ]);
    curl_exec($ch_auth); // 只需要请求通过，不校验返回值
    curl_close($ch_auth);

    // ===================== 核心流程3：请求解析接口，使用token换取真实播放地址 =====================
    $resolve_url = "https://ppl.sszzyy.com" . $config["request"]["resolveApi"] . "?token=" . urlencode($api_token);
    $ch_resolve = curl_init($resolve_url);
    curl_setopt_array($ch_resolve, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => $randomUa,
        CURLOPT_REFERER => $page_url,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_COOKIEFILE => $cookie_file, // 使用同一会话Cookie
        CURLOPT_TIMEOUT => $config["request"]["timeout"],
    ]);
    $resolve_response = curl_exec($ch_resolve);
    $http_code = curl_getinfo($ch_resolve, CURLINFO_HTTP_CODE);
    // 调试日志输出
    if ($config["request"]["debug"]) {
        universalCurlDebugLog($ch_resolve, $resolve_response, ["method" => "GET", "url" => $resolve_url, "token" => $api_token]);
    }
    curl_close($ch_resolve);

    // ===================== 后续解析&输出（原有格式完全不变，兼容TVBox） =====================
    // 计算接口请求耗时
    $jk_hs = round(microtime(true) - $api_start_time, 2);
    // 校验接口请求是否成功
    if ($http_code !== 200 || empty($resolve_response)) buildErrorResponse($config, -2);
    
    // 解析返回的JSON数据
    $res_arr = json_decode($resolve_response, true);
    if (!is_array($res_arr) || ($res_arr["code"] ?? -1) !== 200) buildErrorResponse($config, -3);
    
    // 提取播放地址
    $play_url = getNestedField($res_arr, $config["request"]["videoField"]);
    if (empty($play_url)) buildErrorResponse($config, -3);
    
    // 净化播放地址（原有逻辑不变）
    $play_url = trim($play_url);
    $play_url = rawurldecode($play_url);
    $play_url = preg_replace("/[\\x00-\\x1F\\x7F]/", "", $play_url);
    
    // 自动识别视频格式（原有逻辑不变）
    $type = "mp4";
    if (strpos($play_url, ".m3u8") !== false) $type = "m3u8";
    elseif (strpos($play_url, ".flv") !== false) $type = "flv";
    elseif (strpos($play_url, ".mpd") !== false) $type = "mpd";
    elseif (strpos($play_url, ".ts") !== false) $type = "ts";
    
    // 计算所有耗时（原有逻辑不变）
    $total_cost_time = round(microtime(true) - $script_start_time, 2);
    $jb_hs = round($total_cost_time - $jk_hs, 2);
    $jiange_second = round($delay_ms / 1000, 2);
    
    // 输出最终标准解析结果（原有格式完全不变）
    echo json_encode([
        "code" => 200,
        "msg" => "解析成功",
        "url" => $play_url,
        "type" => $type,
        "troubleshoot" => "",
        "jb_hs" => $jb_hs,
        "jk_hs" => $jk_hs,
        "jb_zhs" => $total_cost_time,
        "jiange_hs" => $jiange_second,
        "server_time" => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// 10.脚本入口执行模块（完全不变）
main($config);
?>
