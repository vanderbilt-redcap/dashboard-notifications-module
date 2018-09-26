<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 8/22/2018
 * Time: 1:32 PM
 */

include_once('base.php');
$projectID = $_GET['pid'];
$notifProjectID = $module->getProjectSetting("notif-project");
$returnHTML = "";
if (isset($_POST['notif_record']) && isset($_POST['new_name']) && is_numeric($projectID) && is_numeric($notifProjectID)) {
    $recordID = "";
    $notifProject = new \Project($notifProjectID);
    $sourceProject = new \Project($projectID);

    //$notifProject->project['secondary_pk'];
    $notifMetaData = $notifProject->metadata;
    $sourceMetaData = $sourceProject->metadata;
    /*echo "<pre>";
    print_r($notifMetaData);
    echo "</pre>";*/

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
                        $notifType = $fieldData[$module->getProjectSetting("notif-type")];
                        $notifAlert = $fieldData[$module->getProjectSetting("notif-alert")];
                        $notifClass = $fieldData[$module->getProjectSetting("notif-class")];
                        $roleList = explode(",",$fieldData[$module->getProjectSetting("role-list")]);
                        $roleResolve = explode(",",$fieldData[$module->getProjectSetting("role-resolve")]);
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
    $returnHTML .= "<div style='padding: 3px;background-color:lightgreen;border:1px solid;'>Notification Name: <input name='".$module->getProjectSetting("notif-name")."' id='".$module->getProjectSetting("notif-name")."' type='text' value='".$notifName."' /></div>
                <div style='padding: 3px;background-color:lightgreen;border:1px solid;'><span style='display:inline-block;'>Active Notification?</span><span style='display:inline-block;'><input name='".$module->getProjectSetting("notif-active")."' type='radio' value='0' ".($notifActive == "0" ? "checked" : "")."/>No<br/><input name='".$module->getProjectSetting("notif-active")."' type='radio' value='1' ".($notifActive == "1" ? "checked" : "")."/>Yes</span></div>
                <div style='padding: 3px;background-color:lightgreen;border:1px solid;'>Notification Type: <select onchange='populateNotifSettings(this, \"notif_settings\");' name='".$module->getProjectSetting("notif-type")."' id='".$module->getProjectSetting("notif-type")."'>";
    $notifTypeChoices = $module->getChoicesFromMetaData($notifMetaData[$module->getProjectSetting("notif-type")]['element_enum']);
    //$roleListChoices = getChoicesFromMetaData($notifMetaData[$module->getProjectSetting("role-list")]['element_enum']);
    foreach ($notifTypeChoices as $raw => $label) {
        $returnHTML .= "<option value='$raw' ".($notifType == $raw ? "selected=\"selected\"" : "").">$label</option>";
    }
    $returnHTML .= "</select></div>
    <div style='padding: 3px;background-color:lightgreen;border:1px solid;'>Notification Classification: <select name='".$module->getProjectSetting("notif-class")."' id='".$module->getProjectSetting("notif-class")."'>";
    $notifClassChoice = $module->getChoicesFromMetaData($notifMetaData[$module->getProjectSetting("notif-class")]['element_enum']);
    foreach ($notifClassChoice as $raw => $label) {
        $returnHTML .= "<option value='$raw' ".($notifClass == $raw ? "selected=\"selected\"" : "").">$label</option>";
    }
    $returnHTML.= "</select></div>
        <div style='padding: 3px;background-color:lightgreen;border:1px solid;'><div style='vertical-align:top;'>Notification Alert Text:</div> <textarea rows='5' cols='75' name='".$module->getProjectSetting("notif-alert")."'>$notifAlert</textarea></div>
                <div id='accordion'><div><h3><a style='display:inline-block;padding-left:20px;' href='#'>User Roles to Receive This Notification</a></h3><div>
    <table><tr>";
    $roleCheckCount = 1;
    foreach ($userRoles as $roleID => $roleData) {
        $returnHTML .= "<td><span><input type='checkbox' id='role_notif_$roleID' name='".$module->getProjectSetting("role-list")."[]' value='$roleID' ".(in_array($roleID,$roleList) ? "checked" : "")." /></span><span>".$roleData['role_name']."</span></td>";
        if ($roleCheckCount % 3 == 0) {
            $returnHTML .= "</tr><tr>";
            $roleCheckCount = 1;
        }
        else {
            $roleCheckCount++;
        }
    }
    $returnHTML .= "</tr></table></div></div>
        <div><h3><a style='display:inline-block;padding-left:20px;' href='#'>User Roles to Resolve this Notification</a></h3><div>
        <table><tr>";
    $roleCheckCount = 1;
    foreach ($userRoles as $roleID => $roleData) {
        $returnHTML .= "<td><span><input type='checkbox' id='role_resolve_$roleID' name='".$module->getProjectSetting("role-resolve")."[]' value='$roleID' ".(in_array($roleID,$roleResolve) ? "checked" : "")." /></span><span>".$roleData['role_name']."</span></td>";
        if ($roleCheckCount % 3 == 0) {
            $returnHTML .= "</tr><tr>";
            $roleCheckCount = 1;
        }
        else {
            $roleCheckCount++;
        }
    }
    $returnHTML .= "</tr></table></div></div>
        <div><h3><a style='display:inline-block;padding-left:20px;' href='#'>Notification Settings</a></h3>
                    <div id='notif_settings'>
                    </div>
                </div>
                <div class='col-md-12'><input type='submit' value='Save Notification' name='notif_save' /></div>
                </div>
                <input type='hidden' value='$recordID' name='".$notifProject->table_pk."'/>";
}

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

    span {
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

    function populateNotifSettings(select_field, destination) {
        var selectValue = select_field.value;
        $('#'+destination).html('');
        var divHTML = "";
        if (selectValue == "1") {
            divHTML = "<div style='col-md-12'><div>Trigger notification only if REDCap project is in Production Status</div><div><input type='radio' name='<?= $module::PROJ_PROD_SETTING ?>' value='0' <?= ($notifSettings[$module::PROJ_PROD_SETTING] == "0" ? "checked" : "") ?>>No<br/><input type='radio' name='<?= $module::PROJ_PROD_SETTING ?>' value='1' <?= ($notifSettings[$module::PROJ_PROD_SETTING] == "1" ? "checked" : "") ?>>Yes</div></div>";
        }
        else if (selectValue == "2") {
            divHTML = generateRepeatableFormList();
            divHTML += "<div style='col-md-12'><div>Trigger notification only if REDCap project is in Production Status</div><div><input type='radio' name='<?= $module::PROJ_PROD_SETTING ?>' value='0' <?= ($notifSettings[$module::PROJ_PROD_SETTING] == "0" ? "checked" : "") ?>>No<br/><input type='radio' name='<?= $module::PROJ_PROD_SETTING ?>' value='1' <?= ($notifSettings[$module::PROJ_PROD_SETTING] == "1" ? "checked" : "") ?>>Yes</div></div>";
        }
        else if (selectValue == "3") {
            divHTML = generateFieldList();
        }
        else if (selectValue == "4") {
            divHTML = "<div class='col-md-12'><span><input type='checkbox' name='<?= $module::USER_NEW_SETTING ?>' value='1' <?= ($notifSettings[$module::USER_NEW_SETTING] == "1" ? "checked" : "") ?> /></span><span>Trigger notification when a new user is added to the project</span></div><div class='col-md-12'><span><input type='checkbox' name='<?= $module::USER_EDIT_SETTING ?>' value='1' <?= ($notifSettings[$module::USER_EDIT_SETTING] == "1" ? "checked" : "") ?> /></span><span>Trigger notification when a user's rights are edited</span></div>";
        }
        else if (selectValue == "5") {
            divHTML = generateRepeatableFieldList();
        }
        else if (selectValue == "6") {
            divHTML = generateRepeatableFieldList();
        }
        else if (selectValue == "7") {
            divHTML = generateRepeatableFieldList();
            divHTML += "<div class='col-md-12' id='<?= $module::RECORD_COUNT_SETTING ?>' style='padding-top:10px;'><span style='display:inline-block;'>Records to Match to Trigger Notification</span><span style='display:inline-block;'><input type='text' name='record_count' value='<?= $notifSettings['record_count'] ?>' /></span></div>";
        }
        divHTML += "<div class='col-md-12' style='padding-top:10px;'><span style='display:inline-block;'>Days Until Notification is Past Due (leave blank if not applicable)</span><span style='display:inline-block;'><input type='text' id='<?= $module::PASTDUE_SETTING ?>' name='<?= $module::PASTDUE_SETTING ?>' /></span></div>";
        //console.log(divHTML);
        $('#'+destination).html(divHTML).css({'width':'auto','height':'auto'});
        loadNotifSettings();
    }
    function loadFieldOptions(select_field, destination, record_id, count) {
        var nameValue = select_field.value;
        var sourceName = select_field.name;
        var returnHTML = "";
        var fieldValueCount = 0;
        if (Object.keys(fieldValueList[nameValue]).length > 0) {
            returnHTML += "<table><tr>";
            for (var key in fieldValueList[nameValue]) {
                returnHTML += "<td><span><input type='checkbox' id='<?= $module::FIELD_VALUE_SETTING ?>_"+count+"_"+fieldValueCount+"' name='<?= $module::FIELD_VALUE_SETTING ?>_"+count+"[]' value='"+key+"' ";
                if (notificationSettings != null && jQuery.type(notificationSettings['<?= $module::FIELD_VALUE_SETTING ?>']) !== "undefined") {
                    if ($.inArray(key, notificationSettings['<?= $module::FIELD_VALUE_SETTING ?>'][count]) !== -1) {
                        returnHTML += "checked";
                    }
                }
                returnHTML += "/></span><span>"+fieldValueList[nameValue][key]+"</span></td>"
                fieldValueCount++;
                if (fieldValueCount % 3 == 0) {
                    returnHTML += "</tr><tr>";
                }
            }
            returnHTML += "</tr></table>";
        }
        else {
            returnHTML += "<span><input type='text' id='<?= $module::FIELD_VALUE_SETTING ?>_"+count+"_"+fieldValueCount+"' name='<?= $module::FIELD_VALUE_SETTING ?>_"+count+"' ";
            if (notificationSettings != null && jQuery.type(notificationSettings['<?= $module::FIELD_VALUE_SETTING ?>']) !== "undefined") {
                if ("0" in notificationSettings['<?= $module::FIELD_VALUE_SETTING ?>'][count]) {
                    returnHTML += "value='" + notificationSettings['<?= $module::FIELD_VALUE_SETTING ?>'][count]["0"] + "'";
                }
            }
            returnHTML += "/></span>";
        }
        $('#'+destination).html(returnHTML);
    }

    function generateRepeatableFieldList() {
        return "<div class='col-md-11' id='field_repeat'>"+generateFieldList()+"</div><div class='col-md-1'><button id='add_field' onclick='addNewDiv(\"field_repeat\",generateFieldList);' type='button'>Add Field</button></div>";
    }

    function generateRepeatableFormList() {
        return "<div class='col-md-11' id='form_repeat'>"+generateFormList()+"</div><div class='col-md-1'><button id='add_form' onclick='addNewDiv(\"form_repeat\",generateFormList);' type='button'>Add Form</button></div>";
    }

    function generateFieldList() {
        var count = getNewCount('fields_');
        var returnHTML = "<div id='fields_"+count+"'><table style='border: 1px solid'><tr style='background-color:lightblue;'><td><button type='button' onclick='removeDiv(\"fields_"+count+"\");'>X</button></td><td><div style='padding:3px;'><span>Field Name to Trigger Notification: </span><span><select style='width:350px;text-overflow: ellipsis;' onchange='loadFieldOptions(this,\"field_options_"+count+"\",\"<?=$recordID?>\",\""+count+"\");' id='<?= $module::FIELD_NAME_SETTING ?>_"+count+"' name='<?= $module::FIELD_NAME_SETTING ?>[]'><option value=''></option>";
        for (var key in projectFieldList) {
            returnHTML += "<option value='"+key+"'>("+key+") -- "+projectFieldList[key]+"</option>";
        }

        returnHTML += "</select></span></div></td></tr><tr><td></td><td><div id='field_options_"+count+"'></div></td></tr></table></div>";
        return returnHTML;
    }

    function generateFormList() {
        var count = getNewCount('forms_');
        var returnHTML = "<div id='forms_"+count+"'><table style='border: 1px solid'><tr><td><button type='button' onclick='removeDiv(\"forms_"+count+"\");'>X</button></td><td style='background-color:lightblue;'><span>Form to Monitor for New Fields (leave blank to monitor all forms): </span></td><td><span><select style='width:350px;text-overflow: ellipsis;' id='<?= $module::FORM_NAME_SETTING ?>_"+count+"' name='<?= $module::FORM_NAME_SETTING ?>[]'><option value=''></option>";

        for (var key in projectFormList) {
            returnHTML += "<option value='"+key+"'>"+projectFormList[key]+"</option>";
        }
        returnHTML += "</select></span></td></tr></table></div>";
        return returnHTML;
    }

    function addNewDiv(destination,functionCallback) {
        $('#'+destination).append(functionCallback());
    }

    function removeDiv(divID) {
        $('#'+divID).remove();
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
                if ($index > 0) {
                    echo "$('#add_field').trigger(\"click\");";
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
                            echo "addNewDiv('field_repeat',generateFieldList);";
                        }
                        echo "$(\"#".$module::FIELD_VALUE_SETTING."_".$index."_".$count."\").val('".$value."');";
                    }
                }
                $index++;
            }
            foreach ($notifSettings['forms_field'] as $count => $value) {
                if ($count > 0) {
                    echo "addNewDiv('form_repeat',generateFormList);";
                }
                echo "$(\"#".$module::FORM_NAME_SETTING."_".$count."\").val('".$value."');";
            }
        ?>
        $('#<?= $module::PASTDUE_SETTING ?>').val('<?= $notifSettings[$module::PASTDUE_SETTING] ?>');
    }

    $(document).ready(function() {
        $('#<?=$module->getProjectSetting("notif-type")?>').change();
        //loadNotifSettings();
    });
</script>
<?php
function cleanJavaString($junkstring) {
    return trim(preg_replace('/\s+/',' ',str_replace("'","\"",strip_tags($junkstring))));
}
?>