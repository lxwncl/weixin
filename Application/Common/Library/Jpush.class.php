<?php

namespace Common\Library;
use Think\Log;

class Jpush {
    private $apiUrl = 'https://api.jpush.cn/v3/push';
    //Device API 用于在服务器端查询、设置、更新、删除设备的 tag,alias 信息，使用时需要注意不要让服务端设置的标签又被客户端给覆盖了

    private $appKey = '';
    private $masterKey = '';
    private  $defaultPostArray = array();
    
    public function __construct()
    {
        $this->appKey    = C('PUSH_APPKEY');
        $this->masterKey = C('PUSH_MASTERKEY');
    }

    private function getHeader()
    {
        return array(
            CURLOPT_HTTPHEADER  => array('Authorization: Basic ' . base64_encode($this->appKey.':'.$this->masterKey)),
            CURLOPT_SSL_VERIFYPEER  => false,
        );
    }

    public function resetPostArray()
    {
        $this->defaultPostArray = array(
            'platform' => array('android', 'ios'),
            'audience' => '',
            'notification' => array(
                "android" => array("alert" => '', "title" => '', "builder_id" => 1, "extras" => array()),
                "ios"        => array("alert" => '', "sound" => 'default', "badge" => "1", "extras" => array())
            ),
            'options' => array("time_to_live"=> 60, "apns_production" => true)//苹果证书true为生产，false为开发
        );
        return $this;
    }

    /**
     * 批量设置设备的推送标签
     *
     * @param $tag  推送标签
     * @param array $registrationIds 极光标示的registrationId
     */
    public function addTag($tag,Array $registrationIds)
    {
        if (empty($tag) || empty($registrationIds))
        {
            return;
        }
        $lastApiUrl  = $this->apiUrl;
        $this->apiUrl = 'https://device.jpush.cn/v3/tags/' . $tag;
        $data = array('registration_ids' => array('add' => $registrationIds, 'remove' => array()));
        $response = $this->send($data);
        $this->apiUrl = $lastApiUrl;
    }

    /**
     * 查询设备标签
     * @param $registrationId
     * @return bool|mixed
     */
    public function queryTags($registrationId)
    {
        $this->apiUrl = 'https://device.jpush.cn/v3/devices/' . $registrationId;
        $header = $this->getHeader();
        return Http::get($this->apiUrl, 3, $header);
    }
    /**
     * 设置单个设备的tags
     *
     * @param string $registrationId
     * @param array $add
     * @param array $remove
     * @return bool
     */
    public function setDeviceTag($registrationId, Array $add, $remove = array())
    {
        if (empty($registrationId))
        {
            return false;
        }
        $lastApiUrl  = $this->apiUrl;
        $this->apiUrl = 'https://device.jpush.cn/v3/devices/' . $registrationId;
        $data = array('tags' => array('add' => $add, 'remove' => $remove), 'alias' => '');
        $response = $this->send($data);
        $this->apiUrl = $lastApiUrl;
    }


    /**
     * 设置ios推送标题
     * @param $title
     * @return $this
     */
    public function setIOSNotificationTitle($title)
    {
        $this->defaultPostArray['notification']['ios']['alert'] = $title;
        return $this;
    }

    /**
     * 设置android推送标题
     *
     * @param $title  标题
     * @param $titleContent 标题下面的内容
     * @return $this
     */
    public function sendAndroidTitle($title, $titleContent)
    {
        $this->defaultPostArray['notification']['android']['title']  = $title;
        $this->defaultPostArray['notification']['android']['alert'] = $titleContent;
        return $this;
    }


    /**
     * 设置audience字段的tag 和 tag_and 字段
     * @param array  $tags  tags数组
     * @param string $tagName tag|tag_and
     */
    public function setTags(Array $tags, $tagName = 'tag')
    {
        $this->defaultPostArray['audience'][$tagName] = $tags;
    }

    /**
     * 添加到audience字段中tag里面
     * @param array $tags
     * @return $this
     */
    public function addTags(Array $tags)
    {
        if (empty($tags))
        {
            return $this;
        }
        //array('all')的情况
        if (in_array('all', $tags))
        {
            $this->defaultPostArray['audience'] = 'all';
        }
        else
        {
            if (!isset($this->defaultPostArray['audience']['tag']))
            {
                $this->defaultPostArray['audience']['tag'] = array();
            }
            $this->defaultPostArray['audience']['tag'] =  $this->defaultPostArray['audience']['tag'] + $tags;
        }

        return $this;
    }

    /**
     * 设置发送到的设备 registration_idd
     * @param $ids
     */
    public function setRegistrationIds(Array $ids)
    {
        $this->defaultPostArray['audience']['registration_id'] = $ids;
    }

    /**
     * 添加到audience registration_id里面
     * @param Array $id
     * @return $this
     */
    public function addRegistrationId(Array $id)
    {
        if (empty($id))
        {
            return $this;
        }
        if (!isset($this->defaultPostArray['audience']['registration_id']))
        {
            $this->defaultPostArray['audience']['registration_id'] = array();
        }
        $this->defaultPostArray['audience']['registration_id'] = $this->defaultPostArray['audience']['registration_id'] + $id;
        return $this;
    }

    /**
     * 设置推送的extras字段
     * @param $extras
     * @return $this
     */
    public function addPushExtras($extras)
    {
        foreach(['android', 'ios'] as $v)
        {
            if (isset($this->defaultPostArray['notification'][$v]['extras']))
            {
                $this->defaultPostArray['notification'][$v]['extras'] = array_merge($this->defaultPostArray['notification']['android']['extras'], $extras);
            }
            else
            {
                $this->defaultPostArray['notification'][$v]['extras'] = $extras;
            }
        }
        return $this;
    }

    /**
     * 推送到设备
     * @return int
     */
    public function pushToDevices()
    {
        if (!isset($this->defaultPostArray['audience']['registration_id']) && !isset($this->defaultPostArray['audience']['tag']))
        {
            $response = $this->send($this->defaultPostArray);
            $info   = json_decode($response);
            return !isset($info->sendno) ? 0 : 1;
        }

        if (isset($this->defaultPostArray['audience']['registration_id']))
        {
            $registrationIds = $this->defaultPostArray['audience']['registration_id'];
            unset($this->defaultPostArray['audience']['registration_id']);
        }

        if (isset($this->defaultPostArray['audience']['tag']))
        {
            $this->setTags(['PUSH_ON'], 'tag_and');
            //超过20个tag需要拆分
            if (20 < count($this->defaultPostArray['audience']['tag']))
            {
                $tagsChunks = array_chunk($this->defaultPostArray['audience']['tag'], 20);
                $tagChunksNum = count($tagsChunks);
                for($i = 0; $i < $tagChunksNum; $i++)
                {
                    $this->setTags($tagsChunks[$i]);
                    //只有开启了推送设置的设备才推送
                    $this->setTags(['PUSH_ON'], 'tag_and');
                    $this->send($this->defaultPostArray);
                }
            }
            else
            {
                $this->send($this->defaultPostArray);
            }
            unset($this->defaultPostArray['audience']['tag']);
        }

        $msg = '';
        $success = 1;
        if (isset($registrationIds))
        {
            $this->defaultPostArray['audience']['registration_id'] = $registrationIds;
            if (1000 < count($this->defaultPostArray['audience']['registration_id']))
            {
                $registrationChunks = array_chunk($this->defaultPostArray['audience']['registration_id'], 1000);
                $registrationChunksNum = count($registrationChunks);
                for($i = 0; $i < $registrationChunksNum; $i++)
                {
                    $this->setRegistrationIds($registrationChunks[$i]);
                    $this->send($this->defaultPostArray);
                }
            }
            else
            {
                $this->send($this->defaultPostArray);
            }
        }
        return $success;
    }

    /**
     * 发送请求
     * @param $param
     * @return mixed
     */
    private function send($param)
    {
        $header = $this->getHeader();
        $strParam = json_encode($param);
        $return = Http::post($this->apiUrl, $strParam, $header);
        if (strpos($return, 'error') !== false)
        {
            $return = json_decode($return);
            \Think\Log::record('push request fail . url :' . $this->apiUrl . ' param:'.$strParam . ' return '.$return->error->message);
            \Think\Log::save('', './ErrLogs/push.log');
            return false;
        }
        else
        {
            return true;
        }
    }
}