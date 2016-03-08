<?php

namespace DaoTH\Youtube;

use DaoTH\Lib\WebdotCurl;
use DaoTH\Lib\Common as C;

/**
 * Youtube Downloader
 * Tran Hieu Dao
 */
class Downloader {

    const ERROR_FAIL = 1;
    const ERROR_NO_ENCODEd_FORMAT_STREAM = 2;
    const ERROR_NO_FORMAT_STREAM_MAP = 3;
    const ERROR_NO_VIDEO_ID = 4;
    const ERROR_INVALID_URL = 5;

    public $videoInfo;
    private $curl;

    public function __construct() {
        $this->curl = new WebdotCurl();
    }

    /**
     * Fetch video info
     * @param string $videoid
     * @return boolean|string
     */
    public function fetchVideoInfo($videoid) {
        $videoInfo = $this->getInfo($videoid);
        $status = $url_encoded_fmt_stream_map = $type = $url = '';
        parse_str($videoInfo);
        if ($status == 'fail') {
            return false;
        }

        if (isset($url_encoded_fmt_stream_map)) {
            $formatsArray = explode(',', $url_encoded_fmt_stream_map);
        } else {
            $this->videoInfo = $videoInfo;
            return self::ERROR_NO_ENCODEd_FORMAT_STREAM;
        }
        if (count($formatsArray) == 0) {
            return self::ERROR_NO_FORMAT_STREAM_MAP;
        }
        $avail_formats = $this->getAvailableFormat($formatsArray);
        $videoInfo = array();

        for ($i = 0; $i < count($avail_formats); $i++) {
            $videoSize = C::formatBytes($this->curl->get_size($avail_formats[$i]['url']));
            if ($videoSize == '0B') {
                continue;
            }
            $directlink = explode('.googlevideo.com/', $avail_formats[$i]['url']);
            $directlink = 'http://redirector.googlevideo.com/' . $directlink[1] . '';
            $data['type'] = $avail_formats[$i]['type'];
            $data['size'] = $videoSize;
            $data['itag'] = $avail_formats[$i]['itag'];
            $data['url'] = $directlink;
            $videoInfo[] = $data;
        }
        return $videoInfo;
    }

    /**
     * Get available format
     * @param array $formatsArray
     * @return string
     */
    private function getAvailableFormat($formatArray) {
        $type = $url = '';
        $expire = time();
        $availFormats = array();
        foreach ($formatArray as $format) {
            $ipbits = $ip = $itag = $sig = $quality = '';
            parse_str($format);
            $type = explode(';', $type);
            $data = array(
                'itag'=>$itag,
                'quality'=>$quality,
                'type' => $type[0],
                'url' => urldecode($url) . '&signature=' . $sig
            );
            
            parse_str(urldecode($url));
            $data['expires'] = date("G:i:s T", $expire);
            $data['ipbits'] = $ipbits;
            $data['ip'] = $ip;
            $availFormats[] = $data;
        }
        return $availFormats;
    }

    /**
     * Get video info
     * @param string $videoid
     * @return string
     */
    public function getInfo($videoid) {
        if ($videoid) {
            if (preg_match('/^https:\/\/w{3}?.youtube.com\//', $videoid)) {
                $url = parse_url($videoid);
                $videoid = null;
                if (is_array($url) && count($url) > 0 && isset($url['query']) && !empty($url['query'])) {
                    $parts = explode('&', $url['query']);
                    if (is_array($parts) && count($parts) > 0) {
                        foreach ($parts as $p) {
                            $pattern = '/^v\=/';
                            if (preg_match($pattern, $p)) {
                                $videoid = preg_replace($pattern, '', $p);
                                break;
                            }
                        }
                    }
                    if (!$videoid) {
                        return self::ERROR_NO_VIDEO_ID;
                    }
                } else {
                    return self::ERROR_INVALID_URL;
                }
            } elseif (preg_match('/^https?:\/\/youtu.be/', $videoid)) {
                $url = parse_url($videoid);
                $videoid = NULL;
                $videoid = preg_replace('/^\//', '', $url['path']);
            }
        } else {
            return self::ERROR_NO_VIDEO_ID;
        }
        $videoInfo = 'http://www.youtube.com/get_video_info?&video_id=' . $videoid . '&asv=3&el=detailpage&hl=en_US'; //video details fix *1
        $videoInfo = $this->curl->curlGet($videoInfo);
        return $videoInfo;
    }

}
