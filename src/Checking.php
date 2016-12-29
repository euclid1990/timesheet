<?php

namespace Src;

use Src\Google;
use Carbon\Carbon;
use Illuminate\Support\Collection;

define('TIMESHEET', __DIR__ . '/../credentials/timesheet.json');

class Checking {

    const SHEET_TAB_NAME = "%s!";

    protected $spreadsheet;
    protected $spreadsheetId;
    protected $sheetTabName;
    protected $dt;
    protected $staffs;
    protected $rangeStaffs = "A3:C";
    protected $workingDates;
    protected $rangeWorkingDates = "D1:AZ3";
    protected $workingDateOriginalFormat = "d/m/Y";
    protected $workingDateFormat = "Y-m-d";
    protected $rangeWorked = "";

    public function __construct() {
        $google = new Google();
        $this->spreadsheet = $google->getServiceSheets();
        $timesheet = json_decode(file_get_contents(TIMESHEET), true);
        $this->spreadsheetId = $timesheet["id"];
        $this->dt = Carbon::now();
        $this->setSheetTabName();
    }

    public function setSheetTabName($name = "")
    {
        $sheetNames = [
            "2016-12-26" => "Jan2017",
            "2017-02-02" => "Feb2017",
            // 3 => "Mar",
            // 4 => "Apr",
            // 5 => "May",
            // 6 => "Jun",
            // 7 => "Jul",
            // 8 => "Aug",
            // 9 => "Sep",
            // 10 => "Oct",
            // 11 => "Nov",
            // 12 => "Dec"
        ];
        if (!empty($name)) {
            return $this->sheetTabName = $name;
        }
        $sheetName = array_values($sheetNames)[0];
        foreach ($sheetNames as $date => $val) {
            $startOfMonth = Carbon::parse($date);
            if ($this->dt->gte($startOfMonth)) {
                $sheetName = $val;
            }
        }
        return $this->sheetTabName = sprintf(self::SHEET_TAB_NAME, $sheetName);
    }

    public function getRangeName($range)
    {
        return "{$this->sheetTabName}$range";
    }

    public function getValues($rangeType)
    {
        $range = $this->getRangeName($rangeType);
        $response = $this->spreadsheet->spreadsheets_values->get($this->spreadsheetId, $range);
        return $response->getValues();
    }

    public function getAllStaffs()
    {
        $values = $this->getValues($this->rangeStaffs);
        if (empty($values)) {
            return new Collection([]);
        }
        $result = [];
        foreach ($values as $key => $row) {
            array_push($result, (object)[
                'number' => $row[0],
                'code' => strtolower($row[1]),
                'name' => $row[2],
                'row_num' => $row[0] + 2,
            ]);
        }
        unset($values);
        return new Collection($result);
    }

    public function findByCode($code)
    {
        if (is_array($code)) {
            $code = array_map("strtolower", $code);
            return $this->staffs->whereIn('code', $code);
        }
        $code = strtolower($code);
        return $this->staffs->where('code', $code);
    }

    public function createColumnArray()
    {
        $columns = range("D", "Z");
        foreach (range("A", "U") as $v) {
            $columns[] = "A$v";
        }
        return $columns;
    }

    public function getAllWorkingDates()
    {
        $values = $this->getValues($this->rangeWorkingDates);
        if (empty($values)) {
            return new Collection([]);
        }
        if (count($values) !== 3) {
            throw new Exception("Working date data is not enough.");
        }
        $columns = $this->createColumnArray();
        $result = [];
        $dates = $values[0];
        $timeInOut = $values[1];
        $lastestWorkingDate = $values[2];
        $mark = 0;
        foreach ($lastestWorkingDate as $key => $value) {
            if (trim($value) !== "NA") break;
            $mark = $key;
        }
        $this->rangeWorked = "{$columns[0]}%s:{$columns[$mark]}%s";
        foreach ($dates as $key => $date) {
            $date = trim(strtolower($date));
            if (in_array($date, ["late", "leave early", "total", "sum"])) break;
            if (empty($date)) continue;
            $date = Carbon::createFromFormat($this->workingDateOriginalFormat, $date)->toDateString($this->workingDateFormat);
            $result[$date] = (object)[
                "timein" => $columns[$key],
                "timeout" => $columns[$key + 1],
                "exist" => $key <= $mark ? true : false,
            ];
        }
        unset($values);
        return new Collection($result);
    }

    public function isInLateLeaveEarly($timein, $timeout)
    {
        $timein = explode(':', $timein);
        $timeout = explode(':', $timeout);
        if (count($timein) == 2) {
            $timeinH = (int)$timein[0];
            $timeinM = (int)$timein[1];
            if (($timeinH > 7) || (($timeinH == 7) && ($timeinM > 45))) return false;
        }
        if (count($timeout) == 2) {
            $timeoutH = (int)$timeout[0];
            $timeoutM = (int)$timeout[1];
            if (($timeoutH < 16) || (($timeoutH == 16) && ($timeoutM < 45))) return false;
        }
        return true;
    }

    public function check($workingDates, $workedDates)
    {
        $result = [];
        $i = 0;
        foreach ($workingDates as $key => $workingDate) {
            if (!$workingDate->exist) break;
            $timein = empty($workedDates[$i]) ? false : trim($workedDates[$i]);
            $timeout = empty($workedDates[$i + 1]) ? false : trim($workedDates[$i + 1]);

            if (!$timein || !$timeout) {
                $result[$key] = 'NG';
            } else {
                $result[$key] = $this->isInLateLeaveEarly($timein, $timeout) ? 'OK' : 'NG';
            }
            $i = $i + 2;
        }
        return $result;
    }

    public function exec($code)
    {
        $this->staffs = $this->getAllStaffs();
        $this->workingDates = $this->getAllWorkingDates();
        $fStaffs = $this->findByCode($code);
        $result = [];
        foreach ($fStaffs as $staff) {
            var_dump($staff);
            $sRange = sprintf($this->rangeWorked, $staff->row_num, $staff->row_num);
            $workedDates = $this->getValues($sRange);
            if (empty($workedDates)) {
                $values = "Not found working date";
            } else {
                $workedDates = $workedDates[0];
                $values = $this->check($this->workingDates, $workedDates);
            }
            $result[$staff->code] = [
                "values" => $values,
                "staff" => $staff,
            ];
        }
        return $result;
    }

}