<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 8/15/2018
 * Time: 1:50 PM
 */

namespace Vanderbilt\DashboardNotificationsExternalModule;

require_once __DIR__ . '/../../plugins/Core/bootstrap.php';
require_once __DIR__ . '/../../plugins/Core/Libraries/Project.php';
require_once __DIR__ . '/../../plugins/Core/Libraries/RecordSet.php';
require_once __DIR__ . '/../../plugins/Core/Libraries/Record.php';

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;
use Plugin\Project;
use Plugin\Record;
use Plugin\RecordSet;

class DashboardNotificationsExternalModule extends AbstractExternalModule
{
    private $notificationProject;
    const PROJ_PROD_SETTING = "project_production";
    const FORM_NAME_SETTING = "form_names";
    const FIELD_NAME_SETTING = "field_names";
    const FIELD_VALUE_SETTING = "field_value";
    const USER_NEW_SETTING = "user_new";
    const USER_EDIT_SETTING = "user_edit";
    const PASTDUE_SETTING = "past_due";
    const RECORD_COUNT_SETTING = "record_count";

    private $notificationTypes = [
        "Create record"                     => [0, 5, 6, 7],
        "Update record"                     => [5, 6, 7],
        "Create data collection instrument" => [1],
        "Create project field"              => [2],
        "Add field comment"                 => [3],
        "Verified data value"               => [3],
        "Edit user"                         => [4],
        "Add user"                          => [4],
    ];

    function hook_every_page_top($project_id)
    {
        $notificationProject = $this->getProjectSetting('notif-project');
        if ($notificationProject) {
            $this->notificationProject = new \Plugin\Project($notificationProject);
            $project = new \Plugin\Project($project_id);
            $lastEvent = $this->getProjectSetting('lastEvent') ? $this->getProjectSetting('lastEvent') : 0;

            $lastEvent = $this->getLogs($project, $lastEvent);
            $this->setProjectSetting('lastEvent', $lastEvent);
        }
    }

    function redcap_module_link_check_display($project_id, $link) {
        if(\REDCap::getUserRights(USERID)[USERID]['design'] == '1'){
            return $link;
        }
        return null;
    }

    function getNotifications($projectID) {
        $returnData = \Records::getData($projectID);
        return $returnData;
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

    /**
     * @param Project $project
     * @param $lastEvent
     * @return mixed
     */
    function getLogs($project, $lastEvent)
    {

        $sql = "SELECT * FROM redcap_log_event 
                  WHERE project_id = {$project->getProjectId()}
                  AND ts > $lastEvent
                  ORDER BY ts DESC";
        $q   = db_query($sql);

        if ($error = db_error()) {
            die($sql . ': ' . $error);
        }

        $rawData   = [];
        while ($row = db_fetch_assoc($q)) {
            $rawData[] = $row;
            if ($lastEvent < $row['ts']) {
                $lastEvent = $row['ts'];
            }

            $descriptions[$row['description']] = $row;
            if (array_key_exists($row['description'], $this->notificationTypes)) {
                $this->handleLogEntry($project, $row['description'], $row);
            }
        }

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
     * @param Project $project
     * @param $logType
     * @param $logEntry
     * @throws \Exception
     */
    function handleLogEntry($project, $logType, $logEntry)
    {
        $user = $logEntry['user'];
        $recordset     = new RecordSet($this->notificationProject, [$this->getProjectSetting('notif-active') => '1', $this->getProjectSetting('project-field') => $project->getProjectId(), RecordSet::getKeyComparatorPair($this->getProjectSetting('notif-type'), 'IN') => $this->notificationTypes[$logType]]);
        $notifications = $recordset->getRecords();
        /**
         * @var  $key
         * @var Record $notification
         */
        foreach ($notifications as $key => $notification) {
            //echo "<pre>";var_dump($notification->getDetails($this->getProjectSetting('notif-name')));echo "</pre>";
            $selectedtype = $notification->getDetails($this->getProjectSetting('notif-type'));
            $jsonOptions = json_decode($notification->getDetails($this->getProjectSetting('access-json')), true);
            $pastDue = $jsonOptions[self::PASTDUE_SETTING];
//                $jsonOptions['production'];
//                $jsonOptions['forms'];
//                $jsonOptions['fields'];
//                $jsonOptions['new_user'];
//                $jsonOptions['edit_user'];
//                $jsonOptions['past_due'];
            $notificationMessage = '';
            switch ($selectedtype) {
                case 0: //New Record
                    $notificationMessage = "New Record created with ID: " . $logEntry['pk'];
                    break;
                case 1: //New Form
                    if (array_key_exists(self::PROJ_PROD_SETTING, $jsonOptions) && ($jsonOptions[self::PROJ_PROD_SETTING] == '1' && $this->inProduction($project)) || ($jsonOptions[self::PROJ_PROD_SETTING] == '0')) {
                        //form_name
                        $formNames           = $this->getKeyValuesFromLog($logEntry);
                        $notificationMessage = "Form created: {$formNames['form_name']}";
                    }
                    break;
                case 2: //New Field
                    if (array_key_exists(self::PROJ_PROD_SETTING, $jsonOptions) && ($jsonOptions[self::PROJ_PROD_SETTING] == '1' && $this->inProduction($project)) || ($jsonOptions[self::PROJ_PROD_SETTING] == '0')) {
                        $dataValues = $this->getKeyValuesFromLog($logEntry['data_values']);
                        $fieldMetaData = $project->getMetadata(trim(array_pop(array_keys($dataValues))));
                        $formName = $fieldMetaData->getFormName();
                        $notificationMessage   = "Field created: {$dataValues['field_name']} on form: $formName";
                    }
                    break;
                case 3: //New Comment/Data Query
                    //TODO allow for multiple fields
                    $comment = json_decode($logEntry['data_values'], true);
                    if (array_key_exists(self::FIELD_NAME_SETTING, $jsonOptions) && in_array($comment['field'], $jsonOptions[self::FIELD_NAME_SETTING])) {
                        $notificationMessage = "Comment added to field {$comment['field']}: {$comment['comment']}";
                    }
                    break;
                case 4: //User rights modified
                    if (($logType === "Edit user" && array_key_exists(self::USER_EDIT_SETTING, $jsonOptions) && $jsonOptions[self::USER_EDIT_SETTING]) ||
                        ($logType === "Add user" && array_key_exists(self::USER_NEW_SETTING, $jsonOptions) && $jsonOptions[self::USER_NEW_SETTING])) {
                        $response['changed_user'] = $logEntry['pk'];
                        $notificationMessage      = "User " . $logEntry['pk'] . " was " . ($logType === "Edit user" ? "edited" : "added");
                    }
                    break;
                case 5: //Field Data Check
                    if (array_key_exists(self::FIELD_NAME_SETTING, $jsonOptions)) {
                        $this->checkRecordFields($project, $logEntry, $jsonOptions[self::FIELD_NAME_SETTING], function ($recordId, $formName=null, $instance=null) use ($notification, $user) {
                            $msg = "Record ID: $recordId\nForm Modified: $formName";
                            if ($instance) {
                                $msg .= "\nInstance: $instance";
                            }
                            $this->saveNotification($notification, $user, $msg);
                        });
                    }
                    break;
                case 6: //E-signature Required
                    if (array_key_exists(self::FIELD_NAME_SETTING, $jsonOptions) && array_key_exists(self::PASTDUE_SETTING, $jsonOptions)) {
                        $pastDue = $jsonOptions[self::PASTDUE_SETTING];
                        $this->checkRecordFields($project, $logEntry, $jsonOptions[self::FIELD_NAME_SETTING], function ($recordId, $formName = null, $instance = null) use ($notification, $user, $pastDue) {
                            $this->saveNotification($notification, $user, "Record ID: $recordId\n", $pastDue);
                        });
                    }
                    break;
                case 7: //Record Count
                    $recordId        = $logEntry['pk'];
                    if (array_key_exists(self::FIELD_NAME_SETTING, $jsonOptions) && array_key_exists(self::RECORD_COUNT_SETTING, $jsonOptions) && !in_array($recordId, $jsonOptions['record_history'])) {
                        $jsonOptions['record_history'][] = $recordId;
                        $matched = false;
                        $this->checkRecordFields($project, $logEntry, $jsonOptions[self::FIELD_NAME_SETTING], function ($recordId, $formName=null, $instance=null) use (&$matched) {
                            $matched = true;
                        });
                        if ($matched) {
                            $notification->updateDetails(['access-json' => json_encode($jsonOptions)]);
                            if (count($jsonOptions['record_history']) % $jsonOptions[self::RECORD_COUNT_SETTING] === 0) {
                                $this->saveNotification($notification, $user, "");
                            }
                        }
                    }
                    break;
            }
            //echo "Notification message: $notificationMessage<br/>";

            if (!empty($notificationMessage)) {
                $this->saveNotification($notification, $logEntry['user'], $notificationMessage, $pastDue);
            }

        }
    }

    /** @param Project $project
     *  @return bool
     */
    function inProduction($project)
    {
        $details = $project->getProjectDetails();

        return $details['status'];
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
                $keyValuePairs[$var] = $val;
            }
        }

        return $keyValuePairs;
    }

    /**
     * @deprecated
     *
     * @param $sql
     * @return array
     */
    function getKeyValuesFromLogSQL($sql)
    {
        $matches = [];
        if (strpos(strtolower($sql), 'insert') === 0) {
            if (preg_match_all('/\([\s\S]*?\)/', $sql, $matches) === 2) {
                $keys    = explode(',', str_replace(')', '', str_replace('(', '', $matches[0][0])));
                $values  = explode(',', str_replace(')', '', str_replace('(', '', $matches[0][0])));
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
     * @param Project $project
     * @param $logEntry
     * @param $fields
     * @param $callback
     */
    function checkRecordFields($project, $logEntry, $fields, $callback)
    {
        $recordId        = $logEntry['pk'];
        $eventId         = $logEntry['event_id'];
        $recordData      = \REDCap::getData($project->getProjectId(), 'array', $recordId, array_keys($fields));
        $matches         = [];
        //this tells if the form that triggered the check is repeating
        $logVals   = $this->getKeyValuesFromLog($logEntry);

        $instance  = $logVals['instance'];
        unset($logVals['instance']);
        if (count($logVals) === 0) {
            return;
        }

        $fieldMetaData = $project->getMetadata(trim(array_pop(array_keys($logVals))));
        $formName = $fieldMetaData->getFormName();

        $repeating = in_array($formName, $project->getRepeatingFormList());
        if (is_null($instance)) {
            $instance  = 1;
        }
        //creates an array that mirrors the structure of REDCap getData, but with booleans for all the field values which indicates which fields matched the provided values to check against
        //Reasoning is to possibly switch the functionality from AND only to OR, so we'd need to preserve all the individual matches
        foreach ($fields as $fieldName => $checkValues) {
            $fieldMeta = $project->getMetadata($fieldName);
            $isCheckbox = $fieldMeta->getElementType() === 'checkbox';
            if (!$project->isRepeating($fieldName) && array_key_exists($eventId, $recordData[$recordId])) {
                $actualValue = $recordData[$recordId][$eventId][$fieldName];
                $matches[$eventId][$fieldName] = $this->checkSingleValue($checkValues, $actualValue, $isCheckbox);
            } else if ($project->isRepeating($fieldName) && array_key_exists($eventId, $recordData[$recordId]['repeat_instances'])) {
                foreach ($recordData[$recordId]['repeat_instances'][$eventId] as $form => $instances) {
                    foreach ($instances as $instanceNum => $instanceFields) {
                        $actualValue = $instanceFields[$fieldName];
                        $matches['repeat_instances'][$eventId][$form][$instanceNum][$fieldName] = $this->checkSingleValue($checkValues, $actualValue, $isCheckbox);
                    }
                }
            } else {
                //If the field isn't in the current event then skip it
                continue;
            }
        }

        if ($repeating) {
            if ((count(array_unique($matches[$eventId])) === 1 && array_pop($matches[$eventId])) &&
                (count(array_unique($matches['repeat_instances'][$eventId][$formName][$instance])) === 1 && array_pop($matches['repeat_instances'][$eventId][$formName][$instance]))) {
//                    $notificationMessage = "Record ID: " . $logEntry['pk'] . "\nInstance: $instance\nForm Modified: $formName";
                $callback($recordId, $formName, $instance);
            } else {//Failed check on repeating values
            }
        } else {
            if (count(array_unique($matches[$eventId])) === 1 && array_pop($matches[$eventId])) {
                if (array_key_exists('repeat_instances', $matches)) {
                    foreach ($matches['repeat_instances'][$eventId] as $form => $instances) {
                        foreach ($instances as $instanceNum => $fieldMatches) {
                            if (count(array_unique($fieldMatches)) === 1 && array_pop($fieldMatches)) {
                                $callback($recordId, $formName, $instanceNum);
                            } else { //Failed check on non-repeating values
                            }
                        }
                    }
                } else {
                    $callback($recordId, $formName);
                }
            } else {//Failed a check against non-repeating values
            }
        }
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
     * @param Record $notification
     * @param $user
     * @param $message
     * @param null $pastDue
     */
    function saveNotification($notification, $user, $message, $pastDue = "")
    {
        $details = $notification->getDetails('notifications_complete');

        //if it's an array then get the max key. if it's not then the instance is 1
        $instance = is_array($details) ? max(array_keys($details)) + 1 : 1;
        $changes = [];
        $changes['notifications_complete'][$instance] = 0;
        $changes[$this->getProjectSetting("user-created")][$instance] = $user;
        $changes[$this->getProjectSetting("notif-date")][$instance] = date("Y-m-d H:i", time());

        $changes[$this->getProjectSetting("notif-context")][$instance] = $message;
        if ($pastDue != "") {
            $changes[$this->getProjectSetting("pastdue-date")][$instance] = date("Y-m-d", strtotime($changes[$this->getProjectSetting("notif-date")][$instance] . " + $pastDue days"));
        }
        $notification->updateDetails($changes);
        $notification->getDetails();
    }
}