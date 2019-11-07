<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 8/15/2018
 * Time: 1:50 PM
 */

namespace Vanderbilt\DashboardNotificationsExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;
use mysql_xdevapi\Exception;

class DashboardNotificationsExternalModule extends AbstractExternalModule
{
    private $notificationProject;
    const PROJ_PROD_SETTING = "project_production";
    const FORM_NAME_SETTING = "form_names";
    const FORM_FIELD_SETTING = "forms_field";
    const FIELD_NAME_SETTING = "field_names";
    const FIELD_VALUE_REQUIRED = "field_names_value";
    const FIELD_VALUE_SETTING = "field_value";
    const USER_NEW_SETTING = "user_new";
    const USER_EDIT_SETTING = "user_edit";
    const DISPLAY_DATE_SETTING = "display_date";
    const DISPLAY_TIMEFRAME = "display_timeframe";
    const PASTDUE_SETTING = "past_due";
    const PASTDUE_TIMEFRAME = "past_timeframe";
    const UNIQUE_USER_SETTING = "unique_user";
    const RECORD_COUNT_SETTING = "record_count";

    const SCHEDULE_START_DATE = "schedule_start";
    const START_TIMEFRAME = "start_timeframe";
    const SCHEDULE_END_DATE = "schedule_end";
    const END_TIMEFRAME = "end_timeframe";
    const SCHEDULE_COUNT = "schedule_count";
    const SCHEDULE_TIMEFRAME = "schedule_timeframe";

    const ROLES_LIST = "roles_list";
    const ROLES_FIELDS = "roles_fields";
    const ROLES_RESOLVE = "resolve";
    const ROLES_RECEIVE = "receive";

    const BASE_URL = APP_PATH_WEBROOT_FULL."redcap_".REDCAP_VERSION;

    private $notificationTypes = [
        "Create record"                     => [0, 5, 6, 7],
        "Create survey response"            => [0,5,6,7],
        "Update record"                     => [5, 6, 7],
        "Create data collection instrument" => [1],
        "Create project field"              => [2],
        "Add field comment"                 => [3],
        "Verified data value"               => [3],
        "Edit user"                         => [4],
        "Add user"                          => [4],
        "Save e-signature"                  => [8]
    ];
    private $eventTypes = ['UPDATE','INSERT','DELETE','SELECT','OTHER','DATA_EXPORT','MANAGE','LOCK_RECORD','ESIGNATURE'];

    function hook_every_page_top($project_id)
    {
        $notificationProject = $this->getProjectSetting('notif-project');

        /*$projects = $this->framework->getProjectsWithModuleEnabled();
        echo "<pre>";
        print_r($projects);
        echo "</pre>";*/
        /*$project = new \Project($project_id);
        $projectEvent = $project->firstEventId;
        $recordData = \REDCap::getData($project_id, 'array', array(), array(), array(), array(), false, false, false);
        $testDate = $this->processDateLogic("[today_date] + 15",$recordData,"weeks");*/
        //$this->scheduledNotifications();
        if ($notificationProject) {
            $this->notificationProject = new \Project($notificationProject);

            $project = new \Project($project_id);

            $lastEvent = $this->getProjectSetting('lastEvent') ? $this->getProjectSetting('lastEvent') : 0;

            $lastEvent = $this->getLogs($project, $lastEvent);

            $this->disableUserBasedSettingPermissions();
            $this->setProjectSetting('lastEvent', $lastEvent);
        }
    }

    /*function redcap_module_link_check_display($project_id, $link) {
        if(\REDCap::getUserRights(USERID)[USERID]['design'] == '1'){
            return $link;
        }
        return null;
    }*/

    function getNotifications($projectID,$sourceProjectID = "") {
        if (is_numeric($sourceProjectID)) {
            $returnData = \REDCap::getData($projectID, 'array', "", array(), array(), array(), false, false, false, "([" . $this->getProjectSetting('project-field') . "] = '$sourceProjectID')");
        }
        else {
            $returnData = \REDCap::getData($projectID, 'array', "", array(), array(), array(), false, false, false);
        }
        return $returnData;
    }

    function processDateLogic($string,$recordData,$timeframe)
    {
        // No provided date logic means don't bother with this
        if ($string === "") {
            return "";
        }

        // We need a valid timeframe. Will need to check later to make sure that the rest of the string is actually valid
        if ($timeframe == "") {
            return "";
        }
        $valid = true;
        // If timeframe isn't days, weeks, or months then it is invalid
        if (isset($recordData[$timeframe]) && $recordData[$timeframe] != "") {
            $timeframe = $recordData[$timeframe];
        }
        if (!in_array(strtolower($timeframe),array("days","weeks","months"))) return "";

        if (is_numeric($string) && $string !== "") {
            return date("Y-m-d", strtotime(" + $string ".strtolower($timeframe)));
        }

        list($stringsToReplace,$fieldNamesReplace,$operators,$daysAdd) = $this->parseLogicString($string);

        // If all the data arrays are somehow empty, that is invalid
        if (empty($stringsToReplace) && empty($fieldNamesReplace) && empty($daysAdd)) return "";
        // If there is a field to replace, and that field is not at the front end of the logic string, it doesn't make any sense, so return
        if (isset($stringsToReplace[0]) && $stringsToReplace[0] != "" && $stringsToReplace[0] != $daysAdd[0]) return "";

        /*echo "<pre>";
        print_r($stringsToReplace);
        echo "</pre>";
        echo "<pre>";
        print_r($fieldNamesReplace);
        echo "</pre>";
        echo "<pre>";
        print_r($operators);
        echo "</pre>";
        echo "<pre>";
        print_r($daysAdd);
        echo "</pre>";*/
        $date = "";

        $startIndex = 0;
        if (isset($fieldNamesReplace[0])) {
            $date = $recordData[$fieldNamesReplace[0]];
            $startIndex = 1;
        }
        elseif ($this->validateDate($daysAdd[0],'Y/m/d')) {
            $date = date('Y-m-d', strtotime(str_replace('/','-',$daysAdd[0])));
            $startIndex = 1;
        }
        else {
            $date = date('Y-m-d');
        }

        /*echo "Date: ".$date."<br/>";*/
        if ($date != "" && $this->validateDate($date)) {

            $dateTime = date_create($date);
            while ($startIndex < count($daysAdd)) {
                /*echo "operator: ".$operators[0][$startIndex-1].", days: ".$daysAdd[$startIndex].", timeframe: $timeframe<br/>";*/
                date_modify($dateTime,$operators[0][$startIndex-1].' '.$daysAdd[$startIndex].' '.$timeframe);
                /*foreach ($daysAdd as $dIndex => $dayAdd) {
                    date_modify($dateTime,$matches[0][$dIndex].' '.$dayAdd.' '.$timeframe);
                }*/
                $startIndex++;
            }
        } else {
            return "";
        }

        return $dateTime->format("Y-m-d");
        //return '';
    }

    function processIntervalString($string,$recordData,$timeframe) {
        // No provided date logic means don't bother with this
        if ($string === "") {
            return "";
        }

        // We need a valid timeframe. Will need to check later to make sure that the rest of the string is actually valid
        if ($timeframe == "") {
            return "";
        }
        $valid = true;
        // If timeframe isn't days, weeks, or months then it is invalid
        if (isset($recordData[$timeframe]) && $recordData[$timeframe] != "") {
            $timeframe = $recordData[$timeframe];
        }
        $timeframe = strtolower($timeframe);
        if (!in_array($timeframe,array("days","weeks","months"))) return "";

        if (is_numeric($string) && $string !== "") {
            return " + $string ".strtolower($timeframe);
        }

        list($stringsToReplace,$fieldNamesReplace,$operators,$daysAdd) = $this->parseLogicString($string);

        // If all the data arrays are somehow empty, that is invalid
        if (empty($stringsToReplace) && empty($fieldNamesReplace) && empty($daysAdd)) return "";

        /*echo "<pre>";
        print_r($stringsToReplace);
        echo "</pre>";
        echo "<pre>";
        print_r($fieldNamesReplace);
        echo "</pre>";
        echo "<pre>";
        print_r($operators);
        echo "</pre>";
        echo "<pre>";
        print_r($daysAdd);
        echo "</pre>";*/
        $interval = "";

        foreach ($daysAdd as $index => $value) {
            $checkValue = "";
            if (false !== $key = array_search($value,$stringsToReplace)) {
                if (is_numeric($recordData[$fieldNamesReplace[$key]])) {
                    $checkValue = $recordData[$fieldNamesReplace[$key]];
                }
            }
            elseif (is_numeric($value)) {
                $checkValue = $value;
            }
            if ($index === "0") {
                $interval = $checkValue;
            }
            else {
                if (isset($operators[$index - 1])) {
                    $interval = $this->processOperator($interval,$operators[$index - 1],$checkValue);
                }
            }
        }

        $prepend = "+";
        if (intval($interval) < 0) {
            $prepend = "-";
        }
        return $prepend.$interval." ".$timeframe;
        //return '';
    }

    function processOperator($interval, $operator, $operatee) {
        if (!is_numeric($interval) || !is_numeric($operatee)) return $interval;
        switch ($operator) {
            case "+":
                return intval($interval) + intval($operatee);
                break;
            case "-":
                return intval($interval) + intval($operatee);
                break;
            case "*":
                return intval($interval) * intval($operatee);
                break;
            case "/":
                return intval($interval) / intval($operatee);
                break;
        }
        return $interval;
    }

    function parseLogicString($string) {
        preg_match_all("/\[(.*?)\]/", $string, $matchRegEx);
        preg_match_all('/[+*\/-]/', $string, $matches);
        $stringsToReplace = $matchRegEx[0];
        $fieldNamesReplace = $matchRegEx[1];

        if (count($fieldNamesReplace[0]) > 1 || count($stringsToReplace[0]) > 1) return array();

        $daysAdd = array();
        if (isset($matches[0]) && !empty($matches[0])) {
            $lastPosition = 0;
            foreach ($matches[0] as $index => $operator) {
                $thisPosition = strpos($string, $matches[0][$index],$lastPosition);
                if ($index == 0) {
                    $daysAdd[$index] = trim(substr($string,0,$thisPosition));
                } else {
                    $daysAdd[$index] = trim(substr($string,$lastPosition + 1,($thisPosition - ($lastPosition + 1))));
                }
                $lastPosition = $thisPosition;
            }
            if ($lastPosition != "") {
                $daysAdd[] = trim(substr($string,$lastPosition + 1));
            }
        }
        else {
            $daysAdd[] = $string;
        }

        return array($stringsToReplace,$fieldNamesReplace,$matches,$daysAdd);
    }

    function validateDate($date,$format = 'Y-m-d') {
        $d = \DateTime::createFromFormat($format, $date);
        // The Y ( 4 digits year ) returns TRUE for any integer with any number of digits so changing the comparison from == to === fixes the issue.
        return $d && $d->format($format) === $date;
    }

    function returnAsArray($possibleArray) {
        return array_map('db_real_escape_string', (is_array($possibleArray) ? $possibleArray : ($possibleArray != "" ? array($possibleArray) : array())));
    }

    function getAutoId($projectId,$eventId = "")
    {
        $inTransaction = false;
        try {
            @db_query("BEGIN");
        }
        catch (Exception $e) {
            $inTransaction = true;
        }

        ### Get a new Auto ID for the given project ###
        $sql = "SELECT DISTINCT record
			FROM redcap_data
			WHERE project_id = $projectId
				AND field_name = 'record_id'
				AND value REGEXP '^[0-9]+$'
			ORDER BY abs(record) DESC
			LIMIT 1";

        $newParticipantId = db_result(db_query($sql),0);
        if ($newParticipantId == "") $newParticipantId = 0;
        $newParticipantId++;

        $sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value) VALUES
			({$projectId},{$eventId},'$newParticipantId','record_id','$newParticipantId')";

        db_query($sql);
        @db_query("COMMIT");
        $logSql = $sql;

        # Verify the new auto ID hasn't been duplicated
        $sql = "SELECT d.field_name
			FROM redcap_data d
			WHERE d.project_id = {$projectId}
				AND d.record = '$newParticipantId'";

        $result = db_query($sql);

        while(db_num_rows($result) > 1) {
            # Delete, increment by a random integer and attempt to re-create the record
            $sql = "DELETE FROM redcap_data
				WHERE d.project_id = $projectId
					AND d.record = '$newParticipantId'
					AND d.field_name = 'record_id'
				LIMIT 1";

            db_query($sql);

            $newParticipantId += rand(1,10);

            @db_query("BEGIN");

            $sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value) VALUES
				({$projectId},{$eventId},'$newParticipantId','record_id','$newParticipantId')";
            $logSql = $sql;

            db_query($sql);
            @db_query("COMMIT");

            $sql = "SELECT d.field_name
				FROM redcap_data d
				WHERE d.project_id = {$projectId}
					AND d.record = '$newParticipantId'";

            $result = db_query($sql);
        }

        \Logging::logEvent($logSql, $projectId, "INSERT", "redcap_data", $newParticipantId,"record_id='$newParticipantId'","Create Record");
        //logUpdate($logSql, $projectId, "INSERT", "redcap_data", $newParticipantId,"record_id='$newParticipantId'","Create Record");

        if($inTransaction) {
            @db_query("BEGIN");
        }
        // Return new auto id value
        return $newParticipantId;
    }

    function getProjectName($projectID) {
        $returnID = "";
        if (is_numeric($projectID)) {
            $sql = "SELECT app_title
                FROM redcap_projects
                WHERE project_id=$projectID";
            $returnID = db_result(db_query($sql),0);
        }
        return $returnID;
    }

    function getChoicesFromMetaData($choicesString) {
        if ($choicesString == "") return "";
        // 1) split by \n or "|" depending on which is used
        if(strpos($choicesString,'\n') !== false)
            $choicesArray1 = explode('\n', $choicesString);
        else
            $choicesArray1 = explode('|', $choicesString);

        // 2) split by ","
        $rawToLabel = array();
        foreach ($choicesArray1 as $keyCommaValue) {
            $separteKeyFromValue = explode(",", $keyCommaValue);
            $key = trim($separteKeyFromValue[0]);
            $value = trim($separteKeyFromValue[1]);
            $rawToLabel[$key] = $value;
        }
        return $rawToLabel;
    }

    function cleanArray($array) {
        foreach ($array as $key => $value) {
            $array[db_real_escape_string($key)] = db_real_escape_string($value);
        }
        return $array;
    }

    function processRequiredFieldValues($valuesRequired,$postdata) {
        $fieldValues = "";
        if (isset($valuesRequired)) {
            if (!empty($postdata)) {
                $fieldValues = $postdata;
            }
            else {
                $fieldValues = array(0=>null);
            }
        }
        else {
            $fieldValues = array();
        }
        return $fieldValues;
    }

    function saveNotifSettings($projectID,$notifProjectID,$postData) {
        $notifProject = new \Project($notifProjectID);
        /*echo "<pre>";
        print_r($postData);
        echo "</pre>";*/
        $overwrite = "overwrite";
        $recordID = "";
        if ($postData['record_id'] != "") {
            $recordID = db_real_escape_string($postData[$notifProject->table_pk]);
        }
        else {
            $recordID = $this->getAutoID($notifProjectID,$notifProject->firstEventId);
        }
        if ($recordID != "") {
            $saveData[$notifProject->table_pk] = $recordID;

            $notifType = db_real_escape_string($postData[$this->getProjectSetting("notif-type")]);
            $saveData[$this->getProjectSetting("project-field")] = $projectID;
            $saveData[$this->getProjectSetting("notif-name")] = db_real_escape_string($postData[$this->getProjectSetting("notif-name")]);
            $saveData[$this->getProjectSetting("notif-alert")] = db_real_escape_string($postData[$this->getProjectSetting("notif-alert")]);
            $saveData[$this->getProjectSetting("notif-class")] = db_real_escape_string($postData[$this->getProjectSetting("notif-class")]);
            $saveData[$this->getProjectSetting("notif-priority")] = db_real_escape_string($postData[$this->getProjectSetting("notif-priority")]);
            $saveData[$this->getProjectSetting("notif-type")] = $notifType;
            $saveData[$this->getProjectSetting("notif-active")] = db_real_escape_string($postData[$this->getProjectSetting("notif-active")]);
            //$saveData[$this->getProjectSetting("role-list")] = db_real_escape_string(implode(",",$postData[$this->ge@tProjectSetting("role-list")]));
            //$saveData[$this->getProjectSetting("role-resolve")] = db_real_escape_string(implode(",",$postData[$this->getProjectSetting("role-resolve")]));
            $saveData[$this->getProjectSetting("role-list")] = json_encode(array("roles"=>$this->cleanArray(array_values($postData[$this::ROLES_RECEIVE."_".$this::ROLES_LIST])),"fields"=>$this->cleanArray(array_values($postData[$this::ROLES_RECEIVE]))));
            $saveData[$this->getProjectSetting("role-resolve")] = json_encode(array("roles"=>$this->cleanArray(array_values($postData[$this::ROLES_RESOLVE."_".$this::ROLES_LIST])),"fields"=>$this->cleanArray(array_values($postData[$this::ROLES_RESOLVE]))));
            $notifSettings = array();
            $scheduleSettings = array();

            switch ($notifType) {
                case "0":
                    break;
                case "1":
                    $notifSettings[$this::PROJ_PROD_SETTING] = db_real_escape_string($postData[$this::PROJ_PROD_SETTING]);
                    break;
                case "2":
                    foreach ($postData[$this::FORM_NAME_SETTING] as $index => $formName) {
                        $notifSettings[$this::FORM_NAME_SETTING][$index] = db_real_escape_string($formName);
                    }
                    $notifSettings[$this::PROJ_PROD_SETTING] = db_real_escape_string($postData[$this::PROJ_PROD_SETTING]);
                    break;
                case "3":
                    foreach ($postData[$this::FIELD_NAME_SETTING] as $index => $fieldName) {
                        //$notifSettings[$this::FIELD_NAME_SETTING][$index] = db_real_escape_string($fieldName);
                        $notifSettings[$this::FIELD_NAME_SETTING][db_real_escape_string($fieldName)] = [];
                    }
                    break;
                case "4":
                    $notifSettings[$this::USER_NEW_SETTING] = db_real_escape_string($postData[$this::USER_NEW_SETTING]);
                    $notifSettings[$this::USER_EDIT_SETTING] = db_real_escape_string($postData[$this::USER_EDIT_SETTING]);
                    break;
                case "5":
                    foreach ($postData[$this::FIELD_NAME_SETTING] as $index => $fieldName) {
                        $fieldValues = $this::processRequiredFieldValues($postData[$this::FIELD_VALUE_REQUIRED."_".$index],$postData[$this::FIELD_VALUE_SETTING.'_'.$index]);
                        /*$notifSettings[$this::FIELD_NAME_SETTING][$index] = db_real_escape_string($fieldName);
                        $notifSettings[$this::FIELD_VALUE_SETTING][$index] = array_map('db_real_escape_string', (is_array($postData[$this::FIELD_VALUE_SETTING.'_'.$index]) ? $postData[$this::FIELD_VALUE_SETTING.'_'.$index] : array($postData[$this::FIELD_VALUE_SETTING.'_'.$index])));*/
                        //$notifSettings[$this::FIELD_NAME_SETTING][db_real_escape_string($fieldName)] = array_map('db_real_escape_string', (is_array($postData[$this::FIELD_VALUE_SETTING.'_'.$index]) ? $postData[$this::FIELD_VALUE_SETTING.'_'.$index] : array($postData[$this::FIELD_VALUE_SETTING.'_'.$index])));
                        $notifSettings[$this::FIELD_NAME_SETTING][db_real_escape_string($fieldName)] = $this->returnAsArray($fieldValues);
                    }
                    break;
                case "6":
                    foreach ($postData[$this::FIELD_NAME_SETTING] as $index => $fieldName) {
                        $fieldValues = $this::processRequiredFieldValues($postData[$this::FIELD_VALUE_REQUIRED."_".$index],$postData[$this::FIELD_VALUE_SETTING.'_'.$index]);
                        /*$notifSettings[$this::FIELD_NAME_SETTING][$index] = db_real_escape_string($fieldName);
                        $notifSettings[$this::FIELD_VALUE_SETTING][$index] = array_map('db_real_escape_string', (is_array($postData[$this::FIELD_VALUE_SETTING.'_'.$index]) ? $postData[$this::FIELD_VALUE_SETTING.'_'.$index] : array($postData[$this::FIELD_VALUE_SETTING.'_'.$index])));*/
                        //$notifSettings[$this::FIELD_NAME_SETTING][db_real_escape_string($fieldName)] = array_map('db_real_escape_string', (is_array($postData[$this::FIELD_VALUE_SETTING.'_'.$index]) ? $postData[$this::FIELD_VALUE_SETTING.'_'.$index] : array($postData[$this::FIELD_VALUE_SETTING.'_'.$index])));
                        $notifSettings[$this::FIELD_NAME_SETTING][db_real_escape_string($fieldName)] = $this->returnAsArray($fieldValues);
                    }
                    break;
                case "7":
                    foreach ($postData[$this::FIELD_NAME_SETTING] as $index => $fieldName) {
                        $fieldValues = $this::processRequiredFieldValues($postData[$this::FIELD_VALUE_REQUIRED."_".$index],$postData[$this::FIELD_VALUE_SETTING.'_'.$index]);
                        /*$notifSettings[$this::FIELD_NAME_SETTING][$index] = db_real_escape_string($fieldName);
                        $notifSettings[$this::FIELD_VALUE_SETTING][$index] = array_map('db_real_escape_string', (is_array($postData[$this::FIELD_VALUE_SETTING.'_'.$index]) ? $postData[$this::FIELD_VALUE_SETTING.'_'.$index] : array($postData[$this::FIELD_VALUE_SETTING.'_'.$index])));*/
                        //$notifSettings[$this::FIELD_NAME_SETTING][db_real_escape_string($fieldName)] = array_map('db_real_escape_string', (is_array($postData[$this::FIELD_VALUE_SETTING.'_'.$index]) ? $postData[$this::FIELD_VALUE_SETTING.'_'.$index] : array($postData[$this::FIELD_VALUE_SETTING.'_'.$index])));
                        $notifSettings[$this::FIELD_NAME_SETTING][db_real_escape_string($fieldName)] = $this->returnAsArray($fieldValues);
                    }
                    $notifSettings[$this::RECORD_COUNT_SETTING] = db_real_escape_string($postData[$this::RECORD_COUNT_SETTING]);
                    break;
                case "8":
                    foreach ($postData[$this::FORM_NAME_SETTING] as $index => $formName) {
                        $notifSettings[$this::FORM_NAME_SETTING][$index] = $formName;
                    }
                    break;
            }
            $notifSettings[$this::PASTDUE_SETTING] = db_real_escape_string($postData[$this::PASTDUE_SETTING]);
            $notifSettings[$this::UNIQUE_USER_SETTING] = db_real_escape_string($postData[$this::UNIQUE_USER_SETTING]);
            $notifSettings[$this::DISPLAY_DATE_SETTING] = db_real_escape_string($postData[$this::DISPLAY_DATE_SETTING]);
            $notifSettings[$this::DISPLAY_TIMEFRAME] = db_real_escape_string($postData[$this::DISPLAY_TIMEFRAME]);
            $notifSettings[$this::PASTDUE_TIMEFRAME] = db_real_escape_string($postData[$this::PASTDUE_TIMEFRAME]);
            //$initialNotifs = $notifSettings;
            $saveData[$this->getProjectSetting("access-json")] = json_encode($notifSettings);

            if (isset($postData[$this::SCHEDULE_START_DATE]) && $postData[$this::SCHEDULE_START_DATE] != "") {
                $scheduleSettings[$this::SCHEDULE_START_DATE] = db_real_escape_string($postData[$this::SCHEDULE_START_DATE]);
                $scheduleSettings[$this::START_TIMEFRAME] = db_real_escape_string($postData[$this::START_TIMEFRAME]);
            }
            if (isset($postData[$this::SCHEDULE_END_DATE]) && $postData[$this::SCHEDULE_END_DATE] != "") {
                $scheduleSettings[$this::SCHEDULE_END_DATE] = db_real_escape_string($postData[$this::SCHEDULE_END_DATE]);
                $scheduleSettings[$this::END_TIMEFRAME] = db_real_escape_string($postData[$this::END_TIMEFRAME]);
            }
            if (isset($postData[$this::SCHEDULE_COUNT]) && $postData[$this::SCHEDULE_COUNT] != "") {
                $scheduleSettings[$this::SCHEDULE_COUNT] = db_real_escape_string($postData[$this::SCHEDULE_COUNT]);
                $scheduleSettings[$this::SCHEDULE_TIMEFRAME] = db_real_escape_string($postData[$this::SCHEDULE_TIMEFRAME]);
            }
            if (!empty($scheduleSettings)) {
                $saveData[$this->getProjectSetting("schedule-json")] = json_encode($scheduleSettings);
            }
            else {
                $saveData[$this->getProjectSetting("schedule-json")] = "";
            }

            $recordsObject = new \Records;
            $saveResult = $recordsObject->saveData($notifProjectID, 'array', [$saveData[$notifProject->table_pk] => [$notifProject->firstEventId => $saveData]],$overwrite);
            if (!empty($saveResult['errors'])) {
                throw new \Exception("Error on creating notification: ".$saveResult['errors']);
            }
            if (method_exists($recordsObject,'addRecordToRecordListCache')) {
                $recordsObject->addRecordToRecordListCache($notifProjectID, $saveData[$notifProject->table_pk], $notifProject->firstArmNum);
            }
        }
    }

    function transferRoleIDsBetweenProjects($roleIDs,$newProjectID) {
        $returnIDs = array();
        $sql = "SELECT d2.role_id,d2.role_name,d2.project_id
            FROM redcap_user_roles d
            JOIN redcap_user_roles d2
              ON d.role_name=d2.role_name AND d2.project_id=$newProjectID
            WHERE d.role_id IN (".implode(',',$roleIDs).")";
        //echo "$sql<br/>";
        $result = db_query($sql);
        while ($row = db_fetch_assoc($result)) {
            $returnIDs[] = $row['role_id'];
        }
        return $returnIDs;
    }

    function getProjects($userID) {
        $returnString = array();
        $sql = "SELECT DISTINCT p.project_id, p.app_title
            FROM redcap_projects p
            WHERE p.project_id IN (SELECT projects.project_id
                FROM redcap_projects projects, redcap_user_rights users
                WHERE users.username = '$userID' and users.project_id = projects.project_id)
            ORDER BY p.project_id";
        //echo "$sql<br/>";
        $result = db_query($sql);
        while ($row = db_fetch_assoc($result)) {
            $returnString[$row['project_id']] = $row['app_title'];
        }
        return $returnString;
    }

    function getModuleID($prefix) {
        $id = "";
        $id = ExternalModules::getIdForPrefix($prefix);
        return $id;
    }

    function setModuleOnProject($destProjectID) {
        if (is_numeric($destProjectID)) {
            $this->disableUserBasedSettingPermissions();
            $externalModuleId = $this->getModuleID($this->PREFIX);
            $settingsResult = ExternalModules::getSettings(array($this->PREFIX), array($this->getProjectId()));
            while ($row = ExternalModules::validateSettingsRow(db_fetch_assoc($settingsResult))) {
                $key = $row['key'];
                $notifSettings[$key] = $row;
            }

            ExternalModules::enableForProject($this->PREFIX,$this->VERSION,$destProjectID);
            foreach ($notifSettings as $key => $details) {
                $this->setProjectSetting($key,$details['value'],$destProjectID);
            }

            /*$currentSettings = array();
            $sql = "SELECT `key`,`value`
                FROM redcap_external_module_settings
                WHERE external_module_id = '$externalModuleId'
                AND `key`='enabled'
                AND project_id=$destProjectID";
            $result = db_query($sql);
            while ($row = db_fetch_assoc($result)) {
                $currentSettings[$row['key']] = $row['value'];
            }
            if (!isset($currentSettings['enabled']) || $currentSettings['enabled'] == "") {
                foreach ($notifSettings as $key => $value) {
                    if ($key == "lastEvent") continue;
                    if ($key == 'enabled' && $value['value'] == 1) $value['value'] = "true";
                    $insertsql = "INSERT INTO redcap_external_module_settings (external_module_id,project_id,`key`,`type`,`value`)
                      VALUES ($externalModuleId,$destProjectID,'$key','".$value['type']."','".$value['value']."')";
                    //echo "$insertsql<br/>";
                    db_query($insertsql);
                    if ($error = db_error()) {
                        die($insertsql . ': ' . $error);
                    }
                }
            }*/
        }
    }

    /**
     * @param \Project $project
     * @param $lastEvent
     * @return mixed
     */
    function getLogs($project, $lastEvent)
    {
        if ($lastEvent == "") {
            $lastEvent = date("YmdHis");
        }
        /*elseif (strtotime("now") - strtotime($lastEvent) < 300) {
            return $lastEvent;
        }*/
        //echo "Started Project ID, Time, and Description Log Check: ".time()."<br/>";
        $sqlID = "SELECT log_event_id
            FROM redcap_log_event use index (ts)
            WHERE project_id={$project->project_id}
            AND ts > $lastEvent
            ORDER BY log_event_id DESC
            LIMIT 1";
        $qID = db_query($sqlID);
        echo "$sqlID<br/>";
        if ($error = db_error()) {
            throw new \Exception("Error: ".$error." trying to run the following SQL statement: ".$sqlID);
        }
        while ($rowID = db_fetch_assoc($qID)) {
            $rawID = $rowID['log_event_id'];
            /*if ($lastEvent < $row['ts']) {
                $lastEvent = $row['ts'];
            }*/
            echo "ID is: $rawID<br/>";
        }

        /*$sql = "SELECT user,pk,event_id,sql_log,event,ts,description,data_values FROM redcap_log_event use index (ts)
                  WHERE project_id = {$project->project_id}
                  AND ts > $lastEvent
                  AND  event in ('".implode("','",array_values($this->eventTypes))."')
                  AND description IN ('".implode("','",array_keys($this->notificationTypes))."')
                  ORDER BY ts DESC";*/
        $sql = "SELECT user,pk,event_id,sql_log,event,ts,description,data_values FROM redcap_log_event use index (PRIMARY)
                  WHERE project_id = {$project->project_id}
                  AND log_event_id > $rawID
                  AND  event in ('".implode("','",array_values($this->eventTypes))."')
                  AND description IN ('".implode("','",array_keys($this->notificationTypes))."')
                  ORDER BY ts DESC";
        echo "$sql<br/>";
        $q   = db_query($sql);

        if ($error = db_error()) {
            throw new \Exception("Error: ".$error." trying to run the following SQL statement: ".$sql);
        }

        //echo "After Project ID, Time, and Description Log Check: ".time()."<br/>";
        $rawData   = [];
        while ($row = db_fetch_assoc($q)) {
            $rawData[] = $row;
            /*if ($lastEvent < $row['ts']) {
                $lastEvent = $row['ts'];
            }*/

            $descriptions[$row['description']] = $row;
            if (array_key_exists($row['description'], $this->notificationTypes)) {
                $this->handleLogEntry($project, $row['description'], $row);
            }
        }
        //echo "After all checks: ".time()."<br/>";
        //return date("YmdHis");
        return $lastEvent;
    }

    /* All notifications require a set of Projects to run on so that will be the main filter on the query when checking notifications
     *
     * 6 Types of notifications
    0, New Record
        - No options
    1, New Form
        - [production:bool] Project in production (True/False). default: false
    2, New Field
        - [production:bool] Project in production (True/False). default: false
        - [forms:array] Specific Forms to monitor instead of entire project (multiple values to check?). default: NULL
    3, New Comment/Data Query
        - [fields:array]Specific fields to monitor. default: NULL
    4, User rights modified
        - [new_user:bool]Trigger on new user (true/false). default: true
        - [edit_user:bool]Trigger on user rights edited (true/false). default: true
    5, Field Data Check
        - [fields: [{fieldName}: {value to check}]]Fields to check. REQUIRED (use an empty string if the value just needs to not be blank)
        - Check for specific value? OPTIONAL if so what value
    6, E-signature Required
        - Fields to check and what to check for. REQUIRED
        - Past due in number of days
    7, Record Count
        - (Only trigger if all the records that trigger it are unique)
        - Number of records need before triggered
        - Running list of Record Ids to ensure unique triggers
    */

    /**
     * @param \Project $project
     * @param $logType
     * @param $logEntry
     * @throws \Exception
     */
    function handleLogEntry($project, $logType, $logEntry)
    {
        global $status;

        //$recordset     = new RecordSet($this->notificationProject->project_id, [$this->getProjectSetting('notif-active') => '1', $this->getProjectSetting('project-field') => $project->project_id, RecordSet::getKeyComparatorPair($this->getProjectSetting('notif-type'), 'IN') => $this->notificationTypes[$logType]]);
        //$notifications = $recordset->getRecords();
        $projectEvent = $this->notificationProject->firstEventId;
        /*$filterLogic = "([" . $this->getProjectSetting('project-field') . "] = '".$project->project_id."' and [" . $this->getProjectSetting('notif-active') . "] = '1'";
        if (is_array($this->notificationTypes[$logType])) {
            $firstLoop = true;
            $filterLogic .= " and (";
            foreach ($this->notificationTypes[$logType] as $notifID) {
                if (!$firstLoop) {
                    $filterLogic .= " or ";
                }
                $filterLogic .= "[".$this->getProjectSetting('notif-type')."] = '".$notifID."'";
                $firstLoop = false;
            }
            $filterLogic .= ")";
        }
        $filterLogic .= ")";*/

        $user = $logEntry['user'];
        $recordId        = $logEntry['pk'];
        $eventId         = $logEntry['event_id'];
        $eventName = $this->getEventName($eventId);

        $logVals   = $this->getKeyValuesFromLog($logEntry);
        $keyVals = $this->getKeyValuesFromLogSql($logEntry['sql_log']);

        if ($logVals['event'] == "ESIGNATURE") {
            $instance = $keyVals['instance'];
        }
        else {
            $instance = $logVals['instance'];
        }
        //unset($logVals['instance']);
        if (is_null($instance)) {
            $instance = 1;
        }

        $notifications = \REDCap::getData($this->notificationProject->project_id,'array', "", array(), $projectEvent, array(), false, false, false);

        $recordData = \REDCap::getData($project->project_id,'array',array($recordId),array(),array($eventId),false,false,false);

        $usefulRecordData = $this->getUsefulData($recordData[$recordId],$eventId,$instance);
        /*$usefulRecordData = array();
        if (isset($recordData[$recordId]['repeat_instances'][$eventId])) {
            foreach ($recordData[$recordId]['repeat_instances'][$eventId] as $formName => $formData) {
                $usefulRecordData = array_merge($usefulRecordData,$formData[$instance]);
            }
        }
        if (isset($recordData[$recordId][$eventId])) {
            $usefulRecordData = array_merge($usefulRecordData,$recordData[$recordId][$eventId]);
        }*/

        /**
         * @var  $key
         * @var \Records $notification
         */
        foreach ($notifications as $record_id => $notification) {
            //echo "<pre>";var_dump($notification->getDetails($this->getProjectSetting('notif-name')));echo "</pre>";
            $selectedtype = $notification[$projectEvent][$this->getProjectSetting('notif-type')];

            if (!in_array($selectedtype,$this->notificationTypes[$logType]) || $notification[$projectEvent][$this->getProjectSetting('project-field')] != $project->project_id || $notification[$projectEvent][$this->getProjectSetting('notif-active')] != '1') continue;

            $notificationMessage = array();
            $jsonOptions = json_decode($notification[$projectEvent][$this->getProjectSetting('access-json')], true);
            $scheduleOptions = json_decode($notification[$projectEvent][$this->getProjectSetting('schedule-json')],true);

            $roles = array_merge_recursive((array)json_decode($notification[$projectEvent][$this->getProjectSetting('role-list')]), (array)json_decode($notification[$projectEvent][$this->getProjectSetting('role-resolve')]));

            $pastDue = $this->processDateLogic($jsonOptions[self::PASTDUE_SETTING],$usefulRecordData,$jsonOptions[self::PASTDUE_TIMEFRAME]);
            $displayDate = $this->processDateLogic($jsonOptions[self::DISPLAY_DATE_SETTING],$usefulRecordData,$jsonOptions[self::DISPLAY_TIMEFRAME]);

//                $jsonOptions['production'];
//                $jsonOptions['forms'];
//                $jsonOptions['fields'];
//                $jsonOptions['new_user'];
//                $jsonOptions['edit_user'];
//                $jsonOptions['past_due'];
            $uniqueByUser = $jsonOptions[self::UNIQUE_USER_SETTING];
            $userList = array();
            if ($uniqueByUser === "1" && !empty($roles)) {
                $fieldList = array_filter(array_unique($roles['fields']));
                $roleList = array_filter(array_unique($roles['roles']));

                foreach ($fieldList as $field) {
                    $userList[] = $usefulRecordData[$field];
                }

                if (!empty($roleList)) {
                    foreach ($roleList as $roleID) {
                        $usersByRole = $this->getUserNamesByRoleID($roleID);
                        $userList = array_filter(array_unique(array_merge_recursive($userList,$usersByRole)));
                    }
                }
            }

            $notificationMessage = $this->processNotificationTrigger($notification,$project,$logEntry,$logType,$userList,$pastDue,$displayDate);

            if (!empty($notificationMessage['message'])) {
                $this->saveNotification($notification, $logEntry['user'], $userList, $notificationMessage, $pastDue, $displayDate);
            }
        }
    }

    function processNotificationTrigger($notification,$project,$logEntry,$logType,$userList,$pastDue,$displayDate) {
        global $status;

        $projectEvent = $this->notificationProject->firstEventId;
        $selectedtype = $notification[$projectEvent][$this->getProjectSetting('notif-type')];
        $jsonOptions = array_filter(json_decode($notification[$projectEvent][$this->getProjectSetting('access-json')], true));

        $user = $logEntry['user'];
        $recordId        = $logEntry['pk'];
        $eventId         = $logEntry['event_id'];
        $eventName = $this->getEventName($eventId);

        $logVals   = $this->getKeyValuesFromLog($logEntry);
        $keyVals = $this->getKeyValuesFromLogSql($logEntry['sql_log']);

        if ($logVals['event'] == "ESIGNATURE") {
            $instance = $keyVals['instance'];
        }
        else {
            $instance = $logVals['instance'];
        }
        //unset($logVals['instance']);
        if (is_null($instance)) {
            $instance = 1;
        }

        $notificationMessage = array("record_id"=>$recordId,"event_id"=>$eventId,"event_name"=>$eventName,"instance"=>$instance,"pid"=>$project->project_id);
        switch ($selectedtype) {
            case 0: //New Record
                $notificationMessage['message'] = "New Record created with ID: " . $logEntry['pk'];
                break;
            case 1: //New Form
                if (array_key_exists(self::PROJ_PROD_SETTING, $jsonOptions) && ($jsonOptions[self::PROJ_PROD_SETTING] == '1' && $status > 0) || ($jsonOptions[self::PROJ_PROD_SETTING] == '0')) {
                    //form_name
                    $formNames = $this->getKeyValuesFromLog($logEntry);
                    $notificationMessage['message'] = "Form created: {$formNames['form_name']}";
                    $notificationMessage['form_name'] = $formNames['form_name'];
                }
                break;
            case 2: //New Field
                if (array_key_exists(self::PROJ_PROD_SETTING, $jsonOptions) && ($jsonOptions[self::PROJ_PROD_SETTING] == '1' && $status > 0) || ($jsonOptions[self::PROJ_PROD_SETTING] == '0')) {
                    $dataValues = $this->getKeyValuesFromLog($logEntry['data_values']);
                    $fieldMetaData = $project->metadata[trim(array_pop(array_keys($dataValues)))];
                    $formName = $fieldMetaData['form_name'];
                    $notificationMessage['message'] = "Field created: {$dataValues['field_name']} on form: $formName";
                    $notificationMessage['form_name'] = $formName;
                }
                break;
            case 3: //New Comment/Data Query
                //TODO allow for multiple fields
                $comment = json_decode($logEntry['data_values'], true);

                if (!array_key_exists(self::FIELD_NAME_SETTING,$jsonOptions) || (array_key_exists(self::FIELD_NAME_SETTING, $jsonOptions) && in_array($comment['field'], $jsonOptions[self::FIELD_NAME_SETTING]))) {
                    $notificationMessage['message'] = "Comment added to field {$comment['field']}: {$comment['comment']}";
                    $fieldMetaData = $project->metadata[trim($comment['field'])];
                    $notificationMessage['field'] = $comment['field'];
                    $notificationMessage['form_name'] = $fieldMetaData['form_name'];
                }
                break;
            case 4: //User rights modified
                if ((($logType === "Edit user" || $logType == "Delete user") && array_key_exists(self::USER_EDIT_SETTING, $jsonOptions) && $jsonOptions[self::USER_EDIT_SETTING]) ||
                    ($logType === "Add user" && array_key_exists(self::USER_NEW_SETTING, $jsonOptions) && $jsonOptions[self::USER_NEW_SETTING])) {
                    $response['changed_user'] = $logEntry['pk'];
                    $notificationMessage['message'] = "User " . $logEntry['pk'] . " was " . ($logType === "Edit user" ? "edited" : ($logType === "Delete user" ? "deleted" : "added"));
                }
                break;
            case 5: //Field Data Check
                if (array_key_exists(self::FIELD_NAME_SETTING, $jsonOptions)) {
                    $this->checkRecordFields($project, $logEntry, $jsonOptions[self::FIELD_NAME_SETTING], function ($recordId, $formName=null, $instance=null) use ($notification, $user, $userList, $pastDue, $displayDate, &$notificationMessage) {
                        $notificationMessage['record_id'] = $recordId;
                        $notificationMessage['form_name'] = $formName;
                        $notificationMessage['instance'] = $instance;
                        $notificationMessage['message'] = "Record ID: $recordId<br/>Form Modified: $formName";
                        if ($instance) {
                            $notificationMessage['message'] .= "<br/>Instance: $instance";
                        }
                        //$this->saveNotification($notification, $user, $userList, $notificationMessage, $pastDue, $displayDate);
                    });
                }
                break;
            case 6: //E-signature Required
                if (array_key_exists(self::FIELD_NAME_SETTING, $jsonOptions) && array_key_exists(self::PASTDUE_SETTING, $jsonOptions)) {
                    $this->checkRecordFields($project, $logEntry, $jsonOptions[self::FIELD_NAME_SETTING], function ($recordId, $formName = null, $instance = null) use ($notification, $user, $userList, $pastDue, $displayDate, &$notificationMessage) {
                        $notificationMessage['record_id'] = $recordId;
                        $notificationMessage['form_name'] = $formName;
                        $notificationMessage['instance'] = $instance;
                        $notificationMessage['message'] = "Record ID: $recordId<br/>Form Modified: $formName";
                        if ($instance) {
                            $notificationMessage['message'] .= "<br/>Instance: $instance";
                        }
                        //$this->saveNotification($notification, $user, $userList, $notificationMessage, $pastDue, $displayDate);
                    });
                }
                break;
            case 7: //Record Count
                $recordId = $logEntry['pk'];
                if (array_key_exists(self::FIELD_NAME_SETTING, $jsonOptions) && array_key_exists(self::RECORD_COUNT_SETTING, $jsonOptions) && !in_array($recordId, $jsonOptions['record_history'])) {
                    $jsonOptions['record_history'][] = $recordId;
                    $matched = false;
                    $this->checkRecordFields($project, $logEntry, $jsonOptions[self::FIELD_NAME_SETTING], function ($recordId, $formName=null, $instance=null) use ($projectEvent,$jsonOptions,$notification, $user, $userList, $pastDue, $displayDate, &$notificationMessage) {
                        //$matched = true;
                        \REDCap::saveData($this->notificationProject->project_id, 'array', [$recordId => [$projectEvent => array($this->getProjectSetting("access-json") => json_encode($jsonOptions))]],'overwrite');
                        if (count($jsonOptions['record_history']) % $jsonOptions[self::RECORD_COUNT_SETTING] === 0) {
                            $notificationMessage['record_id'] = $recordId;
                            $notificationMessage['form_name'] = $formName;
                            $notificationMessage['instance'] = $instance;
                            $notificationMessage['message'] = "Record ID: $recordId<br/>";
                            //$this->saveNotification($notification, $user, $userList, $notificationMessage,$pastDue,$displayDate);
                        }
                    });
                    /*if ($matched) {
                        \REDCap::saveData($this->notificationProject->project_id, 'array', [$record_id => [$projectEvent => array($this->getProjectSetting("access-json") => json_encode($jsonOptions))]],'overwrite');
                        if (count($jsonOptions['record_history']) % $jsonOptions[self::RECORD_COUNT_SETTING] === 0) {
                            $this->saveNotification($notification, $user, $userList, $notificationMessage,$pastDue,$displayDate);
                        }
                    }*/
                }
                break;
            case 8: //E-Signature was added
                $recordId = $logEntry['pk'];
                if (array_key_exists(self::FORM_NAME_SETTING, $jsonOptions)) {
                    //$jsonOptions['record_history'][] = $recordId;

                    if (in_array($keyVals['form_name'],$jsonOptions[self::FORM_NAME_SETTING]) || empty(array_filter($jsonOptions[self::FORM_NAME_SETTING]))) {
                        $formName = $project->forms[$keyVals['form_name']]['menu'];

                        $notificationMessage['record_id'] = $recordId;
                        $notificationMessage['form_name'] = $keyVals['form_name'];
                        $notificationMessage['instance'] = $keyVals['instance'];
                        $notificationMessage['event_id'] = $keyVals['event_id'];
                        $notificationMessage['message'] = "Record ID: ".$recordId."<br/>Form: ".$formName."<br/>User: ".$keyVals['username']."<br/>";
                    }
                }
                break;
        }

        return $notificationMessage;
    }

    function getUsefulData($recordData,$eventId,$instance) {
        $usefulRecordData = array();
        if (isset($recordData['repeat_instances'][$eventId])) {
            foreach ($recordData['repeat_instances'][$eventId] as $formName => $formData) {
                $usefulRecordData = array_merge($usefulRecordData,$formData[$instance]);
            }
        }
        if (isset($recordData[$eventId])) {
            $usefulRecordData = array_merge($usefulRecordData,$recordData[$eventId]);
        }
        return $usefulRecordData;
    }

    /**
     * Only used on log types that definitely have to format {"field" = value, "field2" = value}
     * @param $logEntry
     * @return array
     */
    function getKeyValuesFromLog($logEntry)
    {
        $data          = $logEntry['data_values'];
        $dataChanges   = explode(',', $data);
        $keyValuePairs = [];
        foreach ($dataChanges as $keyVal) {
            if (strpos($keyVal, '[') !== false && strpos($keyVal, ']') !== false) {
                $keyVal = str_replace('[', '', str_replace(']', '', $keyVal));
            }
            if (strpos($keyVal, "''") !== false) {
                $keyVal = str_replace("'", "", $keyVal);
            }
            list($var, $val) = explode(' = ', $keyVal);
            if (!empty($var)) {
                $keyValuePairs[trim($var)] = $val;
            }
        }

        return $keyValuePairs;
    }

    /**
     * Get the name of the form from an esginature log
     * @param $logEntry
     * @return string
     */
    function getFormNameFromLog($logEntry)
    {
        global $lang;
        $data          = $logEntry['data_values'];
        $dataChanges   = explode("\n", $data);

        foreach ($dataChanges as $dataChange) {
            $formCheck = explode(":",$dataChange);
            if ($formCheck[0] == "Form:") {
                return trim($formCheck[1]);
            }
        }
        /*$keyValuePairs = [];
        foreach ($dataChanges as $keyVal) {
            if (strpos($keyVal, '[') !== false && strpos($keyVal, ']') !== false) {
                $keyVal = str_replace('[', '', str_replace(']', '', $keyVal));
            }
            if (strpos($keyVal, "''") !== false) {
                $keyVal = str_replace("'", "", $keyVal);
            }
            list($var, $val) = explode(' = ', $keyVal);
            if (!empty($var)) {
                $keyValuePairs[trim($var)] = $val;
            }
        }

        return $keyValuePairs;*/
        return "";
    }

    /**
     * @param $eventID
     */

    function getEventName($eventID) {
        $returnID = "";
        $sql = "SELECT descrip
            FROM redcap_events_metadata
            WHERE event_id = $eventID
            LIMIT 1";
        //echo "$sql<br/>";
        $returnID = db_result(db_query($sql),0);
        return $returnID;
    }

    /**
     *
     * @param $roleID
     * @return array
     */
    function getUserNamesByRoleID($roleID) {
        $userNames = array();
        $sql = "SELECT username
            FROM redcap_user_rights
            WHERE role_id = '$roleID'";
        $result = db_query($sql);
        while ($row = db_fetch_assoc($result)) {
            if (!in_array($row['username'],$userNames)) {
                $userNames[] = $row['username'];
            }
        }

        return $userNames;
    }

    /**
     *
     * @param $sql
     * @return array
     */

    function getKeyValuesFromLogSQL($sql)
    {
        $matches = [];
        if (strpos(strtolower($sql), 'insert') === 0) {
            if (preg_match_all('/\([\s\S]*?\)/', $sql, $matches) === 2) {
                $keys    = array_map('trim',explode(',', str_replace(')', '', str_replace('(', '', $matches[0][0]))));
                $values  = array_map('trim',explode(',', str_replace('\'', '', str_replace(')', '', str_replace('(', '', $matches[0][1])))));
                $keyVals = array_combine($keys, $values);

                return $keyVals;
            }
        }
    }

    /**
     * Current functionality will skip checking any fields not on the current event
     * which means the the fields skipped won't be considered in the notification being triggered
     * Additionally, if the triggering form is non-repeating, it will check against every repeating field and save a notification for any the match
     * but if the form is repeating, it will check the values for the current instance of that form and all the other non-repeating forms
     *
     */

    /**
     * @param \Project $project
     * @param $logEntry
     * @param $fields
     * @param $callback
     */
    function checkRecordFields($project, $logEntry, $fields, $callback)
    {
        $recordId        = $logEntry['pk'];
        $eventId         = $logEntry['event_id'];
        $recordData      = \REDCap::getData($project->project_id, 'array', $recordId, array_keys($fields));
        $matches         = [];
        //this tells if the form that triggered the check is repeating
        $logVals   = $this->getKeyValuesFromLog($logEntry);

        $instance  = $logVals['instance'];
        unset($logVals['instance']);
        if (count($logVals) === 0) {
            return;
        }

        $logFieldMatch = false;

        foreach ($fields as $fieldName => $checkValues) {
            $fieldMeta = $project->metadata[$fieldName];
            $isCheckbox = $fieldMeta['element_type'] === 'checkbox';
            if ($isCheckbox) {
                foreach ($checkValues as $checkValue) {
                    if (in_array($fieldName."(".$checkValue.")", array_keys($logVals))) {
                        $logFieldMatch = true;
                    }
                }
            }
            else {
                if (in_array($fieldName, array_keys($logVals))) {
                    $logFieldMatch = true;
                }
            }
        }
        if (!$logFieldMatch) {
            return;
        }

        $fieldMetaData = $project->metadata[trim(array_pop(array_keys($logVals)))];
        $formName = $fieldMetaData['form_name'];

        //TODO This doesn't actually check whether the fields are repeating, but only the form containing the record ID. FIX THIS!
        $repeating = $project->isRepeatingForm($eventId,$formName);
        if (is_null($instance)) {
            $instance = 1;
        }

        //Make sure that the data in the record matches the checks for the record
        $matchCount = $this->checkMatchCount($fields,$project,$eventId,$instance,$recordData[$recordId]);

        if ($matchCount === count($fields)) {
            $callback($recordId, $formName);
        }
    }

    function checkMatchCount($fields,$project,$eventId,$instance,$recordData) {
        //creates an array that mirrors the structure of REDCap getData, but with booleans for all the field values which indicates which fields matched the provided values to check against
        //Reasoning is to possibly switch the functionality from AND only to OR, so we'd need to preserve all the individual matches
        $matchCount = 0;

        foreach ($fields as $fieldName => $checkValues) {
            $fieldMeta = $project->metadata[$fieldName];
            $isCheckbox = $fieldMeta['element_type'] === 'checkbox';
            if (!$project->isRepeatingForm($eventId,$fieldMeta['form_name']) && array_key_exists($eventId, $recordData)) {
                $actualValue = $recordData[$eventId][$fieldName];
                $matches[$eventId][$fieldName] = $this->checkSingleValue($checkValues, $actualValue, $isCheckbox);
                if ($matches[$eventId][$fieldName] > 0) {
                    $matchCount++;
                }
            } else if ($project->isRepeatingForm($eventId,$fieldMeta['form_name']) && array_key_exists($eventId, $recordData['repeat_instances'])) {
                foreach ($recordData['repeat_instances'][$eventId] as $form => $instances) {
                    foreach ($instances as $instanceNum => $instanceFields) {
                        if ($instanceNum != $instance) continue;
                        $actualValue = $instanceFields[$fieldName];
                        $matches['repeat_instances'][$eventId][$form][$instanceNum][$fieldName] = $this->checkSingleValue($checkValues, $actualValue, $isCheckbox);
                        if ($matches['repeat_instances'][$eventId][$form][$instanceNum][$fieldName] > 0) {
                            $matchCount++;
                        }
                    }
                }
            } else {
                //If the field isn't in the current event then skip it
                continue;
            }
        }
        return $matchCount;
    }

    /**
     * Checks the given value against an array of possible values
     *
     * @param $validValues
     * @param $actualValue
     * @param $isCheckbox
     * @return bool
     */
    function checkSingleValue($validValues, $actualValue, $isCheckbox)
    {
        if ($isCheckbox){
            if (empty($validValues)) {
                $match = in_array(1, $actualValue);
            } else {
                $match = false;
                foreach ($validValues as $checkValue) {
                    $match |= $actualValue[$checkValue] == 1;
                }
            }
        } else {
            if (empty($validValues)) {
                $match = !empty($actualValue);
            } else {
                $match = false;
                foreach ($validValues as $checkValue) {
                    $match |= trim($actualValue) == trim($checkValue);
                }
            }
        }

        return $match;
    }

    /**
     * @param \Records $notification
     * @param $user
     * @param $message
     * @param null $pastDue
     */
    function saveNotification($notification, $user, $userList, $message, $pastDue, $displayDate)
    {
        $projectEvent = $this->notificationProject->firstEventId;
        $notifForm = $this->notificationProject->metadata[$this->getProjectSetting('user-created')]['form_name'];

        $details = $notification['repeat_instances'][$projectEvent][$notifForm];

        $recordID = $notification[$projectEvent][$this->notificationProject->table_pk];
        //if it's an array then get the max key. if it's not then the instance is 1
        $instance = is_array($details) ? max(array_keys($details)) + 1 : 1;

        $changes = array();
        $changes[$recordID][$notifForm.'_complete'] = 0;
        $changes[$recordID][$this->notificationProject->table_pk] = $recordID;
        $changes[$recordID][$this->getProjectSetting("user-created")] = $user;
        $changes[$recordID][$this->getProjectSetting("notif-date")] = date("Y-m-d", time());
        //$changes[$recordID]['redcap_repeat_instrument'] = $notifForm;
        $changes[$recordID][$this->getProjectSetting("notif-context")] = json_encode($message);
        if ($pastDue != "") {
            $changes[$recordID][$this->getProjectSetting("pastdue-date")] = date("Y-m-d", strtotime($pastDue));
        }
        if ($displayDate != "") {
            $changes[$recordID][$this->getProjectSetting("display-date")] = date("Y-m-d", strtotime($displayDate));
        }

        if (!empty($userList)) {
            $firstuser = true;
            foreach ($userList as $user) {
                if (!$firstuser) {
                    $instance++;
                }
                $firstuser = false;
                $changes[$recordID]['redcap_repeat_instance'] = $instance;
                $changes[$recordID]['redcap_repeat_instrument'] = $notifForm;
                $changes[$recordID][$this->getProjectSetting('unique-user')] = $user;

                $result = \REDCap::saveData($this->notificationProject->project_id, 'json', json_encode($changes), 'overwrite');
            }
        }
        else {
            $changes[$recordID]['redcap_repeat_instance'] = $instance;
            $changes[$recordID]['redcap_repeat_instrument'] = $notifForm;
            $result = \REDCap::saveData($this->notificationProject->project_id, 'json', json_encode($changes), 'overwrite');
        }

        if (!empty($result['errors'])) {
            throw new \Exception("Error on notification creation: ".$result['errors']);
        }
        /*$notification->updateDetails($changes);
        $notification->getDetails();*/
    }

    /**
     * Function called by the CRON to generate scheduled notifications
     * @throws \Exception
     */
    function schedulednotifications() {
        // Only want this to run once a day, preferably when no one is working in the system.
        /*$currentHour = date('H');
        if ($currentHour != "02") return;*/

        $projects = $this->framework->getProjectsWithModuleEnabled();

        foreach ($projects as $projectID) {
            $dataProject = new \Project($projectID);
            $notificationProject = $this->getProjectSetting('notif-project',$projectID);
            $scheduledField = $this->getProjectSetting('schedule-json',$projectID);
            $projectField = $this->getProjectSetting('project-field',$projectID);
            $notifications = \REDCap::getData($notificationProject, 'array');

            $notificationArray = array();
            //$currentDate = date('Y-m-d');
            if ($notificationProject != "" && is_numeric($notificationProject) && $scheduledField != "") {
                $this->notificationProject = new \Project($notificationProject);
                $notifEventID = $this->notificationProject->firstEventId;
                $notifications = \REDCap::getData($notificationProject, 'array', array(), array(), array(), array(), false, false, false, '[' . $scheduledField . '] != "" and ['.$projectField.'] = "'.$projectID.'"');
                $projectData = \REDCap::getData($projectID, 'array', array(), array(), array(), array(), false, false, false);
                $instanceCount = array();
                if (isset($projectData['repeat_instances'])) {
                    foreach ($projectData['repeat_instances'] as $eventID => $eventData) {
                        foreach ($eventData as $formname => $formData) {
                            foreach ($formData as $instanceID => $instanceData) {
                                $instanceCount[] = $instanceID;
                            }
                        }
                    }
                }
                if (empty($instanceCount)) {
                    $instanceCount[] = 1;
                }
                $currentDate = date("Y-m-d");
/*echo "<pre>";
print_r($projectData);
echo "</pre>";*/
                foreach ($notifications as $recordID => $eventData) {
                    $scheduling = json_decode($eventData[$notifEventID][$scheduledField], true);
                    $access = json_decode($eventData[$notifEventID][$this->getProjectSetting('access-json', $projectID)], true);
                    if (!isset($eventData['repeat_instances'])) {
                        foreach ($projectData as $projectRecordID => $recordData) {
                            $startDate = $this->processDateLogic($scheduling[self::SCHEDULE_START_DATE], $recordData[$dataProject->firstEventId], $scheduling[self::START_TIMEFRAME]);
                            $endDate = $this->processDateLogic($scheduling[self::SCHEDULE_END_DATE], $recordData[$dataProject->firstEventId], $scheduling[self::END_TIMEFRAME]);
                            if (strtotime($endDate) < strtotime($currentDate) || strtotime($startDate) > strtotime($currentDate)) continue;
                            if (strtotime($startDate) <= strtotime($currentDate)) {
                                foreach ($recordData as $eventID => $projectEventData) {
                                    if ($eventID == "repeat_instances") continue;
                                    foreach ($instanceCount as $instance) {
                                        $matchCount = $this->checkMatchCount($access[self::FIELD_NAME_SETTING], $dataProject, $eventID, $instance,$projectEventData);
                                        if ($matchCount == count($access[self::FIELD_NAME_SETTING])) {
                                            $usefulRecordData = $this->getUsefulData($recordData[$projectRecordID],$eventID,$instance);
                                            $roles = array_merge_recursive((array)json_decode($eventData[$notifEventID][$this->getProjectSetting('role-list',$projectID)]), (array)json_decode($eventData[$notifEventID][$this->getProjectSetting('role-resolve',$projectID)]));

                                            $pastDue = $this->processDateLogic($access[self::PASTDUE_SETTING],$usefulRecordData,$access[self::PASTDUE_TIMEFRAME]);
                                            $displayDate = $this->processDateLogic($access[self::DISPLAY_DATE_SETTING],$usefulRecordData,$access[self::DISPLAY_TIMEFRAME]);

                                            $uniqueByUser = $access[self::UNIQUE_USER_SETTING];
                                            $userList = array();
                                            if ($uniqueByUser === "1" && !empty($roles)) {
                                                $fieldList = array_filter(array_unique($roles['fields']));
                                                $roleList = array_filter(array_unique($roles['roles']));

                                                foreach ($fieldList as $field) {
                                                    $userList[] = $usefulRecordData[$field];
                                                }

                                                if (!empty($roleList)) {
                                                    foreach ($roleList as $roleID) {
                                                        $usersByRole = $this->getUserNamesByRoleID($roleID);
                                                        $userList = array_filter(array_unique(array_merge_recursive($userList,$usersByRole)));
                                                    }
                                                }
                                            }
                                            $notificationMessage = array("record_id"=>$projectRecordID,"event_id"=>$eventID,"event_name"=>$dataProject->getUniqueEventNames($eventID),"instance"=>$instance,"pid"=>$projectID);
                                            $notificationMessage['instance'] = $instance;
                                            $notificationMessage['message'] = "Record ID: $projectRecordID";
                                            $notificationMessage['message'] .= "<br/>Instance: $instance";
                                            $this->saveNotification($eventData[$notifEventID], "notif_cron", $userList, $notificationMessage, $pastDue, $displayDate);
                                        }
                                    }
                                }
                            }
                        }
                    }
                    else {
                        foreach ($eventData['repeat_instances'] as $eventID => $instanceEvent) {
                            foreach ($instanceEvent as $formName => $instances) {
                                foreach ($instances as $instance => $data) {
                                    $context = json_decode($data[$this->getProjectSetting('notif-context', $projectID)]);
                                    $contextRecordID = $context['record_id'];
                                    $contextEventID = $context['event_id'];
                                    $contextInstance = $context['instance'];
                                    if (!isset($notificationArray[$contextRecordID][$contextEventID][$contextInstance]) || $notificationArray[$contextRecordID][$contextEventID][$contextInstance]['last_instance'] < $instance) {
                                        $notificationArray[$contextRecordID][$contextEventID][$contextInstance]['record_data'] = $data;
                                        $notificationArray[$contextRecordID][$contextEventID][$contextInstance]['last_instance'] = $instance;
                                    }
                                }
                            }
                        }
                        foreach ($notificationArray as $sourceRecordID => $sourceEventData) {
                            foreach ($sourceEventData as $sourceEventID => $sourceInstanceData) {
                                foreach ($sourceInstanceData as $sourceInstanceID => $sourceData) {
                                    $currentRecordData = $this->getUsefulData($projectData[$sourceRecordID],$sourceEventID,$sourceInstanceID);
                                    $startDate = $this->processDateLogic($scheduling[self::SCHEDULE_START_DATE], $projectData[$sourceRecordID][$dataProject->firstEventId], $scheduling[self::START_TIMEFRAME]);
                                    $endDate = $this->processDateLogic($scheduling[self::SCHEDULE_END_DATE], $projectData[$sourceRecordID][$dataProject->firstEventId], $scheduling[self::END_TIMEFRAME]);
                                    $interval = $this->processIntervalString($scheduling[self::SCHEDULE_COUNT],$projectData[$sourceRecordID][$dataProject->firstEventId],$scheduling[self::SCHEDULE_TIMEFRAME]);
                                    if (strtotime($endDate) < strtotime($currentDate) || strtotime($startDate) > strtotime($currentDate)) continue;
                                    $examineDate = new \DateTime($sourceData[$this->getProjectSetting('notif-date',$projectID)]);
                                    $examineDate->modify($interval);
                                    if (strtotime($examineDate->format('Y-m-d')) > strtotime($currentDate)) continue;
                                    while (strtotime($examineDate->format('Y-m-d')) <= strtotime($currentDate)) {
                                        if ($examineDate->format('Y-m-d') == $currentDate) {
                                            //TODO Create new instance of notification, and user list
                                        }
                                        $examineDate->modify($interval);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}