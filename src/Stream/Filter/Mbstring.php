<?php
  /**
   * Stream_Filter_Mbstring - mbstringを使って文字列変換を行うstream filter
   *
   * @package  Stream_Filter_Mbstring
   * @author  Yoshio HANAWA <hanawa@dino.co.jp>
   * @copyright  2009 Yoshio HANAWA
   * @license  http://www.php.net/license/3_01.txt  The PHP License, version 3.01
   * @link  http://openpear.org/package/Stream_Filter_Mbstring
   */

class Stream_Filter_Mbstring  extends php_user_filter
{
  private $remains;
  private $convert_func;
  private $convert_args = array();
  private $from_encoding;

  function onCreate() {
    if (preg_match('/^convert\.mbstring\.kana\.([A-z]*)(?:\:([-\w]+))?$/',
                   $this->filtername, $matches)) {
      $this->convert_func = "mb_convert_kana";
      $convert_option = $matches[1];
      if ($matches[2]) {
        $this->from_encoding = $matches[2];
      } else {
        $this->from_encoding = "auto";
      }
      $this->convert_args = array("", $convert_option, $this->from_encoding);
    } elseif (preg_match('/^convert\.mbstring(?:\.encoding)?(?:\.([-\w]+)\:([-\w]+))?$/',
                         $this->filtername, $matches)) {
      $this->convert_func = "mb_convert_encoding";
      if ($matches[1] && $matches[2]) {
        $this->from_encoding = $matches[1];
        $to_encoding = $matches[2];
      } else {
        $this->from_encoding = "auto";
        $to_encoding = mb_internal_encoding();
      }
      $this->convert_args = array("", $to_encoding, $this->from_encoding);
    } else {
      /* その他の convert.mbstring.* フィルタが問い合わせられた場合は
         失敗を報告し、PHP が検索を続けられるようにする */
      return false;
    }
    if (call_user_func_array($this->convert_func, $this->convert_args)
        === false) {
      // エンコーディングの指定が間違っていたらフィルタを登録しない
      $this->from_encoding = "";
      $this->convert_func = "";
      $this->convert_args = array();
      return false;
    }
    return true;
  }

  /**
   * This method is called on fwrite, fread, etc
   *
   * @param bucket_brigade $in
   * @param bucket_brigade $out
   * @param integer $consumed
   * @param boolean $closing
   * @return integer PSFS_* constants
   */
  function filter($in, $out, &$consumed, $closing)
  {
    while ($bucket = stream_bucket_make_writeable($in)) {
      $buffered_data = $this->remains . $bucket->data;

      // マルチバイト文字として半端なバイト列があれば除去する。
      // "\0..."はmb_strcutのバグ回避のための番兵。
      $data = mb_strcut($buffered_data. "\0\0\0\0\0", 0, strlen($buffered_data),
                        $this->from_encoding);
      if ($data === $buffered_data) {
        $remains = "";
      } else {
        $remains = substr($buffered_data, strlen($data));
      }
      if (strlen($data) === 0) {
        // 有り得ないとは思うが、唯一処理するものが無いパターン
        $this->remains = $remains;
        return PSFS_FEED_ME;
      }
      $this->remains = $remains;
      $consumed += strlen($data);
      $call_args = $this->convert_args;
      $call_args[0] = $data;
      $bucket->data = call_user_func_array($this->convert_func, $call_args);
      stream_bucket_append($out, $bucket);
    }

    // 最後のbucketを処理し終わった。万一残りがあるようならflushする。
    if ($closing && strlen($this->remains) > 0){
      $call_args = $this->convert_args;
      $call_args[0] = $this->remains;
      $this->remains = call_user_func_array($this->convert_func, $call_args);
      $bucket = stream_bucket_new($this->stream, $this->remains);
      stream_bucket_append($out, $bucket);
    }

    // pass on to the next filter
    return PSFS_PASS_ON;
  }
}
