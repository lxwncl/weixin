<?php
namespace Common\Library;
class Http {

    public static function get($url, $timeout = 1, $header = null) {
       $ch = curl_init();
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_RETURNTRANSFER => true
        );
        if (!is_null($header))
        {
            $options =$options + $header;
        }
        curl_setopt_array($ch, $options);
        $data = curl_exec($ch);
        $info = curl_getinfo($ch);
        if ($info['http_code'] == 200) {
            return $data;
        } else {
            \Think\Log::write('request url fail . url :' . $url . ' return '. $data);
            return false;
        }
       curl_close($ch);
       return $data;
    }

    public static function post($url, $data, $header = null) {
        $ch = curl_init();
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true
        );
        if (!is_null($header))
        {
            $options =$options + $header;
        }

        curl_setopt_array($ch, $options);
        $data = curl_exec($ch);
        //$info = curl_getinfo($ch);
        curl_close($ch);
        return $data;
    }

    public static function multi($urlarr) {
        $result = $res = $ch = array();
        $nch = 0;
        $mh = curl_multi_init();
        foreach ($urlarr as $nk => $url) {
            $timeout  = 2;
            $ch[$nch] = curl_init();
            curl_setopt_array($ch[$nch], array(
                CURLOPT_URL => $url,
                CURLOPT_HEADER => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
            ));
            curl_multi_add_handle($mh, $ch[$nch]);
            ++$nch;
        }
        /* wait for performing request */
        do {
            $mrc = curl_multi_exec($mh, $running);
        } while (CURLM_CALL_MULTI_PERFORM == $mrc);
        while ($running && $mrc == CURLM_OK) {
            while (curl_multi_exec($mh, $running) === CURLM_CALL_MULTI_PERFORM);
            if (curl_multi_select($mh) != -1) {
                // pull in new data;
                do {
                    $mrc = curl_multi_exec($mh, $running);
                } while (CURLM_CALL_MULTI_PERFORM == $mrc);
            }
        }
     
        if ($mrc != CURLM_OK) {
            error_log("CURL Data Error");
        }
     
        /* get data */
        $nch = 0;
        foreach ($urlarr as $moudle=>$node) {
            if (($err = curl_error($ch[$nch])) == '') {
                $res[$nch]=curl_multi_getcontent($ch[$nch]);
                $result[$moudle]=$res[$nch];
            }
            curl_multi_remove_handle($mh,$ch[$nch]);
            curl_close($ch[$nch]);
            ++$nch;
        }
        curl_multi_close($mh);
        return  $result;
    }


    /**
     * 发起一个一步请求
     * @author lr
     * @param $url
     * @param array $param
     * @param int $port
     * @param int $timeout
     * @return bool
     */
    public static function sockOpen($url,$param=array(),$port=80,$timeout=30){
        $host = $_SERVER['SERVER_NAME'];
        $error = '';
        $error_str = '';
        $data = http_build_query($param);
        $fp = fsockopen($host, $port, $error, $error_str, $timeout);
        if (!$fp) {
            return false;
        }
        $out = "POST ${url} HTTP/1.1\r\n";
        $out .= "Host:${host}\r\n";
        $out .= "Content-type:application/x-www-form-urlencoded\r\n";
        $out .= "Content-length:" . strlen($data) . "\r\n";
        $out .= "Connection:close\r\n\r\n";
        $out .= "${data}";
        fputs($fp, $out);
        $response = '';
         /*忽略执行结果
        while ($row = fread($fp, 4096)) {
            $response .= $row;
        }*/
        fclose($fp);
       /* $pos = strpos($response, "\r\n\r\n");
        $response = substr($response, $pos + 4);
        echo $response;*/
    }
}
