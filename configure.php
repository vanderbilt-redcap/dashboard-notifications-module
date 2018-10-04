<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 8/20/2018
 * Time: 2:59 PM
 */

include_once('base.php');
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.5/css/select2.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.5/js/select2.min.js"></script>

<?php
$projectID = $_GET['pid'];
$notifProjectID = $module->getProjectSetting("notif-project");
if ($projectID != "" && $notifProjectID != "") {
    $project = new \Project($projectID);
    $notifProject = new \Project($notifProjectID);
    /*echo "<pre>";
    print_r($existingNotifs);
    echo "</pre>";*/
    if (!empty($_POST)) {
        /*echo "<pre>";
        print_r($_POST);
        echo "</pre>";*/
        $overwrite = "overwrite";
        $recordID = "";
        if ($_POST['record_id'] != "") {
            $recordID = db_real_escape_string($_POST[$notifProject->table_pk]);
        }
        else {
            $recordID = $module->getAutoID($notifProjectID,$notifProject->firstEventId);
        }
        if ($recordID != "") {
            $saveData[$notifProject->table_pk] = $recordID;
            $notifType = db_real_escape_string($_POST[$module->getProjectSetting("notif-type")]);
            $saveData[$module->getProjectSetting("project-field")] = $projectID;
            $saveData[$module->getProjectSetting("notif-name")] = db_real_escape_string($_POST[$module->getProjectSetting("notif-name")]);
            $saveData[$module->getProjectSetting("notif-alert")] = db_real_escape_string($_POST[$module->getProjectSetting("notif-alert")]);
            $saveData[$module->getProjectSetting("notif-class")] = db_real_escape_string($_POST[$module->getProjectSetting("notif-class")]);
            $saveData[$module->getProjectSetting("notif-type")] = $notifType;
            $saveData[$module->getProjectSetting("notif-active")] = db_real_escape_string($_POST[$module->getProjectSetting("notif-active")]);
            //$saveData[$module->getProjectSetting("role-list")] = db_real_escape_string(implode(",",$_POST[$module->getProjectSetting("role-list")]));
            //$saveData[$module->getProjectSetting("role-resolve")] = db_real_escape_string(implode(",",$_POST[$module->getProjectSetting("role-resolve")]));
            $saveData[$module->getProjectSetting("role-list")] = json_encode(array("roles"=>$module->cleanArray(array_values($_POST[$module::ROLES_RECEIVE."_".$module::ROLES_LIST])),"fields"=>$module->cleanArray(array_values($_POST[$module::ROLES_RECEIVE]))));
            $saveData[$module->getProjectSetting("role-resolve")] = json_encode(array("roles"=>$module->cleanArray(array_values($_POST[$module::ROLES_RESOLVE."_".$module::ROLES_LIST])),"fields"=>$module->cleanArray(array_values($_POST[$module::ROLES_RESOLVE]))));
            $notifSettings = array();
            switch ($notifType) {
                case "0":
                    break;
                case "1":
                    $notifSettings[$module::PROJ_PROD_SETTING] = db_real_escape_string($_POST[$module::PROJ_PROD_SETTING]);
                    break;
                case "2":
                    foreach ($_POST[$module::FORM_NAME_SETTING] as $index => $formName) {
                        $notifSettings[$module::FORM_FIELD_SETTING][$index] = db_real_escape_string($formName);
                    }
                    $notifSettings[$module::PROJ_PROD_SETTING] = db_real_escape_string($_POST[$module::PROJ_PROD_SETTING]);
                    break;
                case "3":
                    foreach ($_POST[$module::FIELD_NAME_SETTING] as $index => $fieldName) {
                        //$notifSettings[$module::FIELD_NAME_SETTING][$index] = db_real_escape_string($fieldName);
                        $notifSettings[$module::FIELD_NAME_SETTING][db_real_escape_string($fieldName)] = [];
                    }
                    break;
                case "4":
                    $notifSettings[$module::USER_NEW_SETTING] = db_real_escape_string($_POST[$module::USER_NEW_SETTING]);
                    $notifSettings[$module::USER_EDIT_SETTING] = db_real_escape_string($_POST[$module::USER_EDIT_SETTING]);
                    break;
                case "5":
                    foreach ($_POST[$module::FIELD_NAME_SETTING] as $index => $fieldName) {
                        /*$notifSettings[$module::FIELD_NAME_SETTING][$index] = db_real_escape_string($fieldName);
                        $notifSettings[$module::FIELD_VALUE_SETTING][$index] = array_map('db_real_escape_string', (is_array($_POST[$module::FIELD_VALUE_SETTING.'_'.$index]) ? $_POST[$module::FIELD_VALUE_SETTING.'_'.$index] : array($_POST[$module::FIELD_VALUE_SETTING.'_'.$index])));*/
                        $notifSettings[$module::FIELD_NAME_SETTING][db_real_escape_string($fieldName)] = array_map('db_real_escape_string', (is_array($_POST[$module::FIELD_VALUE_SETTING.'_'.$index]) ? $_POST[$module::FIELD_VALUE_SETTING.'_'.$index] : array($_POST[$module::FIELD_VALUE_SETTING.'_'.$index])));
                    }
                    break;
                case "6":
                    foreach ($_POST[$module::FIELD_NAME_SETTING] as $index => $fieldName) {
                        /*$notifSettings[$module::FIELD_NAME_SETTING][$index] = db_real_escape_string($fieldName);
                        $notifSettings[$module::FIELD_VALUE_SETTING][$index] = array_map('db_real_escape_string', (is_array($_POST[$module::FIELD_VALUE_SETTING.'_'.$index]) ? $_POST[$module::FIELD_VALUE_SETTING.'_'.$index] : array($_POST[$module::FIELD_VALUE_SETTING.'_'.$index])));*/
                        $notifSettings[$module::FIELD_NAME_SETTING][db_real_escape_string($fieldName)] = array_map('db_real_escape_string', (is_array($_POST[$module::FIELD_VALUE_SETTING.'_'.$index]) ? $_POST[$module::FIELD_VALUE_SETTING.'_'.$index] : array($_POST[$module::FIELD_VALUE_SETTING.'_'.$index])));
                    }
                    break;
                case "7":
                    foreach ($_POST[$module::FIELD_NAME_SETTING] as $index => $fieldName) {
                        /*$notifSettings[$module::FIELD_NAME_SETTING][$index] = db_real_escape_string($fieldName);
                        $notifSettings[$module::FIELD_VALUE_SETTING][$index] = array_map('db_real_escape_string', (is_array($_POST[$module::FIELD_VALUE_SETTING.'_'.$index]) ? $_POST[$module::FIELD_VALUE_SETTING.'_'.$index] : array($_POST[$module::FIELD_VALUE_SETTING.'_'.$index])));*/
                        $notifSettings[$module::FIELD_NAME_SETTING][db_real_escape_string($fieldName)] = array_map('db_real_escape_string', (is_array($_POST[$module::FIELD_VALUE_SETTING.'_'.$index]) ? $_POST[$module::FIELD_VALUE_SETTING.'_'.$index] : array($_POST[$module::FIELD_VALUE_SETTING.'_'.$index])));
                    }
                    $notifSettings[$module::RECORD_COUNT_SETTING] = db_real_escape_string($_POST[$module::RECORD_COUNT_SETTING]);
                    break;
            }
            $notifSettings[$module::PASTDUE_SETTING] = db_real_escape_string($_POST[$module::PASTDUE_SETTING]);
            $saveData[$module->getProjectSetting("access-json")] = json_encode($notifSettings);
            /*echo "<pre>";
            print_r($saveData);
            echo "</pre>";*/
            $recordsObject = new \Records;
            $recordsObject->saveData($notifProjectID, 'array', [$saveData[$notifProject->table_pk] => [$notifProject->firstEventId => $saveData]],$overwrite);
            if (method_exists($recordsObject,'addRecordToRecordListCache')) {
                $recordsObject->addRecordToRecordListCache($notifProjectID, $saveData[$notifProject->table_pk], $notifProject->firstArmId);
            }
        }
    }
    $existingNotifs = $module->getNotifications($notifProjectID,$projectID);

    echo "<div class='col-md-12'>
        <form method='post' action='".$module->getUrl('configure.php')."'>
            <div id='notif_container' class='col-md-2 bg-info' style='min-width:150px;padding:10px;'>
                <div style='font-weight:bold;border-bottom:4px solid;'>Select or Create a New Notification</div>
                <div>
                    Select a Notification<br/>
                    <select id='notif_select' onchange='hideShowNewNotif(this,\"new_role\");' style='width:125px;text-overflow: ellipsis;'></select>
                </div>
                <div id='new_role' style='display:none;'>
                    Name for New Notifcation<br/>
                    <input style='width:125px;' type='text' id='new_notif_name'/>
                </div>
                <div><button id='submit_role' type='button' onclick='loadRole(\"notif_select\",\"new_notif_name\",\"notif_information\");'>Apply</button></div>
            </div>
            <div class='col-md-10' id='notif_information' style='display:none;'>
            </div>
        </form>
    </div>";
 ?>
    <style>
        #notif_container {
            display:inline-block;
            min-height:500px;
            text-align:center;
        }

        #notif_container > div {
            padding: 10px 0 10px 0;
            width:100%;
        }

        #notif_information {
            min-height:500px;
        }
        #accordion .ui-accordion-header {
            background-color:lightgreen;
        }
    </style>

    <script>
        $(document).ready(function (){
            <?php
            foreach ($existingNotifs as $recordID => $eventData) {
                foreach ($eventData as $event_id => $recordData) {
                    if ($event_id == "repeat_instances") continue;
                    echo "$('#notif_select').append($('<option></option>').attr('value','".$recordID."').text('".$recordData[$module->getProjectSetting('notif-name')]."'));";
                }
            }
            ?>
            $('#notif_select').append($('<option></option>').attr('value','new').text('New Notification')).change();
        });
        function loadRole(notif,newName,destination) {
            var notifValue = $('#'+notif).val();
            var nameValue = $('#'+newName).val();
            $.ajax({
                url: '<?=$module->getUrl('ajax_notifications.php')?>',
                method: 'post',
                data: {
                    'notif_record': notifValue,
                    'new_name': nameValue
                },
                success: function (data) {
                    //console.log(data);
                    $('#'+destination).css('display','inline-block').html(data);
                    $('#accordion > div').accordion({ header: 'h3', collapsible: true, active: false });
                },
                error: function (errorThrown) {
                    console.log(errorThrown);
                }
            });
        }

        function hideShowNewNotif(select,target_id) {
            var selectValue = select.value;
            if (selectValue == 'new') {
                $('#'+target_id).show();
            }
            else {
                $('#'+target_id).hide().find('input').val('');
            }
        }
    </script>
<?php
}