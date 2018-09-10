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

class DashboardNotificationsExternalModule extends AbstractExternalModule
{
    function redcap_module_link_check_display($project_id, $link, $record, $instrument, $instance, $page) {
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

    function processNotifSettings($jsonSettings) {

    }
}