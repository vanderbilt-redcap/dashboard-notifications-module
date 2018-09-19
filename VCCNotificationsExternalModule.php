<?php

namespace Vanderbilt\VCCNotificationsExternalModule;

require_once APP_PATH_DOCROOT . 'Classes/Files.php';
require_once 'base.php';
require_once 'functions.php';
require_once __DIR__ . '/../../plugins/Core/bootstrap.php';
require_once __DIR__ . '/../../plugins/Core/Libraries/Project.php';
require_once __DIR__ . '/../../plugins/Core/Libraries/RecordSet.php';
require_once __DIR__ . '/../../plugins/Core/Libraries/Record.php';

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;
use Plugin\Project;
use Plugin\Record;
use Plugin\RecordSet;


//TODO get 0-4 working initially
class VCCNotificationsExternalModule extends AbstractExternalModule
{
    private $notificationProject;

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
        $notificationProject = $this->getProjectSetting('notification_project');
        if ($notificationProject) {
            $this->notificationProject = new \Plugin\Project($notificationProject);
            $project                   = new \Plugin\Project($project_id);
            $lastEvent                 = $this->getProjectSetting('lastEvent') ? $this->getProjectSetting('lastEvent') : 0;

            $lastEvent = $this->getLogs($project, $lastEvent);
            $this->setProjectSetting('lastEvent', $lastEvent);
        }
    }

    function hook_module_link_check_display($link)
    {
        return true;
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
        $recordset     = new RecordSet($this->notificationProject, ['active' => '1', 'project' => $project->getProjectId(), RecordSet::getKeyComparatorPair('type', 'IN') => $this->notificationTypes[$logType]]);
        $notifications = $recordset->getRecords();
        /**
         * @var  $key
         * @var Record $notification
         */
        foreach ($notifications as $key => $notification) {
            echo "<pre>";var_dump($notification->getDetails('name'));echo "</pre>";
            $selectedtype = $notification->getDetails('type');
            $jsonOptions = json_decode($notification->getDetails('access'), true);
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
                    if (array_key_exists('production', $jsonOptions) && ($jsonOptions['production'] == '1' && $this->inProduction($project)) || ($jsonOptions['production'] == '0')) {
                        //form_name
                        $formNames           = $this->getKeyValuesFromLog($logEntry);
                        $notificationMessage = "Form created: {$formNames['form_name']}";
                    }
                    break;
                case 2: //New Field
                    if (array_key_exists('production', $jsonOptions) && ($jsonOptions['production'] == '1' && $this->inProduction($project)) || ($jsonOptions['production'] == '0')) {
                        $dataValues = $this->getKeyValuesFromLog($logEntry['data_values']);
                        $fieldMetaData = $project->getMetadata(trim(array_pop(array_keys($dataValues))));
                        $formName = $fieldMetaData->getFormName();
                        $notificationMessage   = "Field created: {$dataValues['field_name']} on form: $formName";
                    }
                    break;
                case 3: //New Comment/Data Query
                    //TODO allow for multiple fields
                    $comment = json_decode($logEntry['data_values'], true);
                    if (array_key_exists('fields', $jsonOptions) && in_array($comment['field'], $jsonOptions['fields'])) {
                        $notificationMessage = "Comment added to field {$comment['field']}: {$comment['comment']}";
                    }
                    break;
                case 4: //User rights modified
                    if (($logType === "Edit user" && array_key_exists('edit_user', $jsonOptions) && $jsonOptions['edit_user']) ||
                        ($logType === "Add user" && array_key_exists('add_user', $jsonOptions) && $jsonOptions['add_user'])) {
                        $response['changed_user'] = $logEntry['pk'];
                        $notificationMessage      = "User " . $logEntry['pk'] . " was " . ($logType === "Edit user" ? "edited" : "added");
                    }
                    break;
                case 5: //Field Data Check
                    if (array_key_exists('fields', $jsonOptions)) {
                        $this->checkRecordFields($project, $logEntry, $jsonOptions['fields'], function ($recordId, $formName=null, $instance=null) use ($notification, $user) {
                            $msg = "Record ID: $recordId\nForm Modified: $formName";
                            if ($instance) {
                                $msg .= "\nInstance: $instance";
                            }
                            $this->saveNotification($notification, $user, $msg);
                        });
                    }
                    break;
                case 6: //E-signature Required
                    if (array_key_exists('fields', $jsonOptions) && array_key_exists('past_due', $jsonOptions)) {
                        $pastDue = $jsonOptions['past_due'];
                        $this->checkRecordFields($project, $logEntry, $jsonOptions['fields'], function ($recordId, $formName = null, $instance = null) use ($notification, $user, $pastDue) {
                            $this->saveNotification($notification, $user, "Record ID: $recordId\n", $pastDue);
                        });
                    }
                    break;
                case 7: //Record Count
                    $recordId        = $logEntry['pk'];
                    if (array_key_exists('fields', $jsonOptions) && array_key_exists('repeat_count', $jsonOptions) && !in_array($recordId, $jsonOptions['record_history'])) {
                        $jsonOptions['record_history'][] = $recordId;
                        $matched = false;
                        $this->checkRecordFields($project, $logEntry, $jsonOptions['fields'], function ($recordId, $formName=null, $instance=null) use (&$matched) {
                            $matched = true;
                        });
                        if ($matched) {
                            $notification->updateDetails(['access' => json_encode($jsonOptions)]);
                            if (count($jsonOptions['record_history']) % $jsonOptions['repeat_count'] === 0) {
                                $this->saveNotification($notification, $user, "");
                            }
                        }
                    }
                    break;
            }
            if (!empty($notificationMessage)) {
                $this->saveNotification($notification, $logEntry['user'], $notificationMessage);
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
    function saveNotification($notification, $user, $message, $pastDue = null)
    {
        $details = $notification->getDetails('notifications_complete');

        //if it's an array then get the max key. if it's not then the instance is 1
        $instance = is_array($details) ? max(array_keys($details)) + 1 : 1;
        $changes                                      = [];
        $changes['notifications_complete'][$instance] = 0;
        $changes['user_created'][$instance]           = $user;
        $changes['notification_date'][$instance]      = date("Y-m-d H:i", time());

        $changes['notification_context'][$instance] = $message;
        if ($pastDue) {
            $changes['past_due_date'][$instance] = date("Y-m-d", strtotime($changes['notification_date'][$instance] . " + $pastDue days"));
        }
        echo "\nNotification Saved!\n";

        $notification->updateDetails($changes);
    }
}
