<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 8/22/2018
 * Time: 1:32 PM
 */

include_once('base.php');
$projectID = $_REQUEST['pid'];
$notifProjectID = $module->getProjectSetting("notif-project");
$notifCount = $_POST['div_count'];
$returnHTML = "<div class='col-md-12' style='display:inline-block;' id='notification_".$notifCount."'>";
if (isset($_POST['notif_record']) && is_numeric($projectID) && is_numeric($notifProjectID)) {
    $recordID = "";
    $notifProject = new \Project($notifProjectID);
    $sourceProject = new \Project($projectID);

    //$notifProject->project['secondary_pk'];
    $notifMetaData = $notifProject->metadata;
    $sourceMetaData = $sourceProject->metadata;
    /*echo "<pre>";
    print_r($notifMetaData);
    echo "</pre>";*/

    $repeatProjects = $_POST['repeatable_project'];
    $userRoles = \UserRights::getRoles();
    $notifRecord = db_real_escape_string($_POST['notif_record']);
    $newNotifName = db_real_escape_string($_POST['new_name']);
    if ($notifRecord != 'new' && $newNotifName == "") {
        $recordID = $notifRecord;
    }
    if ($recordID != "") {
        try {
            $recordData = \Records::getData($notifProjectID, 'array', array($recordID));

            foreach ($recordData as $recordID => $eventData) {
                foreach ($eventData as $eventID => $fieldData) {
                    if ($eventID == "repeat_instances") {
                        continue;
                    }
                    else {
                        if ($newNotifName != "") {
                            $notifName = $newNotifName;
                        }
                        else {
                            $notifName = $fieldData[$module->getProjectSetting("notif-name")];
                        }
                        $notifSettings = json_decode($fieldData[$module->getProjectSetting("access-json")],true);
                        $scheduleSettings = json_decode($fieldData[$module->getProjectSetting("schedule-json")],true);
                        $notifType = $fieldData[$module->getProjectSetting("notif-type")];
                        $notifAlert = $fieldData[$module->getProjectSetting("notif-alert")];
                        $notifClass = $fieldData[$module->getProjectSetting("notif-class")];
                        $notifPriority = $fieldData[$module->getProjectSetting("notif-priority")];
                        $receiveData = json_decode($fieldData[$module->getProjectSetting("role-list")],true);
                        $resolveData = json_decode($fieldData[$module->getProjectSetting("role-resolve")],true);
                        $roleList = $module->transferRoleIDsBetweenProjects($receiveData["roles"],$projectID);
                        $receiveFields = $receiveData["fields"];
                        $roleResolve = $module->transferRoleIDsBetweenProjects($resolveData["roles"],$projectID);
                        $resolveFields = $resolveData["fields"];
                        $notifActive = $fieldData[$module->getProjectSetting("notif-active")];
                    }
                }
            }
        }
        catch (Exception $e) {
            echo $e->getMessage()."<br/>";
        }
    }

    if ($_POST['new_name'] != "") $notifName = db_real_escape_string($_POST['new_name']);
    if ($repeatProjects) {
        $projectList = $module->getProjects(USERID);
        echo "<div class='col-md-12' style='display:inline-block;padding:5px;' id='projectlistdiv'>
            <div class='col-md-3'><input type='button' value='Add Project' onclick='addProjectDiv(\"projectlistdiv\",\"source-projects\");'/></div>";
        echo "<div class='col-md-9'><select class='select2-drop' style='width:75%;text-overflow:ellipsis;' id='source-projects' name='projectlist[]'><option value=''></option>";
        foreach($projectList as $projectID => $projectName) {
            if ($projectID != "" && $projectName != "") {
                echo "<option value='$projectID'>$projectName</option>";
            }
        }
        echo "</select></div>";
        echo "</div>";
    }
    $returnHTML .= "<div style='padding: 3px;background-color:lightgreen;border:1px solid;'>Notification Name: <input name='".$module->getProjectSetting("notif-name")."' id='".$module->getProjectSetting("notif-name")."' type='text' value='".$notifName."' /></div>
                <div style='padding: 3px;background-color:lightgreen;border:1px solid;'><span class='notif' style='display:inline-block;'>Active Notification?</span><span class='notif' style='display:inline-block;'><input name='".$module->getProjectSetting("notif-active")."' type='radio' value='0' ".($notifActive == "0" ? "checked" : "")."/>No<br/><input name='".$module->getProjectSetting("notif-active")."' type='radio' value='1' ".($notifActive == "1" ? "checked" : "")."/>Yes</span></div>
                <div style='padding: 3px;background-color:lightgreen;border:1px solid;'>Notification Type: <select class='select2-drop' onchange='populateUserFields(\"".$module::ROLES_RECEIVE."\",\"".$module::ROLES_RECEIVE."_".$module::ROLES_FIELDS."\");populateUserFields(\"".$module::ROLES_RESOLVE."\",\"".$module::ROLES_RESOLVE."_".$module::ROLES_FIELDS."\");populateNotifSettings(this.options[this.selectedIndex].value, \"notif_settings\");' name='".$module->getProjectSetting("notif-type")."' id='".$module->getProjectSetting("notif-type")."'>";
    $notifTypeChoices = $module->getChoicesFromMetaData($notifMetaData[$module->getProjectSetting("notif-type")]['element_enum']);
    //$roleListChoices = getChoicesFromMetaData($notifMetaData[$module->getProjectSetting("role-list")]['element_enum']);
    foreach ($notifTypeChoices as $raw => $label) {
        $returnHTML .= "<option value='$raw' ".($notifType == $raw ? "selected=\"selected\"" : "").">$label</option>";
    }
    $returnHTML .= "</select></div>
    <div style='padding: 3px;background-color:lightgreen;border:1px solid;'>Notification Classification: <select style='min-width:125px;' class='select2-drop' name='".$module->getProjectSetting("notif-class")."' id='".$module->getProjectSetting("notif-class")."'>";
    $notifClassChoice = $module->getChoicesFromMetaData($notifMetaData[$module->getProjectSetting("notif-class")]['element_enum']);
    foreach ($notifClassChoice as $raw => $label) {
        $returnHTML .= "<option value='$raw' ".($notifClass == $raw ? "selected=\"selected\"" : "").">$label</option>";
    }
    $returnHTML.= "</select></div>
        <div style='padding: 3px;background-color:lightgreen;border:1px solid;'>Notification Priority: <select class='select2-drop'name='".$module->getProjectSetting("notif-priority")."' id='".$module->getProjectSetting("notif-priority")."' >";
    $notifPriorityChoice = $module->getChoicesFromMetaData($notifMetaData[$module->getProjectSetting("notif-priority")]['element_enum']);
    foreach ($notifPriorityChoice as $raw => $label) {
        $returnHTML .= "<option value='$raw' ".($notifPriority == $raw ? "selected=\"selected\"" : "").">$label</option>";
    }
        $returnHTML .= "</select></div>
        <div style='padding: 3px;background-color:lightgreen;border:1px solid;'><div style='vertical-align:top;'>Notification Alert Text:</div> <textarea rows='5' cols='75' name='".$module->getProjectSetting("notif-alert")."'>$notifAlert</textarea></div>
    <div id='accordion'>
        <div><h3><a style='display:inline-block;padding-left:20px;' href='#'>User Roles to Receive This Notification</a></h3><div>
        <table><tr>";
        $roleCheckCount = 1;
        foreach ($userRoles as $roleID => $roleData) {
            $returnHTML .= "<td><span class='notif'><input type='checkbox' id='".$module::ROLES_RECEIVE."_".$module::ROLES_LIST."_$roleID' name='".$module::ROLES_RECEIVE."_".$module::ROLES_LIST."[]' value='$roleID' ".(in_array($roleID,$roleList) ? "checked" : "")." /></span><span class='notif'>".$roleData['role_name']."</span></td>";
            if ($roleCheckCount % 3 == 0) {
                $returnHTML .= "</tr><tr>";
                $roleCheckCount = 1;
            }
            else {
                $roleCheckCount++;
            }
        }

    $returnHTML .= "</tr></table></div></div>
        <div><h3><a style='display:inline-block;padding-left:20px;' href='#'>Fields to Track Users to Receive This Notification</a></h3>
            <div id='".$module::ROLES_RECEIVE."_".$module::ROLES_FIELDS."'></div>
        </div>
        <div><h3><a style='display:inline-block;padding-left:20px;' href='#'>User Roles to Resolve this Notification</a></h3><div>
        <table><tr>";
    $roleCheckCount = 1;
    foreach ($userRoles as $roleID => $roleData) {
        $returnHTML .= "<td><span class='notif'><input type='checkbox' id='".$module::ROLES_RESOLVE."_".$module::ROLES_LIST."_$roleID' name='".$module::ROLES_RESOLVE."_".$module::ROLES_LIST."[]' value='$roleID' ".(in_array($roleID,$roleResolve) ? "checked" : "")." /></span><span class='notif'>".$roleData['role_name']."</span></td>";
        if ($roleCheckCount % 3 == 0) {
            $returnHTML .= "</tr><tr>";
            $roleCheckCount = 1;
        }
        else {
            $roleCheckCount++;
        }
    }
    $returnHTML .= "</tr></table></div></div>
        <div><h3><a style='display:inline-block;padding-left:20px;' href='#'>Fields to Track Users to Resolve This Notification</a></h3>
            <div id='".$module::ROLES_RESOLVE."_".$module::ROLES_FIELDS."'></div>
        </div>
        <div><h3><a style='display:inline-block;padding-left:20px;' href='#'>Schedule for Repeated Notification Generation</a></h3>
            <div id='schedule_settings'>
                <div class='col-md-12'>Set Start Date: <input type='text' name='".$module::SCHEDULE_START_DATE."' id='".$module::SCHEDULE_START_DATE."' value='".$scheduleSettings[$module::SCHEDULE_START_DATE]."'/> <input type='text' name='".$module::START_TIMEFRAME."' id='".$module::START_TIMEFRAME."' value='".$scheduleSettings[$module::START_TIMEFRAME]."' /> <span style='font-size:75%;'>(Days, weeks, months, or a field name only valid options)</span></div>
                <div class='col-md-12'>Set End Date: <input type='text' name='".$module::SCHEDULE_END_DATE."' id='".$module::SCHEDULE_END_DATE."' value='".$scheduleSettings[$module::SCHEDULE_END_DATE]."'/> <input type='text' name='".$module::END_TIMEFRAME."' id='".$module::END_TIMEFRAME."' value='".$scheduleSettings[$module::END_TIMEFRAME]."' /> <span style='font-size:75%;'>(Days, weeks, months, or a field name only valid options)</span></div>
                <div class='col-md-12'>Delay Between Notifications: <input type='text' name='".$module::SCHEDULE_COUNT."' id='".$module::SCHEDULE_COUNT."' value='".$scheduleSettings[$module::SCHEDULE_COUNT]."'/> <input type='text' name='".$module::SCHEDULE_TIMEFRAME."' id='".$module::SCHEDULE_TIMEFRAME."' value='".$scheduleSettings[$module::SCHEDULE_TIMEFRAME]."' /> <span style='font-size:75%;'>(Days, weeks, months, or a field name only valid options)</span></div>
            </div>
        </div>
        <div><h3><a style='display:inline-block;padding-left:20px;' href='#'>Notification Settings</a></h3>
                    <div id='notif_settings'>
                    </div>
                </div>
                <div class='col-md-12'><input type='submit' value='Save Notification' name='notif_save' /></div>
                </div>
                <input type='hidden' value='$recordID' name='".$notifProject->table_pk."'/>";
}
$returnHTML .= "</div>";
echo $returnHTML;

/* Parses choices from multiple choice questions in to an array. Raw value -> Label
 *
 * @param string
*/

?>
<style>
    table {
        width:100%;
    }

    td {
        padding:3px;
    }

    span.notif {
        padding-right:5px;
    }
</style>
<script>
    var projectFormList = {};
    <?php
        foreach (array_keys($sourceProject->forms) as $key) {
            echo "projectFormList['$key'] = '".cleanJavaString($sourceProject->forms[$key]['menu'])."';";
        }
    ?>
    var projectFieldList = {};
    var fieldValueList = {};
    var notificationSettings = jQuery.parseJSON('<?= json_encode($notifSettings) ?>');
    //console.log(notificationSettings);
    <?php
    foreach ($sourceMetaData as $fieldName => $fieldMeta) {
        echo "projectFieldList['$fieldName'] = '" . cleanJavaString($fieldMeta['element_label']) . "';";
        echo "fieldValueList['$fieldName'] = {};";
        if (in_array($fieldMeta['element_type'], array("radio", "select", "checkbox", "yesno", "truefalse"))) {
            if ($fieldMeta['element_type'] == "truefalse") {
                echo "fieldValueList['$fieldName']['0'] = 'False';";
                echo "fieldValueList['$fieldName']['1'] = 'True';";
            } elseif ($fieldMeta['element_type'] == "yesno") {
                echo "fieldValueList['$fieldName']['0'] = 'No';";
                echo "fieldValueList['$fieldName']['1'] = 'Yes';";
            } else {
                $fieldChoices = $module->getChoicesFromMetaData($fieldMeta['element_enum']);
                foreach ($fieldChoices as $raw => $label) {
                    echo "fieldValueList['$fieldName']['$raw'] = '".cleanJavaString($label)."';";
                }
            }
        }
    }
    ?>

    function populateUserFields(userType, destination) {
        $('#'+destination).html('');
        var divHTML = "";
        if (userType == 'receive') {
            divHTML = generateRepeatableFieldList(userType+'_repeat',userType+'_fields_',userType,'field_options','','Field Name Containing UserNames: ');;
        }
        else if (userType == 'resolve') {
            divHTML = generateRepeatableFieldList(userType+'_repeat',userType+'_fields_',userType,'field_options','','Field Name Containing UserNames: ');;
        }
        $('#'+destination).html(divHTML).css({'width':'auto','height':'auto'});
    }

    function populateNotifSettings(selectValue, destination) {
        $('#'+destination).html('');
        var divHTML = "";
        if (selectValue == "1") {
            divHTML = "<div style='col-md-12'><div>Trigger notification only if REDCap project is in Production Status</div><div><input type='radio' name='<?= $module::PROJ_PROD_SETTING ?>' value='0' <?= ($notifSettings[$module::PROJ_PROD_SETTING] == "0" ? "checked" : "") ?>>No<br/><input type='radio' name='<?= $module::PROJ_PROD_SETTING ?>' value='1' <?= ($notifSettings[$module::PROJ_PROD_SETTING] == "1" ? "checked" : "") ?>>Yes</div></div>";
        }
        else if (selectValue == "2") {
            divHTML = generateRepeatableFormList('Form to Monitor for New Fields (leave blank to monitor all forms):');
            divHTML += "<div style='col-md-12'><div>Trigger notification only if REDCap project is in Production Status</div><div><input type='radio' name='<?= $module::PROJ_PROD_SETTING ?>' value='0' <?= ($notifSettings[$module::PROJ_PROD_SETTING] == "0" ? "checked" : "") ?>>No<br/><input type='radio' name='<?= $module::PROJ_PROD_SETTING ?>' value='1' <?= ($notifSettings[$module::PROJ_PROD_SETTING] == "1" ? "checked" : "") ?>>Yes</div></div>";
        }
        else if (selectValue == "3") {
            divHTML = generateFieldList('fields_','<?= $module::FIELD_NAME_SETTING ?>','field_options','<?= $module::FIELD_VALUE_REQUIRED ?>','Field Name to Trigger Notification: ');
        }
        else if (selectValue == "4") {
            divHTML = "<div class='col-md-12'><span class='notif'><input type='checkbox' name='<?= $module::USER_NEW_SETTING ?>' value='1' <?= ($notifSettings[$module::USER_NEW_SETTING] == "1" ? "checked" : "") ?> /></span><span class='notif'>Trigger notification when a new user is added to the project</span></div><div class='col-md-12'><span class='notif'><input type='checkbox' name='<?= $module::USER_EDIT_SETTING ?>' value='1' <?= ($notifSettings[$module::USER_EDIT_SETTING] == "1" ? "checked" : "") ?> /></span><span class='notif'>Trigger notification when a user's rights are edited</span></div>";
        }
        else if (selectValue == "5") {
            divHTML = generateRepeatableFieldList('field_repeat','fields_','<?= $module::FIELD_NAME_SETTING ?>','field_options','<?= $module::FIELD_VALUE_REQUIRED ?>','Field Name to Trigger Notification: ');
        }
        else if (selectValue == "6") {
            divHTML = generateRepeatableFieldList('field_repeat','fields_','<?= $module::FIELD_NAME_SETTING ?>','field_options','<?= $module::FIELD_VALUE_REQUIRED ?>','Field Name to Trigger Notification: ');
        }
        else if (selectValue == "7") {
            divHTML = generateRepeatableFieldList('field_repeat','fields_','<?= $module::FIELD_NAME_SETTING ?>','field_options','<?= $module::FIELD_VALUE_REQUIRED ?>','Field Name to Trigger Notification: ');
            divHTML += "<div class='col-md-12' id='<?= $module::RECORD_COUNT_SETTING ?>' style='padding-top:10px;'><span class='notif' style='display:inline-block;'>Records to Match to Trigger Notification</span><span class='notif' style='display:inline-block;'><input type='text' name='record_count' value='<?= $notifSettings['record_count'] ?>' /></span></div>";
        }
        else if (selectValue == "8") {
            divHTML = generateRepeatableFormList('Form to Monitor (leave blank to monitor all forms):');
        }
        divHTML += "<div class='col-md-12' style='padding-top:10px;'><span class='notif' style='display:inline-block;'>Time to Delay Notification (leave blank if not applicable)? </span><span class='notif' style='display:inline-block;'><input name='<?= $module::DISPLAY_DATE_SETTING ?>' id='<?= $module::DISPLAY_DATE_SETTING ?>' type='text' /> <input type='text' id='<?= $module::DISPLAY_TIMEFRAME ?>' name='<?= $module::DISPLAY_TIMEFRAME ?>' value='<?= $notifSettings[$module::DISPLAY_TIMEFRAME] ?>' /></span> <span style='font-size:75%;'>(Days, weeks, months, or a field name only valid options)</span></div>";
        divHTML += "<div class='col-md-12' style='padding-top:10px;'><span class='notif' style='display:inline-block;'>Time Until Notification is Past Due (leave blank if not applicable)</span><span class='notif' style='display:inline-block;'><input type='text' id='<?= $module::PASTDUE_SETTING ?>' name='<?= $module::PASTDUE_SETTING ?>' /> <input type='text' id='<?= $module::PASTDUE_TIMEFRAME ?>' name='<?= $module::PASTDUE_TIMEFRAME ?>' value='<?= $notifSettings[$module::PASTDUE_TIMEFRAME] ?>' /></span><span style='font-size:75%;'>(Days, weeks, months, or a field name only valid options)</span></div>";
        divHTML += "<div style='padding: 3px;background-color:lightgreen;border:1px solid;'><span class='notif' style='display:inline-block;'>Unique User Notifications? </span><span class='notif' style='display:inline-block;'><input name='<?= $module::UNIQUE_USER_SETTING ?>' id='<?= $module::UNIQUE_USER_SETTING ?>' type='radio' value='0' <?= ($notifSettings[$module::UNIQUE_USER_SETTING] == "0" ? "checked" : "") ?>/>No<br/><input name='<?= $module::UNIQUE_USER_SETTING ?>' id='<?= $module::UNIQUE_USER_SETTING ?>' type='radio' value='1' <?= ($notifSettings[$module::UNIQUE_USER_SETTING] == "1" ? "checked" : "") ?>/>Yes</span></div>";
        $('#'+destination).html(divHTML).css({'width':'auto','height':'auto'});
        loadNotifSettings();
        convertSelect2();
    }
    function loadFieldOptions(select_field, destination, record_id, count) {
        var nameValue = select_field.value;
        var sourceName = select_field.name;
        var returnHTML = "";
        var fieldValueCount = 0;
        if (Object.keys(fieldValueList[nameValue]).length > 0) {
            returnHTML += "<table><tr>";
            for (var key in fieldValueList[nameValue]) {
                returnHTML += "<td><span class='notif'><input type='checkbox' id='<?= $module::FIELD_VALUE_SETTING ?>_"+count+"_"+fieldValueCount+"' name='<?= $module::FIELD_VALUE_SETTING ?>_"+count+"[]' value='"+key+"' ";
                if (notificationSettings != null && jQuery.type(notificationSettings['<?= $module::FIELD_VALUE_SETTING ?>']) !== "undefined") {
                    if ($.inArray(key, notificationSettings['<?= $module::FIELD_VALUE_SETTING ?>'][count]) !== -1) {
                        returnHTML += "checked";
                    }
                }
                returnHTML += "/></span><span class='notif'>"+fieldValueList[nameValue][key]+"</span></td>"
                fieldValueCount++;
                if (fieldValueCount % 3 == 0) {
                    returnHTML += "</tr><tr>";
                }
            }
            returnHTML += "</tr></table>";
        }
        else {
            returnHTML += "<span class='notif'><input type='text' id='<?= $module::FIELD_VALUE_SETTING ?>_"+count+"_"+fieldValueCount+"' name='<?= $module::FIELD_VALUE_SETTING ?>_"+count+"' ";
            if (notificationSettings != null && jQuery.type(notificationSettings['<?= $module::FIELD_VALUE_SETTING ?>']) !== "undefined") {
                if ("0" in notificationSettings['<?= $module::FIELD_VALUE_SETTING ?>'][count]) {
                    returnHTML += "value='" + notificationSettings['<?= $module::FIELD_VALUE_SETTING ?>'][count]["0"] + "'";
                }
            }
            returnHTML += "/></span>";
        }
        $('#'+destination).html(returnHTML);
    }

    function addProjectDiv(divid,projectlistdiv) {
        var optionText = "<div class='col-md-3'></div><div class='col-md-9'><select class='select2-drop' style='width:75%;text-overflow:ellipsis;' name='projectlist[]'>";
        jQuery('#'+projectlistdiv).find('option').each(function () {
            optionText += "<option value='"+jQuery(this).val()+"'>"+jQuery(this).text()+"</option>";
        })
        optionText += "</select></div>";
        jQuery('#'+divid).append(optionText);
        convertSelect2();
    }

    function generateRepeatableFieldList(repeatDivID, fieldListID, selectFieldID, fieldOptionsID, fieldOptionsRequired, fieldLabel) {
        return "<div class='col-md-11' id='"+repeatDivID+"'>"+generateFieldList(fieldListID,selectFieldID,fieldOptionsID,fieldOptionsRequired,fieldLabel)+"</div><div class='col-md-1'><button id='add_"+repeatDivID+"' onclick='addNewDiv(\""+repeatDivID+"\",function() { return generateFieldList(\""+fieldListID+"\",\""+selectFieldID+"\",\""+fieldOptionsID+"\",\""+fieldOptionsRequired+"\",\""+fieldLabel+"\") });' type='button'>Add Field</button></div>";
    }

    function generateRepeatableFormList(label) {
        return "<div class='col-md-11' id='form_repeat'>"+generateFormList(label)+"</div><div class='col-md-1'><button id='add_form' onclick='addNewDiv(\"form_repeat\",generateFormList);' type='button'>Add Form</button></div>";
    }

    function generateFieldList(fieldListID, selectFieldID, fieldOptionsID, fieldOptionsRequired, fieldLabel) {
        var count = getNewCount(fieldListID);
        var returnHTML = "<div id='"+fieldListID+count+"'><table style='border: 1px solid'><tr style='background-color:lightblue;'><td><button type='button' onclick='removeDiv(\""+fieldListID+count+"\");'>X</button></td><td><div style='padding:3px;'><span class='notif'>"+fieldLabel+"</span><span class='notif'><select class='select2-drop' style='width:350px;text-overflow: ellipsis;' ";
        if (fieldOptionsRequired != "") {
            returnHTML += "onchange = 'loadFieldOptions(this,\""+fieldOptionsID+count+"\",\"<?=$recordID?>\",\""+count+"\");' ";
        }
        returnHTML += "id='"+selectFieldID+"_"+count+"' name='"+selectFieldID+"[]'><option value=''></option>";
        for (var key in projectFieldList) {
            returnHTML += "<option value='"+key+"'>("+key+") -- "+projectFieldList[key]+"</option>";
        }

        returnHTML += "</select></span></div></td><td>";
        if (fieldOptionsRequired != "") {
            returnHTML += "<input type='checkbox' name='" + fieldOptionsRequired + "_"+count+"' id='" + fieldOptionsRequired + "_"+count+"' value='1' onclick='hideShowNewNotif(this,\""+fieldOptionsID+count+"\")'>Requires specific value";
        }
        returnHTML += "</td></tr>";
        if (fieldOptionsRequired != "") {
            returnHTML += "<tr><td></td><td><div style='display:none;' id='" + fieldOptionsID + count + "'></div></td><td></td></tr>";
        }
        returnHTML += "</table></div>";
        return returnHTML;
    }

    function generateFormList(label = "Form to Monitor for New Fields (leave blank to monitor all forms):") {
        var count = getNewCount('forms_');
        var returnHTML = "<div id='forms_"+count+"'><table style='border: 1px solid'><tr><td><button type='button' onclick='removeDiv(\"forms_"+count+"\");'>X</button></td><td style='background-color:lightblue;'><span class='notif'>"+label+"</span></td><td><span class='notif'><select class='select2-drop' style='width:350px;text-overflow: ellipsis;' id='<?= $module::FORM_NAME_SETTING ?>_"+count+"' name='<?= $module::FORM_NAME_SETTING ?>[]'><option value=''></option>";

        for (var key in projectFormList) {
            returnHTML += "<option value='"+key+"'>"+projectFormList[key]+"</option>";
        }
        returnHTML += "</select></span></td></tr></table></div>";
        return returnHTML;
    }

    function addNewDiv(destination,functionCallback) {
        $('#'+destination).append(functionCallback());
        convertSelect2();
    }

    function removeDiv(divID) {
        $('#'+divID).remove();
    }

    function convertSelect2() {
        $('.select2-drop').select2();
    }

    function getNewCount(idToCheck) {
        var count = 0;
        while ($('#'+idToCheck+count).length) {
            count++;
        }
        return count;
    }

    function loadNotifSettings() {
        <?php
            $index = 0;
            foreach ($notifSettings[$module::FIELD_NAME_SETTING] as $fieldName => $fieldValues) {
                if ($fieldName == "") continue;
                if (!in_array($fieldName, array_keys($sourceMetaData))) continue;
                if ($index > 0) {
                    echo "$('#add_field_repeat').trigger('click');";
                }
                echo "$('#".$module::FIELD_NAME_SETTING."_$index').val('$fieldName').change();";
                if (in_array($sourceMetaData[$fieldName]['element_type'],array("select","radio","checkbox","yesno","truefalse"))) {
                    foreach ($fieldValues as $count => $value) {
                        echo "$('[id^=".$module::FIELD_VALUE_SETTING."_".$index."_]:input[value=\"$value\"]').prop(\"checked\",true);";
                    }
                }
                else {
                    foreach ($fieldValues as $count => $value) {
                        if ($count > 0) {
                            echo "addNewDiv('field_repeat',function() { return generateFieldList('fields_','".$module::FIELD_NAME_SETTING."','field_options','".$module::FIELD_VALUE_REQUIRED."','Field Name to Trigger Notification: ')});";
                        }
                        echo "$(\"#".$module::FIELD_VALUE_SETTING."_".$index."_".$count."\").val('".$value."');";
                    }
                }
                if (!empty($fieldValues)) {
                    echo "console.log(\"#".$module::FIELD_VALUE_REQUIRED."_".$index."\");";
                    echo "$(\"#".$module::FIELD_VALUE_REQUIRED."_".$index."\").click();";
                }
                $index++;
            }
            //TODO For each foreach below, need to make sure the form/field exists on this project, keep indexing of loop separate from the actual count in the array
            foreach ($notifSettings[$module::FORM_FIELD_SETTING] as $count => $value) {
                if ($count > 0) {
                    echo "addNewDiv('form_repeat',generateFormList);";
                }
                echo "$(\"#".$module::FORM_NAME_SETTING."_".$count."\").val('".$value."');";
            }
            foreach ($receiveFields as $count => $value) {
                if ($count > 0) {
                    echo "addNewDiv('".$module::ROLES_RECEIVE."_repeat',function() { return generateFieldList('".$module::ROLES_RECEIVE."_fields_','".$module::ROLES_RECEIVE."','field_options','','Field Name Containing UserNames: ') });";
                }
                echo "$(\"#".$module::ROLES_RECEIVE."_".$count."\").val('".$value."');";
            }
            foreach ($resolveFields as $count => $value) {
                if ($count > 0) {
                    echo "addNewDiv('".$module::ROLES_RESOLVE."_repeat',function() { return generateFieldList('".$module::ROLES_RESOLVE."_fields_','".$module::ROLES_RESOLVE."','field_options','','Field Name Containing UserNames: ') });";
                }
                echo "$(\"#".$module::ROLES_RESOLVE."_".$count."\").val('".$value."');";
            }
        ?>
        $('#<?= $module::PASTDUE_SETTING ?>').val('<?= $notifSettings[$module::PASTDUE_SETTING] ?>');
        $('#<?= $module::DISPLAY_DATE_SETTING ?>').val('<?= $notifSettings[$module::DISPLAY_DATE_SETTING] ?>');
    }

    $(document).ready(function() {
        $('#<?=$module->getProjectSetting("notif-type")?>').change();
        convertSelect2();
        //loadNotifSettings();
    });
</script>
<?php
function cleanJavaString($junkstring) {
    $junkstring = (strlen($junkstring) > 30 ? substr($junkstring,0,25)."..." : $junkstring);
    return trim(preg_replace('/\s+/',' ',str_replace("'","\"",strip_tags($junkstring))));
}
?>