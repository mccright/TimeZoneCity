<?php
/**
 * Time Zone City
 *
 * @version    2019-03-07 05:11:00 UTC
 * @author     Peter Kahl <https://github.com/peterkahl>
 * @copyright  2017-2019 Peter Kahl
 * @license    Apache License, Version 2.0
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      <http://www.apache.org/licenses/LICENSE-2.0>
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace peterkahl\TimeZoneCity;

use \DateTimeZone;
use \DateTime;
use \Exception;

class TimeZoneCity {

  /**
   * DB resource
   *
   */
  public $dbresource;

  /**
   * Array with cached abbreviations
   * Caching to speed up subsequent lookups.
   * @var array
   */
  private $cachedAbbr;

  #===================================================================

  /**
   * Constructor initialises the cache.
   *
   */
  public function __construct() {

    $this->cachedAbbr = array();

  }

  #===================================================================

  /**
   * Returns an array of timezones according to specified criteria.
   *
   * @param string  $sortby ........ Admissible values:
   *          -- 'time_zone'
   *          -- 'std_offset'
   *          -- 'dst_offset'
   *          -- 'place_name'
   *          -- 'country_code'
   *          -- 'country_name'
   *          -- 'latitude'
   *          -- 'longitude'
   *      OR multiple criteria separated by comma, example:
   *          -- 'offset,place_name'
   *
   * @param string  $sortdir ....... Admissible values:
   *          -- 'asc'
   *          -- 'desc'
   *      OR multiple directions separated by comma, example:
   *          -- 'desc,asc'
   *
   * @param string  $onlycountry ... 2-letter country code, OR multiple
   *                                 codes separated by comma
   *      Examples:
   *          -- 'us'
   *          -- 'us,ca,mx'
   *          -- 'de,cz,sk,at,pl,fr,dk,be,nl,it,es,pt,ch,se,no,fi'
   *          -- '' (empty - no country limitation)
   *
   * @return mixed
   * @throws \Exception
   */
  public function GetAllZones($sortby = 'std_offset,place_name', $sortdir = 'asc,asc', $onlycountry = '') {

    $sortdir = strtoupper($sortdir);
    $validSortdir = array(
      'ASC',
      'DESC',
    );
    $sortdirArr = explode(',', $sortdir);
    foreach ($sortdirArr as $k => $v) {
      if (!in_array($v, $validSortdir)) {
        throw new Exception('Illegal value argument sortdir');
      }
    }

    $sortby = strtolower($sortby);
    $validSortby = array(
      'time_zone',
      'std_offset',
      'dst_offset',
      'place_name',
      'country_code',
      'country_name',
      'latitude',
      'longitude',
    );
    $sortbyArr = explode(',', $sortby);
    foreach ($sortbyArr as $k => $v) {
      if (!in_array($v, $validSortby)) {
        throw new Exception('Illegal value argument sortby');
      }
    }

    $sortbyStr = '';
    foreach ($sortbyArr as $k => $v) {
      if (!isset($sortdirArr[$k])) {
        $sortdirArr[$k] = 'ASC';
      }
      $sortbyStr .= ', `'. $v .'` '. $sortdirArr[$k];
    }
    $sortbyStr = trim($sortbyStr, ', ');

    if (!is_string($onlycountry)) {
      throw new Exception('Argument onlycountry must be a string');
    }

    if (!empty($onlycountry)) {
      $onlyArr = explode(',', strtoupper($onlycountry));
      foreach ($onlyArr as $k => $v) {
        if (strlen($v) != 2) {
          throw new Exception('Illegal value argument onlycountry');
        }
        $onlyArr[$k] = "`country_code`='". mysqli_real_escape_string($this->dbresource, $v) ."'";
      }
      $onlyStr = implode(' OR ', $onlyArr);
    }

    if (!empty($onlycountry)) {
      $sql = "SELECT * FROM `timezonecity` WHERE ". $onlyStr ." ORDER BY ". $sortbyStr .";";
    }
    else {
      $sql = "SELECT * FROM `timezonecity` ORDER BY ". $sortbyStr .";";
    }

    $result = mysqli_query($this->dbresource, $sql);

    if ($result === false) {
      throw new Exception('Error executing SQL query');
    }

    $arr = array();
    $n = 0;
    while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
      $arr[$n] = $row;
      $arr[$n]['offset_formatted'] = $this->GetZoneOffset($arr[$n]['time_zone'], 'O');
      $n++;
    }
    return $arr;
  }

  #===================================================================

  /**
   * Validates a timezone.
   * @param  string  $zone
   * @return mixed
   * @throws \Exception
   */
  public function ValidZone($zone) {
    $sql = "SELECT 1 FROM `timezonecity` WHERE `time_zone`='". mysqli_real_escape_string($this->dbresource, $zone) ."' LIMIT 1;";
    $result = mysqli_query($this->dbresource, $sql);
    if ($result === false) {
      throw new Exception('Error executing SQL query');
    }
    if (mysqli_num_rows($result) > 0) {
      return true;
    }
    return false;
  }

  #===================================================================

  /**
   * Returns all information on requested timezone (the whole row).
   * @param  string  $zone
   * @return mixed
   * @throws \Exception
   */
  public function GetZoneInfo($zone) {

    $sql = "SELECT * FROM `timezonecity` WHERE `time_zone`='". mysqli_real_escape_string($this->dbresource, $zone) ."' LIMIT 1;";

    $result = mysqli_query($this->dbresource, $sql);

    if ($result === false) {
      throw new Exception('Error executing SQL query');
    }

    if (mysqli_num_rows($result) > 0) {
      $arr = mysqli_fetch_array($result, MYSQLI_ASSOC);
      return $arr;
    }

    return array();
  }

  #===================================================================

  /**
   * Returns nearest timezone data (incl. timezone db name) for
   * given country, longitude & latitude.
   * @param  mixed   $country ..... 2-letter country code, e.g. 'FR', or boolean false
   * @param  float   $lat  $long
   * @return array   .............. array (the whole row)
   * @throws \Exception
   */
  public function GetNearestZone($country, $lat, $long) {

    if (empty($lat) || empty($long)) {
      throw new Exception('Arguments lat, long cannot be empty');
    }

    if (!empty($country) && strlen($country) == 2) {

      $sql = "SELECT * FROM `timezonecity` WHERE `country_code`='". mysqli_real_escape_string($this->dbresource, strtoupper($country)) ."' AND ABS(`longitude` - '". mysqli_real_escape_string($this->dbresource, $long) ."')<'15' ORDER BY ABS(`longitude` - '". mysqli_real_escape_string($this->dbresource, $long) ."') LIMIT 1;";
      $result = mysqli_query($this->dbresource, $sql);

      if ($result === false) {
        throw new Exception('Error executing SQL query');
      }

      if (mysqli_num_rows($result) > 0) {
        return mysqli_fetch_array($result, MYSQLI_ASSOC);
      }
    }

    # Something was wrong with the country code. Now, we use only coordinates.
    $sql = "SELECT * FROM `timezonecity` ORDER BY ABS(`longitude` - '". mysqli_real_escape_string($this->dbresource, $long) ."'), ABS(`latitude` - '". mysqli_real_escape_string($this->dbresource, $lat) ."') LIMIT 1;";
    $result = mysqli_query($this->dbresource, $sql);

    if ($result === false) {
      throw new Exception('Error executing SQL query');
    }

    if (mysqli_num_rows($result) > 0) {
      return mysqli_fetch_array($result, MYSQLI_ASSOC);
    }

    throw new Exception('Failed to locate nearest timezone data');
  }

  #===================================================================

  /**
   * Returns time zone abbreviation based on DST status.
   * @param  string      $zone .... ex. 'Atlantic/Azores'
   * @return string ............... ex. 'AZOT'
   * @throws \Exception
   */
  public function GetZoneAbbr($zone) {

    $dst = $this->GetDST($zone);

    if (!empty($this->cachedAbbr) && array_key_exists($zone . $dst, $this->cachedAbbr)) {
      return $this->cachedAbbr[$zone . $dst];
    }

    $sql = "SELECT `std_abbr`,`dst_abbr` FROM `timezonecity` WHERE `time_zone`='". mysqli_real_escape_string($this->dbresource, $zone) ."';";

    $result = mysqli_query($this->dbresource, $sql);

    if ($result === false) {
      throw new Exception('Error executing SQL query');
    }

    if (mysqli_num_rows($result) > 0) {
      $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
      if ($dst) {
        $this->cachedAbbr[$zone . $dst] = $row['dst_abbr'];
        return $row['dst_abbr'];
      }
      $this->cachedAbbr[$zone . $dst] = $row['std_abbr'];
      return $row['std_abbr'];
    }

    return 'ERROR';
  }

  #===================================================================

  /**
   * Returns offset from UTC for given timezone (right now).
   * This includes DST (if observed).
   * @param  string   $zone
   * @param  string   $format
   * @return mixed
   */
  private static function GetZoneOffset($zone, $format = 'Z') {
    $DateObj = new DateTime('now');
    $DateObj->setTimeZone(new DateTimeZone($zone));
    return $DateObj->format($format);
  }

  #===================================================================

  /**
   * Tells whether DST is in effect (right now or for given epoch).
   * @param   string    $zone ... e.g. 'Atlantic/Azores'
   * @param   integer   $epoch .... optional (defaults to current epoch)
   * @return  string    '0' or '1'
   * @throws  \Exception
   */
  private function GetDST($zone, $epoch = 'now') {

    if (!empty($epoch) && is_string($epoch) && $epoch == 'now') {
      $DateObj = new DateTime('now');
    }
    elseif (!empty($epoch) && is_numeric($epoch)) {
      $epoch = (integer) $epoch;
      $gmtDateStr = gmdate('Y-m-d H:i:s', $epoch);
      $DateObj = new DateTime($gmtDateStr);
    }
    else {
      throw new Exception('Illegal value argument epoch');
    }

    $DateObj->setTimeZone(new DateTimeZone($zone));

    return $DateObj->format('I');
  }

  #===================================================================

  /**
   * Removes accents. Makes foreign words look more friendly.
   * @param  string $str
   * @return string
   */
  public function RemoveAccents($str) {
    $a = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ');
    $b = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o');
    return str_replace($a, $b, $str);
  }

  #===================================================================
}
