<?php
/**
 * Time Zone City
 * Everything you need for working with timezones and world time.
 *
 * @version    0.7 (2017-07-24 02:19:00 GMT)
 * @author     Peter Kahl <peter.kahl@colossalmind.com>
 * @copyright  2017 Peter Kahl
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

  public $dbresource;

  #===================================================================

  /**
   * Returns the whole array according to specified sort criteria.
   *
   * @var sortby ......... string
   *      Admissible values:
   *          -- 'time_zone'
   *          -- 'offset'
   *          -- 'place_name'
   *          -- 'place_id'
   *          -- 'region_code'
   *          -- 'region_name'
   *          -- 'country_code'
   *          -- 'country_name'
   *          -- 'latitude'
   *          -- 'longitude'
   *      OR multiple codes separated by comma
   *
   * @var sortdir ........ string
   *      Admissible values:
   *          -- 'asc'
   *          -- 'desc'
   *
   * @var onlycountry .... string
   *      2-letter country code, OR multiple codes separated by comma
   *          -- 'us'
   *          -- 'us,ca,mx'
   *          -- '' (empty - no country limitation)
   */
  public function GetAllZones($sortby = 'offset,place_name', $sortdir = 'asc', $onlycountry = '') {
    $sortby = strtolower($sortby);
    $validSortby = array(
      'time_zone',
      'offset',
      'place_name',
      'place_id',
      'region_code',
      'region_name',
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
      $sortbyArr[$k] = "`". mysqli_real_escape_string($this->dbresource, $v) ."`";
    }
    $sortbyStr = implode(', ', $sortbyArr);
    #----
    $sortdir = strtoupper($sortdir);
    $validSortdir = array(
      'ASC',
      'DESC',
    );
    if (!in_array($sortdir, $validSortdir)) {
      throw new Exception('Illegal value argument sortdir');
    }
    if (!is_string($onlycountry)) {
      throw new Exception('Argument onlycountry must be a string');
    }
    #------------------------------------------------------
    if (!empty($onlycountry)) {
      $onlyArr = explode(',', $onlycountry);
      foreach ($onlyArr as $k => $v) {
        if (strlen($v) != 2) {
          throw new Exception('Illegal value argument onlycountry');
        }
        $onlyArr[$k] = "`country_code`='". mysqli_real_escape_string($this->dbresource, strtoupper($v)) ."'";
      }
      $onlyStr = implode(' OR ', $onlyArr);
      $sql = "SELECT * FROM `timezonecity` WHERE ". $onlyStr ." ORDER BY ". $sortbyStr ." ". mysqli_real_escape_string($this->dbresource, $sortdir) .";";
    }
    else {
      $sql = "SELECT * FROM `timezonecity` ORDER BY ". $sortbyStr ." ". mysqli_real_escape_string($this->dbresource, $sortdir) .";";
    }
    $result = mysqli_query($this->dbresource, $sql);
    if ($result === false) {
      throw new Exception('Error executing SQL query');
    }
    $arr = array();
    while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
      $arr[] = $row;
    }
    return $arr;
  }

  #===================================================================

  /**
   * Validates a timezone.
   * @var string
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
   * @var string
   */
  public function GetZoneInfo($zone) {
    $sql = "SELECT * FROM `timezonecity` WHERE `time_zone`='". mysqli_real_escape_string($this->dbresource, $zone) ."' LIMIT 1;";
    $result = mysqli_query($this->dbresource, $sql);
    if ($result === false) {
      throw new Exception('Error executing SQL query');
    }
    if (mysqli_num_rows($result) > 0) {
      return mysqli_fetch_array($result, MYSQLI_ASSOC);
    }
    return array();
  }

  #===================================================================

  /**
   * Returns nearest timezone for given country, longitude, latitude.
   * @var country ....... string (2-letter country code)
   * @var lat ........... float (latitude)
   * @var long .......... float (longitude)
   */
  public function GetNearestZone($country, $lat, $long) {
    if (!empty($country)) {
      $sql = "SELECT `time_zone` FROM `timezonecity` WHERE `country_code`='". mysqli_real_escape_string($this->dbresource, strtoupper($country)) ."' AND ABS(`longitude` - '". mysqli_real_escape_string($this->dbresource, $long) ."')<'15' ORDER BY ABS(`longitude` - '". mysqli_real_escape_string($this->dbresource, $long) ."') LIMIT 1;";
      $result = mysqli_query($this->dbresource, $sql);
      if ($result === false) {
        throw new Exception('Error executing SQL query');
      }
      if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
        return $row['time_zone'];
      }
    }
    # Something was wrong with the country code. Now, we use only coordinates.
    $sql = "SELECT `time_zone` FROM `timezonecity` ORDER BY ABS(`longitude` - '". mysqli_real_escape_string($this->dbresource, $long) ."'), ABS(`latitude` - '". mysqli_real_escape_string($this->dbresource, $lat) ."') LIMIT 1;";
    $result = mysqli_query($this->dbresource, $sql);
    if ($result === false) {
      throw new Exception('Error executing SQL query');
    }
    if (mysqli_num_rows($result) > 0) {
      $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
      return $row['time_zone'];
    }
    throw new Exception('Failed to determime nearest timezone');
  }

  #===================================================================

  /**
   * Calculates offset from GMT for given timezone.
   * This includes DST (if observed).
   */
  public function GetZoneOffset($zone) {
    $remote_dtz = new DateTimeZone($zone);
    $remote_dt = new DateTime("now", $remote_dtz);
    return $remote_dtz->getOffset($remote_dt);
  }

  #===================================================================

  /**
   * Tells whether a timezone is using daylight savings.
   * @author hertzel Armengol <emudojo @ gmail.com>
   */
  public function ZoneDoesDST($zone) {
    $tz = new DateTimeZone($zone);
    $date = new DateTime('now', $tz);
    $trans = $tz->getTransitions();
    foreach ($trans as $k => $t) {
      if ($t["ts"] > $date->format('U')) {
        return $trans[$k-1]['isdst'];
      }
    }
  }

  #===================================================================

  public function RemoveAccents($str) {
    $a = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ');
    $b = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o');
    return str_replace($a, $b, $str);
  }

  #===================================================================
}
