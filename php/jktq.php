<?php
// 配置前置（完全对齐参考排版）
error_reporting(0);
ini_set('display_errors', 0);
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 600);
ini_set('max_input_vars', 5000);
ini_set('output_buffering', 'off');
ob_end_clean();

// 全局变量（按参考分组对齐）
$extractedUrl = '';
$extractedUrls = [];
$errorMsg = '';
$siteResults = [];
$siteSearchKey = '';
$targetField = 'jar';

$base64Error = '';
$base64EncodeResult = '';
$base64DecodeResult = '';
$restoreFileName = '';
$isText = false;

$multiProcessedSites = [];
$videoBlacklist = ['mp4', 'mkv', 'avi', 'flv', 'wmv', 'mov', 'rmvb', 'mpg', '3gp', 'webm'];

// 异步请求入口（完全对齐参考排版）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['status' => 'error', 'msg' => '', 'data' => []];
    try {
        switch ($_POST['action']) {
            case 'do_extract_insert':
                $jsonText = trim($_POST['extract_text']);
                $siteSearchKey = trim($_POST['site_search_key']);
                $siteSearchKey = str_replace('、', ',', $siteSearchKey);
                $keywords = array_filter(array_map('trim', explode(',', $siteSearchKey)));
                $jsonData = json_decode($jsonText, true);
                if (json_last_error() !== JSON_ERROR_NONE) throw new Exception("JSON格式错误，请检查输入");
                if (!isset($jsonData['sites']) || !is_array($jsonData['sites'])) throw new Exception("未找到`sites`数组");
                $matchedSites = [];
                $matchedKeywords = [];
                foreach ($jsonData['sites'] as $site) {
                    if (!isset($site['key']) || !isset($site['name'])) continue;
                    foreach ($keywords as $keyword) {
                        if (strpos($site['key'], $keyword) !== false || strpos($site['name'], $keyword) !== false) {
                            $matchedSites[] = $site;
                            $matchedKeywords[] = $keyword;
                            break;
                        }
                    }
                }
                $matchedKeywords = array_unique($matchedKeywords);
                $unmatchedKeywords = array_diff($keywords, $matchedKeywords);
                $keywordTotal = count($keywords);
                $unmatchedCount = count($unmatchedKeywords);
                if (empty($matchedSites)) throw new Exception("未找到包含关键词「" . implode('、', $keywords) . "」的站点");
                $response['status'] = 'success';
                $response['msg'] = "提取到 " . count($matchedSites) . " 个纯净站点";
                $response['keywordTotal'] = $keywordTotal;
                $response['unmatchedCount'] = $unmatchedCount;
                $response['unmatchedKeywords'] = array_values($unmatchedKeywords);
                $response['data'] = $matchedSites;
                break;

            case 'do_multi_add':
                $siteStr = trim($_POST['multi_site']);
                $targetField = trim($_POST['multi_target_field'] ?? 'jar');
                $fieldValue = trim($_POST['multi_field_value']);
                if (preg_match('/["\'{}]/', $fieldValue)) throw new Exception("字段值不能包含双引号、单引号或大括号");
                $fixedSiteStr = substr($siteStr, 0, 1) === '[' ? $siteStr : "[$siteStr]";
                $siteArray = json_decode($fixedSiteStr, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($siteArray)) throw new Exception("JSON格式错误");
                $processedSites = [];
                foreach ($siteArray as $site) {
                    $newSite = [];
                    foreach ($site as $k => $v) {
                        $newSite[$k] = $v;
                        if ($k === 'api') $newSite[$targetField] = $fieldValue;
                    }
                    $processedSites[] = $newSite;
                }
                $response['status'] = 'success';
                $response['msg'] = "批量处理完成！共 " . count($processedSites) . " 个站点";
                $response['data'] = $processedSites;
                break;

            case 'batch_extract_text':
                $text = trim($_POST['batch_extract_text']);
                preg_match_all('/https?:\/\/[^\s;,"\']+/i', $text, $matches);
                if (empty($matches[0])) throw new Exception("未提取到任何链接");
                $extractedUrls = [];
                foreach ($matches[0] as $url) {
                    $urlExt = strtolower(pathinfo($url, PATHINFO_EXTENSION));
                    if (!in_array($urlExt, $videoBlacklist) && !empty($urlExt)) $extractedUrls[] = $url;
                }
                if (empty($extractedUrls)) throw new Exception("未提取到有效非视频资源链接");
                $extractedUrl = $extractedUrls[0] ?? '';
                $response['status'] = 'success';
                $response['msg'] = "提取到 " . count($extractedUrls) . " 个链接";
                $response['data'] = $extractedUrls;
                break;

            case 'do_base64_decode':
                $encodedStr = trim($_POST['decode_str'] ?? '');
                if (empty($encodedStr)) throw new Exception("请输入编码后的字符串");
                $sepPos = strpos($encodedStr, '**');
                $encodedContent = $sepPos !== false ? substr($encodedStr, $sepPos + 2) : $encodedStr;
                $base64DecodeResult = base64_decode($encodedContent, true);
                if ($base64DecodeResult === false) throw new Exception("Base64解码失败");
                $isText = mb_check_encoding($base64DecodeResult, 'UTF-8') || mb_check_encoding($base64DecodeResult, 'GBK');
                $response['status'] = 'success';
                $response['msg'] = "解码完成";
                $response['data'] = [
                    'content' => $base64DecodeResult,
                    'isText' => $isText,
                    'fileName' => 'restored_' . time() . '.bin'
                ];
                break;

            case 'backend_batch_zip':
                $linkList = json_decode($_POST['link_list'], true);
                if (!is_array($linkList) || empty($linkList)) throw new Exception("链接列表为空");
                $fixedTempDir = __DIR__ . '/wjzip';
                $targetFileDir = $fixedTempDir . '/target_files';
                $progressId = uniqid('batch_');
                $progressFile = $fixedTempDir . '/progress_' . $progressId . '.json';
                if (!is_dir($fixedTempDir)) mkdir($fixedTempDir, 0755, true);
                if (!is_dir($targetFileDir)) mkdir($targetFileDir, 0755, true);
                $oldFiles = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($fixedTempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($oldFiles as $file) {
                    $file->isDir() ? rmdir($file) : unlink($file);
                }
                mkdir($targetFileDir, 0755, true);
                $totalFiles = count($linkList);
                $initProgress = [
                    'total' => $totalFiles,
                    'current' => 0,
                    'status' => 'downloading',
                    'msg' => '开始下载文件...',
                    'zip_name' => '',
                    'fixed_temp_dir' => $fixedTempDir
                ];
                file_put_contents($progressFile, json_encode($initProgress));
                ignore_user_abort(true);
                ob_start();
                $response['status'] = 'success';
                $response['msg'] = "打包任务已启动";
                $response['data'] = [
                    'progress_id' => $progressId,
                    'fixed_temp_dir' => $fixedTempDir
                ];
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                header('Content-Length: ' . ob_get_length());
                ob_end_flush();
                flush();
                if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
                set_time_limit(0);
                $failedCount = 0;
                foreach ($linkList as $index => $url) {
                    $fileName = pathinfo($url, PATHINFO_BASENAME) ?: "file_${index+1}";
                    if (!pathinfo($fileName, PATHINFO_EXTENSION)) {
                        $urlExt = strtolower(pathinfo($url, PATHINFO_EXTENSION));
                        $fileName .= $urlExt ? ".${urlExt}" : ".bin";
                    }
                    $filePath = $targetFileDir . '/' . $fileName;
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: */*', 'Accept-Language: zh-CN', 'Connection: keep-alive', 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36']);
                    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    $fileContent = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    if ($httpCode !== 200 || empty($fileContent)) {
                        $failedCount++;
                    } else {
                        file_put_contents($filePath, $fileContent);
                    }
                    $progress = json_decode(file_get_contents($progressFile), true);
                    $progress['current'] = $index + 1;
                    $progress['msg'] = "正在下载第{$progress['current']}个文件：{$fileName}";
                    file_put_contents($progressFile, json_encode($progress));
                }
                $progress = json_decode(file_get_contents($progressFile), true);
                $progress['status'] = 'packing';
                $progress['msg'] = '文件下载完成，开始打包ZIP...';
                file_put_contents($progressFile, json_encode($progress));
                $zipName = 'tvbox_batch_' . uniqid() . '.zip';
                $zipPath = $fixedTempDir . '/' . $zipName;
                $zip = new ZipArchive();
                if (!$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
                    $progress['status'] = 'error';
                    $progress['msg'] = "ZIP创建失败（请检查ZipArchive扩展）";
                    file_put_contents($progressFile, json_encode($progress));
                    exit;
                }
                $files = scandir($targetFileDir);
                foreach ($files as $file) {
                    $filePath = $targetFileDir . '/' . $file;
                    if (is_file($filePath) && $file !== '.' && $file !== '..') {
                        $zip->addFile($filePath, $file);
                    }
                }
                $zip->close();
                $progress['status'] = 'completed';
                $progress['msg'] = "打包完成！成功" . ($totalFiles - $failedCount) . "个，失败{$failedCount}个";
                $progress['zip_name'] = $zipName;
                file_put_contents($progressFile, json_encode($progress));
                exit;
                break;

            case 'get_batch_progress':
                $progressId = trim($_POST['progress_id']);
                $fixedTempDir = __DIR__ . '/wjzip';
                $progressFile = $fixedTempDir . '/progress_' . $progressId . '.json';
                if (!file_exists($progressFile)) throw new Exception("进度记录不存在");
                $progressData = json_decode(file_get_contents($progressFile), true);
                if (!$progressData) throw new Exception("进度数据解析失败");
                $response['status'] = 'success';
                $response['data'] = $progressData;
                break;

            case 'download_zip':
                $zipName = $_POST['zip_name'];
                $fixedTempDir = $_POST['fixed_temp_dir'];
                $zipPath = $fixedTempDir . '/' . $zipName;
                if (!file_exists($zipPath)) throw new Exception("ZIP文件不存在");
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zipName . '"');
                header('Content-Length: ' . filesize($zipPath));
                header('Cache-Control: no-cache, no-store');
                readfile($zipPath);
                exit;

            case 'delete_temp_zip':
                $fixedTempDir = $_POST['fixed_temp_dir'];
                if (is_dir($fixedTempDir)) {
                    $oldFiles = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($fixedTempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::CHILD_FIRST
                    );
                    foreach ($oldFiles as $file) {
                        $file->isDir() ? rmdir($file) : unlink($file);
                    }
                }
                $response['status'] = 'success';
                $response['msg'] = "临时内容已清空";
                break;

            default:
                throw new Exception("未知操作");
        }
    } catch (Exception $e) {
        $response['msg'] = $e->getMessage();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// 单文件下载（完全对齐参考排版）
if (!empty($_GET['url'])) {
    $targetUrl = trim($_GET['url']);
    $urlExt = strtolower(pathinfo($targetUrl, PATHINFO_EXTENSION));
    if (in_array($urlExt, $videoBlacklist)) {
        die("❌ 不支持视频格式下载");
    }
    $ch = curl_init($targetUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: */*', 'Accept-Language: zh-CN', 'Connection: keep-alive', 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36']);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $fileContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $fileSize = strlen($fileContent);
    curl_close($ch);
    if ($httpCode == 200 && $fileSize > 10) {
        $fileExt = !empty($urlExt) ? $urlExt : 'bin';
        $fileName = pathinfo($targetUrl, PATHINFO_BASENAME) ?: ('resource_' . time() . '.' . $fileExt);
        $contentTypeMap = [
            'json' => 'application/json', 'js' => 'application/javascript', 'txt' => 'text/plain', 'py' => 'text/x-python', 'css' => 'text/css', 'html' => 'text/html', 'jpg' => 'image/jpeg', 'png' => 'image/png', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'icon' => 'image/x-icon', 'jar' => 'application/java-archive', 'apk' => 'application/vnd.android.package-archive', 'zip' => 'application/zip', 'rar' => 'application/x-rar-compressed', 'm3u' => 'application/x-mpegURL', 'exe' => 'application/x-msdownload', 'iso' => 'application/x-iso9660-image', 'bin' => 'application/octet-stream',
            'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'pdf' => 'application/pdf', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xls' => 'application/vnd.ms-excel', 'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'mp3' => 'audio/mpeg', 'flac' => 'audio/flac', 'wav' => 'audio/wav', 'aac' => 'audio/aac', 'ogg' => 'audio/ogg',
            'ttf' => 'font/ttf', 'otf' => 'font/otf', 'woff' => 'font/woff', 'woff2' => 'font/woff2',
            'csv' => 'text/csv', 'xml' => 'application/xml', 'yml' => 'text/yaml', 'yaml' => 'text/yaml',
            '7z' => 'application/x-7z-compressed', 'tar' => 'application/x-tar', 'gz' => 'application/gzip', 'bz2' => 'application/x-bzip2'
        ];
        $contentType = $contentTypeMap[$fileExt] ?? 'application/octet-stream';
        header("Content-Type: {$contentType}");
        header("Content-Disposition: attachment; filename=\"{$fileName}\"");
        header("Content-Length: " . strlen($fileContent));
        header('Cache-Control: no-cache');
        flush();
        echo $fileContent;
        exit;
    } else {
        die("❌ 下载失败：响应码{$httpCode}，文件大小{$fileSize}B");
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛠TVBOX接口综合维护工具</title>
    <style>
        /* 全局基础样式 */
        body {margin: 30px; font-family: Arial; line-height: 1.8; background: #f5f7fa;}
        .container {max-width: 1000px; margin: 0 auto;}
        h2 {color: #2196F3; text-align: center; font-size: 32px; margin: 20px 0; display: flex; align-items: center; justify-content: center; gap: 12px;}
        h3 {color: #1976D2; margin-top: 0;}
        h4 {color: #1976D2; margin: 10px 0; font-size: 16px;}

        /* 主标签样式 */
        .main-tab-container {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin: 25px 0;
        }
        .main-tab-btn {
            padding: 12px 24px;
            border: 2px solid #2196F3;
            background: #fff;
            color: #2196F3;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .main-tab-btn:hover {
            background: #f0f8ff;
            transform: translateY(-1px);
        }
        .main-tab-btn.active {
            background: #2196F3;
            color: #fff;
            box-shadow: 0 2px 8px rgba(33, 150, 243, 0.3);
        }

        /* 子标签样式 */
        .sub-tab-container {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin: 20px 0;
        }
        .sub-tab-btn {
            padding: 10px 20px;
            border: 1px solid #ddd;
            background: #f9f9f9;
            color: #333;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }
        .sub-tab-btn:hover {
            border-color: #2196F3;
            background: #f0f8ff;
        }
        .sub-tab-btn.active {
            background: #2196F3;
            color: #fff;
            border-color: #2196F3;
            box-shadow: 0 2px 6px rgba(33, 150, 243, 0.2);
        }

        /* 内容面板 */
        .tab-panel {
            display: none;
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .tab-panel.active {
            display: block;
        }
        .sub-panel {
            display: none;
        }
        .sub-panel.active {
            display: block;
        }

        /* 输入输出固定高度 + 内部滚动 */
        input, textarea, select {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }
        textarea {
            height: 250px;
            resize: vertical;
            white-space: pre-wrap;
            font-family: 'Consolas', 'Monaco', monospace;
        }
        .url-list, .site-list {
            margin: 15px 0;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            height: 400px;
            overflow-y: auto;
            background: #fff;
        }
        .code-block {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 13px;
            white-space: pre-wrap;
            word-break: break-all;
            height: 350px;
            overflow-y: auto;
        }

        /* 按钮样式 */
        button {
            padding: 12px 24px;
            background: #2196F3;
            border: none;
            border-radius: 4px;
            color: #fff;
            font-weight: bold;
            cursor: pointer;
            margin-right: 10px;
            margin-bottom: 10px;
            transition: background 0.3s ease;
        }
        button:hover {
            background: #1976D2;
        }
        .clear-btn {background: #666 !important;}
        .clear-btn:hover {background: #444 !important;}
        .rollback-btn {background: #4CAF50 !important;}
        .rollback-btn:hover {background: #388E3C !important;}

        /* 提示信息 */
        .error {color: red; margin: 10px 0; font-weight: bold;}
        .success {color: #2E7D32; margin: 10px 0; font-weight: bold;}
        .tip {color: #666; font-size: 14px; margin: 5px 0;}
        .file-upload-tip {color: #2E7D32; font-size: 14px; margin: 5px 0;}

        /* 错误提示tooltip */
        .form-group {position: relative; margin-bottom: 15px;}
        .error-tooltip {
            position: absolute;
            top: 50%;
            left: calc(100% + 10px);
            transform: translateY(-50%);
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
            z-index: 99;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .error-tooltip::before {content: "⚠️"; font-size: 16px;}
        .error-tooltip::after {
            content: "";
            position: absolute;
            top: 50%;
            left: -8px;
            transform: translateY(-50%);
            border-width: 4px;
            border-style: solid;
            border-color: transparent #fff3cd transparent transparent;
        }
        @media (max-width: 768px) {
            .error-tooltip {
                top: calc(100% + 5px);
                left: 0;
                transform: none;
                width: 100%;
                white-space: normal;
            }
            .error-tooltip::after {
                top: -8px;
                left: 15px;
                border-color: transparent transparent #fff3cd transparent;
            }
        }

        /* 打包进度 */
        .progress-display {
            padding: 12px 24px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 10px 0;
            text-align: center;
            color: #333;
            font-size: 14px;
        }

        /* 关键词统计 */
        #keywordCount {color: #2196F3; font-weight: bold; margin-top: -8px; margin-bottom: 10px;}
    </style>
</head>
<body>
    <div class="container">
        <h2>🛠TVBOX接口提取</h2>
        <div class="btn-group" style="text-align: center;">
            <button class="clear-btn" onclick="clearAll()">🗑️ 一键清空所有</button>
        </div>

        <!-- 主标签容器 -->
        <div class="main-tab-container">
            <button class="main-tab-btn active" data-tab="linkage-group">📋 接口提取</button>
            <button class="main-tab-btn" data-tab="independent-group">📥 资源下载</button>
        </div>

        <!-- 主标签内容 -->
        <div class="main-tab-content">
            <!-- 🔹 接口提取（原联动工具组） -->
            <div class="tab-panel active" id="linkage-group">
                <div class="sub-tab-container">
                    <button class="sub-tab-btn active" data-subtab="json-extract">🔍 JSON站点提取</button>
                    <button class="sub-tab-btn" data-subtab="batch-add">📦 批量添加字段</button>
                </div>
                <div class="sub-tab-content">
                    <!-- JSON站点提取 -->
                    <div class="sub-panel active" id="json-extract">
                        <h3>1.📝JSON站点精准提取</h3>
                        <p class="tip">粘贴主JSON文件，按关键词提取纯净站点数据</p>
                        <div>
                            <input type="file" id="siteJsonUpload" accept=".json,.txt" />
                            <button type="button" onclick="uploadSiteJson()">📤 上传JSON文件并填充</button>
                            <div id="siteJsonUploadTip" class="file-upload-tip"></div>
                        </div>
                        <div class="form-group">
                            <textarea id="siteText" placeholder="粘贴完整JSON（含`sites`数组）" required></textarea>
                        </div>
                        <div class="form-group">
                            <textarea id="siteKey" placeholder="关键词（顿号/逗号分隔）" rows="2"></textarea>
                            <div id="keywordCount" class="tip">关键词数量：0</div>
                        </div>
                        <p class="tip">💡 大小写敏感，匹配key/name字段</p>
                        <button type="button" onclick="doExtractInsert()">🔍 提取站点</button>
                        <button type="button" class="clear-btn" onclick="clearSite()">🗑️ 清空</button>
                        <div id="siteMsg"></div>
                        <div id="siteResult"></div>
                    </div>

                    <!-- 批量添加字段 -->
                    <div class="sub-panel" id="batch-add">
                        <h3>2.📦批量添加目标字段</h3>
                        <p class="tip">粘贴站点对象，自动在api后插入字段</p>
                        <div>
                            <input type="file" id="multiSiteUpload" accept=".json,.txt" />
                            <button type="button" onclick="uploadMultiSite()">📤 上传站点文件并填充</button>
                            <div id="multiSiteUploadTip" class="file-upload-tip"></div>
                        </div>
                        <div class="form-group">
                            <textarea id="multiSiteInput" placeholder='示例：{"key":"厂长","api":"xxx"},{"key":"韩圈","api":"xxx"}' rows="10" required></textarea>
                        </div>
                        <select id="multiTargetField">
                            <option value="jar">jar（默认）</option>
                            <option value="json">json</option>
                            <option value="ext">ext</option>
                            <option value="api">api</option>
                        </select>
                        <div class="form-group">
                            <input type="text" id="multiFieldValue" placeholder="字段值（例：./jar/ok.jar）" required />
                        </div>
                        <p class="tip">⚠️ 字段值不能包含引号、大括号</p>
                        <button type="button" onclick="doMultiAdd()">✅ 批量添加字段</button>
                        <button type="button" class="clear-btn" onclick="clearMultiSiteJar()">🗑️ 清空</button>
                        <div id="multiSiteResult"></div>
                    </div>
                </div>
            </div>

            <!-- 🔹 资源下载（原独立工具组） -->
            <div class="tab-panel" id="independent-group">
                <div class="sub-tab-container">
                    <button class="sub-tab-btn active" data-subtab="link-extract">🔗 批量链接提取</button>
                    <button class="sub-tab-btn" data-subtab="base64-tool">🔒 Base64编解码</button>
                </div>
                <div class="sub-tab-content">
                    <!-- 批量链接提取 -->
                    <div class="sub-panel active" id="link-extract">
                        <h3>3.🔗批量提取文本链接</h3>
                        <p class="tip">提取文本中的非视频资源链接</p>
                        <div>
                            <input type="file" id="batchTextUpload" accept="*" />
                            <button type="button" onclick="uploadBatchText()">📤 上传文本文件并填充</button>
                            <div id="batchTextUploadTip" class="file-upload-tip"></div>
                        </div>
                        <div class="form-group">
                            <textarea id="batchText" placeholder="粘贴含链接的文本" required></textarea>
                        </div>
                        <button type="button" onclick="batchExtractText()">🔍 批量提取</button>
                        <button type="button" class="clear-btn" onclick="clearBatch()">🗑️ 清空</button>
                        <div id="batchMsg"></div>
                        <div id="batchResult"></div>
                    </div>

                    <!-- Base64编解码（仅保留主按钮，删除子按钮） -->
                    <div class="sub-panel" id="base64-tool">
                        <h3>4.🔒Base64编解码工具</h3>
                        <div class="base64-content">
                            <!-- 合并编码和解码功能到一个面板 -->
                            <div class="base64-panel active" id="base64-combined">
                                <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
                                    <!-- Base64编码区域 -->
                                    <div>
                                        <h4>🔒 编码功能</h4>
                                        <p class="tip">文本/文件编码，前缀可选</p>
                                        <div>
                                            <input type="file" id="encode_file" />
                                            <button type="button" onclick="uploadEncodeFile()">📤 上传文件并填充</button>
                                            <div id="encodeFileTip" class="file-upload-tip"></div>
                                        </div>
                                        <div class="form-group">
                                            <textarea id="encode_text" placeholder="粘贴需要编码的内容" rows="5"></textarea>
                                        </div>
                                        <div class="form-group">
                                            <input type="text" id="encode_prefix" placeholder="输入前缀（可选）" autocomplete="off" />
                                        </div>
                                        <button type="button" onclick="doBase64Encode()">🔒 开始编码</button>
                                        <button type="button" class="clear-btn" onclick="clearBase64Encode()">🗑️ 清空编码区</button>
                                        <div id="base64Msg"></div>
                                        <div id="base64EncodeResultArea" style="display:none;"></div>
                                    </div>

                                    <!-- Base64解码区域 -->
                                    <div>
                                        <h4>🔓 解码功能</h4>
                                        <p class="tip">自动剔除**前缀，支持文本/二进制文件</p>
                                        <div>
                                            <input type="file" id="decodeFileUpload" accept="*" />
                                            <button type="button" onclick="uploadDecodeFile()">📤 上传编码文件并填充</button>
                                            <div id="decodeFileUploadTip" class="file-upload-tip"></div>
                                        </div>
                                        <div class="form-group">
                                            <textarea id="decode_str" placeholder="粘贴编码后的字符串" rows="5" required></textarea>
                                        </div>
                                        <button type="button" onclick="doBase64Decode()">🔄 一键还原</button>
                                        <button type="button" class="clear-btn" onclick="clearBase64Decode()">🗑️ 清空解码区</button>
                                        <div id="base64DecodeMsg" class="error"></div>
                                        <div id="base64DecodeResultArea"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const uploadFileRecords = { siteJson: '', multiSite: '', batchText: '', encodeFile: '', decodeFile: '' };

        // 关键词统计
        document.getElementById('siteKey').addEventListener('input', function() {
            const text = this.value.trim();
            if (!text) {
                document.getElementById('keywordCount').textContent = "关键词数量：0";
                return;
            }
            const keywords = text.split(/[、,]/).map(k => k.trim()).filter(k => k);
            document.getElementById('keywordCount').textContent = `关键词数量：${keywords.length}`;
        });

        // 标签切换逻辑
        document.querySelectorAll('.main-tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.main-tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
                document.getElementById(this.dataset.tab).classList.add('active');
            });
        });

        document.querySelectorAll('.sub-tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const parentPanel = this.closest('.tab-panel');
                parentPanel.querySelectorAll('.sub-tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                parentPanel.querySelectorAll('.sub-panel').forEach(panel => panel.classList.remove('active'));
                parentPanel.querySelector(`#${this.dataset.subtab}`).classList.add('active');
            });
        });

        // 错误提示逻辑
        function showFieldError(element, msg) {
            const existing = element.parentElement.querySelector('.error-tooltip');
            if (existing) existing.remove();
            const tooltip = document.createElement('div');
            tooltip.className = 'error-tooltip';
            tooltip.textContent = msg;
            element.parentElement.appendChild(tooltip);
            setTimeout(() => tooltip.remove(), 3000);
        }

        function showCenterToast(msg, isSuccess = true) {
            const existingToast = document.querySelector('.center-toast');
            if (existingToast) existingToast.remove();
            const toast = document.createElement('div');
            toast.className = `center-toast ${isSuccess ? 'success' : 'error'}`;
            toast.style = `position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0,0,0,0.7); padding: 8px; border-radius: 8px; z-index: 9999; opacity: 0; transition: opacity 0.3s ease;`;
            toast.innerHTML = `
                <div style="background: #fff; padding: 12px 20px; border-radius: 6px; display: flex; align-items: center; gap: 8px; font-size: 15px;">
                    <span style="font-size: 18px;">${isSuccess ? '✅' : '❌'}</span>
                    <span>${msg}</span>
                </div>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.style.opacity = 1, 10);
            setTimeout(() => {
                toast.style.opacity = 0;
                setTimeout(() => toast.remove(), 300);
            }, 1200);
        }

        function showCustomConfirm(title, message) {
            return new Promise((resolve) => {
                const confirmContainer = document.createElement('div');
                confirmContainer.style = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 99998;
                `;
                const confirmBox = document.createElement('div');
                confirmBox.style = `
                    background: #fff;
                    padding: 20px;
                    border-radius: 8px;
                    width: 80%;
                    max-width: 400px;
                `;
                const confirmTitle = document.createElement('h4');
                confirmTitle.textContent = title;
                confirmTitle.style.marginTop = '0';
                const confirmMsg = document.createElement('p');
                confirmMsg.textContent = message;
                const btnContainer = document.createElement('div');
                btnContainer.style.display = 'flex';
                btnContainer.style.justifyContent = 'flex-end';
                btnContainer.style.gap = '10px';
                const cancelBtn = document.createElement('button');
                cancelBtn.textContent = '取消';
                cancelBtn.style.background = '#666';
                cancelBtn.onclick = () => {
                    document.body.removeChild(confirmContainer);
                    resolve(false);
                };
                const confirmBtn = document.createElement('button');
                confirmBtn.textContent = '确定';
                confirmBtn.onclick = () => {
                    document.body.removeChild(confirmContainer);
                    resolve(true);
                };
                btnContainer.appendChild(cancelBtn);
                btnContainer.appendChild(confirmBtn);
                confirmBox.appendChild(confirmTitle);
                confirmBox.appendChild(confirmMsg);
                confirmBox.appendChild(btnContainer);
                confirmContainer.appendChild(confirmBox);
                document.body.appendChild(confirmContainer);
            });
        }

        function htmlEscape(str) {
            return str.replace(/&/g, '&amp;')
                      .replace(/</g, '&lt;')
                      .replace(/>/g, '&gt;')
                      .replace(/"/g, '&quot;')
                      .replace(/'/g, '&#039;');
        }

        function parseUploadedFile(file, callback) {
            const reader = new FileReader();
            reader.onload = e => callback(e.target.result);
            reader.onerror = () => showFieldError(file, '文件解析失败');
            reader.readAsText(file, 'UTF-8');
        }

        // 上传函数
        function uploadSiteJson() {
            const file = document.getElementById('siteJsonUpload').files[0];
            if (!file) return showFieldError(document.getElementById('siteJsonUpload'), '请选择文件');
            uploadFileRecords.siteJson = file.name;
            document.getElementById('siteJsonUploadTip').textContent = `已上传：${file.name}`;
            parseUploadedFile(file, content => document.getElementById('siteText').value = content);
        }

        function uploadMultiSite() {
            const file = document.getElementById('multiSiteUpload').files[0];
            if (!file) return showFieldError(document.getElementById('multiSiteUpload'), '请选择文件');
            uploadFileRecords.multiSite = file.name;
            document.getElementById('multiSiteUploadTip').textContent = `已上传：${file.name}`;
            parseUploadedFile(file, content => {
                const fixed = content.trim().startsWith('[') ? content.trim().slice(1, -1).trim() : content.trim();
                document.getElementById('multiSiteInput').value = fixed;
            });
        }

        function uploadBatchText() {
            const file = document.getElementById('batchTextUpload').files[0];
            if (!file) return showFieldError(document.getElementById('batchTextUpload'), '请选择文件');
            uploadFileRecords.batchText = file.name;
            document.getElementById('batchTextUploadTip').textContent = `已上传：${file.name}`;
            parseUploadedFile(file, content => document.getElementById('batchText').value = content);
        }

        function uploadEncodeFile() {
            const file = document.getElementById('encode_file').files[0];
            if (!file) return showFieldError(document.getElementById('encode_file'), '请选择文件');
            uploadFileRecords.encodeFile = file.name;
            document.getElementById('encodeFileTip').textContent = `已上传：${file.name}`;
            parseUploadedFile(file, content => document.getElementById('encode_text').value = content.trim());
        }

        function uploadDecodeFile() {
            const file = document.getElementById('decodeFileUpload').files[0];
            if (!file) return showFieldError(document.getElementById('decodeFileUpload'), '请选择文件');
            uploadFileRecords.decodeFile = file.name;
            document.getElementById('decodeFileUploadTip').textContent = `已上传：${file.name}`;
            parseUploadedFile(file, content => document.getElementById('decode_str').value = content.trim());
        }

        // 异步请求
        async function sendAsyncRequest(action, data) {
            try {
                const formData = new FormData();
                formData.append('action', action);
                Object.keys(data).forEach(key => formData.append(key, data[key]));
                const res = await fetch(window.location.href, {method: 'POST', body: formData});
                return await res.json();
            } catch (err) {
                return { status: 'error', msg: '网络请求失败' };
            }
        }

        // 1. 提取站点
        async function doExtractInsert() {
            const extractText = document.getElementById('siteText').value.trim();
            const siteSearchKey = document.getElementById('siteKey').value.trim();
            if (!extractText) return showFieldError(document.getElementById('siteText'), '请填写完整JSON');
            if (!siteSearchKey) return showFieldError(document.getElementById('siteKey'), '请填写关键词');
            const result = await sendAsyncRequest('do_extract_insert', { extract_text: extractText, site_search_key: siteSearchKey });
            if (result.status === 'success') {
                let msgHtml = `✅ 提取到 ${result.data.length} 个纯净站点<br>`;
                msgHtml += `关键词总数：${result.keywordTotal} | 匹配数：${result.keywordTotal - result.unmatchedCount} | 未匹配数：${result.unmatchedCount}`;
                if (result.unmatchedCount > 0) {
                    msgHtml += `<br/><span style="color:orange;">未匹配关键词：${result.unmatchedKeywords.join('、')}</span>`;
                }
                document.getElementById('siteMsg').innerHTML = `<div class='success'>${msgHtml}</div>`;
            } else {
                document.getElementById('siteMsg').innerHTML = `<div class='error'>❌ ${result.msg}</div>`;
            }
            if (result.status === 'success') {
                const jsonStr = JSON.stringify(result.data, null, 2);
                document.getElementById('siteResult').innerHTML = `
                    <div class="site-list"><div class="code-block" id="siteJsonResult">${htmlEscape(jsonStr)}</div></div>
                    <button onclick="copyJson()">📋 复制JSON</button>
                    <button onclick="downloadSiteJson()">📥 下载JSON</button>
                    <button onclick="rollbackToMultiSite()" class="rollback-btn">🔄 回滚到批量添加</button>
                    <button onclick="clearSiteResult()" class="clear-btn">🗑️ 清空输出</button>
                `;
            } else {
                document.getElementById('siteResult').innerHTML = '';
            }
        }

        function rollbackToMultiSite() {
            const content = document.getElementById('siteJsonResult')?.textContent;
            if (!content) return showFieldError(document.getElementById('siteJsonResult'), '无结果可回滚');
            const siteArray = JSON.parse(content);
            const multiFormat = JSON.stringify(siteArray, null, 2).replace(/^\[|\]$/g, '').trim();
            document.getElementById('multiSiteInput').value = multiFormat;
            document.querySelector('.sub-tab-btn[data-subtab="batch-add"]').click();
            showFieldError(document.getElementById('multiSiteInput'), '已回滚到批量添加输入框');
        }

        // 2. 批量添加字段
        async function doMultiAdd() {
            const multiSite = document.getElementById('multiSiteInput').value.trim();
            const multiTargetField = document.getElementById('multiTargetField').value;
            const multiFieldValue = document.getElementById('multiFieldValue').value.trim();
            if (!multiSite) return showFieldError(document.getElementById('multiSiteInput'), '请填写站点内容');
            if (!multiFieldValue) return showFieldError(document.getElementById('multiFieldValue'), '请填写字段值');
            const result = await sendAsyncRequest('do_multi_add', { multi_site: multiSite, multi_target_field: multiTargetField, multi_field_value: multiFieldValue });
            const msgEl = document.createElement('div');
            msgEl.innerHTML = result.status === 'error' 
                ? `<div class='error'>❌ ${result.msg}</div>` 
                : `<div class='success'>✅ ${result.msg}</div>`;
            document.getElementById('multiSiteResult').innerHTML = '';
            document.getElementById('multiSiteResult').appendChild(msgEl);
            if (result.status === 'success') {
                const jsonStr = JSON.stringify(result.data, null, 2);
                document.getElementById('multiSiteResult').innerHTML += `
                    <div class="site-list"><div class="code-block" id="formattedMultiSite">${htmlEscape(jsonStr)}</div></div>
                    <button onclick="copyMultiSiteResult()">📋 复制结果</button>
                    <button onclick="downloadMultiSiteJson()">📥 下载JSON</button>
                    <button onclick="importToMultiInput()" class="rollback-btn">🔄 回滚到输入框</button>
                    <button onclick="clearMultiSiteResult()" class="clear-btn">🗑️ 清空输出</button>
                `;
            }
        }

        function importToMultiInput() {
            const content = document.getElementById('formattedMultiSite').textContent;
            if (!content) return showFieldError(document.getElementById('formattedMultiSite'), '无结果可回滚');
            const fixed = content.replace(/^\[|\]$/g, '').trim();
            document.getElementById('multiSiteInput').value = fixed;
            showFieldError(document.getElementById('multiSiteInput'), '已回滚到输入框');
        }

        // 3. 批量链接提取
        async function batchExtractText() {
            const batchText = document.getElementById('batchText').value.trim();
            if (!batchText) return showFieldError(document.getElementById('batchText'), '请填写文本内容');
            const result = await sendAsyncRequest('batch_extract_text', { batch_extract_text: batchText });
            document.getElementById('batchMsg').innerHTML = result.status === 'error' 
                ? `<div class='error'>❌ ${result.msg}</div>` 
                : `<div class='success'>✅ ${result.msg}</div>`;
            if (result.status === 'success') {
                let linkHtml = '';
                result.data.forEach((url, idx) => {
                    const encoded = encodeURIComponent(url);
                    const uniqueId = `link_${idx}`;
                    linkHtml += `
                        <div style="display: flex; align-items: center; margin: 5px 0; padding: 5px; border-bottom: 1px solid #f0f0f0;">
                            <input type="checkbox" id="${uniqueId}" class="link-checkbox" value="${htmlEscape(url)}" checked style="width: auto; margin-right: 8px;">
                            <label for="${uniqueId}" style="flex: 1; word-break: break-all; font-size: 14px;">
                                ${idx+1}. <a href="?url=${encoded}" class="download-link" style="color: #2196F3; text-decoration: none;">${htmlEscape(url)}</a>
                            </label>
                        </div>
                    `;
                });
                document.getElementById('batchResult').innerHTML = `
                    <div class="url-list">${linkHtml}</div>
                    <button type="button" onclick="toggleSelectAllLinks()">📌 全选/取消全选</button>
                    <button onclick="backendBatchDownloadZip()">📦 打包下载ZIP</button>
                    <div id="batchProgressDisplay" class="progress-display" style="display: none;"></div>
                    <button onclick="copyLinkList()">📋 复制链接</button>
                    <button onclick="downloadLinkList()">📥 下载链接文本</button>
                    <button onclick="clearBatchResult()" class="clear-btn">🗑️ 清空输出</button>
                    <p class="tip">⚠️ 勾选后操作，支持分批打包</p>
                `;
            } else {
                document.getElementById('batchResult').innerHTML = '';
            }
        }

        function toggleSelectAllLinks() {
            const checkboxes = document.querySelectorAll('.link-checkbox');
            const isAllChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !isAllChecked);
            showCenterToast(isAllChecked ? '已取消全选' : '已全选所有链接');
        }

        async function backendBatchDownloadZip() {
            const checkedCheckboxes = document.querySelectorAll('.link-checkbox:checked');
            if (checkedCheckboxes.length === 0) return showFieldError(document.getElementById('batchResult'), '请勾选链接');
            const linkList = Array.from(checkedCheckboxes).map(cb => decodeURIComponent(cb.value));
            const total = linkList.length;
            const progressDisplay = document.getElementById('batchProgressDisplay');
            progressDisplay.style.display = 'block';
            progressDisplay.textContent = `开始处理... 0/${total}`;
            try {
                const result = await sendAsyncRequest('backend_batch_zip', { link_list: JSON.stringify(linkList) });
                if (result.status !== 'success') throw new Error(result.msg);
                const { progress_id, fixed_temp_dir } = result.data;
                let progressTimer = null;
                progressTimer = setInterval(async () => {
                    const progressResult = await sendAsyncRequest('get_batch_progress', { progress_id: progress_id });
                    if (progressResult.status !== 'success') {
                        clearInterval(progressTimer);
                        progressDisplay.textContent = `进度查询失败：${progressResult.msg}`;
                        return;
                    }
                    const { current, status, msg, zip_name } = progressResult.data;
                    if (status === 'downloading') {
                        progressDisplay.textContent = `正在下载第${current}个文件（${current}/${total}）`;
                    } else if (status === 'packing') {
                        progressDisplay.textContent = `正在打包ZIP...（${current}/${total}）`;
                    } else if (status === 'completed') {
                        clearInterval(progressTimer);
                        progressDisplay.textContent = `✅ ${msg}`;
                        setTimeout(async () => {
                            const zipBlob = await fetch(window.location.href, {
                                method: 'POST',
                                body: new URLSearchParams({ 
                                    action: 'download_zip', 
                                    zip_name: zip_name, 
                                    fixed_temp_dir: fixed_temp_dir 
                                })
                            }).then(res => res.blob());
                            const a = document.createElement('a');
                            a.href = URL.createObjectURL(zipBlob);
                            a.download = `TVBOX批量下载_${new Date().getTime()}.zip`;
                            a.click();
                            URL.revokeObjectURL(a.href);
                            sendAsyncRequest('delete_temp_zip', { fixed_temp_dir: fixed_temp_dir });
                            setTimeout(() => {
                                progressDisplay.style.display = 'none';
                                progressDisplay.textContent = '';
                            }, 5000);
                        }, 1200);
                    }
                }, 1500);
            } catch (err) {
                progressDisplay.textContent = `❌ 打包失败：${err.message}`;
                showFieldError(document.getElementById('batchResult'), `打包失败：${err.message}`);
            }
        }

        // 4. Base64编码
        async function doBase64Encode() {
            const encodeText = document.getElementById('encode_text').value.trim();
            const encodeFile = document.getElementById('encode_file').files[0];
            const encodePrefix = document.getElementById('encode_prefix').value.trim();
            if (!encodeText && !encodeFile) return showFieldError(document.getElementById('encode_text'), '请填写文本或上传文件');
            let content = '';
            if (encodeText) {
                content = encodeText;
            } else {
                content = await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = e => resolve(e.target.result);
                    reader.onerror = () => reject('文件读取失败');
                    reader.readAsText(encodeFile, 'UTF-8');
                });
            }
            if (!content) return showFieldError(document.getElementById('encode_text'), '内容不能为空');
            const encoded = encodePrefix + btoa(unescape(encodeURIComponent(content))).replace(/[\n\r ]/g, '');
            document.getElementById('base64Msg').innerHTML = '<div class="success">✅ 编码完成！</div>';
            document.getElementById('base64EncodeResultArea').style.display = 'block';
            const blob = new Blob([encoded], { type: 'text/plain;charset=utf-8' });
            const blobUrl = URL.createObjectURL(blob);
            document.getElementById('base64EncodeResultArea').innerHTML = `
                <div class="code-block" id="base64EncodeCode">${htmlEscape(encoded)}</div>
                <button onclick="copyBase64Encode()">📋 复制结果</button>
                <button type="button" onclick="downloadEncodedFile('${blobUrl}')">📥 下载编码文件</button>
                <button onclick="importToDecode()">🔄 导入到解码</button>
                <button onclick="clearBase64EncodeResult()" class="clear-btn">🗑️ 清空输出</button>
            `;
        }

        function downloadEncodedFile(blobUrl) {
            const a = document.createElement('a');
            a.href = blobUrl;
            a.download = 'encoded.txt';
            a.click();
            URL.revokeObjectURL(blobUrl);
        }

        // 5. Base64解码
        async function doBase64Decode() {
            const decodeStr = document.getElementById('decode_str').value.trim();
            if (!decodeStr) return showFieldError(document.getElementById('decode_str'), '请输入编码内容');
            const result = await sendAsyncRequest('do_base64_decode', { decode_str: decodeStr });
            document.getElementById('base64DecodeMsg').textContent = result.status === 'error' ? `❌ ${result.msg}` : '';
            if (result.status === 'success') {
                localStorage.setItem('base64DecodedResult', result.data.content);
                localStorage.setItem('base64IsText', result.data.isText);
                let preview = result.data.isText 
                    ? `<pre class="code-block" id="decodedPreview">${htmlEscape(result.data.content)}</pre>` 
                    : `<p>📦 二进制文件，建议下载查看</p>`;
                document.getElementById('base64DecodeResultArea').innerHTML = `
                    <div class="success">✅ ${result.msg}</div>
                    <div class="section" style="margin-top:15px;padding:15px;">
                        <h4>🔍 预览：</h4>
                        ${preview}
                    </div>
                    <button onclick="copyDecodedResult()">📋 复制结果</button>
                    <button onclick="downloadDecodedFile('${result.data.fileName}', ${result.data.isText})">📥 下载文件</button>
                    <button onclick="clearBase64DecodeResult()" class="clear-btn">🗑️ 清空输出</button>
                `;
            } else {
                document.getElementById('base64DecodeResultArea').innerHTML = '';
            }
        }

        // 复制函数
        function copyJson() {
            const content = document.getElementById('siteJsonResult').textContent;
            navigator.clipboard.writeText(content).then(() => showCenterToast('复制成功')).catch(() => showCenterToast('复制失败', false));
        }

        function copyMultiSiteResult() {
            const content = document.getElementById('formattedMultiSite').textContent;
            navigator.clipboard.writeText(content).then(() => showCenterToast('复制成功')).catch(() => showCenterToast('复制失败', false));
        }

        function copyLinkList() {
            const checked = document.querySelectorAll('.link-checkbox:checked');
            if (checked.length === 0) return showFieldError(document.getElementById('batchResult'), '请勾选链接');
            const links = Array.from(checked).map(cb => cb.value);
            navigator.clipboard.writeText(links.join('\n')).then(() => showCenterToast(`复制成功（${links.length}个）`)).catch(() => showCenterToast('复制失败', false));
        }

        function copyBase64Encode() {
            const text = document.getElementById('base64EncodeCode').textContent;
            navigator.clipboard.writeText(text).then(() => showCenterToast('复制成功')).catch(() => showCenterToast('复制失败', false));
        }

        function copyDecodedResult() {
            const preview = document.getElementById('decodedPreview');
            if (!preview) return showCenterToast('无文本可复制', false);
            navigator.clipboard.writeText(preview.textContent).then(() => showCenterToast('复制成功')).catch(() => showCenterToast('复制失败', false));
        }

        // 下载函数
        function downloadSiteJson() {
            const content = document.getElementById('siteJsonResult').textContent;
            const blob = new Blob([content], { type: 'application/json' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `tvbox_sites_${new Date().getTime()}.json`;
            a.click();
            URL.revokeObjectURL(a.href);
            showFieldError(document.getElementById('siteResult'), 'JSON开始下载');
        }

        function downloadMultiSiteJson() {
            const content = document.getElementById('formattedMultiSite').textContent;
            const blob = new Blob([content], { type: 'application/json' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `tvbox_multi_sites_${new Date().getTime()}.json`;
            a.click();
            URL.revokeObjectURL(a.href);
            showFieldError(document.getElementById('multiSiteResult'), 'JSON开始下载');
        }

        function downloadLinkList() {
            const checked = document.querySelectorAll('.link-checkbox:checked');
            if (checked.length === 0) return showFieldError(document.getElementById('batchResult'), '请勾选链接');
            const links = Array.from(checked).map(cb => cb.value);
            const blob = new Blob([links.join('\n')], { type: 'text/plain' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `extracted_links_${new Date().getTime()}.txt`;
            a.click();
            URL.revokeObjectURL(a.href);
            showFieldError(document.getElementById('batchResult'), `链接文本开始下载（${links.length}个）`);
        }

        function downloadDecodedFile(fileName, isText) {
            try {
                const content = localStorage.getItem('base64DecodedResult');
                if (!content) throw new Error('无解码结果');
                const blobContent = isText ? content : atob(content);
                const blob = isText 
                    ? new Blob([blobContent], { type: 'text/plain;charset=utf-8' }) 
                    : new Blob([new Uint8Array([...blobContent].map(char => char.charCodeAt(0)))], { type: 'application/octet-stream' });
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = fileName;
                a.click();
                URL.revokeObjectURL(a.href);
                showFieldError(document.getElementById('base64DecodeResultArea'), '文件开始下载');
            } catch (err) {
                showFieldError(document.getElementById('base64DecodeResultArea'), `下载失败：${err.message}`);
            }
        }

        function importToDecode() {
            const code = document.getElementById('base64EncodeCode').textContent;
            document.getElementById('decode_str').value = code;
            showFieldError(document.getElementById('decode_str'), '编码结果已导入');
        }

        // 清空函数
        function clearSite() {
            document.getElementById('siteText').value = '';
            document.getElementById('siteKey').value = '';
            document.getElementById('keywordCount').textContent = "关键词数量：0";
            document.getElementById('siteMsg').innerHTML = '';
            document.getElementById('siteResult').innerHTML = '';
        }

        function clearSiteResult() {
            document.getElementById('siteResult').innerHTML = '';
            document.getElementById('siteMsg').innerHTML = '';
        }

        function clearMultiSiteJar() {
            document.getElementById('multiSiteInput').value = '';
            document.getElementById('multiTargetField').value = 'jar';
            document.getElementById('multiFieldValue').value = '';
            document.getElementById('multiSiteResult').innerHTML = '';
        }

        function clearMultiSiteResult() {
            document.getElementById('multiSiteResult').innerHTML = '';
        }

        function clearBatch() {
            document.getElementById('batchText').value = '';
            document.getElementById('batchMsg').innerHTML = '';
            document.getElementById('batchResult').innerHTML = '';
        }

        function clearBatchResult() {
            document.getElementById('batchResult').innerHTML = '';
            document.getElementById('batchMsg').innerHTML = '';
        }

        function clearBase64Encode() {
            document.getElementById('encode_text').value = '';
            document.getElementById('encode_file').value = '';
            document.getElementById('encode_prefix').value = '';
            document.getElementById('base64Msg').innerHTML = '';
            document.getElementById('base64EncodeResultArea').style.display = 'none';
            uploadFileRecords.encodeFile = '';
            document.getElementById('encodeFileTip').textContent = '';
        }

        function clearBase64EncodeResult() {
            document.getElementById('base64EncodeResultArea').style.display = 'none';
            document.getElementById('base64Msg').innerHTML = '';
        }

        function clearBase64Decode() {
            document.getElementById('decode_str').value = '';
            document.getElementById('base64DecodeMsg').textContent = '';
            document.getElementById('base64DecodeResultArea').innerHTML = '';
            localStorage.removeItem('base64DecodedResult');
            localStorage.removeItem('base64IsText');
            uploadFileRecords.decodeFile = '';
            document.getElementById('decodeFileUploadTip').textContent = '';
        }

        function clearBase64DecodeResult() {
            document.getElementById('base64DecodeResultArea').innerHTML = '';
            document.getElementById('base64DecodeMsg').textContent = '';
            localStorage.removeItem('base64DecodedResult');
            localStorage.removeItem('base64IsText');
        }

        function clearAll() {
            clearSite();
            clearMultiSiteJar();
            clearBatch();
            clearBase64Encode();
            clearBase64Decode();
            Object.keys(uploadFileRecords).forEach(key => uploadFileRecords[key] = '');
            document.querySelectorAll('.file-upload-tip').forEach(tip => tip.textContent = '');
        }
    </script>
</body>
</html>
