<?php

$path_data = '../data';  // 设置数据目录的相对路径。
$path_media = $path_data . '/media';  // 根据数据目录设置媒体目录的路径。

$file_host = $path_data . '/host.txt';  // 设置存储主机信息的文件路径。
$file_imagehost = $path_data . '/imagehost.txt';  // 设置存储图片主机信息的文件路径。
$file_vod = $path_data . '/vod.txt';  // 设置存储视频信息的文件路径。

$host = '';  // 初始化主机信息为空。
$imagehost = '';  // 初始化图片主机信息为空。
$vod_list_max = [];  // 初始化视频列表的最大值为空数组。

$cookie = '572a0a9982db70b0b107d638369b0b95=81188b3b7556baf6e51ad1fd9c9c1e37';  // 定义用于 HTTP 请求的 cookie 字符串。

/**
 * 获取 HTML 内容
 */
function getHtml(string $url): string
{
    try {
        global $cookie;  // 引用全局变量 $cookie。
        $body = shell_exec('curl --connect-timeout 10 -m 30 -H "User-Agent:Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.75 Safari/537.36" --cookie "' . $cookie . '" ' . $url . '  2>&1');  // 使用 curl 获取指定 URL 的 HTML 内容。

        if ($body === false) {
            throw new Exception("Failed to execute curl command.");
        }

        return $body ?: '';  // 如果获取内容为空，返回空字符串。
    } catch (\Throwable $th) {
        error_log('Error in getHtml: ' . $th->getMessage());
        return '';  // 发生异常时返回空字符串。
    }
}

/**
 * 获取普通页面的 header 信息
 */
function getHeader(string $url): string
{
    try {
        $header = shell_exec('curl --connect-timeout 10 -m 30 -H "User-Agent:Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.75 Safari/537.36" -I ' . $url . '  2>&1');  // 使用 curl 获取指定 URL 的 header 信息。

        if ($header === false) {
            throw new Exception("Failed to execute curl command.");
        }

        return $header ?: '';  // 如果获取内容为空，返回空字符串。
    } catch (\Throwable $th) {
        error_log('Error in getHeader: ' . $th->getMessage());
        return '';  // 发生异常时返回空字符串。
    }
}

/**
 * 获取带有 referer 的验证码页面的 header 信息
 */
function getHeader2(string $url, string $host): string
{
    try {
        $curl = 'curl --connect-timeout 10 -m 30 -H "User-Agent:Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.75 Safari/537.36" --referer ' . $host . '/ -I ' . $url;  // 使用 curl 获取带有 referer 头信息的指定 URL 的 header 信息。
        $header = shell_exec($curl);  // 执行 curl 命令获取 header 信息。

        if ($header === false) {
            throw new Exception("Failed to execute curl command.");
        }

        return $header ?: '';  // 如果获取内容为空，返回空字符串。
    } catch (\Throwable $th) {
        error_log('Error in getHeader2: ' . $th->getMessage());
        return '';  // 发生异常时返回空字符串。
    }
}
/**
 * 递归检查当前域名
 */
function checkHost(): bool
{
    global $file_host;
    $tmp_host = "http://hsck.us";  // 设置初始检查的域名。
    $tmp_host = @file_get_contents($file_host);  // 尝试从文件中读取之前保存的域名，如果失败则使用默认域名。

    while (true) {
        $ret = checkHostContent($tmp_host);  // 调用函数检查当前域名内容。
        if ($ret === true) {  // 如果返回 true，表示域名检查通过。
            file_put_contents($file_host, $tmp_host);  // 将通过检查的域名写入文件中。
            global $host;
            $host = $tmp_host;  // 将通过检查的域名赋值给全局变量 $host。
            return true;  // 返回 true 表示检查通过。
        } elseif ($ret && is_string($ret)) {  // 如果返回的是一个字符串，则更新待检查的域名。
            $tmp_host = $ret;
        } else {  // 如果返回 false 或者其他非字符串值，则表示检查失败。
            return false;  // 返回 false 表示检查未通过。
        }
    }
}

function stringToHex($str): string
{
    $val = '';
    for ($i = 0; $i < strlen($str); $i++) {
        $val .= strval(ord($str[$i]) + 1);  // 将字符串转换为十六进制。
    }
    return $val;
}

/**
 * 检查域名正文类型
 */
function checkHostContent(string $tmp_host)
{
    echo "[debug] host check {$tmp_host}\n";  // 输出调试信息，显示当前检查的域名。
    $html = getHtml($tmp_host);  // 获取指定域名的 HTML 内容。

    // 检查是否为合法页面
    if (strlen($html) > 20000 && strstr($html, "stui-warp-content")) {
        $ret = strstr($html, "最近更新");  // 检查页面是否包含特定内容。

        if ($ret) {  // 如果页面包含特定内容，则进一步处理。
            $num = preg_match_all('/\/vodtype\/(\d+)\.html"><span\sclass="count\spull-right">(\d+)<\/span>/', $html, $matches);  // 使用正则表达式匹配视频类型和数量。

            if ($num) {  // 如果成功匹配到内容。
                global $vod_list_max;
                for ($i = 0; $i < $num; $i++) {
                    $vod_list_max[$matches[1][$i]] = ['num' => $matches[2][$i], 'page' => ceil($matches[2][$i] / 40)];  // 将匹配到的视频类型和数量存入全局数组。
                }

                // 检查封面图片域名
                if (preg_match('/data-original="(https?:\/\/[a-zA-Z\d]+\.[a-zA-Z]+)\/images\/[a-zA-Z\d\/\:\.]+">/', $html, $matches)) {
                    global $imagehost;
                    $imagehost = $matches[1];  // 获取图片域名。
                    echo "[debug] images host: {$imagehost}\n";  // 输出调试信息，显示图片域名。
                } else {
                    echo "[error] images host get error!\n";  // 如果获取图片域名失败，则输出错误信息。
                    return false;  // 返回 false 表示获取图片域名出错。
                }
                return true;  // 返回 true 表示域名内容检查通过。
            }
        }
    }

    // 检查是否为跳转页面
    if (strstr($html, 'strU=') && strstr($html, 'id="hao123"')) {
        $num = preg_match('/strU="(https?:\/\/[a-zA-Z0-9:\/\.]+\?u=?)"/', $html, $matches);  // 使用正则表达式匹配跳转 URL。

        if ($num) {  // 如果成功匹配到跳转 URL。
            $url = $matches[1] . "{$tmp_host}/&p=/";  // 构建完整的跳转 URL。
            try {
                echo "[debug] host jump {$url}\n";  // 输出调试信息，显示跳转 URL。
                $html = getHeader($url);  // 获取跳转页面的 header 信息。
                $num = preg_match('/Location: (http:\/\/[a-zA-Z0-9]+\.[a-zA-Z0-9]+)/', $html, $matches);  // 使用正则表达式匹配新的跳转地址。

                if ($num) {
                    return $matches[1];  // 返回新的跳转地址。
                }
            } catch (\Throwable $th) {
                //throw $th;
            }
        }
    }

    // 检查是否为包含滑动验证的页面
    if (strstr($html, '滑动验证')) {
        $num = preg_match('/src="(\/huadong.*js\?id=\w+)"/', $html, $matches);  // 使用正则表达式匹配滑动验证的 JavaScript URL。

        if ($num) {  // 如果成功匹配到 JavaScript URL。
            $js_url = $tmp_host . $matches[1];  // 构建完整的 JavaScript URL。
            var_dump($js_url);  // 输出调试信息，显示 JavaScript URL。
            $js_html = getHtml($js_url);  // 获取 JavaScript 文件的内容。
            $num = preg_match('/key="(\w+)",value="(\w+)"/', $js_html, $matches);  // 使用正则表达式匹配 key 和 value。

            if ($num) {  // 如果成功匹配到 key 和 value。
                $key = $matches[1];
                $value = $matches[2];
                $num = preg_match('/"(\/[\w\_]+yanzheng_huadong.php[\w_=\?]+&key=)"/', $js_html, $matches);  // 使用正则表达式匹配验证 URL。

                if ($num) {  // 如果成功匹配到验证 URL。
                    $yanzheng_url = $tmp_host . $matches[1] . $key . '&value=' . md5(stringToHex($value));  // 构建完整的验证 URL。

                    $yanzheng_url = addcslashes($yanzheng_url, '?=&');  // 对 URL 进行转义处理。

                    $header = getHeader2($yanzheng_url, $tmp_host);  // 获取验证 URL 的 header 信息。

                    $num = preg_match('/Set-Cookie: ([\w\=]+);/', $header, $matches);  // 使用正则表达式匹配 cookie。

                    if ($num) {  // 如果成功匹配到 cookie。
                        global $cookie;
                        $cookie = $matches[1];  // 更新全局变量 $cookie。
                        var_dump('new cookie: ' . $cookie);  // 输出调试信息，显示新的 cookie。
                        return $tmp_host;  // 返回当前域名。
                    } else {
                        var_dump($header);
                        echo "js header cookie preg error!\n";  // 如果匹配 cookie 失败，则输出错误信息。
                    }
                } else {
                    var_dump($js_html);
                    echo "js yanzheng_url preg error!\n";  // 如果匹配验证 URL 失败，则输出错误信息。
                }
            } else {
                var_dump($js_html);
                echo "js key/value preg error!\n";  // 如果匹配 key/value 失败，则输出错误信息。
            }
        }
    } else {
        var_dump($html);
        echo "host check error no rule...\n";  // 如果不符合任何规则，则输出错误信息。
    }
    return false;  // 返回 false 表示未能成功检查当前域名。
}
/**
 * 获取当前全局变量 $host 的值
 */
function getHost(): string
{
    global $host;
    return $host;  // 返回全局变量 $host 的字符串值
}

/**
 * 根据给定的 ID 计算页数，每页 100 条数据
 */
function getPageNum(int $id): int
{
    return ceil($id / 100);  // 返回向上取整后的页数
}

/**
 * 获取指定视频类型和页码的列表数据，并保存为 JSON 文件
 */
function getListArr(int $vod_type_id, string $vod_type_name, int $page, int $max_page): bool
{
    echo "[debug] fetch vod_type: {$vod_type_id} {$vod_type_name} page: {$page} max_page: {$max_page}\n";  // 输出调试信息，显示当前获取列表的视频类型和页码信息
    $url = getHost() . "/vodtype/{$vod_type_id}-{$page}.html";  // 构建获取列表数据的 URL
    $list_html = getHtml($url);  // 获取列表页面的 HTML 内容

    $num = preg_match_all('/stui-vodlist__thumb\slazyload"\shref="(\/vodplay\/(\d+)-1-1\.html)"\stitle="([\w\d\s\.\-_+=\x{4e00}-\x{9fff}]+)"\sdata-original="(https?:\/\/[a-z0-9\/\.\-]+)">/u', $list_html, $matches);
    if (!$num) {
        echo "[error] vod_list preg match error! vod_type: {$vod_type_id} {$vod_type_name} page: {$page} max_page: {$max_page}\n";  // 输出错误信息，显示匹配视频列表失败的详细信息
        echo $list_html;  // 输出获取到的 HTML 内容，用于排查问题
        return false;  // 返回 false 表示获取列表失败
    }

    preg_match_all('/pic-text text-right">([\d\:]+)</', $list_html, $matches_time);
    preg_match_all('/fa-heart"><\/i>&nbsp;(\d+)\s?&nbsp;&nbsp;</', $list_html, $matches_heart);
    preg_match_all('/fa-eye"><\/i>&nbsp;(\d+)\s?&nbsp;&nbsp;</', $list_html, $matches_eye);

    global $path_media;

    for ($i = 0; $i < $num; $i++) {
        $data = [];
        $data['vod_type_id'] = $vod_type_id;
        $data['vod_type_name'] = $vod_type_name;
        $data['vod_id'] = $matches[2][$i];
        $data['title'] = $matches[3][$i];
        $data['time'] = $matches_time[1][$i] ?? '';
        $data['heart'] = $matches_heart[1][$i] ?? 0;
        $data['eye'] = $matches_eye[1][$i] ?? 0;
        $vod_play_url =  $matches[1][$i];
        $content_url = getHost() . $vod_play_url;
        $data['thumd'] = $matches[4][$i];

        $path_vod_id = $path_media . "/" . getPageNum($data['vod_id']) . "/";
        if (!is_dir($path_vod_id)) {
            mkdir($path_vod_id, 0777, true);
        }
        $file_vod_id_json = $path_vod_id . "{$data['vod_id']}.json";
        if (is_file($file_vod_id_json)) {
            $cont = file_get_contents($file_vod_id_json);
            $cont = json_decode($cont, true);
            if ($cont['heart'] != $data['heart'] || $cont['eye'] != $data['eye']) {
                $cont['heart'] = $data['heart'];
                $cont['eye'] = $data['eye'];
                file_put_contents($file_vod_id_json, json_encode($cont, JSON_UNESCAPED_UNICODE));
                echo "[debug] vod_type: {$vod_type_id} {$vod_type_name} page: {$page} max_page: {$max_page} child_i: $i vod_id: {$data['vod_id']} update json file heart | eye ~ \n";
            } else {
                echo "[debug] vod_type: {$vod_type_id} {$vod_type_name} page: {$page} max_page: {$max_page} child_i: $i vod_id: {$data['vod_id']} file json exists! jump~\n";
            }
            continue;
        }

        $html = getHtml($content_url);

        if (!preg_match('/player_aaaa.*"url":"(http.*m3u8)","url_next/', $html, $ret)) {
            echo "[error] vod_play preg match error! vod_id: {$data['vod_id']}\n";  // 输出错误信息，显示匹配视频播放地址失败的详细信息
            continue;
        }

        $data['media'] = str_replace("\/", "/", $ret[1]);

        if (preg_match('/时间：([ \d\-:]+)/', $html, $ret)) {
            $data['uptime'] = $ret[1];
        } else {
            echo "[error] upload time preg match error! vod_id: {$data['vod_id']}\n";  // 输出错误信息，显示匹配上传时间失败的详细信息
            $data['uptime'] = '';
        }

        print_r($data);  // 打印数据信息，用于调试和检查
        echo "[info] page: {$page} max_page: {$max_page} child_i: $i ";
        foreach ($data as $key => $value) {
            echo "{$key}: {$value} ";  // 输出数据的键值对信息
        }
        echo "\n";

        unset($data['vod_type_name']);
        $data['title'] = base64_encode($data['title']);  // 对标题进行 Base64 编码
        $data['thumd'] = preg_replace("/https?:\/\/[a-zA-Z\d\.]+\/images/", "/images", $data['thumd']);  // 替换封面图片 URL 的域名部分
        $data['thumd'] = base64_encode($data['thumd']);  // 对封面图片 URL 进行 Base64 编码
        $data['media'] = base64_encode($data['media']);  // 对视频播放地址进行 Base64 编码

        file_put_contents($file_vod_id_json, json_encode($data, JSON_UNESCAPED_UNICODE));  // 将数据以 JSON 格式写入文件
    }

    return true;  // 返回 true 表示获取列表并保存成功
}

/**
 * 检查图片域名是否发生变化，如果变化则更新并保存
 */
function check_image_host(): bool
{
    global $imagehost;
    global $file_imagehost;
    $old_imagehost = @file_get_contents($file_imagehost);  // 尝试从文件中读取旧的图片域名

    if ($old_imagehost != $imagehost) {  // 如果旧的图片域名与当前图片域名不同
        echo "[debug] images host update! new: {$imagehost} old: {$old_imagehost}\n";  // 输出调试信息，显示图片域名更新的详细信息
        file_put_contents($file_imagehost, $imagehost);  // 将新的图片域名写入文件
        return true;  // 返回 true 表示图片域名已更新
    }

    return false;  // 返回 false 表示图片域名未发生变化
}

function start()
{
    $vod_list = [
        15 => "国产视频",
        9 => "有码中文字幕",
        8 => "无码中文字幕",
        10 => "日本无码",
        7 => "日本有码",
        21 => "欧美高清",
        22 => "动漫剧情",
    ];

    // 使用参数化全局变量代替直接使用全局变量
    $file_vod = '/path/to/file_vod.json';
    $file_imagehost = '/path/to/file_imagehost.txt';
    $vod_list_max = []; // 初始化vod_list_max，这里可以根据实际需求初始化

    // 读取之前的vod_list_max数据
    $prev_vod_list_max = [];
    try {
        $prev_vod_str = file_get_contents($file_vod);
        if ($prev_vod_str !== false) {
            $prev_vod_list_max = json_decode($prev_vod_str, true);
            if ($prev_vod_list_max === null) {
                throw new Exception('Failed to decode previous vod_list_max JSON');
            }
        }
    } catch (Exception $e) {
        echo "[error] Failed to read or decode previous vod_list_max: " . $e->getMessage() . "\n";
        // 记录到日志文件中或其他处理方式
        return; // 停止执行
    }

    // 将当前的vod_list_max写入文件
    try {
        $write_result = file_put_contents($file_vod, json_encode($vod_list_max, JSON_UNESCAPED_UNICODE));
        if ($write_result === false) {
            throw new Exception('Failed to write vod_list_max to file');
        }
    } catch (Exception $e) {
        echo "[error] Failed to write vod_list_max to file: " . $e->getMessage() . "\n";
        // 记录到日志文件中或其他处理方式
        return; // 停止执行
    }

    $max_page = 0;

    // 计算最大页数
    foreach ($vod_list as $vod_type_id => $vod_type_name) {
        if (isset($vod_list_max[$vod_type_id])) {
            if (isset($prev_vod_list_max[$vod_type_id])) {
                if ($vod_list_max[$vod_type_id]['num'] > $prev_vod_list_max[$vod_type_id]['num']) {
                    $vod_list_max[$vod_type_id]['page'] = ceil(($vod_list_max[$vod_type_id]['num'] - $prev_vod_list_max[$vod_type_id]['num']) / 40);
                } else {
                    $vod_list_max[$vod_type_id]['page'] = 0;
                }
            }
            if ($vod_list_max[$vod_type_id]['page'] > $max_page) {
                $max_page = $vod_list_max[$vod_type_id]['page'];
            }
        }
    }

    if ($max_page > 0) {
        // 遍历获取每一页的数据
        for ($i = 1; $i <= $max_page; $i++) {
            $list_page = $i;
            foreach ($vod_list as $vod_type_id => $vod_type_name) {
                if (isset($vod_list_max[$vod_type_id]) && $list_page <= $vod_list_max[$vod_type_id]['page']) {
                    getListArr($vod_type_id, $vod_type_name, $list_page, $max_page);
                }
            }
        }
        
        // 检查并更新图片主机信息
        if (check_image_host()) {
            system('php out.php');
                 }
     else {
        // 如果没有更新，检查图片主机信息
        if (check_image_host()) {
            system('php out.php');
            
           
        } else {
            echo "no update!\n";
            
           
        }
    }
}

// 检查主机是否可用，如果不可用则输出错误消息并退出
if (!checkHost()) {
    echo "[error] check host error! script stop\n";
   
    exit(0);
}

// 执行主函数start
start();

// 脚本执行结束输出消息
var_dump('bin.php run over!');
